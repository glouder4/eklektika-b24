<?php

namespace OnlineService\Sync\FromSite;

use OnlineService\Sync\UfMap;
use OnlineService\Sync\Contract\ContactInheritFromCompanyService;
use OnlineService\Sync\ToSite\CompanySync;
use OnlineService\Sync\ToSite\ContactSync;

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
                'reason_code' => 'invalid_payload',
                'http_status' => 400,
            ];
        }
        $dispatcher = new InboundActionDispatcher([
            'UPDATE_GROUP' => static function (array $r): array { return self::handleUpdateGroup($r); },
            'GET_CONTACT_ID' => static function (array $r): array { return self::handleGetContactId($r); },
            'UPDATE_COMPANY' => static function (array $r): array { return self::handleUpdateCompany($r); },
            'CRM_METHOD' => static function (array $r): array { return self::handleCrmMethod($r); },
            'DELETE_CONTACT' => static function (array $r): array { return self::handleDeleteContactLegacy($r); },
        ]);

        return $dispatcher->dispatch((string)($request['ACTION'] ?? ''), $request);
    }

    /**
     * Делегирование в {@see \OnlineService\LocalApplicationHandler} (контракт R12).
     */
    private static function handleDeleteContactLegacy(array $request): array
    {
        if (!\class_exists(\OnlineService\LocalApplicationHandler::class)) {
            return [
                'success' => 0,
                'error' => 'DELETE_CONTACT handler unavailable',
                'reason_code' => 'delete_contact_handler_missing',
            ];
        }
        $handler = new \OnlineService\LocalApplicationHandler($request);
        $raw = $handler->getResponse();
        $ok = isset($raw['success']) && $raw['success'] === true;

        return [
            'success' => $ok ? 1 : 0,
            'error' => $ok ? '' : (string)($raw['error'] ?? $raw['data'] ?? 'delete_contact_failed'),
            'data' => \is_array($raw['debug'] ?? null) ? $raw['debug'] : [],
            'reason_code' => $ok ? '' : 'delete_contact_failed',
        ];
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
        if ($email === '') {
            return [
                'success' => 0,
                'error' => 'EMAIL is required',
                'reason_code' => 'invalid_payload',
                'http_status' => 400,
                'data' => [],
            ];
        }

        // Registration uniqueness key: email only. PHONE in request is ignored for lookup.
        $emailIds = self::findContactIdsByEmail($email);
        if (\count($emailIds) !== 1) {
            return [
                'success' => 0,
                'error' => 'Contact not found',
                'reason_code' => \count($emailIds) > 1 ? 'contact_lookup_ambiguous' : 'contact_not_found',
                'data' => [],
            ];
        }

        $contactId = (int)$emailIds[0];

        return [
            'success' => 1,
            'data' => self::buildGetContactIdResponseData($contactId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildGetContactIdResponseData(int $contactId): array
    {
        $data = ['ID' => $contactId];
        if ($contactId <= 0) {
            return $data;
        }

        $ufSiteRef = self::uf('contact.delete_site_ref');
        $contact = \CCrmContact::GetByID($contactId, false);
        if (\is_array($contact)) {
            $siteRef = $contact[$ufSiteRef] ?? null;
            if ($siteRef !== null && $siteRef !== '' && $siteRef !== false) {
                $data[$ufSiteRef] = $siteRef;
            }
        }

        $email = self::getFirstContactMultifieldValue($contactId, 'EMAIL');
        if ($email !== '') {
            $data['EMAIL'] = $email;
        }
        $phone = self::getFirstContactMultifieldValue($contactId, 'PHONE');
        if ($phone !== '') {
            $data['PHONE'] = $phone;
        }

        return $data;
    }

    private static function getFirstContactMultifieldValue(int $contactId, string $typeId): string
    {
        if ($contactId <= 0 || $typeId === '') {
            return '';
        }

        $res = \CCrmFieldMulti::GetListEx(
            ['ID' => 'ASC'],
            [
                'ENTITY_ID' => 'CONTACT',
                'ELEMENT_ID' => $contactId,
                'TYPE_ID' => $typeId,
            ],
            false,
            ['nTopCount' => 1],
            ['VALUE']
        );
        if (!\is_object($res)) {
            return '';
        }
        $row = $res->Fetch();

        return \is_array($row) ? trim((string)($row['VALUE'] ?? '')) : '';
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
        $ufCompanySiteElementIdLegacy = self::uf('company.site_element_id_legacy_alias');
        $ufCompanyCity = self::uf('company.city');
        $ufCompanySiteSync = self::uf('company.site_sync_value');
        // Контракт сайта: часть полей приходит вложенным объектом PARAMS (JSON), верхний уровень — ACTION, sync_token и т.д.
        $paramsBlock = $request['PARAMS'] ?? null;
        if (\is_array($paramsBlock)) {
            $request = \array_merge($paramsBlock, $request);
        }
        $siteElementId = (string)(
            $request[$ufCompanySiteElementId]
            ?? $request[$ufCompanySiteElementIdLegacy]
            ?? $request['SITE_ELEMENT_ID']
            ?? ''
        );
        $siteElementId = trim($siteElementId);
        if ($siteElementId === '' || $siteElementId === '0') {
            return [
                'success' => 0,
                'error' => $ufCompanySiteElementId . ', '
                    . $ufCompanySiteElementIdLegacy
                    . ' or SITE_ELEMENT_ID must be a non-empty site element id (not 0)',
            ];
        }

        $matchedCompanyIds = self::findCompanyIdsBySiteElementId($siteElementId);
        if (count($matchedCompanyIds) !== 1) {
            return [
                'success' => 0,
                'error' => 'Company must be matched by site_element_id UF (canonical or legacy) uniquely',
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
        if (
            array_key_exists($ufCompanySiteElementId, $request)
            || array_key_exists($ufCompanySiteElementIdLegacy, $request)
        ) {
            $fields[$ufCompanySiteElementId] = $siteElementId;
            $fields[$ufCompanySiteElementIdLegacy] = $siteElementId;
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
        if (array_key_exists('ASSIGNED_BY_ID', $request)) {
            $fields['ASSIGNED_BY_ID'] = (int)$request['ASSIGNED_BY_ID'];
        } elseif (array_key_exists('ASSIGNED_MANAGER', $request)) {
            // Симметрия с исходящим контактом (ASSIGNED_MANAGER на сайте ↔ ASSIGNED_BY_ID в CRM).
            $fields['ASSIGNED_BY_ID'] = (int)$request['ASSIGNED_MANAGER'];
        }
        if (array_key_exists($ufCompanySiteSync, $request)) {
            $fields[$ufCompanySiteSync] = $request[$ufCompanySiteSync];
        } elseif (array_key_exists('SECOND_MANAGER', $request)) {
            $fields[$ufCompanySiteSync] = $request['SECOND_MANAGER'];
        }

        $shouldMirrorManagersToContacts = array_key_exists('ASSIGNED_BY_ID', $request)
            || array_key_exists('ASSIGNED_MANAGER', $request)
            || array_key_exists($ufCompanySiteSync, $request)
            || array_key_exists('SECOND_MANAGER', $request);

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

        if ($result && $shouldMirrorManagersToContacts) {
            [$assignedFromCompany, $companySiteSyncUf] = self::resolveCompanyManagerMirrorForContacts(
                $companyId,
                $ufCompanySiteSync,
                $fields
            );
            $ufContactSiteSync = self::uf('contact.site_sync_value');
            $contactIds = self::loadCompanyContactIds($companyId);
            if ($contactIds !== []) {
                ContactSync::suspendOutbound(true);
                try {
                    $contactEntity = new \CCrmContact(false);
                    foreach ($contactIds as $contactId) {
                        $contactFields = [
                            'ASSIGNED_BY_ID' => $assignedFromCompany,
                            $ufContactSiteSync => $companySiteSyncUf,
                        ];
                        $contactEntity->Update(
                            $contactId,
                            $contactFields,
                            true,
                            true,
                            [
                                'CURRENT_USER' => 1,
                                'IS_SYSTEM_ACTION' => true,
                                'DISABLE_USER_FIELD_CHECK' => true,
                            ],
                        );
                    }
                } finally {
                    ContactSync::suspendOutbound(false);
                }
                foreach ($contactIds as $contactId) {
                    ContactSync::sendContactToSiteNow($contactId);
                }
            }
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
                'reason_code' => 'crm_module_unavailable',
            ];
        }

        $method = trim((string)($request['METHOD'] ?? ''));
        $params = self::decodeIncomingParams($request['PARAMS'] ?? null);
        if ($method === '') {
            return [
                'success' => 0,
                'error' => 'Invalid payload: METHOD is required',
                'error_code' => 'invalid_payload',
                'reason_code' => 'invalid_payload',
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
                        'reason_code' => 'unsupported_crm_method',
                    ];
            }
        } catch (\Throwable $e) {
            return [
                'success' => 0,
                'error' => $e->getMessage(),
                'reason_code' => 'crm_method_exception',
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
        // `CCrmCompany::Add` is a low-level CRM API; for REST-like inbound payloads normalize multifields to `FM`.
        $fields = self::normalizeCrmMultiFieldsRestToFm($fields);
        $siteElementValidation = self::validateCompanyAddSiteElementIdInFields($fields);
        if ($siteElementValidation !== null) {
            return $siteElementValidation;
        }
        $innDigits = self::normalizeInnDigits(self::extractInnFromCompanyAddFields($fields));
        if ($innDigits !== '') {
            $existingCompanyIds = self::findCompanyIdsByRequisiteInn($innDigits);
            if (\count($existingCompanyIds) === 1) {
                return self::crmCompanyResolveDuplicateInn($fields, $innDigits, (int)$existingCompanyIds[0]);
            }
            if (\count($existingCompanyIds) > 1) {
                return [
                    'success' => 0,
                    'error' => 'Multiple companies matched INN; resolve manually',
                    'reason_code' => 'company_add_ambiguous_inn',
                    'data' => [
                        'matched_company_ids' => $existingCompanyIds,
                        'rq_inn' => $innDigits,
                    ],
                ];
            }
        }

        $id = 0;
        $entity = new \CCrmCompany(false);
        CompanySync::suspendOutbound(true);
        try {
            $id = (int)$entity->Add($fields, true, ['CURRENT_USER' => 1, 'IS_SYSTEM_ACTION' => true]);
        } finally {
            CompanySync::suspendOutbound(false);
        }
        if ($id <= 0) {
            return [
                'success' => 0,
                'error' => (string)$entity->LAST_ERROR,
                'reason_code' => 'company_add_failed',
            ];
        }

        return ['success' => 1, 'result' => $id];
    }

    private static function crmCompanyGet(array $params): array
    {
        $id = (int)($params['id'] ?? 0);
        $row = $id > 0 ? \CCrmCompany::GetByID($id, false) : false;
        if (!$row) {
            return [
                'success' => 0,
                'error' => 'Company not found',
                'reason_code' => 'company_get_not_found',
            ];
        }

        return ['success' => 1, 'result' => $row];
    }

    private static function crmCompanyUpdate(array $params): array
    {
        $id = (int)($params['id'] ?? 0);
        $fields = is_array($params['fields'] ?? null) ? $params['fields'] : [];
        if ($id <= 0) {
            return [
                'success' => 0,
                'error' => 'Company id is required',
                'reason_code' => 'company_update_id_required',
            ];
        }

        // Inbound company update: suppress one-shot outbound UPDATE_COMPANY (same as handleUpdateCompany).
        CompanySync::markInboundCompanyUpdate($id);

        $entity = new \CCrmCompany(false);
        $ok = (bool)$entity->Update($id, $fields, true, true, ['CURRENT_USER' => 1, 'IS_SYSTEM_ACTION' => true]);

        return $ok
            ? ['success' => 1, 'result' => true]
            : [
                'success' => 0,
                'error' => (string)$entity->LAST_ERROR,
                'reason_code' => 'company_update_failed',
            ];
    }

    private static function crmRequisiteAdd(array $params): array
    {
        $fields = is_array($params['fields'] ?? null) ? $params['fields'] : [];
        if (class_exists('\CCrmRequisite')) {
            $entity = new \CCrmRequisite();
            $id = (int)$entity->Add($fields);
            if ($id <= 0) {
                return [
                    'success' => 0,
                    'error' => (string)$entity->LAST_ERROR,
                    'reason_code' => 'requisite_add_failed',
                ];
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
                return [
                    'success' => 0,
                    'error' => 'EntityRequisite add failed',
                    'reason_code' => 'requisite_add_entity_failed',
                ];
            }
            return ['success' => 1, 'result' => $id];
        }
        if (class_exists('\Bitrix\Crm\RequisiteTable')) {
            $addResult = \Bitrix\Crm\RequisiteTable::add($fields);
            $id = method_exists($addResult, 'getId') ? (int)$addResult->getId() : 0;
            if ($id > 0) {
                return ['success' => 1, 'result' => $id];
            }
            return [
                'success' => 0,
                'error' => 'RequisiteTable add failed',
                'reason_code' => 'requisite_table_add_failed',
            ];
        }
        return [
            'success' => 0,
            'error' => 'CCrmRequisite/EntityRequisite/RequisiteTable class not found',
            'reason_code' => 'requisite_class_not_found',
        ];
    }

    private static function crmRequisiteUpdate(array $params): array
    {
        $id = (int)($params['id'] ?? 0);
        $fields = is_array($params['fields'] ?? null) ? $params['fields'] : [];
        if ($id <= 0) {
            return [
                'success' => 0,
                'error' => 'Requisite id is required',
                'reason_code' => 'requisite_update_id_required',
            ];
        }
        if (class_exists('\CCrmRequisite')) {
            $entity = new \CCrmRequisite();
            $ok = (bool)$entity->Update($id, $fields);
            return $ok
                ? ['success' => 1, 'result' => true]
                : [
                    'success' => 0,
                    'error' => (string)$entity->LAST_ERROR,
                    'reason_code' => 'requisite_update_failed',
                ];
        }
        if (class_exists('\Bitrix\Crm\EntityRequisite')) {
            $entity = new \Bitrix\Crm\EntityRequisite();
            $ok = (bool)$entity->update($id, $fields);
            return $ok
                ? ['success' => 1, 'result' => true]
                : [
                    'success' => 0,
                    'error' => 'EntityRequisite update failed',
                    'reason_code' => 'requisite_update_entity_failed',
                ];
        }
        if (class_exists('\Bitrix\Crm\RequisiteTable')) {
            $updateResult = \Bitrix\Crm\RequisiteTable::update($id, $fields);
            $ok = method_exists($updateResult, 'isSuccess') ? (bool)$updateResult->isSuccess() : false;
            return $ok
                ? ['success' => 1, 'result' => true]
                : [
                    'success' => 0,
                    'error' => 'RequisiteTable update failed',
                    'reason_code' => 'requisite_table_update_failed',
                ];
        }
        return [
            'success' => 0,
            'error' => 'CCrmRequisite/EntityRequisite/RequisiteTable class not found',
            'reason_code' => 'requisite_class_not_found',
        ];
    }

    private static function crmContactAdd(array $params): array
    {
        $fields = is_array($params['fields'] ?? null) ? $params['fields'] : [];
        $fields = self::normalizeCrmMultiFieldsRestToFm($fields);
        $entity = new \CCrmContact(false);
        if (class_exists(\OnlineService\Sync\ToSite\ContactSync::class)) {
            \OnlineService\Sync\ToSite\ContactSync::suspendOutbound(true);
        }
        try {
            $id = (int)$entity->Add($fields, true, [
                'CURRENT_USER' => 1,
                'IS_SYSTEM_ACTION' => true,
                // Registration: email is the only uniqueness key; allow same phone on different contacts.
                'DISABLE_DUPLICATE_CONTROL' => true,
            ]);
        } finally {
            if (class_exists(\OnlineService\Sync\ToSite\ContactSync::class)) {
                \OnlineService\Sync\ToSite\ContactSync::suspendOutbound(false);
            }
        }
        if ($id <= 0) {
            return [
                'success' => 0,
                'error' => (string)$entity->LAST_ERROR,
                'reason_code' => 'contact_add_failed',
            ];
        }

        return ['success' => 1, 'result' => $id];
    }

    /**
     * crm.contact.add via low-level CRM entity expects multifields in `FM`.
     * Inbound contract (REST-like) may provide `PHONE/EMAIL/WEB/IM` as sequential arrays of {VALUE, VALUE_TYPE}.
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function normalizeCrmMultiFieldsRestToFm(array $fields): array
    {
        if (isset($fields['FM']) && \is_array($fields['FM']) && $fields['FM'] !== []) {
            // Caller already provides FM explicitly — do not mutate.
            return $fields;
        }

        $codes = ['PHONE', 'EMAIL', 'WEB', 'IM'];
        $fm = [];

        foreach ($codes as $code) {
            if (!isset($fields[$code]) || !\is_array($fields[$code]) || $fields[$code] === []) {
                continue;
            }

            $raw = $fields[$code];

            $isSeq = false;
            if (\function_exists('array_is_list')) {
                $isSeq = \array_is_list($raw);
            } else {
                $k = \array_keys($raw);
                $isSeq = $k !== [] && $k === \range(0, \count($raw) - 1);
            }

            $rows = [];
            if ($isSeq) {
                $rows = $raw;
            } else {
                // Accept shape like PHONE[n0] = {VALUE, VALUE_TYPE}.
                foreach ($raw as $maybeRow) {
                    $rows[] = $maybeRow;
                }
            }

            $out = [];
            $j = 0;
            foreach ($rows as $row) {
                if (!\is_array($row)) {
                    continue;
                }
                $val = (string)($row['VALUE'] ?? $row['value'] ?? '');
                $type = (string)($row['VALUE_TYPE'] ?? $row['value_type'] ?? 'WORK');
                if ($val === '' && $type === '') {
                    continue;
                }
                $out['n' . $j] = ['VALUE' => $val, 'VALUE_TYPE' => $type !== '' ? $type : 'WORK'];
                $j++;
            }

            if ($out !== []) {
                $fm[$code] = $out;
                // Compatibility: some low-level CRM paths accept multifields as $fields[PHONE][n0]...
                // Keep data in both shapes to avoid silent drops.
                $fields[$code] = $out;
            }
        }

        if ($fm === []) {
            return $fields;
        }

        $fields['FM'] = $fm;

        return $fields;
    }

    private static function crmContactUpdate(array $params): array
    {
        $id = (int)($params['id'] ?? 0);
        $fields = is_array($params['fields'] ?? null) ? $params['fields'] : [];
        $shouldInherit = self::isTruthyInboundFlag($params['inherit_from_company'] ?? null);
        if ($id <= 0) {
            return [
                'success' => 0,
                'error' => 'Contact id is required',
                'reason_code' => 'contact_update_id_required',
            ];
        }
        if ($fields === [] && !$shouldInherit) {
            return [
                'success' => 0,
                'error' => 'fields is required',
                'reason_code' => 'contact_update_fields_required',
            ];
        }

        if ($fields !== []) {
            $fields = self::normalizeCrmContactFieldsForUpdate($fields);
            if (class_exists(ContactSync::class)) {
                ContactSync::suspendOutbound(true);
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
                if (class_exists(ContactSync::class)) {
                    ContactSync::suspendOutbound(false);
                }
            }

            if (!$ok) {
                return [
                    'success' => 0,
                    'error' => (string) $entity->LAST_ERROR,
                    'reason_code' => 'contact_update_failed',
                ];
            }
        }

        if (!$shouldInherit) {
            return ['success' => 1, 'result' => true];
        }

        $companyId = self::resolveContactInheritCompanyId($id, $params, $fields);
        if ($companyId <= 0) {
            return [
                'success' => 0,
                'error' => 'company_id is required for inherit_from_company',
                'reason_code' => 'contact_inherit_company_id_required',
            ];
        }

        $inheritDiag = [
            'company_id' => $companyId,
            'contact_id' => $id,
            'service_available' => \class_exists(ContactInheritFromCompanyService::class),
            'built_field_keys' => [],
            'copied_fields' => [],
            'skipped_fields' => [],
            'update_ok' => false,
            'last_error' => '',
        ];

        if (!$inheritDiag['service_available']) {
            return [
                'success' => 1,
                'result' => true,
                'reason_code' => 'contact_inherit_service_unavailable',
                'data' => [
                    'inherit' => $inheritDiag,
                    'contact_inherited' => false,
                    'update_contact_outbound' => [
                        'success' => false,
                        'reason_code' => 'contact_inherit_skipped_outbound',
                    ],
                ],
            ];
        }

        $inheritEvidence = null;
        if (class_exists(ContactSync::class)) {
            ContactSync::suspendOutbound(true);
        }
        try {
            $inheritService = new ContactInheritFromCompanyService();
            $inheritFields = $inheritService->buildInheritFieldsFromCompany($companyId);
            $inheritDiag['built_field_keys'] = \array_keys($inheritFields);

            if ($inheritFields === []) {
                if (class_exists(ContactSync::class)) {
                    ContactSync::suspendOutbound(false);
                }

                return [
                    'success' => 1,
                    'result' => true,
                    'reason_code' => 'contact_inherit_company_snapshot_empty',
                    'data' => [
                        'inherit' => $inheritDiag,
                        'contact_inherited' => false,
                        'update_contact_outbound' => [
                            'success' => false,
                            'reason_code' => 'contact_inherit_skipped_outbound',
                        ],
                    ],
                ];
            }

            // Registration inherit: перезаписываем CRM-дефолты (ASSIGNED_BY_ID=1 и т.п.), не only-if-empty.
            $inheritResult = $inheritService->applyToContact($id, $inheritFields, false);
            $inheritDiag['copied_fields'] = $inheritResult['copied_fields'] ?? [];
            $inheritDiag['skipped_fields'] = $inheritResult['skipped_fields'] ?? [];
            $inheritDiag['update_ok'] = (bool)($inheritResult['update_ok'] ?? false);
            $inheritDiag['last_error'] = (string)($inheritResult['last_error'] ?? '');

            if ($inheritDiag['copied_fields'] !== [] && $inheritDiag['update_ok']) {
                $inheritEvidence = [
                    'company_id' => $companyId,
                    'contact_id' => $id,
                    'copied_fields' => $inheritDiag['copied_fields'],
                ];
            }
        } finally {
            if (class_exists(ContactSync::class)) {
                ContactSync::suspendOutbound(false);
            }
        }

        if ($inheritEvidence !== null) {
            return [
                'success' => 1,
                'result' => true,
                'reason_code' => 'contact_inherited_from_company',
                'data' => [
                    'evidence' => $inheritEvidence,
                    'inherit' => $inheritDiag,
                    'contact_inherited' => true,
                    'update_contact_outbound' => self::runInheritedContactOutbound($id),
                ],
            ];
        }

        $reasonCode = 'contact_inherit_no_fields_copied';
        if ($inheritDiag['last_error'] !== '' && $inheritDiag['last_error'] !== 'all_fields_skipped_only_if_empty') {
            $reasonCode = 'contact_inherit_update_failed';
        }

        return [
            'success' => 1,
            'result' => true,
            'reason_code' => $reasonCode,
            'data' => [
                'inherit' => $inheritDiag,
                'contact_inherited' => false,
                'update_contact_outbound' => [
                    'success' => false,
                    'reason_code' => 'contact_inherit_skipped_outbound',
                ],
            ],
        ];
    }

    private static function crmContactCompanyAdd(array $params): array
    {
        $contactId = (int)($params['id'] ?? 0);
        $companyId = (int)($params['fields']['COMPANY_ID'] ?? 0);
        if ($contactId <= 0 || $companyId <= 0) {
            return [
                'success' => 0,
                'error' => 'Contact id and fields.COMPANY_ID are required',
                'reason_code' => 'contact_company_add_params_required',
            ];
        }

        $entity = new \CCrmContact(false);
        $updateFields = ['COMPANY_ID' => $companyId];
        if (class_exists(ContactSync::class)) {
            ContactSync::suspendOutbound(true);
        }
        try {
            try {
                $ok = (bool)$entity->Update($contactId, $updateFields, true, true, [
                    'CURRENT_USER' => 1,
                    'IS_SYSTEM_ACTION' => true,
                ]);
            } catch (\Throwable $e) {
                $ok = (bool)$entity->Update($contactId, $updateFields);
            }

            if (!$ok) {
                return [
                    'success' => 0,
                    'error' => (string)$entity->LAST_ERROR,
                    'reason_code' => 'contact_company_add_failed',
                ];
            }
        } finally {
            if (class_exists(ContactSync::class)) {
                ContactSync::suspendOutbound(false);
            }
        }

        return ['success' => 1, 'result' => true];
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $fields
     */
    private static function resolveContactInheritCompanyId(int $contactId, array $params, array $fields): int
    {
        $fromFields = (int)($fields['COMPANY_ID'] ?? 0);
        if ($fromFields > 0) {
            return $fromFields;
        }
        $fromParam = (int)($params['company_id'] ?? 0);
        if ($fromParam > 0) {
            return $fromParam;
        }

        return self::resolveContactPrimaryCompanyId($contactId);
    }

    private static function resolveContactPrimaryCompanyId(int $contactId): int
    {
        if ($contactId <= 0) {
            return 0;
        }
        if (\class_exists(\Bitrix\Crm\Binding\CompanyContactTable::class)) {
            $rows = \Bitrix\Crm\Binding\CompanyContactTable::getList([
                'filter' => ['=CONTACT_ID' => $contactId],
                'select' => ['COMPANY_ID', 'IS_PRIMARY'],
                'order' => ['IS_PRIMARY' => 'DESC', 'COMPANY_ID' => 'ASC'],
            ])->fetchAll();
            $fallback = 0;
            foreach ($rows as $row) {
                $companyId = (int)($row['COMPANY_ID'] ?? 0);
                if ($companyId <= 0) {
                    continue;
                }
                if ($fallback <= 0) {
                    $fallback = $companyId;
                }
                if (self::isTruthyInboundFlag($row['IS_PRIMARY'] ?? null)) {
                    return $companyId;
                }
            }
            if ($fallback > 0) {
                return $fallback;
            }
        }

        $contact = \CCrmContact::GetByID($contactId, false);
        if (\is_array($contact)) {
            return (int)($contact['COMPANY_ID'] ?? 0);
        }

        return 0;
    }

    /**
     * @return array{success: bool, reason_code: string}
     */
    private static function runInheritedContactOutbound(int $contactId): array
    {
        if (!class_exists(ContactSync::class)) {
            return ['success' => false, 'reason_code' => 'contact_sync_unavailable'];
        }
        if (\method_exists(ContactSync::class, 'sendRegistrationInheritedContactToSiteNow')) {
            $result = ContactSync::sendRegistrationInheritedContactToSiteNow($contactId);
            if (!\is_array($result)) {
                return ['success' => false, 'reason_code' => 'outbound_invalid_response'];
            }

            return [
                'success' => (bool)($result['success'] ?? false),
                'reason_code' => (string)($result['reason_code'] ?? ''),
            ];
        }

        ContactSync::sendContactToSiteNow($contactId);

        return ['success' => true, 'reason_code' => ''];
    }

    /**
     * @param mixed $value
     */
    private static function isTruthyInboundFlag($value): bool
    {
        if ($value === true || $value === 1 || $value === '1' || $value === 'Y' || $value === 'y') {
            return true;
        }
        if (\is_string($value)) {
            $normalized = \strtolower(\trim($value));

            return \in_array($normalized, ['true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * Если в `crm.company.add` передан UF site_element_id (канонический, legacy или транспортный ключ) —
     * значение должно быть положительным целым. Отсутствие UF допустимо (вариант B: update позже).
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>|null
     */
    private static function validateCompanyAddSiteElementIdInFields(array $fields): ?array
    {
        $keysToCheck = [
            self::uf('company.site_element_id'),
            self::uf('company.site_element_id_legacy_alias'),
            'SITE_ELEMENT_ID',
            'site_element_id',
        ];
        foreach ($keysToCheck as $key) {
            if (!\array_key_exists($key, $fields)) {
                continue;
            }
            if (!self::isPositiveIntScalar($fields[$key])) {
                return [
                    'success' => 0,
                    'error' => $key . ' must be a positive integer when provided in crm.company.add fields',
                    'reason_code' => 'company_add_invalid_site_element_id',
                ];
            }
        }

        return null;
    }

    /**
     * @param mixed $v
     */
    private static function isPositiveIntScalar($v): bool
    {
        if ($v === null || $v === false || $v === '' || $v === '0' || $v === 0) {
            return false;
        }
        if (\is_int($v)) {
            return $v > 0;
        }
        if (\is_string($v) && \ctype_digit($v)) {
            return (int) $v > 0;
        }

        return false;
    }

    /**
     * ИНН из полей `crm.company.add` (верхний уровень или вложенные блоки реквизита).
     *
     * @param array<string, mixed> $fields
     */
    private static function extractInnFromCompanyAddFields(array $fields): string
    {
        $top = self::scalarStringFromMixed($fields['RQ_INN'] ?? null);
        if ($top !== '') {
            return $top;
        }

        foreach (['REQUISITE', 'REQUISITES', 'Requisite', 'requisite'] as $blockKey) {
            if (!isset($fields[$blockKey]) || !\is_array($fields[$blockKey])) {
                continue;
            }
            $hit = self::deepFindInnInNestedArray($fields[$blockKey], 5);
            if ($hit !== '') {
                return $hit;
            }
        }

        return self::deepFindInnInNestedArray($fields, 4);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function deepFindInnInNestedArray(array $data, int $depth): string
    {
        if ($depth <= 0) {
            return '';
        }
        if (\array_key_exists('RQ_INN', $data)) {
            $s = self::scalarStringFromMixed($data['RQ_INN']);
            if ($s !== '') {
                return $s;
            }
        }
        foreach ($data as $v) {
            if (!\is_array($v)) {
                continue;
            }
            $s = self::deepFindInnInNestedArray($v, $depth - 1);
            if ($s !== '') {
                return $s;
            }
        }

        return '';
    }

    /**
     * @param mixed $v
     */
    private static function scalarStringFromMixed($v): string
    {
        if ($v === null || $v === false) {
            return '';
        }
        if (\is_scalar($v)) {
            return \trim((string) $v);
        }
        if (\is_array($v) && \array_key_exists('VALUE', $v)) {
            return \trim((string) $v['VALUE']);
        }

        return '';
    }

    private static function normalizeInnDigits(string $inn): string
    {
        $d = \preg_replace('/\D+/', '', $inn);

        return \is_string($d) ? $d : '';
    }

    /**
     * Компании CRM (ENTITY_ID реквизита), у которых в реквизите указан ИНН.
     *
     * @return list<int>
     */
    private static function findCompanyIdsByRequisiteInn(string $innDigits): array
    {
        if ($innDigits === '' || !\class_exists(\Bitrix\Crm\RequisiteTable::class)) {
            return [];
        }

        $rows = \Bitrix\Crm\RequisiteTable::getList([
            'filter' => [
                '=RQ_INN' => $innDigits,
                '=ENTITY_TYPE_ID' => \CCrmOwnerType::Company,
            ],
            'select' => ['ENTITY_ID'],
        ])->fetchAll();

        $set = [];
        foreach ($rows as $row) {
            $eid = (int)($row['ENTITY_ID'] ?? 0);
            if ($eid > 0) {
                $set[$eid] = true;
            }
        }
        $ids = \array_keys($set);
        \sort($ids, \SORT_NUMERIC);

        return $ids;
    }

    /**
     * ИНН уже занят одной компанией: головная → дочерняя + UF холдинга; иначе → только привязка контакта к найденной компании (без второго Add).
     *
     * @param array<string, mixed> $fields
     */
    private static function crmCompanyResolveDuplicateInn(array $fields, string $innDigits, int $existingCompanyId): array
    {
        $ufHeadFlag = self::uf('company.head_company_flag');
        if (!self::crmCompanyUfIsTruthy($existingCompanyId, $ufHeadFlag)) {
            return [
                'success' => 1,
                'result' => $existingCompanyId,
                'reason_code' => 'company_add_use_existing_for_contact',
                'data' => [
                    'rq_inn' => $innDigits,
                    'company_id' => $existingCompanyId,
                    'attach_contact_only' => true,
                ],
            ];
        }

        return self::crmCompanyAddChildWhenHeadCompanySharesInn($fields, $innDigits, $existingCompanyId);
    }

    /**
     * ИНН уже есть у головной компании: создаём дочернюю и синхронизируем UF «Фирмы холдинга».
     *
     * @param array<string, mixed> $fields
     */
    private static function crmCompanyAddChildWhenHeadCompanySharesInn(array $fields, string $innDigits, int $headCompanyId): array
    {
        $ufHolding = self::uf('company.holding');
        $ufMembers = self::uf('company.holding_group_members');
        $childFields = $fields;
        $childFields[$ufHolding] = $headCompanyId;

        $childId = 0;
        $entity = new \CCrmCompany(false);
        CompanySync::suspendOutbound(true);
        try {
            $childId = (int)$entity->Add($childFields, true, ['CURRENT_USER' => 1, 'IS_SYSTEM_ACTION' => true]);
        } finally {
            CompanySync::suspendOutbound(false);
        }
        if ($childId <= 0) {
            return [
                'success' => 0,
                'error' => (string)$entity->LAST_ERROR,
                'reason_code' => 'company_add_child_failed',
                'data' => ['rq_inn' => $innDigits, 'head_company_id' => $headCompanyId],
            ];
        }

        $memberIds = self::collectHoldingMemberCompanyIds($headCompanyId, $childId, $ufHolding, $ufMembers);
        self::propagateHoldingGroupMembersUfToAll($memberIds, $ufMembers);

        return [
            'success' => 1,
            'result' => $childId,
            'reason_code' => 'company_add_child_under_head_inn',
            'data' => [
                'head_company_id' => $headCompanyId,
                'child_company_id' => $childId,
                'holding_member_company_ids' => $memberIds,
                'rq_inn' => $innDigits,
            ],
        ];
    }

    /**
     * @return list<int>
     */
    private static function collectHoldingMemberCompanyIds(int $headId, int $childId, string $holdingUf, string $membersUf): array
    {
        $linked = self::findCompanyIdsWithHoldingParentRef($headId, $holdingUf);
        $fromHeadList = self::readCompanyUfCrmCompanyMultilistIds($headId, $membersUf);
        $merged = \array_merge([$headId, $childId], $linked, $fromHeadList);
        $out = [];
        foreach ($merged as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $out[$id] = true;
            }
        }
        $ids = \array_keys($out);
        \sort($ids, \SORT_NUMERIC);

        return $ids;
    }

    /**
     * @param list<int> $memberCompanyIds
     */
    private static function propagateHoldingGroupMembersUfToAll(array $memberCompanyIds, string $membersUf): void
    {
        if ($memberCompanyIds === []) {
            return;
        }
        $preferred = self::formatCrmCompanyMultilistBindingValues($memberCompanyIds);
        $fallback = self::formatNumericCompanyIdListValues($memberCompanyIds);
        CompanySync::runWithHoldingGroupMembersUfMutationAllowed(static function () use ($memberCompanyIds, $membersUf, $preferred, $fallback): void {
            foreach ($memberCompanyIds as $cid) {
                if ($cid <= 0) {
                    continue;
                }
                CompanySync::markInboundCompanyUpdate($cid);
                $entity = new \CCrmCompany(false);
                $memberFields = [$membersUf => $preferred];
                $ok = (bool)$entity->Update(
                    $cid,
                    $memberFields,
                    true,
                    true,
                    [
                        'CURRENT_USER' => 1,
                        'IS_SYSTEM_ACTION' => true,
                    ]
                );
                if (!$ok && $fallback !== []) {
                    CompanySync::markInboundCompanyUpdate($cid);
                    $entity2 = new \CCrmCompany(false);
                    $memberFieldsFb = [$membersUf => $fallback];
                    $entity2->Update(
                        $cid,
                        $memberFieldsFb,
                        true,
                        true,
                        [
                            'CURRENT_USER' => 1,
                            'IS_SYSTEM_ACTION' => true,
                        ]
                    );
                }
            }
        });
    }

    /**
     * @param list<int> $companyIds
     * @return list<string>
     */
    private static function formatCrmCompanyMultilistBindingValues(array $companyIds): array
    {
        $values = [];
        foreach (\array_unique(\array_map('intval', $companyIds)) as $id) {
            if ($id > 0) {
                $values[] = 'CO_' . $id;
            }
        }
        \sort($values, \SORT_STRING);

        return \array_values(\array_unique($values));
    }

    /**
     * @param list<int> $companyIds
     * @return list<int>
     */
    private static function formatNumericCompanyIdListValues(array $companyIds): array
    {
        $values = [];
        foreach (\array_unique(\array_map('intval', $companyIds)) as $id) {
            if ($id > 0) {
                $values[] = $id;
            }
        }
        \sort($values, \SORT_NUMERIC);

        return \array_values(\array_unique($values));
    }

    /**
     * Дочерние компании, у которых в UF «холдинг» указана головная (CRM ID / строка / CO_*).
     *
     * @return list<int>
     */
    private static function findCompanyIdsWithHoldingParentRef(int $headId, string $holdingUf): array
    {
        $variants = [$headId, (string)$headId, 'CO_' . $headId];
        $seen = [];
        foreach ($variants as $v) {
            $res = \CCrmCompany::GetListEx(
                ['ID' => 'ASC'],
                [
                    '=' . $holdingUf => $v,
                    'CHECK_PERMISSIONS' => 'N',
                ],
                false,
                false,
                ['ID']
            );
            while ($row = $res->Fetch()) {
                $id = (int)($row['ID'] ?? 0);
                if ($id > 0) {
                    $seen[$id] = true;
                }
            }
        }

        return \array_keys($seen);
    }

    /**
     * Разбор UF типа привязки к компаниям CRM (CO_123 или число).
     *
     * @return list<int>
     */
    private static function readCompanyUfCrmCompanyMultilistIds(int $companyId, string $ufKey): array
    {
        if ($companyId <= 0) {
            return [];
        }
        $row = \CCrmCompany::GetByID($companyId, false);
        if (!\is_array($row)) {
            return [];
        }
        global $USER_FIELD_MANAGER;
        if (\is_object($USER_FIELD_MANAGER)) {
            $ufs = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId);
            if (isset($ufs[$ufKey])) {
                $row[$ufKey] = $ufs[$ufKey]['VALUE'] ?? ($row[$ufKey] ?? null);
            }
        }
        $raw = $row[$ufKey] ?? null;
        if ($raw === null || $raw === '' || $raw === false) {
            return [];
        }
        $values = \is_array($raw) ? $raw : [$raw];
        $ids = [];
        foreach ($values as $value) {
            if (\is_string($value) && \strpos($value, 'CO_') === 0) {
                $ids[] = (int)\substr($value, 3);
                continue;
            }
            $ids[] = (int)$value;
        }

        return \array_values(\array_unique(\array_filter($ids, static function (int $id): bool {
            return $id > 0;
        })));
    }

    private static function crmCompanyUfIsTruthy(int $companyId, string $ufKey): bool
    {
        if ($companyId <= 0) {
            return false;
        }
        $row = \CCrmCompany::GetByID($companyId, false);
        if (!\is_array($row)) {
            return false;
        }
        global $USER_FIELD_MANAGER;
        if (\is_object($USER_FIELD_MANAGER)) {
            $ufs = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId);
            if (isset($ufs[$ufKey])) {
                $row[$ufKey] = $ufs[$ufKey]['VALUE'] ?? ($row[$ufKey] ?? null);
            }
        }
        $raw = $row[$ufKey] ?? null;

        return self::crmScalarIsTruthy($raw);
    }

    /**
     * @param mixed $raw
     */
    private static function crmScalarIsTruthy($raw): bool
    {
        if ($raw === 'Y' || $raw === true || $raw === 1 || $raw === '1') {
            return true;
        }
        if (\is_array($raw)) {
            if (\array_key_exists('VALUE', $raw)) {
                return self::crmScalarIsTruthy($raw['VALUE']);
            }
        }

        return false;
    }

    private static function findCompanyIdsBySiteElementId(string $siteElementId): array
    {
        $canonical = self::uf('company.site_element_id');
        $legacy = self::uf('company.site_element_id_legacy_alias');
        $merged = array_merge(
            self::collectCompanyIdsMatchingUf($canonical, $siteElementId),
            self::collectCompanyIdsMatchingUf($legacy, $siteElementId),
        );

        return array_values(array_unique($merged));
    }

    /**
     * @return list<int>
     */
    private static function collectCompanyIdsMatchingUf(string $ufField, string $siteElementId): array
    {
        $res = \CCrmCompany::GetListEx(
            ['ID' => 'ASC'],
            [
                '=' . $ufField => $siteElementId,
                'CHECK_PERMISSIONS' => 'N',
            ],
            false,
            false,
            ['ID', $ufField],
        );

        $ids = [];
        while ($row = $res->Fetch()) {
            if ((string)($row[$ufField] ?? '') !== $siteElementId) {
                continue;
            }
            $id = (int)($row['ID'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
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

    /**
     * @return list<int>
     */
    private static function loadCompanyContactIds(int $companyId): array
    {
        if ($companyId <= 0 || !\class_exists(\Bitrix\Crm\Binding\CompanyContactTable::class)) {
            return [];
        }
        $bindings = \Bitrix\Crm\Binding\CompanyContactTable::getList([
            'filter' => ['=COMPANY_ID' => $companyId],
            'select' => ['CONTACT_ID'],
        ])->fetchAll();
        if (!\is_array($bindings)) {
            return [];
        }
        $ids = [];
        foreach ($bindings as $row) {
            $cid = (int)($row['CONTACT_ID'] ?? 0);
            if ($cid > 0) {
                $ids[] = $cid;
            }
        }

        return \array_values(\array_unique($ids));
    }

    /**
     * Значения с карточки компании для зеркалирования на контакты (ответственный + UF «второй менеджер» компании).
     *
     * @return array{0: int, 1: mixed}
     */
    private static function readCompanyManagerMirrorValues(int $companyId, string $ufCompanySiteSync): array
    {
        $assigned = 0;
        $ufVal = null;
        $res = \CCrmCompany::GetListEx(
            ['ID' => 'ASC'],
            ['=ID' => $companyId, 'CHECK_PERMISSIONS' => 'N'],
            false,
            false,
            ['ID', 'ASSIGNED_BY_ID', $ufCompanySiteSync]
        );
        $row = $res ? $res->Fetch() : null;
        if (\is_array($row)) {
            $assigned = (int)($row['ASSIGNED_BY_ID'] ?? 0);
            if (\array_key_exists($ufCompanySiteSync, $row)) {
                $ufVal = $row[$ufCompanySiteSync];
            }
        }
        global $USER_FIELD_MANAGER;
        if (($ufVal === null || $ufVal === '') && \is_object($USER_FIELD_MANAGER)) {
            $ufs = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId);
            if (isset($ufs[$ufCompanySiteSync])) {
                $ufVal = $ufs[$ufCompanySiteSync]['VALUE'] ?? null;
            }
        }

        return [$assigned, $ufVal];
    }

    /**
     * После UPDATE_COMPANY: актуальные ответственный и UF компании для записи на контакты (с учётом только что применённых полей).
     *
     * @param array<string, mixed> $companyFieldsSubset
     * @return array{0: int, 1: mixed} assigned_by_id, contact UF value (нормализовано под запись в CRM)
     */
    private static function resolveCompanyManagerMirrorForContacts(
        int $companyId,
        string $ufCompanySiteSync,
        array $companyFieldsSubset
    ): array {
        [$assigned, $ufVal] = self::readCompanyManagerMirrorValues($companyId, $ufCompanySiteSync);
        if (\array_key_exists('ASSIGNED_BY_ID', $companyFieldsSubset)) {
            $assigned = (int)$companyFieldsSubset['ASSIGNED_BY_ID'];
        }
        if (\array_key_exists($ufCompanySiteSync, $companyFieldsSubset)) {
            $ufVal = $companyFieldsSubset[$ufCompanySiteSync];
        }

        return [$assigned, self::normalizeCrmUfSingleValueForWrite($ufVal)];
    }

    /**
     * Приведение значения UF к виду, который CCrmContact::Update обычно принимает (скаляр / пустая строка).
     *
     * @param mixed $v
     * @return mixed
     */
    private static function normalizeCrmUfSingleValueForWrite($v)
    {
        if ($v === null || $v === false) {
            return '';
        }
        if (\is_array($v)) {
            if (\array_key_exists('VALUE', $v)) {
                return self::normalizeCrmUfSingleValueForWrite($v['VALUE']);
            }
            if ($v !== [] && \array_keys($v) === \range(0, \count($v) - 1)) {
                return self::normalizeCrmUfSingleValueForWrite($v[0] ?? '');
            }
            $first = \reset($v);

            return self::normalizeCrmUfSingleValueForWrite($first !== false ? $first : '');
        }

        return $v;
    }
}
