<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

if( $_REQUEST['ACTION'] == "UPDATE_COMPANY" ){
    $company = new \OnlineService\Site\CompanyUpdater();
    echo $company->updateCompanyElement($_REQUEST);
}