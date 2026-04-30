<?php

declare(strict_types=1);

return [
    'company' => [
        /** Привязка к элементу ИБ 34 (`B24_COMPANY_ID` у элемента = CRM ID компании). */
        'holding' => 'UF_CRM_1758028816',
        /** Галочка «головная компания холдинга» (проверка при inbound duplicate INN → дочерняя компания). */
        'head_company_flag' => 'UF_CRM_1758028888',
        /** Список компаний холдинга (головная + дочерние), синхронизируется на всех участников. */
        'holding_group_members' => 'UF_CRM_1776426878',
        'discount' => 'UF_CRM_1771490556028',
        'holding_companies' => 'UF_CRM_1777129490',
        'is_head' => 'UF_CRM_1775030726726',
        'site_element_id' => 'UF_CRM_1774915439581',
        'site_element_id_legacy_alias' => 'UF_CRM_3804624439373',
        'is_marketing_agent' => 'UF_CRM_1675675211485',
        'contact_marketing_agent' => 'UF_CRM_1775034008956',
        'requisites_file' => 'UF_CRM_1755643990423',
        'city' => 'UF_CRM_1618551330657',
        'web' => 'UF_CRM_1777119084064',
        'business_scope' => 'UF_CRM_1777119807943',
        'legal_address' => 'UF_CRM_1777120939583',
        'legan_main_phone' => 'UF_CRM_1777069666894',
        'legan_mobile_phone' => 'UF_CRM_1777069676348',
    ],
    'contact' => [
        'site_user_id' => 'UF_CRM_1776075126830',
        'is_director' => 'UF_CRM_1777068292434',
        /** Идентификатор на сайт: DELETE_CONTACT `ID`; UPDATE_CONTACT `ID` и fallback `OS_COMPANY_B24_ID` без компании. */
        'delete_site_ref' => 'UF_CRM_3804624445748',
    ],
];
