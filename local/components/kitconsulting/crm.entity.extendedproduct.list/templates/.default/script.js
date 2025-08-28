this.BX = this.BX || {};
this.BX.Crm = this.BX.Crm || {};
this.BX.Crm.Entity = this.BX.Crm.Entity || {};
(function (exports,ui_hint,catalog_storeSelector,ui_designTokens,ui_forms,fileinput,catalog_skuTree,main_core_collections,main_loader,ui_infoHelper,ui_entitySelector,catalog_barcodeScanner,ui_notification,ui_qrauthorization,spotlight,ui_tour,catalog_productCalculator,main_core,main_core_events,catalog_storeUse,currency_currencyCore,catalog_productModel,pull_client,main_popup) {
	'use strict';

	var _templateObject;

	var HintPopup = /*#__PURE__*/function () {
		function HintPopup(editor) {
			babelHelpers.classCallCheck(this, HintPopup);
			this.editor = editor;
		}

		babelHelpers.createClass(HintPopup, [{
			key: "load",
			value: function load(node, text) {
				if (!this.hintPopup) {
					this.hintPopup = new main_popup.Popup('ui-hint-popup-' + this.editor.getId(), null, {
						darkMode: true,
						closeIcon: true,
						animation: 'fading-slide'
					});
				}

				this.hintPopup.setBindElement(node);
				this.hintPopup.adjustPosition();
				this.hintPopup.setContent(main_core.Tag.render(_templateObject || (_templateObject = babelHelpers.taggedTemplateLiteral(["\n\t\t\t<div class='ui-hint-content'>", "</div>\n\t\t"])), main_core.Text.encode(text)));
				return this.hintPopup;
			}
		}, {
			key: "show",
			value: function show() {
				if (this.hintPopup) {
					this.hintPopup.show();
				}
			}
		}, {
			key: "close",
			value: function close() {
				if (this.hintPopup) {
					this.hintPopup.close();
				}
			}
		}]);
		return HintPopup;
	}();

	var _templateObject$1, _templateObject2, _templateObject3, _templateObject4, _templateObject5, _templateObject6;

	function _classPrivateMethodInitSpec(obj, privateSet) { _checkPrivateRedeclaration(obj, privateSet); privateSet.add(obj); }

	function _classPrivateFieldInitSpec(obj, privateMap, value) { _checkPrivateRedeclaration(obj, privateMap); privateMap.set(obj, value); }

	function _checkPrivateRedeclaration(obj, privateCollection) { if (privateCollection.has(obj)) { throw new TypeError("Cannot initialize the same private elements twice on an object"); } }

	function _classStaticPrivateMethodGet(receiver, classConstructor, method) { _classCheckPrivateStaticAccess(receiver, classConstructor); return method; }

	function _classCheckPrivateStaticAccess(receiver, classConstructor) { if (receiver !== classConstructor) { throw new TypeError("Private static access of wrong provenance"); } }

	function _classPrivateMethodGet(receiver, privateSet, fn) { if (!privateSet.has(receiver)) { throw new TypeError("attempted to get private field on non-instance"); } return fn; }

	var _model = /*#__PURE__*/new WeakMap();

	var _cache = /*#__PURE__*/new WeakMap();

	var _getDateNode = /*#__PURE__*/new WeakSet();

	var _getReserveInputNode = /*#__PURE__*/new WeakSet();

	var _layoutDateReservation = /*#__PURE__*/new WeakSet();

	var ReserveControl = /*#__PURE__*/function () {
		function ReserveControl(options) {
			babelHelpers.classCallCheck(this, ReserveControl);

			_classPrivateMethodInitSpec(this, _layoutDateReservation);

			_classPrivateMethodInitSpec(this, _getReserveInputNode);

			_classPrivateMethodInitSpec(this, _getDateNode);

			_classPrivateFieldInitSpec(this, _model, {
				writable: true,
				value: null
			});

			_classPrivateFieldInitSpec(this, _cache, {
				writable: true,
				value: new main_core.Cache.MemoryCache()
			});

			babelHelpers.defineProperty(this, "isReserveEqualProductQuantity", true);
			babelHelpers.classPrivateFieldSet(this, _model, options.model);
			this.inputFieldName = options.inputName || ReserveControl.INPUT_NAME;
			this.dateFieldName = options.dateFieldName || ReserveControl.DATE_NAME;
			this.quantityFieldName = options.quantityFieldName || ReserveControl.QUANTITY_NAME;
			this.deductedQuantityFieldName = options.deductedQuantityFieldName || ReserveControl.DEDUCTED_QUANTITY_NAME;
			this.defaultDateReservation = options.defaultDateReservation || null;
			this.isBlocked = options.isBlocked || false;
			this.isReserveEqualProductQuantity = options.isReserveEqualProductQuantity && (this.getReservedQuantity() === this.getQuantity() || babelHelpers.classPrivateFieldGet(this, _model).getOption('id') === null // is new row
			);
		}

		babelHelpers.createClass(ReserveControl, [{
			key: "renderTo",
			value: function renderTo(node) {
				node.appendChild(main_core.Tag.render(_templateObject$1 || (_templateObject$1 = babelHelpers.taggedTemplateLiteral(["<div>", "</div>"])), _classPrivateMethodGet(this, _getReserveInputNode, _getReserveInputNode2).call(this)));
				main_core.Event.bind(_classPrivateMethodGet(this, _getReserveInputNode, _getReserveInputNode2).call(this).querySelector('input'), 'input', main_core.Runtime.debounce(this.onReserveInputChange, 800, this));

				if (this.getReservedQuantity() > 0 || this.isReserveEqualProductQuantity) {
					_classPrivateMethodGet(this, _layoutDateReservation, _layoutDateReservation2).call(this, this.getDateReservation());
				}

				node.appendChild(main_core.Tag.render(_templateObject2 || (_templateObject2 = babelHelpers.taggedTemplateLiteral(["", ""])), _classPrivateMethodGet(this, _getDateNode, _getDateNode2).call(this)));
				main_core.Event.bind(_classPrivateMethodGet(this, _getDateNode, _getDateNode2).call(this), 'click', _classStaticPrivateMethodGet(ReserveControl, ReserveControl, _onDateInputClick).bind(this));
				main_core.Event.bind(_classPrivateMethodGet(this, _getDateNode, _getDateNode2).call(this).querySelector('input'), 'change', this.onDateChange.bind(this));
			}
		}, {
			key: "setReservedQuantity",
			value: function setReservedQuantity(value, isTriggerEvent) {
				var input = _classPrivateMethodGet(this, _getReserveInputNode, _getReserveInputNode2).call(this).querySelector('input');

				if (input) {
					input.value = value;

					if (isTriggerEvent) {
						input.dispatchEvent(new window.Event('input'));
					}
				}
			}
		}, {
			key: "getReservedQuantity",
			value: function getReservedQuantity() {
				return main_core.Text.toNumber(babelHelpers.classPrivateFieldGet(this, _model).getField(this.inputFieldName));
			}
		}, {
			key: "getDateReservation",
			value: function getDateReservation() {
				return babelHelpers.classPrivateFieldGet(this, _model).getField(this.dateFieldName) || null;
			}
		}, {
			key: "getQuantity",
			value: function getQuantity() {
				return main_core.Text.toNumber(babelHelpers.classPrivateFieldGet(this, _model).getField(this.quantityFieldName));
			}
		}, {
			key: "getDeductedQuantity",
			value: function getDeductedQuantity() {
				return main_core.Text.toNumber(babelHelpers.classPrivateFieldGet(this, _model).getField(this.deductedQuantityFieldName));
			}
		}, {
			key: "getAvailableQuantity",
			value: function getAvailableQuantity() {
				return this.getQuantity() - this.getDeductedQuantity();
			}
		}, {
			key: "onReserveInputChange",
			value: function onReserveInputChange(event) {
				var value = main_core.Text.toNumber(event.target.value);
				this.changeInputValue(value);
			}
		}, {
			key: "changeInputValue",
			value: function changeInputValue(value) {
				if (value > this.getAvailableQuantity()) {
					var errorNotifyId = 'reserveCountError';
					var notify = BX.UI.Notification.Center.getBalloonById(errorNotifyId);

					if (!notify) {
						var notificationOptions = {
							id: errorNotifyId,
							closeButton: true,
							autoHideDelay: 3000,
							content: main_core.Tag.render(_templateObject3 || (_templateObject3 = babelHelpers.taggedTemplateLiteral(["<div>", "</div>"])), main_core.Loc.getMessage('CRM_ENTITY_PL_IS_LESS_QUANTITY_WITH_DEDUCTED_THEN_RESERVED'))
						};
						notify = BX.UI.Notification.Center.notify(notificationOptions);
					}

					notify.show();
					value = this.getAvailableQuantity();
					this.setReservedQuantity(value);
				}

				if (value > 0) {
					if (this.getDateReservation() === null) {
						this.changeDateReservation(this.defaultDateReservation);
					} else {
						_classPrivateMethodGet(this, _layoutDateReservation, _layoutDateReservation2).call(this, babelHelpers.classPrivateFieldGet(this, _model).getField(this.dateFieldName));
					}
				} else if (value <= 0) {
					this.changeDateReservation();
				}

				babelHelpers.classPrivateFieldGet(this, _model).setField(this.inputFieldName, value);
				main_core_events.EventEmitter.emit(this, 'onChange', {
					NAME: this.inputFieldName,
					VALUE: value
				});
			}
		}, {
			key: "clearCache",
			value: function clearCache() {
				babelHelpers.classPrivateFieldGet(this, _cache)["delete"]('dateInput');
				babelHelpers.classPrivateFieldGet(this, _cache)["delete"]('reserveInput');
			}
		}, {
			key: "isInputDisabled",
			value: function isInputDisabled() {
				return this.isBlocked || babelHelpers.classPrivateFieldGet(this, _model).isSimple() || babelHelpers.classPrivateFieldGet(this, _model).isEmpty();
			}
		}, {
			key: "onDateChange",
			value: function onDateChange(event) {
				var value = event.target.value;
				var newDate = BX.parseDate(value);
				var current = new Date();
				current.setHours(0, 0, 0, 0);

				if (newDate >= current) {
					this.changeDateReservation(value);
				} else {
					var errorNotifyId = 'reserveDateError';
					var notify = BX.UI.Notification.Center.getBalloonById(errorNotifyId);

					if (!notify) {
						var notificationOptions = {
							id: errorNotifyId,
							closeButton: true,
							autoHideDelay: 3000,
							content: main_core.Tag.render(_templateObject4 || (_templateObject4 = babelHelpers.taggedTemplateLiteral(["<div>", "</div>"])), main_core.Loc.getMessage('CRM_ENTITY_PL_DATE_IN_PAST'))
						};
						notify = BX.UI.Notification.Center.notify(notificationOptions);
					}

					notify.show();
					this.changeDateReservation(this.defaultDateReservation);
				}
			}
		}, {
			key: "changeDateReservation",
			value: function changeDateReservation() {
				var date = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : null;
				main_core_events.EventEmitter.emit(this, 'onChange', {
					NAME: this.dateFieldName,
					VALUE: date
				});
				babelHelpers.classPrivateFieldGet(this, _model).setField(this.dateFieldName, date);

				_classPrivateMethodGet(this, _layoutDateReservation, _layoutDateReservation2).call(this, date);
			}
		}]);
		return ReserveControl;
	}();

	function _onDateInputClick(event) {
		BX.calendar({
			node: event.target,
			field: event.target.parentNode.querySelector('input'),
			bTime: false
		});
	}

	function _getDateNode2() {
		var _this = this;

		return babelHelpers.classPrivateFieldGet(this, _cache).remember('dateInput', function () {
			return main_core.Tag.render(_templateObject5 || (_templateObject5 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<div>\n\t\t\t\t\t<a class=\"crm-entity-product-list-reserve-date\"></a>\n\t\t\t\t\t<input\n\t\t\t\t\t\tdata-name=\"", "\"\n\t\t\t\t\t\tname=\"", "\"\n\t\t\t\t\t\ttype=\"hidden\"\n\t\t\t\t\t\tvalue=\"", "\"\n\t\t\t\t\t>\n\t\t\t\t</div>\n\t\t\t"])), _this.dateFieldName, _this.dateFieldName, _this.getDateReservation());
		});
	}

	function _getReserveInputNode2() {
		var _this2 = this;

		return babelHelpers.classPrivateFieldGet(this, _cache).remember('reserveInput', function () {
			var tag = main_core.Tag.render(_templateObject6 || (_templateObject6 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<div>\n\t\t\t\t\t<input type=\"text\"\n\t\t\t\t\t\tdata-name=\"", "\"\n\t\t\t\t\t\tname=\"", "\"\n\t\t\t\t\t\tclass=\"ui-ctl-element ui-ctl-textbox ", "\"\n\t\t\t\t\t\tautoComplete=\"off\"\n\t\t\t\t\t\tvalue=\"", "\"\n\t\t\t\t\t\tplaceholder=\"0\"\n\t\t\t\t\t\ttitle=\"", "\"\n\t\t\t\t\t\t", "\n\t\t\t\t\t/>\n\t\t\t\t</div>\n\t\t\t"])), _this2.inputFieldName, _this2.inputFieldName, _this2.isInputDisabled() ? "crm-entity-product-list-locked-field" : "", _this2.getReservedQuantity(), _this2.getReservedQuantity(), _this2.isInputDisabled() ? "disabled" : "");

			if (_this2.isBlocked) {
				tag.onclick = function () {
					return top.BX.UI.InfoHelper.show('limit_store_crm_integration');
				};
			}

			return tag;
		});
	}

	function _layoutDateReservation2() {
		var date = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : null;
		var linkText = date === null ? '' : main_core.Loc.getMessage('CRM_ENTITY_PL_RESERVED_DATE', {
			'#FINAL_RESERVATION_DATE#': date
		});

		var link = _classPrivateMethodGet(this, _getDateNode, _getDateNode2).call(this).querySelector('a');

		if (link) {
			link.innerText = linkText;
		}

		var hiddenInput = _classPrivateMethodGet(this, _getDateNode, _getDateNode2).call(this).querySelector('input');

		if (hiddenInput) {
			hiddenInput.value = date;
		}
	}

	babelHelpers.defineProperty(ReserveControl, "INPUT_NAME", 'INPUT_RESERVE_QUANTITY');
	babelHelpers.defineProperty(ReserveControl, "DATE_NAME", 'DATE_RESERVE_END');
	babelHelpers.defineProperty(ReserveControl, "QUANTITY_NAME", 'QUANTITY');
	babelHelpers.defineProperty(ReserveControl, "DEDUCTED_QUANTITY_NAME", 'DEDUCTED_QUANTITY');

	var SearchField = /*#__PURE__*/function () {
		function SearchField(fieldOptions) {
			babelHelpers.classCallCheck(this, SearchField);
			babelHelpers.defineProperty(this, "name", null);
			babelHelpers.defineProperty(this, "type", 'string');
			babelHelpers.defineProperty(this, "searchable", true);
			babelHelpers.defineProperty(this, "system", false);
			babelHelpers.defineProperty(this, "sort", null);
			var options = main_core.Type.isPlainObject(fieldOptions) ? fieldOptions : {};

			if (!main_core.Type.isStringFilled(options.name)) {
				throw new Error('EntitySelector.SearchField: "name" parameter is required.');
			}

			this.name = options.name;
			this.setType(options.type);
			this.setSystem(options.system);
			this.setSort(options.sort);
			this.setSearchable(options.searchable);
		}

		babelHelpers.createClass(SearchField, [{
			key: "getName",
			value: function getName() {
				return this.name;
			}
		}, {
			key: "getType",
			value: function getType() {
				return this.type;
			}
		}, {
			key: "setType",
			value: function setType(type) {
				if (main_core.Type.isStringFilled(type)) {
					this.type = type;
				}
			}
		}, {
			key: "getSort",
			value: function getSort() {
				return this.sort;
			}
		}, {
			key: "setSort",
			value: function setSort(sort) {
				if (main_core.Type.isNumber(sort) || sort === null) {
					this.sort = sort;
				}
			}
		}, {
			key: "setSearchable",
			value: function setSearchable(flag) {
				if (main_core.Type.isBoolean(flag)) {
					this.searchable = flag;
				}
			}
		}, {
			key: "isSearchable",
			value: function isSearchable() {
				return this.searchable;
			}
		}, {
			key: "setSystem",
			value: function setSystem(flag) {
				if (main_core.Type.isBoolean(flag)) {
					this.system = flag;
				}
			}
		}, {
			key: "isCustom",
			value: function isCustom() {
				return !this.isSystem();
			}
		}, {
			key: "isSystem",
			value: function isSystem() {
				return this.system;
			}
		}]);
		return SearchField;
	}();

	var MatchIndex = /*#__PURE__*/function () {
		function MatchIndex(field, queryWord, startIndex) {
			babelHelpers.classCallCheck(this, MatchIndex);
			babelHelpers.defineProperty(this, "field", null);
			babelHelpers.defineProperty(this, "queryWord", null);
			babelHelpers.defineProperty(this, "startIndex", null);
			babelHelpers.defineProperty(this, "endIndex", null);
			this.field = field;
			this.queryWord = queryWord;
			this.startIndex = startIndex;
			this.endIndex = startIndex + queryWord.length;
		}

		babelHelpers.createClass(MatchIndex, [{
			key: "getField",
			value: function getField() {
				return this.field;
			}
		}, {
			key: "getQueryWord",
			value: function getQueryWord() {
				return this.queryWord;
			}
		}, {
			key: "getStartIndex",
			value: function getStartIndex() {
				return this.startIndex;
			}
		}, {
			key: "getEndIndex",
			value: function getEndIndex() {
				return this.endIndex;
			}
		}]);
		return MatchIndex;
	}();

	var comparator = function comparator(a, b) {
		if (a.getStartIndex() === b.getStartIndex()) {
			return a.getEndIndex() > b.getEndIndex() ? -1 : 1;
		} else {
			return a.getStartIndex() > b.getStartIndex() ? 1 : -1;
		}
	};

	var MatchField = /*#__PURE__*/function () {
		function MatchField(field) {
			var indexes = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : [];
			babelHelpers.classCallCheck(this, MatchField);
			babelHelpers.defineProperty(this, "field", null);
			babelHelpers.defineProperty(this, "matchIndexes", new main_core_collections.OrderedArray(comparator));
			this.field = field;
			this.addIndexes(indexes);
		}

		babelHelpers.createClass(MatchField, [{
			key: "getField",
			value: function getField() {
				return this.field;
			}
		}, {
			key: "getMatches",
			value: function getMatches() {
				return this.matchIndexes;
			}
		}, {
			key: "addIndex",
			value: function addIndex(matchIndex) {
				this.matchIndexes.add(matchIndex);
			}
		}, {
			key: "addIndexes",
			value: function addIndexes(matchIndexes) {
				var _this = this;

				if (main_core.Type.isArray(matchIndexes)) {
					matchIndexes.forEach(function (matchIndex) {
						_this.addIndex(matchIndex);
					});
				}
			}
		}]);
		return MatchField;
	}();

	var MatchResult = /*#__PURE__*/function () {
		function MatchResult(item, queryWords) {
			var matchIndexes = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : [];
			babelHelpers.classCallCheck(this, MatchResult);
			babelHelpers.defineProperty(this, "item", null);
			babelHelpers.defineProperty(this, "queryWords", null);
			babelHelpers.defineProperty(this, "matchFields", new Map());
			babelHelpers.defineProperty(this, "sort", null);
			this.item = item;
			this.queryWords = queryWords;
			this.addIndexes(matchIndexes);
		}

		babelHelpers.createClass(MatchResult, [{
			key: "getItem",
			value: function getItem() {
				return this.item;
			}
		}, {
			key: "getQueryWords",
			value: function getQueryWords() {
				return this.queryWords;
			}
		}, {
			key: "getMatchFields",
			value: function getMatchFields() {
				return this.matchFields;
			}
		}, {
			key: "getSort",
			value: function getSort() {
				return this.sort;
			}
		}, {
			key: "addIndex",
			value: function addIndex(matchIndex) {
				var matchField = this.matchFields.get(matchIndex.getField());

				if (!matchField) {
					matchField = new MatchField(matchIndex.getField());
					this.matchFields.set(matchIndex.getField(), matchField);
					var fieldSort = matchIndex.getField().getSort();

					if (fieldSort !== null) {
						this.sort = this.sort === null ? fieldSort : Math.min(this.sort, fieldSort);
					}
				}

				matchField.addIndex(matchIndex);
			}
		}, {
			key: "addIndexes",
			value: function addIndexes(matchIndexes) {
				var _this = this;

				matchIndexes.forEach(function (matchIndex) {
					_this.addIndex(matchIndex);
				});
			}
		}]);
		return MatchResult;
	}();

	var collator = new Intl.Collator(undefined, {
		sensitivity: 'base'
	});

	var SearchEngine = /*#__PURE__*/function () {
		function SearchEngine() {
			babelHelpers.classCallCheck(this, SearchEngine);
		}

		babelHelpers.createClass(SearchEngine, null, [{
			key: "matchItems",
			value: function matchItems(items, searchQuery) {
				var matchResults = [];
				var queryWords = searchQuery.getQueryWords();
				var limit = searchQuery.getResultLimit();

				for (var i = 0; i < items.length; i++) {
					if (limit === 0) {
						break;
					}

					var item = items[i];

					if (item.isSelected() || !item.isSearchable() || item.isHidden() || !item.getEntity().isSearchable()) {
						continue;
					}

					var matchResult = this.matchItem(item, queryWords);

					if (matchResult) {
						matchResults.push(matchResult);
						limit--;
					}
				}

				return matchResults;
			}
		}, {
			key: "matchItem",
			value: function matchItem(item, queryWords) {
				var matches = [];

				for (var i = 0; i < queryWords.length; i++) {
					var queryWord = queryWords[i];
					var results = this.matchWord(item, queryWord); //const match = this.matchWord(item, queryWord);
					//if (match === null)

					if (results.length === 0) {
						return null;
					} else {
						matches = matches.concat(results); //matches.push(match);
					}
				}

				if (matches.length > 0) {
					return new MatchResult(item, queryWords, matches);
				} else {
					return null;
				}
			}
		}, {
			key: "matchWord",
			value: function matchWord(item, queryWord) {
				var searchIndexes = item.getSearchIndex().getIndexes();
				var matches = [];

				for (var i = 0; i < searchIndexes.length; i++) {
					var fieldIndex = searchIndexes[i];
					var indexes = fieldIndex.getIndexes();

					for (var j = 0; j < indexes.length; j++) {
						var index = indexes[j];
						var word = index.getWord().substring(0, queryWord.length);

						if (collator.compare(queryWord, word) === 0) {
							matches.push(new MatchIndex(fieldIndex.getField(), queryWord, index.getStartIndex())); //return new MatchIndex(field, queryWord, index[i][1]);
						}
					}

					if (matches.length > 0) {
						break;
					}
				}

				return matches; //return null;
			}
		}]);
		return SearchEngine;
	}();

	var SearchQuery = /*#__PURE__*/function () {
		function SearchQuery(query) {
			babelHelpers.classCallCheck(this, SearchQuery);
			babelHelpers.defineProperty(this, "queryWords", []);
			babelHelpers.defineProperty(this, "query", '');
			babelHelpers.defineProperty(this, "cacheable", true);
			babelHelpers.defineProperty(this, "dynamicSearchEntities", []);
			babelHelpers.defineProperty(this, "resultLimit", 100);
			this.query = query.trim().replace(/\s\s+/g, ' ');
			this.queryWords = main_core.Type.isStringFilled(this.query) ? this.query.split(' ') : [];
		}

		babelHelpers.createClass(SearchQuery, [{
			key: "getQueryWords",
			value: function getQueryWords() {
				return this.queryWords;
			}
		}, {
			key: "getQuery",
			value: function getQuery() {
				return this.query;
			}
		}, {
			key: "isEmpty",
			value: function isEmpty() {
				return this.getQueryWords().length === 0;
			}
		}, {
			key: "setCacheable",
			value: function setCacheable(flag) {
				if (main_core.Type.isBoolean(flag)) {
					this.cacheable = flag;
				}
			}
		}, {
			key: "isCacheable",
			value: function isCacheable() {
				return this.cacheable;
			}
		}, {
			key: "setResultLimit",
			value: function setResultLimit(limit) {
				if (main_core.Type.isNumber(limit) && limit >= 0) {
					this.resultLimit = limit;
				}
			}
		}, {
			key: "getResultLimit",
			value: function getResultLimit() {
				return this.resultLimit;
			}
		}, {
			key: "hasDynamicSearch",
			value: function hasDynamicSearch() {
				return this.getDynamicSearchEntities().length > 0;
			}
		}, {
			key: "hasDynamicSearchEntity",
			value: function hasDynamicSearchEntity(entityId) {
				return this.getDynamicSearchEntities().includes(entityId);
			}
		}, {
			key: "setDynamicSearchEntities",
			value: function setDynamicSearchEntities(entities) {
				var _this = this;

				if (main_core.Type.isArrayFilled(entities)) {
					entities.forEach(function (entityId) {
						if (main_core.Type.isStringFilled(entityId) && !_this.hasDynamicSearchEntity(entityId)) {
							_this.dynamicSearchEntities.push(entityId);
						}
					});
				}

				return this.dynamicSearchEntities;
			}
		}, {
			key: "getDynamicSearchEntities",
			value: function getDynamicSearchEntities() {
				return this.dynamicSearchEntities;
			}
		}, {
			key: "getAjaxJson",
			value: function getAjaxJson() {
				return this.toJSON();
			}
		}, {
			key: "toJSON",
			value: function toJSON() {
				return {
					queryWords: this.getQueryWords(),
					query: this.getQuery(),
					dynamicSearchEntities: this.getDynamicSearchEntities()
				};
			}
		}]);
		return SearchQuery;
	}();

	var CustomDialog = /*#__PURE__*/function (_Dialog) {
		babelHelpers.inherits(CustomDialog, _Dialog);

		function CustomDialog() {
			babelHelpers.classCallCheck(this, CustomDialog);
			return babelHelpers.possibleConstructorReturn(this, babelHelpers.getPrototypeOf(CustomDialog).apply(this, arguments));
		}

		babelHelpers.createClass(CustomDialog, [{
			key: "search",
			value: function search(queryString) {
				var _this = this;

				var query = main_core.Type.isStringFilled(queryString) ? queryString.trim() : '';
				var event = new main_core_events.BaseEvent({
					data: {
						query: query
					}
				});
				this.emit('onBeforeSearch', event);

				if (event.isDefaultPrevented()) {
					return;
				}

				if (!main_core.Type.isStringFilled(query)) {
					this.selectFirstTab();

					if (this.getSearchTab()) {
						this.getSearchTab().clearResults();
					}
				} else if (this.getSearchTab()) {
					this.selectTab(this.getSearchTab().getId());
					this.getSearchTab().loadWithDebounce = main_core.Runtime.debounce(function () {
						_this.load(_this.getSearchTab().getLastSearchQuery());
					}, 500);
					this.getSearchTab().search(query);
				}

				this.emit('onSearch', {
					query: query
				});
			}
			/**
			 * Search in products list
			 * @param searchQuery
			 */

		}, {
			key: "load",
			value: function load(searchQuery) {
				var _this2 = this;

				var searchTab = this.getSearchTab();

				if (searchQuery === undefined) {
					searchTab.getSearchLoader().hide();
					return;
				}

				if (!searchTab.shouldLoad(searchQuery)) {
					return;
				}

				searchTab.addCacheQuery(searchQuery);
				searchTab.getStub().hide();
				searchTab.getSearchLoader().show();
				main_core.ajax.runComponentAction('kitconsulting:crm.entity.extendedproduct.list', 'doSearch', {
					json: {
						dialog: searchTab.getDialog().getAjaxJson(),
						searchQuery: searchQuery.getAjaxJson()
					},
					onrequeststart: function onrequeststart(xhr) {
						searchTab.queryXhr = xhr;
					},
					getParameters: {
						context: searchTab.getDialog().getContext()
					}
				}).then(function (response) {
					searchTab.getSearchLoader().hide();

					if (!response || !response.data || !response.data.dialog || !response.data.dialog.items) {
						searchTab.removeCacheQuery(searchQuery);
						searchTab.toggleEmptyResult();

						_this2.emit('SearchTab:onLoad', {
							searchTab: _this2
						});

						return;
					}

					if (response.data.searchQuery && response.data.searchQuery.cacheable === false) {
						searchTab.removeCacheQuery(searchQuery);
					}

					if (main_core.Type.isArrayFilled(response.data.dialog.items)) {
						var items = new Set();
						response.data.dialog.items.forEach(function (itemOptions) {
							delete itemOptions.tabs;
							delete itemOptions.children;

							var item = _this2.addItem(itemOptions);

							items.add(item);
						});
						var isTabEmpty = searchTab.isEmptyResult();
						var matchResults = SearchEngine.matchItems(Array.from(items.values()), searchTab.getLastSearchQuery());
						searchTab.appendResults(matchResults);

						if (isTabEmpty && _this2.shouldFocusOnFirst()) {
							_this2.focusOnFirstNode();
						}
					}

					searchTab.toggleEmptyResult();

					_this2.emit('SearchTab:onLoad', {
						searchTab: searchTab
					});
				})["catch"](function (error) {
					searchTab.removeCacheQuery(searchQuery);
					searchTab.getSearchLoader().hide();
					searchTab.toggleEmptyResult();
					console.error(error);
				});
			}
		}]);
		return CustomDialog;
	}(ui_entitySelector.Dialog);

	var _templateObject$2, _templateObject2$1, _templateObject3$1, _templateObject4$1, _templateObject5$1, _templateObject6$1, _templateObject7, _templateObject8;

	var ProductSearchSelectorFooter = /*#__PURE__*/function (_DefaultFooter) {
		babelHelpers.inherits(ProductSearchSelectorFooter, _DefaultFooter);

		function ProductSearchSelectorFooter(dialog, options) {
			var _this;

			babelHelpers.classCallCheck(this, ProductSearchSelectorFooter);
			_this = babelHelpers.possibleConstructorReturn(this, babelHelpers.getPrototypeOf(ProductSearchSelectorFooter).call(this, dialog, options));
			babelHelpers.defineProperty(babelHelpers.assertThisInitialized(_this), "loader", null);
			_this.errorAdminHint = options.errorAdminHint || '';

			_this.getDialog().subscribe('onSearch', _this.handleOnSearch.bind(babelHelpers.assertThisInitialized(_this)));

			return _this;
		}

		babelHelpers.createClass(ProductSearchSelectorFooter, [{
			key: "getContent",
			value: function getContent() {
				var phrase = '';
				var isViewCreateButton = this.options.allowCreateItem === true || this.options.allowEditItem === false;

				if (this.isViewEditButton() && isViewCreateButton) {
					phrase = main_core.Tag.render(_templateObject$2 || (_templateObject$2 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<div>", "</div>\n\t\t\t"])), main_core.Loc.getMessage('CATALOG_SELECTOR_SEARCH_POPUP_FOOTER_1'));
					var createButton = phrase.querySelector('create-button');
					main_core.Dom.replace(createButton, this.getLabelContainer());
					var changeButton = phrase.querySelector('change-button');
					main_core.Dom.replace(changeButton, this.getSaveContainer());
				} else if (this.isViewEditButton()) {
					phrase = this.getSaveContainer();
				} else {
					phrase = this.getLabelContainer();
				}

				return main_core.Tag.render(_templateObject2$1 || (_templateObject2$1 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t<div class=\"ui-selector-search-footer-box\">\n\t\t\t\t", "\n\t\t\t\t", "\n\t\t\t\t", "\n\t\t\t</div>\n\t\t"])), phrase, this.getHintContainer(), this.getLoaderContainer());
			}
		}, {
			key: "isViewEditButton",
			value: function isViewEditButton() {
				return this.options.allowEditItem === true;
			}
		}, {
			key: "getLoader",
			value: function getLoader() {
				if (main_core.Type.isNil(this.loader)) {
					this.loader = new main_loader.Loader({
						target: this.getLoaderContainer(),
						size: 17,
						color: 'rgba(82, 92, 105, 0.9)'
					});
				}

				return this.loader;
			}
		}, {
			key: "showLoader",
			value: function showLoader() {
				void this.getLoader().show();
			}
		}, {
			key: "hideLoader",
			value: function hideLoader() {
				void this.getLoader().hide();
			}
		}, {
			key: "setLabel",
			value: function setLabel(label) {
				if (main_core.Type.isString(label)) {
					this.getLabelContainer().textContent = label;
				}
			}
		}, {
			key: "getLabelContainer",
			value: function getLabelContainer() {
				var _this2 = this;

				return this.cache.remember('label', function () {
					return main_core.Tag.render(_templateObject3$1 || (_templateObject3$1 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<span>\n\t\t\t\t\t<span onclick=\"", "\" class=\"ui-selector-footer-link  ui-selector-footer-link-add\">\n\t\t\t\t\t\t", "\n\t\t\t\t\t</span>\n\t\t\t\t\t", "\n\t\t\t\t</span>\n\t\t\t"])), _this2.handleClick.bind(_this2), _this2.getOption('creationLabel', main_core.Loc.getMessage('CATALOG_SELECTOR_SEARCH_POPUP_FOOTER_CREATE')), _this2.getQueryContainer());
				});
			}
		}, {
			key: "getQueryContainer",
			value: function getQueryContainer() {
				return this.cache.remember('name-container', function () {
					return main_core.Tag.render(_templateObject4$1 || (_templateObject4$1 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<span class=\"ui-selector-search-footer-query\"></span>\n\t\t\t"])));
				});
			}
		}, {
			key: "getSaveContainer",
			value: function getSaveContainer() {
				var _this3 = this;

				return this.cache.remember('save-container', function () {
					var className = "ui-selector-footer-link";
					var messageId = _this3.options.inputName === ProductSelector.INPUT_FIELD_BARCODE ? 'CATALOG_SELECTOR_SEARCH_POPUP_FOOTER_BARCODE_CHANGE' : 'CATALOG_SELECTOR_SEARCH_POPUP_FOOTER_CHANGE';
					return main_core.Tag.render(_templateObject5$1 || (_templateObject5$1 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t<span class=\"", "\" onclick=\"", "\">\n\t\t\t\t", "\n\t\t\t</span>\n\t\t"])), className, _this3.onClickSaveChanges.bind(_this3), main_core.Loc.getMessage(messageId));
				});
			}
		}, {
			key: "getLoaderContainer",
			value: function getLoaderContainer() {
				return this.cache.remember('loader', function () {
					return main_core.Tag.render(_templateObject6$1 || (_templateObject6$1 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<div class=\"ui-selector-search-footer-loader\"></div>\n\t\t\t"])));
				});
			}
		}, {
			key: "getHintContainer",
			value: function getHintContainer() {
				var _this4 = this;

				return this.cache.remember('hint', function () {
					var message = null;

					if (!_this4.options.allowEditItem && !_this4.options.allowCreateItem) {
						message = main_core.Loc.getMessage('CATALOG_SELECTOR_SEARCH_POPUP_DISABLED_FOOTER_ALL_HINT', {
							'#ADMIN_HINT#': _this4.errorAdminHint
						});
					} else if (!_this4.options.allowEditItem) {
						message = main_core.Loc.getMessage('CATALOG_SELECTOR_SEARCH_POPUP_DISABLED_FOOTER_EDIT_HINT', {
							'#ADMIN_HINT#': _this4.errorAdminHint
						});
					} else if (!_this4.options.allowCreateItem) {
						message = main_core.Loc.getMessage('CATALOG_SELECTOR_SEARCH_POPUP_DISABLED_FOOTER_ADD_HINT', {
							'#ADMIN_HINT#': _this4.errorAdminHint
						});
					}

					if (!message) {
						return null;
					}

					var hintNode = main_core.Tag.render(_templateObject7 || (_templateObject7 = babelHelpers.taggedTemplateLiteral(["<span class=\"ui-btn ui-btn-icon-lock ui-btn-link\"></span>"])));
					hintNode.dataset.hint = message;
					hintNode.dataset.hintNoIcon = true;
					BX.UI.Hint.initNode(hintNode);
					return main_core.Tag.render(_templateObject8 || (_templateObject8 = babelHelpers.taggedTemplateLiteral(["<div class=\"product-search-selector-disabled-footer-hint\">", "</div>"])), hintNode);
				});
			}
		}, {
			key: "onClickSaveChanges",
			value: function onClickSaveChanges() {
				if (!this.options.allowEditItem) {
					return;
				}

				var lastQuery = this.getDialog().getActiveTab().getLastSearchQuery();
				this.getDialog().emit('ChangeItem:onClick', {
					query: lastQuery.query
				});
				this.getDialog().clearSearch();
				this.getDialog().hide();
			}
		}, {
			key: "createItem",
			value: function createItem() {
				var _this5 = this;

				if (!this.options.allowCreateItem) {
					return;
				}

				var tagSelector = this.getDialog().getTagSelector();

				if (tagSelector && tagSelector.isLocked()) {
					return;
				}

				var finalize = function finalize() {
					_this5.hideLoader();

					if (_this5.getDialog().getTagSelector()) {
						_this5.getDialog().getTagSelector().unlock();

						_this5.getDialog().focusSearch();
					}
				};

				event.preventDefault();
				this.showLoader();

				if (tagSelector) {
					tagSelector.lock();
				}

				this.getDialog().emitAsync('Search:onItemCreateAsync', {
					searchQuery: this.getDialog().getActiveTab().getLastSearchQuery()
				}).then(function () {
					_this5.getTab().clearResults();

					_this5.getDialog().clearSearch();

					if (_this5.getDialog().getActiveTab() === _this5.getTab()) {
						_this5.getDialog().selectFirstTab();
					}

					finalize();
				})["catch"](function () {
					finalize();
				});
			}
		}, {
			key: "handleClick",
			value: function handleClick() {
				this.createItem();
			}
		}, {
			key: "handleOnSearch",
			value: function handleOnSearch(event) {
				var _event$getData = event.getData(),
					query = _event$getData.query;

				if (this.options.currentValue === query || query === '') {
					this.hide();
				} else {
					this.show();
				}

				this.getQueryContainer().textContent = " " + query;
			}
		}]);
		return ProductSearchSelectorFooter;
	}(ui_entitySelector.DefaultFooter);

	var _templateObject$3, _templateObject2$2, _templateObject3$2;

	var ProductCreationLimitedFooter = /*#__PURE__*/function (_DefaultFooter) {
		babelHelpers.inherits(ProductCreationLimitedFooter, _DefaultFooter);

		function ProductCreationLimitedFooter() {
			babelHelpers.classCallCheck(this, ProductCreationLimitedFooter);
			return babelHelpers.possibleConstructorReturn(this, babelHelpers.getPrototypeOf(ProductCreationLimitedFooter).apply(this, arguments));
		}

		babelHelpers.createClass(ProductCreationLimitedFooter, [{
			key: "getContent",
			value: function getContent() {
				var phrase = main_core.Tag.render(_templateObject$3 || (_templateObject$3 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t<div>", "</div>\n\t\t"])), main_core.Loc.getMessage('CATALOG_SELECTOR_LIMITED_PRODUCT_CREATION'));
				var infoButton = main_core.Tag.render(_templateObject2$2 || (_templateObject2$2 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t<a class=\"ui-btn ui-btn-sm ui-btn-primary ui-btn-hover ui-btn-round\">\n\t\t\t\t", "\n\t\t\t</a>\n\t\t"])), main_core.Loc.getMessage('CATALOG_SELECTOR_LICENSE_EXPLODE'));
				main_core.Event.bind(infoButton, 'click', function () {
					BX.UI.InfoHelper.show('limit_shop_products');
				});
				return main_core.Tag.render(_templateObject3$2 || (_templateObject3$2 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t<div class=\"ui-selector-search-footer-box\">\n\t\t\t\t<div class=\"ui-selector-search-footer-box\">\n\t\t\t\t\t<div class=\"tariff-lock\"></div>\n\t\t\t\t\t", "\n\t\t\t\t</div>\n\t\t\t\t<div>\n\t\t\t\t\t", "\n\t\t\t\t</div>\t\t\t\t\n\t\t\t</div>\n\t\t"])), phrase, infoButton);
			}
		}]);
		return ProductCreationLimitedFooter;
	}(ui_entitySelector.DefaultFooter);

	var SelectorErrorCode = /*#__PURE__*/function () {
		function SelectorErrorCode() {
			babelHelpers.classCallCheck(this, SelectorErrorCode);
		}

		babelHelpers.createClass(SelectorErrorCode, null, [{
			key: "getCodes",
			value: function getCodes() {
				return [SelectorErrorCode.NOT_SELECTED_PRODUCT, SelectorErrorCode.FAILED_PRODUCT];
			}
		}]);
		return SelectorErrorCode;
	}();
	babelHelpers.defineProperty(SelectorErrorCode, "NOT_SELECTED_PRODUCT", 'NOT_SELECTED_PRODUCT');
	babelHelpers.defineProperty(SelectorErrorCode, "FAILED_PRODUCT", 'FAILED_PRODUCT');

	var _templateObject$4, _templateObject2$3, _templateObject3$3, _templateObject4$2, _templateObject5$2, _templateObject6$2, _templateObject7$1, _templateObject8$1, _templateObject9, _templateObject10, _templateObject11, _templateObject12, _templateObject13;

	function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }

	function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys(Object(source), !0).forEach(function (key) { babelHelpers.defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }

	function _classPrivateMethodInitSpec$1(obj, privateSet) { _checkPrivateRedeclaration$1(obj, privateSet); privateSet.add(obj); }

	function _checkPrivateRedeclaration$1(obj, privateCollection) { if (privateCollection.has(obj)) { throw new TypeError("Cannot initialize the same private elements twice on an object"); } }

	function _classPrivateMethodGet$1(receiver, privateSet, fn) { if (!privateSet.has(receiver)) { throw new TypeError("attempted to get private field on non-instance"); } return fn; }

	var DialogMode = function DialogMode() {
		babelHelpers.classCallCheck(this, DialogMode);
	};

	babelHelpers.defineProperty(DialogMode, "SEARCHING", 'SEARCHING');
	babelHelpers.defineProperty(DialogMode, "SHOW_PRODUCT_ITEM", 'SHOW_PRODUCT_ITEM');
	babelHelpers.defineProperty(DialogMode, "SHOW_RECENT", 'SHOW_RECENT');

	var _showSelectedItem = /*#__PURE__*/new WeakSet();

	var _loadPreselectedItems = /*#__PURE__*/new WeakSet();

	var _showPreselectedItems = /*#__PURE__*/new WeakSet();

	var _searchItem = /*#__PURE__*/new WeakSet();

	var ProductSearchInput = /*#__PURE__*/function () {
		function ProductSearchInput(id) {
			var options = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
			babelHelpers.classCallCheck(this, ProductSearchInput);

			_classPrivateMethodInitSpec$1(this, _searchItem);

			_classPrivateMethodInitSpec$1(this, _showPreselectedItems);

			_classPrivateMethodInitSpec$1(this, _loadPreselectedItems);

			_classPrivateMethodInitSpec$1(this, _showSelectedItem);

			babelHelpers.defineProperty(this, "cache", new main_core.Cache.MemoryCache());
			this.id = id || main_core.Text.getRandom();
			this.selector = options.selector;

			if (!(this.selector instanceof ProductSelector)) {
				throw new Error('Product selector instance not found.');
			}

			this.model = options.model || {};
			this.isEnabledSearch = options.isSearchEnabled;
			this.isEnabledDetailLink = options.isEnabledDetailLink;
			this.inputName = options.inputName || ProductSelector.INPUT_FIELD_NAME;
			this.immutableFieldNames = [ProductSelector.INPUT_FIELD_BARCODE, ProductSelector.INPUT_FIELD_NAME];

			if (!this.immutableFieldNames.includes(this.inputName)) {
				this.immutableFieldNames.push(this.inputName);
			}

			this.ajaxInProcess = false;
			this.loadedSelectedItem = null;
			this.handleSearchInput = main_core.Runtime.debounce(this.searchInDialog, 500, this);
		}

		babelHelpers.createClass(ProductSearchInput, [{
			key: "destroy",
			value: function destroy() {}
		}, {
			key: "getId",
			value: function getId() {
				return this.id;
			}
		}, {
			key: "getSelectorType",
			value: function getSelectorType() {
				return ProductSelector.INPUT_FIELD_NAME;
			}
		}, {
			key: "getField",
			value: function getField(fieldName) {
				return this.model.getField(fieldName);
			}
		}, {
			key: "getValue",
			value: function getValue() {
				return this.getField(this.inputName);
			}
		}, {
			key: "getFilledValue",
			value: function getFilledValue() {
				return this.getNameInput().value || '';
			}
		}, {
			key: "isSearchEnabled",
			value: function isSearchEnabled() {
				return this.isEnabledSearch;
			}
		}, {
			key: "toggleIcon",
			value: function toggleIcon(icon, value) {
				if (main_core.Type.isDomNode(icon)) {
					main_core.Dom.style(icon, 'display', value);
				}
			}
		}, {
			key: "getNameBlock",
			value: function getNameBlock() {
				var _this = this;

				return this.cache.remember('nameBlock', function () {
					return main_core.Tag.render(_templateObject$4 || (_templateObject$4 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<div class=\"ui-ctl ui-ctl-textbox ui-ctl-w100\">\n\t\t\t\t\t", "\n\t\t\t\t\t", "\n\t\t\t\t\t", "\n\t\t\t\t</div>\n\t\t\t"])), _this.getNameTag(), _this.getNameInput(), _this.getHiddenNameInput());
				});
			}
		}, {
			key: "getNameTag",
			value: function getNameTag() {
				if (!this.model.isNew()) {
					return '';
				}

				return main_core.Tag.render(_templateObject2$3 || (_templateObject2$3 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t<div class=\"ui-ctl-tag\">", "</div>\n\t\t"])), main_core.Loc.getMessage('CATALOG_SELECTOR_NEW_TAG_TITLE'));
			}
		}, {
			key: "getNameInput",
			value: function getNameInput() {
				var _this2 = this;

				return this.cache.remember('nameInput', function () {
					var input = main_core.Tag.render(_templateObject3$3 || (_templateObject3$3 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<input type=\"text\"\n\t\t\t\t\tclass=\"ui-ctl-element ui-ctl-textbox\"\n\t\t\t\t\tautocomplete=\"off\"\n\t\t\t\t\tdata-name=\"", "\"\n\t\t\t\t\tvalue=\"", "\"\n\t\t\t\t\tplaceholder=\"", "\"\n\t\t\t\t\ttitle=\"", "\"\n\t\t\t\t\tonchange=\"", "\"\n\t\t\t\t>\n\t\t\t"])), main_core.Text.encode(_this2.inputName), main_core.Text.encode(_this2.getValue()), main_core.Text.encode(_this2.getPlaceholder()), main_core.Text.encode(_this2.getValue()), _this2.handleNameInputHiddenChange.bind(_this2));

					if (_this2.selector.getConfig('SELECTOR_INPUT_DISABLED', false)) {
						main_core.Dom.addClass(input, 'ui-ctl-disabled');
						input.setAttribute('disabled', true);
					}

					return input;
				});
			}
		}, {
			key: "getHiddenNameInput",
			value: function getHiddenNameInput() {
				var _this3 = this;

				return this.cache.remember('hiddenNameInput', function () {
					return main_core.Tag.render(_templateObject4$2 || (_templateObject4$2 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<input\n\t\t\t\t \ttype=\"hidden\"\n\t\t\t\t\tname=\"", "\"\n\t\t\t\t\tvalue=\"", "\"\n\t\t\t\t>\n\t\t\t"])), main_core.Text.encode(_this3.inputName), main_core.Text.encode(_this3.getValue()));
				});
			}
		}, {
			key: "handleNameInputHiddenChange",
			value: function handleNameInputHiddenChange(event) {
				this.getHiddenNameInput().value = event.target.value;
			}
		}, {
			key: "getClearIcon",
			value: function getClearIcon() {
				var _this4 = this;

				return this.cache.remember('closeIcon', function () {
					return main_core.Tag.render(_templateObject5$2 || (_templateObject5$2 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<button\n\t\t\t\t\tclass=\"ui-ctl-after ui-ctl-icon-clear\"\n\t\t\t\t\tonclick=\"", "\"\n\t\t\t\t></button>\n\t\t\t"])), _this4.handleClearIconClick.bind(_this4));
				});
			}
		}, {
			key: "getArrowIcon",
			value: function getArrowIcon() {
				var _this5 = this;

				return this.cache.remember('arrowIcon', function () {
					return main_core.Tag.render(_templateObject6$2 || (_templateObject6$2 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<a\n\t\t\t\t\thref=\"", "\"\n\t\t\t\t\ttarget=\"_blank\"\n\t\t\t\t\tclass=\"ui-ctl-after ui-ctl-icon-forward\"\n\t\t\t\t>\n\t\t\t"])), main_core.Text.encode(_this5.model.getDetailPath()));
				});
			}
		}, {
			key: "getSearchIcon",
			value: function getSearchIcon() {
				var _this6 = this;

				return this.cache.remember('searchIcon', function () {
					return main_core.Tag.render(_templateObject7$1 || (_templateObject7$1 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<button\n\t\t\t\t\tclass=\"ui-ctl-after ui-ctl-icon-search\"\n\t\t\t\t\tonclick=\"", "\"\n\t\t\t\t></button>\n\t\t\t"])), _this6.handleSearchIconClick.bind(_this6));
				});
			}
		}, {
			key: "layout",
			value: function layout() {
				this.clearInputCache();
				var block = main_core.Tag.render(_templateObject8$1 || (_templateObject8$1 = babelHelpers.taggedTemplateLiteral(["<div class=\"ui-ctl ui-ctl-w100 ui-ctl-after-icon\"></div>"])));
				this.toggleIcon(this.getClearIcon(), 'none');
				main_core.Dom.append(this.getClearIcon(), block);

				if (this.isSearchEnabled()) {
					if (this.selector.isProductSearchEnabled()) {
						this.initHasDialogItems();
					}

					this.toggleIcon(this.getSearchIcon(), main_core.Type.isStringFilled(this.getFilledValue()) ? 'none' : 'block');
					main_core.Dom.append(this.getSearchIcon(), block);
					main_core.Event.bind(this.getNameInput(), 'click', this.handleClickNameInput.bind(this));
					main_core.Event.bind(this.getNameInput(), 'input', this.handleSearchInput);
					main_core.Event.bind(this.getNameInput(), 'blur', this.handleNameInputBlur.bind(this));
					main_core.Event.bind(this.getNameInput(), 'keydown', this.handleNameInputKeyDown.bind(this));
					this.dialogMode = this.model.isCatalogExisted() ? DialogMode.SHOW_PRODUCT_ITEM : DialogMode.SHOW_RECENT;
				}

				if (this.showDetailLink() && main_core.Type.isStringFilled(this.getValue())) {
					this.toggleIcon(this.getClearIcon(), 'none');
					this.toggleIcon(this.getSearchIcon(), 'none');
					this.toggleIcon(this.getArrowIcon(), 'block');
					main_core.Dom.append(this.getArrowIcon(), block);
				}

				main_core.Event.bind(this.getNameInput(), 'click', this.handleIconsSwitchingOnNameInput.bind(this));
				main_core.Event.bind(this.getNameInput(), 'input', this.handleIconsSwitchingOnNameInput.bind(this));
				main_core.Event.bind(this.getNameInput(), 'change', this.handleNameInputChange.bind(this));
				main_core.Dom.append(this.getNameBlock(), block);
				return block;
			}
		}, {
			key: "showDetailLink",
			value: function showDetailLink() {
				return this.isEnabledDetailLink;
			}
		}, {
			key: "getDialog",
			value: function getDialog() {
				var _this7 = this;

				return this.cache.remember('dialog', function () {
					var _this7$getNameInput;

					var searchTypeId = ProductSearchInput.SEARCH_TYPE_ID;
					var entity = {
						id: searchTypeId,
						options: {
							iblockId: _this7.model.getIblockId(),
							basePriceId: _this7.model.getBasePriceId(),
							currency: _this7.model.getCurrency()
						},
						dynamicLoad: true,
						dynamicSearch: true
					};

					var restrictedProductTypes = _this7.selector.getConfig('RESTRICTED_PRODUCT_TYPES', null);

					if (!main_core.Type.isNil(restrictedProductTypes)) {
						entity.options.restrictedProductTypes = restrictedProductTypes;
					}

					var params = {
						id: _this7.id + '_' + searchTypeId,
						height: 300,
						width: Math.max((_this7$getNameInput = _this7.getNameInput()) === null || _this7$getNameInput === void 0 ? void 0 : _this7$getNameInput.offsetWidth, 565),
						context: 'catalog-products',
						targetNode: _this7.getNameInput(),
						enableSearch: false,
						multiple: false,
						dropdownMode: true,
						searchTabOptions: {
							stub: true,
							stubOptions: {
								title: main_core.Tag.message(_templateObject9 || (_templateObject9 = babelHelpers.taggedTemplateLiteral(["", ""])), 'CATALOG_SELECTOR_IS_EMPTY_TITLE'),
								subtitle: _this7.isAllowedCreateProduct() ? main_core.Tag.message(_templateObject10 || (_templateObject10 = babelHelpers.taggedTemplateLiteral(["", ""])), 'CATALOG_SELECTOR_IS_EMPTY_SUBTITLE') : '',
								arrow: true
							}
						},
						events: {
							'Item:onSelect': _this7.onProductSelect.bind(_this7),
							'Search:onItemCreateAsync': _this7.createProduct.bind(_this7),
							'ChangeItem:onClick': _this7.showChangeNotification.bind(_this7)
						},
						entities: [entity]
					};
					var settingsCollection = main_core.Extension.getSettings('catalog.product-selector');

					if (main_core.Type.isObject(settingsCollection.get('limitInfo'))) {
						params.footer = ProductCreationLimitedFooter;
					} else if (_this7.model && _this7.model.isCatalogExisted()) {
						params.footer = ProductSearchSelectorFooter;
						params.footerOptions = {
							inputName: _this7.inputName,
							allowEditItem: _this7.isAllowedEditProduct(),
							allowCreateItem: _this7.isAllowedCreateProduct(),
							errorAdminHint: settingsCollection.get('errorAdminHint'),
							creationLabel: main_core.Loc.getMessage('CATALOG_SELECTOR_SEARCH_POPUP_FOOTER_CREATE'),
							currentValue: _this7.getValue()
						};
					} else {
						params.searchOptions = {
							allowCreateItem: _this7.isAllowedCreateProduct()
						};
					}

					return new CustomDialog(params);
				});
			}
		}, {
			key: "initHasDialogItems",
			value: function initHasDialogItems() {
				var _this8 = this;

				if (!main_core.Type.isNil(this.selector.getConfig('EXIST_DIALOG_ITEMS'))) {
					return;
				}

				if (!this.selector.getModel().isEmpty()) {
					this.selector.setConfig('EXIST_DIALOG_ITEMS', true);
					return;
				} // is null, that not send ajax


				this.selector.setConfig('EXIST_DIALOG_ITEMS', false);
				var dialog = this.getDialog();

				if (dialog.hasDynamicLoad()) {
					_classPrivateMethodGet$1(this, _loadPreselectedItems, _loadPreselectedItems2).call(this);

					dialog.subscribeOnce('onLoad', function () {
						if (dialog.getPreselectedItems().length > 1) {
							_this8.selector.setConfig('EXIST_DIALOG_ITEMS', true);
						}
					});
				} else {
					this.selector.setConfig('EXIST_DIALOG_ITEMS', true);
				}
			}
		}, {
			key: "isAllowedCreateProduct",
			value: function isAllowedCreateProduct() {
				return this.selector.getConfig('IS_ALLOWED_CREATION_PRODUCT', true) && this.selector.checkProductAddRights();
			}
		}, {
			key: "isAllowedEditProduct",
			value: function isAllowedEditProduct() {
				return this.selector.checkProductEditRights();
			}
		}, {
			key: "handleNameInputKeyDown",
			value: function handleNameInputKeyDown(event) {
				var dialog = this.getDialog();

				if (event.key === 'Enter' && dialog.getActiveTab() === dialog.getSearchTab()) {
					// prevent a form submit
					event.stopPropagation();
					event.preventDefault();

					if (main_core.Browser.isMac() && event.metaKey || event.ctrlKey) {
						dialog.getSearchTab().getFooter().createItem();
					}
				}
			}
		}, {
			key: "handleIconsSwitchingOnNameInput",
			value: function handleIconsSwitchingOnNameInput(event) {
				this.toggleIcon(this.getArrowIcon(), 'none');

				if (main_core.Type.isStringFilled(event.target.value)) {
					this.toggleIcon(this.getClearIcon(), 'block');
					this.toggleIcon(this.getSearchIcon(), 'none');
				} else {
					this.toggleIcon(this.getClearIcon(), 'none');

					if (this.isSearchEnabled()) {
						this.toggleIcon(this.getSearchIcon(), 'block');
					}
				}
			}
		}, {
			key: "clearInputCache",
			value: function clearInputCache() {
				this.cache["delete"]('dialog');
				this.cache["delete"]('nameBlock');
				this.cache["delete"]('nameInput');
				this.cache["delete"]('hiddenNameInput');
			}
		}, {
			key: "handleClearIconClick",
			value: function handleClearIconClick(event) {
				this.selector.emit('onBeforeClear', {
					selectorId: this.selector.getId(),
					rowId: this.selector.getRowId()
				});
				this.loadedSelectedItem = null;

				if (this.selector.isProductSearchEnabled() && !this.model.isEmpty()) {
					this.selector.clearState();
					this.selector.clearLayout();
					this.selector.layout();
				} else {
					var newValue = '';
					this.toggleIcon(this.getClearIcon(), 'none');
					this.onChangeValue(newValue);
				}

				this.selector.focusName();
				this.selector.emit('onClear', {
					selectorId: this.selector.getId(),
					rowId: this.selector.getRowId()
				});
				event.stopPropagation();
				event.preventDefault();
			}
		}, {
			key: "handleNameInputChange",
			value: function handleNameInputChange(event) {
				var value = event.target.value;
				this.onChangeValue(value);
			}
		}, {
			key: "onChangeValue",
			value: function onChangeValue(value) {
				var fields = {};
				this.getNameInput().title = value;
				this.getNameInput().value = value;
				fields[this.inputName] = value;
				main_core_events.EventEmitter.emit('ProductSelector::onNameChange', {
					rowId: this.selector.getRowId(),
					fields: fields
				});

				if (!this.selector.isEnabledAutosave()) {
					return;
				}

				this.selector.getModel().setFields(fields);
				this.selector.getModel().save().then(function () {
					BX.UI.Notification.Center.notify({
						id: 'saving_field_notify_name',
						closeButton: false,
						content: main_core.Tag.render(_templateObject11 || (_templateObject11 = babelHelpers.taggedTemplateLiteral(["<div>", "</div>"])), main_core.Loc.getMessage('CATALOG_SELECTOR_SAVING_NOTIFICATION_NAME')),
						autoHide: true
					});
				});
			}
		}, {
			key: "focusName",
			value: function focusName() {
				var _this9 = this;

				requestAnimationFrame(function () {
					return _this9.getNameInput().focus();
				});
			}
		}, {
			key: "searchInDialog",
			value: function searchInDialog() {
				var searchQuery = this.getFilledValue().trim();

				if (searchQuery === '') {
					if (this.isHasDialogItems === false) {
						this.getDialog().hide();
						return;
					}

					this.loadedSelectedItem = null;

					_classPrivateMethodGet$1(this, _showPreselectedItems, _showPreselectedItems2).call(this);

					return;
				}

				this.dialogMode = DialogMode.SEARCHING;

				_classPrivateMethodGet$1(this, _searchItem, _searchItem2).call(this, searchQuery);

				this.isSearchingInProcess = true;
			}
		}, {
			key: "handleClickNameInput",
			value: function handleClickNameInput() {
				var dialog = this.getDialog();

				if (dialog.isOpen() || this.getFilledValue() === '' && this.isHasDialogItems === false) {
					dialog.hide();
					return;
				}

				this.showItems();
			}
		}, {
			key: "showItems",
			value: function showItems() {
				if (this.getFilledValue() === '') {
					_classPrivateMethodGet$1(this, _showPreselectedItems, _showPreselectedItems2).call(this);

					return;
				}

				if (!this.model.isCatalogExisted() || this.dialogMode !== DialogMode.SHOW_PRODUCT_ITEM) {
					this.searchInDialog();
					return;
				}

				_classPrivateMethodGet$1(this, _showSelectedItem, _showSelectedItem2).call(this);
			}
		}, {
			key: "handleNameInputBlur",
			value: function handleNameInputBlur(event) {
				var _this10 = this;

				// timeout to toggle clear icon handler while cursor is inside of name input
				setTimeout(function () {
					_this10.toggleIcon(_this10.getClearIcon(), 'none');

					if (_this10.showDetailLink() && main_core.Type.isStringFilled(_this10.getValue())) {
						if (_this10.isSearchEnabled()) {
							_this10.toggleIcon(_this10.getSearchIcon(), 'none');
						}

						_this10.toggleIcon(_this10.getArrowIcon(), 'block');
					} else {
						_this10.toggleIcon(_this10.getArrowIcon(), 'none');

						if (_this10.isSearchEnabled()) {
							_this10.toggleIcon(_this10.getSearchIcon(), main_core.Type.isStringFilled(_this10.getFilledValue()) ? 'none' : 'block');
						}
					}
				}, 200);

				if (this.isSearchEnabled() && this.selector.isEnabledEmptyProductError()) {
					setTimeout(function () {
						if (!_this10.selector.inProcess() && (_this10.model.isEmpty() || !main_core.Type.isStringFilled(_this10.getFilledValue()))) {
							_this10.model.getErrorCollection().setError(SelectorErrorCode.NOT_SELECTED_PRODUCT, _this10.selector.getEmptySelectErrorMessage());

							_this10.selector.layoutErrors();
						}
					}, 200);
				}
			}
		}, {
			key: "handleSearchIconClick",
			value: function handleSearchIconClick(event) {
				this.searchInDialog();
				this.focusName();
				event.stopPropagation();
				event.preventDefault();
			}
		}, {
			key: "getImmutableFieldNames",
			value: function getImmutableFieldNames() {
				return this.immutableFieldNames;
			}
		}, {
			key: "setInputValueOnProductSelect",
			value: function setInputValueOnProductSelect(item) {
				item.getDialog().getTargetNode().value = item.getTitle();
			}
		}, {
			key: "onProductSelect",
			value: function onProductSelect(event) {
				var _this11 = this;

				var item = event.getData().item;
				this.setInputValueOnProductSelect(item);
				this.toggleIcon(this.getSearchIcon(), 'none');
				this.clearErrors();

				if (this.selector) {
					var isNew = item.getCustomData().get('isNew');
					var immutableFields = [];
					this.getImmutableFieldNames().forEach(function (key) {
						if (!main_core.Type.isNil(item.getCustomData().get(key))) {
							_this11.model.setField(key, item.getCustomData().get(key));

							immutableFields.push(key);
						}
					});
					this.selector.onProductSelect(item.getId(), {
						isNew: isNew,
						immutableFields: immutableFields
					});
					this.selector.clearLayout();
					this.selector.layout();
				}

				this.dialogMode = DialogMode.SHOW_PRODUCT_ITEM;
				this.loadedSelectedItem = item;
				this.cache["delete"]('dialog');
			}
		}, {
			key: "clearErrors",
			value: function clearErrors() {
				var errors = this.model.getErrorCollection().getErrors();

				for (var code in errors) {
					if (ProductSelector.ErrorCodes.getCodes().includes(code)) {
						this.model.getErrorCollection().removeError(code);
					}
				}
			}
		}, {
			key: "createProductModelFromSearchQuery",
			value: function createProductModelFromSearchQuery(searchQuery) {
				var fields = _objectSpread({}, this.selector.getModel().getFields());

				fields[this.inputName] = searchQuery;
				return new catalog_productModel.ProductModel({
					isSimpleModel: true,
					isNew: true,
					currency: this.selector.options.currency,
					iblockId: this.selector.getModel().getIblockId(),
					basePriceId: this.selector.getModel().getBasePriceId(),
					fields: fields
				});
			}
		}, {
			key: "createProduct",
			value: function createProduct(event) {
				var _this12 = this;

				if (this.ajaxInProcess) {
					return;
				}

				this.ajaxInProcess = true;
				var dialog = event.getTarget();

				var _event$getData = event.getData(),
					searchQuery = _event$getData.searchQuery;

				var newProduct = this.createProductModelFromSearchQuery(searchQuery.getQuery());
				main_core_events.EventEmitter.emit(this.selector, 'onBeforeCreate', {
					model: newProduct
				});
				return new Promise(function (resolve, reject) {
					if (!_this12.checkCreationModel(newProduct)) {
						_this12.ajaxInProcess = false;
						dialog.hide();
						reject();
						return;
					}

					dialog.showLoader();
					newProduct.save().then(function (response) {
						dialog.hideLoader();
						var id = main_core.Text.toInteger(response.data.id);
						var item = dialog.addItem({
							id: id,
							entityId: ProductSearchInput.SEARCH_TYPE_ID,
							title: searchQuery.getQuery(),
							tabs: dialog.getRecentTab().getId(),
							customData: {
								isNew: true
							}
						});

						_this12.selector.getModel().setOption('isSimpleModel', false);

						_this12.selector.getModel().setOption('isNew', true);

						_this12.getImmutableFieldNames().forEach(function (name) {
							_this12.selector.getModel().setField(name, newProduct.getField(name));

							_this12.selector.getModel().setOption(name, newProduct.getField(name));
						});

						if (item) {
							item.select();
						}

						dialog.hide();

						_this12.cache["delete"]('dialog');

						_this12.ajaxInProcess = false;
						_this12.isHasDialogItems = true;
						resolve();
					})["catch"](function (errorResponse) {
						dialog.hideLoader();
						errorResponse.errors.forEach(function (error) {
							BX.UI.Notification.Center.notify({
								closeButton: true,
								content: main_core.Tag.render(_templateObject12 || (_templateObject12 = babelHelpers.taggedTemplateLiteral(["<div>", "</div>"])), error.message),
								autoHide: true
							});
						});
						_this12.ajaxInProcess = false;
						reject();
					});
				});
			}
		}, {
			key: "checkCreationModel",
			value: function checkCreationModel(creationModel) {
				return true;
			}
		}, {
			key: "showChangeNotification",
			value: function showChangeNotification(event) {
				var _this13 = this;

				var _event$getData2 = event.getData(),
					query = _event$getData2.query;

				var options = {
					title: main_core.Loc.getMessage('CATALOG_SELECTOR_SAVING_NOTIFICATION_' + this.selector.getType()),
					events: {
						onSave: function onSave() {
							if (_this13.selector) {
								_this13.selector.getModel().setField(_this13.inputName, query);

								_this13.selector.getModel().save([_this13.inputName])["catch"](function (errorResponse) {
									errorResponse.errors.forEach(function (error) {
										BX.UI.Notification.Center.notify({
											closeButton: true,
											content: main_core.Tag.render(_templateObject13 || (_templateObject13 = babelHelpers.taggedTemplateLiteral(["<div>", "</div>"])), error.message),
											autoHide: true
										});
									});
								});
							}
						}
					}
				};

				if (this.selector.getConfig('ROLLBACK_INPUT_AFTER_CANCEL', false)) {
					options.declineCancelTitle = main_core.Loc.getMessage('CATALOG_SELECTOR_SAVING_NOTIFICATION_CANCEL_TITLE');

					options.events.onCancel = function () {
						_this13.selector.clearLayout();

						_this13.selector.layout();
					};
				}

				this.selector.getModel().showSaveNotifier('nameChanger_' + this.selector.getId(), options);
			}
		}, {
			key: "getPlaceholder",
			value: function getPlaceholder() {
				return this.isSearchEnabled() && this.model.isEmpty() ? main_core.Loc.getMessage('CATALOG_SELECTOR_BEFORE_SEARCH_TITLE') : main_core.Loc.getMessage('CATALOG_SELECTOR_VIEW_NAME_TITLE');
			}
		}, {
			key: "removeSpotlight",
			value: function removeSpotlight() {}
		}, {
			key: "removeQrAuth",
			value: function removeQrAuth() {}
		}]);
		return ProductSearchInput;
	}();

	function _showSelectedItem2() {
		var _this14 = this,
			_dialog$getFooter2;

		if (!this.selector.isProductSearchEnabled()) {
			return;
		}

		var dialog = this.getDialog();
		dialog.removeItems();
		new Promise(function (resolve) {
			if (!main_core.Type.isNil(_this14.loadedSelectedItem)) {
				resolve();
				return;
			}

			dialog.showLoader();
			main_core.ajax.runAction('catalog.productSelector.getSkuSelectorItem', {
				json: {
					id: _this14.selector.getModel().getSkuId(),
					options: {
						iblockId: _this14.model.getIblockId(),
						basePriceId: _this14.model.getBasePriceId(),
						currency: _this14.model.getCurrency()
					}
				}
			}).then(function (response) {
				dialog.hideLoader();
				_this14.loadedSelectedItem = null;

				if (main_core.Type.isObject(response.data) && !dialog.isLoading()) {
					_this14.loadedSelectedItem = dialog.addItem(response.data);
				}

				resolve();
			});
		}).then(function () {
			if (!main_core.Type.isNil(_this14.loadedSelectedItem)) {
				var _dialog$getFooter;

				dialog.setPreselectedItems([_this14.selector.getModel().getSkuId()]);
				dialog.getRecentTab().getRootNode().addItem(_this14.loadedSelectedItem);
				dialog.selectFirstTab();
				(_dialog$getFooter = dialog.getFooter()) === null || _dialog$getFooter === void 0 ? void 0 : _dialog$getFooter.hide();
			} else {
				_this14.searchInDialog();
			}
		});
		dialog.getPopup().show();
		(_dialog$getFooter2 = dialog.getFooter()) === null || _dialog$getFooter2 === void 0 ? void 0 : _dialog$getFooter2.hide();
	}

	function _loadPreselectedItems2() {
		var dialog = this.getDialog();

		if (dialog.isLoading()) {
			return;
		}

		if (this.loadedSelectedItem) {
			dialog.removeItems();
			dialog.loadState = 'UNSENT';
			this.loadedSelectedItem = null;
		}

		dialog.load();
	}

	function _showPreselectedItems2() {
		var _dialog$getFooter3;

		if (!this.selector.isProductSearchEnabled()) {
			return;
		}

		this.dialogMode = DialogMode.SHOW_RECENT;
		var dialog = this.getDialog();

		_classPrivateMethodGet$1(this, _loadPreselectedItems, _loadPreselectedItems2).call(this);

		dialog.selectFirstTab();
		(_dialog$getFooter3 = dialog.getFooter()) === null || _dialog$getFooter3 === void 0 ? void 0 : _dialog$getFooter3.hide();
		dialog.show();
	}

	function _searchItem2() {
		var searchQuery = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : '';

		if (!this.selector.isProductSearchEnabled()) {
			return;
		}

		var dialog = this.getDialog();
		dialog.getPopup().show();
		dialog.search(searchQuery);
	}

	babelHelpers.defineProperty(ProductSearchInput, "SEARCH_TYPE_ID", 'product');

	var _templateObject$5;
	var ProductImageInput = /*#__PURE__*/function () {
		function ProductImageInput(id) {
			var _this$selector$getMod;

			var options = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
			babelHelpers.classCallCheck(this, ProductImageInput);
			this.id = id || main_core.Text.getRandom();
			this.selector = options.selector || null;

			if (!(this.selector instanceof ProductSelector)) {
				throw new Error('Product selector instance not found.');
			}

			this.config = options.config || {};

			if (!main_core.Type.isStringFilled((_this$selector$getMod = this.selector.getModel()) === null || _this$selector$getMod === void 0 ? void 0 : _this$selector$getMod.getImageCollection().getEditInput())) {
				this.restoreDefaultInputHtml();
			}

			this.enableSaving = options.enableSaving;
			this.uploaderFieldMap = {};
		}

		babelHelpers.createClass(ProductImageInput, [{
			key: "getId",
			value: function getId() {
				return this.id;
			}
		}, {
			key: "setId",
			value: function setId(id) {
				this.id = id;
			}
		}, {
			key: "setView",
			value: function setView(html) {
				var _this$selector$getMod2;

				(_this$selector$getMod2 = this.selector.getModel()) === null || _this$selector$getMod2 === void 0 ? void 0 : _this$selector$getMod2.getImageCollection().setPreview(html);
			}
		}, {
			key: "setInputHtml",
			value: function setInputHtml(html) {
				var _this$selector$getMod3;

				(_this$selector$getMod3 = this.selector.getModel()) === null || _this$selector$getMod3 === void 0 ? void 0 : _this$selector$getMod3.getImageCollection().setEditInput(html);
			}
		}, {
			key: "restoreDefaultInputHtml",
			value: function restoreDefaultInputHtml() {
				var _this$selector$getMod4, _this$selector$getMod5;

				var defaultInput = "\n\t\t\t<div class='ui-image-input-container ui-image-input-img--disabled'>\n\t\t\t\t<div class='adm-fileinput-wrapper '>\n\t\t\t\t\t<div class='adm-fileinput-area mode-pict adm-fileinput-drag-area'></div>\n\t\t\t\t</div>\n\t\t\t</div>\n";
				(_this$selector$getMod4 = this.selector.getModel()) === null || _this$selector$getMod4 === void 0 ? void 0 : _this$selector$getMod4.getImageCollection().setEditInput(defaultInput);
				(_this$selector$getMod5 = this.selector.getModel()) === null || _this$selector$getMod5 === void 0 ? void 0 : _this$selector$getMod5.getImageCollection().setPreview(defaultInput);
			}
		}, {
			key: "isViewMode",
			value: function isViewMode() {
				return this.selector && (this.selector.isViewMode() || !this.selector.model.isSaveable());
			}
		}, {
			key: "isEnabledLiveSaving",
			value: function isEnabledLiveSaving() {
				return this.enableSaving;
			}
		}, {
			key: "layout",
			value: function layout() {
				var _this$selector$getMod6, _this$selector$getMod7, _this$selector$getMod8, _this$selector$getMod9;

				var imageContainer = main_core.Tag.render(_templateObject$5 || (_templateObject$5 = babelHelpers.taggedTemplateLiteral(["<div></div>"])));
				var html = this.isViewMode() ? (_this$selector$getMod6 = this.selector.getModel()) === null || _this$selector$getMod6 === void 0 ? void 0 : (_this$selector$getMod7 = _this$selector$getMod6.getImageCollection()) === null || _this$selector$getMod7 === void 0 ? void 0 : _this$selector$getMod7.getPreview() : (_this$selector$getMod8 = this.selector.getModel()) === null || _this$selector$getMod8 === void 0 ? void 0 : (_this$selector$getMod9 = _this$selector$getMod8.getImageCollection()) === null || _this$selector$getMod9 === void 0 ? void 0 : _this$selector$getMod9.getEditInput();
				main_core.Runtime.html(imageContainer, html);
				return imageContainer;
			}
		}]);
		return ProductImageInput;
	}();

	var _templateObject$6, _templateObject2$4, _templateObject3$4, _templateObject4$3, _templateObject5$3;
	var BarcodeSearchSelectorFooter = /*#__PURE__*/function (_ProductSearchSelecto) {
		babelHelpers.inherits(BarcodeSearchSelectorFooter, _ProductSearchSelecto);

		function BarcodeSearchSelectorFooter(id) {
			var _this;

			var options = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
			babelHelpers.classCallCheck(this, BarcodeSearchSelectorFooter);
			_this = babelHelpers.possibleConstructorReturn(this, babelHelpers.getPrototypeOf(BarcodeSearchSelectorFooter).call(this, id, options));
			_this.isEmptyBarcode = options.isEmptyBarcode;

			_this.getDialog().subscribe('SearchTab:onLoad', _this.handleOnSearchLoad.bind(babelHelpers.assertThisInitialized(_this)));

			return _this;
		}

		babelHelpers.createClass(BarcodeSearchSelectorFooter, [{
			key: "getContent",
			value: function getContent() {
				this.barcodeContent = babelHelpers.get(babelHelpers.getPrototypeOf(BarcodeSearchSelectorFooter.prototype), "getContent", this).call(this);
				this.scannerContent = this.getScannerContent();
				main_core.Dom.style(this.barcodeContent, 'display', 'none');
				return main_core.Tag.render(_templateObject$6 || (_templateObject$6 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t<div class=\"catalog-footers-container\">\n\t\t\t\t", "\n\t\t\t\t", "\n\t\t\t</div>\n\t\t"])), this.barcodeContent, this.scannerContent);
			}
		}, {
			key: "isViewEditButton",
			value: function isViewEditButton() {
				return !this.isEmptyBarcode && babelHelpers.get(babelHelpers.getPrototypeOf(BarcodeSearchSelectorFooter.prototype), "isViewEditButton", this).call(this);
			}
		}, {
			key: "getScannerContent",
			value: function getScannerContent() {
				var phrase = main_core.Tag.render(_templateObject2$4 || (_templateObject2$4 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t<div>", "</div>\n\t\t"])), main_core.Loc.getMessage('CATALOG_SELECTOR_SEARCH_POPUP_FOOTER_BARCODE'));
				var createButton = phrase.querySelector('create-button');
				main_core.Dom.replace(createButton, this.getScannerLabelContainer());
				return main_core.Tag.render(_templateObject3$4 || (_templateObject3$4 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t<div class=\"ui-selector-search-footer-box\">\n\t\t\t\t", "\n\t\t\t\t", "\n\t\t\t</div>\n\t\t"])), phrase, this.getLoaderContainer());
			}
		}, {
			key: "getScannerLabelContainer",
			value: function getScannerLabelContainer() {
				var _this2 = this;

				return this.cache.remember('scannerLabel', function () {
					return main_core.Tag.render(_templateObject4$3 || (_templateObject4$3 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<span onclick=\"", "\">\n\t\t\t\t\t<span class=\"ui-selector-footer-link ui-selector-footer-link-add footer-link--warehouse-barcode-icon\">\n\t\t\t\t\t\t", "\n\t\t\t\t\t</span>\n\t\t\t\t\t", "\n\t\t\t\t</span>\n\t\t\t"])), _this2.handleScannerClick.bind(_this2), main_core.Loc.getMessage('CATALOG_SELECTOR_SEARCH_POPUP_FOOTER_BARCODE_START_SCAN_LABEL'), _this2.getScannerQueryContainer());
				});
			}
		}, {
			key: "getScannerQueryContainer",
			value: function getScannerQueryContainer() {
				return this.cache.remember('scanner_name-container', function () {
					return main_core.Tag.render(_templateObject5$3 || (_templateObject5$3 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<span class=\"ui-selector-search-footer-query\"></span>\n\t\t\t"])));
				});
			}
		}, {
			key: "handleScannerClick",
			value: function handleScannerClick() {
				var _this$options;

				var inputEntity = (_this$options = this.options) === null || _this$options === void 0 ? void 0 : _this$options.inputEntity;

				if (inputEntity) {
					inputEntity.startMobileScanner();
				}
			}
		}, {
			key: "handleOnSearch",
			value: function handleOnSearch(event) {
				var _event$getData = event.getData(),
					query = _event$getData.query;

				if (!main_core.Type.isStringFilled(query)) {
					this.show();
					main_core.Dom.style(this.scannerContent, 'display', '');
					main_core.Dom.style(this.barcodeContent, 'display', 'none');
				} else if (this.options.currentValue === query) {
					this.hide();
				} else {
					this.show();
					main_core.Dom.style(this.barcodeContent, 'display', '');
					main_core.Dom.style(this.scannerContent, 'display', 'none');
				}

				this.getQueryContainer().textContent = " " + query;
				this.getScannerQueryContainer().textContent = " " + query;
			}
		}, {
			key: "handleOnSearchLoad",
			value: function handleOnSearchLoad(event) {
				var _this3 = this;

				var _event$getData2 = event.getData(),
					searchTab = _event$getData2.searchTab;

				this.getDialog().getItems().forEach(function (item) {
					if (item.getCustomData().get('BARCODE') === searchTab.getLastSearchQuery().getQuery()) {
						_this3.hide();
					}
				});
			}
		}]);
		return BarcodeSearchSelectorFooter;
	}(ProductSearchSelectorFooter);

	var _templateObject$7, _templateObject2$5, _templateObject3$5, _templateObject4$4, _templateObject5$4, _templateObject6$3, _templateObject7$2, _templateObject8$2, _templateObject9$1, _templateObject10$1;

	function ownKeys$1(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }

	function _objectSpread$1(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys$1(Object(source), !0).forEach(function (key) { babelHelpers.defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys$1(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }
	var BarcodeSearchInput = /*#__PURE__*/function (_ProductSearchInput) {
		babelHelpers.inherits(BarcodeSearchInput, _ProductSearchInput);

		function BarcodeSearchInput(id) {
			var _this;

			var options = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
			babelHelpers.classCallCheck(this, BarcodeSearchInput);
			_this = babelHelpers.possibleConstructorReturn(this, babelHelpers.getPrototypeOf(BarcodeSearchInput).call(this, id, options));
			babelHelpers.defineProperty(babelHelpers.assertThisInitialized(_this), "onFocusHandler", _this.handleFocusEvent.bind(babelHelpers.assertThisInitialized(_this)));
			babelHelpers.defineProperty(babelHelpers.assertThisInitialized(_this), "onBlurHandler", _this.handleBlurEvent.bind(babelHelpers.assertThisInitialized(_this)));
			_this.focused = false;
			_this.settingsCollection = main_core.Extension.getSettings('catalog.product-selector');
			_this.isInstalledMobileApp = _this.selector.getConfig('IS_INSTALLED_MOBILE_APP') || _this.settingsCollection.get('isInstallMobileApp');

			if (!_this.settingsCollection.get('isEnabledQrAuth') && _this.selector.getConfig('ENABLE_BARCODE_QR_AUTH', true)) {
				_this.qrAuth = new ui_qrauthorization.QrAuthorization();

				_this.qrAuth.createQrCodeImage();
			}

			return _this;
		}

		babelHelpers.createClass(BarcodeSearchInput, [{
			key: "destroy",
			value: function destroy() {
				main_core.Event.unbind(this.getNameInput(), 'focus', this.onFocusHandler);
				main_core.Event.unbind(this.getNameInput(), 'blur', this.onBlurHandler);
			}
		}, {
			key: "handleFocusEvent",
			value: function handleFocusEvent() {
				this.focused = true;
			}
		}, {
			key: "handleBlurEvent",
			value: function handleBlurEvent() {
				this.focused = false;
			}
		}, {
			key: "isSearchEnabled",
			value: function isSearchEnabled() {
				return true;
			}
		}, {
			key: "showDetailLink",
			value: function showDetailLink() {
				return false;
			}
		}, {
			key: "getNameBlock",
			value: function getNameBlock() {
				var _this2 = this;

				return this.cache.remember('nameBlock', function () {
					return main_core.Tag.render(_templateObject$7 || (_templateObject$7 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<div class=\"ui-ctl ui-ctl-textbox ui-ctl-w100\">\n\t\t\t\t\t", "\n\t\t\t\t\t", "\n\t\t\t\t</div>\n\t\t\t"])), _this2.getNameInput(), _this2.getHiddenNameInput());
				});
			}
		}, {
			key: "getDialog",
			value: function getDialog() {
				var _this3 = this;

				return this.cache.remember('dialog', function () {
					var _this3$getNameInput;

					var entity = {
						id: BarcodeSearchInput.SEARCH_TYPE_ID,
						options: {
							iblockId: _this3.model.getIblockId(),
							basePriceId: _this3.model.getBasePriceId(),
							currency: _this3.model.getCurrency()
						},
						dynamicLoad: true,
						dynamicSearch: true,
						searchFields: [{
							name: 'title',
							type: 'string',
							system: true,
							searchable: false
						}]
					};

					var restrictedProductTypes = _this3.selector.getConfig('RESTRICTED_PRODUCT_TYPES', null);

					if (!main_core.Type.isNil(restrictedProductTypes)) {
						entity.options.restrictedProductTypes = restrictedProductTypes;
					}

					var params = {
						id: _this3.id + '_' + BarcodeSearchInput.SEARCH_TYPE_ID,
						height: 300,
						width: Math.max((_this3$getNameInput = _this3.getNameInput()) === null || _this3$getNameInput === void 0 ? void 0 : _this3$getNameInput.offsetWidth, 565),
						context: null,
						targetNode: _this3.getNameInput(),
						enableSearch: false,
						multiple: false,
						dropdownMode: true,
						searchTabOptions: {
							stub: true,
							stubOptions: {
								title: main_core.Tag.message(_templateObject2$5 || (_templateObject2$5 = babelHelpers.taggedTemplateLiteral(["", ""])), 'CATALOG_SELECTOR_IS_EMPTY_TITLE'),
								subtitle: _this3.isAllowedCreateProduct() ? main_core.Tag.message(_templateObject3$5 || (_templateObject3$5 = babelHelpers.taggedTemplateLiteral(["", ""])), 'CATALOG_SELECTOR_IS_EMPTY_SUBTITLE') : '',
								arrow: true
							}
						},
						events: {
							'Item:onSelect': _this3.onProductSelect.bind(_this3),
							'Search:onItemCreateAsync': _this3.createProduct.bind(_this3),
							'ChangeItem:onClick': _this3.showChangeNotification.bind(_this3)
						},
						entities: [entity]
					};

					if (_this3.model.getSkuId() && !main_core.Type.isStringFilled(_this3.model.getField(_this3.inputName))) {
						params.preselectedItems = [[BarcodeSearchInput.SEARCH_TYPE_ID, _this3.model.getSkuId()]];
					}

					if (main_core.Type.isObject(_this3.settingsCollection.get('limitInfo'))) {
						params.footer = ProductCreationLimitedFooter;
					} else {
						params.footer = BarcodeSearchSelectorFooter;
						params.footerOptions = {
							inputEntity: _this3,
							isEmptyBarcode: !_this3.model || !_this3.model.isCatalogExisted(),
							inputName: _this3.inputName,
							errorAdminHint: _this3.settingsCollection.get('errorAdminHint'),
							allowEditItem: _this3.isAllowedEditProduct(),
							allowCreateItem: _this3.isAllowedCreateProduct(),
							creationLabel: main_core.Loc.getMessage('CATALOG_SELECTOR_SEARCH_POPUP_FOOTER_CREATE_WITH_BARCODE'),
							currentValue: _this3.getValue(),
							searchOptions: {
								allowCreateItem: _this3.isAllowedCreateProduct(),
								footerOptions: {
									label: main_core.Loc.getMessage('CATALOG_SELECTOR_SEARCH_POPUP_FOOTER_CREATE_WITH_BARCODE')
								}
							}
						};
					}

					return new ui_entitySelector.Dialog(params);
				});
			}
		}, {
			key: "layoutMobileQrPopup",
			value: function layoutMobileQrPopup() {
				var _this4 = this;

				return this.cache.remember('qrMobilePopup', function () {
					var closeIcon = main_core.Tag.render(_templateObject4$4 || (_templateObject4$4 = babelHelpers.taggedTemplateLiteral(["<span class=\"popup-window-close-icon\"></span>"])));
					main_core.Event.bind(closeIcon, 'click', _this4.closeMobilePopup.bind(_this4));
					var sendButton = '';
					var helpButton = '';

					if (top.BX.Helper) {
						helpButton = main_core.Tag.render(_templateObject5$4 || (_templateObject5$4 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t\t<a class=\"product-selector-mobile-popup-link ui-btn ui-btn-light-border ui-btn-round\">\n\t\t\t\t\t\t", "\n\t\t\t\t\t</a>\n\t\t\t\t"])), main_core.Loc.getMessage('CATALOG_SELECTOR_MOBILE_POPUP_HELP_BUTTON'));
						main_core.Event.bind(helpButton, 'click', function () {
							top.BX.Helper.show("redirect=detail&code=14956818");
						});
						sendButton = main_core.Tag.render(_templateObject6$3 || (_templateObject6$3 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t\t<a class=\"product-selector-mobile-popup-link ui-btn ui-btn-link\">\n\t\t\t\t\t\t", "\n\t\t\t\t\t</a>\n\t\t\t\t"])), main_core.Loc.getMessage('CATALOG_SELECTOR_MOBILE_POPUP_SEND_PUSH_BUTTON'));
						main_core.Event.bind(sendButton, 'click', function () {
							top.BX.Helper.show("redirect=detail&code=15042444");
						});
					}

					return main_core.Tag.render(_templateObject7$2 || (_templateObject7$2 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<div data-role=\"mobile-popup\">\n\t\t\t\t\t<div class=\"product-selector-mobile-popup-overlay\"></div>\n\t\t\t\t\t<div class=\"product-selector-mobile-popup-content\">\n\t\t\t\t\t\t<div class=\"product-selector-mobile-popup-title\">", "</div>\n\t\t\t\t\t\t<div class=\"product-selector-mobile-popup-text\">", "</div>\n\t\t\t\t\t\t<div class=\"product-selector-mobile-popup-qr\">\n\t\t\t\t\t\t\t", "\n\t\t\t\t\t\t</div>\n\t\t\t\t\t\t<div class=\"product-selector-mobile-popup-link-container\">\n\t\t\t\t\t\t\t", "\n\t\t\t\t\t\t\t", "\n\t\t\t\t\t\t</div>\n\t\t\t\t\t\t", "\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t"])), main_core.Loc.getMessage('CATALOG_SELECTOR_MOBILE_POPUP_TITLE'), main_core.Loc.getMessage('CATALOG_SELECTOR_MOBILE_POPUP_INSTRUCTION'), _this4.qrAuth.getQrNode(), helpButton, sendButton, closeIcon);
				});
			}
		}, {
			key: "closeMobilePopup",
			value: function closeMobilePopup() {
				var _this5 = this;

				this.removeQrAuth();
				main_core.ajax.runAction('catalog.ProductSelector.isInstalledMobileApp', {
					json: {}
				}).then(function (result) {
					_this5.selector.emit('onBarcodeQrClose', {});

					if (result.data === true) {
						_this5.selector.emit('onBarcodeScannerInstallChecked', {});

						_this5.isInstalledMobileApp = true;
					}
				});
				main_core.userOptions.save('product-selector', 'barcodeQrAuth', 'showed', 'Y');
			}
		}, {
			key: "handleClickNameInput",
			value: function handleClickNameInput(event) {
				if (this.qrAuth && this.getDialog().getContainer()) {
					if (!main_core.Dom.hasClass(this.getDialog().getContainer(), 'qr-barcode-info')) {
						main_core.Dom.addClass(this.getDialog().getContainer(), 'qr-barcode-info');
					}

					if (this.getDialog().getContainer()) {
						main_core.Dom.append(this.layoutMobileQrPopup(), this.getDialog().getContainer());
					}
				}

				babelHelpers.get(babelHelpers.getPrototypeOf(BarcodeSearchInput.prototype), "handleClickNameInput", this).call(this, event);
			}
		}, {
			key: "showItems",
			value: function showItems() {
				this.searchInDialog();
			}
		}, {
			key: "onChangeValue",
			value: function onChangeValue(value) {
				var _this6 = this;

				var fields = {};
				this.getNameInput().title = value;
				this.getNameInput().value = value;
				fields[this.inputName] = value;
				main_core_events.EventEmitter.emit('ProductSelector::onBarcodeChange', {
					rowId: this.selector.getRowId(),
					fields: fields
				});
				this.selector.emit('onBarcodeChange', {
					value: value
				});

				if (this.selector.isEnabledAutosave()) {
					this.selector.getModel().setField(this.inputName, value);
					this.selector.getModel().showSaveNotifier('barcodeChanger_' + this.selector.getId(), {
						title: main_core.Loc.getMessage('CATALOG_SELECTOR_SAVING_NOTIFICATION_BARCODE'),
						disableCancel: true,
						events: {
							onSave: function onSave() {
								if (_this6.selector) {
									_this6.selector.getModel().save([_this6.inputName]);
								}
							}
						}
					});
				}
			}
		}, {
			key: "searchInDialog",
			value: function searchInDialog() {
				var searchQuery = this.getFilledValue().trim();
				this.searchByBarcode(searchQuery);
			}
		}, {
			key: "searchByBarcode",
			value: function searchByBarcode() {
				var searchQuery = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : '';

				if (!this.selector.isProductSearchEnabled()) {
					return;
				}

				var dialog = this.getDialog();

				if (!dialog) {
					return;
				}

				dialog.removeItems();

				if (!main_core.Type.isStringFilled(searchQuery)) {
					if (this.model && this.model.isCatalogExisted()) {
						dialog.setPreselectedItems([[BarcodeSearchInput.SEARCH_TYPE_ID, this.model.getSkuId()]]);
						dialog.loadState = 'UNSENT';
						dialog.load();
					}
				}

				dialog.show();
				dialog.search(searchQuery);
			}
		}, {
			key: "handleNameInputBlur",
			value: function handleNameInputBlur(event) {
				var _this7 = this;

				// timeout to toggle clear icon handler while cursor is inside of name input
				setTimeout(function () {
					_this7.toggleIcon(_this7.getClearIcon(), 'none');

					if (_this7.showDetailLink() && main_core.Type.isStringFilled(_this7.getValue())) {
						_this7.toggleIcon(_this7.getSearchIcon(), 'none');

						_this7.toggleIcon(_this7.getArrowIcon(), 'block');
					} else {
						_this7.toggleIcon(_this7.getArrowIcon(), 'none');

						_this7.toggleIcon(_this7.getSearchIcon(), main_core.Type.isStringFilled(_this7.getFilledValue()) ? 'none' : 'block');
					}
				}, 200);
			}
		}, {
			key: "setInputValueOnProductSelect",
			value: function setInputValueOnProductSelect(item) {
				item.getDialog().getTargetNode().value = item.getSubtitle();
			}
		}, {
			key: "getCreationProduct",
			value: function getCreationProduct(name) {
				var fields = _objectSpread$1({}, this.selector.getModel().getFields());

				fields[ProductSelector.INPUT_FIELD_NAME] = name;
				return new catalog_productModel.ProductModel({
					isSimpleModel: true,
					isNew: true,
					currency: this.selector.options.currency,
					iblockId: this.selector.getModel().getIblockId(),
					basePriceId: this.selector.getModel().getBasePriceId(),
					fields: fields
				});
			}
		}, {
			key: "createProductModelFromSearchQuery",
			value: function createProductModelFromSearchQuery(searchQuery) {
				var model = babelHelpers.get(babelHelpers.getPrototypeOf(BarcodeSearchInput.prototype), "createProductModelFromSearchQuery", this).call(this, searchQuery);
				model.setField(ProductSelector.INPUT_FIELD_NAME, main_core.Loc.getMessage('CATALOG_SELECTOR_NEW_BARCODE_PRODUCT_NAME'));
				model.setField(this.inputName, searchQuery);
				return model;
			}
		}, {
			key: "checkCreationModel",
			value: function checkCreationModel(creationModel) {
				if (!main_core.Type.isStringFilled(creationModel.getField(ProductSelector.INPUT_FIELD_NAME))) {
					this.model.getErrorCollection().setError(SelectorErrorCode.NOT_SELECTED_PRODUCT, main_core.Loc.getMessage('CATALOG_SELECTOR_EMPTY_TITLE'));
					return false;
				}

				return true;
			}
		}, {
			key: "getPlaceholder",
			value: function getPlaceholder() {
				return this.isSearchEnabled() && this.model.isEmpty() ? main_core.Loc.getMessage('CATALOG_SELECTOR_BEFORE_SEARCH_BARCODE_TITLE') : main_core.Loc.getMessage('CATALOG_SELECTOR_VIEW_BARCODE_TITLE');
			}
		}, {
			key: "handleClearIconClick",
			value: function handleClearIconClick(event) {
				this.toggleIcon(this.getClearIcon(), 'none');
				this.onChangeValue('');
				this.selector.focusName();
				event.stopPropagation();
				event.preventDefault();
			}
		}, {
			key: "startMobileScanner",
			value: function startMobileScanner(event) {
				if (this.isInstalledMobileApp) {
					this.sendMobilePush(event);
					return;
				}

				if (!this.qrAuth) {
					this.qrAuth = new ui_qrauthorization.QrAuthorization();
					this.qrAuth.createQrCodeImage();
				}

				if (this.getDialog().isOpen()) {
					this.getDialog().hide();
					this.getDialog().subscribeOnce('onHide', this.handleClickNameInput.bind(this));
				} else {
					this.handleClickNameInput(event);
				}
			}
		}, {
			key: "sendMobilePush",
			value: function sendMobilePush(event) {
				event === null || event === void 0 ? void 0 : event.preventDefault();
				this.getDialog().hide();
				this.getNameInput().focus();

				if (!this.selector.isEnabledMobileScanning()) {
					return;
				}

				var token = this.selector.getMobileScannerToken();
				catalog_barcodeScanner.BarcodeScanner.open(token);
				var repeatLink = main_core.Tag.render(_templateObject8$2 || (_templateObject8$2 = babelHelpers.taggedTemplateLiteral(["<span class='ui-notification-balloon-action'>", "</span>"])), main_core.Loc.getMessage('CATALOG_SELECTOR_SEND_PUSH_ON_SCANNER_NOTIFICATION_REPEAT'));
				main_core.Event.bind(repeatLink, 'click', this.sendMobilePush.bind(this));
				var content = main_core.Tag.render(_templateObject9$1 || (_templateObject9$1 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t<div>\n\t\t\t\t<span>", "</span>\n\t\t\t\t", "\n\t\t\t</div>\n\t\t"])), main_core.Loc.getMessage('CATALOG_SELECTOR_SEND_PUSH_ON_SCANNER_NOTIFICATION'), repeatLink);
				BX.UI.Notification.Center.notify({
					content: content,
					category: 'sending_push_barcode_scanner_notification',
					autoHideDelay: 5000
				});
			}
		}, {
			key: "getProductIdByBarcode",
			value: function getProductIdByBarcode(barcode) {
				return main_core.ajax.runAction('catalog.ProductSelector.getProductIdByBarcode', {
					json: {
						barcode: barcode
					}
				});
			}
		}, {
			key: "applyScannerData",
			value: function applyScannerData(barcode) {
				var _this8 = this;

				this.getProductIdByBarcode(barcode).then(function (response) {
					var productId = response === null || response === void 0 ? void 0 : response.data;

					if (productId) {
						_this8.selectScannedBarcodeProduct(productId);
					} else {
						_this8.searchByBarcode(barcode);
					}

					_this8.getNameInput().value = main_core.Text.encode(barcode);
				});
			}
		}, {
			key: "selectScannedBarcodeProduct",
			value: function selectScannedBarcodeProduct(productId) {
				this.toggleIcon(this.getSearchIcon(), 'none');
				this.clearErrors();

				if (this.selector) {
					this.selector.onProductSelect(productId, {
						isNew: false,
						immutableFields: []
					});
					this.selector.clearLayout();
					this.selector.layout();
				}

				this.cache["delete"]('dialog');
			}
		}, {
			key: "getBarcodeIcon",
			value: function getBarcodeIcon() {
				var _this9 = this;

				return this.cache.remember('barcodeIcon', function () {
					var barcodeIcon = main_core.Tag.render(_templateObject10$1 || (_templateObject10$1 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<button\tclass=\"ui-ctl-before warehouse-barcode-icon\" title=\"", "\"></button>\n\t\t\t"])), main_core.Loc.getMessage('CATALOG_SELECTOR_BARCODE_ICON_TITLE'));

					if (!_this9.settingsCollection.get('isShowedBarcodeSpotlightInfo') && _this9.settingsCollection.get('isAllowedShowBarcodeSpotlightInfo') && _this9.selector.getConfig('ENABLE_INFO_SPOTLIGHT', true)) {
						_this9.spotlight = new BX.SpotLight({
							id: 'selector_barcode_scanner_info',
							targetElement: barcodeIcon,
							autoSave: true,
							targetVertex: "middle-center",
							zIndex: 200
						});

						_this9.spotlight.show();

						main_core_events.EventEmitter.subscribe(_this9.spotlight, 'BX.SpotLight:onTargetEnter', function () {
							var guide = new ui_tour.Guide({
								steps: [{
									target: barcodeIcon,
									title: main_core.Loc.getMessage('CATALOG_SELECTOR_BARCODE_SCANNER_FIRST_TIME_HINT_TITLE'),
									text: main_core.Loc.getMessage('CATALOG_SELECTOR_BARCODE_SCANNER_FIRST_TIME_HINT_TEXT')
								}],
								onEvents: true
							});
							guide.getPopup().setAutoHide(true);
							guide.showNextStep();

							_this9.selector.setConfig('ENABLE_INFO_SPOTLIGHT', false);

							_this9.selector.emit('onSpotlightClose', {});
						});
					}

					main_core.Event.bind(barcodeIcon, 'click', function (event) {
						event.preventDefault();

						if (_this9.qrAuth) {
							_this9.handleClickNameInput(event);
						} else {
							_this9.startMobileScanner(event);
						}
					});
					return barcodeIcon;
				});
			}
		}, {
			key: "layout",
			value: function layout() {
				var block = babelHelpers.get(babelHelpers.getPrototypeOf(BarcodeSearchInput.prototype), "layout", this).call(this);
				main_core.Dom.append(this.getBarcodeIcon(), block);
				this.getNameInput().className += ' catalog-product-field-input-barcode';
				main_core.Event.bind(this.getNameInput(), 'focus', this.onFocusHandler);
				main_core.Event.bind(this.getNameInput(), 'blur', this.onBlurHandler);
				return block;
			}
		}, {
			key: "removeSpotlight",
			value: function removeSpotlight() {
				if (this.spotlight) {
					this.spotlight.close();
				}
			}
		}, {
			key: "removeQrAuth",
			value: function removeQrAuth() {
				var _this$getDialog$getCo;

				var mobilePopup = (_this$getDialog$getCo = this.getDialog().getContainer()) === null || _this$getDialog$getCo === void 0 ? void 0 : _this$getDialog$getCo.querySelector('[data-role="mobile-popup"]');

				if (mobilePopup) {
					main_core.Dom.remove(mobilePopup);

					if (main_core.Dom.hasClass(this.getDialog().getContainer(), 'qr-barcode-info')) {
						main_core.Dom.removeClass(this.getDialog().getContainer(), 'qr-barcode-info');
					}
				}

				this.qrAuth = null;
			}
		}]);
		return BarcodeSearchInput;
	}(ProductSearchInput);
	babelHelpers.defineProperty(BarcodeSearchInput, "SEARCH_TYPE_ID", 'barcode');

	var _templateObject$8, _templateObject2$6, _templateObject3$6, _templateObject4$5, _templateObject5$5, _templateObject6$4, _templateObject7$3, _templateObject8$3;

	function ownKeys$2(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }

	function _objectSpread$2(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys$2(Object(source), !0).forEach(function (key) { babelHelpers.defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys$2(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }

	function _classPrivateFieldInitSpec$1(obj, privateMap, value) { _checkPrivateRedeclaration$2(obj, privateMap); privateMap.set(obj, value); }

	function _checkPrivateRedeclaration$2(obj, privateCollection) { if (privateCollection.has(obj)) { throw new TypeError("Cannot initialize the same private elements twice on an object"); } }
	var instances = new Map();
	var iblockSkuTreeProperties = new Map();

	var _inAjaxProcess = /*#__PURE__*/new WeakMap();

	var ProductSelector = /*#__PURE__*/function (_EventEmitter) {
		babelHelpers.inherits(ProductSelector, _EventEmitter);
		babelHelpers.createClass(ProductSelector, null, [{
			key: "getById",
			value: function getById(id) {
				return instances.get(id) || null;
			}
		}]);

		function ProductSelector(id) {
			var _this;

			var options = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
			babelHelpers.classCallCheck(this, ProductSelector);
			_this = babelHelpers.possibleConstructorReturn(this, babelHelpers.getPrototypeOf(ProductSelector).call(this));

			_classPrivateFieldInitSpec$1(babelHelpers.assertThisInitialized(_this), _inAjaxProcess, {
				writable: true,
				value: false
			});

			babelHelpers.defineProperty(babelHelpers.assertThisInitialized(_this), "mode", ProductSelector.MODE_EDIT);
			babelHelpers.defineProperty(babelHelpers.assertThisInitialized(_this), "cache", new main_core.Cache.MemoryCache());
			babelHelpers.defineProperty(babelHelpers.assertThisInitialized(_this), "type", ProductSelector.INPUT_FIELD_NAME);
			babelHelpers.defineProperty(babelHelpers.assertThisInitialized(_this), "mobileScannerToken", null);
			babelHelpers.defineProperty(babelHelpers.assertThisInitialized(_this), "variationChangeHandler", _this.handleVariationChange.bind(babelHelpers.assertThisInitialized(_this)));
			babelHelpers.defineProperty(babelHelpers.assertThisInitialized(_this), "onSaveImageHandler", _this.onSaveImage.bind(babelHelpers.assertThisInitialized(_this)));
			babelHelpers.defineProperty(babelHelpers.assertThisInitialized(_this), "onChangeFieldsHandler", main_core.Runtime.debounce(_this.onChangeFields, 500, babelHelpers.assertThisInitialized(_this)));
			babelHelpers.defineProperty(babelHelpers.assertThisInitialized(_this), "onUploaderIsInitedHandler", _this.onUploaderIsInited.bind(babelHelpers.assertThisInitialized(_this)));
			babelHelpers.defineProperty(babelHelpers.assertThisInitialized(_this), "onNameChangeFieldHandler", main_core.Runtime.debounce(_this.onNameChange, 500, babelHelpers.assertThisInitialized(_this)));

			_this.setEventNamespace('BX.Catalog.ProductSelector');

			_this.id = id || main_core.Text.getRandom();
			options.inputFieldName = options.inputFieldName || ProductSelector.INPUT_FIELD_NAME;
			_this.options = options || {};
			_this.settings = main_core.Extension.getSettings('catalog.product-selector');
			_this.type = _this.options.type || ProductSelector.INPUT_FIELD_NAME;

			_this.setMode(options.mode);

			if (options.model && options.model instanceof catalog_productModel.ProductModel) {
				_this.model = options.model;
			} else {
				_this.model = catalog_productModel.ProductModel.getById(_this.id);
			}

			if (!(_this.model instanceof catalog_productModel.ProductModel)) {
				_this.model = new catalog_productModel.ProductModel({
					currency: options.currency,
					iblockId: main_core.Text.toNumber(options.iblockId),
					basePriceId: main_core.Text.toNumber(options.basePriceId),
					fields: options.fields,
					skuTree: options.skuTree,
					storeMap: options.storeMap
				});
			}

			_this.model.getImageCollection().setMorePhotoValues(options.morePhotoValues);

			if (!main_core.Type.isNil(_this.getConfig('DETAIL_PATH'))) {
				_this.model.setDetailPath(_this.getConfig('DETAIL_PATH'));
			}

			if (options.failedProduct) {
				_this.model.getErrorCollection().setError(SelectorErrorCode.FAILED_PRODUCT, '');
			}

			if (_this.isShowableEmptyProductError()) {
				_this.model.getErrorCollection().setError(SelectorErrorCode.NOT_SELECTED_PRODUCT, _this.getEmptySelectErrorMessage());
			}

			if (options.fileView) {
				_this.model.getImageCollection().setPreview(options.fileView);
			}

			if (options.fileInput) {
				_this.model.getImageCollection().setEditInput(options.fileInput);
			}

			_this.layout();

			if (options.skuTree) {
				_this.updateSkuTree(options.skuTree);
			}

			if (options.scannerToken) {
				_this.setMobileScannerToken(options.scannerToken);
			}

			_this.subscribeEvents();

			instances.set(_this.id, babelHelpers.assertThisInitialized(_this));
			return _this;
		}

		babelHelpers.createClass(ProductSelector, [{
			key: "setModel",
			value: function setModel(model) {
				this.model = model;
			}
		}, {
			key: "getModel",
			value: function getModel() {
				return this.model;
			}
		}, {
			key: "setMode",
			value: function setMode(mode) {
				if (!main_core.Type.isNil(mode)) {
					this.mode = mode === ProductSelector.MODE_VIEW ? ProductSelector.MODE_VIEW : ProductSelector.MODE_EDIT;
				}
			}
		}, {
			key: "isViewMode",
			value: function isViewMode() {
				return this.mode === ProductSelector.MODE_VIEW;
			}
		}, {
			key: "isSaveable",
			value: function isSaveable() {
				return !this.isViewMode() && this.model.isSaveable();
			}
		}, {
			key: "isEnabledAutosave",
			value: function isEnabledAutosave() {
				return this.isSaveable() && this.getConfig('ENABLE_AUTO_SAVE', false);
			}
		}, {
			key: "isEnabledMobileScanning",
			value: function isEnabledMobileScanning() {
				return !this.isViewMode() && this.getConfig('ENABLE_MOBILE_SCANNING', true);
			}
		}, {
			key: "getEmptySelectErrorMessage",
			value: function getEmptySelectErrorMessage() {
				return this.checkProductAddRights() ? main_core.Loc.getMessage('CATALOG_SELECTOR_SELECTED_PRODUCT_TITLE') : main_core.Loc.getMessage('CATALOG_SELECTOR_SELECT_PRODUCT_TITLE');
			}
		}, {
			key: "getMobileScannerToken",
			value: function getMobileScannerToken() {
				return this.mobileScannerToken || main_core.Text.getRandom(16);
			}
		}, {
			key: "checkProductViewRights",
			value: function checkProductViewRights() {
				var _this$model$checkAcce;

				return (_this$model$checkAcce = this.model.checkAccess(catalog_productModel.RightActionDictionary.ACTION_PRODUCT_VIEW)) !== null && _this$model$checkAcce !== void 0 ? _this$model$checkAcce : true;
			}
		}, {
			key: "checkProductEditRights",
			value: function checkProductEditRights() {
				var _this$model$checkAcce2;

				return (_this$model$checkAcce2 = this.model.checkAccess(catalog_productModel.RightActionDictionary.ACTION_PRODUCT_EDIT)) !== null && _this$model$checkAcce2 !== void 0 ? _this$model$checkAcce2 : false;
			}
		}, {
			key: "checkProductAddRights",
			value: function checkProductAddRights() {
				var _this$model$checkAcce3;

				return (_this$model$checkAcce3 = this.model.checkAccess(catalog_productModel.RightActionDictionary.ACTION_PRODUCT_ADD)) !== null && _this$model$checkAcce3 !== void 0 ? _this$model$checkAcce3 : false;
			}
		}, {
			key: "setMobileScannerToken",
			value: function setMobileScannerToken(token) {
				this.mobileScannerToken = token;
			}
		}, {
			key: "removeMobileScannerToken",
			value: function removeMobileScannerToken() {
				this.mobileScannerToken = null;
			}
		}, {
			key: "getId",
			value: function getId() {
				return this.id;
			}
		}, {
			key: "getType",
			value: function getType() {
				return this.type;
			}
		}, {
			key: "getConfig",
			value: function getConfig(name, defaultValue) {
				return BX.prop.get(this.options.config, name, defaultValue);
			}
		}, {
			key: "setConfig",
			value: function setConfig(name, value) {
				this.options.config[name] = value;
				return this;
			}
		}, {
			key: "getRowId",
			value: function getRowId() {
				return this.getConfig('ROW_ID');
			}
		}, {
			key: "getFileInput",
			value: function getFileInput() {
				if (!this.fileInput) {
					this.fileInput = new ProductImageInput(this.options.fileInputId, {
						selector: this,
						enableSaving: this.getConfig('ENABLE_IMAGE_CHANGE_SAVING', false)
					});
				}

				return this.fileInput;
			}
		}, {
			key: "isProductSearchEnabled",
			value: function isProductSearchEnabled() {
				return this.getConfig('ENABLE_SEARCH', false) && this.model.getIblockId() > 0 && this.checkProductViewRights();
			}
		}, {
			key: "isSkuTreeEnabled",
			value: function isSkuTreeEnabled() {
				return this.getConfig('ENABLE_SKU_TREE', true) !== false;
			}
		}, {
			key: "isImageFieldEnabled",
			value: function isImageFieldEnabled() {
				return this.getConfig('ENABLE_IMAGE_INPUT', true) !== false;
			}
		}, {
			key: "isShowableEmptyProductError",
			value: function isShowableEmptyProductError() {
				return this.isEnabledEmptyProductError() && (this.model.isEmpty() && this.model.isChanged() || this.model.isSimple());
			}
		}, {
			key: "isShowableErrors",
			value: function isShowableErrors() {
				return this.isEnabledEmptyProductError() || this.isEnabledEmptyImagesError();
			}
		}, {
			key: "isEnabledEmptyProductError",
			value: function isEnabledEmptyProductError() {
				return this.getConfig('ENABLE_EMPTY_PRODUCT_ERROR', false);
			}
		}, {
			key: "isEnabledEmptyImagesError",
			value: function isEnabledEmptyImagesError() {
				return this.getConfig('ENABLE_EMPTY_IMAGES_ERROR', false);
			}
		}, {
			key: "isEnabledChangesRendering",
			value: function isEnabledChangesRendering() {
				return this.getConfig('ENABLE_CHANGES_RENDERING', true);
			}
		}, {
			key: "isInputDetailLinkEnabled",
			value: function isInputDetailLinkEnabled() {
				return this.getConfig('ENABLE_INPUT_DETAIL_LINK', false) && main_core.Type.isStringFilled(this.model.getDetailPath()) && this.checkProductViewRights();
			}
		}, {
			key: "getWrapper",
			value: function getWrapper() {
				if (!this.wrapper) {
					this.wrapper = document.getElementById(this.id);
				}

				return this.wrapper;
			}
		}, {
			key: "renderTo",
			value: function renderTo(node) {
				this.clearLayout();
				this.wrapper = node;
				this.layout();
			}
		}, {
			key: "layout",
			value: function layout() {
				var _this2 = this;

				var wrapper = this.getWrapper();

				if (!wrapper) {
					return;
				}

				this.defineWrapperClass(wrapper);
				wrapper.innerHTML = '';
				var block = main_core.Tag.render(_templateObject$8 || (_templateObject$8 = babelHelpers.taggedTemplateLiteral(["<div class=\"catalog-product-field-inner\"></div>"])));
				main_core.Dom.append(this.layoutNameBlock(), block);

				if (this.getSkuTreeInstance()) {
					main_core.Dom.append(this.getSkuTreeInstance().layout(), block);
				}

				main_core.Dom.append(this.getErrorContainer(), block);

				if (!this.isViewMode()) {
					main_core.Dom.append(block, wrapper);
				}

				if (this.isImageFieldEnabled()) {
					if (!main_core.Reflection.getClass('BX.UI.ImageInput')) {
						main_core.ajax.runAction('catalog.productSelector.getFileInput', {
							json: {
								iblockId: this.getModel().getIblockId()
							}
						}).then(function () {
							_this2.layoutImage();
						});
					} else {
						this.layoutImage();
					}

					main_core.Dom.append(this.getImageContainer(), wrapper);
				}

				if (this.isViewMode()) {
					main_core.Dom.append(block, wrapper);
				}

				if (this.isViewMode()) {
					main_core.Dom.append(block, wrapper);
				}

				if (this.isShowableErrors) {
					this.layoutErrors();
				}

				this.subscribeToVariationChange();
			}
		}, {
			key: "focusName",
			value: function focusName() {
				if (this.searchInput) {
					this.searchInput.focusName();
				}

				return this;
			}
		}, {
			key: "getImageContainer",
			value: function getImageContainer() {
				return this.cache.remember('imageContainer', function () {
					return main_core.Tag.render(_templateObject2$6 || (_templateObject2$6 = babelHelpers.taggedTemplateLiteral(["<div class=\"catalog-product-img\"></div>"])));
				});
			}
		}, {
			key: "getErrorContainer",
			value: function getErrorContainer() {
				return this.cache.remember('errorContainer', function () {
					return main_core.Tag.render(_templateObject3$6 || (_templateObject3$6 = babelHelpers.taggedTemplateLiteral(["<div class=\"catalog-product-error\"></div>"])));
				});
			}
		}, {
			key: "layoutErrors",
			value: function layoutErrors() {
				this.getErrorContainer().innerHTML = '';
				this.clearImageErrorBorder();

				if (!this.model.getErrorCollection().hasErrors()) {
					return;
				}

				var errors = this.model.getErrorCollection().getErrors();

				for (var code in errors) {
					if (!ProductSelector.ErrorCodes.getCodes().includes(code)) {
						continue;
					}

					if (code === 'EMPTY_IMAGE') {
						this.setImageErrorBorder();
					} else {
						main_core.Dom.append(main_core.Tag.render(_templateObject4$5 || (_templateObject4$5 = babelHelpers.taggedTemplateLiteral(["<div class=\"catalog-product-error-item\">", "</div>"])), errors[code].text), this.getErrorContainer());

						if (this.searchInput) {
							main_core.Dom.addClass(this.searchInput.getNameBlock(), 'ui-ctl-danger');
						}
					}
				}
			}
		}, {
			key: "setImageErrorBorder",
			value: function setImageErrorBorder() {
				main_core.Dom.addClass(this.getImageContainer().querySelector('.adm-fileinput-area'), 'adm-fileinput-drag-area-error');
			}
		}, {
			key: "clearImageErrorBorder",
			value: function clearImageErrorBorder() {
				main_core.Dom.removeClass(this.getImageContainer().querySelector('.adm-fileinput-area'), 'adm-fileinput-drag-area-error');
			}
		}, {
			key: "onUploaderIsInited",
			value: function onUploaderIsInited() {
				if (this.isEnabledEmptyImagesError()) {
					requestAnimationFrame(this.layoutErrors.bind(this));
				}
			}
		}, {
			key: "layoutImage",
			value: function layoutImage() {
				this.getImageContainer().innerHTML = '';
				main_core.Dom.append(this.getFileInput().layout(), this.getImageContainer());
				this.refreshImageSelectorId = null;
			}
		}, {
			key: "clearState",
			value: function clearState() {
				this.getModel().initFields({
					ID: '',
					NAME: '',
					BARCODE: '',
					PRODUCT_ID: null,
					SKU_ID: null
				}).setOption('isNew', false);
				this.getFileInput().restoreDefaultInputHtml();
				this.getModel().clearSkuTree();
				this.skuTreeInstance = null;
				this.getModel().getStoreCollection().clear();
			}
		}, {
			key: "clearLayout",
			value: function clearLayout() {
				var wrapper = this.getWrapper();

				if (wrapper) {
					wrapper.innerHTML = '';
				}

				this.unsubscribeToVariationChange();
			}
		}, {
			key: "subscribeEvents",
			value: function subscribeEvents() {
				main_core_events.EventEmitter.subscribe('ProductList::onChangeFields', this.onChangeFieldsHandler);
				main_core_events.EventEmitter.subscribe('ProductSelector::onNameChange', this.onNameChangeFieldHandler);
				main_core_events.EventEmitter.subscribe('Catalog.ImageInput::save', this.onSaveImageHandler);
				main_core_events.EventEmitter.subscribe('onUploaderIsInited', this.onUploaderIsInitedHandler);
			}
		}, {
			key: "unsubscribeEvents",
			value: function unsubscribeEvents() {
				this.unsubscribeToVariationChange();
				main_core_events.EventEmitter.unsubscribe('Catalog.ImageInput::save', this.onSaveImageHandler);
				main_core_events.EventEmitter.unsubscribe('ProductList::onChangeFields', this.onChangeFieldsHandler);
				main_core_events.EventEmitter.unsubscribe('onUploaderIsInited', this.onUploaderIsInitedHandler);
				main_core_events.EventEmitter.unsubscribe('onUploaderIsInited', this.onUploaderIsInitedHandler);
				main_core_events.EventEmitter.unsubscribe('ProductSelector::onNameChange', this.onNameChangeFieldHandler);
			}
		}, {
			key: "defineWrapperClass",
			value: function defineWrapperClass(wrapper) {
				if (this.isViewMode()) {
					main_core.Dom.addClass(wrapper, 'catalog-product-view');
					main_core.Dom.removeClass(wrapper, 'catalog-product-edit');
				} else {
					main_core.Dom.addClass(wrapper, 'catalog-product-edit');
					main_core.Dom.removeClass(wrapper, 'catalog-product-view');
				}
			}
		}, {
			key: "getNameBlockView",
			value: function getNameBlockView() {
				var productName = main_core.Text.encode(this.model.getField('NAME'));
				var namePlaceholder = main_core.Loc.getMessage('CATALOG_SELECTOR_VIEW_NAME_TITLE');

				if (this.getModel().getDetailPath()) {
					return main_core.Tag.render(_templateObject5$5 || (_templateObject5$5 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<a href=\"", "\" title=\"", "\">", "</a>\n\t\t\t"])), this.getModel().getDetailPath(), namePlaceholder, productName);
				}

				return main_core.Tag.render(_templateObject6$4 || (_templateObject6$4 = babelHelpers.taggedTemplateLiteral(["<span title=\"", "\">", "</span>"])), namePlaceholder, productName);
			}
		}, {
			key: "getNameInputFilledValue",
			value: function getNameInputFilledValue() {
				if (this.searchInput) {
					return this.searchInput.getFilledValue();
				}

				return '';
			}
		}, {
			key: "layoutNameBlock",
			value: function layoutNameBlock() {
				var block = main_core.Tag.render(_templateObject7$3 || (_templateObject7$3 = babelHelpers.taggedTemplateLiteral(["<div class=\"catalog-product-field-input\"></div>"])));

				if (this.isViewMode()) {
					main_core.Dom.append(this.getNameBlockView(), block);
				} else {
					if (this.getType() === ProductSelector.INPUT_FIELD_BARCODE) {
						if (!this.searchInput) {
							this.searchInput = new BarcodeSearchInput(this.id, {
								selector: this,
								model: this.getModel(),
								inputName: this.options.inputFieldName
							});
						}
					} else {
						this.searchInput = new ProductSearchInput(this.id, {
							selector: this,
							model: this.getModel(),
							inputName: this.options.inputFieldName,
							isSearchEnabled: this.isProductSearchEnabled(),
							isEnabledEmptyProductError: this.isEnabledEmptyProductError(),
							isEnabledDetailLink: this.isInputDetailLinkEnabled()
						});
					}

					main_core.Dom.append(this.searchInput.layout(), block);
				}

				return block;
			}
		}, {
			key: "searchInDialog",
			value: function searchInDialog() {
				this.searchInput.searchInDialog();
				return this;
			}
		}, {
			key: "updateSkuTree",
			value: function updateSkuTree(tree) {
				this.getModel().setSkuTree(tree);
				this.skuTreeInstance = null;
				return this;
			}
		}, {
			key: "getIblockSkuTreeProperties",
			value: function getIblockSkuTreeProperties() {
				var _this3 = this;

				return new Promise(function (resolve) {
					if (iblockSkuTreeProperties.has(_this3.getModel().getIblockId())) {
						resolve(iblockSkuTreeProperties.get(_this3.getModel().getIblockId()));
					} else {
						main_core.ajax.runAction('catalog.productSelector.getSkuTreeProperties', {
							json: {
								iblockId: _this3.getModel().getIblockId()
							}
						}).then(function (response) {
							iblockSkuTreeProperties.set(_this3.getModel().getIblockId(), response);
							resolve(response);
						});
					}
				});
			}
		}, {
			key: "getSkuTreeInstance",
			value: function getSkuTreeInstance() {
				var _this$getModel;

				if (this.isSkuTreeEnabled() && (_this$getModel = this.getModel()) !== null && _this$getModel !== void 0 && _this$getModel.getSkuTree() && !this.skuTreeInstance) {
					this.skuTreeInstance = new catalog_skuTree.SkuTree({
						skuTree: this.getModel().getSkuTree(),
						selectable: this.getConfig('ENABLE_SKU_SELECTION', true),
						hideUnselected: this.getConfig('HIDE_UNSELECTED_ITEMS', false)
					});
				}

				return this.skuTreeInstance;
			}
		}, {
			key: "subscribeToVariationChange",
			value: function subscribeToVariationChange() {
				var skuTree = this.getSkuTreeInstance();

				if (skuTree) {
					this.unsubscribeToVariationChange();
					skuTree.subscribe('SkuProperty::onChange', this.variationChangeHandler);
				}
			}
		}, {
			key: "unsubscribeToVariationChange",
			value: function unsubscribeToVariationChange() {
				var skuTree = this.getSkuTreeInstance();

				if (skuTree) {
					skuTree.unsubscribe('SkuProperty::onChange', this.variationChangeHandler);
				}
			}
		}, {
			key: "handleVariationChange",
			value: function handleVariationChange(event) {
				var _this4 = this;

				var _event$getData = event.getData(),
					_event$getData2 = babelHelpers.slicedToArray(_event$getData, 1),
					skuFields = _event$getData2[0];

				var productId = main_core.Text.toNumber(skuFields.PARENT_PRODUCT_ID);
				var variationId = main_core.Text.toNumber(skuFields.ID);

				if (productId <= 0 || variationId <= 0) {
					return;
				}

				this.emit('onBeforeChange', {
					selectorId: this.getId(),
					rowId: this.getRowId()
				});
				babelHelpers.classPrivateFieldSet(this, _inAjaxProcess, true);
				main_core.ajax.runAction('catalog.productSelector.getSelectedSku', {
					json: {
						variationId: variationId,
						options: {
							priceId: this.basePriceId,
							currency: this.model.getCurrency(),
							urlBuilder: this.getConfig('URL_BUILDER_CONTEXT')
						}
					}
				}).then(function (response) {
					return _this4.processResponse(response, _objectSpread$2({}, _this4.options.config));
				});
			}
		}, {
			key: "onChangeFields",
			value: function onChangeFields(event) {
				var eventData = event.getData();

				if (eventData.rowId !== this.getRowId()) {
					return;
				}

				var fields = eventData.fields;
				this.getModel().setFields(fields);
			}
		}, {
			key: "reloadFileInput",
			value: function reloadFileInput() {
				var _this$getModel2,
					_this5 = this;

				main_core.ajax.runAction('catalog.productSelector.getFileInput', {
					json: {
						iblockId: this.getModel().getIblockId(),
						skuId: (_this$getModel2 = this.getModel()) === null || _this$getModel2 === void 0 ? void 0 : _this$getModel2.getSkuId()
					}
				}).then(function (event) {
					_this5.getModel().getImageCollection().setEditInput(event.data.html);

					if (_this5.isImageFieldEnabled()) {
						_this5.layoutImage();
					}
				});
			}
		}, {
			key: "onNameChange",
			value: function onNameChange(event) {
				var eventData = event.getData();

				if (eventData.rowId !== this.getRowId() || !this.isEnabledAutosave()) {
					return;
				}

				var fields = eventData.fields;
				this.getModel().setFields(fields);
				this.getModel().save().then(function () {
					BX.UI.Notification.Center.notify({
						id: 'saving_field_notify_name',
						closeButton: false,
						content: main_core.Tag.render(_templateObject8$3 || (_templateObject8$3 = babelHelpers.taggedTemplateLiteral(["<div>", "</div>"])), main_core.Loc.getMessage('CATALOG_SELECTOR_SAVING_NOTIFICATION_NAME_CHANGED')),
						autoHide: true
					});
				});
			}
		}, {
			key: "onSaveImage",
			value: function onSaveImage(event) {
				var _event$getData3 = event.getData(),
					_event$getData4 = babelHelpers.slicedToArray(_event$getData3, 3),
					inputId = _event$getData4[1],
					response = _event$getData4[2];

				if (inputId !== this.getFileInput().getId()) {
					return;
				}

				this.getFileInput().setId(response.data.id);
				this.getFileInput().setInputHtml(response.data.input);
				this.getFileInput().setView(response.data.preview);
				this.getModel().getImageCollection().setMorePhotoValues(response.data.values);

				if (this.isImageFieldEnabled()) {
					this.layoutImage();
				}

				this.emit('onChange', {
					selectorId: this.id,
					rowId: this.getRowId(),
					fields: this.getModel().getFields(),
					morePhoto: this.getModel().getImageCollection().getMorePhotoValues()
				});
			}
		}, {
			key: "inProcess",
			value: function inProcess() {
				return babelHelpers.classPrivateFieldGet(this, _inAjaxProcess);
			}
		}, {
			key: "onProductSelect",
			value: function onProductSelect(productId, itemConfig) {
				this.emit('onProductSelect', {
					selectorId: this.getId(),
					rowId: this.getRowId()
				});
				this.emit('onBeforeChange', {
					selectorId: this.getId(),
					rowId: this.getRowId()
				});
				this.productSelectAjaxAction(productId, itemConfig);
			}
		}, {
			key: "productSelectAjaxAction",
			value: function productSelectAjaxAction(productId) {
				var _this6 = this;

				var itemConfig = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {
					isNew: false,
					immutableFields: []
				};
				babelHelpers.classPrivateFieldSet(this, _inAjaxProcess, true);
				main_core.ajax.runComponentAction('kitconsulting:crm.entity.extendedproduct.list', //crm.entity.extendedproduct.list
					'getProduct', {
						mode: 'ajax',
						data: {
							productId: productId,
							options: {
								priceId: this.basePriceId,
								currency: this.model.getCurrency(),
								urlBuilder: this.getConfig('URL_BUILDER_CONTEXT')
							}
						}
					}).then(function (response) {
					return _this6.processResponse(response, _objectSpread$2(_objectSpread$2({}, _this6.options.config), itemConfig), true);
				});
			}
		}, {
			key: "processResponse",
			value: function processResponse(response) {
				var _this7 = this;

				var config = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
				var isProductAction = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : false;
				var data = (response === null || response === void 0 ? void 0 : response.data) || null;
				console.log(data, config);
				babelHelpers.classPrivateFieldSet(this, _inAjaxProcess, false);
				var fields = (data === null || data === void 0 ? void 0 : data.fields) || [];

				if (main_core.Type.isArray(config.immutableFields)) {
					config.immutableFields.forEach(function (field) {
						fields[field] = _this7.getModel().getField(field);
					});
					data.fields = fields;
				}

				if (isProductAction) {
					this.clearState();
				}

				if (data) {
					this.changeSelectedElement(data, config);
				} else if (!isProductAction) {
					this.productSelectAjaxAction(this.getModel().getProductId());
				}

				this.unsubscribeToVariationChange();

				if (this.isEnabledChangesRendering()) {
					this.clearLayout();
					this.layout();
				}

				this.emit('onChange', {
					selectorId: this.id,
					rowId: this.getRowId(),
					isNew: config.isNew || false,
					fields: fields,
					morePhoto: this.getModel().getImageCollection().getMorePhotoValues()
				});
			}
		}, {
			key: "changeSelectedElement",
			value: function changeSelectedElement(data, config) {
				var productId = main_core.Text.toInteger(data.productId);
				var productChanged = this.getModel().getProductId() !== productId;

				if (productChanged) {
					this.getModel().setOption('productId', productId);
					this.getModel().setOption('skuId', main_core.Text.toInteger(data.skuId));
					this.getModel().setOption('isSimpleModel', false);
					this.getModel().setOption('isNew', config.isNew);
				}

				this.getModel().initFields(data.fields);
				var imageField = {
					id: '',
					input: '',
					preview: '',
					values: []
				};

				if (main_core.Type.isObject(data.image)) {
					imageField.id = data.image.id;
					imageField.input = data.image.input;
					imageField.preview = data.image.preview;
					imageField.values = data.image.values;
				}

				this.getFileInput().setId(imageField.id);
				this.getFileInput().setInputHtml(imageField.input);
				this.getFileInput().setView(imageField.preview);
				this.getModel().getImageCollection().setMorePhotoValues(imageField.values);
				this.checkEmptyImageError();

				if (data.detailUrl) {
					this.getModel().setDetailPath(data.detailUrl);
				}

				if (main_core.Type.isObject(data.skuTree)) {
					this.updateSkuTree(data.skuTree);
				}
			}
		}, {
			key: "checkEmptyImageError",
			value: function checkEmptyImageError() {
				if (!main_core.Type.isArrayFilled(this.getModel().getImageCollection().getMorePhotoValues()) && this.isEnabledEmptyImagesError()) {
					this.getModel().getErrorCollection().setError('EMPTY_IMAGE', main_core.Loc.getMessage('CATALOG_SELECTOR_EMPTY_IMAGE_ERROR'));
				} else {
					this.getModel().getErrorCollection().removeError('EMPTY_IMAGE');
				}
			}
		}, {
			key: "removeSpotlight",
			value: function removeSpotlight() {
				var _this$searchInput;

				(_this$searchInput = this.searchInput) === null || _this$searchInput === void 0 ? void 0 : _this$searchInput.removeSpotlight();
				this.setConfig('ENABLE_INFO_SPOTLIGHT', false);
			}
		}, {
			key: "removeQrAuth",
			value: function removeQrAuth() {
				var _this$searchInput2;

				(_this$searchInput2 = this.searchInput) === null || _this$searchInput2 === void 0 ? void 0 : _this$searchInput2.removeQrAuth();
				this.setConfig('ENABLE_BARCODE_QR_AUTH', false);
			}
		}]);
		return ProductSelector;
	}(main_core_events.EventEmitter);
	babelHelpers.defineProperty(ProductSelector, "MODE_VIEW", 'view');
	babelHelpers.defineProperty(ProductSelector, "MODE_EDIT", 'edit');
	babelHelpers.defineProperty(ProductSelector, "INPUT_FIELD_NAME", 'NAME');
	babelHelpers.defineProperty(ProductSelector, "INPUT_FIELD_BARCODE", 'BARCODE');
	babelHelpers.defineProperty(ProductSelector, "ErrorCodes", SelectorErrorCode);

	var _templateObject$9;

	function _classPrivateMethodInitSpec$2(obj, privateSet) { _checkPrivateRedeclaration$3(obj, privateSet); privateSet.add(obj); }

	function _classPrivateFieldInitSpec$2(obj, privateMap, value) { _checkPrivateRedeclaration$3(obj, privateMap); privateMap.set(obj, value); }

	function _checkPrivateRedeclaration$3(obj, privateCollection) { if (privateCollection.has(obj)) { throw new TypeError("Cannot initialize the same private elements twice on an object"); } }

	function _classPrivateMethodGet$2(receiver, privateSet, fn) { if (!privateSet.has(receiver)) { throw new TypeError("attempted to get private field on non-instance"); } return fn; }

	var _rowId = /*#__PURE__*/new WeakMap();

	var _model$1 = /*#__PURE__*/new WeakMap();

	var _node = /*#__PURE__*/new WeakMap();

	var _popup = /*#__PURE__*/new WeakMap();

	var _createPopup = /*#__PURE__*/new WeakSet();

	var StoreAvailablePopup = /*#__PURE__*/function () {
		function StoreAvailablePopup(options) {
			babelHelpers.classCallCheck(this, StoreAvailablePopup);

			_classPrivateMethodInitSpec$2(this, _createPopup);

			_classPrivateFieldInitSpec$2(this, _rowId, {
				writable: true,
				value: void 0
			});

			_classPrivateFieldInitSpec$2(this, _model$1, {
				writable: true,
				value: void 0
			});

			_classPrivateFieldInitSpec$2(this, _node, {
				writable: true,
				value: void 0
			});

			_classPrivateFieldInitSpec$2(this, _popup, {
				writable: true,
				value: void 0
			});

			babelHelpers.classPrivateFieldSet(this, _rowId, options.rowId);
			babelHelpers.classPrivateFieldSet(this, _model$1, options.model);
			this.setNode(options.node);
		}

		babelHelpers.createClass(StoreAvailablePopup, [{
			key: "setNode",
			value: function setNode(node) {
				babelHelpers.classPrivateFieldSet(this, _node, node);
				babelHelpers.classPrivateFieldGet(this, _node).classList.add('store-available-popup-link');
				main_core.Event.bind(babelHelpers.classPrivateFieldGet(this, _node), 'click', this.togglePopup.bind(this));
			}
		}, {
			key: "refreshStoreInfo",
			value: function refreshStoreInfo() {
				babelHelpers.classPrivateFieldGet(this, _model$1).getStoreCollection().refresh();
			}
		}, {
			key: "getPopupContent",
			value: function getPopupContent() {
				var _this = this;

				var storeId = babelHelpers.classPrivateFieldGet(this, _model$1).getField('STORE_ID');
				var storeCollection = babelHelpers.classPrivateFieldGet(this, _model$1).getStoreCollection();
				var storeQuantity = storeCollection.getStoreAmount(storeId);
				var reservedQuantity = storeCollection.getStoreReserved(storeId);
				var availableQuantity = storeCollection.getStoreAvailableAmount(storeId);

				var renderHead = function renderHead(value) {
					return "<td class=\"main-grid-cell-head main-grid-col-no-sortable main-grid-cell-right\">\n\t\t\t\t<div class=\"main-grid-cell-inner\">\n\t\t\t\t\t<span class=\"main-grid-cell-head-container\">".concat(value, "</span>\n\t\t\t\t</div>\n\t\t\t</td>");
				};

				var renderRow = function renderRow(value) {
					return "<td class=\"main-grid-cell main-grid-cell-right\">\n\t\t\t\t<div class=\"main-grid-cell-inner\">\n\t\t\t\t\t<span class=\"main-grid-cell-content\">".concat(value, "</span>\n\t\t\t\t</div>\n\t\t\t</td>");
				};

				var reservedQuantityLink = reservedQuantity > 0 ? "<a href=\"#\" class=\"store-available-popup-reserves-slider-link\">".concat(reservedQuantity, "</a>") : reservedQuantity;
				var result = main_core.Tag.render(_templateObject$9 || (_templateObject$9 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t<div class=\"store-available-popup-container\">\n\t\t\t\t<table class=\"main-grid-table\">\n\t\t\t\t\t<thead class=\"main-grid-header\">\n\t\t\t\t\t\t<tr class=\"main-grid-row-head\">\n\t\t\t\t\t\t\t", "\n\t\t\t\t\t\t\t", "\n\t\t\t\t\t\t\t", "\n\t\t\t\t\t\t</tr>\n\t\t\t\t\t</thead>\n\t\t\t\t\t<tbody>\n\t\t\t\t\t\t<tr class=\"main-grid-row main-grid-row-body\">\n\t\t\t\t\t\t\t", "\n\t\t\t\t\t\t\t", "\n\t\t\t\t\t\t\t", "\n\t\t\t\t\t\t</tr>\n\t\t\t\t\t</tbody>\n\t\t\t\t</table>\n\t\t\t</div>\n\t\t"])), renderHead(main_core.Loc.getMessage('CRM_ENTITY_PL_STORE_AVAILABLE_POPUP_QUANTITY_COMMON')), renderHead(main_core.Loc.getMessage('CRM_ENTITY_PL_STORE_AVAILABLE_POPUP_QUANTITY_RESERVED')), renderHead(main_core.Loc.getMessage('CRM_ENTITY_PL_STORE_AVAILABLE_POPUP_QUANTITY_AVAILABLE')), renderRow(storeQuantity), renderRow(reservedQuantityLink), renderRow(availableQuantity));

				if (reservedQuantity > 0) {
					reservedQuantityLink = result.querySelector('.store-available-popup-reserves-slider-link');
					main_core.Event.bind(reservedQuantityLink, 'click', function (e) {
						e.preventDefault();

						_this.openDealsWithReservedProductSlider();
					});
				}

				return result;
			}
		}, {
			key: "openDealsWithReservedProductSlider",
			value: function openDealsWithReservedProductSlider() {
				var reservedDealsSliderLink = '/bitrix/components/bitrix/catalog.productcard.reserved.deal.list/slider.php';
				var storeId = babelHelpers.classPrivateFieldGet(this, _model$1).getField('STORE_ID');
				var productId = babelHelpers.classPrivateFieldGet(this, _model$1).getField('PRODUCT_ID');
				var sliderLink = new main_core.Uri(reservedDealsSliderLink);
				sliderLink.setQueryParam('productId', productId);
				sliderLink.setQueryParam('storeId', storeId);
				BX.SidePanel.Instance.open(sliderLink.toString(), {
					allowChangeHistory: false,
					cacheable: false
				});
			}
		}, {
			key: "togglePopup",
			value: function togglePopup() {
				if (babelHelpers.classPrivateFieldGet(this, _popup)) {
					if (babelHelpers.classPrivateFieldGet(this, _popup).isShown()) {
						babelHelpers.classPrivateFieldGet(this, _popup).close();
					} else {
						babelHelpers.classPrivateFieldGet(this, _popup).setContent(this.getPopupContent());
						babelHelpers.classPrivateFieldGet(this, _popup).show();
					}
				} else {
					_classPrivateMethodGet$2(this, _createPopup, _createPopup2).call(this);

					babelHelpers.classPrivateFieldGet(this, _popup).show();
				}
			}
		}]);
		return StoreAvailablePopup;
	}();

	function _createPopup2() {
		var popupId = "store-available-popup-row-".concat(babelHelpers.classPrivateFieldGet(this, _rowId));
		var popup = main_popup.PopupManager.getPopupById(popupId);

		if (popup) {
			babelHelpers.classPrivateFieldSet(this, _popup, popup);
			babelHelpers.classPrivateFieldGet(this, _popup).setBindElement(babelHelpers.classPrivateFieldGet(this, _node));
			babelHelpers.classPrivateFieldGet(this, _popup).setContent(this.getPopupContent());
		} else {
			babelHelpers.classPrivateFieldSet(this, _popup, main_popup.PopupManager.create({
				id: popupId,
				bindElement: babelHelpers.classPrivateFieldGet(this, _node),
				autoHide: true,
				draggable: false,
				offsetLeft: -218,
				offsetTop: 0,
				angle: {
					position: 'top',
					offset: 250
				},
				noAllPaddings: true,
				bindOptions: {
					forceBindPosition: true
				},
				closeByEsc: true,
				content: this.getPopupContent()
			}));
		}
	}

	function _regeneratorRuntime() { /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/facebook/regenerator/blob/main/LICENSE */ _regeneratorRuntime = function _regeneratorRuntime() { return exports; }; var exports = {}, Op = Object.prototype, hasOwn = Op.hasOwnProperty, $Symbol = "function" == typeof Symbol ? Symbol : {}, iteratorSymbol = $Symbol.iterator || "@@iterator", asyncIteratorSymbol = $Symbol.asyncIterator || "@@asyncIterator", toStringTagSymbol = $Symbol.toStringTag || "@@toStringTag"; function define(obj, key, value) { return Object.defineProperty(obj, key, { value: value, enumerable: !0, configurable: !0, writable: !0 }), obj[key]; } try { define({}, ""); } catch (err) { define = function define(obj, key, value) { return obj[key] = value; }; } function wrap(innerFn, outerFn, self, tryLocsList) { var protoGenerator = outerFn && outerFn.prototype instanceof Generator ? outerFn : Generator, generator = Object.create(protoGenerator.prototype), context = new Context(tryLocsList || []); return generator._invoke = function (innerFn, self, context) { var state = "suspendedStart"; return function (method, arg) { if ("executing" === state) throw new Error("Generator is already running"); if ("completed" === state) { if ("throw" === method) throw arg; return doneResult(); } for (context.method = method, context.arg = arg;;) { var delegate = context.delegate; if (delegate) { var delegateResult = maybeInvokeDelegate(delegate, context); if (delegateResult) { if (delegateResult === ContinueSentinel) continue; return delegateResult; } } if ("next" === context.method) context.sent = context._sent = context.arg;else if ("throw" === context.method) { if ("suspendedStart" === state) throw state = "completed", context.arg; context.dispatchException(context.arg); } else "return" === context.method && context.abrupt("return", context.arg); state = "executing"; var record = tryCatch(innerFn, self, context); if ("normal" === record.type) { if (state = context.done ? "completed" : "suspendedYield", record.arg === ContinueSentinel) continue; return { value: record.arg, done: context.done }; } "throw" === record.type && (state = "completed", context.method = "throw", context.arg = record.arg); } }; }(innerFn, self, context), generator; } function tryCatch(fn, obj, arg) { try { return { type: "normal", arg: fn.call(obj, arg) }; } catch (err) { return { type: "throw", arg: err }; } } exports.wrap = wrap; var ContinueSentinel = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} var IteratorPrototype = {}; define(IteratorPrototype, iteratorSymbol, function () { return this; }); var getProto = Object.getPrototypeOf, NativeIteratorPrototype = getProto && getProto(getProto(values([]))); NativeIteratorPrototype && NativeIteratorPrototype !== Op && hasOwn.call(NativeIteratorPrototype, iteratorSymbol) && (IteratorPrototype = NativeIteratorPrototype); var Gp = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(IteratorPrototype); function defineIteratorMethods(prototype) { ["next", "throw", "return"].forEach(function (method) { define(prototype, method, function (arg) { return this._invoke(method, arg); }); }); } function AsyncIterator(generator, PromiseImpl) { function invoke(method, arg, resolve, reject) { var record = tryCatch(generator[method], generator, arg); if ("throw" !== record.type) { var result = record.arg, value = result.value; return value && "object" == babelHelpers["typeof"](value) && hasOwn.call(value, "__await") ? PromiseImpl.resolve(value.__await).then(function (value) { invoke("next", value, resolve, reject); }, function (err) { invoke("throw", err, resolve, reject); }) : PromiseImpl.resolve(value).then(function (unwrapped) { result.value = unwrapped, resolve(result); }, function (error) { return invoke("throw", error, resolve, reject); }); } reject(record.arg); } var previousPromise; this._invoke = function (method, arg) { function callInvokeWithMethodAndArg() { return new PromiseImpl(function (resolve, reject) { invoke(method, arg, resolve, reject); }); } return previousPromise = previousPromise ? previousPromise.then(callInvokeWithMethodAndArg, callInvokeWithMethodAndArg) : callInvokeWithMethodAndArg(); }; } function maybeInvokeDelegate(delegate, context) { var method = delegate.iterator[context.method]; if (undefined === method) { if (context.delegate = null, "throw" === context.method) { if (delegate.iterator["return"] && (context.method = "return", context.arg = undefined, maybeInvokeDelegate(delegate, context), "throw" === context.method)) return ContinueSentinel; context.method = "throw", context.arg = new TypeError("The iterator does not provide a 'throw' method"); } return ContinueSentinel; } var record = tryCatch(method, delegate.iterator, context.arg); if ("throw" === record.type) return context.method = "throw", context.arg = record.arg, context.delegate = null, ContinueSentinel; var info = record.arg; return info ? info.done ? (context[delegate.resultName] = info.value, context.next = delegate.nextLoc, "return" !== context.method && (context.method = "next", context.arg = undefined), context.delegate = null, ContinueSentinel) : info : (context.method = "throw", context.arg = new TypeError("iterator result is not an object"), context.delegate = null, ContinueSentinel); } function pushTryEntry(locs) { var entry = { tryLoc: locs[0] }; 1 in locs && (entry.catchLoc = locs[1]), 2 in locs && (entry.finallyLoc = locs[2], entry.afterLoc = locs[3]), this.tryEntries.push(entry); } function resetTryEntry(entry) { var record = entry.completion || {}; record.type = "normal", delete record.arg, entry.completion = record; } function Context(tryLocsList) { this.tryEntries = [{ tryLoc: "root" }], tryLocsList.forEach(pushTryEntry, this), this.reset(!0); } function values(iterable) { if (iterable) { var iteratorMethod = iterable[iteratorSymbol]; if (iteratorMethod) return iteratorMethod.call(iterable); if ("function" == typeof iterable.next) return iterable; if (!isNaN(iterable.length)) { var i = -1, next = function next() { for (; ++i < iterable.length;) { if (hasOwn.call(iterable, i)) return next.value = iterable[i], next.done = !1, next; } return next.value = undefined, next.done = !0, next; }; return next.next = next; } } return { next: doneResult }; } function doneResult() { return { value: undefined, done: !0 }; } return GeneratorFunction.prototype = GeneratorFunctionPrototype, define(Gp, "constructor", GeneratorFunctionPrototype), define(GeneratorFunctionPrototype, "constructor", GeneratorFunction), GeneratorFunction.displayName = define(GeneratorFunctionPrototype, toStringTagSymbol, "GeneratorFunction"), exports.isGeneratorFunction = function (genFun) { var ctor = "function" == typeof genFun && genFun.constructor; return !!ctor && (ctor === GeneratorFunction || "GeneratorFunction" === (ctor.displayName || ctor.name)); }, exports.mark = function (genFun) { return Object.setPrototypeOf ? Object.setPrototypeOf(genFun, GeneratorFunctionPrototype) : (genFun.__proto__ = GeneratorFunctionPrototype, define(genFun, toStringTagSymbol, "GeneratorFunction")), genFun.prototype = Object.create(Gp), genFun; }, exports.awrap = function (arg) { return { __await: arg }; }, defineIteratorMethods(AsyncIterator.prototype), define(AsyncIterator.prototype, asyncIteratorSymbol, function () { return this; }), exports.AsyncIterator = AsyncIterator, exports.async = function (innerFn, outerFn, self, tryLocsList, PromiseImpl) { void 0 === PromiseImpl && (PromiseImpl = Promise); var iter = new AsyncIterator(wrap(innerFn, outerFn, self, tryLocsList), PromiseImpl); return exports.isGeneratorFunction(outerFn) ? iter : iter.next().then(function (result) { return result.done ? result.value : iter.next(); }); }, defineIteratorMethods(Gp), define(Gp, toStringTagSymbol, "Generator"), define(Gp, iteratorSymbol, function () { return this; }), define(Gp, "toString", function () { return "[object Generator]"; }), exports.keys = function (object) { var keys = []; for (var key in object) { keys.push(key); } return keys.reverse(), function next() { for (; keys.length;) { var key = keys.pop(); if (key in object) return next.value = key, next.done = !1, next; } return next.done = !0, next; }; }, exports.values = values, Context.prototype = { constructor: Context, reset: function reset(skipTempReset) { if (this.prev = 0, this.next = 0, this.sent = this._sent = undefined, this.done = !1, this.delegate = null, this.method = "next", this.arg = undefined, this.tryEntries.forEach(resetTryEntry), !skipTempReset) for (var name in this) { "t" === name.charAt(0) && hasOwn.call(this, name) && !isNaN(+name.slice(1)) && (this[name] = undefined); } }, stop: function stop() { this.done = !0; var rootRecord = this.tryEntries[0].completion; if ("throw" === rootRecord.type) throw rootRecord.arg; return this.rval; }, dispatchException: function dispatchException(exception) { if (this.done) throw exception; var context = this; function handle(loc, caught) { return record.type = "throw", record.arg = exception, context.next = loc, caught && (context.method = "next", context.arg = undefined), !!caught; } for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i], record = entry.completion; if ("root" === entry.tryLoc) return handle("end"); if (entry.tryLoc <= this.prev) { var hasCatch = hasOwn.call(entry, "catchLoc"), hasFinally = hasOwn.call(entry, "finallyLoc"); if (hasCatch && hasFinally) { if (this.prev < entry.catchLoc) return handle(entry.catchLoc, !0); if (this.prev < entry.finallyLoc) return handle(entry.finallyLoc); } else if (hasCatch) { if (this.prev < entry.catchLoc) return handle(entry.catchLoc, !0); } else { if (!hasFinally) throw new Error("try statement without catch or finally"); if (this.prev < entry.finallyLoc) return handle(entry.finallyLoc); } } } }, abrupt: function abrupt(type, arg) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.tryLoc <= this.prev && hasOwn.call(entry, "finallyLoc") && this.prev < entry.finallyLoc) { var finallyEntry = entry; break; } } finallyEntry && ("break" === type || "continue" === type) && finallyEntry.tryLoc <= arg && arg <= finallyEntry.finallyLoc && (finallyEntry = null); var record = finallyEntry ? finallyEntry.completion : {}; return record.type = type, record.arg = arg, finallyEntry ? (this.method = "next", this.next = finallyEntry.finallyLoc, ContinueSentinel) : this.complete(record); }, complete: function complete(record, afterLoc) { if ("throw" === record.type) throw record.arg; return "break" === record.type || "continue" === record.type ? this.next = record.arg : "return" === record.type ? (this.rval = this.arg = record.arg, this.method = "return", this.next = "end") : "normal" === record.type && afterLoc && (this.next = afterLoc), ContinueSentinel; }, finish: function finish(finallyLoc) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.finallyLoc === finallyLoc) return this.complete(entry.completion, entry.afterLoc), resetTryEntry(entry), ContinueSentinel; } }, "catch": function _catch(tryLoc) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.tryLoc === tryLoc) { var record = entry.completion; if ("throw" === record.type) { var thrown = record.arg; resetTryEntry(entry); } return thrown; } } throw new Error("illegal catch attempt"); }, delegateYield: function delegateYield(iterable, resultName, nextLoc) { return this.delegate = { iterator: values(iterable), resultName: resultName, nextLoc: nextLoc }, "next" === this.method && (this.arg = undefined), ContinueSentinel; } }, exports; }
	var ProductApplication = /*#__PURE__*/function () {
		/*static unsetCheckbox(rowId)
	  {
	      let checkbox = null;
	        if ((checkbox = document.querySelector('[data-application-row-id="'+rowId+'"]')) !== null) {
	          checkbox.checked = false;
	          delete checkbox.dataset.applicationRowId;
	      }
	  }*/
		function ProductApplication(productRow) {
			babelHelpers.classCallCheck(this, ProductApplication);
			babelHelpers.defineProperty(this, "_popupPrefix", 'application_popup_');
			babelHelpers.defineProperty(this, "_gridPrefix", 'applications_grid_');
			babelHelpers.defineProperty(this, "_productRow", null);
			babelHelpers.defineProperty(this, "_popup", null);
			babelHelpers.defineProperty(this, "_popupLoaded", false);
			this._productRow = productRow;
			var popupId = this.getPopupId(this._productRow.getId());
			var popup = main_popup.PopupWindowManager.getPopupById(popupId);

			if (null === popup) {
				popup = new main_popup.PopupWindow(this.getPopupId(this._productRow.getId()), window.body, {
					autoHide: true,
					closeIcon: true,
					closeByEsc: true,
					overlay: {
						backgroundColor: 'grey',
						opacity: '80'
					},
					width: 800,
					offsetLeft: 0,
					offsetTop: 0
				});
			}

			this._popup = popup;
		}

		babelHelpers.createClass(ProductApplication, [{
			key: "getPopupId",
			value: function getPopupId(productRowId) {
				return this._popupPrefix + productRowId;
			}
		}, {
			key: "showPopup",
			value: function showPopup() {
				var self = this;

				if (!self._popupLoaded) {
					BX.ajax.runComponentAction('kitconsulting:crm.entity.extendedproduct.applications', 'getApplications', {
						mode: 'class',
						data: {
							sessid: BX.bitrix_sessid(),
							ajax: 'Y',
							format: 'Y',
							raw: 'Y',
							productId: this._productRow.getModel().getField('PARENT_PRODUCT_ID', null) || this._productRow.getModel().getProductId(),
							rowId: this._productRow.getId().replace(this._productRow.getEditor().getRowIdPrefix(), '')
						}
					}).then( /*#__PURE__*/function () {
						var _ref = babelHelpers.asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee(res) {
							var applicationsResponse, table;
							return _regeneratorRuntime().wrap(function _callee$(_context) {
								while (1) {
									switch (_context.prev = _context.next) {
										case 0:
											_context.next = 2;
											return res;

										case 2:
											applicationsResponse = _context.sent;
											table = self.createApplicationsTable(applicationsResponse.data);

											self._popup.setContent(table);

											self._popupLoaded = true;

											self._popup.show();

											self.applyListeners();

										case 8:
										case "end":
											return _context.stop();
									}
								}
							}, _callee);
						}));

						return function (_x) {
							return _ref.apply(this, arguments);
						};
					}());
				} else {
					self._popup.show();
				}
			}
		}, {
			key: "createApplicationsTable",
			value: function createApplicationsTable(data) {
				var _this = this;

				var container = document.createElement('DIV');
				var table = document.createElement('TABLE');
				var head = document.createElement('THEAD');
				var headRow = document.createElement('TR');
				var body = document.createElement('TBODY');
				var useAdvPrice = false;

				if (BX.Crm.EntityEditor.defaultInstance.getOwnerInfo().ownerType.startsWith('DEAL')) {
					var control = BX.Crm.EntityEditor.defaultInstance.getActiveControlById("UF_ADV_AGENT");

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
				data.columnNames.forEach(function (columnName) {
					var headColumn = document.createElement('TH');
					headColumn.innerText = columnName;
					headRow.append(headColumn);
				});
				data.applications.forEach(function (elem) {
					var bodyRow = document.createElement('TR');
					var checkboxColumn = document.createElement('TD');
					var checkbox = document.createElement('INPUT');
					var nameColumn = document.createElement('TD');
					checkbox.setAttribute('type', 'checkbox');
					checkbox.setAttribute('value', elem['APPLICATION_ID']);
					checkbox.dataset.price = useAdvPrice ? elem['APPLICATION_PRICE_ADV'] : elem['APPLICATION_PRICE_OPT'];
					checkbox.classList.add('product_application_checkbox');

					if (elem['APPLICATION_PRODUCT_ROW'] != null && _this._productRow.getEditor().getProductById(elem['APPLICATION_PRODUCT_ROW']['ID']) != null) {
						checkbox.checked = true; //checkbox.dataset.applicationRowId = elem['APPLICATION_PRODUCT_ROW']['ID'];
					}

					checkboxColumn.append(checkbox);
					bodyRow.append(checkboxColumn);
					nameColumn.innerText = elem['APPLICATION_NAME'];
					if (elem.PRODUCT_HAS) nameColumn.classList.add("product-bold");
					bodyRow.append(nameColumn);
					body.append(bodyRow);
				});
				return container;
			}
		}, {
			key: "applyListeners",
			value: function applyListeners() {
				var _this2 = this;

				var tableElement = document.querySelector("#" + this.getTableId(this._productRow.getId()));
				tableElement.querySelectorAll('.product_application_checkbox').forEach(function (elem, id) {
					elem.addEventListener('change', _this2.changeApplication.bind(_this2));
				});
			}
		}, {
			key: "destroy",
			value: function destroy() {
				document.getElementById(this.getTableId(this._productRow.getId())).remove();

				this._popup.destroy();
			}
		}, {
			key: "getTableId",
			value: function getTableId(productRowId) {
				return this._gridPrefix + productRowId;
			}
		}, {
			key: "changeApplication",
			value: function changeApplication(event) {
				var editor = this._productRow.getEditor();

				if (event.target.checked) {
					var adjustedProduct = this._productRow.getAdjustedProductRow();

					var id = editor.addProductRowAfter(adjustedProduct || this._productRow);
					var productRow = editor.getProductById(id);
					var selector = editor.getProductSelector(id);
					selector.onProductSelect(event.target.value);
					productRow.setField('UF_APPLICATION_PARENT_PRODUCT_ROW_ID', this._productRow.getId().replace(editor.getRowIdPrefix(), ''));
					productRow.setApplicationPrice(event.target.dataset.price);
					productRow.disableUiField('UF_NEED_ADJUSTMENT');
					productRow.disableUiField('UF_CRM_PR_ADJUSTMENT');
					productRow.getNode().querySelector('[data-field-code="UF_APPLICATION_PRICE"]').style.display = null;
					editor.numerateRows(); //event.target.dataset.applicationRowId = id;
				} else {
					editor.deleteApplicationsForRow(this._productRow.getField('ID'), event.target.value);
					editor.numerateRows(); //delete event.target.dataset.applicationRowId;
				}
			}
		}]);
		return ProductApplication;
	}();

	var _templateObject$a, _templateObject2$7, _templateObject3$7, _templateObject4$6, _templateObject5$6, _templateObject6$5, _templateObject7$4, _templateObject8$4, _templateObject9$2;

	function _createForOfIteratorHelper(o, allowArrayLike) { var it = typeof Symbol !== "undefined" && o[Symbol.iterator] || o["@@iterator"]; if (!it) { if (Array.isArray(o) || (it = _unsupportedIterableToArray(o)) || allowArrayLike && o && typeof o.length === "number") { if (it) o = it; var i = 0; var F = function F() {}; return { s: F, n: function n() { if (i >= o.length) return { done: true }; return { done: false, value: o[i++] }; }, e: function e(_e) { throw _e; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var normalCompletion = true, didErr = false, err; return { s: function s() { it = it.call(o); }, n: function n() { var step = it.next(); normalCompletion = step.done; return step; }, e: function e(_e2) { didErr = true; err = _e2; }, f: function f() { try { if (!normalCompletion && it["return"] != null) it["return"](); } finally { if (didErr) throw err; } } }; }

	function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }

	function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }

	function _classPrivateMethodInitSpec$3(obj, privateSet) { _checkPrivateRedeclaration$4(obj, privateSet); privateSet.add(obj); }

	function _checkPrivateRedeclaration$4(obj, privateCollection) { if (privateCollection.has(obj)) { throw new TypeError("Cannot initialize the same private elements twice on an object"); } }

	function _classPrivateMethodGet$3(receiver, privateSet, fn) { if (!privateSet.has(receiver)) { throw new TypeError("attempted to get private field on non-instance"); } return fn; }
	var MODE_EDIT = 'EDIT';
	var MODE_SET = 'SET';

	var _initActions = /*#__PURE__*/new WeakSet();

	var _showChangePriceNotify = /*#__PURE__*/new WeakSet();

	var _isEditableCatalogPrice = /*#__PURE__*/new WeakSet();

	var _isSaveableCatalogPrice = /*#__PURE__*/new WeakSet();

	var _initSelector = /*#__PURE__*/new WeakSet();

	var _onMainSelectorClear = /*#__PURE__*/new WeakSet();

	var _onMainSelectorChange = /*#__PURE__*/new WeakSet();

	var _initStoreSelector = /*#__PURE__*/new WeakSet();

	var _initStoreAvailablePopup = /*#__PURE__*/new WeakSet();

	var _applyStoreSelectorRestrictionTweaks = /*#__PURE__*/new WeakSet();

	var _initReservedControl = /*#__PURE__*/new WeakSet();

	var _onStoreFieldChange = /*#__PURE__*/new WeakSet();

	var _onStoreFieldClear = /*#__PURE__*/new WeakSet();

	var _showPriceNotifier = /*#__PURE__*/new WeakSet();

	var _onChangeStoreData = /*#__PURE__*/new WeakSet();

	var _onProductErrorsChange = /*#__PURE__*/new WeakSet();

	var _shouldShowSmallPriceHint = /*#__PURE__*/new WeakSet();

	var _togglePriceHintPopup = /*#__PURE__*/new WeakSet();

	var _toggleMinimalPricePopup = /*#__PURE__*/new WeakSet();

	var _isReserveEqualProductQuantity = /*#__PURE__*/new WeakSet();

	var _getNodeChildByDataName = /*#__PURE__*/new WeakSet();

	var _onGridUpdated = /*#__PURE__*/new WeakSet();

	var Row = /*#__PURE__*/function () {
		// handleMainSelectorChange = Runtime.debounce(this.#onMainSelectorChange.bind(this), 500, this);
		function Row(_id, fields, settings, editor) {
			babelHelpers.classCallCheck(this, Row);

			_classPrivateMethodInitSpec$3(this, _onGridUpdated);

			_classPrivateMethodInitSpec$3(this, _getNodeChildByDataName);

			_classPrivateMethodInitSpec$3(this, _isReserveEqualProductQuantity);

			_classPrivateMethodInitSpec$3(this, _toggleMinimalPricePopup);

			_classPrivateMethodInitSpec$3(this, _togglePriceHintPopup);

			_classPrivateMethodInitSpec$3(this, _shouldShowSmallPriceHint);

			_classPrivateMethodInitSpec$3(this, _onProductErrorsChange);

			_classPrivateMethodInitSpec$3(this, _onChangeStoreData);

			_classPrivateMethodInitSpec$3(this, _showPriceNotifier);

			_classPrivateMethodInitSpec$3(this, _onStoreFieldClear);

			_classPrivateMethodInitSpec$3(this, _onStoreFieldChange);

			_classPrivateMethodInitSpec$3(this, _initReservedControl);

			_classPrivateMethodInitSpec$3(this, _applyStoreSelectorRestrictionTweaks);

			_classPrivateMethodInitSpec$3(this, _initStoreAvailablePopup);

			_classPrivateMethodInitSpec$3(this, _initStoreSelector);

			_classPrivateMethodInitSpec$3(this, _onMainSelectorChange);

			_classPrivateMethodInitSpec$3(this, _onMainSelectorClear);

			_classPrivateMethodInitSpec$3(this, _initSelector);

			_classPrivateMethodInitSpec$3(this, _isSaveableCatalogPrice);

			_classPrivateMethodInitSpec$3(this, _isEditableCatalogPrice);

			_classPrivateMethodInitSpec$3(this, _showChangePriceNotify);

			_classPrivateMethodInitSpec$3(this, _initActions);

			babelHelpers.defineProperty(this, "fields", {});
			babelHelpers.defineProperty(this, "externalActions", []);
			babelHelpers.defineProperty(this, "handleFocusUnchangeablePrice", _classPrivateMethodGet$3(this, _showChangePriceNotify, _showChangePriceNotify2).bind(this));
			babelHelpers.defineProperty(this, "handleChangeStoreData", _classPrivateMethodGet$3(this, _onChangeStoreData, _onChangeStoreData2).bind(this));
			babelHelpers.defineProperty(this, "handleProductErrorsChange", main_core.Runtime.debounce(_classPrivateMethodGet$3(this, _onProductErrorsChange, _onProductErrorsChange2), 500, this));
			babelHelpers.defineProperty(this, "handleMainSelectorClear", main_core.Runtime.debounce(_classPrivateMethodGet$3(this, _onMainSelectorClear, _onMainSelectorClear2).bind(this), 500, this));
			babelHelpers.defineProperty(this, "handleMainSelectorChange", _classPrivateMethodGet$3(this, _onMainSelectorChange, _onMainSelectorChange2).bind(this));
			babelHelpers.defineProperty(this, "handleStoreFieldChange", main_core.Runtime.debounce(_classPrivateMethodGet$3(this, _onStoreFieldChange, _onStoreFieldChange2).bind(this), 500, this));
			babelHelpers.defineProperty(this, "handleStoreFieldClear", main_core.Runtime.debounce(_classPrivateMethodGet$3(this, _onStoreFieldClear, _onStoreFieldClear2).bind(this), 500, this));
			babelHelpers.defineProperty(this, "handleOnGridUpdated", _classPrivateMethodGet$3(this, _onGridUpdated, _onGridUpdated2).bind(this));
			babelHelpers.defineProperty(this, "cache", new main_core.Cache.MemoryCache());
			babelHelpers.defineProperty(this, "modeChanges", {
				EDIT: MODE_EDIT,
				SET: MODE_SET
			});
			babelHelpers.defineProperty(this, "_productApplication", null);
			this.setId(_id);
			this.setSettings(settings);
			this.setEditor(editor);
			this.setModel(fields, settings);
			this.setFields(fields);

			_classPrivateMethodGet$3(this, _initActions, _initActions2).call(this);

			_classPrivateMethodGet$3(this, _initSelector, _initSelector2).call(this);

			_classPrivateMethodGet$3(this, _initStoreSelector, _initStoreSelector2).call(this);

			_classPrivateMethodGet$3(this, _initStoreAvailablePopup, _initStoreAvailablePopup2).call(this);

			_classPrivateMethodGet$3(this, _initReservedControl, _initReservedControl2).call(this);

			this.modifyBasePriceInput();
			this.refreshFieldsLayout();
			requestAnimationFrame(this.initHandlers.bind(this));
		}

		babelHelpers.createClass(Row, [{
			key: "getNode",
			value: function getNode() {
				var _this = this;

				return this.cache.remember('node', function () {
					var rowId = _this.getField('ID', 0);

					return _this.getEditorContainer().querySelector('[data-id="' + rowId + '"]');
				});
			}
		}, {
			key: "getSelector",
			value: function getSelector() {
				return this.mainSelector;
			}
		}, {
			key: "clearChanges",
			value: function clearChanges() {
				this.getModel().clearChangedList();
			}
		}, {
			key: "isNewRow",
			value: function isNewRow() {
				return isNaN(+this.getField('ID'));
			}
		}, {
			key: "getId",
			value: function getId() {
				return this.id;
			}
		}, {
			key: "setId",
			value: function setId(id) {
				this.id = id;
			}
		}, {
			key: "getSettings",
			value: function getSettings() {
				return this.settings;
			}
		}, {
			key: "setSettings",
			value: function setSettings(settings) {
				this.settings = main_core.Type.isPlainObject(settings) ? settings : {};
			}
		}, {
			key: "getSettingValue",
			value: function getSettingValue(name, defaultValue) {
				return this.settings.hasOwnProperty(name) ? this.settings[name] : defaultValue;
			}
		}, {
			key: "setSettingValue",
			value: function setSettingValue(name, value) {
				this.settings[name] = value;
			}
		}, {
			key: "setEditor",
			value: function setEditor(editor) {
				this.editor = editor;
			}
		}, {
			key: "getEditor",
			value: function getEditor() {
				return this.editor;
			}
		}, {
			key: "getEditorContainer",
			value: function getEditorContainer() {
				return this.getEditor().getContainer();
			}
		}, {
			key: "getHintPopup",
			value: function getHintPopup() {
				return this.getEditor().getHintPopup();
			}
		}, {
			key: "initHandlers",
			value: function initHandlers() {
				var editor = this.getEditor();
				this.getNode().querySelectorAll('input').forEach(function (node) {
					/*if (node.classList.contains('main-grid-row-checkbox')) {
	        	Event.bind(node, 'change', this.changeMassActionField.bind(this));
	        	return;
	        }*/
					main_core.Event.bind(node, 'input', editor.changeProductFieldHandler);
					main_core.Event.bind(node, 'change', editor.changeProductFieldHandler); // disable drag-n-drop events for text fields

					main_core.Event.bind(node, 'mousedown', function (event) {
						return event.stopPropagation();
					});
				});
				this.getNode().querySelectorAll('select').forEach(function (node) {
					main_core.Event.bind(node, 'change', editor.changeProductFieldHandler); // disable drag-n-drop events for select fields

					main_core.Event.bind(node, 'mousedown', function (event) {
						return event.stopPropagation();
					});
				});
			}
		}, {
			key: "initHandlersForSelectors",
			value: function initHandlersForSelectors() {
				var _this2 = this;

				var editor = this.getEditor();
				var selectorNames = ['MAIN_INFO', 'STORE_INFO', 'RESERVE_INFO'];
				selectorNames.forEach(function (name) {
					_this2.getNode().querySelectorAll('[data-name="' + name + '"] input[type="text"]').forEach(function (node) {
						main_core.Event.bind(node, 'input', editor.changeProductFieldHandler);
						main_core.Event.bind(node, 'change', editor.changeProductFieldHandler); // disable drag-n-drop events for select fields

						main_core.Event.bind(node, 'mousedown', function (event) {
							return event.stopPropagation();
						});
					});
				});
			}
		}, {
			key: "unsubscribeCustomEvents",
			value: function unsubscribeCustomEvents() {
				if (this.mainSelector) {
					this.mainSelector.unsubscribeEvents();
					main_core_events.EventEmitter.unsubscribe(this.mainSelector, 'onClear', this.handleMainSelectorClear);
				}

				if (this.storeSelector) {
					this.storeSelector.unsubscribeEvents();
					main_core_events.EventEmitter.unsubscribe(this.storeSelector, 'onChange', this.handleStoreFieldChange);
					main_core_events.EventEmitter.unsubscribe(this.storeSelector, 'onClear', this.handleStoreFieldClear);
				}

				if (this.reserveControl) {
					main_core_events.EventEmitter.unsubscribeAll(this.reserveControl, 'onChange');
				}

				main_core_events.EventEmitter.unsubscribe(this.model, 'onChangeStoreData', this.handleChangeStoreData);
				main_core_events.EventEmitter.unsubscribe(this.model, 'onErrorsChange', this.handleProductErrorsChange);
			}
		}, {
			key: "changeMassActionField",
			value: function changeMassActionField(event) {
				console.log(this);
			}
		}, {
			key: "modifyBasePriceInput",
			value: function modifyBasePriceInput() {
				var priceNode = _classPrivateMethodGet$3(this, _getNodeChildByDataName, _getNodeChildByDataName2).call(this, 'PRICE');

				if (!priceNode) {
					return;
				}

				if (!_classPrivateMethodGet$3(this, _isEditableCatalogPrice, _isEditableCatalogPrice2).call(this)) {
					var _priceNode$querySelec;

					priceNode.setAttribute('disabled', true);
					main_core.Dom.addClass(priceNode, 'ui-ctl-element');
					(_priceNode$querySelec = priceNode.querySelector('.main-grid-editor-money-price')) === null || _priceNode$querySelec === void 0 ? void 0 : _priceNode$querySelec.setAttribute('disabled', 'true');

					if (!this.editor.getSettingValue('disableNotifyChangingPrice')) {
						main_core.Event.bind(priceNode, 'mouseenter', this.handleFocusUnchangeablePrice);
					}
				} else {
					var _priceNode$querySelec2;

					priceNode.removeAttribute('disabled');
					main_core.Dom.removeClass(priceNode, 'ui-ctl-element');
					(_priceNode$querySelec2 = priceNode.querySelector('.main-grid-editor-money-price')) === null || _priceNode$querySelec2 === void 0 ? void 0 : _priceNode$querySelec2.removeAttribute('disabled');
					main_core.Event.unbind(priceNode, 'mouseenter', this.handleFocusUnchangeablePrice);
				}
			}
		}, {
			key: "layoutReserveControl",
			value: function layoutReserveControl() {
				var storeWrapper = _classPrivateMethodGet$3(this, _getNodeChildByDataName, _getNodeChildByDataName2).call(this, 'RESERVE_INFO');

				if (storeWrapper && this.reserveControl) {
					storeWrapper.innerHTML = '';
					this.reserveControl.clearCache();
					this.reserveControl.renderTo(storeWrapper);
				}
			}
		}, {
			key: "setRowNumber",
			value: function setRowNumber(number) {
				this.getNode().querySelectorAll('.main-grid-row-number').forEach(function (node) {
					node.textContent = number + '.';
				});
			}
		}, {
			key: "getFields",
			value: function getFields() {
				var fields = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : [];
				var result;

				if (!main_core.Type.isArrayFilled(fields)) {
					result = main_core.Runtime.clone(this.fields);
				} else {
					result = {};

					var _iterator = _createForOfIteratorHelper(fields),
						_step;

					try {
						for (_iterator.s(); !(_step = _iterator.n()).done;) {
							var fieldName = _step.value;
							result[fieldName] = this.getField(fieldName);
						}
					} catch (err) {
						_iterator.e(err);
					} finally {
						_iterator.f();
					}
				}

				if ('PRODUCT_NAME' in result) {
					var fixedProductName = this.getField('FIXED_PRODUCT_NAME', '');

					if (main_core.Type.isStringFilled(fixedProductName)) {
						result['PRODUCT_NAME'] = fixedProductName;
					}
				}

				return result;
			}
		}, {
			key: "getCatalogFields",
			value: function getCatalogFields() {
				var fields = this.getFields(['CURRENCY', 'QUANTITY', 'MEASURE_CODE']);
				fields['PRICE'] = this.getBasePrice();
				fields['VAT_INCLUDED'] = this.getTaxIncluded();
				fields['VAT_ID'] = this.getTaxId();
				return fields;
			}
		}, {
			key: "getCalculateFields",
			value: function getCalculateFields() {
				return {
					'PRICE': this.getPrice(),
					'BASE_PRICE': this.getBasePrice(),
					'PRICE_EXCLUSIVE': this.getPriceExclusive(),
					'PRICE_NETTO': this.getPriceNetto(),
					'PRICE_BRUTTO': this.getPriceBrutto(),
					'QUANTITY': this.getQuantity(),
					'DISCOUNT_TYPE_ID': this.getDiscountType(),
					'DISCOUNT_RATE': this.getDiscountRate(),
					'DISCOUNT_SUM': this.getDiscountSum(),
					'DISCOUNT_ROW': this.getDiscountRow(),
					'TAX_INCLUDED': this.getTaxIncluded(),
					'TAX_RATE': this.getTaxRate()
				};
			}
		}, {
			key: "setFields",
			value: function setFields(fields) {
				for (var name in fields) {
					if (fields.hasOwnProperty(name)) {
						this.setField(name, fields[name]);
					}
				}
			}
		}, {
			key: "getField",
			value: function getField(name, defaultValue) {
				return this.fields.hasOwnProperty(name) ? this.fields[name] : defaultValue;
			}
		}, {
			key: "setField",
			value: function setField(name, value) {
				var changeModel = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : true;
				this.fields[name] = value;

				if (changeModel) {
					this.getModel().setField(name, value);
				}
			}
		}, {
			key: "getUiFieldId",
			value: function getUiFieldId(field) {
				return this.getId() + '_' + field;
			}
		}, {
			key: "getBasePrice",
			value: function getBasePrice() {
				return this.getField('BASE_PRICE', 0);
			}
		}, {
			key: "getEnteredPrice",
			value: function getEnteredPrice() {
				return this.getField('ENTERED_PRICE', this.getBasePrice());
			}
		}, {
			key: "getCatalogPrice",
			value: function getCatalogPrice() {
				return this.getField('CATALOG_PRICE', this.getBasePrice());
			}
		}, {
			key: "isPriceNetto",
			value: function isPriceNetto() {
				return this.getEditor().isTaxAllowed() && !this.isTaxIncluded();
			}
		}, {
			key: "getPrice",
			value: function getPrice() {
				return this.getField('PRICE', 0);
			}
		}, {
			key: "getPriceExclusive",
			value: function getPriceExclusive() {
				return this.getField('PRICE_EXCLUSIVE', 0);
			}
		}, {
			key: "getPriceNetto",
			value: function getPriceNetto() {
				return this.getField('PRICE_NETTO', 0);
			}
		}, {
			key: "getPriceBrutto",
			value: function getPriceBrutto() {
				return this.getField('PRICE_BRUTTO', 0);
			}
		}, {
			key: "getQuantity",
			value: function getQuantity() {
				return this.getField('QUANTITY', 1);
			}
		}, {
			key: "getDiscountType",
			value: function getDiscountType() {
				return this.getField('DISCOUNT_TYPE_ID', catalog_productCalculator.DiscountType.UNDEFINED);
			}
		}, {
			key: "isDiscountUndefined",
			value: function isDiscountUndefined() {
				return this.getDiscountType() === catalog_productCalculator.DiscountType.UNDEFINED;
			}
		}, {
			key: "isDiscountPercentage",
			value: function isDiscountPercentage() {
				return this.getDiscountType() === catalog_productCalculator.DiscountType.PERCENTAGE;
			}
		}, {
			key: "isDiscountMonetary",
			value: function isDiscountMonetary() {
				return this.getDiscountType() === catalog_productCalculator.DiscountType.MONETARY;
			}
		}, {
			key: "isDiscountHandmade",
			value: function isDiscountHandmade() {
				return this.isDiscountPercentage() || this.isDiscountMonetary();
			}
		}, {
			key: "getDiscountRate",
			value: function getDiscountRate() {
				return this.getField('DISCOUNT_RATE', 0);
			}
		}, {
			key: "getDiscountSum",
			value: function getDiscountSum() {
				return this.getField('DISCOUNT_SUM', 0);
			}
		}, {
			key: "getDiscountRow",
			value: function getDiscountRow() {
				return this.getField('DISCOUNT_ROW', 0);
			}
		}, {
			key: "isEmptyDiscount",
			value: function isEmptyDiscount() {
				if (this.isDiscountPercentage()) {
					return this.getDiscountRate() === 0;
				} else if (this.isDiscountMonetary()) {
					return this.getDiscountSum() === 0;
				} else if (this.isDiscountUndefined()) {
					return true;
				}

				return false;
			}
		}, {
			key: "getTaxIncluded",
			value: function getTaxIncluded() {
				return this.getField('TAX_INCLUDED', 'N');
			}
		}, {
			key: "isTaxIncluded",
			value: function isTaxIncluded() {
				return this.getTaxIncluded() === 'Y';
			}
		}, {
			key: "getTaxRate",
			value: function getTaxRate() {
				return this.getField('TAX_RATE', 0);
			}
		}, {
			key: "getTaxSum",
			value: function getTaxSum() {
				return this.isTaxIncluded() ? this.getPrice() * this.getQuantity() * (1 - 1 / (1 + this.getTaxRate() / 100)) : this.getPriceExclusive() * this.getQuantity() * this.getTaxRate() / 100;
			}
		}, {
			key: "getTaxNode",
			value: function getTaxNode() {
				return this.getNode().querySelector('select[data-field-code="TAX_RATE"]');
			}
		}, {
			key: "getTaxId",
			value: function getTaxId() {
				var taxNode = this.getTaxNode();

				if (main_core.Type.isDomNode(taxNode) && taxNode.options[taxNode.selectedIndex]) {
					return main_core.Text.toNumber(taxNode.options[taxNode.selectedIndex].getAttribute('data-tax-id'));
				}

				return 0;
			}
		}, {
			key: "updateFieldByEvent",
			value: function updateFieldByEvent(fieldCode, event) {
				var target = event.target;
				var value = target.type === 'checkbox' ? target.checked : target.value;
				var mode = event.type === 'input' ? MODE_EDIT : MODE_SET;
				this.updateField(fieldCode, value, mode);
			}
		}, {
			key: "updateField",
			value: function updateField(fieldCode, value) {
				var mode = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : MODE_SET;
				this.resetExternalActions();
				this.updateFieldValue(fieldCode, value, mode);
				this.executeExternalActions();
			}
		}, {
			key: "updateFieldValue",
			value: function updateFieldValue(code, value) {
				var mode = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : MODE_SET;

				switch (code) {
					case 'ID':
					case 'OFFER_ID':
						this.changeProductId(value);
						break;

					case 'ENTERED_PRICE':
					case 'PRICE':
						this.changeEnteredPrice(value, mode);
						break;

					case 'CATALOG_PRICE':
						this.changeCatalogPrice(value, mode);
						break;

					case 'QUANTITY':
						this.changeQuantity(value, mode);
						break;

					case 'MEASURE_CODE':
						this.changeMeasureCode(value, mode);
						break;

					case 'DISCOUNT':
					case 'DISCOUNT_PRICE':
						this.changeDiscount(value, mode);
						break;

					case 'DISCOUNT_TYPE_ID':
						this.changeDiscountType(value);
						break;

					case 'DISCOUNT_ROW':
						this.changeRowDiscount(value, mode);
						break;

					case 'VAT_ID':
					case 'TAX_ID':
						this.changeTaxId(value);
						break;

					case 'TAX_RATE':
						this.changeTaxRate(value);
						break;

					case 'VAT_INCLUDED':
					case 'TAX_INCLUDED':
						this.changeTaxIncluded(value);
						break;

					case 'SUM':
						this.changeRowSum(value, mode);
						break;

					case 'NAME':
					case 'PRODUCT_NAME':
					case 'MAIN_INFO':
						this.changeProductName(value);
						break;

					case 'SORT':
						this.changeSort(value, mode);
						break;

					case 'STORE_ID':
						this.changeStore(value);
						break;

					case 'STORE_TITLE':
						this.changeStoreName(value);
						break;

					case 'INPUT_RESERVE_QUANTITY':
						this.changeReserveQuantity(value);
						break;

					case 'DATE_RESERVE_END':
						this.changeDateReserveEnd(value);
						break;

					case 'BASE_PRICE':
						this.setBasePrice(value);
						break;

					case 'DEDUCTED_QUANTITY':
						this.setDeductedQuantity(value);
						break;

					case 'ROW_RESERVED':
						this.setRowReserved(value);
						break;

					case 'SKU_TREE':
					case 'DETAIL_URL':
					case 'IMAGE_INFO':
					case 'COMMON_STORE_AMOUNT':
						this.setField(code, value);
						break;

					case 'UF_NEED_ADJUSTMENT':
						this.changeAdjustment(value, mode);
						break;

					case 'UF_APPLICATION_PRICE':
						this.setApplicationPrice(value, mode);
						break;

					case 'UF_PURCHASE_PRICE':
						this.setPurchasePrice(value, mode);
						break;

					case 'MINIMAL_PRICE':
						this.setMinimalPrice(value, mode);
						break;
				}
			}
		}, {
			key: "updateFieldByName",
			value: function updateFieldByName(field, value) {
				switch (field) {
					case 'TAX_INCLUDED':
						this.setTaxIncluded(value);
						break;
				}
			}
		}, {
			key: "handleCopyAction",
			value: function handleCopyAction(event, menuItem) {
				var _this$getEditor;

				(_this$getEditor = this.getEditor()) === null || _this$getEditor === void 0 ? void 0 : _this$getEditor.copyRow(this);
				var menu = menuItem.getMenuWindow();

				if (menu) {
					menu.destroy();
				}
			}
		}, {
			key: "handleDeleteAction",
			value: function handleDeleteAction(event, menuItem) {
				var _this$getEditor3;

				if (!this.isAdjusted() && this.getField('UF_NEED_ADJUSTMENT') == 1) {
					var adjustedRow = this.getAdjustedProductRow();

					if (adjustedRow) {
						var _this$getEditor2;

						(_this$getEditor2 = this.getEditor()) === null || _this$getEditor2 === void 0 ? void 0 : _this$getEditor2.deleteRow(adjustedRow.getField('ID'));
					}
				}
				/*if ((this.getField('UF_APPLICATION_PARENT_PRODUCT_ROW_ID', 0)) != 0) {
	      	ProductApplication.unsetCheckbox(this.getId().replace(this.getEditor().getRowIdPrefix(), ''));
	      }*/


				(_this$getEditor3 = this.getEditor()) === null || _this$getEditor3 === void 0 ? void 0 : _this$getEditor3.deleteRow(this.getField('ID'));
				var menu = menuItem.getMenuWindow();

				if (menu) {
					menu.destroy();
				}
			}
		}, {
			key: "showProductApplication",
			value: function showProductApplication() {
				if (this._productApplication === null) {
					this._productApplication = new ProductApplication(this);
				}

				this._productApplication.showPopup();
			}
		}, {
			key: "changeProductId",
			value: function changeProductId(value) {
				var preparedValue = this.parseInt(value);
				this.setProductId(preparedValue);

				if (!this.isAdjusted()) {
					var adjustedRow = this.getAdjustedProductRow();

					if (adjustedRow) {
						adjustedRow.setProductId(value);
						var productName = this.getField('NAME', '');
						adjustedRow.mainSelector.searchInput.onChangeValue(productName);
						adjustedRow.changeProductName(productName);
					}
				}

				this.getEditor().deleteApplicationsForRow(this.getField('ID'));

				if (this._productApplication) {
					this._productApplication.destroy();

					this._productApplication = null;
				}
			}
		}, {
			key: "changeEnteredPrice",
			value: function changeEnteredPrice(value) {
				var mode = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : MODE_SET;
				var originalPrice = value;
				var minimalPriceAvailable = false;

				if (BX.Crm.EntityEditor.defaultInstance.getOwnerInfo().ownerType.startsWith('DEAL')) {
					minimalPriceAvailable = BX.Crm.EntityEditor.defaultInstance.getModel().getField("UF_AVAILABLE_MINIMAL_PRICE").VALUE == "0";
				} // price can't be less than zero


				if (minimalPriceAvailable && this.getField('MINIMAL_PRICE')) {
					value = Math.max(value, this.getField('MINIMAL_PRICE'), 0);
				} else {
					value = Math.max(value, 0);
				}

				var preparedValue = this.parseFloat(value, this.getPricePrecision());
				this.setField('ENTERED_PRICE', preparedValue);

				if (mode === MODE_EDIT && originalPrice >= 0) {
					if (!_classPrivateMethodGet$3(this, _isEditableCatalogPrice, _isEditableCatalogPrice2)) {
						return;
					}

					if (this.getModel().isCatalogExisted() && _classPrivateMethodGet$3(this, _isSaveableCatalogPrice, _isSaveableCatalogPrice2).call(this)) {
						_classPrivateMethodGet$3(this, _showPriceNotifier, _showPriceNotifier2).call(this, preparedValue);
					} else {
						this.setBasePrice(preparedValue, mode);
					}

					this.addActionProductChange();
					this.addActionUpdateTotal();
					this.addActionDisableSaveButton();
				} else {
					this.refreshFieldsLayout();
				}

				_classPrivateMethodGet$3(this, _togglePriceHintPopup, _togglePriceHintPopup2).call(this, originalPrice < 0 && originalPrice !== value);

				if (!(originalPrice < 0 && originalPrice !== value) && minimalPriceAvailable) {
					_classPrivateMethodGet$3(this, _toggleMinimalPricePopup, _toggleMinimalPricePopup2).call(this, originalPrice < this.getField('MINIMAL_PRICE'));
				}
			}
		}, {
			key: "changeCatalogPrice",
			value: function changeCatalogPrice(value) {
				var preparedValue = this.parseFloat(value, this.getPricePrecision());
				this.setField('CATALOG_PRICE', preparedValue);
				this.refreshFieldsLayout();
			}
		}, {
			key: "getAdjustedProductRow",
			value: function getAdjustedProductRow() {
				if (this.getField('UF_NEED_ADJUSTMENT')) {
					return this.getEditor().getAdjustedProductRow(this.getField('SORT'));
				} else {
					return null;
				}
			}
		}, {
			key: "changeAdjustment",
			value: function changeAdjustment(value) {
				var mode = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : MODE_SET;
				value = +value;
				this.setField('UF_NEED_ADJUSTMENT', value);

				if (value && mode == MODE_SET) {
					var productId = this.getEditor().copyRowAndReturnProductId(this);
					var adjustedProduct = this.getEditor().getProductById(productId);
					adjustedProduct.setField('UF_NEED_ADJUSTMENT', 0);
					adjustedProduct.setField('UF_CRM_PR_ADJUSTMENT', 1);
					adjustedProduct.setQuantity(0);
					adjustedProduct.setDiscount(100, catalog_productCalculator.DiscountType.PERCENTAGE);
					adjustedProduct.disableUiField('UF_NEED_ADJUSTMENT', true);
					adjustedProduct.updateUiCheckboxField('UF_CRM_PR_ADJUSTMENT', 'Y');
					adjustedProduct.updateUiInputField('PRICE', adjustedProduct.getPrice());
				} else if (!value && mode == MODE_SET) {
					var _adjustedProduct = this.getEditor().getAdjustedProductRow(this.getField('SORT'));

					if (_adjustedProduct && _adjustedProduct.getField('UF_APPLICATION_PARENT_PRODUCT_ROW_ID', 0) == 0) {
						this.getEditor().deleteRow(_adjustedProduct.getField('ID'));
					}
				}
			}
		}, {
			key: "setApplicationPrice",
			value: function setApplicationPrice(value) {
				// price can't be less than zero
				value = Number.parseFloat(value);
				value = Math.max(value, 0);
				var preparedValue = this.parseFloat(value, this.getPricePrecision());
				this.setField('UF_APPLICATION_PRICE', preparedValue);
				this.updateUiInputField('UF_APPLICATION_PRICE', preparedValue);
				this.addActionProductChange();
				this.addActionUpdateTotal();
				this.addActionDisableSaveButton();
			}
		}, {
			key: "setPurchasePrice",
			value: function setPurchasePrice(value) {
				// price can't be less than zero
				value = Number.parseFloat(value);
				value = Math.max(value, 0);
				var preparedValue = this.parseFloat(value, this.getPricePrecision());
				this.setField('UF_PURCHASE_PRICE', preparedValue);
				this.updateUiInputField('UF_PURCHASE_PRICE', preparedValue);
				this.addActionProductChange();
			}
		}, {
			key: "setMinimalPrice",
			value: function setMinimalPrice(value) {
				// price can't be less than zero
				value = Number.parseFloat(value);
				value = Math.max(value, 0);
				var preparedValue = this.parseFloat(value, this.getPricePrecision());
				this.setField('MINIMAL_PRICE', preparedValue);
			}
		}, {
			key: "changeQuantity",
			value: function changeQuantity(value) {
				var mode = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : MODE_SET;
				var preparedValue = this.parseFloat(value, this.getQuantityPrecision());
				this.setQuantity(preparedValue, mode);
			}
		}, {
			key: "changeMeasureCode",
			value: function changeMeasureCode(value) {
				var _this3 = this;

				var mode = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : MODE_SET;
				this.getEditor().getMeasures().filter(function (item) {
					return item.CODE === value;
				}).forEach(function (item) {
					return _this3.setMeasure(item, mode);
				});
			}
		}, {
			key: "changeDiscount",
			value: function changeDiscount(value) {
				var mode = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : MODE_SET;
				var preparedValue;

				if (this.isDiscountPercentage()) {
					preparedValue = this.parseFloat(value, this.getCommonPrecision());
				} else {
					preparedValue = this.parseFloat(value, this.getPricePrecision()).toFixed(this.getPricePrecision());
				}

				this.setDiscount(preparedValue, mode);
			}
		}, {
			key: "changeDiscountType",
			value: function changeDiscountType(value) {
				var preparedValue = this.parseInt(value, catalog_productCalculator.DiscountType.UNDEFINED);
				this.setDiscountType(preparedValue);
			}
		}, {
			key: "changeRowDiscount",
			value: function changeRowDiscount(value) {
				var mode = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : MODE_SET;
				var preparedValue = this.parseFloat(value, this.getPricePrecision());
				this.setRowDiscount(preparedValue, mode);
			}
		}, {
			key: "changeTaxId",
			value: function changeTaxId(value) {
				var taxList = this.getEditor().getTaxList();

				if (main_core.Type.isArrayFilled(taxList)) {
					var taxRate = taxList.find(function (item) {
						return parseInt(item.ID) === parseInt(value);
					});

					if (taxRate) {
						this.changeTaxRate(this.parseFloat(taxRate.VALUE));
					}
				}
			}
		}, {
			key: "changeTaxRate",
			value: function changeTaxRate(value) {
				var preparedValue = this.parseFloat(value, this.getCommonPrecision());
				this.setTaxRate(preparedValue);
			}
		}, {
			key: "changeTaxIncluded",
			value: function changeTaxIncluded(value) {
				if (main_core.Type.isBoolean(value)) {
					value = value ? 'Y' : 'N';
				}

				this.setTaxIncluded(value);
			}
		}, {
			key: "changeRowSum",
			value: function changeRowSum(value) {
				var mode = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : MODE_SET;
				var preparedValue = this.parseFloat(value, this.getPricePrecision());
				this.setRowSum(preparedValue, mode);
			}
		}, {
			key: "changeProductName",
			value: function changeProductName(value) {
				var preparedValue = value.toString();
				var isChangedValue = this.getField('PRODUCT_NAME') !== preparedValue;

				if (isChangedValue) {
					this.setField('PRODUCT_NAME', preparedValue);
					this.setField('NAME', preparedValue);
					this.addActionProductChange();

					if (!this.isAdjusted()) {
						var adjustedRow = this.getAdjustedProductRow();

						if (adjustedRow) {
							// adjustedRow.changeProductName(value);
							adjustedRow.mainSelector.searchInput.onChangeValue(value);
							adjustedRow.changeProductName(value);
						}
					}

					this.getEditor().deleteApplicationsForRow(this.getField('ID'));
				}
			}
		}, {
			key: "changeSort",
			value: function changeSort(value) {
				var mode = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : MODE_SET;
				var preparedValue = this.parseInt(value);

				if (mode === MODE_SET) {
					this.setField('SORT', preparedValue);
				}

				var isChangedValue = this.getField('SORT') !== preparedValue;

				if (isChangedValue) {
					this.addActionProductChange();
				}
			}
		}, {
			key: "changeStore",
			value: function changeStore(value) {
				if (this.isReserveBlocked()) {
					return;
				}

				var preparedValue = main_core.Text.toNumber(value);

				if (this.getField('STORE_ID') === preparedValue) {
					return;
				}

				this.setField('STORE_ID', preparedValue);
				this.setField('STORE_AVAILABLE', this.model.getStoreCollection().getStoreAvailableAmount(value));
				this.updateUiStoreAmountData();
				this.addActionProductChange();
			}
		}, {
			key: "updateUiStoreAmountData",
			value: function updateUiStoreAmountData() {
				var _this$editor$getDefau;

				var availableWrapper = _classPrivateMethodGet$3(this, _getNodeChildByDataName, _getNodeChildByDataName2).call(this, 'STORE_AVAILABLE');

				if (!main_core.Type.isDomNode(availableWrapper)) {
					return;
				}

				var storeId = this.getField('STORE_ID');
				var available = this.model.getStoreCollection().getStoreAvailableAmount(storeId);
				var measureName = main_core.Type.isStringFilled(this.model.getField('MEASURE_NAME')) ? this.model.getField('MEASURE_NAME') : ((_this$editor$getDefau = this.editor.getDefaultMeasure()) === null || _this$editor$getDefau === void 0 ? void 0 : _this$editor$getDefau.SYMBOL) || '';

				if (!this.getModel().isCatalogExisted()) {
					availableWrapper.innerHTML = '';
				} else {
					availableWrapper.innerHTML = main_core.Text.toNumber(available) + ' ' + main_core.Text.encode(measureName);
				}
			}
		}, {
			key: "setRowReserved",
			value: function setRowReserved(value) {
				var _this$editor$getDefau2;

				this.setField('ROW_RESERVED', value);

				var reserveWrapper = _classPrivateMethodGet$3(this, _getNodeChildByDataName, _getNodeChildByDataName2).call(this, 'ROW_RESERVED');

				if (!main_core.Type.isDomNode(reserveWrapper)) {
					return;
				}

				if (!this.getModel().isCatalogExisted()) {
					reserveWrapper.innerHTML = '';
					return;
				}

				var measureName = main_core.Type.isStringFilled(this.model.getField('MEASURE_NAME')) ? this.model.getField('MEASURE_NAME') : ((_this$editor$getDefau2 = this.editor.getDefaultMeasure()) === null || _this$editor$getDefau2 === void 0 ? void 0 : _this$editor$getDefau2.SYMBOL) || '';
				reserveWrapper.innerHTML = main_core.Text.toNumber(this.getField('ROW_RESERVED')) + ' ' + main_core.Text.encode(measureName);
			}
		}, {
			key: "setDeductedQuantity",
			value: function setDeductedQuantity(value) {
				var _this$editor$getDefau3;

				this.setField('DEDUCTED_QUANTITY', value);

				var deductedWrapper = _classPrivateMethodGet$3(this, _getNodeChildByDataName, _getNodeChildByDataName2).call(this, 'DEDUCTED_QUANTITY');

				if (!main_core.Type.isDomNode(deductedWrapper)) {
					return;
				}

				if (!this.getModel().isCatalogExisted()) {
					deductedWrapper.innerHTML = '';
					return;
				}

				var measureName = main_core.Type.isStringFilled(this.model.getField('MEASURE_NAME')) ? this.model.getField('MEASURE_NAME') : ((_this$editor$getDefau3 = this.editor.getDefaultMeasure()) === null || _this$editor$getDefau3 === void 0 ? void 0 : _this$editor$getDefau3.SYMBOL) || '';
				deductedWrapper.innerHTML = main_core.Text.toNumber(this.getField('DEDUCTED_QUANTITY')) + ' ' + main_core.Text.encode(measureName);
			}
		}, {
			key: "changeStoreName",
			value: function changeStoreName(value) {
				var preparedValue = value.toString();
				this.setField('STORE_TITLE', preparedValue);
				this.addActionProductChange();
			}
		}, {
			key: "changeDateReserveEnd",
			value: function changeDateReserveEnd(value) {
				var preparedValue = main_core.Type.isNil(value) ? '' : value.toString();
				this.setField('DATE_RESERVE_END', preparedValue);
				this.addActionProductChange();
			}
		}, {
			key: "changeReserveQuantity",
			value: function changeReserveQuantity(value) {
				var preparedValue = main_core.Text.toNumber(value);
				var reserveDifference = preparedValue - this.getField('INPUT_RESERVE_QUANTITY');

				if (reserveDifference == 0 || isNaN(reserveDifference)) {
					return;
				}

				var newReserve = this.getField('ROW_RESERVED') + reserveDifference;
				this.setField('ROW_RESERVED', newReserve);
				this.setField('RESERVE_QUANTITY', Math.max(newReserve, 0));
				this.setField('INPUT_RESERVE_QUANTITY', preparedValue);
				this.addActionProductChange();
			}
		}, {
			key: "resetReserveFields",
			value: function resetReserveFields() {
				this.setField('ROW_RESERVED', null);
				this.setField('RESERVE_QUANTITY', null);
				this.setField('INPUT_RESERVE_QUANTITY', null);
			}
		}, {
			key: "refreshFieldsLayout",
			value: function refreshFieldsLayout() {
				var exceptFields = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : [];

				for (var field in this.fields) {
					if (this.fields.hasOwnProperty(field) && !exceptFields.includes(field)) {
						this.updateUiField(field, this.fields[field]);
					}
				}
			}
		}, {
			key: "getCalculator",
			value: function getCalculator() {
				return this.getModel().getCalculator().setFields(this.getCalculateFields()).setSettings(this.getEditor().getSettings());
			}
		}, {
			key: "setModel",
			value: function setModel() {
				var fields = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
				var settings = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
				var selectorId = settings.selectorId;

				if (selectorId) {
					var model = catalog_productModel.ProductModel.getById(selectorId);

					if (model) {
						this.model = model;
					}
				}

				if (!this.model) {
					this.model = new catalog_productModel.ProductModel({
						id: selectorId,
						currency: this.getEditor().getCurrencyId(),
						iblockId: fields['IBLOCK_ID'],
						basePriceId: fields['BASE_PRICE_ID'],
						isSimpleModel: main_core.Text.toInteger(fields['PRODUCT_ID']) <= 0 && main_core.Type.isStringFilled(fields['NAME']),
						skuTree: main_core.Type.isStringFilled(fields['SKU_TREE']) ? JSON.parse(fields['SKU_TREE']) : null,
						fields: fields
					});
					var imageInfo = main_core.Type.isStringFilled(fields['IMAGE_INFO']) ? JSON.parse(fields['IMAGE_INFO']) : null;

					if (main_core.Type.isObject(imageInfo)) {
						this.model.getImageCollection().setPreview(imageInfo['preview']);
						this.model.getImageCollection().setEditInput(imageInfo['input']);
						this.model.getImageCollection().setMorePhotoValues(imageInfo['values']);
					}

					if (!main_core.Type.isNil(fields['DETAIL_URL'])) {
						this.model.setDetailPath(fields['DETAIL_URL']);
					}
				}

				if (_classPrivateMethodGet$3(this, _isReserveEqualProductQuantity, _isReserveEqualProductQuantity2).call(this)) {
					if (!this.getModel().getField('DATE_RESERVE_END')) {
						this.setField('DATE_RESERVE_END', this.editor.getSettingValue('defaultDateReservation'));
					}
				}

				main_core_events.EventEmitter.subscribe(this.model, 'onErrorsChange', this.handleProductErrorsChange);
				main_core_events.EventEmitter.subscribe(this.model, 'onChangeStoreData', this.handleChangeStoreData);
			}
		}, {
			key: "getModel",
			value: function getModel() {
				return this.model;
			}
		}, {
			key: "setProductId",
			value: function setProductId(value) {
				var _this4 = this;

				var isChangedValue = this.getField('PRODUCT_ID') !== value;

				if (isChangedValue) {
					var _this$storeSelector;

					this.getModel().setOption('isSimpleModel', value <= 0 && main_core.Type.isStringFilled(this.getField('NAME')));
					this.setField('PRODUCT_ID', value, false);
					this.setField('OFFER_ID', value, false);
					(_this$storeSelector = this.storeSelector) === null || _this$storeSelector === void 0 ? void 0 : _this$storeSelector.setProductId(value);
					this.addActionProductChange();
					this.addActionUpdateTotal();

					if (_classPrivateMethodGet$3(this, _isReserveEqualProductQuantity, _isReserveEqualProductQuantity2).call(this)) {
						if (!this.getModel().getField('DATE_RESERVE_END')) {
							this.setField('DATE_RESERVE_END', this.editor.getSettingValue('defaultDateReservation'));
						}

						this.resetReserveFields();

						this.onAfterExecuteExternalActions = function () {
							var _this4$reserveControl;

							(_this4$reserveControl = _this4.reserveControl) === null || _this4$reserveControl === void 0 ? void 0 : _this4$reserveControl.setReservedQuantity(_this4.getField('QUANTITY'), true);
						};
					}

					if (!this.isAdjusted()) {
						var adjustedRow = this.getAdjustedProductRow();

						if (adjustedRow) {
							adjustedRow.setProductId(value);
							adjustedRow.changeProductName(this.getField('NAME'));
							adjustedRow.updateUiInputField(['NAME']);
						}
					}

					this.getEditor().deleteApplicationsForRow(this.getField('ID'));
				}
			}
		}, {
			key: "isAdjusted",
			value: function isAdjusted() {
				return !!+this.getField('UF_CRM_PR_ADJUSTMENT');
			}
		}, {
			key: "setPrice",
			value: function setPrice(value) {
				var originalPrice = value; // price can't be less than zero

				value = Math.max(value, 0);
				var calculatedFields = this.getCalculator().setFields(this.getCalculator().calculateBasePrice(this.getBasePrice())).calculatePrice(value);
				delete calculatedFields['BASE_PRICE'];
				this.setFields(calculatedFields);
				this.refreshFieldsLayout(['PRICE_NETTO', 'PRICE_BRUTTO']);
				this.addActionProductChange();
				this.addActionUpdateTotal();
				this.executeExternalActions();

				_classPrivateMethodGet$3(this, _togglePriceHintPopup, _togglePriceHintPopup2).call(this, originalPrice < 0 && originalPrice !== value);
			}
		}, {
			key: "setBasePrice",
			value: function setBasePrice(value) {
				var mode = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : MODE_SET;
				var originalPrice = value; // price can't be less than zero

				value = Math.max(value, 0);

				if (mode === MODE_SET) {
					this.updateUiInputField('PRICE', value.toFixed(this.getPricePrecision()));
				}

				var isChangedValue = this.getBasePrice() !== value;

				if (isChangedValue) {
					var calculatedFields = this.getCalculator().calculateBasePrice(value);
					this.setFields(calculatedFields);
					var exceptFieldNames = mode === MODE_EDIT ? ['BASE_PRICE', 'PRICE', 'ENTERED_PRICE'] : [];
					this.refreshFieldsLayout(exceptFieldNames);
					this.addActionProductChange();
					this.addActionUpdateTotal();
				}

				_classPrivateMethodGet$3(this, _togglePriceHintPopup, _togglePriceHintPopup2).call(this, originalPrice < 0 && originalPrice !== value);
			}
		}, {
			key: "setQuantity",
			value: function setQuantity(value) {
				var mode = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : MODE_SET;

				if (mode === MODE_SET) {
					this.updateUiInputField('QUANTITY', value);
				}

				var isChangedValue = this.getField('QUANTITY') !== value;

				if (isChangedValue) {
					var errorNotifyId = 'quantityReservedCountError';
					var notify = BX.UI.Notification.Center.getBalloonById(errorNotifyId);

					if (notify) {
						notify.close();
					}

					var calculatedFields = this.getCalculator().calculateQuantity(value);
					this.setFields(calculatedFields);
					this.refreshFieldsLayout(['QUANTITY']);
					this.addActionProductChange();
					this.addActionUpdateTotal();
				}
			}
		}, {
			key: "setReserveQuantity",
			value: function setReserveQuantity(value) {
				var node = _classPrivateMethodGet$3(this, _getNodeChildByDataName, _getNodeChildByDataName2).call(this, 'RESERVE_INFO');

				var input = node === null || node === void 0 ? void 0 : node.querySelector('input[name="INPUT_RESERVE_QUANTITY"]');

				if (main_core.Type.isElementNode(input)) {
					var _this$reserveControl;

					input.value = value;
					(_this$reserveControl = this.reserveControl) === null || _this$reserveControl === void 0 ? void 0 : _this$reserveControl.changeInputValue(value);
				} else {
					this.changeReserveQuantity(value);
				}
			}
		}, {
			key: "setMeasure",
			value: function setMeasure(measure) {
				var _this5 = this;

				var mode = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : MODE_SET;
				this.setField('MEASURE_CODE', measure.CODE);
				this.setField('MEASURE_NAME', measure.SYMBOL);
				this.updateUiMoneyField('MEASURE_CODE', measure.CODE, main_core.Text.encode(measure.SYMBOL));

				if (this.getModel().isNew()) {
					this.getModel().save(['MEASURE_CODE']);
				} else if (mode === MODE_EDIT) {
					this.getModel().showSaveNotifier('measureChanger_' + this.getId(), {
						title: main_core.Loc.getMessage('CATALOG_PRODUCT_MODEL_SAVING_NOTIFICATION_MEASURE_CHANGED_QUERY'),
						events: {
							onSave: function onSave() {
								_this5.getModel().save(['MEASURE_CODE', 'MEASURE_NAME']);
							}
						}
					});
				}

				this.addActionProductChange();
			}
		}, {
			key: "setDiscount",
			value: function setDiscount(value) {
				var mode = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : MODE_SET;

				if (!this.isDiscountHandmade()) {
					return;
				}

				var fieldName = this.isDiscountPercentage() ? 'DISCOUNT_RATE' : 'DISCOUNT_SUM';
				var isChangedValue = this.getField(fieldName) !== value;

				if (isChangedValue) {
					var calculatedFields = this.getCalculator().calculateDiscount(value);
					this.setFields(calculatedFields);
					var exceptFieldNames = mode === MODE_EDIT ? ['DISCOUNT_RATE', 'DISCOUNT_SUM', 'DISCOUNT'] : [];
					this.refreshFieldsLayout(exceptFieldNames);
					this.addActionProductChange();
					this.addActionUpdateTotal();
				}

				_classPrivateMethodGet$3(this, _togglePriceHintPopup, _togglePriceHintPopup2).call(this);
			}
		}, {
			key: "setDiscountType",
			value: function setDiscountType(value) {
				var isChangedValue = value !== catalog_productCalculator.DiscountType.UNDEFINED && this.getField('DISCOUNT_TYPE_ID') !== value;

				if (isChangedValue) {
					var calculatedFields = this.getCalculator().calculateDiscountType(value);
					this.setFields(calculatedFields);
					this.refreshFieldsLayout();
					this.addActionProductChange();
					this.addActionUpdateTotal();
				}
			}
		}, {
			key: "setRowDiscount",
			value: function setRowDiscount(value) {
				var mode = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : MODE_SET;
				var isChangedValue = this.getField('DISCOUNT_ROW') !== value;

				if (isChangedValue) {
					var calculatedFields = this.getCalculator().calculateRowDiscount(value);
					this.setFields(calculatedFields);
					var exceptFieldNames = mode === MODE_EDIT ? ['DISCOUNT_ROW'] : [];
					this.refreshFieldsLayout(exceptFieldNames);
					this.addActionProductChange();
					this.addActionUpdateTotal();
				}
			}
		}, {
			key: "setTaxRate",
			value: function setTaxRate(value) {
				if (!this.getEditor().isTaxAllowed()) {
					return;
				}

				var isChangedValue = this.getTaxRate() !== value;

				if (isChangedValue) {
					var calculatedFields = this.getCalculator().calculateTax(value);
					this.setFields(calculatedFields);
					this.refreshFieldsLayout();
					this.addActionProductChange();
					this.addActionUpdateTotal();
				}
			}
		}, {
			key: "setTaxIncluded",
			value: function setTaxIncluded(value) {
				var mode = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : MODE_SET;

				if (!this.getEditor().isTaxAllowed()) {
					return;
				}

				if (mode === MODE_SET) {
					this.updateUiCheckboxField('TAX_INCLUDED', value);
				}

				var isChangedValue = this.getTaxIncluded() !== value;

				if (isChangedValue) {
					var calculatedFields = this.getCalculator().calculateTaxIncluded(value);
					this.setFields(calculatedFields);
					this.refreshFieldsLayout();
					this.addActionUpdateFieldList('TAX_INCLUDED', value);
					this.addActionProductChange();
					this.addActionUpdateTotal();
				}
			}
		}, {
			key: "setRowSum",
			value: function setRowSum(value) {
				var mode = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : MODE_SET;
				var isChangedValue = this.getField('SUM') !== value;

				if (isChangedValue) {
					var calculatedFields = this.getCalculator().calculateRowSum(value);
					this.setFields(calculatedFields);
					var exceptFieldNames = mode === MODE_EDIT ? ['SUM'] : [];
					this.refreshFieldsLayout(exceptFieldNames);
					this.addActionProductChange();
					this.addActionUpdateTotal();
				}
			} // controls

		}, {
			key: "getInputByFieldName",
			value: function getInputByFieldName(fieldName) {
				var fieldId = this.getUiFieldId(fieldName);
				var item = document.getElementById(fieldId);

				if (!main_core.Type.isElementNode(item)) {
					item = this.getNode().querySelector('[name="' + fieldId + '"]');
				}

				return item;
			}
		}, {
			key: "updateUiInputField",
			value: function updateUiInputField(name, value) {
				var item = this.getInputByFieldName(name);

				if (main_core.Type.isElementNode(item)) {
					item.value = value;
				}
			}
		}, {
			key: "updateUiCheckboxField",
			value: function updateUiCheckboxField(name, value) {
				var item = this.getInputByFieldName(name);

				if (main_core.Type.isElementNode(item)) {
					item.checked = value === 'Y';
				}
			}
		}, {
			key: "disableUiField",
			value: function disableUiField(name) {
				var disabled = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : true;
				var item = this.getInputByFieldName(name);

				if (main_core.Type.isElementNode(item)) {
					item.disabled = disabled;
				}
			}
		}, {
			key: "updateUiDiscountTypeField",
			value: function updateUiDiscountTypeField(name, value) {
				var text = value === catalog_productCalculator.DiscountType.MONETARY ? this.getEditor().getCurrencyText() : '%';
				this.updateUiMoneyField(name, value, text);
			}
		}, {
			key: "getMoneyFieldDropdownApi",
			value: function getMoneyFieldDropdownApi(name) {
				if (!main_core.Reflection.getClass('BX.Main.dropdownManager')) {
					return null;
				}

				return BX.Main.dropdownManager.getById(this.getId() + '_' + name + '_control');
			}
		}, {
			key: "updateMoneyFieldUiWithDropdownApi",
			value: function updateMoneyFieldUiWithDropdownApi(dropdown, value) {
				if (dropdown.getValue() === value) {
					return;
				}

				var item = dropdown.menu.itemsContainer.querySelector('[data-value="' + value + '"]');
				var menuItem = item && dropdown.getMenuItem(item);

				if (menuItem) {
					dropdown.refresh(menuItem);
					dropdown.selectItem(menuItem);
				}
			}
		}, {
			key: "updateMoneyFieldUiManually",
			value: function updateMoneyFieldUiManually(name, value, text) {
				var item = this.getInputByFieldName(name);

				if (!main_core.Type.isElementNode(item)) {
					return;
				}

				item.dataset.value = value;
				var span = item.querySelector('span.main-dropdown-inner');

				if (!main_core.Type.isElementNode(span)) {
					return;
				}

				span.innerHTML = text;
			}
		}, {
			key: "updateUiMoneyField",
			value: function updateUiMoneyField(name, value, text) {
				var dropdownApi = this.getMoneyFieldDropdownApi(name);

				if (dropdownApi) {
					this.updateMoneyFieldUiWithDropdownApi(dropdownApi, value);
				} else {
					this.updateMoneyFieldUiManually(name, value, text);
				}
			}
		}, {
			key: "updateUiMeasure",
			value: function updateUiMeasure(code, name) {
				this.updateUiMoneyField('MEASURE_CODE', code, name);
				this.updateUiStoreAmountData();
			}
		}, {
			key: "updateUiHtmlField",
			value: function updateUiHtmlField(name, html) {
				var item = this.getNode().querySelector('[data-name="' + name + '"]');

				if (main_core.Type.isElementNode(item)) {
					item.innerHTML = html;
				}
			}
		}, {
			key: "updateUiCurrencyFields",
			value: function updateUiCurrencyFields() {
				var _this6 = this;

				var currencyText = this.getEditor().getCurrencyText();
				var currencyId = '' + this.getEditor().getCurrencyId();
				var currencyFieldNames = ['PRICE_CURRENCY', 'SUM_CURRENCY', 'DISCOUNT_TYPE_ID', 'DISCOUNT_ROW_CURRENCY'];
				currencyFieldNames.forEach(function (name) {
					var dropdownValues = [];

					if (name === 'DISCOUNT_TYPE_ID') {
						dropdownValues.push({
							NAME: '%',
							VALUE: '' + catalog_productCalculator.DiscountType.PERCENTAGE
						});
						dropdownValues.push({
							NAME: currencyText,
							VALUE: '' + catalog_productCalculator.DiscountType.MONETARY
						});

						if (_this6.getDiscountType() === catalog_productCalculator.DiscountType.MONETARY) {
							_this6.updateMoneyFieldUiManually(name, catalog_productCalculator.DiscountType.MONETARY, currencyText);
						}
					} else {
						dropdownValues.push({
							NAME: currencyText,
							VALUE: currencyId
						});

						_this6.updateUiMoneyField(name, currencyId, currencyText);
					}

					main_core.Dom.attr(_this6.getInputByFieldName(name), 'data-items', dropdownValues);
				});
				this.updateUiField('TAX_SUM', this.getField('TAX_SUM'));
			}
		}, {
			key: "updateUiField",
			value: function updateUiField(field, value) {
				var uiName = this.getUiFieldName(field);

				if (!uiName) {
					return;
				}

				var uiType = this.getUiFieldType(field);

				if (!uiType) {
					return;
				}

				if (!this.allowUpdateUiField(field)) {
					return;
				}

				switch (uiType) {
					case 'input':
						if (field === 'QUANTITY') {
							value = this.parseFloat(value, this.getQuantityPrecision());
						} else if (field === 'DISCOUNT_RATE' || field === 'TAX_RATE') {
							value = this.parseFloat(value, this.getCommonPrecision());
						} else if (value === 0) {
							value = '';
						} else if (main_core.Type.isNumber(value)) {
							value = this.parseFloat(value, this.getPricePrecision()).toFixed(this.getPricePrecision());
						}

						this.updateUiInputField(uiName, value);
						break;

					case 'checkbox':
						this.updateUiCheckboxField(uiName, value);
						break;

					case 'discount_type_field':
						this.updateUiDiscountTypeField(uiName, value);
						break;

					case 'html':
						this.updateUiHtmlField(uiName, value);
						break;

					case 'money_html':
						value = currency_currencyCore.CurrencyCore.currencyFormat(value, this.getEditor().getCurrencyId(), true);
						this.updateUiHtmlField(uiName, value);
						break;
				}
			}
		}, {
			key: "getUiFieldName",
			value: function getUiFieldName(field) {
				var result = null;

				switch (field) {
					case 'QUANTITY':
					case 'MEASURE_CODE':
					case 'DISCOUNT_ROW':
					case 'DISCOUNT_TYPE_ID':
					case 'TAX_RATE':
					case 'TAX_INCLUDED':
					case 'TAX_SUM':
					case 'SUM':
					case 'PRODUCT_NAME':
					case 'SORT':
						result = field;
						break;

					case 'ENTERED_PRICE':
						result = 'PRICE';
						break;

					case 'DISCOUNT_RATE':
					case 'DISCOUNT_SUM':
						result = 'DISCOUNT_PRICE';
						break;
				}

				return result;
			}
		}, {
			key: "getUiFieldType",
			value: function getUiFieldType(field) {
				var result = null;

				switch (field) {
					case 'PRICE':
					case 'ENTERED_PRICE':
					case 'QUANTITY':
					case 'TAX_RATE':
					case 'DISCOUNT_RATE':
					case 'DISCOUNT_SUM':
					case 'DISCOUNT_ROW':
					case 'SUM':
					case 'PRODUCT_NAME':
					case 'SORT':
						result = 'input';
						break;

					case 'DISCOUNT_TYPE_ID':
						result = 'discount_type_field';
						break;

					case 'TAX_INCLUDED':
						result = 'checkbox';
						break;

					case 'TAX_SUM':
						result = 'money_html';
						break;
				}

				return result;
			}
		}, {
			key: "allowUpdateUiField",
			value: function allowUpdateUiField(field) {
				var result = true;

				switch (field) {
					case 'PRICE_NETTO':
						result = this.isPriceNetto();
						break;

					case 'PRICE_BRUTTO':
						result = !this.isPriceNetto();
						break;

					case 'DISCOUNT_RATE':
						result = this.isDiscountPercentage();
						break;

					case 'DISCOUNT_SUM':
						result = this.isDiscountMonetary();
						break;
				}

				return result;
			} // proxy

		}, {
			key: "parseInt",
			value: function parseInt(value) {
				var defaultValue = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 0;
				return this.getEditor().parseInt(value, defaultValue);
			}
		}, {
			key: "parseFloat",
			value: function parseFloat(value, precision) {
				var defaultValue = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 0;
				return this.getEditor().parseFloat(value, precision, defaultValue);
			}
		}, {
			key: "getPricePrecision",
			value: function getPricePrecision() {
				return this.getEditor().getPricePrecision();
			}
		}, {
			key: "getQuantityPrecision",
			value: function getQuantityPrecision() {
				return this.getEditor().getQuantityPrecision();
			}
		}, {
			key: "getCommonPrecision",
			value: function getCommonPrecision() {
				return this.getEditor().getCommonPrecision();
			}
		}, {
			key: "resetExternalActions",
			value: function resetExternalActions() {
				this.externalActions.length = 0;
			}
		}, {
			key: "addExternalAction",
			value: function addExternalAction(action) {
				this.externalActions.push(action);
			}
		}, {
			key: "addActionProductChange",
			value: function addActionProductChange() {
				this.addExternalAction({
					type: this.getEditor().actions.productChange,
					id: this.getId()
				});
			}
		}, {
			key: "addActionDisableSaveButton",
			value: function addActionDisableSaveButton() {
				this.addExternalAction({
					type: this.getEditor().actions.disableSaveButton,
					id: this.getId()
				});
			}
		}, {
			key: "addActionUpdateFieldList",
			value: function addActionUpdateFieldList(field, value) {
				this.addExternalAction({
					type: this.getEditor().actions.updateListField,
					field: field,
					value: value
				});
			}
		}, {
			key: "addActionStateChanged",
			value: function addActionStateChanged() {
				this.addExternalAction({
					type: this.getEditor().actions.stateChanged,
					value: true
				});
			}
		}, {
			key: "addActionStateReset",
			value: function addActionStateReset() {
				this.addExternalAction({
					type: this.getEditor().actions.stateChanged,
					value: false
				});
			}
		}, {
			key: "addActionUpdateTotal",
			value: function addActionUpdateTotal() {
				this.addExternalAction({
					type: this.getEditor().actions.updateTotal
				});
			}
		}, {
			key: "executeExternalActions",
			value: function executeExternalActions() {
				if (this.externalActions.length === 0) {
					return;
				}

				this.getEditor().executeActions(this.externalActions);
				this.resetExternalActions();

				if (this.onAfterExecuteExternalActions) {
					this.onAfterExecuteExternalActions.call();
					this.onAfterExecuteExternalActions = null;
				}
			}
		}, {
			key: "isEmpty",
			value: function isEmpty() {
				return !main_core.Type.isStringFilled(this.getField('PRODUCT_NAME', '').trim()) && this.getField('PRODUCT_ID', 0) <= 0 && this.getPrice() <= 0;
			}
		}, {
			key: "isReserveBlocked",
			value: function isReserveBlocked() {
				return this.getSettingValue('isReserveBlocked', false);
			}
		}]);
		return Row;
	}();

	function _initActions2() {
		var _this7 = this;

		if (this.getEditor().isReadOnly()) {
			return;
		}

		var actionCellContentContainer = this.getNode().querySelector('.main-grid-cell-action .main-grid-cell-content');

		if (main_core.Type.isDomNode(actionCellContentContainer)) {
			var actionsButton = main_core.Tag.render(_templateObject$a || (_templateObject$a = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<a\n\t\t\t\t\thref=\"#\"\n\t\t\t\t\tclass=\"main-grid-row-action-button\"\n\t\t\t\t></a>\n\t\t\t"])));
			main_core.Event.bind(actionsButton, 'click', function (event) {
				var menuItems = [{
					text: main_core.Loc.getMessage('CRM_ENTITY_PL_COPY'),
					onclick: _this7.handleCopyAction.bind(_this7)
				}, {
					text: main_core.Loc.getMessage('CRM_ENTITY_PL_DELETE'),
					onclick: _this7.handleDeleteAction.bind(_this7),
					disabled: _this7.getModel().isEmpty() && _this7.getEditor().products.length <= 1
				}, {
					text: main_core.Loc.getMessage('ADD_APPLICATION'),
					onclick: _this7.showProductApplication.bind(_this7),
					disabled: Number(_this7.getField('UF_APPLICATION_PARENT_PRODUCT_ROW_ID', 0)) != 0 || _this7.getModel().isEmpty() || _this7.isAdjusted()
				}];
				main_popup.PopupMenu.show({
					id: _this7.getId() + '_actions_popup',
					bindElement: actionsButton,
					items: menuItems,
					cacheable: false
				});
				event.preventDefault();
				event.stopPropagation();
			});
			main_core.Dom.append(actionsButton, actionCellContentContainer);
		}
	}

	function _showChangePriceNotify2() {
		var _this8 = this;

		if (this.editor.getSettingValue('disableNotifyChangingPrice')) {
			return;
		}

		var hint = main_core.Text.encode(this.editor.getSettingValue('catalogPriceEditArticleHint'));
		var changePriceNotifyId = 'disabled-crm-changing-price';
		var changePriceNotify = BX.UI.Notification.Center.getBalloonById(changePriceNotifyId);

		if (!changePriceNotify) {
			var content = main_core.Tag.render(_templateObject2$7 || (_templateObject2$7 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<div>\n\t\t\t\t\t<div style=\"padding: 9px\">", "</div>\n\t\t\t\t</div>\n\t\t\t"])), hint);
			var buttonRow = main_core.Tag.render(_templateObject3$7 || (_templateObject3$7 = babelHelpers.taggedTemplateLiteral(["<div></div>"])));
			content.appendChild(buttonRow);
			var articleCode = this.editor.getSettingValue('catalogPriceEditArticleCode');

			if (articleCode) {
				var moreLink = main_core.Tag.render(_templateObject4$6 || (_templateObject4$6 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t\t<span class=\"ui-notification-balloon-action\">\n\t\t\t\t\t\t", "\n\t\t\t\t\t</span>\n\t\t\t\t"])), main_core.Loc.getMessage('CRM_ENTITY_MORE_LINK'));
				main_core.Event.bind(moreLink, 'click', function () {
					top.BX.Helper.show("redirect=detail&code=" + articleCode);
					changePriceNotify.close();
				});
				buttonRow.appendChild(moreLink);
			}

			var disableNotificationLink = main_core.Tag.render(_templateObject5$6 || (_templateObject5$6 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<span class=\"ui-notification-balloon-action\">\n\t\t\t\t\t", "\n\t\t\t\t</span>\n\t\t\t"])), main_core.Loc.getMessage('CRM_ENTITY_DISABLE_NOTIFICATION'));
			main_core.Event.bind(disableNotificationLink, 'click', function () {
				changePriceNotify.close();

				_this8.editor.setSettingValue('disableNotifyChangingPrice', true);

				main_core.ajax.runComponentAction(_this8.editor.getComponentName(), 'setGridSetting', {
					mode: 'class',
					data: {
						signedParameters: _this8.editor.getSignedParameters(),
						settingId: 'DISABLE_NOTIFY_CHANGING_PRICE',
						selected: true
					}
				});
			});
			buttonRow.appendChild(disableNotificationLink);
			var notificationOptions = {
				id: changePriceNotifyId,
				closeButton: true,
				category: Row.CATALOG_PRICE_CHANGING_DISABLED,
				autoHideDelay: 10000,
				content: content
			};
			changePriceNotify = BX.UI.Notification.Center.notify(notificationOptions);
		}

		changePriceNotify.show();
	}

	function _isEditableCatalogPrice2() {
		return this.editor.canEditCatalogPrice() || !this.getModel().isCatalogExisted() || this.getModel().isNew();
	}

	function _isSaveableCatalogPrice2() {
		return this.editor.canSaveCatalogPrice() || this.getModel().isCatalogExisted() && this.getModel().isNew();
	}

	function _initSelector2() {
		var id = 'crm_grid_' + this.getId();
		this.mainSelector = ProductSelector.getById(id);

		if (!this.mainSelector) {
			var selectorOptions = {
				iblockId: this.model.getIblockId(),
				basePriceId: this.model.getBasePriceId(),
				currency: this.model.getCurrency(),
				model: this.model,
				config: {
					ENABLE_SEARCH: true,
					IS_ALLOWED_CREATION_PRODUCT: true,
					ENABLE_IMAGE_INPUT: true,
					ROLLBACK_INPUT_AFTER_CANCEL: true,
					ENABLE_INPUT_DETAIL_LINK: true,
					ROW_ID: this.getId(),
					ENABLE_SKU_SELECTION: true,
					URL_BUILDER_CONTEXT: this.editor.getSettingValue('productUrlBuilderContext')
				},
				mode: ProductSelector.MODE_EDIT
			};
			this.mainSelector = new ProductSelector('crm_grid_' + this.getId(), selectorOptions);
		}

		var mainInfoNode = _classPrivateMethodGet$3(this, _getNodeChildByDataName, _getNodeChildByDataName2).call(this, 'MAIN_INFO');

		if (mainInfoNode) {
			var plusButton = mainInfoNode.querySelector('.main-grid-plus-button');

			if (!main_core.Type.isDomNode(plusButton)) {
				mainInfoNode.appendChild(main_core.Tag.render(_templateObject6$5 || (_templateObject6$5 = babelHelpers.taggedTemplateLiteral(["<span class=\"main-grid-plus-button\"></span>"]))));
			}

			var numberSelector = mainInfoNode.querySelector('.main-grid-row-number');

			if (!main_core.Type.isDomNode(numberSelector)) {
				mainInfoNode.appendChild(main_core.Tag.render(_templateObject7$4 || (_templateObject7$4 = babelHelpers.taggedTemplateLiteral(["<div class=\"main-grid-row-number\"></div>"]))));
			}

			var selectorWrapper = mainInfoNode.querySelector('.main-grid-row-product-selector');

			if (!main_core.Type.isDomNode(selectorWrapper)) {
				selectorWrapper = main_core.Tag.render(_templateObject8$4 || (_templateObject8$4 = babelHelpers.taggedTemplateLiteral(["<div class=\"main-grid-row-product-selector\"></div>"])));
				mainInfoNode.appendChild(selectorWrapper);
			}

			this.mainSelector.skuTreeInstance = null;
			this.mainSelector.renderTo(selectorWrapper);
		}

		main_core_events.EventEmitter.subscribe(this.mainSelector, 'onClear', this.handleMainSelectorClear);
		main_core_events.EventEmitter.subscribe(this.mainSelector, 'onChange', this.handleMainSelectorChange);
	}

	function _onMainSelectorClear2() {
		this.updateField('OFFER_ID', 0);
		this.updateField('PRODUCT_NAME', '');
		this.updateUiStoreAmountData();
		this.updateField('DEDUCTED_QUANTITY', 0);
		this.updateField('ROW_RESERVED', 0);

		if (!this.isAdjusted()) {
			var adjustedRow = this.getAdjustedProductRow();

			if (adjustedRow) {
				_classPrivateMethodGet$3(adjustedRow, _onMainSelectorClear, _onMainSelectorClear2).call(adjustedRow);
			}
		}

		this.getEditor().deleteApplicationsForRow(this.getField('ID'));
	}

	function _onMainSelectorChange2(event) {
		var _item$PROPERTIES,
			_this9 = this;

		var item = event.getData().fields;
		this.updateUiHtmlField('STORE_GENERAL', item.STORE_GENERAL);
		this.updateUiHtmlField('STORE_VIRTUAL', item.STORE_VIRTUAL);
		this.updateUiHtmlField('SUPPLIER_OF_GOODS', item.SUPPLIER_OF_GOODS);
		this.updateUiHtmlField('COMMON_RESERVED', "".concat(item.ROW_RESERVED, " ").concat(item.MEASURE_NAME));
		this.updateUiHtmlField('UF_RESERVE_QUANTITY', "".concat(item.UF_RESERVE_QUANTITY, " ").concat(item.MEASURE_NAME));
		this.updateUiHtmlField('FREE_STORE', "".concat(item.FREE_STORE, " ").concat(item.MEASURE_NAME));
		(_item$PROPERTIES = item.PROPERTIES) === null || _item$PROPERTIES === void 0 ? void 0 : _item$PROPERTIES.forEach(function (property) {
			var _Object$values;

			(_Object$values = Object.values(property.PROPERTY_VALUES)) === null || _Object$values === void 0 ? void 0 : _Object$values.forEach(function (propertyValue) {
				_this9.updateUiHtmlField(property.CODE, propertyValue.VALUE);
			});
		});
	}

	function _initStoreSelector2() {
		this.storeSelector = new catalog_storeSelector.StoreSelector(this.getId(), {
			inputFieldId: 'STORE_ID',
			inputFieldTitle: 'STORE_TITLE',
			config: {
				ENABLE_SEARCH: true,
				ENABLE_INPUT_DETAIL_LINK: false,
				ROW_ID: this.getId()
			},
			mode: catalog_storeSelector.StoreSelector.MODE_EDIT,
			model: this.model
		});

		var storeWrapper = _classPrivateMethodGet$3(this, _getNodeChildByDataName, _getNodeChildByDataName2).call(this, 'STORE_INFO');

		if (this.storeSelector && storeWrapper) {
			storeWrapper.innerHTML = '';
			this.storeSelector.renderTo(storeWrapper);

			if (this.isReserveBlocked()) {
				_classPrivateMethodGet$3(this, _applyStoreSelectorRestrictionTweaks, _applyStoreSelectorRestrictionTweaks2).call(this);
			}
		}

		main_core_events.EventEmitter.subscribe(this.storeSelector, 'onChange', this.handleStoreFieldChange);
		main_core_events.EventEmitter.subscribe(this.storeSelector, 'onClear', this.handleStoreFieldClear);
	}

	function _initStoreAvailablePopup2() {
		var storeAvaiableNode = _classPrivateMethodGet$3(this, _getNodeChildByDataName, _getNodeChildByDataName2).call(this, 'STORE_AVAILABLE');

		if (!storeAvaiableNode) {
			return;
		}

		this.storeAvailablePopup = new StoreAvailablePopup({
			rowId: this.id,
			model: this.getModel(),
			node: storeAvaiableNode
		}); // runs once because after grid update, row re-created.

		main_core_events.EventEmitter.subscribeOnce('Grid::updated', this.handleOnGridUpdated);
	}

	function _applyStoreSelectorRestrictionTweaks2() {
		var storeSearchInput = this.storeSelector.searchInput;

		if (!storeSearchInput || !storeSearchInput.getNameInput()) {
			return;
		}

		storeSearchInput.toggleIcon(this.storeSelector.searchInput.getSearchIcon(), 'none');
		storeSearchInput.getNameInput().disabled = true;
		storeSearchInput.getNameInput().classList.add('crm-entity-product-list-locked-field');

		if (this.storeSelector.getWrapper()) {
			this.storeSelector.getWrapper().onclick = function () {
				return top.BX.UI.InfoHelper.show('limit_store_crm_integration');
			};
		}
	}

	function _initReservedControl2() {
		var _this10 = this;

		var storeWrapper = _classPrivateMethodGet$3(this, _getNodeChildByDataName, _getNodeChildByDataName2).call(this, 'RESERVE_INFO');

		if (storeWrapper) {
			this.reserveControl = new ReserveControl({
				model: this.getModel(),
				isReserveEqualProductQuantity: _classPrivateMethodGet$3(this, _isReserveEqualProductQuantity, _isReserveEqualProductQuantity2).call(this),
				defaultDateReservation: this.editor.getSettingValue('defaultDateReservation'),
				isBlocked: this.isReserveBlocked()
			});
			main_core_events.EventEmitter.subscribe(this.reserveControl, 'onChange', function (event) {
				var item = event.getData();

				_this10.updateField(item.NAME, item.VALUE);
			});
			this.layoutReserveControl();
		}

		var quantityInput = this.getNode().querySelector('div[data-name="QUANTITY"] input');

		if (quantityInput) {
			main_core.Event.bind(quantityInput, 'change', function (event) {
				var _this10$reserveContro;

				var isReserveEqualProductQuantity = _classPrivateMethodGet$3(_this10, _isReserveEqualProductQuantity, _isReserveEqualProductQuantity2).call(_this10) && ((_this10$reserveContro = _this10.reserveControl) === null || _this10$reserveContro === void 0 ? void 0 : _this10$reserveContro.isReserveEqualProductQuantity);

				if (isReserveEqualProductQuantity) {
					_this10.setReserveQuantity(_this10.getField('QUANTITY'));

					return;
				}

				var value = main_core.Text.toNumber(event.target.value);
				var errorNotifyId = 'quantityReservedCountError';
				var notify = BX.UI.Notification.Center.getBalloonById(errorNotifyId);

				if (value < _this10.getField('INPUT_RESERVE_QUANTITY')) {
					if (!notify) {
						var notificationOptions = {
							id: errorNotifyId,
							closeButton: true,
							autoHideDelay: 3000,
							content: main_core.Tag.render(_templateObject9$2 || (_templateObject9$2 = babelHelpers.taggedTemplateLiteral(["<div>", "</div>"])), main_core.Loc.getMessage('CRM_ENTITY_PL_IS_LESS_QUANTITY_THEN_RESERVED'))
						};
						notify = BX.UI.Notification.Center.notify(notificationOptions);
					}

					_this10.setReserveQuantity(_this10.getField('QUANTITY'));

					notify.show();
				}
			});
		}
	}

	function _onStoreFieldChange2(event) {
		var _this11 = this;

		var data = event.getData();
		data.fields.forEach(function (item) {
			_this11.updateField(item.NAME, item.VALUE);
		});
		this.initHandlersForSelectors();
	}

	function _onStoreFieldClear2(event) {
		this.initHandlersForSelectors();
	}

	function _showPriceNotifier2(enteredPrice) {
		var _this12 = this;

		var disabledPriceNotify = BX.UI.Notification.Center.getBalloonByCategory(Row.CATALOG_PRICE_CHANGING_DISABLED);

		if (disabledPriceNotify) {
			disabledPriceNotify.close();
		}

		this.getModel().showSaveNotifier('priceChanger_' + this.getId(), {
			title: main_core.Loc.getMessage('CATALOG_PRODUCT_MODEL_SAVING_NOTIFICATION_PRICE_CHANGED_QUERY'),
			events: {
				onCancel: function onCancel() {
					if (_this12.getBasePrice() > _this12.getEnteredPrice()) {
						_this12.setField('ENTERED_PRICE', _this12.getBasePrice());

						_this12.updateUiInputField('PRICE', _this12.getBasePrice());
					}

					_this12.setPrice(enteredPrice);

					if (_this12.getField('DISCOUNT_SUM') > 0) {
						var settingPopup = _this12.getEditor().getSettingsPopup();

						var setting = settingPopup === null || settingPopup === void 0 ? void 0 : settingPopup.getSetting('DISCOUNTS');

						if (setting && setting.checked === false) {
							settingPopup.requestGridSettings(setting, true);
						}
					}
				},
				onSave: function onSave() {
					_this12.setField('ENTERED_PRICE', enteredPrice);

					_this12.setField('PRICE', enteredPrice);

					_this12.changeCatalogPrice('CATALOG_PRICE', enteredPrice);

					_this12.setBasePrice(enteredPrice);

					_this12.getModel().save(['BASE_PRICE', 'CURRENCY']);

					_this12.refreshFieldsLayout();

					_this12.addActionUpdateTotal();

					_this12.executeExternalActions();
				}
			}
		});
	}

	function _onChangeStoreData2() {
		var storeId = this.getField('STORE_ID');

		if (!this.isReserveBlocked() && this.isNewRow()) {
			var currentAmount = this.getModel().getStoreCollection().getStoreAmount(storeId);

			if (currentAmount <= 0 && this.getModel().isChanged()) {
				var maxStore = this.getModel().getStoreCollection().getMaxFilledStore();

				if (maxStore.AMOUNT > currentAmount && this.storeSelector) {
					this.storeSelector.onStoreSelect(maxStore.STORE_ID, main_core.Text.decode(maxStore.STORE_TITLE));
				}
			}
		}

		this.setField('STORE_AVAILABLE', this.model.getStoreCollection().getStoreAvailableAmount(storeId));
		this.updateUiStoreAmountData();
	}

	function _onProductErrorsChange2() {
		this.getEditor().handleProductErrorsChange();
	}

	function _shouldShowSmallPriceHint2() {
		return main_core.Text.toNumber(this.getField('PRICE')) > 0 && main_core.Text.toNumber(this.getField('PRICE')) < 1 && this.isDiscountPercentage() && (main_core.Text.toNumber(this.getField('DISCOUNT_SUM')) > 0 || main_core.Text.toNumber(this.getField('DISCOUNT_RATE')) > 0 || main_core.Text.toNumber(this.getField('DISCOUNT_ROW')) > 0);
	}

	function _togglePriceHintPopup2() {
		var showNegative = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : false;

		if (_classPrivateMethodGet$3(this, _shouldShowSmallPriceHint, _shouldShowSmallPriceHint2).call(this)) {
			this.getHintPopup().load(this.getInputByFieldName('PRICE'), main_core.Loc.getMessage('CRM_ENTITY_PL_SMALL_PRICE_NOTICE')).show();
		} else if (showNegative) {
			this.getHintPopup().load(this.getInputByFieldName('PRICE'), main_core.Loc.getMessage('CRM_ENTITY_PL_NEGATIVE_PRICE_NOTICE')).show();
		} else {
			this.getHintPopup().close();
		}
	}

	function _toggleMinimalPricePopup2() {
		var show = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : false;

		if (show) {
			this.getHintPopup().load(this.getInputByFieldName('PRICE'), main_core.Loc.getMessage('CRM_ENTITY_PL_MINIMAL_PRICE_NOTICE')).show();
		} else {
			this.getHintPopup().close();
		}
	}

	function _isReserveEqualProductQuantity2() {
		return this.editor.getSettingValue('isReserveEqualProductQuantity', false);
	}

	function _getNodeChildByDataName2(name) {
		return this.getNode().querySelector("[data-name=\"".concat(name, "\"]"));
	}

	function _onGridUpdated2() {
		if (this.storeAvailablePopup) {
			this.storeAvailablePopup.refreshStoreInfo();
		}
	}

	babelHelpers.defineProperty(Row, "CATALOG_PRICE_CHANGING_DISABLED", 'CATALOG_PRICE_CHANGING_DISABLED');

	var PageEventsManager = /*#__PURE__*/function () {
		function PageEventsManager(settings) {
			babelHelpers.classCallCheck(this, PageEventsManager);
			babelHelpers.defineProperty(this, "_settings", {});
			this._settings = settings ? settings : {};
			this.eventHandlers = {};
		}

		babelHelpers.createClass(PageEventsManager, [{
			key: "registerEventHandler",
			value: function registerEventHandler(eventName, eventHandler) {
				if (!this.eventHandlers[eventName]) this.eventHandlers[eventName] = [];
				this.eventHandlers[eventName].push(eventHandler);
				BX.addCustomEvent(this, eventName, eventHandler);
			}
		}, {
			key: "fireEvent",
			value: function fireEvent(eventName, eventParams) {
				BX.onCustomEvent(this, eventName, eventParams);
			}
		}, {
			key: "unregisterEventHandlers",
			value: function unregisterEventHandlers(eventName) {
				if (this.eventHandlers[eventName]) {
					for (var i = 0; i < this.eventHandlers[eventName].length; i++) {
						BX.removeCustomEvent(this, eventName, this.eventHandlers[eventName][i]);
					}

					delete this.eventHandlers[eventName];
				}
			}
		}]);
		return PageEventsManager;
	}();

	var _templateObject$b, _templateObject2$8, _templateObject3$8, _templateObject4$7, _templateObject5$7;

	function _classPrivateMethodInitSpec$4(obj, privateSet) { _checkPrivateRedeclaration$5(obj, privateSet); privateSet.add(obj); }

	function _classPrivateFieldInitSpec$3(obj, privateMap, value) { _checkPrivateRedeclaration$5(obj, privateMap); privateMap.set(obj, value); }

	function _checkPrivateRedeclaration$5(obj, privateCollection) { if (privateCollection.has(obj)) { throw new TypeError("Cannot initialize the same private elements twice on an object"); } }

	function _classPrivateMethodGet$4(receiver, privateSet, fn) { if (!privateSet.has(receiver)) { throw new TypeError("attempted to get private field on non-instance"); } return fn; }

	var _target = /*#__PURE__*/new WeakMap();

	var _settings = /*#__PURE__*/new WeakMap();

	var _editor = /*#__PURE__*/new WeakMap();

	var _cache$1 = /*#__PURE__*/new WeakMap();

	var _prepareSettingsContent = /*#__PURE__*/new WeakSet();

	var _getSettingItem = /*#__PURE__*/new WeakSet();

	var _setSetting = /*#__PURE__*/new WeakSet();

	var _showNotification = /*#__PURE__*/new WeakSet();

	var SettingsPopup = /*#__PURE__*/function () {
		function SettingsPopup(target) {
			var settings = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : [];
			var editor = arguments.length > 2 ? arguments[2] : undefined;
			babelHelpers.classCallCheck(this, SettingsPopup);

			_classPrivateMethodInitSpec$4(this, _showNotification);

			_classPrivateMethodInitSpec$4(this, _setSetting);

			_classPrivateMethodInitSpec$4(this, _getSettingItem);

			_classPrivateMethodInitSpec$4(this, _prepareSettingsContent);

			_classPrivateFieldInitSpec$3(this, _target, {
				writable: true,
				value: void 0
			});

			_classPrivateFieldInitSpec$3(this, _settings, {
				writable: true,
				value: void 0
			});

			_classPrivateFieldInitSpec$3(this, _editor, {
				writable: true,
				value: void 0
			});

			_classPrivateFieldInitSpec$3(this, _cache$1, {
				writable: true,
				value: new main_core.Cache.MemoryCache()
			});

			babelHelpers.classPrivateFieldSet(this, _target, target);
			babelHelpers.classPrivateFieldSet(this, _settings, settings);
			babelHelpers.classPrivateFieldSet(this, _editor, editor);
		}

		babelHelpers.createClass(SettingsPopup, [{
			key: "show",
			value: function show() {
				this.getPopup().show();
			}
		}, {
			key: "getPopup",
			value: function getPopup() {
				var _this = this;

				return babelHelpers.classPrivateFieldGet(this, _cache$1).remember('settings-popup', function () {
					return new main_popup.Popup(babelHelpers.classPrivateFieldGet(_this, _editor).getId() + '_' + Math.random() * 100, babelHelpers.classPrivateFieldGet(_this, _target), {
						autoHide: true,
						draggable: false,
						offsetLeft: 0,
						offsetTop: 0,
						angle: {
							position: 'top',
							offset: 43
						},
						noAllPaddings: true,
						bindOptions: {
							forceBindPosition: true
						},
						closeByEsc: true,
						content: _classPrivateMethodGet$4(_this, _prepareSettingsContent, _prepareSettingsContent2).call(_this)
					});
				});
			}
		}, {
			key: "getSetting",
			value: function getSetting(id) {
				return babelHelpers.classPrivateFieldGet(this, _settings).filter(function (item) {
					return item.id === id;
				})[0];
			}
		}, {
			key: "requestGridSettings",
			value: function requestGridSettings(setting, enabled) {
				var _this2 = this;

				var headers = [];
				var cells = babelHelpers.classPrivateFieldGet(this, _editor).getGrid().getRows().getHeadFirstChild().getCells();
				Array.from(cells).forEach(function (header) {
					if ('name' in header.dataset) {
						headers.push(header.dataset.name);
					}
				});
				main_core.ajax.runComponentAction(babelHelpers.classPrivateFieldGet(this, _editor).getComponentName(), 'setGridSetting', {
					mode: 'class',
					data: {
						signedParameters: babelHelpers.classPrivateFieldGet(this, _editor).getSignedParameters(),
						settingId: setting.id,
						selected: enabled,
						currentHeaders: headers
					}
				}).then(function () {
					var message;
					setting.checked = enabled;

					if (setting.id === 'ADD_NEW_ROW_TOP') {
						var panel = enabled ? 'top' : 'bottom';
						babelHelpers.classPrivateFieldGet(_this2, _editor).setSettingValue('newRowPosition', panel);
						var activePanel = babelHelpers.classPrivateFieldGet(_this2, _editor).changeActivePanelButtons(panel);
						var settingButton = activePanel.querySelector('[data-role="product-list-settings-button"]');

						_this2.getPopup().setBindElement(settingButton);

						message = enabled ? main_core.Loc.getMessage('CRM_ENTITY_PL_SETTING_ENABLED') : main_core.Loc.getMessage('CRM_ENTITY_PL_SETTING_DISABLED');
						message = message.replace('#NAME#', setting.title);
					} else if (setting.id === 'WAREHOUSE') {
						babelHelpers.classPrivateFieldGet(_this2, _editor).reloadGrid(false);
						message = enabled ? main_core.Loc.getMessage('CRM_ENTITY_CARD_WAREHOUSE_ENABLED') : main_core.Loc.getMessage('CRM_ENTITY_CARD_WAREHOUSE_DISABLED');
					} else {
						babelHelpers.classPrivateFieldGet(_this2, _editor).reloadGrid();
						message = enabled ? main_core.Loc.getMessage('CRM_ENTITY_PL_SETTING_ENABLED') : main_core.Loc.getMessage('CRM_ENTITY_PL_SETTING_DISABLED');
						message = message.replace('#NAME#', setting.title);
					}

					_this2.getPopup().close();

					_classPrivateMethodGet$4(_this2, _showNotification, _showNotification2).call(_this2, message, {
						category: 'popup-settings'
					});
				});
			}
		}, {
			key: "updateCheckboxState",
			value: function updateCheckboxState() {
				var _this3 = this;

				var popupContainer = this.getPopup().getContentContainer();
				babelHelpers.classPrivateFieldGet(this, _settings).filter(function (item) {
					return item.action === 'grid' && main_core.Type.isArray(item.columns);
				}).forEach(function (item) {
					var allColumnsExist = true;
					item.columns.forEach(function (columnName) {
						if (!babelHelpers.classPrivateFieldGet(_this3, _editor).getGrid().getColumnHeaderCellByName(columnName)) {
							allColumnsExist = false;
						}
					});
					var checkbox = popupContainer.querySelector('input[data-setting-id="' + item.id + '"]');

					if (main_core.Type.isDomNode(checkbox)) {
						checkbox.checked = allColumnsExist;
					}
				});
			}
		}]);
		return SettingsPopup;
	}();

	function _prepareSettingsContent2() {
		var _this4 = this;

		var content = main_core.Tag.render(_templateObject$b || (_templateObject$b = babelHelpers.taggedTemplateLiteral(["\n\t\t\t<div class='ui-entity-editor-popup-create-field-list'></div>\n\t\t"])));
		babelHelpers.classPrivateFieldGet(this, _settings).forEach(function (item) {
			content.append(_classPrivateMethodGet$4(_this4, _getSettingItem, _getSettingItem2).call(_this4, item));
		});
		return content;
	}

	function _getSettingItem2(item) {
		var _item$disabled,
			_this5 = this;

		var input = main_core.Tag.render(_templateObject2$8 || (_templateObject2$8 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t<input type=\"checkbox\">\n\t\t"])));
		input.checked = item.checked;
		input.disabled = (_item$disabled = item.disabled) !== null && _item$disabled !== void 0 ? _item$disabled : false;
		input.dataset.settingId = item.id;
		var descriptionNode = main_core.Type.isStringFilled(item.desc) ? main_core.Tag.render(_templateObject3$8 || (_templateObject3$8 = babelHelpers.taggedTemplateLiteral(["<span class=\"ui-entity-editor-popup-create-field-item-desc\">", "</span>"])), item.desc) : '';
		var hintNode = main_core.Type.isStringFilled(item.hint) ? main_core.Tag.render(_templateObject4$7 || (_templateObject4$7 = babelHelpers.taggedTemplateLiteral(["<span class=\"crm-entity-product-list-setting-hint\" data-hint=\"", "\"></span>"])), item.hint) : '';
		var setting = main_core.Tag.render(_templateObject5$7 || (_templateObject5$7 = babelHelpers.taggedTemplateLiteral(["\n\t\t\t<label class=\"ui-ctl-block ui-entity-editor-popup-create-field-item ui-ctl-w100\">\n\t\t\t\t<div class=\"ui-ctl-w10\" style=\"text-align: center\">", "</div>\n\t\t\t\t<div class=\"ui-ctl-w75\">\n\t\t\t\t\t<span class=\"ui-entity-editor-popup-create-field-item-title ", "\">", "", "</span>\n\t\t\t\t\t", "\n\t\t\t\t</div>\n\t\t\t</label>\n\t\t"])), input, item.disabled ? 'crm-entity-product-list-disabled-setting' : '', item.title, hintNode, descriptionNode);
		BX.UI.Hint.init(setting);

		if (item.id === 'SLIDER') {
			main_core.Event.bind(setting, 'change', function (event) {
				new catalog_storeUse.Slider().open(item.url, {}).then(function () {
					return babelHelpers.classPrivateFieldGet(_this5, _editor).reloadGrid(false);
				});
			});
		} else {
			main_core.Event.bind(setting, 'change', _classPrivateMethodGet$4(this, _setSetting, _setSetting2).bind(this));
		}

		return setting;
	}

	function _setSetting2(event) {
		var settingItem = this.getSetting(event.target.dataset.settingId);

		if (!settingItem) {
			return;
		}

		var settingEnabled = event.target.checked;
		this.requestGridSettings(settingItem, settingEnabled);
	}

	function _showNotification2(content, options) {
		options = options || {};
		BX.UI.Notification.Center.notify({
			content: content,
			stack: options.stack || null,
			position: 'top-right',
			width: 'auto',
			category: options.category || null,
			autoHideDelay: options.autoHideDelay || 3000
		});
	}

	var _templateObject$c;

	function _regeneratorRuntime$1() { /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/facebook/regenerator/blob/main/LICENSE */ _regeneratorRuntime$1 = function _regeneratorRuntime() { return exports; }; var exports = {}, Op = Object.prototype, hasOwn = Op.hasOwnProperty, $Symbol = "function" == typeof Symbol ? Symbol : {}, iteratorSymbol = $Symbol.iterator || "@@iterator", asyncIteratorSymbol = $Symbol.asyncIterator || "@@asyncIterator", toStringTagSymbol = $Symbol.toStringTag || "@@toStringTag"; function define(obj, key, value) { return Object.defineProperty(obj, key, { value: value, enumerable: !0, configurable: !0, writable: !0 }), obj[key]; } try { define({}, ""); } catch (err) { define = function define(obj, key, value) { return obj[key] = value; }; } function wrap(innerFn, outerFn, self, tryLocsList) { var protoGenerator = outerFn && outerFn.prototype instanceof Generator ? outerFn : Generator, generator = Object.create(protoGenerator.prototype), context = new Context(tryLocsList || []); return generator._invoke = function (innerFn, self, context) { var state = "suspendedStart"; return function (method, arg) { if ("executing" === state) throw new Error("Generator is already running"); if ("completed" === state) { if ("throw" === method) throw arg; return doneResult(); } for (context.method = method, context.arg = arg;;) { var delegate = context.delegate; if (delegate) { var delegateResult = maybeInvokeDelegate(delegate, context); if (delegateResult) { if (delegateResult === ContinueSentinel) continue; return delegateResult; } } if ("next" === context.method) context.sent = context._sent = context.arg;else if ("throw" === context.method) { if ("suspendedStart" === state) throw state = "completed", context.arg; context.dispatchException(context.arg); } else "return" === context.method && context.abrupt("return", context.arg); state = "executing"; var record = tryCatch(innerFn, self, context); if ("normal" === record.type) { if (state = context.done ? "completed" : "suspendedYield", record.arg === ContinueSentinel) continue; return { value: record.arg, done: context.done }; } "throw" === record.type && (state = "completed", context.method = "throw", context.arg = record.arg); } }; }(innerFn, self, context), generator; } function tryCatch(fn, obj, arg) { try { return { type: "normal", arg: fn.call(obj, arg) }; } catch (err) { return { type: "throw", arg: err }; } } exports.wrap = wrap; var ContinueSentinel = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} var IteratorPrototype = {}; define(IteratorPrototype, iteratorSymbol, function () { return this; }); var getProto = Object.getPrototypeOf, NativeIteratorPrototype = getProto && getProto(getProto(values([]))); NativeIteratorPrototype && NativeIteratorPrototype !== Op && hasOwn.call(NativeIteratorPrototype, iteratorSymbol) && (IteratorPrototype = NativeIteratorPrototype); var Gp = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(IteratorPrototype); function defineIteratorMethods(prototype) { ["next", "throw", "return"].forEach(function (method) { define(prototype, method, function (arg) { return this._invoke(method, arg); }); }); } function AsyncIterator(generator, PromiseImpl) { function invoke(method, arg, resolve, reject) { var record = tryCatch(generator[method], generator, arg); if ("throw" !== record.type) { var result = record.arg, value = result.value; return value && "object" == babelHelpers["typeof"](value) && hasOwn.call(value, "__await") ? PromiseImpl.resolve(value.__await).then(function (value) { invoke("next", value, resolve, reject); }, function (err) { invoke("throw", err, resolve, reject); }) : PromiseImpl.resolve(value).then(function (unwrapped) { result.value = unwrapped, resolve(result); }, function (error) { return invoke("throw", error, resolve, reject); }); } reject(record.arg); } var previousPromise; this._invoke = function (method, arg) { function callInvokeWithMethodAndArg() { return new PromiseImpl(function (resolve, reject) { invoke(method, arg, resolve, reject); }); } return previousPromise = previousPromise ? previousPromise.then(callInvokeWithMethodAndArg, callInvokeWithMethodAndArg) : callInvokeWithMethodAndArg(); }; } function maybeInvokeDelegate(delegate, context) { var method = delegate.iterator[context.method]; if (undefined === method) { if (context.delegate = null, "throw" === context.method) { if (delegate.iterator["return"] && (context.method = "return", context.arg = undefined, maybeInvokeDelegate(delegate, context), "throw" === context.method)) return ContinueSentinel; context.method = "throw", context.arg = new TypeError("The iterator does not provide a 'throw' method"); } return ContinueSentinel; } var record = tryCatch(method, delegate.iterator, context.arg); if ("throw" === record.type) return context.method = "throw", context.arg = record.arg, context.delegate = null, ContinueSentinel; var info = record.arg; return info ? info.done ? (context[delegate.resultName] = info.value, context.next = delegate.nextLoc, "return" !== context.method && (context.method = "next", context.arg = undefined), context.delegate = null, ContinueSentinel) : info : (context.method = "throw", context.arg = new TypeError("iterator result is not an object"), context.delegate = null, ContinueSentinel); } function pushTryEntry(locs) { var entry = { tryLoc: locs[0] }; 1 in locs && (entry.catchLoc = locs[1]), 2 in locs && (entry.finallyLoc = locs[2], entry.afterLoc = locs[3]), this.tryEntries.push(entry); } function resetTryEntry(entry) { var record = entry.completion || {}; record.type = "normal", delete record.arg, entry.completion = record; } function Context(tryLocsList) { this.tryEntries = [{ tryLoc: "root" }], tryLocsList.forEach(pushTryEntry, this), this.reset(!0); } function values(iterable) { if (iterable) { var iteratorMethod = iterable[iteratorSymbol]; if (iteratorMethod) return iteratorMethod.call(iterable); if ("function" == typeof iterable.next) return iterable; if (!isNaN(iterable.length)) { var i = -1, next = function next() { for (; ++i < iterable.length;) { if (hasOwn.call(iterable, i)) return next.value = iterable[i], next.done = !1, next; } return next.value = undefined, next.done = !0, next; }; return next.next = next; } } return { next: doneResult }; } function doneResult() { return { value: undefined, done: !0 }; } return GeneratorFunction.prototype = GeneratorFunctionPrototype, define(Gp, "constructor", GeneratorFunctionPrototype), define(GeneratorFunctionPrototype, "constructor", GeneratorFunction), GeneratorFunction.displayName = define(GeneratorFunctionPrototype, toStringTagSymbol, "GeneratorFunction"), exports.isGeneratorFunction = function (genFun) { var ctor = "function" == typeof genFun && genFun.constructor; return !!ctor && (ctor === GeneratorFunction || "GeneratorFunction" === (ctor.displayName || ctor.name)); }, exports.mark = function (genFun) { return Object.setPrototypeOf ? Object.setPrototypeOf(genFun, GeneratorFunctionPrototype) : (genFun.__proto__ = GeneratorFunctionPrototype, define(genFun, toStringTagSymbol, "GeneratorFunction")), genFun.prototype = Object.create(Gp), genFun; }, exports.awrap = function (arg) { return { __await: arg }; }, defineIteratorMethods(AsyncIterator.prototype), define(AsyncIterator.prototype, asyncIteratorSymbol, function () { return this; }), exports.AsyncIterator = AsyncIterator, exports.async = function (innerFn, outerFn, self, tryLocsList, PromiseImpl) { void 0 === PromiseImpl && (PromiseImpl = Promise); var iter = new AsyncIterator(wrap(innerFn, outerFn, self, tryLocsList), PromiseImpl); return exports.isGeneratorFunction(outerFn) ? iter : iter.next().then(function (result) { return result.done ? result.value : iter.next(); }); }, defineIteratorMethods(Gp), define(Gp, toStringTagSymbol, "Generator"), define(Gp, iteratorSymbol, function () { return this; }), define(Gp, "toString", function () { return "[object Generator]"; }), exports.keys = function (object) { var keys = []; for (var key in object) { keys.push(key); } return keys.reverse(), function next() { for (; keys.length;) { var key = keys.pop(); if (key in object) return next.value = key, next.done = !1, next; } return next.done = !0, next; }; }, exports.values = values, Context.prototype = { constructor: Context, reset: function reset(skipTempReset) { if (this.prev = 0, this.next = 0, this.sent = this._sent = undefined, this.done = !1, this.delegate = null, this.method = "next", this.arg = undefined, this.tryEntries.forEach(resetTryEntry), !skipTempReset) for (var name in this) { "t" === name.charAt(0) && hasOwn.call(this, name) && !isNaN(+name.slice(1)) && (this[name] = undefined); } }, stop: function stop() { this.done = !0; var rootRecord = this.tryEntries[0].completion; if ("throw" === rootRecord.type) throw rootRecord.arg; return this.rval; }, dispatchException: function dispatchException(exception) { if (this.done) throw exception; var context = this; function handle(loc, caught) { return record.type = "throw", record.arg = exception, context.next = loc, caught && (context.method = "next", context.arg = undefined), !!caught; } for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i], record = entry.completion; if ("root" === entry.tryLoc) return handle("end"); if (entry.tryLoc <= this.prev) { var hasCatch = hasOwn.call(entry, "catchLoc"), hasFinally = hasOwn.call(entry, "finallyLoc"); if (hasCatch && hasFinally) { if (this.prev < entry.catchLoc) return handle(entry.catchLoc, !0); if (this.prev < entry.finallyLoc) return handle(entry.finallyLoc); } else if (hasCatch) { if (this.prev < entry.catchLoc) return handle(entry.catchLoc, !0); } else { if (!hasFinally) throw new Error("try statement without catch or finally"); if (this.prev < entry.finallyLoc) return handle(entry.finallyLoc); } } } }, abrupt: function abrupt(type, arg) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.tryLoc <= this.prev && hasOwn.call(entry, "finallyLoc") && this.prev < entry.finallyLoc) { var finallyEntry = entry; break; } } finallyEntry && ("break" === type || "continue" === type) && finallyEntry.tryLoc <= arg && arg <= finallyEntry.finallyLoc && (finallyEntry = null); var record = finallyEntry ? finallyEntry.completion : {}; return record.type = type, record.arg = arg, finallyEntry ? (this.method = "next", this.next = finallyEntry.finallyLoc, ContinueSentinel) : this.complete(record); }, complete: function complete(record, afterLoc) { if ("throw" === record.type) throw record.arg; return "break" === record.type || "continue" === record.type ? this.next = record.arg : "return" === record.type ? (this.rval = this.arg = record.arg, this.method = "return", this.next = "end") : "normal" === record.type && afterLoc && (this.next = afterLoc), ContinueSentinel; }, finish: function finish(finallyLoc) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.finallyLoc === finallyLoc) return this.complete(entry.completion, entry.afterLoc), resetTryEntry(entry), ContinueSentinel; } }, "catch": function _catch(tryLoc) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.tryLoc === tryLoc) { var record = entry.completion; if ("throw" === record.type) { var thrown = record.arg; resetTryEntry(entry); } return thrown; } } throw new Error("illegal catch attempt"); }, delegateYield: function delegateYield(iterable, resultName, nextLoc) { return this.delegate = { iterator: values(iterable), resultName: resultName, nextLoc: nextLoc }, "next" === this.method && (this.arg = undefined), ContinueSentinel; } }, exports; }

	function _createForOfIteratorHelper$1(o, allowArrayLike) { var it = typeof Symbol !== "undefined" && o[Symbol.iterator] || o["@@iterator"]; if (!it) { if (Array.isArray(o) || (it = _unsupportedIterableToArray$1(o)) || allowArrayLike && o && typeof o.length === "number") { if (it) o = it; var i = 0; var F = function F() {}; return { s: F, n: function n() { if (i >= o.length) return { done: true }; return { done: false, value: o[i++] }; }, e: function e(_e) { throw _e; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var normalCompletion = true, didErr = false, err; return { s: function s() { it = it.call(o); }, n: function n() { var step = it.next(); normalCompletion = step.done; return step; }, e: function e(_e2) { didErr = true; err = _e2; }, f: function f() { try { if (!normalCompletion && it["return"] != null) it["return"](); } finally { if (didErr) throw err; } } }; }

	function _unsupportedIterableToArray$1(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray$1(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray$1(o, minLen); }

	function _arrayLikeToArray$1(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }

	function ownKeys$3(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }

	function _objectSpread$3(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys$3(Object(source), !0).forEach(function (key) { babelHelpers.defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys$3(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }

	function _classPrivateMethodInitSpec$5(obj, privateSet) { _checkPrivateRedeclaration$6(obj, privateSet); privateSet.add(obj); }

	function _checkPrivateRedeclaration$6(obj, privateCollection) { if (privateCollection.has(obj)) { throw new TypeError("Cannot initialize the same private elements twice on an object"); } }

	function _classStaticPrivateMethodGet$1(receiver, classConstructor, method) { _classCheckPrivateStaticAccess$1(receiver, classConstructor); return method; }

	function _classCheckPrivateStaticAccess$1(receiver, classConstructor) { if (receiver !== classConstructor) { throw new TypeError("Private static access of wrong provenance"); } }

	function _classPrivateMethodGet$5(receiver, privateSet, fn) { if (!privateSet.has(receiver)) { throw new TypeError("attempted to get private field on non-instance"); } return fn; }
	var GRID_TEMPLATE_ROW = 'template_0';
	var DEFAULT_PRECISION = 2;

	var _initSupportCustomRowActions = /*#__PURE__*/new WeakSet();

	var _getCalculatePriceFieldNames = /*#__PURE__*/new WeakSet();

	var _childrenHasErrors = /*#__PURE__*/new WeakSet();

	var _prepareSmartProcessFunnelsContent = /*#__PURE__*/new WeakSet();

	var _prepareFunnelData = /*#__PURE__*/new WeakSet();

	var Editor = /*#__PURE__*/function () {
		function Editor(id) {
			babelHelpers.classCallCheck(this, Editor);

			_classPrivateMethodInitSpec$5(this, _prepareFunnelData);

			_classPrivateMethodInitSpec$5(this, _prepareSmartProcessFunnelsContent);

			_classPrivateMethodInitSpec$5(this, _childrenHasErrors);

			_classPrivateMethodInitSpec$5(this, _getCalculatePriceFieldNames);

			_classPrivateMethodInitSpec$5(this, _initSupportCustomRowActions);

			babelHelpers.defineProperty(this, "ajaxPool", new Map());
			babelHelpers.defineProperty(this, "products", []);
			babelHelpers.defineProperty(this, "productsWasInitiated", false);
			babelHelpers.defineProperty(this, "isChangedGrid", false);
			babelHelpers.defineProperty(this, "cache", new main_core.Cache.MemoryCache());
			babelHelpers.defineProperty(this, "actions", {
				disableSaveButton: 'disableSaveButton',
				productChange: 'productChange',
				productListChanged: 'productListChanged',
				updateListField: 'listField',
				stateChanged: 'stateChange',
				updateTotal: 'total'
			});
			babelHelpers.defineProperty(this, "stateChange", {
				changed: false,
				sended: false
			});
			babelHelpers.defineProperty(this, "updateFieldForList", null);
			babelHelpers.defineProperty(this, "totalData", {
				inProgress: false
			});
			babelHelpers.defineProperty(this, "productSelectionPopupHandler", this.handleProductSelectionPopup.bind(this));
			babelHelpers.defineProperty(this, "productRowAddHandler", this.handleProductRowAdd.bind(this));
			babelHelpers.defineProperty(this, "showSettingsPopupHandler", this.handleShowSettingsPopup.bind(this));
			babelHelpers.defineProperty(this, "showProcessMakerPopupHandler", this.handleShowProcessMakerPopupHandler.bind(this));
			babelHelpers.defineProperty(this, "withdrawReserveHandler", this.handleWithdrawReserveHandler.bind(this));
			babelHelpers.defineProperty(this, "onDialogSelectProductHandler", this.handleOnDialogSelectProduct.bind(this));
			babelHelpers.defineProperty(this, "onSaveHandler", this.handleOnSave.bind(this));
			babelHelpers.defineProperty(this, "onEntityUpdateHandler", this.handleOnEntityUpdate.bind(this));
			babelHelpers.defineProperty(this, "onEditorSubmit", this.handleEditorSubmit.bind(this));
			babelHelpers.defineProperty(this, "onInnerCancelHandler", this.handleOnInnerCancel.bind(this));
			babelHelpers.defineProperty(this, "onBeforeGridRequestHandler", this.handleOnBeforeGridRequest.bind(this));
			babelHelpers.defineProperty(this, "onGridUpdatedHandler", this.handleOnGridUpdated.bind(this));
			babelHelpers.defineProperty(this, "onGridRowMovedHandler", this.handleOnGridRowMoved.bind(this));
			babelHelpers.defineProperty(this, "onBeforeProductChangeHandler", this.handleOnBeforeProductChange.bind(this));
			babelHelpers.defineProperty(this, "onProductChangeHandler", this.handleOnProductChange.bind(this));
			babelHelpers.defineProperty(this, "onProductClearHandler", this.handleOnProductClear.bind(this));
			babelHelpers.defineProperty(this, "dropdownChangeHandler", this.handleDropdownChange.bind(this));
			babelHelpers.defineProperty(this, "pullReloadGrid", null);
			babelHelpers.defineProperty(this, "changeProductFieldHandler", this.handleFieldChange.bind(this));
			babelHelpers.defineProperty(this, "updateTotalDataDelayedHandler", main_core.Runtime.debounce(this.updateTotalDataDelayed, 1000, this));
			this.setId(id);
		}

		babelHelpers.createClass(Editor, [{
			key: "init",
			value: function init() {
				var config = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
				this.setSettings(config);

				if (this.canEdit()) {
					this.addFirstRowIfEmpty();
					this.enableEdit();
				}

				this.initForm();
				this.initProducts();
				this.initGridData();
				main_core_events.EventEmitter.emit(window, 'EntityProductListController', [this]);

				_classPrivateMethodGet$5(this, _initSupportCustomRowActions, _initSupportCustomRowActions2).call(this);

				this.subscribeDomEvents();
				this.subscribeCustomEvents();

				if (this.getSettingValue('isReserveBlocked', false)) {
					var headersToLock = ['STORE_INFO', 'RESERVE_INFO'];
					var container = this.getContainer();
					headersToLock.forEach(function (headerId) {
						var header = container === null || container === void 0 ? void 0 : container.querySelector(".main-grid-cell-head[data-name=\"".concat(headerId, "\"] .main-grid-cell-head-container"));

						if (header) {
							main_core.Dom.addClass(header, 'main-grid-cell-head-locked');

							header.onclick = function (event) {
								if (main_core.Dom.hasClass(event.target, 'ui-hint-icon')) {
									return;
								}

								top.BX.UI.InfoHelper.show('limit_store_crm_integration');
							};

							var lock = main_core.Tag.render(_templateObject$c || (_templateObject$c = babelHelpers.taggedTemplateLiteral(["<span class=\"crm-entity-product-list-locked-header\"></span>"])));
							header.insertBefore(lock, header.firstChild);
						}
					});
				}
			}
		}, {
			key: "subscribeDomEvents",
			value: function subscribeDomEvents() {
				var _this = this;

				this.unsubscribeDomEvents();
				var container = this.getContainer();

				if (main_core.Type.isElementNode(container)) {
					container.querySelectorAll('[data-role="product-list-select-button"]').forEach(function (selectButton) {
						main_core.Event.bind(selectButton, 'click', _this.productSelectionPopupHandler);
					});
					container.querySelectorAll('[data-role="product-list-add-button"]').forEach(function (addButton) {
						main_core.Event.bind(addButton, 'click', _this.productRowAddHandler);
					});
					container.querySelectorAll('[data-role="product-list-settings-button"]').forEach(function (configButton) {
						main_core.Event.bind(configButton, 'click', _this.showSettingsPopupHandler);
					});
					container.querySelectorAll('[data-role="add-process-button"]').forEach(function (configButton) {
						main_core.Event.bind(configButton, 'click', _this.showProcessMakerPopupHandler);
					});
					container.querySelectorAll('[data-role="withdraw-reserve-button"]').forEach(function (configButton) {
						main_core.Event.bind(configButton, 'click', _this.withdrawReserveHandler);
					});
				}
			}
		}, {
			key: "unsubscribeDomEvents",
			value: function unsubscribeDomEvents() {
				var _this2 = this;

				var container = this.getContainer();

				if (main_core.Type.isElementNode(container)) {
					container.querySelectorAll('[data-role="product-list-select-button"]').forEach(function (selectButton) {
						main_core.Event.unbind(selectButton, 'click', _this2.productSelectionPopupHandler);
					});
					container.querySelectorAll('[data-role="product-list-add-button"]').forEach(function (addButton) {
						main_core.Event.unbind(addButton, 'click', _this2.productRowAddHandler);
					});
					container.querySelectorAll('[data-role="product-list-settings-button"]').forEach(function (configButton) {
						main_core.Event.unbind(configButton, 'click', _this2.showSettingsPopupHandler);
					});
				}
			}
		}, {
			key: "subscribeCustomEvents",
			value: function subscribeCustomEvents() {
				var _this3 = this;

				this.unsubscribeCustomEvents();
				main_core_events.EventEmitter.subscribe('CrmProductSearchDialog_SelectProduct', this.onDialogSelectProductHandler);
				main_core_events.EventEmitter.subscribe('BX.Crm.EntityEditor:onSave', this.onSaveHandler);
				main_core_events.EventEmitter.subscribe('onCrmEntityUpdate', this.onEntityUpdateHandler);
				main_core_events.EventEmitter.subscribe('BX.Crm.EntityEditorAjax:onSubmit', this.onEditorSubmit);
				main_core_events.EventEmitter.subscribe('EntityProductListController:onInnerCancel', this.onInnerCancelHandler);
				main_core_events.EventEmitter.subscribe('Grid::beforeRequest', this.onBeforeGridRequestHandler);
				main_core_events.EventEmitter.subscribe('Grid::updated', this.onGridUpdatedHandler);
				main_core_events.EventEmitter.subscribe('Grid::rowMoved', this.onGridRowMovedHandler);
				main_core_events.EventEmitter.subscribe('BX.Catalog.ProductSelector:onBeforeChange', this.onBeforeProductChangeHandler);
				main_core_events.EventEmitter.subscribe('BX.Catalog.ProductSelector:onChange', this.onProductChangeHandler);
				main_core_events.EventEmitter.subscribe('BX.Catalog.ProductSelector:onClear', this.onProductClearHandler);
				main_core_events.EventEmitter.subscribe('Dropdown::change', this.dropdownChangeHandler);

				if (pull_client.PULL) {
					this.pullReloadGrid = pull_client.PULL.subscribe({
						moduleId: 'crm',
						callback: function callback(data) {
							if (data.command === 'onCatalogInventoryManagementEnabled' || data.command === 'onCatalogInventoryManagementDisabled') {
								_this3.reloadGrid(false);
							}
						}
					}).bind(this);
				}
			}
		}, {
			key: "unsubscribeCustomEvents",
			value: function unsubscribeCustomEvents() {
				main_core_events.EventEmitter.unsubscribe('CrmProductSearchDialog_SelectProduct', this.onDialogSelectProductHandler);
				main_core_events.EventEmitter.unsubscribe('BX.Crm.EntityEditor:onSave', this.onSaveHandler);
				main_core_events.EventEmitter.unsubscribe('onCrmEntityUpdate', this.onEntityUpdateHandler);
				main_core_events.EventEmitter.unsubscribe('BX.Crm.EntityEditorAjax:onSubmit', this.onEditorSubmit);
				main_core_events.EventEmitter.unsubscribe('EntityProductListController:onInnerCancel', this.onInnerCancelHandler);
				main_core_events.EventEmitter.unsubscribe('Grid::beforeRequest', this.onBeforeGridRequestHandler);
				main_core_events.EventEmitter.unsubscribe('Grid::updated', this.onGridUpdatedHandler);
				main_core_events.EventEmitter.unsubscribe('Grid::rowMoved', this.onGridRowMovedHandler);
				main_core_events.EventEmitter.unsubscribe('BX.Catalog.ProductSelector:onBeforeChange', this.onBeforeProductChangeHandler);
				main_core_events.EventEmitter.unsubscribe('BX.Catalog.ProductSelector:onChange', this.onProductChangeHandler);
				main_core_events.EventEmitter.unsubscribe('BX.Catalog.ProductSelector:onClear', this.onProductClearHandler);
				main_core_events.EventEmitter.unsubscribe('Dropdown::change', this.dropdownChangeHandler);

				if (!main_core.Type.isNil(this.pullReloadGrid)) {
					this.pullReloadGrid();
				}
			}
		}, {
			key: "handleOnDialogSelectProduct",
			value: function handleOnDialogSelectProduct(event) {
				var _this$products$;

				var _event$getCompatData = event.getCompatData(),
					_event$getCompatData2 = babelHelpers.slicedToArray(_event$getCompatData, 1),
					productId = _event$getCompatData2[0];

				var id;

				if (this.getProductCount() > 0 || ((_this$products$ = this.products[0]) === null || _this$products$ === void 0 ? void 0 : _this$products$.getField('ID')) <= 0) {
					id = this.addProductRow();
				} else {
					var _this$products$2;

					id = (_this$products$2 = this.products[0]) === null || _this$products$2 === void 0 ? void 0 : _this$products$2.getField('ID');
				}

				this.selectProductInRow(id, productId);
			}
		}, {
			key: "selectProductInRow",
			value: function selectProductInRow(id, productId) {
				var _this4 = this;

				if (!main_core.Type.isStringFilled(id) || main_core.Text.toNumber(productId) <= 0) {
					return;
				}

				requestAnimationFrame(function () {
					var _this4$getProductSele;

					(_this4$getProductSele = _this4.getProductSelector(id)) === null || _this4$getProductSele === void 0 ? void 0 : _this4$getProductSele.onProductSelect(productId);
				});
			}
		}, {
			key: "handleOnSave",
			value: function handleOnSave(event) {
				var items = [];
				this.products.forEach(function (product) {
					var item = {
						fields: _objectSpread$3({}, product.fields),
						rowId: product.fields.ROW_ID
					};
					items.push(item);
					product.getNode().classList.remove('main-grid-row-edit');
				});
				this.setSettingValue('items', items);
			}
		}, {
			key: "handleOnEntityUpdate",
			value: function handleOnEntityUpdate(event) {
				var _this5 = this;

				var _event$getData = event.getData(),
					_event$getData2 = babelHelpers.slicedToArray(_event$getData, 1),
					data = _event$getData2[0];

				if (this.isChanged() && data.entityId === this.getSettingValue('entityId') && data.entityTypeId === this.getSettingValue('entityTypeId')) {
					var rows = this.getProductsFields(_classStaticPrivateMethodGet$1(Editor, Editor, _getAjaxFields).call(Editor)).filter(function (row) {
						return row["PRODUCT_ID"] != null;
					});
					main_core.ajax.runComponentAction(this.getComponentName(), 'saveProductRows', {
						mode: 'class',
						signedParameters: this.getSignedParameters(),
						data: {
							entityId: this.getSettingValue('entityId'),
							entityTypeId: this.getSettingValue('entityTypeId'),
							rows: rows,
							options: {
								ACTION: 'saveProductRows'
							}
						}
					}).then(function (response) {
						_this5.setGridChanged(false);

						_this5.reloadGrid(false);
					});
				}
			}
		}, {
			key: "handleEditorSubmit",
			value: function handleEditorSubmit(event) {
				if (!this.isLocationDependantTaxesEnabled()) {
					return;
				}

				var entityData = event.getData()[0];

				if (!entityData || !entityData.hasOwnProperty('LOCATION_ID')) {
					return;
				}

				if (entityData['LOCATION_ID'] !== this.getLocationId()) {
					this.setLocationId(entityData['LOCATION_ID']);
					this.reloadGrid(false);
				}
			}
		}, {
			key: "handleOnInnerCancel",
			value: function handleOnInnerCancel(event) {
				var _this6 = this;

				if (this.controller) {
					this.controller.rollback();
				}

				this.setGridChanged(false);
				main_core_events.EventEmitter.subscribeOnce(this, 'onGridReloaded', function () {
					return _this6.actionUpdateTotalData({
						isInternalChanging: true
					});
				});
				this.reloadGrid(false);
			}
		}, {
			key: "changeActivePanelButtons",
			value: function changeActivePanelButtons(panelCode) {
				var container = this.getContainer();
				var activePanel = container.querySelector('.crm-entity-product-list-add-block-' + panelCode);

				if (main_core.Type.isDomNode(activePanel)) {
					main_core.Dom.removeClass(activePanel, 'crm-entity-product-list-add-block-hidden');
					main_core.Dom.addClass(activePanel, 'crm-entity-product-list-add-block-active');
				}

				var hiddenPanelCode = panelCode === 'top' ? 'bottom' : 'top';
				var removePanel = container.querySelector('.crm-entity-product-list-add-block-' + hiddenPanelCode);

				if (main_core.Type.isDomNode(removePanel)) {
					main_core.Dom.addClass(removePanel, 'crm-entity-product-list-add-block-hidden');
					main_core.Dom.removeClass(removePanel, 'crm-entity-product-list-add-block-active');
				}

				return activePanel;
			}
		}, {
			key: "reloadGrid",
			value: function reloadGrid() {
				var _this7 = this;

				var useProductsFromRequest = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : true;

				this.getGrid().reloadTable('POST', {
					useProductsFromRequest: useProductsFromRequest
				}, function () {
					return main_core_events.EventEmitter.emit(_this7, 'onGridReloaded');
				});
			}
			/*
	    	keep in mind different actions for this handler:
	    	- native reload by grid actions (columns settings, etc)		- products from request
	    	- reload by tax/discount settings button					- products from request		this.reloadGrid(true)
	    	- rollback													- products from db			this.reloadGrid(false)
	    	- reload after SalesCenter order save						- products from db			this.reloadGrid(false)
	    	- reload after save if location had been changed
	     */

		}, {
			key: "handleOnBeforeGridRequest",
			value: function handleOnBeforeGridRequest(event) {
				var _this8 = this;

				var _event$getCompatData3 = event.getCompatData(),
					_event$getCompatData4 = babelHelpers.slicedToArray(_event$getCompatData3, 2),
					grid = _event$getCompatData4[0],
					eventArgs = _event$getCompatData4[1];

				if (!grid || !grid.parent || grid.parent.getId() !== this.getGridId()) {
					return;
				} // reload by native grid actions (columns settings, etc), otherwise by this.reloadGrid()


				var isNativeAction = !('useProductsFromRequest' in eventArgs.data);
				var useProductsFromRequest = isNativeAction ? true : eventArgs.data.useProductsFromRequest;
				eventArgs.url = this.getReloadUrl();
				eventArgs.method = 'POST';
				eventArgs.sessid = BX.bitrix_sessid();
				eventArgs.data = _objectSpread$3(_objectSpread$3({}, eventArgs.data), {}, {
					signedParameters: this.getSignedParameters(),
					products: useProductsFromRequest ? this.getProductsFields(_classStaticPrivateMethodGet$1(Editor, Editor, _getAjaxFields).call(Editor)) : null,
					locationId: this.getLocationId(),
					currencyId: this.getCurrencyId()
				});
				this.clearEditor();

				if (isNativeAction && this.isChanged()) {
					main_core_events.EventEmitter.subscribeOnce('Grid::updated', function () {
						return _this8.actionUpdateTotalData({
							isInternalChanging: false
						});
					});
				}
			}
		}, {
			key: "handleOnGridUpdated",
			value: function handleOnGridUpdated(event) {
				var _event$getCompatData5 = event.getCompatData(),
					_event$getCompatData6 = babelHelpers.slicedToArray(_event$getCompatData5, 1),
					grid = _event$getCompatData6[0];

				if (!grid || grid.getId() !== this.getGridId()) {
					return;
				}

				this.getSettingsPopup().updateCheckboxState();
			}
		}, {
			key: "handleOnGridRowMoved",
			value: function handleOnGridRowMoved(event) {
				var _event$getCompatData7 = event.getCompatData(),
					_event$getCompatData8 = babelHelpers.slicedToArray(_event$getCompatData7, 3),
					ids = _event$getCompatData8[0],
					grid = _event$getCompatData8[2];

				if (!grid || grid.getId() !== this.getGridId()) {
					return;
				}

				var changed = this.resortProductsByIds(ids);

				if (changed) {
					this.refreshSortFields();
					this.numerateRows();
					this.executeActions([{
						type: this.actions.productListChanged
					}]);
				}
			}
		}, {
			key: "initPageEventsManager",
			value: function initPageEventsManager() {
				var componentId = this.getSettingValue('componentId');
				this.pageEventsManager = new PageEventsManager({
					id: componentId
				});
			}
		}, {
			key: "getPageEventsManager",
			value: function getPageEventsManager() {
				if (!this.pageEventsManager) {
					this.initPageEventsManager();
				}

				return this.pageEventsManager;
			}
		}, {
			key: "canEdit",
			value: function canEdit() {
				return this.getSettingValue('allowEdit', false) === true;
			}
		}, {
			key: "canEditCatalogPrice",
			value: function canEditCatalogPrice() {
				return this.getSettingValue('allowCatalogPriceEdit', false) === true;
			}
		}, {
			key: "canSaveCatalogPrice",
			value: function canSaveCatalogPrice() {
				return this.getSettingValue('allowCatalogPriceSave', false) === true;
			}
		}, {
			key: "enableEdit",
			value: function enableEdit() {
				// Cannot use editSelected because checkboxes have been removed
				var rows = this.getGrid().getRows().getRows();
				rows.forEach(function (current) {
					if (!current.isHeadChild() && !current.isTemplate()) {
						current.edit();
						/** @hack \E2\EE\E7\EC\EE\E6\ED\EE \E1\F3\E4\F3\F2 \EF\F0\EE\E1\EB\E5\EC\FB \F1 \FD\F2\E8\EC */

						current.getNode().classList.remove("main-grid-row-edit");
					}
				});
			}
		}, {
			key: "addFirstRowIfEmpty",
			value: function addFirstRowIfEmpty() {
				var _this9 = this;

				if (this.getGrid().getRows().getCountDisplayed() === 0) {
					requestAnimationFrame(function () {
						return _this9.addProductRow();
					});
				}
			}
		}, {
			key: "clearEditor",
			value: function clearEditor() {
				this.unsubscribeProductsEvents();
				this.products = [];
				this.productsWasInitiated = false;
				this.destroySettingsPopup();
				this.destroyProcessMakerPopup();
				this.unsubscribeDomEvents();
				this.unsubscribeCustomEvents();
				main_core.Event.unbindAll(this.container);
			}
		}, {
			key: "wasProductsInitiated",
			value: function wasProductsInitiated() {
				return this.productsWasInitiated;
			}
		}, {
			key: "unsubscribeProductsEvents",
			value: function unsubscribeProductsEvents() {
				this.products.forEach(function (current) {
					current.unsubscribeCustomEvents();
				});
			}
		}, {
			key: "destroy",
			value: function destroy() {
				this.setForm(null);
				this.clearController();
				this.clearEditor();
			}
		}, {
			key: "setController",
			value: function setController(controller) {
				if (this.controller === controller) {
					return;
				}

				if (this.controller) {
					this.controller.clearProductList();
				}

				this.controller = controller;
			}
		}, {
			key: "clearController",
			value: function clearController() {
				this.controller = null;
			}
		}, {
			key: "getId",
			value: function getId() {
				return this.id;
			}
		}, {
			key: "setId",
			value: function setId(id) {
				this.id = id;
			}
			/* settings tools */

		}, {
			key: "getSettings",
			value: function getSettings() {
				return this.settings;
			}
		}, {
			key: "setSettings",
			value: function setSettings(settings) {
				this.settings = settings ? settings : {};
			}
		}, {
			key: "getSettingValue",
			value: function getSettingValue(name, defaultValue) {
				return this.settings.hasOwnProperty(name) ? this.settings[name] : defaultValue;
			}
		}, {
			key: "setSettingValue",
			value: function setSettingValue(name, value) {
				this.settings[name] = value;
			}
		}, {
			key: "getComponentName",
			value: function getComponentName() {
				return this.getSettingValue('componentName', '');
			}
		}, {
			key: "getReloadUrl",
			value: function getReloadUrl() {
				return this.getSettingValue('reloadUrl', '');
			}
		}, {
			key: "getSignedParameters",
			value: function getSignedParameters() {
				return this.getSettingValue('signedParameters', '');
			}
		}, {
			key: "getContainerId",
			value: function getContainerId() {
				return this.getSettingValue('containerId', '');
			}
		}, {
			key: "getGridId",
			value: function getGridId() {
				return this.getSettingValue('gridId', '');
			}
		}, {
			key: "getLanguageId",
			value: function getLanguageId() {
				return this.getSettingValue('languageId', '');
			}
		}, {
			key: "getSiteId",
			value: function getSiteId() {
				return this.getSettingValue('siteId', '');
			}
		}, {
			key: "getCatalogId",
			value: function getCatalogId() {
				return this.getSettingValue('catalogId', 0);
			}
		}, {
			key: "isReadOnly",
			value: function isReadOnly() {
				return this.getSettingValue('readOnly', true);
			}
		}, {
			key: "setReadOnly",
			value: function setReadOnly(readOnly) {
				this.setSettingValue('readOnly', readOnly);
			}
		}, {
			key: "getCurrencyId",
			value: function getCurrencyId() {
				return this.getSettingValue('currencyId', '');
			}
		}, {
			key: "setCurrencyId",
			value: function setCurrencyId(currencyId) {
				this.setSettingValue('currencyId', currencyId);
				this.products.forEach(function (product) {
					var _product$getModel;

					return (_product$getModel = product.getModel()) === null || _product$getModel === void 0 ? void 0 : _product$getModel.setOption('currency', currencyId);
				});
			}
		}, {
			key: "isLocationDependantTaxesEnabled",
			value: function isLocationDependantTaxesEnabled() {
				return this.getSettingValue('isLocationDependantTaxesEnabled', false);
			}
		}, {
			key: "getLocationId",
			value: function getLocationId() {
				return this.getSettingValue('locationId');
			}
		}, {
			key: "setLocationId",
			value: function setLocationId(locationId) {
				this.setSettingValue('locationId', locationId);
			}
		}, {
			key: "changeCurrencyId",
			value: function changeCurrencyId(currencyId) {
				var _this10 = this;

				this.setCurrencyId(currencyId);
				var products = [];
				this.products.forEach(function (product) {
					var priceFields = {};

					_classPrivateMethodGet$5(_this10, _getCalculatePriceFieldNames, _getCalculatePriceFieldNames2).call(_this10).forEach(function (name) {
						priceFields[name] = product.getField(name);
					});

					products.push({
						fields: priceFields,
						id: product.getId()
					});
				});

				if (products.length > 0) {
					this.ajaxRequest('calculateProductPrices', {
						products: products,
						currencyId: currencyId
					});
				}

				var editData = this.getGridEditData();
				var templateRow = editData[GRID_TEMPLATE_ROW];
				templateRow['CURRENCY'] = this.getCurrencyId();
				var templateFieldNames = ['DISCOUNT_ROW', 'SUM', 'PRICE'];
				templateFieldNames.forEach(function (field) {
					templateRow[field]['CURRENCY']['VALUE'] = _this10.getCurrencyId();
				});
				this.setGridEditData(editData);
			}
		}, {
			key: "onCalculatePricesResponse",
			value: function onCalculatePricesResponse(products) {
				this.products.forEach(function (product) {
					if (main_core.Type.isObject(products[product.getId()])) {
						product.updateUiCurrencyFields();
						['BASE_PRICE', 'ENTERED_PRICE', 'DISCOUNT_ROW', 'DISCOUNT_SUM', 'CURRENCY_ID'].forEach(function (name) {
							product.updateField(name, main_core.Text.toNumber(products[product.getId()][name]));
						});
						product.setField('CURRENCY', products[product.getId()]['CURRENCY_ID']);
					}
				});
				this.updateTotalUiCurrency();
			}
		}, {
			key: "updateTotalUiCurrency",
			value: function updateTotalUiCurrency() {
				var _this11 = this;

				var totalBlock = BX(this.getSettingValue('totalBlockContainerId', null));

				if (main_core.Type.isElementNode(totalBlock)) {
					totalBlock.querySelectorAll('[data-role="currency-wrapper"]').forEach(function (row) {
						row.innerHTML = _this11.getCurrencyText();
					});
				}
			}
		}, {
			key: "getAdjustedProductRow",
			value: function getAdjustedProductRow(sort) {
				var _iterator = _createForOfIteratorHelper$1(this.products),
					_step;

				try {
					for (_iterator.s(); !(_step = _iterator.n()).done;) {
						var product = _step.value;

						if (product.getField('SORT') > sort && product.isAdjusted()) {
							return product;
						}
					}
				} catch (err) {
					_iterator.e(err);
				} finally {
					_iterator.f();
				}
			}
		}, {
			key: "getCurrencyText",
			value: function getCurrencyText() {
				var currencyId = this.getCurrencyId();

				if (!main_core.Type.isStringFilled(currencyId)) {
					return '';
				}

				var format = currency_currencyCore.CurrencyCore.getCurrencyFormat(currencyId);
				return format && format.FORMAT_STRING.replace(/(^|[^&])#/, '$1').trim() || '';
			}
		}, {
			key: "getDataFieldName",
			value: function getDataFieldName() {
				return this.getSettingValue('dataFieldName', '');
			}
		}, {
			key: "getDataSettingsFieldName",
			value: function getDataSettingsFieldName() {
				var field = this.getDataFieldName();
				return main_core.Type.isStringFilled(field) ? field + '_SETTINGS' : '';
			}
		}, {
			key: "getDiscountEnabled",
			value: function getDiscountEnabled() {
				return this.getSettingValue('enableDiscount', 'N');
			}
		}, {
			key: "getPricePrecision",
			value: function getPricePrecision() {
				return this.getSettingValue('pricePrecision', DEFAULT_PRECISION);
			}
		}, {
			key: "getQuantityPrecision",
			value: function getQuantityPrecision() {
				return this.getSettingValue('quantityPrecision', DEFAULT_PRECISION);
			}
		}, {
			key: "getCommonPrecision",
			value: function getCommonPrecision() {
				return this.getSettingValue('commonPrecision', DEFAULT_PRECISION);
			}
		}, {
			key: "getTaxList",
			value: function getTaxList() {
				return this.getSettingValue('taxList', []);
			}
		}, {
			key: "getTaxAllowed",
			value: function getTaxAllowed() {
				return this.getSettingValue('allowTax', 'N');
			}
		}, {
			key: "isTaxAllowed",
			value: function isTaxAllowed() {
				return this.getTaxAllowed() === 'Y';
			}
		}, {
			key: "getTaxEnabled",
			value: function getTaxEnabled() {
				return this.getSettingValue('enableTax', 'N');
			}
		}, {
			key: "isTaxEnabled",
			value: function isTaxEnabled() {
				return this.getTaxEnabled() === 'Y';
			}
		}, {
			key: "isTaxUniform",
			value: function isTaxUniform() {
				return this.getSettingValue('taxUniform', true);
			}
		}, {
			key: "getMeasures",
			value: function getMeasures() {
				return this.getSettingValue('measures', []);
			}
		}, {
			key: "getDefaultMeasure",
			value: function getDefaultMeasure() {
				return this.getSettingValue('defaultMeasure', {});
			}
		}, {
			key: "getRowIdPrefix",
			value: function getRowIdPrefix() {
				return this.getSettingValue('rowIdPrefix', 'crm_entity_product_list_');
			}
			/* settings tools finish */

			/* calculate tools */

		}, {
			key: "parseInt",
			value: function (_parseInt) {
				function parseInt(_x) {
					return _parseInt.apply(this, arguments);
				}

				parseInt.toString = function () {
					return _parseInt.toString();
				};

				return parseInt;
			}(function (value) {
				var defaultValue = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 0;
				var result;
				var isNumberValue = main_core.Type.isNumber(value);
				var isStringValue = main_core.Type.isStringFilled(value);

				if (!isNumberValue && !isStringValue) {
					return defaultValue;
				}

				if (isStringValue) {
					value = value.replace(/^\s+|\s+$/g, '');
					var isNegative = value.indexOf('-') === 0;
					result = parseInt(value.replace(/[^\d]/g, ''), 10);

					if (isNaN(result)) {
						result = defaultValue;
					} else {
						if (isNegative) {
							result = -result;
						}
					}
				} else {
					result = parseInt(value, 10);

					if (isNaN(result)) {
						result = defaultValue;
					}
				}

				return result;
			})
		}, {
			key: "parseFloat",
			value: function (_parseFloat) {
				function parseFloat(_x2) {
					return _parseFloat.apply(this, arguments);
				}

				parseFloat.toString = function () {
					return _parseFloat.toString();
				};

				return parseFloat;
			}(function (value) {
				var precision = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : DEFAULT_PRECISION;
				var defaultValue = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 0.0;
				var result;
				var isNumberValue = main_core.Type.isNumber(value);
				var isStringValue = main_core.Type.isStringFilled(value);

				if (!isNumberValue && !isStringValue) {
					return defaultValue;
				}

				if (isStringValue) {
					value = value.replace(/^\s+|\s+$/g, '');
					var dot = value.indexOf('.');
					var comma = value.indexOf(',');
					var isNegative = value.indexOf('-') === 0;

					if (dot < 0 && comma >= 0) {
						var s1 = value.substr(0, comma);
						var decimalLength = value.length - comma - 1;

						if (decimalLength > 0) {
							s1 += '.' + value.substr(comma + 1, decimalLength);
						}

						value = s1;
					}

					value = value.replace(/[^\d.]+/g, '');
					result = parseFloat(value);

					if (isNaN(result)) {
						result = defaultValue;
					}

					if (isNegative) {
						result = -result;
					}
				} else {
					result = parseFloat(value);
				}

				if (precision >= 0) {
					result = this.round(result, precision);
				}

				return result;
			})
		}, {
			key: "round",
			value: function round(value) {
				var precision = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : DEFAULT_PRECISION;
				var factor = Math.pow(10, precision);
				return Math.round(value * factor) / factor;
			}
		}, {
			key: "calculatePriceWithoutDiscount",
			value: function calculatePriceWithoutDiscount(price, discount, discountType) {
				var result = 0.0;

				switch (discountType) {
					case catalog_productCalculator.DiscountType.PERCENTAGE:
						result = price - price * discount / 100;
						break;

					case catalog_productCalculator.DiscountType.MONETARY:
						result = price - discount;
						break;
				}

				return result;
			}
		}, {
			key: "calculateDiscountRate",
			value: function calculateDiscountRate(originalPrice, price) {
				if (originalPrice === 0.0) {
					return 0.0;
				}

				if (price === 0.0) {
					return originalPrice > 0 ? 100.0 : -100.0;
				}

				return 100 * (originalPrice - price) / originalPrice;
			}
		}, {
			key: "calculateDiscount",
			value: function calculateDiscount(originalPrice, discountRate) {
				return originalPrice * discountRate / 100;
			}
		}, {
			key: "calculatePriceWithoutTax",
			value: function calculatePriceWithoutTax(price, taxRate) {
				// Tax is not included in price
				return price / (1 + taxRate / 100);
			}
		}, {
			key: "calculatePriceWithTax",
			value: function calculatePriceWithTax(price, taxRate) {
				// Tax is included in price
				return price * (1 + taxRate / 100);
			}
			/* calculate tools finish */

		}, {
			key: "getContainer",
			value: function getContainer() {
				var _this12 = this;

				return this.cache.remember('container', function () {
					return document.getElementById(_this12.getContainerId());
				});
			}
		}, {
			key: "initForm",
			value: function initForm() {
				var formId = this.getSettingValue('formId', '');
				var form = main_core.Type.isStringFilled(formId) ? BX('form_' + formId) : null;

				if (main_core.Type.isElementNode(form)) {
					this.setForm(form);
				}
			}
		}, {
			key: "isExistForm",
			value: function isExistForm() {
				return main_core.Type.isElementNode(this.getForm());
			}
		}, {
			key: "getForm",
			value: function getForm() {
				return this.form;
			}
		}, {
			key: "setForm",
			value: function setForm(form) {
				this.form = form;
			}
		}, {
			key: "initFormFields",
			value: function initFormFields() {
				var container = this.getForm();

				if (main_core.Type.isElementNode(container)) {
					var field = this.getDataField();

					if (!main_core.Type.isElementNode(field)) {
						this.initDataField();
					}

					var settingsField = this.getDataSettingsField();

					if (!main_core.Type.isElementNode(settingsField)) {
						this.initDataSettingsField();
					}
				}
			}
		}, {
			key: "initFormField",
			value: function initFormField(fieldName) {
				var container = this.getForm();

				if (main_core.Type.isElementNode(container) && main_core.Type.isStringFilled(fieldName)) {
					main_core.Dom.append(main_core.Dom.create('input', {
						attrs: {
							type: "hidden",
							name: fieldName
						}
					}), container);
				}
			}
		}, {
			key: "removeFormFields",
			value: function removeFormFields() {
				var field = this.getDataField();

				if (main_core.Type.isElementNode(field)) {
					main_core.Dom.remove(field);
				}

				var settingsField = this.getDataSettingsField();

				if (main_core.Type.isElementNode(settingsField)) {
					main_core.Dom.remove(settingsField);
				}
			}
		}, {
			key: "initDataField",
			value: function initDataField() {
				this.initFormField(this.getDataFieldName());
			}
		}, {
			key: "initDataSettingsField",
			value: function initDataSettingsField() {
				this.initFormField(this.getDataSettingsFieldName());
			}
		}, {
			key: "getFormField",
			value: function getFormField(fieldName) {
				var container = this.getForm();

				if (main_core.Type.isElementNode(container) && main_core.Type.isStringFilled(fieldName)) {
					return container.querySelector('input[name="' + fieldName + '"]');
				}

				return null;
			}
		}, {
			key: "getDataField",
			value: function getDataField() {
				return this.getFormField(this.getDataFieldName());
			}
		}, {
			key: "getDataSettingsField",
			value: function getDataSettingsField() {
				return this.getFormField(this.getDataSettingsFieldName());
			}
		}, {
			key: "getProductCount",
			value: function getProductCount() {
				return this.products.filter(function (item) {
					return !item.isEmpty();
				}).length;
			}
		}, {
			key: "initProducts",
			value: function initProducts() {
				var list = this.getSettingValue('items', []);
				var isReserveBlocked = this.getSettingValue('isReserveBlocked', false);

				var _iterator2 = _createForOfIteratorHelper$1(list),
					_step2;

				try {
					for (_iterator2.s(); !(_step2 = _iterator2.n()).done;) {
						var item = _step2.value;

						var fields = _objectSpread$3({}, item.fields);

						var settings = {
							selectorId: item.selectorId,
							isReserveBlocked: isReserveBlocked
						};
						this.products.push(new Row(item.rowId, fields, settings, this));
					}
				} catch (err) {
					_iterator2.e(err);
				} finally {
					_iterator2.f();
				}

				this.numerateRows();
				this.productsWasInitiated = true;
			}
		}, {
			key: "numerateRows",
			value: function numerateRows() {
				var _this13 = this;

				var parentIndex = 1;
				var childIndex = 1;
				this.products.forEach(function (product, index) {
					if (product.getField('UF_APPLICATION_PARENT_PRODUCT_ROW_ID', 0) != 0) {
						return;
					}

					product.setRowNumber(parentIndex);

					for (var i = index; i < _this13.products.length; i++) {
						var applicationRow = _this13.products[i];

						if (applicationRow.getField('UF_APPLICATION_PARENT_PRODUCT_ROW_ID', 0) == product.getField('ID')) {
							applicationRow.setRowNumber(parentIndex + '.' + childIndex++);
						}
					}

					parentIndex++;
					childIndex = 1;
				});
			}
		}, {
			key: "getGrid",
			value: function getGrid() {
				var _this14 = this;

				return this.cache.remember('grid', function () {
					var gridId = _this14.getGridId();

					if (!main_core.Reflection.getClass('BX.Main.gridManager.getInstanceById')) {
						throw Error("Cannot find grid with '".concat(gridId, "' id."));
					}

					return BX.Main.gridManager.getInstanceById(gridId);
				});
			}
		}, {
			key: "initGridData",
			value: function initGridData() {
				var gridEditData = this.getSettingValue('templateGridEditData', null);

				if (gridEditData) {
					this.setGridEditData(gridEditData);
				}
			}
		}, {
			key: "getGridEditData",
			value: function getGridEditData() {
				return this.getGrid().arParams.EDITABLE_DATA;
			}
		}, {
			key: "setGridEditData",
			value: function setGridEditData(data) {
				this.getGrid().arParams.EDITABLE_DATA = data;
			}
		}, {
			key: "setOriginalTemplateEditData",
			value: function setOriginalTemplateEditData(data) {
				this.getGrid().arParams.EDITABLE_DATA[GRID_TEMPLATE_ROW] = data;
			}
		}, {
			key: "handleProductErrorsChange",
			value: function handleProductErrorsChange() {
				if (_classPrivateMethodGet$5(this, _childrenHasErrors, _childrenHasErrors2).call(this)) {
					this.controller.disableSaveButton();
				}
			}
		}, {
			key: "handleFieldChange",
			value: function handleFieldChange(event) {
				var row = event.target.closest('tr');

				if (row && row.hasAttribute('data-id')) {
					var product = this.getProductById(row.getAttribute('data-id'));

					if (product) {
						var cell = event.target.closest('td');
						var fieldCode = this.getFieldCodeByGridCell(row, cell);

						if (fieldCode) {
							product.updateFieldByEvent(fieldCode, event);
						}
					}
				}
			}
		}, {
			key: "handleDropdownChange",
			value: function handleDropdownChange(event) {
				var _event$getData3 = event.getData(),
					_event$getData4 = babelHelpers.slicedToArray(_event$getData3, 5),
					dropdownId = _event$getData4[0],
					value = _event$getData4[4];

				var regExp = new RegExp(this.getRowIdPrefix() + '([A-Za-z0-9]+)_(\\w+)_control', 'i');
				var matches = dropdownId.match(regExp); // match user field dropdown
				// if(!matches) {
				// 	let dropdown = document.querySelector('#' + dropdownId);
				// 	if(dropdown) {
				// 		let parentRow = dropdown.closest('tr');
				// 		console.log(parentRow);
				// 		if(parentRow) {
				// 			matches = [null, parentRow.dataset.id, dropdown.getAttribute('name')];
				// 		}
				// 	}
				// }

				if (matches) {
					var _matches = babelHelpers.slicedToArray(matches, 3),
						rowId = _matches[1],
						fieldCode = _matches[2];

					var product = this.getProductById(rowId);

					if (product) {
						product.updateField(fieldCode, value, product.modeChanges.EDIT);
					}
				}
			}
		}, {
			key: "getProductById",
			value: function getProductById(id) {
				var rowId = this.getRowIdPrefix() + id;
				return this.getProductByRowId(rowId);
			}
		}, {
			key: "getProductByRowId",
			value: function getProductByRowId(rowId) {
				return this.products.find(function (row) {
					return row.getId() === rowId;
				});
			}
		}, {
			key: "getFieldCodeByGridCell",
			value: function getFieldCodeByGridCell(row, cell) {
				if (!main_core.Type.isDomNode(row) || !main_core.Type.isDomNode(cell)) {
					return null;
				}

				var grid = this.getGrid();

				if (grid) {
					var headRow = grid.getRows().getHeadFirstChild();
					var index = babelHelpers.toConsumableArray(row.cells).indexOf(cell);
					return headRow.getCellNameByCellIndex(index);
				}

				return null;
			}
		}, {
			key: "handleProductSelectionPopup",
			value: function handleProductSelectionPopup(event) {
				var caller = 'crm_entity_product_list';
				var jsEventsManagerId = this.getSettingValue('jsEventsManagerId', '');
				var popup = new BX.CDialog({
					content_url: '/bitrix/components/bitrix/crm.product_row.list/product_choice_dialog.php?' + 'caller=' + caller + '&JS_EVENTS_MANAGER_ID=' + BX.util.urlencode(jsEventsManagerId) + '&sessid=' + BX.bitrix_sessid(),
					height: Math.max(500, window.innerHeight - 400),
					width: Math.max(800, window.innerWidth - 400),
					draggable: true,
					resizable: true,
					min_height: 500,
					min_width: 800,
					zIndex: 800
				});
				main_core_events.EventEmitter.subscribeOnce(popup, 'onWindowRegister', BX.defer(function () {
					popup.Get().style.position = 'fixed';
					popup.Get().style.top = parseInt(popup.Get().style.top) - BX.GetWindowScrollPos().scrollTop + 'px';
					popup.OVERLAY.style.zIndex = 798;
				}));
				main_core_events.EventEmitter.subscribeOnce(window, 'EntityProductListController:onInnerCancel', BX.defer(function () {
					popup.Close();
				}));

				if (!main_core.Type.isUndefined(BX.Crm.EntityEvent)) {
					main_core_events.EventEmitter.subscribeOnce(window, BX.Crm.EntityEvent.names.update, BX.defer(function () {
						requestAnimationFrame(function () {
							popup.Close();
						}, 0);
					}));
				}

				popup.Show();
			}
		}, {
			key: "addProductRow",
			value: function addProductRow() {
				var anchorProduct = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : null;
				var row = this.createGridProductRow();
				var newId = row.getId();

				if (anchorProduct) {
					var _this$getGrid$getRows;

					var anchorRowNode = (_this$getGrid$getRows = this.getGrid().getRows().getById(anchorProduct.getField('ID'))) === null || _this$getGrid$getRows === void 0 ? void 0 : _this$getGrid$getRows.getNode();

					if (anchorProduct.getField('UF_APPLICATION_PARENT_PRODUCT_ROW_ID', 0) != 0) {
						row.setDepth(1);
					}

					if (anchorRowNode) {
						anchorRowNode.parentNode.insertBefore(row.getNode(), anchorRowNode.nextSibling);
					}
				}

				this.initializeNewProductRow(newId, anchorProduct);
				this.getGrid().bindOnRowEvents();
				return newId;
			}
		}, {
			key: "addProductRowAfter",
			value: function addProductRowAfter(anchorProduct) {
				var _this$getGrid$getRows2;

				var row = this.createGridProductRow();
				var newId = row.getId();
				var anchorRowNode = (_this$getGrid$getRows2 = this.getGrid().getRows().getById(anchorProduct.getField('ID'))) === null || _this$getGrid$getRows2 === void 0 ? void 0 : _this$getGrid$getRows2.getNode();

				if (anchorRowNode) {
					anchorRowNode.parentNode.insertBefore(row.getNode(), anchorRowNode.nextSibling);
				}

				this.initializeNewProductRowWithoutFields(newId, anchorProduct);
				row.setDepth(1);
				this.getGrid().bindOnRowEvents();
				return newId;
			}
		}, {
			key: "handleProductRowAdd",
			value: function handleProductRowAdd() {
				var id = this.addProductRow();
				this.focusProductSelector(id);
			}
		}, {
			key: "handleShowSettingsPopup",
			value: function handleShowSettingsPopup() {
				this.getSettingsPopup().show();
			}
		}, {
			key: "handleShowProcessMakerPopupHandler",
			value: function handleShowProcessMakerPopupHandler() {
				var popup = this.getProcessMakerPopup();

				if (!popup.firstShow) {
					var content = _classPrivateMethodGet$5(this, _prepareSmartProcessFunnelsContent, _prepareSmartProcessFunnelsContent2).call(this, this.getSettingValue('smartProcessCategories'));

					popup.setContent(content);
				}

				popup.show();
			}
		}, {
			key: "handleWithdrawReserveHandler",
			value: function handleWithdrawReserveHandler(event) {
				var _this15 = this;

				var button = event.target;
				var fields = this.getProductsFields(_classStaticPrivateMethodGet$1(Editor, Editor, _getAjaxFields).call(Editor));
				fields.forEach(function (elem) {
					var oldId = elem.ID;
					var newId = "id_" + main_core.Text.getRandom();
					elem.ID = newId;
					fields.forEach(function (child) {
						if (child.UF_APPLICATION_PARENT_PRODUCT_ROW_ID == oldId) {
							child.UF_APPLICATION_PARENT_PRODUCT_ROW_ID = newId;
						}
					});
				});
				button.classList.add("ui-btn-wait");
				button.disabled = true;
				BX.ajax.runComponentAction(this.getComponentName(), 'withdrawReserve1C', {
					mode: 'class',
					signedParameters: this.getSignedParameters(),
					data: {
						entityId: this.getSettingValue('entityId'),
						entityTypeId: this.getSettingValue('entityTypeId'),
						rows: fields,
						options: {
							ACTION: 'withdrawReserve1C'
						}
					}
				}).then( /*#__PURE__*/function () {
					var _ref = babelHelpers.asyncToGenerator( /*#__PURE__*/_regeneratorRuntime$1().mark(function _callee(res) {
						var popup;
						return _regeneratorRuntime$1().wrap(function _callee$(_context) {
							while (1) {
								switch (_context.prev = _context.next) {
									case 0:
										popup = _this15.getWithdrawReservePopup();
										popup.show();
										if (button.classList.contains("ui-btn-wait")) button.classList.remove("ui-btn-wait");
										button.disabled = false;

									case 4:
									case "end":
										return _context.stop();
								}
							}
						}, _callee);
					}));

					return function (_x3) {
						return _ref.apply(this, arguments);
					};
				}());
			}
		}, {
			key: "getWithdrawReservePopup",
			value: function getWithdrawReservePopup() {
				var _this16 = this;

				return this.cache.remember('withdraw-reserve-popup', function () {
					return new main_popup.Popup(_this16.getId() + '_' + Math.random() * 100, window.body, {
						titleBar: main_core.Loc.getMessage("CRM_ENTITY_PL_WITHDRAW_RESERVE"),
						content: main_core.Loc.getMessage("CRM_ENTITY_PL_WITHDRAW_RESERVE_POPUP"),
						autoHide: true,
						closeIcon: true,
						closeByEsc: true,
						overlay: {
							backgroundColor: 'grey',
							opacity: '80'
						},
						className: "withdraw-reserve-popup",
						width: 800,
						offsetLeft: 0,
						offsetTop: 0
					});
				});
			}
		}, {
			key: "getProcessMakerPopup",
			value: function getProcessMakerPopup() {
				var _this17 = this;

				return this.cache.remember('process-maker-popup', function () {
					return new main_popup.Popup(_this17.getId() + '_' + Math.random() * 100, window.body, {
						titleBar: main_core.Loc.getMessage("CREATE_SMART_PROCESS_BASED_ON"),
						autoHide: true,
						closeIcon: true,
						closeByEsc: true,
						overlay: {
							backgroundColor: 'grey',
							opacity: '80'
						},
						className: "process-maker-popup",
						width: 800,
						offsetLeft: 0,
						offsetTop: 0
					});
				});
			}
		}, {
			key: "destroyProcessMakerPopup",
			value: function destroyProcessMakerPopup() {
				if (this.cache.has('process-maker-popup')) {
					this.cache.get('process-maker-popup').destroy();
					this.cache["delete"]('process-maker-popup');
				}
			}
		}, {
			key: "createSmartProcess",
			value: function createSmartProcess(event) {
				var _this18 = this;

				var fields = this.getSelectedProductsFields(_classStaticPrivateMethodGet$1(Editor, Editor, _getAjaxFields).call(Editor));

				if (fields.length === 0) {
					this.setGridChanged(false);
					this.reloadGrid(false);
					return;
				}

				fields.forEach(function (elem) {
					var oldId = elem.ID;
					var newId = "id_" + main_core.Text.getRandom();
					elem.ID = newId;
					fields.forEach(function (child) {
						if (child.UF_APPLICATION_PARENT_PRODUCT_ROW_ID == oldId) {
							child.UF_APPLICATION_PARENT_PRODUCT_ROW_ID = newId;
						}
					});
				});
				BX.ajax.runComponentAction('bitrix:crm.item.details', 'save', {
					mode: 'class',
					data: {
						sessid: BX.bitrix_sessid(),
						data: {
							TITLE: BX.Crm.EntityEditor.defaultInstance.getModel().getField("TITLE"),
							OPPORTUNITY: BX.Crm.EntityEditor.defaultInstance.getModel().getField("OPPORTUNITY"),
							IS_MANUAL_OPPORTUNITY: 'Y',
							CATEGORY_ID: event.target.dataset.categoryId,
							PARENT_ID_2: BX.Crm.EntityEditor.defaultInstance.getModel().getField("ID") //['DYNAMIC_'+this.getSettingValue('smartProcessId')+'_PRODUCT_DATA']: JSON.stringify(fields)

						},
						signedParameters: this.getSettingValue('smartProcessSignedParameters')
					}
				}).then( /*#__PURE__*/function () {
					var _ref2 = babelHelpers.asyncToGenerator( /*#__PURE__*/_regeneratorRuntime$1().mark(function _callee2(res) {
						var response;
						return _regeneratorRuntime$1().wrap(function _callee2$(_context2) {
							while (1) {
								switch (_context2.prev = _context2.next) {
									case 0:
										_context2.next = 2;
										return res;

									case 2:
										response = _context2.sent;

										/** \D1\ED\E0\F7\E0\EB\E0 \F1\EE\F5\F0\E0\ED\FF\E5\EC \F2\EE\E2\E0\F0\FB \E2 \F1\EC\E0\F0\F2 \EF\F0\EE\F6\E5\F1\F1\E5 \F7\E5\F0\E5\E7 \ED\E0\F8 \EA\EE\EC\EF\EE\ED\E5\ED\F2 \F7\F2\EE\E1\FB \EF\EE\E4\F2\FF\ED\F3\EB\E8\F1\FC userfields */
										main_core.ajax.runComponentAction(_this18.getComponentName(), 'saveProductRows', {
											mode: 'class',
											signedParameters: _this18.getSignedParameters(),
											data: {
												entityId: res.data.ENTITY_ID,
												entityTypeId: _this18.getSettingValue('smartProcessId'),
												rows: fields,
												options: {
													ACTION: 'saveProductRows',
													STAGE: 'addAfterCreate'
												}
											}
										});

										_this18.getSelectedProducts().forEach(function (product) {
											product.setField('UF_SMART_PROCESS_ID', res.data.ENTITY_ID);
											product.setField('UF_SMART_PROCESS_STATUS', res.data.ENTITY_DATA.STAGE_ID);
										});

										main_core.ajax.runComponentAction(_this18.getComponentName(), 'saveProductRows', {
											mode: 'class',
											signedParameters: _this18.getSignedParameters(),
											data: {
												entityId: _this18.getSettingValue('entityId'),
												entityTypeId: _this18.getSettingValue('entityTypeId'),
												rows: _this18.getProductsFields(_classStaticPrivateMethodGet$1(Editor, Editor, _getAjaxFields).call(Editor)),
												options: {
													ACTION: 'saveProductRows'
												}
											}
										}).then(function (response) {
											_this18.setGridChanged(false);

											_this18.reloadGrid(false);
										}, function (response) {
											_this18.setGridChanged(false);

											_this18.reloadGrid(false);
										});
									/*let data = {
	                  	smartProcessId: res.data.ENTITY_ID,
	                  	productRowIds: [],
	                  	ownerInfo: BX.Crm.EntityEditor.defaultInstance.getOwnerInfo()
	                  }
	                  		this.getSelectedProducts().forEach((product) => {
	                  	data.productRowIds.push(product.getField("ID"));
	                  });
	                  		BX.ajax.runComponentAction('kitconsulting:crm.entity.extendedproduct.applications', 'insertSmartProcessIdInRows', {
	                  	mode: 'class',
	                  	data: {
	                  		sessid: BX.bitrix_sessid(),
	                  		data: data
	                  	}
	                  })*/

									case 6:
									case "end":
										return _context2.stop();
								}
							}
						}, _callee2);
					}));

					return function (_x4) {
						return _ref2.apply(this, arguments);
					};
				}());
				/*console.log(this.getSelectedProducts());
	      BX.ajax.runComponentAction('kitconsulting:crm.entity.extendedproduct.applications', 'createSmartProcess', {
	      	mode: 'class',
	      	data: {
	      		sessid: BX.bitrix_sessid(),
	      		data: {
	      			TITLE: BX.Crm.EntityEditor.defaultInstance.getModel().getField("TITLE"),
	      			OPPORTUNITY: BX.Crm.EntityEditor.defaultInstance.getModel().getField("OPPORTUNITY"),
	      			IS_MANUAL_OPPORTUNITY: 'Y',
	      		}
	      	}
	      }).then(async (res) => {
	      	console.log(await res);
	      });*/
			}
		}, {
			key: "getSelectedProducts",
			value: function getSelectedProducts() {
				return this.products.filter(function (elem) {
					if (elem.getNode().classList.contains('main-grid-row-checked')) {
						return true;
					}
				});
			}
		}, {
			key: "destroySettingsPopup",
			value: function destroySettingsPopup() {
				if (this.cache.has('settings-popup')) {
					this.cache.get('settings-popup').getPopup().destroy();
					this.cache["delete"]('settings-popup');
				}
			}
		}, {
			key: "getSettingsPopup",
			value: function getSettingsPopup() {
				var _this19 = this;

				return this.cache.remember('settings-popup', function () {
					return new SettingsPopup(_this19.getContainer().querySelector('.crm-entity-product-list-add-block-active [data-role="product-list-settings-button"]'), _this19.getSettingValue('popupSettings', []), _this19);
				});
			}
		}, {
			key: "getHintPopup",
			value: function getHintPopup() {
				var _this20 = this;

				return this.cache.remember('hint-popup', function () {
					return new HintPopup(_this20);
				});
			}
		}, {
			key: "createGridProductRow",
			value: function createGridProductRow() {
				var newId = "id_" + main_core.Text.getRandom();
				var originalTemplate = this.redefineTemplateEditData(newId);
				var grid = this.getGrid();
				var newRow;

				if (this.getSettingValue('newRowPosition') === 'bottom') {
					newRow = grid.appendRowEditor();
				} else {
					newRow = grid.prependRowEditor();
				}

				var newNode = newRow.getNode();

				if (main_core.Type.isDomNode(newNode)) {
					newNode.setAttribute('data-id', newId);
					newRow.makeCountable();
				}

				if (originalTemplate) {
					this.setOriginalTemplateEditData(originalTemplate);
				}

				main_core_events.EventEmitter.emit('Grid::thereEditedRows', []);
				grid.adjustRows();
				grid.updateCounterDisplayed();
				grid.updateCounterSelected();
				/** @hack \ED\F3\E6\ED\EE \E8\F1\EF\EE\EB\FC\E7\EE\E2\E0\F2\FC newRow.cancelEdit() \ED\EE \F2\E0\EC \EC\ED\EE\E3\EE \EB\E8\F8\ED\E8\F5 \E4\E5\E9\F1\F2\E2\E8\E9 */

				newRow.getNode().classList.remove('main-grid-row-edit');
				newRow.unselect();
				newRow.getNode().classList.add('main-grid-row-edit');
				return newRow;
			}
		}, {
			key: "handleDeleteRow",
			value: function handleDeleteRow(rowId, event) {
				event.preventDefault();
				this.deleteRow(rowId);
			}
		}, {
			key: "redefineTemplateEditData",
			value: function redefineTemplateEditData(newId) {
				var data = this.getGridEditData();
				var originalTemplateData = data[GRID_TEMPLATE_ROW];
				var customEditData = this.prepareCustomEditData(originalTemplateData, newId);
				this.setOriginalTemplateEditData(_objectSpread$3(_objectSpread$3({}, originalTemplateData), customEditData));
				return originalTemplateData;
			}
		}, {
			key: "prepareCustomEditData",
			value: function prepareCustomEditData(originalEditData, newId) {
				var customEditData = {};
				var templateIdMask = this.getSettingValue('templateIdMask', '');

				for (var i in originalEditData) {
					if (originalEditData.hasOwnProperty(i)) {
						if (main_core.Type.isStringFilled(originalEditData[i]) && originalEditData[i].indexOf(templateIdMask) >= 0) {
							customEditData[i] = originalEditData[i].replace(new RegExp(templateIdMask, 'g'), newId);
						} else if (main_core.Type.isPlainObject(originalEditData[i])) {
							customEditData[i] = this.prepareCustomEditData(originalEditData[i], newId);
						} else {
							customEditData[i] = originalEditData[i];
						}
					}
				}

				return customEditData;
			}
		}, {
			key: "initializeNewProductRow",
			value: function initializeNewProductRow(newId) {
				var anchorProduct = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;
				var fields = anchorProduct === null || anchorProduct === void 0 ? void 0 : anchorProduct.getFields();

				if (main_core.Type.isNil(fields)) {
					fields = _objectSpread$3(_objectSpread$3({}, this.getSettingValue('templateItemFields', {})), {
						CURRENCY: this.getCurrencyId()
					});
					var lastItem = this.products[this.products.length - 1];

					if (lastItem) {
						fields.TAX_INCLUDED = lastItem.getField('TAX_INCLUDED');
					}
				}

				var rowId = this.getRowIdPrefix() + newId;
				fields.ID = newId;

				if (main_core.Type.isObject(fields.IMAGE_INFO)) {
					delete fields.IMAGE_INFO.input;
				}

				delete fields.RESERVE_ID;
				var isReserveBlocked = this.getSettingValue('isReserveBlocked', false);
				var product = new Row(rowId, fields, {
					isReserveBlocked: isReserveBlocked
				}, this);
				product.refreshFieldsLayout();

				if (anchorProduct instanceof Row) {
					var _product$getSelector, _product$getSelector2;

					this.products.splice(1 + this.products.indexOf(anchorProduct), 0, product);
					(_product$getSelector = product.getSelector()) === null || _product$getSelector === void 0 ? void 0 : _product$getSelector.reloadFileInput();
					(_product$getSelector2 = product.getSelector()) === null || _product$getSelector2 === void 0 ? void 0 : _product$getSelector2.layout();
					product.updateUiMeasure(product.getField('MEASURE_CODE'), main_core.Text.encode(product.getField('MEASURE_NAME')));
				} else if (this.getSettingValue('newRowPosition') === 'bottom') {
					this.products.push(product);
				} else {
					this.products.unshift(product);
				}

				this.refreshSortFields();
				this.numerateRows();
				product.updateUiCurrencyFields();
				this.updateTotalUiCurrency();

				if (product.getField('UF_APPLICATION_PARENT_PRODUCT_ROW_ID', 0) != 0) {
					product.disableUiField('UF_NEED_ADJUSTMENT');
					product.disableUiField('UF_CRM_PR_ADJUSTMENT');
					product.getNode().querySelector('[data-field-code="UF_APPLICATION_PRICE"]').style.display = null;
					product.setApplicationPrice(product.getField('UF_APPLICATION_PRICE'));
				}

				return product;
			}
		}, {
			key: "initializeNewProductRowWithoutFields",
			value: function initializeNewProductRowWithoutFields(newId, anchorProduct) {
				var fields = null;

				if (main_core.Type.isNil(fields)) {
					fields = _objectSpread$3(_objectSpread$3({}, this.getSettingValue('templateItemFields', {})), {
						CURRENCY: this.getCurrencyId()
					});
					var lastItem = this.products[this.products.length - 1];

					if (lastItem) {
						fields.TAX_INCLUDED = lastItem.getField('TAX_INCLUDED');
					}
				}

				var rowId = this.getRowIdPrefix() + newId;
				fields.ID = newId;

				if (main_core.Type.isObject(fields.IMAGE_INFO)) {
					delete fields.IMAGE_INFO.input;
				}

				delete fields.RESERVE_ID;
				var isReserveBlocked = this.getSettingValue('isReserveBlocked', false);
				var product = new Row(rowId, fields, {
					isReserveBlocked: isReserveBlocked
				}, this);
				product.refreshFieldsLayout();

				if (anchorProduct instanceof Row) {
					var _product$getSelector3, _product$getSelector4;

					this.products.splice(1 + this.products.indexOf(anchorProduct), 0, product);
					(_product$getSelector3 = product.getSelector()) === null || _product$getSelector3 === void 0 ? void 0 : _product$getSelector3.reloadFileInput();
					(_product$getSelector4 = product.getSelector()) === null || _product$getSelector4 === void 0 ? void 0 : _product$getSelector4.layout();
					product.updateUiMeasure(product.getField('MEASURE_CODE'), main_core.Text.encode(product.getField('MEASURE_NAME')));
				} else if (this.getSettingValue('newRowPosition') === 'bottom') {
					this.products.push(product);
				} else {
					this.products.unshift(product);
				}

				this.refreshSortFields();
				this.numerateRows();
				product.updateUiCurrencyFields();
				this.updateTotalUiCurrency();
				return product;
			}
		}, {
			key: "isTaxIncludedActive",
			value: function isTaxIncludedActive() {
				return this.products.filter(function (product) {
					return product.isTaxIncluded();
				}).length > 0;
			}
		}, {
			key: "getProductSelector",
			value: function getProductSelector(newId) {
				return ProductSelector.getById('crm_grid_' + this.getRowIdPrefix() + newId);
			}
		}, {
			key: "focusProductSelector",
			value: function focusProductSelector(newId) {
				var _this21 = this;

				requestAnimationFrame(function () {
					var _this21$getProductSel;

					(_this21$getProductSel = _this21.getProductSelector(newId)) === null || _this21$getProductSel === void 0 ? void 0 : _this21$getProductSel.searchInDialog().focusName();
				});
			}
		}, {
			key: "handleOnBeforeProductChange",
			value: function handleOnBeforeProductChange(event) {
				var data = event.getData();
				var product = this.getProductByRowId(data.rowId);

				if (product) {
					this.getGrid().tableFade();
					product.resetExternalActions();
				}
			}
		}, {
			key: "handleOnProductChange",
			value: function handleOnProductChange(event) {
				var _this22 = this;

				var data = event.getData();
				var productRow = this.getProductByRowId(data.rowId);

				if (productRow && data.fields) {
					var promise = new Promise(function (resolve, reject) {
						var fields = data.fields;
						main_core.ajax.runComponentAction(_this22.getComponentName(), 'getPriceInfo', {
							mode: 'class',
							signedParameters: _this22.getSignedParameters(),
							data: {
								productId: fields['SKU_ID'] > 0 ? fields['SKU_ID'] : fields['PRODUCT_ID'],
								basePriceId: productRow.getModel().getBasePriceId()
							}
						}).then(function (priceResponse) {
							if (priceResponse.data['basePrice']) {
								fields['BASE_PRICE'] = priceResponse.data['basePrice']['PRICE'];
							}

							if (priceResponse.data['minimalPrice']) {
								fields['MINIMAL_PRICE'] = priceResponse.data['minimalPrice']['PRICE'];
							}

							if (!main_core.Type.isNil(fields['IMAGE_INFO'])) {
								fields['IMAGE_INFO'] = JSON.stringify(fields['IMAGE_INFO']);
							}

							if (_this22.getCurrencyId() !== fields['CURRENCY_ID']) {
								fields['CURRENCY'] = fields['CURRENCY_ID'];
								var priceFields = {};

								_classPrivateMethodGet$5(_this22, _getCalculatePriceFieldNames, _getCalculatePriceFieldNames2).call(_this22).forEach(function (name) {
									priceFields[name] = data.fields[name];
								});

								var products = [{
									fields: priceFields,
									id: productRow.getId()
								}];
								main_core.ajax.runComponentAction(_this22.getComponentName(), 'calculateProductPrices', {
									mode: 'class',
									signedParameters: _this22.getSignedParameters(),
									data: {
										products: products,
										currencyId: _this22.getCurrencyId(),
										options: {
											ACTION: 'calculateProductPrices'
										}
									}
								}).then(function (response) {
									var changedFields = response.data.result[productRow.getId()];

									if (changedFields) {
										changedFields['CUSTOMIZED'] = 'Y';
										resolve(Object.assign(fields, changedFields));
									} else {
										resolve(fields);
									}
								});
							} else {
								resolve(fields);
							}
						});
					});
					promise.then(function (fields) {
						if (_this22.products.length > 1) {
							var taxId = fields['VAT_ID'] || fields['TAX_ID'];
							var taxIncluded = fields['VAT_INCLUDED'] || fields['TAX_INCLUDED'];

							if (taxId > 0 && taxIncluded !== productRow.getTaxIncluded()) {
								var _this22$getTaxList;

								var taxRate = (_this22$getTaxList = _this22.getTaxList()) === null || _this22$getTaxList === void 0 ? void 0 : _this22$getTaxList.find(function (item) {
									return parseInt(item.ID) === taxId;
								});

								if ((taxRate === null || taxRate === void 0 ? void 0 : taxRate.VALUE) > 0 && taxIncluded === 'Y') {
									fields['BASE_PRICE'] = fields['BASE_PRICE'] / (1 + taxRate.VALUE / 100);
								}
							}

							['TAX_INCLUDED', 'VAT_INCLUDED'].forEach(function (name) {
								return delete fields[name];
							});
						}

						fields['CATALOG_PRICE'] = fields['BASE_PRICE'];
						fields['ENTERED_PRICE'] = fields['BASE_PRICE'];

						if (productRow.getField('OFFER_ID') !== fields.ID) {
							fields['ROW_RESERVED'] = 0;
							fields['DEDUCTED_QUANTITY'] = 0;
						}

						Object.keys(fields).forEach(function (key) {
							productRow.updateFieldValue(key, fields[key]);
						});

						if (!main_core.Type.isStringFilled(fields['CUSTOMIZED'])) {
							productRow.setField('CUSTOMIZED', 'N');
						}

						productRow.setField('IS_NEW', data.isNew ? 'Y' : 'N');
						productRow.layoutReserveControl();
						productRow.initHandlersForSelectors();
						productRow.updateUiStoreAmountData();
						productRow.modifyBasePriceInput();
						productRow.executeExternalActions();

						_this22.getGrid().tableUnfade();
					});
				} else {
					this.getGrid().tableUnfade();
				}
			}
		}, {
			key: "handleOnProductClear",
			value: function handleOnProductClear(event) {
				var _event$getData5 = event.getData(),
					rowId = _event$getData5.rowId;

				var product = this.getProductByRowId(rowId);

				if (product) {
					product.layoutReserveControl();
					product.initHandlersForSelectors();
					product.changeEnteredPrice(0);
					product.modifyBasePriceInput();
					product.executeExternalActions();
				}
			}
		}, {
			key: "compileProductData",
			value: function compileProductData() {
				if (!this.isExistForm()) {
					return;
				}

				this.initFormFields();
				var field = this.getDataField();
				var settingsField = this.getDataSettingsField();
				this.cleanProductRows();

				if (main_core.Type.isElementNode(field) && main_core.Type.isElementNode(settingsField)) {
					// field.value = this.prepareProductDataValue();
					field.value = this.prepareProductDataValue();
					settingsField.value = JSON.stringify({
						ENABLE_DISCOUNT: this.getDiscountEnabled(),
						ENABLE_TAX: this.getTaxEnabled()
					});
				}

				this.addFirstRowIfEmpty();
			}
		}, {
			key: "prepareProductDataValue",
			value: function prepareProductDataValue() {
				var productDataValue = '';

				if (this.getProductCount()) {
					var productData = [];
					this.products.forEach(function (item) {
						var saveFields = item.getFields(_classStaticPrivateMethodGet$1(Editor, Editor, _getAjaxFields).call(Editor));

						if (!/^[0-9]+$/.test(saveFields['ID'])) {
							saveFields['ID'] = 0;
						}

						saveFields['CUSTOMIZED'] = 'Y';
						productData.push(saveFields);
					});
					productDataValue = JSON.stringify(productData);
				}

				return productDataValue;
			}
		}, {
			key: "executeActions",

			/* actions */
			value: function executeActions(actions) {
				var _this23 = this;

				if (!main_core.Type.isArrayFilled(actions)) {
					return;
				}

				var disableSaveButton = actions.filter(function (action) {
					return action.type === _this23.actions.updateTotal || action.type === _this23.actions.disableSaveButton;
				}).length > 0;

				var _iterator3 = _createForOfIteratorHelper$1(actions),
					_step3;

				try {
					for (_iterator3.s(); !(_step3 = _iterator3.n()).done;) {
						var item = _step3.value;

						if (!main_core.Type.isPlainObject(item) || !main_core.Type.isStringFilled(item.type)) {
							continue;
						}

						switch (item.type) {
							case this.actions.productChange:
								this.actionSendProductChange(item, disableSaveButton);
								break;

							case this.actions.productListChanged:
								this.actionSendProductListChanged(disableSaveButton);
								break;

							case this.actions.updateListField:
								this.actionUpdateListField(item);
								break;

							case this.actions.updateTotal:
								this.actionUpdateTotalData();
								break;

							case this.actions.stateChanged:
								this.actionSendStatusChange(item);
								break;
						}
					}
				} catch (err) {
					_iterator3.e(err);
				} finally {
					_iterator3.f();
				}
			}
		}, {
			key: "actionSendProductChange",
			value: function actionSendProductChange(item, disableSaveButton) {
				if (!main_core.Type.isStringFilled(item.id)) {
					return;
				}

				var product = this.getProductByRowId(item.id);

				if (!product) {
					return;
				}

				main_core_events.EventEmitter.emit(this, 'ProductList::onChangeFields', {
					rowId: item.id,
					productId: product.getField('PRODUCT_ID'),
					fields: this.getProductByRowId(item.id).getCatalogFields()
				});

				if (this.controller) {
					this.controller.productChange(disableSaveButton);
					this.setGridChanged(true);
				}
			}
		}, {
			key: "actionSendProductListChanged",
			value: function actionSendProductListChanged() {
				var disableSaveButton = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : false;

				if (this.controller) {
					this.controller.productChange(disableSaveButton);
					this.setGridChanged(true);
				}
			}
		}, {
			key: "actionUpdateListField",
			value: function actionUpdateListField(item) {
				if (!main_core.Type.isStringFilled(item.field) || !('value' in item)) {
					return;
				}

				if (!this.allowUpdateListField(item.field)) {
					return;
				}

				this.updateFieldForList = item.field;

				var _iterator4 = _createForOfIteratorHelper$1(this.products),
					_step4;

				try {
					for (_iterator4.s(); !(_step4 = _iterator4.n()).done;) {
						var row = _step4.value;
						row.updateFieldByName(item.field, item.value);
					}
				} catch (err) {
					_iterator4.e(err);
				} finally {
					_iterator4.f();
				}

				this.updateFieldForList = null;
			}
		}, {
			key: "actionUpdateTotalData",
			value: function actionUpdateTotalData() {
				var options = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};

				if (this.totalData.inProgress) {
					return;
				}

				this.updateTotalDataDelayedHandler(options);
			}
		}, {
			key: "actionSendStatusChange",
			value: function actionSendStatusChange(item) {
				if (!('value' in item)) {
					return;
				}

				if (this.stateChange.changed === item.value) {
					return;
				}

				this.stateChange.changed = item.value;

				if (this.stateChange.sended) {
					return;
				}

				this.stateChange.sended = true;
			}
			/* actions finish */

			/* action tools */

		}, {
			key: "allowUpdateListField",
			value: function allowUpdateListField(field) {
				if (this.updateFieldForList !== null) {
					return false;
				}

				var result = true;

				switch (field) {
					case 'TAX_INCLUDED':
						result = this.isTaxUniform() && this.isTaxAllowed();
						break;
				}

				return result;
			}
		}, {
			key: "setGridChanged",
			value: function setGridChanged(changed) {
				this.isChangedGrid = changed;
			}
		}, {
			key: "isChanged",
			value: function isChanged() {
				return this.isChangedGrid;
			}
		}, {
			key: "updateTotalDataDelayed",
			value: function updateTotalDataDelayed() {
				var options = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};

				if (this.totalData.inProgress) {
					return;
				}

				this.totalData.inProgress = true;
				var products = this.getProductsFields(this.getProductFieldListForTotalData());
				products.forEach(function (item) {
					return item['CUSTOMIZED'] = 'Y';
				});
				this.ajaxRequest('calculateTotalData', {
					options: options,
					products: products,
					currencyId: this.getCurrencyId()
				});
			}
		}, {
			key: "getProductsFields",
			value: function getProductsFields() {
				var fields = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : [];
				var productFields = [];

				var _iterator5 = _createForOfIteratorHelper$1(this.products),
					_step5;

				try {
					for (_iterator5.s(); !(_step5 = _iterator5.n()).done;) {
						var item = _step5.value;
						productFields.push(item.getFields(fields));
					}
				} catch (err) {
					_iterator5.e(err);
				} finally {
					_iterator5.f();
				}

				return productFields;
			}
		}, {
			key: "getSelectedProductsFields",
			value: function getSelectedProductsFields() {
				var fields = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : [];
				var productFields = [];

				var _iterator6 = _createForOfIteratorHelper$1(this.getSelectedProducts()),
					_step6;

				try {
					for (_iterator6.s(); !(_step6 = _iterator6.n()).done;) {
						var item = _step6.value;
						productFields.push(item.getFields(fields));
					}
				} catch (err) {
					_iterator6.e(err);
				} finally {
					_iterator6.f();
				}

				return productFields;
			}
		}, {
			key: "getProductFieldListForTotalData",
			value: function getProductFieldListForTotalData() {
				return ['PRODUCT_ID', 'PRODUCT_NAME', 'QUANTITY', 'DISCOUNT_TYPE_ID', 'DISCOUNT_RATE', 'DISCOUNT_SUM', 'TAX_RATE', 'TAX_INCLUDED', 'PRICE_EXCLUSIVE', 'PRICE', 'CUSTOMIZED', 'UF_APPLICATION_PARENT_PRODUCT_ROW_ID', 'UF_APPLICATION_PRICE', 'UF_PURCHASE_PRICE'];
			}
		}, {
			key: "setTotalData",
			value: function setTotalData(data) {
				var options = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
				var item = BX(this.getSettingValue('totalBlockContainerId', null));

				if (main_core.Type.isElementNode(item)) {
					var currencyId = this.getCurrencyId();
					var list = ['totalCost', 'totalDelivery', 'totalTax', 'totalWithoutTax', 'totalDiscount', 'totalWithoutDiscount', 'totalDefaultProducts', 'totalApplicationsAndCirculation'];

					for (var _i = 0, _list = list; _i < _list.length; _i++) {
						var id = _list[_i];
						var row = item.querySelector('[data-total="' + id + '"]');

						if (main_core.Type.isElementNode(row) && id in data) {
							row.innerHTML = currency_currencyCore.CurrencyCore.currencyFormat(data[id], currencyId, false);
						}
					}
				}

				this.sendTotalData(data, options);
				this.totalData.inProgress = false;
			}
		}, {
			key: "sendTotalData",
			value: function sendTotalData(data, options) {
				var _this24 = this;

				if (this.controller) {
					var needMarkAsChanged = true;

					if (main_core.Type.isObject(options) && (options.isInternalChanging === true || options.isInternalChanging === 'true')) {
						needMarkAsChanged = false;
					}

					setTimeout(function () {
						_this24.controller.changeSumTotal(data, needMarkAsChanged, !_classPrivateMethodGet$5(_this24, _childrenHasErrors, _childrenHasErrors2).call(_this24));
					}, 500);
				}
			}
			/* action tools finish */

			/* ajax tools */

		}, {
			key: "ajaxRequest",
			value: function ajaxRequest(action, data) {
				var _this25 = this;

				var requestKey = main_core.Text.getRandom();
				this.ajaxPool.set(action, requestKey);

				if (!main_core.Type.isPlainObject(data.options)) {
					data.options = {};
				}

				data.options.ACTION = action;
				data.options.REQUEST_KEY = requestKey;
				main_core.ajax.runComponentAction(this.getComponentName(), action, {
					mode: 'class',
					signedParameters: this.getSignedParameters(),
					data: data
				}).then(function (response) {
					return _this25.ajaxResultSuccess(response, data.options);
				}, function (response) {
					return _this25.ajaxResultFailure(response, data.options);
				});
			}
		}, {
			key: "ajaxResultSuccess",
			value: function ajaxResultSuccess(response, requestOptions) {
				if (!this.ajaxResultCommonCheck(response) || this.ajaxPool.get(response.data.action) !== requestOptions.REQUEST_KEY) {
					return;
				}

				this.ajaxPool["delete"](response.data.action);
				main_core_events.EventEmitter.emit(this, 'onAjaxSuccess', response.data.action);

				switch (response.data.action) {
					case 'calculateTotalData':
						if (main_core.Type.isPlainObject(response.data.result)) {
							this.setTotalData(response.data.result, requestOptions);
						}

						break;

					case 'calculateProductPrices':
						if (main_core.Type.isPlainObject(response.data.result)) {
							this.onCalculatePricesResponse(response.data.result);
						}

						break;
				}
			}
		}, {
			key: "validateSubmit",
			value: function validateSubmit() {
				return new Promise(function (resolve, reject) {
					var currentBalloon = BX.UI.Notification.Center.getBalloonByCategory(catalog_productModel.ProductModel.SAVE_NOTIFICATION_CATEGORY);

					if (currentBalloon) {
						main_core_events.EventEmitter.subscribeOnce(currentBalloon, BX.UI.Notification.Event.getFullName('onClose'), function () {
							setTimeout(resolve, 500);
						});
						currentBalloon.close();
					} else {
						setTimeout(resolve(), 50);
					}
				});
			}
		}, {
			key: "ajaxResultFailure",
			value: function ajaxResultFailure(response, requestOptions) {
				this.ajaxPool["delete"](requestOptions.ACTION);
			}
		}, {
			key: "ajaxResultCommonCheck",
			value: function ajaxResultCommonCheck(responce) {
				if (!main_core.Type.isPlainObject(responce)) {
					return false;
				}

				if (!main_core.Type.isStringFilled(responce.status)) {
					return false;
				}

				if (responce.status !== 'success') {
					return false;
				}

				if (!main_core.Type.isPlainObject(responce.data)) {
					return false;
				}

				if (!main_core.Type.isStringFilled(responce.data.action)) {
					return false;
				} // noinspection RedundantIfStatementJS


				if (!('result' in responce.data)) {
					return false;
				}

				return true;
			}
		}, {
			key: "deleteApplicationsForRow",
			value: function deleteApplicationsForRow(rowId) {
				var productId = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;

				for (var i = 0; i < this.products.length; i++) {
					var applicationRow = this.products[i];

					if (applicationRow.getField('UF_APPLICATION_PARENT_PRODUCT_ROW_ID', 0) == rowId) {
						if (productId === null || productId == applicationRow.getField('PRODUCT_ID')) {
							this.deleteRow(applicationRow.getField('ID'));
							i--;
						}
					}
				}
			}
		}, {
			key: "deleteRow",
			value: function deleteRow(rowId) {
				var skipActions = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;
				this.deleteApplicationsForRow(rowId);

				if (!main_core.Type.isStringFilled(rowId)) {
					return;
				}

				var gridRow = this.getGrid().getRows().getById(rowId);

				if (gridRow) {
					main_core.Dom.remove(gridRow.getNode());
					this.getGrid().getRows().reset();
				}

				var productRow = this.getProductById(rowId);

				if (productRow) {
					var index = this.products.indexOf(productRow);

					if (index > -1) {
						this.products.splice(index, 1);
						this.refreshSortFields();
						this.numerateRows();
					}
				}

				main_core_events.EventEmitter.emit('Grid::thereEditedRows', []);

				if (!skipActions) {
					this.addFirstRowIfEmpty();
					this.executeActions([{
						type: this.actions.productListChanged
					}, {
						type: this.actions.updateTotal
					}]);
				}
			}
		}, {
			key: "copyRow",
			value: function copyRow(row) {
				this.addProductRow(row);
				this.refreshSortFields();
				this.numerateRows();
				main_core_events.EventEmitter.emit('Grid::thereEditedRows', []);
				this.executeActions([{
					type: this.actions.productListChanged
				}, {
					type: this.actions.updateTotal
				}]);
			}
		}, {
			key: "copyRowAndReturnProductId",
			value: function copyRowAndReturnProductId(row) {
				var id = this.addProductRow(row);
				this.refreshSortFields();
				this.numerateRows();
				main_core_events.EventEmitter.emit('Grid::thereEditedRows', []);
				this.executeActions([{
					type: this.actions.productListChanged
				}, {
					type: this.actions.updateTotal
				}]);
				return id;
			}
		}, {
			key: "cleanProductRows",
			value: function cleanProductRows() {
				var _this26 = this;

				this.products.filter(function (item) {
					return item.isEmpty();
				}).forEach(function (row) {
					return _this26.deleteRow(row.getField('ID'), true);
				});
			}
		}, {
			key: "resortProductsByIds",
			value: function resortProductsByIds(ids) {
				var changed = false;

				if (main_core.Type.isArrayFilled(ids)) {
					this.products.sort(function (a, b) {
						if (ids.indexOf(a.getField('ID')) > ids.indexOf(b.getField('ID'))) {
							return 1;
						}

						changed = true;
						return -1;
					});
				}

				return changed;
			}
		}, {
			key: "refreshSortFields",
			value: function refreshSortFields() {
				this.products.forEach(function (item, index) {
					return item.setField('SORT', (index + 1) * 10);
				});
			}
		}, {
			key: "handleOnTabShow",
			value: function handleOnTabShow() {
				main_core_events.EventEmitter.emit('onDemandRecalculateWrapper');
			}
		}]);
		return Editor;
	}();

	function _initSupportCustomRowActions2() {
		this.getGrid()._clickOnRowActionsButton = function () {};
	}

	function _getCalculatePriceFieldNames2() {
		return ['BASE_PRICE', 'ENTERED_PRICE', 'TAX_INCLUDED', 'PRICE_NETTO', 'PRICE_BRUTTO', 'DISCOUNT_ROW', 'DISCOUNT_SUM', 'CURRENCY'];
	}

	function _childrenHasErrors2() {
		return this.products.filter(function (product) {
			return product.getModel().getErrorCollection().hasErrors();
		}).length > 0;
	}

	function _prepareSmartProcessFunnelsContent2(funnels) {
		var content = document.createElement('TABLE');
		var tbody = document.createElement('TBODY');
		content.append(tbody);

		for (var key in funnels) {
			tbody.append(_classPrivateMethodGet$5(this, _prepareFunnelData, _prepareFunnelData2).call(this, funnels[key]));
		}

		return content;
	}

	function _prepareFunnelData2(funnel) {
		var row = document.createElement('TR');
		var data = document.createElement('TD');
		var link = document.createElement('A');
		link.innerText = funnel.name;
		link.dataset.categoryId = funnel.id;
		link.classList.add('process-stage-link');
		link.addEventListener('click', this.createSmartProcess.bind(this));
		data.append(link);
		row.append(data);
		return row;
	}

	function _getAjaxFields() {
		return ['ID', 'PRODUCT_ID', 'PRODUCT_NAME', 'QUANTITY', 'TAX_RATE', 'TAX_INCLUDED', 'PRICE_EXCLUSIVE', 'PRICE_NETTO', 'PRICE_BRUTTO', 'PRICE', 'CUSTOMIZED', 'BASE_PRICE', 'ENTERED_PRICE', 'DISCOUNT_ROW', 'DISCOUNT_SUM', 'DISCOUNT_TYPE_ID', 'DISCOUNT_RATE', 'CURRENCY', 'STORE_ID', 'INPUT_RESERVE_QUANTITY', 'RESERVE_QUANTITY', 'DATE_RESERVE_END', 'SORT', 'MEASURE_CODE', 'MEASURE_NAME', 'UF_NEED_ADJUSTMENT', 'UF_CRM_PR_ADJUSTMENT', 'UF_APPLICATION_PRICE', 'UF_APPLICATION_PARENT_PRODUCT_ROW_ID', 'UF_SMART_PROCESS_ID', 'UF_PURCHASE_PRICE'];
	}

	exports.Editor = Editor;
	exports.PageEventsManager = PageEventsManager;
	exports.ProductApplication = ProductApplication;

}((this.BX.Crm.Entity.ExtendedProductList = this.BX.Crm.Entity.ExtendedProductList || {}),BX,BX.Catalog,BX,BX,BX,BX.Catalog.SkuTree,BX.Collections,BX,BX,BX.UI.EntitySelector,BX.Catalog,BX,BX.UI,BX,BX.UI.Tour,BX.Catalog,BX,BX.Event,BX.Catalog.StoreUse,BX.Currency,BX.Catalog,BX,BX.Main));
//# sourceMappingURL=script.js.map