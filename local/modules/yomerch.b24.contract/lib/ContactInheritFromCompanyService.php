<?php

declare(strict_types=1);

namespace OnlineService\Sync\Contract;

use OnlineService\Sync\ToSite\OutboundContactMarketingForSite;
use OnlineService\Sync\UfMap;

/**
 * Наследование полей компании на контакт (registration + existing INN, crm.contact.company.add).
 * Логика согласована с {@see \OnlineService\Sync\ToSite\CompanySync::onAfterCompanyUpdate}.
 */
final class ContactInheritFromCompanyService
{
    private CompanySyncReadService $companyReader;

    public function __construct(?CompanySyncReadService $companyReader = null)
    {
        $this->companyReader = $companyReader ?? new CompanySyncReadService();
    }

    /**
     * @return array<string, mixed> поля для CCrmContact::Update (ключи CRM)
     */
    public function buildInheritFieldsFromCompany(int $companyId): array
    {
        if ($companyId <= 0 || !\Bitrix\Main\Loader::includeModule('crm')) {
            return [];
        }

        $company = $this->companyReader->loadCompanySnapshot($companyId);
        if ($company === []) {
            return [];
        }

        $ufCompanyIsMarketingAgent = UfMap::get('company.is_marketing_agent');
        $ufCompanySiteSync = UfMap::get('company.site_sync_value');
        $ufContactSiteSync = UfMap::get('contact.site_sync_value');
        $ufContactMarketingAgent = UfMap::get('company.contact_marketing_agent');
        $ufInheritsMarketingAgent = UfMap::get('contact.inherits_company_is_marketing_agent');

        $isMarketingAgentRaw = self::extractCompanyUfScalar($company, $ufCompanyIsMarketingAgent);
        $isMarketingAgent = OutboundContactMarketingForSite::isMarketingAgentTruthy($isMarketingAgentRaw);
        $contactMarketingAgentForCrm = self::normalizeCompanyUfMirrorForContactUpdate($isMarketingAgentRaw);
        $companySiteSyncForContacts = self::normalizeCompanyUfMirrorForContactUpdate(
            self::extractCompanyUfScalar($company, $ufCompanySiteSync)
        );
        // На портале второй менеджер компании может храниться в UF контакта (UF_CRM_1757682312), не только в company.site_sync_value.
        if ($companySiteSyncForContacts === '' || $companySiteSyncForContacts === null) {
            $companySiteSyncForContacts = self::normalizeCompanyUfMirrorForContactUpdate(
                self::extractCompanyUfScalar($company, $ufContactSiteSync)
            );
        }
        $companyAssignedForContacts = (int)($company['ASSIGNED_BY_ID'] ?? 0);

        return [
            'ASSIGNED_BY_ID' => $companyAssignedForContacts,
            $ufContactSiteSync => $companySiteSyncForContacts,
            $ufContactMarketingAgent => $contactMarketingAgentForCrm,
            $ufInheritsMarketingAgent => $isMarketingAgent ? 'Y' : 'N',
            'ACTIVE' => $isMarketingAgent ? 'Y' : 'N',
        ];
    }

