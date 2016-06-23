<?php
namespace ActiveRecord;

interface InterfaceRelationship {
  public function __construct ($options=array ());
  public function build_association (Model $model, $attributes=array (), $guard_attributes=true);
  public function create_association (Model $model, $attributes=array (), $guard_attributes=true);
}

abstract class AbstractRelationship implements InterfaceRelationship {
  public $attribute_name;
  public $class_name;
  public $foreign_key = array ();
  protected $options = array ();
  protected $poly_relationship = false;
  static protected $valid_association_options = array ('class_name', 'class', 'foreign_key', 'conditions', 'select', 'readonly', 'namespace');
  
  public function __construct ($options=array ()) {
    $this->attribute_name = $options[0];
    $this->options = $this->merge_association_options ($options);

    $relationship = strtolower (denamespace (get_called_class ()));

    if ($relationship === 'hasmany' || $relationship === 'hasandbelongstomany')
      $this->poly_relationship = true;

    if (isset ($this->options['conditions']) && !is_array ($this->options['conditions']))
      $this->options['conditions'] = array ($this->options['conditions']);

    if (isset ($this->options['class'])) $this->set_class_name ($this->options['class']);
    elseif (isset ($this->options['class_name'])) $this->set_class_name ($this->options['class_name']);

    $this->attribute_name = strtolower (Inflector::instance ()->variablize ($this->attribute_name));

    if (!$this->foreign_key && isset ($this->options['foreign_key']))
      $this->foreign_key = is_array ($this->options['foreign_key']) ? $this->options['foreign_key'] : array ($this->options['foreign_key']);
  }

  protected function get_table () { return Table::load ($this->class_name); }
  public function is_poly () { return $this->poly_relationship; }

  protected function query_and_attach_related_models_eagerly (Table $table, $models, $attributes, $includes=array (), $query_keys=array (), $model_values_keys=array ()) {
    $values = array ();
    $options = $this->options;
    $inflector = Inflector::instance ();
    $query_key = $query_keys[0];
    $model_values_key = $model_values_keys[0];

    foreach ($attributes as $column => $value)
      $values[] = $value[$inflector->variablize ($model_values_key)];

    $values = array_unique ($values);

    $values = array ($values);
    $conditions = SQLBuilder::create_conditions_from_underscored_string ($table->conn,$query_key,$values);

    if (isset ($options['conditions']) && strlen ($options['conditions'][0]) > 1) Utils::add_condition ($options['conditions'], $conditions);
    else $options['conditions'] = $conditions;

    if (!empty ($includes)) $options['include'] = $includes;

    if (!empty ($options['through'])) {
      $pk = $this->primary_key;
      $fk = $this->foreign_key;

      $this->set_keys ($this->get_table ()->class->getName (), true);

      if (!isset ($options['class_name'])) {
        $class = classify ($options['through'], true);
        if (isset ($this->options['namespace']) && !class_exists ($class))
          $class = $this->options['namespace'].'\\'.$class;

        $through_table = $class::table ();
      } else {
        $class = $options['class_name'];
        $relation = $class::table ()->get_relationship ($options['through']);
        $through_table = $relation->get_table ();
      }
      $options['joins'] = $this->construct_inner_join_sql ($through_table, true);

      $query_key = $this->primary_key[0];
      $this->primary_key = $pk;
      $this->foreign_key = $fk;
    }

    $options = $this->unset_non_finder_options ($options);

    $class = $this->class_name;

    $related_models = $class::find ('all', $options);
    $used_models = array ();
    $model_values_key = $inflector->variablize ($model_values_key);
    $query_key = $inflector->variablize ($query_key);

    foreach ($models as $model) {
      $matches = 0;
      $key_to_match = $model->$model_values_key;

      foreach ($related_models as $related) {
        if ($related->$query_key == $key_to_match) {
          $hash = spl_object_hash ($related);

          if (in_array ($hash, $used_models))
            $model->set_relationship_from_eager_load (clone ($related), $this->attribute_name);
          else
            $model->set_relationship_from_eager_load ($related, $this->attribute_name);

          $used_models[] = $hash;
          $matches++;
        }
      }

      if (0 === $matches)
        $model->set_relationship_from_eager_load (null, $this->attribute_name);
    }
  }

  
  public function build_association (Model $model, $attributes=array (), $guard_attributes=true) {
    $class_name = $this->class_name;
    return new $class_name ($attributes, $guard_attributes);
  }

  
  public function create_association (Model $model, $attributes=array (), $guard_attributes=true) {
    $class_name = $this->class_name;
    $new_record = $class_name::create ($attributes, true, $guard_attributes);
    return $this->append_record_to_associate ($model, $new_record);
  }

