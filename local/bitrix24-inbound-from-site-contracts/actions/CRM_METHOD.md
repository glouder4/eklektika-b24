# ACTION: `CRM_METHOD`

Унифицированный вызов разрешённых операций CRM через входящий канал.

Реализация: `InboundEndpoint::handleCrmMethod`.

## Обязательные поля тела запроса

| Поле | Описание |
|------|----------|
| `ACTION` | `CRM_METHOD` |
| `METHOD` | Одна из поддерживаемых строк ниже |
| `PARAMS` | Объект или JSON-строка с объектом параметров для конкретного метода |

Если `PARAMS` не массив и не валидный JSON-массив — трактуется как пустой объект `{}`.

## Поддерживаемые значения `METHOD`

| METHOD | Назначение | Ожидаемые `PARAMS` (ключевые поля) |
|--------|------------|-------------------------------------|
| `crm.requisite.list` | Список реквизитов | `filter` (массив), опционально `select`; при фильтре по `RQ_INN` без `ENTITY_TYPE_ID` принудительно добавляется `ENTITY_TYPE_ID = 4` |
| `crm.company.add` | Создание компании | `fields` — массив полей для `CCrmCompany::Add`. На время `Add` подавляется полный **`CompanySync::onAfterCompanyAdd`** (`CompanySync::suspendOutbound`) — inbound не ждёт исходящий `UPDATE_COMPANY` на сайт. **Валидация `site_element_id`:** если в `fields` передан канонический UF (`UF_CRM_1774915439581`), legacy UF (`UF_CRM_3804624439373`), `SITE_ELEMENT_ID` или `site_element_id` — значение должно быть **положительным int**; иначе `success: 0`, `reason_code`: `company_add_invalid_site_element_id`. **Отсутствие** этих ключей в `fields` допустимо (вариант B: элемент на сайте после add, затем `crm.company.update`). **Если в `fields` есть `RQ_INN` и в CRM уже есть компания с этим ИНН:** при **ровно одной** совпавшей компании и включённом UF **`company.head_company_flag`** (`UF_CRM_1758028888`) создаётся **новая дочерняя** компания: в UF **`company.holding`** (`UF_CRM_1758028816`) пишется CRM ID головной компании; затем UF **`company.holding_group_members`** (`UF_CRM_1776426878`) обновляется **одинаковым списком** (головная + все дочерние) на всех участниках холдинга. Ответ: `success: 1`, **`result`** = int (ID дочерней), `reason_code`: `company_add_child_under_head_inn`, `data`: `head_company_id`, `child_company_id`, `holding_member_company_ids`. Если головной признак **выключен** — новая компания **не** создаётся: `success: 1`, **`result`** = int (ID существующей), `reason_code`: `company_add_use_existing_for_contact`, `data.attach_contact_only`: `true`. Несколько компаний с одним ИНН — `company_add_ambiguous_inn`. |
| `crm.company.get` | Чтение компании | `id` — числовой ID |
| `crm.company.update` | Обновление компании | `id`, `fields`. Перед `CCrmCompany::Update` вызывается **`CompanySync::markInboundCompanyUpdate`** (как в `UPDATE_COMPANY`) — исходящий **`UPDATE_COMPANY`** на сайт для этого сохранения не уходит. Опционально **`skip_outbound_sync`** (truthy: `Y`, `true`, `1`, `'1'`) — явный флаг намерения у вызывающего; отдельного отключения подавления нет, inbound-обновление компании всегда помечается. |
| `crm.requisite.add` | Создание реквизита | `fields` |
| `crm.requisite.update` | Обновление реквизита | `id`, `fields` |
| `crm.contact.add` | Создание контакта | `fields`. Во время `CCrmContact::Add` исходящие CRM-события подавляются (`ContactSync::suspendOutbound`). **Немедленный** `UPDATE_CONTACT` на сайт **не выполняется** (ответ inbound не ждёт outbound; см. ADR R26). |
| `crm.contact.update` | Обновление контакта | `id`, `fields` (мультиполя нормализуются в `n0`, `n1`, …; при **`inherit_from_company`** `fields` может быть `{}`). События `onAfter` подавлены на время `Update`. **Без** `inherit_from_company` — **немедленный** outbound на сайт **не выполняется** (ADR R26). Опционально **`inherit_from_company`** (truthy: `true`, `Y`, `1`, `'1'`, `'yes'`) — **только** для registration + existing INN (после `crm.contact.company.add`): контакт наследует от primary company `ASSIGNED_BY_ID`, второй менеджер (`contact.site_sync_value` ← `company.site_sync_value`), рекламного агента и `ACTIVE`; затем обязательный **`ContactSync::sendRegistrationInheritedContactToSiteNow`** с payload `UPDATE_CONTACT` (ADR R29 v2). Ответ при наследовании: `reason_code=contact_inherited_from_company`, `data.evidence`, `data.update_contact_outbound` (`success`, `reason_code` сайта, ожидается `update_contact_ok`). |
| `crm.contact.company.add` | Привязка контакта к компании | `id` — контакт, `fields.COMPANY_ID` — компания. На время `CCrmContact::Update` — **`ContactSync::suspendOutbound`** (нет синхронного **`UPDATE_CONTACT`** ~2.5 s при регистрации). **Только** привязка `COMPANY_ID` — без наследования, без outbound, без `inherit_from_company` (ADR R29 v2). Наследование — отдельный вызов `crm.contact.update` + `inherit_from_company`. |

Иные значения `METHOD` возвращают ошибку с текстом `Unsupported CRM METHOD: …` и `reason_code`: `unsupported_crm_method`.

## Ответы

Унифицированно:

