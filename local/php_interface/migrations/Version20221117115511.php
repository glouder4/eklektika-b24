<?php

namespace Sprint\Migration;


use Bitrix\Main\EventManager;

class Version20221117115511 extends Version
{
    const MODULE_ID = "kit.scripts";

    protected $description = "Установка событий на обновление элемента инфоблока";

    protected $moduleVersion = "4.1.1";

    public function up()
    {
        $eventManager = EventManager::getInstance();

        $eventManager->registerEventHandlerCompatible(
            'iblock',
            'OnBeforeIBlockElementUpdate',
            self::MODULE_ID,
            '\\Kit\\Scripts\\EventHandler',
            'onBeforeIBlockElementUpdate'
        );

        $eventManager->registerEventHandlerCompatible(
            'iblock',
            'OnBeforeIBlockElementAdd',
            self::MODULE_ID,
            '\\Kit\\Scripts\\EventHandler',
            'onBeforeIBlockElementUpdate'
        );
    }

    public function down()
    {
        $eventManager = EventManager::getInstance();

        $eventManager->unRegisterEventHandler(
            'iblock',
            'OnBeforeIBlockElementUpdate',
            self::MODULE_ID,
            '\\Kit\\Scripts\\EventHandler',
            'onBeforeIBlockElementUpdate'
        );

        $eventManager->unRegisterEventHandler(
            'iblock',
            'OnBeforeIBlockElementAdd',
            self::MODULE_ID,
            '\\Kit\\Scripts\\EventHandler',
            'onBeforeIBlockElementUpdate'
        );
    }
}
