<?php

use OnlineService\Sync\UfMap;
use OnlineService\Sync\FromSite\InboundEndpoint;

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('CHK_EVENT', false);
define('BX_NO_ACCELERATOR_RESET', true);
 
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/bootstrap.php';

$ufCompanyRequisitesFile = UfMap::get('company.requisites_file');

$trace = \OnlineService\SyncTraceContext::resolve();
$traceId = $trace['trace_id'];
$effectiveRequestId = '';
$inboundTrace = static function (string $event, array $context = []) use (&$traceId): void {
    $context = \OnlineService\SyncTraceContext::appendToContext($context, [
        'trace_id' => $traceId,
        'correlation_id' => $traceId,
        'cutover_label' => \OnlineService\SyncTraceContext::CUTOVER_LABEL,
    ]);
    $line = date('Y-m-d H:i:s') . ' [trace] ' . $event . ' ';
    $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $line .= ($json !== false ? $json : '{"encode":"failed"}') . PHP_EOL;

    $roots = [];
    $push = static function (string $root) use (&$roots): void {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if ($root === '') {
            return;
        }
        foreach ($roots as $existing) {
            if ($existing === $root) {
                return;
            }
        }
        $roots[] = $root;
    };

    $push((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if (class_exists(\Bitrix\Main\Application::class)) {
        try {
            $push((string)\Bitrix\Main\Application::getDocumentRoot());
        } catch (\Throwable $e) {
        }
    }
    $push(dirname(__DIR__, 4));

    foreach ($roots as $root) {
        $path = $root . '/local/logs/inbound-b24.log';
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (@file_put_contents($path, $line, FILE_APPEND | LOCK_EX) !== false) {
            return;
        }
    }

    if (@file_put_contents('/tmp/inbound-b24.log', $line, FILE_APPEND | LOCK_EX) !== false) {
        return;
    }

    @error_log('[site_requests_handler] inboundTrace write failed event=' . $event . ' trace_id=' . $traceId);
};

/**
 * Выставляет `X-Sync-Request-Id` для корреляции с логами B24/сайта (см. `BITRIX24_EXTERNAL_TEAM_HANDOFF.md`).
 * Если входящего ID нет — дублируем `trace_id` (fallback).
 */
$applyRequestIdHeaders = static function (?string $requestIdCandidate) use (&$effectiveRequestId): void {
    $rid = is_string($requestIdCandidate) ? trim($requestIdCandidate) : '';
    if ($rid !== '') {
        $rid = substr(preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $rid), 0, 128);
    }
    if ($rid === '') {
        return;
    }
    $effectiveRequestId = $rid;
    header('X-Sync-Request-Id: ' . $rid, false);
};

$truncateLogString = static function (string $value, int $maxLength = 800): string {
    if ($maxLength < 1) {
        $maxLength = 1;
    }
    if (strlen($value) <= $maxLength) {
        return $value;
    }
    return substr($value, 0, $maxLength) . '...[truncated:' . strlen($value) . ']';
};

$maskContactValue = static function (string $value): string {
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }
    if (strlen($trimmed) <= 3) {
        return '***';
    }
    return substr($trimmed, 0, 3) . '***';
};

$isContactPiiKey = static function ($key): bool {
    if (!is_scalar($key)) {
        return false;
    }
    $normalized = strtolower((string)$key);
    if ($normalized === '') {
        return false;
    }
    $markers = [
        'email',
        'phone',
        'legal_entity_email',
        'legal_entity_phone',
        'legan_entity_email',
        'legan_entity_phone',
    ];
    foreach ($markers as $marker) {
        if (strpos($normalized, $marker) !== false) {
            return true;
        }
    }
    return false;
};

$isSensitiveLogKey = static function ($key): bool {
    if (!is_scalar($key)) {
        return false;
    }
    $normalized = strtolower((string)$key);
    if ($normalized === '') {
        return false;
    }
    $sensitiveMarkers = [
        'password',
        'passwd',
        'secret',
        'token',
        'authorization',
        'cookie',
        'api_key',
        'apikey',
        'access_token',
        'refresh_token',
        'signature',
        'filedata',
        'base64',
        'content',
    ];
    foreach ($sensitiveMarkers as $marker) {
        if (strpos($normalized, $marker) !== false) {
            return true;
        }
    }
    return false;
};

