# Task R24 — зеркалирование UF маркетингового агента на контакты

## Критерии готовности

- После **сохранения компании в CRM** (карточка / API CRM, не inbound с сайта) у всех привязанных контактов UF **`UF_CRM_1698752707853`** совпадает с UF компании **`UF_CRM_1675675211485`** (`$isMarketingAgentRaw` в **`CompanySync`**).

## Код

- `yomerch.b24.outbound/lib/CompanySync.php`
- `yomerch.b24.contract/lib/config/uf_mapping.php`
