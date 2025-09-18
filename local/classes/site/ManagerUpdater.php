<?php

    namespace OnlineService\Site;
    use OnlineService\Site\UpdaterAbstract;

    class ManagerUpdater extends UpdaterAbstract{
        public static function OnAfterUserUpdate($arFields){
            if( $arFields['UF_USR_1756295452395'] == 1 ){

                // Получаем данные пользователя
                $user = \CUser::GetByID($arFields['ID'])->Fetch();

                if ($user) {
                    if (!empty($user['PERSONAL_PHOTO'])) {
                        // Генерируем URL к фото
                        $photoUrl = \CFile::GetPath($user['PERSONAL_PHOTO']);
                        $arFields['PERSONAL_PHOTO'] = $photoUrl;
                    }
                }

                $params = [
                    'ACTION' => 'UPDATE_MANAGER',
                    'ID' => $arFields['ID'],
                    'NAME' => $user['NAME'],
                    'LAST_NAME' => $user['LAST_NAME'],
                    'EMAIL' => $user['EMAIL'],
                    'PHONE' => (!empty($user['WORK_PHONE']) && $user['WORK_PHONE'] != "") ? $user['WORK_PHONE'] : $user['PERSONAL_PHONE'],
                    'POSITION' => $user['WORK_POSITION'],
                    'PERSONAL_PHOTO' => $arFields['PERSONAL_PHOTO']
                ];

                // Создаем временный экземпляр только для отправки запроса
                $updater = new self();
                $updater->sendRequest($params,false);
            }
        }
    }