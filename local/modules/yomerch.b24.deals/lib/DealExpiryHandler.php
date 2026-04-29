<?php
namespace OnlineService;

use Bitrix\Main\Loader;
use Bitrix\Crm\DealTable;
use Bitrix\Crm\ActivityTable;
use Bitrix\Main\Type\DateTime;
use Bitrix\Im\Notify;

class DealExpiryHandler
{
    private $currentDate;
    
    // Поля для проверки просрочки
    private const RESERVE_EXPIRY_FIELD = 'UF_CRM_1713789046772'; // Просрок резерва
    private const SAMPLE_EXPIRY_FIELD = 'UF_CRM_1754666329972';  // Просрок образца
    
    public function __construct()
    {
        $this->currentDate = new DateTime();
        Loader::requireModule('crm');
        Loader::requireModule('main');
        
        // Инициализируем пользователя если не инициализирован
        if (!isset($GLOBALS['USER']) || !$GLOBALS['USER']->IsAuthorized()) {
            global $USER;
            if (!$USER) {
                $USER = new \CUser();
            }
        }
    }
    
    /**
     * Обработчик для сделок с просроченным резервом
     */
    public function handleReserveExpired($deal, $testMode = false)
    {
        try {
            if (!$testMode) {
                // Создаем активность для уведомления о просроченном резерве
                $this->createReserveExpiredActivity($deal);
            }
            
            // Можно добавить изменение стадии сделки
            $this->updateDealStage($deal['ID'], 10);
            
            // Можно отправить уведомление ответственному
            $this->sendNotification($deal['ASSIGNED_BY_ID'], 'reserve_expired', $deal);
            
            // Логируем событие
            $this->logExpiredEvent($deal['ID'], $testMode ? 'reserve_expired_test' : 'reserve_expired', [
                'deal_id' => $deal['ID'],
                'deal_title' => $deal['TITLE'],
                'expired_date' => $deal[self::RESERVE_EXPIRY_FIELD] ?? 'не указана',
                'current_date' => $this->currentDate->format('Y-m-d H:i:s'),
                'assigned_by' => $deal['ASSIGNED_BY_ID'],
                'test_mode' => $testMode
            ]);
            
            return true;
        } catch (\Exception $e) {
            $errorMessage = "Ошибка обработки просроченного резерва для сделки {$deal['ID']}: " . $e->getMessage();
            error_log($errorMessage);
            $this->logExpiredEvent($deal['ID'], 'reserve_expired_error', [
                'deal_id' => $deal['ID'],
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'test_mode' => $testMode
            ]);
            return false;
        }
    }
    
    /**
     * Обработчик для сделок с просроченным образцом
     */
    public function handleSampleExpired($deal, $testMode = false)
    {
        try {
            if (!$testMode) {
                // Создаем активность для уведомления о просроченном образце
                $this->createSampleExpiredActivity($deal);
            }
            
            // Можно добавить изменение стадии сделки
            $this->updateDealStage($deal['ID'], 8);
            
            // Можно отправить уведомление ответственному
            $this->sendNotification($deal['ASSIGNED_BY_ID'], 'sample_expired', $deal);
            
            // Логируем событие
            $this->logExpiredEvent($deal['ID'], $testMode ? 'sample_expired_test' : 'sample_expired', [
                'deal_id' => $deal['ID'],
                'deal_title' => $deal['TITLE'],
                'expired_date' => $deal[self::SAMPLE_EXPIRY_FIELD] ?? 'не указана',
                'current_date' => $this->currentDate->format('Y-m-d H:i:s'),
                'assigned_by' => $deal['ASSIGNED_BY_ID'],
                'test_mode' => $testMode
            ]);
            
            return true;
        } catch (\Exception $e) {
            $errorMessage = "Ошибка обработки просроченного образца для сделки {$deal['ID']}: " . $e->getMessage();
            error_log($errorMessage);
            $this->logExpiredEvent($deal['ID'], 'sample_expired_error', [
                'deal_id' => $deal['ID'],
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'test_mode' => $testMode
            ]);
            return false;
        }
    }
    
