# ADR R13: исходящий `UPDATE_COMPANY`, `ACTIVE` и UF маркетингового агента

## Статус

Принято (исправление после уточнения).

## Контекст

Поле CRM **`UF_CRM_1675675211485`** — это и есть пользовательское поле, описываемое в карте как **`company.is_marketing_agent`** (активность / признак маркетингового агента для сайта). В `uf_mapping.php` ошибочно указывался другой код UF (`UF_CRM_1774915252680`), из-за чего **`ACTIVE`** и **`OS_IS_MARKETING_AGENT`** в исходящем payload читали «не то» поле.

Отдельный ключ `company.active_on_site` не нужен: один UF задаёт и маркер для **`OS_IS_MARKETING_AGENT`**, и **`ACTIVE`** (`Y`/`N`).

## Решение

1. **`uf_mapping.php`**: `company.is_marketing_agent` → **`UF_CRM_1675675211485`**.
2. **`CompanySync::onAfterCompanyUpdate`**: скаляр для маркетингового UF берётся через **`extractCompanyUfScalarForOutbound`** (приоритет `$arFields`, развёртка `VALUE`), одно и то же значение используется для **`OS_IS_MARKETING_AGENT`**, **`ACTIVE`** и пропагации на контакты (`company.contact_marketing_agent`).

## Риски

- Портал, где реально использовался старый **`UF_CRM_1774915252680`**, потеряет связь с ним в коде — при необходимости миграция данных в CRM или отдельный legacy-ключ в карте.
- Семантика **`isTruthy`**: только `Y`, `true`, `1`, `'1'`. Для enum-списков может понадобиться маппинг (см. задачи R13).

## Задачи

`tasks/r13-company-active-on-site-tasks.md`
