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
  - `local/classes/site` (источник текущей логики)
  - `local/classes/events.php`, `local/php_interface/init.php`
- Изменяемые файлы/области:
  - `docs/features/*` (ADR и миграционная карта)

## Зависимости
- Блокируется:
  - ST-01
- Блокирует:
  - ST-03
  - ST-04
  - ST-05
  - ST-06

## Критерии приёмки
- [ ] Есть ADR с описанием модулей, границ ответственности и интерфейсов
- [ ] Есть карта миграции событий/сущностей с этапами cutover и rollback-точками
- [ ] Зафиксированы compatibility contract и правила включения feature toggle

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
  - ADR-файл в `docs/features/` для модульного рефакторинга

## Статус
- planned
