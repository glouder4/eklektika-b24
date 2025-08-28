<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main;

Main\Loader::includeModule('iblock');
Main\Loader::includeModule('kitconsulting.productapplications');

class CatalogProductApplicationsAjaxController extends Main\Engine\Controller
{
    public function getProductAction($product_id) {
        if (!Main\Loader::includeModule('iblock'))
        {
			return $this->sendErrorResponse('Could not load "iblock" module.');
		}

        if (!Main\Loader::includeModule('kitconsulting.productapplications'))
        {
            return $this->sendErrorResponse('Could not load "kitconsulting.productapplications" module.');
        }

//        $res = \Bitrix\Catalog\Model\Product::getList([
//            'filter' => [
//                '=ID' => $product_id
//            ]
//        ]);

        $res = CIBlockElement::GetList([],[
            'ID' => $product_id
        ]);

        $product = $res->Fetch();

        return [
            'ID' => $product['ID'],
            'NAME' => $product['NAME']
        ];
    }

    public function saveApplicationsAction($rows) {
        if (!Main\Loader::includeModule('kitconsulting.productapplications'))
        {
            return $this->sendErrorResponse('Could not load "kitconsulting.productapplications" module.');
        }

        $params = $this->getUnsignedParameters();

        $ownerProductId = $params['ENTITY_ID'];
        \Kitconsulting\ProductApplications\Entity\ProductApplication::saveRows($ownerProductId, $rows);

        return true;
    }

    private function sendErrorResponse(string $message)
    {
        $errorCollection = new Main\ErrorCollection();
        $errorCollection->setError(new Main\Error($message));

        return Main\Engine\Response\AjaxJson::createError($errorCollection);
    }
}