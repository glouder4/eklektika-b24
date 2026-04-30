# R14 — `UPDATE_COMPANY`: город UF и сайт из мультполя WEB

## Выполнено

- [x] R14-01 — `company.city` → `UF_CRM_1618551330657` в `uf_mapping.php`.
- [x] R14-02 — `CompanySyncNormalizeService`: город с приоритетом `$arFields`; сайт из `MULTIFIELDS['WEB']` с fallback на `company.web`.
- [x] R14-03 — `UPDATE_COMPANY.md` + ADR R14.

## Осталось

- [ ] R14-04 — При необходимости: поддержка нескольких URL в `WEB` (агрегация или выбор рабочего).

## Проверка

- В CRM заполнить UF города и мультполе WEB, сохранить компанию — в trace inbound сайта **`OS_COMPANY_CITY`** / **`OS_COMPANY_WEB_SITE`** и зеркальные UF совпадают с ожиданием.
