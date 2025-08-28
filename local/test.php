<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;

Loader::includeModule('crm');

$entityId = 2574;
$entityType = \CCrmOwnerType::Company;
var_dump($entityType);

//$contacts = \Bitrix\Crm\Binding\EntityContactTable::getContactIds($entityType, $entityId);
$contacts = \Bitrix\Crm\Binding\ContactCompanyTable::getCompanyBindings($entityId);

echo "<pre>";
var_dump($contacts);
echo "</pre>";