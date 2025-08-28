<?php

namespace Kitconsulting\ProductApplicationsExtension;

use Bitrix;

class Helper
{
    protected Bitrix\Crm\Service\DynamicTypesMap $types;
    protected Bitrix\Crm\Model\Dynamic\Type $type;
    /** @var Bitrix\Crm\Model\EO_ItemCategory[] $categories */
    protected $categories;
    protected $stages;

    public function __construct()
    {
        Bitrix\Main\Loader::includeModule('crm');
        $this->types = Bitrix\Crm\Service\Container::getInstance()->getDynamicTypesMap()->load(['isLoadStages' => true, 'isLoadCategories' => true]);

        foreach ($this->types->getTypes() as $type) {
            if ($type->getTitle() === 'Реализация проекта') {
                $this->type = $type;
                $this->categories = $this->types->getCategories($type->getEntityTypeId());
                break;
            }
        }

        if ($this->type !== null) {
            $stages = [];

            foreach ($this->categories as $category) {
                foreach ($this->types->getStages($this->type->getEntityTypeId(), $category->getId()) as $stage) {
                    $stages[$stage->getStatusId()] = [
                        'name' => $stage->getName(),
                        'status_id' => $stage->getStatusId(),
                        'color' => $stage->getColor(),
                    ];
                }
            }
            $this->stages = $stages;
        }
    }

    public function getStages()
    {
        return $this->stages;
    }

    public function getCategories()
    {
        return $this->categories;
    }

    public function getJsonSerializableCategories()
    {
        $categories = [];

        foreach ($this->categories as $category) {
            $categories[$category->getId()] = [
                'id' => $category->getId(),
                'name' => $category->getName()
            ];
        }
        return $categories;
    }

    public function isReadyToShipStage($stageId)
    {
        return $this->stages[$stageId]['name'] === 'Товары готовы к отгрузке';
    }

    public function getType()
    {
        return $this->type;
    }

    public function getSmartProcessStage($entityId)
    {
        $result = Bitrix\Crm\Service\Container::getInstance()->getFactory($this->type->getEntityTypeId())->getItem($entityId);

        if ($result === null) return [];

        return $this->stages[$result->getStageId()];
    }

    public function getSmartProcessCategory($entityId)
    {
        $result = Bitrix\Crm\Service\Container::getInstance()->getFactory($this->type->getEntityTypeId())->getItem($entityId);

        if ($result === null) return [];

        return $this->categories[$result->getCategoryId()];
    }

    public function areSmartProcessItemsReadyToShip($entityIds)
    {
        $result = Bitrix\Crm\Service\Container::getInstance()->getFactory($this->type->getEntityTypeId())->getItems(
            [
                'select' => ['*'],
                'filter' => [
                    'ID' => $entityIds
                ]
            ]
        );

        if (empty($result)) return false;

        foreach ($result as $item) {
            if (!$this->isReadyToShipStage($item->getStageId())) return false;
        }

        return true;
    }
}