  protected function append_record_to_associate (Model $associate, Model $record) {
    $association =& $associate->{$this->attribute_name};

    if ($this->poly_relationship)
      $association[] = $record;
    else
      $association = $record;

    return $record;
  }

  protected function merge_association_options ($options) {
    $available_options = array_merge (self::$valid_association_options,static::$valid_association_options);
    $valid_options = array_intersect_key (array_flip ($available_options),$options);

    foreach ($valid_options as $option => $v)
      $valid_options[$option] = $options[$option];

    return $valid_options;
  }

  protected function unset_non_finder_options ($options) {
    foreach (array_keys ($options) as $option) {
      if (!in_array ($option, Model::$VALID_OPTIONS))
        unset ($options[$option]);
    }
    return $options;
  }

  
  protected function set_inferred_class_name () {
    $singularize = ($this instanceOf HasMany ? true : false);
    $this->set_class_name (classify ($this->attribute_name, $singularize));
  }

  protected function set_class_name ($class_name) {
    if (!has_absolute_namespace ($class_name) && isset ($this->options['namespace'])) {
      $class_name = $this->options['namespace'].'\\'.$class_name;
    }
    
    $reflection = Reflections::instance ()->add ($class_name)->get ($class_name);

    if (!$reflection->isSubClassOf ('ActiveRecord\\Model'))
      throw new RelationshipException ("'$class_name' must extend from ActiveRecord\\Model");

    $this->class_name = $class_name;
  }

  protected function create_conditions_from_keys (Model $model, $condition_keys=array (), $value_keys=array ()) {
    $condition_string = implode ('_and_', $condition_keys);
    $condition_values = array_values ($model->get_values_for ($value_keys));
    if (all_condition (null,$condition_values))
      return null;

    $conditions = SQLBuilder::create_conditions_from_underscored_string ($condition_string,$condition_values);

    # DO NOT CHANGE THE NEXT TWO LINES. add_condition operates on a reference and will screw options array up
    if (isset ($this->options['conditions']))
      $options_conditions = $this->options['conditions'];
    else
      $options_conditions = array ();

    return Utils::add_condition ($options_conditions, $conditions);
  }

  
  public function construct_inner_join_sql (Table $from_table, $using_through=false, $alias=null) {
    if ($using_through) {
      $join_table = $from_table;
      $join_table_name = $from_table->get_fully_qualified_table_name ();
      $from_table_name = Table::load ($this->class_name)->get_fully_qualified_table_name ();
     }
    else {
      $join_table = Table::load ($this->class_name);
      $join_table_name = $join_table->get_fully_qualified_table_name ();
      $from_table_name = $from_table->get_fully_qualified_table_name ();
    }
    if ($this instanceof HasMany || $this instanceof HasOne) {
      $this->set_keys ($from_table->class->getName ());

      if ($using_through) {
        $foreign_key = $this->primary_key[0];
        $join_primary_key = $this->foreign_key[0];
      }
      else {
        $join_primary_key = $this->foreign_key[0];
        $foreign_key = $this->primary_key[0];
      }
    }
    else {
      $foreign_key = $this->foreign_key[0];
      $join_primary_key = $this->primary_key[0];
    }

    if (!is_null ($alias)) {
      $aliased_join_table_name = $alias = $this->get_table ()->conn->quote_name ($alias);
      $alias .= ' ';
    }
    else
      $aliased_join_table_name = $join_table_name;

    return "INNER JOIN $join_table_name {$alias}ON($from_table_name.$foreign_key = $aliased_join_table_name.$join_primary_key)";
  }
  
  abstract function load (Model $model);
}


class HasMany extends AbstractRelationship {
  static protected $valid_association_options = array ('primary_key', 'order', 'group', 'having', 'limit', 'offset', 'through', 'source');
  protected $primary_key;
  private $has_one = false;
  private $through;
  
  public function __construct ($options=array ()) {
    parent::__construct ($options);

    if (isset ($this->options['through'])) {
      $this->through = $this->options['through'];

      if (isset ($this->options['source']))
        $this->set_class_name ($this->options['source']);
    }

    if (!$this->primary_key && isset ($this->options['primary_key']))
      $this->primary_key = is_array ($this->options['primary_key']) ? $this->options['primary_key'] : array ($this->options['primary_key']);

    if (!$this->class_name)
      $this->set_inferred_class_name ();
  }

  protected function set_keys ($model_class_name, $override=false) {
    if (!$this->foreign_key || $override)
      $this->foreign_key = array (Inflector::instance ()->keyify ($model_class_name));

    if (!$this->primary_key || $override)
      $this->primary_key = Table::load ($model_class_name)->pk;
  }

