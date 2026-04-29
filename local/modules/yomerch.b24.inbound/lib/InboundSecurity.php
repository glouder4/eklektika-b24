<?php

namespace OnlineService\Sync\FromSite;

/**
 * Авторизация и базовые security-проверки входящего HTTP-канала B24→сайт.
 *
 * Контракт см. `local/bitrix24-external-developers/BITRIX24_EXTERNAL_TEAM_HANDOFF.md` и `b24_site_contracts_yomerch.md`.
 */
final class InboundSecurity
{
    /** @var array<string, mixed>|null */
    private static ?array $parsedSettingsCache = null;

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    public static function loadInboundSettings(?string $documentRoot = null): array
    {
        if (self::$parsedSettingsCache !== null) {
            return [self::$parsedSettingsCache, (string)$documentRoot];
        }

        self::$parsedSettingsCache = \OnlineService\Sync\SiteConnectorLocalSettings::load($documentRoot);

        $root = $documentRoot;
        if (!is_string($root) || $root === '') {
            $root = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
        }
        $root = rtrim($root, '/');

        return [self::$parsedSettingsCache, $root];
    }

    /**
     * Извлекает секрет синхронизации.
     *
     * Совместимо с ключом `sync_token` (фактический source-of-truth в `site_sync_settings.local.php`)
     * и синонимом `inbound_secret` из описания контракта сайта (без смешения с git-tracked конфигами).
     */
    public static function resolveSecret(array $settings): string
    {
        foreach (['sync_token', 'inbound_secret'] as $key) {
            if (!empty($settings[$key]) && is_scalar($settings[$key])) {
                return (string)$settings[$key];
            }
        }
        return '';
    }

    /** Проверки, которые возможны только после чтения сырого тела (HMAC считается от raw body). */
    public static function assertInboundTransportSecurity(array $settings, string $secret, string $rawBody): void
    {
        $hmacSecret = '';
        if (!empty($settings['inbound_hmac_secret']) && is_scalar($settings['inbound_hmac_secret'])) {
            $hmacSecret = (string)$settings['inbound_hmac_secret'];
        }
        $hmacExpected = hash_hmac('sha256', $rawBody, $hmacSecret);

        // HMAC включается только если задан секрет подписи.
        if ($hmacSecret !== '') {
            $sig = '';
            if (isset($_SERVER['HTTP_X_SYNC_SIGNATURE']) && is_scalar($_SERVER['HTTP_X_SYNC_SIGNATURE'])) {
                $sig = trim((string)$_SERVER['HTTP_X_SYNC_SIGNATURE']);
            }
            if ($sig === '') {
                throw new \RuntimeException('sync_signature_missing');
            }

            // Поддержка hex/lowercase-хеша без префикса.
            if (!preg_match('/^[0-9a-f]{64}$/', strtolower($sig))) {
                throw new \RuntimeException('sync_signature_invalid');
            }
            $left = strtolower($sig);
            $right = strtolower($hmacExpected);
            if (!hash_equals($right, $left)) {
                throw new \RuntimeException('sync_signature_invalid');
            }
        }

    }

    /**
     * Skew проверяется после доступа и к заголовку, и к полю `sync_ts` в теле (если оно уже распаршено как payload).
     */
    public static function assertInboundClockSkew(array $settings, array $requestPayload = []): void
    {
        $skew = 0;
        if (!empty($settings['inbound_max_skew_seconds']) && is_numeric($settings['inbound_max_skew_seconds'])) {
            $skew = (int)$settings['inbound_max_skew_seconds'];
        }
        if ($skew <= 0) {
            return;
        }

        $tsCandidates = [];

        // Header (recommended).
        if (isset($_SERVER['HTTP_X_SYNC_TIMESTAMP']) && is_scalar($_SERVER['HTTP_X_SYNC_TIMESTAMP'])) {
            $tsCandidates[] = trim((string)$_SERVER['HTTP_X_SYNC_TIMESTAMP']);
        }

        // Body/query field (alternate).
        foreach (['_SYNC_TIMESTAMP', '_SYNC_TS', '_SYNC_TIME', '_SYNC_TIME_UNIX'] as $k) {
            if (!empty($requestPayload[$k]) && is_scalar($requestPayload[$k])) {
                $tsCandidates[] = trim((string)$requestPayload[$k]);
                break;
            }
        }

        foreach (['sync_ts', 'SYNC_TS', '_SYNC_TS', 'TIMESTAMP'] as $k) {
            if (!empty($requestPayload[$k]) && is_scalar($requestPayload[$k])) {
                $tsCandidates[] = trim((string)$requestPayload[$k]);
                break;
            }
        }

        $tsRaw = '';
        foreach ($tsCandidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                $tsRaw = $candidate;
                break;
            }
        }

        if ($tsRaw === '' || !ctype_digit($tsRaw)) {
            throw new \RuntimeException('sync_timestamp_invalid');
        }

        $clientTs = (int)$tsRaw;
        if ($clientTs <= 0) {
            throw new \RuntimeException('sync_timestamp_invalid');
        }
        $delta = abs(time() - $clientTs);
        if ($delta > $skew) {
            throw new \RuntimeException('sync_timestamp_expired');
        }
    }
}

