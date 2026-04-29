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

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $contactRow CCrmContact::GetByID или аналог
     */
    public static function mergeIntoUpdateContactPost(array &$post, array $contactRow): void
    {
        $ufKey = self::contactAdvertisingAgentUf();
        $raw = self::extractCrmFieldScalar($contactRow, $ufKey);

        if (self::marketingAbsent($raw)) {
            unset($post[$ufKey], $post['IS_MARKETING_AGENT']);

            return;
        }

        $resolvedEnum = self::tryResolveCrmContactEnumerationLabel($ufKey, $raw);
        $candidate = $resolvedEnum !== null ? $resolvedEnum : $raw;

        if (self::marketingTrue($candidate) || self::marketingTrue($raw)) {
            $post['IS_MARKETING_AGENT'] = 'Y';
            $post[$ufKey] = 'Y';

            return;
        }

        if (self::marketingFalse($candidate) || self::marketingFalse($raw)) {
            $post['IS_MARKETING_AGENT'] = 'N';
            $post[$ufKey] = 'N';

            return;
        }

        unset($post[$ufKey], $post['IS_MARKETING_AGENT']);
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
    private static function tryResolveCrmContactEnumerationLabel(string $ufKey, $raw): ?string
    {
        $enumId = self::toPositiveEnumId($raw);
        if ($enumId === null) {
            return null;
        }

        $rs = \CUserTypeEntity::GetList([], [
            'ENTITY_ID' => 'CRM_CONTACT',
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
