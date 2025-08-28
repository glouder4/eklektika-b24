<?php
use Bitrix\Main\Loader;

class kitconsulting_productapplicationsextension extends CModule
{
    var $MODULE_ID = "kitconsulting.productapplicationsextension";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $errors;

    function __construct()
    {
        $this->MODULE_VERSION = "0.0.1";
        $this->MODULE_VERSION_DATE = "27.12.2022";
        $this->MODULE_NAME = "Расширение модуля для нанесений";
        $this->MODULE_DESCRIPTION = "Устанавливается после kitconsulting.productapplications";
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
        Bitrix\Main\Loader::includeModule('crm');

        $this->errors = false;

        $arFields = [
            'UF_APPLICATION_PARENT_PRODUCT_ROW_ID' => [
                'ENTITY_ID' => 'CRM_PRODUCT_ROW',
                'FIELD_NAME' => 'UF_APPLICATION_PARENT_PRODUCT_ROW_ID',
                'USER_TYPE_ID' => 'integer',
                'MANDATORY' => 'Y',
                'EDIT_FORM_LABEL' => ['ru' => 'Родительский товар'],
                'LIST_COLUMN_LABEL' => ['ru'=> 'Родительский товар'],
                'LIST_FILTER_LABEL' => ['ru'=> 'Родительский товар'],
                'ERROR_MESSAGE' => ['ru' => ''],
                'HELP_MESSAGE' => ['ru' => '']
            ],
            'UF_APPLICATION_PRICE' => [
                'ENTITY_ID' => 'CRM_PRODUCT_ROW',
                'FIELD_NAME' => 'UF_APPLICATION_PRICE',
                'SETTINGS' => [
                    'DEFAULT_VALUE' => '0|RUB',
                ],
                'USER_TYPE_ID' => 'money',
                'MANDATORY' => 'Y',
                'EDIT_FORM_LABEL' => ['ru' => 'Настройка тиража'],
                'LIST_COLUMN_LABEL' => ['ru'=> 'Настройка тиража'],
                'LIST_FILTER_LABEL' => ['ru'=> 'Настройка тиража'],
                'ERROR_MESSAGE' => ['ru' => ''],
                'HELP_MESSAGE' => ['ru' => '']
            ],
            /*'UF_SMART_PROCESS_ID' => [
                'ENTITY_ID' => 'CRM_PRODUCT_ROW',
                'FIELD_NAME' => 'UF_SMART_PROCESS_ID',
                'SETTINGS' => [
                    'DEFAULT_VALUE' => '0|RUB',
                ],
                'USER_TYPE_ID' => 'money',
                'MANDATORY' => 'N',
                'EDIT_FORM_LABEL' => ['ru' => 'Реализация проекта'],
                'LIST_COLUMN_LABEL' => ['ru'=> 'Реализация проекта'],
                'LIST_FILTER_LABEL' => ['ru'=> 'Реализация проекта'],
                'ERROR_MESSAGE' => ['ru' => ''],
                'HELP_MESSAGE' => ['ru' => '']
            ],*/
            'UF_ADV_AGENT' => [
                'ENTITY_ID' => 'CRM_DEAL',
                'FIELD_NAME' => 'UF_ADV_AGENT',
                'USER_TYPE_ID' => 'boolean',
                'MANDATORY' => 'N',
                'EDIT_FORM_LABEL' => ['ru' => 'Это рекламный агент'],
                'LIST_COLUMN_LABEL' => ['ru'=> 'Это рекламный агент'],
                'LIST_FILTER_LABEL' => ['ru'=> 'Это рекламный агент'],
                'ERROR_MESSAGE' => ['ru' => ''],
                'HELP_MESSAGE' => ['ru' => '']
            ],
            'UF_AVAILABLE_MINIMAL_PRICE' => [
                'ENTITY_ID' => 'CRM_DEAL',
                'FIELD_NAME' => 'UF_AVAILABLE_MINIMAL_PRICE',
                'USER_TYPE_ID' => 'boolean',
                'MANDATORY' => 'N',
                'EDIT_FORM_LABEL' => ['ru' => 'Разрешаю в этой сделке ставить цену ниже минимальной'],
                'LIST_COLUMN_LABEL' => ['ru'=> 'Разрешаю в этой сделке ставить цену ниже минимальной'],
                'LIST_FILTER_LABEL' => ['ru'=> 'Разрешаю в этой сделке ставить цену ниже минимальной'],
                'ERROR_MESSAGE' => ['ru' => ''],
                'HELP_MESSAGE' => ['ru' => '']
            ],
        ];
        $types = Bitrix\Crm\Service\Container::getInstance()->getDynamicTypesMap()->load(['isLoadStages' => true, 'isLoadCategories' => true]);

        foreach ($types->getTypes() as $type) {
            if ($type->getTitle() === 'Реализация проекта') {
                $arFields['UF_SMART_PROCESS_ID'] = [
                    'ENTITY_ID' => 'CRM_PRODUCT_ROW',
                    'FIELD_NAME' => 'UF_SMART_PROCESS_ID',
                    'SETTINGS' => [
                        'DYNAMIC_'.$type->getEntityTypeId() => "Y",
                    ],
                    'USER_TYPE_ID' => 'crm',
                    'MANDATORY' => 'N',
                    'EDIT_FORM_LABEL' => ['ru' => 'Реализация проекта'],
                    'LIST_COLUMN_LABEL' => ['ru'=> 'Реализация проекта'],
                    'LIST_FILTER_LABEL' => ['ru'=> 'Реализация проекта'],
                    'ERROR_MESSAGE' => ['ru' => ''],
                    'HELP_MESSAGE' => ['ru' => '']
                ];
                break;
            }
        }

        foreach($arFields as $arField) (new CUserTypeEntity)->Add($arField);

        if (!$this->errors) return true;
        else return $this->errors;
    }

