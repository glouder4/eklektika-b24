<?php

namespace Kit\Scripts;

use Bitrix\Crm\Item;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventResult;
use Kit\Scripts\Cml\PropertiesConverter;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Kitconsulting\ExtendedProductsRow\CCrmProductRowExtended;

class EventHandler
{
    const ALLOWED_IBLOCK_IDS = [
        24 => 'CML2_TRAITS',
        30 => 'CML2_ATTRIBUTES'
    ];
    const HANDLER = "http://Администратор:@192.168.1.202/UNF_PROD/hs/Bitrix/";
    const HTTP_SOCKET_TIMEOUT = 10;
    const HTTP_STREAM_TIMEOUT = 10;

    const ARTICLE_PROPERTY = "ARTIKUL_BITRIKS";

    public static function onSmartDesignCreate(\Bitrix\Main\Event $event) {

        /** @var Item $item */
        $item = $event->getParameter('item');

        if($item['CATEGORY_ID'] != 3) {
            return EventResult::SUCCESS;
        }

        $dealId = null;

        // Поле parentId2 появляется только в массиве.
        $itemValues = $item->toArray();

        if(isset($itemValues['parentId2']) && $itemValues['parentId2'] > 0) {
            $dealId = $itemValues['parentId2'];
        }

        if($dealId) {
            $folderStruct = new FolderStructure();

            try {
                $folderPath = $folderStruct->createForDeal($dealId);
                $item['UF_CRM_3_TEMPLATE_FOLDER'] = $folderPath;
                if($item->isChanged('UF_CRM_3_TEMPLATE_FOLDER')) {
                    $item->save();
                }
            } catch (\Exception $exception) {
                file_put_contents(__DIR__.'/log.txt', $exception->getMessage()."\n", FILE_APPEND);
                return EventResult::ERROR;
            }
        }

        return EventResult::SUCCESS;
    }

    public static function onBeforeIBlockElementUpdate(&$arFields) {
		return true;

		// file_put_contents(__DIR__.'/check.txt', var_export($arFields, true), FILE_APPEND);

        if(!in_array($arFields['IBLOCK_ID'], array_keys(self::ALLOWED_IBLOCK_IDS))) {
            return;
        }

        //PropertiesConverter::convertProps($arFields, self::ALLOWED_IBLOCK_IDS[$arFields['IBLOCK_ID']]);
    }

    protected static function getPropertyId (int $iBlockId, ?int $elementId)
    {
        return \CIBlockElement::GetProperty(
            $iBlockId,
            $elementId ?? 0,
            [],
            [
                'CODE' => self::ARTICLE_PROPERTY
            ]
        )->Fetch()['ID'] ?? 0;
    }

    protected static function generateProductArticle (int $numberSet, ?string $article) : string
    {
        if (!empty($article)) return $article;
        return $numberSet . '.' . sprintf('%05d', self::getArticleMaxCode($numberSet) + 1);
    }

    protected static function getArticleMaxCode (int $numberSet)
    {
        $articleMaxCode = 0;
        $element = \CIBlockElement::GetList(
            [
                'PROPERTY_' . self::ARTICLE_PROPERTY => "DESC"
            ],
            [
                '%PROPERTY_' . self::ARTICLE_PROPERTY => $numberSet . '.'
            ],
            false,
            [
                'nPageSize' => 1
            ],
            [
                'ID', 'PROPERTY_PROPERTY_' . self::ARTICLE_PROPERTY
            ]
        )->GetNextElement();
        if (!empty($element))
        {
            $articleMaxCode = $element->GetFields()['PROPERTY_' . self::ARTICLE_PROPERTY . '_VALUE'];
            $articleMaxCode = str_replace($numberSet . '.', '', $articleMaxCode);
        }

        return $articleMaxCode;
    }

    protected static function getHttpClient()
    {
        return new HttpClient(array(
            'socketTimeout' => static::HTTP_SOCKET_TIMEOUT,
            'streamTimeout' => static::HTTP_STREAM_TIMEOUT,
        ));
    }

    public static function OnAfterDealMoveToCategory (\Bitrix\Main\Event $event)
    {
        self::sendTo1C($event->getParameter('id'),
            'Deal',
            [
                'STAGE_ID' => $event->getParameter('stageId'),
                'CATEGORY_ID' => $event->getParameter('categoryId')
            ]
        );
    }

    public static function omSmartItemUpdate (\Bitrix\Main\Event $event)
	{
		/** @var Item $item */
		$item = $event->getParameter('item');

		if (self::parseStageCategory($item->getEntityTypeId(), $item['STAGE_ID']) !=
			self::parseStageCategory($item->getEntityTypeId(), $item['PREVIOUS_STAGE_ID']))
		{
		    self::sendTo1C($item->getId(),
                'SmartProcess',
                [
                    'STAGE_ID' => $item->getStageId(),
                    'CATEGORY_ID' => $item->getCategoryId()
                ]
            );
		}
	}

