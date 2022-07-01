<?php
/**
 * Класс логирования
 * @author Yuri Frantsevich (FYN)
 * @version 3.0.4
 * @copyright 2018-2022
 */

namespace FYN;

use FYN\Base;
use FYN\DB;
use Exception;

class FLog implements LoggerInterface {

    /**
     * Количество дней на протяжении которых сохраняются логи
     * @var int
     */
    private $days = 365;

    /**
     * Максимальный размер файла логов в мегабайтах (Мб)
     * @var int
     */
    private $max_size = 2;

    /**
     * Имя файла логов
     * @var string
     */
    private $fileName = '';

    /**
     * Директория логов
     * @var string
     */
    private $log_dir = 'logs';

    /**
     * Директория в которой создаётся директория логов
     * @var string
     */
    private $path = '';

    /**
     * Путь к корневой директории, по умолчанию - корневая директория сайта
     * @var string
     */
    private $root_dir = '';

    /**
     * Путь к текущей папке относительно корневой директории
     * !!! Warning !!! Check your folder path!
     * @var string
     */
    private $folder_path = 'vendor/toropyga/flog/src';

    /**
     * Уровень лога
     * @var string
     */
    private $loglevel = 'error';

    /**
     * Тип сохранения
     *  0 - сохранять в файл
     *  1 - сохранять в STDOUT
     *  2 - сохранять в БД
     *  3 - сохранять в файл и STDOUT
     *  4 - сохранять в файл и БД
     *  5 - сохранять в STDOUT и БД
     *  6 - сохранять в файл, STDOUT и БД
     * @var integer
     */
    private $saveType = 0;

    /**
     * Сохранять в лог сразу или по окончании работы
     * @var bool
     */
    private $saveNow = false;

    /**
     * Текст лога
     * @var array
     */
    private $LOG = array();

    /**
     * Перевод строки
     * @var string
     */
    private $rn = PHP_EOL;

    /**
     * Разделитель блоков лога
     * @var string
     */
    private $separator = " - ";

    /**
     * Объём служебной информации в логе:
     *   simple     - date, level, uri
     *   advanced   - ip, date, level, uri
     *   full       - ip, date, level, uri, user agent
     * @var string
     */
    private $system_info = 'full'; // simple, advanced, full

    /**
     * Объект для работы с базой данных
     * @var object
     */
    private $DB;

    /**
     * Имя таблицы в базе данных для хранения логов
     * @var string
     */
    private $table_name = '';

    /**
     * Параметр вызова функции инициализации базы данных
     * @var bool
     */
    private $db_init = false;

