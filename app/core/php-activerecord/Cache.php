<?php
namespace ActiveRecord;
use Closure;

class Cache {
  static $adapter = null;
  static $options = array ('expire' => 30, 'namespace' => '');
  
  public static function initFilecache ($path, $options = array ()) {
    if (!(file_exists ($path) && is_writable ($path)))
      if (!(mkdir777 ($path) && file_exists ($path) && is_writable ($path)))
        return static::$adapter = null;

    if (!(($file = 'Filecache') && file_exists ($filePath = __DIR__ . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $file . EXT)))
      return static::$adapter = null;

    $class = "ActiveRecord\\" . $file;
    require_once $filePath;
    static::$adapter = new $class ($path);

    static::$options = array_merge (static::$options, $options);
  }

  public static function flush () {
    if (static::$adapter && method_exists (static::$adapter, 'flush') && is_callable (array (static::$adapter, 'flush')))
      static::$adapter->flush ();
  }
  
  public static function get ($key, $closure, $expire = null) {
    if (!(static::$adapter && method_exists (static::$adapter, 'read') && is_callable (array (static::$adapter, 'read'))))
      return $closure ();

    if (!$expire) $expire = static::$options['expire'];

    $key = static::get_namespace () . $key;

    if (!($value = static::$adapter->read ($key)))
      static::$adapter->write ($key, ($value = $closure ()), $expire);

    return $value;
  }

  public static function set ($key, $var, $expire = null) {
    if (!(static::$adapter && method_exists (static::$adapter, 'write') && is_callable (array (static::$adapter, 'write'))))
      return;

    if (!$expire) $expire = static::$options['expire'];
    $key = static::get_namespace () . $key;
    return static::$adapter->write ($key, $var, $expire);
  }

  public static function delete ($key) {
    if (!(static::$adapter && method_exists (static::$adapter, 'delete') && is_callable (array (static::$adapter, 'delete'))))
      return;

    $key = static::get_namespace () . $key;
    return static::$adapter->delete ($key);
  }

  private static function get_namespace () {
    return (isset (static::$options['namespace']) && strlen (static::$options['namespace']) > 0) ? (static::$options['namespace'] . "::") : "";
  }
}
