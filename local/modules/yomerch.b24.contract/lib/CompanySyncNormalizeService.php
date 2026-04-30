<?php

namespace OnlineService\Sync\Contract;

class CompanySyncNormalizeService
{
    public function normalizeForOutbound(array $company, array $arFields, array $ufMap): array
    {
        $title = (string)($arFields['TITLE'] ?? $company['TITLE'] ?? '');
        $inn = (string)($company['REQUISITES']['RQ_INN'] ?? '');
        $cityKey = (string)($ufMap['company_city'] ?? '');
        $city = $cityKey !== '' ? self::scalarStringFromCompanyOrArFields($arFields, $company, $cityKey) : '';
        // Сайт: OS_COMPANY_WEB_SITE — из мультполя CRM WEB (как телефон/почта); при пустом — UF `company.web`.
        $webMulti = \trim((string)($company['MULTIFIELDS']['WEB']['VALUE'] ?? ''));
        $webKey = (string)($ufMap['company_web'] ?? '');
        $webUf = $webKey !== '' ? self::scalarStringFromCompanyOrArFields($arFields, $company, $webKey) : '';
        $web = $webMulti !== '' ? $webMulti : $webUf;
        $businessScope = (string)($company[$ufMap['company_business_scope']] ?? '');
        $legalAddress = (string)($company[$ufMap['company_legal_address']] ?? '');
        $canonicalUf = (string)($ufMap['company_site_element_id'] ?? '');
        $siteElementId = trim((string)($arFields[$canonicalUf] ?? $company[$canonicalUf] ?? ''));
        if ($siteElementId === '' && !empty($ufMap['company_site_element_id_legacy'])) {
            $legacyUf = (string)$ufMap['company_site_element_id_legacy'];
            $siteElementId = trim((string)($arFields[$legacyUf] ?? $company[$legacyUf] ?? ''));
        }

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

    /**
     * @param array<string, mixed> $arFields
     * @param array<string, mixed> $company
     */
    private static function scalarStringFromCompanyOrArFields(array $arFields, array $company, string $key): string
    {
        $raw = \array_key_exists($key, $arFields) ? $arFields[$key] : ($company[$key] ?? null);
        if (\is_array($raw)) {
            if (\array_key_exists('VALUE', $raw)) {
                $raw = $raw['VALUE'];
            } else {
                $first = \reset($raw);
                if (\is_array($first) && \array_key_exists('VALUE', $first)) {
                    $raw = $first['VALUE'];
                }
            }
        }

        return \trim((string) ($raw ?? ''));
    }
}