$sanitizeForLog = static function ($value, int $depth = 0, string $keyHint = '') use (&$sanitizeForLog, $truncateLogString, $isSensitiveLogKey, $isContactPiiKey, $maskContactValue) {
    if ($depth > 5) {
        return '[truncated_depth]';
    }
    if (is_array($value)) {
        $sanitized = [];
        $index = 0;
        foreach ($value as $key => $item) {
            if ($index >= 60) {
                $sanitized['__truncated_items'] = count($value) - 60;
                break;
            }
            if ($isSensitiveLogKey($key)) {
                $sanitized[$key] = '[redacted]';
            } else {
                $sanitized[$key] = $sanitizeForLog($item, $depth + 1, is_scalar($key) ? (string)$key : '');
            }
            $index++;
        }
        return $sanitized;
    }
    if (is_string($value)) {
        if ($keyHint !== '' && $isContactPiiKey($keyHint)) {
            return $maskContactValue($value);
        }
        return $truncateLogString($value, 700);
    }
    if (is_object($value)) {
        return '[object:' . get_class($value) . ']';
    }
    if (is_resource($value)) {
        return '[resource]';
    }
    return $value;
};

$buildContractLogBody = static function (string $rawInput, string $contentType, array $requestPayload) use ($sanitizeForLog): array {
    $maxLogBodyBytes = 16384;
    $bodyRawLen = strlen($rawInput);
    $sourcePayload = null;

    if ($rawInput === '') {
        $sourcePayload = $requestPayload;
    } elseif (stripos($contentType, 'application/json') !== false) {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $sourcePayload = $decoded;
        } else {
            $sourcePayload = [
                'body_type' => 'invalid_json',
                'json_error' => json_last_error_msg(),
                'raw_input_sha256' => hash('sha256', $rawInput),
            ];
        }
    } else {
        $parsedForm = [];
        parse_str($rawInput, $parsedForm);
        if (is_array($parsedForm) && $parsedForm !== []) {
            $sourcePayload = $parsedForm;
        } else {
            $sourcePayload = [
                'body_type' => 'non_json_unparsed',
                'raw_input_sha256' => hash('sha256', $rawInput),
            ];
        }
    }

    $sanitizedPayload = $sanitizeForLog($sourcePayload);
    $encodedBody = json_encode($sanitizedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($encodedBody) || $encodedBody === '') {
        $encodedBody = '{"encode":"failed"}';
    }

    $bodyTruncated = false;
    $bodyTruncatedFrom = 0;
    if (strlen($encodedBody) > $maxLogBodyBytes) {
        $bodyTruncated = true;
        $bodyTruncatedFrom = strlen($encodedBody);
        $suffix = '... [TRUNCATED ' . $bodyTruncatedFrom . ' bytes]';
        $prefixMax = $maxLogBodyBytes - strlen($suffix);
        if ($prefixMax < 0) {
            $prefixMax = 0;
        }
        $encodedBody = substr($encodedBody, 0, $prefixMax) . $suffix;
    }

    return [
        'body' => $encodedBody,
        'body_raw_len' => $bodyRawLen,
        'body_logged_len' => strlen($encodedBody),
        'body_truncated' => $bodyTruncated,
        'body_truncated_from' => $bodyTruncatedFrom,
    ];
};

