# ADR R24: UF компании «маркетинговый агент» → UF контакта у всех привязанных

## Статус

Принято.

## Контекст

У компании в CRM UF **`UF_CRM_1675675211485`** (`company.is_marketing_agent`). У контакта в CRM должен дублироваться смысл в **`UF_CRM_1698752707853`** для всех контактов, связанных с компанией. Речь о **событии внутри CRM** (сохранение компании), а не о входящем `UPDATE_COMPANY` с сайта.

## Решение

- В **`uf_mapping.php`**: ключ **`contact.inherits_company_is_marketing_agent`** → **`UF_CRM_1698752707853`**.
- **Исходящий контур CRM → сайт + побочные обновления CRM**: в **`CompanySync::onAfterCompanyUpdate`** в тот же цикл обновления привязанных контактов, что и **`company.contact_marketing_agent`**, добавлено поле **`contact.inherits_company_is_marketing_agent`** с тем же сырьём **`$isMarketingAgentRaw`** (значение с UF компании **`company.is_marketing_agent`**).

Входящий **`UPDATE_COMPANY`** (`InboundEndpoint`) **не** дублирует эту логику: он обновляет поля компании по контракту сайта; наследование UF на контакты — зона ответственности CRM-события выше.

## Риски

- Расхождение типов enum/list между UF компании и UF контакта — настройка полей в CRM должна допускать те же значения.
- Контакты только по `COMPANY_ID` без строки в `ContactCompanyTable` могут не попасть в основной список — срабатывает fallback.

## Задачи

`tasks/r24-company-marketing-agent-to-contact-uf-task.md`
