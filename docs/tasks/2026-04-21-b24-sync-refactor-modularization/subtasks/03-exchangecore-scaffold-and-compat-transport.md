# ST-03: Bitrix24SiteConnectorCore scaffold и compat transport

## Связь с задачей
- Родительская задача: [TASK-2026-04-21-b24-sync-refactor-modularization](../README.md)
- Внешние ссылки:
  - нет

## Цель подзадачи
Ввести модуль `Bitrix24SiteConnectorCore` как новый слой transport/event orchestration без изменения внешнего контракта синхронизации.

## Описание работ
1. Создать каркас директории `local/classes/site/Bitrix24SiteConnectorCore/`:
   - `Transport/`
   - `Routing/`
   - `Guard/`
   - `Contracts/`
2. Реализовать `HttpTransportClient` на базе текущей логики `UpdaterAbstract::sendRequest`.
3. Ввести `ActionDispatcher` для единообразной отправки payload с `ACTION`.
4. Добавить `LoopGuard`/`IdempotencyContext` (trace-id, depth, origin marker).
5. Создать compat-адаптеры, чтобы legacy updater-классы использовали `Bitrix24SiteConnectorCore` без изменения публичных сигнатур.

## Технические детали
- Компоненты/модули:
  - `local/classes/site/UpdaterAbstract.php`
  - Новый модуль `local/classes/site/Bitrix24SiteConnectorCore/*`
- Изменяемые файлы/области:
  - `local/classes/site/UpdaterAbstract.php` (минимальный мост)
  - `local/classes/site/*Updater.php` (инъекция/вызов compat dispatcher)

## Зависимости
- Блокируется:
  - ST-02
- Блокирует:
  - ST-04
  - ST-05
  - ST-06

## Критерии приёмки
- [ ] Введён `Bitrix24SiteConnectorCore` каркас с контрактами transport/routing/guard
- [ ] Все вызовы отправки payload проходят через единый dispatcher (напрямую или через compat adapter)
- [ ] Поведение legacy контрактов (`ACTION`, endpoint, формат response) не изменилось
- [ ] В логах доступны trace-id и базовые метрики времени для вызовов

## Проверка
- Unit/интеграционные проверки:
  - Контрактный тест `sendRequest` legacy vs `Bitrix24SiteConnectorCore` на идентичность payload/response handling
- Ручной сценарий:
  - Выполнить обновление компании/контакта/менеджера/статуса и убедиться, что вызовы уходят на тот же endpoint и без ошибок

## Документация
- Изученные документы:
  - `docs/features/README.md`
  - ADR миграции из ST-02
- Что обновить:
  - Документ `Bitrix24SiteConnectorCore` (контракты и примеры подключения legacy адаптера)
- Что создать (если нужно):
  - Раздел по loop guard и idempotency policy

## Статус
- planned
