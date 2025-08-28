<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use \Bitrix\Rest\Sqs;

class CBPDirectWebHookActivity
	extends CBPActivity
{
    const HTTP_SOCKET_TIMEOUT = 10;
    const HTTP_STREAM_TIMEOUT = 10;

	public function __construct($name)
	{
		parent::__construct($name);
		$this->arProperties = array(
			"Title" => "",
			"Handler" => ""
		);
	}

	public function Execute()
	{
		$handler = $this->Handler;

		if ($handler)
		{
			$handlerData = parse_url($handler);

			if (is_array($handlerData)
				&& $handlerData['host'] <> ''
				&& mb_strpos($handlerData['host'], '.') > 0
				&& ($handlerData['scheme'] == 'http' || $handlerData['scheme'] == 'https')
			)
			{
				$target = $handlerData['scheme'] . '://';
				if (isset($handlerData['user']) || isset($handlerData['pass']))
				{
					$target .= $handlerData['user'];
					if (isset($handlerData['pass']))
					{
						$target .= ':'. $handlerData['pass'];
					}
					$target .= '@';
				}
				$target .= $handlerData['host'];
				if (isset($handlerData['port']))
				{
					$target .= ':'.$handlerData['port'];
				}
				if (isset($handlerData['path']))
				{
					$target .= CHTTP::urnEncode($handlerData['path']);
				}
				if (isset($handlerData['query']))
				{
					$target .= '?'.CHTTP::urnEncode($handlerData['query']);
				}
				if (isset($handlerData['fragment']))
				{
					$target .= '#'.CHTTP::urnEncode($handlerData['fragment']);
				}

                $params = [
                    'document_id' => $this->GetDocumentId()
                ];

                $httpClient = $this->getHttpClient();
                $httpClient->setHeader('Content-Type', 'application/json', true);
                $httpResult = $httpClient->post(
                    $target,
                    json_encode($params)
                );

				file_put_contents(__DIR__.'/logs.txt', date("Y-m-d H:i:s")."\n", FILE_APPEND);
				file_put_contents(__DIR__.'/logs.txt', $httpResult."\n", FILE_APPEND);
				file_put_contents(__DIR__.'/logs.txt', $target."\n", FILE_APPEND);
				file_put_contents(__DIR__.'/logs.txt', json_encode($params)."\n", FILE_APPEND);

                $response = $this->prepareResponse($httpResult);

				file_put_contents(__DIR__.'/logs.txt', var_export($response)."\n", FILE_APPEND);
			}
		}

		return CBPActivityExecutionStatus::Closed;
	}

    protected function prepareResponse($result)
    {
        try
        {
            return Json::decode($result);
        }
        catch(ArgumentException $e)
        {
            return false;
        }
    }

    protected function getHttpClient()
    {
        return new HttpClient(array(
            'socketTimeout' => static::HTTP_SOCKET_TIMEOUT,
            'streamTimeout' => static::HTTP_STREAM_TIMEOUT,
        ));
    }

	public static function ValidateProperties($arTestProperties = array(), CBPWorkflowTemplateUser $user = null)
	{
		$arErrors = array();

		if ($arTestProperties["Handler"] == '')
		{
			$arErrors[] = array(
				"code" => "emptyHandler",
				"message" => GetMessage("BPWHA_EMPTY_TEXT"),
			);
		}

		return array_merge($arErrors, parent::ValidateProperties($arTestProperties, $user));
	}

	public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters, $arWorkflowVariables, $arCurrentValues = null, $formName = "", $popupWindow = null, $siteId = '')
	{
		$dialog = new \Bitrix\Bizproc\Activity\PropertiesDialog(__FILE__, array(
			'documentType' => $documentType,
			'activityName' => $activityName,
			'workflowTemplate' => $arWorkflowTemplate,
			'workflowParameters' => $arWorkflowParameters,
			'workflowVariables' => $arWorkflowVariables,
			'currentValues' => $arCurrentValues,
			'formName' => $formName,
			'siteId' => $siteId
		));

		$dialog->setMap(array(
			'Handler' => array(
				'Name' => GetMessage('BPWHA_HANDLER_NAME'),
				'Description' => GetMessage('BPWHA_HANDLER_NAME'),
				'FieldName' => 'handler',
				'Type' => 'text',
				'Required' => true
			)
		));

		return $dialog;
	}

	public static function GetPropertiesDialogValues($documentType, $activityName, &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables, $arCurrentValues, &$arErrors)
	{
		$arErrors = array();

		$arProperties = array(
			"Handler" => $arCurrentValues["handler"],
		);

		$arErrors = self::ValidateProperties($arProperties, new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser));
		if (count($arErrors) > 0)
			return false;

		$arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
		$arCurrentActivity["Properties"] = $arProperties;

		return true;
	}
}
