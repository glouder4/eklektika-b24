<?php

namespace Kitconsulting\Products;

use Bitrix;
use Bitrix\Main\Loader;
use Bitrix\Rest\RestException;


class Rest
{
    public static function onRestServiceBuildDescription()
    {
        return [
            'kit.productapplications' => [
                'kitconsulting.1c.stock.update' => [__CLASS__, 'stockUpdate'],
            ]
        ];
    }

    public static function stockUpdate($query, $n, \CRestServer $server)
    {
        $products = $query;
        if (!is_array($products)) {
            throw new RestException('Product list is empty.');
        }

        $xmlIds = array_column($products, "XML_ID");
        if (empty($xmlIds))
        {
            throw new RestException('Product list is empty.');
        }

        $reserveUpdated = false;
        $productsList = \CIBlockElement::GetList([], ['XML_ID' => $xmlIds]);
        while ($product = $productsList->Fetch())
        {
            $id = $product['ID'];
            $reservedInfoKey = array_search($product['XML_ID'], $xmlIds);
            $reservedInfo = $products[$reservedInfoKey];

            $reserved = $reservedInfo['RESERVED'];
            if (empty($reserved)) continue;
            \CCatalogProduct::Update($id, [
                "QUANTITY_RESERVED" => $reserved
            ]);
            $reserveUpdated = true;
        }

        if (!$reserveUpdated)
        {
            throw new RestException('Reservation data has not been transferred.');
        }

        return true;
    }
}