[$inboundSettings] = \OnlineService\Sync\FromSite\InboundSecurity::loadInboundSettings(null);
/** @var array<string, mixed> $inboundSettings */
// Source-of-truth policy: секрет синхронизации задаётся только локальным файлом сайта (`site_sync_settings.local.php`).
// Дополнительный синоним `inbound_secret` поддержан только для совпадения с контрактом сайта и не смешивается с git-tracked настройками.
$secret = \OnlineService\Sync\FromSite\InboundSecurity::resolveSecret($inboundSettings);
// Нет секрета — fail-closed, кроме явного override в `site_sync_settings.local.php` (`allow_inbound_without_secret`).
if ($secret === '') {
    $mustHaveSecret = true;
    if (!empty($inboundSettings['allow_inbound_without_secret'])) {
        $mustHaveSecret = false;
    }

    if ($mustHaveSecret) {
        $inboundTrace('site_requests_handler.reject.misconfigured');
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => 0,
            'error' => 'sync_misconfigured',
            'trace_id' => $traceId,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $inboundTrace('site_requests_handler.reject.method_not_allowed');
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => 0,
        'error' => 'method_not_allowed',
        'trace_id' => $traceId,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$requestPayload = is_array($_POST) ? $_POST : [];
$rawInput = (string) file_get_contents('php://input');
$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
if ($rawInput !== '' && (stripos($contentType, 'application/json') !== false || $requestPayload === [])) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded) && $decoded !== []) {
        $requestPayload = $decoded;
    } elseif (stripos($contentType, 'application/json') !== false) {
        $inboundTrace('site_requests_handler.reject.invalid_json', [
            'json_error' => json_last_error_msg(),
        ]);
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => 0,
            'error' => 'Invalid payload: request body must be valid JSON object',
            'error_code' => 'invalid_payload',
            'trace_id' => $traceId,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
if ($requestPayload === [] && is_array($_REQUEST) && $_REQUEST !== []) {
    $requestPayload = $_REQUEST;
}
$requestPayload = \OnlineService\Sync\FromSite\InboundPayloadNormalizer::normalizeRequestPayload($requestPayload);

try {
    \OnlineService\Sync\FromSite\InboundSecurity::assertInboundTransportSecurity($inboundSettings, $secret, $rawInput);
} catch (\Throwable $e) {
    $reason = $e->getMessage();
    if ($reason === 'misconfigured_secret') {
        $inboundTrace('site_requests_handler.reject.misconfigured_security');
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => 0,
            'error' => 'sync_misconfigured',
            'trace_id' => $traceId,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @var array<string, mixed> $errorMap */
    $errorMap = [
        // HMAC signature issues.
        'sync_signature_missing' => ['http' => 403, 'error' => 'sync_signature_missing'],
        'sync_signature_invalid' => ['http' => 403, 'error' => 'sync_signature_invalid'],
        // Clock skew checks are executed after normalize (see below) when enabled.
    ];
    /** @phpstan-ignore-next-line array index */
    $mapped = $errorMap[$reason] ?? null;
    if (is_array($mapped)) {
        $inboundTrace('site_requests_handler.reject.transport_security', ['reason' => $reason]);
        http_response_code((int)($mapped['http'] ?? 403));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => 0,
            'error' => (string)($mapped['error'] ?? $reason),
            'error_code' => (string)$reason,
            'trace_id' => $traceId,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $inboundTrace('site_requests_handler.reject.transport_security', ['reason' => $reason]);
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => 0,
        'error' => 'sync_forbidden',
        'trace_id' => $traceId,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    \OnlineService\Sync\FromSite\InboundSecurity::assertInboundClockSkew($inboundSettings, $requestPayload);
} catch (\Throwable $e) {
    $reason = $e->getMessage();
    if ($reason === 'sync_timestamp_invalid') {
        $http = 403;
        $payload = [
            'success' => 0,
            'error' => 'sync_timestamp_invalid',
            'error_code' => $reason,
            'trace_id' => $traceId,
        ];
    } elseif ($reason === 'sync_timestamp_expired') {
        $http = 403;
        $payload = [
            'success' => 0,
            'error' => 'sync_timestamp_expired',
            'error_code' => $reason,
            'trace_id' => $traceId,
        ];
    } else {
        $http = 403;
        $payload = [
            'success' => 0,
            'error' => $reason !== '' ? $reason : 'sync_forbidden',
            'error_code' => $reason,
            'trace_id' => $traceId,
        ];
    }
    $inboundTrace('site_requests_handler.reject.timestamp_skew', ['reason' => $reason]);
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$headerToken = '';
if (isset($_SERVER['HTTP_X_SYNC_TOKEN']) && is_scalar($_SERVER['HTTP_X_SYNC_TOKEN'])) {
    $headerToken = trim((string)$_SERVER['HTTP_X_SYNC_TOKEN']);
}
$requireHeaderOnly = !empty($inboundSettings['inbound_require_header_token']);

$syncTokenCandidates = [];
if ($headerToken !== '') {
    $syncTokenCandidates[] = $headerToken;
}
foreach (['_TOKEN', '_SYNC_TOKEN'] as $k) {
    if (!empty($requestPayload[$k]) && is_scalar($requestPayload[$k])) {
        $syncTokenCandidates[] = trim((string)$requestPayload[$k]);
        break;
    }
}
foreach (['sync_token', 'SYNC_TOKEN'] as $k) {
    if (!empty($requestPayload[$k]) && is_scalar($requestPayload[$k])) {
        $syncTokenCandidates[] = trim((string)$requestPayload[$k]);
        break;
    }
}

$resolvedInboundToken = '';
foreach ($syncTokenCandidates as $candidate) {
    if (is_string($candidate) && $candidate !== '') {
        $resolvedInboundToken = $candidate;
        break;
    }
}

/** Контекст для лога до успешной проверки токена — иначе событие payload.received недостижимо при 403. */
$buildSyncForbiddenDiag = static function () use ($requestPayload, $rawInput, $contentType, $buildContractLogBody): array {
    $base = [
        'request_method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
        'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
        'content_type' => $contentType,
        'action' => (string)($requestPayload['ACTION'] ?? ''),
        'crm_method_field' => (string)($requestPayload['METHOD'] ?? ''),
        'payload_keys' => array_keys($requestPayload),
    ];
    $rp = $requestPayload['PARAMS'] ?? null;
    if (is_string($rp) && $rp !== '') {
        $decodedRp = json_decode($rp, true);
        if (is_array($decodedRp)) {
            $rp = $decodedRp;
        }
    }
    if (is_array($rp)) {
        $base['params_keys'] = array_keys($rp);
        if (isset($rp['fields']) && is_array($rp['fields'])) {
            $base['fields_keys'] = array_keys($rp['fields']);
        }
    }

    return array_merge($base, $buildContractLogBody($rawInput, $contentType, $requestPayload));
};

if ($secret !== '') {
    if ($resolvedInboundToken === '' || !hash_equals($secret, $resolvedInboundToken)) {
        $reasonDetail = $resolvedInboundToken === '' ? 'missing_or_empty_token' : 'token_mismatch';
        $inboundTrace('site_requests_handler.reject.sync_forbidden', array_merge([
            'token_present' => $resolvedInboundToken !== '',
            'via_header' => $headerToken !== '',
            'reason_detail' => $reasonDetail,
        ], $buildSyncForbiddenDiag()));
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => 0,
            'error' => 'sync_forbidden',
            'trace_id' => $traceId,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($requireHeaderOnly && $headerToken === '') {
        $inboundTrace('site_requests_handler.reject.sync_forbidden', array_merge([
            'reason' => 'header_token_required',
            'token_present' => true,
            'via_header' => false,
        ], $buildSyncForbiddenDiag()));
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => 0,
            'error' => 'sync_forbidden',
            'trace_id' => $traceId,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$normalizedRequestId = '';
if (!empty($_SERVER['HTTP_X_SYNC_REQUEST_ID']) && is_scalar($_SERVER['HTTP_X_SYNC_REQUEST_ID'])) {
    $normalizedRequestId = trim((string)$_SERVER['HTTP_X_SYNC_REQUEST_ID']);
}
if ($normalizedRequestId === '' && !empty($_SERVER['HTTP_X_SYNC_REQUEST_UUID']) && is_scalar($_SERVER['HTTP_X_SYNC_REQUEST_UUID'])) {
    $normalizedRequestId = trim((string)$_SERVER['HTTP_X_SYNC_REQUEST_UUID']);
}

if ($normalizedRequestId === '') {
    foreach (['REQUEST_ID', 'request_id', '_REQUEST_ID'] as $k) {
        if (!empty($requestPayload[$k]) && is_scalar($requestPayload[$k])) {
            $normalizedRequestId = trim((string)$requestPayload[$k]);
            break;
        }
    }
}
if ($normalizedRequestId !== '') {
    // Нормализуем ограниченный алфавит (как в trace id).
    $normalizedRequestId = substr(preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $normalizedRequestId), 0, 128);
}

// Для корреляции в логах всегда отдаём заголовок `X-Sync-Request-Id` (если известен входящий ID — он; иначе — trace_id).
$applyRequestIdHeaders($normalizedRequestId !== '' ? $normalizedRequestId : $traceId);

$dedupStoreConfig = \OnlineService\Sync\FromSite\InboundDedupStore::resolveStore(
    $inboundSettings,
    rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/')
);
if (is_array($dedupStoreConfig) && isset($dedupStoreConfig['path'], $dedupStoreConfig['ttl']) && $normalizedRequestId !== '') {
    /** @phpstan-ignore-next-line */
    $dedupAction = isset($requestPayload['ACTION']) && is_scalar($requestPayload['ACTION']) ? (string)$requestPayload['ACTION'] : '';
    /** @phpstan-ignore-next-line */
    $dedupKey = 'inbound|' . strtolower($dedupAction) . '|' . $normalizedRequestId;
    /** @phpstan-ignore-next-line */
    $ttl = (int)$dedupStoreConfig['ttl'];
    /** @phpstan-ignore-next-line */
    $path = (string)$dedupStoreConfig['path'];
    try {
        if (\OnlineService\Sync\FromSite\InboundDedupStore::isDuplicateThenRemember($path, $dedupKey, $ttl)) {
            $inboundTrace('site_requests_handler.reject.dedup_duplicate', [
                'request_id' => $normalizedRequestId,
                'action' => $dedupAction,
            ]);
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => 0,
                'error' => 'duplicate_request',
                'reason_code' => 'dedup_duplicate',
                'request_id' => $effectiveRequestId !== '' ? $effectiveRequestId : $traceId,
                'trace_id' => $traceId,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } catch (\Throwable $e) {
        // Fail-open: dedup недоступен, продолжаем обработку.
        $inboundTrace('site_requests_handler.dedup.error', ['error_class' => get_class($e)]);
    }
}

$requestFieldKeys = [];
$requestParams = $requestPayload['PARAMS'] ?? null;
if (is_string($requestParams) && $requestParams !== '') {
    $decodedRequestParams = json_decode($requestParams, true);
    if (is_array($decodedRequestParams)) {
        $requestParams = $decodedRequestParams;
    }
}
if (is_array($requestParams) && isset($requestParams['fields']) && is_array($requestParams['fields'])) {
    $requestFieldKeys = array_keys($requestParams['fields']);
}
$requestParamsKeys = is_array($requestParams) ? array_keys($requestParams) : [];
$incomingPayloadSnapshot = [
    'ts' => date('c'),
    'event' => 'site_requests_handler.payload.received',
    'request_method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
    'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
    'content_type' => $contentType,
    'action' => (string)($requestPayload['ACTION'] ?? ''),
    'method' => (string)($requestPayload['METHOD'] ?? ''),
    'payload_keys' => array_keys($requestPayload),
    'params_keys' => $requestParamsKeys,
    'field_keys' => $requestFieldKeys,
];
$incomingPayloadSnapshot = array_merge($incomingPayloadSnapshot, $buildContractLogBody($rawInput, $contentType, $requestPayload));
$inboundTrace('site_requests_handler.payload.received', $incomingPayloadSnapshot);

if (!empty($requestPayload['_INVALID_PAYLOAD'])) {
    $inboundTrace('site_requests_handler.reject.invalid_payload', [
        'reason' => (string)($requestPayload['_INVALID_PAYLOAD_REASON'] ?? 'unknown'),
    ]);
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => 0,
        'error' => (string)($requestPayload['_INVALID_PAYLOAD_REASON'] ?? 'Invalid payload'),
        'error_code' => 'invalid_payload',
        'trace_id' => $traceId,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$snapshot = [
    'action' => (string)($requestPayload['ACTION'] ?? ''),
    'method' => (string)($requestPayload['METHOD'] ?? ''),
    'has_params' => array_key_exists('PARAMS', $requestPayload),
    'params_type' => array_key_exists('PARAMS', $requestPayload) ? gettype($requestPayload['PARAMS']) : null,
];
$params = $requestPayload['PARAMS'] ?? null;
if (is_array($params)) {
    $snapshot['params_keys'] = array_keys($params);
    $fields = isset($params['fields']) && is_array($params['fields']) ? $params['fields'] : [];
    $snapshot['fields_keys'] = array_keys($fields);
    if (isset($fields[$ufCompanyRequisitesFile]) && is_array($fields[$ufCompanyRequisitesFile])) {
        $fileField = $fields[$ufCompanyRequisitesFile];
        $snapshot['requisites_file_tuple'] = [
            'present' => true,
            'shape' => array_key_exists('fileData', $fileField) ? 'fileData' : 'tuple',
            'name' => null,
            'content_len' => null,
        ];
        if (isset($fileField['fileData']) && is_array($fileField['fileData'])) {
            $fileTuple = $fileField['fileData'];
            $snapshot['requisites_file_tuple']['name'] = isset($fileTuple[0]) && is_scalar($fileTuple[0]) ? (string)$fileTuple[0] : null;
            $snapshot['requisites_file_tuple']['content_len'] = isset($fileTuple[1]) && is_scalar($fileTuple[1]) ? strlen((string)$fileTuple[1]) : null;
        } else {
            $snapshot['requisites_file_tuple']['name'] = isset($fileField[0]) && is_scalar($fileField[0]) ? (string)$fileField[0] : null;
            $snapshot['requisites_file_tuple']['content_len'] = isset($fileField[1]) && is_scalar($fileField[1]) ? strlen((string)$fileField[1]) : null;
        }
    } else {
        $snapshot['requisites_file_tuple'] = ['present' => false];
    }
} elseif (is_string($params) && $params !== '') {
    $snapshot['params_len'] = strlen($params);
    $decodedParams = json_decode($params, true);
    if (is_array($decodedParams)) {
        $snapshot['params_keys'] = array_keys($decodedParams);
        $fields = isset($decodedParams['fields']) && is_array($decodedParams['fields']) ? $decodedParams['fields'] : [];
        $snapshot['fields_keys'] = array_keys($fields);
        if (isset($fields[$ufCompanyRequisitesFile]) && is_array($fields[$ufCompanyRequisitesFile])) {
            $fileField = $fields[$ufCompanyRequisitesFile];
            $snapshot['requisites_file_tuple'] = [
                'present' => true,
                'shape' => array_key_exists('fileData', $fileField) ? 'fileData' : 'tuple',
                'name' => null,
                'content_len' => null,
            ];
            if (isset($fileField['fileData']) && is_array($fileField['fileData'])) {
                $fileTuple = $fileField['fileData'];
                $snapshot['requisites_file_tuple']['name'] = isset($fileTuple[0]) && is_scalar($fileTuple[0]) ? (string)$fileTuple[0] : null;
                $snapshot['requisites_file_tuple']['content_len'] = isset($fileTuple[1]) && is_scalar($fileTuple[1]) ? strlen((string)$fileTuple[1]) : null;
            } else {
                $snapshot['requisites_file_tuple']['name'] = isset($fileField[0]) && is_scalar($fileField[0]) ? (string)$fileField[0] : null;
                $snapshot['requisites_file_tuple']['content_len'] = isset($fileField[1]) && is_scalar($fileField[1]) ? strlen((string)$fileField[1]) : null;
            }
        } else {
            $snapshot['requisites_file_tuple'] = ['present' => false];
        }
    }
}
$inboundTrace('site_requests_handler.payload.snapshot', $snapshot);

$methodName = (string)($requestPayload['METHOD'] ?? '');
$methodParams = $requestPayload['PARAMS'] ?? null;
if (is_string($methodParams) && $methodParams !== '') {
    $decodedMethodParams = json_decode($methodParams, true);
    if (is_array($decodedMethodParams)) {
        $methodParams = $decodedMethodParams;
    }
}

$diagCompanyId = 0;
$diagHasRequisitesUf = false;
$diagConvertedFileId = 0;
if ($methodName === 'crm.company.update' && is_array($methodParams)) {
    $diagCompanyId = (int)($methodParams['id'] ?? 0);
    $diagHasRequisitesUf = isset($methodParams['fields'])
        && is_array($methodParams['fields'])
        && array_key_exists($ufCompanyRequisitesFile, $methodParams['fields']);
}

// Fallback: для внутренних CRM_METHOD формата fileData может быть недостаточно.
// Конвертируем fileData -> b_file ID и подставляем ID в UF, чтобы гарантировать сохранение.
if ($methodName === 'crm.company.update' && is_array($methodParams) && $diagHasRequisitesUf) {
    $fieldsRef = $methodParams['fields'];
    $rawFileField = $fieldsRef[$ufCompanyRequisitesFile] ?? null;
    $tuple = null;
    if (is_array($rawFileField) && isset($rawFileField['fileData']) && is_array($rawFileField['fileData'])) {
        $tuple = $rawFileField['fileData'];
    } elseif (is_array($rawFileField) && isset($rawFileField[0], $rawFileField[1])) {
        $tuple = $rawFileField;
    }

    if (is_array($tuple)) {
        $fileName = isset($tuple[0]) && is_scalar($tuple[0]) ? (string)$tuple[0] : '';
        $base64 = isset($tuple[1]) && is_scalar($tuple[1]) ? (string)$tuple[1] : '';
        $binary = $base64 !== '' ? base64_decode($base64, true) : false;
        if ($binary !== false && $binary !== '') {
            $tmpPath = tempnam(sys_get_temp_dir(), 'crm_uf_');
            if (is_string($tmpPath) && $tmpPath !== '' && @file_put_contents($tmpPath, $binary) !== false) {
                $fileArray = \CFile::MakeFileArray($tmpPath);
                if (is_array($fileArray)) {
                    if ($fileName !== '') {
                        $fileArray['name'] = $fileName;
                    }
                    $fileArray['MODULE_ID'] = 'crm';
                    $savedFileId = (int)\CFile::SaveFile($fileArray, 'crm');
                    if ($savedFileId > 0) {
                        $diagConvertedFileId = $savedFileId;
                        $methodParams['fields'][$ufCompanyRequisitesFile] = $savedFileId;
                        $requestPayload['PARAMS'] = $methodParams;
                        $inboundTrace('site_requests_handler.requisites_file.converted', [
                            'company_id' => (int)($methodParams['id'] ?? 0),
                            'mode' => 'fileData_to_file_id',
                            'file_id' => $savedFileId,
                            'file_name' => $fileName,
                            'bytes' => strlen($binary),
                        ]);
                    } else {
                        $inboundTrace('site_requests_handler.requisites_file.convert_failed', [
                            'company_id' => (int)($methodParams['id'] ?? 0),
                            'error' => 'save_file_failed',
                        ]);
                    }
                } else {
                    $inboundTrace('site_requests_handler.requisites_file.convert_failed', [
                        'company_id' => (int)($methodParams['id'] ?? 0),
                        'error' => 'make_file_array_failed',
                    ]);
                }
                @unlink($tmpPath);
            } else {
                $inboundTrace('site_requests_handler.requisites_file.convert_failed', [
                    'company_id' => (int)($methodParams['id'] ?? 0),
                    'error' => 'tmp_write_failed',
                ]);
            }
        } else {
            $inboundTrace('site_requests_handler.requisites_file.convert_failed', [
                'company_id' => (int)($methodParams['id'] ?? 0),
                'error' => 'base64_decode_failed',
            ]);
        }
    }
}

if ($diagCompanyId > 0 && $diagHasRequisitesUf && class_exists('CCrmCompany')) {
    $before = \CCrmCompany::GetByID($diagCompanyId, false);
    $beforeUf = is_array($before) ? ($before[$ufCompanyRequisitesFile] ?? null) : null;
    $inboundTrace('site_requests_handler.company_uf.before_update', [
        'company_id' => $diagCompanyId,
        'uf_type' => gettype($beforeUf),
        'uf_scalar' => is_scalar($beforeUf) ? (string)$beforeUf : null,
        'uf_is_array' => is_array($beforeUf),
    ]);
}

$requestPayload['_SYNC_TRACE_ID'] = $traceId;
$requestPayload['_SYNC_CUTOVER_LABEL'] = \OnlineService\SyncTraceContext::CUTOVER_LABEL;
$inboundTrace('site_requests_handler.dispatch.start', [
    'action' => (string)($requestPayload['ACTION'] ?? ''),
    'payload_keys' => array_keys($requestPayload),
    'payload_size' => strlen($rawInput),
]);
$response = null;
try {
    $response = InboundEndpoint::processRequest($requestPayload);
} catch (\Throwable $e) {
    $inboundTrace('site_requests_handler.dispatch.exception', [
        'action' => (string)($requestPayload['ACTION'] ?? ''),
        'error_class' => get_class($e),
    ]);
    $response = \OnlineService\Sync\FromSite\InboundPayloadNormalizer::normalizeError($e, $trace);
}
$response = \OnlineService\Sync\FromSite\InboundPayloadNormalizer::normalizeResponse($response, $trace);
$responseHttpStatus = (int)($response['http_status'] ?? 0);
if ($responseHttpStatus <= 0) {
    $responseHttpStatus = ((string)($response['error_code'] ?? '') === 'dispatch_failed') ? 500 : 200;
}
http_response_code($responseHttpStatus);
$inboundTrace('site_requests_handler.dispatch.done', [
    'action' => (string)($requestPayload['ACTION'] ?? ''),
    'success' => isset($response['success']) ? (int)$response['success'] : null,
    'error' => (string)($response['error'] ?? ''),
    'error_code' => (string)($response['error_code'] ?? ''),
    'http_status' => $responseHttpStatus,
    'response_keys' => is_array($response) ? array_keys($response) : [],
    'result_type' => isset($response['result']) ? gettype($response['result']) : null,
]);

if ($diagCompanyId > 0 && $diagHasRequisitesUf && class_exists('CCrmCompany')) {
    $ufFieldId = 0;
    if (class_exists(\Bitrix\Main\Application::class)) {
        try {
            $conn = \Bitrix\Main\Application::getConnection();
            $helper = $conn->getSqlHelper();
            $ufFieldName = $ufCompanyRequisitesFile;
            $ufFieldSql = $helper->forSql($ufFieldName);
            $ufMeta = $conn->query(
                "SELECT ID, FIELD_NAME, USER_TYPE_ID, MULTIPLE, MANDATORY FROM b_user_field WHERE ENTITY_ID='CRM_COMPANY' AND FIELD_NAME='" . $ufFieldSql . "' LIMIT 1"
            )->fetch();
            $ufFieldId = is_array($ufMeta) ? (int)($ufMeta['ID'] ?? 0) : 0;
            $inboundTrace('site_requests_handler.company_uf.meta', [
                'company_id' => $diagCompanyId,
                'exists' => is_array($ufMeta),
                'field_id' => $ufFieldId,
                'user_type' => is_array($ufMeta) ? (string)($ufMeta['USER_TYPE_ID'] ?? '') : '',
                'multiple' => is_array($ufMeta) ? (string)($ufMeta['MULTIPLE'] ?? '') : '',
                'mandatory' => is_array($ufMeta) ? (string)($ufMeta['MANDATORY'] ?? '') : '',
            ]);
        } catch (\Throwable $e) {
            $inboundTrace('site_requests_handler.company_uf.meta_error', [
                'company_id' => $diagCompanyId,
                'error_class' => get_class($e),
            ]);
        }
    }

    $after = \CCrmCompany::GetByID($diagCompanyId, false);
    $afterUf = is_array($after) ? ($after[$ufCompanyRequisitesFile] ?? null) : null;
    $inboundTrace('site_requests_handler.company_uf.after_update', [
        'company_id' => $diagCompanyId,
        'uf_type' => gettype($afterUf),
        'uf_scalar' => is_scalar($afterUf) ? (string)$afterUf : null,
        'uf_is_array' => is_array($afterUf),
    ]);

    if (class_exists(\Bitrix\Main\Application::class)) {
        try {
            $conn = \Bitrix\Main\Application::getConnection();
            $helper = $conn->getSqlHelper();
            $ufCol = $helper->forSql($ufCompanyRequisitesFile);
            $row = $conn->query("SELECT " . $ufCol . " AS UF_VAL FROM b_uts_crm_company WHERE VALUE_ID=" . (int)$diagCompanyId . " LIMIT 1")->fetch();
            $dbUf = is_array($row) ? ($row['UF_VAL'] ?? null) : null;
            $inboundTrace('site_requests_handler.company_uf.db_after_update', [
                'company_id' => $diagCompanyId,
                'uf_type' => gettype($dbUf),
                'uf_scalar' => is_scalar($dbUf) ? (string)$dbUf : null,
                'uf_is_array' => is_array($dbUf),
            ]);

            if (($dbUf === null || $dbUf === '' || $dbUf === false)
                && $diagConvertedFileId > 0
                && class_exists('CCrmCompany')
            ) {
                $crmEntity = new \CCrmCompany(false);
                $currentUserId = 1;
                if (class_exists('CCrmSecurityHelper')) {
                    try {
                        $resolvedUserId = (int)\CCrmSecurityHelper::GetCurrentUserID();
                        if ($resolvedUserId > 0) {
                            $currentUserId = $resolvedUserId;
                        }
                    } catch (\Throwable $e) {
                    }
                }
                $fileArrayForUf = \CFile::MakeFileArray($diagConvertedFileId);
                if (\is_array($fileArrayForUf)) {
                    $fileArrayForUf['MODULE_ID'] = 'crm';
                }
                $fallbackFields = [$ufCompanyRequisitesFile => $fileArrayForUf];
                $fallbackUpdated = $crmEntity->Update(
                    $diagCompanyId,
                    $fallbackFields,
                    true,
                    [
                        'CURRENT_USER' => $currentUserId,
                        'IS_SYSTEM_ACTION' => true,
                        'ENABLE_DUP_INDEX_INVALIDATION' => false,
                        'REGISTER_SONET_EVENT' => false,
                    ]
                );
                $inboundTrace('site_requests_handler.company_uf.fallback_direct_update', [
                    'company_id' => $diagCompanyId,
                    'file_id' => $diagConvertedFileId,
                    'success' => (bool)$fallbackUpdated,
                    'last_error' => $fallbackUpdated ? '' : (string)$crmEntity->LAST_ERROR,
                ]);

                $rowAfterFallback = $conn->query("SELECT " . $ufCol . " AS UF_VAL FROM b_uts_crm_company WHERE VALUE_ID=" . (int)$diagCompanyId . " LIMIT 1")->fetch();
                $dbUfAfterFallback = is_array($rowAfterFallback) ? ($rowAfterFallback['UF_VAL'] ?? null) : null;
                $inboundTrace('site_requests_handler.company_uf.db_after_fallback', [
                    'company_id' => $diagCompanyId,
                    'uf_type' => gettype($dbUfAfterFallback),
                    'uf_scalar' => is_scalar($dbUfAfterFallback) ? (string)$dbUfAfterFallback : null,
                    'uf_is_array' => is_array($dbUfAfterFallback),
                ]);

                // Стандартный fallback через менеджер пользовательских полей CRM (без прямого SQL).
                if (($dbUfAfterFallback === null || $dbUfAfterFallback === '' || $dbUfAfterFallback === false) && $diagConvertedFileId > 0) {
                    $ufManagerUpdated = false;
                    $ufManagerError = '';
                    if (isset($GLOBALS['USER_FIELD_MANAGER']) && \is_object($GLOBALS['USER_FIELD_MANAGER'])) {
                        try {
                            $ufManagerFields = [$ufCompanyRequisitesFile => $fileArrayForUf];
                            $ufManagerUpdated = (bool)$GLOBALS['USER_FIELD_MANAGER']->Update('CRM_COMPANY', $diagCompanyId, $ufManagerFields);
                        } catch (\Throwable $e) {
                            $ufManagerError = $e->getMessage();
                        }
                    } else {
                        $ufManagerError = 'user_field_manager_unavailable';
                    }
                    $inboundTrace('site_requests_handler.company_uf.uf_manager_update', [
                        'company_id' => $diagCompanyId,
                        'file_id' => $diagConvertedFileId,
                        'success' => $ufManagerUpdated,
                        'error' => $ufManagerError,
                    ]);
                    $rowAfterUfManager = $conn->query("SELECT " . $ufCol . " AS UF_VAL FROM b_uts_crm_company WHERE VALUE_ID=" . (int)$diagCompanyId . " LIMIT 1")->fetch();
                    $dbUfAfterUfManager = is_array($rowAfterUfManager) ? ($rowAfterUfManager['UF_VAL'] ?? null) : null;
                    $inboundTrace('site_requests_handler.company_uf.db_after_uf_manager', [
                        'company_id' => $diagCompanyId,
                        'uf_type' => gettype($dbUfAfterUfManager),
                        'uf_scalar' => is_scalar($dbUfAfterUfManager) ? (string)$dbUfAfterUfManager : null,
                        'uf_is_array' => is_array($dbUfAfterUfManager),
                    ]);
                }
            }

            if ($ufFieldId > 0) {
                $utmRows = [];
                $utmRes = $conn->query(
                    "SELECT * " .
                    "FROM b_utm_crm_company WHERE VALUE_ID=" . (int)$diagCompanyId . " AND FIELD_ID=" . $ufFieldId
                );
                while ($utmRow = $utmRes->fetch()) {
                    $utmRows[] = $utmRow;
                }
                $inboundTrace('site_requests_handler.company_uf.utm_rows', [
                    'company_id' => $diagCompanyId,
                    'field_id' => $ufFieldId,
                    'rows_count' => count($utmRows),
                    'rows_preview' => array_slice($utmRows, 0, 3),
                ]);
            }
        } catch (\Throwable $e) {
            $inboundTrace('site_requests_handler.company_uf.db_after_update_error', [
                'company_id' => $diagCompanyId,
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
  