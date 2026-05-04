# R27 — задачи: UF контакта в UPDATE_CONTACT

## Выполнено

- [x] R27-01 — `uf_mapping`: `contact.site_sync_value` → `UF_CRM_1757682312`.
- [x] R27-02 — `ContactSync::sendContactToSite`: передача значения в payload.
- [x] R27-04 — `ACTIVE` из `UF_CRM_1698752707853`, ключ **`UF_ADVERTISING_AGENT`** из UF рекламного агента контакта.

## Осталось

- [ ] R27-03 — Смоук: сменить ответственного и UF `UF_CRM_1757682312` у контакта в CRM → в `UPDATE_CONTACT` на сайте **`ASSIGNED_MANAGER`** = CRM `ASSIGNED_BY_ID`, **`SECOND_MANAGER`** = значение UF.
