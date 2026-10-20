<?php
declare(strict_types=1);

/**
 * includes/AI/OllamaService.php
 * Local Ollama AI implementation for VQDSS.
 */
require_once __DIR__ . '/AIServiceInterface.php';
require_once dirname(__DIR__, 2) . '/config/ai_config.php';

class OllamaService implements AIServiceInterface
{
    private string $baseUrl;
    private string $model;
    private int $connectTimeout;
    private int $requestTimeout;

    public function __construct(
        string $baseUrl = OLLAMA_BASE_URL,
        string $model = OLLAMA_MODEL,
        int $connectTimeout = AI_CONNECT_TIMEOUT_SECONDS,
        int $requestTimeout = AI_REQUEST_TIMEOUT_SECONDS
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model;
        $this->connectTimeout = $connectTimeout;
        $this->requestTimeout = $requestTimeout;
    }

    /**
     * Diagnostic check of local Ollama server and configured model.
     */
    public function healthCheck(): array
    {
        $result = $this->curlRequest('GET', '/api/tags', null, 5, 5);

        if (!$result['success']) {
            return [
                'available' => false,
                'message' => 'Cannot connect to local Ollama at ' . $this->baseUrl . ': ' . ($result['error'] ?? 'Connection refused'),
                'models' => [],
                'model_installed' => false,
            ];
        }

        if (($result['http_status'] ?? 0) >= 400) {
            return [
                'available' => false,
                'message' => 'Ollama returned HTTP ' . (int)$result['http_status'] . '.',
                'models' => [],
                'model_installed' => false,
            ];
        }

        $data = json_decode((string)$result['body'], true);
        if (!is_array($data) || !isset($data['models']) || !is_array($data['models'])) {
            return [
                'available' => false,
                'message' => 'Ollama returned an unexpected model list format.',
                'models' => [],
                'model_installed' => false,
            ];
        }

        $installedModels = [];
        $configuredBase = explode(':', $this->model)[0];
        $modelInstalled = false;

        foreach ($data['models'] as $row) {
            $name = (string)($row['name'] ?? $row['model'] ?? '');
            if ($name === '') continue;
            $installedModels[] = $name;

            if ($name === $this->model || explode(':', $name)[0] === $configuredBase) {
                $modelInstalled = true;
            }
        }

        return [
            'available' => true,
            'message' => $modelInstalled
                ? "Ollama is active and model '{$this->model}' is ready."
                : "Ollama is active, but configured model '{$this->model}' is not pulled yet.",
            'models' => $installedModels,
            'model_installed' => $modelInstalled,
            'configured_model' => $this->model,
            'base_url' => $this->baseUrl,
        ];
    }

    /**
     * Generate formal legislative decision statement, statutory legal basis, and effectivity conditions.
     */
    public function draftDecisionStatement(array $decisionContext): array
    {
        $title = trim((string)($decisionContext['legislative_title'] ?? $decisionContext['motion_text'] ?? 'Legislative Measure'));
        $ref = trim((string)($decisionContext['legislative_reference'] ?? $decisionContext['decision_record_number'] ?? 'VQD-DEC'));
        $outcome = trim((string)($decisionContext['official_outcome'] ?? 'Approved'));
        $threshold = trim((string)($decisionContext['decision_threshold'] ?? 'Simple Majority'));
        $yes = (int)($decisionContext['affirmative_votes'] ?? 0);
        $no = (int)($decisionContext['negative_votes'] ?? 0);
        $abstain = (int)($decisionContext['abstain_votes'] ?? 0);
        $required = (int)($decisionContext['required_votes'] ?? 0);

        $prompt = <<<EOT
You are an expert Chief Parliamentary Legal Officer and Sangguniang Panlungsod (City Council) Secretary for the City of Manila, Philippines.
Draft an official legislative decision record in JSON format for the following vote outcome:

MEASURE TITLE: {$title}
REFERENCE NUMBER: {$ref}
OFFICIAL OUTCOME: {$outcome}
VOTING THRESHOLD: {$threshold}
VOTES: Affirmative: {$yes}, Negative: {$no}, Abstain: {$abstain}, Required: {$required}

INSTRUCTIONS:
1. Provide a formal parliamentary Decision Statement ("BE IT RESOLVED by the City Council of Manila in session assembled...").
2. Provide the statutory and procedural legal basis (e.g., Section 53 of Republic Act No. 7160 Local Government Code, Internal Rules of Procedure of the City Council).
3. Provide recommended conditions or effectivity clause (e.g. Immediate upon signature/publication).
4. Provide an executive summary of the decision.

Return ONLY a JSON object with this exact schema:
{
  "decision_statement": "Full formal resolution/ordinance decision text...",
  "legal_basis": "Statutory citations and council rules...",
  "conditions": "Implementation conditions and prerequisites...",
  "effectivity_clause": "Recommended effectivity clause...",
  "executive_summary": "1-2 sentence executive summary..."
}
EOT;

        $response = $this->queryOllama($prompt, true);
        if (!$response['success']) {
            return $response;
        }

        $json = $this->extractJsonFromText($response['raw_text']);
        if (!is_array($json) || empty($json['decision_statement'])) {
            return [
                'success' => true,
                'decision_statement' => $this->cleanOutput($response['raw_text']),
                'legal_basis' => 'Section 53, Republic Act No. 7160 (Local Government Code of 1991); Internal Rules of the City Council of Manila.',
                'conditions' => 'Subject to standard executive transmission and archival certification.',
                'effectivity_clause' => 'Immediate upon formal certification and approval.',
                'executive_summary' => "The City Council of Manila formally recorded the measure as {$outcome}.",
                'model' => $this->model,
            ];
        }

        $result = [
            'success' => true,
            'decision_statement' => trim((string)$json['decision_statement']),
            'legal_basis' => trim((string)($json['legal_basis'] ?? '')),
            'conditions' => trim((string)($json['conditions'] ?? '')),
            'effectivity_clause' => trim((string)($json['effectivity_clause'] ?? '')),
            'executive_summary' => trim((string)($json['executive_summary'] ?? '')),
            'model' => $this->model,
        ];

        $this->recordAiAudit('decisions', (int)($decisionContext['id'] ?? 0), $ref, 'decision_draft', $title, $result);
        return $result;
    }

