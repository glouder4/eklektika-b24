<?php

namespace Kitconsulting\ProductApplicationsExtension;

use Bitrix;
use Bitrix\Main\Loader;
use Kitconsulting\ExtendedProductsRow\CCrmProductRowExtended;
use Bitrix\Rest\RestException;


class Events
{
    public static function onCrmDynamicItemAdd(Bitrix\Main\Event $event)
    {
        self::processEvent($event);
    }

    public static function onCrmDynamicItemUpdate(Bitrix\Main\Event $event)
    {
        self::processEvent($event);
    }

    public static function processEvent(Bitrix\Main\Event $event)
    {
        if (
            !Bitrix\Main\Loader::includeModule('kitconsulting.extendedproductsrow')
            || !Bitrix\Main\Loader::includeModule('im')
        ) return;

        /** @var Bitrix\Crm\Item\Dynamic $item */
        $item = $event->getParameter("item");
        $helper = new Helper();

        if ($item->getEntityTypeId() !== $helper->getType()->getEntityTypeId()) return;
        $dealId = $item->get('PARENT_ID_2');

        if (empty($dealId)) return;
        $result = CCrmProductRowExtended::getList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            ['OWNER_ID' => $dealId, 'OWNER_TYPE' => "D"]
        );
        $smartProcessesIds = [];

        $productsCount = 0;
        while ($productRow = $result->Fetch()) {
            $productsCount++;
            if (empty($productRow['UF_SMART_PROCESS_ID'])) continue;
            $smartProcessesIds[] = $productRow['UF_SMART_PROCESS_ID'];
        }

        if (empty($smartProcessesIds)) return;
        if (count($smartProcessesIds) < $productsCount) return; //отправляем уведомление только тогда, когда все товары готовы

