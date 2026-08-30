# FLog
Logging class

![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)
![Version](https://img.shields.io/badge/version-v4.1.1-blue.svg)
![PHP](https://img.shields.io/badge/php-v7.4_--_v8-blueviolet.svg)

## Contents

- [Overview](#overview)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
    - [Including the class file](#including-the-class-file)
    - [Initializing the class](#initializing-the-class)
    - [Setting parameters](#setting-parameters)
    - [Saving a log line](#saving-a-log-line)
    - [Saving an array of logs](#saving-an-array-of-logs)

## Overview

The FLog class is designed to save the passed data to a file.
It requires PHP version 7.1 or higher.

You can configure the maximum size of the resulting file and the retention period for log files.
Log files will not exceed the specified size and will not be stored longer than the specified retention period.

## Installation

The recommended way to install the FLog library is via [Composer](http://getcomposer.org/):

```bash
composer require toropyga/flog
```

## Configuration
Default parameters can be pre-configured either directly in the class itself, via named constants, or by passing parameters when the class is initialized.
Named constants, if needed, are declared before the class is called (for example, in a configuration file) and define the default parameters.
* LOG_ROOT_PATH - path to the site's root directory; defaults to the current directory;
* LOG_PATH - name of the directory in which the logs directory is created;
* LOG_DIR - name of the logs directory;
* LOG_NAME - name of the log file;
* LOG_SIZE - maximum log file size in megabytes (MB);
* LOG_TIME - number of days for which logs are kept;
* LOG_LEVEL - default log level (debug, info, notice, warning, error, critical, alert, emergency);
* LOG_SAVE_NOW - whether to save the log line to the file immediately or save it as a batch at the end of execution
* LOG_CLEAR - whether to clean up the directory from old files

When initializing the class, you can pass an array with the following keys for configuration:
* log_root_dir - path to the root directory
* log_path - path to the logs directory relative to the root directory
* log_dir - name of the logs directory
* log_name - name of the log file
* log_max_size - maximum log file size in megabytes (MB)
* log_time - number of days for which logs are kept
* log_level - log level
* log_save_now - whether to save the log immediately or at the end of execution
* log_system_info - amount of service information included in the log ('full', 'advanced', 'simple')
* log_clear - whether to clean up the directory from old files

## Usage

### Including the class file
```php
require_once("Base.php");
require_once("FLog.php");
```
or using Composer
```php
require_once("vendor/autoload.php");
```
---
### Initializing the class
```php
$LOG = new Toropyga\FLog();
```
or
```php
$config = array();
$config['log_name'] = 'my.log';
$config['log_clear'] = true;
$config['log_level'] = 'debug';
$config['log_system_info'] = 'full';

$LOG = new Toropyga\FLog($config);
```
---
### Setting parameters
Configure the amount of service information included in the log.
Accepted values:
* **simple** - date, level, uri
* **advanced** - ip, date, level, uri
* **full** - ip, date, level, uri, user agent
```php
$LOG->setSystemInfo('advanced');
```
---
Set the log level.
Accepted values: emergency, alert, critical, error, warning, notice, info, debug
```php
$LOG->setLogLevel('error');
```
---
Set the file name used for writing logs
```php
$LOG->setName ($file);
```
---
Set the log storage method.

Accepts a number from 0 to 6, or a string (file - to a file, stdout - to the system output, db - to a database)

Numbers:
*  0 - save to a file
*  1 - save to STDOUT
*  2 - save to a database
*  3 - save to a file and STDOUT
*  4 - save to a file and a database
*  5 - save to STDOUT and a database
*  6 - save to a file, STDOUT, and a database

If a string is passed instead of a number, it can list several types separated by commas in any order ('file, db')

By default, logs are saved to a file only.
```php
$LOG->setSaveType(4);
```
or
```php
$LOG->setSaveType('file,db');
```
---
Connecting a database for writing logs to a database
```php
use Toropyga\DB;
$DB = new DB\MySQL();
$LOG->setDB($DB);
```
---

### Saving a log line
Preliminary log data.

*The log text supports placeholder substitution. A placeholder variable is enclosed in curly braces.
Substitution is performed using values from the context array, keyed by the name of the placeholder variable without the curly braces. ([see the documentation, section 1.2](https://www.php-fig.org/psr/psr-3/))*
```php
$message = "log text for {user}";
$context = array("user" => "you", "other" => "Other information"); // optional parameter
```

**debug** level log
```php
$LOG->debug($message, $context);
```
**info** level log
```php
$LOG->info($message, $context);
```
**notice** level log
```php
$LOG->notice($message, $context);
```
**warning** level log
```php
$LOG->warning($message, $context);
```
**error** level log
```php
$LOG->error($message, $context);
```
**critical** level log
```php
$LOG->critical($message, $context);
```
**alert** level log
```php
$LOG->alert($message, $context);
```
**emergency** level log
```php
$LOG->emergency($message, $context);
```
---
A generic variant is also available, with the log level specified explicitly
```php
$level = "debug";
$message = "log text for {user}";
$context = array("user" => "you"); // optional parameter
$LOG->log($level, $message, $context); // save the log
```
---
You can use the legacy variant
```php
$message = "log text";
$file = "file_log_name";
$LOG->set2Log($message, $file); // save the log
```
---
### Saving an array of logs
```php
$LOG->setLevel('debug'); // set the log level, if needed

$logs = array();
$logs['log'][] = "log text line 1";
$logs['log'][] = "log text line 2";
$logs['log'][] = "log text line 3";
$logs['file'] = "file_log_name";
$LOG->setArray2Log($logs); // save the log
```
