<?php
namespace ActiveRecord;

class Table {
  private static $cache = array ();

  public $class;
  public $conn;
  public $pk;
  public $last_sql;
  public $columns = array ();
  public $table;
  public $db_name;
  public $sequence;
  public $cache_individual_model;
  public $cache_model_expire;
  public $callback;
  
  private $relationships = array ();

  public static function load ($model_class_name) {
    if (!isset (self::$cache[$model_class_name])) {
      self::$cache[$model_class_name] = new Table ($model_class_name);
      self::$cache[$model_class_name]->set_associations ();
    }
    return self::$cache[$model_class_name];
  }

  public static function clear_cache ($model_class_name=null) {
    if ($model_class_name && array_key_exists ($model_class_name,self::$cache)) unset (self::$cache[$model_class_name]);
    else self::$cache = array ();
  }

  public function __construct ($class_name) {
    $this->class = Reflections::instance ()->add ($class_name)->get ($class_name);

    $this->reestablish_connection (false);
    $this->set_table_name ();
    $this->get_meta_data ();
    $this->set_primary_key ();
    $this->set_sequence_name ();
    $this->set_delegates ();
    $this->set_cache ();
    $this->set_setters_and_getters ();
  }

  public function reestablish_connection ($close = true) {
    if ($close) Connection::closeInstance () && static::clear_cache ();
    return Connection::instance ();
  }

  public function create_joins ($joins) {
    if (!is_array ($joins)) return $joins;

    $ret = $space = '';

    $existing_tables = array ();
    foreach ($joins as $value) {
      $ret .= $space;

      if (stripos ($value,'JOIN ') === false)
        if (array_key_exists ($value, $this->relationships)) {
          $rel = $this->get_relationship ($value);
          if (array_key_exists ($rel->class_name, $existing_tables)) {
            $alias = $value;
            $existing_tables[$rel->class_name]++;
          }
          else {
            $existing_tables[$rel->class_name] = true;
            $alias = null;
          }

          $ret .= $rel->construct_inner_join_sql ($this, false, $alias);
        }
        else throw new RelationshipException ("Relationship named $value has not been declared for class: {$this->class->getName ()}");
      else $ret .= $value;

      $space = ' ';
    }
    return $ret;
  }

  public function options_to_sql ($options) {
    $table = array_key_exists ('from', $options) ? $options['from'] : $this->get_fully_qualified_table_name ();
    $sql = new SQLBuilder ($table);

    if (array_key_exists ('joins',$options)) {
      $sql->joins ($this->create_joins ($options['joins']));
      if (!array_key_exists ('select', $options)) $options['select'] = $this->get_fully_qualified_table_name () . '.*';
    }

    if (array_key_exists ('select',$options)) $sql->select ($options['select']);

    if (array_key_exists ('conditions',$options)) {
      if (!is_hash ($options['conditions'])) {
        if (is_string ($options['conditions'])) $options['conditions'] = array ($options['conditions']);
        call_user_func_array (array ($sql,'where'),$options['conditions']);
      }
      else {
        if (!empty ($options['mapped_names'])) $options['conditions'] = $this->map_names ($options['conditions'],$options['mapped_names']);
        $sql->where ($options['conditions']);
      }
    }

    if (array_key_exists ('order',$options)) $sql->order ($options['order']);
    if (array_key_exists ('limit',$options)) $sql->limit ($options['limit']);
    if (array_key_exists ('offset',$options)) $sql->offset ($options['offset']);
    if (array_key_exists ('group',$options)) $sql->group ($options['group']);
    if (array_key_exists ('having',$options)) $sql->having ($options['having']);
    return $sql;
  }

  public function find ($options) {
    $sql = $this->options_to_sql ($options);
    $readonly = (array_key_exists ('readonly',$options) && $options['readonly']) ? true : false;
    $eager_load = array_key_exists ('include',$options) ? $options['include'] : null;

    return $this->find_by_sql ($sql->to_s (),$sql->get_where_values (), $readonly, $eager_load);
  }

  public function cache_key_for_model ($pk) {
    if (is_array ($pk)) $pk = implode ('-', $pk);
    return $this->class->name . '-' . $pk;
  }

