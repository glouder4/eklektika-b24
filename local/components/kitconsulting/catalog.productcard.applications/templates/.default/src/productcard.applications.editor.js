import {ajax, Cache, Dom, Event, Loc, Reflection, Runtime, Tag, Text, Type} from 'main.core';
import {BaseEvent, EventEmitter} from 'main.core.events';
import {PageEventsManager} from "./page.events.manager";
import {ProductModel} from "catalog.product-model";
import {ProductSelector} from "catalog.product-selector";

import {Row} from "./productcard.applications.row";

export class Editor {

    productSelectionPopupHandler = this.handleProductSelectionPopup.bind(this);
    onDialogSelectProductHandler = this.handleOnDialogSelectProduct.bind(this);
    onBeforeGridRequestHandler = this.handleOnBeforeGridRequest.bind(this);
    onSaveHandler = this.handleOnSave.bind(this);
    onEntityUpdateHandler = this.handleOnEntityUpdate.bind(this);
    onEditorSubmit = this.handleEditorSubmit.bind(this);

    products = [];

    cache = new Cache.MemoryCache();

    constructor(id)
    {
        this.setId(id);
        this.products = [];
    }

    init(config = {})
    {
        this.setSettings(config);
        this.initProducts();
        this.subscribeDomEvents();
        this.subscribeCustomEvents();
    }

    getId()
    {
        return this.id;
    }

    setId(id)
    {
        this.id = id;
    }

    /* settings tools */
    getSettings()
    {
        return this.settings;
    }

    setSettings(settings)
    {
        this.settings = settings ? settings : {};
    }

    getSettingValue(name, defaultValue)
    {
        return this.settings.hasOwnProperty(name) ? this.settings[name] : defaultValue;
    }

    setSettingValue(name, value)
    {
        this.settings[name] = value;
    }

    initProducts()
    {
        this.products = [];
        const list = this.getSettingValue('items', []);

        for (const item of list)
        {
            const fields = {...item.fields};
            console.log(fields);
            this.products.push(new Row(item.rowId, fields, this));
        }
    }

    subscribeDomEvents()
    {
        this.unsubscribeDomEvents();
        const container = this.getContainer();

        if (Type.isElementNode(container))
        {
            container.querySelectorAll('[data-role="product-list-select-button"]').forEach((selectButton) => {
                Event.bind(
                    selectButton,
                    'click',
                    this.productSelectionPopupHandler
                );
            });

            // container.querySelectorAll('[data-role="product-list-settings-button"]').forEach((configButton) => {
            //     Event.bind(
            //         configButton,
            //         'click',
            //         this.showSettingsPopupHandler
            //     );
            // });
        }
    }

    unsubscribeDomEvents()
    {
        const container = this.getContainer();

        if (Type.isElementNode(container))
        {
            container.querySelectorAll('[data-role="product-list-select-button"]').forEach((selectButton) => {
                Event.unbind(
                    selectButton,
                    'click',
                    this.productSelectionPopupHandler
                );
            });

            // container.querySelectorAll('[data-role="product-list-add-button"]').forEach((addButton) => {
            //     Event.unbind(
            //         addButton,
            //         'click',
            //         this.productRowAddHandler
            //     );
            // });
            //
            // container.querySelectorAll('[data-role="product-list-settings-button"]').forEach((configButton) => {
            //     Event.unbind(
            //         configButton,
            //         'click',
            //         this.showSettingsPopupHandler
            //     );
            // });
        }
    }

    subscribeCustomEvents()
    {
        this.unsubscribeCustomEvents();
        EventEmitter.subscribe('CrmProductSearchDialog_SelectProduct', this.onDialogSelectProductHandler);
        // EventEmitter.subscribe('BX.Crm.EntityEditor:onSave', this.onSaveHandler);
        EventEmitter.subscribe('onEntityUpdate', this.onEntityUpdateHandler);
        // EventEmitter.subscribe('BX.Crm.EntityEditorAjax:onSubmit', this.onEditorSubmit);
        // EventEmitter.subscribe('EntityProductListController:onInnerCancel', this.onInnerCancelHandler);
        EventEmitter.subscribe('Grid::beforeRequest', this.onBeforeGridRequestHandler);
        // EventEmitter.subscribe('Grid::updated', this.onGridUpdatedHandler);
        // EventEmitter.subscribe('Grid::rowMoved', this.onGridRowMovedHandler);
        // EventEmitter.subscribe('BX.Catalog.ProductSelector:onBeforeChange', this.onBeforeProductChangeHandler);
        // EventEmitter.subscribe('BX.Catalog.ProductSelector:onChange', this.onProductChangeHandler);
        // EventEmitter.subscribe('BX.Catalog.ProductSelector:onClear', this.onProductClearHandler);
        // EventEmitter.subscribe('Dropdown::change', this.dropdownChangeHandler);
    }

