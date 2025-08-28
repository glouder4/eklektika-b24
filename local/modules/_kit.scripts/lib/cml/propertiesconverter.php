<?php

namespace Kit\Scripts\Cml;

use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\Loader;

class PropertiesConverter
{
    static array $traitProps = [];
    static array $propsCache = [];

    public static function convertProps(&$arFields, $propCode) {
        $iblockId = $arFields['IBLOCK_ID'];

        $props = [];
        $traitPropId = null;

        if(
            isset(self::$propsCache[$iblockId])
            && isset(self::$traitProps[$iblockId])
            && isset(self::$traitProps[$iblockId][$propCode])
        ) {
            $props = self::$propsCache[$iblockId];
            $traitPropId = self::$traitProps[$iblockId][$propCode];
        } else {
            $propertyIterator = PropertyTable::getList([
                'filter' => [
                    'IBLOCK_ID' => $iblockId,
                    '=ACTIVE' => 'Y'
                ]
            ]);

            if(!isset(self::$traitProps[$iblockId])) {
                self::$traitProps[$iblockId] = [];
            }

            while ($property = $propertyIterator->fetch())
            {
                if(!$traitPropId && $property['CODE'] == $propCode) {
                    $traitPropId = $property['ID'];
                }

                $props[$property['NAME']] = $property;
            }

            if(!empty($props)) {
                self::$propsCache[$iblockId] = $props;
            }

            if($traitPropId) {
                self::$traitProps[$iblockId][$propCode] = $traitPropId;
            }

            if(!$traitPropId) {
//                throw new \Exception('TRAIT PROP NOT FOUND');
                return;
            }
        }

        if(empty($props) || !$traitPropId) {
            return;
        }

        $values = &$arFields['PROPERTY_VALUES'][$traitPropId];
        if (empty($values)) return;
        $valIds = array_keys($values);

        foreach ($valIds as $valId) {
            $desc = $values[$valId]['DESCRIPTION'];
            $val = $values[$valId]['VALUE'];

            if($desc && isset($props[$desc])) {
                $prop = $props[$desc];
                $newValue = self::convertValue($prop, $val);

                if(!empty($newValue)) {

                    $newValId = 'n0';

                    if(!isset($values[$prop['ID']]) || $prop['MULTIPLE'] == 'N') {
                        $arFields['PROPERTY_VALUES'][$prop['ID']] = [];
                    }

                    if($prop['MULTIPLE'] == 'Y') {
                        if(!empty($values[$prop['ID']])) {
                            $newCounter = 0;
                            $newValId = 'n' . $newCounter;
                            while (isset($values[$prop['ID']][$newValId])) {
                                $newValId = 'n'.++$newCounter;
                            }
                        }
                    }

                    $arFields['PROPERTY_VALUES'][$prop['ID']][$newValId] = $newValue;
                    unset($values[$valId]);
                }
            }
        }
    }

    public static function convertValue($property, $value) {

        $result = [];

        switch ($property['USER_TYPE']) {
            case 'ECrm':

                $propSettings = unserialize($property['USER_TYPE_SETTINGS']);
                if($value && Loader::includeModule('crm')) {
                    $typesIds = [];

                    foreach ($propSettings as $setting => $enabled) {

                        if($enabled == 'Y') {
                           $typeId = \CCrmOwnerType::ResolveID($setting);

                           if($typeId) {
                               $typesIds[] = $typeId;
                           }
                        }
                    }

                    if(!empty($typesIds)) {
                        $entityId = self::findCrmEntity($typesIds, $value);

                        if($entityId) {
                            $value = $entityId;
                        } else {
                            $value = null;
                        }
                    }
                }
        }

        if($value) {
            switch ($property['PROPERTY_TYPE']) {
                case PropertyTable::TYPE_STRING:
                case PropertyTable::TYPE_NUMBER:
                    $result['VALUE'] = $value;
                    break;
            }
        }

        return $result;
    }

    public static function findCrmEntity($typeIds, $guid) {
        $result = null;

        foreach ($typeIds as $typeId) {
            if($typeId == \CCrmOwnerType::Company) {
                $dbRes = \CCrmCompany::GetList([], [
                    'ORIGIN_ID' => $guid
                ]);

                if($company = $dbRes->Fetch()) {
                    $result = \CCrmOwnerTypeAbbr::Company.'_'.$company['ID'];
                    break;
                }
            } elseif($typeId == \CCrmOwnerType::Contact) {
                $dbRes = \CCrmContact::GetList([], [
                    'ORIGIN_ID' => $guid
                ]);

                if($contact = $dbRes->Fetch()) {
                    $result = \CCrmOwnerTypeAbbr::Contact.'_'.$contact['ID'];
                    break;
                }
            }
        }

        return $result;
    }
}