  public function find_by_sql ($sql, $values=null, $readonly=false, $includes=null) {
    $this->last_sql = $sql;

    $collect_attrs_for_includes = is_null ($includes) ? false : true;
    $list = $attrs = array ();
    $sth = Connection::instance ()->query ($sql,$this->process_data ($values));

    $self = $this;
    while (($row = $sth->fetch ())) {
      
      $cb = function () use ($row, $self) {
        return new $self->class->name ($row, false, true, false);
      };

      if ($this->cache_individual_model) {
        $key = $this->cache_key_for_model (array_intersect_key ($row, array_flip ($this->pk)));
        $model = Cache::get ($key, $cb, $this->cache_model_expire);
      }
      else {
        $model = $cb ();
      }

      if ($readonly) $model->readonly ();
      if ($collect_attrs_for_includes) $attrs[] = $model->attributes ();

      $list[] = $model;
    }

    if ($collect_attrs_for_includes && !empty ($list))
      $this->execute_eager_load ($list, $attrs, $includes);

    return $list;
  }

  
  private function execute_eager_load ($models=array (), $attrs=array (), $includes=array ()) {
    if (!is_array ($includes)) $includes = array ($includes);

    foreach ($includes as $index => $name) {
      if (is_array ($name)) { $nested_includes = count ($name) > 0 ? $name : $name[0]; $name = $index; }
      else $nested_includes = array ();

      $rel = $this->get_relationship ($name, true);
      $rel->load_eagerly ($models, $attrs, $nested_includes, $this);
    }
  }

  public function get_column_by_inflected_name ($inflected_name) {
    foreach ($this->columns as $raw_name => $column) if ($column->inflected_name == $inflected_name) return $column;
    return null;
  }

  public function get_fully_qualified_table_name ($quote_name=true) {
    $table = $quote_name ? Connection::instance ()->quote_name ($this->table) : $this->table;
    if ($this->db_name) $table = Connection::instance ()->quote_name ($this->db_name) . ".$table";
    return $table;
  }

  
  public function get_relationship ($name, $strict=false) {
    if ($this->has_relationship ($name)) return $this->relationships[$name];
    if ($strict) throw new RelationshipException ("Relationship named $name has not been declared for class: {$this->class->getName ()}");
    return null;
  }

  
  public function has_relationship ($name) { return array_key_exists ($name, $this->relationships); }

  public function insert (&$data, $pk=null, $sequence_name=null) {
    $data = $this->process_data ($data);

    $sql = new SQLBuilder ($this->get_fully_qualified_table_name ());
    $sql->insert ($data,$pk,$sequence_name);

    $values = array_values ($data);
    return Connection::instance ()->query (($this->last_sql = $sql->to_s ()),$values);
  }

  public function update (&$data, $where) {
    $data = $this->process_data ($data);

    $sql = new SQLBuilder ($this->get_fully_qualified_table_name ());
    $sql->update ($data)->where ($where);

    $values = $sql->bind_values ();
    return Connection::instance ()->query (($this->last_sql = $sql->to_s ()),$values);
  }

  public function delete ($data) {
    $data = $this->process_data ($data);

    $sql = new SQLBuilder ($this->get_fully_qualified_table_name ());
    $sql->delete ($data);

    $values = $sql->bind_values ();
    return Connection::instance ()->query (($this->last_sql = $sql->to_s ()),$values);
  }

  
  private function add_relationship ($relationship) {
    $this->relationships[$relationship->attribute_name] = $relationship;
  }

  private function get_meta_data () {
    $quote_name = !(Connection::instance () instanceof PgsqlAdapter);

    $table_name = $this->get_fully_qualified_table_name ($quote_name);
    $conn = Connection::instance ();
    $this->columns = Cache::get ("MetaData_" . $table_name, function () use ($conn, $table_name) { return $conn->columns ($table_name); });
  }

  
  private function map_names (&$hash, &$map) {
    $ret = array ();

    foreach ($hash as $name => &$value) {
      if (array_key_exists ($name,$map)) $name = $map[$name];
      $ret[$name] = $value;
    }
    return $ret;
  }

