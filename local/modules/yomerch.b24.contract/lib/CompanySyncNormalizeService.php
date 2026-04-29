<?php

namespace OnlineService\Sync\Contract;

class CompanySyncNormalizeService
{
    public function normalizeForOutbound(array $company, array $arFields, array $ufMap): array
    {
        $title = (string)($arFields['TITLE'] ?? $company['TITLE'] ?? '');
        $inn = (string)($company['REQUISITES']['RQ_INN'] ?? '');
        $city = (string)($company[$ufMap['company_city']] ?? '');
        $web = (string)($company[$ufMap['company_web']] ?? '');
        $businessScope = (string)($company[$ufMap['company_business_scope']] ?? '');
        $legalAddress = (string)($company[$ufMap['company_legal_address']] ?? '');
        $siteElementId = trim((string)($arFields[$ufMap['company_site_element_id']] ?? $company[$ufMap['company_site_element_id']] ?? ''));

        return [
            'title' => $title,
            'inn' => $inn,
            'city' => $city,
            'web' => $web,
            'business_scope' => $businessScope,
            'legal_address' => $legalAddress,
            'site_element_id' => $siteElementId,
        ];
    }
}
