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
| `crm.company.add` | Создание компании | `fields` — массив полей для `CCrmCompany::Add`. На время `Add` подавляется полный **`CompanySync::onAfterCompanyAdd`** (`CompanySync::suspendOutbound`) — inbound не ждёт исходящий `UPDATE_COMPANY` на сайт. **Если в `fields` есть `RQ_INN` и в CRM уже есть компания с этим ИНН:** при **ровно одной** совпавшей компании и включённом UF **`company.head_company_flag`** (`UF_CRM_1758028888`) создаётся **новая дочерняя** компания: в UF **`company.holding`** (`UF_CRM_1758028816`) пишется CRM ID головной компании; затем UF **`company.holding_group_members`** (`UF_CRM_1776426878`) обновляется **одинаковым списком** (головная + все дочерние) на всех участниках холдинга. Ответ: `reason_code`: `company_add_child_under_head_inn`, `data`: `head_company_id`, `child_company_id`, `holding_member_company_ids`. Если головной признак **выключен** — новая компания **не** создаётся: `success`: `1`, `result` = CRM ID найденной компании, `reason_code`: `company_add_use_existing_for_contact`, `data.attach_contact_only`: `true` (дальше сайт привязывает контакт через `crm.contact.*`). Несколько компаний с одним ИНН — `company_add_ambiguous_inn`. |
| `crm.company.get` | Чтение компании | `id` — числовой ID |
| `crm.company.update` | Обновление компании | `id`, `fields`. Перед `CCrmCompany::Update` вызывается **`CompanySync::markInboundCompanyUpdate`** (как в `UPDATE_COMPANY`) — исходящий **`UPDATE_COMPANY`** на сайт для этого сохранения не уходит. Опционально **`skip_outbound_sync`** (truthy: `Y`, `true`, `1`, `'1'`) — явный флаг намерения у вызывающего; отдельного отключения подавления нет, inbound-обновление компании всегда помечается. |
| `crm.requisite.add` | Создание реквизита | `fields` |
| `crm.requisite.update` | Обновление реквизита | `id`, `fields` |
| `crm.contact.add` | Создание контакта | `fields`. Во время `CCrmContact::Add` исходящие CRM-события подавляются (`ContactSync::suspendOutbound`). **Немедленный** `UPDATE_CONTACT` на сайт **не выполняется** (ответ inbound не ждёт outbound; см. ADR R26). |
| `crm.contact.update` | Обновление контакта | `id`, `fields` (мультиполя нормализуются в `n0`, `n1`, …). События `onAfter` подавлены на время `Update`; **немедленный** outbound на сайт **не выполняется** (ADR R26). |
| `crm.contact.company.add` | Привязка контакта к компании | `id` — контакт, `fields.COMPANY_ID` — компания. На время `CCrmContact::Update` — **`ContactSync::suspendOutbound`** (нет синхронного **`UPDATE_CONTACT`** ~2.5 s при регистрации). |

Иные значения `METHOD` возвращают ошибку с текстом `Unsupported CRM METHOD: …` и `reason_code`: `unsupported_crm_method`.

## Ответы

Унифицированно:

- Успех: `success`: `1`, полезная нагрузка чаще в `result` (как возвращает соответствующий helper); для `crm.company.add` при существующей компании по ИНН дополнительно могут быть `reason_code` и `data` (см. таблицу выше).
- Ошибка Bitrix или исключение: `success`: `0`, текст в `error`.

## Контракт данных для сайта

Для каждого вызова **`PARAMS.fields`** должны соответствовать требованиям CRM и бизнес-правилам портала. В частности, для сценариев синхронизации с сайтом в полях компании нужно явно передавать пользовательские поля связи с элементом инфоблока:

| Роль | Ключ `UfMap` | UF CRM (текущий портал) |
|------|----------------|-------------------------|
| Канонический ID элемента ИБ на сайте | `company.site_element_id` | **`UF_CRM_1774915439581`** |
| Legacy-зеркало (поиск, исходящий fallback) | `company.site_element_id_legacy_alias` | **`UF_CRM_3804624439373`** |

Сайт при создании/обновлении компании через `crm.company.add` / `crm.company.update` должен писать **оба** UF одним и тем же положительным ID элемента **или** использовать ключи из **`UfMap`** / `uf_mapping.php` (`local/modules/yomerch.b24.contract/lib/config/uf_mapping.php`), чтобы `UPDATE_COMPANY`, inbound-поиск и исходящий `CompanySync` не расходились. Значение **`0`** или пустое — недопустимый ID элемента.
