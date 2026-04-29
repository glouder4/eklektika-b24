<?php
defined('B_PROLOG_INCLUDED') || die();

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(null, [
    \OnlineService\Sync\ToSite\OutboundRequest::class => '/local/modules/yomerch.b24.outbound/lib/OutboundRequest.php',
    \OnlineService\Sync\ToSite\CompanySync::class => '/local/modules/yomerch.b24.outbound/lib/CompanySync.php',
    \OnlineService\Sync\ToSite\ContactSync::class => '/local/modules/yomerch.b24.outbound/lib/ContactSync.php',
    \OnlineService\Sync\ToSite\ManagerUserSync::class => '/local/modules/yomerch.b24.outbound/lib/ManagerUserSync.php',
    \OnlineService\Sync\ToSite\OutboundContactMarketingForSite::class => '/local/modules/yomerch.b24.outbound/lib/OutboundContactMarketingForSite.php',
]);
