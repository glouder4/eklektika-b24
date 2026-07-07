# Ответ CRM-команды → команде сайта (регистрация, inbound perf P0/P2)

Дата: 2026-05-27. Репозиторий: `eklektika-b24` (портал Bitrix24).

## Кратко

Подтверждаем внедрение **P0** (perf регистрации, без блокирующего outbound в HTTP inbound) и **P2** (дозакрытие `crm.company.update` / `crm.contact.company.add`). Карта UF для связи компании с элементом ИБ — ниже. Поля оплаты / платёжных документов **не входят** в контракт регистрации `CRM_METHOD`.

---

## P0 — подтверждено (уже на портале)

| Сценарий | Поведение inbound | Исходящий шум |
|----------|-------------------|---------------|
| `CRM_METHOD` → `crm.company.add` | `CompanySync::suspendOutbound` на время `CCrmCompany::Add` | Нет синхронного `UPDATE_COMPANY` на сайт в том же HTTP-запросе |
| `CRM_METHOD` → `crm.contact.add` / `crm.contact.update` | `ContactSync::suspendOutbound` на время `Add`/`Update`; **нет** `sendContactToSiteNow` после успеха (ADR R26) | Нет синхронного `UPDATE_CONTACT` (~18 s при медленном сайте) |
| `ACTION` → `UPDATE_COMPANY` | `CompanySync::markInboundCompanyUpdate` перед `CCrmCompany::Update` | Одноразовое подавление `UPDATE_COMPANY` outbound для этого сохранения |

**Ожидание для сайта:** ответ inbound по регистрации не должен ждать CURL к сайту из CRM. Push контакта/компании на сайт после регистрации — отдельный сценарий (очередь / ручное сохранение в CRM / будущий P1).

**Проверка ответа `CRM_METHOD` (регистрация):**

- При **`success: 0`** — в теле **всегда** есть **`reason_code`** (например `company_add_failed`, `contact_add_failed`); HTTP обычно **200** (доменная ошибка, не транспорт).
- При **`success: 1`** для `crm.company.add` / `crm.contact.add` — **`result`** = числовой CRM ID.
- Если сериализация ответа упала — **HTTP 500**, `reason_code`: **`response_encode_failed`** (не пустое тело).
- В `inbound-b24.log` для шагов регистрации смотреть `site_requests_handler.dispatch.done`: `crm_method`, `result_int`, `reason_code`, `response_body_bytes` (детали — `actions/CRM_METHOD.md`).

**Уникальность контакта при регистрации (2026-05):**

- Ключ уникальности — **только email** (сайт + CRM согласованы).
- `GET_CONTACT_ID` — поиск **только по `EMAIL`**; `PHONE` в запросе игнорируется для lookup (см. `actions/GET_CONTACT_ID.md`).
- `crm.contact.add` через inbound: `DISABLE_DUPLICATE_CONTROL` — не блокировать создание при совпадении телефона с другим контактом.
- На портале в **CRM → Контроль дубликатов** для контакта рекомендуется оставить уникальность по **email**, не по телефону (настройка админки).
- Сайт: `crm.duplicate.findbycomm` только с `type: EMAIL`; `type: PHONE` в потоке регистрации не использовать для отказа.

---

## P2 — внедрено в этом релизе

| Метод | Изменение |
|-------|-----------|
| `crm.company.update` | Перед `CCrmCompany::Update` — **`CompanySync::markInboundCompanyUpdate($id)`** (как в `UPDATE_COMPANY`). Исходящий `UPDATE_COMPANY` для этого update не уходит. |
| `crm.contact.company.add` | `CCrmContact::Update` обёрнут в **`ContactSync::suspendOutbound(true/false)`** — нет ~2.5 s `UPDATE_CONTACT` при привязке контакта к компании в цепочке регистрации. **Только** привязка `COMPANY_ID` (ADR R29 v2). |
| `crm.contact.update` + `inherit_from_company` | Для registration + existing INN: наследование полей от компании + обязательный outbound `UPDATE_CONTACT` (ADR R29 v2). Без флага — поведение R26 (без синхронного outbound). |

Контракт: `local/bitrix24-inbound-from-site-contracts/actions/CRM_METHOD.md`.

### `skip_outbound_sync` (опционально)

В `PARAMS` для `crm.company.update` допускается флаг **`skip_outbound_sync`** (truthy: `Y`, `true`, `1`, `'1'`) как явное намерение «не эхоить на сайт». На портале inbound-обновление компании **всегда** помечается через `markInboundCompanyUpdate`; отдельного «включить outbound» через этот флаг нет.

