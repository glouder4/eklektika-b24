# Инцидент: «Регистрация: b_user создан, контакт CRM не появился (существующий ИНН)»

Runbook для CRM-команды / inbound-операторов портала Bitrix24.  
Репозиторий: `eklektika-b24`, область: `local/`.

---

## 1. Симптом (контекст обращения клиента)

Пользователь **успешно зарегистрировался на сайте** (создан `b_user`, иногда уже проставлен `UF_B24_USER_ID`), но **контакт в CRM не появился** или не привязан к компании.

Типичный контекст кейса:

- Регистрация **нового сотрудника** юрлица с **уникальным email** (контакт с таким email в CRM ранее отсутствовал).
- **ИНН компании уже есть в CRM** — при `crm.company.add` inbound возвращает существующую компанию (`company_add_use_existing_for_contact`), а не создаёт новую.
- На сайте пользователь видит «успешную» регистрацию или частично завершённый профиль, в CRM — нет нового контакта / нет связи контакт ↔ компания / не заполнен `contact.site_user_id`.

**Ключевой вопрос инцидента:** inbound CRM **получил и обработал** `crm.contact.add`, или цепочка **оборвалась на сайте до вызова CRM**?

---

## 2. Ожидаемое бизнес-поведение

### Инвариант «один ИНН — одна компания» (dedup R15)

- Если в CRM уже есть **ровно одна** компания с тем же **ИНН** и **не** включён признак головной компании (`company.head_company_flag`), inbound **`crm.company.add` не создаёт новую компанию**.
- Ответ: `success: 1`, **`result`** = int (CRM ID **существующей** компании), `reason_code`: **`company_add_use_existing_for_contact`**, `data.attach_contact_only`: `true`.

Это **нормально** для сценария «новый сотрудник в существующем юрлице».

### Ожидаемая цепочка для нового сотрудника (существующий ИНН)

| # | Шаг | Где | Критерий успеха |
|---|-----|-----|-----------------|
| 1 | `GET_CONTACT_ID` (опционально) | CRM inbound | `contact_not_found` — ok для нового email |
| 2 | `crm.requisite.list` / dedup по ИНН (часто внутри `crm.company.add`) | CRM inbound | Найдена существующая компания |
| 3 | `crm.company.add` | CRM inbound | `success: 1`, `result` = int, `reason_code` = `company_add_use_existing_for_contact` |
| 4 | Элемент каталога компании на сайте (ИБ, на стенде часто **57**) | **Сайт** | Элемент с **`OS_COMPANY_B24_ID`** = CRM ID из шага 3; пользователь добавлен в **`OS_COMPANY_USERS`** |
| 5 | `crm.contact.add` | CRM inbound | `success: 1`, `result` = contact ID; в `fields` — **`contact.site_user_id`** → UF **`UF_CRM_1776075126830`** (= `b_user.ID`) |
| 6 | `crm.contact.company.add` | CRM inbound | `success: 1`; `fields.COMPANY_ID` = company ID из шага 3 |
| 7 | Финализация на сайте | **Сайт** | `UF_B24_USER_ID` у пользователя, сессия регистрации закрыта |

Inbound CRM **не знает** об успехе шага 4 — это оркестрация **на сайте** (см. `CRM_TEAM_RESPONSE_REGISTRATION_SYNC.md`).

---

## 3. Чеклист расследования (сторона CRM / inbound)

### 3.1. Входные данные от поддержки / сайта

- [ ] Дата и время регистрации (TZ: UTC+5 / локальное время стенда).
- [ ] Email пользователя (ключ уникальности контакта).
- [ ] ИНН юрлица.
- [ ] `b_user.ID` на сайте.
- [ ] `UF_B24_USER_ID` (если уже проставлен).
- [ ] `trace_id` / **`X-Sync-Request-Id`** из ответа inbound (если сайт сохранил).
- [ ] ID элемента каталога компании на сайте (ИБ **57** на prod-сайте).

### 3.2. Проверка в CRM (UI)

