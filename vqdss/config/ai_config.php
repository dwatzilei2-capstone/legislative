<?php
declare(strict_types=1);

/**
 * config/ai_config.php
 * Ollama AI configuration for VQDSS (Voting, Quorum, and Decision Support System).
 *
 * Scope:
 * - Decision statement and legal basis drafting
 * - Implementation action plan suggestions for executive departments
 * - Legislative integrity audit review & executive summaries
 * - Statutory voting threshold & parliamentary procedure advising
 * - Recount justification drafting
 *
 * All AI operations run on-premises via local Ollama (Philippine DPA compliant).
 */

if (!defined('AI_ENABLED')) define('AI_ENABLED', true);
if (!defined('AI_PROVIDER')) define('AI_PROVIDER', 'ollama');
if (!defined('OLLAMA_BASE_URL')) define('OLLAMA_BASE_URL', 'http://127.0.0.1:11434');
if (!defined('OLLAMA_MODEL')) define('OLLAMA_MODEL', 'llama3.2:1b');
if (!defined('AI_CONNECT_TIMEOUT_SECONDS')) define('AI_CONNECT_TIMEOUT_SECONDS', 5);
if (!defined('AI_REQUEST_TIMEOUT_SECONDS')) define('AI_REQUEST_TIMEOUT_SECONDS', 120);
if (!defined('AI_MAX_RESPONSE_TOKENS')) define('AI_MAX_RESPONSE_TOKENS', 750);
if (!defined('AI_MODEL_KEEP_ALIVE_MINUTES')) define('AI_MODEL_KEEP_ALIVE_MINUTES', 30);
if (!defined('AI_LOG_FILE')) define('AI_LOG_FILE', dirname(__DIR__) . '/logs/ai.log');
