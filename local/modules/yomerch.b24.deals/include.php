<?php
defined('B_PROLOG_INCLUDED') || die();

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(null, [
    \OnlineService\DealExpiryHandler::class => '/local/modules/yomerch.b24.deals/lib/DealExpiryHandler.php',
    \OnlineService\DealExpiryAgent::class => '/local/modules/yomerch.b24.deals/lib/DealExpiryAgent.php',
    \OnlineService\DealExpiryRulesService::class => '/local/modules/yomerch.b24.deals/lib/DealExpiryRulesService.php',
]);
