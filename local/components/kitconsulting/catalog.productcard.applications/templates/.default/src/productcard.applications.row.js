import {ajax, Cache, Dom, Event, Loc, Reflection, Runtime, Tag, Text, Type} from 'main.core';
import {Editor} from './productcard.applications.editor';
import 'ui.hint';
import 'ui.notification';
import {ProductModel} from "catalog.product-model";
import {EventEmitter} from "main.core.events";
import {PopupMenu} from "main.popup";
import {ProductSelector} from "catalog.product-selector";

export class Row {

    id: ?string;
    editor: ?Editor;
    // model: ?ProductModel;
    // mainSelector: ?ProductSelector;
    fields: Object = {};

    // handleFocusUnchangeablePrice = this.#showChangePriceNotify.bind(this);
    // handleChangeStoreData = this.#onChangeStoreData.bind(this);
    // handleProductErrorsChange = Runtime.debounce(this.#onProductErrorsChange, 500, this);
    // handleMainSelectorClear = Runtime.debounce(this.#onMainSelectorClear.bind(this), 500, this);
    // // handleMainSelectorChange = Runtime.debounce(this.#onMainSelectorChange.bind(this), 500, this);
    // handleMainSelectorChange = this.#onMainSelectorChange.bind(this);
    // handleStoreFieldChange = Runtime.debounce(this.#onStoreFieldChange.bind(this), 500, this);
    // handleStoreFieldClear = Runtime.debounce(this.#onStoreFieldClear.bind(this), 500, this);
    // handleOnGridUpdated = this.#onGridUpdated.bind(this);

    cache = new Cache.MemoryCache();

    constructor(id, fields, editor) {
        this.setId(id);
        // this.setSettings(settings);
        this.setEditor(editor);
        // this.setModel(fields, settings);
        this.setFields(fields);
        this.#initActions();
        // // this.#initSelector();
        // // this.#initStoreSelector();
        // // this.#initStoreAvailablePopup();
        // // this.#initReservedControl();
        // this.modifyBasePriceInput();
        this.refreshFieldsLayout();
        //
        // requestAnimationFrame(this.initHandlers.bind(this));
    }

    setFields(fields) {
        this.fields = fields;
    }

    getFields() {
        return this.fields;
    }

    setEditor(editor) {
        this.editor = editor;
    }

    getEditor() {
        return this.editor;
    }

    setId(id) {
        this.id = id;
    }

    getId() {
        return this.id;
    }

    #initActions()
    {
        const actionCellContentContainer = this.getNode().querySelector('.main-grid-cell-action .main-grid-cell-content');
        if (Type.isDomNode(actionCellContentContainer))
        {
            const actionsButton = Tag.render`
				<a
					href="#"
					class="main-grid-row-action-button"
				></a>
			`;

            Event.bind(actionsButton, 'click', (event) => {
                const menuItems = [
                    {
                        text: Loc.getMessage('ROW_ACTION_REMOVE'),
                        onclick: this.handleDeleteAction.bind(this),
                    }
                ];

                PopupMenu.show({
                    id: this.getId() + '_actions_popup',
                    bindElement: actionsButton,
                    items: menuItems,
                    cacheable: false,
                });

                event.preventDefault();
                event.stopPropagation();
            });

            Dom.append(actionsButton, actionCellContentContainer);
        }
    }

    handleDeleteAction(event, menuItem)
    {
        this.getEditor()?.deleteRow(this.getField('ID'));

        const menu = menuItem.getMenuWindow();
        if (menu)
        {
            menu.destroy();
        }
    }

    refreshFieldsLayout(): void
    {
        for (const field in this.fields)
        {
            this.updateUiField(field, this.fields[field]);
        }
    }

    updateUiField(name, value)
    {
        const item = this.getInputByFieldName(name);

        if (Type.isElementNode(item))
        {
            item.textContent = value;
        }
    }

    // controls
    getInputByFieldName(fieldName: string): ?HTMLElement
    {
        return this.getNode().querySelector('[data-field="' + fieldName + '"]');
    }

    getNode(): ?HTMLElement
    {
        return this.cache.remember('node', () => {
            const rowId = this.getField('ID', 0);

            return this.getEditorContainer().querySelector('[data-id="' + rowId + '"]');
        });
    }

    getEditorContainer(): HTMLElement
    {
        return this.getEditor().getContainer();
    }

    getField(name: string, defaultValue)
    {
        return this.fields.hasOwnProperty(name) ? this.fields[name] : defaultValue;
    }
}