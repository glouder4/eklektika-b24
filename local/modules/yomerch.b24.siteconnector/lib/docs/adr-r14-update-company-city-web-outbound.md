# ADR R14: исходящий `UPDATE_COMPANY` — город и сайт

## Статус

Принято.

## Контекст

Для сайта **`OS_COMPANY_CITY`** должен соответствовать UF города **`UF_CRM_1618551330657`**, а не прежнему коду в карте. **`OS_COMPANY_WEB_SITE`** должен отражать **мультиполе CRM `WEB`**, а не только строковый UF «веб», иначе при заполненном сайте в карточке в outbound уходили пустые значения.

## Решение

1. **`uf_mapping.php`**: `company.city` → **`UF_CRM_1618551330657`**.
2. **`CompanySyncNormalizeService::normalizeForOutbound`**:
   - город — скаляр из `$arFields` или снимка компании по ключу UF (в т.ч. вложенный `VALUE`);
   - сайт — `trim($company['MULTIFIELDS']['WEB']['VALUE'])`, если пусто — fallback на UF `company.web` с тем же разбором скаляра и приоритетом `$arFields`.
3. Контракт **`UPDATE_COMPANY.md`**: уточнены код UF города и правило для исходящего веба.

## Риски

- Данные, оставшиеся только в старом UF города (**`UF_CRM_1775034571084`**), перестанут попадать в outbound до переноса в **`UF_CRM_1618551330657`**.
- Несколько значений `WEB` в CRM: в снимке `CompanySyncReadService` по-прежнему одна строка на `TYPE_ID` — берётся то, что вернул `GetEntityFields`.

## Задачи

`tasks/r14-update-company-city-web-tasks.md`
