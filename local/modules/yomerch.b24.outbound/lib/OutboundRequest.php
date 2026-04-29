<?php

namespace OnlineService\Sync\ToSite;

class OutboundRequest
{
    private const TRANSPORT_ERROR_CODES = [
        'transport_curl_error',
        'transport_http_error',
        'transport_json_error',
    ];

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private static function normalizePositiveIntList($raw): array
    {
        if (!\is_array($raw)) {
            if (\is_scalar($raw)) {
                $one = (int)(string)$raw;
                return $one > 0 ? [$one] : [];
            }
            return [];
        }

        $set = [];
        foreach ($raw as $v) {
            if (!\is_scalar($v)) {
                continue;
            }
            $id = (int)(string)$v;
            if ($id > 0) {
                $set[$id] = true;
            }
        }

        return \array_map('intval', \array_keys($set));
    }

    /**
     * @param mixed $v
     */
    private static function parseBoolFlag($v): bool
    {
        if ($v === true || $v === 1) {
            return true;
        }
        if (\is_string($v)) {
            return \in_array(\strtolower(\trim($v)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * Расширенный вывод pre()+die() в {@see sendRequest()}.
     * Источник: $GLOBALS['YOMERRCH24_B24_SITE_SYNC']['sync_debug'], иначе константа YOMERRCH24_SITE_SYNC_DEBUG.
     */
    protected static function isSiteSyncDebugEnabled(): bool
    {
        if (isset($GLOBALS['YOMERRCH24_B24_SITE_SYNC']) && \is_array($GLOBALS['YOMERRCH24_B24_SITE_SYNC'])
            && \array_key_exists('sync_debug', $GLOBALS['YOMERRCH24_B24_SITE_SYNC'])) {
            return self::parseBoolFlag($GLOBALS['YOMERRCH24_B24_SITE_SYNC']['sync_debug']);
        }
        if (!\defined('YOMERRCH24_SITE_SYNC_DEBUG')) {
            return false;
        }
        $v = \YOMERRCH24_SITE_SYNC_DEBUG;

        return self::parseBoolFlag($v);
    }

    /**
     * Файловый лог: sync_trace ИЛИ sync_debug (чтобы при одном только debug не терялся b24-to-site-sync.log).
     * Источник: $GLOBALS['YOMERRCH24_B24_SITE_SYNC'], иначе константы.
     */
    protected static function isSiteSyncTraceEnabled(): bool
    {
        if (isset($GLOBALS['YOMERRCH24_B24_SITE_SYNC']) && \is_array($GLOBALS['YOMERRCH24_B24_SITE_SYNC'])) {
            $g = $GLOBALS['YOMERRCH24_B24_SITE_SYNC'];
            if (\array_key_exists('sync_trace', $g) && self::parseBoolFlag($g['sync_trace'])) {
                return true;
            }
            if (\array_key_exists('sync_debug', $g) && self::parseBoolFlag($g['sync_debug'])) {
                return true;
            }
        }
        if (\defined('YOMERRCH24_SITE_SYNC_TRACE') && self::parseBoolFlag(\YOMERRCH24_SITE_SYNC_TRACE)) {
            return true;
        }
        if (\defined('YOMERRCH24_SITE_SYNC_DEBUG') && self::parseBoolFlag(\YOMERRCH24_SITE_SYNC_DEBUG)) {
            return true;
        }

        return false;
    }

    /**
     * Кандидаты корня сайта для логов. На ext_www DOCUMENT_ROOT часто указывает на /home/bitrix/www,
     * а код лежит в /home/bitrix/ext_www/… — тогда mkdir в «чужом» local падает или пишется не туда.
     * Корень от расположения класса: модуль yomerch.b24.outbound, каталог lib.
     *
     * @return list<string>
     */
    private static function documentRootCandidatesForTrace(): array
    {
        $out = [];
        $push = static function (string $root) use (&$out): void {
            $root = \rtrim(\str_replace('\\', '/', $root), '/');
            if ($root === '') {
                return;
            }
            foreach ($out as $existing) {
                if ($existing === $root) {
                    return;
                }
            }
            $marker = $root . '/local/modules/yomerch.b24.outbound/lib/OutboundRequest.php';
            if (@\is_file($marker)) {
                $out[] = $root;
            }
        };

        $fromClass = \dirname(__DIR__, 4);
        $push($fromClass);
        $rp = @\realpath($fromClass);
        if (\is_string($rp) && $rp !== '') {
            $push($rp);
        }
        if (\class_exists(\Bitrix\Main\Application::class)) {
            try {
                $push((string)\Bitrix\Main\Application::getDocumentRoot());
            } catch (\Throwable $e) {
            }
        }
        $push((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));

        return $out;
    }

    /**
     * Только local/logs/b24-to-site-sync.log относительно корня сайта (см. yomerrch24-ru-b24/local/logs).
     * Корень берётся по маркеру модуля yomerch.b24.siteconnector (ext_www и т.п.).
     *
     * @param array<string, mixed> $context
     */
    public static function writeOutboundTrace(string $message, array $context = []): void
    {
        if (!self::isSiteSyncTraceEnabled()) {
            return;
        }

        $suffix = $context === [] ? '' : ' ' . \json_encode($context, \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE);
        $line = \date('Y-m-d H:i:s') . ' ' . $message . $suffix . \PHP_EOL;

        $roots = self::documentRootCandidatesForTrace();
        $root = $roots[0] ?? '';
        if ($root === '') {
            \error_log('[OutboundRequest] trace: no document root candidate for local/logs');

            return;
        }

        $dir = $root . '/local/logs';
        if (!\is_dir($dir)) {
            @\mkdir($dir, 0775, true);
        }
        $path = $dir . '/b24-to-site-sync.log';
        if (@\file_put_contents($path, $line, \FILE_APPEND | \LOCK_EX) !== false) {
            return;
        }

        \error_log(
            '[OutboundRequest] trace write failed: ' . $path
            . ' — проверьте права (chown пользователя веб-сервера на local/logs). '
            . (\error_get_last()['message'] ?? '')
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function redactSyncTokenForLog(array $params): array
    {
        if (!isset($params['sync_token'])) {
            return $params;
        }
        $out = $params;
        $out['sync_token'] = '(redacted)';

        return $out;
    }

    /**
     * Backward-compatible no-op logger: kept to avoid fatals
     * if legacy handlers still call self::logSiteSync().
     */
    protected static function logSiteSync(string $line): void
    {
    }

    /**
     * Backward-compatible helper for legacy logging calls.
     */
    protected static function truncateLog(string $s, int $max = 2000): string
    {
        return strlen($s) <= $max ? $s : substr($s, 0, $max) . '...';
    }

    protected function sendRequest(array $params, bool $debug = false): array
    {
        $debug = $debug || self::isSiteSyncDebugEnabled();
        $trace = \OnlineService\SyncTraceContext::resolve($params);

        // Для UPDATE_COMPANY отправляем весь список CONTACT_IDS в пользовательские поля.
        // На стороне сайта этот список резолвится в b_user.ID через contact->user mapping.
        if (($params['ACTION'] ?? '') === 'UPDATE_COMPANY') {
            self::writeOutboundTrace('sendRequest update_company requisites keys', [
                'correlation_id' => (string)$trace['correlation_id'],
                'cutover_label' => (string)$trace['cutover_label'],
                'has_OS_REQUSITES_FILE' => \array_key_exists('OS_REQUSITES_FILE', $params),
                'type_OS_REQUSITES_FILE' => \array_key_exists('OS_REQUSITES_FILE', $params) ? \gettype($params['OS_REQUSITES_FILE']) : null,
                'has_OS_REQUISITES_FILE' => \array_key_exists('OS_REQUISITES_FILE', $params),
                'type_OS_REQUISITES_FILE' => \array_key_exists('OS_REQUISITES_FILE', $params) ? \gettype($params['OS_REQUISITES_FILE']) : null,
            ]);
            $contactIds = self::normalizePositiveIntList($params['CONTACT_IDS'] ?? []);
            if ($contactIds !== []) {
                $params['OS_COMPANY_USERS'] = $contactIds;
                $params['LEGAN_ENTITY_USERS'] = $contactIds;
            }
        }

        $headers = [];
        $headers[] = 'X-Correlation-ID: ' . (string)$trace['correlation_id'];
        $headers[] = 'X-Cutover-Label: ' . (string)$trace['cutover_label'];
        $headers[] = 'X-Sync-Trace-ID: ' . (string)$trace['trace_id'];
        if (\defined('YOMERRCH24_SITE_SYNC_TOKEN') && \YOMERRCH24_SITE_SYNC_TOKEN !== '') {
            // Keep legacy POST token and add header for stricter inbound validation.
            $params['sync_token'] = \YOMERRCH24_SITE_SYNC_TOKEN;
            $headers[] = 'X-Sync-Token: ' . \YOMERRCH24_SITE_SYNC_TOKEN;
        }
        $params['_SYNC_TRACE_ID'] = (string)$trace['trace_id'];
        $params['_SYNC_CUTOVER_LABEL'] = (string)$trace['cutover_label'];

        $queryUrl = \YOMERRCH24_SITE_URL . '/local/modules/yomerch.b24.inbound/endpoint.php';

        $queryData = http_build_query($params);
        $retryCodes = [429, 503];
        // Более агрессивный backoff для защиты от nginx rate-limit на сайте.
        $retryDelaysUs = [1000000, 2000000, 4000000, 8000000];
        $result = '';
        $httpCode = 0;
        $curlError = '';
        $curlErrno = 0;
        $attempts = count($retryDelaysUs) + 1;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_POST => 1,
                CURLOPT_HEADER => 0,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $queryUrl,
                CURLOPT_POSTFIELDS => $queryData,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $result = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            $curlErrno = curl_errno($curl);
            curl_close($curl);

            $shouldRetry = ($curlErrno === 0)
                && in_array((int)$httpCode, $retryCodes, true)
                && $attempt < $attempts;
            if (!$shouldRetry) {
                break;
            }
            $baseDelayUs = $retryDelaysUs[$attempt - 1];
            $jitterUs = random_int(0, 300000);
            $sleepUs = $baseDelayUs + $jitterUs;
            usleep($sleepUs);
        }

        if ($debug) {
            pre('=== CURL Request Details ===');
            pre('URL: ' . $queryUrl);
            pre('Params: ' . print_r(self::redactSyncTokenForLog($params), true));
            pre('HTTP Code: ' . $httpCode);
            pre('CURL Error: ' . $curlError);
            pre('CURL Errno: ' . $curlErrno);
            pre('Raw Response: ' . self::truncateLog((string) $result));
        }

        if ($curlErrno) {
            self::writeOutboundTrace('sendRequest curl_error', [
                'action' => (string)($params['ACTION'] ?? ''),
                'errno' => $curlErrno,
                'error' => self::truncateLog($curlError, 500),
            ]);
            if ($debug) {
                pre('CURL Error occurred: ' . $curlError);
            } else {
                error_log('[OutboundRequest] CURL errno=' . $curlErrno . ' ' . $curlError);
            }

            return [
                'success' => 0,
                'error' => 'CURL Error: ' . $curlError,
                'errno' => $curlErrno,
                'error_code' => 'transport_curl_error',
                'http_status' => 0,
                'retryable' => true,
            ];
        }

        if ($httpCode !== 200) {
            $decodedError = \json_decode((string)$result, true);
            $errorCode = '';
            $reasonCode = '';
            if (\is_array($decodedError)) {
                $errorCode = (string)($decodedError['error_code'] ?? '');
                $reasonCode = (string)($decodedError['reason_code'] ?? '');
            }
            self::writeOutboundTrace('sendRequest http_not_200', [
                'action' => (string)($params['ACTION'] ?? ''),
                'http_code' => $httpCode,
                'error_code' => $errorCode,
                'reason_code' => $reasonCode,
                'body_head' => self::truncateLog((string) $result, 800),
            ]);
            if ($debug) {
                pre('HTTP Error: ' . $httpCode);
            }

            return [
                'success' => 0,
                'error' => 'HTTP Error: ' . $httpCode,
                'error_code' => $errorCode !== '' ? $errorCode : 'transport_http_error',
                'reason_code' => $reasonCode,
                'http_status' => $httpCode,
                'retryable' => \in_array((int)$httpCode, $retryCodes, true),
                'response' => $result,
            ];
        }

        $decodedResult = json_decode((string) $result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::writeOutboundTrace('sendRequest json_error', [
                'action' => (string)($params['ACTION'] ?? ''),
                'json_err' => json_last_error_msg(),
                'body_head' => self::truncateLog((string) $result, 800),
            ]);
            if ($debug) {
                pre('JSON Parse Error: ' . json_last_error_msg());
                pre('Raw response that failed to parse: ' . $result);
            }

            return [
                'success' => 0,
                'error' => 'JSON Parse Error: ' . json_last_error_msg(),
                'error_code' => 'transport_json_error',
                'http_status' => $httpCode,
                'retryable' => false,
                'raw_response' => $result,
            ];
        }

        if (!is_array($decodedResult)) {
            // Совместимость с legacy-ответами сайта вида "146731" (число/строка).
            $decodedResult = [
                'success' => 1,
                'data' => $decodedResult,
            ];
        }

        $decodedResult = self::normalizeContractOutcome($decodedResult, $httpCode);

        self::writeOutboundTrace('sendRequest ok', [
            'action' => (string)($params['ACTION'] ?? ''),
            'success' => $decodedResult['success'] ?? null,
            'error' => $decodedResult['error'] ?? null,
            'error_code' => $decodedResult['error_code'] ?? null,
            'reason_code' => $decodedResult['reason_code'] ?? null,
            'http_status' => $decodedResult['http_status'] ?? null,
            'retryable' => $decodedResult['retryable'] ?? null,
            'has_debug_trace' => isset($decodedResult['debug_trace']),
        ]);

        if ($debug) {
            pre('=== Parsed Response ===');
            pre($decodedResult);
            die();
        }

        return $decodedResult;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    protected static function normalizeContractOutcome(array $response, int $httpCode): array
    {
        if (!isset($response['http_status']) || (int)$response['http_status'] <= 0) {
            $response['http_status'] = $httpCode > 0 ? $httpCode : 200;
        } else {
            $response['http_status'] = (int)$response['http_status'];
        }
        $response['success'] = (int)($response['success'] ?? 0);
        $response['error_code'] = (string)($response['error_code'] ?? '');
        $response['reason_code'] = (string)($response['reason_code'] ?? '');
        if (!isset($response['error'])) {
            $response['error'] = '';
        }

        $transportOk = $response['http_status'] === 200;
        $domainOk = $response['success'] === 1;
        $isTransportError = !$transportOk || \in_array($response['error_code'], self::TRANSPORT_ERROR_CODES, true);
        if (!isset($response['retryable'])) {
            $response['retryable'] = $isTransportError && \in_array((int)$response['http_status'], [0, 429, 503], true);
        } else {
            $response['retryable'] = (bool)$response['retryable'];
        }
        $response['outcome'] = $isTransportError ? 'transport_error' : ($domainOk ? 'domain_success' : 'domain_failure');
        $response['transport_ok'] = $transportOk ? 1 : 0;
        $response['domain_ok'] = $domainOk ? 1 : 0;

        return $response;
    }
}
