<?php

/**
 * @author      OA Wu <comdan66@gmail.com>
 * @copyright   Copyright (c) 2016 OA Wu Design
 */

class UserPicModelUploader extends ImageModelUploader {

  public function getVersions () {
    return array (
        '' => array (),
        '100x100c' => array ('adaptiveResizeQuadrant', 100, 100, 'c'),
      );
  }
}