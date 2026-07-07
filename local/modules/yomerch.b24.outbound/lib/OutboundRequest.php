<?php

namespace OnlineService\Sync\ToSite;

class OutboundRequest
{
    /** @var array<string, int> action:entityId → unix time последней отправки */
    private static array $outboundCircuitLastSentAt = [];

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
     * Отладка perf CompanySync: ?os_usersync_debug=1 (GET/REQUEST/POST или referer страницы).
     */
    public static function isOsUserSyncDebugEnabled(): bool
    {
        foreach (['$_GET', '$_REQUEST', '$_POST'] as $bucket) {
            $v = null;
            if ($bucket === '$_GET') {
                $v = $_GET['os_usersync_debug'] ?? null;
            } elseif ($bucket === '$_REQUEST') {
                $v = $_REQUEST['os_usersync_debug'] ?? null;
            } else {
                $v = $_POST['os_usersync_debug'] ?? null;
            }
            if ($v !== null && $v !== '' && self::parseBoolFlag($v)) {
                return true;
            }
        }
        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer !== '' && \preg_match('/(?:\?|&)os_usersync_debug=(?:1|true|yes|on)(?:&|$)/i', $referer)) {
            return true;
        }

        return false;
    }

    /** @var list<array<string, mixed>> */
    private static array $perfScreenBuffer = [];

    private static bool $perfScreenCollect = false;

    public static function beginPerfScreenCollect(): void
    {
        self::$perfScreenBuffer = [];
        self::$perfScreenCollect = true;
    }

    public static function endPerfScreenCollect(): void
    {
        self::$perfScreenCollect = false;
    }

    /**
     * Вывести накопленные метрики через pre() (перед die в конце onAfterCompanyUpdate).
     */
    public static function dumpPerfScreenBuffer(): void
    {
        if (self::$perfScreenBuffer === []) {
            if (\function_exists('pre')) {
                \pre('CompanySync perf: буфер пуст (событие не дошло до фаз или ранний return)');
            } else {
                echo '<pre>CompanySync perf: buffer empty</pre>';
            }

            return;
        }
        if (\function_exists('pre')) {
            \pre('=== CompanySync::perf buffer (' . \count(self::$perfScreenBuffer) . ' rows) ===');
            \pre(self::$perfScreenBuffer);
        } else {
            echo '<pre>' . \htmlspecialchars(
                (string)\json_encode(self::$perfScreenBuffer, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT),
                \ENT_QUOTES | \ENT_SUBSTITUTE,
                'UTF-8'
            ) . '</pre>';
        }
    }

    /**
     * Завершение onAfterCompanyUpdate в режиме os_usersync_debug (pre уже выведен, die).
     */
    public static function finishOsUserSyncDebugScreen(
        string $outcome,
        array $context = [],
        bool $dieWhenDebug = true
    ): void {
        if ($dieWhenDebug) {
            self::dumpPerfScreenBuffer();
        }
        if (!self::isOsUserSyncDebugEnabled()) {
            return;
        }
        if (!$dieWhenDebug) {
            return;
        }
        $payload = \array_merge(['outcome' => $outcome], $context);
        if (\function_exists('pre')) {
            \pre('=== CompanySync::onAfterCompanyUpdate END (os_usersync_debug) ===');
            \pre($payload);
        } else {
            echo '<pre>END os_usersync_debug ' . \htmlspecialchars(
                (string)\json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT),
                \ENT_QUOTES | \ENT_SUBSTITUTE,
                'UTF-8'
            ) . '</pre>';
        }
        echo 'exit';
        die();
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
     * Метрики скорости CompanySync: только при ?os_usersync_debug=1 → pre() на экран (без файлов).
     *
     * @param array<string, mixed> $context
     */
    public static function writePerfTrace(string $message, array $context = []): void
    {
        $row = \array_merge([
            'event' => $message,
            'perf_ts' => \date('Y-m-d H:i:s'),
            'os_usersync_debug_detected' => self::isOsUserSyncDebugEnabled(),
        ], $context);
        if (self::$perfScreenCollect) {
            self::$perfScreenBuffer[] = $row;
        }
        if (!self::isOsUserSyncDebugEnabled()) {
            return;
        }
        if (\function_exists('pre')) {
            \pre($row);
        } else {
            echo '<pre>' . \htmlspecialchars(
                (string)\json_encode($row, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT),
                \ENT_QUOTES | \ENT_SUBSTITUTE,
                'UTF-8'
            ) . '</pre>';
        }
    }

    /**
     * Компактный снимок POST для ?os_usersync_debug=1 (без die, в perf-буфер и на экран).
     *
     * @param array<string, mixed> $postParams
     * @return array<string, mixed>
     */
    public static function buildPerfOutboundPayloadPreview(array $postParams): array
    {
        $out = [];
        foreach ($postParams as $key => $value) {
            $key = (string)$key;
            if ($key === 'sync_token') {
                $out[$key] = ($value !== '' && $value !== null) ? '(set, redacted)' : '';

                continue;
            }
            if ($key === 'OS_REQUSITES_FILE' && \is_array($value)) {
                $out[$key] = '(CFile id=' . (int)($value['ID'] ?? 0) . ')';

                continue;
            }
            if (\is_array($value)) {
                $encoded = (string)\json_encode($value, \JSON_UNESCAPED_UNICODE);
                if (\strlen($encoded) > 240) {
                    $out[$key] = self::truncateLog($encoded, 240);
                } else {
                    $out[$key] = $value;
                }

                continue;
            }
            if (\is_scalar($value) || $value === null) {
                $s = (string)$value;
                $out[$key] = \strlen($s) > 300 ? self::truncateLog($s, 300) : $value;
            }
        }
        \ksort($out, \SORT_STRING);

        return $out;
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

    /**
     * URL исходящего POST на сайт (CRM→сайт).
     * Каноника на сайте: /local/modules/yomerch.b24.inbound/endpoint.php (InboundGateway → Company::updateCompanyElement).
     * Override: $GLOBALS['YOMERRCH24_B24_SITE_SYNC']['outbound_company_path'] (legacy site_ajax и т.п.).
     */
    public static function resolveOutboundQueryUrl(string $action): string
    {
        unset($action);
        $siteUrl = \rtrim((string)\YOMERRCH24_SITE_URL, '/');
        $path = '/local/modules/yomerch.b24.inbound/endpoint.php';
        if (isset($GLOBALS['YOMERRCH24_B24_SITE_SYNC']) && \is_array($GLOBALS['YOMERRCH24_B24_SITE_SYNC'])) {
            $override = $GLOBALS['YOMERRCH24_B24_SITE_SYNC']['outbound_company_path'] ?? null;
            if (\is_string($override) && $override !== '') {
                $path = $override[0] === '/' ? $override : '/' . $override;
            }
        }

        return $siteUrl . $path;
    }

    /**
     * Секрет для CRM→site POST. На сайте — inbound_secret (config.local.php), не обязательно тот же, что sync_token B24←site.
     */
    protected static function resolveOutboundSyncToken(): string
    {
        if (isset($GLOBALS['YOMERRCH24_B24_SITE_SYNC']) && \is_array($GLOBALS['YOMERRCH24_B24_SITE_SYNC'])) {
            foreach (['site_inbound_secret', 'inbound_secret', 'site_outbound_token'] as $key) {
                if (!empty($GLOBALS['YOMERRCH24_B24_SITE_SYNC'][$key]) && \is_scalar($GLOBALS['YOMERRCH24_B24_SITE_SYNC'][$key])) {
                    return (string)$GLOBALS['YOMERRCH24_B24_SITE_SYNC'][$key];
                }
            }
        }
        if (\defined('YOMERRCH24_SITE_OUTBOUND_TOKEN') && (string)\YOMERRCH24_SITE_OUTBOUND_TOKEN !== '') {
            return (string)\YOMERRCH24_SITE_OUTBOUND_TOKEN;
        }
        if (\defined('YOMERRCH24_SITE_SYNC_TOKEN') && (string)\YOMERRCH24_SITE_SYNC_TOKEN !== '') {
            return (string)\YOMERRCH24_SITE_SYNC_TOKEN;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function stripInternalOutboundParams(array $params): array
    {
        unset($params['_OUTBOUND_TIMEOUT_SEC'], $params['_OUTBOUND_MAX_ATTEMPTS']);

        return $params;
    }

    /**
     * Повторять только «временный» 429. 503 не ретраим никогда: nginx rate-limit (HTML) и PHP endpoint
     * (JSON sync_misconfigured и т.п.) — повторы только множат POST и усугубляют блокировку.
     */
    private static function isOutboundHttpResponseRetryable(int $httpCode, string $responseBody): bool
    {
        if ($httpCode === 403 || $httpCode === 503) {
            return false;
        }
        if ($httpCode !== 429) {
            return false;
        }
        $head = \strtolower(\substr($responseBody, 0, 2000));
        if (\str_contains($head, 'too many requests')
            || (\str_contains($head, '<html') && \str_contains($head, 'temporarily unavailable'))) {
            return false;
        }

        return true;
    }

    private static function resolveOutboundCircuitEntityId(string $action, array $params): int
    {
        if ($action === 'UPDATE_COMPANY' || $action === 'DELETE_COMPANY') {
            return (int)($params['OS_COMPANY_B24_ID'] ?? $params['ID'] ?? 0);
        }
        if ($action === 'UPDATE_CONTACT' || $action === 'DELETE_CONTACT') {
            return (int)($params['B24_ID'] ?? $params['CONTACT_ID'] ?? $params['ID'] ?? 0);
        }
        if ($action === 'UPDATE_MANAGER') {
            return (int)($params['BITRIX24_ID'] ?? $params['ID'] ?? $params['USER_ID'] ?? 0);
        }
        if ($action === 'UPDATE_GROUP') {
            return (int)($params['ID'] ?? 0);
        }

        return 0;
    }

    private static function resolveOutboundCallerHint(): string
    {
        $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 14);
        $parts = [];
        foreach ($trace as $frame) {
            $class = (string)($frame['class'] ?? '');
            $func = (string)($frame['function'] ?? '');
            if ($func === 'sendRequest' || $class === self::class) {
                continue;
            }
            if ($class !== '' || $func !== '') {
                $parts[] = ($class !== '' ? $class . '::' : '') . $func;
            }
            if (\count($parts) >= 3) {
                break;
            }
        }

        return \implode(' <- ', $parts);
    }

    private static function resolveOutboundCircuitCooldownSec(): int
    {
        $cfg = $GLOBALS['YOMERRCH24_B24_SITE_SYNC'] ?? [];
        if (\is_array($cfg) && isset($cfg['outbound_circuit_cooldown_sec'])) {
            $v = (int)$cfg['outbound_circuit_cooldown_sec'];
            if ($v >= 0 && $v <= 300) {
                return $v;
            }
        }

        // По умолчанию выключено: иначе в PHP-FPM static «залипает» между запросами и блокирует
        // UPDATE_COMPANY без ?os_usersync_debug=1 (там circuit раньше отключался отдельно).
        return 0;
    }

    private static function isOutboundCircuitOpen(string $action, int $entityId): bool
    {
        if ($entityId <= 0) {
            return false;
        }
        $cooldown = self::resolveOutboundCircuitCooldownSec();
        if ($cooldown <= 0) {
            return false;
        }
        $key = $action . ':' . $entityId;
        $last = self::$outboundCircuitLastSentAt[$key] ?? 0;

        return $last > 0 && (\time() - $last) < $cooldown;
    }

    private static function recordOutboundCircuitSent(string $action, int $entityId): void
    {
        if ($entityId <= 0 || self::resolveOutboundCircuitCooldownSec() <= 0) {
            return;
        }
        self::$outboundCircuitLastSentAt[$action . ':' . $entityId] = \time();
    }

    protected function sendRequest(array $params, bool $debug = false): array
    {
        $debug = $debug || self::isSiteSyncDebugEnabled();
        $trace = \OnlineService\SyncTraceContext::resolve($params);

        $action = (string)($params['ACTION'] ?? '');
        $circuitEntityId = self::resolveOutboundCircuitEntityId($action, $params);
        if ($action !== '' && self::isOutboundCircuitOpen($action, $circuitEntityId)) {
            self::writeOutboundTrace('sendRequest outbound_circuit_open', [
                'action' => $action,
                'entity_id' => $circuitEntityId,
                'cooldown_sec' => self::resolveOutboundCircuitCooldownSec(),
            ]);

            return [
                'success' => 0,
                'error' => 'outbound_circuit_open',
                'error_code' => 'outbound_circuit_open',
                'http_status' => 0,
                'retryable' => false,
                'outcome' => 'circuit_open',
            ];
        }

        // Для UPDATE_COMPANY дублируем CONTACT_IDS в OS_COMPANY_USERS / LEGAN_ENTITY_USERS.
        // CONTACT_IDS — идентификаторы на сайте (UF contact.delete_site_ref и т.п.), не CRM CONTACT_ID.
        if (($params['ACTION'] ?? '') === 'UPDATE_COMPANY') {
            $contactIds = self::normalizePositiveIntList($params['CONTACT_IDS'] ?? []);
            if ($contactIds !== []) {
                $params['OS_COMPANY_USERS'] = $contactIds;
                $params['LEGAN_ENTITY_USERS'] = $contactIds;
            }
        }

        $callerHint = self::resolveOutboundCallerHint();

        $headers = [];
        $headers[] = 'X-Correlation-ID: ' . (string)$trace['correlation_id'];
        $headers[] = 'X-Cutover-Label: ' . (string)$trace['cutover_label'];
        $headers[] = 'X-Sync-Trace-ID: ' . (string)$trace['trace_id'];
        if ($action !== '') {
            $headers[] = 'X-Yomerch-Outbound-Action: ' . $action;
        }
        if ($circuitEntityId > 0) {
            $headers[] = 'X-Yomerch-Outbound-Entity-Id: ' . $circuitEntityId;
        }
        if ($callerHint !== '') {
            $headers[] = 'X-Yomerch-Outbound-Caller: ' . \substr($callerHint, 0, 200);
        }
        $outboundToken = self::resolveOutboundSyncToken();
        if ($outboundToken !== '') {
            $params['sync_token'] = $outboundToken;
            $headers[] = 'X-Sync-Token: ' . $outboundToken;
        }
        $params['_SYNC_TRACE_ID'] = (string)$trace['trace_id'];
        $params['_SYNC_CUTOVER_LABEL'] = (string)$trace['cutover_label'];

        $queryUrl = self::resolveOutboundQueryUrl($action);
        if ($debug) {
            pre([
                'event' => 'OutboundRequest::sendRequest begin',
                'action' => $action,
                'entity_id' => $circuitEntityId,
                'caller' => $callerHint,
                'url' => $queryUrl,
            ]);
        }

        $curlTimeoutSec = 30;
        if (isset($params['_OUTBOUND_TIMEOUT_SEC'])) {
            $t = (int)$params['_OUTBOUND_TIMEOUT_SEC'];
            if ($t >= 3 && $t <= 60) {
                $curlTimeoutSec = $t;
            }
        }

        $curlConnectTimeoutSec = \min(10, $curlTimeoutSec);

        $retryDelaysUs = [1000000, 2000000, 4000000, 8000000];
        $attempts = \count($retryDelaysUs) + 1;
        if (isset($params['_OUTBOUND_MAX_ATTEMPTS'])) {
            $maxAttempts = (int)$params['_OUTBOUND_MAX_ATTEMPTS'];
            if ($maxAttempts >= 1 && $maxAttempts <= 5) {
                $attempts = $maxAttempts;
            }
        }

        $postParams = self::stripInternalOutboundParams($params);
        if (self::isOsUserSyncDebugEnabled()) {
            self::writePerfTrace('OutboundRequest::perf.sendRequest.payload', [
                'action' => $action,
                'entity_id' => $circuitEntityId,
                'url' => $queryUrl,
                'payload' => self::buildPerfOutboundPayloadPreview($postParams),
            ]);
        }
        $queryData = \http_build_query($postParams);

        $retryCodes = [429];
        $result = '';
        $httpCode = 0;
        $curlError = '';
        $curlErrno = 0;
        $curlStartedAt = \microtime(true);

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
                CURLOPT_TIMEOUT => $curlTimeoutSec,
                CURLOPT_CONNECTTIMEOUT => $curlConnectTimeoutSec,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $result = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            $curlErrno = curl_errno($curl);
            curl_close($curl);

            $shouldRetry = ($curlErrno === 0)
                && \in_array((int)$httpCode, $retryCodes, true)
                && self::isOutboundHttpResponseRetryable((int)$httpCode, (string)$result)
                && $attempt < $attempts;
            if (!$shouldRetry) {
                break;
            }
            $baseDelayUs = $retryDelaysUs[\min($attempt - 1, \count($retryDelaysUs) - 1)];
            $jitterUs = random_int(0, 300000);
            $sleepUs = $baseDelayUs + $jitterUs;
            usleep($sleepUs);
        }

        if ($action !== '' && $circuitEntityId > 0 && $curlErrno === 0 && (int)$httpCode === 200) {
            self::recordOutboundCircuitSent($action, $circuitEntityId);
        }

        if ($action === 'UPDATE_COMPANY') {
            $perfContext = [
                'company_b24_id' => (int)($postParams['OS_COMPANY_B24_ID'] ?? 0),
                'elapsed_ms' => (int)\round((microtime(true) - $curlStartedAt) * 1000),
                'http_code' => (int)$httpCode,
                'curl_errno' => (int)$curlErrno,
                'attempts' => (int)$attempt,
                'contact_ids_count' => \count(self::normalizePositiveIntList($postParams['CONTACT_IDS'] ?? [])),
                'request_url' => $queryUrl,
            ];
            if ((int)$httpCode !== 200) {
                $bodyStr = (string)$result;
                if (!self::isOutboundHttpResponseRetryable((int)$httpCode, $bodyStr)) {
                    $perfContext['retryable'] = 0;
                    $perfContext['rate_limit_or_nginx'] = 1;
                }
                if (self::isOsUserSyncDebugEnabled()) {
                    $perfContext['response_body_head'] = self::truncateLog($bodyStr, 800);
                }
            }
            self::writePerfTrace('OutboundRequest::perf.UPDATE_COMPANY.http', $perfContext);
        }

        if ($debug) {
            pre('=== CURL Request Details ===');
            pre('URL: ' . $queryUrl);
            pre('Params: ' . print_r(self::redactSyncTokenForLog($postParams), true));
            pre('HTTP Code: ' . $httpCode);
            pre('CURL Error: ' . $curlError);
            pre('CURL Errno: ' . $curlErrno);
            pre('Raw Response: ' . self::truncateLog((string) $result));
        }

        if ($curlErrno) {
            self::writeOutboundTrace('sendRequest curl_error', [
                'action' => $action,
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
                'action' => $action,
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
                'action' => $action,
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
            'action' => $action,
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