    /**
     * Конструктор класса логирования
     * Log constructor.
     */
    public function __construct () {
        if (!defined('SEPARATOR')) {
            $separator = getenv("COMSPEC")? '\\' : '/';
            define("SEPARATOR", $separator);
        }
        if (defined('LOG_ROOT_PATH')) $this->root_dir = LOG_ROOT_PATH;
        elseif (defined('ROOT_PATH')) $this->root_dir = ROOT_PATH;
        if (!$this->root_dir || !file_exists($this->root_dir)) {
            if (isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT']) $this->root_dir = preg_replace("/\/$/", '', $_SERVER['DOCUMENT_ROOT']);
            else {
                $this_file_path = dirname(__FILE__);
                // !!! Warning !!! Check your folder path!
                $this_folder_path = $this->folder_path; // путь к текущей папке относительно корневой директории
                $this_file_path = str_replace("\\", '/', $this_file_path);
                if ($this_folder_path) {
                    $this_folder_path = str_replace("\\", "/", $this_folder_path);
                    $this_folder_path = preg_replace("/^\//", '', $this_folder_path);
                    $this->root_dir = str_replace("$this_folder_path", '', $this_file_path);
                }
                else $this->root_dir = $this_folder_path;
            }
            $repl_separator = getenv("COMSPEC")? '/' : '\\';
            $this->root_dir = str_replace("$repl_separator", SEPARATOR, $this->root_dir);
        }
        if (defined('LOG_PATH')) $this->path = LOG_PATH;
        if (defined('LOG_DIR')) $this->log_dir = LOG_DIR;
        if (defined('LOG_NAME')) $this->fileName = LOG_NAME;
        if (defined('LOG_SIZE')) $this->max_size = LOG_SIZE;
        if (defined('LOG_TIME')) $this->days = LOG_TIME;
        if (defined('LOG_LEVEL')) $this->setLogLevel(LOG_LEVEL);
        if (defined('LOG_SAVE_NOW')) $this->saveNow = (bool)LOG_SAVE_NOW;
        if (defined('LOG_SYSTEM_INFO')) $this->setSystemInfo(LOG_SYSTEM_INFO);
        if (!$this->max_size < 1) $this->max_size = 1;
        if (!$this->days < 1) $this->days = 1;
        if (!$this->checkDir()) exit;
    }

    /**
     * Деструктор класса логирования
     */
    public function __destruct () {
        if (!$this->saveNow) $this->saveLog();
    }

    /**
     * Сохранение лога уровня emergency - чрезвычайная
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function emergency (string $message, array $context = array()) {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Сохранение лога уровня alert - предупреждение
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function alert (string $message, array $context = array()) {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Сохранение лога уровня critical- критическая
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function critical (string $message, array $context = array()) {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Сохранение лога уровня error - ошибка
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function error (string $message, array $context = array()) {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Сохранение лога уровня warning - предупреждение
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function warning (string $message, array $context = array()) {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Сохранение лога уровня notice - уведомление
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function notice (string $message, array $context = array()) {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Сохранение лога уровня info - информация
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function info (string $message, array $context = array()) {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Сохранение лога уровня debug - отладка
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function debug (string $message, array $context = array()) {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Сохраняем данные в лог
     * @param string $level - уровень лога
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function log (string $level, string $message, array $context = array()) {
        $this->setLogLevel($level);
        list($message, $context) = $this->interpolate($message, $context);
        if (sizeof($context) > 0) {
            foreach ($context as $key => $line) {
                if (preg_match("/^\d+$/", $key)) $message .= $this->separator.print_r($line, true);
                else $message .= " - $key: ".print_r($line, true);
            }
        }
        $this->set2Log($message);
    }

    /**
     * Предварительная обработка сообщения
     * Замена вставок вида {key} в тексте сообщения на данные из массива context array("key" => "value")
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     * @return array
     */
    private function interpolate (string $message, array $context = array()): array
    {
        // build a replacement array with braces around the context keys
        $replace = array();
        foreach ($context as $key => $val) {
            // check that the value can be cast to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $repl = '{' . $key . '}';
                $replace[$repl] = $val;
                if (preg_match("/$repl/", $message)) unset($context[$key]);
            }
        }
        // interpolate replacement values into the message and return
        return array(strtr($message, $replace), $context);
    }

    /**
     * Сохраняем все логи в файлы
     */
    private function saveLog () {
        if (count($this->LOG)) {
            $this->saveNow = true;
            foreach ($this->LOG as $file => $logs) {
                if (is_array($logs)) foreach ($logs as $log) $this->set2Log($log, $file, true);
                else $this->set2Log($logs, $file, true);
            }
            $this->LOG = array();
        }
    }

    /**
     * Сохраняем в файл
     * @param string $file - имя файла в который сохраняем
     * @param string $log - строка, которую сохраняем
     */
    private function save2File (string $file, string $log) {
        $this->checkFiles($file);
        $path_index = $this->root_dir;
        if ($this->path) $path_index = $path_index.SEPARATOR.$this->path;
        if ($this->log_dir) $path_index = $path_index.SEPARATOR.$this->log_dir;
        $file = $file.".log";
        $path = $path_index.SEPARATOR.$file; // Путь к файлу логов
        try {
            // Создаём, или открываем для записи файл логов
            if (!($f = fopen($path, "a+"))) {
                throw new Exception("Couldn't open log file: ".$path);
            }
            // Записываем лог в файл
            fwrite($f, $log);
            fclose($f);
        }
        catch (Exception $e) {
            echo 'Error: ',  $e->getMessage(), "\n";
        }

    }

