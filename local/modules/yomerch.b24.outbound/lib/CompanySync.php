<?php

namespace OnlineService\Sync\ToSite;

use OnlineService\Sync\UfMap;
use OnlineService\Sync\Contract\CompanySyncNormalizeService;
use OnlineService\Sync\Contract\CompanySyncPolicyService;
use OnlineService\Sync\Contract\CompanySyncReadService;

// UF-карта: модуль yomerch.b24.contract → lib/config/uf_mapping.php.
class CompanySync extends OutboundRequest
{
    /** Инфоблок каталога холдингов: UF `company.holding` ссылается на элементы этого ИБ; головная/дочерние — здесь же. */
    private const HOLDING_IBLOCK_ID = 34;
    /** Семантически то же, что {@see HOLDING_IBLOCK_ID} (отдельного «ИБ 57» на портале нет). */
    private const HOLDING_UF_IBLOCK_ID = 34;
    private const UF_COMPANY_HOLDING = 'company.holding';
    private const UF_COMPANY_DISCOUNT = 'company.discount';
    private const UF_COMPANY_HOLDING_COMPANIES = 'company.holding_companies';
    /** Множественная привязка к компаниям CRM: головная + все участники холдинга (см. {@see syncHoldingGroupMembersAcrossCluster}). */
    private const UF_COMPANY_HOLDING_GROUP_MEMBERS = 'company.holding_group_members';
    private const UF_COMPANY_IS_HEAD = 'company.is_head';
    /** UF «головная» для холдинга (портал), см. inbound duplicate INN / очистка при снятии флага. */
    private const UF_COMPANY_HEAD_PORTAL = 'company.head_company_flag';
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
    /**
     * Защита от сбоев фильтра GetListEx: при превышении не пишем UF «участники холдинга» на всех (риск записать всю базу).
     */
    private const MAX_HOLDING_GROUP_CLUSTER_MEMBERS = 2000;
    /** @var array<int, bool> */
    private static array $skipOutboundForCompanyIds = [];

    /**
     * UF «участники холдинга» ({@see UF_COMPANY_HOLDING_GROUP_MEMBERS}) меняется только из синхронизации
     * (обёртка {@see runWithHoldingGroupMembersUfMutationAllowed}); ручное редактирование в карточке снимается в OnBefore.
     */
    private static int $holdingGroupMembersUfMutationDepth = 0;

    /**
     * Выполнить блок, внутри которого разрешены CCrmCompany::Update с UF участников холдинга.
     */
    public static function runWithHoldingGroupMembersUfMutationAllowed(callable $fn): void
    {
        self::$holdingGroupMembersUfMutationDepth++;
        try {
            $fn();
        } finally {
            self::$holdingGroupMembersUfMutationDepth--;
        }
    }

    /**
     * Ручная правка UF участников холдинга запрещена политикой — снимаем ключ до сохранения.
     *
     * @param array<string, mixed> $arFields
     */
    private static function stripUnauthorizedHoldingGroupMembersUf(array &$arFields): void
    {
        $k = self::uf(self::UF_COMPANY_HOLDING_GROUP_MEMBERS);
        if (\array_key_exists($k, $arFields) && self::$holdingGroupMembersUfMutationDepth <= 0) {
            unset($arFields[$k]);
        }
    }

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
     *
     * @param array<string, mixed> $arFields
     */
    public static function onBeforeCompanyUpdate(&$arFields): bool
    {
        if (!\is_array($arFields)) {
            return true;
        }

        self::stripUnauthorizedHoldingGroupMembersUf($arFields);

        $companyId = (int)($arFields['ID'] ?? 0);
        global $USER_FIELD_MANAGER;
        $ufValues = ($companyId > 0 && \is_object($USER_FIELD_MANAGER))
            ? $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId)
            : [];

        $policyService = new CompanySyncPolicyService();
        $ufHeadPortal = self::uf(self::UF_COMPANY_HEAD_PORTAL);
        $ufCompanyHolding = self::uf(self::UF_COMPANY_HOLDING);

        $portalPolicy = $policyService->validatePortalHeadFlagVsHoldingExclusive(
            $ufValues,
            $arFields,
            $ufHeadPortal,
            $ufCompanyHolding
        );
        if (!(bool)($portalPolicy['ok'] ?? false)) {
            $arFields['RESULT_MESSAGE'] = (string)($portalPolicy['message'] ?? 'Несовместимые значения полей головной компании и холдинга');

            return false;
        }

        if ($companyId <= 0) {
            return true;
        }

