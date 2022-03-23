<?php

namespace FYN;


/**
 * Describes log levels.
 */
class LogLevel {
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';
    static public array $logLevels = array(
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug'
    );
    static public string $tableName = "logs_fyn";
    static public string $LogTable = "
        CREATE TABLE `{tableName}` (
            `log_id` INT NOT NULL AUTO_INCREMENT,
            `log_name` VARCHAR(45) NULL,
            `log_date` DATETIME NULL,
            `log_ip` VARCHAR(15) NULL,
            `log_level` VARCHAR(45) NULL,
            `log_path` VARCHAR(250) NULL,
            `log_browser` TINYTEXT NULL,
            `log_text` LONGTEXT NULL,
            PRIMARY KEY (`log_id`)
        );
    ";
}