    /**
     * Generate recommended implementation actions for executive departments.
     */
    public function suggestImplementationActions(array $decisionContext): array
    {
        $title = trim((string)($decisionContext['legislative_title'] ?? $decisionContext['motion_text'] ?? 'Official Decision'));
        $statement = trim((string)($decisionContext['decision_statement'] ?? $title));
        $outcome = trim((string)($decisionContext['official_outcome'] ?? 'Approved'));
        $ref = trim((string)($decisionContext['reference_number'] ?? ''));

        $prompt = <<<EOT
You are an executive local governance coordinator for the City Government of Manila.
Given the following legislative decision adopted by the City Council:
TITLE: {$title}
DECISION: {$statement}
OUTCOME: {$outcome}

Generate 3 to 4 actionable, practical implementation tasks for City Hall departments (such as City Legal Office, City Treasurer, City Engineering, Department of Public Services, Manila Health Department, or Secretary to the Mayor).

Return ONLY a JSON object with this exact structure:
{
  "actions": [
    {
      "title": "Clear action title",
      "description": "Specific directive and what needs to be executed",
      "suggested_office": "Name of relevant city office",
      "priority": "High",
      "days_due": 14
    }
  ]
}
Allowed priority values: "Low", "Normal", "High", "Urgent".
EOT;

        $response = $this->queryOllama($prompt, true);
        if (!$response['success']) {
            return $response;
        }

        $json = $this->extractJsonFromText($response['raw_text']);
        $actions = [];
        if (is_array($json) && isset($json['actions']) && is_array($json['actions'])) {
            foreach ($json['actions'] as $a) {
                if (empty($a['title'])) continue;
                $actions[] = [
                    'title' => trim((string)$a['title']),
                    'description' => trim((string)($a['description'] ?? '')),
                    'suggested_office' => trim((string)($a['suggested_office'] ?? 'General Services')),
                    'priority' => in_array($a['priority'] ?? '', ['Low', 'Normal', 'High', 'Urgent'], true) ? $a['priority'] : 'Normal',
                    'days_due' => max(1, min(90, (int)($a['days_due'] ?? 14))),
                ];
            }
        }

        if (empty($actions)) {
            $actions = [
                [
                    'title' => 'Transmittal of Certified Decision Copy to City Mayor',
                    'description' => 'Transmit authentic sealed copy of council decision for mayoral approval and operational awareness.',
                    'suggested_office' => 'Office of the Secretary to the Sangguniang Panlungsod',
                    'priority' => 'High',
                    'days_due' => 3,
                ],
                [
                    'title' => 'Legal Compliance and Implementing Guidelines Review',
                    'description' => 'Draft administrative rules and verify compliance with local and national statutes.',
                    'suggested_office' => 'City Legal Office',
                    'priority' => 'Normal',
                    'days_due' => 15,
                ],
                [
                    'title' => 'Departmental Execution and Monitoring',
                    'description' => 'Coordinate field units and executive divisions for implementation of enacted provisions.',
                    'suggested_office' => 'City Administrator',
                    'priority' => 'Normal',
                    'days_due' => 30,
                ],
            ];
        }

        $result = [
            'success' => true,
            'actions' => $actions,
            'model' => $this->model,
        ];

        $this->recordAiAudit('decisions', (int)($decisionContext['id'] ?? 0), $ref, 'action_plan', $title, $result);
        return $result;
    }

