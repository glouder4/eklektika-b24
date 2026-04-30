# R17 — снятие UF головной компании

## Выполнено

- [x] R17-01 — `CompanySync::clearHoldingChildrenWhenPortalHeadFlagRemoved`: ИБ 34, деактивация элемента, очистка UF у дочерних.
- [x] R17-02 — ADR R17.

## Осталось

- [ ] R17-03 — Ручной тест: снять `UF_CRM_1758028888` у головной → проверить ИБ 34, дочерние без UF холдинга.

## Проверка

- Трейс `CompanySync::portal_head_flag_cleanup` в outbound-логе.
