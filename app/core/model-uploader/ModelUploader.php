<?php

/**
 * @author      OA Wu <comdan66@gmail.com>
 * @copyright   Copyright (c) 2016 OA Wu Design
 */

class ModelUploader {
  const CLASS_SUFFIX = 'ModelUploader';
  const SHOW_DEBUG = false;
  const STORAGE = 'local'; // local 、 s3
  const PK_COLUMN = 'id';
  const D4_URL = '';

  protected $model = null;
  protected $columnName = null;
  protected $columnValue = null;
  private static $urls = array ();

  public function __construct ($model = null, $columnName = null) {
    if (!($model && $columnName && in_array ($columnName, array_keys ($model->attributes ()))))
      return ModelUploader::error ('ModelUploader 錯誤！', '初始化失敗！', '請檢查建構子參數！');

    $this->model = $model;
    $this->columnName = $columnName;
    $this->columnValue = $model->$columnName;
    $model->$columnName = $this;

    if (!in_array (ModelUploader::PK_COLUMN, array_keys ($model->attributes ())))
      return ModelUploader::error ('ModelUploader 錯誤！', '無法取得 primary key 欄位資訊！', 'Model 需要 select primary key，或者修改 ModelUploader PK_COLUMN 常數值！');
    
    if (!in_array (ModelUploader::STORAGE, array ('local', 's3')))    
      return ModelUploader::error ('ModelUploader 錯誤！', '未知的 driver，系統尚未支援 ' . ModelUploader::STORAGE . ' 的空間！');

    if (!is_writable (TEMP))
      return ModelUploader::error ('ModelUploader 錯誤！', '暫存資料夾不可讀寫或不存在！', '請檢查暫存資料夾是否存在以及可讀寫！', '預設值 暫存資料夾！');

    switch (ModelUploader::STORAGE) {
      default: case 'local':
        break;
      
      case 's3':
        require_once LIBRARY . 'S3' . EXT;
        S3::init (Config::get ('s3', 'access_key'), Config::get ('s3', 'secret_key'));
        break;
    }
  }
  // boolean
  public function put ($fileInfo) {
    if (is_array ($fileInfo)) {
      foreach (array ('name', 'type', 'tmp_name', 'error', 'size') as $key)
        if (!isset ($fileInfo[$key])) return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ModelUploader 錯誤！', '參數格式錯誤！', '請程式設計者確認狀況！') : false;

      $name = $fileInfo['name'];
      $isUseMoveUploadedFile = true;
    } else if (is_string ($fileInfo) && is_file ($fileInfo) && is_writable ($fileInfo)) {
      $name = basename ($fileInfo);
      $fileInfo = array ('name' => 'file', 'type' => '', 'tmp_name' => $fileInfo, 'error' => '', 'size' => '1');
      $isUseMoveUploadedFile = false;
    } else {
      return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ModelUploader 錯誤！', '參數格式錯誤！', '請程式設計者確認狀況！') : false;
    }

    $format = pathinfo ($name = preg_replace ("/[^a-zA-Z0-9\\._-]/", "", $name), PATHINFO_EXTENSION);
    $name = !($name = pathinfo ($name, PATHINFO_FILENAME)) ? ModelUploader::getRandomName () : $name;
    $name .= $format ? '.' . $format : '';

    if (!($temp = ModelUploader::moveOriFile ($fileInfo, $isUseMoveUploadedFile))) return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ModelUploader 錯誤！', '搬移至暫存資料夾時發生錯誤！', '請檢查暫存資料夾是否存在以及可讀寫！', '預設值 暫存資料夾！') : false;
    if (!($savePath = $this->_verifySavePath ())) return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ModelUploader 錯誤！', '確認儲存路徑發生錯誤！', '請程式設計者確認狀況！') : false;
    if (!($result = $this->moveFileAndUploadColumn ($temp, $savePath, $name))) return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ModelUploader 錯誤！', '搬移預設位置時發生錯誤！', '請程式設計者確認狀況！') : false;

    return $result;
  }
  // boolean
  public function put_url ($url) {
    $format = pathinfo ($url, PATHINFO_EXTENSION);
    $temp = TEMP . ModelUploader::getRandomName () . ($format ? '.' . $format : '');
    return ($temp = ModelUploader::download ($url, $temp)) && $this->put ($temp) ? file_exists ($temp) ? @unlink ($temp) : true : false;
  }
  // sring
  public static function moveOriFile ($fileInfo, $isUseMoveUploadedFile) {
    $temp = TEMP . ModelUploader::getRandomName ();

    if ($isUseMoveUploadedFile) @move_uploaded_file ($fileInfo['tmp_name'], $temp);
    else @rename ($fileInfo['tmp_name'], $temp);

    if (!is_readable ($temp)) return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ModelUploader 錯誤！', '移動檔案錯誤！路徑：' . $temp, '請程式設計者確認狀況！') : '';

    ModelUploader::chmod ($temp);
    return $temp;
  }
  // array ()
  private function _verifySavePath () {
    switch (ModelUploader::STORAGE) {
      case 'local':
        if (!is_writable (UPLOAD)) return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ModelUploader 錯誤！', '資料夾不能儲存！路徑：' . UPLOAD) : array ();
        if (!file_exists (UPLOAD . implode (DIRECTORY_SEPARATOR, $path = $this->getSavePath ()))) mkdir777 (UPLOAD . implode (DIRECTORY_SEPARATOR, $path));
        return !is_writable (UPLOAD . implode (DIRECTORY_SEPARATOR, $path)) ? ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ModelUploader 錯誤！', '資料夾不能儲存！路徑：' . UPLOAD . implode (DIRECTORY_SEPARATOR, $path), '請程式設計者確認狀況！') : array () : $path;
        break;

      case 's3':
        return $this->getSavePath ();
        break;
    }
    return array ();
  }
  // boolean
  protected function moveFileAndUploadColumn ($temp, $savePath, $oriName) {
    switch (ModelUploader::STORAGE) {
      case 'local':
        return !($this->uploadColumnAndUpload () && @rename ($temp, $savePath = UPLOAD . implode (DIRECTORY_SEPARATOR, $savePath) . DIRECTORY_SEPARATOR . $oriName)) ? ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ModelUploader 錯誤！', '搬移預設位置時發生錯誤！', 'temp：' . $temp, ', Save Path：' . $savePath, ', Name：' . $ori_name, '請程式設計者確認狀況！') : false : $this->uploadColumnAndUpload ($oriName);
        break;

      case 's3':
        return !($this->uploadColumnAndUpload () && S3::putObject ($temp, Config::get ('s3', 'bucket'), UPLOAD_NAME . implode ('/', $savePath) . '/' . $oriName) && $this->uploadColumnAndUpload ($oriName) && @unlink ($temp)) ? ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ModelUploader 錯誤！', '搬移預設位置時發生錯誤！', 'temp：' . $temp, ', Save Path：' . $savePath, ', Name：' . $ori_name, '請程式設計者確認狀況！') : false : true;
        break;
    }
    return false;
  }
  // boolean
  protected function uploadColumnAndUpload ($value = '', $isSave = true) {
    if (!$this->_cleanOldFile ()) return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ModelUploader 錯誤！', '清除檔案發生錯誤！', '請程式設計者確認狀況！') : false;
    if ($isSave && $this->uploadColumn ($value)) return true;
    return true;
  }
  // boolean
  protected function _cleanOldFile () {
    switch (ModelUploader::STORAGE) {
      case 'local':
        if ($paths = $this->getAllPaths ()) foreach ($paths as $path) if (file_exists ($path = UPLOAD . implode (DIRECTORY_SEPARATOR, $path)) && is_file ($path)) if (!@unlink ($path)) return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ModelUploader 錯誤！', '清除檔案發生錯誤！', '請程式設計者確認狀況！') : false;
        return true;
        break;
      
      case 's3':
        if ($paths = $this->getAllPaths ())
          foreach ($paths as $path)
            if (!S3::deleteObject (Config::get ('s3', 'bucket'), UPLOAD_NAME . implode ('/', $path)))
              return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ModelUploader 錯誤！', '清除檔案發生錯誤！', '請程式設計者確認狀況！') : false;
        return true;
        break;
    }
    return false;
  }
  // array
  public function getAllPaths () {
    switch (ModelUploader::STORAGE) {
      case 'local':
        return is_writable (UPLOAD . implode (DIRECTORY_SEPARATOR, $path = $this->getSavePath ($this->getValue ()))) ? array ($path) : array ();
        break;

      case 's3':
        return array ($this->getSavePath ($this->getValue ()));
        break;
    }
    return array ();
  }
  // boolean
  protected function uploadColumn ($value) {
    $columnName = $this->columnName;
    $this->model->$columnName = $value;
    $this->model->save ();
    $this->columnValue = $value;
    $this->model->$columnName = $this;

    $k = $this->model->table ()->table . '_' . $this->columnName;
    ModelUploader::$urls[$k] = array ();
    return true;
  }
  // string
  public function url ($key = '') {
    $k = $this->model->table ()->table . '_' . $this->columnName;
    if (isset (ModelUploader::$urls[$k][$key])) return ModelUploader::$urls[$k][$key];

    switch (ModelUploader::STORAGE) {
      case 'local':
        return ModelUploader::$urls[$k][$key] = ($path = $this->path ($key)) ? '/' . UPLOAD_NAME . implode ('/', $path) : ModelUploader::D4_URL;
        break;

      case 's3':
        return ModelUploader::$urls[$k][$key] = trim (Config::get ('s3', 'url'), '/') . '/' . UPLOAD_NAME . implode ('/', $this->path ($key));
        break;
    }
    return '';
  }
  // array
  public function path ($fileName = '') {
    switch (ModelUploader::STORAGE) {
      case 'local':
        return is_readable (UPLOAD . implode (DIRECTORY_SEPARATOR, $path = $this->getSavePath ($fileName))) ? $path : array ();
        break;

      case 's3':
        return $this->getSavePath ($fileName);
        break;
    }
    return array ();
  }
  // boolean
  public static function chmod ($path) {
    $oldmask = umask (0);
    @chmod ($path, 0777);
    umask ($oldmask);
    return true;
  }
  // string
  public static function download ($url, $fileName = null, $is_use_reffer = false, $cainfo = null) {
    if (is_readable ($cainfo)) $url = str_replace (' ', '%20', $url);

    $options = array (CURLOPT_URL => $url, CURLOPT_TIMEOUT => 120, CURLOPT_HEADER => false, CURLOPT_MAXREDIRS => 10, CURLOPT_AUTOREFERER => true, CURLOPT_CONNECTTIMEOUT => 30, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.76 Safari/537.36");
    if (is_readable ($cainfo)) $options[CURLOPT_CAINFO] = $cainfo;
    if ($is_use_reffer) $options[CURLOPT_REFERER] = $url;

    $ch = curl_init ($url);
    curl_setopt_array ($ch, $options);
    $data = curl_exec ($ch);
    curl_close ($ch);

    if (!$fileName) return $data;

    $write = fopen ($fileName, 'w');
    fwrite ($write, $data);
    fclose ($write);

    $oldmask = umask (0);
    @chmod ($fileName, 0777);
    umask ($oldmask);

    return filesize ($fileName) ?  $fileName : null;
  }
  // uploader object
  public static function bind ($columnName) {
    if (!(($trace = debug_backtrace (DEBUG_BACKTRACE_PROVIDE_OBJECT)) && isset ($trace[1]) && isset ($trace[1]['object']) && ($trace[1]['object'] instanceof Model))) return ModelUploader::error ('ModelUploader 錯誤！', '取得 debug_backtrace 發生錯誤，無法取得 debug_backtrace 或 無法取得上層物件！', '請確認 ModelUploader::bind 的使用方法的正確性！');

    $model = $trace[1]['object'];
    $className = get_class ($model) . ActiveRecord\classify ($columnName) . ModelUploader::CLASS_SUFFIX;

    if (!is_readable ($path = MODEL_UPLOADER . $className . EXT)) return ModelUploader::error ('ModelUploader 錯誤！', '讀取不到 ' . $className . ' 物件！路徑：' . $path, '請確認 ModelUploader::bind 的使用方法的正確性！');

    require_once $path;

    return new $className ($model, $columnName);
  }
  public static function error () {
    $trace = array_map (function ($t) { return isset ($t['file']) && isset ($t['line']) ? array ('file' => $t['file'], 'line' => $t['line']) : null; }, debug_backtrace (DEBUG_BACKTRACE_PROVIDE_OBJECT));
    $messages = array ();
    foreach (func_get_args () as $value)
      if (is_array ($value)) $messages = array_merge ($messages, $value);
      else array_push ($messages, $value);

    var_dump ($messages, $trace);
    return exit;
  }
  // sring
  protected function getSavePath ($fileName = '') { return ($id = $this->getColumnValue (ModelUploader::PK_COLUMN)) ? $fileName ? array ($this->model->table ()->table, $this->columnName, floor ($id / 1000000), floor (($id % 1000000) / 10000), floor ((($id % 1000000) % 10000) / 100), ($id % 100), $fileName) : array ($this->model->table ()->table, $this->columnName, floor ($id / 1000000), floor (($id % 1000000) / 10000), floor ((($id % 1000000) % 10000) / 100), ($id % 100)) : ($fileName ? array ($this->model->table ()->table, $this->columnName, $fileName) : array ($this->model->table ()->table, $this->columnName)); }
  // sring
  protected function getColumnValue ($columnMame) { return isset ($this->model->$columnMame) ? $this->model->$columnMame : ''; }
  // string
  public function __toString () { return $this->getValue (); }
  // string
  public function getValue () { return (string)$this->columnValue; }
  // boolean
  public function cleanAllFiles () { return $this->uploadColumnAndUpload (); }
  // string
  public static function getRandomName () { return uniqid (rand () . '_'); }
}

include_once 'ImageModelUploader' . EXT;
include_once 'FileModelUploader' . EXT;
