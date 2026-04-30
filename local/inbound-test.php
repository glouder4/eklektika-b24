<?php

/**
 * Самодиагностика входящего канала (сайт → B24): настройки, пути, запись в local/logs.
 *
 * Доступ:
 * - Веб: только администратор портала (сессия Bitrix).
 * - CLI: `php local/inbound-test.php` из корня сайта или с корректным DOCUMENT_ROOT.
 *
 * Параметры URL: `format=json|text` (по умолчанию json).
 * `handler_smoke=1` (только веб, админ): выполнить GET к `endpoint.php` → в inbound-b24.log попадёт
 * строка как у боевого обработчика: `site_requests_handler.reject.method_not_allowed`.
 *
 * CLI: `php local/inbound-test.php --handler-smoke URL` где URL — корень сайта, например `https://portal.example`
 *      (переменная окружения `INBOUND_TEST_BASE_URL` если URL не передан после флага).
 *
 * Не выводит значения sync_token / секретов — только длины и флаги.
 */

use OnlineService\Sync\FromSite\InboundSecurity;

$cliHandlerSmokeUrl = '';
if (PHP_SAPI === 'cli' && isset($argv) && is_array($argv)) {
    foreach ($argv as $i => $arg) {
        if ($arg === '--handler-smoke') {
            $cliHandlerSmokeUrl = isset($argv[$i + 1]) ? trim((string)$argv[$i + 1]) : '';
            break;
        }
    }
    if ($cliHandlerSmokeUrl === '') {
        $envBase = getenv('INBOUND_TEST_BASE_URL');
        if ($envBase !== false && trim((string)$envBase) !== '') {
            $cliHandlerSmokeUrl = trim((string)$envBase);
        }
    }
}

if (PHP_SAPI === 'cli') {
    if (empty($_SERVER['DOCUMENT_ROOT'])) {
        $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/..') ?: '';
    }
    if ($_SERVER['DOCUMENT_ROOT'] === '' || !is_dir($_SERVER['DOCUMENT_ROOT'] . '/bitrix')) {
        fwrite(STDERR, "inbound-test: cannot resolve DOCUMENT_ROOT (expected parent of /local next to /bitrix).\n");
        exit(1);
    }
} else {
    define('NO_KEEP_STATISTIC', true);
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
    global $USER;
    if (!is_object($USER) || !$USER->IsAuthorized() || !$USER->IsAdmin()) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden: admin only';
        return;
    }
}

