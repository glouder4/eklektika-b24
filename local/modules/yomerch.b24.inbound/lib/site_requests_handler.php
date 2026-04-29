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

$inboundTrace('site_requests_handler.entry', [
    'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
    'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
    'content_type' => (string)($_SERVER['CONTENT_TYPE'] ?? ''),
    'has_token_header' => isset($_SERVER['HTTP_X_SYNC_TOKEN']) && (string)$_SERVER['HTTP_X_SYNC_TOKEN'] !== '',
    'request_keys' => is_array($_REQUEST) ? array_keys($_REQUEST) : [],
]);

$secret = '';
// Source-of-truth policy: inbound token is loaded only from site_sync_settings.local.php.
// Tracked configs/env are intentionally ignored for deterministic and fail-closed behavior.
$settingsPath = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/local/modules/yomerch.b24.siteconnector/site_sync_settings.local.php';
if (is_file($settingsPath)) {
    $settings = include $settingsPath;
    if (is_array($settings) && !empty($settings['sync_token']) && is_scalar($settings['sync_token'])) {
        $secret = (string)$settings['sync_token'];
    }
}
if ($secret === '') {
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

$token = $_SERVER['HTTP_X_SYNC_TOKEN'] ?? '';
$token = is_scalar($token) ? (string)$token : '';
if ($token === '' || !hash_equals($secret, $token)) {
    $inboundTrace('site_requests_handler.reject.sync_forbidden', [
        'token_present' => $token !== '',
    ]);
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => 0,
        'error' => 'sync_forbidden',
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
  