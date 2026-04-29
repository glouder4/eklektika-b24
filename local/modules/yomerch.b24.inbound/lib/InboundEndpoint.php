<?php

namespace OnlineService\Sync\FromSite;

use OnlineService\Sync\UfMap;
use OnlineService\Sync\ToSite\CompanySync;

/**
 * Inbound channel (site -> CRM).
 * Основная логика входящего канала — в модуле **yomerch.b24.inbound**; legacy URL проксируют в `site_requests_handler.php`.
 */
class InboundEndpoint
{
    private static function uf(string $key): string
    {
        return UfMap::get($key);
    }

    public static function processRequest(array $request): array
    {
        $request = InboundPayloadNormalizer::normalizeRequestPayload($request);
        if (!empty($request['_INVALID_PAYLOAD'])) {
            return [
                'success' => 0,
                'error' => (string)($request['_INVALID_PAYLOAD_REASON'] ?? 'Invalid payload'),
                'error_code' => 'invalid_payload',
                'http_status' => 400,
            ];
        }
        $dispatcher = new InboundActionDispatcher([
            'UPDATE_GROUP' => static function (array $r): array { return self::handleUpdateGroup($r); },
            'GET_CONTACT_ID' => static function (array $r): array { return self::handleGetContactId($r); },
            'UPDATE_COMPANY' => static function (array $r): array { return self::handleUpdateCompany($r); },
            'CRM_METHOD' => static function (array $r): array { return self::handleCrmMethod($r); },
        ]);

        return $dispatcher->dispatch((string)($request['ACTION'] ?? ''), $request);
    }

