<?php

$eventManager = \Bitrix\Main\EventManager::getInstance();

/*// Создание/обновление статуса компании (отдельная сущность)
$eventManager->addEventHandlerCompatible(
    'iblock',
    'OnAfterIBlockElementAdd',
    array('\OnlineService\Site\CompanyStatusUpdater', "addElementEvent")
);

$eventManager->addEventHandlerCompatible(
    'iblock',
    'OnAfterIBlockElementUpdate',
    array('\OnlineService\Site\CompanyStatusUpdater', "updateElementEvent")
);

$eventManager->addEventHandlerCompatible(
    'crm',
    'OnAfterCrmCompanyUpdate',
    array('\OnlineService\Site\CompanyUpdater', "updateCompany")
);

$eventManager->addEventHandlerCompatible(
    'crm',
    'OnBeforeCrmCompanyDelete',
    array('\OnlineService\Site\CompanyUpdater', "deleteCompany")
);


//Обновление контакта
$eventManager->addEventHandlerCompatible(
    'crm',
    'OnAfterCrmContactUpdate',
    array('\OnlineService\Site\ContactUpdater', "OnAfterCrmContactAdd")
);

$eventManager->addEventHandlerCompatible(
    'crm',
    'OnBeforeCrmContactDelete',
    array('\OnlineService\Site\ContactUpdater', "OnBeforeCrmContactDelete")
);*/

// Обновление компании
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

// Обновление контакта CRM -> сайт
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

// Обновление сотрудника (менеджера) после изменения пользователя в CRM | Выполнено
$eventManager->addEventHandlerCompatible(
    'main',
    'OnAfterUserUpdate',
    [\OnlineService\Sync\ToSite\ManagerUserSync::class, 'onAfterUserUpdate']
);