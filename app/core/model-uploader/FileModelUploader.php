<?php

/**
 * @author      OA Wu <comdan66@gmail.com>
 * @copyright   Copyright (c) 2015 OA Wu Design
 */

class FileModelUploader extends ModelUploader {
  public function __construct ($model = null, $columnName = null) {
    parent::__construct ($model, $columnName);
  }
  // return string
  public function url ($url = '') {
    return parent::url ('');
  }
  // return array
  public function path ($fileName = '') {
    return parent::path ($this->getValue ());
  }
}
