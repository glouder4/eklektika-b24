<?php

namespace Kitconsulting\ExtendedProductsRow;

use Bitrix\Crm\Category\DealCategory;
use Bitrix\Crm\Component\EntityDetails\ProductList;
use Bitrix\Crm\Integration\Main\UISelector\CrmSmartInvoices;
use Bitrix\Crm\Item;
use Bitrix\Crm\ItemIdentifier;
use Bitrix\Crm\Product\Url\ProductBuilder;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Service\EditorAdapter;
use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Request;
use CCrmOwnerTypeAbbr;
use CCrmOwnerType;
use CCrmPaySystem;
use Kitconsulting\ExtendedProductsRow\Component\ExtendedProductList;
use Kitconsulting\ProductApplicationsExtension\Helper;

class DealHandler
{
    public static function onEntityDetailsTabsInitialized(Event $event)
    {
        global $APPLICATION;
        $parameters = $event->getParameters();
        $entityTypeID = $event->getParameter('entityTypeID');
        $entityId = $event->getParameter('entityID');
        $categoryId = $event->getParameter('categoryId') ?? 0;

        if (!\Bitrix\Main\Loader::includeModule('kitconsulting.productapplicationsextension'))
        {
            $smartProcessHelper = null;
        }
        else {
            $smartProcessHelper = new Helper();
        }

        if(!in_array($entityTypeID, [\CCrmOwnerType::Deal, \CCrmOwnerType::SmartInvoice]) && !isset($smartProcessHelper))
        {
            return new EventResult(EventResult::SUCCESS, $parameters);
        } elseif (
            isset($smartProcessHelper)
            && !in_array($entityTypeID, [\CCrmOwnerType::Deal, \CCrmOwnerType::SmartInvoice, $smartProcessHelper->getType()->getEntityTypeId()])
        ) {
            return new EventResult(EventResult::SUCCESS, $parameters);
        }

        if ($entityTypeID == \CCrmOwnerType::Deal) {
            $componentSettings = self::getDealComponentSettings($entityId, $entityTypeID);
        } else {
            $componentSettings = self::getSmartProcessComponentSettings($entityId, $entityTypeID, $categoryId);
        }

        foreach ($parameters['tabs'] as $i => $tab) {
            if($tab['id'] == 'tab_products') {
                ob_start();
                $APPLICATION->IncludeComponent(
                    'kitconsulting:crm.entity.extendedproduct.list',
                    '.default',
                    $componentSettings,
                    false,
                    [
                        'HIDE_ICONS' => 'Y',
                        'ACTIVE_COMPONENT' => 'Y',
                    ]
                );

                $parameters['tabs'][$i] = [
                    'id' => 'tab_products',
                    'name' => 'Товары',
                    'enabled' => $entityId > 0,
                    'html' => ob_get_clean()
                ];
            }
        }

        return new EventResult(EventResult::SUCCESS, $parameters);
    }

    public static function resolvePersonTypeID(array $entityData)
    {
        $companyID = isset($entityData['COMPANY_ID']) ? (int)$entityData['COMPANY_ID'] : 0;
        $personTypes = CCrmPaySystem::getPersonTypeIDs();
        $personTypeID = 0;
        if (isset($personTypes['COMPANY']) && isset($personTypes['CONTACT']))
        {
            $personTypeID = $companyID > 0 ? $personTypes['COMPANY'] : $personTypes['CONTACT'];
        }

        return $personTypeID;
    }

