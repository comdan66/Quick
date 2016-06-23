<?php
namespace ActiveRecord;

class SQLBuilder {
  private $connection;
  private $operation = 'SELECT';
  private $table;
  private $select = '*';
  private $joins;
  private $order;
  private $limit;
  private $offset;
  private $group;
  private $having;
  private $update;
  private $where;
  private $where_values = array ();
  private $data;
  private $sequence;

  
  public function __construct ($table) {
    $this->table    = $table;
  }

  
  public function __toString () {
    return $this->to_s ();
  }
  
  public function to_s () {
    $func = 'build_' . strtolower ($this->operation);
    return $this->$func ();
  }
  
  public function bind_values () {
    $ret = array ();

    if ($this->data)
      $ret = array_values ($this->data);

    if ($this->get_where_values ())
      $ret = array_merge ($ret,$this->get_where_values ());

    return array_flatten ($ret);
  }

  public function get_where_values () {
    return $this->where_values;
  }

  public function where () {
    $this->apply_where_conditions (func_get_args ());
    return $this;
  }

  public function order ($order) {
    $this->order = $order;
    return $this;
  }

  public function group ($group) {
    $this->group = $group;
    return $this;
  }

  public function having ($having) {
    $this->having = $having;
    return $this;
  }

  public function limit ($limit) {
    $this->limit = intval ($limit);
    return $this;
  }

  public function offset ($offset) {
    $this->offset = intval ($offset);
    return $this;
  }

  public function select ($select) {
    $this->operation = 'SELECT';
    $this->select = $select;
    return $this;
  }

  public function joins ($joins) {
    $this->joins = $joins;
    return $this;
  }

  public function insert ($hash, $pk=null, $sequence_name=null) {
    if (!is_hash ($hash))
      throw new ActiveRecordException ('Inserting requires a hash.');

    $this->operation = 'INSERT';
    $this->data = $hash;

    if ($pk && $sequence_name)
      $this->sequence = array ($pk,$sequence_name);

    return $this;
  }

  public function update ($mixed) {
    $this->operation = 'UPDATE';

    if (is_hash ($mixed))
      $this->data = $mixed;
    elseif (is_string ($mixed))
      $this->update = $mixed;
    else
      throw new ActiveRecordException ('Updating requires a hash or string.');

    return $this;
  }

  public function delete () {
    $this->operation = 'DELETE';
    $this->apply_where_conditions (func_get_args ());
    return $this;
  }

  
  public static function reverse_order ($order) {
    if (!trim ($order))
      return $order;

    $parts = explode (',',$order);

    for ($i=0,$n=count ($parts); $i<$n; ++$i) {
      $v = strtolower ($parts[$i]);

      if (strpos ($v,' asc') !== false)
        $parts[$i] = preg_replace ('/asc/i','DESC',$parts[$i]);
      elseif (strpos ($v,' desc') !== false)
        $parts[$i] = preg_replace ('/desc/i','ASC',$parts[$i]);
      else
        $parts[$i] .= ' DESC';
    }
    return join (',',$parts);
  }

  
  public static function create_conditions_from_underscored_string ($name, &$values=array (), &$map=null) {
    if (!$name)
      return null;

    $parts = preg_split ('/(_and_|_or_)/i',$name,-1,PREG_SPLIT_DELIM_CAPTURE);
    $num_values = count ($values);
    $conditions = array ('');

    for ($i=0,$j=0,$n=count ($parts); $i<$n; $i+=2,++$j) {
      if ($i >= 2)
        $conditions[0] .= preg_replace (array ('/_and_/i','/_or_/i'),array (' AND ',' OR '),$parts[$i-1]);

      if ($j < $num_values) {
        if (!is_null ($values[$j])) {
          $bind = is_array ($values[$j]) ? ' IN(?)' : '=?';
          $conditions[] = $values[$j];
        }
        else
          $bind = ' IS NULL';
      }
      else
        $bind = ' IS NULL';
      $name = $map && isset ($map[$parts[$i]]) ? $map[$parts[$i]] : $parts[$i];

      $conditions[0] .= Connection::instance ()->quote_name ($name) . $bind;
    }
    return $conditions;
  }

  
  public static function create_hash_from_underscored_string ($name, &$values=array (), &$map=null) {
    $parts = preg_split ('/(_and_|_or_)/i',$name);
    $hash = array ();

    for ($i=0,$n=count ($parts); $i<$n; ++$i) {
      $name = $map && isset ($map[$parts[$i]]) ? $map[$parts[$i]] : $parts[$i];
      $hash[$name] = $values[$i];
    }
    return $hash;
  }

  
  private function prepend_table_name_to_fields ($hash=array ()) {
    $new = array ();
    $table = Connection::instance ()->quote_name ($this->table);

    foreach ($hash as $key => $value) {
      $k = Connection::instance ()->quote_name ($key);
      $new[$table.'.'.$k] = $value;
    }

    return $new;
  }

