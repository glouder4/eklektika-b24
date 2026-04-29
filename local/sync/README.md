# Синхронизация Bitrix24 ↔ сайт

Зона интеграции на стороне портала. Новую логику обмена с сайтом размещать **здесь**, не размазывая по `local/` без структуры.

Документация по каналам и транспорту на стороне сайта: [`bitrix-docker/www/local/sync/`](../../../bitrix-docker/www/local/sync/) (корень монорепозитория `eklektika-ru`). Канон предметного контракта — там; при работе только в этом репозитории — либо `docs/b24-inbound.md` со ссылкой на сайт, либо одна ссылка без дубля текста (см. `functional-contract.md` §6 на сайте).

## Содержимое

| Путь | Назначение |
|------|------------|
| [`to-site/`](to-site/) | Канал **CRM → сайт** (исходящие события и HTTP на сайт) |
| [`from-site/`](from-site/) | Канал **сайт → CRM** (входящие сценарии, точка расширения) |
| [`docs/`](docs/) | Документация, специфичная для CRM (отправка на сайт, приём с сайта) |
| [`bootstrap.php`](bootstrap.php) | Единая точка подключения классов синхронизации |

## Текущее подключение

- `local/php_interface/init.php` подключает `local/events/requires.php`.
- `local/events/requires.php` подключает `local/sync/bootstrap.php` и затем `local/events/events.php`.
- `local/events/events.php` регистрирует обработчики через `Bitrix\Main\EventManager`.
- Модульный контрактный endpoint входящего канала: `/local/modules/yomerch.b24.inbound/endpoint.php` (прокси в `from-site/site_requests_handler.php`).
- `local/modules/yomerch.b24.inbound` используется как контейнер endpoint, это не отдельный бизнес-модуль Bitrix.

## Каналы (логически)

- **CRM → сайт** — `OnlineService\Sync\ToSite\*`, события и `sendRequest` на URL сайта.
- **Сайт → CRM** — `OnlineService\Sync\FromSite\*`, отдельная зона кода для входящих сценариев.

Подключаемые точки по-прежнему: `local/php_interface/init.php` → `local/events/`.
