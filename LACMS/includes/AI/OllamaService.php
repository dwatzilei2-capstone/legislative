<?php
declare(strict_types=1);

require_once __DIR__.'/AIServiceInterface.php';
require_once dirname(__DIR__,2).'/config/lacms_ai.php';

final class OllamaService implements AIServiceInterface
{
    public function healthCheck(): array
    {
        if(!LACMS_OLLAMA_ENABLED)return ['available'=>false,'model_installed'=>false,'models'=>[],'message'=>'LACMS AI is disabled.'];
        $r=$this->request('GET','/api/tags');
        if(!$r['success'])return ['available'=>false,'model_installed'=>false,'models'=>[],'message'=>'Cannot connect to local Ollama: '.($r['error']??'Connection refused.')];
        $data=json_decode((string)$r['body'],true);$models=[];$installed=false;
        foreach(($data['models']??[]) as $m){$name=(string)($m['name']??$m['model']??'');if($name==='')continue;$models[]=$name;if($name===LACMS_OLLAMA_MODEL)$installed=true;}
        return ['available'=>true,'model_installed'=>$installed,'models'=>$models,'configured_model'=>LACMS_OLLAMA_MODEL,
            'message'=>$installed?'Ollama is online and the configured model is ready.':'Ollama is online, but '.LACMS_OLLAMA_MODEL.' is not installed.'];
    }

    public function analyzePriority(array $c): array
    {
        $prompt="You are a legislative agenda planning analyst for a Philippine city council. Assess priority using only the supplied facts.\n".
            "Reference: ".($c['reference_number']??'')."\nTitle: ".($c['title']??'')."\nSummary: ".($c['summary']??'').
            "\nCurrent status: ".($c['current_status']??'')."\nCurrent priority: ".($c['priority_level']??'Normal').
            "\nCategory: ".($c['legislative_category']??'')."\nPolicy area: ".($c['policy_area']??'').
            "\nReturn JSON only: {\"recommended_priority\":\"Low|Normal|High|Urgent\",\"priority_score\":0,\"public_impact_score\":0,".
            "\"time_sensitivity_score\":0,\"readiness_score\":0,\"strategic_alignment_score\":0,\"rationale\":\"...\",\"agenda_recommendation\":\"...\",\"risk_factors\":[\"...\"]}. Scores are 0-100.";
        return $this->generate($prompt,'Priority',$c,[
            'recommended_priority'=>'Normal','priority_score'=>50,'public_impact_score'=>50,'time_sensitivity_score'=>50,
            'readiness_score'=>50,'strategic_alignment_score'=>50,'rationale'=>'Manual evaluation is required.',
            'agenda_recommendation'=>'Review during the next agenda planning session.','risk_factors'=>[]
        ]);
    }