  private function apply_where_conditions ($args) {
    require_once 'Expressions.php';
    $num_args = count ($args);

    if ($num_args == 1 && is_hash ($args[0])) {
      $hash = is_null ($this->joins) ? $args[0] : $this->prepend_table_name_to_fields ($args[0]);
      $e = new Expressions ($hash);
      $this->where = $e->to_s ();
      $this->where_values = array_flatten ($e->values ());
    }
    elseif ($num_args > 0) {
      $values = array_slice ($args,1);

      foreach ($values as $name => &$value) {
        if (is_array ($value)) {
          $e = new Expressions ($args[0]);
          $e->bind_values ($values);
          $this->where = $e->to_s ();
          $this->where_values = array_flatten ($e->values ());
          return;
        }
      }
      $this->where = $args[0];
      $this->where_values = &$values;
    }
  }

  private function build_delete () {
    $sql = "DELETE FROM $this->table";

    if ($this->where) $sql .= " WHERE $this->where";
    if (Connection::instance ()->accepts_limit_and_order_for_update_and_delete ()) {
      if ($this->order) $sql .= " ORDER BY $this->order";
      if ($this->limit) $sql = Connection::instance ()->limit ($sql,null,$this->limit);
    }

    return $sql;
  }

  private function build_insert () {
    require_once 'Expressions.php';
    $keys = join (',',$this->quoted_key_names ());
    $sql = $this->sequence ? "INSERT INTO $this->table ($keys," . Connection::instance ()->quote_name ($this->sequence[0]) . ") VALUES(?," . Connection::instance ()->next_sequence_value ($this->sequence[1]) . ")" : "INSERT INTO $this->table ($keys) VALUES(?)";
    $e = new Expressions ($sql,array_values ($this->data));
    return $e->to_s ();
  }

  private function build_select () {
    $sql = "SELECT $this->select FROM $this->table";

    if ($this->joins) $sql .= ' ' . $this->joins;
    if ($this->where) $sql .= " WHERE $this->where";
    if ($this->group) $sql .= " GROUP BY $this->group";
    if ($this->having) $sql .= " HAVING $this->having";
    if ($this->order) $sql .= " ORDER BY $this->order";
    if ($this->limit || $this->offset) $sql = Connection::instance ()->limit ($sql,$this->offset,$this->limit);

    return $sql;
  }

  private function build_update () {
    $set = strlen ($this->update) <= 0 ? join ('=?, ', $this->quoted_key_names ()) . '=?' : $this->update;
    $sql = "UPDATE $this->table SET $set";

    if ($this->where) $sql .= " WHERE $this->where";

    if (Connection::instance ()->accepts_limit_and_order_for_update_and_delete ()) {
      if ($this->order) $sql .= " ORDER BY $this->order";
      if ($this->limit) $sql = Connection::instance ()->limit ($sql,null,$this->limit);
    }

    return $sql;
  }

  private function quoted_key_names () {
    $keys = array ();
    foreach ($this->data as $key => $value) array_push ($keys, Connection::instance ()->quote_name ($key));
    return $keys;
  }
}