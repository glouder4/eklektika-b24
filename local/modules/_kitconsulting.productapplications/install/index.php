<?php

class kitconsulting_productapplications extends \CModule {

    var $MODULE_ID = "kitconsulting.productapplications";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $errors;

    function __construct()
    {
        $this->MODULE_VERSION = "1.0.0";
        $this->MODULE_VERSION_DATE = "31.10.2022";
        $this->MODULE_NAME = "Нанесения для товаров";
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
        global $APPLICATION, $DB;

        $this->errors = false;

        $this->errors = $DB->RunSQLBatch(__DIR__.'/db/mysql/install.sql');

        if (is_array($this->errors))
        {
            $GLOBALS['errors'] = $this->errors;
            $APPLICATION->ThrowException(implode(' ', $this->errors));

            return false;
        }

        return true;
    }

    function UnInstallDB()
    {
        global $APPLICATION, $DB;

        $this->errors = false;

        $this->errors = $DB->RunSQLBatch(__DIR__.'/db/mysql/uninstall.sql');

        if (is_array($this->errors))
        {
            $GLOBALS['errors'] = $this->errors;
            $APPLICATION->ThrowException(implode(' ', $this->errors));

            return false;
        }

        return true;
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
//        $eventManager->registerEventHandler("crm", "onEntityDetailsTabsInitialized", $this->MODULE_ID, "\Kitconsulting\ExtendedProductsRow\DealHandler", "onEntityDetailsTabsInitialized");
        $eventManager->registerEventHandler("rest", "OnRestServiceBuildDescription", $this->MODULE_ID, "\Kitconsulting\ProductApplications\Rest", "onRestServiceBuildDescription");

        return true;
    }

    function UnInstallEvents()
    {
        $eventManager = \Bitrix\Main\EventManager::getInstance();
//        $eventManager->unRegisterEventHandler("crm", "onEntityDetailsTabsInitialized", $this->MODULE_ID, "\Kitconsulting\ExtendedProductsRow\DealHandler", "onEntityDetailsTabsInitialized");
        $eventManager->unRegisterEventHandler("rest", "OnRestServiceBuildDescription", $this->MODULE_ID, "\Kitconsulting\ProductApplicationsRest", "onRestServiceBuildDescription");

        return true;
    }
}
