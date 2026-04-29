<?php

namespace OnlineService\Sync\ToSite;

class ManagerUserSync extends OutboundRequest
{
    private static $mustBeFields = [
        'UF_USR_1774868229179', // Является персональным менеджером
    ];

    public static function onAfterUserUpdate(&$arFields): void
    {
        $rsUser = \CUser::GetList(
            ($by = ''),
            ($order = ''),
            ['ID' => $arFields['ID']],
            ['SELECT' => self::$mustBeFields]
        );

        if ($user = $rsUser->Fetch()) {
            if (!empty($user['PERSONAL_PHOTO'])) {
                $photoUrl = \CFile::GetPath($user['PERSONAL_PHOTO']);
                $arFields['PERSONAL_PHOTO'] = $photoUrl;
            }

            $params = [
                'ACTION' => 'UPDATE_MANAGER',
                'BITRIX24_ID' => $user['ID'],
                'NAME' => $user['NAME'],
                'LAST_NAME' => $user['LAST_NAME'],
                'EMAIL' => $user['EMAIL'],
                'PHONE' => (!empty($user['WORK_PHONE']) && $user['WORK_PHONE'] !== '')
                    ? $user['WORK_PHONE']
                    : $user['PERSONAL_PHONE'],
                'POSITION' => $user['WORK_POSITION'],
                'PERSONAL_PHOTO' => $arFields['PERSONAL_PHOTO'] ?? '',
                'IS_PERSONAL_MANAGER' => $user['UF_USR_1774868229179'],
            ];

            $sync = new self();
            $sync->sendRequest($params, false);
        }
    }
}
