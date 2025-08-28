<?php
namespace Kit\Scripts;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service;
use Bitrix\Crm\Service\Context;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Service\Factory;
use Bitrix\Crm\Service\Operation;
use Bitrix\Crm\Service\Operation\Action;

class DynamicSmartInvoice
    extends Factory\SmartInvoice
{
    protected function configureAddOperation(Operation $operation): void
    {
        $eventManager = Service\Container::getInstance()->getRestEventManager();

        $operation
            ->addAction(
                Operation::ACTION_BEFORE_SAVE,
                new Operation\Action\Compatible\SendEvent\WithCancel\Update(
                    'OnBeforeSmartInvoiceAdd',
                    'CRM_SMART_INVOICE_ADD_CANCELED',
                ),
            )
            ->addAction(
                Operation::ACTION_AFTER_SAVE,
                new Action\SendEvent($eventManager::EVENT_DYNAMIC_ITEM_ADD)
            )
            ->addAction(
                Operation::ACTION_AFTER_SAVE,
                new Action\SendEvent($eventManager->getItemEventNameWithEntityTypeId(
                    $eventManager::EVENT_DYNAMIC_ITEM_ADD,
                    $this->getEntityTypeId()
                ))
            )
        ;
    }
    public function getUpdateOperation(Item $item, Context $context = null): Operation\Update
    {
        $operation = parent::getUpdateOperation($item, $context);

        return $operation
            ->addAction(
                Operation::ACTION_BEFORE_SAVE,
                new Operation\Action\Compatible\SendEvent\WithCancel\Update(
                    'OnBeforeSmartInvoiceUpdate',
                    'CRM_SMART_INVOICE_UPDATE_CANCELED',
                ),
            );
    }
}