if (PHP_SAPI === 'cli' && $cliHandlerSmokeUrl !== '') {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/bootstrap.php';

$format = 'json';
if (PHP_SAPI !== 'cli' && isset($_GET['format'])) {
    $f = strtolower((string)$_GET['format']);
    if ($f === 'text' || $f === 'json') {
        $format = $f;
    }
}

$root = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\');
$localDir = $root . '/local';
$logsDir = $localDir . '/logs';
$inboundLog = $logsDir . '/inbound-b24.log';
$settingsPath = $root . '/local/modules/yomerch.b24.siteconnector/site_sync_settings.local.php';
$endpointPath = $root . '/local/modules/yomerch.b24.inbound/endpoint.php';
$handlerPath = $root . '/local/modules/yomerch.b24.inbound/lib/site_requests_handler.php';

[$settings,] = InboundSecurity::loadInboundSettings($root);
$secret = InboundSecurity::resolveSecret($settings);

$secretPresent = $secret !== '';
$secretLen = $secretPresent ? strlen($secret) : 0;

$logsDirExists = is_dir($logsDir);
$logsWritable = $logsDirExists && is_writable($logsDir);
$canCreateLogsDir = !$logsDirExists && is_writable($localDir);

$writeProbe = false;
$writeProbeError = null;
$probeLine = date('Y-m-d H:i:s') . " [inbound-test] probe ok\n";
if ($logsDirExists || @mkdir($logsDir, 0775, true)) {
    $writeProbe = @file_put_contents($inboundLog, $probeLine, FILE_APPEND | LOCK_EX) !== false;
    if (!$writeProbe) {
        $writeProbeError = 'file_put_contents failed (check permissions on local/logs or SELinux)';
    }
} else {
    $writeProbeError = 'cannot create local/logs';
}

$report = [
    'ok' => $secretPresent && ($logsWritable || $writeProbe),
    'document_root' => $root,
    'php_sapi' => PHP_SAPI,
    'checks' => [
        'site_sync_settings_file_exists' => is_file($settingsPath),
        'site_sync_settings_readable' => is_readable($settingsPath),
        'sync_token_configured' => $secretPresent,
        'sync_token_length' => $secretLen,
        'inbound_endpoint_php_exists' => is_file($endpointPath),
        'site_requests_handler_exists' => is_file($handlerPath),
        'local_logs_dir_exists' => $logsDirExists,
        'local_logs_dir_writable' => $logsWritable || $canCreateLogsDir,
        'inbound_b24_log_write_probe' => $writeProbe,
        'inbound_b24_log_path' => $inboundLog,
    ],
    'hints' => [],
];

if (!$secretPresent) {
    $report['hints'][] = 'Секрет пуст: заполните sync_token (или inbound_secret) в site_sync_settings.local.php — иначе inbound вернёт 503 sync_misconfigured.';
}
if (!$writeProbe) {
    $report['hints'][] = 'Лог входящего канала: local/logs/inbound-b24.log. Если запись не удалась, на Linux проверьте /tmp/inbound-b24.log и права на local/logs для пользователя apache/nginx/php-fpm.';
    if ($writeProbeError !== null) {
        $report['hints'][] = $writeProbeError;
    }
}

$report['hints'][] = 'Реальный вызов crm.company.add через входящий канал: POST на /local/modules/yomerch.b24.inbound/endpoint.php с ACTION=CRM_METHOD, METHOD=crm.company.add и заголовком X-SYNC-TOKEN — не через стандартный /rest/.';
$report['hints'][] = 'Чтобы записать строку именно боевым `site_requests_handler`: добавьте `handler_smoke=1` к URL (GET→405→`site_requests_handler.reject.method_not_allowed` в inbound-b24.log).';

$report['handler_smoke'] = null;

$surfaceHandlerSmokeResult = static function (string $endpointUrl): array {
    $out = [
        'endpoint_url' => $endpointUrl,
        'http_status' => 0,
        'response_preview' => '',
        'ok' => false,
        'note' => 'Ожидание HTTP 405; в логе inbound-b24.log — событие site_requests_handler.reject.method_not_allowed',
    ];

    if (class_exists(\Bitrix\Main\Web\HttpClient::class) && class_exists(\Bitrix\Main\Loader::class) && \Bitrix\Main\Loader::includeModule('main')) {
        try {
            $client = new \Bitrix\Main\Web\HttpClient();
            if (method_exists($client, 'setTimeout')) {
                $client->setTimeout(8);
            }
            $client->setHeader('User-Agent', 'inbound-test.php handler_smoke');
            $body = $client->get($endpointUrl);
            $out['http_status'] = (int)$client->getStatus();
            if (is_string($body)) {
                $out['response_preview'] = strlen($body) > 500 ? substr($body, 0, 500) . '…' : $body;
            }
            $out['ok'] = $out['http_status'] === 405;
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
        }
    }

    if ($out['http_status'] === 0 && !$out['ok']) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'ignore_errors' => true,
                'header' => "User-Agent: inbound-test.php handler_smoke\r\n",
            ],
        ]);
        $body = @file_get_contents($endpointUrl, false, $ctx);
        if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
            if (preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
                $out['http_status'] = (int)$m[1];
            }
        }
        if (is_string($body) && $body !== '') {
            $out['response_preview'] = strlen($body) > 500 ? substr($body, 0, 500) . '…' : $body;
        }
        $out['ok'] = $out['http_status'] === 405;
        if ($body === false && $out['http_status'] === 0) {
            $out['error'] = trim(($out['error'] ?? '') . ' file_get_contents failed (loopback blocked? open ?handler_smoke=1 in browser).');
            $out['fallback'] = 'file_get_contents';
        }
    }

    return $out;
};

$endpointUriPath = '/local/modules/yomerch.b24.inbound/endpoint.php';

if (PHP_SAPI !== 'cli' && isset($_GET['handler_smoke']) && $_GET['handler_smoke'] !== '' && $_GET['handler_smoke'] !== '0') {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '') {
        $fullUrl = $scheme . '://' . $host . $endpointUriPath;
        $report['handler_smoke'] = $surfaceHandlerSmokeResult($fullUrl);
    } else {
        $report['handler_smoke'] = ['ok' => false, 'error' => 'HTTP_HOST empty'];
    }
}

if (PHP_SAPI === 'cli' && $cliHandlerSmokeUrl !== '') {
    $base = rtrim($cliHandlerSmokeUrl, '/');
    if (!preg_match('#^https?://#i', $base)) {
        $base = 'https://' . $base;
    }
    $fullUrl = $base . $endpointUriPath;
    $report['handler_smoke'] = $surfaceHandlerSmokeResult($fullUrl);
}

if (PHP_SAPI === 'cli' || $format === 'json') {
    if (PHP_SAPI !== 'cli') {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo "inbound-test (local/inbound-test.php)\n";
    echo 'document_root: ' . $report['document_root'] . "\n";
    echo 'overall ok: ' . ($report['ok'] ? 'yes' : 'no') . "\n\n";
    foreach ($report['checks'] as $k => $v) {
        $val = is_bool($v) ? ($v ? 'true' : 'false') : $v;
        echo $k . ': ' . $val . "\n";
    }
    echo "\nHints:\n";
    foreach ($report['hints'] as $h) {
        echo '- ' . $h . "\n";
    }
    if (is_array($report['handler_smoke'])) {
        echo "\nhandler_smoke:\n";
        foreach ($report['handler_smoke'] as $k => $v) {
            if (is_scalar($v) || $v === null) {
                echo $k . ': ' . json_encode($v, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    }
}