    public static function getDealComponentSettings($entityId, $entityTypeID)
    {
        $entityData = [];
        $entityTypeAbbr = \CCrmOwnerTypeAbbr::ResolveByTypeID($entityTypeID);

        $dbResult = \CCrmDeal::GetListEx(
            array(),
            array('=ID' => $entityId, 'CHECK_PERMISSIONS' => 'N')
        );

        if(is_object($dbResult) && $dbResult->SelectedRowsCount() > 0)
        {
            $entityData = $dbResult->Fetch();
        }

        $currencyID = \CCrmCurrency::GetBaseCurrencyID();
        if(isset($entityData['CURRENCY_ID']) && $entityData['CURRENCY_ID'] !== '')
        {
            $currencyID = $entityData['CURRENCY_ID'];
        }

        $entityInfo = array(
            'ENTITY_ID' => $entityId,
            'ENTITY_TYPE_ID' => $entityData['IS_RECURRING'] !== "Y" ? CCrmOwnerType::Deal : CCrmOwnerType::DealRecurring,
            'ENTITY_TYPE_NAME' =>  $entityData['IS_RECURRING'] !== "Y" ? CCrmOwnerType::DealName : CCrmOwnerType::DealRecurringName,
            'ENTITY_TYPE_CODE' => CCrmOwnerTypeAbbr::Deal,
            'TITLE' => isset($entityData['TITLE']) ? $entityData['TITLE'] : '',
            'SHOW_URL' => CCrmOwnerType::GetEntityShowPath(CCrmOwnerType::Deal, $entityId, false),
            'ORDER_LIST' => $entityData['ORDER_LIST'],
        );

        $isTaxMode = \CCrmTax::isTaxMode();

        return [
            'INTERNAL_FILTER' => [
                'OWNER_ID' => $entityInfo['ENTITY_ID'],
                'OWNER_TYPE' => $entityInfo['ENTITY_TYPE_CODE']
            ],
            'PATH_TO_ENTITY_PRODUCT_LIST' => ExtendedProductList::getComponentUrl(
                ['site' => SITE_ID],
                bitrix_sessid_get()
            ),
            'ACTION_URL' => ExtendedProductList::getLoaderUrl(
                ['site' => SITE_ID],
                bitrix_sessid_get()
            ),
            'ENTITY_ID' => $entityInfo['ENTITY_ID'],
            'ENTITY_TYPE_NAME' => $entityInfo['ENTITY_TYPE_NAME'],
            'ENTITY_TITLE' => $entityInfo['TITLE'],
            'CUSTOM_SITE_ID' => SITE_ID,
            'CUSTOM_LANGUAGE_ID' => LANGUAGE_ID,
            'ALLOW_EDIT' => 'Y',
            'ALLOW_ADD_PRODUCT' => 'Y',
            //'ALLOW_CREATE_NEW_PRODUCT' => (!$this->arResult['READ_ONLY'] ? 'Y' : 'N'),
            'ID' => 'deal_product_editor',
            'PREFIX' => 'deal_product_editor',

            'FORM_ID' => '',

            'PERMISSION_TYPE' => 'WRITE',
            'PERMISSION_ENTITY_TYPE' => DealCategory::convertToPermissionEntityType($entityData['CATEGORY_ID'] ?? 0),
            'PERSON_TYPE_ID' => self::resolvePersonTypeID($entityData),
            'CURRENCY_ID' => $currencyID,
            'LOCATION_ID' => $isTaxMode && isset($entityData['LOCATION_ID']) ? $entityData['LOCATION_ID'] : '',
            'CLIENT_SELECTOR_ID' => '', //TODO: Add Client Selector
            'PRODUCTS' => null,
            'PRODUCT_DATA_FIELD_NAME' => 'DEAL_PRODUCT_DATA',
            'BUILDER_CONTEXT' => \Bitrix\Crm\Product\Url\ProductBuilder::TYPE_ID,
        ];
    }