    /**
     * Сохраняем в STDOUT
     * @param string $log - строка, которую сохраняем
     */
    private function save2STDOUT (string $log) {
        try {
            // Записываем лог в STDOUT
            $log_array = preg_split("/".$this->separator."/", $log);
            preg_match("/\[(\d+)\/(\w+)\/(\d+)\s((\d+):(\d+):(\d+))\s\+\d+\]\s(\w+)/", $log_array[1], $match);
            $level = mb_strtolower($match[8]);
            if ($level == 'error' || $level == 'critical') $STD = fopen("php://stderr", "w");
            else $STD = fopen("php://stderr", "w");
            if (!(fwrite($STD, $log))) {
                throw new Exception("Couldn't write to STDOUT!");
            }
        }
        catch (Exception $e) {
            echo 'Error: ',  $e->getMessage(), "\n";
        }

    }

    /**
     * Сохраняем в БД
     * @param string $log - строка, которую сохраняем
     */
    private function save2DB (string $log, string $file = '') {
        $log_array = preg_split("/".$this->separator."/", $log);
        preg_match("/\[(\d+)\/(\w+)\/(\d+)\s((\d+):(\d+):(\d+))\s\+\d+\]\s(\w+)/", $log_array[1], $match);
        $level = mb_strtolower($match[8]);
        $dt = $match[2]."-".$match[1]."-".$match[3]." ".$match[4];
        $tm = strtotime($dt);
        $dt = date("Y-m-d H:i:s", $tm);
        $ip = $log_array[0];
        $path = substr($log_array[2], 0, 250);
        $browser = $log_array[3];
        unset($log_array[0],$log_array[1],$log_array[2],$log_array[3]);
        $text = trim(implode($this->separator, $log_array));
        $data = array(
            'log_name' => $file,
            'log_date' => $dt,
            'log_ip' => $ip,
            'log_level' => $level,
            'log_path' => $path,
            'log_browser' => $browser,
            'log_text' => $text
        );
        $sql = $this->DB->getInsertSQL('logs_fyn', $data);
        $this->DB->query($sql);
    }

    /**
     * Генерация информации о дате и времени записи лога для начала строки
     * @return string
     */
    private function getInit (): string
    {
        $zone = date('Z');
        $zone = $zone/36;
        $k = ($zone>0)?'+':'-';
        $zone = $k.sprintf("%04d", $zone);
        $uri = (isset($_SERVER['REQUEST_URI']))?$_SERVER['REQUEST_URI']:'REQUEST URI NOT DEFINED';
        $return = date("[d/M/Y H:i:s $zone]")." ".mb_strtoupper($this->loglevel).$this->separator.$uri;
        if (in_array($this->system_info, array('full', 'advanced'))) {
            $ip = Base::getIP();
            $return = $ip['ip'].$this->separator.$return;
            if ($this->system_info == 'full') {
                $agent = (isset($_SERVER['HTTP_USER_AGENT']))?$_SERVER['HTTP_USER_AGENT']:'USER AGENT NOT DEFINED';
                $return .= $this->separator."\"".$agent.'"';
            }
        }
        return $return;
    }

    /**
     * Установка способа сохранения логов
     * Может принимать на вход числа от 0 до 6 или строку (file - в файл, stdout - система, db - база данных)
     * Числа:
     *  0 - сохранять в файл
     *  1 - сохранять в STDOUT
     *  2 - сохранять в БД
     *  3 - сохранять в файл и STDOUT
     *  4 - сохранять в файл и БД
     *  5 - сохранять в STDOUT и БД
     *  6 - сохранять в файл, STDOUT и БД
     * Если на вход подаётся не число, а строка, то в ней может быть казано несколько типов, разделённых запятой в любом порядке ('file, db')
     *
     * @param mixed $type - запрашиваемый тип сохранения логов
     */
    public function setSaveType ($type = 0) {
        $types = array(
            'file'              => 0,
            'stdout'            => 1,
            'db'                => 2,
            'file,stdout'       => 3,
            'stdout,file'       => 3,
            'file,db'           => 4,
            'db,file'           => 4,
            'stdout,db'         => 5,
            'db,stdout'         => 5,
            'file,stdout,db'    => 6,
            'stdout,db,file'    => 6,
            'stdout,file,db'    => 6,
            'file,db,stdout'    => 6,
            'db,stdout,file'    => 6,
            'db,file,stdout'    => 6,
        );
        $type = preg_replace("/\s/", "", $type);
        if (in_array($type, array_keys($types))) $type = $types[$type];
        if (preg_match("/^\d+$/", $type) && in_array((int)$type, array(0,1,2,3,4,5,6))) {
            $this->saveType = $type;
        }
    }