    /**
     * Создает активность для просроченного резерва
     */
    private function createReserveExpiredActivity($deal)
    {
        // Проверяем права доступа - используем проверку через CCrmDeal
        if (!\CCrmDeal::CheckReadPermission($deal['ID'])) {
            throw new \Exception('Нет прав на чтение сделки ' . $deal['ID'] . ' (необходимо для создания активностей)');
        }
        
        // Проверяем права на создание активностей через проверку прав на сделки
        if (!\CCrmDeal::CheckUpdatePermission($deal['ID'])) {
            throw new \Exception('Нет прав на обновление сделки ' . $deal['ID'] . ' (необходимо для создания активностей)');
        }
        
        // Проверяем существование ответственного
        if (!empty($deal['ASSIGNED_BY_ID'])) {
            $user = \CUser::GetByID($deal['ASSIGNED_BY_ID']);
            if (!$user->Fetch()) {
                throw new \Exception('Пользователь-ответственный не найден: ' . $deal['ASSIGNED_BY_ID']);
            }
        }
        
        // Проверяем корректность данных сделки
        if (empty($deal['TITLE'])) {
            throw new \Exception('Название сделки не указано');
        }
        
        if (empty($deal['ASSIGNED_BY_ID'])) {
            throw new \Exception('Ответственный по сделке не указан');
        }
        
        $activityFields = [
            'OWNER_TYPE_ID' => \CCrmOwnerType::Deal,
            'OWNER_ID' => $deal['ID'],
            'TYPE_ID' => \CCrmActivityType::Call,
            'PROVIDER_ID' => 'CRM_CALL',
            'PROVIDER_TYPE_ID' => 'CALL',
            'SUBJECT' => 'Просрок резерва - Сделка ' . $deal['TITLE'],
            'DESCRIPTION' => 'Резерв по сделке просрочен. Дата истечения: ' . ($deal[self::RESERVE_EXPIRY_FIELD] ?? 'не указана'),
            'PRIORITY' => \CCrmActivityPriority::High,
            'RESPONSIBLE_ID' => $deal['ASSIGNED_BY_ID'],
            'START_TIME' => $this->currentDate,
            'END_TIME' => $this->currentDate,
            'COMPLETED' => 'N'
        ];
        
        // Попробуем создать активность через CCrmActivity
        $activity = new \CCrmActivity();
        
                // Добавляем детальную диагностику
        $this->logExpiredEvent($deal['ID'], 'activity_creation_debug', [
            'deal_id' => $deal['ID'],
            'activity_fields' => $activityFields,
            'current_user' => $this->getCurrentUserId(),
            'user_rights' => [
                'can_read_deals' => \CCrmDeal::CheckReadPermission(),
                'can_update_deals' => \CCrmDeal::CheckUpdatePermission($deal['ID'])
            ]
        ]);
        
        $activityId = $activity->Add($activityFields);
        
        if (!$activityId) {
            $error = $activity->LAST_ERROR;
            $errorMessage = !empty($error) ? $error : 'Неизвестная ошибка при создании активности';
            
            // Логируем детальную информацию об ошибке
            $this->logExpiredEvent($deal['ID'], 'activity_creation_error', [
                'deal_id' => $deal['ID'],
                'activity_fields' => $activityFields,
                'error' => $error,
                'error_message' => $errorMessage,
                'activity_object' => get_class($activity)
            ]);
            
            // Попробуем альтернативный способ создания активности
            try {
                $activityId = $this->createActivityAlternative($activityFields);
                if ($activityId) {
                    return $activityId;
                }
            } catch (\Exception $altException) {
                $this->logExpiredEvent($deal['ID'], 'activity_creation_alternative_error', [
                    'deal_id' => $deal['ID'],
                    'error' => $altException->getMessage()
                ]);
            }
            
            throw new \Exception('Не удалось создать активность для просроченного резерва. Ошибка: ' . $errorMessage);
        }
        
        return $activityId;
    }
    
    /**
     * Создает активность для просроченного образца
     */
    private function createSampleExpiredActivity($deal)
    {
        // Проверяем права доступа - используем проверку через CCrmDeal
        if (!\CCrmDeal::CheckReadPermission($deal['ID'])) {
            throw new \Exception('Нет прав на чтение сделки ' . $deal['ID'] . ' (необходимо для создания активностей)');
        }
        
        // Проверяем права на создание активностей через проверку прав на сделки
        if (!\CCrmDeal::CheckUpdatePermission($deal['ID'])) {
            throw new \Exception('Нет прав на обновление сделки ' . $deal['ID'] . ' (необходимо для создания активностей)');
        }
        
        // Проверяем существование ответственного
        if (!empty($deal['ASSIGNED_BY_ID'])) {
            $user = \CUser::GetByID($deal['ASSIGNED_BY_ID']);
            if (!$user->Fetch()) {
                throw new \Exception('Пользователь-ответственный не найден: ' . $deal['ASSIGNED_BY_ID']);
            }
        }
        
        // Проверяем корректность данных сделки
        if (empty($deal['TITLE'])) {
            throw new \Exception('Название сделки не указано');
        }
        
        if (empty($deal['ASSIGNED_BY_ID'])) {
            throw new \Exception('Ответственный по сделке не указан');
        }
        
        $activityFields = [
            'OWNER_TYPE_ID' => \CCrmOwnerType::Deal,
            'OWNER_ID' => $deal['ID'],
            'TYPE_ID' => \CCrmActivityType::Call,
            'PROVIDER_ID' => 'CRM_CALL',
            'PROVIDER_TYPE_ID' => 'CALL',
            'SUBJECT' => 'Просрок образца - Сделка ' . $deal['TITLE'],
            'DESCRIPTION' => 'Образец по сделке просрочен. Дата истечения: ' . ($deal[self::SAMPLE_EXPIRY_FIELD] ?? 'не указана'),
            'PRIORITY' => \CCrmActivityPriority::High,
            'RESPONSIBLE_ID' => $deal['ASSIGNED_BY_ID'],
            'START_TIME' => $this->currentDate,
            'END_TIME' => $this->currentDate,
            'COMPLETED' => 'N'
        ];
        
        // Добавляем детальную диагностику
        $this->logExpiredEvent($deal['ID'], 'activity_creation_debug', [
            'deal_id' => $deal['ID'],
            'activity_fields' => $activityFields,
            'current_user' => $this->getCurrentUserId(),
            'user_rights' => [
                'can_read_deals' => \CCrmDeal::CheckReadPermission(),
                'can_update_deals' => \CCrmDeal::CheckUpdatePermission($deal['ID'])
            ]
        ]);
        
        $activity = new \CCrmActivity();
        $activityId = $activity->Add($activityFields);
        
        if (!$activityId) {
            $error = $activity->LAST_ERROR;
            $errorMessage = !empty($error) ? $error : 'Неизвестная ошибка при создании активности';
            
            // Логируем детальную информацию об ошибке
            $this->logExpiredEvent($deal['ID'], 'activity_creation_error', [
                'deal_id' => $deal['ID'],
                'activity_fields' => $activityFields,
                'error' => $error,
                'error_message' => $errorMessage,
                'activity_object' => get_class($activity)
            ]);
            
            // Попробуем альтернативный способ создания активности
            try {
                $activityId = $this->createActivityAlternative($activityFields);
                if ($activityId) {
                    return $activityId;
                }
            } catch (\Exception $altException) {
                $this->logExpiredEvent($deal['ID'], 'activity_creation_alternative_error', [
                    'deal_id' => $deal['ID'],
                    'error' => $altException->getMessage()
                ]);
            }
            
            throw new \Exception('Не удалось создать активность для просроченного образца. Ошибка: ' . $errorMessage);
        }
        
        return $activityId;
    }
    
