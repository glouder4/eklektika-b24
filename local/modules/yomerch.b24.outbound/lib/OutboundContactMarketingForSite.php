<?php

namespace OnlineService\Sync\ToSite;

use OnlineService\Sync\UfMap;

/**
 * Поля маркетингового агента в POST на сайт (UPDATE_CONTACT).
 *
 * Зеркалить логику с сайта: {@see …/bitrix-docker/www/local/sync/to-crm/OutboundUpdateContactPayload.php}
 * и {@see …/bitrix-docker/www/local/sync/from-crm/CrmInboundUfMap.php} (marketingInboundSignal*).
 */
final class OutboundContactMarketingForSite
{
    /** Канонический ключ маппинга UF контакта CRM «рекламный агент». */
    private const CONTACT_ADVERTISING_AGENT_UF_MAP_KEY = 'company.contact_marketing_agent';

    /** Значение свойства сайта `OS_IS_MARKETING_AGENT` («да»). */
    private const SITE_OS_IS_MARKETING_AGENT_YES = 2076;

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $contactRow CCrmContact::GetByID или аналог
     */
    /**
     * Признак «рекламный агент» с UF компании: Y/1, enum «да» компании, ID 2076 (сайт).
     *
     * @param mixed $raw
     */
    public static function isMarketingAgentTruthy($raw): bool
    {
        if (self::marketingAbsent($raw)) {
            return false;
        }
        if (self::marketingTrue($raw)) {
            return true;
        }
        if (self::toPositiveEnumId($raw) === self::SITE_OS_IS_MARKETING_AGENT_YES) {
            return true;
        }

        $companyUf = UfMap::get('company.is_marketing_agent');
        $companyLabel = self::tryResolveCrmEnumerationLabel('CRM_COMPANY', $companyUf, $raw);

        return $companyLabel !== null && self::marketingTrue($companyLabel);
    }

    /**
     * Прямое зеркало UF компании `company.is_marketing_agent` в `company.contact_marketing_agent`
     * (тот же enum ID / скаляр, без подстановки «да»-варианта списка контакта).
     *
     * @param mixed $raw сырьё с UF компании
     * @return mixed
     */
    public static function mirrorCompanyMarketingAgentToContactUf($raw)
    {
        if (self::marketingAbsent($raw)) {
            return '';
        }
        if (\is_array($raw)) {
            if (\array_key_exists('VALUE', $raw)) {
                return self::mirrorCompanyMarketingAgentToContactUf($raw['VALUE']);
            }
            if ($raw !== [] && \array_keys($raw) === \range(0, \count($raw) - 1)) {
                return self::mirrorCompanyMarketingAgentToContactUf($raw[0] ?? '');
            }
            $first = \reset($raw);

            return self::mirrorCompanyMarketingAgentToContactUf($first !== false ? $first : '');
        }

        return $raw;
    }

    /**
     * @deprecated Используйте {@see mirrorCompanyMarketingAgentToContactUf()}.
     *
     * @param mixed $raw
     * @return mixed
     */
    public static function resolveMarketingAgentValueForContactUf($raw)
    {
        return self::mirrorCompanyMarketingAgentToContactUf($raw);
    }

    /**
     * Полный блок маркетинга для UPDATE_CONTACT (зеркало сайта OutboundUpdateContactPayload::mergeAdvertisingMarketingFromCrmContact).
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $contactRow
     */
    public static function mergeAdvertisingMarketingFromCrmContact(array &$post, array $contactRow): void
    {
        self::mergeIntoUpdateContactPost($post, $contactRow);
        self::applyActiveAndAdvertisingAgentToPost($post, $contactRow);
    }