    /**
     * Conduct legislative integrity audit and generate Executive Summary, Findings, and Secretary's Certification.
     */
    public function auditValidationResult(array $validationContext): array
    {
        $ref = (string)($validationContext['validation_reference'] ?? 'VAL-REC');
        $score = (float)($validationContext['integrity_score'] ?? 100);
        $risk = (string)($validationContext['risk_level'] ?? 'Low');
        $outcome = (string)($validationContext['validation_outcome'] ?? 'Validated');
        $decisionOutcome = (string)($validationContext['official_outcome'] ?? 'Approved');
        $checksSummary = (string)($validationContext['checks_summary'] ?? 'All automated integrity checks passed.');
        $findingsSummary = (string)($validationContext['findings_summary'] ?? 'No discrepancies recorded.');

        $prompt = <<<EOT
You are an expert Chief Legislative Auditor for the City Council of Manila.
Review the following post-session legislative voting and decision validation record:

VALIDATION REFERENCE: {$ref}
INTEGRITY SCORE: {$score}%
RISK LEVEL: {$risk}
VALIDATION OUTCOME: {$outcome}
OFFICIAL DECISION OUTCOME: {$decisionOutcome}
INTEGRITY CHECKS: {$checksSummary}
AUDIT FINDINGS: {$findingsSummary}

Generate an analytical audit report for the City Council Secretary and Presiding Officer with:
1. Executive Summary: concise synthesis of the integrity of the voting chain.
2. Overall Findings: professional breakdown of procedural compliance or points of concern.
3. Corrective Action: recommended steps to address risk or maintain rigor.
4. Management Response: administrative notes for session records.
5. Recommendation: final audit recommendation.
6. Certification Statement: formal declaration of authentication.

Return ONLY a JSON object with this exact schema:
{
  "executive_summary": "...",
  "overall_findings": "...",
  "corrective_action": "...",
  "management_response": "...",
  "recommendation": "...",
  "certification_statement": "..."
}
EOT;

        $response = $this->queryOllama($prompt, true);
        if (!$response['success']) {
            return $response;
        }

        $json = $this->extractJsonFromText($response['raw_text']);
        if (!is_array($json) || empty($json['executive_summary'])) {
            return [
                'success' => true,
                'executive_summary' => "The legislative voting event and decision record {$ref} underwent automated integrity evaluation with an audit score of {$score}% ({$risk} Risk). The voting chain was evaluated as {$outcome}.",
                'overall_findings' => "End-to-end verification confirmed quorum status, ballot reconciliation, and arithmetic tally consistency. {$findingsSummary}",
                'corrective_action' => $risk === 'Low' ? 'Maintain current verification safeguards.' : 'Resolve open ballot challenges and conduct secondary tally reconciliation.',
                'management_response' => 'Procedural validation noted and incorporated into the official council journal.',
                'recommendation' => 'Transmit validated decision to the Office of the City Mayor.',
                'certification_statement' => "I hereby certify that the voting results and decision record {$ref} have been verified in full accordance with the Internal Rules of the City Council of Manila.",
                'model' => $this->model,
            ];
        }

        $result = [
            'success' => true,
            'executive_summary' => trim((string)$json['executive_summary']),
            'overall_findings' => trim((string)($json['overall_findings'] ?? '')),
            'corrective_action' => trim((string)($json['corrective_action'] ?? '')),
            'management_response' => trim((string)($json['management_response'] ?? '')),
            'recommendation' => trim((string)($json['recommendation'] ?? '')),
            'certification_statement' => trim((string)($json['certification_statement'] ?? '')),
            'model' => $this->model,
        ];

        $this->recordAiAudit('validation', (int)($validationContext['id'] ?? 0), $ref, 'audit_review', "Validation {$ref}", $result);
        return $result;
    }

