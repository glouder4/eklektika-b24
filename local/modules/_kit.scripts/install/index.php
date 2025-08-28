<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;


class kit_scripts extends CModule
{
	const MODULE_ID = "kit.scripts";
	public $MODULE_ID = "kit.scripts";
	public $MODULE_VERSION = "1.0.0";
	public $MODULE_VERSION_DATE = "01.09.2022";
	public $MODULE_NAME = 'kit scripts';
	public $MODULE_DESCRIPTION = "kit scripts";
	public $errors;

	public function __construct()
	{
	}

	public function DoInstall()
	{
		ModuleManager::registerModule(self::MODULE_ID);
		$this->InstallEvents();
		return true;
	}

	public function DoUninstall()
	{
		$this->UnInstallEvents();
		ModuleManager::unRegisterModule(self::MODULE_ID);
		return true;
	}

	public function InstallEvents()
	{
		$eventManager = EventManager::getInstance();
		$eventManager->registerEventHandler(
			'crm',
			'OnAfterCrmDealAdd',
			self::MODULE_ID,
			"Kit\\Scripts\\Events",
			'OnAfterCrmDealAdd'
		);

		$eventManager->registerEventHandler(
			'crm',
			'OnAfterCrmDealUpdate',
			self::MODULE_ID,
			"Kit\\Scripts\\Events",
			'OnAfterCrmDealUpdate'
		);

        $eventManager->registerEventHandler(
            'crm',
            'OnAfterCrmLeadAdd',
            self::MODULE_ID,
            "Kit\\Scripts\\Events",
            'OnAfterCrmLeadAdd'
        );

        $eventManager->registerEventHandler(
            'crm',
            'OnBeforeCrmLeadUpdate',
            self::MODULE_ID,
            "Kit\\Scripts\\Events",
            'OnBeforeCrmLeadUpdate'
        );

        $eventManager->registerEventHandler(
            'crm',
            'onCrmTimelineCommentAdd',
            self::MODULE_ID,
            "Kit\\Scripts\\Events",
            'OnBeforeCrmLeadUpdate'
        );

        $eventManager->registerEventHandler(
            'crm',
            'onCrmDynamicItemAdd_133',
            self::MODULE_ID,
            "\\Kit\\Scripts\\EventHandler",
            'onSmartDesignCreate'
        );

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

        /**
         * Изменение артикула товара
         */
        $eventManager->registerEventHandlerCompatible(
            'iblock',
            'OnBeforeIBlockElementAdd',
            self::MODULE_ID,
            '\\Kit\\Scripts\\EventHandler',
            'OnBeforeIBlockElementAdd'
        );

        /**
         * Отправка уведомления в 1С при изменении воронки в смарт-процессе
         */
		$eventManager->registerEventHandler(
			"crm",
			"onCrmDynamicItemUpdate_133",
			self::MODULE_ID,
			"\\Kit\\Scripts\\EventHandler",
			"omSmartItemUpdate"
		);

        /**
         * Отправка уведомления в 1С при изменении воронки в сделке
         */
        $eventManager->registerEventHandler(
            'crm',
            'OnAfterDealMoveToCategory',
            self::MODULE_ID,
            "Kit\\Scripts\\EventHandler",
            'OnAfterDealMoveToCategory'
        );

        /**
         * Проверка на запрет работы с контрагентом при создании сделки
         */
        $eventManager->registerEventHandler(
            'crm',
            'OnBeforeCrmDealAdd',
            self::MODULE_ID,
            "Kit\\Scripts\\EventHandler",
            'OnBeforeCrmDealAdd'
        );

        /**
         * Проверка на запрет работы с контрагентом при редактировании сделки
         */
        $eventManager->registerEventHandler(
            'crm',
            'OnBeforeCrmDealUpdate',
            self::MODULE_ID,
            "Kit\\Scripts\\EventHandler",
            'OnBeforeCrmDealAdd'
        );

        /**
         * Проверка на то, может ли контакт быть запрещённым контрагентом
         */
        $eventManager->registerEventHandler(
            'crm',
            'OnBeforeCrmContactAdd',
            self::MODULE_ID,
            "Kit\\Scripts\\EventHandler",
            'OnBeforeCrmContactAdd'
        );

        /**
         * Проверка на то, может ли контакт быть запрещённым контрагентом (при обновлении)
         */
        $eventManager->registerEventHandler(
            'crm',
            'OnBeforeCrmContactUpdate',
            self::MODULE_ID,
            "Kit\\Scripts\\EventHandler",
            'OnBeforeCrmContactAdd'
        );

        /**
         * Проверка на то, может ли компания быть запрещённым контрагентом
         */
        $eventManager->registerEventHandler(
            'crm',
            'OnBeforeCrmCompanyAdd',
            self::MODULE_ID,
            "Kit\\Scripts\\EventHandler",
            'OnBeforeCrmCompanyAdd'
        );

        /**
         * Проверка на то, может ли компания быть запрещённым контрагентом (при обновлении)
         */
        $eventManager->registerEventHandler(
            'crm',
            'OnBeforeCrmCompanyUpdate',
            self::MODULE_ID,
            "Kit\\Scripts\\EventHandler",
            'OnBeforeCrmCompanyAdd'
        );

        /**
         * Проверка на запрет работы с контрагентом при создании смарт-процесса
         */
        $eventManager->registerEventHandler(
            'crm',
            'OnBeforeDynamicItemAdd_133',
            self::MODULE_ID,
            "\\Kit\\Scripts\\EventHandler",
            'OnBeforeDynamicItemAdd_133'
        );

        /**
         * Проверка на запрет работы с контрагентом при редактировании смарт-процесса
         */
        $eventManager->registerEventHandler(
            'crm',
            'OnBeforeDynamicItemUpdate_133',
            self::MODULE_ID,
            "\\Kit\\Scripts\\EventHandler",
            'OnBeforeDynamicItemAdd_133'
        );

        /**
         * Проверка на запрет работы с контрагентом при создании счёта
         */
        $eventManager->registerEventHandler(
            'crm',
            'OnBeforeSmartInvoiceAdd',
            self::MODULE_ID,
            "\\Kit\\Scripts\\EventHandler",
            'OnBeforeSmartInvoiceAdd'
        );

        /**
         * Проверка на запрет работы с контрагентом при редактировании счёта
         */
        $eventManager->registerEventHandler(
            'crm',
            'OnBeforeSmartInvoiceUpdate',
            self::MODULE_ID,
            "\\Kit\\Scripts\\EventHandler",
            'OnBeforeSmartInvoiceAdd'
        );

        /**
         * Проверка на запрет работы с контрагентом при создании счёта
         */
        $eventManager->registerEventHandler(
            'crm',
            'OnBeforeSmartInvoiceAdd',
            self::MODULE_ID,
            "\\Kit\\Scripts\\EventHandler",
            'OnBeforeSmartInvoiceAdd'
        );

        /**
         * После создания нового счёта
         */
        $eventManager->registerEventHandler(
            'crm',
            'onCrmDynamicItemAdd_' . CCrmOwnerType::SmartInvoice,
            self::MODULE_ID,
            "\\Kit\\Scripts\\EventHandler",
            'onAfterSmartInvoiceAdd'
        );
	}