    private static function handleGetContactId(array $request): array
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return [
                'success' => 0,
                'error' => 'CRM module is not available',
            ];
        }

        $email = trim((string)($request['EMAIL'] ?? ''));
        $phone = trim((string)($request['PHONE'] ?? ''));
        $contactId = 0;

        if ($email !== '') {
            $emailIds = self::findContactIdsByEmail($email);
            if (count($emailIds) === 1) {
                $contactId = (int)$emailIds[0];
            } elseif (count($emailIds) > 1 && $phone !== '') {
                $phoneDigits = self::normalizePhoneDigits($phone);
                foreach ($emailIds as $candidateId) {
                    if (self::contactHasPhoneDigits((int)$candidateId, $phoneDigits)) {
                        $contactId = (int)$candidateId;
                        break;
                    }
                }
            }
        }
        if ($contactId <= 0 && $phone !== '') {
            $phoneIds = self::findContactIdsByPhone($phone);
            if (count($phoneIds) === 1) {
                $contactId = (int)$phoneIds[0];
            }
        }

        if ($contactId <= 0) {
            return [
                'success' => 0,
                'error' => 'Contact not found',
                'data' => [],
            ];
        }

        return [
            'success' => 1,
            'data' => [
                'ID' => $contactId,
            ],
        ];
    }

    private static function handleUpdateGroup(array $request): array
    {
        if (!\Bitrix\Main\Loader::includeModule('main')) {
            return [
                'success' => 0,
                'error' => 'Main module is not available',
            ];
        }

        $groupId = (int)($request['ID'] ?? 0);
        if ($groupId <= 0) {
            return [
                'success' => 0,
                'error' => 'ID is required',
            ];
        }

        $activeRaw = strtoupper(trim((string)($request['ACTIVE'] ?? '')));
        if ($activeRaw !== 'Y' && $activeRaw !== 'N') {
            return [
                'success' => 0,
                'error' => 'ACTIVE must be Y or N',
            ];
        }

        $name = trim((string)($request['NAME'] ?? ''));
        $sort = (int)($request['C_SORT'] ?? $request['SORT'] ?? 100);
        if ($sort <= 0) {
            $sort = 100;
        }

        $fields = [
            'ACTIVE' => $activeRaw,
            'SORT' => $sort,
        ];
        if ($name !== '') {
            $fields['NAME'] = $name;
        }

        $group = new \CGroup();
        $updated = (bool)$group->Update($groupId, $fields);
        if (!$updated) {
            return [
                'success' => 0,
                'error' => (string)$group->LAST_ERROR,
            ];
        }

        return [
            'success' => 1,
            // Legacy integrations expected scalar group id; keep it in result.
            'result' => (string)$groupId,
            'data' => [
                'ID' => $groupId,
            ],
        ];
    }

    private static function handleUpdateCompany(array $request): array
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return [
                'success' => 0,
                'error' => 'CRM module is not available',
            ];
        }

        $ufCompanySiteElementId = self::uf('company.site_element_id');
        $ufCompanyCity = self::uf('company.city');
        $siteElementId = (string)($request[$ufCompanySiteElementId] ?? $request['SITE_ELEMENT_ID'] ?? '');
        $siteElementId = trim($siteElementId);
        if ($siteElementId === '') {
            return [
                'success' => 0,
                'error' => $ufCompanySiteElementId . ' is required',
            ];
        }

        $matchedCompanyIds = self::findCompanyIdsBySiteElementId($siteElementId);
        if (count($matchedCompanyIds) !== 1) {
            return [
                'success' => 0,
                'error' => 'Company must be matched by ' . $ufCompanySiteElementId . ' uniquely',
                'matched_ids' => $matchedCompanyIds,
            ];
        }

        $companyId = (int)$matchedCompanyIds[0];
        $fields = [];
        if (array_key_exists('LEGAN_ENTITY_NAME', $request)) {
            $fields['TITLE'] = (string)$request['LEGAN_ENTITY_NAME'];
        }
        if (array_key_exists('LEGAN_ENTITY_CITY', $request)) {
            $fields[$ufCompanyCity] = (string)$request['LEGAN_ENTITY_CITY'];
        }
        if (array_key_exists($ufCompanySiteElementId, $request)) {
            $fields[$ufCompanySiteElementId] = $siteElementId;
        }

        if (array_key_exists('LEGAN_ENTITY_PHONE', $request) && trim((string)$request['LEGAN_ENTITY_PHONE']) !== '') {
            $fields['PHONE'] = [[
                'VALUE' => (string)$request['LEGAN_ENTITY_PHONE'],
                'VALUE_TYPE' => 'WORK',
            ]];
        }
        if (array_key_exists('LEGAN_ENTITY_EMAIL', $request) && trim((string)$request['LEGAN_ENTITY_EMAIL']) !== '') {
            $fields['EMAIL'] = [[
                'VALUE' => (string)$request['LEGAN_ENTITY_EMAIL'],
                'VALUE_TYPE' => 'WORK',
            ]];
        }
        if (array_key_exists('LEGAN_ENTITY_WWW', $request) && trim((string)$request['LEGAN_ENTITY_WWW']) !== '') {
            $fields['WEB'] = [[
                'VALUE' => (string)$request['LEGAN_ENTITY_WWW'],
                'VALUE_TYPE' => 'WORK',
            ]];
        }

        $result = true;
        if (!empty($fields)) {
            CompanySync::markInboundCompanyUpdate($companyId);
            $entity = new \CCrmCompany(false);
            $result = (bool)$entity->Update($companyId, $fields, true, true, [
                'CURRENT_USER' => 1,
                'IS_SYSTEM_ACTION' => true,
                'DISABLE_USER_FIELD_CHECK' => false,
            ]);
        }

        return [
            'success' => $result ? 1 : 0,
            'data' => [
                'ID' => $companyId,
                'matched_by' => $ufCompanySiteElementId,
            ],
        ];
    }

    private static function handleCrmMethod(array $request): array
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return [
                'success' => 0,
                'error' => 'CRM module is not available',
            ];
        }

        $method = trim((string)($request['METHOD'] ?? ''));
        $params = self::decodeIncomingParams($request['PARAMS'] ?? null);
        if ($method === '') {
            return [
                'success' => 0,
                'error' => 'Invalid payload: METHOD is required',
                'error_code' => 'invalid_payload',
                'http_status' => 400,
            ];
        }

        try {
            switch ($method) {
                case 'crm.requisite.list':
                    return self::crmRequisiteList($params);
                case 'crm.company.add':
                    return self::crmCompanyAdd($params);
                case 'crm.company.get':
                    return self::crmCompanyGet($params);
                case 'crm.company.update':
                    return self::crmCompanyUpdate($params);
                case 'crm.requisite.add':
                    return self::crmRequisiteAdd($params);
                case 'crm.requisite.update':
                    return self::crmRequisiteUpdate($params);
                case 'crm.contact.add':
                    return self::crmContactAdd($params);
                case 'crm.contact.update':
                    return self::crmContactUpdate($params);
                case 'crm.contact.company.add':
                    return self::crmContactCompanyAdd($params);
                default:
                    return [
                        'success' => 0,
                        'error' => 'Unsupported CRM METHOD: ' . $method,
                    ];
            }
        } catch (\Throwable $e) {
            return [
                'success' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private static function decodeIncomingParams($raw): array
    {
        if (\is_array($raw)) {
            return $raw;
        }
        if (\is_string($raw) && $raw !== '') {
            $d = \json_decode($raw, true);
            if (\is_array($d)) {
                return $d;
            }
        }

        return [];
    }

    /**
     * CCrmContact::Update: мультиполя (PHONE, EMAIL, …) — в формате n0, n1, ….
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function normalizeCrmContactFieldsForUpdate(array $fields): array
    {
        foreach (['PHONE', 'EMAIL', 'WEB', 'IM'] as $code) {
            if (!isset($fields[$code]) || !\is_array($fields[$code])) {
                continue;
            }
            $list = $fields[$code];
            if ($list === []) {
                continue;
            }
            $isSeq = false;
            if (\function_exists('array_is_list')) {
                $isSeq = \array_is_list($list);
            } else {
                $k = \array_keys($list);
                $isSeq = $k !== [] && $k === \range(0, \count($list) - 1);
            }
            if (!$isSeq) {
                continue;
            }
            $out = [];
            $j = 0;
            foreach ($list as $row) {
                if (!\is_array($row)) {
                    continue;
                }
                $val = (string)($row['VALUE'] ?? $row['value'] ?? '');
                $type = (string)($row['VALUE_TYPE'] ?? $row['value_type'] ?? 'WORK');
                if ($val === '' && $type === '') {
                    continue;
                }
                $out['n' . $j] = ['VALUE' => $val, 'VALUE_TYPE' => $type];
                $j++;
            }
            if ($out !== []) {
                $fields[$code] = $out;
            }
        }

        return $fields;
    }

    private static function crmRequisiteList(array $params): array
    {
        $filter = is_array($params['filter'] ?? null) ? $params['filter'] : [];
        // Для поиска по ИНН по умолчанию ограничиваем компанию (ENTITY_TYPE_ID=4),
        // чтобы не возвращать реквизиты других сущностей.
        if (array_key_exists('RQ_INN', $filter) && !array_key_exists('ENTITY_TYPE_ID', $filter)) {
            $filter['ENTITY_TYPE_ID'] = 4;
        }
        $select = is_array($params['select'] ?? null) && $params['select'] !== [] ? $params['select'] : ['*'];
        $result = \Bitrix\Crm\RequisiteTable::getList([
            'filter' => $filter,
            'select' => $select,
        ])->fetchAll();
        return ['success' => 1, 'result' => $result];
    }

    private static function crmCompanyAdd(array $params): array
    {
        $fields = is_array($params['fields'] ?? null) ? $params['fields'] : [];
        $entity = new \CCrmCompany(false);
        $id = (int)$entity->Add($fields, true, ['CURRENT_USER' => 1, 'IS_SYSTEM_ACTION' => true]);
        if ($id <= 0) {
            return ['success' => 0, 'error' => (string)$entity->LAST_ERROR];
        }

        return ['success' => 1, 'result' => $id];
    }

    private static function crmCompanyGet(array $params): array
    {
        $id = (int)($params['id'] ?? 0);
        $row = $id > 0 ? \CCrmCompany::GetByID($id, false) : false;
        if (!$row) {
            return ['success' => 0, 'error' => 'Company not found'];
        }

        return ['success' => 1, 'result' => $row];
    }

    private static function crmCompanyUpdate(array $params): array
    {
        $id = (int)($params['id'] ?? 0);
        $fields = is_array($params['fields'] ?? null) ? $params['fields'] : [];
        if ($id <= 0) {
            return ['success' => 0, 'error' => 'Company id is required'];
        }

        $entity = new \CCrmCompany(false);
        $ok = (bool)$entity->Update($id, $fields, true, true, ['CURRENT_USER' => 1, 'IS_SYSTEM_ACTION' => true]);

        return $ok ? ['success' => 1, 'result' => true] : ['success' => 0, 'error' => (string)$entity->LAST_ERROR];
    }

    private static function crmRequisiteAdd(array $params): array
    {
        $fields = is_array($params['fields'] ?? null) ? $params['fields'] : [];
        if (class_exists('\CCrmRequisite')) {
            $entity = new \CCrmRequisite();
            $id = (int)$entity->Add($fields);
            if ($id <= 0) {
                return ['success' => 0, 'error' => (string)$entity->LAST_ERROR];
            }
            return ['success' => 1, 'result' => $id];
        }
        if (class_exists('\Bitrix\Crm\EntityRequisite')) {
            $entity = new \Bitrix\Crm\EntityRequisite();
            $rawAddResult = $entity->add($fields);
            $id = is_object($rawAddResult) && method_exists($rawAddResult, 'getId')
                ? (int)$rawAddResult->getId()
                : (int)$rawAddResult;
            if ($id <= 0) {
                return ['success' => 0, 'error' => 'EntityRequisite add failed'];
            }
            return ['success' => 1, 'result' => $id];
        }
        if (class_exists('\Bitrix\Crm\RequisiteTable')) {
            $addResult = \Bitrix\Crm\RequisiteTable::add($fields);
            $id = method_exists($addResult, 'getId') ? (int)$addResult->getId() : 0;
            if ($id > 0) {
                return ['success' => 1, 'result' => $id];
            }
            return ['success' => 0, 'error' => 'RequisiteTable add failed'];
        }
        return ['success' => 0, 'error' => 'CCrmRequisite/EntityRequisite/RequisiteTable class not found'];
    }

    private static function crmRequisiteUpdate(array $params): array
    {
        $id = (int)($params['id'] ?? 0);
        $fields = is_array($params['fields'] ?? null) ? $params['fields'] : [];
        if ($id <= 0) {
            return ['success' => 0, 'error' => 'Requisite id is required'];
        }
        if (class_exists('\CCrmRequisite')) {
            $entity = new \CCrmRequisite();
            $ok = (bool)$entity->Update($id, $fields);
            return $ok ? ['success' => 1, 'result' => true] : ['success' => 0, 'error' => (string)$entity->LAST_ERROR];
        }
        if (class_exists('\Bitrix\Crm\EntityRequisite')) {
            $entity = new \Bitrix\Crm\EntityRequisite();
            $ok = (bool)$entity->update($id, $fields);
            return $ok ? ['success' => 1, 'result' => true] : ['success' => 0, 'error' => 'EntityRequisite update failed'];
        }
        if (class_exists('\Bitrix\Crm\RequisiteTable')) {
            $updateResult = \Bitrix\Crm\RequisiteTable::update($id, $fields);
            $ok = method_exists($updateResult, 'isSuccess') ? (bool)$updateResult->isSuccess() : false;
            return $ok ? ['success' => 1, 'result' => true] : ['success' => 0, 'error' => 'RequisiteTable update failed'];
        }
        return ['success' => 0, 'error' => 'CCrmRequisite/EntityRequisite/RequisiteTable class not found'];
    }

    private static function crmContactAdd(array $params): array
    {
        $fields = is_array($params['fields'] ?? null) ? $params['fields'] : [];
        $entity = new \CCrmContact(false);
        if (class_exists(\OnlineService\Sync\ToSite\ContactSync::class)) {
            \OnlineService\Sync\ToSite\ContactSync::suspendOutbound(true);
        }
        try {
            $id = (int)$entity->Add($fields, true, ['CURRENT_USER' => 1, 'IS_SYSTEM_ACTION' => true]);
        } finally {
            if (class_exists(\OnlineService\Sync\ToSite\ContactSync::class)) {
                \OnlineService\Sync\ToSite\ContactSync::suspendOutbound(false);
            }
        }
        if ($id <= 0) {
            return ['success' => 0, 'error' => (string)$entity->LAST_ERROR];
        }

        return ['success' => 1, 'result' => $id];
    }

    private static function crmContactUpdate(array $params): array
    {
        $id = (int)($params['id'] ?? 0);
        $fields = is_array($params['fields'] ?? null) ? $params['fields'] : [];
        if ($id <= 0) {
            return ['success' => 0, 'error' => 'Contact id is required'];
        }
        if ($fields === []) {
            return ['success' => 0, 'error' => 'fields is required'];
        }
        $fields = self::normalizeCrmContactFieldsForUpdate($fields);
        if (class_exists(\OnlineService\Sync\ToSite\ContactSync::class)) {
            \OnlineService\Sync\ToSite\ContactSync::suspendOutbound(true);
        }
        $entity = new \CCrmContact(false);
        try {
            $ok = (bool) $entity->Update(
                $id,
                $fields,
                true,
                true,
                [
                    'CURRENT_USER' => 1,
                    'IS_SYSTEM_ACTION' => true,
                ],
            );
        } finally {
            if (class_exists(\OnlineService\Sync\ToSite\ContactSync::class)) {
                \OnlineService\Sync\ToSite\ContactSync::suspendOutbound(false);
            }
        }

        return $ok
            ? ['success' => 1, 'result' => true]
            : ['success' => 0, 'error' => (string) $entity->LAST_ERROR];
    }

    private static function crmContactCompanyAdd(array $params): array
    {
        $contactId = (int)($params['id'] ?? 0);
        $companyId = (int)($params['fields']['COMPANY_ID'] ?? 0);
        if ($contactId <= 0 || $companyId <= 0) {
            return ['success' => 0, 'error' => 'Contact id and fields.COMPANY_ID are required'];
        }

        $entity = new \CCrmContact(false);
        $updateFields = ['COMPANY_ID' => $companyId];
        try {
            $ok = (bool)$entity->Update($contactId, $updateFields, true, true, [
                'CURRENT_USER' => 1,
                'IS_SYSTEM_ACTION' => true,
            ]);
        } catch (\Throwable $e) {
            $ok = (bool)$entity->Update($contactId, $updateFields);
        }

        return $ok ? ['success' => 1, 'result' => true] : ['success' => 0, 'error' => (string)$entity->LAST_ERROR];
    }

    private static function findCompanyIdsBySiteElementId(string $siteElementId): array
    {
        $res = \CCrmCompany::GetListEx(
            ['ID' => 'ASC'],
            [
                '=' . self::uf('company.site_element_id') => $siteElementId,
                'CHECK_PERMISSIONS' => 'N',
            ],
            false,
            false,
            ['ID', self::uf('company.site_element_id')]
        );

        $ids = [];
        while ($row = $res->Fetch()) {
            if ((string)($row[self::uf('company.site_element_id')] ?? '') !== $siteElementId) {
                continue;
            }
            $id = (int)($row['ID'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private static function findContactIdsByEmail(string $email): array
    {
        $res = \CCrmContact::GetListEx(
            ['ID' => 'ASC'],
            ['=EMAIL' => $email, 'CHECK_PERMISSIONS' => 'N'],
            false,
            false,
            ['ID', 'EMAIL']
        );
        $ids = [];
        while ($row = $res->Fetch()) {
            $id = (int)($row['ID'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private static function findContactIdsByPhone(string $phone): array
    {
        $ids = [];
        $variants = self::phoneVariants($phone);
        foreach ($variants as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $res = \CCrmContact::GetListEx(
                ['ID' => 'ASC'],
                ['=PHONE' => $candidate, 'CHECK_PERMISSIONS' => 'N'],
                false,
                false,
                ['ID', 'PHONE']
            );
            while ($row = $res->Fetch()) {
                $id = (int)($row['ID'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Разные форматы номера для поиска по PHONE в CRM.
     */
    private static function phoneVariants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return [];
        }

        $variants = [$phone, $digits];
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $variants[] = '7' . substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            $variants[] = '7' . $digits;
        }

        $normalized = [];
        foreach ($variants as $v) {
            $v = trim((string)$v);
            if ($v === '') {
                continue;
            }
            $normalized[] = $v;
            $normalized[] = '+' . ltrim($v, '+');
        }

        return array_values(array_unique($normalized));
    }

    private static function normalizePhoneDigits(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }
        if (strlen($digits) >= 10) {
            return substr($digits, -10);
        }
        return $digits;
    }

    private static function contactHasPhoneDigits(int $contactId, string $phoneDigits): bool
    {
        if ($contactId <= 0 || $phoneDigits === '') {
            return false;
        }

        $mf = \CCrmFieldMulti::GetListEx(
            [],
            [
                'ENTITY_ID' => 'CONTACT',
                'ELEMENT_ID' => $contactId,
                'TYPE_ID' => 'PHONE',
            ],
            false,
            false,
            ['VALUE']
        );
        while ($row = $mf->Fetch()) {
            $valueDigits = self::normalizePhoneDigits((string)($row['VALUE'] ?? ''));
            if ($valueDigits !== '' && $valueDigits === $phoneDigits) {
                return true;
            }
        }

        return false;
    }
}
