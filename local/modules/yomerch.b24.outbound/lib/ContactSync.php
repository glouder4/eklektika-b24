<?php

namespace OnlineService\Sync\ToSite;

use OnlineService\Sync\UfMap;

class ContactSync extends OutboundRequest
{
    /**
     * UF CRM «руководитель/директор» — на сайте в старых сценариях маппится в UF_IS_DIRECTOR (0/1).
     */
    private const UF_CONTACT_IS_DIRECTOR = 'contact.is_director';
    private const UF_COMPANY_IS_HEAD = 'company.is_head';
    private const UF_COMPANY_HOLDING = 'company.holding';
    private const HOLDING_IBLOCK_ID = 34;
    private const HOLDING_PROP_B24 = 'B24_COMPANY_ID';
    private const UF_COMPANY_DISCOUNT = 'company.discount';

    /** @var array<string, bool> */
    private static array $eventDedup = [];
    private static bool $suspendOutbound = false;

    public static function suspendOutbound(bool $state): void
    {
        self::$suspendOutbound = $state;
    }

    public static function onAfterContactAdd(...$args): void
    {
        if (self::$suspendOutbound) {
            return;
        }
        $contactId = self::extractContactIdFromArgs($args);
        if ($contactId <= 0) {
            return;
        }
        if (!self::shouldProcessOnce('add', $contactId)) {
            return;
        }

        self::sendContactToSite($contactId);
    }

    public static function onAfterContactUpdate(...$args): void
    {
        if (self::$suspendOutbound) {
            return;
        }
        $contactId = self::extractContactIdFromArgs($args);
        if ($contactId <= 0) {
            return;
        }
        if (!self::shouldProcessOnce('update', $contactId)) {
            return;
        }

        self::sendContactToSite($contactId);
    }

    public static function onBeforeContactDelete(...$args): bool
    {
        $contactId = self::extractContactIdFromArgs($args);
        if ($contactId <= 0) {
            return true;
        }

        $siteRef = self::resolveContactOutboundSiteRef($contactId, $args);
        if ($siteRef === '') {
            self::writeOutboundTrace('ContactSync::delete_contact.skip_no_site_ref', [
                'contact_id' => $contactId,
                'uf' => self::uf('contact.delete_site_ref'),
            ]);
            return true;
        }

        $sync = new self();
        $response = $sync->sendRequest([
            'ACTION' => 'DELETE_CONTACT',
            'ID' => $siteRef,
            'OS_COMPANY_B24_ID' => $siteRef,
            'B24_ID' => $contactId,
        ], false);

        if ((int)($response['success'] ?? 0) !== 1 || !empty($response['error'])) {
            error_log(
                '[ContactSync::onBeforeContactDelete] Site sync failed for contact '
                . $contactId
                . ' delete_site_id='
                . $siteRef
                . '; result='
                . json_encode($response, JSON_UNESCAPED_UNICODE)
            );
            self::writeOutboundTrace('ContactSync::delete_contact.failed', [
                'contact_id' => $contactId,
                'http_status' => (int)($response['http_status'] ?? 0),
                'error_code' => (string)($response['error_code'] ?? ''),
                'reason_code' => (string)($response['reason_code'] ?? ''),
                'retryable' => !empty($response['retryable']),
                'outcome' => (string)($response['outcome'] ?? ''),
            ]);
        }

        // Fail-open: не блокируем удаление в CRM при временной недоступности сайта.
        return true;
    }

    /**
     * Прямая отправка контакта на сайт (в обход CRM-событий), например после батча в {@see CompanySync}.
     */
    public static function sendContactToSiteNow(int $contactId): void
    {
        if ($contactId <= 0) {
            return;
        }
        self::sendContactToSite($contactId);
    }

    private static function uf(string $key): string
    {
        return UfMap::get($key);
    }

    private static function shouldProcessOnce(string $eventType, int $contactId): bool
    {
        $key = $eventType . ':' . $contactId;
        if (isset(self::$eventDedup[$key])) {
            return false;
        }
        self::$eventDedup[$key] = true;

        return true;
    }

