<?php

namespace OnlineService\Sync\ToSite;

use OnlineService\Sync\UfMap;
use OnlineService\Sync\Contract\CompanySyncNormalizeService;
use OnlineService\Sync\Contract\CompanySyncPolicyService;
use OnlineService\Sync\Contract\CompanySyncReadService;

// UF-карта: модуль yomerch.b24.contract → lib/config/uf_mapping.php.
class CompanySync extends OutboundRequest
{
    private const HOLDING_IBLOCK_ID = 57;
    private const UF_COMPANY_HOLDING = 'company.holding';
    private const UF_COMPANY_DISCOUNT = 'company.discount';
    private const UF_COMPANY_HOLDING_COMPANIES = 'company.holding_companies';
    private const UF_COMPANY_IS_HEAD = 'company.is_head';
    /** ID пользователя на сайте, хранится в UF контакта CRM */
    private const UF_CONTACT_SITE_USER_ID = 'contact.site_user_id';
    /** UF компании CRM → на сайте LEGAN_MAIN_PHONE (только в UPDATE_COMPANY) */
    private const UF_COMPANY_LEGAN_MAIN_PHONE = 'company.legan_main_phone';
    /** UF компании CRM → на сайте LEGAN_MOBILE_PHONE */
    private const UF_COMPANY_LEGAN_MOBILE_PHONE = 'company.legan_mobile_phone';
    /** ИБ 16: головной холдинг — коды свойств (см. настройки инфоблока) */
    private const IBLOCK16_PROP_B24 = 'B24_COMPANY_ID';
    /** «Является головной» — ИБ 16 (код должен совпадать с админкой: IS_HEAD_*, CHILDREN_*) */
    private const IBLOCK16_PROP_IS_HEAD = 'IS_HEAD_COMPANIES';
    private const IBLOCK16_PROP_CHILDREN = 'CHILDREN_COMPANIES';
    /** Enum «головная» (IS_HEAD_*) */
    private const IBLOCK16_IS_HEAD_ENUM_ID = 56;
    /** @var array<int, bool> */
    private static array $skipOutboundForCompanyIds = [];

    public static function markInboundCompanyUpdate(int $companyId): void
    {
        if ($companyId > 0) {
            self::$skipOutboundForCompanyIds[$companyId] = true;
        }
    }

    private static function unmarkInboundCompanyUpdate(int $companyId): void
    {
        if ($companyId > 0 && isset(self::$skipOutboundForCompanyIds[$companyId])) {
            unset(self::$skipOutboundForCompanyIds[$companyId]);
        }
    }

    private static function shouldSkipOutbound(int $companyId): bool
    {
        if ($companyId <= 0) {
            return false;
        }
        if (!isset(self::$skipOutboundForCompanyIds[$companyId])) {
            return false;
        }
        unset(self::$skipOutboundForCompanyIds[$companyId]);

        return true;
    }

    private static function uf(string $key): string
    {
        return UfMap::get($key);
    }

    /**
     * Возвращает первое строковое значение MULTIFIELDS по типу (PHONE/EMAIL/WEB).
     * Поддерживает как плоский формат ['TYPE' => ['VALUE' => '...']], так и список записей.
     */
    private static function extractMultifieldString(array $company, string $typeId): string
    {
        $multi = $company['MULTIFIELDS'][$typeId] ?? null;
        if ($multi === null || $multi === '') {
            return '';
        }

        if (\is_array($multi) && \array_key_exists('VALUE', $multi)) {
            return (string)($multi['VALUE'] ?? '');
        }

        if (\is_array($multi)) {
            foreach ($multi as $row) {
                if (\is_array($row) && \array_key_exists('VALUE', $row)) {
                    return (string)($row['VALUE'] ?? '');
                }
            }
        }

        return '';
    }

    /**
     * Компания в CRM обновляется: валидация + отправка минимального события на сайт.
     */
    public static function onBeforeCompanyAdd(&$arFields): bool
    {
        if (!\is_array($arFields)) {
            return true;
        }

        // Повторно используем ту же валидацию и защитные ограничения, что и для update.
        return self::onBeforeCompanyUpdate($arFields);
    }

    /**
     * Компания в CRM обновляется: валидация + отправка минимального события на сайт.
     */
    public static function onBeforeCompanyUpdate(&$arFields): bool
    {
        $companyId = (int)($arFields['ID'] ?? 0);
        if ($companyId <= 0) {
            return true;
        }

        global $USER_FIELD_MANAGER;
        $ufValues = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId);
        $ufCompanyIsHead = self::uf(self::UF_COMPANY_IS_HEAD);
        $ufCompanyHolding = self::uf(self::UF_COMPANY_HOLDING);
        $policyService = new CompanySyncPolicyService();
        $policy = $policyService->validateHeadHoldingTransition($ufValues, $arFields, $ufCompanyIsHead, $ufCompanyHolding);
        $wasHeadCompany = (bool)($policy['was_head'] ?? false);
        $willBeHeadCompany = (bool)($policy['will_head'] ?? false);

        if (!(bool)($policy['ok'] ?? false)) {
            $arFields['RESULT_MESSAGE'] = (string)($policy['message'] ?? 'Компания не может быть головной И входить в другой холдинг');
            return false;
        }

        if ($wasHeadCompany && !$willBeHeadCompany) {
            $headElementId = self::findHeadElementInIblock($companyId);
            $hasChildCompanies = $headElementId ? self::checkForChildCompanies($headElementId) : false;

            if ($hasChildCompanies) {
                $arFields['RESULT_MESSAGE'] = 'Нельзя убрать флаг головной компании. У компании есть дочерние филиалы.';
                return false;
            }

            $holdingElementId = self::findHeadElementInIblock($companyId);
            if ($holdingElementId > 0) {
                $el = new \CIBlockElement();
                $el->Update($holdingElementId, ['ACTIVE' => 'N']);
            }
        }

        if ($willBeHeadCompany) {
            $holdingElementId = self::findHeadElementInIblock($companyId);
            if ($holdingElementId > 0) {
                $el = new \CIBlockElement();
                $el->Update($holdingElementId, ['ACTIVE' => 'Y']);
            }
        }

