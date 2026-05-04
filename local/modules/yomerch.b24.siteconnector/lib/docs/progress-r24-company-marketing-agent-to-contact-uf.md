# Прогресс R24: UF маркетингового агента компании → контакты

## Сделано

- [x] **`CompanySync::onAfterCompanyUpdate`** — зеркалирование UF на привязанные контакты (CRM-событие).
- [x] **`uf_mapping.php`** — ключ `contact.inherits_company_is_marketing_agent`.
- [x] Уточнены **`UPDATE_COMPANY.md`**, **ADR R24**: inbound не дублирует эту политику.

## Осталось

- [ ] Проверка на стенде: смена UF у компании → все привязанные контакты получили `UF_CRM_1698752707853`.
