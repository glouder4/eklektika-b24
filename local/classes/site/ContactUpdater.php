<?php
    namespace OnlineService\Site;
    use OnlineService\Site\UpdaterAbstract;

    class ContactUpdater extends UpdaterAbstract{
        public static function OnAfterCrmContactAdd(&$arFields) {
            // Срабатывает при вызове удаления, из-за отвязки от компании

            $name = $arFields['NAME'];
            $last_name = $arFields['LAST_NAME'];
            $post = $arFields['POST'];
            $email = (!is_null($arFields['FM']['EMAIL'])) ? $arFields['FM']['EMAIL'][array_key_first($arFields['FM']['EMAIL'])]['VALUE'] : "";
            $phone = ( !is_null($arFields['FM']['PHONE']) ) ? $arFields['FM']['PHONE'][array_key_first($arFields['FM']['PHONE'])]['VALUE'] : "";
            $bDate = $arFields['BIRTHDATE'];
            $isMarketingAgent = $arFields['UF_CRM_1698752707853'];
            $isDirector = $arFields['UF_CRM_1712732096274'];

            $ASSIGNED_MANAGER = $arFields['ASSIGNED_BY_ID'];
            $SECOND_MANAGER_ID = $arFields['UF_CRM_1757682312'] ?? false;

            $params = [
                'B24_ID' => $arFields['ID'],
                'ACTIVE' => $isMarketingAgent,
                'ASSIGNED_MANAGER' => $ASSIGNED_MANAGER,
                'SECOND_MANAGER' => $SECOND_MANAGER_ID,
                'UF_IS_DIRECTOR' => $isDirector == "Y",
                'NAME' => $name,
                'LAST_NAME' => $last_name,
                'EMAIL' => $email,
                'LOGIN' => $email,
                'PERSONAL_PHONE' => $phone,
                'PERSONAL_BIRTHDAYPERSONAL_BIRTHDAY' => $bDate,
                'UF_CITY' => $arFields['UF_CRM_3804624445810'],
                'WORK_POSITION' => $post,
                'UF_ADVERSTERING_AGENT' => $isMarketingAgent,
                'ACTION' => 'UPDATE_CONTACT'
            ];

            // Создаем временный экземпляр только для отправки запроса
            $updater = new self();
            $updater->sendRequest($params,false);
        }

        public static function OnBeforeCrmContactDelete($id){
            $params = [
                'ID' => $id,
                'ACTION' => 'DELETE_CONTACT'
            ];

            $updater = new self();
            $res = $updater->sendRequest($params,false);
            /*pre($res);
            die();*/
        }
    }