        return true;
    }

    public static function onAfterCompanyUpdate(&$arFields): bool
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return true;
        }

        \Bitrix\Crm\Settings\CompanySettings::getCurrent()->setFactoryEnabled(true);

        $companyId = (int)($arFields['ID'] ?? 0);
        self::writeOutboundTrace('CompanySync::onAfterCompanyUpdate', [
            'company_id' => $companyId,
            'arFields_keys_sample' => \array_slice(\array_keys($arFields), 0, 25),
        ]);
        if ($companyId <= 0) {
            self::writeOutboundTrace('CompanySync::onAfterCompanyUpdate bail_no_id', []);

            return true;
        }
        if (self::shouldSkipOutbound($companyId)) {
            self::writeOutboundTrace('CompanySync::onAfterCompanyUpdate skip_outbound_once', [
                'company_id' => $companyId,
            ]);

            return true;
        }

        $readService = new CompanySyncReadService();
        $company = $readService->loadCompanySnapshot($companyId);
        if (!$company) {
            self::writeOutboundTrace('CompanySync::onAfterCompanyUpdate bail_no_company_row', [
                'company_id' => $companyId,
            ]);

            return true;
        }
        self::writeOutboundTrace('CompanySync::phaseA_read_snapshot', [
            'company_id' => $companyId,
            'has_rq_inn' => !empty($company['REQUISITES']['RQ_INN']),
            'has_multifields' => !empty($company['MULTIFIELDS']),
        ]);

        $ufCompanyIsHead = self::uf(self::UF_COMPANY_IS_HEAD);
        $ufCompanyHolding = self::uf(self::UF_COMPANY_HOLDING);
        $ufCompanyDiscount = self::uf(self::UF_COMPANY_DISCOUNT);
        $ufCompanyHoldingCompanies = self::uf(self::UF_COMPANY_HOLDING_COMPANIES);
        $isHead = self::isTruthy($company[$ufCompanyIsHead] ?? null);

        $headCompanyIblockId = false;
        if ($isHead) {
            $headCompanyIblockId = self::createHoldingHeadCompany($companyId, (string)($arFields['TITLE'] ?? $company['TITLE']));
        }

        $holdingIblockRef = self::resolveHoldingElementId(
            self::extractHoldingRefValue($company[$ufCompanyHolding] ?? null)
        );
        if (!$isHead && $holdingIblockRef > 0) {
            self::refreshHoldingIblockElementChildrenList($holdingIblockRef);
        }
        if (
            $isHead
            || array_key_exists($ufCompanyHolding, $arFields)
            || array_key_exists($ufCompanyIsHead, $arFields)
        ) {
            self::syncHoldingCompaniesBindingField($companyId, $company);
        }
        if (
            $isHead
            && array_key_exists($ufCompanyDiscount, $arFields)
        ) {
            $discountValue = (string)($arFields[$ufCompanyDiscount] ?? $company[$ufCompanyDiscount] ?? '');
            self::propagateHeadDiscountToChildrenAndContacts($companyId, $discountValue);
        }

        $companyUsers = $arFields['CONTACT_BINDINGS'] ?? [];
        if (!is_array($companyUsers) || empty($companyUsers)) {
            $companyUsers = self::loadCompanyContactBindings($companyId);
        }
        $contactIds = is_array($companyUsers) ? array_values(array_filter(array_map(static function ($item) {
            return (int)($item['CONTACT_ID'] ?? 0);
        }, $companyUsers))) : [];
        $contactIds = array_values(array_unique($contactIds));

        $ufCompanyIsMarketingAgent = self::uf('company.is_marketing_agent');
        $ufCompanyRequisitesFile = self::uf('company.requisites_file');
        $ufCompanySiteElementId = self::uf('company.site_element_id');
        $ufCompanySiteElementIdLegacy = self::uf('company.site_element_id_legacy_alias');
        $ufCompanyWeb = self::uf('company.web');
        $ufCompanyCity = self::uf('company.city');
        $ufCompanyBusinessScope = self::uf('company.business_scope');
        $ufCompanyLegalAddress = self::uf('company.legal_address');
        $isMarketingAgentRaw = $company[$ufCompanyIsMarketingAgent] ?? null;
        //$companyStatusId = $arFields['company.status'] ?? null;
        $reqFileId = (int)($arFields[$ufCompanyRequisitesFile] ?? 0);
        $fileInfo = $reqFileId > 0 ? \CFile::GetFileArray($reqFileId) : null;

        /** ID элемента на сайте (см. контракт inbound site_element_id); без него сайт не может сопоставить сущность. */
        $normalizeService = new CompanySyncNormalizeService();
        $normalizedCompany = $normalizeService->normalizeForOutbound($company, $arFields, [
            'company_city' => $ufCompanyCity,
            'company_web' => $ufCompanyWeb,
            'company_business_scope' => $ufCompanyBusinessScope,
            'company_legal_address' => $ufCompanyLegalAddress,
            'company_site_element_id' => $ufCompanySiteElementId,
        ]);
        self::writeOutboundTrace('CompanySync::phaseA_normalize_shadow', [
            'company_id' => $companyId,
            'site_element_id_present' => $normalizedCompany['site_element_id'] !== '',
        ]);

        $siteElementId = (string)$normalizedCompany['site_element_id'];
        $inn = (string)$normalizedCompany['inn'];
        $title = (string)$normalizedCompany['title'];
        $phone = (string)($company['MULTIFIELDS']['PHONE']['VALUE'] ?? '');
        $email = (string)($company['MULTIFIELDS']['EMAIL']['VALUE'] ?? '');
        $web = (string)$normalizedCompany['web'];
        $city = (string)$normalizedCompany['city'];
        $businessScope = (string)$normalizedCompany['business_scope'];
        $legalAddress = (string)$normalizedCompany['legal_address'];
        $resolvedHoldingElementId = self::resolveHoldingElementId(
            self::extractHoldingRefValue($company[$ufCompanyHolding] ?? null)
        );
        $holdingCompaniesBinding = self::resolveHoldingCompanyB24Ids($companyId, $company);
        $holdingCompaniesBindingPayload = self::formatHoldingChildMultival($holdingCompaniesBinding);
        $headList = ['VALUE' => $isHead ? 2074 : false];

        // OS_COMPANY_USERS / LEGAN_ENTITY_USERS — ID пользователей на сайте, не CRM CONTACT_ID.
        $companySiteUserIds = self::buildSiteUserIdsForContacts($contactIds);
        $leganPhones = self::leganPhoneFieldsFromCompany($company);

        // Сайт (updateCompanyElement) подмешивает только ключи из $codeProps — префикс OS_*.
        // Раньше уходили в основном LEGAN_ENTITY_* без OS_* → свойства элемента не обновлялись.
        $params = \array_merge(
            [
                'ACTION' => 'UPDATE_COMPANY',
                'CONTACT_IDS' => $contactIds,
                'OS_COMPANY_B24_ID' => $companyId,
                $ufCompanySiteElementId => $siteElementId,
                // @deprecated temporary alias for legacy site handlers; remove after full cutover.
                $ufCompanySiteElementIdLegacy => $siteElementId,
                'OS_COMPANY_NAME' => $title,
                'OS_COMPANY_IS_HEAD_OF_HOLDING' => $headList,
                'OS_HOLDING_OF' => self::getHoldingOfBitrixId($resolvedHoldingElementId),
                'OS_HEAD_COMPANY_B24_ID' => $isHead ? $headCompanyIblockId : false,
                'OS_COMPANY_USERS' => $companySiteUserIds,
                'LEGAN_ENTITY_USERS' => $companySiteUserIds,
                'OS_COMPANY_INN' => $inn,
                'OS_COMPANY_CITY' => $city,
                $ufCompanyCity => $city,
                'OS_COMPANY_DISCOUNT_VALUE' => $arFields[$ufCompanyDiscount] ?? '',
                'OS_COMPANY_WEB_SITE' => $web,
                $ufCompanyWeb => $web,
                $ufCompanyBusinessScope => $businessScope,
                $ufCompanyLegalAddress => $legalAddress,
                'OS_COMPANY_HOLDING_COMPANIES' => $holdingCompaniesBindingPayload === [] ? false : $holdingCompaniesBindingPayload,
                $ufCompanyHoldingCompanies => $holdingCompaniesBindingPayload === [] ? false : $holdingCompaniesBindingPayload,
                'OS_COMPANY_PHONE' => $phone,
                'OS_COMPANY_EMAIL' => $email,
                'OS_IS_MARKETING_AGENT' => ['VALUE' => self::isTruthy($isMarketingAgentRaw) ? 2076 : false],
                'ACTIVE' => self::isTruthy($company[$ufCompanyIsMarketingAgent] ?? null) ? 'Y' : 'N',
            ],
            $leganPhones
        );

        // Сайт ожидает полный CFile::GetFileArray (в т.ч. SRC) для оригинала/скачивания, не только ID.
        if (!empty($fileInfo) && \is_array($fileInfo)) {
            $params['OS_REQUSITES_FILE'] = $fileInfo;
        }

        $paramKeys = \array_keys($params);
        \sort($paramKeys, \SORT_STRING);
        self::writeOutboundTrace('CompanySync::outbound_payload', [
            'company_id' => $companyId,
            'has_site_element_id' => $siteElementId !== '',
            'YOMERRCH24_SITE_URL' => \defined('YOMERRCH24_SITE_URL') ? \YOMERRCH24_SITE_URL : '(undefined)',
            'param_keys' => $paramKeys,
            'title_len' => \strlen($title),
            'inn_len' => \strlen($inn),
        ]);

        // Сначала UPDATE_COMPANY, затем правки контактов без исходящих событий, затем явный UPDATE_CONTACT.
        // Иначе при sync_debug первый UPDATE_CONTACT обрывает выполнение до UPDATE_COMPANY.
        // Если UPDATE_COMPANY не шлём (пустой site_element_id), в sync_debug с дампом curl останется только sendRequest по контакту — см. pre() ниже.
        ContactSync::suspendOutbound(true);

        if ($siteElementId === '') {
            self::writeOutboundTrace('CompanySync::outbound skip_update_company_no_site_element_id', [
                'company_id' => $companyId,
            ]);
            if (self::isSiteSyncDebugEnabled() && \function_exists('pre')) {
                pre(
                    '=== CompanySync: UPDATE_COMPANY не отправлен: пустой ' . $ufCompanySiteElementId . ' (ID элемента на сайте). '
                    . 'Дальше в дампе curl будет только UPDATE_CONTACT, если вызывается. company_id='
                    . $companyId
                    . ' ==='
                );
            }
        } else {
            $sync = new self();
            $updateCompanyResult = $sync->sendRequest($params, false);
            if ((int)($updateCompanyResult['success'] ?? 0) !== 1 || !empty($updateCompanyResult['error'])) {
                self::writeOutboundTrace('CompanySync::update_company.failed', [
                    'company_id' => $companyId,
                    'http_status' => (int)($updateCompanyResult['http_status'] ?? 0),
                    'error_code' => (string)($updateCompanyResult['error_code'] ?? ''),
                    'reason_code' => (string)($updateCompanyResult['reason_code'] ?? ''),
                    'retryable' => !empty($updateCompanyResult['retryable']),
                    'outcome' => (string)($updateCompanyResult['outcome'] ?? ''),
                ]);
                error_log(
                    '[CompanySync::onAfterCompanyUpdate] UPDATE_COMPANY sync failed for company '
                    . $companyId
                    . '; result='
                    . json_encode($updateCompanyResult, JSON_UNESCAPED_UNICODE)
                );
            }
        }

        try {
            foreach ($contactIds as $contactId) {
                $contactFields = [
                    self::uf('company.contact_marketing_agent') => $isMarketingAgentRaw,
                ];
                $bCompare = true;
                $updateOptions = [
                    'CURRENT_USER' => \CCrmSecurityHelper::GetCurrentUserID(),
                    'IS_SYSTEM_ACTION' => false,
                    'ENABLE_DUP_INDEX_INVALIDATION' => true,
                    'REGISTER_SONET_EVENT' => true,
                    'DISABLE_USER_FIELD_CHECK' => false,
                    'DISABLE_REQUIRED_USER_FIELD_CHECK' => false,
                ];

                $contactEntity = new \CCrmContact(true);
                $contactEntity->Update(
                    $contactId,
                    $contactFields,
                    $bCompare,
                    $updateOptions
                );
            }
        } finally {
            ContactSync::suspendOutbound(false);
        }

        foreach ($contactIds as $contactId) {
            if ($contactId > 0) {
                ContactSync::sendContactToSiteNow($contactId);
            }
        }

        return true;
    }

    /**
     * Создание компании: та же ветка, что и onAfterCompanyUpdate (головняк ИБ 16, сайт, контакты).
     */
    public static function onAfterCompanyAdd(&$arFields): bool
    {
        if (!\is_array($arFields)) {
            return true;
        }

        return self::onAfterCompanyUpdate($arFields);
    }

    /**
     * Удаление компании в CRM: синхронно пробрасываем удаление на сайт (ИБ 23).
     */
    public static function onBeforeCompanyDelete(int $companyId): bool
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return true;
        }

        // Для головной/дочерней компании синхронизируем состояние ИБ16 до удаления в CRM.
        if (!self::deleteHoldingHeadElementOnCompanyDelete($companyId)) {
            error_log(
                '[CompanySync::onBeforeCompanyDelete] Abort CRM delete: failed to sync IB16 state for company '
                . $companyId
            );
            return false;
        }

        $sync = new self();
        $result = $sync->sendRequest([
            'ACTION' => 'DELETE_COMPANY',
            'ID' => $companyId,
        ], false);

        if ((int)($result['success'] ?? 0) === 1 && empty($result['error'])) {
            return true;
        }

        // Fail-open: не блокируем удаление компании в CRM, если сайт временно недоступен
        // или вернул ошибку синхронизации.
        error_log(
            '[CompanySync::onBeforeCompanyDelete] Site sync failed for company '
            . $companyId
            . '; result='
            . json_encode($result, JSON_UNESCAPED_UNICODE)
        );

        return true;
    }

    private static function deleteHoldingHeadElementOnCompanyDelete(int $companyId): bool
    {
        if ($companyId <= 0 || !\Bitrix\Main\Loader::includeModule('crm')) {
            return true;
        }
        global $USER_FIELD_MANAGER;
        if (!\is_object($USER_FIELD_MANAGER)) {
            return true;
        }

        $ufValues = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId);
        $ufCompanyIsHead = self::uf(self::UF_COMPANY_IS_HEAD);
        $ufCompanyHolding = self::uf(self::UF_COMPANY_HOLDING);
        $isHead = self::isTruthy($ufValues[$ufCompanyIsHead]['VALUE'] ?? null);
        $holdingRef = (int)($ufValues[$ufCompanyHolding]['VALUE'] ?? 0);

        // Если удаляем дочернюю компанию — убираем ID из children у родительского элемента сразу.
        if ($holdingRef > 0) {
            if (!self::removeChildCompanyFromHoldingElement($holdingRef, $companyId)) {
                return false;
            }
        }
        if (!$isHead) {
            return true;
        }

        $headElementId = (int)self::findHeadElementInIblock($companyId);
        if ($headElementId <= 0) {
            return true;
        }

        // Перед удалением головного элемента отвязываем дочерние компании от холдинга.
        if (!self::clearHoldingReferenceForChildren($headElementId)) {
            return false;
        }

        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            return true;
        }

        if (!\CIBlockElement::Delete($headElementId)) {
            error_log(
                '[CompanySync::deleteHoldingHeadElementOnCompanyDelete] Failed to delete iblock head element '
                . $headElementId
                . ' for company '
                . $companyId
            );
            return false;
        }

        self::writeOutboundTrace('CompanySync::holding_iblock_delete', [
            'iblock_element_id' => $headElementId,
            'b24_company_id' => $companyId,
        ]);
        return true;
    }

    private static function removeChildCompanyFromHoldingElement(int $holdingElementId, int $companyId): bool
    {
        if ($holdingElementId <= 0 || $companyId <= 0 || !\Bitrix\Main\Loader::includeModule('iblock')) {
            return true;
        }
        $current = self::getChildB24IdsLinkedToHoldingIblock($holdingElementId);
        if ($current === []) {
            return true;
        }
        $filtered = array_values(array_filter($current, static function (int $id) use ($companyId): bool {
            return $id !== $companyId;
        }));
        $fmt = self::formatHoldingChildMultival($filtered);
        \CIBlockElement::SetPropertyValuesEx(
            $holdingElementId,
            self::HOLDING_IBLOCK_ID,
            [self::IBLOCK16_PROP_CHILDREN => $fmt === [] ? false : $fmt]
        );
        return true;
    }

    private static function clearHoldingReferenceForChildren(int $holdingElementId): bool
    {
        if ($holdingElementId <= 0 || !\Bitrix\Main\Loader::includeModule('crm')) {
            return true;
        }
        $childCompanyIds = self::getChildB24IdsLinkedToHoldingIblock($holdingElementId);
        if ($childCompanyIds === []) {
            return true;
        }
        $companyEntity = new \CCrmCompany(false);
        $detachedChildCompanyIds = [];
        foreach ($childCompanyIds as $childCompanyId) {
            if ($childCompanyId <= 0) {
                continue;
            }
            $ok = $companyEntity->Update(
                $childCompanyId,
                [self::uf(self::UF_COMPANY_HOLDING) => false],
                true,
                true,
                [
                    'CURRENT_USER' => \CCrmSecurityHelper::GetCurrentUserID(),
                    'IS_SYSTEM_ACTION' => true,
                ]
            );
            if (!$ok) {
                foreach ($detachedChildCompanyIds as $rollbackChildCompanyId) {
                    $companyEntity->Update(
                        $rollbackChildCompanyId,
                        [self::uf(self::UF_COMPANY_HOLDING) => $holdingElementId],
                        true,
                        true,
                        [
                            'CURRENT_USER' => \CCrmSecurityHelper::GetCurrentUserID(),
                            'IS_SYSTEM_ACTION' => true,
                        ]
                    );
                }
                error_log(
                    '[CompanySync::clearHoldingReferenceForChildren] Failed to detach child company '
                    . $childCompanyId
                    . ' from holding element '
                    . $holdingElementId
                );
                return false;
            }
            $detachedChildCompanyIds[] = $childCompanyId;
        }
        return true;
    }

    private static function propagateHeadDiscountToChildrenAndContacts(int $headCompanyId, string $discountValue): void
    {
        if ($headCompanyId <= 0 || !\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }
        $headElementId = (int)self::findHeadElementInIblock($headCompanyId);
        if ($headElementId <= 0) {
            return;
        }
        $childCompanyIds = self::getChildB24IdsLinkedToHoldingIblock($headElementId);
        if ($childCompanyIds === []) {
            return;
        }

        $companyEntity = new \CCrmCompany(false);
        $successfulCompanyIdsForContacts = [$headCompanyId];
        foreach ($childCompanyIds as $childCompanyId) {
            $childCompanyId = (int)$childCompanyId;
            if ($childCompanyId <= 0 || $childCompanyId === $headCompanyId) {
                continue;
            }
            // Технический каскад скидки не должен повторно запускать outbound для дочерней компании.
            self::markInboundCompanyUpdate($childCompanyId);
            $fields = [self::uf(self::UF_COMPANY_DISCOUNT) => $discountValue];
            $ok = $companyEntity->Update(
                $childCompanyId,
                $fields,
                true,
                true,
                [
                    'CURRENT_USER' => \CCrmSecurityHelper::GetCurrentUserID(),
                    'IS_SYSTEM_ACTION' => true,
                ]
            );
            if (!$ok) {
                // Если скидка уже совпадает с head-значением, всё равно нужно обновить сотрудников дочерней на сайте.
                if (self::isCompanyDiscountEqual($childCompanyId, $discountValue)) {
                    // Update не создал CRM-событие — снимаем флаг skip, чтобы не поглотить следующий реальный outbound.
                    self::unmarkInboundCompanyUpdate($childCompanyId);
                    error_log(
                        '[CompanySync::propagateHeadDiscountToChildrenAndContacts] Child company discount update returned false, '
                        . 'but discount already equal; continue contact sync. child='
                        . $childCompanyId
                        . '; head='
                        . $headCompanyId
                    );
                    $successfulCompanyIdsForContacts[] = $childCompanyId;
                    continue;
                }
                error_log(
                    '[CompanySync::propagateHeadDiscountToChildrenAndContacts] Failed to update discount for child company '
                    . $childCompanyId
                    . ' from head company '
                    . $headCompanyId
                );
                // Update не произошёл — снимаем skip-флаг, чтобы не блокировать следующий реальный outbound.
                self::unmarkInboundCompanyUpdate($childCompanyId);
                continue;
            }
            $successfulCompanyIdsForContacts[] = $childCompanyId;
        }

        $contactIds = [];
        foreach (array_values(array_unique($successfulCompanyIdsForContacts)) as $companyIdForContacts) {
            foreach (self::loadCompanyContactBindings((int)$companyIdForContacts) as $binding) {
                $contactId = (int)($binding['CONTACT_ID'] ?? 0);
                if ($contactId > 0) {
                    $contactIds[] = $contactId;
                }
            }
        }

        foreach (array_values(array_unique($contactIds)) as $contactId) {
            ContactSync::sendContactToSiteNow((int)$contactId);
        }
    }

    private static function isCompanyDiscountEqual(int $companyId, string $expectedDiscount): bool
    {
        if ($companyId <= 0) {
            return false;
        }
        global $USER_FIELD_MANAGER;
        if (\is_object($USER_FIELD_MANAGER)) {
            $ufRows = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId, LANGUAGE_ID);
            $raw = $ufRows[self::uf(self::UF_COMPANY_DISCOUNT)]['VALUE'] ?? null;
            if ($raw !== null) {
                return (string)$raw === $expectedDiscount;
            }
        }
        $company = \CCrmCompany::GetByID($companyId, false);
        if (!\is_array($company)) {
            return false;
        }
        return (string)($company[self::uf(self::UF_COMPANY_DISCOUNT)] ?? '') === $expectedDiscount;
    }

    private static function syncHoldingCompaniesBindingField(int $companyId, array $company): void
    {
        if ($companyId <= 0 || !\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }
        $holdingCompanyIds = self::resolveHoldingCompanyB24Ids($companyId, $company);
        $preferredValue = self::formatCompanyCrmBindingValues($holdingCompanyIds);
        $fallbackValue = self::formatCompanyNumericBindingValues($holdingCompanyIds);

        if (!self::updateHoldingCompaniesBindingField($companyId, $preferredValue)) {
            return;
        }

        $readBack = self::readHoldingCompaniesBindingField($companyId);
        self::writeOutboundTrace('CompanySync::holding_companies_binding_after_update', [
            'company_id' => $companyId,
            'expected_ids' => $holdingCompanyIds,
            'read_back_ids' => $readBack,
        ]);
        if ($holdingCompanyIds !== [] && $readBack === []) {
            // На части инсталляций CRM-binding UF принимает числовые ID без префикса.
            self::updateHoldingCompaniesBindingField($companyId, $fallbackValue);
            $readBackAfterFallback = self::readHoldingCompaniesBindingField($companyId);
            self::writeOutboundTrace('CompanySync::holding_companies_binding_after_fallback', [
                'company_id' => $companyId,
                'expected_ids' => $holdingCompanyIds,
                'read_back_ids' => $readBackAfterFallback,
            ]);
        }
    }

    /**
     * @param array<int, string>|array<int, int>|false $value
     */
    private static function updateHoldingCompaniesBindingField(int $companyId, $value): bool
    {
        $fields = [self::uf(self::UF_COMPANY_HOLDING_COMPANIES) => $value];
        self::markInboundCompanyUpdate($companyId);
        $companyEntity = new \CCrmCompany(false);
        $ok = $companyEntity->Update(
            $companyId,
            $fields,
            true,
            true,
            [
                'CURRENT_USER' => \CCrmSecurityHelper::GetCurrentUserID(),
                'IS_SYSTEM_ACTION' => true,
            ]
        );
        if (!$ok) {
            error_log(
                '[CompanySync::syncHoldingCompaniesBindingField] Failed to update '
                . self::uf(self::UF_COMPANY_HOLDING_COMPANIES)
                . ' for company '
                . $companyId
                . '; last_error='
                . (string)($companyEntity->LAST_ERROR ?? '')
            );
            return false;
        }

        return true;
    }

    /**
     * @return list<int>
     */
    private static function readHoldingCompaniesBindingField(int $companyId): array
    {
        if ($companyId <= 0) {
            return [];
        }
        $row = \CCrmCompany::GetByID($companyId, false);
        if (!\is_array($row)) {
            return [];
        }
        $raw = $row[self::uf(self::UF_COMPANY_HOLDING_COMPANIES)] ?? null;
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
        $ids = array_values(array_unique(array_filter($ids, static function (int $id): bool {
            return $id > 0;
        })));
        sort($ids, \SORT_NUMERIC);

        return $ids;
    }

    private static function resolveHoldingCompanyB24Ids(int $companyId, array $company): array
    {
        $holdingElementId = 0;
        if (self::isTruthy($company[self::uf(self::UF_COMPANY_IS_HEAD)] ?? null)) {
            $holdingElementId = (int)self::findHeadElementInIblock($companyId);
        } else {
            $holdingElementId = self::resolveHoldingElementId(
                self::extractHoldingRefValue($company[self::uf(self::UF_COMPANY_HOLDING)] ?? null)
            );
        }
        if ($holdingElementId <= 0) {
            return [];
        }

        $ids = [];
        $headCompanyId = (int)self::getHoldingOfBitrixId($holdingElementId);
        if ($headCompanyId > 0) {
            $ids[] = $headCompanyId;
        }
        foreach (self::getChildB24IdsLinkedToHoldingIblock($holdingElementId) as $childCompanyId) {
            $childCompanyId = (int)$childCompanyId;
            if ($childCompanyId > 0) {
                $ids[] = $childCompanyId;
            }
        }
        // Гарантия полноты списка: текущая компания тоже входит в состав своего холдинга.
        if ($companyId > 0) {
            $ids[] = $companyId;
        }
        if ($ids === []) {
            return [];
        }
        $ids = array_values(array_unique($ids));
        sort($ids, \SORT_NUMERIC);

        return $ids;
    }

    private static function resolveHoldingElementId(int $rawHoldingValue): int
    {
        if ($rawHoldingValue <= 0 || !\Bitrix\Main\Loader::includeModule('iblock')) {
            return 0;
        }
        // 1) Канонично: поле холдинга хранит ID элемента ИБ16.
        // Но принимаем только элемент, у которого реально задан B24_COMPANY_ID (head CRM ID),
        // иначе это может быть чужой/случайный элемент ИБ16 с совпавшим ID.
        $byElementId = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => self::HOLDING_IBLOCK_ID,
                '=ID' => $rawHoldingValue,
            ],
            false,
            false,
            ['ID']
        )->Fetch();
        $byElementId = \is_array($byElementId) ? (int)($byElementId['ID'] ?? 0) : 0;
        if ($byElementId > 0) {
            $headCompanyId = (int)self::getHoldingOfBitrixId($byElementId);
            if ($headCompanyId > 0) {
                return $byElementId;
            }
            if (self::isHoldingHeadElement($byElementId)) {
                self::writeOutboundTrace('CompanySync::resolveHoldingElementId_ib16_head_without_b24', [
                    'raw_holding_value' => $rawHoldingValue,
                    'iblock_element_id' => $byElementId,
                ]);
                return $byElementId;
            }
            self::writeOutboundTrace('CompanySync::resolveHoldingElementId_ib16_without_head_b24', [
                'raw_holding_value' => $rawHoldingValue,
                'iblock_element_id' => $byElementId,
            ]);
        }

        // 2) Fallback только для legacy-данных, где ошибочно хранится B24 ID head-компании.
        $byHeadCompanyId = (int)self::findHeadElementInIblock($rawHoldingValue);
        if ($byHeadCompanyId > 0) {
            self::writeOutboundTrace('CompanySync::resolveHoldingElementId_legacy_head_b24_ref', [
                'raw_holding_value' => $rawHoldingValue,
                'resolved_iblock_element_id' => $byHeadCompanyId,
            ]);
            return $byHeadCompanyId;
        }
        return 0;
    }

    private static function isHoldingHeadElement(int $holdingElementId): bool
    {
        if ($holdingElementId <= 0 || !\Bitrix\Main\Loader::includeModule('iblock')) {
            return false;
        }
        $res = \CIBlockElement::GetProperty(
            self::HOLDING_IBLOCK_ID,
            $holdingElementId,
            [],
            ['CODE' => self::IBLOCK16_PROP_IS_HEAD]
        );
        while ($prop = $res->Fetch()) {
            $value = (string)($prop['VALUE'] ?? '');
            if ($value !== '' && $value !== '0') {
                return true;
            }
        }

        return false;
    }

    /**
     * Поле холдинга может приходить как scalar, ['VALUE'=>...], 'CO_<ID>'.
     *
     * @param mixed $raw
     */
    private static function extractHoldingRefValue($raw): int
    {
        if (\is_array($raw) && \array_key_exists('VALUE', $raw)) {
            $raw = $raw['VALUE'];
        }
        if (\is_string($raw)) {
            $raw = \trim($raw);
            if ($raw === '') {
                return 0;
            }
            if (\strpos($raw, 'CO_') === 0) {
                $coId = \substr($raw, 3);
                return \ctype_digit($coId) ? (int)$coId : 0;
            }
            return \ctype_digit($raw) ? (int)$raw : 0;
        }
        if (\is_int($raw)) {
            return $raw > 0 ? $raw : 0;
        }
        if (\is_float($raw)) {
            $int = (int)$raw;
            return $int > 0 ? $int : 0;
        }

        return 0;
    }

    /**
     * @param list<int> $contactIds
     * @return list<int>
     */
    private static function buildSiteUserIdsForContacts(array $contactIds): array
    {
        $out = [];
        foreach ($contactIds as $contactId) {
            $sid = self::getContactSiteUserId((int)$contactId);
            if ($sid > 0) {
                $out[] = $sid;
            }
        }

        return \array_values(\array_unique($out, \SORT_REGULAR));
    }

    private static function getContactSiteUserId(int $contactId): int
    {
        if ($contactId <= 0) {
            return 0;
        }
        $contact = \CCrmContact::GetByID($contactId, false);
        if (!\is_array($contact) || $contact === []) {
            return 0;
        }
        global $USER_FIELD_MANAGER;
        if (\is_object($USER_FIELD_MANAGER)) {
            $ufRows = $USER_FIELD_MANAGER->GetUserFields('CRM_CONTACT', $contactId, LANGUAGE_ID);
            $fieldName = self::uf(self::UF_CONTACT_SITE_USER_ID);
            if (isset($ufRows[$fieldName])) {
                $v = $ufRows[$fieldName]['VALUE'] ?? null;
                if ($v !== null && $v !== '') {
                    return (int)$v;
                }
            }
        }

        return (int)($contact[self::uf(self::UF_CONTACT_SITE_USER_ID)] ?? 0);
    }

    private static function loadCompanyContactBindings(int $companyId): array
    {
        if ($companyId <= 0) {
            return [];
        }
        if (!class_exists('\Bitrix\Crm\Binding\CompanyContactTable')) {
            return [];
        }

        $bindings = \Bitrix\Crm\Binding\CompanyContactTable::getList([
            'filter' => ['=COMPANY_ID' => $companyId],
            'select' => ['CONTACT_ID'],
        ])->fetchAll();

        return is_array($bindings) ? $bindings : [];
    }

    /**
     * Поиск элемента "головной компании" в ИБ холдингов по B24 ID компании.
     */
    private static function findHeadElementInIblock($companyId)
    {
        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            return false;
        }

        $filter = [
            'IBLOCK_ID' => self::HOLDING_IBLOCK_ID,
            'PROPERTY_' . self::IBLOCK16_PROP_B24 => $companyId,
        ];

        $rsElement = \CIBlockElement::GetList(
            [],
            $filter,
            false,
            false,
            ['ID', 'NAME']
        );

        if ($element = $rsElement->Fetch()) {
            return $element['ID'];
        }

        return false;
    }

    private static function checkForChildCompanies($headElementId): bool
    {
        return \count(self::getChildB24IdsLinkedToHoldingIblock((int) $headElementId)) > 0;
    }

    /**
     * B24 ID компаний, у которых в поле холдинга указан элемент ИБ 16 (холдинг-родитель).
     *
     * @return list<int>
     */
    private static function getChildB24IdsLinkedToHoldingIblock(int $holdingIblockElementId): array
    {
        if ($holdingIblockElementId <= 0) {
            return [];
        }
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return [];
        }
        $target = $holdingIblockElementId;
        $ids = [];
        $rsCompanies = \Bitrix\Crm\CompanyTable::getList([
            'select' => ['ID'],
            'order' => ['ID' => 'ASC'],
        ]);
        global $USER_FIELD_MANAGER;
        if (!\is_object($USER_FIELD_MANAGER)) {
            return [];
        }
        while ($row = $rsCompanies->fetch()) {
            $cid = (int)($row['ID'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $ufValues = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $cid);
            $hRaw = $ufValues[self::uf(self::UF_COMPANY_HOLDING)]['VALUE'] ?? null;
            $holdingRef = self::extractHoldingRefValue($hRaw);
            $resolvedHoldingElementId = self::resolveHoldingElementId($holdingRef);
            if ($resolvedHoldingElementId === $target) {
                $ids[] = $cid;
            }
        }

        return \array_values(\array_unique($ids));
    }

    /**
     * @param list<int> $ids
     * @return array<int|string, int>
     */
    private static function formatHoldingChildMultival(array $ids): array
    {
        $seen = [];
        foreach ($ids as $n) {
            $i = (int) $n;
            if ($i > 0) {
                $seen[$i] = true;
            }
        }
        $v = \array_keys($seen);
        if ($v === []) {
            return [];
        }
        \sort($v, \SORT_NUMERIC);
        $out = [];
        $k = 0;
        foreach ($v as $n) {
            $out['n' . $k] = (int) $n;
            $k++;
        }

        return $out;
    }

    /**
     * @param list<int> $precomputedChildB24Ids
     * @return array<string, mixed>
     */
    private static function buildHoldingHeadElementPropertyValues(int $headB24CompanyId, array $precomputedChildB24Ids): array
    {
        $fmt = self::formatHoldingChildMultival($precomputedChildB24Ids);
        $p = [
            self::IBLOCK16_PROP_B24 => $headB24CompanyId,
            self::IBLOCK16_PROP_IS_HEAD => self::IBLOCK16_IS_HEAD_ENUM_ID,
        ];
        $p[self::IBLOCK16_PROP_CHILDREN] = $fmt === [] ? false : $fmt;

        return $p;
    }

    private static function refreshHoldingIblockElementChildrenList(int $holdingIblockElementId): void
    {
        if ($holdingIblockElementId <= 0) {
            return;
        }
        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            return;
        }
        $r = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => self::HOLDING_IBLOCK_ID,
                '=ID' => $holdingIblockElementId,
            ],
            false,
            false,
            ['ID']
        );
        if (!$r->fetch()) {
            return;
        }
        $childB24 = self::getChildB24IdsLinkedToHoldingIblock($holdingIblockElementId);
        $fmt = self::formatHoldingChildMultival($childB24);
        \CIBlockElement::SetPropertyValuesEx(
            $holdingIblockElementId,
            self::HOLDING_IBLOCK_ID,
            [self::IBLOCK16_PROP_CHILDREN => $fmt === [] ? false : $fmt]
        );
    }

    private static function getHoldingOfBitrixId($holdingElementId)
    {
        $holdingElementId = (int)$holdingElementId;
        if ($holdingElementId <= 0 || !\Bitrix\Main\Loader::includeModule('iblock')) {
            return false;
        }

        $res = \CIBlockElement::GetProperty(
            self::HOLDING_IBLOCK_ID,
            $holdingElementId,
            [],
            ['CODE' => self::IBLOCK16_PROP_B24]
        );
        if ($prop = $res->Fetch()) {
            return (int)($prop['VALUE'] ?? 0) ?: false;
        }

        return false;
    }

    private static function createHoldingHeadCompany(int $companyId, string $title)
    {
        if ($companyId <= 0 || !\Bitrix\Main\Loader::includeModule('iblock')) {
            return false;
        }

        $existingId = self::findHeadElementInIblock($companyId);
        $el = new \CIBlockElement();
        $name = $title !== '' ? $title : ('Холдинг ' . $companyId);

        if ($existingId) {
            $eid = (int) $existingId;
            $childrenB24 = self::getChildB24IdsLinkedToHoldingIblock($eid);
            $el->Update($eid, [
                'NAME' => $name,
                'ACTIVE' => 'Y',
                'PROPERTY_VALUES' => self::buildHoldingHeadElementPropertyValues($companyId, $childrenB24),
            ]);
            self::writeOutboundTrace('CompanySync::holding_iblock_update', [
                'iblock_element_id' => $eid,
                'b24_company_id' => $companyId,
                'children_b24' => $childrenB24,
            ]);
            if (!self::ensureHoldingHeadCompanyProperty($eid, $companyId)) {
                return false;
            }

            return $eid;
        }

        // Иногда в ИБ16 мог появиться элемент без B24_COMPANY_ID (ручной/исторический кейс).
        // В таком случае переиспользуем его вместо создания дубля.
        $existingIdWithoutB24 = self::findHeadElementWithoutB24ByName($name);
        if ($existingIdWithoutB24 > 0) {
            $eid = $existingIdWithoutB24;
            $childrenB24 = self::getChildB24IdsLinkedToHoldingIblock($eid);
            $el->Update($eid, [
                'NAME' => $name,
                'ACTIVE' => 'Y',
                'PROPERTY_VALUES' => self::buildHoldingHeadElementPropertyValues($companyId, $childrenB24),
            ]);
            self::writeOutboundTrace('CompanySync::holding_iblock_reuse_without_b24', [
                'iblock_element_id' => $eid,
                'b24_company_id' => $companyId,
                'children_b24' => $childrenB24,
            ]);
            if (!self::ensureHoldingHeadCompanyProperty($eid, $companyId)) {
                return false;
            }

            return $eid;
        }

        $newId = $el->Add([
            'IBLOCK_ID' => self::HOLDING_IBLOCK_ID,
            'IBLOCK_SECTION_ID' => false,
            'NAME' => $name,
            'ACTIVE' => 'Y',
            'PROPERTY_VALUES' => self::buildHoldingHeadElementPropertyValues($companyId, []),
        ]);
        if (!$newId) {
            return false;
        }
        $eid = (int) $newId;
        $childrenB24 = self::getChildB24IdsLinkedToHoldingIblock($eid);
        $el->Update($eid, [
            'PROPERTY_VALUES' => self::buildHoldingHeadElementPropertyValues($companyId, $childrenB24),
        ]);
        if (!self::ensureHoldingHeadCompanyProperty($eid, $companyId)) {
            return false;
        }
        self::writeOutboundTrace('CompanySync::holding_iblock_add', [
            'iblock_element_id' => $eid,
            'b24_company_id' => $companyId,
            'children_b24' => $childrenB24,
        ]);

        return $eid;
    }

    private static function ensureHoldingHeadCompanyProperty(int $holdingElementId, int $headCompanyId): bool
    {
        if (
            $holdingElementId <= 0
            || $headCompanyId <= 0
            || !\Bitrix\Main\Loader::includeModule('iblock')
        ) {
            return false;
        }
        \CIBlockElement::SetPropertyValuesEx(
            $holdingElementId,
            self::HOLDING_IBLOCK_ID,
            [self::IBLOCK16_PROP_B24 => $headCompanyId]
        );
        $actual = (int)self::getHoldingOfBitrixId($holdingElementId);
        if ($actual !== $headCompanyId) {
            error_log(
                '[CompanySync::ensureHoldingHeadCompanyProperty] B24_COMPANY_ID mismatch for iblock element '
                . $holdingElementId
                . '; expected='
                . $headCompanyId
                . '; actual='
                . $actual
            );
            return false;
        }

        return true;
    }

    private static function findHeadElementWithoutB24ByName(string $name): int
    {
        if ($name === '' || !\Bitrix\Main\Loader::includeModule('iblock')) {
            return 0;
        }
        $rs = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            [
                'IBLOCK_ID' => self::HOLDING_IBLOCK_ID,
                '=NAME' => $name,
            ],
            false,
            false,
            ['ID', 'NAME']
        );
        while ($row = $rs->Fetch()) {
            $elementId = (int)($row['ID'] ?? 0);
            if ($elementId <= 0) {
                continue;
            }
            if ((int)self::getHoldingOfBitrixId($elementId) > 0) {
                continue;
            }
            return $elementId;
        }

        return 0;
    }

    /**
     * Значения для UF типа "Привязка к элементам CRM" (множ.) в формате CO_<ID>.
     *
     * @param list<int> $companyIds
     * @return array<int, string>|false
     */
    private static function formatCompanyCrmBindingValues(array $companyIds)
    {
        if ($companyIds === []) {
            return false;
        }
        $values = [];
        foreach ($companyIds as $companyId) {
            $id = (int)$companyId;
            if ($id <= 0) {
                continue;
            }
            $values[] = 'CO_' . $id;
        }
        if ($values === []) {
            return false;
        }

        return array_values(array_unique($values));
    }

    /**
     * @param list<int> $companyIds
     * @return array<int, int>|false
     */
    private static function formatCompanyNumericBindingValues(array $companyIds)
    {
        if ($companyIds === []) {
            return false;
        }
        $values = [];
        foreach ($companyIds as $companyId) {
            $id = (int)$companyId;
            if ($id > 0) {
                $values[] = $id;
            }
        }
        if ($values === []) {
            return false;
        }
        $values = array_values(array_unique($values));
        sort($values, \SORT_NUMERIC);

        return $values;
    }

    /**
     * Телефоны Legan: UF с карточки компании (CRM_COMPANY), в payload — ещё LEGAN_* для сайта.
     *
     * @param array<string, mixed> $company строка компании + подмешанные UF (см. onAfterCompanyUpdate)
     * @return array<string, string>
     */
    private static function leganPhoneFieldsFromCompany(array $company): array
    {
        $kM = self::uf(self::UF_COMPANY_LEGAN_MAIN_PHONE);
        $kMob = self::uf(self::UF_COMPANY_LEGAN_MOBILE_PHONE);
        $main = self::stringFromUfScalarForLegan(self::extractSingleUfValueForLegan($company, $kM));
        $mob = self::stringFromUfScalarForLegan(self::extractSingleUfValueForLegan($company, $kMob));

        return [
            $kM => $main,
            $kMob => $mob,
            'LEGAN_MAIN_PHONE' => $main,
            'LEGAN_MOBILE_PHONE' => $mob,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return mixed
     */
    private static function extractSingleUfValueForLegan(array $row, string $key)
    {
        if (!\array_key_exists($key, $row)) {
            return null;
        }
        $v = $row[$key];
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
     * @param mixed $v
     */
    private static function stringFromUfScalarForLegan($v): string
    {
        if ($v === null) {
            return '';
        }
        if (\is_scalar($v)) {
            return \trim((string) $v);
        }
        if (\is_object($v) && \method_exists($v, '__toString')) {
            return \trim((string) $v);
        }

        return '';
    }

    private static function isTruthy($value): bool
    {
        return $value === 'Y' || $value === true || $value === 1 || $value === '1';
    }
}
