<?php
    namespace OnlineService\Site;
    use OnlineService\Site\UpdaterAbstract;

    class CompanyUpdater extends UpdaterAbstract{

        public static function updateCompany(&$arFields) {

            $COMPANY_STATUS = $arFields['UF_CRM_1754047803'];
            $COMPANY_USERS = $arFields['CONTACT_BINDINGS'];

            pre($arFields);
            die();

            return true;
        }
    }