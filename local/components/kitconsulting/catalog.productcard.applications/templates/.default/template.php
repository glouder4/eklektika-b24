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

$containerId = $arResult['PREFIX'].'_crm_productcard_applications_container';

$jsEventsManagerId = 'PageEventsManager_'.$arResult['COMPONENT_ID'];

$rowIdPrefix = $arResult['PREFIX'].'_product_row_';


$editorConfig = [
    'componentName' => $component->getName(),
    'signedParameters' => $component->getSignedParameters(),
    'reloadUrl' => '/local/components/kitconsulting/catalog.productcard.applications/list.ajax.php',
//    'productUrlBuilderContext' => $arResult['URL_BUILDER_CONTEXT'],

    'containerId' => $containerId,
//    'totalBlockContainerId' => $productTotalContainerId,
    'gridId' => $gridId,
//    'formId' => $grid['FORM_ID'],
    'entityId' => $arResult['ENTITY']['ID'] ?? 0,
//    'entityTypeId' => $arResult['ENTITY']['TYPE_ID'] ?? '',

//    'popupSettings' => $component->getPopupSettings(),
//    'languageId' => $component->getLanguageId(),
//    'siteId' => $component->getSiteId(),
    'catalogId' => $arResult['CATALOG_ID'],
    'componentId' => $arResult['COMPONENT_ID'],
    'jsEventsManagerId' => $jsEventsManagerId,
    'rowIdPrefix' => $rowIdPrefix,
    'items' => []
];

$productIdMask = '#PRODUCT_ID_MASK#';
$grid['ROWS']['template_0'] = [
    'ID' => $productIdMask,
    'OWNER_PRODUCT_ID' => null,
    'PRODUCT_ID' => null,
    'IBLOCK_ELEMENT' => [
            'NAME' => 'NEW'
    ]
];

$rows = [];
foreach ($grid['ROWS'] as $product)
{
    $rawProduct = $product;

    $rowId = $rowIdPrefix.$rawProduct['ID'];

    $product['NAME'] = $product['IBLOCK_ELEMENT']['NAME'];

    $columns = [
        'NAME' => '<span data-field="NAME">'.$product['NAME'].'</span>'
    ];

    $item = [
            'ID' => strval($product['ID']),
            'PRODUCT_ID' => $product['PRODUCT_ID'],
            'NAME' => $product['NAME'],
    ];

    if ($rawProduct['ID'] !== $productIdMask)
    {
        $editorConfig['items'][] = [
            'rowId' => $rowId,
            'fields' => $item,
        ];
    }

    $rows[] = [
        'id' => $rawProduct['ID'] === $productIdMask ? 'template_0' : $rawProduct['ID'],
        'raw_data' => $rawProduct,
        'data' => $product,
        'columns' => $columns,
        'has_child' => false,
//        'parent_id' => \Bitrix\Main\Grid\Context::isInternalRequest() && !empty($rawProduct['PARENT_ID']) ? $rawProduct['PARENT_ID'] : 0,
//		'parent_id' => !empty($rows) ? $rows[0]['id'] : '',
        'editable' => false,
    ];
}

//$panelStatus = ($arResult['NEW_ROW_POSITION'] === 'bottom') ? 'hidden' : 'active';
$panelStatus = 'active';
$buttonTopPanelClasses = [
    'crm-entity-product-list-add-block',
    'crm-entity-product-list-add-block-top',
    'crm-entity-product-list-add-block-' . $panelStatus,
];

$buttonTopPanelClasses = implode(' ', $buttonTopPanelClasses);

?>

<div class="crm-productcard-applications-wrapper" id="<?=$containerId?>">

    <div class="<?=$buttonTopPanelClasses?>">
        <div>
            <a class="ui-btn ui-btn-light-border"
               data-role="product-list-select-button"
               title="Выбрать товар из каталога"
               tabindex="-1">
                Выбрать товар
            </a>
        </div>
        <button class="ui-btn ui-btn-light-border ui-btn-icon-setting"
                data-role="product-list-settings-button"></button>
    </div>

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
    ],
    $component
);
?>

</div>

<script>
    BX.message(<?=\Bitrix\Main\Web\Json::encode(Loc::loadLanguageFile(__FILE__))?>);
    BX.ready(function() {
        if (!BX.Reflection.getClass('BX.Kitconsulting.ProductCard.Applications.Instance'))
        {
            BX.Kitconsulting.ProductCard.Applications.Instance = new BX.Kitconsulting.ProductCard.Applications.Editor('<?=$arResult['ID']?>');
        }

        BX.Kitconsulting.ProductCard.Applications.Instance.init(<?=\Bitrix\Main\Web\Json::encode($editorConfig)?>);
        BX.Crm["<?=$jsEventsManagerId?>"] = BX.Kitconsulting.ProductCard.Applications.Instance.getPageEventsManager();
    });
</script>
