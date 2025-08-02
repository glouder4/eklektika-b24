<?php
    error_reporting(E_ALL);
    ini_set('display_errors', '1');

    define("NO_KEEP_STATISTIC", true);
    define("NO_AGENT_STATISTIC", true);
    define("NO_AGENT_CHECK", true);
    define("NOT_CHECK_PERMISSIONS", true);

    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
    use OnlineService\LocalApplicationHandler;

    if( isset($_REQUEST['ACTION']) ){
        $handler = new OnlineService\LocalApplicationHandler($_REQUEST);
        echo json_encode($handler->getResponse());
    }

