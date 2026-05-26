# R28 — регистрация пользователя сайта → CRM контакт + компания + привязка

## Цель

Найти фактический entrypoint и контракт, по которому после регистрации на сайте создаются:

- CRM контакт
- CRM компания (с учётом dedup по ИНН/холдингу)
- связь контакт ↔ компания

и зафиксировать это в документации/контрактах так, чтобы Team Lead мог раздать параллельный поиск и быстро собрать “source of truth”.

## Выполнено

- [x] R28-00 — Выявлены кандидаты Tier-0 в коде: `InboundEndpoint::handleCrmMethod` + `crm.company.add`/`crm.contact.add`/`crm.contact.company.add`; регистрация CRM outbound обработчиков — `register_bitrix_handlers.php`.
- [x] R28-01 — ADR R28 (discovery) создан.

## Осталось

- [ ] R28-02 — Инвентаризация inbound потока “создание сущностей”: проверить `InboundEndpoint::crmCompanyAdd`, `InboundEndpoint::crmContactAdd`, `InboundEndpoint::crmContactCompanyAdd`, и где именно они вызываются из `site_requests_handler.php` (формат payload/trace).
- [ ] R28-03 — Поиск событий регистрации `main` по всему `local/modules` и `local/php_interface/init.php`: подтвердить отсутствие/наличие подписок `OnAfterUserRegister|OnAfterUserAdd|CUser::Register`.
- [ ] R28-04 — Проверка legacy пути: существует ли входящий `ACTION=ADD_CONTACT` / использование `LocalApplicationHandler::handleAddContact()`; если контракт живой — описать gap/нехватку реализации.
- [ ] R28-05 — Сформировать “canonical sequence” для регистрации (ожидаемая последовательность методов и минимальные обязательные поля): `crm.company.add` → `crm.contact.add` → `crm.contact.company.add` (или альтернативы), и где происходит dedup.

## Артефакты проверки (DoD)

- Список файлов и конкретных строк/методов, которые являются entrypoint’ом.
- Пример payload (из `docs/*` или из `site_requests_handler` trace) для регистрации/создания сущностей.
- Ясное правило: кто инициатор (сайт или B24 event), и где выполняется привязка контакта к компании.

## Связанные документы

- ADR: `adr-r28-site-user-register-create-crm-contact-company.md`
- Смежные ADR: R15 (dedup company by INN), R26/R27 (outbound contact sync)

