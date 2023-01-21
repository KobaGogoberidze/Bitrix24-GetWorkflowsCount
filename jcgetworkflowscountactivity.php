<?

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Bizproc\FieldType;
use \Bitrix\Main\Entity\Query;
use Bitrix\Bizproc\Workflow\Entity\WorkflowInstanceTable;

class CBPJCGetWorkflowsCountActivity extends CBPActivity
{
    /**
     * Initialize activity
     * 
     * @param string $name
     */
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = array(
            "Title" => "",
            "Template" => "",
            "ExcludeCurrent" => false,
            "Count" => 0
        );

        $this->SetPropertiesTypes(
            array(
                "ExcludeCurrent" => array(
                    "Type" => FieldType::BOOL
                ),
                "Count" => array(
                    "Type" => FieldType::INT
                )
            )
        );
    }
    /**
     * Start the execution of activity
     * @return CBPActivityExecutionStatus
     */
    public function Execute()
    {
        $rootActivity = $this->GetRootActivity();
        [$module, $entity, $document_id] = $rootActivity->GetDocumentId();

        $queryBuilder = new Query(WorkflowInstanceTable::getEntity());
        $queryBuilder->addSelect("ID")
            ->addFilter("MODULE_ID", $module)
            ->addFilter("ENTITY", $entity)
            ->addFilter("DOCUMENT_ID", $document_id);

        if (CBPHelper::getBool($this->ExcludeCurrent)) {
            $queryBuilder->addFilter("!ID", $rootActivity->GetWorkflowInstanceId());
        }

        $workflowInstancesQuery = $queryBuilder->exec();
        $this->Count = $workflowInstancesQuery->getSelectedRowsCount();

        return CBPActivityExecutionStatus::Closed;
    }

    /**
     * Generate setting form
     * 
     * @param array $documentType
     * @param string $activityName
     * @param array $arWorkflowTemplate
     * @param array $arWorkflowParameters
     * @param array $arWorkflowVariables
     * @param array $arCurrentValues
     * @param string $formName
     * @return string
     */
    public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters, $arWorkflowVariables, $arCurrentValues = null, $formName = '', $popupWindow = null, $siteId = '')
    {
        $dialog = new \Bitrix\Bizproc\Activity\PropertiesDialog(__FILE__, array(
            "documentType" => $documentType,
            "activityName" => $activityName,
            "workflowTemplate" => $arWorkflowTemplate,
            "workflowParameters" => $arWorkflowParameters,
            "workflowVariables" => $arWorkflowVariables,
            "currentValues" => $arCurrentValues,
            "formName" => $formName,
            "siteId" => $siteId
        ));
        $dialog->setMap(static::getPropertiesDialogMap($documentType));

        return $dialog;
    }

    /**
     * Process form submition
     * 
     * @param array $documentType
     * @param string $activityName
     * @param array &$arWorkflowTemplate
     * @param array &$arWorkflowParameters
     * @param array &$arWorkflowVariables
     * @param array &$arCurrentValues
     * @param array &$arErrors
     * @return bool
     */
    public static function GetPropertiesDialogValues($documentType, $activityName, &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables, $arCurrentValues, &$arErrors)
    {
        $documentService = CBPRuntime::GetRuntime(true)->getDocumentService();
        $dialog = new \Bitrix\Bizproc\Activity\PropertiesDialog(__FILE__, array(
            "documentType" => $documentType,
            "activityName" => $activityName,
            "workflowTemplate" => $arWorkflowTemplate,
            "workflowParameters" => $arWorkflowParameters,
            "workflowVariables" => $arWorkflowVariables,
            "currentValues" => $arCurrentValues,
        ));

        $arProperties = [];
        foreach (static::getPropertiesDialogMap($documentType) as $fieldID => $arFieldProperties) {
            $field = $documentService->getFieldTypeObject($dialog->getDocumentType(), $arFieldProperties);
            if (!$field) {
                continue;
            }

            $arProperties[$fieldID] = $field->extractValue(
                ["Field" => $arFieldProperties["FieldName"]],
                $arCurrentValues,
                $arErrors
            );
        }

        $arErrors = static::ValidateProperties($arProperties, new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser));

        if (count($arErrors) > 0) {
            return false;
        }

        $currentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $currentActivity["Properties"] = $arProperties;

        return true;
    }

    /**
     * Validate user provided properties
     * 
     * @param array $arTestProperties
     * @param CBPWorkflowTemplateUser $user
     * @return array
     */
    public static function ValidateProperties($arTestProperties = array(), CBPWorkflowTemplateUser $user = null)
    {
        $arErrors = array();
        foreach (static::getPropertiesDialogMap($documentType) as $fieldID => $arFieldProperties) {
            if (isset($arFieldProperties["Required"]) && $arFieldProperties["Required"] && empty($arTestProperties[$fieldID])) {
                $arErrors[] = array(
                    "code" => "emptyText",
                    "parameter" => $fieldID,
                    "message" => str_replace("#FIELD_NAME#", $arFieldProperties["Name"], GetMessage("JC_GWC_FIELD_NOT_SPECIFIED")),
                );
            }
        }

        return array_merge($arErrors, parent::ValidateProperties($arTestProperties, $user));
    }

    /**
     * User provided properties
     * 
     * @return array
     */
    private static function getPropertiesDialogMap($documentType = array())
    {
        $arTemplates = array();
        if (is_array($documentType) && !empty($documentType)) {
            foreach (CBPDocument::GetWorkflowTemplatesForDocumentType($documentType) as $arTemplate) {
                $arTemplates[$arTemplate["ID"]] = $arTemplate["NAME"];
            }
        }

        return array(
            "Template" => array(
                "Name" => GetMessage("JC_GWC_TEMPLATES_FIELD_TITLE"),
                "FieldName" => "Template",
                "Type" => FieldType::SELECT,
                "OPTIONS" => $arTemplates,
                "Required" => true
            ),
            "ExcludeCurrent" => array(
                "Name" => GetMessage("JC_GWC_EXCLUDE_CURRENT_FIELD_TITLE"),
                "FieldName" => "ExcludeCurrent",
                "Type" => FieldType::BOOL,
                "Required" => true,
                "Default" => "N",
            )
        );
    }
}
