# R28-SUB-01 — карта поиска: entrypoint регистрации → CRM контакт/компания/привязка

## Цель

Дать Team Lead готовую “карту поиска” по репозиторию для параллельной проверки: где может находиться обработчик регистрации и где создаются CRM-сущности.

## Область (приоритет)

- `local/modules` (особенно `yomerch.b24.inbound`, `yomerch.b24.base`, `yomerch.b24.siteconnector`)
- `local/php_interface/init.php`

## Кандидат #1 (наиболее вероятно): inbound `CRM_METHOD` (site → CRM)

- `local/modules/yomerch.b24.inbound/lib/site_requests_handler.php`
  - Ищем: как формируется/валидируется payload, какие `ACTION` реально прилетают после регистрации.
  - Ищем: примеры `ACTION=CRM_METHOD` и последовательность `METHOD`.
- `local/modules/yomerch.b24.inbound/lib/InboundEndpoint.php`
  - Ищем: `handleCrmMethod`, `crmCompanyAdd`, `crmContactAdd`, `crmContactCompanyAdd`.
  - Ищем: любые ветки, где dedup компании по ИНН возвращает “attach_contact_only”.

Строки для поиска:

- `ACTION` / `CRM_METHOD` / `METHOD`
- `crm.company.add`
- `crm.contact.add`
- `crm.contact.company.add`
- `crmCompanyAdd(` / `crmContactAdd(` / `crmContactCompanyAdd(`
- `reason_code` = `company_add_use_existing_for_contact`

## Кандидат #2: событие регистрации `main` (B24 event → CRM)

Файлы-кандидаты:

- `local/php_interface/init.php`
- `local/modules/**/include.php`
- `local/modules/**/init.php`
- `local/modules/**/bootstrap*.php`

Строки для поиска:

- `OnAfterUserRegister`
- `OnBeforeUserRegister`
- `OnAfterUserAdd`
- `OnBeforeUserAdd`
- `CUser::Register`
- `->Register(`
- `addEventHandlerCompatible(` / `addEventHandler(` / `AddEventHandler(`

## Кандидат #3: legacy `LocalApplicationHandler` (site → CRM, не через InboundEndpoint)

Файлы:

- `local/modules/yomerch.b24.base/lib/LocalApplicationHandler.php`
  - Проверить: `ALLOWED_ACTIONS`, `case 'ADD_CONTACT'`, реализация `handleAddContact()`.
- `local/modules/yomerch.b24.inbound/lib/InboundEndpoint.php`
  - Проверить: какие `ACTION` реально поддержаны в dispatcher’е; сейчас там есть `DELETE_CONTACT` как legacy-делегирование.

Строки для поиска:

- `ADD_CONTACT`
- `handleAddContact`
- `ALLOWED_ACTIONS`

## DoD

- Список “entrypoints” с доказательством: файл + метод + тип вызова (event / inbound action / legacy).
- Минимальный контракт: какие поля нужны, чтобы создать контакт, компанию и связать их (включая dedup по ИНН).

## Риски/ловушки

- “Пользователь сайта” (b_user) может вообще не фигурировать в этом репозитории, если регистрация полностью на сайте и CRM получает только данные контакта/компании.
- Привязка может быть не `COMPANY_ID`, а запись в `ContactCompanyTable` (проверять, если наблюдаются расхождения).