- [ ] Поиск контакта **только по email** — контакт отсутствует / есть дубликаты?
- [ ] Поиск компании по **ИНН** (реквизиты) — одна компания или неоднозначность?
- [ ] У существующей компании заполнены UF `company.site_element_id` / legacy alias?
- [ ] В контакте (если найден) UF **`UF_CRM_1776075126830`** (`contact.site_user_id`) = `b_user.ID`?

### 3.3. Проверка inbound-лога (обязательно)

Путь: **`local/logs/inbound-b24.log`** (fallback Linux: **`/tmp/inbound-b24.log`**).

- [ ] Есть ли **`site_requests_handler.payload.received`** в окне времени инцидента?
- [ ] Есть ли **`site_requests_handler.dispatch.done`** с `crm_method=crm.company.add` и `reason_code=company_add_use_existing_for_contact`?
- [ ] Есть ли **`dispatch.done`** с `crm_method=crm.contact.add`?
- [ ] Есть ли **`dispatch.done`** с `crm_method=crm.contact.company.add`?
- [ ] При `success: 0` — зафиксировать `reason_code` и `error_truncated` (`contact_add_failed`, `company_add_failed`, …).
- [ ] Нет ли **`sync_forbidden`**, **`dedup_duplicate` (409)**, **`response_encode_failed` (500)**?
- [ ] Совпадает ли `trace_id` / `X-Sync-Request-Id` между шагами одной регистрации?

### 3.4. Самодиагностика окружения (если лог пуст)

- [ ] `local/inbound-test.php` — права на `local/logs`, наличие `site_sync_settings.local.php`.
- [ ] См. ADR R11 (`adr-r11-inbound-self-diagnostics.md`).

---

## 4. Экспорт и фильтрация `local/logs/inbound-b24.log`

### Путь и fallback

| Sink | Путь |
|------|------|
| Основной | `{DOCUMENT_ROOT}/local/logs/inbound-b24.log` |
| Fallback | `/tmp/inbound-b24.log` (если основной недоступен для записи) |

Формат строки: `[trace] <event> {JSON}`.

### Выборка по диапазону дат

На сервере портала (пример: инцидент **2026-07-07**, окно ±30 мин):

```bash
# Linux — по полю ts в JSON (UTC ISO)
grep '"ts":"2026-07-07' local/logs/inbound-b24.log > /tmp/inbound-2026-07-07.registration.incident.log

# Или по локальной дате в имени архива + ручной отбор trace_id
sed -n '/2026-07-07T09:/,/2026-07-07T10:/p' local/logs/inbound-b24.log
```

Windows (PowerShell):

```powershell
Select-String -Path "local\logs\inbound-b24.log" -Pattern '"ts":"2026-07-07' |
  Set-Content "local\logs\inbound-2026-07-07.registration.incident.log"
```

### Grep по ключевым событиям регистрации

```bash
# Все dispatch по CRM_METHOD в окне trace
grep 'site_requests_handler.dispatch.done' local/logs/inbound-b24.log | grep 'crm.contact.add'
grep 'site_requests_handler.dispatch.done' local/logs/inbound-b24.log | grep 'crm.contact.company.add'
grep 'site_requests_handler.dispatch.done' local/logs/inbound-b24.log | grep 'crm.company.add'
grep 'site_requests_handler.dispatch.done' local/logs/inbound-b24.log | grep 'company_add_use_existing_for_contact'

# Поиск dedup / requisite до company.add
grep 'crm.requisite.list' local/logs/inbound-b24.log
grep 'crm_method=crm.requisite.list' local/logs/inbound-b24.log

# Один trace целиком (подставить trace_id из ответа сайта)
grep 'a1b2c3d4-e5f6-7890-abcd-ef1234567890' local/logs/inbound-b24.log

# GET_CONTACT_ID перед регистрацией
grep 'GET_CONTACT_ID' local/logs/inbound-b24.log
grep 'contact_not_found' local/logs/inbound-b24.log
```

### Поля в `dispatch.done` (смотреть в JSON)

