<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arActivityDescription = [
    "NAME" => GetMessage("ROEPA_DESCR_NAME"),
    "DESCRIPTION" => GetMessage("ROEPA_DESCR_DESCR"),
    "TYPE" => ['activity'],
    "CLASS" => "ReserveOfEntityProductsActivity",
    "JSCLASS" => "BizProcActivity",
    "CATEGORY" => [
        "ID" => "other",
    ],
    "RETURN" => [
        "ReserveProducts" => [
            "NAME" => GetMessage("ROEPA_RESERVE_PRODUCTS_NAME"),
            "TYPE" => "select",
            "MULTIPLY" => true,
        ],
    ],
];
