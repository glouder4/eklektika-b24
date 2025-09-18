<?php
    namespace OnlineService\Site;
    use OnlineService\Site\UpdaterAbstract;
    use Bitrix\Main\Loader;
    use Exception;

    class CompanyUpdater extends UpdaterAbstract{
        private function createHoldingHeadCompany($companyId, $companyTitle){
            // Подключаем модуль инфоблоков
            if (!\Bitrix\Main\Loader::includeModule('iblock')) {
                return false;
            }

            try {
                // Проверяем существование элемента по свойству B24_COMPANY_ID
                $filter = [
                    'IBLOCK_ID' => 34,
                    'PROPERTY_B24_COMPANY_ID' => $companyId
                ];
                
                $rsElements = \CIBlockElement::GetList(
                    [],
                    $filter,
                    false,
                    false,
                    ['ID', 'NAME', 'PROPERTY_B24_COMPANY_ID']
                );

                if($element = $rsElements->Fetch()) {
                    return $element['ID'];
                }

                // Если элемент не существует, создаем новый
                // Подготавливаем данные для создания элемента
                $arFields = [
                    'IBLOCK_ID' => 34,
                    'NAME' => $companyTitle,
                    'ACTIVE' => 'Y',
                    'PROPERTY_VALUES' => [
                        'B24_COMPANY_ID' => $companyId
                    ]
                ];

                $element = new \CIBlockElement();
                $elementId = $element->Add($arFields);

                if ($elementId) {
                    return $elementId;
                } else {
                    return false;
                }
            } catch (Exception $e) {
                error_log('Исключение при создании элемента инфоблока: ' . $e->getMessage());
                return false;
            }
        }

        private static function getHoldingOfBitrixId($field_id_value){
            // Подключаем модуль инфоблоков
            if (!\Bitrix\Main\Loader::includeModule('iblock')) {
                return false;
            }
            
            // Если не передан ID элемента
            if (!$field_id_value) {
                return false;
            }
            
            // Получаем элемент инфоблока 34 по ID
            $rsElement = \CIBlockElement::GetList(
                [],
                ['ID' => $field_id_value, 'IBLOCK_ID' => 34],
                false,
                false,
                ['ID', 'NAME', 'PROPERTY_B24_COMPANY_ID']
            );
            
            if ($element = $rsElement->Fetch()) {
                // Возвращаем значение свойства B24_COMPANY_ID
                return $element['PROPERTY_B24_COMPANY_ID_VALUE'];
            }
            
            return false;
        }

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

                $headCompanyIblock_id = false;
                if( $arFields['UF_CRM_1758028888'] == "Y" ){
                    // Создаем элемент в инфоблоке 34 для головной компании холдинга
                    $updater = new self();
                    $headCompanyIblock_id = $updater->createHoldingHeadCompany($arFields['ID'], $arFields['TITLE']);
                }

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


                $companyParams = [
                    'ACTION' => "UPDATE_COMPANY",
                    'CONTACT_IDS' => $contactIds,
                    "OS_COMPANY_B24_ID" => $companyId,
                    "OS_COMPANY_NAME" => $company['TITLE'],
                    "OS_COMPANY_IS_HEAD_OF_HOLDING" => [
                        'VALUE' => ($arFields['UF_CRM_1758028888'] === 'Y' || $arFields['UF_CRM_1758028888'] === true || $arFields['UF_CRM_1758028888'] === 1 || $arFields['UF_CRM_1758028888'] === "1")
                            ? 31520
                            : false // или null, или ''
                    ],
                    "OS_HOLDING_OF" => self::getHoldingOfBitrixId($arFields['UF_CRM_1758028816']),
                    "OS_COMPANY_STATUS" => $arFields['UF_CRM_1755690874'],
                    "OS_HEAD_COMPANY_B24_ID" => ( $arFields['UF_CRM_1758028888'] == "Y" ) ? $headCompanyIblock_id : false,
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

        public static function beforeUpdateCompany(&$arFields){
            // Получаем ID компании
            $companyId = $arFields['ID'];
            
            // Проверяем, пытаются ли убрать флаг головной компании
            // Получаем UF поля через USER_FIELD_MANAGER
            global $USER_FIELD_MANAGER;
            $ufValues = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId);
            
            $wasHeadCompany = $ufValues['UF_CRM_1758028888']['VALUE'] === 'Y' || $ufValues['UF_CRM_1758028888']['VALUE'] == 1;
            $willBeHeadCompany = $arFields['UF_CRM_1758028888'] === 'Y';

            if( $willBeHeadCompany && isset($arFields['UF_CRM_1758028816']) && !empty($arFields['UF_CRM_1758028816']) ){
                $arFields['RESULT_MESSAGE'] = "Компания не может быть головной И входить в другой холдинг";
                return false;
            }
            
            // Если пытаются убрать флаг головной компании
            if ($wasHeadCompany && !$willBeHeadCompany) {
                // Находим элемент в инфоблоке 34 по B24_COMPANY_ID
                $headElementId = self::findHeadElementInIblock($companyId);
                
                if ($headElementId) {
                    // Проверяем, есть ли дочерние филиалы
                    $hasChildCompanies = self::checkForChildCompanies($headElementId);
                } else {
                    $hasChildCompanies = false;
                }

                if ($hasChildCompanies) {
                    global $APPLICATION;
                    // Устанавливаем сообщение об ошибке через глобальную переменную
                    //$arFields['RESULT_MESSAGE'] = 'Нельзя убрать флаг головной компании. У компании есть дочерние филиалы.';
                    //throw new \Exception("Нельзя убрать флаг головной компании. У компании есть дочерние филиалы.", "Ограничение прав");

                    $arFields['RESULT_MESSAGE'] = "Нельзя убрать флаг головной компании. У компании есть дочерние филиалы.";
                    return false;
                }
                else{
                    $holdingElementID = self::findHeadElementInIblock($arFields['ID']);

                    $el = new \CIBlockElement;
                    $el->Update($holdingElementID, [
                        "ACTIVE" => "N"
                    ]);
                }
            }
            if( $willBeHeadCompany ){
                $holdingElementID = self::findHeadElementInIblock($arFields['ID']);

                $el = new \CIBlockElement;
                $el->Update($holdingElementID, [
                    "ACTIVE" => "Y"
                ]);
            }

            return true;
        }
        
        private static function findHeadElementInIblock($companyId) {
            // Подключаем модуль инфоблоков
            if (!\Bitrix\Main\Loader::includeModule('iblock')) {
                return false;
            }
            
            // Ищем элемент в инфоблоке 34 по свойству B24_COMPANY_ID
            $filter = [
                'IBLOCK_ID' => 34,
                'PROPERTY_B24_COMPANY_ID' => $companyId
            ];
            
            $rsElement = \CIBlockElement::GetList(
                [],
                $filter,
                false,
                false,
                ['ID', 'NAME', 'PROPERTY_B24_COMPANY_ID']
            );
            
            if ($element = $rsElement->Fetch()) {
                return $element['ID'];
            }

            return false;
        }
        
        private static function checkForChildCompanies($headElementId) {
            // Подключаем модуль CRM
            if (!\Bitrix\Main\Loader::includeModule('crm')) {
                return false;
            }
            
            // Получаем все компании и проверяем их UF поля
            $rsCompanies = \Bitrix\Crm\CompanyTable::getList([
                'select' => ['ID', 'TITLE']
            ]);
            
            $childCompanies = [];
            while ($company = $rsCompanies->fetch()) {
                // Получаем UF поля для каждой компании
                global $USER_FIELD_MANAGER;
                $ufValues = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $company['ID']);
                
                // Проверяем, является ли эта компания дочерней (сравниваем с ID элемента инфоблока)
                if (isset($ufValues['UF_CRM_1758028816']) && $ufValues['UF_CRM_1758028816']['VALUE'] == $headElementId) {
                    $company['UF_CRM_1758028816'] = $ufValues['UF_CRM_1758028816']['VALUE'];
                    $childCompanies[] = $company;
                }
            }
            
            // Возвращаем true если есть дочерние компании
            return count($childCompanies) > 0;
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