    public function adviseSchedule(array $c): array
    {
        $nearby=json_encode($c['conflicting_events']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $prompt="You are a legislative calendar coordinator. Analyze this scheduled event and the deterministic overlap list. Do not claim a conflict unless it appears in the supplied list.\n".
            "Event: ".($c['event_reference']??'').' - '.($c['title']??'')."\nType: ".($c['event_type']??'')."\nStart: ".($c['start_datetime']??'')."\nEnd: ".($c['end_datetime']??'').
            "\nVenue: ".($c['venue']??'')."\nOverlap list: ".$nearby.
            "\nReturn JSON only: {\"conflict_level\":\"Clear|Low|Medium|High\",\"schedule_summary\":\"...\",\"identified_conflicts\":[\"...\"],\"recommended_actions\":[\"...\"],\"participant_considerations\":[\"...\"],\"recommended_time_note\":\"...\"}.";
        return $this->generate($prompt,'Calendar',$c,[
            'conflict_level'=>empty($c['conflicting_events'])?'Clear':'High','schedule_summary'=>'Review the event schedule.',
            'identified_conflicts'=>[],'recommended_actions'=>[],'participant_considerations'=>[],'recommended_time_note'=>''
        ]);
    }

    public function draftSynchronizationBrief(array $c): array
    {
        $prompt="You are an executive-legislative coordination officer for a Philippine city government. Compare the recorded positions neutrally and suggest coordination steps. Do not decide policy.\n".
            "Reference: ".($c['sync_reference']??'')."\nTitle: ".($c['title']??'')."\nType: ".($c['sync_type']??'')."\nPriority: ".($c['priority_level']??'').
            "\nExecutive position: ".($c['executive_position']??'Not recorded')."\nLegislative position: ".($c['legislative_position']??'Not recorded').
            "\nExisting resolution notes: ".($c['resolution_notes']??'').
            "\nReturn JSON only: {\"alignment_status\":\"Aligned|Partially Aligned|Needs Coordination|Insufficient Information\",\"executive_summary\":\"...\",\"common_ground\":[\"...\"],\"open_issues\":[\"...\"],\"recommended_actions\":[{\"title\":\"...\",\"description\":\"...\",\"priority\":\"Low|Normal|High|Urgent\",\"days_due\":7}],\"proposed_resolution_note\":\"...\"}.";
        return $this->generate($prompt,'Synchronization',$c,[
            'alignment_status'=>'Insufficient Information','executive_summary'=>'More position details are required.',
            'common_ground'=>[],'open_issues'=>[],'recommended_actions'=>[],'proposed_resolution_note'=>''
        ]);
    }

    private function generate(string $prompt,string $sourceType,array $context,array $fallback): array
    {
        $started=microtime(true);$r=$this->request('POST','/api/generate',[
            'model'=>LACMS_OLLAMA_MODEL,'prompt'=>$prompt,'stream'=>false,'format'=>'json','keep_alive'=>LACMS_AI_KEEP_ALIVE,
            'options'=>['temperature'=>0.2,'num_predict'=>LACMS_AI_MAX_TOKENS]
        ]);$duration=(int)round((microtime(true)-$started)*1000);
        if(!$r['success']){$this->recordRequest($sourceType,$context,$prompt,null,$r,$duration);return ['success'=>false,'error'=>'Ollama communication error: '.($r['error']??'Unknown error')];}
        $outer=json_decode((string)$r['body'],true);$raw=(string)($outer['response']??'');$data=$this->decodeJson($raw);
        if(!is_array($data)){$this->recordRequest($sourceType,$context,$prompt,$r['body']??null,['http_status'=>$r['http_status']??null,'error'=>'Invalid JSON'], $duration);return ['success'=>false,'error'=>'Ollama returned an invalid JSON response.'];}
        $result=array_merge($fallback,$data,['success'=>true,'model'=>LACMS_OLLAMA_MODEL]);
        foreach(['priority_score','public_impact_score','time_sensitivity_score','readiness_score','strategic_alignment_score'] as $key)if(isset($result[$key]))$result[$key]=max(0,min(100,(int)$result[$key]));
        if(isset($result['recommended_priority'])&&!in_array($result['recommended_priority'],['Low','Normal','High','Urgent'],true))$result['recommended_priority']='Normal';
        foreach(['risk_factors','identified_conflicts','recommended_actions','participant_considerations','common_ground','open_issues'] as $key)if(isset($result[$key])&&!is_array($result[$key]))$result[$key]=[];
        $this->recordRequest($sourceType,$context,$prompt,$r['body']??null,$r,$duration);$this->recordAnalysis($sourceType,$context,$result,$duration);
        return $result;
    }

    private function request(string $method,string $path,?array $payload=null): array
    {
        if(!function_exists('curl_init'))return ['success'=>false,'error'=>'PHP cURL extension is not enabled.'];
        $ch=curl_init(rtrim(LACMS_OLLAMA_BASE_URL,'/').$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>LACMS_AI_CONNECT_TIMEOUT,CURLOPT_TIMEOUT=>LACMS_OLLAMA_TIMEOUT,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json']]);
        if($method==='POST'){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
        $body=curl_exec($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($body===false||$status<200||$status>=300){$this->log($error?:'Ollama HTTP '.$status);return ['success'=>false,'error'=>$error?:'Ollama HTTP '.$status,'http_status'=>$status];}
        return ['success'=>true,'body'=>(string)$body,'http_status'=>$status];
    }

    private function decodeJson(string $text): ?array
    {
        $d=json_decode(trim($text),true);if(is_array($d))return $d;
        if(preg_match('/\{[\s\S]*\}/',$text,$m)){$d=json_decode($m[0],true);return is_array($d)?$d:null;}return null;
    }

    private function recordRequest(string $type,array $c,string $prompt,?string $response,array $transport,int $duration): void
    {
        try{if(!function_exists('db'))return;db()->prepare('INSERT INTO lacms_ai_request_logs (source_type,source_id,provider,model_used,request_prompt,response_payload,http_status,success,error_message,duration_ms,created_by,created_at) VALUES (:type,:id,"ollama",:model,:prompt,:response,:http,:success,:error,:duration,:user,NOW())')->execute([
            ':type'=>$type,':id'=>(int)($c['id']??0),':model'=>LACMS_OLLAMA_MODEL,':prompt'=>$prompt,':response'=>$response,
            ':http'=>$transport['http_status']??null,':success'=>!empty($transport['success'])?1:0,':error'=>$transport['error']??null,
            ':duration'=>$duration,':user'=>function_exists('currentUserId')?currentUserId():null]);}catch(Throwable $e){$this->log('Request log failed: '.$e->getMessage());}
    }

    private function recordAnalysis(string $type,array $c,array $result,int $duration): void
    {
        try{db()->prepare('INSERT INTO lacms_ai_analysis (source_type,source_id,reference_number,analysis_type,model_used,result_json,review_status,created_by,duration_ms,created_at) VALUES (:source,:id,:reference,:analysis,:model,:result,"Advisory",:user,:duration,NOW())')->execute([
            ':source'=>$type,':id'=>(int)($c['id']??0),':reference'=>$c['reference_number']??$c['event_reference']??$c['sync_reference']??null,
            ':analysis'=>strtolower($type).'_analysis',':model'=>LACMS_OLLAMA_MODEL,':result'=>json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ':user'=>function_exists('currentUserId')?currentUserId():null,':duration'=>$duration]);}catch(Throwable $e){$this->log('Analysis log failed: '.$e->getMessage());}
    }

    private function log(string $message): void {@file_put_contents(LACMS_AI_LOG_FILE,'['.date('Y-m-d H:i:s').'] '.$message.PHP_EOL,FILE_APPEND);}
}
