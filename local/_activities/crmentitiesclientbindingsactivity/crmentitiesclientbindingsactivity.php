<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Crm\Service\Container;

class CBPCRMEntitiesClientBindingsActivity
    extends CBPActivity
{
    public function __construct($name)
    {
        parent::__construct($name);

        $this->arProperties = [
            "EntityId" => "",
            "EntityType" => "",

            //return
            "Bindings" => [],
        ];

        $this->SetPropertiesTypes([
            'Bindings' => [
                'Multiple' => true
            ],
        ]);
    }

    public function Execute()
    {
        $entityId = $this->EntityId;
        $entityType = $this->EntityType;

        if ($entityId && $entityType)
        {
            switch($entityType)
            {
                case \CCrmOwnerType::Deal: {
                    $contacts = \Bitrix\Crm\Binding\DealContactTable::getDealBindings($entityId);
                    break;
                }
                case \CCrmOwnerType::Lead: {
                    $contacts = \Bitrix\Crm\Binding\LeadContactTable::getLeadBindings($entityId);
                    break;
                }
                case \CCrmOwnerType::Company: {
                    $contacts = \Bitrix\Crm\Binding\ContactCompanyTable::getCompanyBindings($entityId);
                    break;
                }
                default: {
                    $contacts = \Bitrix\Crm\Binding\EntityContactTable::getContactIds($entityType, $entityId);
                }
            }

            $contactsId = array_column($contacts, 'CONTACT_ID');
            $this->Bindings = $contactsId;
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
                "message" => Loc::getMessage("CRMECBA_EMPTY_ENTITY_ID_FIELD_ERROR"),
            );
        }
        if (empty($arTestProperties["EntityType"]))
        {
            $arErrors[] = array(
                "code" => "emptyHandler",
                "message" => Loc::getMessage("CRMECBA_EMPTY_ENTITY_TYPE_FIELD_ERROR"),
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
                'Name' => Loc::getMessage('CRMECBA_ENTITY_ID_TEXT'),
                'Description' => Loc::getMessage('CRMECBA_ENTITY_ID_TEXT'),
                'FieldName' => 'entityId',
                'Type' => \Bitrix\Bizproc\FieldType::INT,
                'Required' => true
            ),
            'EntityType' => array(
                'Name' => Loc::getMessage('CRMECBA_ENTITY_TYPE_TEXT'),
                'Description' => Loc::getMessage('CRMECBA_ENTITY_TYPE_TEXT'),
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
            //\CCrmOwnerType::Contact,
            \CCrmOwnerType::Company,
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