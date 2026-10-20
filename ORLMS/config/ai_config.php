<?php
declare(strict_types=1);

/** Local Ollama configuration for ORLMS. */
if (!defined('ORLMS_AI_ENABLED')) define('ORLMS_AI_ENABLED', true);
if (!defined('ORLMS_OLLAMA_BASE_URL')) define('ORLMS_OLLAMA_BASE_URL', 'http://127.0.0.1:11434');
if (!defined('ORLMS_OLLAMA_MODEL')) define('ORLMS_OLLAMA_MODEL', 'llama3.2:1b');
if (!defined('ORLMS_AI_CONNECT_TIMEOUT')) define('ORLMS_AI_CONNECT_TIMEOUT', 5);
if (!defined('ORLMS_AI_REQUEST_TIMEOUT')) define('ORLMS_AI_REQUEST_TIMEOUT', 120);
if (!defined('ORLMS_AI_MAX_TOKENS')) define('ORLMS_AI_MAX_TOKENS', 800);
if (!defined('ORLMS_AI_KEEP_ALIVE')) define('ORLMS_AI_KEEP_ALIVE', '30m');
if (!defined('ORLMS_AI_LOG_FILE')) define('ORLMS_AI_LOG_FILE', dirname(__DIR__) . '/logs/ai.log');
