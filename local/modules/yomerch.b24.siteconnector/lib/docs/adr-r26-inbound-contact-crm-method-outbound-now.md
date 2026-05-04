# ADR R26: `crm.contact.add` / `crm.contact.update` через inbound — явный `UPDATE_CONTACT` на сайт

## Статус

Принято.

## Контекст

В **`InboundEndpoint`** при вызовах **`CCrmContact::Add` / `Update`** включается **`ContactSync::suspendOutbound(true)`**, чтобы обработчик **`OnAfterCrmContactUpdate`** не уходил в рекурсию и не дублировал работу во время входящего запроса. В результате **`onAfterContactUpdate`** выходил по флагу suspend и **не вызывал** **`sendContactToSite`** — сайт не получал **`UPDATE_CONTACT`** после изменений контакта с сайта через **`CRM_METHOD`**.

## Решение

После успешного **`Add`** / **`Update`** (после `suspendOutbound(false)`) вызывается **`ContactSync::sendContactToSiteNow($id)`**.

Дополнительно в **`ContactSync::extractContactIdFromArgs`**: поддержка **`Bitrix\Main\Event`** и вложенных структур с **`ID` / `FIELDS` / `data`** — для сценариев UI/новых форматов событий, где ID не лежит на верхнем уровне массива.

## Риски

- Дополнительный HTTP-запрос на сайт при каждом успешном inbound-обновлении контакта; при недоступности сайта ошибка логируется в исходящем контуре, успех **`CRM_METHOD`** в CRM сохраняется.

## Задачи

`tasks/r26-inbound-contact-outbound-now-tasks.md`