- `crm_method`, `result_int`, `reason_code`, `response_body_bytes`, `error_truncated`, `trace_id`.
- Для `CRM_METHOD` дополнительно: `has_rq_inn`, `has_rq_inn_filter`, `contact_id_param`, `company_id_param` (см. `actions/CRM_METHOD.md`).

---

## 5. Шаблон таблицы данных инцидента

Заполнить одну строку на кейс; приложить выгрузку лога по `trace_id`.

| Поле | Значение | Источник |
|------|----------|----------|
| **Дата/время** | `YYYY-MM-DD HH:MM TZ` | Обращение / лог `ts` |
| **Email** | | Клиент / `b_user.EMAIL` |
| **ИНН** | | Форма регистрации / CRM requisite |
| **b_user.ID** | | Сайт |
| **UF_B24_USER_ID** | | Сайт (`b_user` UF) |
| **Company CRM ID** | | CRM UI / `dispatch.done` `crm.company.add` → `result_int` |
| **Элемент ИБ 57 ID** | | Сайт (`OS_COMPANY_B24_ID` ↔ элемент) |
| **trace_id / X-Sync-Request-Id** | | Ответ inbound / заголовок ответа |
| **crm.contact.add** | `да / нет / error` | `dispatch.done` |
| **crm.contact.company.add** | `да / нет / error` | `dispatch.done` |
| **reason_code (company.add)** | e.g. `company_add_use_existing_for_contact` | `dispatch.done` |
| **reason_code (contact.add)** | e.g. `contact_add_failed` | `dispatch.done` |
| **Вердикт** | CRM / Сайт / Неоднозначно | см. §6 |

---

## 6. Дерево решений: проблема CRM vs проблема сайта

```
Регистрация: b_user есть, контакт CRM нет (ИНН уже в CRM)
│
├─ В inbound-b24.log НЕТ dispatch.done с crm_method=crm.contact.add
│  в окне времени регистрации (±15–30 мин)?
│  └─ ДА → **Проблема на сайте (оркестрация)**
│         Цепочка не дошла до CRM или запрос не дошёл до endpoint.
│         Типично: падение после company.add / до contact.add.
│
├─ Есть dispatch.done crm.contact.add с success: 0 (reason_code / error_truncated)?
│  └─ ДА → **Проблема CRM / inbound / валидация CRM**
│         Разбор reason_code: contact_add_failed, crm_module_unavailable,
│         response_encode_failed, duplicate control и т.д.
│
├─ crm.contact.add success: 1 (result_int > 0), но нет crm.contact.company.add?
│  └─ ДА → **Проблема сайта (не завершил цепочку)** или частичный rollback на сайте.
│         Контакт может существовать в CRM без COMPANY_ID — проверить UI.
│
├─ Оба contact.add и contact.company.add success: 1, контакт в CRM есть,
│  но на сайте «нет контакта» / нет UF_B24_USER_ID?
│  └─ ДА → **Проблема сайта (финализация регистрации)**, CRM выполнила свою часть.
│
└─ company.add success: 1 + company_add_use_existing_for_contact,
   contact.add отсутствует, b_user и UF_B24_USER_ID уже есть?
   └─ **Гипотеза сайта** (§7): регистрация продолжилась без CRM-контакта — см. §8.
```

### Критерии в одну строку

| Наблюдение в логе | Владелец |
|-------------------|----------|
| **Нет** `crm.contact.add` | **Сайт** |
| `crm.contact.add` + **`success: 0`** + `reason_code` | **CRM** |
| `crm.contact.add` **`success: 1`**, нет `crm.contact.company.add` | **Сайт** (цепочка) |
| Оба успешны, расхождение только в `b_user` / UF | **Сайт** |

---

## 7. Гипотезы на стороне сайта (для эскалации)

Коды/формулировки для команды сайта — **не** генерируются inbound CRM, но типичны для сценария «компания по ИНН найдена, контакт не создан»:

