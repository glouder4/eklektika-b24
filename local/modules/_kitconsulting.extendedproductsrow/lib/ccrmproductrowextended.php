<?php

namespace Kitconsulting\ExtendedProductsRow;

use Bitrix\Crm\ProductRow;
use Bitrix\Crm\ProductRowTable;
use Bitrix\Crm\Reservation\Strategy\ManualStrategy;
use Bitrix\Crm\Service\Sale\Reservation\ReservationService;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Configuration;
use CCrmOwnerTypeAbbr;
use CCrmDeal;
use CCrmPaySystem;
use CCrmProduct;
use CCrmLead;
use CCrmQuote;
use CCrmCurrency;
use CCrmEvent;
use CCrmTax;
use CSite;
use CCrmSaleHelper;
use Bitrix\Crm\Item;
use Bitrix\Rest\RestException;
use Bitrix\Catalog\StoreTable;
use Bitrix\Main\Result;


class CCrmProductRowExtended extends \CCrmProductRow
{
    static $sUFEntityID = 'CRM_PRODUCT_ROW';
    static $userFields = null;

    public static function LoadRows($ownerType, $ownerID, $assoc = false)
    {
        $ownerType = strval($ownerType);
        $filter = array();

        if(isset($ownerType[0]))
        {
            $filter['OWNER_TYPE'] = $ownerType;
        }

        if(is_array($ownerID))
        {
            if(count($ownerID) > 0)
            {
                $filter['@OWNER_ID'] = $ownerID;
            }
        }
        else
        {
            $ownerID = (int)$ownerID;
            if($ownerID > 0)
            {
                $filter['OWNER_ID'] = $ownerID;
            }
        }

		$productsID = [];
        $measurelessProductIDs = array();
        $dbRes = self::GetList(array('SORT' => 'ASC', 'ID'=>'ASC'), $filter);
        $results = array();
        while($ary = $dbRes->Fetch())
        {
            $productID = $ary['PRODUCT_ID'] = isset($ary['PRODUCT_ID']) ? intval($ary['PRODUCT_ID']) : 0;
			$productsID[$productID] = $productID;

            $ary['QUANTITY'] = isset($ary['QUANTITY']) ? round((double)$ary['QUANTITY'], 4) : 0.0;
            $ary['PRICE'] = isset($ary['PRICE']) ? round((double)$ary['PRICE'], 2) : 0.0;
            $ary['UF_PURCHASE_PRICE'] = isset($ary['UF_PURCHASE_PRICE']) ? round((double)$ary['UF_PURCHASE_PRICE'], 2) : 0.0;
            $ary['PRICE_EXCLUSIVE'] = isset($ary['PRICE_EXCLUSIVE']) ? round((double)$ary['PRICE_EXCLUSIVE'], 2) : 0.0;
            $ary['PRICE_NETTO'] = isset($ary['PRICE_NETTO']) ? round((double)$ary['PRICE_NETTO'], 2) : 0.0;
            $ary['PRICE_BRUTTO'] = isset($ary['PRICE_BRUTTO']) ? round((double)$ary['PRICE_BRUTTO'], 2) : 0.0;

            $ary['DISCOUNT_TYPE_ID'] = isset($ary['DISCOUNT_TYPE_ID'])
                ? (int)$ary['DISCOUNT_TYPE_ID'] : \Bitrix\Crm\Discount::UNDEFINED;
            $ary['DISCOUNT_RATE'] = isset($ary['DISCOUNT_RATE']) ? round((double)$ary['DISCOUNT_RATE'], 2) : 0.0;
            $ary['DISCOUNT_SUM'] = isset($ary['DISCOUNT_SUM']) ? round((double)$ary['DISCOUNT_SUM'], 2) : 0.0;

            $ary['TAX_RATE'] = isset($ary['TAX_RATE']) ? round((double)$ary['TAX_RATE'], 2) : 0.0;
            $ary['TAX_INCLUDED'] = isset($ary['TAX_INCLUDED']) ? $ary['TAX_INCLUDED'] : 'N';
            $ary['CUSTOMIZED'] = isset($ary['CUSTOMIZED']) ? $ary['CUSTOMIZED'] : 'N';

            $ary['SORT'] = isset($ary['SORT']) ? (int)$ary['SORT'] : 0;

            $ary['MEASURE_CODE'] = isset($ary['MEASURE_CODE']) ? (int)$ary['MEASURE_CODE'] : 0;
            $ary['MEASURE_NAME'] = isset($ary['MEASURE_NAME']) ? $ary['MEASURE_NAME'] : '';

            $ary['RESERVE_ID'] = null;

            if($productID > 0 && $ary['MEASURE_CODE'] <= 0)
            {
                if(!in_array($productID, $measurelessProductIDs, true))
                {
                    $measurelessProductIDs[] = $productID;
                }
            }

            if(!isset($ary['PRODUCT_NAME']) || $ary['PRODUCT_NAME'] === '')
            {
                if($ary['PRODUCT_ID'] > 0 && isset($ary['ORIGINAL_PRODUCT_NAME']))
                {
                    $ary['PRODUCT_NAME'] = $ary['ORIGINAL_PRODUCT_NAME'];
                }
                elseif(!isset($ary['PRODUCT_NAME']))
                {
                    $ary['PRODUCT_NAME'] = '';
                }
            }

            $productRow = \Bitrix\Crm\ProductRow::createFromArray($ary);
            $ary['PRODUCT_ROW_RESERVATION'] = $productRow->getProductRowReservation() ?? 0;

            if($assoc)
            {
                $results[(int)$ary['ID']] = $ary;
            }
            else
            {
                $results[] = $ary;
            }
        }

        $results = \Bitrix\Crm\Service\Sale\Reservation\ReservationService::getInstance()->fillBasketReserves($results);

        if(!empty($measurelessProductIDs))
        {
            $defaultMeasureInfo = \Bitrix\Crm\Measure::getDefaultMeasure();
            $measureInfos = \Bitrix\Crm\Measure::getProductMeasures($measurelessProductIDs);
            foreach($results as &$result)
            {
                if($result['MEASURE_CODE'] > 0)
                {
                    continue;
                }

                $productID = $result['PRODUCT_ID'];
                if(isset($measureInfos[$productID]) && !empty($measureInfos[$productID]))
                {
                    $measureInfo = $measureInfos[$productID][0];
                    $result['MEASURE_CODE'] = $measureInfo['CODE'];
                    $result['MEASURE_NAME'] = $measureInfo['SYMBOL'];
                }
                elseif($defaultMeasureInfo !== null)
                {
                    $result['MEASURE_CODE'] = $defaultMeasureInfo['CODE'];
                    $result['MEASURE_NAME'] = $defaultMeasureInfo['SYMBOL'];
                }
            }
            unset($result);
        }

        if (!empty($productsID))
		{
			\Bitrix\Main\Loader::includeModule('iblock');
            \Bitrix\Main\Loader::includeModule('catalog');

			$productsElements = \CIBlockElement::GetList([], [
				'ID' => $productsID,
			], false, false, [
				'ID',
				'PROPERTY_ARTIKUL_BITRIKS',
				'PROPERTY_ARTIKUL_NOMENKLATURY_DLYA_BITRIKS24',
				'PROPERTY_ARTIKUL_POSTAVSHCHIKA',
                'PROPERTY_VIRTUAL_INSTOCK' => 'PROPERTY_VIRTUAL_INSTOCK',
                'PROPERTY_REAL_INSTOCK' => 'PROPERTY_REAL_INSTOCK',
                'PROPERTY_SUPPLIER_OF_GOODS' => 'PROPERTY_SUPPLIER_OF_GOODS',
                'PROPERTY_postavcshik' => 'PROPERTY_postavcshik',
			]);

			while ($data = $productsElements->Fetch())
			{
				foreach($results as &$result)
				{
					if ($result['PRODUCT_ID'] != $data['ID']) continue;

					$result['PROPERTY_ARTIKUL_BITRIKS'] = $data['PROPERTY_ARTIKUL_BITRIKS_VALUE'];
					$result['PROPERTY_ARTIKUL_NOMENKLATURY_DLYA_BITRIKS24'] = $data['PROPERTY_ARTIKUL_NOMENKLATURY_DLYA_BITRIKS24_VALUE'];
					$result['PROPERTY_ARTIKUL_POSTAVSHCHIKA'] = $data['PROPERTY_ARTIKUL_POSTAVSHCHIKA_VALUE'];
					$result['PROPERTY_VIRTUAL_INSTOCK'] = $data['PROPERTY_VIRTUAL_INSTOCK'];
					$result['PROPERTY_REAL_INSTOCK'] = $data['PROPERTY_REAL_INSTOCK'];
					$result['PROPERTY_SUPPLIER_OF_GOODS'] = $data['PROPERTY_SUPPLIER_OF_GOODS_VALUE'];

					$result['PROPERTY_POSTAVCSHIK'] = $data['PROPERTY_POSTAVCSHIK_VALUE'];


                    $mxResult = \CCatalogSku::GetProductInfo($result['PRODUCT_ID']);
                    if (is_array($mxResult)) {
                        $result['PARENT_PRODUCT_ID'] = $mxResult['ID']; // ID товара родителя
                        $parentProduct = \CIBlockElement::GetList([], [
                            'ID' => $mxResult['ID'],
                        ], false, false, [
                            'NAME',
                            'PROPERTY_SUPPLIER_OF_GOODS' => 'PROPERTY_SUPPLIER_OF_GOODS'
                        ])->Fetch();
                        $result['PARENT_PRODUCT_NAME'] = $parentProduct['NAME'];
                        if (empty($result['PROPERTY_SUPPLIER_OF_GOODS']))
                        {
                            $result['PROPERTY_SUPPLIER_OF_GOODS'] = $parentProduct['PROPERTY_SUPPLIER_OF_GOODS_VALUE'];
                            $result['PROPERTY_SUPPLIER_OF_GOODS_TITLE'] = self::getCrmEntityName($result['PROPERTY_SUPPLIER_OF_GOODS']);
                        }
                    }
                    else {
                        $result['PARENT_PRODUCT_ID'] = null;
                        $result['PARENT_PRODUCT_NAME'] = null;
                    }
				}
			}
		}

        return $results;
    }

