<?php
namespace ActiveRecord;
use PDO;
use PDOException;
use Closure;

require_once 'Column.php';

abstract class Connection {
  const DATETIME_TRANSLATE_FORMAT = 'Y-m-d\TH:i:s';

  private static $connection;
  public $last_query;
  public $protocol;
  static $date_format = 'Y-m-d';
  static $datetime_format = 'Y-m-d H:i:s T';
  static $QUOTE_CHARACTER = '`';
  static $DEFAULT_PORT = 0;

  static $PDO_OPTIONS = array (
    PDO::ATTR_CASE => PDO::CASE_LOWER,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
    PDO::ATTR_STRINGIFY_FETCHES => false);

  public static function closeInstance () {
    unset (self::$connection);
    self::$connection = null;
    return true;
  }
  public static function instance () {
    if (isset (self::$connection))
      return self::$connection;

    $info = static::parse_connection_url (Config::getConnectionUrl ());
    $fqclass = static::load_adapter_class ($info->protocol);

    try {
      $connection = new $fqclass ($info);
      $connection->protocol = $info->protocol;
      if (isset ($info->charset)) $connection->set_encoding ($info->charset);
      
      self::$connection = $connection;
    } catch (PDOException $e) {
      throw new DatabaseException ($e);
    }
    return self::$connection;
  }

  private static function load_adapter_class ($adapter) {
    $class = ucwords ($adapter) . 'Adapter';
    $fqclass = 'ActiveRecord\\' . $class;
    $source = __DIR__ . "/adapters/$class.php";

    if (!file_exists ($source))
      throw new DatabaseException ("$fqclass not found!");

    require_once ($source);
    return $fqclass;
  }

  public static function parse_connection_url ($connection_url) {
    $url = @parse_url ($connection_url);

    if (!isset ($url['host'])) throw new DatabaseException ('Database host must be specified in the connection string. If you want to specify an absolute filename, use e.g. sqlite://unix (/path/to/file)');

    $info = new \stdClass ();
    $info->protocol = $url['scheme'];
    $info->host = $url['host'];
    $info->db = isset ($url['path']) ? substr ($url['path'], 1) : null;
    $info->user = isset ($url['user']) ? $url['user'] : null;
    $info->pass = isset ($url['pass']) ? $url['pass'] : null;

    $allow_blank_db = ($info->protocol == 'sqlite');

    if ($info->host == 'unix (') {
      $socket_database = $info->host . '/' . $info->db;
      $unix_regex = $allow_blank_db ? '/^unix\((.+)\)\/?().*$/' : '/^unix\((.+)\)\/(.+)$/';

      if (preg_match_all ($unix_regex, $socket_database, $matches) > 0) {
        $info->host = $matches[1][0];
        $info->db = $matches[2][0];
      }
    } elseif (substr ($info->host, 0, 8) == 'windows (') {
      $info->host = urldecode (substr ($info->host, 8) . '/' . substr ($info->db, 0, -1));
      $info->db = null;
    }

    if ($allow_blank_db && $info->db) $info->host .= '/' . $info->db;
    if (isset ($url['port'])) $info->port = $url['port'];

    if (strpos ($connection_url, 'decode=true') !== false) {
      if ($info->user) $info->user = urldecode ($info->user);
      if ($info->pass) $info->pass = urldecode ($info->pass);
    }

    if (isset ($url['query']))
      foreach (explode ('/&/', $url['query']) as $pair) {
        list ($name, $value) = explode ('=', $pair);

        if ($name == 'charset') $info->charset = $value;
      }

    return $info;
  }

  protected function __construct ($info) {
    try {
      if ($info->host[0] != '/') {
        $host = 'host=' . $info->host;
        if (isset ($info->port)) $host .= ';port=' . $info->port;
      }
      else $host = 'unix_socket=' . $info->host;

      $this->connection = new PDO($info->protocol . ':' . $host . ';dbname=' . $info->db, $info->user, $info->pass, static::$PDO_OPTIONS);
    } catch (PDOException $e) {
      throw new DatabaseException ($e);
    }
  }

