# ADR R24: UF компании «маркетинговый агент» → UF контакта у всех привязанных

## Статус

Принято.

## Контекст

У компании в CRM UF **`UF_CRM_1675675211485`** (`company.is_marketing_agent`). У привязанных контактов при сохранении компании в CRM:

- **`UF_CRM_1775034008956`** (`company.contact_marketing_agent`) — **прямая копия** скаляра/enum ID компании (например **2076** → **2076**), через `normalizeCompanyUfMirrorForContactUpdate($isMarketingAgentRaw)`; без подстановки enum «да» списка контакта.
- **`UF_CRM_1698752707853`** (`contact.inherits_company_is_marketing_agent`) — **Y/N** по `isMarketingAgentTruthy` (для `ACTIVE` на сайте в `ContactSync`); сырой enum компании сюда не пишется.

Речь о **событии внутри CRM** (сохранение компании), а не о входящем `UPDATE_COMPANY` с сайта.

## Решение

- В **`uf_mapping.php`**: **`company.contact_marketing_agent`** → **`UF_CRM_1775034008956`**; **`contact.inherits_company_is_marketing_agent`** → **`UF_CRM_1698752707853`**.
- **`CompanySync::onAfterCompanyUpdate`**: в цикле `CCrmContact::Update` для привязанных контактов — `company.contact_marketing_agent` = зеркало UF компании; `contact.inherits_company_is_marketing_agent` = `Y`/`N`.
- Outbound на сайт: **`isMarketingAgentTruthy`** → `OS_IS_MARKETING_AGENT` / `ACTIVE`; **`UF_ADVERTISING_AGENT`** в `ContactSync` (в т.ч. fallback при `ACTIVE=Y`) — без изменения в рамках R24.

Входящий **`UPDATE_COMPANY`** (`InboundEndpoint`) **не** дублирует эту логику: он обновляет поля компании по контракту сайта; наследование UF на контакты — зона ответственности CRM-события выше.

## Риски

- Расхождение типов enum/list между UF компании и UF контакта — настройка полей в CRM должна допускать те же значения.
- Контакты только по `COMPANY_ID` без строки в `ContactCompanyTable` могут не попасть в основной список — срабатывает fallback.

## Задачи

`tasks/r24-company-marketing-agent-to-contact-uf-task.md`
