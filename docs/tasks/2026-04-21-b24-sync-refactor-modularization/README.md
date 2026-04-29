# Рефакторинг B24 синхронизаций в Bitrix24SiteConnectorCore / HoldingModel / EntityFieldSync

## Метаданные
- ID: TASK-2026-04-21-b24-sync-refactor-modularization
- Статус: in_progress (module split завершен, refactor wave по dedup в работе)
- Приоритет: high
- Дата создания: 2026-04-21
- Ответственный: нет

## Текущая упаковка коннектора (2026-04-29)
Код B24↔site разнесён по локальным модулям **`yomerch.b24.base` | `contract` | `deals` | `inbound` | `legacy` | `outbound`** с оркестратором **`yomerch.b24.siteconnector`** (настройки `site_sync_settings*.php`, `lib/bootstrap_autoload.php`, регистрация CRM/обработчиков). Автозагрузка: в каждом пакете свой `include.php` (`Loader::registerAutoLoadClasses`). Полный вход: `local/php_interface/init.php` → `local/modules/bootstrap.php` — **glob `*/include.php` + lexical sort** (канонический порядок пакетов: base → … → outbound → siteconnector). Прямой inbound без полного init: `yomerch.b24.inbound` тянет [`bootstrap_autoload.php`](../../../local/modules/yomerch.b24.siteconnector/lib/bootstrap_autoload.php) с тем же логическим порядком подмодулей. Прокси в `local/classes/` сохранены для совместимости URL. Удалены верхнеуровневые `local/sync`, `local/events`, `local/site_connector`. Подробнее: [`adr-yomerch-b24-siteconnector-consolidation.md`](../../features/adr-yomerch-b24-siteconnector-consolidation.md), [`adr-yomerch-b24-modular-integration-packages.md`](../../features/adr-yomerch-b24-modular-integration-packages.md).

## Текущий фокус refactor-wave (2026-04-29)
- dedup-цель 1: unification `deals` pipeline между cron / agent / event handlers;
- dedup-цель 2: consolidation inbound ACTION handling в `yomerch.b24.inbound`;
- dedup-цель 3: decomposition roadmap для `CompanySync` с phase-based cutover.
- архитектурный якорь волны: [`adr-yomerch-b24-refactor-wave-dedup-roadmap.md`](../../features/adr-yomerch-b24-refactor-wave-dedup-roadmap.md).

## Ссылки на источники
- Issue: нет
- PRD/Док: нет
- Доп. контекст: нет

## Цель
Текущая синхронизация в `local/classes/site` концентрирует transport, доменные правила холдинга и обработку CRM-полей в нескольких tightly coupled классах (`CompanyUpdater`, `ContactUpdater`, `ManagerUpdater`, `CompanyStatusUpdater`, `UpdaterAbstract`). Это усложняет расширение, повышает риск регрессий и увеличивает цену изменений в обработчиках событий Bitrix.

Цель задачи — поэтапно выделить модульный каркас из трех зон ответственности без big-bang миграции: `Bitrix24SiteConnectorCore` (transport/event routing), `HoldingModel` (структура холдингов + `DiscountPolicy`), `EntityFieldSync` (синхронизация полей сущностей), сохранив обратную совместимость и существующие интеграционные контракты (`ACTION` payload и EventManager wiring).

Ожидаемый эффект: прозрачные границы модулей, управляемая миграция через feature toggle/compat adapters, снижение риска циклов обновлений и потери полей, а также подготовленная документация архитектуры и сценариев ручной проверки.

## Границы (Scope)
- In scope:
  - Анализ текущих зависимостей `CompanyUpdater`/`ContactUpdater`/`ManagerUpdater`/`CompanyStatusUpdater`/`UpdaterAbstract` и wiring в `events.php`, `requires.php`, `init.php`.
  - Проектирование и внедрение каркаса `Bitrix24SiteConnectorCore` (transport, dispatcher, event routing, guard).
  - Выделение `HoldingModel` (агрегаты холдинга, `DiscountPolicy`, сервисы наследования/пропагации скидки).
  - Выделение `EntityFieldSync` (компания/контакт/менеджер/статус) и точки расширения `ClientAddon`.
  - Миграция обработчиков событий без отключения старого поведения до завершения cutover.
  - Обновление и создание документации в `docs/features/`.
