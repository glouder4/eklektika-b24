<?php


if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}

use Bitrix\Main;
use Bitrix\Crm\Service;

class CrmEntityExtendedproductApplicationsComponent extends \CBitrixComponent
    implements Main\Engine\Contract\Controllerable, Main\Errorable
{
    protected Main\ErrorCollection $errorCollection;

    public function __construct($component = null)
    {
        parent::__construct($component);
        Main\Loader::includeModule('crm');
        Main\Loader::includeModule('kitconsulting.extendedproductsrow');
        Main\Loader::includeModule('kitconsulting.productapplicationsextension');
    }

    public function configureActions()
    {
        return [];
    }

    public function onPrepareComponentParams($arParams)
    {
        $this->errorCollection = new Main\ErrorCollection();
    }

    public function getErrors()
    {
        return $this->errorCollection->toArray();
    }

    public function getErrorByCode($code)
    {
        return $this->errorCollection->getErrorByCode($code);
    }

    public function getApplicationsAction()
    {
        Main\Loader::includeModule('crm');
        Main\Loader::includeModule('kitconsulting.productapplications');
        $productId = $this->request->get('productId');
        $rowId = $this->request->get('rowId');

        $applications = \Kitconsulting\ProductApplications\ProductApplicationsTable::getList(
            [
                'select' => [
                    'APPLICATION_' => 'IBLOCK_ELEMENT.*'
                ],
                'filter' => [
                    'OWNER_PRODUCT_ID' => $productId
                ]
            ]
        )->fetchAll();

        foreach ($applications as &$application) {
            $helper = new \Kitconsulting\ProductApplicationsExtension\ApplicationsHelper();
            $application = $helper->loadProductPrices($application['APPLICATION_ID'], $application);

            if (!empty($rowId)) {
                $applicationProductRow = \Kitconsulting\ExtendedProductsRow\CCrmProductRowExtended::getList(
                    [],
                    [
                        'PRODUCT_ID' => $application['APPLICATION_ID'],
                        'UF_APPLICATION_PARENT_PRODUCT_ROW_ID' => $rowId
                    ]
                )->Fetch();

                if ($applicationProductRow) $application['APPLICATION_PRODUCT_ROW'] = $applicationProductRow;
            }

            $application['PRODUCT_HAS'] = true;
        }

        $anotherApplications = \Kitconsulting\ProductApplications\ProductApplicationsTable::getList(
            [
                'filter' => [
                    '!PRODUCT_ID' => array_column($applications, 'APPLICATION_ID'),
                ],
                'select' => [
                    'PRODUCT_ID',
                    'APPLICATION_' => 'IBLOCK_ELEMENT.*'
                ],
                'group' => [
                    'PRODUCT_ID'
                ]
            ]
        )->fetchAll();

        foreach ($anotherApplications as $anotherApplication) {
            $helper = new \Kitconsulting\ProductApplicationsExtension\ApplicationsHelper();
            $anotherApplication = $helper->loadProductPrices($anotherApplication['APPLICATION_ID'], $anotherApplication);

            $anotherApplication['PRODUCT_HAS'] = false;

            $applications[] = $anotherApplication;
        }

        return [
            'applications' => $applications,
            'columnNames' => ['', 'Наименование']
        ];
    }
}