    unsubscribeCustomEvents()
    {
        EventEmitter.unsubscribe('CrmProductSearchDialog_SelectProduct', this.onDialogSelectProductHandler);
        // EventEmitter.unsubscribe('BX.Crm.EntityEditor:onSave', this.onSaveHandler);
        EventEmitter.unsubscribe('onEntityUpdate', this.onEntityUpdateHandler);
        // EventEmitter.unsubscribe('BX.Crm.EntityEditorAjax:onSubmit', this.onEditorSubmit);
        // EventEmitter.unsubscribe('EntityProductListController:onInnerCancel', this.onInnerCancelHandler);
        EventEmitter.unsubscribe('Grid::beforeRequest', this.onBeforeGridRequestHandler);
        // EventEmitter.unsubscribe('Grid::updated', this.onGridUpdatedHandler);
        // EventEmitter.unsubscribe('Grid::rowMoved', this.onGridRowMovedHandler);
        // EventEmitter.unsubscribe('BX.Catalog.ProductSelector:onBeforeChange', this.onBeforeProductChangeHandler);
        // EventEmitter.unsubscribe('BX.Catalog.ProductSelector:onChange', this.onProductChangeHandler);
        // EventEmitter.unsubscribe('BX.Catalog.ProductSelector:onClear', this.onProductClearHandler);
        // EventEmitter.unsubscribe('Dropdown::change', this.dropdownChangeHandler);
    }

    handleOnDialogSelectProduct(event)
    {
        const [productId] = event.getCompatData();

        if(!this.getRowByProductId(productId)) {
            this.addProductRow(productId);
        }
        // if (this.getProductCount() > 0 || this.products[0]?.getField('ID') <= 0)
        // {
        //     // id = this.addProductRow();
        // }
        // else
        // {
        //     id = this.products[0]?.getField('ID');
        // }
        // this.selectProductInRow(id, productId)
    }

    getRowByProductId (productId) {
        let result = this.products.filter((elem) => +(elem.getField('PRODUCT_ID')) == +productId);

        if(result.length > 0) {
            return result[0];
        }

        return null;
    }

    handleProductSelectionPopup(event)
    {
        const caller = 'crm_productcard_applications_list';
        const jsEventsManagerId = this.getSettingValue('jsEventsManagerId', '');

        const popup = new BX.CDialog({
            content_url: '/bitrix/components/bitrix/crm.product_row.list/product_choice_dialog.php?'
                + 'caller=' + caller
                + '&JS_EVENTS_MANAGER_ID=' + BX.util.urlencode(jsEventsManagerId)
                + '&sessid=' + BX.bitrix_sessid(),
            height: Math.max(500, window.innerHeight - 400),
            width: Math.max(800, window.innerWidth - 400),
            draggable: true,
            resizable: true,
            min_height: 500,
            min_width: 800,
            zIndex: 800
        });

        EventEmitter.subscribeOnce(popup, 'onWindowRegister', BX.defer(() => {
            popup.Get().style.position = 'fixed';
            popup.Get().style.top = (parseInt(popup.Get().style.top) - BX.GetWindowScrollPos().scrollTop) + 'px';
            popup.OVERLAY.style.zIndex = 798;
        }));

        EventEmitter.subscribeOnce(window, 'EntityProductListController:onInnerCancel', BX.defer(() => {
            popup.Close();
        }));

        if (!Type.isUndefined(BX.Crm.EntityEvent))
        {
            EventEmitter.subscribeOnce(window, BX.Crm.EntityEvent.names.update, BX.defer(() => {
                requestAnimationFrame(() => {
                    popup.Close()
                }, 0);
            }));
        }

        popup.Show();
    }

