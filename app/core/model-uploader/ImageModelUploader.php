<?php

/**
 * @author      OA Wu <comdan66@gmail.com>
 * @copyright   Copyright (c) 2016 OA Wu Design
 */

class ImageModelUploader extends ModelUploader {
  const SEPARATE_SYMBOL = '_';
  const AUTO_ADD_FORMTA = true;
  const DEFAULT_VERSION = array ('' => array ());
  const IMAGE_UTILITY_CLASS = 'ImageGdUtility';

  public function __construct ($model = null, $columnName = null) {
    parent::__construct ($model, $columnName);
  
    require_once LIBRARY . 'image-utility' . DIRECTORY_SEPARATOR . 'ImageUtility' . EXT;
  }
  // return array
  protected function getVersions () {
    return ImageModelUploader::DEFAULT_VERSION;
  }
  // return array
  public function path ($key = '') {
    if (($versions = ($versions = $this->getVersions ()) ? $versions : ImageModelUploader::DEFAULT_VERSION) && isset ($versions[$key]) && ($fileName = $key . ImageModelUploader::SEPARATE_SYMBOL . $this->getValue ()))
      return parent::path ($fileName);
    else
      return array ();
  }
  // return array
  public function getAllPaths () {
    if (!($versions = ($versions = $this->getVersions ()) ? $versions : ImageModelUploader::DEFAULT_VERSION))
      return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ImageModelUploader 錯誤！', 'Versions 格式錯誤，請檢查 getVersions () 或者 ImageModelUploader::DEFAULT_VERSION！', '預設值 ImageModelUploader::DEFAULT_VERSION！') : '';

    $paths = array ();
    switch (ModelUploader::STORAGE) {
      case 'local':
        foreach ($versions as $key => $version)
          if (is_writable (UPLOAD . implode (DIRECTORY_SEPARATOR, $path = $this->getSavePath ($key . ImageModelUploader::SEPARATE_SYMBOL . $this->getValue ()))))
            array_push ($paths, $path);
        return $paths;
        break;

      case 's3':
        foreach ($versions as $key => $version)
          array_push ($paths, $this->getSavePath ($key . ImageModelUploader::SEPARATE_SYMBOL . $this->getValue ()));
        return $paths;
        break;
    }
    return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('OrmUploader 錯誤！', '未知的 driver，系統尚未支援 ' . $this->getDriver () . ' 的空間！') : array ();
  }
  // return boolean
  protected function moveFileAndUploadColumn ($temp, $savePath, $ori_name) {
    if (!$versions = ($versions = $this->getVersions ()) ? $versions : ImageModelUploader::DEFAULT_VERSION)
      return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ImageModelUploader 錯誤！', 'Versions 格式錯誤，請檢查 getVersions () 或者 ImageModelUploader::DEFAULT_VERSION！', '預設值 ImageModelUploader::DEFAULT_VERSION！') : '';

    $news = array ();
    try {
      foreach ($versions as $key => $version) {
        $image = ImageUtility::create ($temp, ImageModelUploader::IMAGE_UTILITY_CLASS);
        $name = !isset ($name) ? ModelUploader::getRandomName () . (ImageModelUploader::AUTO_ADD_FORMTA ? '.' . $image->getFormat () : '') : $name;
        $newName = $key . ImageModelUploader::SEPARATE_SYMBOL . $name;

        $newPath = TEMP . $newName;
        array_push ($news, array ('name' => $newName, 'path' => $newPath));

        if (!$this->_utility ($image, $newPath, $key, $version)) return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ImageModelUploader 錯誤！', '圖想處理失敗！', '請程式設計者確認狀況！') : false;
      }
    } catch (Exception $e) {
      return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('Exception Error', $e->getMessages ()) : false;
    }

    if (count ($news) != count ($versions))
      return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ImageModelUploader 錯誤！', '不明原因錯誤！', '請程式設計者確認狀況！') : false;

    switch (ModelUploader::STORAGE) {
      case 'local':
        @self::uploadColumnAndUpload ();
        foreach ($news as $new)
          if (!@rename ($new['path'], UPLOAD . implode (DIRECTORY_SEPARATOR, $savePath) . DIRECTORY_SEPARATOR . $new['name']))
            return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ImageModelUploader 錯誤！', '不明原因錯誤！', '請程式設計者確認狀況！') : false;
        return self::uploadColumnAndUpload ($name) && @unlink ($temp);
        break;

      case 's3':
        @self::uploadColumnAndUpload ();
        foreach ($news as $new)
          if (!(S3::putObject ($new['path'], Config::get ('s3', 'bucket'), UPLOAD_NAME . implode ('/', $savePath) . '/' . $new['name']) && @unlink ($new['path'])))
            return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ImageModelUploader 錯誤！', '不明原因錯誤！', '請程式設計者確認狀況！') : false;
        return self::uploadColumnAndUpload ($name) && @unlink ($temp);
        break;
    }

    return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ImageModelUploader 錯誤！', '未知的 driver，系統尚未支援 ' . $this->getDriver () . ' 的空間！') : false;
  }
  // return boolean
  private function _utility ($image, $save, $key, $version) {
    if ($version)
      if (is_callable (array ($image, $method = array_shift ($version))))
        call_user_func_array (array ($image, $method), $version);
      else
        return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ImageModelUploader 錯誤！', 'ImageUtility 無法呼叫的 method，method：' . $method, '請程式設計者確認狀況！') : '';
    return $image->save ($save, true);
  }
  
