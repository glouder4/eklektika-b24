# R13 — `UPDATE_COMPANY` / `ACTIVE` и UF маркетингового агента

## Выполнено

- [x] R13-01 — В `uf_mapping.php` для **`company.is_marketing_agent`** зафиксирован корректный UF **`UF_CRM_1675675211485`** (убран ошибочный `UF_CRM_1774915252680`).
- [x] R13-02 — Удалён лишний ключ **`company.active_on_site`**; **`ACTIVE`** и **`OS_IS_MARKETING_AGENT`** используют одно поле через **`extractCompanyUfScalarForOutbound`**.
- [x] R13-03 — ADR `adr-r13-company-active-on-site-uf.md` обновлён под уточнение.

## Осталось (при необходимости)

- [ ] R13-04 — Если **`UF_CRM_1675675211485`** — enum: задокументировать значения и при необходимости заменить **`isTruthy`** на маппинг ID варианта → `Y`/`N` и флаг для `OS_IS_MARKETING_AGENT`.

## Проверка

- Сохранить компанию с изменением UF маркетингового агента и убедиться по trace, что **`ACTIVE`** и **`OS_IS_MARKETING_AGENT`** согласованы с полем в CRM.