    private static function sendContactToSite(int $contactId): void
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }

        $contact = \CCrmContact::GetByID($contactId, false);
        if (!is_array($contact) || empty($contact)) {
            return;
        }

        global $USER_FIELD_MANAGER;
        if (is_object($USER_FIELD_MANAGER)) {
            $ufRows = $USER_FIELD_MANAGER->GetUserFields('CRM_CONTACT', $contactId, LANGUAGE_ID);
            foreach ($ufRows as $fieldName => $fieldMeta) {
                if (!is_string($fieldName) || strncmp($fieldName, 'UF_', 3) !== 0) {
                    continue;
                }
                $v = $fieldMeta['VALUE'] ?? null;
                if (!array_key_exists($fieldName, $contact) || $contact[$fieldName] === null || $contact[$fieldName] === '') {
                    $contact[$fieldName] = $v;
                }
            }
        }

        $email = self::getFirstMultiValue($contactId, 'EMAIL');
        $phone = self::getFirstMultiValue($contactId, 'PHONE');

        $directorUf = self::uf(self::UF_CONTACT_IS_DIRECTOR);
        $ufContactSiteUserId = self::uf('contact.site_user_id');
        $ufSecondManagerSource = self::uf('contact.site_sync_value');
        $ufCompanyDiscount = self::uf(self::UF_COMPANY_DISCOUNT);
        $directorRaw = self::extractSingleUfValue($contact, $directorUf);
        $secondManagerRaw = self::extractSingleUfValue($contact, $ufSecondManagerSource);

        $params = [
            'ACTION' => 'UPDATE_CONTACT',
            'B24_ID' => $contactId,
            $ufContactSiteUserId => (int)($contact[$ufContactSiteUserId] ?? 0),
            'NAME' => (string)($contact['NAME'] ?? ''),
            'LAST_NAME' => (string)($contact['LAST_NAME'] ?? ''),
            'SECOND_NAME' => (string)($contact['SECOND_NAME'] ?? ''),
            'WORK_POSITION' => (string)($contact['POST'] ?? ''),
            'PERSONAL_BIRTHDAY' => (string)($contact['BIRTHDATE'] ?? ''),
            // Сайт: `ASSIGNED_MANAGER` = `ASSIGNED_BY_ID` контакта; `SECOND_MANAGER` = UF `contact.site_sync_value`.
            'ASSIGNED_MANAGER' => (int)($contact['ASSIGNED_BY_ID'] ?? 0),
            'SECOND_MANAGER' => $secondManagerRaw !== null ? $secondManagerRaw : '',
            $directorUf => $directorRaw,
            'UF_IS_DIRECTOR' => self::ufToDirectorInt($directorRaw),
        ];
        if ($email !== '') {
            $params['EMAIL'] = $email;
        }
        if ($phone !== '') {
            $params['PERSONAL_PHONE'] = $phone;
            $params['WORK_PHONE'] = $phone;
        }

        OutboundContactMarketingForSite::mergeIntoUpdateContactPost($params, $contact);

        $inheritsUfKey = self::uf('contact.inherits_company_is_marketing_agent');
        $inheritsRaw = self::extractSingleUfValue($contact, $inheritsUfKey);
        if ($inheritsRaw !== null && $inheritsRaw !== '') {
            $params['ACTIVE'] = self::crmScalarToActiveYn($inheritsRaw);
        } elseif (
            isset($params['IS_MARKETING_AGENT'])
            && ($params['IS_MARKETING_AGENT'] === 'Y' || $params['IS_MARKETING_AGENT'] === 'N')
        ) {
            $params['ACTIVE'] = (string) $params['IS_MARKETING_AGENT'];
        } else {
            $params['ACTIVE'] = 'N';
        }

        $advUfKey = self::uf('company.contact_marketing_agent');
        if (\array_key_exists($advUfKey, $params) && $params[$advUfKey] !== null && $params[$advUfKey] !== '') {
            $params['UF_ADVERTISING_AGENT'] = $params[$advUfKey];
        } else {
            $advRaw = self::extractSingleUfValue($contact, $advUfKey);
            $params['UF_ADVERTISING_AGENT'] = $advRaw !== null && $advRaw !== '' ? $advRaw : '';
        }

        // По умолчанию отправляем пустое значение: это позволяет сайту корректно очищать скидку.
        $params[$ufCompanyDiscount] = '';
        $params['OS_COMPANY_DISCOUNT_VALUE'] = '';
        $companyB24Id = self::resolvePrimaryCompanyB24Id($contactId);
        $siteCatalogRef = self::resolveContactOutboundSiteRef($contactId);
        if ($siteCatalogRef !== '') {
            $params['ID'] = $siteCatalogRef;
            // Сайт: `ID` и `OS_COMPANY_B24_ID` — одно и то же значение UF (`contact.delete_site_ref`).
            $params['OS_COMPANY_B24_ID'] = $siteCatalogRef;
        } elseif ($companyB24Id > 0) {
            $params['OS_COMPANY_B24_ID'] = $companyB24Id;
        }
        if ($companyB24Id > 0) {
            $discountValue = self::getCompanyDiscountValue($companyB24Id);
            $params[$ufCompanyDiscount] = $discountValue;
            // Совместимость с сайтом: часть обработчиков читает OS_* ключи.
            $params['OS_COMPANY_DISCOUNT_VALUE'] = $discountValue;
        }

        $sync = new self();
        $response = $sync->sendRequest($params, false);
        if ((int)($response['success'] ?? 0) !== 1) {
            self::writeOutboundTrace('ContactSync::update_contact.failed', [
                'contact_id' => $contactId,
                'http_status' => (int)($response['http_status'] ?? 0),
                'error_code' => (string)($response['error_code'] ?? ''),
                'reason_code' => (string)($response['reason_code'] ?? ''),
                'retryable' => !empty($response['retryable']),
                'outcome' => (string)($response['outcome'] ?? ''),
            ]);
        }
    }

    private static function resolvePrimaryCompanyB24Id(int $contactId): int
    {
        if ($contactId <= 0 || !\class_exists('\Bitrix\Crm\Binding\CompanyContactTable')) {
            return 0;
        }
        $rows = \Bitrix\Crm\Binding\CompanyContactTable::getList([
            'filter' => ['=CONTACT_ID' => $contactId],
            'select' => ['COMPANY_ID', 'IS_PRIMARY'],
            'order' => ['IS_PRIMARY' => 'DESC', 'COMPANY_ID' => 'ASC'],
        ])->fetchAll();
        if (!is_array($rows) || $rows === []) {
            return 0;
        }
        $companyIds = [];
        $primaryCompanyId = 0;
        foreach ($rows as $row) {
            $id = (int)($row['COMPANY_ID'] ?? 0);
            if ($id > 0) {
                $companyIds[] = $id;
                if (
                    $primaryCompanyId <= 0
                    && self::isTruthyFlag($row['IS_PRIMARY'] ?? null)
                ) {
                    $primaryCompanyId = $id;
                }
            }
        }
        $companyIds = array_values(array_unique($companyIds));
        if ($companyIds === []) {
            return 0;
        }

        if (self::isDirectorContact($contactId)) {
            foreach ($companyIds as $companyId) {
                if (self::isHeadCompany($companyId)) {
                    return $companyId;
                }
            }
            foreach ($companyIds as $companyId) {
                $holdingElementId = self::getCompanyHoldingElementId($companyId);
                if ($holdingElementId <= 0) {
                    continue;
                }
                $headCompanyId = self::getHeadCompanyIdByHoldingElement((int)$holdingElementId);
                if ($headCompanyId > 0) {
                    return $headCompanyId;
                }
            }
        }

        if ($primaryCompanyId > 0) {
            return $primaryCompanyId;
        }

        return (int)$companyIds[0];
    }

    /**
     * Нормализованная скидка компании по CRM ID (для UPDATE_COMPANY и др.).
     */
    public static function getNormalizedCompanyDiscount(int $companyId): string
    {
        return self::getCompanyDiscountValue($companyId);
    }

    /**
     * Нормализация сырого значения UF скидки для контракта с сайтом.
     *
     * @param mixed $raw
     */
    public static function normalizeDiscountFromRaw($raw): string
    {
        return self::normalizeDiscountValue($raw);
    }

    private static function getCompanyDiscountValue(int $companyId): string
    {
        if ($companyId <= 0) {
            return '';
        }
        global $USER_FIELD_MANAGER;
        if (is_object($USER_FIELD_MANAGER)) {
            $ufRows = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId, LANGUAGE_ID);
            $key = self::uf(self::UF_COMPANY_DISCOUNT);
            $cell = $ufRows[$key] ?? null;
            $raw = null;
            if (is_array($cell)) {
                $raw = $cell['VALUE'] ?? null;
                if (($raw === null || $raw === '') && array_key_exists('VALUE_ID', $cell)) {
                    $raw = $cell['VALUE_ID'];
                }
            }
            if ($raw !== null && $raw !== '') {
                return self::normalizeDiscountValue($raw);
            }
        }
        $company = \CCrmCompany::GetByID($companyId, false);
        if (!is_array($company)) {
            return '';
        }
        return self::normalizeDiscountValue($company[self::uf(self::UF_COMPANY_DISCOUNT)] ?? '');
    }

    /**
     * @param mixed $raw
     */
    private static function normalizeDiscountValue($raw): string
    {
        if (is_array($raw)) {
            if (array_key_exists('VALUE', $raw)) {
                $raw = $raw['VALUE'];
            } else {
                $raw = reset($raw);
            }
        }
        if (!is_scalar($raw)) {
            return '';
        }
        $value = trim((string)$raw);
        if ($value === '') {
            return '';
        }
        // «1,017» как тысячи — не превращаем в 1.017.
        $compact = str_replace([' ', "\xc2\xa0"], '', $value);
        if (preg_match('/^\d{1,3}(?:,\d{3})+$/', $compact)) {
            $value = str_replace(',', '', $compact);
        } else {
            // Приводим десятичный разделитель к точке, оставляя формат как строку для контракта сайта.
            $value = str_replace(',', '.', $value);
        }
        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            return '';
        }
        return $value;
    }

    private static function isDirectorContact(int $contactId): bool
    {
        if ($contactId <= 0) {
            return false;
        }
        global $USER_FIELD_MANAGER;
        if (!is_object($USER_FIELD_MANAGER)) {
            return false;
        }
        $ufRows = $USER_FIELD_MANAGER->GetUserFields('CRM_CONTACT', $contactId, LANGUAGE_ID);
        $raw = $ufRows[self::uf(self::UF_CONTACT_IS_DIRECTOR)]['VALUE'] ?? null;
        return self::ufToDirectorInt($raw) === 1;
    }

    private static function isHeadCompany(int $companyId): bool
    {
        if ($companyId <= 0) {
            return false;
        }
        global $USER_FIELD_MANAGER;
        if (!is_object($USER_FIELD_MANAGER)) {
            return false;
        }
        $ufRows = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId, LANGUAGE_ID);
        $raw = $ufRows[self::uf(self::UF_COMPANY_IS_HEAD)]['VALUE'] ?? null;
        if ($raw === 'Y' || $raw === true || $raw === 1 || $raw === '1') {
            return true;
        }
        return false;
    }

    private static function getCompanyHoldingElementId(int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }
        global $USER_FIELD_MANAGER;
        if (!is_object($USER_FIELD_MANAGER)) {
            return 0;
        }
        $ufRows = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId, LANGUAGE_ID);
        return (int)($ufRows[self::uf(self::UF_COMPANY_HOLDING)]['VALUE'] ?? 0);
    }

    private static function getHeadCompanyIdByHoldingElement(int $holdingElementId): int
    {
        if ($holdingElementId <= 0 || !\Bitrix\Main\Loader::includeModule('iblock')) {
            return 0;
        }
        $res = \CIBlockElement::GetProperty(
            self::HOLDING_IBLOCK_ID,
            $holdingElementId,
            [],
            ['CODE' => self::HOLDING_PROP_B24]
        );
        if ($prop = $res->Fetch()) {
            return (int)($prop['VALUE'] ?? 0);
        }
        return 0;
    }

    /**
     * Значение UF `contact.delete_site_ref` для произвольного CRM-контакта (без аргументов события).
     * Используется в {@see CompanySync} для CONTACT_IDS / пользовательских списков в UPDATE_COMPANY.
     */
    public static function outboundSiteCatalogRefForContact(int $contactId): string
    {
        return self::resolveContactOutboundSiteRef($contactId, []);
    }

    /**
     * Значение UF {@see UfMap} `contact.delete_site_ref` (`UF_CRM_3804624445748`):
     * DELETE_CONTACT — поля `ID` / `OS_COMPANY_B24_ID`; UPDATE_CONTACT — то же + скидка компании.
     *
     * @param array<int, mixed> $eventArgs аргументы CRM-события (например OnBeforeCrmContactDelete) — UF часто доступен здесь раньше, чем в «обрезанном» GetByID.
     */
    private static function resolveContactOutboundSiteRef(int $contactId, array $eventArgs = []): string
    {
        if ($contactId <= 0 || !\Bitrix\Main\Loader::includeModule('crm')) {
            return '';
        }

        $ufKey = self::uf('contact.delete_site_ref');

        $fromEvent = self::extractSiteRefFromCrmEventArgs($eventArgs, $ufKey);
        if ($fromEvent !== '') {
            return $fromEvent;
        }

        if (class_exists(\Bitrix\Crm\ContactTable::class)) {
            try {
                $row = \Bitrix\Crm\ContactTable::getList([
                    'filter' => ['=ID' => $contactId],
                    'select' => ['ID', $ufKey],
                    'limit' => 1,
                ])->fetch();
                if (is_array($row)) {
                    $fromTable = self::normalizeOutboundSiteRefScalar(self::extractSingleUfValue($row, $ufKey));
                    if ($fromTable !== '') {
                        return $fromTable;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $contact = \CCrmContact::GetByID($contactId, false);
        if (!is_array($contact) || empty($contact)) {
            return '';
        }

        global $USER_FIELD_MANAGER;
        if (is_object($USER_FIELD_MANAGER)) {
            $lang = defined('LANGUAGE_ID') ? (string)LANGUAGE_ID : '';
            $ufRows = $USER_FIELD_MANAGER->GetUserFields('CRM_CONTACT', $contactId, $lang);
            foreach ($ufRows as $fieldName => $fieldMeta) {
                if (!is_string($fieldName) || strncmp($fieldName, 'UF_', 3) !== 0) {
                    continue;
                }
                $v = $fieldMeta['VALUE'] ?? null;
                if (!array_key_exists($fieldName, $contact) || $contact[$fieldName] === null || $contact[$fieldName] === '') {
                    $contact[$fieldName] = $v;
                }
            }
        }

        $raw = self::extractSingleUfValue($contact, $ufKey);

        return self::normalizeOutboundSiteRefScalar($raw);
    }

    /**
     * @param array<int, mixed> $eventArgs
     */
    private static function extractSiteRefFromCrmEventArgs(array $eventArgs, string $ufKey): string
    {
        foreach ($eventArgs as $payload) {
            if (!is_array($payload)) {
                continue;
            }
            $blocks = [];
            foreach (['FIELDS', 'fields', 'arFields', 'AR_FIELDS', 'FIELD_VALUES', 'ENTITY_FIELDS'] as $k) {
                if (isset($payload[$k]) && is_array($payload[$k])) {
                    $blocks[] = $payload[$k];
                }
            }
            $blocks[] = $payload;
            foreach ($blocks as $block) {
                $hit = self::deepFindUfValue($block, $ufKey, 0);
                if ($hit !== '') {
                    return $hit;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function deepFindUfValue(array $data, string $ufKey, int $depth): string
    {
        if ($depth > 6) {
            return '';
        }
        if (array_key_exists($ufKey, $data)) {
            $s = self::normalizeOutboundSiteRefScalar($data[$ufKey]);
            if ($s !== '') {
                return $s;
            }
        }
        foreach ($data as $v) {
            if (is_array($v)) {
                $s = self::deepFindUfValue($v, $ufKey, $depth + 1);
                if ($s !== '') {
                    return $s;
                }
            }
        }

        return '';
    }

    /**
     * @param mixed $raw
     */
    private static function normalizeOutboundSiteRefScalar($raw): string
    {
        if ($raw === null || $raw === false) {
            return '';
        }
        if (is_scalar($raw)) {
            return trim((string)$raw);
        }
        if (is_array($raw)) {
            if (array_key_exists('VALUE', $raw)) {
                return self::normalizeOutboundSiteRefScalar($raw['VALUE']);
            }
            $first = reset($raw);
            if ($first !== false) {
                return self::normalizeOutboundSiteRefScalar($first);
            }
        }

        return '';
    }

    /**
     * Поиск CRM ID контакта в структурах события (в т.ч. вложенные `FIELDS` / `data`), без широкого обхода всего дерева.
     *
     * @param array<string, mixed> $data
     */
    private static function extractContactIdFromNestedFieldsArray(array $data, int $depth): int
    {
        if ($depth > 8) {
            return 0;
        }
        foreach (['ID', 'id', 'CONTACT_ID', 'contactId'] as $k) {
            if (\array_key_exists($k, $data)) {
                $id = (int) $data[$k];
                if ($id > 0) {
                    return $id;
                }
            }
        }
        foreach (['FIELDS', 'fields', 'arFields', 'DATA', 'data'] as $nestedKey) {
            if (!isset($data[$nestedKey]) || !\is_array($data[$nestedKey])) {
                continue;
            }
            $id = self::extractContactIdFromNestedFieldsArray($data[$nestedKey], $depth + 1);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    /**
     * В разных обработчиках CRM ID может приходить в разных ключах.
     */
    private static function extractContactIdFromArgs(array $args): int
    {
        foreach ($args as $arg) {
            if ($arg instanceof \Bitrix\Main\Event) {
                $params = $arg->getParameters();
                $id = \is_array($params) ? self::extractContactIdFromNestedFieldsArray($params, 0) : 0;
                if ($id > 0) {
                    return $id;
                }

                continue;
            }
            if (is_scalar($arg)) {
                $id = (int)$arg;
                if ($id > 0) {
                    return $id;
                }
                continue;
            }
            if (!is_array($arg)) {
                continue;
            }

            $candidates = [
                $arg['ID'] ?? null,
                $arg['id'] ?? null,
                $arg['ENTITY_ID'] ?? null,
                $arg['FIELDS']['ID'] ?? null,
                $arg['FIELDS']['id'] ?? null,
                $arg['RESULT']['ID'] ?? null,
            ];
            foreach ($candidates as $candidate) {
                $id = (int)$candidate;
                if ($id > 0) {
                    return $id;
                }
            }
        }
        foreach ($args as $arg) {
            if (!\is_array($arg)) {
                continue;
            }
            $id = self::extractContactIdFromNestedFieldsArray($arg, 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $contact
     * @return mixed
     */
    private static function extractSingleUfValue(array $contact, string $key)
    {
        if (!\array_key_exists($key, $contact)) {
            return null;
        }
        $v = $contact[$key];
        if (\is_array($v)) {
            if (\array_key_exists('VALUE', $v)) {
                return $v['VALUE'];
            }
            $first = \reset($v);
            if (\is_array($first) && \array_key_exists('VALUE', $first)) {
                return $first['VALUE'];
            }
        }

        return $v;
    }

    /**
     * Для обратной совместимости с полем UF_IS_DIRECTOR (0/1) на стороне сайта.
     *
     * @param mixed $v
     */
    /**
     * Сайт: `ACTIVE` (`Y`/`N`) из UF «наследует маркетинговый признак компании».
     *
     * @param mixed $raw
     */
    private static function crmScalarToActiveYn($raw): string
    {
        if ($raw === null || $raw === '' || $raw === false) {
            return 'N';
        }
        if ($raw === true || $raw === 1 || $raw === '1' || $raw === 'Y' || $raw === 'y') {
            return 'Y';
        }
        if (\is_numeric($raw)) {
            return ((float) $raw) != 0.0 ? 'Y' : 'N';
        }
        if (\is_string($raw)) {
            $t = \strtolower(\trim($raw));
            if (\in_array($t, ['y', 'yes', 'true', '1'], true)) {
                return 'Y';
            }
        }

        return 'N';
    }

    private static function ufToDirectorInt($v): int
    {
        if ($v === null || $v === '' || $v === false) {
            return 0;
        }
        if ($v === true) {
            return 1;
        }
        if (\is_int($v) || \is_float($v)) {
            return ((int) $v) !== 0 ? 1 : 0;
        }
        if (\is_string($v)) {
            $s = \strtolower(\trim($v));
            if ($s === 'y' || $s === '1' || $s === 'yes' || $s === 'true') {
                return 1;
            }
            if ($s === '0' || $s === 'n' || $s === 'no' || $s === '') {
                return 0;
            }
        }

        return 0;
    }

    /**
     * @param mixed $value
     */
    private static function isTruthyFlag($value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }
        if (!is_string($value)) {
            return false;
        }
        $v = strtolower(trim($value));
        return $v === 'y' || $v === 'yes' || $v === 'true';
    }

    private static function getFirstMultiValue(int $contactId, string $typeId): string
    {
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
        if ($row = $res->Fetch()) {
            return trim((string)($row['VALUE'] ?? ''));
        }

        return '';
    }
}
