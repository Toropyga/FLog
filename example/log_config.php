<?php
//      +------------------------------------------+
//      |          Настройка логирования           |
//      +------------------------------------------+

/**
 * Путь к корневой директории
 * @var string
 */
$config['log_root_path'] = '';

/**
 * Путь к папке в которой расположена директория логов относительно корневой директории
 * @var string
 */
$config['log_path'] = '';

/**
 * Директория логов
 * @var string
 */
$config['log_dir'] = 'logs';

/**
 * Имя файла логов
 * @var string
 */
$config['log_name'] = 'site.log';

/**
 * Максимальный размер файла логов в мегабайтах (Мб)
 * @var int
 */
$config['log_size'] = 2;

/**
 * Количество дней на протяжении которых сохраняются логи
 * @var int
 */
$config['log_time'] = 365;

/**
 * Уровень логов
 * @var string
 */
$config['log_level'] = 'error';

/**
 * Сохранять строку лога сразу в файл или сохранить пакетом по окончании работы
 * @var boolean
 */
$config['log_save_now'] = true;


//---------------------------------------//
//  Сохранение в статические переменные  //
//---------------------------------------//

if (!defined('LOG_ROOT_PATH')) define('LOG_ROOT_PATH', $config['log_root_path']);
if (!defined('LOG_PATH')) define('LOG_PATH', $config['log_path']);
if (!defined('LOG_DIR')) define('LOG_DIR', $config['log_dir']);
if (!defined('LOG_NAME')) define('LOG_NAME', $config['log_name']);
if (!defined('LOG_SIZE')) define('LOG_SIZE', $config['log_size']);
if (!defined('LOG_TIME')) define('LOG_TIME', $config['log_time']);
if (!defined('LOG_LEVEL')) define('LOG_LEVEL', $config['log_level']);
if (!defined('LOG_SAVE_NOW')) define('LOG_SAVE_NOW', $config['log_save_now']);