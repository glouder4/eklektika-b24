<?php

namespace Kitconsulting\ProductApplications\Entity;

use Bitrix\Crm\IBlockElementProxyTable;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\ORM\Fields\FieldTypeMask;
use Bitrix\Main\ORM\Objectify\Values;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\ORM\Query\Query;
use Kitconsulting\ProductApplications\ProductApplicationsTable;

class ProductApplication
{
    public static function loadRows($ownerProductId) {
        $result = [];

        $res = ProductApplicationsTable::getList([
            'filter' => [
                'OWNER_PRODUCT_ID' => $ownerProductId
            ],
            'select' => ['*', 'IBLOCK_ELEMENT']
        ]);

        while ($row = $res->fetchObject()) {
            $result[] = $row->collectValues(Values::ALL, FieldTypeMask::ALL, true);
        }

        return $result;
    }

    public static function loadAllRows($ownerProductId) {
        $result = [];

        $res = ProductApplicationsTable::getList([
            'filter' => [
                'OWNER_PRODUCT_ID' => $ownerProductId
            ],
            'select' => ['*', 'IBLOCK_ELEMENT']
        ]);

        $counter = 0;
        $applications = [];
        while ($row = $res->fetchObject()) {
            $result[$counter] = $row->collectValues(Values::ALL, FieldTypeMask::ALL, true);
            $result[$counter]["PRODUCT_HAS"] = true;

            $applications[] = $result[$counter]['PRODUCT_ID'];

            $counter++;
        }

        $applicationsList = \Kitconsulting\ProductApplications\ProductApplicationsTable::getList(
            [
                'filter' => [
                    '!PRODUCT_ID' => $applications
                ],
                'group' => [
                    'PRODUCT_ID'
                ],
                'select' => [
                    'PRODUCT_ID', 'APPLICATIONS_' => 'IBLOCK_ELEMENT.*'
                ],
            ]
        )->fetchAll();

        foreach ($applicationsList as $row) {
            $result[$counter++] = [
                'PRODUCT_ID' => $row['PRODUCT_ID'],
                'IBLOCK_ELEMENT' => [
                    "ID" => $row['APPLICATIONS_ID'],
                    "IBLOCK_ID" => $row['APPLICATIONS_IBLOCK_ID'],
                    "NAME" => $row['APPLICATIONS_NAME'],
                ],
                'PRODUCT_HAS' => false
            ];
        }

        return $result;
    }

    public static function saveRows($ownerProductId, $rows) {
        $currentRowsRes = ProductApplicationsTable::getList([
            'filter' => [
                'OWNER_PRODUCT_ID' => $ownerProductId
            ]
        ]);

        $currentRows = [];

        while ($row = $currentRowsRes->fetch()) {
            $currentRows[intval($row['ID'])] = $row;
        }

        foreach ($rows as $row) {
            $rowId = 0;
            if(isset($row['ID'])) {
                $rowId = intval($row['ID']);
            }

            $row['OWNER_PRODUCT_ID'] = $ownerProductId;

            if($rowId && isset($currentRows[$rowId])) {
                ProductApplicationsTable::update($rowId, $row);
            } else {
                ProductApplicationsTable::add($row);
            }

            unset($currentRows[$rowId]);
        }

        foreach ($currentRows as $rowId => $row) {
            ProductApplicationsTable::delete($rowId);
        }
    }

    public static function unbindAll($productId) {
        $filter = [
            '=PRODUCT_ID' => $productId,
        ];

        $entity = ProductApplicationsTable::getEntity();
        $connection = $entity->getConnection();

        return $connection->query(sprintf(
            'DELETE FROM %s WHERE %s',
            $connection->getSqlHelper()->quote($entity->getDbTableName()),
            Query::buildFilterSql($entity, $filter)
        ));
    }

    public static function bind($productId, $applicationIds) {
        $rows = [];

        foreach ($applicationIds as $applicationId) {
            $rows[] = ['OWNER_PRODUCT_ID' => $productId, 'PRODUCT_ID' => $applicationId];
        }

        self::saveRows($productId, $rows);
    }
}