import {PopupWindow, PopupWindowManager} from "main.popup";
import {Row} from "./product.list.row";

export class ProductApplication
{
    _popupPrefix = 'application_popup_';
    _gridPrefix = 'applications_grid_';
    _productRow: ?Row = null;
    _popup: ?PopupWindow = null;
    _popupLoaded: boolean = false;

    /*static unsetCheckbox(rowId)
    {
        let checkbox = null;

        if ((checkbox = document.querySelector('[data-application-row-id="'+rowId+'"]')) !== null) {
            checkbox.checked = false;
            delete checkbox.dataset.applicationRowId;
        }
    }*/

    constructor(productRow: Row)
    {
        this._productRow = productRow;
        let popupId = this.getPopupId(this._productRow.getId());
        let popup = PopupWindowManager.getPopupById(popupId);

        if (null === popup) {
            popup = new PopupWindow(this.getPopupId(this._productRow.getId()), window.body,{
                autoHide: true,
                closeIcon: true,
                closeByEsc: true,
                overlay: {
                    backgroundColor: 'grey', opacity: '80'
                },
                width: 800,
                offsetLeft: 0,
                offsetTop: 0
            });
        }
        this._popup = popup;
    }

    getPopupId(productRowId)
    {
        return this._popupPrefix + productRowId;
    }

    showPopup()
    {
        let self = this;

        if (!self._popupLoaded) {
            BX.ajax.runComponentAction('kitconsulting:crm.entity.extendedproduct.applications', 'getApplications', {
                mode: 'class',
                data: {
                    sessid: BX.bitrix_sessid(),
                    ajax: 'Y',
                    format: 'Y',
                    raw: 'Y',
                    productId: this._productRow.getModel().getField('PARENT_PRODUCT_ID', null)
                        || this._productRow.getModel().getProductId(),
                    rowId: this._productRow.getId().replace(this._productRow.getEditor().getRowIdPrefix(), '')
                }
            }).then(async (res) => {
                let applicationsResponse = await res;
                let table = self.createApplicationsTable(applicationsResponse.data);
                self._popup.setContent(table);
                self._popupLoaded = true;
                self._popup.show();
                self.applyListeners();
            });
        } else {
            self._popup.show();
        }
    }

    createApplicationsTable(data)
    {
        let container = document.createElement('DIV');
        let table = document.createElement('TABLE');
        let head = document.createElement('THEAD');
        let headRow = document.createElement('TR');
        let body = document.createElement('TBODY');
        let useAdvPrice = false;

        if (BX.Crm.EntityEditor.defaultInstance.getOwnerInfo().ownerType.startsWith('DEAL')) {
            let control = BX.Crm.EntityEditor.defaultInstance.getActiveControlById("UF_ADV_AGENT")

            if (control != null) {
                useAdvPrice = control.getContainer().querySelector('input[type="checkbox"]').checked;
            } else {
                useAdvPrice = BX.Crm.EntityEditor.defaultInstance.getModel().getField("UF_ADV_AGENT").VALUE != "0";
            }
        }

        container.id = this.getTableId(this._productRow.getId());
        container.append(table);
        table.append(head);
        head.append(headRow);
        table.append(body);

        data.columnNames.forEach((columnName) => {
            let headColumn = document.createElement('TH');

            headColumn.innerText = columnName;
            headRow.append(headColumn);
        });

        data.applications.forEach((elem) => {
            let bodyRow = document.createElement('TR');
            let checkboxColumn = document.createElement('TD');
            let checkbox = document.createElement('INPUT');
            let nameColumn = document.createElement('TD');

            checkbox.setAttribute('type', 'checkbox');
            checkbox.setAttribute('value', elem['APPLICATION_ID']);
            checkbox.dataset.price = useAdvPrice ? elem['APPLICATION_PRICE_ADV'] : elem['APPLICATION_PRICE_OPT'];
            checkbox.classList.add('product_application_checkbox');

            if (elem['APPLICATION_PRODUCT_ROW'] != null && this._productRow.getEditor().getProductById(elem['APPLICATION_PRODUCT_ROW']['ID']) != null) {
                checkbox.checked = true;
                //checkbox.dataset.applicationRowId = elem['APPLICATION_PRODUCT_ROW']['ID'];
            }
            checkboxColumn.append(checkbox)
            bodyRow.append(checkboxColumn);
            nameColumn.innerText = elem['APPLICATION_NAME']
            if (elem.PRODUCT_HAS)
                nameColumn.classList.add("product-bold");
            bodyRow.append(nameColumn);
            body.append(bodyRow);
        });

        return container;
    }

    applyListeners()
    {
        let tableElement = document.querySelector("#"+ this.getTableId(this._productRow.getId()));

        tableElement.querySelectorAll('.product_application_checkbox')
            .forEach((elem, id) => {
                elem.addEventListener('change', this.changeApplication.bind(this));
            });
    }

    destroy()
    {
        document.getElementById(this.getTableId(this._productRow.getId())).remove()
        this._popup.destroy();
    }

    getTableId(productRowId)
    {
        return this._gridPrefix + productRowId;
    }

    changeApplication(event)
    {
        let editor = this._productRow.getEditor();

        if (event.target.checked) {
            let adjustedProduct = this._productRow.getAdjustedProductRow();
            let id = editor.addProductRowAfter(adjustedProduct || this._productRow);
            let productRow = editor.getProductById(id);
            let selector = editor.getProductSelector(id);

            selector.onProductSelect(event.target.value);
            productRow.setField(
                'UF_APPLICATION_PARENT_PRODUCT_ROW_ID',
                this._productRow.getId().replace(editor.getRowIdPrefix(), '')
            );
            productRow.setApplicationPrice(event.target.dataset.price);
            productRow.disableUiField('UF_NEED_ADJUSTMENT');
            productRow.disableUiField('UF_CRM_PR_ADJUSTMENT');
            productRow.getNode().querySelector('[data-field-code="UF_APPLICATION_PRICE"]').style.display = null;
            editor.numerateRows();
            //event.target.dataset.applicationRowId = id;
        } else {
            editor.deleteApplicationsForRow(this._productRow.getField('ID'), event.target.value);
            editor.numerateRows();
            //delete event.target.dataset.applicationRowId;
        }
    }
}