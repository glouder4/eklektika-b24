<?php



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
/*\Bitrix\Main\EventManager::getInstance()->addEventHandlerCompatible(
    'crm',
    'OnBeforeCrmDealUpdate',
    'ek_onBeforeDealUpdateNotify'
);*/

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
$yomerrch24B24Flag = static function ($v): bool {
    if ($v === true || $v === 1) {
        return true;
    }
    if (\is_string($v)) {
        return \in_array(\strtolower(\trim($v)), ['1', 'true', 'yes', 'on'], true);
    }

    return false;
};

$yomerrch24B24SiteSync = [
    'site_url' => 'https://yomerch.ru',
    'sync_token' => '',
    'sync_debug' => false,
    'sync_trace' => false,
];
$syncSettings = __DIR__ . '/../modules/yomerch.b24.siteconnector/site_sync_settings.php';
$syncSettingsMainReal = \is_file($syncSettings) ? \realpath($syncSettings) : '';
$syncSettingsMainMtime = \is_file($syncSettings) ? @\filemtime($syncSettings) : 0;
$syncSettingsMainOk = false;
$syncSettingsMainType = 'not_loaded';
if (\is_file($syncSettings)) {
    $cfg = include $syncSettings;
    $syncSettingsMainType = \gettype($cfg);
    if (\is_array($cfg)) {
        $syncSettingsMainOk = true;
        $yomerrch24B24SiteSync = \array_replace($yomerrch24B24SiteSync, $cfg);
    }
}
$syncSettingsLocal = __DIR__ . '/../modules/yomerch.b24.siteconnector/site_sync_settings.local.php';
$syncSettingsLocalReal = \is_file($syncSettingsLocal) ? \realpath($syncSettingsLocal) : '';
$syncSettingsLocalMtime = \is_file($syncSettingsLocal) ? @\filemtime($syncSettingsLocal) : 0;
$syncSettingsLocalOk = false;
$syncSettingsLocalType = 'not_loaded';
if (\is_file($syncSettingsLocal)) {
    $cfg = include $syncSettingsLocal;
    $syncSettingsLocalType = \gettype($cfg);
    if (\is_array($cfg)) {
        $syncSettingsLocalOk = true;
        $yomerrch24B24SiteSync = \array_replace($yomerrch24B24SiteSync, $cfg);
    }
}

// Source-of-truth policy: integration secret is read only from site_sync_settings.local.php.
// Values from tracked site_sync_settings.php are ignored for sync_token to avoid ambiguity.
$syncTokenFromLocal = '';
if ($syncSettingsLocalOk && \is_array($cfg ?? null) && \array_key_exists('sync_token', $cfg) && \is_scalar($cfg['sync_token'])) {
    $syncTokenFromLocal = (string)$cfg['sync_token'];
}
$yomerrch24B24SiteSync['sync_token'] = $syncTokenFromLocal;

// CRM→site: секрет для POST на endpoint.php сайта (= inbound_secret в config.local.php на yomerch.ru).
$siteOutboundToken = $syncTokenFromLocal;
if ($syncSettingsLocalOk && \is_array($cfg ?? null)) {
    foreach (['site_inbound_secret', 'inbound_secret'] as $outboundSecretKey) {
        if (!empty($cfg[$outboundSecretKey]) && \is_scalar($cfg[$outboundSecretKey])) {
            $siteOutboundToken = (string)$cfg[$outboundSecretKey];
            break;
        }
    }
}
$yomerrch24B24SiteSync['site_outbound_token'] = $siteOutboundToken;

/**
 * Авторитетно для OutboundRequest::isSiteSync* (обход уже объявленных констант).
 * sync_debug без sync_trace: раньше не писался b24-to-site-sync.log — теперь debug тянет за собой trace в файл.
 */
$yomerrch24B24Debug = $yomerrch24B24Flag($yomerrch24B24SiteSync['sync_debug'] ?? false);
$yomerrch24B24Trace = $yomerrch24B24Flag($yomerrch24B24SiteSync['sync_trace'] ?? false);
$GLOBALS['YOMERRCH24_B24_SITE_SYNC'] = [
    'sync_debug' => $yomerrch24B24Debug,
    'sync_trace' => $yomerrch24B24Trace || $yomerrch24B24Debug,
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
        'raw_sync_debug' => $yomerrch24B24SiteSync['sync_debug'] ?? null,
        'raw_sync_trace' => $yomerrch24B24SiteSync['sync_trace'] ?? null,
        'sync_token_source' => 'site_sync_settings.local.php',
        'sync_token_local_present' => $syncTokenFromLocal !== '',
    ],
];
unset($yomerrch24B24Debug, $yomerrch24B24Trace);

if (!\defined('YOMERRCH24_SITE_URL')) {
    $u = \trim((string)($yomerrch24B24SiteSync['site_url'] ?? ''));
    \define('YOMERRCH24_SITE_URL', $u !== '' ? \rtrim($u, '/') : 'http://new.yomerrch24.ru');
}
if (!\defined('YOMERRCH24_SITE_SYNC_TOKEN')) {
    \define('YOMERRCH24_SITE_SYNC_TOKEN', (string)($yomerrch24B24SiteSync['sync_token'] ?? ''));
}
if (!\defined('YOMERRCH24_SITE_OUTBOUND_TOKEN')) {
    \define('YOMERRCH24_SITE_OUTBOUND_TOKEN', (string)($yomerrch24B24SiteSync['site_outbound_token'] ?? ''));
}
if (!\defined('YOMERRCH24_SITE_SYNC_DEBUG')) {
    \define('YOMERRCH24_SITE_SYNC_DEBUG', (bool)($GLOBALS['YOMERRCH24_B24_SITE_SYNC']['sync_debug'] ?? false));
}
if (!\defined('YOMERRCH24_SITE_SYNC_TRACE')) {
    \define('YOMERRCH24_SITE_SYNC_TRACE', (bool)($GLOBALS['YOMERRCH24_B24_SITE_SYNC']['sync_trace'] ?? false));
}

unset(
    $yomerrch24B24SiteSync,
    $yomerrch24B24Flag,
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
    $syncSettingsLocalType,
    $syncTokenFromLocal
);

require_once __DIR__ . '/../modules/bootstrap.php'; // кастомные include.php, в т.ч. yomerch.b24.*



