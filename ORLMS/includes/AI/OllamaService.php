<?php
declare(strict_types=1);

require_once __DIR__ . '/AIServiceInterface.php';
require_once dirname(__DIR__, 2) . '/config/ai_config.php';

final class OllamaService implements AIServiceInterface
{
    public function healthCheck(): array
    {
        if (!ORLMS_AI_ENABLED) return ['available'=>false,'model_installed'=>false,'message'=>'ORLMS AI is disabled.','models'=>[]];
        $res=$this->request('GET','/api/tags');
        if (!$res['success']) return ['available'=>false,'model_installed'=>false,'message'=>'Cannot connect to local Ollama: '.($res['error']??'Connection refused.'),'models'=>[]];
        $body=json_decode((string)$res['body'],true);
        $models=[];$installed=false;
        foreach(($body['models']??[]) as $row){
            $name=(string)($row['name']??$row['model']??'');
            if($name==='')continue;
            $models[]=$name;
            if($name===ORLMS_OLLAMA_MODEL)$installed=true;
        }
        return ['available'=>true,'model_installed'=>$installed,'models'=>$models,'configured_model'=>ORLMS_OLLAMA_MODEL,
            'message'=>$installed?'Ollama is online and the configured model is ready.':'Ollama is online, but '.ORLMS_OLLAMA_MODEL.' is not installed.'];
    }

    public function classifyDocument(array $c): array
    {
        $prompt="You are a legislative records analyst for a Philippine city council. Analyze this proposed local measure without inventing facts.\n".
            "Reference: ".($c['reference_number']??'')."\nType: ".($c['item_type']??'')."\nTitle: ".($c['title']??'').
            "\nSummary: ".($c['summary']??'')."\nPurpose: ".($c['purpose']??'')."\nExplanatory note: ".($c['explanatory_note']??'').
            "\nBody text: ".mb_substr((string)($c['body_text']??''),0,10000)."\nLegal basis: ".($c['legal_basis']??'').
            "\nReturn JSON only using: {\"legislative_category\":\"...\",\"policy_area\":\"...\",\"keywords\":[\"...\"],".
            "\"recommended_priority\":\"Low|Normal|High|Urgent\",\"document_summary\":\"...\",\"classification_reason\":\"...\",".
            "\"review_flags\":[\"...\"],\"confidence_score\":0}. Confidence must be 0-100. Suggestions require human confirmation.";
        return $this->generateAndNormalize($prompt,'classification',$c,[
            'legislative_category'=>'General Legislation','policy_area'=>'General Governance','keywords'=>[],
            'recommended_priority'=>'Normal','document_summary'=>(string)($c['summary']??''),
            'classification_reason'=>'Review the measure manually before confirming its classification.','review_flags'=>[],
            'confidence_score'=>0
        ]);
    }

    public function reviewDraft(array $c): array
    {
        $prompt="You are a cautious legislative drafting assistant for a Philippine city council. Review the draft for clarity and completeness only. " .
            "Do not claim final legality and do not invent legal citations.\nReference: ".($c['reference_number']??'')."\nTitle: ".($c['title']??'').
            "\nSummary: ".($c['summary']??'')."\nPurpose: ".($c['purpose']??'')."\nBody: ".mb_substr((string)($c['body_text']??''),0,12000).
            "\nLegal basis supplied: ".($c['legal_basis']??'')."\nFiscal impact supplied: ".($c['fiscal_impact']??'').
            "\nReturn JSON only: {\"executive_summary\":\"...\",\"strengths\":[\"...\"],\"clarity_issues\":[\"...\"],".
            "\"missing_elements\":[\"...\"],\"legal_review_questions\":[\"...\"],\"recommended_edits\":[\"...\"],\"readiness\":\"Needs Revision|Ready for Committee Review\"}.";
        return $this->generateAndNormalize($prompt,'draft_review',$c,[
            'executive_summary'=>'AI review was unavailable; conduct the standard committee checklist review.',
            'strengths'=>[],'clarity_issues'=>[],'missing_elements'=>[],'legal_review_questions'=>[],
            'recommended_edits'=>[],'readiness'=>'Needs Revision'
        ]);
    }

