# ADR R29: Наследование полей компании контактом при registration + existing INN

## Статус

Принято (v2).

## Контекст

При регистрации на eklektika.ru с **существующим ИНН** сайт вызывает:

1. `crm.company.add` → `reason_code=company_add_use_existing_for_contact`, `data.attach_contact_only=true`
2. `crm.contact.add`
3. `crm.contact.company.add` — **только** привязка `COMPANY_ID`
4. `crm.contact.update` — наследование от компании (флаг `inherit_from_company`)

Новый контакт **не наследовал** от компании: менеджеров, рекламного агента, `ACTIVE`. Эталонная логика уже есть в **`CompanySync::onAfterCompanyUpdate`** (при сохранении компании в UI), но inbound-привязка её не вызывала.

**ADR R26** снял немедленный outbound после `crm.contact.add`; для existing INN заказчик требует явный **`UPDATE_CONTACT`** после наследования при `crm.contact.update` + `inherit_from_company`.

### v2 (текущий контракт)

Наследование и outbound **вынесены** из `crm.contact.company.add` в отдельный шаг `crm.contact.update`. Привязка контакта к компании остаётся лёгкой (без наследования, без outbound).

## Решение

### Canonical sequence (CRM_METHOD, contact chain)

| Шаг | METHOD | Назначение |
|-----|--------|------------|
| 1 | `crm.contact.add` | Создание контакта; в `fields` — `UF_CRM_3804624445748` или ключ `contact.site_user_id` (ID пользователя сайта) |
| 2 | `crm.contact.company.add` | **Только** привязка `fields.COMPANY_ID`; без наследования, без outbound |
| 3 | `crm.contact.update` | Опционально `inherit_from_company: true` — наследование полей от компании (registration + existing INN) |
| 4 | *(автоматически CRM)* | Обязательный outbound **`UPDATE_CONTACT`** на сайт после успешного наследования (шаг 3) |

Шаг 3 выполняется **только** при `company_add_use_existing_for_contact` (existing INN). Для new company flow шаги 1–2 без изменений, шаг 3 **не вызывается**.

### Триггер наследования

Опциональный флаг **`PARAMS.inherit_from_company`** (truthy: `true`, `Y`, `1`, `'1'`, `'yes'`) в **`crm.contact.update`**.

- Сайт передаёт **только** для registration + existing INN flow (после шага 2).
- Без флага — `crm.contact.update` ведёт себя как обычный update (без наследования, без явного outbound по R29).

`crm.contact.company.add` **не принимает** `inherit_from_company` — только `id` + `fields.COMPANY_ID`.

### Наследуемые поля (company → contact)

Согласовано с **`CompanySync::onAfterCompanyUpdate`** и **`uf_mapping.php`**:

| Контакт (CRM) | Источник (компания) |
|---------------|---------------------|
| `ASSIGNED_BY_ID` | `ASSIGNED_BY_ID` |
| `contact.site_sync_value` (`UF_CRM_1757682312`) | `company.site_sync_value` (`UF_CRM_1778601096`) |
| `company.contact_marketing_agent` (`UF_CRM_1775034008956`) | зеркало `company.is_marketing_agent` (`UF_CRM_1675675211485`) |
| `contact.inherits_company_is_marketing_agent` (`UF_CRM_1698752707853`) | `Y`/`N` по `OutboundContactMarketingForSite::isMarketingAgentTruthy` |
| `ACTIVE` | `Y` если компания — рекламный агент, иначе `N` |

**Copy only if empty** на контакте (не перезаписывать вручную заданные поля).

### Реализация

- **`ContactInheritFromCompanyService`** (`yomerch.b24.contract`): `buildInheritFieldsFromCompany`, `applyToContact`.
- **`InboundEndpoint::crmContactCompanyAdd`**: только привязка `COMPANY_ID`; **`ContactSync::suspendOutbound`** на время `Update`.
- **`InboundEndpoint::crmContactUpdate`**: при `inherit_from_company` — наследование от primary company контакта → **`ContactSync::sendContactToSiteNow`**.
- **`ContactSync::suspendOutbound`** на оба `Update`; outbound только явный в конце шага 3.

### Outbound `UPDATE_CONTACT` (обязательный после наследования)

Минимальный контракт payload для R29 (поля, критичные для сайта после registration + existing INN):

| Поле outbound | Смысл |
|---------------|-------|
| `B24_ID` | CRM ID контакта |
| `UF_CRM_3804624445748` | ID пользователя сайта (`contact.site_user_id` / site ref) |
| `ASSIGNED_BY_ID` | Ответственный менеджер контакта |
| `UF_CRM_1757682312` | Второй менеджер (`contact.site_sync_value`) |
| `UF_CRM_1698752707853` | Наследование признака рекламного агента (`contact.inherits_company_is_marketing_agent`) |
| `ACTIVE` | `Y`/`N` — активность контакта на сайте |

Полный `sendContactToSiteNow` может включать дополнительные поля (имя, email, `UF_ADVERTISING_AGENT` и т.д. — см. R27, `ContactSync`).

### Ответ API

При успешном наследовании в **`crm.contact.update`**:

```json
{
  "success": 1,
  "result": true,
  "reason_code": "contact_inherited_from_company",
  "data": {
    "contact_inherited": true,
    "evidence": {
      "company_id": 123,
      "contact_id": 456,
      "copied_fields": ["ASSIGNED_BY_ID", "UF_CRM_..."]
    }
  }
}
```

`crm.contact.company.add` при успехе — только `success: 1`, `result: true` (без `contact_inherited_from_company`).

### Trace

`site_requests_handler.dispatch.done`:

- **`crm.contact.company.add`**: `contact_id_param`, `company_id_param` (без `inherit_from_company`).
- **`crm.contact.update`**: `contact_id_param`, `inherit_from_company`, `contact_inherited`, `copied_fields_count` (без PII).

## Ограничения

- Поля компании **не меняются**.
- Сценарий **новой компании** (не existing INN) — без шага 3, без изменений.
- Не рефакторить **`CompanySync::onAfterCompanyUpdate`** в рамках R29 (минимальный diff).

## Связанные ADR

- **R13** — `ACTIVE` / маркетинговый агент на сайте
- **R24** — UF рекламного агента company → contact
- **R26** — отложенный outbound при `crm.contact.add`; R29 — исключение для `crm.contact.update` + `inherit_from_company`
- **R27** — поля `UPDATE_CONTACT` outbound

## Задачи

`tasks/r29-contact-inherit-from-company-tasks.md`

## Smoke (curl)

```bash
# Шаг 2 — только привязка
curl -sS -X POST 'https://<portal>/local/inbound-test.php' \
  -H 'Content-Type: application/json' \
  -d '{
    "ACTION": "CRM_METHOD",
    "METHOD": "crm.contact.company.add",
    "PARAMS": {
      "id": <CONTACT_ID>,
      "fields": { "COMPANY_ID": <COMPANY_ID> }
    }
  }'

# Шаг 3 — наследование + outbound UPDATE_CONTACT
curl -sS -X POST 'https://<portal>/local/inbound-test.php' \
  -H 'Content-Type: application/json' \
  -d '{
    "ACTION": "CRM_METHOD",
    "METHOD": "crm.contact.update",
    "PARAMS": {
      "id": <CONTACT_ID>,
      "fields": {},
      "inherit_from_company": true
    }
  }'
```

Ожидание шага 3: `success: 1`, `reason_code: contact_inherited_from_company`, в trace — `contact_inherited: true`, `copied_fields_count` > 0.
