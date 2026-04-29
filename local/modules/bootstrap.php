<?php

require_once __DIR__ . '/../sync/site_sync_settings.php';

$moduleIncludeFiles = glob(__DIR__ . '/*/include.php') ?: [];
sort($moduleIncludeFiles, SORT_STRING);
foreach ($moduleIncludeFiles as $moduleIncludeFile) {
    require_once $moduleIncludeFile;
}

if (class_exists(\OnlineService\Site\CatalogPriceFloor::class)) {
    \OnlineService\Site\CatalogPriceFloor::markCompositeNonCacheableForAuthorizedCatalog();
}

$GLOBALS['OS_BREADCRUMBS_ADD_CONTAINER'] = 'Y';
