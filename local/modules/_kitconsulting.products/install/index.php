<?php

class kitconsulting_products extends CModule
{
    var $MODULE_ID = "kitconsulting.products";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $errors;

    function __construct()
    {
        $this->MODULE_VERSION = "0.0.1";
        $this->MODULE_VERSION_DATE = "03.05.2024";
        $this->MODULE_NAME = "Расширение Products";
        $this->MODULE_DESCRIPTION = "";
        $this->PARTNER_NAME = "KIT Consulting";
        $this->PARTNER_URI = "https://kit-consulting.ru/";
    }

    function DoInstall()
    {
        $result = true;
        $result = $result && $this->InstallDB();
        $result = $result && $this->InstallEvents();
        $result = $result && $this->InstallFiles();

        RegisterModule($this->MODULE_ID);

        return $result;
    }

    function InstallDB()
    {
        global $DB;

        $this->errors = false;

        if (!$this->errors) return true;
        else return $this->errors;
    }

    function UnInstallDB()
    {
        global $DB;

        $this->errors = false;

        if (!$this->errors) return true;
        else return $this->errors;
    }

    function DoUninstall()
    {
        $result = true;
        $result = $result && $this->UnInstallEvents();
        $result = $result && $this->UnInstallFiles();
        $result = $result && $this->UnInstallDB();

        UnRegisterModule($this->MODULE_ID);
        return $result;
    }

    function InstallFiles() {
        return true;
    }

    function UnInstallFiles() {
        return true;
    }

    function InstallEvents()
    {
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->registerEventHandler(
            "rest",
            "OnRestServiceBuildDescription",
            $this->MODULE_ID,
            "\Kitconsulting\Products\Rest",
            "onRestServiceBuildDescription"
        );
        return true;
    }

    function UnInstallEvents()
    {
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
            "rest",
            "OnRestServiceBuildDescription",
            $this->MODULE_ID,
            "\Kitconsulting\Products\Rest",
            "onRestServiceBuildDescription"
        );
        return true;
    }
}
