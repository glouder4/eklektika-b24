this.BX = this.BX || {};
this.BX.Kitconsulting = this.BX.Kitconsulting || {};
this.BX.Kitconsulting.ProductCard = this.BX.Kitconsulting.ProductCard || {};
(function (exports,main_core,ui_hint,ui_notification,catalog_productModel,main_core_events,main_popup,catalog_productSelector) {
    'use strict';

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

    function _regeneratorRuntime() { /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/facebook/regenerator/blob/main/LICENSE */ _regeneratorRuntime = function _regeneratorRuntime() { return exports; }; var exports = {}, Op = Object.prototype, hasOwn = Op.hasOwnProperty, $Symbol = "function" == typeof Symbol ? Symbol : {}, iteratorSymbol = $Symbol.iterator || "@@iterator", asyncIteratorSymbol = $Symbol.asyncIterator || "@@asyncIterator", toStringTagSymbol = $Symbol.toStringTag || "@@toStringTag"; function define(obj, key, value) { return Object.defineProperty(obj, key, { value: value, enumerable: !0, configurable: !0, writable: !0 }), obj[key]; } try { define({}, ""); } catch (err) { define = function define(obj, key, value) { return obj[key] = value; }; } function wrap(innerFn, outerFn, self, tryLocsList) { var protoGenerator = outerFn && outerFn.prototype instanceof Generator ? outerFn : Generator, generator = Object.create(protoGenerator.prototype), context = new Context(tryLocsList || []); return generator._invoke = function (innerFn, self, context) { var state = "suspendedStart"; return function (method, arg) { if ("executing" === state) throw new Error("Generator is already running"); if ("completed" === state) { if ("throw" === method) throw arg; return doneResult(); } for (context.method = method, context.arg = arg;;) { var delegate = context.delegate; if (delegate) { var delegateResult = maybeInvokeDelegate(delegate, context); if (delegateResult) { if (delegateResult === ContinueSentinel) continue; return delegateResult; } } if ("next" === context.method) context.sent = context._sent = context.arg;else if ("throw" === context.method) { if ("suspendedStart" === state) throw state = "completed", context.arg; context.dispatchException(context.arg); } else "return" === context.method && context.abrupt("return", context.arg); state = "executing"; var record = tryCatch(innerFn, self, context); if ("normal" === record.type) { if (state = context.done ? "completed" : "suspendedYield", record.arg === ContinueSentinel) continue; return { value: record.arg, done: context.done }; } "throw" === record.type && (state = "completed", context.method = "throw", context.arg = record.arg); } }; }(innerFn, self, context), generator; } function tryCatch(fn, obj, arg) { try { return { type: "normal", arg: fn.call(obj, arg) }; } catch (err) { return { type: "throw", arg: err }; } } exports.wrap = wrap; var ContinueSentinel = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} var IteratorPrototype = {}; define(IteratorPrototype, iteratorSymbol, function () { return this; }); var getProto = Object.getPrototypeOf, NativeIteratorPrototype = getProto && getProto(getProto(values([]))); NativeIteratorPrototype && NativeIteratorPrototype !== Op && hasOwn.call(NativeIteratorPrototype, iteratorSymbol) && (IteratorPrototype = NativeIteratorPrototype); var Gp = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(IteratorPrototype); function defineIteratorMethods(prototype) { ["next", "throw", "return"].forEach(function (method) { define(prototype, method, function (arg) { return this._invoke(method, arg); }); }); } function AsyncIterator(generator, PromiseImpl) { function invoke(method, arg, resolve, reject) { var record = tryCatch(generator[method], generator, arg); if ("throw" !== record.type) { var result = record.arg, value = result.value; return value && "object" == babelHelpers["typeof"](value) && hasOwn.call(value, "__await") ? PromiseImpl.resolve(value.__await).then(function (value) { invoke("next", value, resolve, reject); }, function (err) { invoke("throw", err, resolve, reject); }) : PromiseImpl.resolve(value).then(function (unwrapped) { result.value = unwrapped, resolve(result); }, function (error) { return invoke("throw", error, resolve, reject); }); } reject(record.arg); } var previousPromise; this._invoke = function (method, arg) { function callInvokeWithMethodAndArg() { return new PromiseImpl(function (resolve, reject) { invoke(method, arg, resolve, reject); }); } return previousPromise = previousPromise ? previousPromise.then(callInvokeWithMethodAndArg, callInvokeWithMethodAndArg) : callInvokeWithMethodAndArg(); }; } function maybeInvokeDelegate(delegate, context) { var method = delegate.iterator[context.method]; if (undefined === method) { if (context.delegate = null, "throw" === context.method) { if (delegate.iterator["return"] && (context.method = "return", context.arg = undefined, maybeInvokeDelegate(delegate, context), "throw" === context.method)) return ContinueSentinel; context.method = "throw", context.arg = new TypeError("The iterator does not provide a 'throw' method"); } return ContinueSentinel; } var record = tryCatch(method, delegate.iterator, context.arg); if ("throw" === record.type) return context.method = "throw", context.arg = record.arg, context.delegate = null, ContinueSentinel; var info = record.arg; return info ? info.done ? (context[delegate.resultName] = info.value, context.next = delegate.nextLoc, "return" !== context.method && (context.method = "next", context.arg = undefined), context.delegate = null, ContinueSentinel) : info : (context.method = "throw", context.arg = new TypeError("iterator result is not an object"), context.delegate = null, ContinueSentinel); } function pushTryEntry(locs) { var entry = { tryLoc: locs[0] }; 1 in locs && (entry.catchLoc = locs[1]), 2 in locs && (entry.finallyLoc = locs[2], entry.afterLoc = locs[3]), this.tryEntries.push(entry); } function resetTryEntry(entry) { var record = entry.completion || {}; record.type = "normal", delete record.arg, entry.completion = record; } function Context(tryLocsList) { this.tryEntries = [{ tryLoc: "root" }], tryLocsList.forEach(pushTryEntry, this), this.reset(!0); } function values(iterable) { if (iterable) { var iteratorMethod = iterable[iteratorSymbol]; if (iteratorMethod) return iteratorMethod.call(iterable); if ("function" == typeof iterable.next) return iterable; if (!isNaN(iterable.length)) { var i = -1, next = function next() { for (; ++i < iterable.length;) { if (hasOwn.call(iterable, i)) return next.value = iterable[i], next.done = !1, next; } return next.value = undefined, next.done = !0, next; }; return next.next = next; } } return { next: doneResult }; } function doneResult() { return { value: undefined, done: !0 }; } return GeneratorFunction.prototype = GeneratorFunctionPrototype, define(Gp, "constructor", GeneratorFunctionPrototype), define(GeneratorFunctionPrototype, "constructor", GeneratorFunction), GeneratorFunction.displayName = define(GeneratorFunctionPrototype, toStringTagSymbol, "GeneratorFunction"), exports.isGeneratorFunction = function (genFun) { var ctor = "function" == typeof genFun && genFun.constructor; return !!ctor && (ctor === GeneratorFunction || "GeneratorFunction" === (ctor.displayName || ctor.name)); }, exports.mark = function (genFun) { return Object.setPrototypeOf ? Object.setPrototypeOf(genFun, GeneratorFunctionPrototype) : (genFun.__proto__ = GeneratorFunctionPrototype, define(genFun, toStringTagSymbol, "GeneratorFunction")), genFun.prototype = Object.create(Gp), genFun; }, exports.awrap = function (arg) { return { __await: arg }; }, defineIteratorMethods(AsyncIterator.prototype), define(AsyncIterator.prototype, asyncIteratorSymbol, function () { return this; }), exports.AsyncIterator = AsyncIterator, exports.async = function (innerFn, outerFn, self, tryLocsList, PromiseImpl) { void 0 === PromiseImpl && (PromiseImpl = Promise); var iter = new AsyncIterator(wrap(innerFn, outerFn, self, tryLocsList), PromiseImpl); return exports.isGeneratorFunction(outerFn) ? iter : iter.next().then(function (result) { return result.done ? result.value : iter.next(); }); }, defineIteratorMethods(Gp), define(Gp, toStringTagSymbol, "Generator"), define(Gp, iteratorSymbol, function () { return this; }), define(Gp, "toString", function () { return "[object Generator]"; }), exports.keys = function (object) { var keys = []; for (var key in object) { keys.push(key); } return keys.reverse(), function next() { for (; keys.length;) { var key = keys.pop(); if (key in object) return next.value = key, next.done = !1, next; } return next.done = !0, next; }; }, exports.values = values, Context.prototype = { constructor: Context, reset: function reset(skipTempReset) { if (this.prev = 0, this.next = 0, this.sent = this._sent = undefined, this.done = !1, this.delegate = null, this.method = "next", this.arg = undefined, this.tryEntries.forEach(resetTryEntry), !skipTempReset) for (var name in this) { "t" === name.charAt(0) && hasOwn.call(this, name) && !isNaN(+name.slice(1)) && (this[name] = undefined); } }, stop: function stop() { this.done = !0; var rootRecord = this.tryEntries[0].completion; if ("throw" === rootRecord.type) throw rootRecord.arg; return this.rval; }, dispatchException: function dispatchException(exception) { if (this.done) throw exception; var context = this; function handle(loc, caught) { return record.type = "throw", record.arg = exception, context.next = loc, caught && (context.method = "next", context.arg = undefined), !!caught; } for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i], record = entry.completion; if ("root" === entry.tryLoc) return handle("end"); if (entry.tryLoc <= this.prev) { var hasCatch = hasOwn.call(entry, "catchLoc"), hasFinally = hasOwn.call(entry, "finallyLoc"); if (hasCatch && hasFinally) { if (this.prev < entry.catchLoc) return handle(entry.catchLoc, !0); if (this.prev < entry.finallyLoc) return handle(entry.finallyLoc); } else if (hasCatch) { if (this.prev < entry.catchLoc) return handle(entry.catchLoc, !0); } else { if (!hasFinally) throw new Error("try statement without catch or finally"); if (this.prev < entry.finallyLoc) return handle(entry.finallyLoc); } } } }, abrupt: function abrupt(type, arg) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.tryLoc <= this.prev && hasOwn.call(entry, "finallyLoc") && this.prev < entry.finallyLoc) { var finallyEntry = entry; break; } } finallyEntry && ("break" === type || "continue" === type) && finallyEntry.tryLoc <= arg && arg <= finallyEntry.finallyLoc && (finallyEntry = null); var record = finallyEntry ? finallyEntry.completion : {}; return record.type = type, record.arg = arg, finallyEntry ? (this.method = "next", this.next = finallyEntry.finallyLoc, ContinueSentinel) : this.complete(record); }, complete: function complete(record, afterLoc) { if ("throw" === record.type) throw record.arg; return "break" === record.type || "continue" === record.type ? this.next = record.arg : "return" === record.type ? (this.rval = this.arg = record.arg, this.method = "return", this.next = "end") : "normal" === record.type && afterLoc && (this.next = afterLoc), ContinueSentinel; }, finish: function finish(finallyLoc) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.finallyLoc === finallyLoc) return this.complete(entry.completion, entry.afterLoc), resetTryEntry(entry), ContinueSentinel; } }, "catch": function _catch(tryLoc) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.tryLoc === tryLoc) { var record = entry.completion; if ("throw" === record.type) { var thrown = record.arg; resetTryEntry(entry); } return thrown; } } throw new Error("illegal catch attempt"); }, delegateYield: function delegateYield(iterable, resultName, nextLoc) { return this.delegate = { iterator: values(iterable), resultName: resultName, nextLoc: nextLoc }, "next" === this.method && (this.arg = undefined), ContinueSentinel; } }, exports; }

    function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }

    function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys(Object(source), !0).forEach(function (key) { babelHelpers.defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }

    function _createForOfIteratorHelper(o, allowArrayLike) { var it = typeof Symbol !== "undefined" && o[Symbol.iterator] || o["@@iterator"]; if (!it) { if (Array.isArray(o) || (it = _unsupportedIterableToArray(o)) || allowArrayLike && o && typeof o.length === "number") { if (it) o = it; var i = 0; var F = function F() {}; return { s: F, n: function n() { if (i >= o.length) return { done: true }; return { done: false, value: o[i++] }; }, e: function e(_e) { throw _e; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var normalCompletion = true, didErr = false, err; return { s: function s() { it = it.call(o); }, n: function n() { var step = it.next(); normalCompletion = step.done; return step; }, e: function e(_e2) { didErr = true; err = _e2; }, f: function f() { try { if (!normalCompletion && it["return"] != null) it["return"](); } finally { if (didErr) throw err; } } }; }

    function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }

    function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }
    var Editor = /*#__PURE__*/function () {
      function Editor(id) {
        babelHelpers.classCallCheck(this, Editor);
        babelHelpers.defineProperty(this, "productSelectionPopupHandler", this.handleProductSelectionPopup.bind(this));
        babelHelpers.defineProperty(this, "onDialogSelectProductHandler", this.handleOnDialogSelectProduct.bind(this));
        babelHelpers.defineProperty(this, "onBeforeGridRequestHandler", this.handleOnBeforeGridRequest.bind(this));
        babelHelpers.defineProperty(this, "onSaveHandler", this.handleOnSave.bind(this));
        babelHelpers.defineProperty(this, "onEntityUpdateHandler", this.handleOnEntityUpdate.bind(this));
        babelHelpers.defineProperty(this, "onEditorSubmit", this.handleEditorSubmit.bind(this));
        babelHelpers.defineProperty(this, "products", []);
        babelHelpers.defineProperty(this, "cache", new main_core.Cache.MemoryCache());
        this.setId(id);
        this.products = [];
      }

      babelHelpers.createClass(Editor, [{
        key: "init",
        value: function init() {
          var config = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
          this.setSettings(config);
          this.initProducts();
          this.subscribeDomEvents();
          this.subscribeCustomEvents();
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
        key: "initProducts",
        value: function initProducts() {
          this.products = [];
          var list = this.getSettingValue('items', []);

          var _iterator = _createForOfIteratorHelper(list),
              _step;

          try {
            for (_iterator.s(); !(_step = _iterator.n()).done;) {
              var item = _step.value;

              var fields = _objectSpread({}, item.fields);

              console.log(fields);
              this.products.push(new Row(item.rowId, fields, this));
            }
          } catch (err) {
            _iterator.e(err);
          } finally {
            _iterator.f();
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
            }); // container.querySelectorAll('[data-role="product-list-settings-button"]').forEach((configButton) => {
            //     Event.bind(
            //         configButton,
            //         'click',
            //         this.showSettingsPopupHandler
            //     );
            // });
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
            }); // container.querySelectorAll('[data-role="product-list-add-button"]').forEach((addButton) => {
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
      }, {
        key: "subscribeCustomEvents",
        value: function subscribeCustomEvents() {
          this.unsubscribeCustomEvents();
          main_core_events.EventEmitter.subscribe('CrmProductSearchDialog_SelectProduct', this.onDialogSelectProductHandler); // EventEmitter.subscribe('BX.Crm.EntityEditor:onSave', this.onSaveHandler);

          main_core_events.EventEmitter.subscribe('onEntityUpdate', this.onEntityUpdateHandler); // EventEmitter.subscribe('BX.Crm.EntityEditorAjax:onSubmit', this.onEditorSubmit);
          // EventEmitter.subscribe('EntityProductListController:onInnerCancel', this.onInnerCancelHandler);

          main_core_events.EventEmitter.subscribe('Grid::beforeRequest', this.onBeforeGridRequestHandler); // EventEmitter.subscribe('Grid::updated', this.onGridUpdatedHandler);
          // EventEmitter.subscribe('Grid::rowMoved', this.onGridRowMovedHandler);
          // EventEmitter.subscribe('BX.Catalog.ProductSelector:onBeforeChange', this.onBeforeProductChangeHandler);
          // EventEmitter.subscribe('BX.Catalog.ProductSelector:onChange', this.onProductChangeHandler);
          // EventEmitter.subscribe('BX.Catalog.ProductSelector:onClear', this.onProductClearHandler);
          // EventEmitter.subscribe('Dropdown::change', this.dropdownChangeHandler);
        }
      }, {
        key: "unsubscribeCustomEvents",
        value: function unsubscribeCustomEvents() {
          main_core_events.EventEmitter.unsubscribe('CrmProductSearchDialog_SelectProduct', this.onDialogSelectProductHandler); // EventEmitter.unsubscribe('BX.Crm.EntityEditor:onSave', this.onSaveHandler);

          main_core_events.EventEmitter.unsubscribe('onEntityUpdate', this.onEntityUpdateHandler); // EventEmitter.unsubscribe('BX.Crm.EntityEditorAjax:onSubmit', this.onEditorSubmit);
          // EventEmitter.unsubscribe('EntityProductListController:onInnerCancel', this.onInnerCancelHandler);

          main_core_events.EventEmitter.unsubscribe('Grid::beforeRequest', this.onBeforeGridRequestHandler); // EventEmitter.unsubscribe('Grid::updated', this.onGridUpdatedHandler);
          // EventEmitter.unsubscribe('Grid::rowMoved', this.onGridRowMovedHandler);
          // EventEmitter.unsubscribe('BX.Catalog.ProductSelector:onBeforeChange', this.onBeforeProductChangeHandler);
          // EventEmitter.unsubscribe('BX.Catalog.ProductSelector:onChange', this.onProductChangeHandler);
          // EventEmitter.unsubscribe('BX.Catalog.ProductSelector:onClear', this.onProductClearHandler);
          // EventEmitter.unsubscribe('Dropdown::change', this.dropdownChangeHandler);
        }
      }, {
        key: "handleOnDialogSelectProduct",
        value: function handleOnDialogSelectProduct(event) {
          var _event$getCompatData = event.getCompatData(),
              _event$getCompatData2 = babelHelpers.slicedToArray(_event$getCompatData, 1),
              productId = _event$getCompatData2[0];

          if (!this.getRowByProductId(productId)) {
            this.addProductRow(productId);
          } // if (this.getProductCount() > 0 || this.products[0]?.getField('ID') <= 0)
          // {
          //     // id = this.addProductRow();
          // }
          // else
          // {
          //     id = this.products[0]?.getField('ID');
          // }
          // this.selectProductInRow(id, productId)

        }
      }, {
        key: "getRowByProductId",
        value: function getRowByProductId(productId) {
          var result = this.products.filter(function (elem) {
            return +elem.getField('PRODUCT_ID') == +productId;
          });

          if (result.length > 0) {
            return result[0];
          }

          return null;
        }
      }, {
        key: "handleProductSelectionPopup",
        value: function handleProductSelectionPopup(event) {
          var caller = 'crm_productcard_applications_list';
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
        key: "getContainer",
        value: function getContainer() {
          // return this.cache.remember('container', () => {
          return document.getElementById(this.getContainerId()); // });
        }
      }, {
        key: "getContainerId",
        value: function getContainerId() {
          return this.getSettingValue('containerId', '');
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
        key: "getGridId",
        value: function getGridId() {
          return this.getSettingValue('gridId', '');
        }
      }, {
        key: "getGrid",
        value: function getGrid() {
          var _this3 = this;

          return this.cache.remember('grid', function () {
            var gridId = _this3.getGridId();

            if (!main_core.Reflection.getClass('BX.Main.gridManager.getInstanceById')) {
              throw Error("Cannot find grid with '".concat(gridId, "' id."));
            }

            return BX.Main.gridManager.getInstanceById(gridId);
          });
        }
      }, {
        key: "createGridProductRow",
        value: function createGridProductRow() {
          var newId = main_core.Text.getRandom(); // const originalTemplate = this.redefineTemplateEditData(newId);

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
          } // if (originalTemplate)
          // {
          //     this.setOriginalTemplateEditData(originalTemplate);
          // }


          main_core_events.EventEmitter.emit('Grid::thereEditedRows', []);
          grid.adjustRows();
          grid.updateCounterDisplayed();
          grid.updateCounterSelected();
          return newRow;
        }
      }, {
        key: "addProductRow",
        value: function () {
          var _addProductRow = babelHelpers.asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee(productId) {
            var row, newId;
            return _regeneratorRuntime().wrap(function _callee$(_context) {
              while (1) {
                switch (_context.prev = _context.next) {
                  case 0:
                    row = this.createGridProductRow();
                    newId = row.getId(); // if (anchorProduct)
                    // {
                    //     const anchorRowNode = this.getGrid().getRows().getById(anchorProduct.getField('ID'))?.getNode();
                    //     if (anchorRowNode)
                    //     {
                    //         anchorRowNode.parentNode.insertBefore(row.getNode(), anchorRowNode.nextSibling);
                    //     }
                    // }

                    _context.next = 4;
                    return this.initializeNewProductRow(newId, productId);

                  case 4:
                    this.getGrid().bindOnRowEvents();
                    return _context.abrupt("return", newId);

                  case 6:
                  case "end":
                    return _context.stop();
                }
              }
            }, _callee, this);
          }));

          function addProductRow(_x) {
            return _addProductRow.apply(this, arguments);
          }

          return addProductRow;
        }()
      }, {
        key: "getRowIdPrefix",
        value: function getRowIdPrefix() {
          return this.getSettingValue('rowIdPrefix', 'productcard_applications_list');
        }
      }, {
        key: "getProductSelector",
        value: function getProductSelector(newId) {
          return catalog_productSelector.ProductSelector.getById('crm_grid_' + this.getRowIdPrefix() + newId);
        }
      }, {
        key: "reloadGrid",
        value: function reloadGrid() {
          var _this4 = this;

          var useProductsFromRequest = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : true;

          this.getGrid().reloadTable('POST', {
            useProductsFromRequest: useProductsFromRequest
          }, function () {
            return main_core_events.EventEmitter.emit(_this4, 'onGridReloaded');
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
          eventArgs.data = _objectSpread(_objectSpread({}, eventArgs.data), {}, {
            signedParameters: this.getSignedParameters(),
            products: useProductsFromRequest ? this.getProductsFields() : null // locationId: this.getLocationId(),
            // currencyId: this.getCurrencyId(),

          });
        }
      }, {
        key: "getReloadUrl",
        value: function getReloadUrl() {
          return this.getSettingValue('reloadUrl', '');
        }
      }, {
        key: "getComponentName",
        value: function getComponentName() {
          return this.getSettingValue('componentName', 'kitconsulting:catalog.productcard.applications');
        }
      }, {
        key: "getSignedParameters",
        value: function getSignedParameters() {
          return this.getSettingValue('signedParameters', '');
        }
      }, {
        key: "initializeNewProductRow",
        value: function () {
          var _initializeNewProductRow = babelHelpers.asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee2(newId, productId) {
            var productFields, fields, rowId, productRow;
            return _regeneratorRuntime().wrap(function _callee2$(_context2) {
              while (1) {
                switch (_context2.prev = _context2.next) {
                  case 0:
                    _context2.next = 2;
                    return this.fetchProduct(productId);

                  case 2:
                    productFields = _context2.sent;
                    fields = {
                      ID: newId,
                      PRODUCT_ID: productId,
                      NAME: productFields['NAME']
                    };
                    rowId = this.getRowIdPrefix() + newId; // fields.ID = newId;

                    productRow = new Row(rowId, fields, this);

                    if (this.getSettingValue('newRowPosition') === 'bottom') {
                      this.products.push(productRow);
                    } else {
                      this.products.unshift(productRow);
                    }

                    return _context2.abrupt("return", productRow);

                  case 8:
                  case "end":
                    return _context2.stop();
                }
              }
            }, _callee2, this);
          }));

          function initializeNewProductRow(_x2, _x3) {
            return _initializeNewProductRow.apply(this, arguments);
          }

          return initializeNewProductRow;
        }()
      }, {
        key: "getProductsFields",
        value: function getProductsFields() {
          var productFields = [];

          var _iterator2 = _createForOfIteratorHelper(this.products),
              _step2;

          try {
            for (_iterator2.s(); !(_step2 = _iterator2.n()).done;) {
              var item = _step2.value;
              productFields.push(item.getFields());
            }
          } catch (err) {
            _iterator2.e(err);
          } finally {
            _iterator2.f();
          }

          return productFields;
        }
      }, {
        key: "fetchProduct",
        value: function () {
          var _fetchProduct = babelHelpers.asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee3(product_id) {
            var componentName, result;
            return _regeneratorRuntime().wrap(function _callee3$(_context3) {
              while (1) {
                switch (_context3.prev = _context3.next) {
                  case 0:
                    componentName = this.getComponentName();
                    _context3.next = 3;
                    return BX.ajax.runComponentAction(componentName, 'getProduct', {
                      mode: 'ajax',
                      signedParameters: this.getSignedParameters(),
                      data: {
                        product_id: product_id
                      }
                    });

                  case 3:
                    result = _context3.sent;
                    return _context3.abrupt("return", result.data);

                  case 5:
                  case "end":
                    return _context3.stop();
                }
              }
            }, _callee3, this);
          }));

          function fetchProduct(_x4) {
            return _fetchProduct.apply(this, arguments);
          }

          return fetchProduct;
        }()
      }, {
        key: "deleteRow",
        value: function deleteRow(rowId) {

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
            }
          }

          main_core_events.EventEmitter.emit('Grid::thereEditedRows', []);
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
        key: "handleOnSave",
        value: function handleOnSave(event) {
          var items = [];
          this.products.forEach(function (product) {
            var item = {
              fields: _objectSpread({}, product.fields),
              rowId: product.fields.ROW_ID
            };
            items.push(item);
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

          {
            main_core.ajax.runComponentAction(this.getComponentName(), 'saveApplications', {
              mode: 'ajax',
              signedParameters: this.getSignedParameters(),
              data: {
                rows: this.getProductsFields()
              }
            }).then(function (response) {
              // this.setGridChanged(false);
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
      }]);
      return Editor;
    }();

    var _templateObject;

    function _classPrivateMethodInitSpec(obj, privateSet) { _checkPrivateRedeclaration(obj, privateSet); privateSet.add(obj); }

    function _checkPrivateRedeclaration(obj, privateCollection) { if (privateCollection.has(obj)) { throw new TypeError("Cannot initialize the same private elements twice on an object"); } }

    function _classPrivateMethodGet(receiver, privateSet, fn) { if (!privateSet.has(receiver)) { throw new TypeError("attempted to get private field on non-instance"); } return fn; }

    var _initActions = /*#__PURE__*/new WeakSet();

    var Row = /*#__PURE__*/function () {
      // model: ?ProductModel;
      // mainSelector: ?ProductSelector;
      // handleFocusUnchangeablePrice = this.#showChangePriceNotify.bind(this);
      // handleChangeStoreData = this.#onChangeStoreData.bind(this);
      // handleProductErrorsChange = Runtime.debounce(this.#onProductErrorsChange, 500, this);
      // handleMainSelectorClear = Runtime.debounce(this.#onMainSelectorClear.bind(this), 500, this);
      // // handleMainSelectorChange = Runtime.debounce(this.#onMainSelectorChange.bind(this), 500, this);
      // handleMainSelectorChange = this.#onMainSelectorChange.bind(this);
      // handleStoreFieldChange = Runtime.debounce(this.#onStoreFieldChange.bind(this), 500, this);
      // handleStoreFieldClear = Runtime.debounce(this.#onStoreFieldClear.bind(this), 500, this);
      // handleOnGridUpdated = this.#onGridUpdated.bind(this);
      function Row(id, fields, editor) {
        babelHelpers.classCallCheck(this, Row);

        _classPrivateMethodInitSpec(this, _initActions);

        babelHelpers.defineProperty(this, "fields", {});
        babelHelpers.defineProperty(this, "cache", new main_core.Cache.MemoryCache());
        this.setId(id); // this.setSettings(settings);

        this.setEditor(editor); // this.setModel(fields, settings);

        this.setFields(fields);

        _classPrivateMethodGet(this, _initActions, _initActions2).call(this); // // this.#initSelector();
        // // this.#initStoreSelector();
        // // this.#initStoreAvailablePopup();
        // // this.#initReservedControl();
        // this.modifyBasePriceInput();


        this.refreshFieldsLayout(); //
        // requestAnimationFrame(this.initHandlers.bind(this));
      }

      babelHelpers.createClass(Row, [{
        key: "setFields",
        value: function setFields(fields) {
          this.fields = fields;
        }
      }, {
        key: "getFields",
        value: function getFields() {
          return this.fields;
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
        key: "setId",
        value: function setId(id) {
          this.id = id;
        }
      }, {
        key: "getId",
        value: function getId() {
          return this.id;
        }
      }, {
        key: "handleDeleteAction",
        value: function handleDeleteAction(event, menuItem) {
          var _this$getEditor;

          (_this$getEditor = this.getEditor()) === null || _this$getEditor === void 0 ? void 0 : _this$getEditor.deleteRow(this.getField('ID'));
          var menu = menuItem.getMenuWindow();

          if (menu) {
            menu.destroy();
          }
        }
      }, {
        key: "refreshFieldsLayout",
        value: function refreshFieldsLayout() {
          for (var field in this.fields) {
            this.updateUiField(field, this.fields[field]);
          }
        }
      }, {
        key: "updateUiField",
        value: function updateUiField(name, value) {
          var item = this.getInputByFieldName(name);

          if (main_core.Type.isElementNode(item)) {
            item.textContent = value;
          }
        } // controls

      }, {
        key: "getInputByFieldName",
        value: function getInputByFieldName(fieldName) {
          return this.getNode().querySelector('[data-field="' + fieldName + '"]');
        }
      }, {
        key: "getNode",
        value: function getNode() {
          var _this = this;

          return this.cache.remember('node', function () {
            var rowId = _this.getField('ID', 0);

            return _this.getEditorContainer().querySelector('[data-id="' + rowId + '"]');
          });
        }
      }, {
        key: "getEditorContainer",
        value: function getEditorContainer() {
          return this.getEditor().getContainer();
        }
      }, {
        key: "getField",
        value: function getField(name, defaultValue) {
          return this.fields.hasOwnProperty(name) ? this.fields[name] : defaultValue;
        }
      }]);
      return Row;
    }();

    function _initActions2() {
      var _this2 = this;

      var actionCellContentContainer = this.getNode().querySelector('.main-grid-cell-action .main-grid-cell-content');

      if (main_core.Type.isDomNode(actionCellContentContainer)) {
        var actionsButton = main_core.Tag.render(_templateObject || (_templateObject = babelHelpers.taggedTemplateLiteral(["\n\t\t\t\t<a\n\t\t\t\t\thref=\"#\"\n\t\t\t\t\tclass=\"main-grid-row-action-button\"\n\t\t\t\t></a>\n\t\t\t"])));
        main_core.Event.bind(actionsButton, 'click', function (event) {
          var menuItems = [{
            text: main_core.Loc.getMessage('ROW_ACTION_REMOVE'),
            onclick: _this2.handleDeleteAction.bind(_this2)
          }];
          main_popup.PopupMenu.show({
            id: _this2.getId() + '_actions_popup',
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

    exports.PageEventsManager = PageEventsManager;
    exports.Row = Row;
    exports.Editor = Editor;

}((this.BX.Kitconsulting.ProductCard.Applications = this.BX.Kitconsulting.ProductCard.Applications || {}),BX,BX,BX,BX.Catalog,BX.Event,BX.Main,BX.Catalog));
//# sourceMappingURL=script.js.map
