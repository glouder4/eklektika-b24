<?php
	ini_set('memory_limit', '2048M');
// /home/bitrix/ext_www/bitrix.yomerch.ru/local/logs/bitrix-debug.log

define('EK_COMPANY_UPDATER_DEBUG', false);
define('LOG_FILENAME', $_SERVER['DOCUMENT_ROOT'].'/local/logs/bitrix-debug.log');

define('BX_SALT', 'crm_yomerch');
ini_set('session.cookie_domain', 'bitrix.yomerch.ru');
\Bitrix\Main\Config\Option::set('main', 'cookie_domain', 'bitrix.yomerch.ru');
\Bitrix\Main\Config\Option::set('main', 'use_domain_without_dot_for_cookie', 'Y');

session_set_cookie_params([
    'path' => '/',
    'domain' => 'yomerch.ru',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

    // В самом начале, после подключения пролога
    if (function_exists('header_remove')) {
        // Разрешаем показывать Server Timing
        header_remove('Server-Timing');
    }

    // Добавляем измерение времени для разных этапов
    $GLOBALS['BX_TIMINGS'] = [];

    // Перехватываем время начала запроса
    $GLOBALS['BX_TIMINGS']['start'] = microtime(true);

    // Регистрируем завершение работы ядра Bitrix
    AddEventHandler('main', 'OnEpilog', 'OnEpilogHandler');
    function OnEpilogHandler()
    {
        $timings = $GLOBALS['BX_TIMINGS'];
        $total = microtime(true) - $timings['start'];

        // Безопасное получение времени SQL запросов
        global $DB;
        $sql_time = 0;

        if (is_object($DB)) {
            // Пробуем разные варианты названия метода
            if (method_exists($DB, 'getQueryTime')) {
                $sql_time = $DB->getQueryTime();
            } elseif (method_exists($DB, 'GetQueryTime')) {
                $sql_time = $DB->GetQueryTime();
            } elseif (method_exists($DB, 'getSqlQueryTime')) {
                $sql_time = $DB->getSqlQueryTime();
            } elseif (property_exists($DB, 'sql_time')) {
                $sql_time = $DB->sql_time;
            } elseif (defined('BX_SQL_TIME')) {
                $sql_time = BX_SQL_TIME;
            }
        }

        // Формируем заголовок Server-Timing
        $header = sprintf(
            'total;dur=%f, sql;dur=%f, php;dur=%f',
            $total * 1000,
            $sql_time * 1000,
            ($total - $sql_time) * 1000
        );

        header("Server-Timing: $header");
    }

    //session_set_cookie_params(10800);

	//ini_set('max_execution_time', 300);
    //require_once __DIR__.'/../classes/requires.php'; // Подключение кастомных обработчиков

    $is_test_server = false;
    define('EKLEKTIKA_SITE_URL', ($is_test_server) ? 'https://test.yomerch.ru' : 'https://yomerch.ru');
	define('URL_B24', ($is_test_server) ? 'https://testbitrix.yomerch.ru/' : 'https://bitrix.yomerch.ru/');



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



/**
 * @param mixed $v
 */
$eklektikaB24Flag = static function ($v): bool {
    if ($v === true || $v === 1) {
        return true;
    }
    if (\is_string($v)) {
        return \in_array(\strtolower(\trim($v)), ['1', 'true', 'yes', 'on'], true);
    }

    return false;
};

$eklektikaB24SiteSync = [
    'site_url' => 'https://yomerch.ru',
    'sync_token' => '',
    'sync_debug' => false,
    'sync_trace' => false,
];
$syncSettings = __DIR__ . '/../sync/site_sync_settings.php';
$syncSettingsMainReal = \is_file($syncSettings) ? \realpath($syncSettings) : '';
$syncSettingsMainMtime = \is_file($syncSettings) ? @\filemtime($syncSettings) : 0;
$syncSettingsMainOk = false;
$syncSettingsMainType = 'not_loaded';
if (\is_file($syncSettings)) {
    $cfg = include $syncSettings;
    $syncSettingsMainType = \gettype($cfg);
    if (\is_array($cfg)) {
        $syncSettingsMainOk = true;
        $eklektikaB24SiteSync = \array_replace($eklektikaB24SiteSync, $cfg);
    }
}
$syncSettingsLocal = __DIR__ . '/../sync/site_sync_settings.local.php';
$syncSettingsLocalReal = \is_file($syncSettingsLocal) ? \realpath($syncSettingsLocal) : '';
$syncSettingsLocalMtime = \is_file($syncSettingsLocal) ? @\filemtime($syncSettingsLocal) : 0;
$syncSettingsLocalOk = false;
$syncSettingsLocalType = 'not_loaded';
if (\is_file($syncSettingsLocal)) {
    $cfg = include $syncSettingsLocal;
    $syncSettingsLocalType = \gettype($cfg);
    if (\is_array($cfg)) {
        $syncSettingsLocalOk = true;
        $eklektikaB24SiteSync = \array_replace($eklektikaB24SiteSync, $cfg);
    }
}

/**
 * Авторитетно для OutboundRequest::isSiteSync* (обход уже объявленных констант).
 * sync_debug без sync_trace: раньше не писался b24-to-site-sync.log — теперь debug тянет за собой trace в файл.
 */
$eklektikaB24Debug = $eklektikaB24Flag($eklektikaB24SiteSync['sync_debug'] ?? false);
$eklektikaB24Trace = $eklektikaB24Flag($eklektikaB24SiteSync['sync_trace'] ?? false);
$GLOBALS['EKLEKTIKA_B24_SITE_SYNC'] = [
    'sync_debug' => $eklektikaB24Debug,
    'sync_trace' => $eklektikaB24Trace || $eklektikaB24Debug,
    /** Для отладки: сырые значения после merge файлов и факт наличия local (часто он обнуляет флаги). */
    '_diag' => [
        'path_main' => $syncSettingsMainReal ?: $syncSettings,
        'path_local' => $syncSettingsLocalReal ?: ($syncSettingsLocal . (\is_file($syncSettingsLocal) ? '' : ' (missing)')),
        'mtime_main' => $syncSettingsMainMtime,
        'mtime_local' => $syncSettingsLocalMtime,
        'main_returned_array' => $syncSettingsMainOk,
        'main_include_type' => $syncSettingsMainType,
        'local_returned_array' => $syncSettingsLocalOk,
        'local_include_type' => $syncSettingsLocalType,
        'raw_sync_debug' => $eklektikaB24SiteSync['sync_debug'] ?? null,
        'raw_sync_trace' => $eklektikaB24SiteSync['sync_trace'] ?? null,
    ],
];
unset($eklektikaB24Debug, $eklektikaB24Trace);

if (!\defined('EKLEKTIKA_SITE_URL')) {
    $u = \trim((string)($eklektikaB24SiteSync['site_url'] ?? ''));
    \define('EKLEKTIKA_SITE_URL', $u !== '' ? \rtrim($u, '/') : 'http://new.eklektika.ru');
}
if (!\defined('EKLEKTIKA_SITE_SYNC_TOKEN')) {
    \define('EKLEKTIKA_SITE_SYNC_TOKEN', (string)($eklektikaB24SiteSync['sync_token'] ?? ''));
}
if (!\defined('EKLEKTIKA_SITE_SYNC_DEBUG')) {
    \define('EKLEKTIKA_SITE_SYNC_DEBUG', (bool)($GLOBALS['EKLEKTIKA_B24_SITE_SYNC']['sync_debug'] ?? false));
}
if (!\defined('EKLEKTIKA_SITE_SYNC_TRACE')) {
    \define('EKLEKTIKA_SITE_SYNC_TRACE', (bool)($GLOBALS['EKLEKTIKA_B24_SITE_SYNC']['sync_trace'] ?? false));
}

unset(
    $eklektikaB24SiteSync,
    $eklektikaB24Flag,
    $syncSettings,
    $syncSettingsLocal,
    $cfg,
    $syncSettingsMainReal,
    $syncSettingsMainMtime,
    $syncSettingsMainOk,
    $syncSettingsMainType,
    $syncSettingsLocalReal,
    $syncSettingsLocalMtime,
    $syncSettingsLocalOk,
    $syncSettingsLocalType
);

require_once __DIR__ . '/../modules/bootstrap.php'; // Подключение кастомных обработчиков
require_once __DIR__ . '/../events/requires.php';
