import { Runtime, Type, ajax as Ajax } from 'main.core';
import { EventEmitter, BaseEvent } from 'main.core.events';

import { Dialog } from 'ui.entity-selector';
import SearchEngine from './search/search-engine';
import SearchQuery from './search/search-query';

export class CustomDialog
	extends Dialog
{
	search(queryString: string): void
	{
		const query = Type.isStringFilled(queryString) ? queryString.trim() : '';

		const event = new BaseEvent({ data: { query } });
		this.emit('onBeforeSearch', event);
		if (event.isDefaultPrevented())
		{
			return;
		}

		if (!Type.isStringFilled(query))
		{
			this.selectFirstTab();
			if (this.getSearchTab())
			{
				this.getSearchTab().clearResults();
			}
		}
		else if (this.getSearchTab())
		{
			this.selectTab(this.getSearchTab().getId());

			this.getSearchTab().loadWithDebounce = Runtime.debounce(() => {
				this.load(this.getSearchTab().getLastSearchQuery());
			}, 500);

			this.getSearchTab().search(query);
		}

		this.emit('onSearch', { query });
	}

	/**
	 * Search in products list
	 * @param searchQuery
	 */
	load(searchQuery: SearchQuery): void
	{
		let searchTab = this.getSearchTab();

		if (searchQuery === undefined)
		{
			searchTab.getSearchLoader().hide();
			return;
		}

		if (!searchTab.shouldLoad(searchQuery))
		{
			return;
		}

		searchTab.addCacheQuery(searchQuery);

		searchTab.getStub().hide();
		searchTab.getSearchLoader().show();

		Ajax.runComponentAction('kitconsulting:crm.entity.extendedproduct.list', 'doSearch', {
			json: {
				dialog: searchTab.getDialog().getAjaxJson(),
				searchQuery: searchQuery.getAjaxJson()
			},
			onrequeststart: (xhr) => {
				searchTab.queryXhr = xhr;
			},
			getParameters: {
				context: searchTab.getDialog().getContext()
			}
		})
			.then(response => {
				searchTab.getSearchLoader().hide();

				if (!response || !response.data || !response.data.dialog || !response.data.dialog.items)
				{
					searchTab.removeCacheQuery(searchQuery);
					searchTab.toggleEmptyResult();
					this.emit('SearchTab:onLoad', { searchTab: this });

					return;
				}

				if (response.data.searchQuery && response.data.searchQuery.cacheable === false)
				{
					searchTab.removeCacheQuery(searchQuery);
				}

				if (Type.isArrayFilled(response.data.dialog.items))
				{
					const items = new Set();
					response.data.dialog.items.forEach((itemOptions: ItemOptions) => {
						delete itemOptions.tabs;
						delete itemOptions.children;

						const item = this.addItem(itemOptions);
						items.add(item);
					});

					const isTabEmpty = searchTab.isEmptyResult();

					const matchResults = SearchEngine.matchItems(
						Array.from(items.values()),
						searchTab.getLastSearchQuery()
					);
					searchTab.appendResults(matchResults);

					if (isTabEmpty && this.shouldFocusOnFirst())
					{
						this.focusOnFirstNode();
					}
				}

				searchTab.toggleEmptyResult();

				this.emit('SearchTab:onLoad', { searchTab: searchTab });
			})
			.catch((error) => {
				searchTab.removeCacheQuery(searchQuery);
				searchTab.getSearchLoader().hide();
				searchTab.toggleEmptyResult();

				console.error(error);
			});
	}
}