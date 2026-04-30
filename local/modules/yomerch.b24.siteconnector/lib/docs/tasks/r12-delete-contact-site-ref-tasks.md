# R12 — DELETE_CONTACT: ID = UF delete_site_ref

| ID | Описание | Статус |
|----|----------|--------|
| R12-01 | `uf_mapping`: `contact.delete_site_ref` | done |
| R12-02 | `ContactSync::onBeforeContactDelete` — `ID` из UF, `B24_ID` = CRM | done |
| R12-03 | `LocalApplicationHandler` — приоритет `B24_ID`, резолв по UF | done |
| R12-04 | `UPDATE_CONTACT`: `ID` и fallback `OS_COMPANY_B24_ID` из UF | done |
| R12-05 | `DELETE_CONTACT`: `OS_COMPANY_B24_ID` = то же, что `ID` (UF) | done |
| R12-06 | Outbound `UPDATE_COMPANY`: `site_element_id` из legacy UF, если канонический пуст | done |

## Next steps for Team Lead

- На стенде: контакт с заполненным `UF_CRM_3804624445748` → удалить в CRM → проверить payload на сайте (`ID` и `OS_COMPANY_B24_ID` одинаковы, `B24_ID` = CRM).
- UPDATE_CONTACT: контакт без привязанной компании → в payload должны быть непустые `ID` и `OS_COMPANY_B24_ID` (значение UF).
- Задокументировать на стороне сайта ожидание полей (если отдельный контракт).