  public function columns ($table) {
    $columns = array ();
    $sth = $this->query_column_info ($table);

    while (($row = $sth->fetch ())) {
      $c = $this->create_column ($row);
      $columns[$c->name] = $c;
    }
    return $columns;
  }

  public function escape ($string) {
    return $this->connection->quote ($string);
  }

  public function insert_id ($sequence=null) {
    return $this->connection->lastInsertId ($sequence);
  }

  public function query ($sql, &$values=array ()) {
    $this->last_query = $sql;

    try {
      if (!($sth = $this->connection->prepare ($sql)))
        throw new DatabaseException ($this);
    } catch (PDOException $e) {
      throw new DatabaseException ($this);
    }

    $sth->setFetchMode (PDO::FETCH_ASSOC);

    try {
      if (!$this->profile_and_execute ($sth, $sql, $values))
        throw new DatabaseException ($this);
    } catch (PDOException $e) {
      throw new DatabaseException ($e);
    }
    return $sth;
  }

  public function profile_and_execute ($sth, $sql, &$values = array ()) {
    $ext = function () use ($sth, &$values) { return $sth->execute ($values); };

    if ($path = Config::getLogPath ()) {
      $start_time = microtime (true);
      $valid = $ext ();
      $exec_time = number_format ((microtime (true) - $start_time) * 1000, 1);
      $log_str = sprintf('% -30s', date ('Y-m-d H:i:s'). ' (' . $exec_time . 'ms)') . ' - ' . $sql . ($values ? " [['" . implode("', '", $values) . "']]" : '') . "\n";
      write_file ($path, $log_str, 'a');  
    } else $valid = $ext ();
    return $valid;
  }

  public function query_and_fetch_one ($sql, &$values=array ()) {
    $sth = $this->query ($sql, $values);
    $row = $sth->fetch (PDO::FETCH_NUM);
    return $row[0];
  }

  public function query_and_fetch ($sql, Closure $handler) {
    $sth = $this->query ($sql);

    while (($row = $sth->fetch (PDO::FETCH_ASSOC)))
      $handler ($row);
  }

  public function tables () {
    $tables = array ();
    $sth = $this->query_for_tables ();

    while (($row = $sth->fetch (PDO::FETCH_NUM)))
      $tables[] = $row[0];

    return $tables;
  }

  public function transaction () {
    if (!$this->connection->beginTransaction ())
      throw new DatabaseException ($this);
  }

  public function commit () {
    if (!$this->connection->commit ())
      throw new DatabaseException ($this);
  }

  public function rollback () {
    if (!$this->connection->rollback ())
      throw new DatabaseException ($this);
  }

  function supports_sequences () {
    return false;
  }

  public function get_sequence_name ($table, $column_name) {
    return "{$table}_seq";
  }

  public function next_sequence_value ($sequence_name) {
    return null;
  }

  public function quote_name ($string) {
    return $string[0] === static::$QUOTE_CHARACTER || $string[strlen ($string) - 1] === static::$QUOTE_CHARACTER ?
      $string : static::$QUOTE_CHARACTER . $string . static::$QUOTE_CHARACTER;
  }

  public function date_to_string ($datetime) {
    return $datetime->format (static::$date_format);
  }

  public function datetime_to_string ($datetime) {
    return $datetime->format (static::$datetime_format);
  }

  public function string_to_datetime ($string) {
    $date = date_create ($string);
    $errors = \DateTime::getLastErrors ();

    if ($errors['warning_count'] > 0 || $errors['error_count'] > 0)
      return null;

    $date_class = Config::instance ()->get_date_class ();

    return $date_class::createFromFormat (
      static::DATETIME_TRANSLATE_FORMAT,
      $date->format (static::DATETIME_TRANSLATE_FORMAT),
      $date->getTimezone ()
    );
  }

  abstract function limit ($sql, $offset, $limit);
  abstract public function query_column_info ($table);
  abstract function query_for_tables ();
  abstract function set_encoding ($charset);
  abstract public function native_database_types ();

  public function accepts_limit_and_order_for_update_and_delete () { return false; }
}