        $ufCompanyIsHead = self::uf(self::UF_COMPANY_IS_HEAD);
        $policy = $policyService->validateHeadHoldingTransition($ufValues, $arFields, $ufCompanyIsHead, $ufCompanyHolding);
        $wasHeadCompany = (bool)($policy['was_head'] ?? false);
        $willBeHeadCompany = (bool)($policy['will_head'] ?? false);

        if (!(bool)($policy['ok'] ?? false)) {
            $arFields['RESULT_MESSAGE'] = (string)($policy['message'] ?? 'Компания не может быть головной И входить в другой холдинг');

            return false;
        }

        $wasPortalHead = self::isTruthy($ufValues[$ufHeadPortal]['VALUE'] ?? null);
        $willPortalHead = \array_key_exists($ufHeadPortal, $arFields)
            ? self::isTruthy($arFields[$ufHeadPortal] ?? null)
            : $wasPortalHead;
        if ($wasPortalHead && !$willPortalHead) {
            self::clearHoldingChildrenWhenPortalHeadFlagRemoved($companyId);
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
        $ufHeadPortal = self::uf(self::UF_COMPANY_HEAD_PORTAL);
        $ufCompanyDiscount = self::uf(self::UF_COMPANY_DISCOUNT);
        $ufCompanyHoldingCompanies = self::uf(self::UF_COMPANY_HOLDING_COMPANIES);
        $isHead = self::isTruthy($company[$ufCompanyIsHead] ?? null);
        $isHeadPortalCompany = self::isTruthy(
            self::extractCompanyUfScalarForOutbound($arFields, $company, $ufHeadPortal)
        );
        if ($isHeadPortalCompany) {
            self::ensureHoldingUfCatalogElementForPortalHeadCompany(
                $companyId,
                (string)($arFields['TITLE'] ?? $company['TITLE'] ?? '')
            );
        }

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
        $holdingClusterElementId = self::resolveHoldingIblockElementForOutboundCluster($companyId, $company);
        $holdingMembersSyncOnChildSave = $holdingClusterElementId > 0
            && !$isHeadPortalCompany
            && !$isHead;
        // UF участников холдинга: смена «головная по порталу»/холдинга в запросе, либо любое сохранение компании с
        // включённым {@see UF_COMPANY_HEAD_PORTAL} (после {@see ensureHoldingUfCatalogElementForPortalHeadCompany} есть
        // элемент ИБ 34 с B24_COMPANY_ID), либо сохранение дочерней (есть резолвящийся кластер по UF холдинга, без
        // флагов головной) — пересчёт списка головная + дочерние на всех участниках.
        if (
            array_key_exists($ufHeadPortal, $arFields)
            || array_key_exists($ufCompanyHolding, $arFields)
            || $isHeadPortalCompany
            || $holdingMembersSyncOnChildSave
        ) {
            self::syncHoldingGroupMembersAcrossCluster($companyId, $company);
        }
        if ($isHeadPortalCompany) {
            $discountValue = (string)($company[$ufCompanyDiscount] ?? '');
            self::propagateHeadDiscountToChildrenAndContacts($companyId, $discountValue);
        } elseif (
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
        $isMarketingAgentRaw = self::extractCompanyUfScalarForOutbound(
            $arFields,
            $company,
            $ufCompanyIsMarketingAgent
        );
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
            'company_site_element_id_legacy' => $ufCompanySiteElementIdLegacy,
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
        $isHeadPortal = self::isTruthy(self::extractCompanyUfScalarForOutbound($arFields, $company, $ufHeadPortal));
        $headList = ['VALUE' => $isHeadPortal ? 2074 : false];
        if ($isHeadPortal) {
            self::ensureHoldingUfCatalogElementForPortalHeadCompany($companyId, $title);
        }

        // Скидка всегда от карточки обновляемой компании ($companyId): в $arFields UF часто отсутствует при частичном сохранении.
        $discountForOutbound = self::resolveOutboundDiscountForUpdatedCompany($companyId, $arFields, $ufCompanyDiscount);

        // CONTACT_IDS / OS_COMPANY_USERS / LEGAN_ENTITY_USERS — идентификаторы на сайте (UF contact.delete_site_ref),
        // не CRM CONTACT_ID. OutboundRequest::sendRequest для UPDATE_COMPANY подставляет OS_* из CONTACT_IDS.
        $companySiteUserIds = self::buildSiteUserIdsForContacts($contactIds);
        $leganPhones = self::leganPhoneFieldsFromCompany($company);

        // Сайт (updateCompanyElement) подмешивает только ключи из $codeProps — префикс OS_*.
        // Раньше уходили в основном LEGAN_ENTITY_* без OS_* → свойства элемента не обновлялись.
        $params = \array_merge(
            [
                'ACTION' => 'UPDATE_COMPANY',
                'CONTACT_IDS' => $companySiteUserIds,
                'OS_COMPANY_B24_ID' => $companyId,
                $ufCompanySiteElementId => $siteElementId,
                // @deprecated temporary alias for legacy site handlers; remove after full cutover.
                $ufCompanySiteElementIdLegacy => $siteElementId,
                'OS_COMPANY_NAME' => $title,
                'OS_COMPANY_IS_HEAD_OF_HOLDING' => $headList,
                $ufHeadPortal => $isHeadPortal ? 'Y' : 'N',
                'OS_HOLDING_OF' => self::getHoldingOfBitrixId($resolvedHoldingElementId),
                'OS_HEAD_COMPANY_B24_ID' => $isHead ? $headCompanyIblockId : false,
                'OS_COMPANY_USERS' => $companySiteUserIds,
                'LEGAN_ENTITY_USERS' => $companySiteUserIds,
                'OS_COMPANY_INN' => $inn,
                'OS_COMPANY_CITY' => $city,
                $ufCompanyCity => $city,
                $ufCompanyDiscount => $discountForOutbound,
                'OS_COMPANY_DISCOUNT_VALUE' => $discountForOutbound,
                'OS_COMPANY_WEB_SITE' => $web,
                $ufCompanyWeb => $web,
                $ufCompanyBusinessScope => $businessScope,
                $ufCompanyLegalAddress => $legalAddress,
                'OS_COMPANY_HOLDING_COMPANIES' => $holdingCompaniesBindingPayload === [] ? false : $holdingCompaniesBindingPayload,
                $ufCompanyHoldingCompanies => $holdingCompaniesBindingPayload === [] ? false : $holdingCompaniesBindingPayload,
                'OS_COMPANY_PHONE' => $phone,
                'OS_COMPANY_EMAIL' => $email,
                'OS_IS_MARKETING_AGENT' => ['VALUE' => self::isTruthy($isMarketingAgentRaw) ? 2076 : false],
                'ACTIVE' => self::isTruthy($isMarketingAgentRaw) ? 'Y' : 'N',
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
                    self::uf('contact.inherits_company_is_marketing_agent') => $isMarketingAgentRaw,
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
            $detachHoldingFields = [self::uf(self::UF_COMPANY_HOLDING) => false];
            $ok = $companyEntity->Update(
                $childCompanyId,
                $detachHoldingFields,
                true,
                true,
                [
                    'CURRENT_USER' => \CCrmSecurityHelper::GetCurrentUserID(),
                    'IS_SYSTEM_ACTION' => true,
                ]
            );
            if (!$ok) {
                foreach ($detachedChildCompanyIds as $rollbackChildCompanyId) {
                    $rollbackHoldingFields = [self::uf(self::UF_COMPANY_HOLDING) => $holdingElementId];
                    $companyEntity->Update(
                        $rollbackChildCompanyId,
                        $rollbackHoldingFields,
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
     * UF {@see UF_COMPANY_HOLDING_GROUP_MEMBERS}: одинаковый состав (головная + дочерние по элементу каталога
     * {@see HOLDING_IBLOCK_ID}) на всех участниках кластера — через {@see getChildB24IdsLinkedToHoldingIblock},
     * без отдельного поиска «всех с UF = CRM ID головы» при некорректном разнесении ИБ.
     *
     * @param array<string, mixed> $company
     */
    private static function syncHoldingGroupMembersAcrossCluster(int $companyId, array $company): void
    {
        if ($companyId <= 0 || !\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }
        $membersUf = self::uf(self::UF_COMPANY_HOLDING_GROUP_MEMBERS);
        $memberIds = self::collectHoldingGroupMemberCompanyIdsForOutbound($companyId, $company);

        if ($memberIds === []) {
            self::writeOutboundTrace('CompanySync::holding_group_members_skip_empty_cluster', [
                'company_id' => $companyId,
            ]);

            return;
        }
        if (\count($memberIds) > self::MAX_HOLDING_GROUP_CLUSTER_MEMBERS) {
            self::writeOutboundTrace('CompanySync::holding_group_members_abort_cluster_too_large', [
                'company_id' => $companyId,
                'member_count' => \count($memberIds),
                'max_allowed' => self::MAX_HOLDING_GROUP_CLUSTER_MEMBERS,
            ]);
            error_log(
                '[CompanySync::syncHoldingGroupMembersAcrossCluster] Aborted: cluster size '
                . \count($memberIds)
                . ' exceeds MAX_HOLDING_GROUP_CLUSTER_MEMBERS ('
                . self::MAX_HOLDING_GROUP_CLUSTER_MEMBERS
                . '). Check CRM filters / UF holding logic.'
            );

            return;
        }

        $preferred = self::formatCompanyCrmBindingValues($memberIds);
        if ($preferred === false) {
            return;
        }
        $fallback = self::formatCompanyNumericBindingValues($memberIds);

        self::writeOutboundTrace('CompanySync::holding_group_members_propagate', [
            'trigger_company_id' => $companyId,
            'member_count' => \count($memberIds),
            'member_ids_sample' => \array_slice($memberIds, 0, 30),
        ]);

        self::runWithHoldingGroupMembersUfMutationAllowed(static function () use ($membersUf, $memberIds, $preferred, $fallback): void {
            foreach ($memberIds as $cid) {
                if ($cid <= 0) {
                    continue;
                }
                self::markInboundCompanyUpdate($cid);
                $memberFields = [$membersUf => $preferred];
                $entity = new \CCrmCompany(false);
                $ok = (bool) $entity->Update(
                    $cid,
                    $memberFields,
                    true,
                    true,
                    [
                        'CURRENT_USER' => \CCrmSecurityHelper::GetCurrentUserID(),
                        'IS_SYSTEM_ACTION' => true,
                    ]
                );
                if (!$ok && $fallback !== false) {
                    self::markInboundCompanyUpdate($cid);
                    $memberFieldsFb = [$membersUf => $fallback];
                    $entity2 = new \CCrmCompany(false);
                    (bool) $entity2->Update(
                        $cid,
                        $memberFieldsFb,
                        true,
                        true,
                        [
                            'CURRENT_USER' => \CCrmSecurityHelper::GetCurrentUserID(),
                            'IS_SYSTEM_ACTION' => true,
                        ]
                    );
                }
            }
        });
    }

    /**
     * Пересчитать UF «участники холдинга» для кластера этой компании (обновляются все участники кластера).
     * Для разового прогона после исправления логики {@see getChildB24IdsLinkedToHoldingIblock} / вариантов UF.
     */
    public static function repairHoldingGroupMembersForCompanyId(int $companyId): bool
    {
        if ($companyId <= 0 || !\Bitrix\Main\Loader::includeModule('crm')) {
            return false;
        }
        $readService = new CompanySyncReadService();
        $company = $readService->loadCompanySnapshot($companyId);
        if (!$company) {
            return false;
        }
        self::syncHoldingGroupMembersAcrossCluster($companyId, $company);

        return true;
    }

    /**
     * Массовый пересчёт: для каждого ID грузим снимок, пропускаем дубликаты по одному элементу каталога холдинга
     * (один вызов {@see syncHoldingGroupMembersAcrossCluster} уже обновляет весь кластер).
     *
     * @param list<int> $companyIds
     *
     * @return array{processed: int, skipped_duplicate_cluster: int, failed: int, failed_ids: list<int>}
     */
    public static function repairHoldingGroupMembersForCompanyIds(array $companyIds): array
    {
        $stats = [
            'processed' => 0,
            'skipped_duplicate_cluster' => 0,
            'failed' => 0,
            'failed_ids' => [],
        ];
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return $stats;
        }
        $readService = new CompanySyncReadService();
        $seenCluster = [];
        foreach ($companyIds as $rawId) {
            $companyId = (int) $rawId;
            if ($companyId <= 0) {
                continue;
            }
            $company = $readService->loadCompanySnapshot($companyId);
            if (!$company) {
                ++$stats['failed'];
                $stats['failed_ids'][] = $companyId;

                continue;
            }
            $key = self::holdingClusterRepairKey($companyId, $company);
            if (isset($seenCluster[$key])) {
                ++$stats['skipped_duplicate_cluster'];

                continue;
            }
            $seenCluster[$key] = true;
            self::syncHoldingGroupMembersAcrossCluster($companyId, $company);
            ++$stats['processed'];
        }

        return $stats;
    }

    /**
     * Ключ дедупликации при массовом repair: один элемент каталога ИБ → один прогон синхронизации кластера.
     */
    private static function holdingClusterRepairKey(int $companyId, array $company): string
    {
        $el = self::resolveHoldingIblockElementForOutboundCluster($companyId, $company);

        return $el > 0 ? 'ib:' . $el : 'cid:' . $companyId;
    }

    /**
     * Элемент каталога холдингов ({@see HOLDING_IBLOCK_ID}) для кластера UF «участники»: совпадает с опорой
     * {@see resolveHoldingCompanyB24Ids}; при необходимости — переход по CRM ID головы из элемента каталога.
     *
     * @param array<string, mixed> $company
     */
    private static function resolveHoldingIblockElementForOutboundCluster(int $triggerCompanyId, array $company): int
    {
        if ($triggerCompanyId <= 0) {
            return 0;
        }
        if (
            self::isTruthy($company[self::uf(self::UF_COMPANY_IS_HEAD)] ?? null)
            || self::isTruthy($company[self::uf(self::UF_COMPANY_HEAD_PORTAL)] ?? null)
        ) {
            $headEl = self::findHeadElementInIblock($triggerCompanyId);

            return $headEl ? (int) $headEl : 0;
        }
        $holdingUf = self::uf(self::UF_COMPANY_HOLDING);
        $ref = self::extractHoldingRefValue($company[$holdingUf] ?? null);
        if ($ref <= 0) {
            return 0;
        }
        $holdingElementId = self::resolveHoldingElementId($ref);
        if ($holdingElementId > 0) {
            return $holdingElementId;
        }
        $headFromCatalog = self::getHeadCrmIdFromHoldingUfCatalogElement34($ref);
        if ($headFromCatalog > 0) {
            $headEl = self::findHeadElementInIblock($headFromCatalog);

            return $headEl ? (int) $headEl : 0;
        }

        return 0;
    }

    /**
     * Состав для UF «участники холдинга»: головная + дочерние по тому же элементу каталога {@see HOLDING_IBLOCK_ID},
     * что и привязки {@see getChildB24IdsLinkedToHoldingIblock} (см. outbound holding_companies). Раньше при широком
     * поиске по UF вызывался {@see findCompanyIdsByHoldingUfVariants} с вариантом «CRM ID головы», из‑за чего в кластер
     * попадали лишние компании. Если элемент каталога не удаётся резолвить — fallback {@see resolveHoldingCompanyB24Ids}.
     *
     * @param array<string, mixed> $company
     *
     * @return list<int>
     */
    private static function collectHoldingGroupMemberCompanyIdsForOutbound(int $triggerCompanyId, array $company): array
    {
        $holdingElementId = self::resolveHoldingIblockElementForOutboundCluster($triggerCompanyId, $company);

        $memberSet = [];

        if ($holdingElementId > 0) {
            $headCrm = (int) (self::getHoldingOfBitrixId($holdingElementId) ?: 0);
            if ($headCrm > 0) {
                $memberSet[$headCrm] = true;
            }
            foreach (self::getChildB24IdsLinkedToHoldingIblock($holdingElementId) as $cid) {
                $cid = (int) $cid;
                if ($cid > 0) {
                    $memberSet[$cid] = true;
                }
            }
        } else {
            foreach (self::resolveHoldingCompanyB24Ids($triggerCompanyId, $company) as $cid) {
                $cid = (int) $cid;
                if ($cid > 0) {
                    $memberSet[$cid] = true;
                }
            }
        }

        if ($triggerCompanyId > 0) {
            $memberSet[$triggerCompanyId] = true;
        }

        $ids = \array_keys($memberSet);
        \sort($ids, \SORT_NUMERIC);

        return $ids;
    }

    /**
     * CRM ID головной компании: элемент {@see HOLDING_UF_IBLOCK_ID} с ID = $elementId и свойством {@see IBLOCK16_PROP_B24}.
     */
    private static function getHeadCrmIdFromHoldingUfCatalogElement34(int $elementId): int
    {
        if ($elementId <= 0 || !\Bitrix\Main\Loader::includeModule('iblock')) {
            return 0;
        }
        $exists = \CIBlockElement::GetList(
            [],
            [
                'ID' => $elementId,
                'IBLOCK_ID' => self::HOLDING_UF_IBLOCK_ID,
            ],
            false,
            false,
            ['ID']
        )->Fetch();
        if (!\is_array($exists)) {
            return 0;
        }
        $res = \CIBlockElement::GetProperty(
            self::HOLDING_UF_IBLOCK_ID,
            $elementId,
            [],
            ['CODE' => self::IBLOCK16_PROP_B24]
        );
        if ($prop = $res->Fetch()) {
            $v = (int)($prop['VALUE'] ?? 0);

            return $v > 0 ? $v : 0;
        }

        return 0;
    }

    /**
     * Значения UF холдинга для поиска «всех своих»: элемент каталога ИБ (int/string), CRM ID головной (int/string).
     * Префикс CO_ для фильтра GetListEx не используем: на части порталов `= UF => CO_<crmId>` возвращает сотни ложных
     * совпадений при типе UF «привязка к элементам инфоблока» (см. отладку: CO_2973 → 179, элемент ИБ → 1).
     *
     * @return list<int|string>
     */
    private static function holdingUfSearchVariantsForCluster(int $holdingRef, int $headCrmId): array
    {
        $out = [$holdingRef, (string) $holdingRef];
        if ($headCrmId > 0) {
            $out[] = $headCrmId;
            $out[] = (string) $headCrmId;
        }

        return array_values(array_unique($out, \SORT_REGULAR));
    }

    /**
     * Все возможные значения UF холдинга для одного кластера с элементом каталога $holdingCatalogElementId
     * ({@see HOLDING_IBLOCK_ID}), включая дублирующий поиск по элементу того же ИБ с тем же B24 головной.
     *
     * @return list<int|string>
     */
    private static function holdingUfSearchVariantsForLinkedToHoldingCatalogElement(int $holdingCatalogElementId): array
    {
        if ($holdingCatalogElementId <= 0) {
            return [];
        }
        $headB24 = (int) (self::getHoldingOfBitrixId($holdingCatalogElementId) ?: 0);
        $variants = self::holdingUfSearchVariantsForCluster($holdingCatalogElementId, $headB24);
        if ($headB24 > 0) {
            $catalogDup = self::findHoldingCatalogElementIdByB24Company(self::HOLDING_UF_IBLOCK_ID, $headB24);
            if ($catalogDup > 0) {
                $variants[] = $catalogDup;
                $variants[] = (string) $catalogDup;
            }
        }

        return array_values(array_unique($variants, \SORT_REGULAR));
    }

    /**
     * Все компании CRM, у которых UF холдинга равен одному из вариантов.
     * Отдельный GetListEx на каждый вариант (несколько коротких выборок по индексу UF), без LOGIC OR:
     * в CCrmCompany::GetListEx связка LOGIC OR + CHECK_PERMISSIONS и UF на части порталов даёт запрос без ограничения по UF
     * и возвращает все компании — из‑за этого UF «участники холдинга» заполнялся всей базой.
     *
     * @param list<int|string> $variants
     *
     * @return list<int>
     */
    private static function findCompanyIdsByHoldingUfVariants(string $holdingUf, array $variants): array
    {
        $dedupeVal = [];
        $seen = [];
        foreach ($variants as $val) {
            if ($val === '' || $val === false || $val === null) {
                continue;
            }
            $k = (\is_int($val) ? 'i:' : 's:') . (string) $val;
            if (isset($dedupeVal[$k])) {
                continue;
            }
            $dedupeVal[$k] = true;

            $res = \CCrmCompany::GetListEx(
                ['ID' => 'ASC'],
                [
                    '=' . $holdingUf => $val,
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
        $ids = array_keys($seen);
        sort($ids, \SORT_NUMERIC);

        return $ids;
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
        if (
            self::isTruthy($company[self::uf(self::UF_COMPANY_IS_HEAD)] ?? null)
            || self::isTruthy($company[self::uf(self::UF_COMPANY_HEAD_PORTAL)] ?? null)
        ) {
            $headEl = self::findHeadElementInIblock($companyId);
            $holdingElementId = $headEl ? (int) $headEl : 0;
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
     * Идентификаторы контактов для сайта: сначала UF {@see UfMap} `contact.delete_site_ref`, иначе UF `contact.site_user_id`.
     *
     * @param list<int> $contactIds CRM CONTACT_ID
     * @return list<int>
     */
    private static function buildSiteUserIdsForContacts(array $contactIds): array
    {
        $out = [];
        foreach ($contactIds as $contactId) {
            $cid = (int)$contactId;
            if ($cid <= 0) {
                continue;
            }
            $ref = ContactSync::outboundSiteCatalogRefForContact($cid);
            if ($ref !== '') {
                $n = (int)$ref;
                if ($n > 0) {
                    $out[] = $n;
                    continue;
                }
            }
            $sid = self::getContactSiteUserId($cid);
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
     * Снятие `company.head_company_flag` (UF_CRM_1758028888): элемент ИБ 34 с `B24_COMPANY_ID` = головной компании
     * деактивируется; у дочерних (UF `company.holding` → этот элемент или legacy CRM ID) очищаются `company.holding` и `company.holding_group_members`.
     */
    private static function clearHoldingChildrenWhenPortalHeadFlagRemoved(int $headCompanyId): void
    {
        if ($headCompanyId <= 0) {
            return;
        }
        $holdingUf = self::uf(self::UF_COMPANY_HOLDING);
        $membersUf = self::uf(self::UF_COMPANY_HOLDING_GROUP_MEMBERS);

        $elementId = self::findHoldingCatalogElementIdByB24Company(self::HOLDING_UF_IBLOCK_ID, $headCompanyId);
        if ($elementId > 0 && \Bitrix\Main\Loader::includeModule('iblock')) {
            $el = new \CIBlockElement();
            if (!$el->Update($elementId, ['ACTIVE' => 'N'])) {
                self::writeOutboundTrace('CompanySync::portal_head_flag_iblock34_deactivate_failed', [
                    'head_company_id' => $headCompanyId,
                    'iblock_element_id' => $elementId,
                ]);
            }
        }

        $childIds = self::findChildCompaniesForHoldingUnlink($elementId, $holdingUf, $headCompanyId);
        self::runWithHoldingGroupMembersUfMutationAllowed(static function () use ($childIds, $holdingUf, $membersUf): void {
            foreach ($childIds as $cid) {
                self::markInboundCompanyUpdate($cid);
                $entity = new \CCrmCompany(false);
                // Нельзя передавать литерал массива: у CCrmCompany::Update $arFields — по ссылке (PHP 8+).
                $clearChildUfFields = [
                    $holdingUf => false,
                    $membersUf => false,
                ];
                $ok = (bool) $entity->Update(
                    $cid,
                    $clearChildUfFields,
                    true,
                    true,
                    [
                        'CURRENT_USER' => 1,
                        'IS_SYSTEM_ACTION' => true,
                    ]
                );
                if (!$ok) {
                    self::writeOutboundTrace('CompanySync::portal_head_flag_child_uf_clear_failed', [
                        'child_company_id' => $cid,
                        'error' => (string) ($entity->LAST_ERROR ?? ''),
                    ]);
                }
            }
        });

        self::writeOutboundTrace('CompanySync::portal_head_flag_cleanup', [
            'head_company_id' => $headCompanyId,
            'iblock34_element_id' => $elementId,
            'cleared_child_ids' => $childIds,
        ]);
    }

    /**
     * Элемент инфоблока холдинга (ИБ 34) по свойству {@see IBLOCK16_PROP_B24}.
     */
    private static function findHoldingCatalogElementIdByB24Company(int $iblockId, int $b24CompanyId): int
    {
        if ($b24CompanyId <= 0 || !\Bitrix\Main\Loader::includeModule('iblock')) {
            return 0;
        }

        $rsElement = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $iblockId,
                'PROPERTY_' . self::IBLOCK16_PROP_B24 => $b24CompanyId,
            ],
            false,
            false,
            ['ID']
        );
        if ($row = $rsElement->Fetch()) {
            return (int)($row['ID'] ?? 0);
        }

        return 0;
    }

    /**
     * Установка `company.head_company_flag` (UF_CRM_1758028888): в ИБ {@see HOLDING_UF_IBLOCK_ID} ищем элемент
     * с {@see IBLOCK16_PROP_B24} = CRM ID компании — при необходимости активируем или создаём запись.
     * Снятие галочки обрабатывается в {@see clearHoldingChildrenWhenPortalHeadFlagRemoved}.
     */
    private static function ensureHoldingUfCatalogElementForPortalHeadCompany(int $companyId, string $title): void
    {
        if ($companyId <= 0 || !\Bitrix\Main\Loader::includeModule('iblock')) {
            return;
        }
        $iblockId = self::HOLDING_UF_IBLOCK_ID;
        $elementId = self::findHoldingCatalogElementIdByB24Company($iblockId, $companyId);
        $name = $title !== '' ? $title : ('Компания ' . $companyId);
        $el = new \CIBlockElement();

        if ($elementId > 0) {
            $rs = \CIBlockElement::GetList(
                [],
                ['ID' => $elementId, 'IBLOCK_ID' => $iblockId],
                false,
                false,
                ['ID', 'ACTIVE']
            );
            $row = $rs->Fetch();
            if (!\is_array($row)) {
                self::writeOutboundTrace('CompanySync::holding_uf_ib34_ensure_no_element_row', [
                    'company_id' => $companyId,
                    'element_id' => $elementId,
                ]);

                return;
            }
            if (($row['ACTIVE'] ?? '') === 'Y') {
                return;
            }
            if (!$el->Update($elementId, ['ACTIVE' => 'Y'])) {
                self::writeOutboundTrace('CompanySync::holding_uf_ib34_activate_failed', [
                    'company_id' => $companyId,
                    'element_id' => $elementId,
                    'error' => (string) ($el->LAST_ERROR ?? ''),
                ]);

                return;
            }
            self::writeOutboundTrace('CompanySync::holding_uf_ib34_activated', [
                'company_id' => $companyId,
                'element_id' => $elementId,
            ]);

            return;
        }

        $newId = $el->Add([
            'IBLOCK_ID' => $iblockId,
            'IBLOCK_SECTION_ID' => false,
            'NAME' => $name,
            'ACTIVE' => 'Y',
            'PROPERTY_VALUES' => [
                self::IBLOCK16_PROP_B24 => $companyId,
            ],
        ]);
        if (!$newId) {
            self::writeOutboundTrace('CompanySync::holding_uf_ib34_create_failed', [
                'company_id' => $companyId,
                'error' => (string) ($el->LAST_ERROR ?? ''),
            ]);

            return;
        }
        self::writeOutboundTrace('CompanySync::holding_uf_ib34_created', [
            'company_id' => $companyId,
            'iblock_element_id' => (int) $newId,
        ]);
    }

    /**
     * @param mixed $value
     * @return list<int>
     */
    private static function findCompanyIdsByHoldingUfValue(string $holdingUf, $value): array
    {
        if ($value === null || $value === '' || $value === false) {
            return [];
        }

        $ids = [];
        $res = \CCrmCompany::GetListEx(
            ['ID' => 'ASC'],
            [
                '=' . $holdingUf => $value,
                'CHECK_PERMISSIONS' => 'N',
            ],
            false,
            false,
            ['ID']
        );
        while ($row = $res->Fetch()) {
            $id = (int)($row['ID'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Дочерние компании для очистки UF: привязка к головной через элемент каталога или legacy (CRM ID в UF).
     *
     * @return list<int>
     */
    private static function findChildCompaniesForHoldingUnlink(int $elementId, string $holdingUf, int $headCompanyId): array
    {
        $seen = [];
        $variants = [];
        if ($elementId > 0) {
            $variants[] = $elementId;
            $variants[] = (string) $elementId;
        }
        $variants[] = $headCompanyId;
        $variants[] = (string) $headCompanyId;

        foreach (\array_unique($variants, \SORT_REGULAR) as $v) {
            foreach (self::findCompanyIdsByHoldingUfValue($holdingUf, $v) as $cid) {
                if ($cid > 0 && $cid !== $headCompanyId) {
                    $seen[$cid] = true;
                }
            }
        }

        $out = \array_keys($seen);
        \sort($out, \SORT_NUMERIC);

        return $out;
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
     * B24 ID компаний, у которых в поле холдинга указан тот же холдинг, что и элемент каталога {@see HOLDING_IBLOCK_ID}
     * ($holdingIblockElementId).
     * Раньше: полный перебор всех компаний + UF на каждую (минуты на больших базах).
     * Сейчас: отдельные короткие GetListEx по каноническим значениям UF (см. {@see findCompanyIdsByHoldingUfVariants}).
     *
     * @return list<int>
     */
    private static function getChildB24IdsLinkedToHoldingIblock(int $holdingIblockElementId): array
    {
        if ($holdingIblockElementId <= 0 || !\Bitrix\Main\Loader::includeModule('crm')) {
            return [];
        }
        $holdingUf = self::uf(self::UF_COMPANY_HOLDING);
        $variants = self::holdingUfSearchVariantsForLinkedToHoldingCatalogElement($holdingIblockElementId);

        return self::findCompanyIdsByHoldingUfVariants($holdingUf, $variants);
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

    /**
     * Скидка для UPDATE_COMPANY: непустая только если в CRM у этой компании поле реально пустое.
     * Приоритет — фактическое значение в карточке (GetUserFields + VALUE_ID для списков), не $arFields:
     * иначе при частичном сохранении в $arFields часто приходит пустой VALUE для enum, хотя в базе выбран вариант.
     *
     * @param array<string, mixed> $arFields
     */
    private static function resolveOutboundDiscountForUpdatedCompany(int $companyId, array $arFields, string $ufDiscountKey): string
    {
        if ($companyId <= 0) {
            return '';
        }
        $fromCrm = ContactSync::getNormalizedCompanyDiscount($companyId);
        if ($fromCrm !== '') {
            return $fromCrm;
        }
        if (\array_key_exists($ufDiscountKey, $arFields)) {
            return ContactSync::normalizeDiscountFromRaw($arFields[$ufDiscountKey]);
        }

        return '';
    }

    /**
     * Скаляр UF компании для исходящего payload: при событии обновления приоритет у значения из `$arFields`.
     *
     * @param array<string, mixed> $arFields
     * @param array<string, mixed> $company
     */
    private static function extractCompanyUfScalarForOutbound(array $arFields, array $company, string $ufKey)
    {
        $raw = \array_key_exists($ufKey, $arFields) ? $arFields[$ufKey] : ($company[$ufKey] ?? null);
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

    private static function isTruthy($value): bool
    {
        return $value === 'Y' || $value === true || $value === 1 || $value === '1';
    }
}
