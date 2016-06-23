<?php
namespace ActiveRecord;

class ActiveRecordException extends \Exception {}
class RecordNotFound extends ActiveRecordException {}
class ModelException extends ActiveRecordException {}
class ExpressionsException extends ActiveRecordException {}
class ConfigException extends ActiveRecordException {}
class ValidationsArgumentError extends ActiveRecordException {}
class RelationshipException extends ActiveRecordException {}
class HasManyThroughAssociationException extends RelationshipException {}

class DatabaseException extends ActiveRecordException {
  public function __construct ($adapter_or_string_or_mystery) {
    if ($adapter_or_string_or_mystery instanceof Connection) parent::__construct (implode (', ', $adapter_or_string_or_mystery->connection->errorInfo ()), intval ($adapter_or_string_or_mystery->connection->errorCode ()));
    elseif ($adapter_or_string_or_mystery instanceof \PDOStatement) parent::__construct (implode (', ', $adapter_or_string_or_mystery->errorInfo ()), intval ($adapter_or_string_or_mystery->errorCode ()));
    else parent::__construct ($adapter_or_string_or_mystery);
  }
}

class UndefinedPropertyException extends ModelException {
  public function __construct ($class_name, $property_name) {
    if (is_array ($property_name)) return $this->message = implode ("\r\n", $property_name);
    $this->message = 'Undefined property: ' . $class_name . '->' . $property_name . ' in ' . $this->file . ' on line ' . $this->line;
    parent::__construct ();
  }
}

class ReadOnlyException extends ModelException {
  public function __construct ($class_name, $method_name) {
    $this->message = $class_name . '::' . $method_name . '() cannot be invoked because this model is set to read only';
    parent::__construct ();
  }
}