	function UnInstallEvents()
	{
		$eventManager = EventManager::getInstance();

		$eventManager->unRegisterEventHandler(
			'crm',
			'OnAfterCrmDealAdd',
			self::MODULE_ID,
			"Kit\\Scripts\\Events",
			'OnAfterCrmDealAdd'
		);

		$eventManager->unRegisterEventHandler(
			'crm',
			'OnAfterCrmDealUpdate',
			self::MODULE_ID,
			"Kit\\Scripts\\Events",
			'OnAfterCrmDealUpdate'
		);
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnAfterCrmLeadAdd',
            self::MODULE_ID,
            "Kit\\Scripts\\Events",
            'OnAfterCrmLeadAdd'
        );

        $eventManager->unRegisterEventHandler(
            'crm',
            'OnBeforeCrmLeadUpdate',
            self::MODULE_ID,
            "Kit\\Scripts\\Events",
            'OnBeforeCrmLeadUpdate'
        );

        $eventManager->unRegisterEventHandler(
            'crm',
            'onCrmTimelineCommentAdd',
            self::MODULE_ID,
            "Kit\\Scripts\\Events",
            'OnBeforeCrmLeadUpdate'
        );

        $eventManager->unRegisterEventHandler(
            'crm',
            'onCrmDynamicItemAdd_133',
            self::MODULE_ID,
            "\\Kit\\Scripts\\EventHandler",
            'onSmartDesignCreate'
        );

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

