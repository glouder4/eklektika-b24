<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arActivityDescription = [
    "NAME" => GetMessage("CICREPA_DESCR_NAME"),
    "DESCRIPTION" => GetMessage("CICREPA_DESCR_DESCR"),
    "TYPE" => ['activity'],
    "CLASS" => "CheckIfCanReserveEntityProductsActivity",
    "JSCLASS" => "BizProcActivity",
    "CATEGORY" => [
        "ID" => "other",
    ],
    "RETURN" => [
        "ProductsCanReserve" => [
            "NAME" => GetMessage("CICREPA_CAN_RESERVE_NAME"),
            "TYPE" => "bool",
        ],
    ],
];
