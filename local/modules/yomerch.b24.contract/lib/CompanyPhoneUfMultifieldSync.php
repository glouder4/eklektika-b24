<?php

declare(strict_types=1);

namespace OnlineService\Sync\Contract;

use OnlineService\Sync\UfMap;

/**
 * Взаимная синхронизация рабочего/мобильного телефона: UF компании ↔ multifield PHONE (WORK/MOBILE).
 * Bitrix CRM сам не связывает эти поля; без этого в outbound/webhook уходит только последняя строка PHONE.
 */
final class CompanyPhoneUfMultifieldSync
{
    /**
     * Перед сохранением карточки: согласовать UF и PHONE[] в $arFields.
     *
     * @param array<string, mixed> $arFields
     */
    public static function enrichArFieldsBeforeSave(int $companyId, array &$arFields): void
    {
        if ($companyId <= 0 || !\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }

        $ufWork = UfMap::get('company.legan_main_phone');
        $ufMobile = UfMap::get('company.legan_mobile_phone');

        $read = new CompanySyncReadService();
        $snapshot = $read->loadCompanySnapshot($companyId);

        [$work, $mobile] = self::resolveCanonical($snapshot, $arFields, $ufWork, $ufMobile);

        if ($work !== '') {
            $arFields[$ufWork] = $work;
        }
        if ($mobile !== '') {
            $arFields[$ufMobile] = $mobile;
        }

        $phoneFm = self::buildPhoneMultifieldForCrmUpdate($snapshot, $work, $mobile);
        if ($phoneFm !== []) {
            $arFields['PHONE'] = $phoneFm;
        }
    }

    /**
     * После сохранения: если в БД UF и PHONE[] всё ещё расходятся — дописать в CRM (без повторного outbound).
     */
    public static function syncStoredCompanyIfNeeded(int $companyId): void
    {
        if ($companyId <= 0 || !\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }

        $ufWork = UfMap::get('company.legan_main_phone');
        $ufMobile = UfMap::get('company.legan_mobile_phone');

        $read = new CompanySyncReadService();
        $snapshot = $read->loadCompanySnapshot($companyId);
        if ($snapshot === []) {
            return;
        }

        [$work, $mobile] = self::resolveCanonical($snapshot, [], $ufWork, $ufMobile);

        $fields = [];
        $storedWork = self::trimScalar($snapshot[$ufWork] ?? '');
        $storedMobile = self::trimScalar($snapshot[$ufMobile] ?? '');
        [$storedMfWork, $storedMfMobile] = self::pickWorkAndMobileFromRows(
            self::phoneRowsFromMultifields($snapshot['MULTIFIELDS'] ?? [])
        );

        if ($work !== '' && $work !== $storedWork) {
            $fields[$ufWork] = $work;
        }
        if ($mobile !== '' && $mobile !== $storedMobile) {
            $fields[$ufMobile] = $mobile;
        }

        $phoneFm = self::buildPhoneMultifieldForCrmUpdate($snapshot, $work, $mobile);
        $mfChanged = ($work !== '' && $work !== $storedMfWork) || ($mobile !== '' && $mobile !== $storedMfMobile);
        if ($phoneFm !== [] && $mfChanged) {
            $fields['PHONE'] = $phoneFm;
        }

        if ($fields === []) {
            return;
        }

        if (\class_exists(\OnlineService\Sync\ToSite\CompanySync::class)) {
            \OnlineService\Sync\ToSite\CompanySync::markInboundCompanyUpdate($companyId);
        }

        $entity = new \CCrmCompany(false);
        $entity->Update($companyId, $fields);
    }