    getContainer()
    {
        // return this.cache.remember('container', () => {
            return document.getElementById(this.getContainerId());
        // });
    }

    getContainerId()
    {
        return this.getSettingValue('containerId', '');
    }

    initPageEventsManager()
    {
        const componentId = this.getSettingValue('componentId');
        this.pageEventsManager = new PageEventsManager({id: componentId});
    }

    getPageEventsManager()
    {
        if (!this.pageEventsManager)
        {
            this.initPageEventsManager();
        }

        return this.pageEventsManager;
    }

    getGridId()
    {
        return this.getSettingValue('gridId', '');
    }

    getGrid()
    {
        return this.cache.remember('grid', () => {
            const gridId = this.getGridId();

            if (!Reflection.getClass('BX.Main.gridManager.getInstanceById'))
            {
                throw Error(`Cannot find grid with '${gridId}' id.`)
            }

            return BX.Main.gridManager.getInstanceById(gridId);
        });
    }

    createGridProductRow()
    {
        const newId = Text.getRandom();
        // const originalTemplate = this.redefineTemplateEditData(newId);

        const grid = this.getGrid();
        let newRow;
        if (this.getSettingValue('newRowPosition') === 'bottom')
        {
            newRow = grid.appendRowEditor();
        }
        else
        {
            newRow = grid.prependRowEditor();
        }

        const newNode = newRow.getNode();

        if (Type.isDomNode(newNode))
        {
            newNode.setAttribute('data-id', newId);
            newRow.makeCountable();
        }

        // if (originalTemplate)
        // {
        //     this.setOriginalTemplateEditData(originalTemplate);
        // }

        EventEmitter.emit('Grid::thereEditedRows', []);

        grid.adjustRows();
        grid.updateCounterDisplayed();
        grid.updateCounterSelected();

        return newRow;
    }

    async addProductRow(productId)
    {
        const row = this.createGridProductRow();
        const newId = row.getId();

        // if (anchorProduct)
        // {
        //     const anchorRowNode = this.getGrid().getRows().getById(anchorProduct.getField('ID'))?.getNode();
        //     if (anchorRowNode)
        //     {
        //         anchorRowNode.parentNode.insertBefore(row.getNode(), anchorRowNode.nextSibling);
        //     }
        // }

        await this.initializeNewProductRow(newId, productId);
        this.getGrid().bindOnRowEvents();
        return newId;
    }

    getRowIdPrefix()
    {
        return this.getSettingValue('rowIdPrefix', 'productcard_applications_list');
    }

    getProductSelector(newId)
    {
        return ProductSelector.getById('crm_grid_' + this.getRowIdPrefix() + newId);
    }

    reloadGrid(useProductsFromRequest: boolean = true, isInternalChanging: ?boolean = null): void
    {
        if (isInternalChanging === null)
        {
            isInternalChanging = !useProductsFromRequest;
        }

        this.getGrid().reloadTable(
            'POST',
            {useProductsFromRequest},
            () => EventEmitter.emit(this, 'onGridReloaded')
        );
    }

    /*
        keep in mind different actions for this handler:
        - native reload by grid actions (columns settings, etc)		- products from request
        - reload by tax/discount settings button					- products from request		this.reloadGrid(true)
        - rollback													- products from db			this.reloadGrid(false)
        - reload after SalesCenter order save						- products from db			this.reloadGrid(false)
        - reload after save if location had been changed
     */
    handleOnBeforeGridRequest(event)
    {
        const [grid, eventArgs] = event.getCompatData();

        if (!grid || !grid.parent || grid.parent.getId() !== this.getGridId())
        {
            return;
        }

        // reload by native grid actions (columns settings, etc), otherwise by this.reloadGrid()
        const isNativeAction = !('useProductsFromRequest' in eventArgs.data);
        const useProductsFromRequest = isNativeAction ? true : eventArgs.data.useProductsFromRequest;

        eventArgs.url = this.getReloadUrl();
        eventArgs.method = 'POST';
        eventArgs.sessid = BX.bitrix_sessid();
        eventArgs.data = {
            ...eventArgs.data,
            signedParameters: this.getSignedParameters(),
            products: useProductsFromRequest ? this.getProductsFields() : null,
            // locationId: this.getLocationId(),
            // currencyId: this.getCurrencyId(),
        };
    }

