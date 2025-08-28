<?php

namespace Kitconsulting\Restevents;

use Bitrix\Main\Loader;
use Bitrix\Main;

if (!Loader::IncludeModule('rest')) {
    return;
}

class Rest
{
    public static function OnRestServiceBuildDescription()
    {
        return [
            'user' => [
                \CRestUtil::EVENTS => [
                    'OnUserUpdate' => ['main', 'OnAfterUserUpdate', [__CLASS__, 'onAfterUserUpdate']],
                ],
            ],
            'user_basic' => [
                \CRestUtil::EVENTS => [
                    'OnUserUpdate' => ['main', 'OnAfterUserUpdate', [__CLASS__, 'onAfterUserUpdate']],
                ],
            ],
            'user_brief' => [
                \CRestUtil::EVENTS => [
                    'OnUserUpdate' => ['main', 'OnAfterUserUpdate', [__CLASS__, 'onAfterUserUpdate']],
                ],
            ],
        ];
    }

    public static function onRemoteDictionaryLoad($id, &$dictionary)
    {
        $dictionary[] = [
            "code" => "ONUSERUPDATE",
            "name" => "ONUSERUPDATE",
            "descr" => "ONUSERUPDATE",
        ];
    }

    public static function onAfterUserUpdate(&$arFields)
    {
        return [
            "FIELDS" => $arFields
        ];
    }
}