    /**
     * Параметр объёма служебной информации в логе
     * @param string $system_info - объём служебной информации в логе ('full', 'advanced', 'simple')
     */
    public function setSystemInfo (string $system_info) {
        if (in_array($system_info, array('full', 'advanced', 'simple'))) {
            $this->system_info = $system_info;
        }
    }

    /**
     * Установка уровня логов
     * @param string $level
     */
    public function setLogLevel (string $level) {
        $level = mb_strtolower($level);
        if (in_array($level, LogLevel::$logLevels)) {
            $this->loglevel = $level;
        }
    }

    /**
     * Установка имени файла для записи логов
     * @param string $file
     */
    public function setFileName (string $file) {
        if ($file) {
            if (preg_match("/\.log$/ui", $file)) $file = preg_replace("/\.log$/", "", $file);
            $this->fileName = $file;
        }
    }

    /**
     * Установка имени лога для записи логов
     * !! Дублирование с функцией setFileName для поддержки совместимости
     * @param string $file
     */
    public function setName (string $file) {
        if ($file) {
            if (preg_match("/\.log$/ui", $file)) $file = preg_replace("/\.log$/", "", $file);
            $this->fileName = $file;
        }
    }

    /**
     * Передача параметров подключения к базе данных и инициализация таблицы
     * @param FYN\DB\MySQL $DB
     * @param string $tableName - имя таблицы для сохранения логов, если не указано, то используется имя по умолчанию
     */
    public function setDB (DB\MySQL $DB, string $tableName = '') {
        if ($DB->status) {
            $this->DB = $DB;
            if (!$tableName) $tableName = ($this->table_name)?$this->table_name:LogLevel::$tableName;
            if (!in_array($tableName, $this->DB->getTableList())) {
                $sql = strtr(LogLevel::$LogTable, array("{tableName}" => $tableName));
                if ($this->DB->query($sql)) $this->db_init = true;
            }
            else {
                $this->db_init = true;
                $this->checkDBData();
            }
        }
    }

    /**
     * Задание имени таблицы для хранения логов
     * @param $name - имя таблицы
     * @return bool
     */
    public function setTableName ($name) {
        if (preg_match("/[A-z]+([A-z_]+)?/", $name)) {
            $this->table_name = $name;
            return true;
        }
        return false;
    }

    /**
     * Запись массива в лог по имени сохраняемого файла
     * @param array $array - массив логов
     * @param string $level - уровень логов
     * @return boolean
     */
    public function setArray2Log (array $array, $level = 'debug'): bool
    {
        if (!is_array($array)) {
            $this->set2Log($array);
            return true;
        }
        if (isset($array['file'])) {
            $file = $array['file'];
            unset($array['file']);
        }
        else $file = $this->fileName;
        $this->setLogLevel($level);
        if (isset($array['log'])) $logs = $array['log'];
        elseif (isset($array['logs'])) $logs = $array['logs'];
        else $logs = $array;
        if (!is_string($logs)) {
            foreach ($logs as $text) $this->set2Log($text, $file);
        }
        else $this->set2Log($logs, $file);
        return true;
    }

    /**
     * Запись логов в массив по имени сохраняемого файла
     * @param mixed $text - текст лога
     * @param string $file - файл в который записываем
     * @param bool $log_ready - лог не нуждается в дополнительной обработке
     */
    public function set2Log ($text, $file = '', $log_ready = false) {
        if (!$file) $file = $this->fileName;
        if (!$file) $file = 'fynlog';
        if (!isset($this->LOG[$file]) || !is_array($this->LOG[$file])) $this->LOG[$file] = array();
        $i = count($this->LOG[$file]);
        if (!is_string($text)) $text = print_r($text, true);
        if ($log_ready) $log = $text;
        else {
            $log = $this->getInit();
            $log .= " - " . $text;
            $log .= $this->rn;
        }
        $this->checkDB();
        if ($this->saveNow) {
            switch ($this->saveType) {
                case 1:
                    $this->save2STDOUT($log);
                    break;
                case 2:
                    $this->save2DB($log, $file);
                    break;
                case 3:
                    $this->save2File($file, $log);
                    $this->save2STDOUT($log);
                    break;
                case 4:
                    $this->save2File($file, $log);
                    $this->save2DB($log, $file);
                    break;
                case 5:
                    $this->save2STDOUT($log);
                    $this->save2DB($log, $file);
                    break;
                case 6:

                    $this->save2File($file, $log);
                    $this->save2STDOUT($log);
                    $this->save2DB($log, $file);
                    break;
                default:
                    $this->save2File($file, $log);
            }
        }
        else $this->LOG[$file][$i] = $log;
    }

