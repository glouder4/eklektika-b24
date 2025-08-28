<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Bitrix\Main\UI\EntitySelector\EntityUsageTable;
use Bitrix\UI\EntitySelector\RecentItem;
use Bitrix\UI\EntitySelector\SearchQuery;

class CustomDialog
	extends Bitrix\UI\EntitySelector\Dialog
{
	public function doSearch(SearchQuery $searchQuery)
	{
		if (empty($searchQuery->getQueryWords())) return;

		$entities = [];
		foreach ($this->getEntities() as $entity)
		{
			$hasDynamicSearch =
				$entity->isSearchable() &&
				($entity->hasDynamicSearch() || $searchQuery->hasDynamicSearchEntity($entity->getId()))
			;

			if ($hasDynamicSearch)
			{
				$entities[] = $entity->getId();
			}
		}

		$this->fillGlobalRecentItems($entities);
		foreach ($entities as $entityId)
		{
			$entity = $this->getEntity($entityId);

			if ($entity->getId() == "product")
			{
				$entity->setProvider(new CustomProductProvider($entity->getOptions()));
			}
			$entity->getProvider()->doSearch($searchQuery, $this);
		}
	}

	private function fillGlobalRecentItems(array $entities)
	{
		if (empty($entities))
		{
			return;
		}

		$usages = $this->getGlobalUsages($entities);
		while ($usage = $usages->fetch())
		{
			$this->getGlobalRecentItems()->add(
				new RecentItem(
					[
						'id' => $usage['ITEM_ID'],
						'entityId' => $usage['ENTITY_ID'],
						'lastUseDate' => $usage['MAX_LAST_USE_DATE']->getTimestamp()
					]
				)
			);
		}
	}

	private function getGlobalUsages(array $entities, int $limit = 200)
	{
		$query = EntityUsageTable::query();
		$query->setSelect(['ENTITY_ID', 'ITEM_ID', 'MAX_LAST_USE_DATE']);
		$query->setGroup(['ENTITY_ID', 'ITEM_ID']);
		$query->where('USER_ID', $this->getCurrentUserId());
		$query->whereIn('ENTITY_ID', $entities);

		if ($this->getContext() !== null)
		{
			$query->whereNot('CONTEXT', $this->getContext());
		}

		$query->registerRuntimeField(new ExpressionField('MAX_LAST_USE_DATE', 'MAX(%s)', 'LAST_USE_DATE'));
		$query->setOrder(['MAX_LAST_USE_DATE' => 'desc']);
		$query->setLimit($limit);

		return $query->exec();
	}
}