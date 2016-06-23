<?php
namespace ActiveRecord;

class DateTime extends \DateTime implements DateTimeInterface {
  private $model;
  private $attribute_name;
  public static $DEFAULT_FORMAT = 'db';
  public static $FORMATS = array (
    'db'      => 'Y-m-d H:i:s',
    'number'  => 'YmdHis',
    'time'    => 'H:i',
    'short'   => 'd M H:i',
    'long'    => 'F d, Y H:i',
    'atom'    => \DateTime::ATOM,
    'cookie'  => \DateTime::COOKIE,
    'iso8601' => \DateTime::ISO8601,
    'rfc822'  => \DateTime::RFC822,
    'rfc850'  => \DateTime::RFC850,
    'rfc1036' => \DateTime::RFC1036,
    'rfc1123' => \DateTime::RFC1123,
    'rfc2822' => \DateTime::RFC2822,
    'rfc3339' => \DateTime::RFC3339,
    'rss'     => \DateTime::RSS,
    'w3c'     => \DateTime::W3C);

  public function attribute_of ($model, $attribute_name) {
    $this->model = $model;
    $this->attribute_name = $attribute_name;
  }
  
  public function format ($format=null) {
    return parent::format (self::get_format ($format));
  }
  
  public static function get_format ($format=null) {
    if (!$format)
      $format = self::$DEFAULT_FORMAT;
    if (array_key_exists ($format, self::$FORMATS))
       return self::$FORMATS[$format];
    return $format;
  }
  
  public static function createFromFormat ($format, $time, $tz = null) {
    $phpDate = $tz ? parent::createFromFormat ($format, $time, $tz) : parent::createFromFormat ($format, $time);
    if (!$phpDate)
      return false;
    $ourDate = new static (null, $phpDate->getTimezone ());
    $ourDate->setTimestamp ($phpDate->getTimestamp ());
    return $ourDate;
  }

  public function __toString () {
    return $this->format ();
  }
  
  public function __clone () {
    $this->model = null;
    $this->attribute_name = null;
  }

  private function flag_dirty () {
    if ($this->model)
      $this->model->flag_dirty ($this->attribute_name);
  }

  public function setDate ($year, $month, $day) {
    $this->flag_dirty ();
    return parent::setDate ($year, $month, $day);
  }

  public function setISODate ($year, $week , $day = 1) {
    $this->flag_dirty ();
    return parent::setISODate ($year, $week, $day);
  }

  public function setTime ($hour, $minute, $second = 0) {
    $this->flag_dirty ();
    return parent::setTime ($hour, $minute, $second);
  }

  public function setTimestamp ($unixtimestamp) {
    $this->flag_dirty ();
    return parent::setTimestamp ($unixtimestamp);
  }

  public function setTimezone ($timezone) {
    $this->flag_dirty ();
    return parent::setTimezone ($timezone);
  }
  
  public function modify ($modify) {
    $this->flag_dirty ();
    return parent::modify ($modify);
  }
  
  public function add ($interval) {
    $this->flag_dirty ();
    return parent::add ($interval);
  }

  public function sub ($interval) {
    $this->flag_dirty ();
    return parent::sub ($interval);
  }
}