| Гипотеза | Смысл |
|----------|--------|
| **`site_company_element_b24_id_missing_before_contact_add`** | После `crm.company.add` (existing INN) сайт **не** нашёл/не создал элемент каталога с **`OS_COMPANY_B24_ID`**, не добавил пользователя в **`OS_COMPANY_USERS`**, и **не вызвал** `crm.contact.add`. |
| **`registration_resume_failed`** | Повтор/возобновление регистрации: `b_user` уже создан, но resume-flow **не** дошёл до CRM contact chain (таймаут, exception, некорректное состояние сессии). |

CRM может **подтвердить или опровергнуть** только факт вызова inbound-методов по логу (§4–§6).

---

## 8. Шаблон ответа команде сайта

**Тема:** Регистрация `{email}` / ИНН `{inn}` — разбор inbound CRM

**Данные кейса**

- Дата/время: `{datetime}`
- Email: `{email}`
- ИНН: `{inn}`
- b_user.ID: `{site_user_id}`
- trace_id / X-Sync-Request-Id: `{trace_id}`

**Что CRM подтверждает по `local/logs/inbound-b24.log`**

1. **`crm.company.add`:** `{да|нет}` — `result_int={company_id}`, `reason_code={code}`  
   _(ожидаемо при существующем ИНН: `company_add_use_existing_for_contact`)_
2. **`crm.contact.add`:** `{да|нет}` — `{result_int|reason_code|—}`
3. **`crm.contact.company.add`:** `{да|нет}` — `{result_int|reason_code|—}`
4. **`crm.requisite.list` / dedup INN:** `{кратко}`
5. Транспортные ошибки: `{нет | sync_forbidden | dedup_duplicate | 500 encode_failed}`

**Вывод CRM**

- `{В логе отсутствует crm.contact.add → прошу проверить оркестрацию регистрации на сайте после company.add (createCompanyElement / OS_COMPANY_B24_ID / OS_COMPANY_USERS).}`
- `{crm.contact.add вернул success: 0, reason_code: … → разбираем на стороне CRM.}`
- `{crm.contact.add и crm.contact.company.add успешны, contact_id=… → CRM-часть выполнена; прошу проверить финализацию UF_B24_USER_ID и отображение на сайте.}`

**Приложение:** фрагмент лога по `trace_id` (без PII в переписке с клиентом).

---

## 9. Ссылки на контракты и ADR

| Документ | Путь |
|----------|------|
| `CRM_METHOD` (методы, `dispatch.done`, dedup INN) | [`actions/CRM_METHOD.md`](actions/CRM_METHOD.md) |
| `GET_CONTACT_ID` (lookup только по email) | [`actions/GET_CONTACT_ID.md`](actions/GET_CONTACT_ID.md) |
| ADR R28 (discovery регистрации site → CRM) | [`../modules/yomerch.b24.siteconnector/lib/docs/adr-r28-site-user-register-create-crm-contact-company.md`](../modules/yomerch.b24.siteconnector/lib/docs/adr-r28-site-user-register-create-crm-contact-company.md) |
| Ответ CRM → сайт (P0/P2, обязательные шаги) | [`CRM_TEAM_RESPONSE_REGISTRATION_SYNC.md`](CRM_TEAM_RESPONSE_REGISTRATION_SYNC.md) |
| Карта UF (источник правды) | `local/modules/yomerch.b24.contract/lib/config/uf_mapping.php` |
| Логирование inbound (R6) | `local/modules/yomerch.b24.siteconnector/lib/docs/b24-inbound.md` |

---

## 10. UF mapping: `contact.site_user_id` vs `contact.delete_site_ref`

**Важно при разборе полей контакта и ответов `GET_CONTACT_ID`:**

| Ключ `UfMap` | UF CRM | Назначение |
|--------------|--------|------------|
| **`contact.site_user_id`** | **`UF_CRM_1776075126830`** | ID пользователя сайта (`b_user.ID`) — **передавать в `crm.contact.add` при регистрации** |
| **`contact.delete_site_ref`** | **`UF_CRM_3804624445748`** | Ref для DELETE/UPDATE на сайт — **не** путать с `site_user_id` |

