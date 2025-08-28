<?php

class kitconsulting_extendedproductsrow extends CModule
{
    var $MODULE_ID = "kitconsulting.extendedproductsrow";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $errors;

    function __construct()
    {
        $this->MODULE_VERSION = "0.0.1";
        $this->MODULE_VERSION_DATE = "09.09.2022";
        $this->MODULE_NAME = "Расширение ProductsRow";
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
//        $this->errors = $DB->RunSQLBatch(__DIR__.'/db/install.sql');

        if (!$this->errors) return true;
        else return $this->errors;
    }

    function UnInstallDB()
    {
        global $DB;

        $this->errors = false;
//        $this->errors = $DB->RunSQLBatch(__DIR__.'/db/uninstall.sql');

        if (!$this->errors) return true;
        else return $this->errors;
    }

    function DoUninstall()
    {
        $result = true;
        /*$result = $result && $this->UnInstallDB();*/
        $result = $result && $this->UnInstallEvents();
        $result = $result && $this->UnInstallFiles();

//        \CAgent::RemoveModuleAgents($this->MODULE_ID);

        UnRegisterModule($this->MODULE_ID);
        return $result;
    }

    function InstallFiles() {
//        CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/local/modules/kitconsulting.extendedproductsrow/install/components",
//            $_SERVER["DOCUMENT_ROOT"]."/local/components", true, true);
        return true;
    }

    function UnInstallFiles() {
//        DeleteDirFilesEx("/local/components/kitconsulting");
        return true;
    }

    function InstallEvents()
    {
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->registerEventHandler("crm", "onEntityDetailsTabsInitialized", $this->MODULE_ID, "\Kitconsulting\ExtendedProductsRow\DealHandler", "onEntityDetailsTabsInitialized");
//        $eventManager->registerEventHandler("rest", "OnRestServiceBuildDescription", $this->MODULE_ID, "\Kit\ExtendedProductsRow\Rest", "onRestServiceBuildDescription");
        return true;
    }

    function UnInstallEvents()
    {
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->unRegisterEventHandler("crm", "onEntityDetailsTabsInitialized", $this->MODULE_ID, "\Kitconsulting\ExtendedProductsRow\DealHandler", "onEntityDetailsTabsInitialized");
//        $eventManager->unRegisterEventHandler("rest", "OnRestServiceBuildDescription", $this->MODULE_ID, "\Kit\ExtendedProductsRow\Rest", "onRestServiceBuildDescription");
        return true;
    }
}