---

## UF: связь компания ↔ элемент сайта

Источник правды: `local/modules/yomerch.b24.contract/lib/config/uf_mapping.php` (`UfMap`).

| Роль | Ключ карты | UF на портале |
|------|------------|---------------|
| **Канонический** ID элемента ИБ | `company.site_element_id` | **`UF_CRM_1774915439581`** |
| **Legacy** (поиск inbound, fallback outbound) | `company.site_element_id_legacy_alias` | **`UF_CRM_3804624439373`** |

**Рекомендация сайту:** при `crm.company.add` / `crm.company.update` передавать в `fields` **оба** UF с одним и тем же положительным ID элемента **или** использовать ключи из `UfMap`, а не хардкодить UF в разных местах. Не использовать `0` / пустое как ID элемента.

Inbound `UPDATE_COMPANY` и поиск компании принимают канонический UF, legacy UF или транспортное поле `SITE_ELEMENT_ID` (см. `UPDATE_COMPANY.md`).

---

## Поле «Форма оплаты» (обязательное на портале)

| Параметр | Значение |
|----------|----------|
| UF CRM | **`UF_CRM_1756112106093`** |
| Значение при регистрации с сайта | **`876`** («Предоплата 100%») |

Сайт передаёт UF в `fields` при `crm.company.add` — **верно**. Без поля inbound/CRM возвращает ошибку валидации (`success: 0`).

В `uf_mapping.php` это поле **не заведено** (бизнес-обязательность CRM, не контур sync). **Обязуемся заранее сообщить**, если на проде сменится enum ID или обязательность поля.

Платёжные документы сделок/заказов — отдельный контур, не часть P0/P2 регистрации.

---

## Регистрация юрлица / агента — обязательные шаги

Регистрация юридического лица или рекламного агента на сайте **не завершается** только вызовом `crm.contact.add`. Обязательны успешные шаги на **сайте** и в **CRM**:

| Шаг | Владелец | Критерий успеха |
|-----|----------|-----------------|
| 1. `crm.company.add` (или merge по ИНН) | CRM inbound | `success: 1`, **`result`** = числовой CRM ID компании (int). При dedup по ИНН — те же правила: `reason_code` может быть `company_add_use_existing_for_contact` или `company_add_child_under_head_inn`, но **`result` всегда int** |
| 2. Создание/обновление элемента каталога на сайте | **Сайт** (`createCompanyElement` / аналог) | Элемент ИБ с **`OS_COMPANY_B24_ID`** = CRM ID из шага 1; при `false` — **регистрация блокируется** (ответ пользователю, без `UF_B24_USER_ID`, без завершения flow) |
| 3. `crm.contact.add` + привязка + наследование (existing INN) | CRM inbound | `crm.contact.add` → `success: 1`, `result` = contact ID (`fields` с `UF_CRM_3804624445748` или `contact.site_user_id`). Затем `crm.contact.company.add` — **только** `fields.COMPANY_ID`. Для **existing INN** (`company_add_use_existing_for_contact`) — отдельно `crm.contact.update` с **`inherit_from_company: true`** (ADR R29 v2): наследование менеджеров, рекламного агента, `ACTIVE` + обязательный outbound `UPDATE_CONTACT` |

**Граница CRM-репо:** inbound **не знает** об успехе `createCompanyElement` — это оркестрация **на сайте**. CRM гарантирует корректный контрактный ответ только на `crm.company.add` / последующие `CRM_METHOD`.

---

## Инвариант `site_element_id` ↔ `OS_COMPANY_B24_ID`

К концу успешной регистрации должна существовать согласованная пара:

| Сторона | Поле | Значение |
|---------|------|----------|
| CRM компания | UF `company.site_element_id` (+ legacy alias) | Положительный ID элемента ИБ на сайте |
| Элемент каталога на сайте | **`OS_COMPANY_B24_ID`** | CRM ID компании (`result` из `crm.company.add`) |

Inbound `UPDATE_COMPANY` и исходящий `CompanySync` резолвят компанию по UF; сайт ищет/обновляет элемент по `OS_COMPANY_B24_ID`.

---

## Допустимые порядки шагов (вариант A / B)

Оба варианта допустимы, если в конце выполнен инвариант выше.

**Вариант A — элемент до CRM:**

