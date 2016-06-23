<?php
namespace ActiveRecord;

class Model {
  public $errors;
  private $attributes = array ();
  private $__dirty = null;
  private $__readonly = false;
  private $__relationships = array ();
  private $__new_record = true;
  static $db;
  static $table_name;
  static $primary_key;
  static $sequence;
  static $cache = false;
  static $cache_expire;
  static $alias_attribute = array ();
  static $attr_accessible = array ();
  static $attr_protected = array ();
  static $delegate = array ();
  
  public function __construct (array $attributes=array (), $guard_attributes=true, $instantiating_via_find=false, $new_record=true) {
    $this->__new_record = $new_record;
    
    if (!$instantiating_via_find)
      foreach (static::table ()->columns as $name => $meta)
        $this->attributes[$meta->inflected_name] = $meta->default;

    $this->set_attributes_via_mass_assignment ($attributes, $guard_attributes);
    if ($instantiating_via_find)
      $this->__dirty = array ();
  }

  
  public function &__get ($name) {
    if (method_exists ($this, "get_$name")) {
      $name = "get_$name";
      $value = $this->$name ();
      return $value;
    }

    return $this->read_attribute ($name);
  }
  
  public function __set ($name, $value) {
    if (array_key_exists ($name, static::$alias_attribute))
      $name = static::$alias_attribute[$name];
    elseif (method_exists ($this,"set_$name")) {
      $name = "set_$name";
      return $this->$name ($value);
    }

    if (array_key_exists ($name,$this->attributes)) return $this->assign_attribute ($name,$value);
    if ($name == 'id') return $this->assign_attribute ($this->get_primary_key (true),$value);

    foreach (static::$delegate as &$item) {
      if (($delegated_name = $this->is_delegated ($name,$item)))
        return $this->$item['to']->$delegated_name = $value;
    }

    throw new UndefinedPropertyException (get_called_class (),$name);
  }
  
  public function assign_attribute ($name, $value) {
    $table = static::table ();

    if (!is_object ($value))
      if (array_key_exists ($name, $table->columns)) $value = $table->columns[$name]->cast ($value, Connection::instance ());
      else if (!$table->get_column_by_inflected_name ($name)) $value = $col->cast ($value, Connection::instance ());

    if ($value instanceof \DateTime) {
      $date_class = Config::instance ()->get_date_class ();
      if (!($value instanceof $date_class)) $value = $date_class::createFromFormat (Connection::DATETIME_TRANSLATE_FORMAT, $value->format (Connection::DATETIME_TRANSLATE_FORMAT), $value->getTimezone ());
    }

    if ($value instanceof DateTimeInterface)
      $value->attribute_of ($this,$name);

    $this->attributes[$name] = $value;
    $this->flag_dirty ($name);
    return $value;
  }
  
  public function &read_attribute ($name) {
    if (array_key_exists ($name, static::$alias_attribute)) $name = static::$alias_attribute[$name];
    if (array_key_exists ($name,$this->attributes)) return $this->attributes[$name];
    if (array_key_exists ($name,$this->__relationships)) return $this->__relationships[$name];

    $table = static::table ();
    if (($relationship = $table->get_relationship ($name))) {
      $this->__relationships[$name] = $relationship->load ($this);
      return $this->__relationships[$name];
    }

    if ($name == 'id') {
      $pk = $this->get_primary_key (true);
      if (isset ($this->attributes[$pk])) return $this->attributes[$pk];
    }
    
    foreach (static::$delegate as &$item)
      if ($delegated_name = $this->is_delegated ($name,$item)) {
        $to = $item['to'];
        
        if ($this->$to) {
          $val =& $this->$to->__get ($delegated_name);
          return $val;
        } else return null;
      }

    throw new UndefinedPropertyException (get_called_class (),$name);
  }

  public function flag_dirty ($name) {
    if (!$this->__dirty) $this->__dirty = array ();
    $this->__dirty[$name] = true;
  }

  public function dirty_attributes () {
    if (!$this->__dirty) return null;
    $dirty = array_intersect_key ($this->attributes,$this->__dirty);
    return !empty ($dirty) ? $dirty : null;
  }
  
