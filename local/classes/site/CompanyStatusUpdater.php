<?php
    namespace OnlineService\Site;
    use OnlineService\Site\UpdaterAbstract;

    class CompanyStatusUpdater extends UpdaterAbstract{

        public static function addElementEvent(&$arFields) {
            // Логика для добавления элемента
        }

        public static function updateElementEvent(&$arFields) {
            global $USER;
            if( $USER->IsAdmin() ){
                $props = $arFields['PROPERTY_VALUES'];
                $DISCOUNT_VALUE = array_shift($props['331'])['VALUE'];

                $params = [
                    'ACTION' => "UPDATE_GROUP",
                    'ID' => $arFields['ID'],
                    'DISCOUNT_VALUE' => $DISCOUNT_VALUE,
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