	protected static function sendTo1C (int $entityId, string $entityType, array $params)
    {
        $target = self::parseHandlerTarget(parse_url(self::HANDLER . $entityType . '?ID=' . $entityId));

        $httpClient = self::getHttpClient();
        $httpClient->setHeader('Content-Type', 'application/json', true);
        $httpResult = $httpClient->post(
            $target,
            json_encode($params)
        );

//        file_put_contents($_SERVER["DOCUMENT_ROOT"] . '/local/1cupdate.txt',
//            $httpResult . PHP_EOL, FILE_APPEND);
    }

	public static function parseStageCategory (int $entityTypeId, string $stage)
	{
		return str_replace("DT{$entityTypeId}_", "", explode(":", $stage)[0]);
	}

	public static function parseHandlerTarget (array $handlerData)
    {
        $target = $handlerData['scheme'] . '://';
        if (isset($handlerData['user']) || isset($handlerData['pass']))
        {
            $target .= $handlerData['user'];
            if (isset($handlerData['pass']))
            {
                $target .= ':'. $handlerData['pass'];
            }
            $target .= '@';
        }
        $target .= $handlerData['host'];
        if (isset($handlerData['port']))
        {
            $target .= ':'.$handlerData['port'];
        }
        if (isset($handlerData['path']))
        {
            $target .= \CHTTP::urnEncode($handlerData['path']);
        }
        if (isset($handlerData['query']))
        {
            $target .= '?'.\CHTTP::urnEncode($handlerData['query']);
        }
        if (isset($handlerData['fragment']))
        {
            $target .= '#'. \CHTTP::urnEncode($handlerData['fragment']);
        }

        return $target;
    }

    /**
     * Обновление поля "Артикул" при создании товара
     * @param $arFields
     */
    public static function OnBeforeIBlockElementAdd (&$arFields)
    {
        $propertyId = self::getPropertyId($arFields['IBLOCK_ID'], $arFields['ID']);
        if ($propertyId > 0)
        {
            $currentArticleKey = key($arFields['PROPERTY_VALUES'][$propertyId] ?? []);
            $currentArticleValue = $arFields['PROPERTY_VALUES'][$propertyId][$currentArticleKey]['VALUE'] ?? null;

            $newValue = self::generateProductArticle($arFields['IBLOCK_ID'], $currentArticleValue);
            if ($currentArticleValue != $newValue)
            {
                unset($arFields['PROPERTY_VALUES'][$propertyId][$currentArticleKey]);
                $arFields['PROPERTY_VALUES'][$propertyId]['n0']['VALUE'] = $newValue;
            }
        }
    }

    /**
     * Проверка на запрет работы с контрагентом
     */
    public static function OnBeforeCrmDealAdd (&$arFields)
    {
        global $contactID;

        if (!empty($contactID))
        {
            $contact = \CCrmContact::GetList(
                [],
                [
                    'ID' => $contactID,
                    'UF_CRM_1681120601710' => 1
                ],
                [
                    'UF_CRM_1681120601710'
                ]
            )->Fetch();

            if ($contact !== false)
            {
                $arFields['RESULT_MESSAGE'] = "Работа с данным контрагентом запрещена.";
                return false;
            }
        }

        if (!empty($arFields['COMPANY_ID']))
        {
            $company = \CCrmCompany::GetList(
                [],
                [
                    'ID' => $arFields['COMPANY_ID'],
                    'UF_CRM_1681120791520' => 1
                ],
                [
                    'UF_CRM_1681120791520'
                ]
            )->Fetch();

            if ($company !== false)
            {
                $arFields['RESULT_MESSAGE'] = "Работа с данным контрагентом запрещена.";
                return false;
            }
        }
    }

    /**
     * Проверка на запрет работы с контрагентом (контакт)
     */
    public static function OnBeforeCrmContactAdd (&$arFields)
    {
        if (isset($arFields['UF_CRM_1681120601710']) && $arFields['UF_CRM_1681120601710'] == 1)
        {
            $dealsCount = self::getClientDealsCount($arFields['ID'], 'contact');
            if ($dealsCount > 0)
            {
                $arFields['RESULT_MESSAGE'] = "По данному контрагенту есть открытая сделка.";
                return false;
            }
        }
    }