        if ($helper->areSmartProcessItemsReadyToShip($smartProcessesIds)) {
            $deal = Bitrix\Crm\DealTable::getById($dealId)->fetchObject();
            $url = \CCrmOwnerType::GetEntityShowPath(\CCrmOwnerType::Deal, $dealId);

            if (!empty($deal->getAssignedById())) {
                $arMessageFields = [
                    "FROM_USER_ID" => 0,
                    "NOTIFY_TYPE" => 4,
                    "NOTIFY_MESSAGE" => "Все товары в <a href=\"$url\">сделке</a> готовы к отгрузке",
                    "NOTIFY_MESSAGE_OUT" => null,
                    "NOTIFY_MODULE" => "crm",
                    "NOTIFY_EVENT" => "other",
                    "TO_USER_ID" => $deal->getAssignedById()
                ];
                \CIMNotify::Add($arMessageFields);
            }
        }
    }

    public static function onRestServiceBuildDescription()
    {
        return [
            'kit.productapplications' => [
                'kit.productapplications.deal.productrows.get' => [__CLASS__, 'getDealProductsRows'],
                'kit.productapplications.smart.productrows.get' => [__CLASS__, 'getSmartProductsRow'],
                'kit.productapplications.invoice.productrows.get' => [__CLASS__, 'getSmartInvoicesRow'],

                'kit.extendedproductsrow.deal.productrows.set' => [__CLASS__, 'setDealProductsRows'], //устанавливает продукты для сделки (перезаписывает существующие)
                'kit.extendedproductsrow.smart.productrows.set' => [__CLASS__, 'setSmartProductsRows'], //устанавливает продукты для смарт-процесса (перезаписывает существующие)
                'kit.extendedproductsrow.invoice.productrows.set' => [__CLASS__, 'setInvoiceProductsRows'], //устанавливает продукты для счёта (перезаписывает существующие)


                'kit.extendedproductsrow.deal.productrow.update.reserve' => [__CLASS__, 'updateProductReserve'],

                'kit.extendedproductsrow.productrow.uf.list' => [__CLASS__, 'getProductRowUserField'],
                'kit.extendedproductsrow.productrow.uf.set' => [__CLASS__, 'setProductRowUserField'],

                //'kit.extendedproductsrow.quote.productrows.get' => [__CLASS__, 'getQuoteProductsRows'],
                //'kit.extendedproductsrow.quote.productrows.set' => [__CLASS__, 'setQuoteProductsRows'],
                //'kit.extendedproductsrow.productrows.get' => [__CLASS__, 'getDealProductsRows']
                'kit.extendedproductsrow.productrows.updateXmlId' => [__CLASS__, 'updateProductXmlId'],
            ]
        ];
    }

    public static function getProductsRows($entityTypeId, $entityId)
    {
        return CCrmProductRowExtended::LoadRows($entityTypeId, $entityId);
    }

    /**
     * @throws \Bitrix\Main\LoaderException
     * @throws RestException
     */
    public static function getDealProductsRows($query, $n, \CRestServer $server)
    {
        Loader::includeModule('kitconsulting.extendedproductsrow');
        $query = array_change_key_case($query, CASE_UPPER);
        self::validate($query, ['ID']);

        $ID = intval($query['ID']);

        if ($ID <= 0) {
            throw new RestException('The parameter id is invalid or not defined.');
        }

        $userPermissions = \CCrmPerms::GetCurrentUserPermissions();
        $categoryID = \CCrmDeal::GetCategoryID($ID);
        if ($categoryID < 0) {
            throw new RestException(
                !\CCrmDeal::CheckReadPermission(0, $userPermissions) ? 'Access denied' : 'Not found'
            );
        } elseif (!\CCrmDeal::CheckReadPermission($ID, $userPermissions, $categoryID)) {
            throw new RestException('Access denied.');
        }

        if (!\CCrmDeal::Exists($ID)) {
            throw new RestException('Not found.');
        }

        return self::getProductsRows(\CCrmOwnerTypeAbbr::Deal, $ID);
    }

    /**
     * Получение списка товаров из смарт-процессов
     * @param $query
     * @param $n
     * @param \CRestServer $server
     */
    public static function getSmartProductsRow($query, $n, \CRestServer $server)
    {
        Loader::includeModule('kitconsulting.extendedproductsrow');
        $query = array_change_key_case($query, CASE_UPPER);
        self::validate($query, ['ID']);

        $ID = intval($query['ID']);
        if ($ID <= 0) throw new RestException('The parameter id is invalid or not defined.');

        $smartProcessHelper = new Helper();
        $entityTypeID = $smartProcessHelper->getType()->getEntityTypeId();
        $typeName = \CCrmOwnerType::ResolveName($entityTypeID);
        $typeCode = \CCrmOwnerTypeAbbr::ResolveByTypeName($typeName);

        if (!\CCrmAuthorizationHelper::CheckReadPermission($typeName, $ID)) throw new RestException('Access denied.');

        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeID);
        if ($factory->getItem($ID) === null) throw new RestException('Not found.');

        return self::getProductsRows($typeCode, $ID);
    }

    /**
     * Получение списка товаров из счетов
     * @param $query
     * @param $n
     * @param \CRestServer $server
     */
    public static function getSmartInvoicesRow($query, $n, \CRestServer $server)
    {
        Loader::includeModule('kitconsulting.extendedproductsrow');
        $query = array_change_key_case($query, CASE_UPPER);
        self::validate($query, ['ID']);

        $ID = intval($query['ID']);
        if ($ID <= 0) throw new RestException('The parameter id is invalid or not defined.');

        $entityTypeID = \CCrmOwnerType::SmartInvoice;
        $typeName = \CCrmOwnerType::SmartInvoiceName;
        $typeCode = \CCrmOwnerTypeAbbr::SmartInvoice;

        if (!\CCrmAuthorizationHelper::CheckReadPermission($typeName, $ID)) throw new RestException('Access denied.');

        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeID);
        if ($factory->getItem($ID) === null) throw new RestException('Not found.');

        return self::getProductsRows($typeCode, $ID);
    }

    /**
     * Обновление внешнего ключа товара
     * @param $query
     * @param $n
     * @param \CRestServer $server
     * @return bool|mixed
     * @throws Bitrix\Main\LoaderException
     */
    public static function updateProductXmlId($query, $n, \CRestServer $server)
    {
        Loader::includeModule('kitconsulting.extendedproductsrow');
        $query = array_change_key_case($query, CASE_UPPER);
        self::validate($query, ['ID', 'XML_ID']);

        $ID = intval($query['ID']);
        if ($ID <= 0) throw new RestException('The parameter id is invalid or not defined.');

        $xmlID = trim($query['XML_ID']);
        if ($xmlID == '') throw new RestException('The parameter xml_id is invalid or not defined.');

        $iBlockId = \CIBlockElement::GetIBlockByID($ID);
        $perms = \CIBlock::GetPermission($iBlockId);
        if ($perms < "R") throw new RestException('Access denied.');

        $iBlockElement = \CIBlockElement::GetByID($ID);
        if (empty($iBlockElement)) throw new RestException('Not found.');

        return CCrmProductRowExtended::updateProductXmlId($ID, $xmlID);
    }

    /**
     * @throws \Bitrix\Main\LoaderException
     * @throws RestException
     */
    public static function validate($query, $keys = [])
    {
        if (!Loader::includeModule('crm')) {
            throw new RestException('module crm required');
        }

        foreach ($keys as $key) {
            if (!array_key_exists($key, $query)) {
                throw new RestException("$key required!");
            }
        }
    }

    public static function OnBeforeCrmDealUpdate($arFields)
    {
        if (!isset($arFields['UF_ADV_AGENT'])) return;

        if (!Bitrix\Main\Loader::includeModule('kitconsulting.extendedproductsrow')) return;
        $rows = CCrmProductRowExtended::LoadRows('D', $arFields['ID']);
        $helper = new ApplicationsHelper();
        $newRows = [];

        define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"] . "/local/log.txt");
        AddMessage2Log(json_encode($arFields));
        AddMessage2Log(json_encode($rows));

        foreach ($rows as $productRow)
        {
            $productPriceRow = $helper->loadProductPrices($productRow['PRODUCT_ID'], $productRow);
            AddMessage2Log(json_encode($productPriceRow));

            if (!isset($productPriceRow['PRICE_ADV'])) $productPriceRow['PRICE_ADV'] = 0.00;
            if (!isset($productPriceRow['PRICE_OPT'])) $productPriceRow['PRICE_OPT'] = 0.00;

//            if ($productPriceRow['PRICE_BRUTTO'] > $productPriceRow['PRICE_ADV']) {
//                $newRows[] = $productPriceRow;
//                continue;
//            }
            $productPriceRow['PRICE'] = 0;

            if (!$arFields['UF_ADV_AGENT'] || $arFields['UF_ADV_AGENT'] === 'N') {
                $productPriceRow['PRICE_BRUTTO'] = $productPriceRow['PRICE_OPT'];

                if (isset($productPriceRow['UF_APPLICATION_PRICE'])) {
                    $productPriceRow['UF_APPLICATION_PRICE'] = $productPriceRow['APPLICATION_PRICE_OPT'];
                }
            } elseif ($arFields['UF_ADV_AGENT'] || $arFields['UF_ADV_AGENT'] === 'Y') {
                $productPriceRow['PRICE_BRUTTO'] = $productPriceRow['PRICE_ADV'];

                if (isset($productPriceRow['UF_APPLICATION_PRICE'])) {
                    $productPriceRow['UF_APPLICATION_PRICE'] = $productPriceRow['APPLICATION_PRICE_ADV'];
                }
            }

            $newRows[] = $productPriceRow;
        }

        CCrmProductRowExtended::SaveRows('D', $arFields['ID'], $newRows, null, true, true, false);
    }

    public static function setDealProductsRows($query, $n, \CRestServer $server)
    {
        \Bitrix\Main\Diag\Debug::dumpToFile($query, 'setDealProductsRows - ' . date("Y-m-d H:i:s"), '/local/rest.log');
        \Bitrix\Main\Diag\Debug::dumpToFile($_REQUEST, 'setDealProductsRows - ' . date("Y-m-d H:i:s"), '/local/rest.log');
        \Bitrix\Main\Diag\Debug::dumpToFile($_SERVER, 'setDealProductsRows - ' . date("Y-m-d H:i:s"), '/local/rest.log');

        Loader::includeModule('kitconsulting.extendedproductsrow');
        $query = array_change_key_case($query, CASE_UPPER);
        self::validate($query, ['ID', 'ROWS']);

        $ID = intval($query['ID']);
        $rows = $query['ROWS'];

        if ($ID <= 0) {
            throw new RestException('The parameter id is invalid or not defined.');
        }

        if (!is_array($rows)) {
            throw new RestException('The parameter rows must be array.');
        }

        $userPermissions = \CCrmPerms::GetCurrentUserPermissions();
        $categoryID = \CCrmDeal::GetCategoryID($ID);
        if ($categoryID < 0) {
            throw new RestException(
                !\CCrmDeal::CheckUpdatePermission(0, $userPermissions) ? 'Access denied' : 'Not found'
            );
        } elseif (!\CCrmDeal::CheckUpdatePermission($ID, $userPermissions, $categoryID)) {
            throw new RestException('Access denied.');
        }

        if (!\CCrmDeal::Exists($ID)) {
            throw new RestException('Not found.');
        }

        $ownerType = \CCrmOwnerTypeAbbr::Deal;
        $rows = self::prepareRows($rows);
        if (empty($rows)) throw new RestException('The parameter rows must be array of arrays.');

        return CCrmProductRowExtended::SaveRows($ownerType, $ID, $rows);
    }

    public static function setSmartProductsRows($query, $n, \CRestServer $server)
    {
        Loader::includeModule('kitconsulting.extendedproductsrow');
        $query = array_change_key_case($query, CASE_UPPER);
        self::validate($query, ['ID', 'ROWS']);

        $ID = intval($query['ID']);
        $rows = $query['ROWS'];

        if ($ID <= 0) {
            throw new RestException('The parameter id is invalid or not defined.');
        }

        if (!is_array($rows)) {
            throw new RestException('The parameter rows must be array.');
        }

        $smartProcessHelper = new Helper();
        $entityTypeID = $smartProcessHelper->getType()->getEntityTypeId();
        $typeName = \CCrmOwnerType::ResolveName($entityTypeID);
        $typeCode = \CCrmOwnerTypeAbbr::ResolveByTypeName($typeName);

        if (!\CCrmAuthorizationHelper::CheckReadPermission($typeName, $ID)) throw new RestException('Access denied.');

        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeID);
        if ($factory->getItem($ID) === null) throw new RestException('Not found.');

        $rows = self::prepareRows($rows);
        if (empty($rows)) throw new RestException('The parameter rows must be array of arrays.');
        return CCrmProductRowExtended::SaveRows($typeCode, $ID, $rows);
    }

    public static function setInvoiceProductsRows($query, $n, \CRestServer $server)
    {
        Loader::includeModule('kitconsulting.extendedproductsrow');
        $query = array_change_key_case($query, CASE_UPPER);
        self::validate($query, ['ID', 'ROWS']);

        $ID = intval($query['ID']);
        $rows = $query['ROWS'];

        if ($ID <= 0) {
            throw new RestException('The parameter id is invalid or not defined.');
        }

        if (!is_array($rows)) {
            throw new RestException('The parameter rows must be array.');
        }

        $typeName = \CCrmOwnerType::SmartInvoiceName;
        $typeCode = \CCrmOwnerTypeAbbr::SmartInvoice;
        if (!\CCrmAuthorizationHelper::CheckReadPermission($typeName, $ID)) throw new RestException('Access denied.');

        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::SmartInvoice);
        if ($factory->getItem($ID) === null) throw new RestException('Not found.');

        $rows = self::prepareRows($rows);
        if (empty($rows)) throw new RestException('The parameter rows must be array of arrays.');
        return CCrmProductRowExtended::SaveRows($typeCode, $ID, $rows);
    }

    public static function setProductRowUserField ($query, $n, \CRestServer $server)
    {
        Loader::includeModule('kitconsulting.extendedproductsrow');
        $query = array_change_key_case($query, CASE_UPPER);
        self::validate($query, ['ID', 'FIELDS']);

        $ID = intval($query['ID']);
        $FIELDS = $query['FIELDS'];

        if ($ID <= 0) {
            throw new RestException('The parameter ID is invalid or not defined.');
        }

        if (!is_array($FIELDS)) {
            throw new RestException('The parameter FIELDS must be array.');
        }

        return CCrmProductRowExtended::DoSaveRowUF($ID, $FIELDS);
    }

    public static function getProductRowUserField ($query, $n, \CRestServer $server)
    {
        Loader::includeModule('kitconsulting.extendedproductsrow');

        $ufRows = $GLOBALS['USER_FIELD_MANAGER']->GetUserFields(CCrmProductRowExtended::$sUFEntityID);
        return $ufRows;
    }

    public static function prepareRows($rows)
    {
        $actualRows = [];

        for ($i = 0, $qty = count($rows); $i < $qty; $i++) {
            $row = $rows[$i];
            if (!is_array($row)) {
                continue;
            }

            if (isset($row['OWNER_TYPE'])) {
                unset($row['OWNER_TYPE']);
            }

            if (isset($row['OWNER_TYPE_ID'])) {
                unset($row['OWNER_TYPE_ID']);
            }

            if (isset($row['OWNER_ID'])) {
                unset($row['OWNER_ID']);
            }

            $actualRows[] = $row;
        }

        return $actualRows;
    }
}