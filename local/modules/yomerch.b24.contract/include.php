<?php
defined('B_PROLOG_INCLUDED') || die();

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(null, [
    \OnlineService\Sync\UfMap::class => '/local/modules/yomerch.b24.contract/lib/UfMap.php',
    \OnlineService\Sync\Contract\CompanySyncReadService::class => '/local/modules/yomerch.b24.contract/lib/CompanySyncReadService.php',
    \OnlineService\Sync\Contract\CompanySyncNormalizeService::class => '/local/modules/yomerch.b24.contract/lib/CompanySyncNormalizeService.php',
    \OnlineService\Sync\Contract\CompanySyncPolicyService::class => '/local/modules/yomerch.b24.contract/lib/CompanySyncPolicyService.php',
    \OnlineService\Sync\Contract\CompanyPhoneUfMultifieldSync::class => '/local/modules/yomerch.b24.contract/lib/CompanyPhoneUfMultifieldSync.php',
]);
