<?php
    $eventManager = \Bitrix\Main\EventManager::getInstance();


    // Создание/обновление статуса компании (отдельная сущность)
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

    // Обновление компании
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
    );

    //Обновление сотрудника(менеджера)
    $eventManager->addEventHandlerCompatible(
        'main',
        'OnAfterUserUpdate',
        array('\OnlineService\Site\ManagerUpdater', "OnAfterUserUpdate")
    );