    private function generateAndNormalize(string $prompt,string $type,array $context,array $fallback): array
    {
        $started=microtime(true);
        $res=$this->request('POST','/api/generate',[
            'model'=>ORLMS_OLLAMA_MODEL,'prompt'=>$prompt,'stream'=>false,'format'=>'json','keep_alive'=>ORLMS_AI_KEEP_ALIVE,
            'options'=>['temperature'=>0.2,'num_predict'=>ORLMS_AI_MAX_TOKENS]
        ]);
        if(!$res['success'])return ['success'=>false,'error'=>'Ollama communication error: '.($res['error']??'Unknown error')];
        $outer=json_decode((string)$res['body'],true);$raw=(string)($outer['response']??'');$data=$this->decodeJson($raw);
        if(!is_array($data))return ['success'=>false,'error'=>'Ollama returned an invalid JSON response.'];
        $result=array_merge($fallback,$data,['success'=>true,'model'=>ORLMS_OLLAMA_MODEL]);
        if(isset($result['recommended_priority'])&&!in_array($result['recommended_priority'],['Low','Normal','High','Urgent'],true))$result['recommended_priority']='Normal';
        if(isset($result['confidence_score']))$result['confidence_score']=max(0,min(100,(int)$result['confidence_score']));
        foreach(['keywords','review_flags','strengths','clarity_issues','missing_elements','legal_review_questions','recommended_edits'] as $key){
            if(isset($result[$key])&&!is_array($result[$key]))$result[$key]=[];
        }
        $this->recordAudit($type,$context,$prompt,$result,(int)round((microtime(true)-$started)*1000));
        return $result;
    }

    private function request(string $method,string $path,?array $payload=null): array
    {
        if(!function_exists('curl_init'))return ['success'=>false,'error'=>'PHP cURL extension is not enabled.'];
        $ch=curl_init(rtrim(ORLMS_OLLAMA_BASE_URL,'/').$path);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>ORLMS_AI_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT=>ORLMS_AI_REQUEST_TIMEOUT,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json']]);
        if($method==='POST'){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
        $body=curl_exec($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($body===false||$status<200||$status>=300){$this->log($error?:'Ollama HTTP '.$status);return ['success'=>false,'error'=>$error?:'Ollama HTTP '.$status,'http_status'=>$status];}
        return ['success'=>true,'body'=>(string)$body,'http_status'=>$status];
    }

    private function decodeJson(string $text): ?array
    {
        $data=json_decode(trim($text),true);if(is_array($data))return $data;
        if(preg_match('/\{[\s\S]*\}/',$text,$m)){ $data=json_decode($m[0],true); return is_array($data)?$data:null; }
        return null;
    }

    private function recordAudit(string $type,array $context,string $prompt,array $result,int $duration): void
    {
        try{
            if(!function_exists('db'))return;
            db()->prepare('INSERT INTO orlms_ai_analysis (legislative_item_id,analysis_type,model_used,prompt_summary,result_json,confidence_score,review_status,created_by,duration_ms,created_at) VALUES (:item,:type,:model,:prompt,:result,:confidence,"Pending Review",:user,:duration,NOW())')->execute([
                ':item'=>(int)($context['id']??0),':type'=>$type,':model'=>ORLMS_OLLAMA_MODEL,':prompt'=>mb_substr($prompt,0,1000),
                ':result'=>json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),':confidence'=>(int)($result['confidence_score']??0),
                ':user'=>function_exists('currentUserId')?currentUserId():null,':duration'=>$duration
            ]);
        }catch(Throwable $e){$this->log('AI audit write failed: '.$e->getMessage());}
    }

    private function log(string $message): void
    {
        @file_put_contents(ORLMS_AI_LOG_FILE,'['.date('Y-m-d H:i:s').'] '.$message.PHP_EOL,FILE_APPEND);
    }
}
