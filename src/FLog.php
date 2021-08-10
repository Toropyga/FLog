<?php
/**
 * Класс логирования
 * @author Yuri Frantsevich (FYN)
 * Date: 15/01/2018
 * Time: 17:17
 * @version 2.0.0
 * @copyright 2018-2021
 */
namespace FYN;

class FLog {

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
    private $file = 'site.log';

    /**
     * Директория логов
     * @var string
     */
    private $log_dir = 'logs';

    /**
     * Директория в которой создаётся директория логов
     * @var string
     */
    private $root_dir = 'files';

    /**
     * Путь к корневой директории, по умолчанию - текущая директория сайта
     * @var string
     */
    private $path = __DIR__;

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
     * Конструктор класса логирования
     * Log constructor.
     */
    public function __construct() {
        if (defined('LOG_ROOT_PATH')) $this->path = LOG_ROOT_PATH;
        if (defined('LOG_PATH')) $this->root_dir = LOG_PATH;
        if (defined('LOG_DIR')) $this->log_dir = LOG_DIR;
        if (defined('LOG_NAME')) $this->file = LOG_NAME;
        if (defined('LOG_SIZE')) $this->max_size = LOG_SIZE;
        if (defined('LOG_TIME')) $this->days = LOG_TIME;
        if (!$this->file) $this->file = 'site.log';
        if (!$this->max_size < 1) $this->max_size = 1;
        if (!$this->days < 1) $this->days = 1;
        if (!defined('SEPARATOR')) {
            $separator = getenv("COMSPEC")? '\\' : '/';
            define("SEPARATOR", $separator);
        }
        $this->checkDir();
    }

    /**
     * Деструктор класса логирования
     */
    public function __destruct() {
        $this->saveLog();
    }

    /**
     * Сохраняем все логи в файлы
     */
    public function saveLog() {
        if (count($this->LOG)) {
            foreach ($this->LOG as $file => $logs) {
                $this->checkFiles($file);
                $path_index = $this->path;
                if ($this->root_dir) $path_index = $path_index.SEPARATOR.$this->root_dir;
                if ($this->log_dir) $path_index = $path_index.SEPARATOR.$this->log_dir;
                $path = $path_index.SEPARATOR.$file; // Путь к файлу логов
                // Создаём, или открываем для записи файл логов
                if (!($f = fopen($path, "a+"))) {
                    echo "ERROR open log file: ".$path;
                    exit;
                }
                // Записываем лог в файл
                foreach ($logs as $text) fwrite($f, $text);
                fclose($f);
            }
            $this->LOG = array();
        }
    }

    /**
     * Генерация информации о дате и времени записи лога для начала строки
     * @return string
     */
    private function init () {
        $i = date('Z');
        $i = $i/36;
        $k = ($i>0)?'+':'-';
        $i = $k.sprintf("%04d", $i);
        $ip = $this->getIP();
        $agent = (isset($_SERVER['HTTP_USER_AGENT']))?$_SERVER['HTTP_USER_AGENT']:'USER AGENT NOT DEFINED';
        $uri = (isset($_SERVER['REQUEST_URI']))?$_SERVER['REQUEST_URI']:'REQUEST URI NOT DEFINED';
        $text = $ip['ip']." - ".date("[d/M/Y H:i:s $i]")." - ".$uri." - \"".$agent.'"';
        return $text;
    }

    /**
     * Запись логов в массив по имени сохраняемого файла
     * @param $text - текст лога
     * @param string $file - файл в который записываем
     */
    public function set2Log ($text, $file = '') {
        if (!$file) $file = $this->file;
        if (!isset($this->LOG[$file]) || !is_array($this->LOG[$file])) $this->LOG[$file] = array();
        $i = count($this->LOG[$file]);
        $this->LOG[$file][$i] = $this->init();
        $this->LOG[$file][$i] .= " - ".$text;
        $this->LOG[$file][$i] .= $this->rn;
    }

    /**
     * Проверка на существование директории логирования,
     * создание директории логирования,
     * очистка директории от старых файлов
     * @return bool
     */
    private function checkDir () {
        $path_index = $this->path;
        if ($this->root_dir) $path_index = $path_index.SEPARATOR.$this->root_dir;
        if (!is_dir($path_index)) {
            if (mkdir($path_index, 0755)) chmod($path_index, 0755);
            else {
                echo "ERROR log dir:".$path_index;
                exit;
            }
        }
        if ($this->log_dir) $path_index = $path_index.SEPARATOR.$this->log_dir;
        if (!is_dir($path_index)) {
            if (mkdir($path_index, 0755)) chmod($path_index, 0755);
            else {
                echo "ERROR log dir:".$path_index;
                exit;
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
        if (!$file) $file = $this->file;
        $path_index = $this->path;
        if ($this->root_dir) $path_index = $path_index.SEPARATOR.$this->root_dir;
        if ($this->log_dir) $path_index = $path_index.SEPARATOR.$this->log_dir;
        $path = $path_index.SEPARATOR.$file; // Путь к файлу логов
        // Проверяем размер файла логов, если более указанного в настройках - переименовываем
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

    /**
     * Определение IP адреса с которого открывается страница
     * @return mixed
     */
    public function getIP () {
        $ipn = (isset($_SERVER['REMOTE_ADDR']))?$_SERVER['REMOTE_ADDR']:'';
        if (!$ipn) $ipn = urldecode(getenv('HTTP_CLIENT_IP'));
        if (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown")) $strIP = getenv('HTTP_X_FORWARDED_FOR');
        elseif (getenv('HTTP_X_FORWARDED') && strcasecmp(getenv("HTTP_X_FORWARDED"), "unknown")) $strIP = getenv('HTTP_X_FORWARDED');
        elseif (getenv('HTTP_FORWARDED_FOR') && strcasecmp(getenv("HTTP_FORWARDED_FOR"), "unknown")) $strIP = getenv('HTTP_FORWARDED_FOR');
        elseif (getenv('HTTP_FORWARDED') && strcasecmp(getenv("HTTP_FORWARDED"), "unknown")) $strIP = getenv('HTTP_FORWARDED');
        else $strIP = (isset($_SERVER['REMOTE_ADDR']))?$_SERVER['REMOTE_ADDR']:'127.0.0.1';
        if ($ipn == '::1') $ipn = '127.0.0.1';
        if ($strIP == '::1') $strIP = '127.0.0.1';
        if (!preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/", $ipn)) $ipn = '';
        if (!preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/", $strIP)) $strIP = $ipn;
        if ($strIP != $ipn) {
            $ip['proxy'] = $ipn;
            $ip['ip'] = $strIP;
        }
        else {
            $ip['proxy'] = '';
            $ip['ip'] = $ipn;
        }
        return $ip;
    }
}

