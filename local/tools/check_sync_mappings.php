<?php

declare(strict_types=1);

/**
 * Lightweight guardrail for critical CRM UF mapping consistency.
 *
 * Exit code:
 * - 0: all checks passed
 * - 1: at least one mismatch found
 */

$root = dirname(__DIR__, 2);
require_once $root . '/local/sync/UfMap.php';

$ufMap = \OnlineService\Sync\UfMap::all();

$checks = [
    [
        'label' => 'UF config has company.discount',
        'ok' => isset($ufMap['company']['discount']) && is_string($ufMap['company']['discount']) && $ufMap['company']['discount'] !== '',
    ],
    [
        'label' => 'UF config has company.holding',
        'ok' => isset($ufMap['company']['holding']) && is_string($ufMap['company']['holding']) && $ufMap['company']['holding'] !== '',
    ],
    [
        'label' => 'ContactSync canonical holding iblock',
        'file' => $root . '/local/sync/to-site/ContactSync.php',
        'mustContain' => "private const HOLDING_IBLOCK_ID = 57;",
    ],
    [
        'label' => 'CompanySync uses mapped site element key in payload',
        'file' => $root . '/local/sync/to-site/CompanySync.php',
        'mustContain' => '$ufCompanySiteElementId => $siteElementId',
    ],
    [
        'label' => 'InboundEndpoint uses UfMap for site element id',
        'file' => $root . '/local/sync/from-site/InboundEndpoint.php',
        'mustContain' => "self::uf('company.site_element_id')",
    ],
    [
        'label' => 'OutboundContactMarketingForSite uses mapped contact marketing UF key',
        'file' => $root . '/local/sync/to-site/OutboundContactMarketingForSite.php',
        'mustContain' => "UfMap::get('company.contact_marketing_agent')",
    ],
    [
        'label' => 'OutboundContactMarketingForSite has no hardcoded marketing UF literal',
        'file' => $root . '/local/sync/to-site/OutboundContactMarketingForSite.php',
        'mustNotContain' => 'UF_CRM_1775034008956',
    ],
];

$errors = [];

foreach ($checks as $check) {
    if (array_key_exists('ok', $check)) {
        if ($check['ok']) {
            echo "[OK] {$check['label']}\n";
            continue;
        }
        $errors[] = "[FAIL] {$check['label']}: expected mapping key is missing or empty.";
        continue;
    }

    $file = $check['file'];
    if (!is_file($file)) {
        $errors[] = "[FAIL] {$check['label']}: file not found: {$file}";
        continue;
    }

    $content = (string)file_get_contents($file);
    if ($content === '') {
        $errors[] = "[FAIL] {$check['label']}: file is empty or unreadable: {$file}";
        continue;
    }

    if (strpos($content, $check['mustContain']) === false) {
        $errors[] = "[FAIL] {$check['label']}: expected fragment not found: {$check['mustContain']}";
        continue;
    }

    if (isset($check['mustNotContain']) && strpos($content, $check['mustNotContain']) !== false) {
        $errors[] = "[FAIL] {$check['label']}: forbidden fragment found: {$check['mustNotContain']}";
        continue;
    }

    echo "[OK] {$check['label']}\n";
}

// Compatibility alias remains temporary but must exist while legacy handlers are in use.
$companySyncFile = $root . '/local/sync/to-site/CompanySync.php';
$companySyncContent = is_file($companySyncFile) ? (string)file_get_contents($companySyncFile) : '';
if (strpos($companySyncContent, '$ufCompanySiteElementIdLegacy => $siteElementId') === false) {
    $errors[] = '[FAIL] CompanySync legacy alias site_element_id_legacy_alias is missing.';
} else {
    echo "[OK] CompanySync legacy alias site_element_id_legacy_alias exists\n";
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "All sync UF mapping checks passed.\n";
exit(0);