    /**
     * Проверка подключения к БД и типа записи
     */
    private function checkDB () {
        if (!$this->db_init && in_array($this->saveType, array(2, 4, 5, 6))) {
            switch ($this->saveType) {
                case 4:
                case 2:
                    $this->saveType = 0;
                    break;
                case 5:
                    $this->saveType = 1;
                    break;
                case 6:
                    $this->saveType = 3;
                    break;
            }
        }
    }

    /**
     * Очистка базы данных от старых записей
     */
    private function checkDBData () {
        if ($this->db_init) {
            $time = time()-60*60*24*$this->days;
            $date = date("Y-m-d H:is", $time);
            $sql = "DELETE FROM `".LogLevel::$tableName."` WHERE log_date < '$date'";
            $this->DB->query($sql);
        }
    }

    /**
     * Проверка на существование директории логирования,
     * создание директории логирования,
     * очистка директории от старых файлов
     * @return bool
     */
    private function checkDir (): bool
    {
        $path_index = $this->root_dir;
        if ($this->path) $path_index = $path_index.SEPARATOR.$this->path;
        if (!is_dir($path_index)) {
            try {
                if (!mkdir($path_index, 0755)) {
                    throw new Exception("Unable to create folder: ".$path_index);
                }
                chmod($path_index, 0755);
            }
            catch (Exception $e) {
                echo 'Error: ',  $e->getMessage(), "\n";
                return false;
            }
        }
        if ($this->log_dir) $path_index = $path_index.SEPARATOR.$this->log_dir;
        if (!is_dir($path_index)) {
            try {
                if (!mkdir($path_index, 0755)) {
                    throw new Exception("Unable to create folder: ".$path_index);
                }
                chmod($path_index, 0755);
            }
            catch (Exception $e) {
                echo 'Error: ',  $e->getMessage(), "\n";
                return false;
            }
        }
        // Если в директории есть старые файлы логов - удаляем их.
        $dir = opendir($path_index);
        $time_now = time();
        while (FALSE !== ($fl = readdir($dir))) {
            if ($fl != '.' && $fl != '..') {
                $fn = $path_index.SEPARATOR.$fl;
                $ftm = filemtime($fn);
                if (($time_now-$ftm) > (60*60*24*$this->days)) unlink($fn);
            }
        }
        closedir($dir);
        return true;
    }

    /**
     * Проверка файлов логов на допустимый размер
     * если размер превышает допустимый, то файл переименовывается
     * @param string $file - имя файла, который проверяем
     */
    private function checkFiles ($file = '') {
        if (!$file) $file = $this->fileName;
        $file = $file.".log";
        $path_index = $this->root_dir;
        if ($this->path) $path_index = $path_index.SEPARATOR.$this->path;
        if ($this->log_dir) $path_index = $path_index.SEPARATOR.$this->log_dir;
        $path = $path_index.SEPARATOR.$file; // Путь к файлу логов.
        // Проверяем размер файла логов, если более указанного в настройках, переименовываем.
        if (file_exists($path)) {
            $now_size = filesize($path);
            if ($now_size > (1048576*$this->max_size)) {
                if (preg_match("/^([^.]+)\.(.+)$/", $file, $match)) {
                    $file = $match[1];
                    $rs = ".".$match[2];
                }
                else $rs = '';
                if (file_exists($path_index.SEPARATOR.$file.date('Ymd', filemtime($path)).$rs)) {
                    $i = 1;
                    while (file_exists($path_index.SEPARATOR.$file.date('Ymd', filemtime($path))."_".$i.$rs)) $i++;
                    rename($path, $path_index.SEPARATOR.$file.date('Ymd', filemtime($path))."_".$i.$rs);
                }
                else rename($path, $path_index.SEPARATOR.$file.date('Ymd', filemtime($path)).$rs);
            }
        }
    }
}