        /**
         * Изменение артикула товара
         */
        $eventManager->unRegisterEventHandler(
            'iblock',
            'OnBeforeIBlockElementAdd',
            self::MODULE_ID,
            '\\Kit\\Scripts\\EventHandler',
            'OnBeforeIBlockElementAdd'
        );

        /**
         * Отправка уведомления в 1С при изменении воронки в смарт-процессе
         */
        $eventManager->unRegisterEventHandler(
            "crm",
            "onCrmDynamicItemUpdate_133",
            self::MODULE_ID,
            "\\Kit\\Scripts\\EventHandler",
            "omSmartItemUpdate"
        );

        /**
         * Отправка уведомления в 1С при изменении воронки в сделке
         */
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnAfterDealMoveToCategory',
            self::MODULE_ID,
            "Kit\\Scripts\\EventHandler",
            'OnAfterDealMoveToCategory'
        );

        /**
         * Проверка на запрет работы с контрагентом при создании сделки
         */
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnBeforeCrmDealAdd',
            self::MODULE_ID,
            "Kit\\Scripts\\EventHandler",
            'OnBeforeCrmDealAdd'
        );

        /**
         * Проверка на запрет работы с контрагентом при редактировании сделки
         */
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnBeforeCrmDealUpdate',
            self::MODULE_ID,
            "Kit\\Scripts\\EventHandler",
            'OnBeforeCrmDealAdd'
        );

        /**
         * Проверка на то, может ли контакт быть запрещённым контрагентом
         */
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnBeforeCrmContactAdd',
            self::MODULE_ID,
            "Kit\\Scripts\\EventHandler",
            'OnBeforeCrmContactAdd'
        );

        /**
         * Проверка на то, может ли контакт быть запрещённым контрагентом (при обновлении)
         */
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnBeforeCrmContactUpdate',
            self::MODULE_ID,
            "Kit\\Scripts\\EventHandler",
            'OnBeforeCrmContactAdd'
        );

        /**
         * Проверка на то, может ли компания быть запрещённым контрагентом
         */
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnBeforeCrmCompanyAdd',
            self::MODULE_ID,
            "Kit\\Scripts\\EventHandler",
            'OnBeforeCrmCompanyAdd'
        );

        /**
         * Проверка на то, может ли компания быть запрещённым контрагентом (при обновлении)
         */
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnBeforeCrmCompanyUpdate',
            self::MODULE_ID,
            "Kit\\Scripts\\EventHandler",
            'OnBeforeCrmCompanyAdd'
        );

        /**
         * Проверка на запрет работы с контрагентом при создании смарт-процесса
         */
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnBeforeDynamicItemAdd_133',
            self::MODULE_ID,
            "\\Kit\\Scripts\\EventHandler",
            'OnBeforeDynamicItemAdd_133'
        );

        /**
         * Проверка на запрет работы с контрагентом при редактировании смарт-процесса
         */
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnBeforeDynamicItemUpdate_133',
            self::MODULE_ID,
            "\\Kit\\Scripts\\EventHandler",
            'OnBeforeDynamicItemAdd_133'
        );

        /**
         * Проверка на запрет работы с контрагентом при создании счёта
         */
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnBeforeSmartInvoiceAdd',
            self::MODULE_ID,
            "\\Kit\\Scripts\\EventHandler",
            'OnBeforeSmartInvoiceAdd'
        );

        /**
         * Проверка на запрет работы с контрагентом при редактировании счёта
         */
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnBeforeSmartInvoiceUpdate',
            self::MODULE_ID,
            "\\Kit\\Scripts\\EventHandler",
            'OnBeforeSmartInvoiceAdd'
        );

        /**
         * После создания нового счёта
         */
        $eventManager->unRegisterEventHandler(
            'crm',
            'onCrmDynamicItemAdd_' . CCrmOwnerType::SmartInvoice,
            self::MODULE_ID,
            "\\Kit\\Scripts\\EventHandler",
            'onAfterSmartInvoiceAdd'
        );
	}
}
