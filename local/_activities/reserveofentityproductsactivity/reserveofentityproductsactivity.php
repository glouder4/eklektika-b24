<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use \Bitrix\Rest\Sqs;
use Bitrix\Crm\Service\Container;
use Kitconsulting\ExtendedProductsRow\CCrmProductRowExtended;

class CBPReserveOfEntityProductsActivity
    extends CBPActivity
{
    public function __construct($name)
    {
        parent::__construct($name);

        $this->arProperties = [
            "Title" => "",
            "EntityId" => "",
            "EntityType" => "",

            //return
            "ReserveProducts" => [],
        ];

        $this->SetPropertiesTypes([
            'ReserveProducts' => [
                //'Type' => Bitrix\Bizproc\FieldType::SELECT,
                'Multiple' => true
            ],
        ]);
    }

    public function Execute()
    {
        if (!Loader::includeModule('kitconsulting.extendedproductsrow'))
        {
            return CBPActivityExecutionStatus::Closed;
        }

        $entityId = $this->EntityId;
        $entityType = $this->EntityType;

        if ($entityId && $entityType)
        {
            $entityRows = CCrmProductRowExtended::LoadRows(
                \CCrmOwnerTypeAbbr::ResolveByTypeID($entityType),
                $entityId,
                true
            );

            $products = [];
            foreach ($entityRows as $row)
            {
                $products[] = "{$row['PRODUCT_NAME']} - " . ($row['UF_RESERVE_QUANTITY'] ?? 0) . " {$row['MEASURE_NAME']}.";
            }

            $this->ReserveProducts = $products;
        }

        return CBPActivityExecutionStatus::Closed;
    }

    public static function ValidateProperties($arTestProperties = [], CBPWorkflowTemplateUser $user = null)
    {
        $arErrors = [];

        if (empty($arTestProperties["EntityId"]))
        {
            $arErrors[] = array(
                "code" => "emptyHandler",
                "message" => GetMessage("ROEPA_EMPTY_ENTITY_ID_FIELD_ERROR"),
            );
        }
        if (empty($arTestProperties["EntityType"]))
        {
            $arErrors[] = array(
                "code" => "emptyHandler",
                "message" => GetMessage("ROEPA_EMPTY_ENTITY_TYPE_FIELD_ERROR"),
            );
        }

        return array_merge($arErrors, parent::ValidateProperties($arTestProperties, $user));
    }

    public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters,
                                               $arWorkflowVariables, $arCurrentValues = null, $formName = "", $popupWindow = null, $siteId = '')
    {
        $dialog = new \Bitrix\Bizproc\Activity\PropertiesDialog(__FILE__, [
            'documentType' => $documentType,
            'activityName' => $activityName,
            'workflowTemplate' => $arWorkflowTemplate,
            'workflowParameters' => $arWorkflowParameters,
            'workflowVariables' => $arWorkflowVariables,
            'currentValues' => $arCurrentValues,
            'formName' => $formName,
            'siteId' => $siteId
        ]);

        $dialog->setMap(array(
            'EntityId' => array(
                'Name' => GetMessage('ROEPA_ENTITY_ID_TEXT'),
                'Description' => GetMessage('ROEPA_ENTITY_ID_TEXT'),
                'FieldName' => 'entityId',
                'Type' => \Bitrix\Bizproc\FieldType::INT,
                'Required' => true
            ),
            'EntityType' => array(
                'Name' => GetMessage('ROEPA_ENTITY_TYPE_TEXT'),
                'Description' => GetMessage('ROEPA_ENTITY_TYPE_TEXT'),
                'FieldName' => 'entityType',
                'Type' => \Bitrix\Bizproc\FieldType::SELECT,
                'Required' => true
            )
        ));

        $dialog->setRuntimeData([
            "crmTypes" => self::getCrmTypes()
        ]);

        return $dialog;
    }

    protected static function getCrmTypes()
    {
        $typesIds = [
            \CCrmOwnerType::Lead,
            \CCrmOwnerType::Deal,
            \CCrmOwnerType::Contact,
            \CCrmOwnerType::Company,
            \CCrmOwnerType::Invoice,
            \CCrmOwnerType::SmartInvoice
        ];
        $dynamicTypesMap = Container::getInstance()->getDynamicTypesMap();
        $dynamicTypesMap->load([
            'isLoadCategories' => false,
            'isLoadStages' => false,
        ]);
        foreach ($dynamicTypesMap->getTypes() as $type)
        {
            $typesIds[] = $type->getEntityTypeId();
        }

        return \CCrmOwnerType::GetDescriptions($typesIds);
    }

    public static function GetPropertiesDialogValues($documentType, $activityName, &$arWorkflowTemplate, &$arWorkflowParameters,
                                                     &$arWorkflowVariables, $arCurrentValues, &$arErrors)
    {
        $arErrors = [];

        $arProperties = [
            "EntityId" => trim($arCurrentValues["entityId"]),
            "EntityType" => $arCurrentValues["entityType"],
        ];

        $arErrors = self::ValidateProperties($arProperties, new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser));
        if (count($arErrors) > 0)
            return false;

        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $arCurrentActivity["Properties"] = $arProperties;

        return true;
    }
}