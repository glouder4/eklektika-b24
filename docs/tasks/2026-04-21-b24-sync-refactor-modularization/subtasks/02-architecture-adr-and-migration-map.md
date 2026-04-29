# ST-02: Архитектурный ADR и миграционная карта модулей

## Связь с задачей
- Родительская задача: [TASK-2026-04-21-b24-sync-refactor-modularization](../README.md)
- Внешние ссылки:
  - нет

## Цель подзадачи
Зафиксировать целевую архитектуру и путь миграции без big-bang через ADR и пошаговую карту переключения.

## Описание работ
1. Описать текущие dependency edges: event handlers -> updater classes -> sendRequest + domain rules.
2. Определить target boundaries и интерфейсы:
   - `Bitrix24SiteConnectorCore`: `TransportClientInterface`, `ActionDispatcherInterface`, `EventRouterInterface`, `LoopGuardInterface`.
   - `HoldingModel`: `HoldingRepositoryInterface`, `DiscountPolicyInterface`, `HoldingServiceInterface`.
   - `EntityFieldSync`: `EntitySyncHandlerInterface`, `FieldMapProviderInterface`, `ClientAddonInterface`.
3. Зафиксировать стратегию совместимости: legacy wrappers + feature toggle + shadow logging.
4. Подготовить диаграмму/таблицу миграции по сущностям и событиям.

## Технические детали
- Компоненты/модули:
  - `docs/features/` архитектурные документы
  - **Пакеты интеграции:** `local/modules/yomerch.b24.{base,contract,deals,inbound,legacy,outbound}/` (`include.php` в каждом), оркестратор **`yomerch.b24.siteconnector`** (`site_sync_settings*.php`, `lib/bootstrap_autoload.php`, CRM handlers — `lib/register_bitrix_handlers.php` и связанное). Вход с сайта: `yomerch.b24.inbound` (`lib/site_requests_handler.php`, `endpoint.php`). См. [ADR modular packages](../../../features/adr-yomerch-b24-modular-integration-packages.md).
  - Legacy-прослойка: `local/classes/requires.php` (может звать `bootstrap_autoload`), `local/classes/events.php`, прокси `local/classes/ajax.php`, `local/classes/site_requests_handler.php`
  - `local/php_interface/init.php` (wiring при необходимости)
- Изменяемые файлы/области:
  - `docs/features/*` (ADR и миграционная карта) — см. [`adr-yomerch-b24-siteconnector-consolidation.md`](../../../features/adr-yomerch-b24-siteconnector-consolidation.md), [`adr-yomerch-b24-modular-integration-packages.md`](../../../features/adr-yomerch-b24-modular-integration-packages.md)

## Зависимости
- Блокируется:
  - ST-01
- Блокирует:
  - ST-03
  - ST-04
  - ST-05
  - ST-06

## Критерии приёмки
- [x] Зафиксирована консолидация коннектора в `yomerch.b24.siteconnector` (ADR consolidation)
- [x] Есть ADR с описанием пакетов `yomerch.b24.*`, автозагрузки (`include.php`), порядка `bootstrap.php`/`bootstrap_autoload` — см. [`adr-yomerch-b24-modular-integration-packages.md`](../../../features/adr-yomerch-b24-modular-integration-packages.md)
- [x] Есть ADR с целевыми интерфейсами/направлениями refactor-wave и dedup-приоритетами — см. [`adr-yomerch-b24-refactor-wave-dedup-roadmap.md`](../../../features/adr-yomerch-b24-refactor-wave-dedup-roadmap.md)
- [x] Есть карта миграции событий/сущностей с этапами cutover и rollback-точками (W1 draft ниже)
- [x] Зафиксированы compatibility contract и правила включения feature toggle

## Проверка
- Unit/интеграционные проверки:
  - нет
- Ручной сценарий:
  - Пройти по ADR и проверить, что каждый legacy updater имеет явный target-модуль и этап миграции

## Документация
- Изученные документы:
  - `docs/features/README.md` (после ST-01)
- Что обновить:
  - Архитектурный документ по синхронизациям
  - Документ стратегии миграции и rollback
