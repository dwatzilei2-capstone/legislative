<?php
declare(strict_types=1);

/*
 * LACMS local AI configuration.
 *
 * Ollama is optional. The reminder workflow still works when Ollama is
 * unavailable because LACMS falls back to deterministic approved templates.
 */
if(!defined('LACMS_OLLAMA_ENABLED')) {
    define('LACMS_OLLAMA_ENABLED', true);
}
if(!defined('LACMS_OLLAMA_URL')) {
    define('LACMS_OLLAMA_URL', 'http://127.0.0.1:11434/api/generate');
}
if(!defined('LACMS_OLLAMA_BASE_URL')) {
    define('LACMS_OLLAMA_BASE_URL', 'http://127.0.0.1:11434');
}
if(!defined('LACMS_OLLAMA_MODEL')) {
    define('LACMS_OLLAMA_MODEL', 'llama3.2:1b');
}
if(!defined('LACMS_OLLAMA_TIMEOUT')) {
    define('LACMS_OLLAMA_TIMEOUT', 120);
}
if(!defined('LACMS_AI_CONNECT_TIMEOUT')) define('LACMS_AI_CONNECT_TIMEOUT', 5);
if(!defined('LACMS_AI_MAX_TOKENS')) define('LACMS_AI_MAX_TOKENS', 800);
if(!defined('LACMS_AI_KEEP_ALIVE')) define('LACMS_AI_KEEP_ALIVE', '30m');
if(!defined('LACMS_AI_LOG_FILE')) define('LACMS_AI_LOG_FILE', dirname(__DIR__) . '/logs/ai.log');
