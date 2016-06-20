<?php

/**
 * @author      OA Wu <comdan66@gmail.com>
 * @copyright   Copyright (c) 2016 OA Wu Design
 */

include_once '../core/controller.php';

$html = Controller::load ()->view (function ($view) {
  return array ();
});

if (!defined ('ENV')) echo $html;
else return $html;