    /**
     * Обновляет стадию сделки
     */
    private function updateDealStage($dealId, $stageId)
    {
        $deal = new \CCrmDeal(false);
        $fields = ['STAGE_ID' => $stageId];
        $result = $deal->Update($dealId, $fields);
        
        if (!$result) {
            throw new \Exception('Не удалось обновить стадию сделки: ' . $deal->LAST_ERROR);
        }
        
        return $result;
    }
    
    /**
     * Отправляет уведомление пользователю
     */
    private function sendNotification($userId, $type, $deal)
    {
        // Здесь можно добавить логику отправки уведомлений
        // Например, через Bitrix24 API или внутренние уведомления
        
        $message = '';
        switch ($type) {
            case 'reserve_expired':
                $message = "Просрок резерва по сделке {$deal['TITLE']} (ID: {$deal['ID']})";
                break;
            case 'sample_expired':
                $message = "Просрок образца по сделке {$deal['TITLE']} (ID: {$deal['ID']})";
                break;
        }

        if (\CModule::IncludeModule("im")) {
            $result = \CIMNotify::Add([
                "TO_USER_ID" => $userId,
                "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                "NOTIFY_MODULE" => "crm",
                "NOTIFY_EVENT" => "deal_assigned",
                "MESSAGE" => $message,
                "TAG" => "crm|deal|123", // чтобы не дублировать
            ]);

            if (!$result) {
                global $APPLICATION;
                echo "Ошибка: " . $APPLICATION->GetException()->GetString();
            }
        }
    }
    
    /**
     * Альтернативный способ создания активности
     */
    private function createActivityAlternative($activityFields)
    {
        // Упрощенный способ создания активности
        $fields = [
            'OWNER_TYPE_ID' => $activityFields['OWNER_TYPE_ID'],
            'OWNER_ID' => $activityFields['OWNER_ID'],
            'TYPE_ID' => $activityFields['TYPE_ID'],
            'PROVIDER_ID' => $activityFields['PROVIDER_ID'],
            'PROVIDER_TYPE_ID' => $activityFields['PROVIDER_TYPE_ID'],
            'SUBJECT' => $activityFields['SUBJECT'],
            'DESCRIPTION' => $activityFields['DESCRIPTION'],
            'PRIORITY' => $activityFields['PRIORITY'],
            'RESPONSIBLE_ID' => $activityFields['RESPONSIBLE_ID'],
            'START_TIME' => $activityFields['START_TIME'],
            'END_TIME' => $activityFields['END_TIME'],
            'COMPLETED' => $activityFields['COMPLETED']
        ];
        
        // Создаем активность через ActivityTable
        $result = \Bitrix\Crm\ActivityTable::add($fields);
        
        if ($result->isSuccess()) {
            return $result->getId();
        } else {
            throw new \Exception('Альтернативный способ создания активности не удался: ' . implode(', ', $result->getErrorMessages()));
        }
    }
    
    /**
     * Безопасное получение ID текущего пользователя
     */
    private function getCurrentUserId()
    {
        global $USER;
        if (isset($USER) && $USER instanceof \CUser && $USER->IsAuthorized()) {
            return $USER->GetID();
        }
        return 'unknown';
    }
    
    /**
     * Логирование событий просрочки
     */
    private function logExpiredEvent($dealId, $eventType, $data)
    {
        $logMessage = date('Y-m-d H:i:s') . " - {$eventType} - Сделка {$dealId} - " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/cron/deals_expired.log', $logMessage, FILE_APPEND | LOCK_EX);
    }
}
