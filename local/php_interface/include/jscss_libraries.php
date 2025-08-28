<?php
if (!defined('JSCSS_LIBRARIES')) die();

$arLibsConfig = [
    'custom_styles' => [
        'css' => '/local/php_interface/include/css/custom.css',
    ],
];

foreach ($arLibsConfig as $ext => $arExt) {
    \CJSCore::RegisterExt($ext, $arExt);
}
CUtil::InitJSCore(array_keys($arLibsConfig));