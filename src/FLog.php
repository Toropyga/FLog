<?php
/**
 * Logging class
 * @author Yuri Frantsevich
 * @version 4.1.0
 * @copyright 2018-2026
 */
declare(strict_types=1);

namespace Toropyga;

use Toropyga\Base;
use Toropyga\DB;
use Exception;

class FLog implements LoggerInterface {

    /**
     * Whether to clean up the directory from old files
     * @var bool
     */
    private $clear_old_files = true;

    /**
     * Number of days logs are kept for
     * @var int
     */
    private $days = 365;

    /**
     * Maximum log file size in megabytes (MB)
     * @var int
     */
    private $max_size = 2;

    /**
     * Log file name
     * @var string
     */
    private $fileName = '';

    /**
     * Logs directory
     * @var string
     */
    private $log_dir = 'logs';

    /**
     * Directory in which the logs directory is created
     * @var string
     */
    private $path = '';

    /**
     * Path to the root directory; defaults to the site's root directory
     * @var string
     */
    private $root_dir = '';

    /**
     * Path to the current folder relative to the root directory
     * !!! Warning !!! Check your folder path!
     * @var string
     */
    private $folder_path = 'vendor/toropyga/flog/src';

    /**
     * Log level
     * @var string
     */
    private $loglevel = 'error';

    /**
     * Save type
     *  0 - save to a file
     *  1 - save to STDOUT
     *  2 - save to a database
     *  3 - save to a file and STDOUT
     *  4 - save to a file and a database
     *  5 - save to STDOUT and a database
     *  6 - save to a file, STDOUT, and a database
     * @var integer
     */
    private $saveType = 0;

    /**
     * Whether to save the log immediately or at the end of execution
     * @var bool
     */
    private $saveNow = false;

    /**
     * Log text
     * @var array
     */
    private $LOG = array();

    /**
     * Line break
     * @var string
     */
    private $rn = PHP_EOL;

    /**
     * Log block separator
     * @var string
     */
    private $separator = " - ";

    /**
     * Amount of service information included in the log:
     *   simple     - date, level, uri
     *   advanced   - ip, date, level, uri
     *   full       - ip, date, level, uri, user agent
     * @var string
     */
    private $system_info = 'full'; // simple, advanced, full

    /**
     * Object for working with the database
     * @var object
     */
    private $DB;

    /**
     * Name of the database table used to store logs
     * @var string
     */
    private $table_name = '';

    /**
     * Flag indicating whether the database has been initialized
     * @var bool
     */
    private $db_init = false;

