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
            if (!\is_array($multiField)) {
                continue;
            }
            $typeId = (string) ($multiField['TYPE_ID'] ?? '');
            if ($typeId === '') {
                continue;
            }
            // PHONE/EMAIL/WEB — несколько строк; раньше каждая перезаписывала предыдущую (терялся WORK).
            if (\in_array($typeId, ['PHONE', 'EMAIL', 'WEB'], true)) {
                if (!isset($company['MULTIFIELDS'][$typeId]) || !\is_array($company['MULTIFIELDS'][$typeId])) {
                    $company['MULTIFIELDS'][$typeId] = [];
                }
                if (isset($company['MULTIFIELDS'][$typeId]['VALUE'])) {
                    $company['MULTIFIELDS'][$typeId] = [$company['MULTIFIELDS'][$typeId]];
                }
                $company['MULTIFIELDS'][$typeId][] = $multiField;
                continue;
            }
            $company['MULTIFIELDS'][$typeId] = $multiField;
        }

        return $company;
    }
}
