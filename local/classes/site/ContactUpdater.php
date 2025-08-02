<?php
    namespace OnlineService\Site;
    use OnlineService\Site\UpdaterAbstract;

    class ContactUpdater extends UpdaterAbstract{
        public static function OnAfterCrmContactAdd(&$arFields) {
            // Срабатывает при вызове удаления, из-за отвязки от компании

            $name = $arFields['NAME'];
            $last_name = $arFields['LAST_NAME'];
            $post = $arFields['POST'];
            $email = $arFields['FM']['EMAIL'][array_key_first($arFields['FM']['EMAIL'])]['VALUE'];
            $phone = $arFields['FM']['PHONE'][array_key_first($arFields['FM']['PHONE'])]['VALUE'];
            $bDate = $arFields['BIRTHDATE'];

            $params = [
                'B24_ID' => $arFields['ID'],
                'NAME' => $name,
                'LAST_NAME' => $last_name,
                'EMAIL' => $email,
                'LOGIN' => $email,
                'PERSONAL_PHONE' => $phone,
                'PERSONAL_BIRTHDAYPERSONAL_BIRTHDAY' => $bDate,
                'UF_CITY' => $arFields['UF_CRM_3804624445810'],
                'WORK_POSITION' => $post,
                'ACTION' => 'UPDATE_CONTACT'
            ];

            // Создаем временный экземпляр только для отправки запроса
            $updater = new self();
            $updater->sendRequest($params,false);
        }
    }