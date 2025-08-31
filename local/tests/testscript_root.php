<?php
$DOCUMENT_ROOT = realpath(__DIR__ . '/..');
$_SERVER['DOCUMENT_ROOT'] = '/home/bitrix/ext_www/bitrix.yomerch.ru';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

define('LANG', 's1');
define('BX_UTF', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_BUFFER_USED', true);
define('STOP_STATISTICS', true);
define('BX_SECURITY_SHOW_MESSAGE', true);
define('NO_KEEP_STATISTIC', 'Y');
define('NO_AGENT_STATISTIC', 'Y');
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
global $USER;
$USER->Authorize(1);

ini_set('memory_limit', '-1');
set_time_limit(0);