    public static function getSmartProcessComponentSettings($entityId, $entityTypeID, $categoryId)
    {
        $factory = Container::getInstance()->getFactory($entityTypeID);
        $entityNameRoot = \CCrmOwnerType::ResolveName($entityTypeID);
        $guid = $entityNameRoot.'_details';

        if ($categoryId > 0)
        {
            $guid .= '_C'.$categoryId;
        }
        $productEditorId = mb_strtolower($guid).'_product_editor';
        /** @hack хз нужен ли метод isCopyMode поэтому закомментил (код отсюда - \Bitrix\Crm\Component\EntityDetails\BaseComponent::isCopyMode) */

        if( ($entityId <= 0) /*|| ($this->isCopyMode()) */)
        {
            $item = $factory->createItem();
            $request = Application::getInstance()->getContext()->getRequest();

            $parentIdentifiers = [];
            $parentRelations = Container::getInstance()->getRelationManager()->getParentRelations($entityTypeID);

            foreach ($parentRelations as $relation)
            {
                $parentEntityTypeId = $relation->getParentEntityTypeId();
                $entityName = mb_strtolower(\CCrmOwnerType::ResolveName($parentEntityTypeId)) . '_id';
                $entityId = (int)$request->get($entityName);
                if ($entityId > 0)
                {
                    if ($relation->isPredefined())
                    {
                        $fieldName = \CCrmOwnerType::ResolveName($relation->getParentEntityTypeId()) . '_ID';
                    } else {
                        $fieldName = EditorAdapter::getParentFieldName($relation->getParentEntityTypeId());
                    }

                    if ($fieldName)
                    {
                        $parentIdentifiers[$fieldName] = new ItemIdentifier($relation->getParentEntityTypeId(), $entityId);
                    }
                }
            }
            ;
            foreach ($parentIdentifiers as $fieldName => $itemIdentifier)
            {
                if ($item->hasField($fieldName))
                {
                    $item->set($fieldName, $itemIdentifier->getEntityId());
                }
            }
        }
        else
        {
            $select = ['*'];

            if ($factory->isLinkWithProductsEnabled())
            {
                $select[] = Item::FIELD_NAME_PRODUCTS;
            }

            if ($factory->isClientEnabled())
            {
                $select[] = Item::FIELD_NAME_CONTACTS;
            }

            if ($factory->isObserversEnabled())
            {
                $select[] = Item::FIELD_NAME_OBSERVERS;
            }
            $item = $factory->getItems([
                'select' => $select,
                'filter' => ['=ID' => $entityId],
            ])[0] ?? null;

            if (!$item)
            {
                throw new \Exception('No entity type with id'.$entityTypeID);
            }
        }

        if($entityId <= 0)
        {
            $title = 'Создание '.$factory->getEntityDescription();
        } else {
            $title =  $item->getHeading() ?? '';
        }
        $userPermissions = Container::getInstance()->getUserPermissions();
        $locationId = null;
        $accountingService = Container::getInstance()->getAccounting();

        if ($item->hasField(Item::FIELD_NAME_LOCATION_ID) && $accountingService->isTaxMode())
        {
            $locationId = $item->getLocationId();
        }

        return [
            'INTERNAL_FILTER' => [
                'OWNER_ID' => $entityId,
                'OWNER_TYPE' => $entityTypeID,
            ],
            'PATH_TO_ENTITY_PRODUCT_LIST' => ExtendedProductList::getComponentUrl(
                ['site' => SITE_ID],
                bitrix_sessid_get()
            ),
            'ACTION_URL' => ExtendedProductList::getLoaderUrl(
                ['site' => SITE_ID],
                bitrix_sessid_get()
            ),
            'ENTITY_ID' => $entityId,
            'ENTITY_TYPE_NAME' => $entityNameRoot,
            'ENTITY_TITLE' =>
                $item->isNew()
                    ? $title
                    : $item->getTitlePlaceholder()
            ,
            'CUSTOM_SITE_ID' => SITE_ID,
            'CUSTOM_LANGUAGE_ID' => LANGUAGE_ID,
            'ALLOW_EDIT' => 'Y',
            'ALLOW_ADD_PRODUCT' => 'Y',
            // 'ALLOW_CREATE_NEW_PRODUCT' => (!$this->arResult['READ_ONLY'] ? 'Y' : 'N'),
            'ID' => $productEditorId,
            'PREFIX' => $productEditorId,
            'FORM_ID' => '',
            'PERMISSION_TYPE' => 'WRITE',
            'PERMISSION_ENTITY_TYPE' => $userPermissions::getPermissionEntityType($entityTypeID, $categoryId),
            'PERSON_TYPE_ID' => $accountingService->resolvePersonTypeId($item),
            'CURRENCY_ID' => $item->getCurrencyId(),
            'ALLOW_LD_TAX' => Container::getInstance()->getAccounting()->isTaxMode() ? 'Y' : 'N',
            'LOCATION_ID' => $locationId,
            'CLIENT_SELECTOR_ID' => '', //TODO: Add Client Selector
            'PRODUCTS' => null /* @hack $this->getProductsData() */,
            'PRODUCT_DATA_FIELD_NAME' => $entityNameRoot.'_PRODUCT_DATA',
            'BUILDER_CONTEXT' => ProductBuilder::TYPE_ID,
            /*
            'HIDE_MODE_BUTTON' => !$this->isEditMode ? 'Y' : 'N',
            'TOTAL_SUM' => isset($this->entityData['OPPORTUNITY']) ? $this->entityData['OPPORTUNITY'] : null,
            'TOTAL_TAX' => isset($this->entityData['TAX_VALUE']) ? $this->entityData['TAX_VALUE'] : null,
            'PATH_TO_PRODUCT_EDIT' => $this->arResult['PATH_TO_PRODUCT_EDIT'],
            'PATH_TO_PRODUCT_SHOW' => $this->arResult['PATH_TO_PRODUCT_SHOW'],
            'INIT_LAYOUT' => 'N',
            'INIT_EDITABLE' => $this->arResult['READ_ONLY'] ? 'N' : 'Y',
            'ENABLE_MODE_CHANGE' => 'N',
            'USE_ASYNC_ADD_PRODUCT' => 'Y',
            */
        ];
    }

}