  public function load (Model $model) {
    $class_name = $this->class_name;
    $this->set_keys (get_class ($model));
    if (!isset ($this->initialized)) {
      if ($this->through) {
        if (!($through_relationship = $this->get_table ()->get_relationship ($this->through)))
          throw new HasManyThroughAssociationException ("Could not find the association $this->through in model " . get_class ($model));

        if (!($through_relationship instanceof HasMany) && !($through_relationship instanceof BelongsTo))
          throw new HasManyThroughAssociationException ('has_many through can only use a belongs_to or has_many association');
        $pk = $this->primary_key;
        $fk = $this->foreign_key;

        $this->set_keys ($this->get_table ()->class->getName (), true);
        
        $class = $this->class_name;
        $relation = $class::table ()->get_relationship ($this->through);
        $through_table = $relation->get_table ();
        $this->options['joins'] = $this->construct_inner_join_sql ($through_table, true);
        $this->primary_key = $pk;
        $this->foreign_key = $fk;
      }

      $this->initialized = true;
    }

    if (!($conditions = $this->create_conditions_from_keys ($model, $this->foreign_key, $this->primary_key)))
      return null;

    $options = $this->unset_non_finder_options ($this->options);
    $options['conditions'] = $conditions;
    return $class_name::find ($this->poly_relationship ? 'all' : 'first',$options);
  }

  
  private function get_foreign_key_for_new_association (Model $model) {
    $this->set_keys ($model);
    $primary_key = Inflector::instance ()->variablize ($this->foreign_key[0]);

    return array (
      $primary_key => $model->id,
    );
  }

  private function inject_foreign_key_for_new_association (Model $model, &$attributes) {
    $primary_key = $this->get_foreign_key_for_new_association ($model);

    if (!isset ($attributes[key ($primary_key)]))
      $attributes[key ($primary_key)] = current ($primary_key);

    return $attributes;
  }

  public function build_association (Model $model, $attributes=array (), $guard_attributes=true) {
    $relationship_attributes = $this->get_foreign_key_for_new_association ($model);

    if ($guard_attributes) {
      $record = parent::build_association ($model, $relationship_attributes, false);
      $record->set_attributes ($attributes);
    } else {
      $attributes = array_merge ($relationship_attributes, $attributes);
      $record = parent::build_association ($model, $attributes, $guard_attributes);
    }

    return $record;
  }

  public function create_association (Model $model, $attributes=array (), $guard_attributes=true) {
    $relationship_attributes = $this->get_foreign_key_for_new_association ($model);

    if ($guard_attributes) {
      $record = parent::build_association ($model, $relationship_attributes, false);
      $record->set_attributes ($attributes);
      $record->save ();
    } else {
      $attributes = array_merge ($relationship_attributes, $attributes);
      $record = parent::create_association ($model, $attributes, $guard_attributes);
    }

    return $record;
  }

  public function load_eagerly ($models=array (), $attributes=array (), $includes, Table $table) {
    $this->set_keys ($table->class->name);
    $this->query_and_attach_related_models_eagerly ($table,$models,$attributes,$includes,$this->foreign_key, $table->pk);
  }
}


class HasOne extends HasMany {
}

class HasAndBelongsToMany extends AbstractRelationship {
  public function __construct ($options=array ()) {
    
  }
  public function load (Model $model) {
  }
}

class BelongsTo extends AbstractRelationship {
  public function __construct ($options=array ()) {
    parent::__construct ($options);

    if (!$this->class_name)
      $this->set_inferred_class_name ();
    if (!$this->foreign_key)
      $this->foreign_key = array (Inflector::instance ()->keyify ($this->class_name));
  }

  public function __get ($name) {
    if ($name === 'primary_key' && !isset ($this->primary_key)) {
      $this->primary_key = array (Table::load ($this->class_name)->pk[0]);
    }

    return $this->$name;
  }

  public function load (Model $model) {
    $keys = array ();
    $inflector = Inflector::instance ();

    foreach ($this->foreign_key as $key)
      $keys[] = $inflector->variablize ($key);

    if (!($conditions = $this->create_conditions_from_keys ($model, $this->primary_key, $keys)))
      return null;

    $options = $this->unset_non_finder_options ($this->options);
    $options['conditions'] = $conditions;
    $class = $this->class_name;
    return $class::first ($options);
  }

  public function load_eagerly ($models=array (), $attributes, $includes, Table $table) {
    $this->query_and_attach_related_models_eagerly ($table,$models,$attributes,$includes, $this->primary_key,$this->foreign_key);
  }
}