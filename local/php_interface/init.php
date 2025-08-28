<?php
	ini_set('memory_limit', '2048M');
	//ini_set('max_execution_time', 300);
    require_once __DIR__.'/../classes/requires.php'; // Подключение кастомных обработчиков

    define('EKLEKTIKA_SITE_URL', 'https://test.yoliba.ru/');
	define('URL_B24', 'https://testb24.yoliba.ru/');
	



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

/*======= Уведомления о смене статуса сделки ========*/
\Bitrix\Main\Loader::includeModule('crm');
\Bitrix\Main\EventManager::getInstance()->addEventHandlerCompatible(
    'crm',
    'OnBeforeCrmDealUpdate',
    'ek_onBeforeDealUpdateNotify'
);

function ek_onBeforeDealUpdateNotify(&$arFields) {
    if (!\Bitrix\Main\Loader::includeModule('crm')) return;

    $dealId = isset($arFields['ID']) ? (int)$arFields['ID'] : 0;
    if ($dealId <= 0) return;

    // Новая стадия (если не передана, то стадия не меняется)
    $newStage = isset($arFields['STAGE_ID']) ? (string)$arFields['STAGE_ID'] : '';
    if ($newStage === '') return;

    // Текущая стадия до обновления
    $deal = \Bitrix\Crm\DealTable::getById($dealId)->fetch();
    if (!$deal || !isset($deal['STAGE_ID'])) return;

    $prevStage = (string)$deal['STAGE_ID'];
    if ($prevStage === $newStage) return; // стадия не изменилась

    // Условия уведомлений: на 10, на 9, на NEW с любого
    $rawNew = (string)$newStage;
    $suffixNew = (strpos($rawNew, ':') !== false) ? substr($rawNew, strrpos($rawNew, ':') + 1) : $rawNew;
    $normalizedNew = strtoupper($suffixNew);
    $shouldNotify = in_array($normalizedNew, ['10', '9'], true) || $normalizedNew === 'NEW';
    if (!$shouldNotify) return;

    // Кому отправлять: ответственному по сделке
    $assignedId = isset($deal['ASSIGNED_BY_ID']) ? (int)$deal['ASSIGNED_BY_ID'] : 0;
    if ($assignedId <= 0) return;

    // Формируем сообщение
    $title = isset($deal['TITLE']) ? (string)$deal['TITLE'] : ('Сделка #'.$dealId);
    $url = \CCrmOwnerType::GetEntityShowPath(\CCrmOwnerType::Deal, $dealId);
    $message = sprintf(
        'Статус сделки "%s" изменён %s на %s. <a href="%s">Открыть</a>',
        $title,
        $prevStage !== '' ? ('с '.$prevStage) : 'на',
        $newStage,
        $url
    );

    if (\CModule::IncludeModule('im')) {
        \CIMNotify::Add([
            'TO_USER_ID' => $assignedId,
            'NOTIFY_TYPE' => IM_NOTIFY_SYSTEM,
            'NOTIFY_MODULE' => 'crm',
            'NOTIFY_EVENT' => 'deal_status_changed',
            'MESSAGE' => $message,
            'TAG' => 'crm|deal|status|'.$dealId,
        ]);
    }
}
