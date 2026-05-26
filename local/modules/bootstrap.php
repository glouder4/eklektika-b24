<?php

require_once __DIR__ . '/yomerch.b24.siteconnector/site_sync_settings.php';
require_once __DIR__ . '/yomerch.b24.base/lib/SyncTraceContext.php';

if (!defined('YOMERRCH24_SYNC_CUTOVER_LABEL')) {
    define('YOMERRCH24_SYNC_CUTOVER_LABEL', \OnlineService\SyncTraceContext::CUTOVER_LABEL);
}

$bootstrapTrace = \OnlineService\SyncTraceContext::resolve();
if (!defined('YOMERRCH24_SYNC_CORRELATION_ID')) {
    define('YOMERRCH24_SYNC_CORRELATION_ID', (string)$bootstrapTrace['correlation_id']);
}

$moduleIncludeFiles = glob(__DIR__ . '/*/include.php') ?: [];
sort($moduleIncludeFiles, SORT_STRING);
foreach ($moduleIncludeFiles as $moduleIncludeFile) {
    require_once $moduleIncludeFile;
}

if (class_exists(\OnlineService\Site\CatalogPriceFloor::class)) {
    \OnlineService\Site\CatalogPriceFloor::markCompositeNonCacheableForAuthorizedCatalog();
}
