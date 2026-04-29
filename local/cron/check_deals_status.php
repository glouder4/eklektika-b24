<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
// Только DealExpiryHandler из модуля deals.
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/yomerch.b24.deals/lib/DealExpiryHandler.php';

use Bitrix\Main\Loader;
use Bitrix\Crm\DealTable;

class DealStatusChecker
{
    private $currentDate;
    
    // Поля для проверки просрочки
    private const RESERVE_EXPIRY_FIELD = 'UF_CRM_1713789046772'; // Просрок резерва
    private const SAMPLE_EXPIRY_FIELD = 'UF_CRM_1754666329972';  // Просрок образца

    private const CUTOVER_SOURCE_NEW_RULES = 'new_rules';
    private const CUTOVER_SOURCE_LEGACY_FALLBACK = 'legacy_fallback';
    
    public function __construct()
    {
        $this->currentDate = new DateTime();
        Loader::requireModule('crm');
        
        // Проверяем права доступа
        if (!\CCrmDeal::CheckReadPermission()) {
            throw new \Exception('Нет прав на чтение сделок');
        }
        
        // Проверяем права на создание активностей через проверку прав на сделки
        if (!\CCrmDeal::CheckReadPermission()) {
            throw new \Exception('Нет прав на чтение сделок (необходимо для создания активностей)');
        }
    }
    
