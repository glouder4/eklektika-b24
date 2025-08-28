<?php
namespace Kit\Scripts;

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Engine\Response;

class CustomOrmObject
    extends \Bitrix\Crm\Service\Converter\OrmObject
{
    protected $entityTypeId = 0;

    /**
     * Converts $fieldName from camelCase to UPPER_CASE.
     *
     * @param string $fieldName
     * @return string
     */
    public function convertFieldNameFromCamelCaseToUpperCase(string $fieldName): string
    {
        global $entityTypeId;
        if (isset($entityTypeId) && !empty($entityTypeId))
            $this->entityTypeId = $entityTypeId;

        // if there is camelCase for this $fieldName - than this $fieldName is already in UPPER_CASE.
        $CaseCache = Container::getInstance()->getConverterCaseCache();
        $camelCase = $CaseCache->getCamelCase($fieldName);
        if ($camelCase)
        {
            return $fieldName;
        }

        $upperCase = $CaseCache->getUpperCase($fieldName);
        if ($upperCase)
        {
            return $upperCase;
        }

        if (in_array($this->entityTypeId, [31]))
        {
            $string = preg_replace('/([^_])([A-Z])/', '$1_$2', $fieldName);
            $string = preg_replace('/Crm(\d+)/', 'Crm_$1', $string, 1);
            $upperCase = $this->getToUpperCaseConverterToSmartInvoice()->process($string);
        }
        else $upperCase = $this->getToUpperCaseConverter()->process($fieldName);

        $CaseCache->add($fieldName, $upperCase);

        return $upperCase;
    }

    protected function getToUpperCaseConverterToSmartInvoice (): Response\Converter
    {
        $this->upperConverter = new Response\Converter(
            Response\Converter::TO_UPPER
        );
        return $this->upperConverter;
    }
}