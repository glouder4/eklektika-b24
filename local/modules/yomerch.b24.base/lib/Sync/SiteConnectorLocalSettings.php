<?php

namespace OnlineService\Sync;

/**
 * Локальные настройки интеграции сайта↔B24 на портале.
 *
 * Source-of-truth: `local/modules/yomerch.b24.siteconnector/site_sync_settings.local.php`
 * (не коммитится; на проде создаётся вручную).
 */
final class SiteConnectorLocalSettings
{
    /**
     * @return array<string, mixed>
     */
    public static function load(?string $documentRoot = null): array
    {
        $root = $documentRoot;
        if (!is_string($root) || $root === '') {
            $root = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
        }
        $root = rtrim($root, '/');
        if ($root === '') {
            return [];
        }

        $path = $root . '/local/modules/yomerch.b24.siteconnector/site_sync_settings.local.php';
        if (!is_file($path)) {
            return [];
        }

        /** @var mixed $included */
        $included = include $path;
        return is_array($included) ? $included : [];
    }
}
