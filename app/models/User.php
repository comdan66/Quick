<?php

/**
 * @author      OA Wu <comdan66@gmail.com>
 * @copyright   Copyright (c) 2016 OA Wu Design
 */

class User extends Model {
  // static $cache = true;

  static $has_one = array (
  );

  static $has_many = array (
  );

  static $belongs_to = array (
  );

  public function __construct () {
    forward_static_call_array (array ('parent', '__construct'), func_get_args ());

    ModelUploader::bind ('pic');
  }
}