  private function &process_data ($hash) {
    if (!$hash) return $hash;
    $date_class = 'ActiveRecord\\DateTime';

    foreach ($hash as $name => &$value)
      if ($value instanceof $date_class || $value instanceof \DateTime) $hash[$name] = isset ($this->columns[$name]) && $this->columns[$name]->type == Column::DATE ? Connection::instance ()->date_to_string ($value) : Connection::instance ()->datetime_to_string ($value);
      else $hash[$name] = $value;
    return $hash;
  }

  private function set_primary_key () {
    if (($pk = $this->class->getStaticPropertyValue ('pk',null)) || ($pk = $this->class->getStaticPropertyValue ('primary_key',null)))
      $this->pk = is_array ($pk) ? $pk : array ($pk);
    else {
      $this->pk = array ();
      foreach ($this->columns as $c) if ($c->pk) $this->pk[] = $c->inflected_name;
    }
  }

  private function set_table_name () {
    if (($table = $this->class->getStaticPropertyValue ('table',null)) || ($table = $this->class->getStaticPropertyValue ('table_name',null)))
      $this->table = $table;
    else {
      $this->table = Inflector::instance ()->tableize ($this->class->getName ());
      $parts = explode ('\\',$this->table);
      $this->table = $parts[count ($parts) - 1];
    }

    if (($db = $this->class->getStaticPropertyValue ('db',null)) || ($db = $this->class->getStaticPropertyValue ('db_name',null)))
      $this->db_name = $db;
  }

  private function set_cache () {
    if (!Cache::$adapter) return;
    $model_class_name = $this->class->name;
    $this->cache_individual_model = $model_class_name::$cache;
    $this->cache_model_expire = property_exists ($model_class_name, 'cache_expire') && isset ($model_class_name::$cache_expire) ? $model_class_name::$cache_expire : Cache::$options['expire'];
  }

  private function set_sequence_name () {
    if (!Connection::instance ()->supports_sequences ()) return;
    if (!($this->sequence = $this->class->getStaticPropertyValue ('sequence'))) $this->sequence = Connection::instance ()->get_sequence_name ($this->table,$this->pk[0]);
  }

  private function set_associations () {
    require_once __DIR__ . '/Relationship.php';
    $namespace = $this->class->getNamespaceName ();

    foreach ($this->class->getStaticProperties () as $name => $definitions) {
      if (!$definitions)
        continue;

      foreach (wrap_strings_in_arrays ($definitions) as $definition) {
        $relationship = null;
        $definition += array ('namespace' => $namespace);

        switch ($name) {
          case 'has_many':
            $relationship = new HasMany ($definition);
            break;

          case 'has_one':
            $relationship = new HasOne ($definition);
            break;

          case 'belongs_to':
            $relationship = new BelongsTo ($definition);
            break;

          case 'has_and_belongs_to_many':
            $relationship = new HasAndBelongsToMany ($definition);
            break;
        }

        if ($relationship)
          $this->add_relationship ($relationship);
      }
    }
  }

  
  private function set_delegates () {
    $delegates = $this->class->getStaticPropertyValue ('delegate',array ());
    $new = array ();

    if (!array_key_exists ('processed', $delegates))
      $delegates['processed'] = false;

    if (!empty ($delegates) && !$delegates['processed']) {
      foreach ($delegates as &$delegate) {
        if (!is_array ($delegate) || !isset ($delegate['to'])) continue;
        if (!isset ($delegate['prefix'])) $delegate['prefix'] = null;

        $new_delegate = array ('to' => $delegate['to'], 'prefix' => $delegate['prefix'], 'delegate' => array ());

        foreach ($delegate as $name => $value) if (is_numeric ($name)) $new_delegate['delegate'][] = $value;

        array_push ($new, $new_delegate);
      }

      $new['processed'] = true;
      $this->class->setStaticPropertyValue ('delegate', $new);
    }
  }

  private function set_setters_and_getters () {
    $getters = $this->class->getStaticPropertyValue ('getters', array ());
    $setters = $this->class->getStaticPropertyValue ('setters', array ());
    if (!empty ($getters) || !empty ($setters)) trigger_error ('static::$getters and static::$setters are deprecated. Please define your setters and getters by declaring methods in your model prefixed with get_ or set_.', E_USER_DEPRECATED);
  }
}
