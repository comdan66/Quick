<?php
namespace ActiveRecord;

class Memcache {
  const DEFAULT_PORT = 11211;

  private $memcache;

  
  public function __construct ($options) {
    $this->memcache = new \Memcache ();
    $options['port'] = isset ($options['port']) ? $options['port'] : self::DEFAULT_PORT;

    if (!$this->memcache->connect ($options['host'],$options['port']))
      throw new CacheException ("Could not connect to $options[host]:$options[port]");
  }

  public function flush () {
    $this->memcache->flush ();
  }

  public function read ($key) {
    return $this->memcache->get ($key);
  }

  public function write ($key, $value, $expire) {
    $this->memcache->set ($key,$value,null,$expire);
  }

  public function delete ($key) {
    $this->memcache->delete ($key);
  }
}
