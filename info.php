<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
session_start();
echo "Bitrix session check:<br>";
echo "Session ID: " . session_id() . "<br>";
echo "User ID: " . $USER->GetID() . "<br>";
echo "Is authorized: " . ($USER->IsAuthorized() ? "Yes" : "No") . "<br>";
var_dump($_SESSION);