- Успех: `success`: `1`, полезная нагрузка чаще в `result` (как возвращает соответствующий helper); для `crm.company.add` / `crm.contact.add` при успешном `Add` — **`result` = числовой CRM ID** (int). Для `crm.company.add` при существующей компании по ИНН дополнительно могут быть `reason_code` и `data` (см. таблицу выше).
- Ошибка CRM / валидация / доменный отказ: `success`: `0`, текст в `error`, **обязательно `reason_code`** (стабильный машинный код; см. `InboundEndpoint.php`). Примеры: `company_add_failed`, `company_add_invalid_site_element_id`, `contact_add_failed`, `unsupported_crm_method`, `crm_module_unavailable`.
- Транспорт для распознанного `ACTION=CRM_METHOD`: **HTTP 200** (бизнес-ошибка не меняет HTTP), кроме случаев ниже.

### `response_encode_failed` (HTTP 500)

Если финальный `json_encode` ответа не удался (некодируемые данные в `result` / `data` и т.п.), handler **не** отдаёт пустое тело на HTTP 200:

- **HTTP 500**
- JSON: `success`: `0`, `reason_code`: `response_encode_failed`, `error` — сообщение `json_last_error_msg()`
- В `local/logs/inbound-b24.log`: событие `site_requests_handler.response.encode_failed` (`json_error`, `action`)

### Trace: `site_requests_handler.dispatch.done` (регистрация)

Для **`ACTION=CRM_METHOD`** (в т.ч. `crm.company.add`, `crm.contact.add`) в лог дополнительно пишутся (без PII из `fields`):

| Поле | Описание |
|------|----------|
| `crm_method` | Значение `METHOD` из запроса |
| `reason_code` | Из тела ответа; при `success: 1` без кода — пустая строка; при dedup INN (`company_add_use_existing_for_contact`, `company_add_child_under_head_inn`) — **непустой** код также пишется в trace |
| `has_rq_inn_filter` | Только `crm.requisite.list`: `true`, если в `PARAMS.filter` есть ключ `RQ_INN` (значение не логируется) |
| `has_rq_inn` | Только `crm.company.add`: `true`, если в `PARAMS.fields` есть ключ `RQ_INN` (значение не логируется) |
| `contact_id_param` | Только `crm.contact.company.add` / `crm.contact.update`: положительный int из `PARAMS.id` или `null` |
| `company_id_param` | Только `crm.contact.company.add`: положительный int из `PARAMS.fields.COMPANY_ID` или `null` |
| `inherit_from_company` | Только `crm.contact.update`: `true`/`false` — был ли передан truthy флаг `PARAMS.inherit_from_company` |
| `contact_inherited` | Только `crm.contact.update`: `true`, если в ответе `reason_code=contact_inherited_from_company` или `data.contact_inherited` |
| `copied_fields_count` | Только `crm.contact.update`: число скопированных полей из `data.evidence.copied_fields` (без имён UF в trace) |
| `update_contact_outbound_ok` | Только `crm.contact.update` + `inherit_from_company`: `true`, если outbound `UPDATE_CONTACT` на сайт вернул `success: 1` |
| `update_contact_reason_code` | Только `crm.contact.update` + `inherit_from_company`: `reason_code` ответа сайта (ожидается `update_contact_ok`; без PII) |
| `result_type` | `gettype(result)` |
| `result_int` | Числовой CRM ID, если `result` int / numeric string |
| `response_body_bytes` | Длина сериализованного JSON-ответа |
| `error_truncated` | При `success: 0` — усечённый `error` (до 300 символов) |

Сайт **обязан** проверять `(int)success === 1` перед использованием `result` как ID; при `success: 0` — читать `reason_code` и `error`.

## Контракт данных для сайта

Для каждого вызова **`PARAMS.fields`** должны соответствовать требованиям CRM и бизнес-правилам портала. В частности, для сценариев синхронизации с сайтом в полях компании нужно явно передавать пользовательские поля связи с элементом инфоблока:

| Роль | Ключ `UfMap` | UF CRM (текущий портал) |
|------|----------------|-------------------------|
| Канонический ID элемента ИБ на сайте | `company.site_element_id` | **`UF_CRM_1774915439581`** |
| Legacy-зеркало (поиск, исходящий fallback) | `company.site_element_id_legacy_alias` | **`UF_CRM_3804624439373`** |

Сайт при создании/обновлении компании через `crm.company.add` / `crm.company.update` должен писать **оба** UF одним и тем же положительным ID элемента **или** использовать ключи из **`UfMap`** / `uf_mapping.php` (`local/modules/yomerch.b24.contract/lib/config/uf_mapping.php`), чтобы `UPDATE_COMPANY`, inbound-поиск и исходящий `CompanySync` не расходились. Значение **`0`** или пустое — недопустимый ID элемента.

## Регистрация юрлица / агента (сквозной контракт)

Полная регистрация на сайте включает шаги **вне** inbound CRM (см. `CRM_TEAM_RESPONSE_REGISTRATION_SYNC.md`):

| Шаг | Где | Inbound CRM |
|-----|-----|-------------|
| `crm.company.add` | CRM | Да — `result` = int |
| `createCompanyElement` + **`OS_COMPANY_B24_ID`** | **Сайт** | Нет — оркестрация сайта |
| `crm.contact.add` + `crm.contact.company.add` (+ `crm.contact.update` при existing INN) | CRM | Да |

**Инвариант:** к концу регистрации CRM UF `site_element_id` ↔ элемент каталога с **`OS_COMPANY_B24_ID`** = CRM ID компании.

**При `createCompanyElement` → false** после успешного `crm.company.add` сайт **блокирует** регистрацию; CRM не получает сигнал об этом шаге.

Допустимые порядки: **вариант A** (элемент/ID → add с UF) или **вариант B** (add → элемент с `OS_COMPANY_B24_ID` → `crm.company.update` с UF).