В ответе `GET_CONTACT_ID` поле `UF_CRM_3804624445748` — это **`delete_site_ref`**, а не ID пользователя сайта.

При inbound **`crm.contact.add`** для привязки регистрации использовать **`UF_CRM_1776075126830`** (или ключ `contact.site_user_id` из `UfMap`).

---

## 11. Быстрые SQL/UI-проверки (опционально)

- Контакт по email: CRM → Контакты → фильтр по email.
- Компания по ИНН: CRM → Компании → реквизиты / поиск по `RQ_INN`.
- Связь контакт–компания: карточка контакта → «Компании» / поле `COMPANY_ID`.

Inbound **не** пишет в `b_user`; обратная связь site user ↔ CRM contact — через UF **`UF_CRM_1776075126830`** и исходящий контур (отдельно от регистрации P0).

---

## 12. Existing INN flow (code verification)

Проверено по коду `local/modules/yomerch.b24.inbound/lib/InboundEndpoint.php` и `site_requests_handler.php` (2026-07-07, stream C).

### 12.1. Цепочка CRM inbound (не-head company, существующий ИНН)

| # | Вызов сайта | Handler | Поведение в коде |
|---|-------------|---------|------------------|
| 0 | `GET_CONTACT_ID` (опционально) | `handleGetContactId` | Только `EMAIL`; `findContactIdsByEmail` — фильтр `=EMAIL`, `PHONE` игнорируется (стр. 76–88, 1377–1394) |
| 1 | `CRM_METHOD` → `crm.company.add` | `crmCompanyAdd` → `findCompanyIdsByRequisiteInn` → `crmCompanyResolveDuplicateInn` | При **ровно одной** компании с ИНН и **выключенном** `company.head_company_flag` — **`CCrmCompany::Add` не вызывается** (стр. 513–527, 1060–1073) |
| 2 | *(сайт)* `createCompanyElement` | — | **Вне inbound.** Merge к существующему элементу ИБ **57** (или создание с `OS_COMPANY_B24_ID` = `result` шага 1). Inbound не логирует этот шаг |
| 3 | `CRM_METHOD` → `crm.contact.add` | `crmContactAdd` | `CCrmContact::Add` с `DISABLE_DUPLICATE_CONTROL` (стр. 702–730) |
| 4 | `CRM_METHOD` → `crm.contact.company.add` | `crmContactCompanyAdd` | `CCrmContact::Update` с `fields.COMPANY_ID` = ID из шага 1 (стр. 856–894) |

**Dedup INN:** ИНН извлекается из `PARAMS.fields` (`RQ_INN` или вложенные блоки `REQUISITE` / …) — `extractInnFromCompanyAddFields` (стр. 951–968). Поиск — `RequisiteTable` по `RQ_INN` + `ENTITY_TYPE_ID = Company` (стр. 1028–1050).

**Head company (не этот runbook):** если `company.head_company_flag` truthy — создаётся **дочерняя** компания (`company_add_child_under_head_inn`), не `company_add_use_existing_for_contact` (стр. 1076–1119).

### 12.2. Code-verified response shapes

#### `crm.company.add` — dedup, не-head (`company_add_use_existing_for_contact`)

Источник: `crmCompanyResolveDuplicateInn`, строки 1064–1073.

```json
{
  "success": 1,
  "result": 12345,
  "reason_code": "company_add_use_existing_for_contact",
  "data": {
    "rq_inn": "7707083893",
    "company_id": 12345,
    "attach_contact_only": true
  }
}
```

| Поле | Тип | Проверка |
|------|-----|----------|
| `success` | `1` | int |
| `result` | int | CRM ID **существующей** компании (= `data.company_id`) |
| `reason_code` | string | **`company_add_use_existing_for_contact`** |
| `data.attach_contact_only` | bool | **`true`** — сигнал сайту: новая компания не создана, нужны contact chain + merge элемента ИБ |

Сайт **обязан** использовать `(int)result` как `fields.COMPANY_ID` в `crm.contact.company.add` (не ожидать новый CRM ID).

#### `crm.company.add` — новая компания (ИНН не найден)

