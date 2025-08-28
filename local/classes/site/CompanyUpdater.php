<?php
    namespace OnlineService\Site;
    use OnlineService\Site\UpdaterAbstract;
    use Bitrix\Main\Loader;

    class CompanyUpdater extends UpdaterAbstract{

        public static function updateCompany(&$arFields) {
            global $USER;

            if (\Bitrix\Main\Loader::requireModule('crm')) {
                \Bitrix\Crm\Settings\CompanySettings::getCurrent()->setFactoryEnabled(true);

                $companyId = $arFields['ID'];

                $company = \Bitrix\Crm\CompanyTable::getById($companyId)->fetch();

                if ($company) {
                    global $USER_FIELD_MANAGER;

                    $ufValues = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId);

                    foreach ($ufValues as $key => $value)
                        $company[$key] = $value['VALUE'];

                    $requisites = \Bitrix\Crm\RequisiteTable::getList([
                        'filter' => [
                            'ENTITY_ID' => $company['ID']
                        ],
                        'select' => ['*'] // Все поля реквизита или конкретные, например ['RQ_INN', 'RQ_KPP']
                    ])->fetchAll();

                    $company['REQUISITES'] = $requisites[0];


                    // 2. Получаем множественные поля (включая WEB, PHONE, EMAIL и т.д.)
                    $multiFields = \CCrmFieldMulti::GetEntityFields('COMPANY', $companyId, "");
                    $company['MULTIFIELDS'] = [];

                    foreach ($multiFields as $multiField){
                        $company['MULTIFIELDS'][$multiField['TYPE_ID']] = $multiField;
                    }
                }
            }

            if( $USER->IsAdmin() ){

                $COMPANY_USERS = $arFields['CONTACT_BINDINGS'];
                $IS_MARKETING_AGENT = $arFields['UF_CRM_1675675211485'];
                $COMPANY_STATUS_ID = $arFields['UF_CRM_1755690874'];

                $REQ_FILE_ID = $arFields['UF_CRM_1755643990423'];
                $fileInfo = \CFile::GetFileArray($REQ_FILE_ID);

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


                /*$params = [
                    'ACTION' => "UPDATE_BATCH_USERS",
                    'CONTACT_IDS' => $contactIds,
                    'IS_MARKETING_AGENT' => $IS_MARKETING_AGENT
                ];

                // Создаем временный экземпляр только для отправки запроса
                $updater = new self();
                $res = $updater->sendRequest($params);*/


                $companyParams = [
                    'ACTION' => "UPDATE_COMPANY",
                    'CONTACT_IDS' => $contactIds,
                    "OS_COMPANY_B24_ID" => $companyId,
                    "OS_COMPANY_NAME" => $company['TITLE'],
                    "OS_COMPANY_STATUS" => $arFields['UF_CRM_1755690874'],
                    "OS_COMPANY_USERS" => $contactIds,
                    "OS_COMPANY_INN" => $company['REQUISITES']['RQ_INN'],
                    "OS_COMPANY_CITY" => $arFields['UF_CRM_1618551330657'],
                    "OS_COMPANY_WEB_SITE" => $company['MULTIFIELDS']['WEB']['VALUE'],
                    "OS_COMPANY_PHONE" => $company['MULTIFIELDS']['PHONE']['VALUE'],
                    "OS_COMPANY_EMAIL" => $company['MULTIFIELDS']['EMAIL']['VALUE'],
                    "OS_COMPANY_STATUS" => $COMPANY_STATUS_ID,
                    'OS_IS_MARKETING_AGENT' => [
                        'VALUE' => ($IS_MARKETING_AGENT === 'Y' || $IS_MARKETING_AGENT === true || $IS_MARKETING_AGENT === 1 || $IS_MARKETING_AGENT === "1")
                            ? 31519
                            : false // или null, или ''
                    ],
                    'OS_IS_COMPANY_DISABLED' => [
                        'VALUE' => ($arFields['UF_CRM_1681120791520'] === 'Y' || $arFields['UF_CRM_1681120791520'] === true || $arFields['UF_CRM_1681120791520'] === 1 || $arFields['UF_CRM_1681120791520'] === "1")
                            ? 31518
                            : false // или null, или ''
                    ],
                    "OS_REQUSITES_FILE" => $fileInfo,
                    "ACTIVE" => ($company['UF_CRM_1675675211485'] === 'Y' || $company['UF_CRM_1675675211485'] === true || $company['UF_CRM_1675675211485'] === 1 || $company['UF_CRM_1675675211485'] === "1") ? "Y" : "N"
                ];

                $updater = new self();
                $res = $updater->sendRequest($companyParams,false);

                return true;
            }

            return true;
        }

        public static function deleteCompany($id){
            $params = [
                'ACTION' => "DELETE_COMPANY",
                'ID' => $id,
            ];

            $updater = new self();
            $res = $updater->sendRequest($params,false );

            return true;
        }

        public function updateCompanyElement($params){
            pre($params);
            die();
        }
    }