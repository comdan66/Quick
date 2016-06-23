<?php
namespace ActiveRecord;

class Filecache {
  private $path = null;
  public function __construct ($path = '') {
    $path = rtrim ($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $this->path = is_dir ($path) && is_writable ($path) ? $path : null;
  }
  public function flush () {
    if ($this->path === null) return false;
    return delete_files ($this->path, false);
  }
  public function read ($key) {
    if ($this->path === null) return false;
    if (($data = read_file ($this->path . $key)) === false)
      return false;

    $data = unserialize ($data);
    if (time () > $data['time'] + $data['ttl']) return $this->delete ($key) && false;

    return $data['data'];
  }
  public function write ($key, $value, $expire = 300) {
    if ($this->path === null) return false;

    $contents = array(
        'data' => $value,
        'time' => time (),
        'ttl' => $expire
      );

    if (!write_file ($this->path . $key, serialize ($contents))) return false;
    @chmod ($this->path . $key, 0777);
    return true;
  }
  public function delete ($key) {
    @unlink ($this->path . $key);
    return true;
  }
}