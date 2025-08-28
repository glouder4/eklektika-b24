<?php
namespace Kitconsulting\ProductApplications;

use Bitrix\Main\Loader;
use Bitrix\Rest\RestException;
use Kitconsulting\ProductApplications\Entity\ProductApplication;

class Rest
{
    public static function onRestServiceBuildDescription()
    {
        return [
            'kit.productapplications' => [
                'kit.productapplications.set' => [__CLASS__, 'setProductApplications'],
            ]
        ];
    }

    /**
     * @throws \Bitrix\Main\LoaderException
     * @throws RestException
     */
    public static function validate($query, $keys = [])
    {
        if (!Loader::includeModule('crm')) {
            throw new RestException('module crm required');
        }

        foreach ($keys as $key) {
            if (!array_key_exists($key, $query)) {
                throw new RestException("$key required!");
            }
        }
    }

    public static function setProductApplications($query, $n, \CRestServer  $server) {
//        $query = array_change_key_case($query, CASE_UPPER);

        $binds = [];

        foreach ($query as $bind) {
            $bind = array_change_key_case($bind, CASE_UPPER);
            self::validate($bind, ['PRODUCT_XML_ID', 'APPLICATIONS']);
            $binds[] = $bind;
        }

        file_put_contents(__DIR__ . '/binds.log', var_export([
            "time" => date("Y-m-d H:i:s"),
            "binds" => $binds
        ], true), FILE_APPEND);

        foreach ($binds as $bind) {
            $productXmlId = $bind['PRODUCT_XML_ID'];

            $productRes = \CIBlockElement::GetList([], ['XML_ID' => $productXmlId]);

            if(!$product = $productRes->Fetch()) {
                throw new RestException("Product not found");
            }

            $applicationXmlIds = array_filter($bind['APPLICATIONS']);
            $applicationsIds = [];
            if (!empty($applicationXmlIds))
            {
                $applicationsRes = \CIBlockElement::GetList([], ['XML_ID' => $applicationXmlIds]);

                while ($application = $applicationsRes->Fetch()) {
                    $applicationsIds[$application['XML_ID']] = $application['ID'];
                }
            }

            $foundedApplicationXmlIds = array_keys($applicationsIds);

            if(count($applicationXmlIds) != count($foundedApplicationXmlIds)) {
                $notFoundXmlIds = array_diff($applicationXmlIds, $foundedApplicationXmlIds);

                throw new RestException("Products not found: ".implode(',', $notFoundXmlIds));
            }

            ProductApplication::unbindAll($product['ID']);
            ProductApplication::bind($product['ID'], array_values($applicationsIds));
        }

        return [
            'success' => true
        ];
    }
}
