<?php

/**
 * @author      OA Wu <comdan66@gmail.com>
 * @copyright   Copyright (c) 2016 OA Wu Design
 */

include_once 'app' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'Defines.php';

include_once CORE . 'Functions' . EXT;
require_once CORE . 'Config' . EXT;
include_once CORE . 'Controller' . EXT;
// include_once CORE . 'Model' . EXT;

if (defined ('ENV')) {
  error_reporting (0);
  ini_set ('display_errors', 0);
} else {
  error_reporting (E_ALL);
  ini_set ('display_errors', 1);
}

if (php_sapi_name () == 'cli')
  $_SERVER['PATH_INFO'] = CONTROLLER_NAME . (implode (DIRECTORY_SEPARATOR, count ($args = array_slice ($_SERVER['argv'], 1)) ? array_merge ($args) : $args));
else  
  $_SERVER['PATH_INFO'] = isset ($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] ? trim ($_SERVER['PATH_INFO'], DIRECTORY_SEPARATOR) : 'index.php';

$_SERVER['PATH_INFO'] .= ('.' . pathinfo ($_SERVER['PATH_INFO'], PATHINFO_EXTENSION) == EXT ? '' : EXT);

if (!file_exists (FCPATH . $_SERVER['PATH_INFO']))
  exit ('No controller file');

$html = include_once (FCPATH . $_SERVER['PATH_INFO']);

if (!defined ('BUILD'))
  if (is_string ($html)) echo $html;
  else if (is_array ($html)) print_r ($html);
  else var_dump ($html);
else return $html;
