<?php

/**
 * @author      OA Wu <comdan66@gmail.com>
 * @copyright   Copyright (c) 2016 OA Wu Design
 */

include_once 'app' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'Defines.php';
include_once CORE . 'Functions' . EXT;

array_map ('mkdir777', array (FCPATH . 'temp', FCPATH . 'upload', FCPATH . 'caches', FCPATH . 'sitemap', FCPATH . 'logs'));