  public function get_real_attribute_name ($name) {
    if (array_key_exists ($name,$this->attributes)) return $name;
    if (array_key_exists ($name,static::$alias_attribute)) return static::$alias_attribute[$name];
    return null;
  }
  
  public function get_validation_rules () {
    require_once 'Validations.php';
    $validator = new Validations ($this);
    return $validator->rules ();
  }

  public function get_values_for ($attributes) {
    $ret = array ();
    foreach ($attributes as $name)
      if (array_key_exists ($name,$this->attributes))
        $ret[$name] = $this->attributes[$name];
    return $ret;
  }
  
  private function is_delegated ($name, &$delegate) {
    if ($delegate['prefix'] != '') $name = substr ($name,strlen ($delegate['prefix'])+1);
    if (is_array ($delegate) && in_array ($name,$delegate['delegate'])) return $name;
    return null;
  }

  public static function create ($attributes, $validate=true, $guard_attributes=true) {
    $class_name = get_called_class ();
    $model = new $class_name ($attributes, $guard_attributes);
    $model->save ($validate);
    return $model;
  }

  public function save ($validate=true) {
    $this->verify_not_readonly ('save');
    return $this->is_new_record () ? $this->insert ($validate) : $this->update ($validate);
  }

  private function insert ($validate=true) {
    $this->verify_not_readonly ('insert');

    if (($validate && !$this->_validate ()))
      return false;

    $table = static::table ();

    if (!($attributes = $this->dirty_attributes ()))
      $attributes = $this->attributes;

    $pk = $this->get_primary_key (true);
    $use_sequence = false;

    if ($table->sequence && !isset ($attributes[$pk])) {  
      if (array_key_exists ($pk,$attributes))
        unset ($attributes[$pk]);

      $table->insert ($attributes,$pk,$table->sequence);
      $use_sequence = true;
    }
    else
      $table->insert ($attributes);
      $column = $table->get_column_by_inflected_name ($pk);

      if ($column->auto_increment || $use_sequence)
        $this->attributes[$pk] = Connection::instance ()->insert_id ($table->sequence);

    $this->__new_record = false;

    $this->update_cache ();
    return true;
  }

  
  private function update ($validate=true) {
    $this->verify_not_readonly ('update');

    if ($validate && !$this->_validate ()) return false;

    if ($this->is_dirty ()) {
      $pk = $this->values_for_pk ();

      if (empty ($pk)) throw new ActiveRecordException ("Cannot update, no primary key defined for: " . get_called_class ());

      $dirty = $this->dirty_attributes ();
      static::table ()->update ($dirty,$pk);
      $this->update_cache ();
    }

    return true;
  }

  protected function update_cache () {
    $table = static::table ();
    if ($table->cache_individual_model) Cache::set ($this->cache_key (), $this, $table->cache_model_expire);
  }

  protected function cache_key () {
    $table = static::table ();
    return $table->cache_key_for_model ($this->values_for_pk ());
  }
  
  public static function delete_all ($options=array ()) {
    $table = static::table ();
    $sql = new SQLBuilder ($table->get_fully_qualified_table_name ());

    $conditions = is_array ($options) ? $options['conditions'] : $options;

    if (is_array ($conditions) && !is_hash ($conditions))
      call_user_func_array (array ($sql, 'delete'), $conditions);
    else
      $sql->delete ($conditions);

    if (isset ($options['limit']))
      $sql->limit ($options['limit']);

    if (isset ($options['order']))
      $sql->order ($options['order']);

    $values = $sql->bind_values ();
    $ret = Connection::instance ()->query (($table->last_sql = $sql->to_s ()), $values);
    return $ret->rowCount ();
  }

  
  public static function update_all ($options = array ()) {
    $table = static::table ();
    $sql = new SQLBuilder ($table->get_fully_qualified_table_name ());

    $sql->update ($options['set']);

    if (isset ($options['conditions']) && ($conditions = $options['conditions'])) {
      if (is_array ($conditions) && !is_hash ($conditions))
        call_user_func_array (array ($sql, 'where'), $conditions);
      else
        $sql->where ($conditions);
    }

    if (isset ($options['limit']))
      $sql->limit ($options['limit']);

    if (isset ($options['order']))
      $sql->order ($options['order']);

    $values = $sql->bind_values ();
    $ret = Connection::instance ()->query (($table->last_sql = $sql->to_s ()), $values);
    return $ret->rowCount ();

  }

  
  public function delete () {
    $this->verify_not_readonly ('delete');

    $pk = $this->values_for_pk ();

    if (empty ($pk))
      throw new ActiveRecordException ("Cannot delete, no primary key defined for: " . get_called_class ());

    static::table ()->delete ($pk);
    $this->remove_from_cache ();

    return true;
  }

