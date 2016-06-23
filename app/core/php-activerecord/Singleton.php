<?php
namespace ActiveRecord;

abstract class Singleton {
  private static $instances = array ();
  
  final public static function instance () {
    $class_name = get_called_class ();

    if (isset (self::$instances[$class_name]))
      return self::$instances[$class_name];

    return self::$instances[$class_name] = new $class_name;
  }
  
  final protected function get_called_class () {
    $backtrace = debug_backtrace ();
    return get_class ($backtrace[2]['object']);
  }

  final private function __clone () {}
}