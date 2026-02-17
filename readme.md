# MAX API

## PHP SDK
В репозиторий добавлена легковесная PHP-библиотека `MaxApiClient`, которая инкапсулирует все методы, перечисленные в https://dev.max.ru/docs-api. Подключение выполняется через Composer (PSR-4 namespace `MaxApi\`). Клиент предоставляет единообразную обёртку над REST API MAX, отвечает за сериализацию JSON, установку заголовков авторизации и обработку ошибок.

### Установка
```bash
composer install
```
Или добавьте репозиторий как зависимость в другом проекте и выполните `composer require koovod/max-api-php-client`. Требуется PHP 8.1+ и расширение `ext-curl`.

### Использование
```php
<?php

require __DIR__ . '/vendor/autoload.php';

use MaxApi\MaxApiClient;

$client = new MaxApiClient('Authorization: <token>');
$me = $client->getMe();
$client->sendMessage([
    'chat_id' => 12345,
    'text' => 'Привет, MAX!',
]);
```

Доступные методы клиента соответствуют разделам в https://dev.max.ru/docs-api: `getMe`, `getChats`, `getChat`, `updateChat`, `deleteChat`, `sendChatAction`, управление закреплёнными сообщениями, членством, подписками, загрузками, сообщениями, видео и callback-ответами. Каждый метод принимает ассоциативные массивы с параметрами, описанными в документации.

### Тесты
Для проверки логики используйте
```bash
composer test
```
В окружении разработчика PHPUnit использует встроенный тестовый HTTP-хэндлер, поэтому реальные запросы в MAX не выполняются.