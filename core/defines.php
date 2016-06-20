<?php

/**
 * @author      OA Wu <comdan66@gmail.com>
 * @copyright   Copyright (c) 2016 OA Wu Design
 */

date_default_timezone_set ('Asia/Taipei');

define ('EXT', '.php');
define ('EXTENSION', defined('ENV') ? '.html' : '.php');

define ('FCPATH', implode (DIRECTORY_SEPARATOR, explode (DIRECTORY_SEPARATOR, dirname (str_replace (pathinfo (__FILE__, PATHINFO_BASENAME), '', __FILE__)))) . '/');

define ('VIEW_NAME', 'views' . DIRECTORY_SEPARATOR);
define ('VIEW', FCPATH . VIEW_NAME);

define ('ASSET_NAME', 'assets' . DIRECTORY_SEPARATOR);
define ('ASSET', FCPATH . ASSET_NAME);

define ('CONTENT_NAME', 'content');