  public function remove_from_cache () {
    $table = static::table ();
    if ($table->cache_individual_model) {
      Cache::delete ($this->cache_key ());
    }
  }

  
  public function values_for_pk () {
    return $this->values_for (static::table ()->pk);
  }

  
  public function values_for ($attribute_names) {
    $filter = array ();

    foreach ($attribute_names as $name)
      $filter[$name] = $this->$name;

    return $filter;
  }

  public function set_timestamps () {
    $now = date ('Y-m-d H:i:s');

    if (isset ($this->updated_at))
      $this->updated_at = $now;

    if (isset ($this->created_at) && $this->is_new_record ())
      $this->created_at = $now;
  }

  
  public function update_attributes ($attributes) {
    $this->set_attributes ($attributes);
    return $this->save ();
  }

  
  public function update_attribute ($name, $value) {
    $this->__set ($name, $value);
    return $this->update (false);
  }

  
  public function set_attributes (array $attributes) {
    $this->set_attributes_via_mass_assignment ($attributes, true);
  }

  
  private function set_attributes_via_mass_assignment (array &$attributes, $guard_attributes) {
    $table = static::table ();
    $exceptions = array ();
    $use_attr_accessible = !empty (static::$attr_accessible);
    $use_attr_protected = !empty (static::$attr_protected);

    foreach ($attributes as $name => $value) {
      if (array_key_exists ($name,$table->columns)) {
        $value = $table->columns[$name]->cast ($value, Connection::instance ());
        $name = $table->columns[$name]->inflected_name;
      }

      if ($guard_attributes) {
        if ($use_attr_accessible && !in_array ($name,static::$attr_accessible))
          continue;

        if ($use_attr_protected && in_array ($name,static::$attr_protected))
          continue;
        try {
          $this->$name = $value;
        } catch (UndefinedPropertyException $e) {
          $exceptions[] = $e->getMessage ();
        }
      }
      else {
        if ($name == 'ar_rnum__')
          continue;
        $this->assign_attribute ($name,$value);
      }
    }

    if (!empty ($exceptions))
      throw new UndefinedPropertyException (get_called_class (),$exceptions);
  }

  
  public function set_relationship_from_eager_load (Model $model=null, $name) {
    $table = static::table ();

    if (($rel = $table->get_relationship ($name))) {
      if ($rel->is_poly ()) {
        if (is_null ($model))
          return $this->__relationships[$name] = array ();
        else
          return $this->__relationships[$name][] = $model;
      }
      else
        return $this->__relationships[$name] = $model;
    }

    throw new RelationshipException ("Relationship named $name has not been declared for class: {$table->class->getName ()}");
  }

  
  public function reload () {
    $this->remove_from_cache ();

    $this->__relationships = array ();
    $pk = array_values ($this->get_values_for ($this->get_primary_key ()));

    $this->set_attributes_via_mass_assignment ($this->find ($pk)->attributes, false);
    $this->reset_dirty ();

    return $this;
  }

