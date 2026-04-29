<?php

namespace OnlineService\Sync\FromSite;

/**
 * Файловое хранилище dedup ключей входящего канала B24→сайт.
 *
 * См. `bitrix24-external-developers/inbound_dedup_storage_policy.md`.
 */
final class InboundDedupStore
{
    /**
     * @param array<string, mixed> $settings
     *
     * @return array{path: string, ttl: int}|null ttl в секундах; null если dedup выключен
     */
    public static function resolveStore(array $settings, string $documentRoot): ?array
    {
        $ttl = 0;
        if (!empty($settings['inbound_dedup_ttl_seconds']) && is_numeric($settings['inbound_dedup_ttl_seconds'])) {
            $ttl = (int)$settings['inbound_dedup_ttl_seconds'];
        }
        if ($ttl <= 0) {
            return null;
        }

        $path = '';
        if (!empty($settings['inbound_dedup_store_path']) && is_scalar($settings['inbound_dedup_store_path'])) {
            $path = (string)$settings['inbound_dedup_store_path'];
        }
        $path = trim($path);
        if ($path === '') {
            // Dev default (как описано во внешней политике).
            $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yomerch-inbound-dedup.json';
        }

        return [
            'path' => $path,
            'ttl' => $ttl,
        ];
    }

    /** @throws \Throwable */
    public static function isDuplicateThenRemember(string $path, string $dedupKey, int $ttlSeconds): bool
    {
        self::touchParentDirIfNeeded($path);

        $fp = @fopen($path, 'c+b');
        if ($fp === false) {
            // Fail-open: без dedup, чтобы не блокировать синхронизацию из-за FS.
            return false;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return false;
            }

            rewind($fp);
            $contents = stream_get_contents($fp);
            $data = [];
            if (is_string($contents) && $contents !== '') {
                /** @var mixed $decoded */
                $decoded = json_decode($contents, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }

            $now = time();
            foreach ($data as $k => $meta) {
                if (!is_string($k) || !is_array($meta)) {
                    unset($data[$k]);
                    continue;
                }
                $ts = isset($meta['ts']) && is_numeric($meta['ts']) ? (int)$meta['ts'] : 0;
                if ($ts <= 0 || ($now - $ts) > $ttlSeconds) {
                    unset($data[$k]);
                }
            }

            if (isset($data[$dedupKey])) {
                return true;
            }

            $data[$dedupKey] = ['ts' => $now];

            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($fp);

            return false;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private static function touchParentDirIfNeeded(string $path): void
    {
        $dir = dirname($path);
        if ($dir === '' || $dir === '.' || is_dir($dir)) {
            return;
        }
        @mkdir($dir, 0775, true);
    }
}
