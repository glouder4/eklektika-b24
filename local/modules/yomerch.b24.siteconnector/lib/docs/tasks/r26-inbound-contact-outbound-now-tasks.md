# R26 — задачи: inbound контакт → сайт

## Выполнено

- [x] R26-01 — `InboundEndpoint`: после успешного `crm.contact.add` / `crm.contact.update` — `ContactSync::sendContactToSiteNow` *(снято 2026-05: perf регистрации, см. ADR R26 «Дополнение»)*.
- [x] R26-02 — `ContactSync::extractContactIdFromArgs`: `Main\Event` + вложенный поиск ID.

## Осталось

- [ ] R26-03 — Смоук: `CRM_METHOD` `crm.contact.update` с изменением ФИО → на сайте обновился соответствующий контакт (после **ручного** save в CRM или отдельного sync; не через немедленный inbound outbound).
- [ ] R26-04 — P1: очередь/async для `UPDATE_CONTACT` после inbound create (если сайту нужен push сразу после регистрации).
