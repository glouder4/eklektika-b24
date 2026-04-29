<?php
defined('B_PROLOG_INCLUDED') || die();

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(null, [
    \OnlineService\LocalApplicationHandler::class => '/local/modules/yomerch.b24.base/lib/LocalApplicationHandler.php',
    \OnlineService\Rest\RestCall::class => '/local/modules/yomerch.b24.base/lib/RestCall.php',
    \OnlineService\SyncTraceContext::class => '/local/modules/yomerch.b24.base/lib/SyncTraceContext.php',
]);
