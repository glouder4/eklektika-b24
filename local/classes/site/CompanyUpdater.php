<?php
    namespace OnlineService\Site;
    use OnlineService\Site\UpdaterAbstract;

    class CompanyUpdater extends UpdaterAbstract{

        public static function updateCompany(&$arFields) {
            global $USER;

            if( $USER->IsAdmin() ){
                $COMPANY_STATUS = $arFields['UF_CRM_1754047803'];
                $COMPANY_USERS = $arFields['CONTACT_BINDINGS'];
                $IS_MARKETING_AGENT = $arFields['UF_CRM_1675675211485'];

                $contactIds = array_column($COMPANY_USERS, 'CONTACT_ID');

                foreach ($contactIds as $contactId){
                    $contactFields = [
                        'UF_CRM_1698752707853'   => $IS_MARKETING_AGENT
                    ];

                    $contactEntity = new \CCrmContact( true );

                    $contactEntity->Update(
                        $contactId,
                        $contactFields,
                        $bCompare = true,
                        $arOptions = [
                            'CURRENT_USER' => \CCrmSecurityHelper::GetCurrentUserID(),
                            'IS_SYSTEM_ACTION' => false,
                            'ENABLE_DUP_INDEX_INVALIDATION' => true,
                            'REGISTER_SONET_EVENT' => true,
                            'DISABLE_USER_FIELD_CHECK' => false,
                            'DISABLE_REQUIRED_USER_FIELD_CHECK' => false,
                        ]
                    );
                }


                $params = [
                    'ACTION' => "UPDATE_BATCH_USERS",
                    'CONTACT_IDS' => $contactIds,
                    'IS_MARKETING_AGENT' => $IS_MARKETING_AGENT
                ];

                // Создаем временный экземпляр только для отправки запроса
                $updater = new self();
                $res = $updater->sendRequest($params);
            }

            return true;
        }
    }