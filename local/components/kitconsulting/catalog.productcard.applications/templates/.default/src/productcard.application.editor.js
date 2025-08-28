import {BaseEvent, EventEmitter} from 'main.core.events';

export class Editor {

    productSelectionPopupHandler = this.handleProductSelectionPopup.bind(this);


    handleProductSelectionPopup(event)
    {
        const caller = 'crm_entity_product_list';
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
}