1. Сайт создаёт черновик элемента ИБ (или резервирует ID).
2. `crm.company.add` с `UF_CRM_1774915439581` + legacy UF = ID элемента (+ реквизит, форма оплаты `876` и т.д.).
3. Сайт финализирует элемент, проставляет **`OS_COMPANY_B24_ID`** = `result` шага 2.
4. `crm.contact.add` → `crm.contact.company.add` → при existing INN — `crm.contact.update` + `inherit_from_company`.

**Вариант B — CRM ID до элемента:**

1. `crm.company.add` → `company_id` (`result`).
2. `createCompanyElement(..., OS_COMPANY_B24_ID => company_id)` — при **`false`** rollback/блок на сайте.
3. `crm.company.update` с UF `site_element_id` = ID элемента (если не передали в add).
4. Contact chain (шаг 3 таблицы выше).

---

## Ответственность сайта при failed `createCompanyElement`

Если **`crm.company.add` вернул `success: 1` и числовой `result`**, но **`createCompanyElement` вернул `false`**:

- **Регистрация блокируется** — пользователю ошибка, без выдачи `UF_B24_USER_ID`, без финализации сессии регистрации.
- Сайт **не** должен вызывать `crm.contact.add` / завершать flow «как будто компания создана».
- Откат/компенсация «осиротевшей» CRM-компании — **политика сайта** (ручной разбор, отложенный cleanup); CRM inbound в этом HTTP не участвует.

---

## Canonical sequence регистрации (CRM_METHOD, contact chain, R29 v2)

После успешных шагов компании (`crm.company.add` + `createCompanyElement` / `crm.company.update`):

1. **`crm.contact.add`** — `fields` контакта, в т.ч. **`UF_CRM_3804624445748`** или ключ **`contact.site_user_id`** (ID пользователя сайта).
2. **`crm.contact.company.add`** — `id` контакта, `fields.COMPANY_ID` компании. **Только привязка** — без `inherit_from_company`, без наследования, без outbound.
3. **`crm.contact.update`** — **только** при **existing INN** (шаг `crm.company.add` с `reason_code=company_add_use_existing_for_contact`): `id` контакта, `fields` (может быть `{}`), **`inherit_from_company: true`**. Контакт наследует `ASSIGNED_BY_ID`, второй менеджер, рекламного агента, `ACTIVE`; ответ `reason_code=contact_inherited_from_company`.
4. **Outbound `UPDATE_CONTACT`** на сайт (обязательный после шага 3) — минимум полей: `B24_ID`, `UF_CRM_3804624445748`, `ASSIGNED_BY_ID`, `UF_CRM_1757682312`, `UF_CRM_1698752707853`, `ACTIVE`.

При обновлении компании после создания — `crm.company.update` с тем же правилом UF.

---

## Расследование инцидента: контакт не создан

Полный разбор кейса и чеклист — в **[INCIDENT_REGISTRATION_CONTACT_MISSING.md](./INCIDENT_REGISTRATION_CONTACT_MISSING.md)**.

### Классификация: CRM vs сайт

| Признак | Вероятная сторона | Действие |
|---------|-------------------|----------|
| `crm.contact.add` в `inbound-b24.log`: `dispatch.done` с `success: 0` / непустой `reason_code` (`contact_add_failed` и т.п.) | **CRM inbound** | Смотреть `error_truncated`, `reason_code` в trace; проверить дубликаты email, обязательные UF |
| `crm.contact.add`: `result_int` > 0, но на сайте нет `UF_B24_USER_ID` / flow не завершён | **Сайт** | Смотреть оркестрацию после успешного CRM_METHOD; `createCompanyElement`, post-registration sync |
| `crm.company.add` успешен, `createCompanyElement` = `false`, но сайт всё равно вызвал `crm.contact.add` | **Сайт** | Нарушение таблицы обязательных шагов — контакт мог создаться «в обход» без валидной компании на сайте |
| Нет строки `dispatch.done` с `crm_method=crm.contact.add` для `trace_id` регистрации | **Сайт** | Запрос до CRM не дошёл или оборвался до `CRM_METHOD`; смотреть CURL/таймауты, CEventLog на сайте |
| `crm.contact.add` успешен, `crm.contact.company.add` с `success: 0` | **CRM inbound** / данные запроса | Проверить `company_id_param`, `contact_id_param` в trace шага `crm.contact.company.add` |
| `crm.contact.update` + `inherit_from_company`: `success: 0` или нет outbound на сайте | **CRM inbound** / оркестрация сайта | Проверить `inherit_from_company`, `contact_inherited`, `copied_fields_count` в trace; был ли вызван шаг 3 v2 sequence |

