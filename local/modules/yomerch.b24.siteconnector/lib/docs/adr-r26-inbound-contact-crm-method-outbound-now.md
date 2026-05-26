# ADR R26: `crm.contact.add` / `crm.contact.update` через inbound — явный `UPDATE_CONTACT` на сайт

## Статус

Принято (с дополнением по perf, 2026-05).

## Дополнение: отложенный outbound при inbound create (perf регистрации)

**Проблема:** синхронный **`sendContactToSiteNow`** после **`crm.contact.add`** / **`crm.contact.update`** блокировал inbound HTTP на время CURL к сайту (~18 s при медленном ответе).

**Решение:** для **`CRM_METHOD`** `crm.contact.add` / `crm.contact.update` явный **`sendContactToSiteNow`** **не вызывается**. События CRM по-прежнему подавляются **`suspendOutbound`** на время `Add`/`Update`. Исходящий **`UPDATE_CONTACT`** возможен позже: ручное сохранение в CRM, отдельный сценарий sync, будущая очередь (P1).

**Не затронуто:** `UPDATE_COMPANY` inbound, `CompanySync::onAfterCompanyUpdate` при правках из UI, `sendContactToSiteNow` из `CompanySync` / `handleUpdateCompany`.

## Контекст

В **`InboundEndpoint`** при вызовах **`CCrmContact::Add` / `Update`** включается **`ContactSync::suspendOutbound(true)`**, чтобы обработчик **`OnAfterCrmContactUpdate`** не уходил в рекурсию и не дублировал работу во время входящего запроса. В результате **`onAfterContactUpdate`** выходил по флагу suspend и **не вызывал** **`sendContactToSite`** — сайт не получал **`UPDATE_CONTACT`** после изменений контакта с сайта через **`CRM_METHOD`**.

## Решение (исходное R26, пересмотрено для create)

~~После успешного **`Add`** / **`Update`** (после `suspendOutbound(false)`) вызывается **`ContactSync::sendContactToSiteNow($id)`**.~~  
См. блок **«Дополнение: отложенный outbound»** выше — для inbound **`crm.contact.*`** немедленный outbound снят.

Дополнительно в **`ContactSync::extractContactIdFromArgs`**: поддержка **`Bitrix\Main\Event`** и вложенных структур с **`ID` / `FIELDS` / `data`** — для сценариев UI/новых форматов событий, где ID не лежит на верхнем уровне массива.

## Риски

- Дополнительный HTTP-запрос на сайт при каждом успешном inbound-обновлении контакта; при недоступности сайта ошибка логируется в исходящем контуре, успех **`CRM_METHOD`** в CRM сохраняется.

## Задачи

`tasks/r26-inbound-contact-outbound-now-tasks.md`
