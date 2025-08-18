<?php
namespace OnlineService;

use Bitrix\Main\Loader;

class DealExpiryAgent
{
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
            require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/DealExpiryHandler.php');
            
            // Создаем экземпляр обработчика
            $handler = new DealExpiryHandler();
            
            // Получаем просроченные сделки
            $deals = self::getExpiredDeals();
            
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
            $logMessage = date('Y-m-d H:i:s') . " - AGENT_RUN - " . json_encode($processed, JSON_UNESCAPED_UNICODE) . "\n";
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/cron/deals_expired.log', $logMessage, FILE_APPEND | LOCK_EX);
            
            return "\\OnlineService\\DealExpiryAgent::checkExpiredDeals();";
            
        } catch (\Exception $e) {
            $errorMessage = date('Y-m-d H:i:s') . " - AGENT_ERROR - " . $e->getMessage() . "\n";
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/cron/deals_expired.log', $errorMessage, FILE_APPEND | LOCK_EX);
            
            return "\\OnlineService\\DealExpiryAgent::checkExpiredDeals();";
        }
    }
    
    /**
     * Получает просроченные сделки
     */
    private static function getExpiredDeals()
    {
        $currentDate = new \Bitrix\Main\Type\DateTime();
        
        // Сделки с просроченным резервом (стадии UC_JSQC9O или 6)
        $reserveExpired = \Bitrix\Crm\DealTable::getList([
            'select' => ['ID', 'TITLE', 'ASSIGNED_BY_ID', 'UF_CRM_1713789046772'],
            'filter' => [
                '=STAGE_ID' => ['UC_JSQC9O', '6'],
                '<UF_CRM_1713789046772' => $currentDate,
                '!UF_CRM_1713789046772' => false
            ]
        ])->fetchAll();
        
        // Сделки с просроченным образцом (стадии 5 или 7)
        $sampleExpired = \Bitrix\Crm\DealTable::getList([
            'select' => ['ID', 'TITLE', 'ASSIGNED_BY_ID', 'UF_CRM_1754666329972'],
            'filter' => [
                '=STAGE_ID' => ['5', '7'],
                '<UF_CRM_1754666329972' => $currentDate,
                '!UF_CRM_1754666329972' => false
            ]
        ])->fetchAll();
        
        return [
            'reserve_expired' => $reserveExpired,
            'sample_expired' => $sampleExpired
        ];
    }
}