    /**
     * Получает сделки с просроченными датами
     */
    public function getExpiredDeals($customFilter = [])
    {
        $legacyResult = $this->getExpiredDealsLegacy($customFilter);
        try {
            $rulesService = new \OnlineService\DealExpiryRulesService();
            $newResult = $rulesService->getExpiredDeals();

            $parity = $rulesService->compareLegacyParity($legacyResult, $newResult);
            $trace = \OnlineService\SyncTraceContext::resolve();
            $fallbackOnMismatch = $this->isLegacyFallbackOnMismatchEnabled();
            $hasMismatch = !$parity['reserve_match'] || !$parity['sample_match'];
            $source = self::CUTOVER_SOURCE_NEW_RULES;
            $selected = $newResult;
            if ($fallbackOnMismatch && $hasMismatch) {
                $source = self::CUTOVER_SOURCE_LEGACY_FALLBACK;
                $selected = $legacyResult;
            }
            $parityLog = date('Y-m-d H:i:s') . " - PARITY_SMOKE - " . json_encode([
                'correlation_id' => $trace['correlation_id'],
                'cutover_label' => $trace['cutover_label'],
                'cutover_source' => $source,
                'fallback_on_mismatch' => $fallbackOnMismatch ? 1 : 0,
                'reserve_match' => $parity['reserve_match'],
                'sample_match' => $parity['sample_match'],
                'legacy' => $parity['legacy'],
                'current' => $parity['current'],
            ], JSON_UNESCAPED_UNICODE) . "\n";
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/cron/deals_expired.log', $parityLog, FILE_APPEND | LOCK_EX);

            $result = [
                'reserve_expired' => array_values($selected['reserve_expired'] ?? []),
                'sample_expired' => array_values($selected['sample_expired'] ?? []),
                'both_expired' => [],
                'total_found' => count($selected['reserve_expired'] ?? []) + count($selected['sample_expired'] ?? []),
                'cutover_source' => $source,
            ];
        } catch (\Throwable $e) {
            if ($this->isLegacyFallbackOnErrorEnabled()) {
                $trace = \OnlineService\SyncTraceContext::resolve();
                $errorLog = date('Y-m-d H:i:s') . " - RULES_FALLBACK - " . json_encode([
                    'correlation_id' => $trace['correlation_id'],
                    'cutover_label' => $trace['cutover_label'],
                    'cutover_source' => self::CUTOVER_SOURCE_LEGACY_FALLBACK,
                    'reason' => 'rules_service_error',
                    'error' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE) . "\n";
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/cron/deals_expired.log', $errorLog, FILE_APPEND | LOCK_EX);

                return [
                    'reserve_expired' => array_values($legacyResult['reserve_expired'] ?? []),
                    'sample_expired' => array_values($legacyResult['sample_expired'] ?? []),
                    'both_expired' => [],
                    'total_found' => count($legacyResult['reserve_expired'] ?? []) + count($legacyResult['sample_expired'] ?? []),
                    'cutover_source' => self::CUTOVER_SOURCE_LEGACY_FALLBACK,
                ];
            }

            return [
                'error' => $e->getMessage(),
                'success' => false,
            ];
        }
        
        return $result;
    }

    private function isLegacyFallbackOnMismatchEnabled(): bool
    {
        if (\defined('YOMERRCH24_DEALS_FALLBACK_ON_MISMATCH')) {
            return $this->toBool(\YOMERRCH24_DEALS_FALLBACK_ON_MISMATCH);
        }

        $settings = \OnlineService\Sync\SiteConnectorLocalSettings::load();
        if (!empty($settings['deals_fallback_on_mismatch'])) {
            return $this->toBool($settings['deals_fallback_on_mismatch']);
        }

        return false;
    }

    private function isLegacyFallbackOnErrorEnabled(): bool
    {
        if (\defined('YOMERRCH24_DEALS_FALLBACK_ON_ERROR')) {
            return $this->toBool(\YOMERRCH24_DEALS_FALLBACK_ON_ERROR);
        }

        return true;
    }

    private function toBool($value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if (\is_string($value)) {
            return \in_array(\strtolower(\trim($value)), ['true', 'yes', 'on'], true);
        }

        return false;
    }

    private function getExpiredDealsLegacy($customFilter = [])
    {
        $result = [
            'reserve_expired' => [],
            'sample_expired' => [],
        ];

        $reserveDeals = $this->getDealsForReserveCheck($customFilter);
        $sampleDeals = $this->getDealsForSampleCheck($customFilter);

        foreach ($reserveDeals as $deal) {
            if (!empty($deal[self::RESERVE_EXPIRY_FIELD])) {
                $reserveDate = new DateTime($deal[self::RESERVE_EXPIRY_FIELD]);
                if ($this->currentDate > $reserveDate) {
                    $result['reserve_expired'][] = $deal;
                }
            }
        }
        foreach ($sampleDeals as $deal) {
            if (!empty($deal[self::SAMPLE_EXPIRY_FIELD])) {
                $sampleDate = new DateTime($deal[self::SAMPLE_EXPIRY_FIELD]);
                if ($this->currentDate > $sampleDate) {
                    $result['sample_expired'][] = $deal;
                }
            }
        }

        return $result;
    }
    
    /**
     * Получает сделки для проверки просрочки резерва (статусы UC_JSQC9O или 6)
     */
    private function getDealsForReserveCheck($customFilter = [])
    {
        $filter = [
            '=STAGE_ID' => ['UC_JSQC9O', '6'],
            '!=' . self::RESERVE_EXPIRY_FIELD => false
        ];
        
        if (!empty($customFilter)) {
            $filter = array_merge($filter, $customFilter);
        }
        
        $deals = DealTable::getList([
            'select' => [
                'ID',
                'TITLE',
                'STAGE_ID',
                'ASSIGNED_BY_ID',
                'COMPANY_ID',
                'CONTACT_ID',
                self::RESERVE_EXPIRY_FIELD,
                self::SAMPLE_EXPIRY_FIELD
            ],
            'filter' => $filter
        ]);
        
        $result = [];
        while ($deal = $deals->fetch()) {
            $result[] = $deal;
        }
        
        return $result;
    }
    
    /**
     * Получает сделки для проверки просрочки образца (статусы 5 или 7)
     */
    private function getDealsForSampleCheck($customFilter = [])
    {
        $filter = [
            '=STAGE_ID' => ['5', '7'],
            '!=' . self::SAMPLE_EXPIRY_FIELD => false
        ];
        
        if (!empty($customFilter)) {
            $filter = array_merge($filter, $customFilter);
        }
        
        $deals = DealTable::getList([
            'select' => [
                'ID',
                'TITLE',
                'STAGE_ID',
                'ASSIGNED_BY_ID',
                'COMPANY_ID',
                'CONTACT_ID',
                self::RESERVE_EXPIRY_FIELD,
                self::SAMPLE_EXPIRY_FIELD
            ],
            'filter' => $filter
        ]);
        
        $result = [];
        while ($deal = $deals->fetch()) {
            $result[] = $deal;
        }
        
        return $result;
    }
    
    /**
     * Обрабатывает просроченные сделки
     */
    public function processExpiredDeals($deals, $testMode = false)
    {
        $processed = [
            'reserve_processed' => 0,
            'sample_processed' => 0,
            'errors' => [],
            'error_details' => []
        ];
        
        // Обрабатываем сделки с просроченным резервом
        foreach ($deals['reserve_expired'] as $deal) {
            try {
                if ($this->handleReserveExpired($deal, $testMode)) {
                    $processed['reserve_processed']++;
                } else {
                    $processed['errors'][] = "Ошибка обработки сделки {$deal['ID']} (просрок резерва)";
                    $processed['error_details'][] = [
                        'deal_id' => $deal['ID'],
                        'type' => 'reserve_expired',
                        'message' => 'Не удалось обработать просроченный резерв'
                    ];
                }
            } catch (Exception $e) {
                $processed['errors'][] = "Ошибка обработки сделки {$deal['ID']} (просрок резерва): " . $e->getMessage();
                $processed['error_details'][] = [
                    'deal_id' => $deal['ID'],
                    'type' => 'reserve_expired',
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ];
            }
        }
        
        // Обрабатываем сделки с просроченным образцом
        foreach ($deals['sample_expired'] as $deal) {
            try {
                if ($this->handleSampleExpired($deal, $testMode)) {
                    $processed['sample_processed']++;
                } else {
                    $processed['errors'][] = "Ошибка обработки сделки {$deal['ID']} (просрок образца)";
                    $processed['error_details'][] = [
                        'deal_id' => $deal['ID'],
                        'type' => 'sample_expired',
                        'message' => 'Не удалось обработать просроченный образец'
                    ];
                }
            } catch (Exception $e) {
                $processed['errors'][] = "Ошибка обработки сделки {$deal['ID']} (просрок образца): " . $e->getMessage();
                $processed['error_details'][] = [
                    'deal_id' => $deal['ID'],
                    'type' => 'sample_expired',
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ];
            }
        }
        
        // Обрабатываем сделки с обоими просроченными полями
        foreach ($deals['both_expired'] as $deal) { 
            $reserveHandled = false;
            $sampleHandled = false;
            
            try {
                $reserveHandled = $this->handleReserveExpired($deal, $testMode);
            } catch (Exception $e) {
                $processed['errors'][] = "Ошибка обработки резерва для сделки {$deal['ID']}: " . $e->getMessage();
                $processed['error_details'][] = [
                    'deal_id' => $deal['ID'],
                    'type' => 'both_expired_reserve',
                    'message' => $e->getMessage()
                ];
            }
            
            try {
                $sampleHandled = $this->handleSampleExpired($deal, $testMode);
            } catch (Exception $e) {
                $processed['errors'][] = "Ошибка обработки образца для сделки {$deal['ID']}: " . $e->getMessage();
                $processed['error_details'][] = [
                    'deal_id' => $deal['ID'],
                    'type' => 'both_expired_sample',
                    'message' => $e->getMessage()
                ];
            }
            
            if ($reserveHandled) {
                $processed['reserve_processed']++;
            }
            if ($sampleHandled) {
                $processed['sample_processed']++;
            }
            
            if (!$reserveHandled || !$sampleHandled) {
                $processed['errors'][] = "Ошибка обработки сделки {$deal['ID']} (оба поля просрочены)";
            }
        }
        
        return $processed;
    }
    
    /**
     * Обработчик для сделок с просроченным резервом
     */
    private function handleReserveExpired($deal, $testMode = false)
    {
        try {
            $handler = new \OnlineService\DealExpiryHandler();
            return $handler->handleReserveExpired($deal, $testMode);
        } catch (Exception $e) {
            $errorMessage = "Ошибка обработки просроченного резерва для сделки {$deal['ID']}: " . $e->getMessage();
            error_log($errorMessage);
            return false;
        }
    }
    
    /**
     * Обработчик для сделок с просроченным образцом
     */
    private function handleSampleExpired($deal, $testMode = false)
    {
        try {
            $handler = new \OnlineService\DealExpiryHandler();
            return $handler->handleSampleExpired($deal, $testMode);
        } catch (Exception $e) {
            $errorMessage = "Ошибка обработки просроченного образца для сделки {$deal['ID']}: " . $e->getMessage();
            error_log($errorMessage);
            return false;
        }
    }
    

}

// Пример функции для агента
function CheckDealStatus()
{
    // Твой PHP-скрипт
    \CModule::IncludeModule('crm');

    $checker = new DealStatusChecker();

    // Подготавливаем пользовательский фильтр из параметров запроса
    $customFilter = [];

    // Фильтр по стадии (если передан)
    if (!empty($_GET['stage_id'])) {
        $customFilter['=STAGE_ID'] = $_GET['stage_id'];
    }

    // Фильтр по ответственному (если передан)
    if (!empty($_GET['assigned_by'])) {
        $customFilter['=ASSIGNED_BY_ID'] = $_GET['assigned_by'];
    }

    // Фильтр по компании (если передан)
    if (!empty($_GET['company_id'])) {
        $customFilter['=COMPANY_ID'] = $_GET['company_id'];
    }

    // Флаг для включения детальной информации об ошибках
    $includeErrorDetails = isset($_GET['debug']) && $_GET['debug'] === '1';

    // Флаг для тестового режима (только проверка без создания активностей)
    $testMode = isset($_GET['test']) && $_GET['test'] === '1';

    // Получаем просроченные сделки
    $expiredDeals = $checker->getExpiredDeals($customFilter);

    if (isset($expiredDeals['error'])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $expiredDeals['error']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Обрабатываем найденные сделки
    $processed = $checker->processExpiredDeals($expiredDeals, $testMode);

    // ВАЖНО: верни строку "MyCustomAgentFunction();"
    // Это нужно для повторного запуска
    return "CheckDealStatus();";
}

// Пример: запускать каждый час
/*\CAgent::AddAgent(
    "CheckDealStatus();",       // имя функции с точкой с запятой!
    "",                               // модуль (не обязателен)
    "N",                              // тип агента (не агрегирующий)
    3600,                             // интервал в секундах (3600 = 1 час)
    "",                               // дата следующего запуска (авто)
    "Y",                              // включён?
    "",                               // время запуска (например, "15:00")
    30                                // смещение в секундах
);*/

// Удаление агента
// \CAgent::RemoveAgent("MyCustomAgentFunction();", "");


