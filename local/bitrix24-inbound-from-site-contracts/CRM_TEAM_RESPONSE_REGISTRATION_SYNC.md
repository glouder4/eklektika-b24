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

---

## P2 — внедрено в этом релизе

| Метод | Изменение |
|-------|-----------|
| `crm.company.update` | Перед `CCrmCompany::Update` — **`CompanySync::markInboundCompanyUpdate($id)`** (как в `UPDATE_COMPANY`). Исходящий `UPDATE_COMPANY` для этого update не уходит. |
| `crm.contact.company.add` | `CCrmContact::Update` обёрнут в **`ContactSync::suspendOutbound(true/false)`** — нет ~2.5 s `UPDATE_CONTACT` при привязке контакта к компании в цепочке регистрации. |

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

## Canonical sequence регистрации (напоминание)

1. `crm.company.add` — `fields` с обоими UF `site_element_id` (+ реквизит/ИНН по бизнес-правилам R15).
2. `crm.contact.add` — `fields` контакта (в т.ч. `contact.site_user_id` при наличии).
3. `crm.contact.company.add` — `id` контакта, `fields.COMPANY_ID` компании.

При обновлении компании после создания — `crm.company.update` с тем же правилом UF.

---

## Контакты по коду

- `InboundEndpoint.php` — `crmCompanyUpdate`, `crmContactCompanyAdd`
- `CompanySync::markInboundCompanyUpdate`
- `ContactSync::suspendOutbound`
- ADR: R26 (contact outbound), R28 (registration discovery)
