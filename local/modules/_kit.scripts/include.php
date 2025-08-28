<?php
defined('B_PROLOG_INCLUDED') || die;
\Bitrix\Main\Loader::registerAutoloadClasses(
    "kit.scripts",
    array(
        "Kit\\Scripts\\Folders" => "classes/Folders.php",
        "Kit\\Scripts\\Events" => "classes/Events.php",
        "Kit\\Scripts\\CallMade" => "classes/CallMade.php",
    )
);
?>