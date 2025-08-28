<?php

use Kitconsulting\ExtendedProductsRow\CCrmProductRowExtended;
use Kitconsulting\ProductApplicationsExtension\Helper;
use Bitrix\Main\Loader;
use Bitrix\Catalog\StoreProductTable;

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

Loader::includeModule('kitconsulting.extendedproductsrow');

$entityType = 2;
$entityId = 12184;
const STORE_GENERAL = 11;

