# ADR R28: регистрация пользователя сайта → создание контакта CRM и привязка к компании

## Статус

Предложено (discovery).

## Контекст

Нужно найти в кодовой базе Bitrix/CRM обработчик, который при **регистрации нового пользователя на сайте**:

- создаёт (или находит) **контакт** в CRM;
- создаёт (или находит) **компанию** в CRM (включая кейсы dedup по ИНН / холдинг);
- **привязывает контакт к компании** (в CRM это обычно `COMPANY_ID` в контакте и/или запись в `ContactCompanyTable`).

В текущем репозитории обнаружен развитый интеграционный контур `site ↔ b24` в `local/modules/*`:

- **Inbound (site → CRM)**: `local/modules/yomerch.b24.inbound/lib/InboundEndpoint.php` + `.../lib/site_requests_handler.php` (dispatcher по `ACTION`, поддержка `CRM_METHOD`).
- **Outbound (CRM → site)**: `local/modules/yomerch.b24.outbound/*` (в т.ч. `ContactSync`, `CompanySync`), регистрация обработчиков событий — `local/modules/yomerch.b24.siteconnector/lib/register_bitrix_handlers.php`.

Важно: прямых обработчиков `OnAfterUserRegister`/`CUser::Register` в `local/modules` на данный момент не найдено, поэтому вероятен сценарий: **регистрация происходит на сайте**, а сайт вызывает **InboundEndpoint** с последовательностью `CRM_METHOD` (например `crm.company.add` → `crm.contact.add` → `crm.contact.company.add`), а не событие `main` в этом репозитории.

## Что ищем (гипотезы реализации)

### Вариант A — событие регистрации в B24/Bitrix (`main`)

Регистрация пользователя инициируется в этом репозитории, и обработчик подписан на одно из событий:

- `main`: `OnAfterUserRegister`, `OnBeforeUserRegister`, `OnAfterUserAdd`, `OnBeforeUserAdd`, `OnAfterUserUpdate`
- либо прямой вызов `CUser::Register(...)` / `$user->Register(...)`

Дальше обработчик может:

- создать CRM контакт (`CCrmContact::Add` / `\Bitrix\Crm\ContactTable::add`)
- создать CRM компанию (`CCrmCompany::Add` / `\Bitrix\Crm\CompanyTable::add`)
- связать (`crm.contact.company.add` или `CCrmContact::Update(['COMPANY_ID'=>...])`)

### Вариант B — сайт регистрирует, а CRM создаёт сущности через inbound `CRM_METHOD` (наиболее вероятно по текущему коду)

Сайт после регистрации (или в процессе) вызывает endpoint `yomerch.b24.inbound`:

- `ACTION=CRM_METHOD`, `METHOD=crm.company.add|crm.contact.add|crm.contact.company.add`
- либо `ACTION=UPDATE_COMPANY` (если контакт/компания резолвятся через UF “site element id” и затем обновляются)

Ключевые места:

- `InboundEndpoint::handleCrmMethod` (allow-list методов)
- `InboundEndpoint::crmCompanyAdd` (dedup по ИНН, см. ADR R15)
- `InboundEndpoint::crmContactAdd`
- `InboundEndpoint::crmContactCompanyAdd` (привязка контакта к компании через `CCrmContact::Update(['COMPANY_ID'=>...])`)

### Вариант C — legacy/альтернативный handler в `LocalApplicationHandler`

`local/modules/yomerch.b24.base/lib/LocalApplicationHandler.php` содержит каркас для `ACTION` вроде `DELETE_CONTACT`, `UPDATE_CONTACT`, но `handleAddContact()` сейчас пустой.
Если на сайте есть legacy-контракт `ADD_CONTACT`, он может быть “точкой входа” для регистрации, но реализация в данном репозитории отсутствует/не завершена.

## Решение (на время discovery)

1. **Считать Tier-0 source-of-truth** для “создание контакта + привязка к компании” — inbound `CRM_METHOD` в `InboundEndpoint.php` (уже реализованы: `crm.contact.add`, `crm.company.add`, `crm.contact.company.add`).
2. **Perf регистрации (2026-05):** при inbound `crm.company.add` — `CompanySync::suspendOutbound` на время `Add`; при `crm.contact.add`/`update` — без синхронного `sendContactToSiteNow` (ADR R26). Inbound не блокируется outbound CURL к сайту.
3. **Регистрация юрлица/агента (2026-05, дополнение):** помимо CRM `crm.company.add` → `crm.contact.add` → `crm.contact.company.add`, на **сайте** обязателен `createCompanyElement` (или аналог) с **`OS_COMPANY_B24_ID`** = CRM ID компании. Inbound CRM **не** знает об успехе этого шага; при `createCompanyElement` → false сайт блокирует регистрацию даже если `crm.company.add` успешен. Инвариант: пара `(company_id, site_element_id)` + `OS_COMPANY_B24_ID` на элементе. Допустимы варианты A (элемент до add) и B (add → элемент → update UF). Детали — `CRM_TEAM_RESPONSE_REGISTRATION_SYNC.md`, `actions/CRM_METHOD.md`.
4. **Параллельно** проверить отсутствие/наличие подписок на события регистрации `main` в `local/php_interface/init.php` и всех модулях `local/modules/**/include.php|init.php|bootstrap.php`.
5. Если бизнес-требование строго “после регистрации пользователя сайта в B24” (а не “после регистрации на сайте”), то:
   - либо добавлять подписку на `OnAfterUserRegister`/`OnAfterUserAdd` в `yomerch.b24.siteconnector` (и явно документировать побочные эффекты),
   - либо оставлять регистрационный flow на сайте и просто формализовать контракт inbound последовательности методов.

## Строки/сигнатуры для поиска (быстрый чеклист)

### Регистрация пользователя / события `main`

- `OnAfterUserRegister`
- `OnBeforeUserRegister`
- `OnAfterUserAdd`
- `OnBeforeUserAdd`
- `CUser::Register`
- `->Register(`
- `\Bitrix\Main\EventManager::getInstance()->addEventHandler(` / `addEventHandlerCompatible(` / `AddEventHandler(`

### Создание/поиск CRM контакта/компании/привязка

- `crm.contact.add`
- `crm.company.add`
- `crm.contact.company.add`
- `CCrmContact::Add` / `CCrmContact->Add`
- `CCrmCompany::Add` / `CCrmCompany->Add`
- `\Bitrix\Crm\ContactTable::add`
- `\Bitrix\Crm\CompanyTable::add`
- `COMPANY_ID` (в контексте контакта)
- `ContactCompanyTable`
- `RQ_INN`, `RequisiteTable`, `EntityRequisite` (dedup компании)

## Последствия

- Вероятнее всего, “обработчик регистрации” находится **не** в событиях `main`, а в **inbound-контуре** (создание CRM-сущностей инициируется сайтом).
- Если выяснится, что регистрация должна происходить на стороне B24 (по событию `main`), потребуется новый ADR на внедрение подписки и обсуждение рисков (дубликаты, гонки, правовые основания данных, ретраи).

## Риски

- Несовпадение терминов: “регистрация пользователя” (b_user) vs “создание контакта” (crm_contact) — это разные сущности; связка может быть через UF контакта/пользователя или через отдельный реестр.
- Dedup контакт/компания может расходиться между сайтом и CRM (например, по email/телефону/ИНН).
- Привязка контакта к компании через `COMPANY_ID` может быть недостаточной в некоторых версиях CRM (нужна `ContactCompanyTable`), но в текущем inbound-коде используется `CCrmContact::Update`.

## Задачи

`tasks/r28-site-user-register-create-crm-contact-company-tasks.md`