  // return array
  public function save_as ($key, $version) {
    if (!($key && $version))
      return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ImageModelUploader 錯誤！', '參數錯誤，請檢查 save_as 函式參數！', '請程式設計者確認狀況！') : array ();

    if (!(($versions = ($versions = $this->getVersions ()) ? $versions : ImageModelUploader::DEFAULT_VERSION)))
      return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ImageModelUploader 錯誤！', 'Versions 格式錯誤，請檢查 getVersions () 或者 ImageModelUploader::DEFAULT_VERSION！', '預設值 ImageModelUploader::DEFAULT_VERSION！') : array ();

    if (in_array ($key, $keys = array_keys ($versions)))
      return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ImageModelUploader 錯誤！', '已經有相符合的 key 名稱，key：' . $key, '目前的 key 有：' . implode (', ', $keys)) : array ();

    switch (ModelUploader::STORAGE) {
      case 'local':
        foreach ($keys as $oriKey)
          if (is_readable ($oriPath = UPLOAD . implode (DIRECTORY_SEPARATOR, $this->getSavePath ($oriKey . ImageModelUploader::SEPARATE_SYMBOL . ($name = $this->getValue ())))))
            break;

        if (!$oriPath) return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ImageModelUploader 錯誤！', '沒有任何的檔案可以被使用！', '請確認 getVersions () 函式內有存在的檔案可被另存！', '請程式設計者確認狀況！') : array ();

        if (!file_exists (UPLOAD . implode (DIRECTORY_SEPARATOR, $path = $this->getSavePath ())))
          mkdir777 (UPLOAD . implode (DIRECTORY_SEPARATOR, $path));

        if (!is_writable (UPLOAD . implode (DIRECTORY_SEPARATOR, $path)))
          return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ImageModelUploader 錯誤！', '資料夾不能儲存！路徑：' . $path, '請程式設計者確認狀況！') : array ();

        try {
          $image = ImageUtility::create ($oriPath, ImageModelUploader::IMAGE_UTILITY_CLASS);
          $path = array_merge ($path, array ($key . ImageModelUploader::SEPARATE_SYMBOL . $name));
          return $this->_utility ($image, UPLOAD . implode (DIRECTORY_SEPARATOR, $path), $key, $version) ? $path : array ();
        } catch (Exception $e) {
          return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('Exception Error', $e->getMessages ()) : false;
        }
        break;

      case 's3':
        if (!@S3::getObject (Config::get ('s3', 'bucket'), UPLOAD_NAME . implode ('/', $path = $this->getSavePath ($fileName = array_shift ($keys) . ImageModelUploader::SEPARATE_SYMBOL . ($name = $this->getValue ()))), TEMP . $fileName))
          return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('ImageModelUploader 錯誤！', '沒有任何的檔案可以被使用！', '請確認 getVersions () 函式內有存在的檔案可被另存！', '請程式設計者確認狀況！') : array ();

        try {
          $image = ImageUtility::create (TEMP . $fileName, ImageModelUploader::IMAGE_UTILITY_CLASS);
          $newPath = $this->getSavePath ($newName = $key . ImageModelUploader::SEPARATE_SYMBOL . $name);
          return $this->_utility ($image, TEMP . $fileName, $key, $version) && S3::putObject (TEMP . $fileName, Config::get ('s3', 'bucket'), UPLOAD_NAME . implode ('/', $newPath)) && @unlink (TEMP . $fileName) ? $newPath : array ();
        } catch (Exception $e) {
          return ModelUploader::SHOW_DEBUG ? ModelUploader::error ('Exception Error', $e->getMessages ()) : false;
        }
        break;
    }
    return array ();
  }
}
