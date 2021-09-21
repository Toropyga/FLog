<?php
/**
 * Класс логирования
 * @author Yuri Frantsevich (FYN)
 * @version 2.2.2
 * @copyright 2018-2021
 */

namespace FYN;

use FYN\Base;
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
    private $fileName = 'site.log';

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
        if (!$this->fileName) $this->fileName = 'site.log';
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
    public function emergency ($message, array $context = array()) {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Сохранение лога уровня alert - предупреждение
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function alert ($message, array $context = array()) {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Сохранение лога уровня critical- критическая
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function critical ($message, array $context = array()) {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Сохранение лога уровня error - ошибка
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function error ($message, array $context = array()) {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Сохранение лога уровня warning - предупреждение
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function warning ($message, array $context = array()) {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Сохранение лога уровня notice - уведомление
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function notice ($message, array $context = array()) {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Сохранение лога уровня info - информация
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function info ($message, array $context = array()) {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Сохранение лога уровня debug - отладка
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function debug ($message, array $context = array()) {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Сохраняем данные в лог
     * @param string $level - уровень лога
     * @param string $message - основной текст лога
     * @param array $context - дополнительные данные
     */
    public function log ($level, $message, array $context = array()) {
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
    private function interpolate ($message, array $context = array()) {
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
            foreach ($this->LOG as $file => $logs) {
                if (is_array($logs)) foreach ($logs as $log) $this->save2File($file, $log);
                else $this->save2File($file, $logs);
            }
            $this->LOG = array();
        }
    }

    /**
     * Сохраняем в файл
     * @param string $file - имя файла в который сохраняем
     * @param string $log - строка, которую сохраняем
     */
    private function save2File ($file, $log) {
        $this->checkFiles($file);
        $path_index = $this->root_dir;
        if ($this->path) $path_index = $path_index.SEPARATOR.$this->path;
        if ($this->log_dir) $path_index = $path_index.SEPARATOR.$this->log_dir;
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
     * Генерация информации о дате и времени записи лога для начала строки
     * @return string
     */
    private function getInit () {
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
     * Параметр объёма служебной информации в логе
     * @param string $system_info - объём служебной информации в логе ('full', 'advanced', 'simple')
     */
    public function setSystemInfo ($system_info) {
        if (in_array($system_info, array('full', 'advanced', 'simple'))) {
            $this->system_info = $system_info;
        }
    }

    /**
     * Установка уровня логов
     * @param string $level
     */
    public function setLogLevel ($level) {
        $level = mb_strtolower($level);
        if (in_array($level, LogLevel::$logLevels)) {
            $this->loglevel = $level;
        }
    }

    /**
     * Установка имени файла для записи логов
     * @param string $file
     */
    public function setFileName ($file) {
        if ($file) $this->fileName = $file;
    }

    /**
     * Запись массива в лог по имени сохраняемого файла
     * @param array $array - массив логов
     * @param string $level - уровень логов
     * @return boolean
     */
    public function setArray2Log ($array, $level = 'debug') {
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
     * @param $text - текст лога
     * @param string $file - файл в который записываем
     */
    public function set2Log ($text, $file = '') {
        if (!$file) $file = $this->fileName;
        if (!isset($this->LOG[$file]) || !is_array($this->LOG[$file])) $this->LOG[$file] = array();
        $i = count($this->LOG[$file]);
        if (!is_string($text)) $text = print_r($text, true);
        $log = $this->getInit();
        $log .= " - ".$text;
        $log .= $this->rn;
        if ($this->saveNow) $this->save2File($file, $log);
        else $this->LOG[$file][$i] = $log;
    }

    /**
     * Проверка на существование директории логирования,
     * создание директории логирования,
     * очистка директории от старых файлов
     * @return bool
     */
    private function checkDir () {
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

