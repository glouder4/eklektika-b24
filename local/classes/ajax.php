<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

    if( $_REQUEST['ACTION'] == "UPDATE_COMPANY" ){
        $company = new \OnlineService\Site\CompanyUpdater();
        echo $company->updateCompanyElement($_REQUEST);
    }

    if( $_REQUEST['ACTION'] == "CREATED_EMPLOYEE" ){
        $contact = new \OnlineService\Site\ContactUpdater();
        echo $contact->createContact($_REQUEST);
    }