<?php

/**
 * @author      OA Wu <comdan66@gmail.com>
 * @copyright   Copyright (c) 2016 OA Wu Design
 */

$classes = array ('Singleton', 'Config', 'Utils', 'DateTimeInterface', 'DateTime', 'Model', 'Table', 'Connection', 'SQLBuilder', 'Reflections', 'Inflector', 'Exceptions', 'Cache');
foreach ($classes as $class)
  require_once CORE . 'php-activerecord' . DIRECTORY_SEPARATOR . $class . EXT;

require_once CORE . 'model-uploader' . DIRECTORY_SEPARATOR . 'ModelUploader' . EXT;

class Model extends ActiveRecord\Model {
  private $attributes = array ();
  
  public function __construct ($attributes = array (), $guard_attributes = true, $instantiating_via_find = false, $new_record = true) {
    parent::__construct ($attributes, $guard_attributes, $instantiating_via_find, $new_record);
  }
}

if (!$config = Config::get ('mysql')) exit ('Database Config Error！');
ActiveRecord\Config::setConnectionUrl ('mysql://' . $config['user'] . ':' . $config['pass'] . '@' . $config['host'] . ':' . $config['port'] . '/' . $config['base']);
ActiveRecord\Config::setLogDir (LOG);
ActiveRecord\Cache::initFilecache (CACHE . 'model' . DIRECTORY_SEPARATOR);
