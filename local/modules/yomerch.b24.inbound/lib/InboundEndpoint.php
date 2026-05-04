<?php

namespace OnlineService\Sync\FromSite;

use OnlineService\Sync\UfMap;
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
        $ufCompanySiteElementIdLegacy = self::uf('company.site_element_id_legacy_alias');
        $ufCompanyCity = self::uf('company.city');
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
        if (\class_exists(ContactSync::class)) {
            ContactSync::sendContactToSiteNow($id);
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
        if ($ok && \class_exists(ContactSync::class)) {
            ContactSync::sendContactToSiteNow($id);
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

        $entity = new \CCrmCompany(false);
        $childId = (int)$entity->Add($childFields, true, ['CURRENT_USER' => 1, 'IS_SYSTEM_ACTION' => true]);
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
