<?php
use Bitrix\Catalog\v2\Integration\UI\EntitySelector\ProductProvider;
use Bitrix\UI\EntitySelector\Dialog;
use Bitrix\UI\EntitySelector\SearchQuery;
use Bitrix\Main\Loader;

Loader::includeModule("catalog");

class CustomProductProvider
	extends ProductProvider
{
	public function searchFilterFields (string $searchString = '')
	{
		return [
			'PROPERTY_ARTIKUL_BITRIKS' => $searchString . '%',
		];
	}

	protected function getProductsBySearchString(string $searchString = ''): array
	{
		$iblockInfo = $this->getIblockInfo();
		if (!$iblockInfo)
		{
			return [];
		}

		$productFilter = [];
		$offerFilter = [];

		if ($searchString !== '')
		{
			$simpleProductFilter = [
				[
					'LOGIC' => 'OR',
					'*SEARCHABLE_CONTENT' => $searchString,
					'PRODUCT_BARCODE' => $searchString . '%',
				] + $this->searchFilterFields($searchString)
			];

			if ($iblockInfo->canHaveSku())
			{
				$productFilter[] = [
					'LOGIC' => 'OR',
					'*SEARCHABLE_CONTENT' => $searchString,
					'=ID' => \CIBlockElement::SubQuery('PROPERTY_' . $iblockInfo->getSkuPropertyId(), [
						'CHECK_PERMISSIONS' => 'Y',
						'MIN_PERMISSION' => 'R',
						'ACTIVE' => 'Y',
						'ACTIVE_DATE' => 'Y',
						'IBLOCK_ID' => $iblockInfo->getSkuIblockId(),
						[
							"LOGIC" => "OR",
							'*SEARCHABLE_CONTENT' => $searchString,
						] + $this->searchFilterFields($searchString)
					]),
					'PRODUCT_BARCODE' => $searchString . '%',
				] + $this->searchFilterFields($searchString);

				$offerFilter = $simpleProductFilter;
			}
			else
			{
				$productFilter[] = $simpleProductFilter;
			}
		}

		return $this->getProducts([
			'filter' => $productFilter,
			'offer_filter' => $offerFilter,
			'searchString' => $searchString,
		]);
	}
}