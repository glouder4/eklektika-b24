<?php
/**
 * Legacy-заглушка в репозитории CRM-портала. На yomerch.ru каноника — endpoint.php (InboundGateway).
 * Не использовать для CRM→site без явного outbound_company_path в site_sync_settings.local.php.
 *
 * @see local/modules/yomerch.b24.inbound/endpoint.php
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'success' => 0,
    'error' => 'use_inbound_endpoint',
    'error_code' => 'deprecated_entrypoint',
    'hint' => '/local/modules/yomerch.b24.inbound/endpoint.php',
], JSON_UNESCAPED_UNICODE);
