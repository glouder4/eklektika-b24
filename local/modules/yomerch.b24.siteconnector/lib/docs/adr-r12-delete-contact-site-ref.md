# ADR R12: контакт → сайт, UF `contact.delete_site_ref` (`UF_CRM_3804624445748`)

## Статус

Принято (расширено: UPDATE_CONTACT; см. также R12-06 — legacy `site_element_id` для исходящего `UPDATE_COMPANY`).

## Контекст

Сайт ожидает не числовой CRM `ID` контакта, а значение пользовательского поля **`UF_CRM_3804624445748`** в части сценариев. Для **UPDATE_CONTACT** при отсутствии компании в payload уходили **`ID`: null** и **`OS_COMPANY_B24_ID`: null**.

## Решение

1. **`UfMap`**: ключ `contact.delete_site_ref` → `UF_CRM_3804624445748` в `uf_mapping.php`.
2. **`ContactSync::onBeforeContactDelete`**: в outbound-теле **`ID`** и **`OS_COMPANY_B24_ID`** = одно и то же значение UF; **`B24_ID`** = CRM ID контакта. Пустой UF → не отправлять DELETE, trace `skip_no_site_ref`.
3. **`ContactSync::sendContactToSite` (UPDATE_CONTACT)**: при непустом UF — **`ID`** и **`OS_COMPANY_B24_ID`** = значение UF; при пустом UF и наличии компании — **`OS_COMPANY_B24_ID`** = CRM ID компании (legacy).
4. **`LocalApplicationHandler::handleDeleteContact`**: приоритет **`B24_ID`**; иначе резолв по CRM `ID` или по UF.

## Риски

- Пустой UF при удалении: сайт не получит DELETE_CONTACT — мониторинг `skip_no_site_ref`. На практике UF после регистрации должен быть в CRM; если раньше «терялся», причина в чтении в момент **`OnBeforeCrmContactDelete`** (неполный `GetByID` / нет поля в аргументах события). Резолвер читает: аргументы события → **`ContactTable`** → **`CCrmContact::GetByID`** + **`GetUserFields`**.
- **`OS_COMPANY_B24_ID`** со строкой UF: сайт должен принимать тип; при несовпадении контракта — доработка на стороне сайта.

## Legacy-ветка UPDATE (`elseif ($companyB24Id > 0)`)

Остаётся для контактов **без** значения в `contact.delete_site_ref` (импорт, старые данные, другой портал) — тогда **`OS_COMPANY_B24_ID`** по-прежнему может быть числовым CRM ID компании.

## Задачи

`tasks/r12-delete-contact-site-ref-tasks.md`
