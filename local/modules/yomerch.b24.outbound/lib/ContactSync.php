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
    private const HOLDING_IBLOCK_ID = 57;
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

        $sync = new self();
        $response = $sync->sendRequest([
            'ACTION' => 'DELETE_CONTACT',
            'ID' => $contactId,
            'B24_ID' => $contactId,
        ], false);

        if ((int)($response['success'] ?? 0) !== 1 || !empty($response['error'])) {
            error_log(
                '[ContactSync::onBeforeContactDelete] Site sync failed for contact '
                . $contactId
                . '; result='
                . json_encode($response, JSON_UNESCAPED_UNICODE)
            );
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
        $ufCompanyDiscount = self::uf(self::UF_COMPANY_DISCOUNT);
        $directorRaw = self::extractSingleUfValue($contact, $directorUf);

        $params = [
            'ACTION' => 'UPDATE_CONTACT',
            'B24_ID' => $contactId,
            $ufContactSiteUserId => (int)($contact[$ufContactSiteUserId] ?? 0),
            'NAME' => (string)($contact['NAME'] ?? ''),
            'LAST_NAME' => (string)($contact['LAST_NAME'] ?? ''),
            'SECOND_NAME' => (string)($contact['SECOND_NAME'] ?? ''),
            'WORK_POSITION' => (string)($contact['POST'] ?? ''),
            'PERSONAL_BIRTHDAY' => (string)($contact['BIRTHDATE'] ?? ''),
            // Ключи оставляем для совместимости с обработчиком на сайте.
            'ASSIGNED_MANAGER' => '',
            'SECOND_MANAGER' => '',
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

        // По умолчанию отправляем пустое значение: это позволяет сайту корректно очищать скидку.
        $params[$ufCompanyDiscount] = '';
        $params['OS_COMPANY_DISCOUNT_VALUE'] = '';
        $companyB24Id = self::resolvePrimaryCompanyB24Id($contactId);
        if ($companyB24Id > 0) {
            $params['OS_COMPANY_B24_ID'] = $companyB24Id;
            $discountValue = self::getCompanyDiscountValue($companyB24Id);
            $params[$ufCompanyDiscount] = $discountValue;
            // Совместимость с сайтом: часть обработчиков читает OS_* ключи.
            $params['OS_COMPANY_DISCOUNT_VALUE'] = $discountValue;
        }

        $sync = new self();
        $response = $sync->sendRequest($params, false);
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

    private static function getCompanyDiscountValue(int $companyId): string
    {
        if ($companyId <= 0) {
            return '';
        }
        global $USER_FIELD_MANAGER;
        if (is_object($USER_FIELD_MANAGER)) {
            $ufRows = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId, LANGUAGE_ID);
            $raw = $ufRows[self::uf(self::UF_COMPANY_DISCOUNT)]['VALUE'] ?? null;
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
        // Приводим десятичный разделитель к точке, оставляя формат как строку для контракта сайта.
        $value = str_replace(',', '.', $value);
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
     * В разных обработчиках CRM ID может приходить в разных ключах.
     */
    private static function extractContactIdFromArgs(array $args): int
    {
        foreach ($args as $arg) {
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
