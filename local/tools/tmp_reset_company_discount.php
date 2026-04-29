<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/sync/UfMap.php');

@set_time_limit(0);
@ignore_user_abort(true);

if (!\Bitrix\Main\Loader::includeModule('crm')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "CRM module is not available\n";
    exit;
}

global $USER;
if (!is_object($USER) || !$USER->IsAdmin()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Access denied\n";
    exit;
}

$discountField = \OnlineService\Sync\UfMap::get('company.discount');
$batchSize = max(1, (int)($_REQUEST['batch'] ?? 50));
$limit = max(0, (int)($_REQUEST['limit'] ?? 0));
$offset = max(0, (int)($_REQUEST['offset'] ?? 0));
$dryRun = (string)($_REQUEST['dry_run'] ?? '1') === '1';
$run = (string)($_REQUEST['run'] ?? '0') === '1';

header('Content-Type: application/json; charset=utf-8');

if (!$run) {
    echo json_encode([
        'ok' => false,
        'message' => 'Set run=1 to execute. Default is safe mode.',
        'example' => '/local/tools/tmp_reset_company_discount.php?run=1&dry_run=1&batch=50',
        'params' => [
            'run' => '1 to execute',
            'dry_run' => '1 only analyze, 0 perform updates',
            'batch' => 'companies per run (default 50)',
            'limit' => 'max companies to process in this run (0 = no limit)',
            'offset' => 'skip first N companies',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$select = ['ID', $discountField, 'TITLE'];
$companyDb = \CCrmCompany::GetListEx(
    ['ID' => 'ASC'],
    [],
    false,
    false,
    $select
);

$processed = 0;
$changed = 0;
$skipped = 0;
$errors = [];
$sample = [];
$entity = new \CCrmCompany(false);
$index = 0;

while ($row = $companyDb->Fetch()) {
    $index++;
    if ($index <= $offset) {
        continue;
    }

    $companyId = (int)($row['ID'] ?? 0);
    if ($companyId <= 0) {
        continue;
    }

    if ($limit > 0 && $processed >= $limit) {
        break;
    }
    if ($processed >= $batchSize) {
        break;
    }

    $processed++;
    $currentValue = $row[$discountField] ?? null;
    $isAlreadyEmpty = ($currentValue === null || $currentValue === '' || $currentValue === false);

    if ($isAlreadyEmpty) {
        $skipped++;
        if (count($sample) < 20) {
            $sample[] = [
                'id' => $companyId,
                'title' => (string)($row['TITLE'] ?? ''),
                'action' => 'skip_already_empty',
            ];
        }
        continue;
    }

    if ($dryRun) {
        $changed++;
        if (count($sample) < 20) {
            $sample[] = [
                'id' => $companyId,
                'title' => (string)($row['TITLE'] ?? ''),
                'action' => 'would_update_to_empty',
                'old_value' => $currentValue,
            ];
        }
        continue;
    }

    $fields = [
        $discountField => '',
    ];

    $ok = $entity->Update(
        $companyId,
        $fields,
        true,
        [
            'CURRENT_USER' => \CCrmSecurityHelper::GetCurrentUserID(),
            'IS_SYSTEM_ACTION' => false,
            'REGISTER_SONET_EVENT' => false,
            'DISABLE_USER_FIELD_CHECK' => false,
            'DISABLE_REQUIRED_USER_FIELD_CHECK' => false,
        ]
    );

    if ($ok) {
        $changed++;
        if (count($sample) < 20) {
            $sample[] = [
                'id' => $companyId,
                'title' => (string)($row['TITLE'] ?? ''),
                'action' => 'updated_to_empty',
            ];
        }
    } else {
        $errors[] = [
            'id' => $companyId,
            'title' => (string)($row['TITLE'] ?? ''),
            'error' => $entity->LAST_ERROR,
        ];
    }
}

echo json_encode([
    'ok' => count($errors) === 0,
    'dry_run' => $dryRun,
    'discount_field' => $discountField,
    'processed' => $processed,
    'changed' => $changed,
    'skipped' => $skipped,
    'errors_count' => count($errors),
    'errors' => array_slice($errors, 0, 50),
    'sample' => $sample,
    'next_offset' => $offset + $processed,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

