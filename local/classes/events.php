<?php
    $eventManager = \Bitrix\Main\EventManager::getInstance();

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
        'OnAfterCrmContactUpdate',
        array('\OnlineService\Site\ContactUpdater', "OnAfterCrmContactAdd")
    );