    /**
     * Проверка на запрет работы с контрагентом (компания)
     */
    public static function OnBeforeCrmCompanyAdd (&$arFields)
    {
        if (isset($arFields['UF_CRM_1681120791520']) && $arFields['UF_CRM_1681120791520'] == 1)
        {
            $dealsCount = self::getClientDealsCount($arFields['ID'], 'company');
            if ($dealsCount > 0)
            {
                $arFields['RESULT_MESSAGE'] = "По данному контрагенту есть открытая сделка.";
                return false;
            }
        }
    }

    /**
     * Получить количество активных сделок контрагента
     * @param int $ID
     * @param string $type
     * @return mixed
     */
    protected static function getClientDealsCount (int $ID, string $type = "contact")
    {
        $arFilter = [
            'OPENED' => "Y"
        ];
        if ($type === 'contact') $arFilter['CONTACT_ID'] = $ID;
        if ($type === 'company') $arFilter['COMPANY_ID'] = $ID;

        $result = \CCrmDeal::GetList(
            [],
            $arFilter,
            [
                'ID'
            ]
        );

        return $result->SelectedRowsCount();
    }

    public static function OnBeforeDynamicItemAdd_133 (&$arFields)
    {
        if (!empty($arFields['CONTACT_ID']))
        {
            $contact = \CCrmContact::GetList(
                [],
                [
                    'ID' => $arFields['CONTACT_ID'],
                    'UF_CRM_1681120601710' => 1
                ],
                [
                    'UF_CRM_1681120601710'
                ]
            )->Fetch();

            if ($contact !== false)
            {
                $arFields['RESULT_MESSAGE'] = "Работа с данным контрагентом запрещена.";
                return false;
            }
        }

        if (!empty($arFields['COMPANY_ID']))
        {
            $company = \CCrmCompany::GetList(
                [],
                [
                    'ID' => $arFields['COMPANY_ID'],
                    'UF_CRM_1681120791520' => 1
                ],
                [
                    'UF_CRM_1681120791520'
                ]
            )->Fetch();

            if ($company !== false)
            {
                $arFields['RESULT_MESSAGE'] = "Работа с данным контрагентом запрещена.";
                return false;
            }
        }
    }

    public static function OnBeforeSmartInvoiceAdd (&$arFields)
    {
        $check = self::OnBeforeDynamicItemAdd_133($arFields);
        if ($check === false && isset($arFields['RESULT_MESSAGE'])) {
            return false;
        }
    }

    public static function onAfterSmartInvoiceAdd(\Bitrix\Main\Event $event)
    {
        \Bitrix\Main\Loader::includeModule('kitconsulting.extendedproductsrow');

        /** @var Item $item */
        $item = $event->getParameter('item');

        if (!empty($item['PARENT_ID_' . \CCrmOwnerType::Deal]))
        {
            CCrmProductRowExtended::setPerRowInsert(true);

            $smartInvoiceRows = CCrmProductRowExtended::LoadRows(
                \CCrmOwnerTypeAbbr::SmartInvoice,
                $item->getId());

            $dealRows = CCrmProductRowExtended::LoadRows(
                \CCrmOwnerTypeAbbr::Deal,
                $item['PARENT_ID_' . \CCrmOwnerType::Deal]);

            $ufArrays = [];
            if (!empty($dealRows) && !empty($smartInvoiceRows))
            {
                $productsRowsId = [];
                foreach ($dealRows as $row)
                {
                    $ufArray = [];
                    foreach ($row as $key => $value)
                    {
                        if (mb_strpos($key, "UF_") !== false)
                        {
                            $ufArray[$key] = $value;
                        }
                    }

                    $ufArrays[] = $ufArray;
                    $productsRowsId[] = $row['ID'];
                }


                foreach ($smartInvoiceRows as $index => &$row)
                {
                    if ($ufArrays[$index]['UF_APPLICATION_PARENT_PRODUCT_ROW_ID'] > 0)
                    {
                        $parentIndex = array_search($ufArrays[$index]['UF_APPLICATION_PARENT_PRODUCT_ROW_ID'],
                            $productsRowsId);
                        $ufArrays[$index]['UF_APPLICATION_PARENT_PRODUCT_ROW_ID'] = $smartInvoiceRows[$parentIndex]['ID'];
                    }
                    $row = array_merge($row, $ufArrays[$index]);
                }

                CCrmProductRowExtended::SaveRows(
                    \CCrmOwnerTypeAbbr::SmartInvoice,
                    $item->getId(), $smartInvoiceRows, null, true, true, false);
            }

            CCrmProductRowExtended::setPerRowInsert(false);
        }
    }

    public static function onBeforeProductAdd(&$arFields) {
        if(empty($arFields['VAT_ID'])) {
            $arFields['VAT_ID'] = Option::get('kit.scripts', 'DEFAULT_VAT_ID', null);
            $arFields['VAT_INCLUDED'] = 'Y';
        }
        return true;
    }
}
