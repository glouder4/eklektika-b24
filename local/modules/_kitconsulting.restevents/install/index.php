<?php

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\EventManager;

class kitconsulting_restevents extends CModule
{
    const MODULE_ID = "kitconsulting.restevents";
    public $MODULE_ID = "kitconsulting.restevents";
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_CSS;
    public $errors;

    /**
     * @var EventManager
     */
    private $eventManager;

    private static $events = [
        [
            "rest",
            "OnRestServiceBuildDescription",
            self::MODULE_ID,
            "\\Kitconsulting\\Restevents\\Rest",
            "OnRestServiceBuildDescription"
        ],
        [
            "rest",
            "onRemoteDictionaryLoad",
            self::MODULE_ID,
            "\\Kitconsulting\\Restevents\\Rest",
            "onRemoteDictionaryLoad"
        ]
    ];

    public function __construct()
    {
        $this->MODULE_VERSION = "1.0.0";
        $this->MODULE_VERSION_DATE = "17.07.2024";
        $this->MODULE_NAME = Loc::getMessage('RESTEVENTS_HELPERS_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('RESTEVENTS_HELPERS_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage('RESTEVENTS_HELPERS_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('RESTEVENTS_HELPERS_PARTNER_URI');

        $this->eventManager = EventManager::getInstance();
    }

    /**
     * @return void
     */
    public function doInstall(): void
    {
        registerModule(static::MODULE_ID);
        $this->InstallEvents();
    }

    /**
     * @return void
     */
    public function doUninstall(): void
    {
        unRegisterModule(static::MODULE_ID);
        $this->UnInstallEvents();
    }

    /**
     * Register module events
     */
    public function InstallEvents()
    {
        foreach (self::$events as $event) {
            $this->eventManager->registerEventHandlerCompatible(...$event);
        }
    }

    /**
     * Unregister module events
     */
    public function UnInstallEvents()
    {
        foreach (self::$events as $event) {
            $this->eventManager->unRegisterEventHandlerCompatible(...$event);
        }
    }
}
