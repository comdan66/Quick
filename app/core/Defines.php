<?php

/**
 * @author      OA Wu <comdan66@gmail.com>
 * @copyright   Copyright (c) 2016 OA Wu Design
 */

if (version_compare (PHP_VERSION, '5.6') < 0)
  exit ('PHP ActiveRecord requires PHP 5.6 or higher');

date_default_timezone_set ('Asia/Taipei');

if ((preg_match ('/./u', 'é') === 1) && function_exists ('iconv') && (ini_get ('mbstring.func_overload') != 1) && define ('UTF8_ENABLED', true) && extension_loaded ('mbstring') && mb_internal_encoding ('UTF-8'))
  define ('MB_ENABLED', true);
else
  define('UTF8_ENABLED', false);

define ('EXT', '.php');
define ('FMT', defined('BUILD') ? '.html' : '.php');

define ('CONTENT_NAME', 'content');

$temp = explode (DIRECTORY_SEPARATOR, dirname (str_replace (pathinfo (__FILE__, PATHINFO_BASENAME), '', __FILE__)));
$app = array_pop ($temp);
define ('FCPATH', implode (DIRECTORY_SEPARATOR, $temp) . DIRECTORY_SEPARATOR);
unset ($temp);


define ('APP_NAME', $app . DIRECTORY_SEPARATOR);
define ('APP', FCPATH . APP_NAME);

  define ('CORE_NAME', 'core' . DIRECTORY_SEPARATOR);
  define ('CORE', APP . CORE_NAME);

  define ('CONTROLLER_NAME', 'controllers' . DIRECTORY_SEPARATOR);
  define ('CONTROLLER', APP . CONTROLLER_NAME);

  define ('MODEL_NAME', 'models' . DIRECTORY_SEPARATOR);
  define ('MODEL', APP . MODEL_NAME);

    define ('MODEL_UPLOADER_NAME', 'model-uploader' . DIRECTORY_SEPARATOR);
    define ('MODEL_UPLOADER', MODEL . MODEL_UPLOADER_NAME);

  define ('LIBRARY_NAME', 'libraries' . DIRECTORY_SEPARATOR);
  define ('LIBRARY', APP . LIBRARY_NAME);

  define ('VIEW_NAME', 'views' . DIRECTORY_SEPARATOR);
  define ('VIEW', APP . VIEW_NAME);


define ('CACHE_NAME', 'caches' . DIRECTORY_SEPARATOR);
define ('CACHE', FCPATH . CACHE_NAME);

define ('LOG_NAME', 'logs' . DIRECTORY_SEPARATOR);
define ('LOG', FCPATH . LOG_NAME);

define ('TEMP_NAME', 'temp' . DIRECTORY_SEPARATOR);
define ('TEMP', FCPATH . TEMP_NAME);

define ('UPLOAD_NAME', 'upload' . DIRECTORY_SEPARATOR);
define ('UPLOAD', FCPATH . UPLOAD_NAME);

define ('ASSET_NAME', 'assets' . DIRECTORY_SEPARATOR);
define ('ASSET', FCPATH . ASSET_NAME);