  public function __clone () {
    $this->__relationships = array ();
    $this->reset_dirty ();
    return $this;
  }

  
  public function reset_dirty () {
    $this->__dirty = null;
  }

  
  static $VALID_OPTIONS = array ('conditions', 'limit', 'offset', 'order', 'select', 'joins', 'include', 'readonly', 'group', 'from', 'having');

  
  public static function __callStatic ($method, $args) {
    $options = static::extract_and_validate_options ($args);
    $create = false;

    if (substr ($method,0,17) == 'find_or_create_by') {
      $attributes = substr ($method,17);
      if (strpos ($attributes,'_or_') !== false)
        throw new ActiveRecordException ("Cannot use OR'd attributes in find_or_create_by");

      $create = true;
      $method = 'find_by' . substr ($method,17);
    }

    if (substr ($method,0,7) === 'find_by') {
      $attributes = substr ($method,8);
      $options['conditions'] = SQLBuilder::create_conditions_from_underscored_string ($attributes,$args,static::$alias_attribute);

      if (!($ret = static::find ('first',$options)) && $create)
        return static::create (SQLBuilder::create_hash_from_underscored_string ($attributes,$args,static::$alias_attribute));

      return $ret;
    }
    elseif (substr ($method,0,11) === 'find_all_by') {
      $options['conditions'] = SQLBuilder::create_conditions_from_underscored_string (substr ($method,12),$args,static::$alias_attribute);
      return static::find ('all',$options);
    }
    elseif (substr ($method,0,8) === 'count_by') {
      $options['conditions'] = SQLBuilder::create_conditions_from_underscored_string (substr ($method,9),$args,static::$alias_attribute);
      return static::count ($options);
    }

    throw new ActiveRecordException ("Call to undefined method: $method");
  }

  
  public function __call ($method, $args) {
    if (preg_match ('/(build|create)_/', $method)) {
      if (!empty ($args))
        $args = $args[0];

      $association_name = str_replace (array ('build_', 'create_'), '', $method);
      $method = str_replace ($association_name, 'association', $method);
      $table = static::table ();

      if (($association = $table->get_relationship ($association_name)) ||
          ($association = $table->get_relationship (($association_name = Utils::pluralize ($association_name))))) {
        $this->$association_name;
        return $association->$method ($this, $args);
      }
    }

    throw new ActiveRecordException ("Call to undefined method: $method");
  }

  
  public static function all () {
    return call_user_func_array ('static::find',array_merge (array ('all'),func_get_args ()));
  }

  
  public static function count () {
    $args = func_get_args ();
    $options = static::extract_and_validate_options ($args);
    $options['select'] = 'COUNT(*)';

    if (!empty ($args) && !is_null ($args[0]) && !empty ($args[0])) {
      if (is_hash ($args[0]))
        $options['conditions'] = $args[0];
      else
        $options['conditions'] = call_user_func_array ('static::pk_conditions',$args);
    }

    $table = static::table ();
    $sql = $table->options_to_sql ($options);
    $values = $sql->get_where_values ();
    return Connection::instance ()->query_and_fetch_one ($sql->to_s (),$values);
  }

  
  public static function exists () {
    return call_user_func_array ('static::count',func_get_args ()) > 0 ? true : false;
  }

  
  public static function first () {
    return call_user_func_array ('static::find',array_merge (array ('first'),func_get_args ()));
  }

  
  public static function last () {
    return call_user_func_array ('static::find',array_merge (array ('last'),func_get_args ()));
  }

  
  public static function find () {
    $class = get_called_class ();

    if (func_num_args () <= 0)
      throw new RecordNotFound ("Couldn't find $class without an ID");

    $args = func_get_args ();
    $options = static::extract_and_validate_options ($args);
    $num_args = count ($args);
    $single = true;

    if ($num_args > 0 && ($args[0] === 'all' || $args[0] === 'first' || $args[0] === 'last')) {
      switch ($args[0]) {
        case 'all':
          $single = false;
          break;

         case 'last':
          if (!array_key_exists ('order',$options))
            $options['order'] = join (' DESC, ',static::table ()->pk) . ' DESC';
          else
            $options['order'] = SQLBuilder::reverse_order ($options['order']);

         case 'first':
          $options['limit'] = 1;
          $options['offset'] = 0;
           break;
      }

      $args = array_slice ($args,1);
      $num_args--;
    }
    elseif (1 === count ($args) && 1 == $num_args)
      $args = $args[0];
    if ($num_args > 0 && !isset ($options['conditions']))
      return static::find_by_pk ($args, $options);

    $options['mapped_names'] = static::$alias_attribute;
    $list = static::table ()->find ($options);

    return $single ? (!empty ($list) ? $list[0] : null) : $list;
  }

  protected static function get_models_from_cache (array $pks) {
    $models = array ();
    $table = static::table ();

    foreach ($pks as $pk) {
      $options =array ('conditions' => static::pk_conditions ($pk));
      $models[] = Cache::get ($table->cache_key_for_model ($pk), function () use ($table, $options) {
        $res = $table->find ($options);
        return $res ? $res[0] : null;
      }, $table->cache_model_expire);
    }
    return array_filter ($models);
  }