    /**
     * @param array<string, mixed> $fields
     * @return array{
     *     copied_fields: list<string>,
     *     skipped_fields: list<string>,
     *     update_ok: bool,
     *     last_error: string
     * }
     */
    public function applyToContact(int $contactId, array $fields, bool $onlyIfEmpty = true): array
    {
        $copiedFields = [];
        $skippedFields = [];
        $emptyResult = [
            'copied_fields' => $copiedFields,
            'skipped_fields' => $skippedFields,
            'update_ok' => false,
            'last_error' => '',
        ];

        if ($contactId <= 0 || $fields === [] || !\Bitrix\Main\Loader::includeModule('crm')) {
            return $emptyResult;
        }

        $contact = \CCrmContact::GetByID($contactId, false);
        if (!\is_array($contact) || $contact === []) {
            $emptyResult['last_error'] = 'contact_not_found';

            return $emptyResult;
        }

        global $USER_FIELD_MANAGER;
        if (\is_object($USER_FIELD_MANAGER)) {
            $ufRows = $USER_FIELD_MANAGER->GetUserFields('CRM_CONTACT', $contactId, LANGUAGE_ID);
            foreach ($ufRows as $fieldName => $fieldMeta) {
                if (!\is_string($fieldName) || \strncmp($fieldName, 'UF_', 3) !== 0) {
                    continue;
                }
                if (!\array_key_exists($fieldName, $contact) || $contact[$fieldName] === null || $contact[$fieldName] === '') {
                    $contact[$fieldName] = $fieldMeta['VALUE'] ?? null;
                }
            }
        }

        $updateFields = [];
        foreach ($fields as $key => $value) {
            if (!\is_string($key) || $key === '') {
                continue;
            }
            if ($onlyIfEmpty && self::contactFieldHasValue($contact, $key)) {
                $skippedFields[] = $key;
                continue;
            }
            $updateFields[$key] = $value;
            $copiedFields[] = $key;
        }

        if ($updateFields === []) {
            return [
                'copied_fields' => $copiedFields,
                'skipped_fields' => $skippedFields,
                'update_ok' => false,
                'last_error' => $skippedFields !== [] ? 'all_fields_skipped_only_if_empty' : 'no_fields_to_update',
            ];
        }

        $entity = new \CCrmContact(false);
        $ok = (bool)$entity->Update(
            $contactId,
            $updateFields,
            true,
            true,
            [
                'CURRENT_USER' => 1,
                'IS_SYSTEM_ACTION' => true,
                'ENABLE_DUP_INDEX_INVALIDATION' => true,
                'REGISTER_SONET_EVENT' => false,
                'DISABLE_USER_FIELD_CHECK' => true,
                'DISABLE_REQUIRED_USER_FIELD_CHECK' => true,
            ]
        );

        if (!$ok) {
            return [
                'copied_fields' => [],
                'skipped_fields' => $skippedFields,
                'update_ok' => false,
                'last_error' => (string)$entity->LAST_ERROR,
            ];
        }

        return [
            'copied_fields' => $copiedFields,
            'skipped_fields' => $skippedFields,
            'update_ok' => true,
            'last_error' => '',
        ];
    }

    /**
     * @param array<string, mixed> $company
     * @return mixed
     */
    private static function extractCompanyUfScalar(array $company, string $ufKey)
    {
        $raw = $company[$ufKey] ?? null;
        if (\is_array($raw)) {
            if (\array_key_exists('VALUE', $raw)) {
                return $raw['VALUE'];
            }
            $first = \reset($raw);
            if (\is_array($first) && \array_key_exists('VALUE', $first)) {
                return $first['VALUE'];
            }
        }

        return $raw;
    }

    /**
     * @param mixed $v
     * @return mixed
     */
    private static function normalizeCompanyUfMirrorForContactUpdate($v)
    {
        if ($v === null || $v === false) {
            return '';
        }
        if (!\is_array($v)) {
            return $v;
        }
        if (\array_key_exists('VALUE', $v)) {
            return self::normalizeCompanyUfMirrorForContactUpdate($v['VALUE']);
        }
        if ($v !== [] && \array_keys($v) === \range(0, \count($v) - 1)) {
            return self::normalizeCompanyUfMirrorForContactUpdate($v[0] ?? '');
        }
        $first = \reset($v);

        return self::normalizeCompanyUfMirrorForContactUpdate($first !== false ? $first : '');
    }

    /**
     * @param array<string, mixed> $contact
     */
    private static function contactFieldHasValue(array $contact, string $key): bool
    {
        if (!\array_key_exists($key, $contact)) {
            return false;
        }
        $value = $contact[$key];
        if ($value === null || $value === false || $value === '') {
            return false;
        }
        if (\is_array($value) && $value === []) {
            return false;
        }
        if (\is_int($value) && $value === 0 && $key === 'ASSIGNED_BY_ID') {
            return false;
        }

        return true;
    }
}