    public static function getCrmEntityName($entityCode)
    {
        $providerClassList = [
            'CrmLeads',
            'CrmCompanies',
            'CrmContacts',
            'CrmDeals',
            'CrmQuotes',
            'CrmOrders',
            'CrmProducts',
            'CrmQuotes',
            'CrmSmartInvoices',
        ];

        if (!empty($entityCode))
        {
            $crmEntityId = 0;
            $crmEntityType = "";
            foreach ($providerClassList as $className)
            {
                $className = '\\Bitrix\\Crm\\Integration\\Main\\UISelector\\' . $className;
                if (preg_match('/^' . $className::PREFIX_SHORT . '(\d+)$/i', $entityCode, $matches))
                {
                    $crmEntityId = $matches[1];
                    $crmEntityType = str_replace("CRM", "", $className::PREFIX_FULL);
                    break;
                }
            }

            if ($crmEntityId > 0)
            {
                $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::ResolveID($crmEntityType));
                $supplier = $factory->getDataClass()::getById($crmEntityId)->fetch();

                return $supplier['TITLE'] ?? $supplier['NAME'];
            }
        }

        return null;
    }

    public static function GetList($arOrder = array(), $arFilter = array(), $arGroupBy = false, $arNavStartParams = false, $arSelectFields = array(), $arOptions = array())
    {
        $fields = self::GetFields();
        if (is_array($arOptions) && isset($arOptions['EXTENDED_FIELDS']))
        {
            if ($arOptions['EXTENDED_FIELDS'] === 'Y' || $arOptions['EXTENDED_FIELDS'] === true)
            {
                $fields = array_replace($fields, self::GetExtendedFields());
            }
        }
        $lb = new \CCrmEntityListBuilder(
            self::DB_TYPE,
            self::TABLE_NAME,
            self::TABLE_ALIAS,
            $fields,
            self::$sUFEntityID,
            '',
            array()
        );

        if(!$arSelectFields) {
            $arSelectFields = ['*', 'UF_*'];
        }

        return $lb->Prepare($arOrder, $arFilter, $arGroupBy, $arNavStartParams, $arSelectFields, $arOptions);
    }

    public static function AreEquals(array $leftRows, array $rightRows)
    {
        if(count($leftRows) !== count($rightRows))
        {
            return false;
        }

        for($i = 0, $length = count($leftRows); $i < $length; $i++)
        {
            if(!self::areRowsEqual($leftRows[$i], $rightRows[$i]))
            {
                return false;
            }
        }
        return true;
    }

    public static function areRowsEqual(array $a, array $b)
    {
        return (\Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'PRODUCT_NAME')
            && \Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'PRODUCT_ID')
            && \Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'QUANTITY')
            && \Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'MEASURE_CODE')
            && \Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'MEASURE_NAME')
            && \Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'PRICE')
            && \Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'PRICE_EXCLUSIVE')
            && \Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'PRICE_NETTO')
            && \Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'PRICE_BRUTTO')
            && \Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'DISCOUNT_TYPE_ID')
            && \Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'DISCOUNT_RATE')
            && \Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'DISCOUNT_SUM')
            && \Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'TAX_INCLUDED')
            && \Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'CUSTOMIZED')
            && \Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'SORT')
            && \Bitrix\Crm\Comparer\ComparerBase::areFieldsEquals($a, $b, 'UF_APPLICATION_PRICE')
        );
    }

    public static function DoSaveRowUF($rowId, $productRow)
    {
        $dbRes = self::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            [ 'ID' => $rowId]
        );
        $product = $dbRes->Fetch();

        if (empty($product) || !$product)
            throw new RestException('Product row not found.');

        \CCrmEntityHelper::NormalizeUserFields($productRow, self::$sUFEntityID, $GLOBALS['USER_FIELD_MANAGER'], ['IS_NEW' => true]);
        $result = $GLOBALS['USER_FIELD_MANAGER']->Update(self::$sUFEntityID, $rowId, $productRow);
        return $result;
    }

    public static function DoSaveRows($ownerType, $ownerID, array $arRows)
    {
        global $DB;

        static::$originalRows = $arRows;

        if (!is_int($ownerID)) {
            $ownerID = (int)$ownerID;
        }

        $trace = implode("\n", array_map(function ($val) {
            return "$val[file]:$val[function]:$val[line]";
        }, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));
        \Bitrix\Main\Diag\Debug::dumpToFile($trace, 'SaveProducts - ' . date("Y-m-d H:i:s"), '/local/products_trace.log');
        \Bitrix\Main\Diag\Debug::dumpToFile($arRows, 'SaveProducts - ' . date("Y-m-d H:i:s"), '/local/products.log');

        $insertRows = [];
        $updateRows = [];
        $deleteRows = [];
        foreach ($arRows as $index => $row) {
            if (isset($row['ID']) && $row['ID'] > 0) {
                $updateRows[$row['ID']] = $row;
            } else {
                $row['ORIGINAL_INDEX'] = $index;
                $insertRows[] = $row;
            }
        }

        $dbResult = self::GetList(
            ['ID' => 'ASC'],
            ['=OWNER_TYPE' => $ownerType, '=OWNER_ID' => $ownerID]
        );
        if (is_object($dbResult)) {
            while ($row = $dbResult->Fetch()) {
                $ID = $row['ID'];
                if (!isset($updateRows[$ID]))
                {
                    $deleteRows[] = $ID;
                }
                elseif (!self::NeedForUpdate($row, $updateRows[$ID]))
                {
                    unset($updateRows[$ID]);
                }
            }
        }

        \Bitrix\Main\Diag\Debug::dumpToFile($deleteRows, 'DELETE_ROWS - ' . date("Y-m-d H:i:s"), '/local/delete_rows.log');
        \Bitrix\Main\Diag\Debug::dumpToFile($updateRows, 'UPDATE_ROWS - ' . date("Y-m-d H:i:s"), '/local/update_rows.log');
        \Bitrix\Main\Diag\Debug::dumpToFile($insertRows, 'INSERT_ROWS - ' . date("Y-m-d H:i:s"), '/local/insert_rows.log');

        $tableName = self::TABLE_NAME;

        if(!empty($deleteRows))
        {
            $scriptValues = implode(',', $deleteRows);
            $DB->Query("DELETE FROM {$tableName} WHERE ID IN ({$scriptValues})", false, 'FILE: '.__FILE__.'<br /> LINE: '.__LINE__);

            foreach ($deleteRows as $rowId) {
                $GLOBALS['USER_FIELD_MANAGER']->Delete(self::$sUFEntityID, $rowId);
            }

            $reservationTableName = \Bitrix\Crm\Reservation\Internals\ProductRowReservationTable::getTableName();
            $DB->Query(
                "DELETE FROM {$reservationTableName} WHERE ROW_ID IN ({$scriptValues})",
                true
            );
        }
        if(!empty($updateRows))
        {
            foreach($updateRows as $ID => $row)
            {
                unset($row['ID'], $row['OWNER_TYPE'], $row['OWNER_ID']);
                $scriptValues = $DB->PrepareUpdate($tableName, $row);

                $DB->Query("UPDATE {$tableName} SET {$scriptValues} WHERE ID = {$ID}", false, 'FILE: '.__FILE__.'<br /> LINE: '.__LINE__);
                \CCrmEntityHelper::NormalizeUserFields($row, self::$sUFEntityID, $GLOBALS['USER_FIELD_MANAGER'], array('IS_NEW' => true));
                $GLOBALS['USER_FIELD_MANAGER']->Update(self::$sUFEntityID, $ID, $row);
            }
        }
        if(!empty($insertRows))
        {
            $lastParentRowId = null;
            $lastParentInterfaceRowId = null;

            if (self::$perRowInsert)
            {
                foreach($insertRows as $row)
                {
                    unset($row['ID']);

                    $row['OWNER_TYPE'] = $ownerType;
                    $row['OWNER_ID'] = $ownerID;
                    $data = $DB->PrepareInsert($tableName, $row);

                    $DB->Query(
                        "INSERT INTO {$tableName}({$data[0]}) VALUES ({$data[1]})",
                        false,
                        'File: '.__FILE__.'<br/>Line: '.__LINE__
                    );

                    $lastId = (int)$DB->LastID();
                    static::$originalRows[$row['ORIGINAL_INDEX']]['ID'] = $lastId;

                    \CCrmEntityHelper::NormalizeUserFields($row, self::$sUFEntityID, $GLOBALS['USER_FIELD_MANAGER'], array('IS_NEW' => true));

                    if ($row['ORIGINAL_ROW']['UF_APPLICATION_PARENT_PRODUCT_ROW_ID'] === "undefined" || empty($row['ORIGINAL_ROW']['UF_APPLICATION_PARENT_PRODUCT_ROW_ID'])) {
                        $lastParentRowId = $lastId;
                        $lastParentInterfaceRowId = $row['ORIGINAL_ROW']['ID'];
                    } elseif ($row['UF_APPLICATION_PARENT_PRODUCT_ROW_ID'] === $lastParentInterfaceRowId) {
                        $row['UF_APPLICATION_PARENT_PRODUCT_ROW_ID'] = (string)$lastParentRowId;
                    }

                    if ($row['ORIGINAL_ROW']['UF_SMART_PROCESS_ID'] === 'undefined') {
                        $row['UF_SMART_PROCESS_ID'] = null;
                    }
                    $GLOBALS['USER_FIELD_MANAGER']->Update(self::$sUFEntityID, $lastId, $row);
                }
            }
            else
            {
                $scriptColumns = '';
                $scriptValues = '';
                foreach($insertRows as $row)
                {
                    unset($row['ID']);

                    $row['OWNER_TYPE'] = $ownerType;
                    $row['OWNER_ID'] = $ownerID;
                    $data = $DB->PrepareInsert($tableName, $row);

                    if($scriptColumns === '')
                    {
                        $scriptColumns = $data[0];
                    }

                    if($scriptValues !== '')
                    {
                        $scriptValues .= ",({$data[1]})";
                    }
                    else
                    {
                        $scriptValues = "({$data[1]})";
                    }
                }

                $DB->Query(
                    "INSERT INTO {$tableName}({$scriptColumns}) VALUES {$scriptValues}",
                    false,
                    'File: '.__FILE__.'<br/>Line: '.__LINE__
                );

                $lastId = $DB->LastID();

                $rowsCount = count($insertRows);
                $rowInsertIndex = 0;
                foreach ($insertRows as $row) {
                    \CCrmEntityHelper::NormalizeUserFields($row, self::$sUFEntityID, $GLOBALS['USER_FIELD_MANAGER'], array('IS_NEW' => true));
                    $GLOBALS['USER_FIELD_MANAGER']->Update(self::$sUFEntityID, $lastId + $rowInsertIndex, $row);
                    $rowInsertIndex++;
                }
            }
        }

        return true;
    }

    public static function SaveRows($ownerType, $ownerID, $arRows, $accountContext = null, $checkPerms = true, $regEvent = true, $syncOwner = true, $totalInfo = array())
    {
        $ownerType = strval($ownerType);
        $ownerID = intval($ownerID);

        if(!isset($ownerType[0]) || $ownerID <= 0 || !is_array($arRows))
        {
            self::RegisterError('Invalid arguments are supplied.');
            return false;
        }

        if(!self::$userFields) {
            self::$userFields = $GLOBALS['USER_FIELD_MANAGER']->GetUserFields(self::$sUFEntityID);
        }

        if (!is_array($totalInfo))
            $totalInfo = array();

        $owner = null;
        if (!is_array($accountContext))
        {
            if($ownerType === CCrmOwnerTypeAbbr::Deal)
            {
                $dbResult = CCrmDeal::GetListEx(
                    array(),
                    array('=ID' => $ownerID, 'CHECK_PERMISSIONS' => 'N'),
                    false,
                    false,
                    array('ID', 'CURRENCY_ID', 'EXCH_RATE')
                );
                if(is_object($dbResult))
                {
                    $owner = $dbResult->Fetch();
                }
            }
            elseif($ownerType === CCrmOwnerTypeAbbr::Lead)
            {
                $dbResult = CCrmLead::GetListEx(
                    array(),
                    array('=ID' => $ownerID, 'CHECK_PERMISSIONS' => 'N'),
                    false,
                    false,
                    array('ID', 'CURRENCY_ID', 'EXCH_RATE')
                );
                if(is_object($dbResult))
                {
                    $owner = $dbResult->Fetch();
                }
            }
            elseif($ownerType === CCrmOwnerTypeAbbr::Quote)
            {
                $dbResult = CCrmQuote::GetList(
                    array(),
                    array('=ID' => $ownerID, 'CHECK_PERMISSIONS' => 'N'),
                    false,
                    false,
                    array('ID', 'CURRENCY_ID', 'EXCH_RATE')
                );
                if(is_object($dbResult))
                {
                    $owner = $dbResult->Fetch();
                }
            }
            elseif($ownerType === CCrmOwnerTypeAbbr::SmartInvoice)
            {
                $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::ResolveID($ownerType));
                /**
                 * @var Item
                 */
                $owner = $factory->getItem($ownerID);
            }
            elseif(\CCrmOwnerType::ResolveID($ownerType) > 0)
            {
                $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::ResolveID($ownerType));
                /**
                 * @var Item
                 */
                $owner = $factory->getItem($ownerID);
            }
        }

        // Preparing accounting context -->
        if(!is_array($accountContext))
        {
            $accountContext = array();

            if(is_array($owner))
            {
                if(isset($owner['CURRENCY_ID']))
                {
                    $accountContext['CURRENCY_ID'] = $owner['CURRENCY_ID'];
                }

                if(isset($owner['EXCH_RATE']))
                {
                    $accountContext['EXCH_RATE'] = $owner['EXCH_RATE'];
                }
            }
        }

        $currencyID = isset($accountContext['CURRENCY_ID'])
            ? $accountContext['CURRENCY_ID'] : CCrmCurrency::GetBaseCurrencyID();

        $exchRate = isset($accountContext['EXCH_RATE'])
            ? $accountContext['EXCH_RATE'] : null;
        // <-- Preparing accounting context

        $productIDs = array();
        $products = array();
        foreach($arRows as &$arRow)
        {
            $productID = isset($arRow['PRODUCT_ID']) ? intval($arRow['PRODUCT_ID']) : 0;
            if($productID > 0 && !in_array($productID, $productIDs, true))
            {
                $productIDs[] = $productID;
            }
        }
        unset($arRow);

        if(!empty($productIDs))
        {
            $dbProduct = CCrmProduct::GetList(
                array(),
                array('ID' => $productIDs),
                array('ID', 'NAME')
            );
            if(is_object($dbProduct))
            {
                while($product = $dbProduct->Fetch())
                {
                    $products[intval($product['ID'])] = $product;
                }
            }
        }

        $measurelessProductIDs = array();
        $arSafeRows = array();
        foreach($arRows as &$arRow)
        {
            $rowID = isset($arRow['ID']) ? (int)$arRow['ID'] : 0;
            $productID = $arRow['PRODUCT_ID'] = isset($arRow['PRODUCT_ID']) ? (int)$arRow['PRODUCT_ID'] : 0;
            $productName = $arRow['PRODUCT_NAME'] = isset($arRow['PRODUCT_NAME']) ? $arRow['PRODUCT_NAME'] : '';
            $arRow['MEASURE_CODE'] = isset($arRow['MEASURE_CODE']) ? (int)$arRow['MEASURE_CODE'] : 0;
            $arRow['MEASURE_NAME'] = isset($arRow['MEASURE_NAME']) ? $arRow['MEASURE_NAME'] : '';
            $arRow['CUSTOMIZED'] = isset($arRow['CUSTOMIZED']) && mb_strtoupper($arRow['CUSTOMIZED']) === 'Y' ? 'Y' : 'N';
            $arRow['SORT'] = isset($arRow['SORT']) ? (int)$arRow['SORT'] : 0;

            $prices = static::preparePrices($arRow, $currencyID, $exchRate);
            if (false === $prices)
            {
                return false;
            }

            $measureCode = $arRow['MEASURE_CODE'];
            if($productID > 0 && $measureCode <= 0)
            {
                if(!in_array($productID, $measurelessProductIDs, true))
                {
                    $measurelessProductIDs[] = $productID;
                }
            }

            $safeRow = array(
                'ID' => $rowID,
                'OWNER_TYPE' => $ownerType,
                'OWNER_ID' => $ownerID,
                'PRODUCT_ID' => $productID,
                'PRODUCT_NAME' => $productName,

                'PRICE' => $prices['PRICE'],
                'PRICE_EXCLUSIVE' => $prices['PRICE_EXCLUSIVE'],
                'PRICE_NETTO' => $prices['PRICE_NETTO'],
                'PRICE_BRUTTO' => $prices['PRICE_BRUTTO'],
                'QUANTITY'=> $prices['QUANTITY'],
                'DISCOUNT_TYPE_ID' => $prices['DISCOUNT_TYPE_ID'],
                'DISCOUNT_SUM' => $prices['DISCOUNT_SUM'],
                'DISCOUNT_RATE' => $prices['DISCOUNT_RATE'],
                'TAX_RATE' => $prices['TAX_RATE'],
                'TAX_INCLUDED' => $prices['TAX_INCLUDED'],

                'UF_RESERVE_QUANTITY' => $arRow['UF_RESERVE_QUANTITY'] ?? 0,

                'MEASURE_CODE' => $measureCode,
                'MEASURE_NAME' => $arRow['MEASURE_NAME'],
                'CUSTOMIZED' => 'Y', //Is always enabled for disable requests to product catalog
                'SORT' => $arRow['SORT'],
            );

            if(isset($prices['PRICE_ACCOUNT']))
            {
                $safeRow['PRICE_ACCOUNT'] = $prices['PRICE_ACCOUNT'];
            }

            //append user fields
            foreach (self::$userFields as $field => $fieldParams) {
                $safeRow[$field] = $arRow[$field];
            }

            $safeRow['ORIGINAL_ROW'] = $arRow;
            $arSafeRows[] = &$safeRow;
            unset($safeRow);
        }
        unset($arRow);

        if(!empty($measurelessProductIDs))
        {
            $defaultMeasureInfo = \Bitrix\Crm\Measure::getDefaultMeasure();
            $measureInfos = \Bitrix\Crm\Measure::getProductMeasures($measurelessProductIDs);
            foreach($arSafeRows as &$safeRow)
            {
                if($safeRow['MEASURE_CODE'] > 0)
                {
                    continue;
                }

                $productID = $safeRow['PRODUCT_ID'];
                if(isset($measureInfos[$productID]) && !empty($measureInfos[$productID]))
                {
                    $measureInfo = $measureInfos[$productID][0];
                    $safeRow['MEASURE_CODE'] = $measureInfo['CODE'];
                    $safeRow['MEASURE_NAME'] = isset($measureInfo['SYMBOL']) ? $measureInfo['SYMBOL'] : '';
                }
                elseif($defaultMeasureInfo !== null)
                {
                    $safeRow['MEASURE_CODE'] = $defaultMeasureInfo['CODE'];
                    $safeRow['MEASURE_NAME'] = isset($defaultMeasureInfo['SYMBOL']) ? $defaultMeasureInfo['SYMBOL'] : '';
                }

                if(!isset($safeRow['MEASURE_NAME']) || $safeRow['MEASURE_NAME'] === '')
                {
                    $safeRow['MEASURE_NAME'] = '-';
                }
            }
            unset($safeRow);
        }

        $arPresentRows = self::LoadRows($ownerType, $ownerID, true);

        // Registering events -->
        if($regEvent)
        {
            $arRowIDs = array();
            foreach($arRows as &$arRow)
            {
                if(isset($arRow['ID']))
                {
                    $arRowIDs[] = intval($arRow['ID']);
                }

                $rowID = isset($arRow['ID']) ? intval($arRow['ID']) : 0;
                if($rowID <= 0)
                {
                    // Row was added
                    self::RegisterAddEvent($ownerType, $ownerID, $arRow, $checkPerms);
                    continue;
                }

                $arPresentRow = isset($arPresentRows[$rowID]) ? $arPresentRows[$rowID] : null;
                if($arPresentRow)
                {
                    // Row was modified
                    self::RegisterUpdateEvent($ownerType, $ownerID, $arRow, $arPresentRow, $checkPerms);
                }
            }
            unset($arRow);

            foreach($arPresentRows as $rowID => &$arPresentRow)
            {
                if(!in_array($rowID, $arRowIDs, true))
                {
                    // Product  was removed
                    self::RegisterRemoveEvent($ownerType, $ownerID, $arPresentRow, $checkPerms);
                }
            }
        }
        // <-- Registering events

        $result = self::DoSaveRows($ownerType, $ownerID, $arSafeRows);

        // Update list of taxes
        if (!isset($totalInfo['CURRENCY_ID']))
            $totalInfo['CURRENCY_ID'] = $currencyID;
        self::UpdateTotalInfo($ownerType, $ownerID, $totalInfo);

        // Disable sum synchronization if product rows are empty
        if($result && $syncOwner && (count($arPresentRows) > 0 || count($arSafeRows) > 0))
        {
            self::SynchronizeOwner($ownerType, $ownerID, $checkPerms, $totalInfo);
        }

        self::updateEntityFields($ownerType, $ownerID, $arPresentRows ?? []);
        return $result;
    }

    /**
     * Synchronize owner fields if required.
     * For example, update Deal OPPORTUNITY field according to totals of the product rows.
     * @param string $ownerType Owner Type Character ('D' - Deal, 'L' - Lead, 'Q' - Quote).
     * @param int $ownerID Owner ID.
     * @param bool $checkPerms Check Permission Flag.
     * @param array $totalInfo Reserveds parameter.
     */
    protected static function SynchronizeOwner($ownerType, $ownerID, $checkPerms = true, $totalInfo = array())
    {
        $ownerType = mb_strtoupper(strval($ownerType));
        $ownerID = intval($ownerID);

        if($ownerType === CCrmOwnerTypeAbbr::Deal)
        {
            CCrmDeal::SynchronizeProductRows($ownerID, $checkPerms);
        }
        elseif($ownerType === CCrmOwnerTypeAbbr::Quote)
        {
            CCrmQuote::SynchronizeProductRows($ownerID, $checkPerms);
        }
        elseif($ownerType === CCrmOwnerTypeAbbr::Lead)
        {
            CCrmLead::SynchronizeProductRows($ownerID, $checkPerms);
        }
        elseif($ownerType === CCrmOwnerTypeAbbr::Quote)
        {

        }
        elseif($ownerType === CCrmOwnerTypeAbbr::SmartInvoice)
        {

        }
        elseif(\CCrmOwnerType::ResolveID($ownerType) > 0)
        {
            self::SynchronizeProductRows($ownerType, $ownerID, $checkPerms, $totalInfo);
        }
    }

    protected static function updateEntityFields($ownerType, $ownerID, array $arPresentRows = [])
    {
        if (empty($arPresentRows))
            $arPresentRows = self::LoadRows($ownerType, $ownerID, true);

        $supplier = null;
        foreach ($arPresentRows as $product)
        {
            if (empty($product['PROPERTY_SUPPLIER_OF_GOODS'])) continue;
            $supplier = $product['PROPERTY_SUPPLIER_OF_GOODS'];
            break;
        }

        if (empty($supplier)) return;

        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::ResolveID($ownerType));
        $fields = $factory->getUserFields();
        if (!in_array("UF_CRM_3_1667479782", array_keys($fields))) return;

        $result = $factory->getDataClass()::update($ownerID, [
            'UF_CRM_3_1667479782' => $supplier,
            'UF_CRM_3_1667479589' => $supplier,
        ]);
    }

    public static function CalculateTotalInfo($ownerType, $ownerID, $checkPerms = true, $params = null, $rows = null, $totalInfo = array())
    {
        if (!is_array($totalInfo))
        {
            $totalInfo = array();
        }

        $result = false;
        if (isset($totalInfo['OPPORTUNITY']) && isset($totalInfo['TAX_VALUE']))
        {
            $result = array(
                'OPPORTUNITY' => round(doubleval($totalInfo['OPPORTUNITY']), 2),
                'TAX_VALUE' => round(doubleval($totalInfo['TAX_VALUE']), 2)
            );
        }
        else
        {
            $arParams = null;
            if ($ownerID <= 0)
            {
                $arParams = $params;
            }
            else
            {
                if ($ownerType === 'L')
                {
                    $arParams = CCrmLead::GetByID($ownerID, $checkPerms);
                }
                elseif ($ownerType === 'D')
                {
                    $arParams = CCrmDeal::GetByID($ownerID, $checkPerms);
                }
                elseif ($ownerType === CCrmQuote::OWNER_TYPE)
                {
                    $arParams = CCrmQuote::GetByID($ownerID, $checkPerms);
                }
                elseif(\CCrmOwnerType::ResolveID($ownerType) > 0)
                {
                    $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(
                        \CCrmOwnerType::ResolveID($ownerType)
                    );
                    $arParams = $factory->getDataClass()::getById($ownerID)->fetch();
                }
            }

            if(!is_array($arParams))
            {
                return $result;
            }

            $arRows = null;
            if (is_array($rows))
            {
                $arRows = $rows;
            }
            elseif($ownerID > 0)
            {
                $arRows = self::LoadRows($ownerType, $ownerID);
            }

            if (!is_array($arRows))
            {
                return $result;
            }

            $currencyID = isset($params['CURRENCY_ID']) ? $params['CURRENCY_ID'] : '';
            if($currencyID === '')
            {
                $currencyID = CCrmCurrency::GetBaseCurrencyID();
            }

            $companyID = isset($params['COMPANY_ID']) ? intval($params['COMPANY_ID']) : 0;
            $contactID = isset($params['CONTACT_ID']) ? intval($params['CONTACT_ID']) : 0;

            // Determine person type
            $personTypeId = 0;
            $arPersonTypes = CCrmPaySystem::getPersonTypeIDs();
            if ($companyID > 0 && isset($arPersonTypes['COMPANY']))
            {
                $personTypeId = $arPersonTypes['COMPANY'];
            }
            elseif ($contactID > 0 && isset($arPersonTypes['CONTACT']))
            {
                $personTypeId = $arPersonTypes['CONTACT'];
            }

            $enableSaleDiscount = false;
            $siteID = '';
            if (defined('SITE_ID'))
            {
                $siteID = SITE_ID;
            }
            else
            {
                $obSite = CSite::GetList('def', 'desc', array('ACTIVE' => 'Y'));
                if ($obSite && $arSite = $obSite->Fetch())
                    $siteID= $arSite["LID"];
                unset($obSite, $arSite);
            }

            $calculateOptions = array();
            if (CCrmTax::isTaxMode())
            {
                $calculateOptions['LOCATION_ID'] = isset($arParams['LOCATION_ID']) ? $arParams['LOCATION_ID'] : '';
            }
            $calculated = CCrmSaleHelper::Calculate($arRows, $currencyID, $personTypeId, $enableSaleDiscount, $siteID, $calculateOptions);
            $totalApplicationPrice = 0.00;

            foreach ($arRows as $arRow) {
                if (!empty($arRow['UF_APPLICATION_PRICE'])) {
                    $totalApplicationPrice += (float)$arRow['UF_APPLICATION_PRICE'];
                }
            }

            $result = array(
                'OPPORTUNITY' => (isset($calculated['PRICE']) ? round(doubleval($calculated['PRICE']), 2) : 0.0) + $totalApplicationPrice,
                'TAX_VALUE' => isset($calculated['TAX_VALUE']) ? round(doubleval($calculated['TAX_VALUE']), 2) : 0.0
            );
        }

        return $result;
    }

    protected static function NeedForUpdate(array $original, array $modified)
    {
        $result = (
            isset($modified['PRODUCT_ID']) && $modified['PRODUCT_ID'] != $original['PRODUCT_ID'] ||
            isset($modified['PRODUCT_NAME']) && $modified['PRODUCT_NAME'] != $original['PRODUCT_NAME'] ||
            isset($modified['PRICE']) && $modified['PRICE'] != $original['PRICE'] ||
            isset($modified['PRICE_ACCOUNT']) && $modified['PRICE_ACCOUNT'] != $original['PRICE_ACCOUNT'] ||
            isset($modified['PRICE_EXCLUSIVE']) && $modified['PRICE_EXCLUSIVE'] != $original['PRICE_EXCLUSIVE'] ||
            isset($modified['PRICE_NETTO']) && $modified['PRICE_NETTO'] != $original['PRICE_NETTO'] ||
            isset($modified['PRICE_BRUTTO']) && $modified['PRICE_BRUTTO'] != $original['PRICE_BRUTTO'] ||
            isset($modified['QUANTITY']) && $modified['QUANTITY'] != $original['QUANTITY'] ||
            isset($modified['DISCOUNT_TYPE_ID']) && $modified['DISCOUNT_TYPE_ID'] != $original['DISCOUNT_TYPE_ID'] ||
            isset($modified['DISCOUNT_RATE']) && $modified['DISCOUNT_RATE'] != $original['DISCOUNT_RATE'] ||
            isset($modified['DISCOUNT_SUM']) && $modified['DISCOUNT_SUM'] != $original['DISCOUNT_SUM'] ||
            isset($modified['TAX_RATE']) && $modified['TAX_RATE'] != $original['TAX_RATE'] ||
            isset($modified['TAX_INCLUDED']) && $modified['TAX_INCLUDED'] != $original['TAX_INCLUDED'] ||
            isset($modified['CUSTOMIZED']) && $modified['CUSTOMIZED'] != $original['CUSTOMIZED'] ||
            isset($modified['MEASURE_CODE']) && $modified['MEASURE_CODE'] != $original['MEASURE_CODE'] ||
            isset($modified['MEASURE_NAME']) && $modified['MEASURE_NAME'] != $original['MEASURE_NAME'] ||
            isset($modified['SORT']) && $modified['SORT'] != $original['SORT']
        );

        $userFields = $GLOBALS['USER_FIELD_MANAGER']->GetUserFields(self::$sUFEntityID);

        foreach ($userFields as $field => $arParams) {
            $result = $result || (isset($modified[$field]) && $modified[$field] != $original[$field]);
        }

        return $result;
    }

    private static function RegisterAddEvent($ownerType, $ownerID, $arRow, $checkPerms)
    {
        IncludeModuleLangFile(__FILE__);

        $productID = isset($arRow['PRODUCT_ID']) ? intval($arRow['PRODUCT_ID']) : 0;
        $productName = isset($arRow['PRODUCT_NAME']) ? $arRow['PRODUCT_NAME'] : '';
        $productName = self::NormalizeProductName($productID, $productName);

        $arFields = array(
            'EVENT_NAME' => GetMessage('CRM_EVENT_PROD_ROW_ADD'),
            'EVENT_TEXT_1' => $productName,
            'EVENT_TEXT_2' => ''
        );

        return self::RegisterEvents($ownerType, $ownerID, array($arFields), $checkPerms);
    }

    private static function RegisterUpdateEvent($ownerType, $ownerID, $arRow, $arPresentRow, $checkPerms)
    {
        IncludeModuleLangFile(__FILE__);

        $productID = isset($arRow['PRODUCT_ID']) ? intval($arRow['PRODUCT_ID']) : 0;
        $productName = isset($arRow['PRODUCT_NAME']) ? $arRow['PRODUCT_NAME'] : '';
        $productName = self::NormalizeProductName($productID, $productName);
        $presentProductID = isset($arPresentRow['PRODUCT_ID']) ? intval($arPresentRow['PRODUCT_ID']) : 0;
        $presentproductName = isset($arPresentRow['PRODUCT_NAME']) ? $arPresentRow['PRODUCT_NAME'] : '';
        $presentproductName = self::NormalizeProductName($presentProductID, $presentproductName);

        $arEvents = array();
        if($arPresentRow['PRODUCT_ID'] !== $arRow['PRODUCT_ID'])
        {
            // Product was changed
            $arEvents[] = array(
                'EVENT_NAME' => GetMessage('CRM_EVENT_PROD_ROW_UPD'),
                'EVENT_TEXT_1' => $presentproductName,
                'EVENT_TEXT_2' => $productName
            );
        }
        else
        {
            if($arRow['PRODUCT_ID'] === 0)
            {
                $nameChanged = $arRow['PRODUCT_NAME'] !== $arPresentRow['PRODUCT_NAME'];
            }
            else
            {
                //If PRODUCT_NAME is not emty - user set custom name
                $nameChanged = ($arRow['PRODUCT_NAME'] !== '' && $arRow['PRODUCT_NAME'] !== $arPresentRow['PRODUCT_NAME'])
                    || ($arRow['PRODUCT_NAME'] === '' && $arPresentRow['PRODUCT_NAME'] !== $arPresentRow['ORIGINAL_PRODUCT_NAME']);
            }

            if($nameChanged)
            {
                // Product name was changed
                $arEvents[] = array(
                    'EVENT_NAME' => GetMessage('CRM_EVENT_PROD_ROW_NAME_UPD'),
                    'EVENT_TEXT_1' => $arPresentRow['PRODUCT_NAME'],
                    'EVENT_TEXT_2' => $arRow['PRODUCT_NAME'] !== '' ? $arRow['PRODUCT_NAME'] : $arPresentRow['ORIGINAL_PRODUCT_NAME']
                );
            }

            $productName = $arRow['PRODUCT_NAME'];
            if($productName === '' && $arRow['PRODUCT_ID'] > 0)
            {
                $productName = $arPresentRow['ORIGINAL_PRODUCT_NAME'];
            }

            $price = round(doubleval($arRow['PRICE']), 2);
            $presentPrice = round(doubleval($arPresentRow['PRICE']), 2);
            if($presentPrice !== $price)
            {
                // Product price was changed
                $arEvents[] = array(
                    'EVENT_NAME' => GetMessage('CRM_EVENT_PROD_ROW_PRICE_UPD', array('#NAME#' => $productName)),
                    'EVENT_TEXT_1' => $arPresentRow['PRICE'],
                    'EVENT_TEXT_2' => $arRow['PRICE']
                );
            }

            $quantity = round(doubleval($arRow['QUANTITY']), 4);
            $presentQuantity = round(doubleval($arPresentRow['QUANTITY']), 4);
            if($presentQuantity !== $quantity)
            {
                // Product  quantity was changed
                $arEvents[] = array(
                    'EVENT_NAME' => GetMessage('CRM_EVENT_PROD_ROW_QTY_UPD', array('#NAME#' => $productName)),
                    'EVENT_TEXT_1' => $arPresentRow['QUANTITY'],
                    'EVENT_TEXT_2' => $arRow['QUANTITY']
                );
            }

            $discountSum = round(doubleval($arRow['DISCOUNT_SUM']), 2);
            $presentDiscountSum = round(doubleval($arPresentRow['DISCOUNT_SUM']), 2);
            if($discountSum !== $presentDiscountSum)
            {
                // Product  discount was changed
                $arEvents[] = array(
                    'EVENT_NAME' => GetMessage('CRM_EVENT_PROD_ROW_DISCOUNT_UPD', array('#NAME#' => $productName)),
                    'EVENT_TEXT_1' => $presentDiscountSum,
                    'EVENT_TEXT_2' => $discountSum
                );
            }
            unset($discountSum, $presentDiscountSum);

            $taxRate = round(doubleval($arRow['TAX_RATE']), 2);
            $presentTaxRate = round(doubleval($arPresentRow['TAX_RATE']), 2);
            if($presentTaxRate !== $taxRate)
            {
                // Product  tax was changed
                $arEvents[] = array(
                    'EVENT_NAME' => GetMessage('CRM_EVENT_PROD_ROW_TAX_UPD', array('#NAME#' => $productName)),
                    'EVENT_TEXT_1' => "{$arPresentRow['TAX_RATE']}%",
                    'EVENT_TEXT_2' => "{$arRow['TAX_RATE']}%"
                );
            }

            if($arPresentRow['MEASURE_NAME'] !== $arRow['MEASURE_NAME'])
            {
                // Product  measure was changed
                $arEvents[] = array(
                    'EVENT_NAME' => GetMessage('CRM_EVENT_PROD_ROW_MEASURE_UPD', array('#NAME#' => $productName)),
                    'EVENT_TEXT_1' => $arPresentRow['MEASURE_NAME'],
                    'EVENT_TEXT_2' => $arRow['MEASURE_NAME']
                );
            }
        }

        return count($arEvents) > 0 ? self::RegisterEvents($ownerType, $ownerID, $arEvents, $checkPerms) : false;
    }

    private static function RegisterRemoveEvent($ownerType, $ownerID, $arPresentRow, $checkPerms)
    {
        IncludeModuleLangFile(__FILE__);

        $productID = isset($arPresentRow['PRODUCT_ID']) ? intval($arPresentRow['PRODUCT_ID']) : 0;
        $productName = isset($arPresentRow['PRODUCT_NAME']) ? $arPresentRow['PRODUCT_NAME'] : '';
        $productName = self::NormalizeProductName($productID, $productName);

        $arFields = array(
            'EVENT_NAME' => GetMessage('CRM_EVENT_PROD_ROW_REM'),
            'EVENT_TEXT_1' => $productName,
            'EVENT_TEXT_2' => ''
        );

        return self::RegisterEvents($ownerType, $ownerID, array($arFields), $checkPerms);
    }

    private static function RegisterEvents($ownerType, $ownerID, $arEvents, $checkPerms)
    {
        global $USER;
        $userID = isset($USER) && ($USER instanceof CUser) && ('CUser' === get_class($USER)) ? $USER->GetId() : 0;

        $CCrmEvent = new CCrmEvent();
        foreach($arEvents as $arEvent)
        {
            $arEvent['EVENT_TYPE'] = 1;
            $arEvent['ENTITY_TYPE'] = CCrmOwnerTypeAbbr::ResolveName($ownerType);
            $arEvent['ENTITY_ID'] = $ownerID;
            $arEvent['ENTITY_FIELD'] = 'PRODUCT_ROWS';

            if($userID > 0)
            {
                $arEvent['USER_ID']  = $userID;
            }

            $CCrmEvent->Add($arEvent, $checkPerms);
        }

        return true;
    }

    public static function updateProductXmlId (int $ID, string $xmlID)
	{
	    global $DB;

	    // при обновлении через CIblockElement::Update у торговых предложений слетает привязка к товару
		$update = $DB->query("UPDATE b_iblock_element SET TIMESTAMP_X = NOW(), XML_ID = '" .
            trim($xmlID, " \t\n\r") . "' WHERE ID = " . $ID);
        \CIBlockElement::UpdateSearch($ID, true);
        return !$update ? false : true;
	}

    protected static function SynchronizeProductRows($ownerType, $ownerID, $checkPerms = true, $totalInfo = array())
    {
        $arTotalInfo = self::CalculateTotalInfo($ownerType, $ownerID, $checkPerms, null, null, $totalInfo);
        if (is_array($arTotalInfo))
        {
            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(
                \CCrmOwnerType::ResolveID($ownerType)
            );
            $item = $factory->getItem($ownerID);

            $taxValue = isset($arTotalInfo['TAX_VALUE']) ? $arTotalInfo['TAX_VALUE'] : 0.0;
            $item->setTaxValue($taxValue);

            if ($item->getIsManualOpportunity())
            {
                $opportunity = isset($arTotalInfo['OPPORTUNITY']) ? $arTotalInfo['OPPORTUNITY'] : 0.0;
                $item->setOpportunity($opportunity);
            }

            $item->save();
        }
    }
}