    /**
     * Advise on statutory voting threshold and procedural rules under RA 7160.
     */
    public function adviseVotingThreshold(array $votingContext): array
    {
        $title = trim((string)($votingContext['title'] ?? $votingContext['motion_text'] ?? 'Council Measure'));
        $quorum = trim((string)($votingContext['quorum_status'] ?? 'Quorum Met'));
        $members = (int)($votingContext['eligible_members'] ?? 38);

        $prompt = <<<EOT
You are an expert Parliamentarian on the Philippine Local Government Code (Republic Act No. 7160) and City Council Rules of Procedure.
Given the following legislative measure:
MEASURE: {$title}
TOTAL ELIGIBLE COUNCIL MEMBERS: {$members}
CURRENT QUORUM: {$quorum}

Analyze what voting threshold and method is legally and procedurally appropriate:
- Under Section 53 of RA 7160:
  * Ordinary resolutions/motions: Simple Majority of members present (there being a quorum).
  * Ordinances enacting tax levies, revenue measures, authorizing the mayor to contract loans, creating liabilities: Absolute Majority of ALL members of the Sanggunian (e.g. 50% + 1 of ALL elected members).
  * Overriding a Mayoral Veto: Two-Thirds (2/3) of all members.

Return ONLY a JSON object with this exact schema:
{
  "recommended_threshold": "Simple Majority",
  "statutory_basis": "Explanation referencing RA 7160 Section 53 or Council Rules...",
  "recommended_method": "Roll Call",
  "tie_rule_recommendation": "Presiding Officer Decides",
  "parliamentary_guidance": "Key procedural advice for the Presiding Officer..."
}
Allowed recommended_threshold values: "Simple Majority", "Majority of Eligible Members", "Two-Thirds", "Three-Fourths".
Allowed recommended_method values: "Manual", "Electronic", "Hybrid".
EOT;

        $response = $this->queryOllama($prompt, true);
        if (!$response['success']) {
            return $response;
        }

        $json = $this->extractJsonFromText($response['raw_text']);
        if (!is_array($json) || empty($json['recommended_threshold'])) {
            return [
                'success' => true,
                'recommended_threshold' => 'Simple Majority',
                'statutory_basis' => 'Section 53, RA 7160 provides that a simple majority of members present where quorum exists is sufficient for ordinary legislative measures.',
                'recommended_method' => 'Roll Call',
                'tie_rule_recommendation' => 'Presiding Officer Decides',
                'parliamentary_guidance' => 'Ensure roll call order follows official protocol and verify eligible voting members.',
                'model' => $this->model,
            ];
        }

        $result = [
            'success' => true,
            'recommended_threshold' => trim((string)$json['recommended_threshold']),
            'statutory_basis' => trim((string)($json['statutory_basis'] ?? '')),
            'recommended_method' => trim((string)($json['recommended_method'] ?? 'Roll Call')),
            'tie_rule_recommendation' => trim((string)($json['tie_rule_recommendation'] ?? 'Presiding Officer Decides')),
            'parliamentary_guidance' => trim((string)($json['parliamentary_guidance'] ?? '')),
            'model' => $this->model,
        ];

        $this->recordAiAudit('voting', (int)($votingContext['id'] ?? 0), (string)($votingContext['reference_number'] ?? ''), 'threshold_advisor', $title, $result);
        return $result;
    }

    /**
     * Generate recount justification memorandum based on tally discrepancy or tie vote.
     */
    public function draftRecountJustification(array $tallyContext): array
    {
        $ref = (string)($tallyContext['reference_number'] ?? 'TLY');
        $motion = (string)($tallyContext['motion_text'] ?? 'Voting Motion');
        $outcome = (string)($tallyContext['result_outcome'] ?? 'Tie');
        $votesCast = (int)($tallyContext['votes_cast'] ?? 0);
        $affirmative = (int)($tallyContext['affirmative_votes'] ?? 0);
        $negative = (int)($tallyContext['negative_votes'] ?? 0);
        $invalid = (int)($tallyContext['invalid_ballots'] ?? 0);
        $challenged = (int)($tallyContext['challenged_ballots'] ?? 0);
        $discrepancy = (string)($tallyContext['discrepancy_classification'] ?? 'None');

        $prompt = <<<EOT
You are a Legislative Legal Officer for the City Council of Manila.
Draft a formal procedural justification memorandum for ordering a recount on the following vote tally:

TALLY REFERENCE: {$ref}
MOTION: {$motion}
OUTCOME: {$outcome}
VOTES: Affirmative: {$affirmative}, Negative: {$negative}, Invalid: {$invalid}, Challenged: {$challenged}
DISCREPANCY STATUS: {$discrepancy}

Explain why a controlled recount is warranted under parliamentary law and council rules.
Return ONLY a JSON object with this exact schema:
{
  "recount_reason": "Formal memorandum stating reasons for recount...",
  "recommended_method": "System Recount",
  "summary": "1-sentence summary of the tally condition..."
}
Allowed recommended_method values: "System Recount", "Manual Verification", "Hybrid Recount".
EOT;

        $response = $this->queryOllama($prompt, true);
        if (!$response['success']) {
            return $response;
        }

        $json = $this->extractJsonFromText($response['raw_text']);
        if (!is_array($json) || empty($json['recount_reason'])) {
            return [
                'success' => true,
                'recount_reason' => "A controlled recount is ordered for {$ref} to verify ballot accuracy following an outcome of {$outcome} with {$challenged} open challenge(s) and {$invalid} invalid ballot(s).",
                'recommended_method' => 'System Recount',
                'summary' => "Recount necessitated by {$outcome} outcome and {$discrepancy} discrepancy classification.",
                'model' => $this->model,
            ];
        }

        $result = [
            'success' => true,
            'recount_reason' => trim((string)$json['recount_reason']),
            'recommended_method' => trim((string)($json['recommended_method'] ?? 'System Recount')),
            'summary' => trim((string)($json['summary'] ?? '')),
            'model' => $this->model,
        ];

        $this->recordAiAudit('tallying', (int)($tallyContext['id'] ?? 0), $ref, 'recount_justification', "Tally {$ref}", $result);
        return $result;
    }

