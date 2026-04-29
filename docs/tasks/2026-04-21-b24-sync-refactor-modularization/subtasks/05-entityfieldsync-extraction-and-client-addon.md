# ST-05: EntityFieldSync extraction и ClientAddon

## Связь с задачей
- Родительская задача: [TASK-2026-04-21-b24-sync-refactor-modularization](../README.md)
- Внешние ссылки:
  - нет

## Цель подзадачи
Вынести синхронизацию полей сущностей в модуль `EntityFieldSync` с расширяемостью через `ClientAddon`.

## Описание работ
1. Создать `EntityFieldSync` как набор обработчиков сущностей:
   - `CompanyFieldSyncHandler`
   - `ContactFieldSyncHandler`
   - `ManagerFieldSyncHandler`
   - `CompanyStatusFieldSyncHandler`
2. Вынести маппинг UF/CRM полей в `FieldMapProvider` с учётом test/prod конфигурации.
3. Определить `ClientAddonInterface` для кастомных post-process хуков и tenant-специфичных расширений.
4. Подключить legacy updater-классы к новым handler-ам через compat facade, сохранив сигнатуры событий.
5. Подготовить контрактные проверки полноты payload и обязательных полей.

## Технические детали
- Компоненты/модули:
  - `local/classes/site/CompanyUpdater.php`
  - `local/classes/site/ContactUpdater.php`
  - `local/classes/site/ManagerUpdater.php`
  - `local/classes/site/CompanyStatusUpdater.php`
  - Новый модуль `local/classes/site/EntityFieldSync/*`
- Изменяемые файлы/области:
  - `local/classes/site/*Updater.php`
  - `local/classes/site/EntityFieldSync/`

## Зависимости
- Блокируется:
  - ST-03
  - ST-02
- Блокирует:
  - ST-06
  - ST-07

## Критерии приёмки
- [ ] Введён модуль `EntityFieldSync` с отдельными handler-ами по сущностям
- [ ] Маппинг полей централизован и не дублируется между updater-классами
- [ ] Определён и задокументирован `ClientAddonInterface`
- [ ] Контрактные проверки подтверждают отсутствие потери обязательных полей в payload

## Проверка
- Unit/интеграционные проверки:
  - Тесты map provider (test/prod UF map)
  - Тесты формирования payload по каждой сущности
- Ручной сценарий:
  - Обновить компанию/контакт/менеджера/статус и сверить payload legacy/new в shadow режиме

## Документация
- Изученные документы:
  - `docs/features/README.md`
  - ADR миграции из ST-02
- Что обновить:
  - Документ `EntityFieldSync` с контрактом handler-ов и field mapping
- Что создать (если нужно):
  - Документ по `ClientAddon` с примерами расширения

## Статус
- planned
