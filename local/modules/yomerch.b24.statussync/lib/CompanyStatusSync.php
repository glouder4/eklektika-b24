<?php

namespace OnlineService\StatusSync;

class CompanyStatusSync extends OutboundBase
{
    public static function addElementEvent(&$arFields)
    {
        static::updateElementEvent($arFields);
    }

    // Создание/Обновление статуса
    public static function updateElementEvent(&$arFields)
    {
        global $USER;
        if ($USER->IsAdmin()) {
            $trace = \OnlineService\SyncTraceContext::resolve();
            $params = [
                'ACTION' => 'UPDATE_GROUP',
                'ID' => $arFields['ID'],
                'ACTIVE' => $arFields['ACTIVE'],
                'NAME' => $arFields['NAME'],
                'C_SORT' => $arFields['SORT'],
                '_SYNC_TRACE_ID' => (string)$trace['trace_id'],
                '_SYNC_CUTOVER_LABEL' => (string)$trace['cutover_label'],
            ];

            $updater = new self();
            $updater->sendRequest($params, false);
        }
    }
}