- Out of scope:
  - Изменения во внешних/сторонних зонах (`local/modules/intec.eklectika/`, `script/crm/rest/`, namespace `intec\eklectika\`).
  - Перепроектирование бизнес-процессов CRM, не связанных с текущими синхронизациями.
  - Миграция всех legacy-классов проекта вне целевых апдейтеров.

## План внедрения
1. Зафиксировать документационную базу (`docs/features`) и целевой ADR по модульной архитектуре.
2. Ввести `Bitrix24SiteConnectorCore` как совместимый слой transport/event orchestration поверх текущих контрактов.
3. Извлечь `HoldingModel` из `CompanyUpdater` с сохранением существующего поведения наследования и пропагации скидок.
4. Извлечь `EntityFieldSync` для отдельных сущностей и внедрить `ClientAddon` extension points.
5. Выполнить поэтапный cutover wiring событий Bitrix и провести shadow-валидацию payload/side-effects.
6. Завершить deprecation legacy-путей, обновить документацию и финальные регрессионные проверки.

## Подзадачи
- [ ] [ST-01: Базовый индекс документации features](./subtasks/01-features-docs-index-bootstrap.md)
- [ ] [ST-02: Архитектурный ADR и миграционная карта модулей](./subtasks/02-architecture-adr-and-migration-map.md) — in_progress (ADR по волне dedup добавлен)
- [ ] [ST-03: Bitrix24SiteConnectorCore scaffold и compat transport](./subtasks/03-exchangecore-scaffold-and-compat-transport.md)
- [ ] [ST-04: HoldingModel extraction и DiscountPolicy](./subtasks/04-holdingmodel-extraction-and-discount-policy.md)
- [ ] [ST-05: EntityFieldSync extraction и ClientAddon](./subtasks/05-entityfieldsync-extraction-and-client-addon.md)
- [ ] [ST-06: Поэтапный event wiring cutover и shadow-run](./subtasks/06-event-wiring-cutover-and-shadow-run.md)
- [ ] [ST-07: Финальный hardening, deprecation и release-checklist](./subtasks/07-hardening-deprecation-and-release-checklist.md)

## Зависимости и риски
- Зависимости:
  - Доступность тестового контура Bitrix с репрезентативными данными компаний/контактов/менеджеров.
  - Актуальность UF-карты для test/prod и валидность обязательных полей.
  - Доступ к логам для сравнения legacy/new payload (в т.ч. `bitrix-debug.log`).
- Риски:
  - Регрессии триггеров Bitrix-событий и пропуск обязательных side-effects.
  - Циклические обновления (company -> contacts -> company children) после выноса логики.
  - Потеря/искажение полей при миграции маппинга в `EntityFieldSync`.
  - Рост времени обработки событий из-за дополнительной маршрутизации.
  - **Остаточный:** закладки, wiki и конфиги сайта с URL на удалённые пути `/local/sync/...` (и аналоги) — обновить на актуальные endpoint’ы модуля/прокси.
  - Нет согласованной метрики "dedup done" для контролируемого завершения refactor-wave.
- Митигации:
  - Включать новый путь через feature toggle и shadow-run сравнений payload до полного переключения.
  - Добавить loop guard/idempotency marker и защиту глубины рекурсии в `Bitrix24SiteConnectorCore`.
  - Ввести контрактные проверки payload и ручные smoke-сценарии по каждой сущности.
  - Собирать метрики времени обработки до/после и оптимизировать skip unchanged updates.

## Критерии готовности задачи
- [ ] Все подзадачи закрыты
- [ ] Выполнены критерии приёмки
- [ ] Обновлена документация в `docs/features/`
