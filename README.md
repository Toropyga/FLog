# FLog
Класс логирования

![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)
![Version](https://img.shields.io/badge/version-v2.2.1-blue.svg)
![PHP](https://img.shields.io/badge/php-v7.1_--_v8-blueviolet.svg)

## Содержание

- [Общее описание](#Общее-описание)
- [Установка](#Установка)
- [Настройка](#Настройка)
- [Описание работы](#описание-работы)
    - [Подключение файла класса](#Подключение-файла-класса)
    - [Инициализация класса](#Инициализация-класса)
    - [Настройка параметров](#Настройка-параметров)
    - [Сохранение строки логов](#Сохранение-строки-логов)
    - [Сохранение массива логов](#Сохранение-массива-логов)
    
## Общее описание

Класс FLog предназначен для сохранения переданных данных в файл.
Для работы необходимо наличие PHP версии 5 и выше.

Есть возможность настройки размера конечного файла и времени хранения файлов.
Файлы логов не будут превышать указанного размера и не будут хранится дольше указанного времени хранения.

## Установка

Рекомендуемый способ установки библиотеки FLog с использованием [Composer](http://getcomposer.org/):

```bash
composer require toropyga/flog
```

## Настройка
Предварительная настройка параметров по умолчанию может осуществляться или непосредственно в самом классе, или с помощью именованных констант.
Именованные константы при необходимости объявляются до вызова класса, например, в конфигурационном файле, и определяют параметры по умолчанию.
* LOG_ROOT_PATH - путь к корневой директории сайта, по умолчанию - текущая директория;
* LOG_PATH - имя директории в которой создаётся директория логов;
* LOG_DIR - имя директории логов;
* LOG_NAME - имя файла логов;
* LOG_SIZE - максимальный размер файла логов в мегабайтах (Мб);
* LOG_TIME - количество дней на протяжении которых сохраняются логи;
* LOG_LEVEL - уровень лога по умолчанию (debug, info, notice, warning, error, critical, alert, emergency);
* LOG_SAVE_NOW - сохранять строку лога сразу в файл или сохранить пакетом по окончании работы;

## Описание работы

### Подключение файла класса
```php
require_once("Base.php");
require_once("FLog.php");
```
или с использованием composer
```php
require_once("vendor/autoload.php");
```
---
### Инициализация класса
```php
$LOG = new FYN\FLog();
```
---
### Настройка параметров
Настройка объёма служебной информации в логе.
Может принимать значения:
* **simple** - date, level, uri
* **advanced** - ip, date, level, uri
* **full** - ip, date, level, uri, user agent
```php
$LOG->setSystemInfo('advanced');
```
---
Установка уровня логов.
Может принимать значения: emergency, alert, critical, error, warning, notice, info, debug 
```php
$LOG->setLogLevel('error');
```
---
Установка имени файла для записи логов
```php
$LOG->setFileName ($file)
```
---

### Сохранение строки логов
Предварительные данные лога
В тексте лога возможна подстановка. Подстановочная переменная выделяется фигурными скобками.
Подстановка осуществляется значениями из массива context по ключу, соответствующему имени подстановочной переменной без фигурных скобок. ([см. документацию п.1.2](https://www.php-fig.org/psr/psr-3/))
```php
$message = "log text for {user}";
$context = array("user" => "you", "other" => "Other information"); // необязательный параметр
```

Лог уровня **debug**
```php
$LOG->debug($message, $context);
```
Лог уровня **info**
```php
$LOG->info($message, $context);
```
Лог уровня **notice**
```php
$LOG->notice($message, $context);
```
Лог уровня **warning**
```php
$LOG->warning($message, $context);
```
Лог уровня **error**
```php
$LOG->error($message, $context);
```
Лог уровня **critical**
```php
$LOG->critical($message, $context);
```
Лог уровня **alert**
```php
$LOG->alert($message, $context);
```
Лог уровня **emergency**
```php
$LOG->emergency($message, $context);
```
---
Также возможен общий вариант с указанием уровня логов
```php
$level = "debug";
$message = "log text for {user}";
$context = array("user" => "you"); // необязательный параметр
$LOG->log($level, $message, $context); // сохраняем лог
```
---
Можно использовать устаревший вариант
```php
$message = "log text";
$file = "file_log_name";
$LOG->set2Log($message, $file); // сохраняем лог
```
---
### Сохранение массива логов
```php
$LOG->setLevel('debug'); // устанавливаем, если необходимо, уровень логов

$logs = array();
$logs['log'][] = "log text line 1";
$logs['log'][] = "log text line 2";
$logs['log'][] = "log text line 3";
$logs['file'] = "file_log_name";
$LOG->setArray2Log($logs); // сохраняем лог
```