```json
{ "success": 1, "result": 67890 }
```

Без `reason_code` / `data` (стр. 557).

#### `crm.contact.add` — успех

```json
{ "success": 1, "result": 11111 }
```

`result` = int contact ID (стр. 730).

#### `crm.contact.company.add` — успех

```json
{ "success": 1, "result": true }
```

`result` = boolean `true`, не ID (стр. 888–889).

#### `GET_CONTACT_ID` — lookup только по email

Источник: `handleGetContactId` (стр. 67–104), контракт: [`actions/GET_CONTACT_ID.md`](actions/GET_CONTACT_ID.md).

- Поиск: **только** `EMAIL` (`findContactIdsByEmail`, `=EMAIL`).
- `PHONE` в теле запроса **не участвует** в lookup (комментарий стр. 87).
- Новый email: `success: 0`, `reason_code`: **`contact_not_found`** — норма перед регистрацией.
- Ровно один контакт: `success: 1`, `data.ID` = int.

### 12.3. Expected site call sequence (EXISTING INN, non-head)

1. **`crm.company.add`** с `fields.RQ_INN` (и прочими UF регистрации) → dedup path, **не** create.
2. **`createCompanyElement`** на сайте → merge к **существующему** элементу каталога (ИБ **57** на prod) или элемент с **`OS_COMPANY_B24_ID`** = `result` шага 1; пользователь в **`OS_COMPANY_USERS`**.
3. **`crm.contact.add`** — в `fields` передать **`UF_CRM_1776075126830`** (`contact.site_user_id`) = `b_user.ID`.
4. **`crm.contact.company.add`** — `id` = contact ID из шага 3, `fields.COMPANY_ID` = **`result` шага 1** (existing company ID).

При `attach_contact_only: true` сайт **не должен** трактовать ответ как «ошибку» или прерывать цепочку до шагов 3–4.

### 12.4. Expected inbound log sequence (`dispatch.done`)

Путь: `local/logs/inbound-b24.log`. Событие: **`site_requests_handler.dispatch.done`** (`site_requests_handler.php`, стр. 785–852).

Ожидаемый порядок для одного `trace_id` (существующий ИНН, успешная регистрация):

| Порядок | `crm_method` | Ключевые поля JSON в `dispatch.done` |
|---------|--------------|----------------------------------------|
| 1 | `crm.company.add` | `success: 1`, `result_int` = existing company ID, `reason_code`: **`company_add_use_existing_for_contact`**, `has_rq_inn`: `true` |
| 2 | *(нет inbound)* | Шаг `createCompanyElement` на сайте — **не** появляется в `inbound-b24.log` |
| 3 | `crm.contact.add` | `success: 1`, `result_int` = new contact ID, `reason_code`: `""` |
| 4 | `crm.contact.company.add` | `success: 1`, `contact_id_param`, `company_id_param` = IDs шагов 3 и 1; `result_type`: `"boolean"` (нет `result_int`) |

Опционально **до** шага 1: `action=GET_CONTACT_ID`, `reason_code=contact_not_found` (отдельный `dispatch.done` без `crm_method`).

Опционально **до** `crm.company.add`: `crm.requisite.list` с `has_rq_inn_filter: true` — если сайт явно проверяет ИНН до add.

**Диагностика обрыва:** есть строка 1, нет строк 3 → типично **сайт** (§6–§7): не выполнен merge элемента ИБ / не вызван `crm.contact.add`.

### 12.5. UF mapping (регистрация контакта)

Источник: `local/modules/yomerch.b24.contract/lib/config/uf_mapping.php`.

| Ключ `UfMap` | UF CRM | Когда |
|--------------|--------|-------|
| **`contact.site_user_id`** | **`UF_CRM_1776075126830`** | Передавать в `crm.contact.add` → `b_user.ID` |
| `contact.delete_site_ref` | `UF_CRM_3804624445748` | **Не** подставлять вместо `site_user_id`; в `GET_CONTACT_ID` — опционально в ответе |

См. также §10 этого runbook.