    public static function mergeIntoUpdateContactPost(array &$post, array $contactRow): void
    {
        $ufKey = self::contactAdvertisingAgentUf();
        $raw = self::extractCrmFieldScalar($contactRow, $ufKey);

        if (self::marketingAbsent($raw)) {
            unset($post[$ufKey], $post['IS_MARKETING_AGENT']);
        } else {
            $resolvedEnum = self::tryResolveCrmEnumerationLabel('CRM_CONTACT', $ufKey, $raw);
            $candidate = $resolvedEnum !== null ? $resolvedEnum : $raw;

            if (self::marketingTrue($candidate) || self::marketingTrue($raw)) {
                $post['IS_MARKETING_AGENT'] = 'Y';
                $post[$ufKey] = 'Y';
            } elseif (self::marketingFalse($candidate) || self::marketingFalse($raw)) {
                $post['IS_MARKETING_AGENT'] = 'N';
                $post[$ufKey] = 'N';
            } else {
                unset($post[$ufKey], $post['IS_MARKETING_AGENT']);
            }
        }

        $inheritsUfKey = UfMap::get('contact.inherits_company_is_marketing_agent');
        $inheritsRaw = self::extractCrmFieldScalar($contactRow, $inheritsUfKey);
        if (self::marketingAbsent($inheritsRaw)) {
            unset($post[$inheritsUfKey]);
        } else {
            $post[$inheritsUfKey] = self::crmScalarToActiveYn($inheritsRaw);
        }
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $contactRow
     */
    private static function applyActiveAndAdvertisingAgentToPost(array &$post, array $contactRow): void
    {
        $inheritsUfKey = UfMap::get('contact.inherits_company_is_marketing_agent');
        $inheritsRaw = self::extractCrmFieldScalar($contactRow, $inheritsUfKey);
        if (!self::marketingAbsent($inheritsRaw)) {
            $yn = self::crmScalarToActiveYn($inheritsRaw);
            $post['IS_MARKETING_AGENT'] = $yn;
            $post['ACTIVE'] = $yn;
        } elseif (
            isset($post['IS_MARKETING_AGENT'])
            && ($post['IS_MARKETING_AGENT'] === 'Y' || $post['IS_MARKETING_AGENT'] === 'N')
        ) {
            $post['ACTIVE'] = (string) $post['IS_MARKETING_AGENT'];
        } else {
            $post['ACTIVE'] = 'N';
        }

        $advUfKey = self::contactAdvertisingAgentUf();
        if (\array_key_exists($advUfKey, $post) && $post[$advUfKey] !== null && $post[$advUfKey] !== '') {
            $post['UF_ADVERTISING_AGENT'] = $post[$advUfKey];
        } else {
            $advRaw = self::extractCrmFieldScalar($contactRow, $advUfKey);
            $post['UF_ADVERTISING_AGENT'] = !self::marketingAbsent($advRaw) ? $advRaw : '';
        }
        if (($post['UF_ADVERTISING_AGENT'] ?? '') === '') {
            if (
                (isset($post['IS_MARKETING_AGENT']) && $post['IS_MARKETING_AGENT'] === 'Y')
                || ($post['ACTIVE'] ?? 'N') === 'Y'
            ) {
                $post['UF_ADVERTISING_AGENT'] = 'Y';
            }
        }
    }

    /** @param mixed $raw */
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

    private static function contactAdvertisingAgentUf(): string
    {
        return UfMap::get(self::CONTACT_ADVERTISING_AGENT_UF_MAP_KEY);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function extractCrmFieldScalar(array $row, string $key)
    {
        if (!array_key_exists($key, $row)) {
            return null;
        }

        $v = $row[$key];
        if (is_array($v)) {
            if (array_key_exists('VALUE', $v)) {
                return $v['VALUE'];
            }
            $first = reset($v);
            if (is_array($first) && array_key_exists('VALUE', $first)) {
                return $first['VALUE'];
            }
            if (is_scalar($first)) {
                return $first;
            }

            return null;
        }

        return $v;
    }

    /**
     * UF типа «список» в CRM хранится как ID варианта; приводим к VALUE/XML_ID для тех же правил, что на сайте.
     *
     * @param mixed $raw
     */
    private static function tryResolveCrmEnumerationLabel(string $entityId, string $ufKey, $raw): ?string
    {
        $enumId = self::toPositiveEnumId($raw);
        if ($enumId === null) {
            return null;
        }

        $rs = \CUserTypeEntity::GetList([], [
            'ENTITY_ID' => $entityId,
            'FIELD_NAME' => $ufKey,
        ]);
        $field = $rs->Fetch();
        if (!is_array($field)) {
            return null;
        }
        if (($field['USER_TYPE_ID'] ?? '') !== 'enumeration') {
            return null;
        }
        $userFieldId = (int)($field['ID'] ?? 0);
        if ($userFieldId <= 0) {
            return null;
        }

        $rsE = \CUserFieldEnum::GetList(['SORT' => 'ASC'], [
            'USER_FIELD_ID' => $userFieldId,
            'ID' => $enumId,
        ]);
        $row = $rsE->Fetch();
        if (!is_array($row)) {
            return null;
        }

        $xml = trim((string)($row['XML_ID'] ?? ''));
        $val = trim((string)($row['VALUE'] ?? ''));
        $combined = trim($xml . ' ' . $val);

        return $combined !== '' ? $combined : null;
    }

    private static function findYesEnumerationIdForField(string $entityId, string $ufKey): ?int
    {
        $rs = \CUserTypeEntity::GetList([], [
            'ENTITY_ID' => $entityId,
            'FIELD_NAME' => $ufKey,
        ]);
        $field = $rs->Fetch();
        if (!is_array($field) || ($field['USER_TYPE_ID'] ?? '') !== 'enumeration') {
            return null;
        }
        $userFieldId = (int)($field['ID'] ?? 0);
        if ($userFieldId <= 0) {
            return null;
        }

        $rsE = \CUserFieldEnum::GetList(['SORT' => 'ASC'], ['USER_FIELD_ID' => $userFieldId]);
        while ($row = $rsE->Fetch()) {
            if (!is_array($row)) {
                continue;
            }
            $xml = trim((string)($row['XML_ID'] ?? ''));
            $val = trim((string)($row['VALUE'] ?? ''));
            if (self::marketingTrue($xml) || self::marketingTrue($val)) {
                $id = (int)($row['ID'] ?? 0);

                return $id > 0 ? $id : null;
            }
        }

        return null;
    }

    /** @param mixed $raw */
    private static function toPositiveEnumId($raw): ?int
    {
        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }
        if (is_string($raw) && $raw !== '' && ctype_digit($raw)) {
            $n = (int) $raw;

            return $n > 0 ? $n : null;
        }

        return null;
    }

    /** @param mixed $v */
    private static function marketingAbsent($v): bool
    {
        if ($v === null) {
            return true;
        }
        if (is_string($v) && trim($v) === '') {
            return true;
        }

        return false;
    }

    /** @param mixed $v */
    private static function marketingTrue($v): bool
    {
        return $v === true || $v === 1 || $v === '1' || $v === 'Y' || $v === 'y'
            || $v === 'Да' || $v === 'да' || $v === 'on' || $v === 'ON';
    }

    /** @param mixed $v */
    private static function marketingFalse($v): bool
    {
        return $v === false || $v === 0 || $v === '0' || $v === 'N' || $v === 'n'
            || $v === 'Нет' || $v === 'нет' || $v === 'off' || $v === 'OFF';
    }
}
