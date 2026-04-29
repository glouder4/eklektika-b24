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
        private static int $updateDepth = 0;
        private static ?string $debugTraceId = null;

        /** Тестовый Bitrix24: соответствие семантических имён к UF_CRM_* (компания и контакты). */
        private static array $UF_FIELD_MAP_TEST = [
            'MARKETING_AGENT' => 'UF_CRM_1675675211485',
            'HEAD_OF_HOLDING' => 'UF_CRM_1758028888',
            'REQUISITES_FILE' => 'UF_CRM_1775033868000',
            'CONTACT_MARKETING_AGENT' => 'UF_CRM_1698752707853',
            /** Внешний идентификатор клиента на стороне Eklektika (в запросе OS_COMPANY_USERS). */
            'CONTACT_OS_USER_ID' => 'UF_CRM_3804624445748',
            'HOLDING_OF' => 'UF_CRM_1758028816',
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
            'REQUISITES_FILE' => 'UF_CRM_1775033868000',
            'CONTACT_MARKETING_AGENT' => 'UF_CRM_1698752707853',
            /** Внешний идентификатор клиента на стороне Eklektika (в запросе OS_COMPANY_USERS). */
            'CONTACT_OS_USER_ID' => 'UF_CRM_3804624445748',
            'HOLDING_OF' => 'UF_CRM_1758028816',
            'CITY' => 'UF_CRM_1618551330657',
            'DISCOUNT_VALUE' => 'UF_CRM_1771490556028',
            'COMPANY_DISABLED' => 'UF_CRM_1681120791520',
            'HOLDING_HEAD_CRM_LINK' => 'UF_CRM_1776426878',
        ];

        private static function getUfFieldMap(): array
        {
            return self::$isTestServer ? self::$UF_FIELD_MAP_TEST : self::$UF_FIELD_MAP_PROD;
        }

        private static function shouldDebug(): bool
        {
            if (defined('EK_COMPANY_UPDATER_DEBUG') && EK_COMPANY_UPDATER_DEBUG) {
                return true;
            }

            return isset($_REQUEST['ek_company_updater_debug']) && (string) $_REQUEST['ek_company_updater_debug'] === '1';
        }

        private static function getDebugTraceId(): string
        {
            if (self::$debugTraceId !== null) {
                return self::$debugTraceId;
            }

            self::$debugTraceId = substr(md5(uniqid('', true)), 0, 10);

            return self::$debugTraceId;
        }

        private static function debugLog(string $message, array $context = []): void
        {
            if (!self::shouldDebug()) {
                return;
            }

            $traceId = self::getDebugTraceId();
            $payload = '[CompanyUpdater][' . $traceId . '] ' . $message;
            if (!empty($context)) {
                $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $payload .= ' | ' . ($encoded !== false ? $encoded : print_r($context, true));
            }

            \AddMessage2Log($payload, 'company_updater_debug');
        }

        /**
         * Всегда пишет файловый trace для приёмника CRM:
         * <document_root>/local/logs/company-updater-inbound.log
         *
         * @param array<string, mixed> $context
         */
        private static function inboundTrace(string $event, array $context = []): void
        {
            $line = date('Y-m-d H:i:s') . ' [trace] ' . $event;
            if ($context !== []) {
                $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                $line .= ' ' . ($json !== false ? $json : '{"encode":"failed"}');
            }
            $line .= PHP_EOL;
            $roots = self::documentRootCandidatesForInboundTrace();
            $writeTargets = [
                '/local/logs/company-updater-inbound.log',
                '/local/logs/inbound-b24.log',
            ];
            foreach ($roots as $root) {
                foreach ($writeTargets as $target) {
                    $path = $root . $target;
                    $dir = dirname($path);
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0775, true);
                    }
                    if (@file_put_contents($path, $line, FILE_APPEND | LOCK_EX) !== false) {
                        return;
                    }
                }
            }

            // Жесткий fallback: /tmp обычно доступен даже при проблемах с DOCUMENT_ROOT/local/logs.
            if (@file_put_contents('/tmp/company-updater-inbound.log', $line, FILE_APPEND | LOCK_EX) !== false) {
                return;
            }

            $fallback = '[CompanyUpdater][inboundTrace] write failed: local/logs/company-updater-inbound.log';
            @error_log($fallback . ' roots=' . json_encode($roots, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        /**
         * Кандидаты document root для стабильной записи local/logs на ext_www/нестандартных окружениях.
         *
         * @return list<string>
         */
        private static function documentRootCandidatesForInboundTrace(): array
        {
            $out = [];
            $push = static function (string $root) use (&$out): void {
                $root = rtrim(str_replace('\\', '/', $root), '/');
                if ($root === '') {
                    return;
                }
                foreach ($out as $existing) {
                    if ($existing === $root) {
                        return;
                    }
                }
                // Маркер корня CRM-приёмника.
                $marker = $root . '/local/classes/site/CompanyUpdater.php';
                if (@is_file($marker)) {
                    $out[] = $root;
                }
            };

            $fromClass = dirname(__DIR__, 3);
            $push($fromClass);
            $rp = @realpath($fromClass);
            if (is_string($rp) && $rp !== '') {
                $push($rp);
            }
            if (class_exists(\Bitrix\Main\Application::class)) {
                try {
                    $push((string)\Bitrix\Main\Application::getDocumentRoot());
                } catch (\Throwable $e) {
                }
            }
            $push((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));

            return $out;
        }

        /**
         * Безопасный срез метаданных файла для debug-логов (без путей/временных имён).
         */
        private static function buildSafeFileMetaForDebug(?array $file): array
        {
            if (!is_array($file)) {
                return [
                    'present' => false,
                ];
            }

            $name = (string)($file['name'] ?? $file['ORIGINAL_NAME'] ?? '');
            $type = (string)($file['type'] ?? $file['CONTENT_TYPE'] ?? '');
            $sizeRaw = $file['size'] ?? $file['FILE_SIZE'] ?? null;

            return [
                'present' => true,
                'name' => $name !== '' ? $name : null,
                'type' => $type !== '' ? $type : null,
                'size' => is_numeric($sizeRaw) ? (int)$sizeRaw : null,
            ];
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

                if ($element = $rsElements->Fetch()) {
                    $existingId = (int) ($element['ID'] ?? 0);
                    if ($existingId > 0) {
                        $currentName = trim((string) ($element['NAME'] ?? ''));
                        $newName = trim((string) $companyTitle);
                        if ($currentName !== $newName) {
                            $el = new \CIBlockElement();
                            $el->Update($existingId, ['NAME' => $newName]);
                        }
                    }

                    return $existingId > 0 ? $existingId : false;
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

        /**
         * Нормализация значения скидки для безопасного сравнения.
         */
        private static function normalizeDiscountUfValue($value): string
        {
            if (is_array($value)) {
                if (count($value) === 0) {
                    return '';
                }
                $value = reset($value);
            }

            if ($value === null || $value === false) {
                return '';
            }

            return (string) $value;
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
            $startedAt = microtime(true);
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
                self::debugLog('propagate skipped: no head iblock element', [
                    'company_id' => $companyId,
                ]);
                return;
            }

            $members = self::collectHoldingMemberCompanyIds((int) $headIblockEl);
            self::debugLog('propagate members resolved', [
                'company_id' => $companyId,
                'head_iblock_element_id' => (int) $headIblockEl,
                'members' => $members,
            ]);
            $updatedChildren = 0;
            $skippedChildren = 0;

            foreach ($members as $memberId) {
                if ((int) $memberId === $companyId) {
                    continue;
                }

                global $USER_FIELD_MANAGER;
                $memberUf = $USER_FIELD_MANAGER->GetUserFields('CRM_COMPANY', (int) $memberId);
                $memberDiscount = $memberUf[$discountKey]['VALUE'] ?? null;

                // Сильно сокращаем время обработки: не обновляем, если скидка уже совпадает.
                if (self::normalizeDiscountUfValue($memberDiscount) === self::normalizeDiscountUfValue($headDiscount)) {
                    $skippedChildren++;
                    continue;
                }

                $entity = new \CCrmCompany(false);
                // Второй аргумент Update() передаётся по ссылке — нельзя передавать литерал массива.
                $childFields = [$discountKey => $headDiscount];
                $childUpdated = $entity->Update(
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

                if ($childUpdated) {
                    $updatedChildren++;
                }

                self::debugLog('child discount sync', [
                    'parent_company_id' => $companyId,
                    'child_company_id' => (int) $memberId,
                    'updated' => (bool) $childUpdated,
                    'error' => $childUpdated ? '' : $entity->LAST_ERROR,
                ]);
            }

            self::debugLog('propagateHeadDiscountToChildren finished', [
                'parent_company_id' => $companyId,
                'members_total' => count($members),
                'updated_children' => $updatedChildren,
                'skipped_children' => $skippedChildren,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        }

        public static function updateCompany(&$arFields) {
            global $USER;

            $company = null;
            $companyId = (int) ($arFields['ID'] ?? 0);
            $traceId = self::getDebugTraceId();
            self::$updateDepth++;
            $depth = self::$updateDepth;
            $startedAt = microtime(true);
            /*self::debugLog('updateCompany enter', [
                'company_id' => $companyId,
                'depth' => $depth,
                'keys' => array_keys($arFields),
            ]);*/

            try {

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
                        return;
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
                    $osCompanyUsers = $contactIds;//self::collectContactOsUserIdsForPayload($contactIds, $uf);

                    $IS_MARKETING_AGENT = $effective[$uf['MARKETING_AGENT']] ?? null;

                    $rawRequisitesKeyUsed = (string)($uf['REQUISITES_FILE'] ?? '');
                    $REQ_FILE_ID = $rawRequisitesKeyUsed !== '' ? ($effective[$rawRequisitesKeyUsed] ?? null) : null;
                    $fileInfo = $REQ_FILE_ID ? \CFile::GetFileArray($REQ_FILE_ID) : null;
                    self::debugLog('updateCompanyElement requisites key resolved', [
                        'company_id' => (int)$companyId,
                        'rawRequisitesKeyUsed' => $rawRequisitesKeyUsed,
                        'rawRequisitesValueType' => \gettype($REQ_FILE_ID),
                        'hasFileInfo' => \is_array($fileInfo),
                    ]);

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
                    $requestStartedAt = microtime(true);
                    $res = $updater->sendRequest($companyParams,false);
                    self::debugLog('sendRequest finished', [
                        'company_id' => $companyId,
                        'duration_ms' => (int) round((microtime(true) - $requestStartedAt) * 1000),
                        'result_success' => is_array($res) ? ($res['success'] ?? null) : null,
                        'result_error' => is_array($res) ? ($res['error'] ?? null) : null,
                    ]);

                    if (array_key_exists($uf['DISCOUNT_VALUE'], $arFields)) {
                        self::propagateHeadDiscountToChildren($companyId, $effective, $uf);
                    }
                    return;
                }

                return;
            } finally {
                /*self::debugLog('updateCompany exit', [
                    'company_id' => $companyId,
                    'trace_id' => $traceId,
                    'depth' => $depth,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);*/
                self::$updateDepth = max(0, self::$updateDepth - 1);
            }
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
                self::debugLog('collect members aborted', [
                    'reason' => 'invalid head iblock or crm module not loaded',
                    'head_iblock_element_id' => $headIblockElementId,
                ]);
                return [];
            }

            $holdingKey = self::getUfFieldMap()['HOLDING_OF'];
            $headCrmId = self::getHoldingOfBitrixId($headIblockElementId);
            if (!$headCrmId) {
                self::debugLog('collect members aborted', [
                    'reason' => 'head crm id not resolved by iblock element',
                    'head_iblock_element_id' => $headIblockElementId,
                    'holding_key' => $holdingKey,
                ]);
                return [];
            }

            $ids = [(int) $headCrmId];
            $connection = \Bitrix\Main\Application::getConnection();
            $sqlHelper = $connection->getSqlHelper();
            $holdingColumn = $sqlHelper->forSql($holdingKey);
            $headIblockElementId = (int) $headIblockElementId;

            $query = "
                SELECT C.ID, C.TITLE, U.{$holdingColumn} AS HOLDING_RAW
                FROM b_crm_company C
                INNER JOIN b_uts_crm_company U ON U.VALUE_ID = C.ID
                WHERE U.{$holdingColumn} = {$headIblockElementId}
                ORDER BY C.TITLE ASC
            ";

            $rows = [];
            $unexpectedRows = 0;
            $db = $connection->query($query);
            while ($row = $db->fetch()) {
                $childId = (int) ($row['ID'] ?? 0);
                $holdingRaw = $row['HOLDING_RAW'] ?? null;
                $holdingId = (int) (is_array($holdingRaw) ? reset($holdingRaw) : $holdingRaw);

                // Защитный фильтр: добавляем только тех, у кого холдинг реально совпал.
                if ($childId > 0 && $holdingId === $headIblockElementId) {
                    $ids[] = $childId;
                } elseif ($childId > 0) {
                    $unexpectedRows++;
                }

                if (count($rows) < 200) {
                    $rows[] = [
                        'id' => $childId,
                        'title' => (string) ($row['TITLE'] ?? ''),
                        'holding_raw' => $holdingRaw,
                        'holding_id_cast' => $holdingId,
                    ];
                }
            }

            $uniqueIds = array_values(array_unique($ids));
            self::debugLog('collect members result', [
                'head_iblock_element_id' => $headIblockElementId,
                'head_crm_id' => (int) $headCrmId,
                'holding_key' => $holdingKey,
                'raw_rows_count' => count($rows),
                'unexpected_rows_count' => $unexpectedRows,
                'raw_rows_preview' => $rows,
                'ids_raw' => $ids,
                'ids_unique' => $uniqueIds,
            ]);

            return $uniqueIds;
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
            
            $found = [];
            while ($element = $rsElement->Fetch()) {
                $found[] = [
                    'id' => (int) ($element['ID'] ?? 0),
                    'name' => (string) ($element['NAME'] ?? ''),
                    'b24_company_id' => $element['PROPERTY_B24_COMPANY_ID_VALUE'] ?? null,
                ];
            }

            if ($found !== []) {
                self::debugLog('findHeadElementInIblock matches', [
                    'company_id' => (int) $companyId,
                    'matches_count' => count($found),
                    'matches' => $found,
                ]);

                return (int) $found[0]['id'];
            }

            self::debugLog('findHeadElementInIblock no matches', [
                'company_id' => (int) $companyId,
            ]);

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

        /**
         * Скачать файл реквизитов с сайта и подготовить массив для CRM UF(file).
         *
         * @return array|null
         */
        private function downloadRequisitesFileForCrmUf($rawFile): ?array
        {
            $maxFileSize = 20 * 1024 * 1024; // 20 MB
            if (!is_array($rawFile)) {
                self::inboundTrace('download_requisites skipped', [
                    'error' => 'raw_file_not_array',
                ]);
                self::debugLog('download requisites skipped: invalid payload', [
                    'error' => 'raw_file_not_array',
                ]);
                return null;
            }
            $src = (string)($rawFile['SRC'] ?? '');
            if ($src === '') {
                self::inboundTrace('download_requisites skipped', [
                    'error' => 'empty_src',
                ]);
                self::debugLog('download requisites skipped: empty src', [
                    'error' => 'empty_src',
                ]);
                return null;
            }

            $url = $src;
            $base = rtrim((string)(defined('EKLEKTIKA_SITE_URL') ? EKLEKTIKA_SITE_URL : ''), '/');
            if ($base === '') {
                self::debugLog('download requisites skipped: empty site base url', [
                    'src' => $src,
                    'error' => 'empty_base_url',
                ]);
                return null;
            }

            $baseUrlParts = parse_url($base);
            $baseScheme = strtolower((string)($baseUrlParts['scheme'] ?? ''));
            $allowedHost = strtolower((string)($baseUrlParts['host'] ?? ''));
            if ($allowedHost === '' || $baseScheme !== 'https') {
                self::debugLog('download requisites skipped: invalid site base host', [
                    'src' => $src,
                    'base_url' => $base,
                    'error' => 'invalid_base_url_or_scheme',
                ]);
                return null;
            }

            if (strpos($src, 'http://') !== 0 && strpos($src, 'https://') !== 0) {
                $url = $base . '/' . ltrim($src, '/');
            }

            $urlParts = parse_url($url);
            $scheme = strtolower((string)($urlParts['scheme'] ?? ''));
            $urlHost = strtolower((string)($urlParts['host'] ?? ''));
            if ($scheme !== 'https' || $urlHost === '') {
                self::debugLog('download requisites skipped: invalid resolved url', [
                    'src' => $src,
                    'resolved_url' => $url,
                    'error' => 'invalid_url',
                ]);
                return null;
            }
            if ($urlHost !== $allowedHost) {
                self::inboundTrace('download_requisites skipped', [
                    'src' => $src,
                    'resolved_url' => $url,
                    'allowed_host' => $allowedHost,
                    'actual_host' => $urlHost,
                    'error' => 'host_not_allowed',
                ]);
                self::debugLog('download requisites skipped: host not allowed', [
                    'src' => $src,
                    'resolved_url' => $url,
                    'allowed_host' => $allowedHost,
                    'actual_host' => $urlHost,
                    'error' => 'host_not_allowed',
                ]);
                return null;
            }

            self::debugLog('download requisites start', [
                'src' => $src,
                'resolved_url' => $url,
            ]);
            self::inboundTrace('download_requisites start', [
                'src' => $src,
                'resolved_url' => $url,
            ]);

            $tmpPath = tempnam(sys_get_temp_dir(), 'ek_req_');
            if ($tmpPath === false || $tmpPath === '') {
                self::debugLog('download requisites result', [
                    'src' => $src,
                    'resolved_url' => $url,
                    'http_code' => 0,
                    'bytes' => 0,
                    'error' => 'tempnam_failed',
                ]);
                return null;
            }

            $fp = fopen($tmpPath, 'wb');
            if ($fp === false) {
                @unlink($tmpPath);
                self::debugLog('download requisites result', [
                    'src' => $src,
                    'resolved_url' => $url,
                    'http_code' => 0,
                    'bytes' => 0,
                    'error' => 'fopen_failed',
                ]);
                return null;
            }

            $curl = curl_init($url);
            if ($curl === false) {
                fclose($fp);
                @unlink($tmpPath);
                self::debugLog('download requisites result', [
                    'src' => $src,
                    'resolved_url' => $url,
                    'http_code' => 0,
                    'bytes' => 0,
                    'error' => 'curl_init_failed',
                ]);
                return null;
            }

            curl_setopt_array($curl, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            }
            if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                curl_setopt($curl, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
            }
            $ok = curl_exec($curl);
            $curlError = '';
            if ($ok === false) {
                $curlError = (string)curl_error($curl);
            }
            $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $effectiveUrl = (string)curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
            curl_close($curl);
            fclose($fp);

            if ($effectiveUrl !== '') {
                $effectiveParts = parse_url($effectiveUrl);
                $effectiveScheme = strtolower((string)($effectiveParts['scheme'] ?? ''));
                $effectiveHost = strtolower((string)($effectiveParts['host'] ?? ''));
                if ($effectiveScheme !== 'https' || $effectiveHost === '' || $effectiveHost !== $allowedHost) {
                    @unlink($tmpPath);
                    self::debugLog('download requisites result', [
                        'src' => $src,
                        'resolved_url' => $url,
                        'effective_url' => $effectiveUrl,
                        'allowed_host' => $allowedHost,
                        'http_code' => $httpCode,
                        'bytes' => 0,
                        'error' => 'effective_url_not_allowed',
                    ]);
                    return null;
                }
            }

            $bytes = (int)@filesize($tmpPath);
            if ($bytes > $maxFileSize) {
                @unlink($tmpPath);
                self::debugLog('download requisites result', [
                    'src' => $src,
                    'resolved_url' => $url,
                    'effective_url' => $effectiveUrl,
                    'http_code' => $httpCode,
                    'bytes' => $bytes,
                    'error' => 'file_too_large',
                ]);
                return null;
            }

            if ($ok === false || $httpCode < 200 || $httpCode >= 300 || $bytes <= 0) {
                @unlink($tmpPath);
                self::inboundTrace('download_requisites result', [
                    'src' => $src,
                    'resolved_url' => $url,
                    'effective_url' => $effectiveUrl,
                    'http_code' => $httpCode,
                    'bytes' => max($bytes, 0),
                    'error' => $curlError !== '' ? $curlError : 'download_failed_or_empty',
                ]);
                self::debugLog('download requisites result', [
                    'src' => $src,
                    'resolved_url' => $url,
                    'effective_url' => $effectiveUrl,
                    'http_code' => $httpCode,
                    'bytes' => max($bytes, 0),
                    'error' => $curlError !== '' ? $curlError : 'download_failed_or_empty',
                ]);
                return null;
            }

            $name = (string)($rawFile['ORIGINAL_NAME'] ?? $rawFile['FILE_NAME'] ?? basename(parse_url($url, PHP_URL_PATH) ?: 'requisites.dat'));
            if ($name === '') {
                $name = 'requisites.dat';
            }

            $fileArray = \CFile::MakeFileArray($tmpPath);
            if (!is_array($fileArray)) {
                @unlink($tmpPath);
                self::debugLog('download requisites result', [
                    'src' => $src,
                    'resolved_url' => $url,
                    'effective_url' => $effectiveUrl,
                    'http_code' => $httpCode,
                    'bytes' => $bytes,
                    'error' => 'make_file_array_failed',
                ]);
                return null;
            }

            $fileArray['name'] = $name;
            $fileArray['MODULE_ID'] = 'crm';

            self::debugLog('download requisites result', [
                'src' => $src,
                'resolved_url' => $url,
                'effective_url' => $effectiveUrl,
                'http_code' => $httpCode,
                'bytes' => $bytes,
                'error' => '',
            ]);
            self::inboundTrace('download_requisites result', [
                'src' => $src,
                'resolved_url' => $url,
                'effective_url' => $effectiveUrl,
                'http_code' => $httpCode,
                'bytes' => $bytes,
                'error' => '',
            ]);

            return $fileArray;
        }

        public function updateCompanyElement($params){
            if (!\Bitrix\Main\Loader::includeModule('crm')) {
                self::inboundTrace('update_company crm_module_not_loaded', [
                    'params_keys' => is_array($params) ? array_keys($params) : [],
                ]);
                return json_encode(['success' => 0, 'error' => 'crm_module_not_loaded'], JSON_UNESCAPED_UNICODE);
            }

            $companyId = (int)($params['OS_COMPANY_B24_ID'] ?? $params['ID'] ?? 0);
            $inputKeys = is_array($params) ? array_keys($params) : [];
            if ($companyId <= 0) {
                self::inboundTrace('update_company invalid_company_id', [
                    'company_id' => $companyId,
                    'params_keys' => $inputKeys,
                ]);
                self::debugLog('update_company: invalid company id', [
                    'company_id' => $companyId,
                    'params_keys' => $inputKeys,
                ]);
                return json_encode(['success' => 0, 'error' => 'empty_company_id'], JSON_UNESCAPED_UNICODE);
            }

            $uf = self::getUfFieldMap();
            $fields = [];
            $requisitesField = (string)($uf['REQUISITES_FILE'] ?? '');

            self::debugLog('update_company start', [
                'company_id' => $companyId,
                'params_keys' => $inputKeys,
                'target_file_uf' => $requisitesField,
            ]);
            self::inboundTrace('update_company start', [
                'company_id' => $companyId,
                'params_keys' => $inputKeys,
                'target_file_uf' => $requisitesField,
            ]);

            if (!empty($params['OS_COMPANY_NAME'])) {
                $fields['TITLE'] = (string)$params['OS_COMPANY_NAME'];
            }
            if (!empty($params['OS_COMPANY_CITY']) && !empty($uf['CITY'])) {
                $fields[$uf['CITY']] = (string)$params['OS_COMPANY_CITY'];
            }
            if (!empty($params['OS_COMPANY_PHONE'])) {
                $fields['PHONE'] = [[
                    'VALUE' => (string)$params['OS_COMPANY_PHONE'],
                    'VALUE_TYPE' => 'WORK',
                ]];
            }
            if (!empty($params['OS_COMPANY_EMAIL'])) {
                $fields['EMAIL'] = [[
                    'VALUE' => (string)$params['OS_COMPANY_EMAIL'],
                    'VALUE_TYPE' => 'WORK',
                ]];
            }
            if (!empty($params['OS_COMPANY_WEB_SITE'])) {
                $fields['WEB'] = [[
                    'VALUE' => (string)$params['OS_COMPANY_WEB_SITE'],
                    'VALUE_TYPE' => 'WORK',
                ]];
            }

            $rawRequisitesFile = null;
            $rawRequisitesKeyUsed = 'none';
            if (!empty($params['OS_REQUSITES_FILE']) && is_array($params['OS_REQUSITES_FILE'])) {
                $rawRequisitesFile = $params['OS_REQUSITES_FILE'];
                $rawRequisitesKeyUsed = 'OS_REQUSITES_FILE';
            } elseif (!empty($params['OS_REQUISITES_FILE']) && is_array($params['OS_REQUISITES_FILE'])) {
                $rawRequisitesFile = $params['OS_REQUISITES_FILE'];
                $rawRequisitesKeyUsed = 'OS_REQUISITES_FILE';
            }

            self::debugLog('updateCompanyElement requisites key resolved', [
                'company_id' => $companyId,
                'rawRequisitesKeyUsed' => $rawRequisitesKeyUsed,
                'rawRequisitesValueType' => $rawRequisitesKeyUsed === 'none'
                    ? null
                    : gettype($params[$rawRequisitesKeyUsed] ?? null),
                'rawRequisitesHasSrc' => is_array($rawRequisitesFile) && !empty($rawRequisitesFile['SRC']),
            ]);
            self::inboundTrace('update_company requisites key resolved', [
                'company_id' => $companyId,
                'rawRequisitesKeyUsed' => $rawRequisitesKeyUsed,
                'rawRequisitesValueType' => $rawRequisitesKeyUsed === 'none'
                    ? null
                    : gettype($params[$rawRequisitesKeyUsed] ?? null),
                'rawRequisitesHasSrc' => is_array($rawRequisitesFile) && !empty($rawRequisitesFile['SRC']),
            ]);

            if ($requisitesField !== '' && is_array($rawRequisitesFile)) {
                $fileForUf = $this->downloadRequisitesFileForCrmUf($rawRequisitesFile);
                if ($fileForUf !== null) {
                    $fields[$requisitesField] = $fileForUf;
                }
            }

            self::debugLog('update_company before update', [
                'company_id' => $companyId,
                'target_uf' => $requisitesField,
                'fields_keys' => array_keys($fields),
                'has_file' => ($requisitesField !== '' && isset($fields[$requisitesField])),
                'file_meta' => self::buildSafeFileMetaForDebug(
                    ($requisitesField !== '' && isset($fields[$requisitesField]) && is_array($fields[$requisitesField]))
                        ? $fields[$requisitesField]
                        : null
                ),
            ]);
            self::inboundTrace('update_company before update', [
                'company_id' => $companyId,
                'target_uf' => $requisitesField,
                'fields_keys' => array_keys($fields),
                'has_file' => ($requisitesField !== '' && isset($fields[$requisitesField])),
            ]);

            if ($fields === []) {
                self::debugLog('update_company skipped: empty fields', [
                    'company_id' => $companyId,
                ]);
                return json_encode([
                    'success' => 0,
                    'error' => 'empty_update_fields',
                ], JSON_UNESCAPED_UNICODE);
            }

            $entity = new \CCrmCompany(false);
            $updated = $entity->Update(
                $companyId,
                $fields,
                true,
                [
                    'CURRENT_USER' => \CCrmSecurityHelper::GetCurrentUserID(),
                    'IS_SYSTEM_ACTION' => false,
                    'ENABLE_DUP_INDEX_INVALIDATION' => true,
                    'REGISTER_SONET_EVENT' => false,
                ]
            );

            self::debugLog('update_company after update', [
                'company_id' => $companyId,
                'success' => (bool)$updated,
                'last_error' => $updated ? '' : (string)$entity->LAST_ERROR,
            ]);
            self::inboundTrace('update_company after update', [
                'company_id' => $companyId,
                'success' => (bool)$updated,
                'last_error' => $updated ? '' : (string)$entity->LAST_ERROR,
            ]);

            if (!$updated) {
                return json_encode([
                    'success' => 0,
                    'error' => (string)$entity->LAST_ERROR,
                ], JSON_UNESCAPED_UNICODE);
            }

            return json_encode([
                'success' => 1,
                'company_id' => $companyId,
            ], JSON_UNESCAPED_UNICODE);
        }
    }