  public static function find_by_pk ($values, $options = array ()) {
    if ($values === null)
      throw new RecordNotFound ("Couldn't find ".get_called_class ()." without an ID");

    $table = static::table ();

    if ($table->cache_individual_model) {
      $pks = is_array ($values) ? $values : array ($values);
      $list = static::get_models_from_cache ($pks);
    } else {
      $options['conditions'] = static::pk_conditions ($values);
      $list = $table->find ($options);
    }
    $results = count ($list);

    if ($results != ($expected = count ($values))) {
      $class = get_called_class ();
      if (is_array ($values)) $values = join (',',$values);
      if ($expected == 1) throw new RecordNotFound ("Couldn't find $class with ID=$values");
      throw new RecordNotFound ("Couldn't find all $class with IDs ($values) (found $results, but was looking for $expected)");
    }
    return $expected == 1 ? $list[0] : $list;
  }
  
  public static function find_by_sql ($sql, $values=null) {
    return static::table ()->find_by_sql ($sql, $values, true);
  }

  public static function query ($sql, $values=null) {
    return Connection::instance ()->query ($sql, $values);
  }
  
  public static function is_options_hash ($array, $throw=true) {
    if (is_hash ($array)) {
      $keys = array_keys ($array);
      $diff = array_diff ($keys,self::$VALID_OPTIONS);

      if (!empty ($diff) && $throw) throw new ActiveRecordException ("Unknown key (s): " . join (', ',$diff));

      $intersect = array_intersect ($keys,self::$VALID_OPTIONS);

      if (!empty ($intersect)) return true;
    }
    return false;
  }

  public static function pk_conditions ($args) {
    $table = static::table ();
    $ret = array ($table->pk[0] => $args);
    return $ret;
  }
  
  public static function extract_and_validate_options (array &$array) {
    $options = array ();

    if ($array) {
      $last = &$array[count ($array)-1];

      try {
        if (self::is_options_hash ($last)) {
          array_pop ($array);
          $options = $last;
        }
      }
      catch (ActiveRecordException $e) {
        if (!is_hash ($last)) throw $e;
        $options = array ('conditions' => $last);
      }
    }
    return $options;
  }

  public function to_array () {
    $date_class = 'ActiveRecord\\DateTime';
    foreach ($this->attributes as &$value)
      if ($value instanceof $date_class)
        $value = $value->format (DateTime::$DEFAULT_FORMAT);

    return $this->attributes;
  }

  public static function transaction ($closure) {
    try {
      Connection::instance ()->transaction ();

      if ($closure () === false) {
        Connection::instance ()->rollback ();
        return false;
      }
      else Connection::instance ()->commit ();
    } catch (\Exception $e) {
      Connection::instance ()->rollback ();
      throw $e;
    }
    return true;
  }
  
  public static function reestablish_connection () { return static::table ()->reestablish_connection (); }
  public static function table () { return Table::load (get_called_class ()); }
  public static function table_name () { return static::table ()->table; }

  public function __wakeup () { static::table (); }
  public function __isset ($attribute_name) { return array_key_exists ($attribute_name,$this->attributes) || array_key_exists ($attribute_name,static::$alias_attribute); }

  public function is_dirty () { return empty ($this->__dirty) ? false : true; }
  public function is_valid () { return $this->_validate (); }
  public function is_invalid () { return !$this->_validate (); }
  public function readonly ($readonly=true) { $this->__readonly = $readonly; }
  public function is_readonly () { return $this->__readonly; }
  public function is_new_record () { return $this->__new_record; }
  public function attribute_is_dirty ($attribute) { return $this->__dirty && isset ($this->__dirty[$attribute]) && array_key_exists ($attribute, $this->attributes); }
  public function attributes () { return $this->attributes; }
  public function get_primary_key ($first=false) { $pk = static::table ()->pk; return $first ? $pk[0] : $pk; }

  private function verify_not_readonly ($method_name) { if ($this->is_readonly ()) throw new ReadOnlyException (get_class ($this), $method_name); }
  private function _validate () { return true; }
}