    /**
     * Для исходящего payload / webhook: PHONE[] (оба типа) + зеркало в UF-ключах.
     *
     * @param array<string, mixed> $company snapshot из {@see CompanySyncReadService}
     * @return array{work: string, mobile: string, crm_multifields: array<string, mixed>, uf: array<string, string>}
     */
    public static function resolveForOutbound(array $company): array
    {
        $ufWork = UfMap::get('company.legan_main_phone');
        $ufMobile = UfMap::get('company.legan_mobile_phone');

        [$work, $mobile] = self::resolveCanonical($company, [], $ufWork, $ufMobile);

        $phoneList = [];
        if ($work !== '') {
            $phoneList[] = ['VALUE' => $work, 'VALUE_TYPE' => 'WORK'];
        }
        if ($mobile !== '') {
            $phoneList[] = ['VALUE' => $mobile, 'VALUE_TYPE' => 'MOBILE'];
        }

        return [
            'work' => $work,
            'mobile' => $mobile,
            'crm_multifields' => $phoneList === [] ? [] : ['PHONE' => $phoneList],
            'uf' => [
                $ufWork => $work,
                $ufMobile => $mobile,
                'LEGAN_MAIN_PHONE' => $work,
                'LEGAN_MOBILE_PHONE' => $mobile,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $arFields
     * @return array{0: string, 1: string}
     */
    private static function resolveCanonical(array $snapshot, array $arFields, string $ufWork, string $ufMobile): array
    {
        $ufWorkPending = self::trimScalar($arFields[$ufWork] ?? null);
        $ufMobilePending = self::trimScalar($arFields[$ufMobile] ?? null);
        $mfPending = self::phoneRowsFromArFields($arFields);
        [$mfWorkPending, $mfMobilePending] = self::pickWorkAndMobileFromRows($mfPending);

        $ufWorkStored = self::trimScalar($snapshot[$ufWork] ?? null);
        $ufMobileStored = self::trimScalar($snapshot[$ufMobile] ?? null);
        [$mfWorkStored, $mfMobileStored] = self::pickWorkAndMobileFromRows(
            self::phoneRowsFromMultifields($snapshot['MULTIFIELDS'] ?? [])
        );

        $work = $ufWorkPending !== '' ? $ufWorkPending : ($mfWorkPending !== '' ? $mfWorkPending : $ufWorkStored);
        if ($work === '' && $mfWorkStored !== '') {
            $work = $mfWorkStored;
        }

        $mobile = $ufMobilePending !== '' ? $ufMobilePending : ($mfMobilePending !== '' ? $mfMobilePending : $ufMobileStored);
        if ($mobile === '' && $mfMobileStored !== '') {
            $mobile = $mfMobileStored;
        }

        if ($work === '' && $ufWorkStored !== '') {
            $work = $ufWorkStored;
        }
        if ($mobile === '' && $ufMobileStored !== '') {
            $mobile = $ufMobileStored;
        }

        return [$work, $mobile];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return list<array<string, mixed>>
     */
    private static function buildPhoneMultifieldForCrmUpdate(array $snapshot, string $work, string $mobile): array
    {
        if ($work === '' && $mobile === '') {
            return [];
        }

        $out = [];
        foreach (self::phoneRowsFromMultifields($snapshot['MULTIFIELDS'] ?? []) as $row) {
            $id = (int) ($row['ID'] ?? 0);
            if ($id > 0) {
                $out[] = ['ID' => $id, 'DELETE' => 'Y'];
            }
        }
        if ($work !== '') {
            $out[] = ['VALUE' => $work, 'VALUE_TYPE' => 'WORK'];
        }
        if ($mobile !== '') {
            $out[] = ['VALUE' => $mobile, 'VALUE_TYPE' => 'MOBILE'];
        }

        return $out;
    }

    /**
     * @param mixed $multifields
     * @return list<array<string, mixed>>
     */
    public static function phoneRowsFromMultifields($multifields): array
    {
        if (!\is_array($multifields)) {
            return [];
        }
        $raw = $multifields['PHONE'] ?? null;
        if ($raw === null || $raw === '') {
            return [];
        }
        if (\is_array($raw) && (isset($raw['VALUE']) || isset($raw['ID']))) {
            return [$raw];
        }
        if (!\is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (\is_array($row) && (isset($row['VALUE']) || isset($row['ID']))) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $arFields
     * @return list<array<string, mixed>>
     */
    private static function phoneRowsFromArFields(array $arFields): array
    {
        if (!isset($arFields['PHONE']) || !\is_array($arFields['PHONE'])) {
            return [];
        }
        $raw = $arFields['PHONE'];
        if (isset($raw['VALUE']) || isset($raw['ID'])) {
            return [$raw];
        }
        $out = [];
        foreach ($raw as $row) {
            if (\is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{0: string, 1: string}
     */
    private static function pickWorkAndMobileFromRows(array $rows): array
    {
        $work = '';
        $mobile = '';
        foreach ($rows as $row) {
            if (!empty($row['DELETE']) || !empty($row['delete'])) {
                continue;
            }
            $v = self::trimScalar($row['VALUE'] ?? '');
            if ($v === '') {
                continue;
            }
            $type = \strtoupper(self::trimScalar($row['VALUE_TYPE'] ?? 'WORK'));
            if ($type === 'MOBILE') {
                if ($mobile === '') {
                    $mobile = $v;
                }
            } elseif ($work === '') {
                $work = $v;
            }
        }

        return [$work, $mobile];
    }

    private static function trimScalar(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (\is_scalar($v)) {
            return \trim((string) $v);
        }
        if (\is_array($v) && \array_key_exists('VALUE', $v)) {
            return self::trimScalar($v['VALUE']);
        }

        return '';
    }
}
