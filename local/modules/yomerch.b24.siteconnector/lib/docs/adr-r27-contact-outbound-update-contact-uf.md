# ADR R27: исходящее обновление контакта (не через компанию) и UF `UF_CRM_1757682312`

## Статус

Принято.

## Контекст

Исходящий канал **«сохранение контакта в CRM → сайт»** не идёт через `CompanySync`, а через отдельные обработчики **`OnAfterCrmContactAdd` / `OnAfterCrmContactUpdate`** → **`ContactSync::sendContactToSite`** (`ACTION` = **`UPDATE_CONTACT`**). Регистрация: **`yomerch.b24.siteconnector`** → `register_bitrix_handlers.php`.

Дополнительно после **`CRM_METHOD` `crm.contact.*`** при подавлении CRM-событий вызывается **`ContactSync::sendContactToSiteNow`** (см. ADR R26).

Поле контакта **`UF_CRM_1757682312`** должно уходить на сайт не под именем UF, а в контрактном ключе **`SECOND_MANAGER`**. Ответственный контакта — в **`ASSIGNED_MANAGER`** из **`ASSIGNED_BY_ID`**.

## Решение

- В **`uf_mapping.php`**: **`contact.site_sync_value`** → **`UF_CRM_1757682312`** (источник для **`SECOND_MANAGER`**).
- В **`ContactSync::sendContactToSite`**: **`ASSIGNED_MANAGER`** = **`(int)($contact['ASSIGNED_BY_ID'] ?? 0)`**; **`SECOND_MANAGER`** = значение UF (пустая строка, если в CRM пусто).
- После **`OutboundContactMarketingForSite::mergeIntoUpdateContactPost`**:
  - **`ACTIVE`** (`Y`/`N`) — из UF **`contact.inherits_company_is_marketing_agent`** (**`UF_CRM_1698752707853`**); если он пуст, fallback на **`IS_MARKETING_AGENT`**, иначе **`N`**.
  - **`UF_ADVERTISING_AGENT`** — дублирует значение, выставленное для UF **`company.contact_marketing_agent`** (тот же смысл, что **`IS_MARKETING_AGENT`** / ключ UF в POST), для совместимости с обработчиком на сайте.

## Риски

- Смена типа **`ASSIGNED_MANAGER`** с пустой строки на число **`0`** при отсутствии ответственного: если сайт ожидал именно `''`, может понадобиться правка обработчика на сайте.
- Для **`ACTIVE`** при числовом значении UF «список» без явного `Y`/`N` правило «не ноль → Y» может дать ложное **`Y`** — при необходимости сузить до enum-разрешения как в **`OutboundContactMarketingForSite`**.

## Задачи

`tasks/r27-contact-outbound-update-contact-uf-tasks.md`
