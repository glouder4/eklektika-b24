<?php
if (!defined('CRM_USE_CUSTOM_SERVICES')) die();
use Bitrix\Crm\Service;
use Bitrix\Crm\Service\Converter;
use Bitrix\Main\DI;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Crm\Service\Factory\Dynamic;
use Bitrix\Crm\Service\Factory;

if (\Bitrix\Main\Loader::includeModule('crm'))
{
	\Bitrix\Main\Loader::includeModule('kit.scripts');

    $container = new class extends Service\Container {
        public function getFactory(int $entityTypeId): ?Factory
        {
            if ($entityTypeId === \CCrmOwnerType::SmartInvoice)
            {
                $identifier = static::getIdentifierByClassName(static::$dynamicFactoriesClassName, [$entityTypeId]);
                $type = $this->getTypeByEntityTypeId($entityTypeId);
                $factoryClassName = \Kit\Scripts\DynamicSmartInvoice::class;
                $factory = new $factoryClassName($type);

                ServiceLocator::getInstance()->addInstance(
                    $identifier,
                    $factory
                );
                return $factory;
            }
            return parent::getFactory($entityTypeId);
        }
    };
    DI\ServiceLocator::getInstance()->addInstance('crm.service.container', $container);

    $smartType = Service\Container::getInstance()->getTypeByEntityTypeId(\Kit\Scripts\DynamicItem::SMART_PROCESS_ITEM);
    DI\ServiceLocator::getInstance()->addInstance(
        'crm.service.factory.dynamic.' . \Kit\Scripts\DynamicItem::SMART_PROCESS_ITEM,
        new \Kit\Scripts\DynamicItem($smartType)
    );

	DI\ServiceLocator::getInstance()->addInstance('crm.service.converter.ormObject', new \Kit\Scripts\CustomOrmObject);
}