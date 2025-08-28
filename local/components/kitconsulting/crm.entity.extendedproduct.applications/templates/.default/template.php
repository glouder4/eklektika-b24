<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}

use Bitrix\Main\Localization\Loc;

/**
 * @var \CCrmEntityProductListComponent $component
 * @var \CBitrixComponentTemplate $this
 * @var \CMain $APPLICATION
 */

/** @var array $grid */
$grid = &$arResult['GRID'];
/** @var string $gridId */
$gridId = $grid['GRID_ID'];
/** @var array $settings */
$settings = &$arResult['SETTINGS'];
$containerId = $arResult['PREFIX'].'_crm_entity_extendedproduct_applications_container';
$jsEventsManagerId = 'PageEventsManager_'.$arResult['COMPONENT_ID'];
$rowIdPrefix = $arResult['PREFIX'].'_product_row_';
$productIdMask = '#PRODUCT_ID_MASK#';
/*$grid['ROWS']['template_0'] = [
    'ID' => $productIdMask,
    'OWNER_PRODUCT_ID' => null,
    'PRODUCT_ID' => null,
    'IBLOCK_ELEMENT' => [
            'NAME' => 'NEW'
    ]
];*/
$rows = [];

foreach ($grid['ROWS'] as $product)
{
    $product['APPLICATION_ADD'] = '<input type="checkbox" class="product_application_checkbox" value="'.$product['APPLICATION_ID'].'" data-name="'.$product['APPLICATION_NAME'].'">';
    $rows[] = [
        'id' => $product['APPLICATION_ID'],
        'data' => $product,
        'editable' => true
    ];
}

$panelStatus = 'active';
$buttonTopPanelClasses = [
    'crm-entity-product-list-add-block',
    'crm-entity-product-list-add-block-top',
    'crm-entity-product-list-add-block-' . $panelStatus,
];

$buttonTopPanelClasses = implode(' ', $buttonTopPanelClasses);
?>
<div class="crm-productcard-applications-wrapper" id="<?=$containerId?>">
<?php
$APPLICATION->IncludeComponent(
    'bitrix:main.ui.grid',
    '',
    [
        'GRID_ID' => $gridId,
        'HEADERS' => $grid['COLUMNS'],
        // 'ROW_LAYOUT' => $rowLayout,
        'SORT' => $grid['SORT'],
        'SORT_VARS' => $grid['SORT_VARS'],
        'ROWS' => $rows,
        'FORM_ID' => $grid['FORM_ID'],
        'TAB_ID' => $grid['TAB_ID'],
        'AJAX_ID' => $grid['AJAX_ID'],
        'AJAX_MODE' => $grid['AJAX_MODE'],
        'AJAX_OPTION_JUMP' => $grid['AJAX_OPTION_JUMP'],
        'AJAX_OPTION_HISTORY' => $grid['AJAX_OPTION_HISTORY'],
        'AJAX_LOADER' => $grid['AJAX_LOADER'],
        'SHOW_NAVIGATION_PANEL' => $grid['SHOW_NAVIGATION_PANEL'],
        'SHOW_PAGINATION' => $grid['SHOW_PAGINATION'],
        'SHOW_TOTAL_COUNTER' => $grid['SHOW_TOTAL_COUNTER'],
        'SHOW_PAGESIZE' => $grid['SHOW_PAGESIZE'],
        'SHOW_ROW_ACTIONS_MENU' => false,
        'PAGINATION' => $grid['PAGINATION'],
        'ALLOW_SORT' => false,
        'ALLOW_ROWS_SORT' => false,
        'ALLOW_ROWS_SORT_IN_EDIT_MODE' => false,
        'ALLOW_ROWS_SORT_INSTANT_SAVE' => false,
        'ENABLE_ROW_COUNT_LOADER' => false,
        'HIDE_FILTER' => true,
        'ENABLE_COLLAPSIBLE_ROWS' => true,
        'ADVANCED_EDIT_MODE' => true,
        'TOTAL_ROWS_COUNT' => $grid['TOTAL_ROWS_COUNT'],
        'NAME_TEMPLATE' => (string)($arParams['~NAME_TEMPLATE'] ?? ''),
        'ACTION_PANEL' => $grid['ACTION_PANEL'],
        'SHOW_ACTION_PANEL' => !empty($grid['ACTION_PANEL']),
        'SHOW_ROW_CHECKBOXES' => false,
        'SETTINGS_WINDOW_TITLE' => $arResult['ENTITY']['TITLE'],
        'SHOW_GRID_SETTINGS_MENU' => false
    ],
    $component
);
?>
</div>
<script>
    BX.message(<?=\Bitrix\Main\Web\Json::encode(Loc::loadLanguageFile(__FILE__))?>);
</script>
