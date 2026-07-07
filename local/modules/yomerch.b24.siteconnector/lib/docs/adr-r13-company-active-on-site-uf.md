# ADR R13: исходящий `UPDATE_COMPANY`, `ACTIVE` и UF маркетингового агента

## Статус

Принято (исправление после уточнения).

## Контекст

Поле CRM **`UF_CRM_1675675211485`** — это и есть пользовательское поле, описываемое в карте как **`company.is_marketing_agent`** (активность / признак маркетингового агента для сайта). В `uf_mapping.php` ошибочно указывался другой код UF (`UF_CRM_1774915252680`), из-за чего **`ACTIVE`** и **`OS_IS_MARKETING_AGENT`** в исходящем payload читали «не то» поле.

Отдельный ключ `company.active_on_site` не нужен: один UF задаёт и маркер для **`OS_IS_MARKETING_AGENT`**, и **`ACTIVE`** (`Y`/`N`).

## Решение

1. **`uf_mapping.php`**: `company.is_marketing_agent` → **`UF_CRM_1675675211485`**.
2. **`CompanySync::onAfterCompanyUpdate`**: скаляр через **`extractCompanyUfScalarForOutbound`**; для сайта — **`isMarketingAgentTruthy`** → **`OS_IS_MARKETING_AGENT`** / **`ACTIVE`**. На контакт **`company.contact_marketing_agent`** (`UF_CRM_1775034008956`) пишется **тот же скаляр/enum ID**, что на компании (`normalizeCompanyUfMirrorForContactUpdate`), без подстановки enum «да» списка контакта. **`contact.inherits_company_is_marketing_agent`** — Y/N (см. ADR R24).

3. **Perf (2026-05):** если в `$arFields` изменён **только** UF «Рекламный агент» — fast-path (минимальный outbound). Отладка фаз: **`?os_usersync_debug=1`** в URL карточки компании → `pre()` на экран и **`die()`** в конце `onAfterCompanyUpdate` (без `b24-sync-perf.log`). При debug outbound не откладывается в shutdown.

## Риски

- Портал, где реально использовался старый **`UF_CRM_1774915252680`**, потеряет связь с ним в коде — при необходимости миграция данных в CRM или отдельный legacy-ключ в карте.
- Семантика **`isTruthy`**: только `Y`, `true`, `1`, `'1'`. Для enum-списков может понадобиться маппинг (см. задачи R13).

## Задачи

`tasks/r13-company-active-on-site-tasks.md`