    /**
     * Logging class constructor
     * Log constructor.
     * @param array $config - configuration array
     *      configuration array keys:
     *          log_root_dir - path to the root directory
     *          log_path - path to the logs directory relative to the root directory
     *          log_dir - name of the logs directory
     *          log_name - log file name
     *          log_max_size - maximum log file size in megabytes (MB)
     *          log_time - number of days logs are kept for
     *          log_level - log level
     *          log_save_now - whether to save the log immediately or at the end of execution
     *          log_system_info - amount of service information included in the log ('full', 'advanced', 'simple')
     *          log_clear - whether to clean up the directory from old files
     */
    public function __construct (array $config = []) {
        if (!defined('SEPARATOR')) {
            $separator = getenv("COMSPEC")? '\\' : '/';
            define("SEPARATOR", $separator);
        }
        if (isset($config['log_root_dir'])) $this->root_dir = $config['log_root_dir'];
        elseif (defined('LOG_ROOT_PATH')) $this->root_dir = LOG_ROOT_PATH;
        elseif (defined('ROOT_PATH')) $this->root_dir = ROOT_PATH;
        if (!$this->root_dir || !file_exists($this->root_dir)) {
            if (isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT']) $this->root_dir = preg_replace("/\/$/", '', $_SERVER['DOCUMENT_ROOT']);
            else {
                $this_file_path = dirname(__FILE__);
                // !!! Warning !!! Check your folder path!
                $this_folder_path = $this->folder_path; // path to the current folder relative to the root directory
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
        if (isset($config['log_path'])) $this->path = $config['log_path'];
        elseif (defined('LOG_PATH')) $this->path = LOG_PATH;
        if (isset($config['log_dir'])) $this->log_dir = $config['log_dir'];
        elseif (defined('LOG_DIR')) $this->log_dir = LOG_DIR;
        if (isset($config['log_name'])) $this->setFileName($config['log_name']);
        elseif (defined('LOG_NAME')) $this->setFileName(LOG_NAME);
        if (isset($config['log_max_size'])) $this->max_size = (int)$config['log_max_size'];
        elseif (defined('LOG_SIZE')) $this->max_size = LOG_SIZE;
        if (isset($config['log_time'])) $this->days = (int)$config['log_time'];
        elseif (defined('LOG_TIME')) $this->days = LOG_TIME;
        if (isset($config['log_level'])) $this->setLogLevel($config['log_level']);
        elseif (defined('LOG_LEVEL')) $this->setLogLevel(LOG_LEVEL);
        if (isset($config['log_save_now'])) $this->saveNow = (bool)$config['log_save_now'];
        elseif (defined('LOG_SAVE_NOW')) $this->saveNow = (bool)LOG_SAVE_NOW;
        if (isset($config['log_system_info'])) $this->setSystemInfo($config['log_system_info']);
        elseif (defined('LOG_SYSTEM_INFO')) $this->setSystemInfo(LOG_SYSTEM_INFO);
        if (isset($config['log_clear'])) $this->clear_old_files = (bool)$config['log_clear'];
        elseif (defined('LOG_CLEAR')) $this->clear_old_files = (bool)LOG_CLEAR;
        if ($this->max_size < 1) $this->max_size = 1;
        if ($this->days < 1) $this->days = 1;
        if (!$this->checkDir()) exit;
    }

    /**
     * Logging class destructor
     */
    public function __destruct () {
        if (!$this->saveNow) $this->saveLog();
    }

    /**
     * Save a log entry at the emergency level
     * @param string $message - main log text
     * @param array $context - additional data
     */
    public function emergency (string $message, array $context = array()) {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Save a log entry at the alert level
     * @param string $message - main log text
     * @param array $context - additional data
     */
    public function alert (string $message, array $context = array()) {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Save a log entry at the critical level
     * @param string $message - main log text
     * @param array $context - additional data
     */
    public function critical (string $message, array $context = array()) {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Save a log entry at the error level
     * @param string $message - main log text
     * @param array $context - additional data
     */
    public function error (string $message, array $context = array()) {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Save a log entry at the warning level
     * @param string $message - main log text
     * @param array $context - additional data
     */
    public function warning (string $message, array $context = array()) {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Save a log entry at the notice level
     * @param string $message - main log text
     * @param array $context - additional data
     */
    public function notice (string $message, array $context = array()) {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Save a log entry at the info level
     * @param string $message - main log text
     * @param array $context - additional data
     */
    public function info (string $message, array $context = array()) {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Save a log entry at the debug level
     * @param string $message - main log text
     * @param array $context - additional data
     */
    public function debug (string $message, array $context = array()) {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Save data to the log
     * @param string $level - log level
     * @param string $message - main log text
     * @param array $context - additional data
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
     * Message pre-processing
     * Replaces {key}-style placeholders in the message text with values from the context array("key" => "value")
     * @param string $message - main log text
     * @param array $context - additional data
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
                if (preg_match("/" . preg_quote($repl, "/") . "/", $message)) unset($context[$key]);
            }
        }
        // interpolate replacement values into the message and return
        return array(strtr($message, $replace), $context);
    }

    /**
     * Save all logs to files
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
     * Save to a file
     * @param string $file - name of the file to save to
     * @param array $log - the line being saved
     */
    private function save2File (string $file, array $log) {
        $this->checkFiles($file);
        $path_index = $this->root_dir;
        if ($this->path) $path_index = $path_index.SEPARATOR.$this->path;
        if ($this->log_dir) $path_index = $path_index.SEPARATOR.$this->log_dir;
        $path = $path_index.SEPARATOR.$file; // Path to the log file
        try {
            // Create, or open for writing, the log file
            if (!($f = fopen($path, "a+"))) {
                throw new Exception("Couldn't open log file: ".$path);
            }
            // Write the log entry to the file
            $out = $this->getLOGstring($log);
            if (flock($f, LOCK_EX)) {
                rewind($f);
                fwrite($f, $out);
                fflush($f);
                flock($f, LOCK_UN);
            }
            else {
                throw new Exception("Couldn't write to log file: ".$path);
            }
            fclose($f);
        }
        catch (Exception $e) {
            error_log('Error: ' . $e->getMessage() . "\n");
        }

    }

    /**
     * Save to STDOUT
     * @param array $log - array of data or the line being saved
     */
    private function save2STDOUT (array $log) {
        try {
            $level = $log['level'];
            $out = $this->getLOGstring($log);
            if ($level == 'error' || $level == 'critical') $STD = fopen("php://stderr", "w");
            else $STD = fopen("php://stdout", "w");
            if (!(fwrite($STD, $out))) {
                throw new Exception("Couldn't write to STDOUT!");
            }
        }
        catch (Exception $e) {
            error_log('Error: ' . $e->getMessage() . "\n");
        }

    }

    /**
     * Save to the database
     * @param array $log - array of data being saved
     * @param string $tableName - database table name
     */
    private function save2DB (array $log, string $tableName = '') {
        $level = $log['level'];
        $dt = $log['date_db'];
        $ip = (isset($log['ip'])) ? $log['ip'] : '';
        $path = substr($log['uri'], 0, 250);
        $browser = (isset($log['agent'])) ? $log['agent'] : '';
        $text = trim($log['text']);
        $file = $log['file'];
        $data = array(
            'log_name' => $file,
            'log_date' => $dt,
            'log_ip' => $ip,
            'log_level' => $level,
            'log_path' => $path,
            'log_browser' => $browser,
            'log_text' => $text
        );
        if (!$tableName) $tableName = ($this->table_name)?$this->table_name:LogLevel::$tableName;
        $sql = $this->DB->getInsertSQL($tableName, $data);
        $this->DB->query($sql);
    }

    /**
     * Generate the date/time information for the start of the log line
     * @return array
     */
    private function getInit (): array
    {
        $data = array();
        $zone = date('Z');
        $zone = $zone/36;
        $k = ($zone>0)?'+':'-';
        $zone = $k.sprintf("%04d", $zone);
        $data['uri'] = (isset($_SERVER['REQUEST_URI']))?$_SERVER['REQUEST_URI']:'REQUEST URI NOT DEFINED';
        $data['date'] = date("[d/M/Y H:i:s $zone]");
        $data['date_db'] = date("Y-m-d H:i:s");
        $data['level'] = mb_strtoupper($this->loglevel);
        if (in_array($this->system_info, array('full', 'advanced'))) {
            $ip = Base::getIP();
            $data['ip'] = $ip['ip'];
            if ($this->system_info == 'full') $data['agent'] = (isset($_SERVER['HTTP_USER_AGENT']))?$_SERVER['HTTP_USER_AGENT']:'USER AGENT NOT DEFINED';
        }
        return $data;
    }

    /**
     * Set the log storage method
     * Accepts a number from 0 to 6, or a string (file - to a file, stdout - to the system, db - to a database)
     * Numbers:
     *  0 - save to a file
     *  1 - save to STDOUT
     *  2 - save to a database
     *  3 - save to a file and STDOUT
     *  4 - save to a file and a database
     *  5 - save to STDOUT and a database
     *  6 - save to a file, STDOUT, and a database
     * If a string is passed instead of a number, it may list several types separated by commas in any order ('file, db')
     *
     * @param mixed $type - the requested log storage type
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
     * Amount of service information included in the log
     * @param string $system_info - amount of service information included in the log ('full', 'advanced', 'simple')
     */
    public function setSystemInfo (string $system_info = 'simple') {
        if (in_array($system_info, array('full', 'advanced', 'simple'))) {
            $this->system_info = $system_info;
        }
        elseif (!in_array($this->system_info, array('full', 'advanced', 'simple'))) $this->system_info = 'simple';
    }

    /**
     * Set the log level
     * @param string $level
     */
    public function setLogLevel (string $level = 'info') {
        $level = mb_strtolower($level);
        if (in_array($level, LogLevel::$logLevels)) {
            $this->loglevel = $level;
        }
        elseif (!in_array($this->loglevel, LogLevel::$logLevels)) $this->loglevel = 'info';
    }

    /**
     * Set the file name used for writing logs
     * @param string $file
     */
    public function setFileName (string $file) {
        try {
            if (!(preg_match("/^([A-Za-z_\.])+$/ui", $file))) {
                throw new Exception("Wrong file name: ".$file);
            }
            $file = preg_replace("/\.log$/ui", "", $file);
            $this->fileName = $file . ".log";
        }
        catch (Exception $e) {
            error_log('Error: ' . $e->getMessage() . "\n");
            return false;
        }    
        return true;
    }

    /**
     * Set the log name used for writing logs
     * Duplicates setFileName for backward compatibility
     * @param string $file
     */
    public function setName (string $file) {
        return $this->setFileName($file);
    }

    /**
     * Pass in the database connection parameters and initialize the table
     * @param Toropyga\DB\MySQL $DB
     * @param string $tableName - name of the table used to store logs; if not specified, the default name is used
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
     * Set the name of the table used to store logs
     * @param string $name - table name
     * @return bool
     */
    public function setTableName (string $name) {
        if (preg_match("/[A-Za-z]+([A-Za-z_]+)?/", $name)) {
            $this->table_name = $name;
            return true;
        }
        return false;
    }

    /**
     * Write data from an array to the log, by the name of the file being saved
     * @param array $array - array of logs
     * @param string $level - log level
     * @return boolean
     */
    public function setArray2Log (array $array, string $level = 'debug'): bool
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
     * Write logs to the array, keyed by the name of the file being saved
     * @param mixed $text - log text
     * @param string $file - file to write to
     * @param bool $log_ready - whether the data is already prepared for saving
     */
    public function set2Log ($text, $file = '', $log_ready = false) {
        if (!$file) $file = $this->fileName;
        if (!$file) $file = 'fynlog';
        if (!isset($this->LOG[$file]) || !is_array($this->LOG[$file])) $this->LOG[$file] = array();
        $i = count($this->LOG[$file]);
        if (!$log_ready) {
            $log = $this->getInit();
            $log['text'] = " - " . $text;
        }
        else $log = $text;
        $log['file'] = $file;
        $this->checkDB();
        if ($this->saveNow) {
            switch ($this->saveType) {
                case 1:
                    $this->save2STDOUT($log);
                    break;
                case 2:
                    $this->save2DB($log);
                    break;
                case 3:
                    $this->save2File($file, $log);
                    $this->save2STDOUT($log);
                    break;
                case 4:
                    $this->save2File($file, $log);
                    $this->save2DB($log);
                    break;
                case 5:
                    $this->save2STDOUT($log);
                    $this->save2DB($log);
                    break;
                case 6:

                    $this->save2File($file, $log);
                    $this->save2STDOUT($log);
                    $this->save2DB($log);
                    break;
                default:
                    $this->save2File($file, $log);
            }
        }
        else $this->LOG[$file][$i] = $log;
    }

    /**
     * Convert the log array into a string
     * @return string
     */
    private function getLOGstring(array $log) {
        if (!isset($log['ip']) && !isset($log['agent'])) return $log['date']." ".$log['level'].$this->separator.$log['uri'].$log['text'].$this->rn;
        elseif (!isset($log['agent'])) return $log['ip'].$this->separator.$log['date']." ".$log['level'].$this->separator.$log['uri'].$log['text'].$this->rn;
        else return $log['ip'].$this->separator.$log['date']." ".$log['level'].$this->separator.$log['uri'].'"'.$log['agent'].'"'.$log['text'].$this->rn;
    }

    /**
     * Check the database connection and the save type
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
     * Clean up old records from the database
     * @param string $tableName - name of the table used to store logs; if not specified, the default name is used
     */
    private function checkDBData (string $tableName = '') {
        if (!$tableName) $tableName = ($this->table_name)?$this->table_name:LogLevel::$tableName;
        if ($this->db_init) {
            $time = time()-60*60*24*$this->days;
            $date = date("Y-m-d H:i:s", $time);
            $sql = "DELETE FROM `".$tableName."` WHERE log_date < '$date'";
            $this->DB->query($sql);
        }
    }

    /**
     * Check whether the logging directory exists,
     * create the logging directory,
     * clean up the directory from old files
     * @return bool
     */
    private function checkDir (): bool
    {
        $path_index = $this->root_dir;
        if ($this->path) $path_index = $path_index.SEPARATOR.$this->path;
        if (!is_dir($path_index)) {
            try {
                if (!@mkdir($path_index, 0755, true)) {
                    throw new Exception("Unable to create folder: ".$path_index);
                }
                chmod($path_index, 0755);
            }
            catch (Exception $e) {
                error_log('Error: ' . $e->getMessage() . "\n");
                return false;
            }
        }
        if ($this->log_dir) $path_index = $path_index.SEPARATOR.$this->log_dir;
        if (!is_dir($path_index)) {
            try {
                if (!@mkdir($path_index, 0755, true)) {
                    throw new Exception("Unable to create folder: ".$path_index);
                }
                chmod($path_index, 0755);
            }
            catch (Exception $e) {
                error_log('Error: ' . $e->getMessage() . "\n");
                return false;
            }
        }
        if ($this->clear_old_files) $this->clearDIR();
        
        return true;
    }
    
    /**
     * Clean up the directory of old logs
     * If the directory contains old log files, delete them.
     * @return bool
     */
    public function clearDIR () {
        $path_index = $this->root_dir;
        if ($this->path) $path_index = $path_index.SEPARATOR.$this->path;
        if ($this->log_dir) $path_index = $path_index.SEPARATOR.$this->log_dir;
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
     * Check log files against the allowed size
     * if the size exceeds the allowed value, the file is renamed
     * @param string $file - name of the file being checked
     */
    private function checkFiles ($file = '') {
        if (!$file) $file = $this->fileName;
        $path_index = $this->root_dir;
        if ($this->path) $path_index = $path_index.SEPARATOR.$this->path;
        if ($this->log_dir) $path_index = $path_index.SEPARATOR.$this->log_dir;
        $path = $path_index.SEPARATOR.$file; // Path to the log file.
        // Check the log file's size; if it exceeds the configured limit, rename it.
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

