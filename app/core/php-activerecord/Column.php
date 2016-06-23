<?php
namespace ActiveRecord;

class Column {
  const STRING = 1;
  const INTEGER = 2;
  const DECIMAL = 3;
  const DATETIME = 4;
  const DATE = 5;
  const TIME = 6;

  public $name;
  public $inflected_name;
  public $type;
  public $raw_type;
  public $length;
  public $nullable;
  public $pk;
  public $default;
  public $auto_increment;
  public $sequence;
  
  static $TYPE_MAPPING = array (
    'datetime' => self::DATETIME,
    'timestamp' => self::DATETIME,
    'date' => self::DATE,
    'time' => self::TIME,
    'tinyint' => self::INTEGER,
    'smallint' => self::INTEGER,
    'mediumint' => self::INTEGER,
    'int' => self::INTEGER,
    'bigint' => self::INTEGER,
    'float' => self::DECIMAL,
    'double' => self::DECIMAL,
    'numeric' => self::DECIMAL,
    'decimal' => self::DECIMAL,
    'dec' => self::DECIMAL);

  public static function castIntegerSafely ($value) {
    if (is_int ($value)) return $value;
    elseif (is_numeric ($value) && floor ($value) != $value) return (int) $value;
    elseif (is_string ($value) && is_float ($value + 0)) return (string) $value;
    elseif (is_float ($value) && $value >= PHP_INT_MAX) return number_format ($value, 0, '', '');

    return (int) $value;
  }

  public function cast ($value, $connection) {
    if ($value === null)
      return null;

    switch ($this->type) {
      case self::STRING:  return (string)$value;
      case self::INTEGER:  return static::castIntegerSafely ($value);
      case self::DECIMAL:  return (double)$value;
      case self::DATETIME:
      case self::DATE:
        if (!$value) return null;
        $date_class = Config::instance ()->get_date_class ();
        if ($value instanceof $date_class) return $value;
        if ($value instanceof \DateTime) return $date_class::createFromFormat (Connection::DATETIME_TRANSLATE_FORMAT, $value->format (Connection::DATETIME_TRANSLATE_FORMAT), $value->getTimezone ());
        return $connection->string_to_datetime ($value);
    }
    return $value;
  }
  
  public function map_raw_type () {
    if ($this->raw_type == 'integer') $this->raw_type = 'int';
    $this->type = array_key_exists ($this->raw_type, self::$TYPE_MAPPING) ? self::$TYPE_MAPPING[$this->raw_type] : self::STRING;
    return $this->type;
  }
}
