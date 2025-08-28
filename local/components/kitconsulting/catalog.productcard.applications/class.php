<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}

use Bitrix\Main;

class CatalogProductApplicationsComponent
    extends \CBitrixComponent
    implements \Bitrix\Main\Engine\Contract\Controllerable, Main\Errorable
{
    use Main\ErrorableImplementation;

    protected const STORAGE_GRID = 'GRID';
    private const PREFIX_MEASURE_INDEX = 'measure_';
    private const NEW_ROW_ID_PREFIX = 'n';

    /** @var Main\Grid\Options $gridConfig */
    protected $gridConfig;
    protected $storage = [];
    protected $defaultSettings = [];
    protected $rows = [];
    protected $newRowCounter = 0;

    /** @var Main\UI\PageNavigation $navigation */
    protected $navigation;

    public function __construct($component = null)
    {
        parent::__construct($component);
        $this->errorCollection = new Main\ErrorCollection();
    }

    public function configureActions()
    {
        // TODO: Implement configureActions() method.
    }

    public function onPrepareComponentParams($params)
    {
        $this->prepareEntitySettings($params);

        return $params;
    }

    public function executeComponent()
    {
        $this->fillSettings();

        $this->loadData();
        $this->prepareResult();
        $this->includeComponentTemplate();
    }

    protected function prepareResult()
    {
        Main\UI\Extension::load($this->getUiExtensions());

        $grid = [
//            'NAV_OBJECT' => $this->navigation,
            '~NAV_PARAMS' => ['SHOW_ALWAYS' => false],
            'SHOW_ROW_CHECKBOXES' => false,

            'SHOW_SELECTED_COUNTER' => false,
//            'ACTION_PANEL' => $this->getGridActionPanel(),

            // checked
            'GRID_ID' => $this->getGridId(),
            'COLUMNS' => array_values($this->getColumns()),
            'VISIBLE_COLUMNS' => array_values($this->getVisibleColumns()),

//            'AJAX_ID' => $this->getStorageItem(self::STORAGE_GRID, 'AJAX_ID'),
            'AJAX_MODE' => $this->arParams['~AJAX_MODE'],
            'AJAX_OPTION_JUMP' => $this->arParams['~AJAX_OPTION_JUMP'],
            'AJAX_OPTION_HISTORY' => $this->arParams['~AJAX_OPTION_HISTORY'],
            'AJAX_LOADER' => $this->arParams['~AJAX_LOADER'],

            'SHOW_NAVIGATION_PANEL' => false,
            //'SHOW_PAGINATION' => $this->arParams['~SHOW_PAGINATION'],
            'SHOW_PAGINATION' => false,
            //'SHOW_TOTAL_COUNTER' => $this->arParams['~SHOW_TOTAL_COUNTER'],
            'SHOW_TOTAL_COUNTER' => false,
            //'SHOW_PAGESIZE' => $this->arParams['~SHOW_PAGESIZE'],
            'SHOW_PAGESIZE' => false,
            //'PAGINATION' => $this->arParams['~PAGINATION'],
            'PAGINATION' => [],

//            'FORM_ID' => $this->getStorageItem(self::STORAGE_GRID, 'FORM_ID'),
//            'TAB_ID' => $this->getStorageItem(self::STORAGE_GRID, 'TAB_ID'),
        ];

//        $grid['SORT'] = $this->getStorageItem(self::STORAGE_GRID, 'GRID_ORDER');
//        $grid['SORT_VARS'] = $this->getStorageItem(self::STORAGE_GRID, 'GRID_ORDER_VARS');

        $grid['ROWS'] = $this->getGridRows();
//        $grid['TOTAL_ROWS_COUNT'] = $this->arParams['~TOTAL_PRODUCTS_COUNT'];

        $this->arResult['GRID'] = $grid;
//        $this->arResult['SETTINGS'] = $this->getSettings();
        $this->arResult['ENTITY'] = $this->entity;
        $this->arResult['CATALOG_TYPE_ID'] = \CCrmCatalog::GetCatalogTypeID();
        $this->arResult['CATALOG_ID'] = \CCrmCatalog::EnsureDefaultExists();
        $this->arResult['COMPONENT_ID'] = $this->randString();

        $this->arResult['PREFIX'] = $this->arParams['PREFIX'];
        if ($this->arResult['PREFIX'] === '') {
            $this->arResult['PREFIX'] = $this->getDefaultPrefix();
        }

        $this->arResult['ID'] = $this->arParams['ID'];
        if ($this->arResult['ID'] === '') {
            $this->arResult['ID'] = $this->arResult['PREFIX'];
        }
    }

    /**
     * @return array
     */
    protected function getGridRows(): array
    {
//        $currencyId = $this->getCurrencyId();

        if (!empty($this->rows))
        {
            $catalogItems = [];

            foreach ($this->rows as $index => $row)
            {
//                if (empty($row['IBLOCK_ID']))
//                {
//                    $row['IBLOCK_ID'] = \CCrmCatalog::GetDefaultID();
//                }

//                if (empty($row['CURRENCY']))
//                {
//                    $row['CURRENCY'] = $currencyId;
//                }

                $id = (int)$row['PRODUCT_ID'];
                if ($id > 0)
                {
                    if (!isset($catalogItems[$id]))
                    {
                        $catalogItems[$id] = [];
                    }
                    $catalogItems[$id][] = $index;
                }

                $row['IS_NEW'] = ($row['IS_NEW'] ?? 'N') === 'Y' ? 'Y' : 'N';

                $this->rows[$index] = $row;
            }

//            if (!empty($catalogItems))
//            {
//                $this->loadCatalog($catalogItems);
//                $this->loadSkuTree($catalogItems);
//            }

//            $this->fillEmptyCatalog();
        }

        return $this->rows;
    }

    /**
     * @return array
     */
    protected function listKeysSignedParameters()
    {
        return [
            // prepareEntityIds
            'GRID_ID',
            'NAVIGATION_ID',
            'FORM_ID',
            'TAB_ID',
            'AJAX_ID',
            // prepareAjaxOptions
            'AJAX_MODE',
            'AJAX_OPTION_JUMP',
            'AJAX_OPTION_HISTORY',
            'AJAX_LOADER',
            // preparePaginationOptions
            'SHOW_PAGINATION',
            'SHOW_TOTAL_COUNTER',
            'SHOW_PAGESIZE',
            // prepareSettings
            'CUSTOM_SITE_ID',
            'CUSTOM_LANGUAGE_ID',
            'CURRENCY_ID',
            'SET_ITEMS',
            'ALLOW_EDIT',
            'ALLOW_ADD_PRODUCT',
            'ALLOW_CREATE_NEW_PRODUCT',
            'PREFIX',
            'ID',
            'PRODUCT_DATA_FIELD_NAME',
            'PERSON_TYPE_ID',
            'ALLOW_LD_TAX',
            'LOCATION_ID',
            // prepareEntitySettings
            'ENTITY_TYPE_NAME',
            'ENTITY_ID',
        ];
    }

    /**
     * @return string
     */
    protected function getDefaultPrefix(): string
    {
        return (
            $this->entity['ID'] > 0
                ? strtolower($this->entity['TYPE_NAME']) . '_' . $this->entity['ID']
                : 'new_' . strtolower($this->entity['TYPE_NAME'])
            )
            . '_product_editor';
    }

    /**
     * @param array &$params
     * @return void
     */
    protected function prepareEntitySettings(array &$params): void
    {
        $params['ENTITY_TYPE_NAME'] = (isset($params['ENTITY_TYPE_NAME']) && is_string($params['ENTITY_TYPE_NAME'])
            ? $params['ENTITY_TYPE_NAME']
            : ''
        );
        $this->entity['TYPE_NAME'] = $params['ENTITY_TYPE_NAME'];
        $this->entity['ID'] = $params['ENTITY_ID'];
        $this->entity['TITLE'] = $params['ENTITY_TITLE'];
    }

    protected function getGridActionPanel(): array
    {
        return [];
    }

    /**
     * @return void
     */
    protected function initGridConfig(): void
    {
        $this->gridConfig = new Main\Grid\Options($this->getGridId());
        $this->gridConfig->setExpandedRows([]);
    }

    /**
     * @return void
     */
    protected function initGridColumns(): void
    {
        $visibleColumns = [];
        $visibleColumnsMap = [];

        $defaultList = true;
        $userColumnsIndex = [];
        $userColumns = $this->getUserGridColumnIds();
        if (!empty($userColumns)) {
            $defaultList = false;
            $userColumnsIndex = array_fill_keys($userColumns, true);
        }

        $columns = $this->getGridColumnsDescription();
        foreach (array_keys($columns) as $index) {
            if ($defaultList || isset($userColumnsIndex[$index])) {
                $visibleColumnsMap[$index] = true;
                $visibleColumns[$index] = $columns[$index];
            }
        }

        $this->fillStorageNode(self::STORAGE_GRID, [
            'COLUMNS' => $columns,
            'VISIBLE_COLUMNS' => $visibleColumns,
            'VISIBLE_COLUMNS_MAP' => $visibleColumnsMap,
        ]);
    }

    /**
     * @return void
     */
    protected function initGridPageNavigation(): void
    {
        $naviParams = $this->getGridNavigationParams();
        $this->navigation = new Main\UI\PageNavigation($this->getNavigationId());
        $this->navigation->setPageSizes($this->getPageSizes());
        $this->navigation->allowAllRecords(false);
        $this->navigation->setPageSize($naviParams['nPageSize']);

//        if (!$this->isUsedImplicitPageNavigation()) {
//            $this->navigation->initFromUri();
//        }
    }

    /**
     * @return array
     */
    protected function getGridNavigationParams(): array
    {
        return $this->gridConfig->getNavParams(['nPageSize' => 20]);
    }

    /**
     * @return void
     */
    protected function initGridOrder(): void
    {
        $result = ['ID' => 'DESC'];

        $sorting = $this->gridConfig->getSorting(['sort' => $result]);

        $order = strtolower(reset($sorting['sort']));
        if ($order !== 'asc')
            $order = 'desc';
        $field = key($sorting['sort']);
        $found = false;

        foreach ($this->getVisibleColumns() as $column) {
            if (!isset($column['sort']))
                continue;
            if ($column['sort'] == $field) {
                $found = true;
                break;
            }
        }
        unset($column);

        if ($found)
            $result = [$field => $order];

        $this->fillStorageNode(
            self::STORAGE_GRID,
            [
                'GRID_ORDER' => $this->modifyGridOrder($result),
                'GRID_ORDER_VARS' => $sorting['vars'],
            ]
        );

        unset($found, $field, $order, $sorting, $result);
    }

    /* Storage tools */

    /**
     * @param string $node
     * @param array $nodeValues
     * @return void
     */
    protected function fillStorageNode(string $node, array $nodeValues): void
    {
        if ($node === '' || empty($nodeValues)) {
            return;
        }

        if (!isset($this->storage[$node])) {
            $this->storage[$node] = [];
        }

        $this->storage[$node] = array_merge($this->storage[$node], $nodeValues);
    }

    /**
     * @param string $node
     * @return array|null
     */
    protected function getStorageNode(string $node): ?array
    {
        return $this->storage[$node] ?? null;
    }

    /**
     * @param string $node
     * @param string $item
     * @param mixed $value
     * @return void
     */
    protected function setStorageItem(string $node, string $item, $value): void
    {
        $this->fillStorageNode($node, [$item => $value]);
    }

    /**
     * @param string $node
     * @param string $item
     * @return mixed|null
     */
    protected function getStorageItem(string $node, string $item)
    {
        return $this->storage[$node][$item] ?? null;
    }

    /**
     * @return string
     */
    protected function getGridId(): ?string
    {
        return $this->getStorageItem(self::STORAGE_GRID, 'GRID_ID');
    }

    /**
     * @return string
     */
    protected function getNavigationId(): string
    {
        return $this->getStorageItem(self::STORAGE_GRID, 'NAVIGATION_ID');
    }

    /**
     * @return array
     */
    protected function getPageSizes(): array
    {
        return $this->getStorageItem(self::STORAGE_GRID, 'PAGE_SIZES');
    }

    /* Storage tools finish */

    /**
     * @return array
     */
    protected function getGridColumnsDescription(): array
    {
        global $USER_FIELD_MANAGER;

        $result = [];
        $columnDefaultWidth = 150;

        $result['NAME'] = [
            'id' => 'NAME',
            'name' => 'Название',
            'sort' => 'NAME'
        ];

        return $result;
    }

    protected function getUserGridColumnIds(): array
    {
        $result = $this->gridConfig->GetVisibleColumns();

        if (!empty($result) && !in_array('ID', $result, true)) {
            array_unshift($result, 'ID');
        }

        return $result;
    }

    /**
     * @return array
     */
    protected function getColumns()
    {
        return $this->getStorageItem(self::STORAGE_GRID, 'COLUMNS');
    }

    /**
     * @return array
     */
    protected function getVisibleColumns()
    {
        return $this->getStorageItem(self::STORAGE_GRID, 'VISIBLE_COLUMNS');

    }

    /**
     * @return void
     */
    protected function fillSettings(): void
    {
        $this->checkModules();
        $this->initDefaultSettings();
//        $this->loadReferences();
        $this->initSettings();
    }

    /**
     * @return void
     */
    protected function initDefaultSettings(): void
    {
        $this->defaultSettings = [
            'GRID_ID' => self::getDefaultGridId(),
        ];
        $this->defaultSettings['NAVIGATION_ID'] = static::createNavigationId($this->defaultSettings['GRID_ID']);
        $this->defaultSettings['FORM_ID'] = static::createFormId($this->defaultSettings['GRID_ID']);
        $this->defaultSettings['TAB_ID'] = '';
        $this->defaultSettings['AJAX_ID'] = '';
        $this->defaultSettings['PAGE_SIZES'] = [5, 10, 20, 50, 100];
        $this->defaultSettings['NEW_ROW_POSITION'] = CUserOptions::GetOption("crm.entity.product.list", 'new.row.position', 'top');
        $this->defaultSettings['ALLOW_CATALOG_PRICE_EDIT'] = CUserOptions::GetOption("crm.entity.product.list", 'new.row.position', 'top');
    }

    /**
     * @return string
     */
    public static function getDefaultGridId(): string
    {
        return self::clearStringValue(self::class);
    }

    /**
     * @param string $gridId
     * @return string
     */
    protected static function createNavigationId(string $gridId): string
    {
        return $gridId . '_NAVIGATION';
    }

    /**
     * @param string $gridId
     * @return string
     */
    protected static function createFormId(string $gridId): string
    {
        return 'form_' . $gridId;
    }

    /**
     * @return void
     */
    protected function initEntitySettings(): void
    {

    }

    /**
     * @return void
     */
    protected function initSettings(): void
    {
        $this->initEntitySettings();

        $paramsList = [
            self::STORAGE_GRID => [
                'GRID_ID',
                'NAVIGATION_ID',
                'PAGE_SIZES',
                'FORM_ID',
                'TAB_ID',
                'AJAX_ID',
                'NEW_ROW_POSITION',
            ],
        ];
        foreach ($paramsList as $entity => $list)
        {
            foreach ($list as $param)
            {
                $value = !empty($this->arParams[$param]) ? $this->arParams[$param] : $this->defaultSettings[$param];
                $this->setStorageItem($entity, $param, $value);
            }
        }

        $this->initGrid();
    }

    /**
     * @return void
     */
    protected function initGrid(): void
    {
        $this->initGridConfig();
        $this->initGridColumns();
        $this->initGridPageNavigation();
        $this->initGridOrder();
    }

    protected function checkModules(): bool
    {
        if (!Main\Loader::includeModule('catalog'))
        {
            $this->addErrorMessage('Module "catalog" is not installed.');

            return false;
        }

        if (!Main\Loader::includeModule('sale'))
        {
            $this->addErrorMessage('Module "sale" is not installed.');

            return false;
        }

        if (!Main\Loader::includeModule('iblock'))
        {
            $this->addErrorMessage('Module "iblock" is not installed.');

            return false;
        }

        if (!Main\Loader::includeModule('kitconsulting.productapplications'))
        {
            $this->addErrorMessage('Module "kitconsulting.productapplications" is not installed.');

            return false;
        }


        return true;
    }

    /**
     * @param string $value
     * @return string
     */
    private static function clearStringValue(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_:\\[\\]]/', '', $value);
    }

    /**
     * @param array $order
     * @return array
     */
    protected function modifyGridOrder(array $order): array
    {
        return $order;
    }

    /**
     * @return array
     */
    protected function getUiExtensions(): array
    {
        return [
            'core',
            'ajax',
            'tooltip',
            'ui.hint',
            'ui.fonts.ruble',
            'ui.notification',
            'catalog.product-model',
            'catalog.product-selector',
        ];
    }

    /**
     * @return void
     */
    protected function loadData(): void
    {
        $this->rows = [];

        if (is_array($this->arParams['~PRODUCTS']))
        {
            $this->rows = $this->arParams['~PRODUCTS'];

            foreach ($this->rows as $index => &$row)
            {
                if (!isset($row['ID']))
                {
                    $row['ID'] = $this->getNewRowId();
                }

                $id = $row['ID'];
            }
        } elseif ($this->entity['ID'] > 0)
        {
            $this->rows = \Kitconsulting\ProductApplications\Entity\ProductApplication::loadAllRows($this->entity['ID']);
        }
    }

    /**
     * @return string
     */
    protected function getNewRowId(): string
    {
        $result = self::NEW_ROW_ID_PREFIX . $this->getNewRowCounter();
        $this->newRowCounter++;

        return $result;
    }

    /**
     * @return int
     */
    protected function getNewRowCounter(): int
    {
        return $this->newRowCounter;
    }
}
