<?php

namespace OnlineService\Sync\Contract;

class CompanySyncReadService
{
    public function loadCompanySnapshot(int $companyId): array
    {
        if ($companyId <= 0 || !\Bitrix\Main\Loader::includeModule('crm')) {
            return [];
        }

        $company = \Bitrix\Crm\CompanyTable::getById($companyId)->fetch();
        if (!is_array($company)) {
            return [];
        }

        global $USER_FIELD_MANAGER;
        if (is_object($USER_FIELD_MANAGER)) {
            $ufValues = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId);
            foreach ($ufValues as $key => $value) {
                $company[$key] = $value['VALUE'];
            }
        }

        $requisites = \Bitrix\Crm\RequisiteTable::getList([
            'filter' => [
                '=ENTITY_TYPE_ID' => \CCrmOwnerType::Company,
                '=ENTITY_ID' => $companyId,
            ],
            'select' => ['*'],
            'order' => ['ID' => 'ASC'],
        ])->fetchAll();
        $company['REQUISITES'] = $requisites[0] ?? [];

        $multiFields = \CCrmFieldMulti::GetEntityFields('COMPANY', $companyId, '');
        $company['MULTIFIELDS'] = [];
        foreach ($multiFields as $multiField) {
            $company['MULTIFIELDS'][$multiField['TYPE_ID']] = $multiField;
        }

        return $company;
    }
}
