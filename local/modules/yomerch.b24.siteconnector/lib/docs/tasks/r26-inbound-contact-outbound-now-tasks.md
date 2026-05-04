# R26 — задачи: inbound контакт → сайт

## Выполнено

- [x] R26-01 — `InboundEndpoint`: после успешного `crm.contact.add` / `crm.contact.update` — `ContactSync::sendContactToSiteNow`.
- [x] R26-02 — `ContactSync::extractContactIdFromArgs`: `Main\Event` + вложенный поиск ID.

## Осталось

- [ ] R26-03 — Смоук: `CRM_METHOD` `crm.contact.update` с изменением ФИО → на сайте обновился соответствующий контакт (по `contact.delete_site_ref` / fallback `OS_COMPANY_B24_ID`).