### Что CRM-команде нужно от сайта

| Поле | Зачем |
|------|-------|
| Дата/время регистрации (TZ портала или UTC) | Окно поиска в `inbound-b24.log` и CEventLog |
| Email пользователя | Ключ уникальности контакта; поиск в CRM |
| ИНН компании | Dedup `crm.company.add`, связь контакт ↔ компания |
| ID пользователя сайта (`b_user.ID`) | Сверка с `contact.site_user_id` / UF на контакте |
| `trace_id` из ответа inbound (если есть) | Сквозная корреляция сайт ↔ `local/logs/inbound-b24.log` |

Без `trace_id` — указать точное время вызова `crm.contact.add` (±1–2 мин) и HTTP-статус ответа.

### Что проверить на сайте

| Источник | Что искать |
|----------|------------|
| **CEventLog** | События регистрации в окне инцидента; ошибки до/после вызова inbound |
| **`YOMERCH_POST_REGISTRATION_SYNC_ISSUE`** | Причины сбоя post-registration sync (`reason` / код в описании события) — контакт в CRM мог создаться, но сайт не зафиксировал `UF_B24_USER_ID` |
| Логи оркестрации регистрации | Был ли вызов `crm.contact.add`; не пропущен ли шаг из-за `createCompanyElement` = `false` |

### Что смотреть на портале CRM (`inbound-b24.log`)

Для шага `crm.contact.add`, `crm.contact.company.add` и (при existing INN) `crm.contact.update` — событие **`site_requests_handler.dispatch.done`** в `local/logs/inbound-b24.log` (fallback: `/tmp/inbound-b24.log`):

| Поле trace | Смысл для инцидента |
|------------|---------------------|
| `crm_method` | Должно быть `crm.contact.add`, `crm.contact.company.add` и/или `crm.contact.update` |
| `reason_code` | Пусто при `success: 1`; иначе доменная причина отказа |
| `result_int` | CRM ID контакта; `null` / 0 при провале |
| `error_truncated` | Текст ошибки CRM при `success: 0` |
| `response_body_bytes` | 0 или аномально мало — подозрение на обрыв ответа |
| `company_id_param` / `contact_id_param` | Только для `crm.contact.company.add` — сверка привязки |

Полная таблица полей trace — `actions/CRM_METHOD.md` (раздел «Trace: `site_requests_handler.dispatch.done`»).

---

## CRM → сайт (`UPDATE_COMPANY`, «Рекламный агент»)

| Параметр | Значение |
|----------|----------|
| URL на сайте | `POST {site_url}/local/modules/yomerch.b24.inbound/endpoint.php` |
| Обработчик на сайте | `InboundGateway` → `OnlineService\Site\Company::updateCompanyElement` (не `site_ajax.php` / `CompanyUpdater`) |
| `ACTION` | `UPDATE_COMPANY` |
| Токен в POST/заголовке | **`inbound_secret`** с сайта (`config.local.php`); на портале CRM в `site_sync_settings.local.php`: **`inbound_secret`** или **`site_inbound_secret`** (см. `YOMERRCH24_SITE_OUTBOUND_TOKEN`) |
| Ключевые поля | `OS_COMPANY_B24_ID`, `OS_IS_MARKETING_AGENT`, `ACTIVE`; поиск элемента по B24 ID; UF элемента опционально |

После сохранения компании с «рекламный агент» в CRM контакты получают UF контакта в формате enum «да» (не сырой ID enum компании); в `UPDATE_CONTACT` поле **`UF_ADVERTISING_AGENT`** не уходит пустым при `ACTIVE=Y` (см. R13-04, `OutboundContactMarketingForSite`).

**503** при outbound: (1) неверный/пустой `inbound_secret`; (2) **nginx rate-limit** на yomerch.ru — HTML «You have made too many requests per second» (нужен whitelist IP CRM или ослабление лимита на `endpoint.php`; retry с CRM только ухудшает ситуацию).

## Контакты по коду

- `InboundEndpoint.php` — `crmCompanyUpdate`, `crmContactUpdate`, `crmContactCompanyAdd`
- `OutboundRequest::resolveOutboundQueryUrl()` — endpoint.php; `resolveOutboundSyncToken()` — inbound_secret
- `CompanySync::markInboundCompanyUpdate`
- `ContactSync::suspendOutbound`
- ADR: R26 (contact outbound), R28 (registration discovery)