    function UnInstallDB()
    {
        global $DB;

        $this->errors = false;
        $result = CUserTypeEntity::GetList([], ['FIELD_NAME' => 'UF_APPLICATION_PARENT_PRODUCT_ROW_ID'])->Fetch();

        if (is_array($result)) (new CUserTypeEntity())->Delete($result['ID']);
        $result = CUserTypeEntity::GetList([], ['FIELD_NAME' => 'UF_APPLICATION_PRICE'])->Fetch();

        if (is_array($result)) (new CUserTypeEntity())->Delete($result['ID']);
        $result = CUserTypeEntity::GetList([], ['FIELD_NAME' => 'UF_ADV_AGENT'])->Fetch();

        if (is_array($result)) (new CUserTypeEntity())->Delete($result['ID']);
        $result = CUserTypeEntity::GetList([], ['FIELD_NAME' => 'UF_AVAILABLE_MINIMAL_PRICE'])->Fetch();

        if (is_array($result)) (new CUserTypeEntity())->Delete($result['ID']);
        $result = CUserTypeEntity::GetList([], ['FIELD_NAME' => 'UF_SMART_PROCESS_ID'])->Fetch();

        if (is_array($result)) (new CUserTypeEntity())->Delete($result['ID']);

        if (!$this->errors) return true;
        else return $this->errors;
    }

    function DoUninstall()
    {
        $result = true;
        $result = $result && $this->UnInstallDB();
        $result = $result && $this->UnInstallEvents();
        $result = $result && $this->UnInstallFiles();

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
            "crm",
            "onCrmDynamicItemAdd",
            $this->MODULE_ID,
            "\Kitconsulting\ProductApplicationsExtension\Events",
            "onCrmDynamicItemAdd"
        );
        $eventManager->registerEventHandler(
            "crm",
            "onCrmDynamicItemUpdate",
            $this->MODULE_ID,
            "\Kitconsulting\ProductApplicationsExtension\Events",
            "onCrmDynamicItemUpdate"
        );
        $eventManager->registerEventHandler(
            "rest",
            "OnRestServiceBuildDescription",
            $this->MODULE_ID,
            "\Kitconsulting\ProductApplicationsExtension\Events",
            "onRestServiceBuildDescription"
        );
        $eventManager->registerEventHandler(
            "crm",
            "OnBeforeCrmDealUpdate",
            $this->MODULE_ID,
            "\Kitconsulting\ProductApplicationsExtension\Events",
            "OnBeforeCrmDealUpdate"
        );
        return true;
    }

    function UnInstallEvents()
    {
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
            "crm",
            "onCrmDynamicItemAdd",
            $this->MODULE_ID,
            "\Kitconsulting\ProductApplicationsExtension\Events",
            "onCrmDynamicItemAdd"
        );
        $eventManager->unRegisterEventHandler(
            "crm",
            "onCrmDynamicItemUpdate",
            $this->MODULE_ID,
            "\Kitconsulting\ProductApplicationsExtension\Events",
            "onCrmDynamicItemUpdate"
        );
        $eventManager->unRegisterEventHandler(
            "rest",
            "OnRestServiceBuildDescription",
            $this->MODULE_ID,
            "\Kitconsulting\ProductApplicationsExtension\Events",
            "onRestServiceBuildDescription"
        );
        $eventManager->unRegisterEventHandler(
            "crm",
            "OnBeforeCrmDealUpdate",
            $this->MODULE_ID,
            "\Kitconsulting\ProductApplicationsExtension\Events",
            "OnBeforeCrmDealUpdate"
        );
        return true;
    }
}
