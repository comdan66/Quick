<?php
namespace ActiveRecord;

class Config {
  private static $logName = 'query.log';
  private static $logDir = null;
  private static $logPath = null;
  private static $connectionUrl = null;

  public static function setLogName ($logName) {
    self::$logName = $logName && is_string ($logName) ? $logName : self::$logName;
    self::$logPath = self::getLogDir () ? self::getLogDir () . self::getLogName () : null;
  }
  public static function getLogName () {
    return self::$logName;
  }
  public static function setLogDir ($logDir) {
    $logDir = rtrim ($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    self::$logDir = file_exists ($logDir) && is_dir ($logDir) && is_writable ($logDir) ? $logDir : null;
    self::$logPath = self::getLogDir () ? self::getLogDir () . self::getLogName () : null;
  }
  public static function getLogDir () {
    return self::$logDir;
  }
  public static function getLogPath () {
    return self::$logPath;
  }
  public static function setConnectionUrl ($connectionUrl) {
    self::$connectionUrl = $connectionUrl;
  }
  public static function getConnectionUrl () {
    return self::$connectionUrl;
  }
}