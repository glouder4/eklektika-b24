<?php
namespace Kitconsulting\ExtendedProductsRow\Component;

use Bitrix\Crm\Component\EntityDetails\ProductList;

class ExtendedProductList extends ProductList
{
    /**
     * @param array $options
     * @param string $queryString
     * @return string
     */
    public static function getComponentUrl(array $options = [], string $queryString = ''): string
    {
        return static::getUrl(
            '/local/components/kitconsulting/crm.entity.extendedproduct.list/class.php',
            $options,
            $queryString
        );
    }

    /**
     * @param array $options
     * @param string $queryString
     * @return string
     */
    public static function getLoaderUrl(array $options = [], string $queryString = ''): string
    {
        return static::getUrl(
            '/local/components/kitconsulting/crm.entity.extendedproduct.list/lazyload.ajax.php',
            $options,
            $queryString
        );
    }
}