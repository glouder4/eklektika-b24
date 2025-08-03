<?php
    namespace OnlineService\Site;
    use OnlineService\Site\UpdaterAbstract;

    class CompanyStatusUpdater extends UpdaterAbstract{

        public static function addElementEvent(&$arFields) {
            // Логика для добавления элемента
        }

        // Создание/Обновление статуса
        public static function updateElementEvent(&$arFields) {
            global $USER;
            if( $USER->IsAdmin() ){
                $props = $arFields['PROPERTY_VALUES'];
                //$PRICE_TYPE_ID = array_shift($props['333'])['VALUE'];

                $params = [
                    'ACTION' => "UPDATE_GROUP",
                    'ID' => $arFields['ID'],
                    //'PRICE_TYPE_ID' => $PRICE_TYPE_ID,
                    'ACTIVE' => $arFields['ACTIVE'],
                    'NAME' => $arFields['NAME'],
                    'C_SORT' => $arFields['SORT']
                ];

                // Создаем временный экземпляр только для отправки запроса
                $updater = new self();
                $res = $updater->sendRequest($params);
            }
        }
    }