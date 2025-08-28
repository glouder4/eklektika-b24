<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Engine\Response;
use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\Loader;
use Bitrix\Crm;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\JsonController;
use Bitrix\Main\Engine\JsonPayload;
use Bitrix\UI\EntitySelector\BaseFilter;
use Bitrix\UI\EntitySelector\Dialog;
use Bitrix\UI\EntitySelector\Item;
use Bitrix\UI\EntitySelector\RecentItem;
use Bitrix\UI\EntitySelector\SearchQuery;

require_once __DIR__ . '/class/CustomDialog.php';
require_once __DIR__ . '/class/CustomProductProvider.php';

Loader::includeModule('kitconsulting.extendedproductsrow');

class ProductListController extends \Bitrix\Main\Engine\Controller
{
	/**
	 * @return \Bitrix\Main\Engine\Response\HtmlContent
	 * @throws \Bitrix\Main\LoaderException
	 */
	public function getProductGridAction(): Response\HtmlContent
	{
		if (!Loader::includeModule('crm'))
		{
			return $this->sendErrorResponse('Could not load "crm" module.');
		}

		$componentParams = $this->getUnsignedParameters();

		$template = $this->request->get('template') ?: '.default';

		return new Response\Component(
			'bitrix:crm.entity.product.list',
			$template,
			$componentParams,
			[
				'HIDE_ICONS' => 'Y',
				'ACTIVE_COMPONENT' => 'Y'
			]
		);
	}

    const STORE_GENERAL = 11;
    const STORE_VIRTUAL = 17;
    public function getProductAction(int $productId, array $options = []): ?array
    {
        $productSelector = new \Bitrix\Catalog\Controller\ProductSelector();
        $result = $productSelector->getProductAction($productId, $options);

        $stores = \CCatalogStore::GetList(
            ['PRODUCT_ID' => 'ASC', 'ID' => 'ASC'],
            ['ACTIVE' => 'Y', 'PRODUCT_ID' => $productId, '!ELEMENT_ID' => ""],
            false,
            false,
            ["ID", "TITLE", "ACTIVE", "PRODUCT_AMOUNT", "ELEMENT_ID"]
        );

        $storesList= [];
        while ($store = $stores->Fetch())
        {
            $storesList[$store['ELEMENT_ID']][$store['ID']] = $store;
        }

        $result['fields']['STORE_GENERAL'] = $storesList[$productId][self::STORE_GENERAL]['PRODUCT_AMOUNT'] ?? 0;
        $result['fields']['STORE_VIRTUAL'] = $storesList[$productId][self::STORE_VIRTUAL]['PRODUCT_AMOUNT'] ?? 0;

        $result['fields']['UF_RESERVE_QUANTITY'] = 0;
        $result['fields']['ROW_RESERVED'] = $result['fields']['COMMON_STORE_RESERVED'] ?? 0;
        $result['fields']['FREE_STORE'] = ($result['fields']['STORE_GENERAL'] - $result['fields']['COMMON_STORE_RESERVED']) ?? 0;

        $result['fields']['SUPPLIER_OF_GOODS'] = \Kitconsulting\ExtendedProductsRow\CCrmProductRowExtended::getCrmEntityName($result['fields']['PROPERTY_280']);

        return $result;
    }

	private function sendErrorResponse(string $message)
	{
		$errorCollection = new ErrorCollection();
		$errorCollection->setError(new Error($message));

		return Response\AjaxJson::createError($errorCollection);
	}

	public function doSearchAction(JsonPayload $payload)
	{
		$request = $payload->getData();
		$request = is_array($request) ? $request : [];

		$dialog = new CustomDialog(
			isset($request['dialog']) && is_array($request['dialog'])
				? $request['dialog'] : []
		);
		$searchQuery = new SearchQuery(
			isset($request['searchQuery']) && is_array($request['searchQuery']) ? $request['searchQuery'] : []
		);

		$dialog->doSearch($searchQuery);

		return [
			'dialog' => $dialog->getAjaxData(),
			'searchQuery' => $searchQuery,
		];
	}
}