- Что создать (если нужно):
  - дополнительные ADR по интерфейсам Core/HoldingModel/EntityFieldSync — после завершения W1 cutover

## Обновления волны W1 (2026-04-29)
- Добавлен ADR: [`adr-yomerch-b24-refactor-wave-dedup-roadmap.md`](../../../features/adr-yomerch-b24-refactor-wave-dedup-roadmap.md).
- Зафиксированы три dedup-приоритета: deals pipeline, inbound operations, CompanySync decomposition.
- Подтверждено архитектурное ограничение: тонкие аддоны в `yomerch.b24.*`, orchestration в `yomerch.b24.siteconnector`.

## Migration map (W1 draft)
| Поток | Текущий контур | Целевой контур | Cutover marker | Rollback marker |
|---|---|---|---|---|
| Deals pipeline | `check_deals_status.php` + `DealExpiryAgent` + `DealExpiryHandler` | единый rule/pipeline сервис в `yomerch.b24.deals` | feature flag на общий status-rule сервис | возврат на текущие раздельные ветки cron/event |
| Inbound operations | `site_requests_handler.php` + `InboundEndpoint` | unified ACTION router + общие normalizers/errors в `yomerch.b24.inbound` | action-dispatch через единую карту обработчиков | откат на legacy dispatch |
| CompanySync | `CompanySync.php` + связанные UF/holding ветки | phase A: сервисы read/normalize/policy, mutation в legacy классе | подключение сервисов к read/normalize сегменту | отключение новых сервисов и возврат legacy потока |

## Чеклист W1
- [x] Архитектурный ADR волны и dedup-приоритеты зафиксированы.
- [x] Миграционная таблица с cutover/rollback маркерами зафиксирована.
- [ ] Определены feature toggles и shadow logging по каждому потоку.
- [ ] Подготовлены smoke-сценарии на ключевые ACTION и deal status transitions.
- [ ] Согласована количественная метрика "dedup done".

## Next steps for Team Lead
1. **P1 — Deals pipeline unification**
   - Зависимости: текущие cron/event сценарии, модуль `yomerch.b24.deals`.
   - Действия: выделить общий service/rules слой и переиспользовать его из cron + agent + event.
   - Критерий готовности: обе ветки (cron/event) используют один и тот же сервис правил с fallback на legacy.
   - Артефакты проверки: diff модуля `deals`, smoke-лог сравнения old/new решений по статусу.
2. **P1 — Inbound operation consolidation**
   - Зависимости: `site_requests_handler.php`, `InboundEndpoint.php`, контрактный пакет `bitrix24-external-developers`.
   - Действия: внедрить unified ACTION map и общий слой нормализации payload/errors.
   - Критерий готовности: единая таблица маршрутизации, отсутствие дублей в основных ACTION-ветках.
   - Артефакты проверки: чеклист ST-08 + трассировка трех ключевых ACTION.
3. **P2 — CompanySync decomposition phase A**
   - Зависимости: `CompanySync.php`, `UfMap`, outbound request flow.
   - Действия: вынести read/normalize/policy в отдельные сервисы без полного отключения legacy mutation.
   - Критерий готовности: новый сервисный слой подключен и не меняет контракт outbound payload.
   - Артефакты проверки: mapping "legacy method -> new service", shadow-run отчёт.
4. **P2 — Unified cutover observability**
   - Зависимости: логирование inbound/outbound/deals, request-id в текущем потоке.
   - Действия: ввести единый correlation id и cutover source label для всех трех потоков.
   - Критерий готовности: одна транзакция трассируется end-to-end.
   - Артефакты проверки: пример end-to-end лога для тестового запроса.

## Риски / блокеры
- Нет единого baseline-набора регрессионных сценариев legacy vs new.
- Возможен контрактный drift при изменениях inbound до полного закрытия ST-08.
- Частичный cutover `CompanySync` может сохранить скрытые side-effects в legacy hooks.

## Статус
- in_progress (W1 migration map и ADR волны зафиксированы; в работе implementation wave у Team Lead)
