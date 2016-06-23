<?php
namespace ActiveRecord;
use ReflectionClass;

class Reflections extends Singleton {
  private $reflections = array ();
  
  public function add ($class=null) {
    $class = $this->get_class ($class);
    if (!isset ($this->reflections[$class])) $this->reflections[$class] = new ReflectionClass ($class);
    return $this;
  }
  
  public function destroy ($class) {
    if (isset ($this->reflections[$class])) $this->reflections[$class] = null;
  }
  
  public function get ($class=null) {
    $class = $this->get_class ($class);
    if (isset ($this->reflections[$class])) return $this->reflections[$class];
    throw new ActiveRecordException ("Class not found: $class");
  }
  
  private function get_class ($mixed=null) {
    if (is_object ($mixed)) return get_class ($mixed);
    if (!is_null ($mixed)) return $mixed;
    return $this->get_called_class ();
  }
}