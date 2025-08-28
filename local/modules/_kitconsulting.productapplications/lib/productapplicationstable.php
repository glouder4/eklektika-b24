<?php
namespace Kitconsulting\ProductApplications;

use Bitrix\Crm\IBlockElementProxyTable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Query\Join;

class ProductApplicationsTable extends \Bitrix\Main\ORM\Data\DataManager {

    public static function getTableName()
    {
        return 'b_kitconsulting_product_applications';
    }

    public static function getMap()
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),
            (new IntegerField('OWNER_PRODUCT_ID'))
                ->configureRequired(),
            (new IntegerField('PRODUCT_ID'))
                ->configureRequired(),
            (new Reference(
                'OWNER_IBLOCK_ELEMENT',
                IBlockElementProxyTable::class,
                Join::on('this.OWNER_PRODUCT_ID', 'ref.ID')
            )),
            (new Reference(
                'IBLOCK_ELEMENT',
                IBlockElementProxyTable::class,
                Join::on('this.PRODUCT_ID', 'ref.ID')
            ))->configureJoinType('inner')
        ];
    }
}
