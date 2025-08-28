<?php

namespace Sprint\Migration;


use Bitrix\Main\EventManager;
use Bitrix\Main\Config\Option;

class Version20240827160115 extends Version
{
    const MODULE_ID = "kit.scripts";

    protected $description = "Установка события до создания товара";

    protected $moduleVersion = "4.1.1";

    public function up() {
        $eventManager = EventManager::getInstance();
        $eventManager->registerEventHandlerCompatible(
            'catalog',
            'OnBeforeProductAdd',
            self::MODULE_ID,
            '\\Kit\\Scripts\\EventHandler',
            'onBeforeProductAdd'
        );
        Option::set('kit.scripts', 'DEFAULT_VAT_ID', 4);
    }

    public function down() {
        $eventManager = EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
            'catalog',
            'OnBeforeProductAdd',
            self::MODULE_ID,
            '\\Kit\\Scripts\\EventHandler',
            'onBeforeProductAdd'
        );
    }
}
