# ST-06: Поэтапный event wiring cutover и shadow-run

## Связь с задачей
- Родительская задача: [TASK-2026-04-21-b24-sync-refactor-modularization](../README.md)
- Внешние ссылки:
  - нет

## Цель подзадачи
Переключить обработчики событий Bitrix на новую модульную архитектуру поэтапно и безопасно через shadow-run с обратной совместимостью.

## Описание работ
1. Ввести конфигурацию cutover (`legacy`, `shadow`, `new`) на уровне event routing.
2. Переключать события итерациями:
   - сначала `CompanyStatusUpdater`
   - затем `ManagerUpdater`
   - затем `ContactUpdater`
   - затем `CompanyUpdater` (последним, как наиболее рискованный путь)
3. Для shadow-режима логировать legacy/new payload и результат side-effects с trace-id.
4. Для каждого события подготовить rollback switch на legacy путь.
5. Обновить wiring в `local/classes/events.php` и подключение в `local/classes/requires.php`/`local/php_interface/init.php` без удаления legacy до финального этапа.

## Технические детали
- Компоненты/модули:
  - `local/classes/events.php`
  - `local/classes/requires.php`
  - `local/php_interface/init.php`
  - `Bitrix24SiteConnectorCore`, `HoldingModel`, `EntityFieldSync`
- Изменяемые файлы/области:
  - Слой маршрутизации обработчиков событий
  - Конфигурация feature toggle/режимов cutover

## Зависимости
- Блокируется:
  - ST-04
  - ST-05
- Блокирует:
  - ST-07

## Критерии приёмки
- [ ] Все целевые события переведены в управляемый режим `legacy/shadow/new`
- [ ] Есть журнал сравнения payload legacy/new для каждой сущности
- [ ] Rollback до legacy выполняется конфигурационно без изменения кода
- [ ] После стабилизации в `new` режиме отсутствуют критические расхождения side-effects

## Проверка
- Unit/интеграционные проверки:
  - Тест маршрутизации event -> handler в каждом режиме cutover
  - Проверка, что loop guard блокирует повторный циклический вызов
- Ручной сценарий:
  - Пройти регрессионные сценарии CRUD/update для компании/контакта/менеджера/статуса в `shadow`, затем в `new`

## Документация
- Изученные документы:
  - Документы `Bitrix24SiteConnectorCore`, `HoldingModel`, `EntityFieldSync`
- Что обновить:
  - Руководство по cutover и rollback
  - Чеклист ручной проверки событий Bitrix
- Что создать (если нужно):
  - Таблица соответствия legacy handler -> new handler

## Статус
- planned
