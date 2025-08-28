<?php

namespace Kitconsulting\ProductApplicationsExtension;

class ApplicationsHelper
{
    public function __construct()
    {
        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('kitconsulting.productapplications');
    }

    public function loadProductPrices($productId, $product)
    {
        $properties = \Bitrix\Iblock\ElementPropertyTable::getList(
            [
                'select' => [
                    'VALUE',
                    'VALUE_NUM',
                    'DESCRIPTION',
                    'PROPERTY_NAME' => 'PB.NAME'
                ],
                'filter' => [
                    'IBLOCK_ELEMENT_ID' => $productId,
                ],
                'runtime' => [
                    'PB' => [
                        'data_type' => \Bitrix\Iblock\PropertyTable::class,
                        'reference' => [
                            '=this.IBLOCK_PROPERTY_ID' => 'ref.ID',
                        ],
                        'join_type' => 'inner'
                    ],
                ]
            ]
        )->fetchAll();

        foreach ($properties as $property) {
            switch ($property['PROPERTY_NAME']) {
                case 'Настройка тиража (оптовая цена)':
                    $product['APPLICATION_PRICE_OPT'] = $property['VALUE_NUM'];
                    break;
                case 'Настройка тиража (рекламная цена)':
                    $product['APPLICATION_PRICE_ADV'] = $property['VALUE_NUM'];
                    break;
            }
        }

        $pricesResult = \Bitrix\Catalog\GroupTable::getList([
            'select' => ['ID', 'NAME'],
            'filter' => [
                'NAME' => ['Рекламная цена', 'Оптовая цена']
            ]
        ])->fetchAll();

        if ($pricesResult) {
            foreach ($pricesResult as $priceType) {
                $priceResult = \Bitrix\Catalog\PriceTable::getList([
                    'select' => ['PRICE'],
                    'filter' => [
                        'PRODUCT_ID' => $productId,
                        'CATALOG_GROUP_ID' => $priceType['ID']
                    ]
                ])->fetch();

                if ($priceResult) {
                    switch ($priceType['NAME']) {
                        case 'Рекламная цена':
                            $product['PRICE_ADV'] = $priceResult['PRICE'];
                            break;
                        case 'Оптовая цена':
                            $product['PRICE_OPT'] = $priceResult['PRICE'];
                            break;
                    }
                }
            }
        }

        return $product;
    }
}