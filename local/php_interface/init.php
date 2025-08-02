<?php
    require_once __DIR__.'/../classes/requires.php'; // Подключение кастомных обработчиков

    define('EKLEKTIKA_SITE_URL', 'https://smm.3dlookinside.ru/');



//$eventManager = \Bitrix\Main\EventManager::getInstance();
//
//$handler = \Bitrix\Main\EventManager::getInstance()->addEventHandler(
//    "crm",
//    "onCrmDynamicItemUpdate_133",
//    'handle_dynamic_update'
//);

//\Bitrix\Main\EventManager::getInstance()->addEventHandlerCompatible(
//    "iblock",
//    "OnIBlockElementSetPropertyValuesEx",
//    'handle_props'
//);
//\Bitrix\Main\EventManager::getInstance()->addEventHandlerCompatible(
//    "iblock",
//    "OnBeforeIBlockElementUpdate",
//    'handle_iblock_update'
//);
//
//$handler = \Bitrix\Main\EventManager::getInstance()->addEventHandler(
//    "crm",
//    "onCrmDynamicItemAdd_133",
//    'handle_dynamic_update'
//);
//
function handle_dynamic_update(\Bitrix\Main\Event $event) {
    $item = $event->getParameter('item');

    file_put_contents(__DIR__.'/d_log.txt', print_r($item->toArray(), true), FILE_APPEND);
}

//function handle_props($ELEMENT_ID, $IBLOCK_ID, &$PROPERTY_VALUES, $propertyList, &$arDBProps) {
//    $s = [
//        'vals' => $PROPERTY_VALUES,
//        'ar_prop' => $propertyList,
//        'db_props' => $arDBProps
//    ];
//    file_put_contents(__DIR__.'/p_log.txt', print_r($s, true), FILE_APPEND);
//
//    if($ELEMENT_ID == 6957) {
//        foreach ($arDBProps[114] as $id => &$pr) {
//            $pr['VALUE'] = $pr['VALUE'].'1';
//        }
////        $arDBProps[114][103965]['VALUE'] = $arDBProps[114][103965]['VALUE'].'1';
//        file_put_contents(__DIR__.'/p_log.txt', print_r($s, true), FILE_APPEND);
//    }
//
//
//}

function handle_iblock_update(&$arFields) {
//    file_put_contents(__DIR__.'/p_log.txt', print_r($arFields, true), FILE_APPEND);

//    foreach ($arFields['PROPERTY_VALUES'][114] as $id => &$pr) {
//        $pr['VALUE'] = $pr['VALUE'].'1';
//    }
}

/*======= Подмена сервисов битрикса ========*/
//define('CRM_USE_CUSTOM_SERVICES', 1);
//$fileName = __DIR__ . '/include/crm_services.php';
//if (file_exists($fileName)) require_once ($fileName);

// help!! не работает ctl + O

/*======= JS и CSS библиотеки ========*/
define('JSCSS_LIBRARIES', 1);
$fileName = __DIR__ . '/include/jscss_libraries.php';
if (file_exists($fileName)) require_once ($fileName);


function pre($o) {

    $bt = debug_backtrace();
    $bt = $bt[0];
    $dRoot = $_SERVER["DOCUMENT_ROOT"];
    $dRoot = str_replace("/", "\\", $dRoot);
    $bt["file"] = str_replace($dRoot, "", $bt["file"]);
    $dRoot = str_replace("\\", "/", $dRoot);
    $bt["file"] = str_replace($dRoot, "", $bt["file"]);
    ?>
    <div style='font-size:9pt; color:#000; background:#fff; border:1px dashed #000;text-align: left!important;'>
        <div style='padding:3px 5px; background:#99CCFF; font-weight:bold;'>File: <?= $bt["file"] ?> [<?= $bt["line"] ?>]</div>
        <pre style='padding:5px;'><? print_r($o) ?></pre>
    </div>
    <?
}
