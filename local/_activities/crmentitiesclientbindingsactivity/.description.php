<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

$arActivityDescription = [
    "NAME" => Loc::getMessage("CRMECBA_DESCR_NAME"),
    "DESCRIPTION" => Loc::getMessage("CRMECBA_DESCR_DESCR"),
    "TYPE" => ['activity'],
    "CLASS" => "CRMEntitiesClientBindingsActivity",
    "JSCLASS" => "BizProcActivity",
    "CATEGORY" => [
        "ID" => "other",
    ],
    "RETURN" => [
        "Bindings" => [
            "NAME" => Loc::getMessage("CRMECBA_BINDINGS_NAME"),
            "TYPE" => "select",
            "MULTIPLY" => true,
        ],
    ],
];
