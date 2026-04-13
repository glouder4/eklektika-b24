<?php
    namespace OnlineService\Site;
    use OnlineService\Site\UpdaterAbstract;
    use Bitrix\Main\Loader;
    use Bitrix\Crm\Binding\ContactCompanyTable;
    use Exception;

    class CompanyUpdater extends UpdaterAbstract{
        /**
         * true — тестовый портал (UF_FIELD_MAP_TEST), false — прод (UF_FIELD_MAP_PROD).
         * При необходимости переопределите из init.php: CompanyUpdater::setTestServer(false);
         */
        private static bool $isTestServer = false;

        /** Тестовый Bitrix24: соответствие семантических имён к UF_CRM_* (компания и контакты). */
        private static array $UF_FIELD_MAP_TEST = [
            'MARKETING_AGENT' => 'UF_CRM_1675675211485',
            'HEAD_OF_HOLDING' => 'UF_CRM_1758028888',
            'REQUISITES_FILE' => 'UF_CRM_1755643990423',
            'CONTACT_MARKETING_AGENT' => 'UF_CRM_1698752707853',
            /** Внешний идентификатор клиента на стороне Eklektika (в запросе OS_COMPANY_USERS). */
            'CONTACT_OS_USER_ID' => 'UF_CRM_3804624445748',
            'HOLDING_OF' => 'UF_CRM_1775394584',
            'CITY' => 'UF_CRM_1618551330657',
            'DISCOUNT_VALUE' => 'UF_CRM_1775218790772',
            'COMPANY_DISABLED' => 'UF_CRM_1681120791520',
            /** Привязка к элементу CRM: головная компания холдинга (для филиалов — голова; для головы — сама компания). */
            'HOLDING_HEAD_CRM_LINK' => 'UF_CRM_1775394340',
        ];

        /**
         * Продовый Bitrix24: те же ключи, другие UF_CRM_* (подставьте ID с боевого портала).
         *
         * @var array<string, string>
         */
        private static array $UF_FIELD_MAP_PROD = [
            'MARKETING_AGENT' => 'UF_CRM_1675675211485',
            'HEAD_OF_HOLDING' => 'UF_CRM_1758028888',
            'REQUISITES_FILE' => 'UF_CRM_1755643990423',
            'CONTACT_MARKETING_AGENT' => 'UF_CRM_1698752707853',
            /** Внешний идентификатор клиента на стороне Eklektika (в запросе OS_COMPANY_USERS). */
            'CONTACT_OS_USER_ID' => 'UF_CRM_3804624445748',
            'HOLDING_OF' => 'UF_CRM_1775394584',
            'CITY' => 'UF_CRM_1618551330657',
            'DISCOUNT_VALUE' => 'UF_CRM_1771490556028',
            'COMPANY_DISABLED' => 'UF_CRM_1681120791520',
            'HOLDING_HEAD_CRM_LINK' => 'UF_CRM_1775394340',
        ];

        private static function getUfFieldMap(): array
        {
            return self::$isTestServer ? self::$UF_FIELD_MAP_TEST : self::$UF_FIELD_MAP_PROD;
        }

        /**
         * По привязанным контактам компании — значения UF «внешний клиент» для передачи в OS_COMPANY_USERS (порядок = порядок CONTACT_IDS).
         *
         * @param list<int> $contactIds
         * @return list<mixed>
         */
        private static function collectContactOsUserIdsForPayload(array $contactIds, array $uf): array
        {
            if ($contactIds === []) {
                return [];
            }

            $key = $uf['CONTACT_OS_USER_ID'] ?? '';
            if ($key === '') {
                return array_fill(0, count($contactIds), '');
            }

            global $USER_FIELD_MANAGER;
            $out = [];
            foreach ($contactIds as $cid) {
                $cid = (int) $cid;
                if ($cid <= 0) {
                    $out[] = '';

                    continue;
                }

                $rows = $USER_FIELD_MANAGER->GetUserFields('CRM_CONTACT', $cid);
                $val = $rows[$key]['VALUE'] ?? null;
                if (is_array($val)) {
                    $val = count($val) ? reset($val) : '';
                }
                if ($val === null || $val === false) {
                    $val = '';
                }
                $out[] = $val;
            }

            return $out;
        }

        public static function setTestServer(bool $isTest): void
        {
            self::$isTestServer = $isTest;
        }

        private function createHoldingHeadCompany($companyId, $companyTitle){
            // Подключаем модуль инфоблоков
            if (!\Bitrix\Main\Loader::includeModule('iblock')) {
                return false;
            }

            try {
                // Проверяем существование элемента по свойству B24_COMPANY_ID
                $filter = [
                    'IBLOCK_ID' => 34,
                    'PROPERTY_B24_COMPANY_ID' => $companyId
                ];
                
                $rsElements = \CIBlockElement::GetList(
                    [],
                    $filter,
                    false,
                    false,
                    ['ID', 'NAME', 'PROPERTY_B24_COMPANY_ID']
                );

                if($element = $rsElements->Fetch()) {
                    return $element['ID'];
                }

                // Если элемент не существует, создаем новый
                // Подготавливаем данные для создания элемента
                $arFields = [
                    'IBLOCK_ID' => 34,
                    'NAME' => $companyTitle,
                    'ACTIVE' => 'Y',
                    'PROPERTY_VALUES' => [
                        'B24_COMPANY_ID' => $companyId
                    ]
                ];

                $element = new \CIBlockElement();
                $elementId = $element->Add($arFields);

                if ($elementId) {
                    return $elementId;
                } else {
                    return false;
                }
            } catch (Exception $e) {
                error_log('Исключение при создании элемента инфоблока: ' . $e->getMessage());
                return false;
            }
        }

        private static function getHoldingOfBitrixId($field_id_value){
            // Подключаем модуль инфоблоков
            if (!\Bitrix\Main\Loader::includeModule('iblock')) {
                return false;
            }
            
            // Если не передан ID элемента
            if (!$field_id_value) {
                return false;
            }
            
            // Получаем элемент инфоблока 34 по ID
            $rsElement = \CIBlockElement::GetList(
                [],
                ['ID' => $field_id_value, 'IBLOCK_ID' => 34],
                false,
                false,
                ['ID', 'NAME', 'PROPERTY_B24_COMPANY_ID']
            );
            
            if ($element = $rsElement->Fetch()) {
                // Возвращаем значение свойства B24_COMPANY_ID
                return $element['PROPERTY_B24_COMPANY_ID_VALUE'];
            }
            
            return false;
        }

        /**
         * Пустое значение UF скидки (наследование / «не задано»).
         */
        private static function isDiscountUfValueEmpty($value): bool
        {
            if ($value === null || $value === false || $value === '') {
                return true;
            }
            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item !== null && $item !== '' && $item !== false) {
                        return false;
                    }
                }

                return true;
            }

            return false;
        }

        private static function normalizeHoldingIblockElementId($raw): int
        {
            if ($raw === null || $raw === '' || $raw === false) {
                return 0;
            }
            if (is_array($raw)) {
                $raw = reset($raw);
            }

            return (int) $raw;
        }

        /**
         * Установка или смена UF «Холдинг» (элемент ИБ) — скопировать UF скидки с головной компании этого холдинга
         * (в т.ч. пустое значение). Не срабатывает, если холдинг в запросе совпадает с уже сохранённым (обновление без смены привязки).
         *
         * @param array|null $ufValues GetUserFields для компании; null при создании компании (нет записи в БД).
         */
        private static function inheritDiscountOnHoldingBind(array &$arFields, ?array $ufValues, array $uf): void
        {
            $discountKey = $uf['DISCOUNT_VALUE'] ?? '';
            $headKey = $uf['HEAD_OF_HOLDING'] ?? '';
            $holdKey = $uf['HOLDING_OF'] ?? '';
            if ($discountKey === '' || $headKey === '' || $holdKey === '') {
                return;
            }

            if (!array_key_exists($holdKey, $arFields)) {
                return;
            }

            $companyId = (int) ($arFields['ID'] ?? 0);

            $rawHead = array_key_exists($headKey, $arFields)
                ? $arFields[$headKey]
                : (($ufValues !== null && isset($ufValues[$headKey])) ? ($ufValues[$headKey]['VALUE'] ?? null) : null);
            $isHead = ($rawHead === 'Y' || $rawHead === true || $rawHead === 1 || $rawHead === '1');
            if ($isHead) {
                return;
            }

            $newHoldingId = self::normalizeHoldingIblockElementId($arFields[$holdKey]);
            if ($newHoldingId <= 0) {
                return;
            }

            if ($ufValues !== null) {
                $oldHoldingId = self::normalizeHoldingIblockElementId(
                    isset($ufValues[$holdKey]) ? ($ufValues[$holdKey]['VALUE'] ?? null) : null
                );
                if ($oldHoldingId === $newHoldingId) {
                    return;
                }
            }

            $headCrmId = (int) self::getHoldingOfBitrixId($newHoldingId);
            if ($headCrmId <= 0 || ($companyId > 0 && $headCrmId === $companyId)) {
                return;
            }

            global $USER_FIELD_MANAGER;
            $headUf = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $headCrmId);
            $parentDiscount = $headUf[$discountKey]['VALUE'] ?? null;
            $arFields[$discountKey] = $parentDiscount;
        }

        /**
         * Дочерняя компания: если скидка пустая — подставить значение с головной (только текущая карточка).
         */
        private static function inheritDiscountFromHeadIfEmpty(array &$arFields, array $ufValues, array $uf): void
        {
            $discountKey = $uf['DISCOUNT_VALUE'] ?? '';
            $headKey = $uf['HEAD_OF_HOLDING'] ?? '';
            $holdKey = $uf['HOLDING_OF'] ?? '';
            if ($discountKey === '' || $headKey === '' || $holdKey === '') {
                return;
            }

            $companyId = (int) ($arFields['ID'] ?? 0);
            if ($companyId <= 0) {
                return;
            }

            $rawHead = array_key_exists($headKey, $arFields)
                ? $arFields[$headKey]
                : ($ufValues[$headKey]['VALUE'] ?? null);
            $isHead = ($rawHead === 'Y' || $rawHead === true || $rawHead === 1 || $rawHead === '1');
            if ($isHead) {
                return;
            }

            $holdingRaw = array_key_exists($holdKey, $arFields)
                ? $arFields[$holdKey]
                : ($ufValues[$holdKey]['VALUE'] ?? null);
            if (is_array($holdingRaw)) {
                $holdingRaw = reset($holdingRaw);
            }
            $iblockElId = (int) $holdingRaw;
            if ($iblockElId <= 0) {
                return;
            }

            $inRequest = array_key_exists($discountKey, $arFields);
            $dbVal = $ufValues[$discountKey]['VALUE'] ?? null;
            $willBeEmpty = $inRequest
                ? self::isDiscountUfValueEmpty($arFields[$discountKey])
                : self::isDiscountUfValueEmpty($dbVal);

            if (!$willBeEmpty) {
                return;
            }

            $headCrmId = (int) self::getHoldingOfBitrixId($iblockElId);
            if ($headCrmId <= 0 || $headCrmId === $companyId) {
                return;
            }

            global $USER_FIELD_MANAGER;
            $headUf = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $headCrmId);
            $parentDiscount = $headUf[$discountKey]['VALUE'] ?? null;
            if (self::isDiscountUfValueEmpty($parentDiscount)) {
                return;
            }

            $arFields[$discountKey] = $parentDiscount;
        }

        /**
         * Головная: при изменении UF скидки в этом сохранении — то же значение записывается всем дочерним компаниям холдинга (всегда, без проверки «пусто/своё/совпадает»).
         * У каждой дочерней срабатывает OnAfter → updateCompany: на EKLEKTIKA уходит OS_COMPANY_DISCOUNT_VALUE и CONTACT_IDS.
         */
        private static function propagateHeadDiscountToChildren(int $companyId, array $effective, array $uf): void
        {
            $discountKey = $uf['DISCOUNT_VALUE'] ?? '';
            $headKey = $uf['HEAD_OF_HOLDING'] ?? '';
            if ($discountKey === '' || $headKey === '') {
                return;
            }

            $rawHead = $effective[$headKey] ?? null;
            $isHead = ($rawHead === 'Y' || $rawHead === true || $rawHead === 1 || $rawHead === '1');
            if (!$isHead) {
                return;
            }

            $headDiscount = $effective[$discountKey] ?? null;

            $headIblockEl = self::findHeadElementInIblock($companyId);
            if (!$headIblockEl) {
                return;
            }

            $members = self::collectHoldingMemberCompanyIds((int) $headIblockEl);

            foreach ($members as $memberId) {
                if ((int) $memberId === $companyId) {
                    continue;
                }

                $entity = new \CCrmCompany(false);
                // Второй аргумент Update() передаётся по ссылке — нельзя передавать литерал массива.
                $childFields = [$discountKey => $headDiscount];
                $entity->Update(
                    (int) $memberId,
                    $childFields,
                    true,
                    [
                        'CURRENT_USER' => \CCrmSecurityHelper::GetCurrentUserID(),
                        'IS_SYSTEM_ACTION' => true,
                        'ENABLE_DUP_INDEX_INVALIDATION' => true,
                        'REGISTER_SONET_EVENT' => false,
                        'DISABLE_USER_FIELD_CHECK' => false,
                    ]
                );
            }
        }

        public static function updateCompany(&$arFields) {
            global $USER;

            $company = null;
            $companyId = (int) ($arFields['ID'] ?? 0);

            if (\Bitrix\Main\Loader::requireModule('crm')) {
                \Bitrix\Crm\Settings\CompanySettings::getCurrent()->setFactoryEnabled(true);

                $company = \Bitrix\Crm\CompanyTable::getById($companyId)->fetch();

                if ($company) {
                    global $USER_FIELD_MANAGER;

                    $ufValues = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId);

                    foreach ($ufValues as $key => $value)
                        $company[$key] = $value['VALUE'];

                    $requisites = \Bitrix\Crm\RequisiteTable::getList([
                        'filter' => [
                            'ENTITY_ID' => $company['ID']
                        ],
                        'select' => ['*'] // Все поля реквизита или конкретные, например ['RQ_INN', 'RQ_KPP']
                    ])->fetchAll();

                    $company['REQUISITES'] = $requisites[0];


                    // 2. Получаем множественные поля (включая WEB, PHONE, EMAIL и т.д.)
                    $multiFields = \CCrmFieldMulti::GetEntityFields('COMPANY', $companyId, "");
                    $company['MULTIFIELDS'] = [];

                    foreach ($multiFields as $multiField){
                        $company['MULTIFIELDS'][$multiField['TYPE_ID']] = $multiField;
                    }
                }
            }

            if( $USER->IsAdmin() ){

                if (!$company || $companyId <= 0) {
                    return true;
                }

                $uf = self::getUfFieldMap();

                $effective = $company;
                foreach ($arFields as $k => $v) {
                    if ($k === 'ID') {
                        continue;
                    }
                    $effective[$k] = $v;
                }

                $headCompanyIblock_id = false;
                $rawHeadForIblock = $effective[$uf['HEAD_OF_HOLDING']] ?? null;
                if ($rawHeadForIblock === 'Y' || $rawHeadForIblock === true || $rawHeadForIblock === 1 || $rawHeadForIblock === '1') {
                    // Создаем элемент в инфоблоке 34 для головной компании холдинга
                    $updater = new self();
                    $title = $effective['TITLE'] ?? $company['TITLE'];
                    $headCompanyIblock_id = $updater->createHoldingHeadCompany($companyId, $title);
                }

                // «Клиенты» компании: CONTACT_IDS — ID контактов в B24; OS_COMPANY_USERS — значения UF CONTACT_OS_USER_ID по тем же контактам (внешний клиент на Eklektika).
                // После сохранения карточки источник истины — БД; так дочерние компании при программном Update по скидке получают тот же набор, что в CRM.
                $boundIds = [];
                if (class_exists(ContactCompanyTable::class)) {
                    $boundIds = ContactCompanyTable::getCompanyContactIDs($companyId) ?: [];
                }
                if ($boundIds === [] && !empty($arFields['CONTACT_BINDINGS']) && is_array($arFields['CONTACT_BINDINGS'])) {
                    $boundIds = array_map('intval', array_column($arFields['CONTACT_BINDINGS'], 'CONTACT_ID'));
                }
                $contactIds = array_values(array_unique(array_filter(array_map('intval', $boundIds))));
                $osCompanyUsers = self::collectContactOsUserIdsForPayload($contactIds, $uf);

                $IS_MARKETING_AGENT = $effective[$uf['MARKETING_AGENT']] ?? null;

                $REQ_FILE_ID = $effective[$uf['REQUISITES_FILE']] ?? null;
                $fileInfo = $REQ_FILE_ID ? \CFile::GetFileArray($REQ_FILE_ID) : null;

                foreach ($contactIds as $contactId){
                    $contactFields = [
                        $uf['CONTACT_MARKETING_AGENT']   => $IS_MARKETING_AGENT
                    ];

                    $contactEntity = new \CCrmContact( true );

                    $contactEntity->Update(
                        $contactId,
                        $contactFields,
                        $bCompare = true,
                        $arOptions = [
                            'CURRENT_USER' => \CCrmSecurityHelper::GetCurrentUserID(),
                            'IS_SYSTEM_ACTION' => false,
                            'ENABLE_DUP_INDEX_INVALIDATION' => true,
                            'REGISTER_SONET_EVENT' => true,
                            'DISABLE_USER_FIELD_CHECK' => false,
                            'DISABLE_REQUIRED_USER_FIELD_CHECK' => false,
                        ]
                    );
                }

                $rawHead = $effective[$uf['HEAD_OF_HOLDING']] ?? null;
                $rawMarketing = $effective[$uf['MARKETING_AGENT']] ?? null;
                $rawDisabled = $effective[$uf['COMPANY_DISABLED']] ?? null;

                $companyParams = [
                    'ACTION' => "UPDATE_COMPANY",
                    'CONTACT_IDS' => $contactIds,
                    "OS_COMPANY_B24_ID" => $companyId,
                    "OS_COMPANY_NAME" => $effective['TITLE'] ?? $company['TITLE'],
                    "OS_COMPANY_IS_HEAD_OF_HOLDING" => [
                        'VALUE' => ($rawHead === 'Y' || $rawHead === true || $rawHead === 1 || $rawHead === "1")
                            ? 31520
                            : false // или null, или ''
                    ],
                    "OS_HOLDING_OF" => self::getHoldingOfBitrixId($effective[$uf['HOLDING_OF']] ?? null),
                    "OS_HEAD_COMPANY_B24_ID" => ($rawHead === 'Y' || $rawHead === true || $rawHead === 1 || $rawHead === '1') ? $headCompanyIblock_id : false,
                    "OS_COMPANY_USERS" => $osCompanyUsers,
                    "OS_COMPANY_INN" => $company['REQUISITES']['RQ_INN'] ?? null,
                    "OS_COMPANY_CITY" => $effective[$uf['CITY']] ?? null,
                    "OS_COMPANY_DISCOUNT_VALUE" => $effective[$uf['DISCOUNT_VALUE']] ?? null,
                    "OS_COMPANY_WEB_SITE" => $company['MULTIFIELDS']['WEB']['VALUE'] ?? null,
                    "OS_COMPANY_PHONE" => $company['MULTIFIELDS']['PHONE']['VALUE'] ?? null,
                    "OS_COMPANY_EMAIL" => $company['MULTIFIELDS']['EMAIL']['VALUE'] ?? null,
                    'OS_IS_MARKETING_AGENT' => [
                        'VALUE' => ($rawMarketing === 'Y' || $rawMarketing === true || $rawMarketing === 1 || $rawMarketing === "1")
                            ? 31519
                            : false // или null, или ''
                    ],
                    'OS_IS_COMPANY_DISABLED' => [
                        'VALUE' => ($rawDisabled === 'Y' || $rawDisabled === true || $rawDisabled === 1 || $rawDisabled === "1")
                            ? 31518
                            : false // или null, или ''
                    ],
                    "OS_REQUSITES_FILE" => $fileInfo,
                    "ACTIVE" => ($rawMarketing === 'Y' || $rawMarketing === true || $rawMarketing === 1 || $rawMarketing === "1") ? "Y" : "N"
                ];

                $updater = new self();
                $res = $updater->sendRequest($companyParams,false);

                if (array_key_exists($uf['DISCOUNT_VALUE'], $arFields)) {
                    self::propagateHeadDiscountToChildren($companyId, $effective, $uf);
                }

                return true;
            }

            return true;
        }

        public static function beforeUpdateCompany(&$arFields){
            // Получаем ID компании
            $companyId = $arFields['ID'];
            
            // Проверяем, пытаются ли убрать флаг головной компании
            // Получаем UF поля через USER_FIELD_MANAGER
            global $USER_FIELD_MANAGER;
            $ufValues = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', $companyId);
            
            $uf = self::getUfFieldMap();

            $wasHeadCompany = $ufValues[$uf['HEAD_OF_HOLDING']]['VALUE'] === 'Y' || $ufValues[$uf['HEAD_OF_HOLDING']]['VALUE'] == 1;
            $willBeHeadCompany = $arFields[$uf['HEAD_OF_HOLDING']] === 'Y';

            if( $willBeHeadCompany && isset($arFields[$uf['HOLDING_OF']]) && !empty($arFields[$uf['HOLDING_OF']]) ){
                $arFields['RESULT_MESSAGE'] = "Компания не может быть головной И входить в другой холдинг";
                return false;
            }
            
            // Если пытаются убрать флаг головной компании
            if ($wasHeadCompany && !$willBeHeadCompany) {
                // Находим элемент в инфоблоке 34 по B24_COMPANY_ID
                $headElementId = self::findHeadElementInIblock($companyId);
                
                if ($headElementId) {
                    // Проверяем, есть ли дочерние филиалы
                    $hasChildCompanies = self::checkForChildCompanies($headElementId);
                } else {
                    $hasChildCompanies = false;
                }

                if ($hasChildCompanies) {
                    global $APPLICATION;
                    // Устанавливаем сообщение об ошибке через глобальную переменную
                    //$arFields['RESULT_MESSAGE'] = 'Нельзя убрать флаг головной компании. У компании есть дочерние филиалы.';
                    //throw new \Exception("Нельзя убрать флаг головной компании. У компании есть дочерние филиалы.", "Ограничение прав");

                    $arFields['RESULT_MESSAGE'] = "Нельзя убрать флаг головной компании. У компании есть дочерние филиалы.";
                    return false;
                }
                else{
                    $holdingElementID = self::findHeadElementInIblock($arFields['ID']);

                    $el = new \CIBlockElement;
                    $el->Update($holdingElementID, [
                        "ACTIVE" => "N"
                    ]);
                }
            }
            if( $willBeHeadCompany ){
                $holdingElementID = self::findHeadElementInIblock($arFields['ID']);

                $el = new \CIBlockElement;
                $el->Update($holdingElementID, [
                    "ACTIVE" => "Y"
                ]);
            }

            self::inheritDiscountOnHoldingBind($arFields, $ufValues, $uf);
            self::inheritDiscountFromHeadIfEmpty($arFields, $ufValues, $uf);
            self::fillHoldingHeadCrmLinkField($arFields, $ufValues, $uf);

            return true;
        }

        /**
         * Создание компании: если сразу указан холдинг — скидка с головной (до появления ID в БД).
         */
        public static function beforeAddCompany(&$arFields): bool
        {
            $uf = self::getUfFieldMap();
            self::inheritDiscountOnHoldingBind($arFields, null, $uf);

            return true;
        }

        /**
         * Метаданные UF компании (без static-кэша — после пересоздания поля MULTIPLE/тип должны читаться заново).
         */
        private static function getCrmCompanyUserFieldRow(string $fieldName): ?array
        {
            if (!Loader::includeModule('crm')) {
                return null;
            }
            $rs = \CUserTypeEntity::GetList([], [
                'ENTITY_ID' => 'CRM_COMPANY',
                'FIELD_NAME' => $fieldName,
            ]);
            $row = $rs->Fetch();

            return is_array($row) ? $row : null;
        }

        /**
         * MULTIPLE для UF (карточка шлёт UF_xxx[0], UF_xxx[1] — ID компаний).
         */
        private static function isCompanyUserFieldMultiple(string $fieldName, array $ufValues = []): bool
        {
            if (isset($ufValues[$fieldName]['MULTIPLE'])) {
                $m = $ufValues[$fieldName]['MULTIPLE'];

                return $m === 'Y' || $m === true || $m === 1;
            }
            $row = self::getCrmCompanyUserFieldRow($fieldName);
            if (!$row) {
                return false;
            }

            return (($row['MULTIPLE'] ?? 'N') === 'Y');
        }

        /**
         * Головная CRM-компания по элементу ИБ холдинга + все компании с UF «Холдинг» = этот элемент ИБ.
         *
         * @return list<int>
         */
        private static function collectHoldingMemberCompanyIds(int $headIblockElementId): array
        {
            if ($headIblockElementId <= 0 || !Loader::includeModule('crm')) {
                return [];
            }

            $holdingKey = self::getUfFieldMap()['HOLDING_OF'];
            $headCrmId = self::getHoldingOfBitrixId($headIblockElementId);
            if (!$headCrmId) {
                return [];
            }

            $ids = [(int) $headCrmId];
            $db = \CCrmCompany::GetListEx(
                ['TITLE' => 'ASC'],
                ['=' . $holdingKey => $headIblockElementId],
                false,
                false,
                ['ID']
            );
            while ($row = $db->Fetch()) {
                $ids[] = (int) $row['ID'];
            }

            return array_values(array_unique($ids));
        }

        /**
         * UF «Фирмы холдинга»: головная + все дочерние (UF «Холдинг» = элемент ИБ), только если головная
         * или выбран холдинг. Иначе не трогаем (ручной ввод при пустом «Холдинг»).
         */
        private static function fillHoldingHeadCrmLinkField(array &$arFields, array $ufValues, array $uf): void
        {
            $linkKey = $uf['HOLDING_HEAD_CRM_LINK'] ?? '';
            if ($linkKey === '') {
                return;
            }

            $companyId = (int) ($arFields['ID'] ?? 0);
            if ($companyId <= 0) {
                return;
            }

            $meta = self::getCrmCompanyUserFieldRow($linkKey);
            if (!$meta || ($meta['USER_TYPE_ID'] ?? '') !== 'crm') {
                return;
            }

            $headKey = $uf['HEAD_OF_HOLDING'];
            $holdKey = $uf['HOLDING_OF'];

            $rawHead = array_key_exists($headKey, $arFields)
                ? $arFields[$headKey]
                : ($ufValues[$headKey]['VALUE'] ?? null);
            $isHead = ($rawHead === 'Y' || $rawHead === true || $rawHead === 1 || $rawHead === '1');

            $holdingRaw = array_key_exists($holdKey, $arFields)
                ? $arFields[$holdKey]
                : ($ufValues[$holdKey]['VALUE'] ?? null);
            if (is_array($holdingRaw)) {
                $holdingRaw = reset($holdingRaw);
            }
            $iblockElId = (int) $holdingRaw;
            $hasHolding = ($iblockElId > 0);

            if (!$isHead && !$hasHolding) {
                return;
            }

            $multiple = self::isCompanyUserFieldMultiple($linkKey, $ufValues);

            if ($isHead) {
                $headIblockEl = self::findHeadElementInIblock($companyId);
                $allIds = $headIblockEl
                    ? self::collectHoldingMemberCompanyIds((int) $headIblockEl)
                    : [$companyId];
            } else {
                $allIds = self::collectHoldingMemberCompanyIds($iblockElId);
            }

            if ($allIds === []) {
                return;
            }

            $allIds = array_values(array_unique(array_map('intval', $allIds)));

            $arFields[$linkKey] = $multiple ? $allIds : ($allIds[0] ?? false);
        }
        
        private static function findHeadElementInIblock($companyId) {
            // Подключаем модуль инфоблоков
            if (!\Bitrix\Main\Loader::includeModule('iblock')) {
                return false;
            }
            
            // Ищем элемент в инфоблоке 34 по свойству B24_COMPANY_ID
            $filter = [
                'IBLOCK_ID' => 34,
                'PROPERTY_B24_COMPANY_ID' => $companyId
            ];
            
            $rsElement = \CIBlockElement::GetList(
                [],
                $filter,
                false,
                false,
                ['ID', 'NAME', 'PROPERTY_B24_COMPANY_ID']
            );
            
            if ($element = $rsElement->Fetch()) {
                return $element['ID'];
            }

            return false;
        }
        
        private static function checkForChildCompanies($headElementId) {
            $headElementId = (int) $headElementId;
            if ($headElementId <= 0 || !Loader::includeModule('crm')) {
                return false;
            }

            $holdingKey = self::getUfFieldMap()['HOLDING_OF'];
            $connection = \Bitrix\Main\Application::getConnection();
            $col = $connection->getSqlHelper()->forSql($holdingKey);

            $row = $connection->query(
                'SELECT 1 AS C FROM b_uts_crm_company WHERE ' . $col . ' = ' . $headElementId . ' LIMIT 1'
            )->fetch();

            return $row !== false;
        }

        public static function deleteCompany($id){
            $params = [
                'ACTION' => "DELETE_COMPANY",
                'ID' => $id,
            ];

            $updater = new self();
            $res = $updater->sendRequest($params,false );

            return true;
        }

        public function updateCompanyElement($params){
            pre($params);
            die();
        }
    }