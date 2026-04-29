<?php

if (!empty($GLOBALS['YOMERRCH24_B24_SITE_SYNC_HANDLERS_REGISTERED'])) {
    return;
}
$GLOBALS['YOMERRCH24_B24_SITE_SYNC_HANDLERS_REGISTERED'] = true;

$eventManager = \Bitrix\Main\EventManager::getInstance();

$eventManager->addEventHandlerCompatible(
    'iblock',
    'OnAfterIBlockElementAdd',
    [\OnlineService\StatusSync\CompanyStatusSync::class, 'addElementEvent']
);
$eventManager->addEventHandlerCompatible(
    'iblock',
    'OnAfterIBlockElementUpdate',
    [\OnlineService\StatusSync\CompanyStatusSync::class, 'updateElementEvent']
);

$eventManager->addEventHandlerCompatible(
    'crm',
    'OnBeforeCrmCompanyAdd',
    [\OnlineService\Sync\ToSite\CompanySync::class, 'onBeforeCompanyAdd']
);
$eventManager->addEventHandlerCompatible(
    'crm',
    'OnBeforeCrmCompanyUpdate',
    [\OnlineService\Sync\ToSite\CompanySync::class, 'onBeforeCompanyUpdate']
);
$eventManager->addEventHandlerCompatible(
    'crm',
    'OnAfterCrmCompanyAdd',
    [\OnlineService\Sync\ToSite\CompanySync::class, 'onAfterCompanyAdd']
);
$eventManager->addEventHandlerCompatible(
    'crm',
    'OnAfterCrmCompanyUpdate',
    [\OnlineService\Sync\ToSite\CompanySync::class, 'onAfterCompanyUpdate']
);
$eventManager->addEventHandlerCompatible(
    'crm',
    'OnBeforeCrmCompanyDelete',
    [\OnlineService\Sync\ToSite\CompanySync::class, 'onBeforeCompanyDelete']
);

$eventManager->addEventHandlerCompatible(
    'crm',
    'OnAfterCrmContactAdd',
    [\OnlineService\Sync\ToSite\ContactSync::class, 'onAfterContactAdd']
);
$eventManager->addEventHandlerCompatible(
    'crm',
    'OnAfterCrmContactUpdate',
    [\OnlineService\Sync\ToSite\ContactSync::class, 'onAfterContactUpdate']
);
$eventManager->addEventHandlerCompatible(
    'crm',
    'OnBeforeCrmContactDelete',
    [\OnlineService\Sync\ToSite\ContactSync::class, 'onBeforeContactDelete']
);

$eventManager->addEventHandlerCompatible(
    'main',
    'OnAfterUserUpdate',
    [\OnlineService\Sync\ToSite\ManagerUserSync::class, 'onAfterUserUpdate']
);