    getReloadUrl()
    {
        return this.getSettingValue('reloadUrl', '');
    }

    getComponentName()
    {
       return this.getSettingValue('componentName', 'kitconsulting:catalog.productcard.applications');
    }

    getSignedParameters()
    {
        return this.getSettingValue('signedParameters', '');
    }

    async initializeNewProductRow(newId, productId)
    {
        // if (Type.isNil(fields))
        // {
        //     fields = {
        //         ...this.getSettingValue('templateItemFields', {}),
        //         ...{
        //             CURRENCY: this.getCurrencyId()
        //         }
        //     };
        //
        // }


        let productFields = await this.fetchProduct(productId);
        let fields = {
            ID: newId,
            PRODUCT_ID: productId,
            NAME: productFields['NAME']
        };

        const rowId = this.getRowIdPrefix() + newId;
        // fields.ID = newId;

        const productRow = new Row(rowId, fields, this);

        if (this.getSettingValue('newRowPosition') === 'bottom')
        {
            this.products.push(productRow);
        }
        else
        {
            this.products.unshift(productRow);
        }

        return productRow;
    }

    getProductsFields()
    {
        const productFields = [];

        for (const item of this.products)
        {
            productFields.push(item.getFields());
        }

        return productFields;
    }

    async fetchProduct(product_id) {
        let componentName = this.getComponentName();
        let result = await BX.ajax.runComponentAction(componentName, 'getProduct', {
            mode: 'ajax',
            signedParameters: this.getSignedParameters(),
            data: {
                product_id: product_id
            }
        });

        return result.data;
    }

    deleteRow(rowId: string, skipActions: boolean = false): void
    {
        if (!Type.isStringFilled(rowId))
        {
            return;
        }

        const gridRow = this.getGrid().getRows().getById(rowId);
        if (gridRow)
        {
            Dom.remove(gridRow.getNode());
            this.getGrid().getRows().reset();
        }

        const productRow = this.getProductById(rowId);
        if (productRow)
        {
            const index = this.products.indexOf(productRow);
            if (index > -1)
            {
                this.products.splice(index, 1);
            }
        }

        EventEmitter.emit('Grid::thereEditedRows', []);
    }

    getProductById(id: string): ?Row
    {
        const rowId = this.getRowIdPrefix() + id;

        return this.getProductByRowId(rowId);
    }

    getProductByRowId(rowId: string): ?Row
    {
        return this.products.find((row: Row) => {
            return row.getId() === rowId;
        });
    }

    handleOnSave(event: BaseEvent)
    {
        const items = [];

        this.products.forEach((product) => {
            const item = {
                fields: {...product.fields},
                rowId: product.fields.ROW_ID
            };
            items.push(item);
        });

        this.setSettingValue('items', items);
    }

    handleOnEntityUpdate(event: BaseEvent)
    {
        const [data] = event.getData();
        if (true
            // this.isChanged()
            // && data.entityId === this.getSettingValue('entityId')
            // && data.entityTypeId === this.getSettingValue('entityTypeId')
        )
        {
            ajax.runComponentAction(
                this.getComponentName(),
                'saveApplications',
                {
                    mode: 'ajax',
                    signedParameters: this.getSignedParameters(),
                    data: {
                        rows: this.getProductsFields()
                    }
                }
            ).then(response => {
                // this.setGridChanged(false);
                this.reloadGrid(false);
            })
        }
    }

    handleEditorSubmit(event: BaseEvent)
    {
        if (!this.isLocationDependantTaxesEnabled())
        {
            return;
        }
        const entityData = event.getData()[0];
        if (!entityData || !entityData.hasOwnProperty('LOCATION_ID'))
        {
            return;
        }
        if (entityData['LOCATION_ID'] !== this.getLocationId())
        {
            this.setLocationId(entityData['LOCATION_ID']);
            this.reloadGrid(false);
        }
    }
}
