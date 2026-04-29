<?php
defined('B_PROLOG_INCLUDED') || die();

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(null, [
    \OnlineService\Sync\FromSite\InboundEndpoint::class => '/local/modules/yomerch.b24.inbound/lib/InboundEndpoint.php',
    \OnlineService\Sync\FromSite\InboundActionDispatcher::class => '/local/modules/yomerch.b24.inbound/lib/InboundActionDispatcher.php',
    \OnlineService\Sync\FromSite\InboundPayloadNormalizer::class => '/local/modules/yomerch.b24.inbound/lib/InboundPayloadNormalizer.php',
]);
