<?php
namespace OnlineService;

use Bitrix\Crm\DealTable;
use Bitrix\Main\Loader;

class DealExpiryAgent
{
    private const CUTOVER_SOURCE_NEW_RULES = 'new_rules';
    private const CUTOVER_SOURCE_LEGACY_FALLBACK = 'legacy_fallback';

    /**
     * Агент для проверки просроченных сделок
     * Вызывается автоматически Bitrix24
     */
    public static function checkExpiredDeals()
    {
        try {
            // Подключаем модули
            Loader::requireModule('crm');
            Loader::requireModule('main');
            
            // Подключаем наш обработчик
            require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/yomerch.b24.deals/lib/DealExpiryHandler.php';
            
            // Создаем экземпляр обработчика
            $handler = new DealExpiryHandler();
            
            // Получаем просроченные сделки из единого rules-сервиса.
            $rulesService = new DealExpiryRulesService();
            $legacyDeals = self::getExpiredDealsLegacy();
            $cutoverSource = self::CUTOVER_SOURCE_NEW_RULES;
            try {
                $deals = $rulesService->getExpiredDeals();
            } catch (\Throwable $e) {
                $deals = $legacyDeals;
                $cutoverSource = self::CUTOVER_SOURCE_LEGACY_FALLBACK;
            }
            $parity = $rulesService->compareLegacyParity($legacyDeals, $deals);
            $fallbackOnMismatch = self::isLegacyFallbackOnMismatchEnabled();
            $hasMismatch = !$parity['reserve_match'] || !$parity['sample_match'];
            if ($fallbackOnMismatch && $hasMismatch) {
                $deals = $legacyDeals;
                $cutoverSource = self::CUTOVER_SOURCE_LEGACY_FALLBACK;
            }
            
            $processed = [
                'reserve_processed' => 0,
                'sample_processed' => 0,
                'errors' => []
            ];
            
            // Обрабатываем сделки с просроченным резервом
            foreach ($deals['reserve_expired'] as $deal) {
                try {
                    if ($handler->handleReserveExpired($deal, false)) {
                        $processed['reserve_processed']++;
                    } else {
                        $processed['errors'][] = "Ошибка обработки сделки {$deal['ID']} (просрок резерва)";
                    }
                } catch (\Exception $e) {
                    $processed['errors'][] = "Ошибка обработки сделки {$deal['ID']} (просрок резерва): " . $e->getMessage();
                }
            }
            
            // Обрабатываем сделки с просроченным образцом
            foreach ($deals['sample_expired'] as $deal) {
                try {
                    if ($handler->handleSampleExpired($deal, false)) {
                        $processed['sample_processed']++;
                    } else {
                        $processed['errors'][] = "Ошибка обработки сделки {$deal['ID']} (просрок образца)";
                    }
                } catch (\Exception $e) {
                    $processed['errors'][] = "Ошибка обработки сделки {$deal['ID']} (просрок образца): " . $e->getMessage();
                }
            }
            
            // Логируем результат
            $trace = \OnlineService\SyncTraceContext::resolve();
            $processed['cutover_label'] = $trace['cutover_label'];
            $processed['correlation_id'] = $trace['correlation_id'];
            $processed['cutover_source'] = $cutoverSource;
            $logMessage = date('Y-m-d H:i:s') . " - AGENT_RUN - " . json_encode($processed, JSON_UNESCAPED_UNICODE) . "\n";
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/cron/deals_expired.log', $logMessage, FILE_APPEND | LOCK_EX);
            
            return "\\OnlineService\\DealExpiryAgent::checkExpiredDeals();";
            
        } catch (\Exception $e) {
            $errorMessage = date('Y-m-d H:i:s') . " - AGENT_ERROR - " . $e->getMessage() . "\n";
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/cron/deals_expired.log', $errorMessage, FILE_APPEND | LOCK_EX);
            
            return "\\OnlineService\\DealExpiryAgent::checkExpiredDeals();";
        }
    }

    private static function getExpiredDealsLegacy(): array
    {
        $result = [
            'reserve_expired' => [],
            'sample_expired' => [],
        ];
        $now = new \DateTime();

        $reserveDeals = DealTable::getList([
            'select' => [
                'ID',
                'TITLE',
                'STAGE_ID',
                'ASSIGNED_BY_ID',
                'COMPANY_ID',
                'CONTACT_ID',
                DealExpiryRulesService::RESERVE_EXPIRY_FIELD,
                DealExpiryRulesService::SAMPLE_EXPIRY_FIELD,
            ],
            'filter' => [
                '=STAGE_ID' => DealExpiryRulesService::RESERVE_STAGES,
                '!=' . DealExpiryRulesService::RESERVE_EXPIRY_FIELD => false,
            ],
        ])->fetchAll();
        foreach ($reserveDeals as $deal) {
            if (empty($deal[DealExpiryRulesService::RESERVE_EXPIRY_FIELD])) {
                continue;
            }
            $reserveDate = new \DateTime((string)$deal[DealExpiryRulesService::RESERVE_EXPIRY_FIELD]);
            if ($now > $reserveDate) {
                $result['reserve_expired'][] = $deal;
            }
        }

        $sampleDeals = DealTable::getList([
            'select' => [
                'ID',
                'TITLE',
                'STAGE_ID',
                'ASSIGNED_BY_ID',
                'COMPANY_ID',
                'CONTACT_ID',
                DealExpiryRulesService::RESERVE_EXPIRY_FIELD,
                DealExpiryRulesService::SAMPLE_EXPIRY_FIELD,
            ],
            'filter' => [
                '=STAGE_ID' => DealExpiryRulesService::SAMPLE_STAGES,
                '!=' . DealExpiryRulesService::SAMPLE_EXPIRY_FIELD => false,
            ],
        ])->fetchAll();
        foreach ($sampleDeals as $deal) {
            if (empty($deal[DealExpiryRulesService::SAMPLE_EXPIRY_FIELD])) {
                continue;
            }
            $sampleDate = new \DateTime((string)$deal[DealExpiryRulesService::SAMPLE_EXPIRY_FIELD]);
            if ($now > $sampleDate) {
                $result['sample_expired'][] = $deal;
            }
        }

        return $result;
    }

    private static function isLegacyFallbackOnMismatchEnabled(): bool
    {
        if (\defined('YOMERRCH24_DEALS_FALLBACK_ON_MISMATCH')) {
            return self::toBool(\YOMERRCH24_DEALS_FALLBACK_ON_MISMATCH);
        }

        $settings = \OnlineService\Sync\SiteConnectorLocalSettings::load();
        if (!empty($settings['deals_fallback_on_mismatch'])) {
            return self::toBool($settings['deals_fallback_on_mismatch']);
        }

        return false;
    }

    private static function toBool($value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if (\is_string($value)) {
            return \in_array(\strtolower(\trim($value)), ['true', 'yes', 'on'], true);
        }

        return false;
    }
    
}








