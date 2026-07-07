# R29 — задачи: наследование полей компании контактом (registration + existing INN)

## Выполнено

- [x] R29-01 — `ContactInheritFromCompanyService` в `yomerch.b24.contract` (`buildInheritFieldsFromCompany`, `applyToContact` с copy-only-if-empty).
- [x] R29-02 — `InboundEndpoint::crmContactCompanyAdd` (v1): флаг `inherit_from_company`, наследование, `sendContactToSiteNow` — **заменяется v2** (R29-10).
- [x] R29-03 — Trace `dispatch.done`: `inherit_from_company`, `contact_inherited`, `copied_fields_count`.
- [x] R29-04 — Контракт: `CRM_METHOD.md`, `CRM_TEAM_RESPONSE_REGISTRATION_SYNC.md`.
- [x] R29-05 — ADR R29.

## Осталось (сайт / стенд)

- [ ] R29-06 — Сайт: v2 sequence — `crm.contact.company.add` только привязка; `inherit_from_company: true` в **`crm.contact.update`** только при `company_add_use_existing_for_contact`.
- [ ] R29-07 — Смоук: регистрация в компанию-рекламного агента с 2 менеджерами → контакт в CRM и на сайте с теми же `UF_MANAGER`, `UF_MANAGER2`, `UF_ADVERTISING_AGENT`, `ACTIVE`.
- [ ] R29-08 — Смоук: new company flow без флага — без наследования и без лишнего outbound.
- [ ] R29-09 — Смоук: повторная регистрация сотрудника в ту же компанию — тот же результат.

## v2 доработка (перенос inherit в crm.contact.update)

- [x] R29-10 — `InboundEndpoint`: убрать наследование и outbound из `crmContactCompanyAdd`; добавить в `crmContactUpdate` — флаг `inherit_from_company`, `ContactInheritFromCompanyService`, `sendRegistrationInheritedContactToSiteNow`, ответ `contact_inherited_from_company` + `data.update_contact_outbound`.
- [x] R29-11 — Trace `site_requests_handler`: перенести inherit-поля на `crm.contact.update`; событие `registration.update_contact_outbound` в inbound-b24.log.
- [ ] R29-12 — Сайт + смоук: обновить оркестрацию registration flow на canonical sequence v2 (add → company.add → update+inherit); проверить payload `UPDATE_CONTACT` (`B24_ID`, `UF_CRM_3804624445748`, `ASSIGNED_BY_ID`, `UF_CRM_1757682312`, `UF_CRM_1698752707853`, `ACTIVE`).

## Критерии приёмки (чеклист)

- [ ] New company flow без флага — без наследования
- [ ] copy-only-if-empty не затирает ручные значения
- [ ] Outbound `UPDATE_CONTACT` содержит `B24_ID`, `UF_CRM_3804624445748`, `ASSIGNED_BY_ID`, `UF_CRM_1757682312`, `UF_CRM_1698752707853`, `ACTIVE` (после `crm.contact.update` + `inherit_from_company`)
- [ ] Нет PII в новых trace-полях
- [ ] Согласовано с ADR R13/R24/R26/R27
- [ ] `CompanySync::onAfterCompanyUpdate` не изменён