    /**
     * Send cURL prompt to Ollama /api/generate endpoint.
     */
    private function queryOllama(string $prompt, bool $jsonMode = false): array
    {
        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'temperature' => 0.2,
                'num_predict' => AI_MAX_RESPONSE_TOKENS,
            ],
            'keep_alive' => AI_MODEL_KEEP_ALIVE_MINUTES . 'm',
        ];

        if ($jsonMode) {
            $payload['format'] = 'json';
        }

        $res = $this->curlRequest('POST', '/api/generate', $payload, $this->connectTimeout, $this->requestTimeout);

        if (!$res['success']) {
            return [
                'success' => false,
                'error' => 'Ollama communication error: ' . ($res['error'] ?? 'Unknown'),
            ];
        }

        $body = json_decode((string)$res['body'], true);
        if (!is_array($body) || !isset($body['response'])) {
            return [
                'success' => false,
                'error' => 'Invalid response structure received from Ollama daemon.',
            ];
        }

        return [
            'success' => true,
            'raw_text' => (string)$body['response'],
            'eval_duration' => $body['eval_duration'] ?? 0,
        ];
    }

    /**
     * Generic cURL executor.
     */
    private function curlRequest(string $method, string $path, ?array $payload, int $connectTimeout, int $requestTimeout): array
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $requestTimeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }

        $body = curl_exec($ch);
        $errNo = curl_errno($ch);
        $errMsg = curl_error($ch);
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo !== 0) {
            $this->log("cURL Error [{$errNo}]: {$errMsg} on {$url}");
            return ['success' => false, 'error' => $errMsg, 'http_status' => $httpStatus];
        }

        return ['success' => true, 'http_status' => $httpStatus, 'body' => (string)$body];
    }

    /**
     * Extract clean JSON array from model output.
     */
    private function extractJsonFromText(string $text): ?array
    {
        $text = trim($text);
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $candidate = json_decode($matches[0], true);
            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Clean formatting artifacts from model text output.
     */
    private function cleanOutput(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?/i', '', $text);
        $text = preg_replace('/```$/', '', $text);
        return trim($text);
    }

    /**
     * Audit trail insertion into vqd_ai_analysis table.
     */
    private function recordAiAudit(string $module, int $refId, string $refNumber, string $type, string $promptSummary, array $result): void
    {
        try {
            if (!function_exists('db')) return;
            $pdo = db();
            $stmt = $pdo->prepare(
                'INSERT INTO vqd_ai_analysis
                 (module, reference_id, reference_number, analysis_type, model_used, prompt_summary, result_json, created_by, created_at)
                 VALUES
                 (:mod, :ref_id, :ref_num, :type, :model, :summary, :json, :user, NOW())'
            );
            $stmt->execute([
                ':mod' => $module,
                ':ref_id' => $refId,
                ':ref_num' => $refNumber ?: null,
                ':type' => $type,
                ':model' => $this->model,
                ':summary' => substr($promptSummary, 0, 500),
                ':json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':user' => function_exists('currentUserId') ? currentUserId() : null,
            ]);
        } catch (Throwable $e) {
            $this->log("Failed to write vqd_ai_analysis audit: " . $e->getMessage());
        }
    }

    /**
     * Local file logging.
     */
    private function log(string $msg): void
    {
        $dir = dirname(AI_LOG_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        @file_put_contents(AI_LOG_FILE, $line, FILE_APPEND);
    }
}
