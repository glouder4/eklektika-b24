<?php
defined('B_PROLOG_INCLUDED') || die();

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(null, [
    \OnlineService\StatusSync\OutboundBase::class => '/local/modules/yomerch.b24.statussync/lib/OutboundBase.php',
    \OnlineService\StatusSync\CompanyStatusSync::class => '/local/modules/yomerch.b24.statussync/lib/CompanyStatusSync.php',
]);
