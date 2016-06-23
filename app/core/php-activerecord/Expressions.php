<?php
namespace ActiveRecord;

class Expressions {
  const ParameterMarker = '?';

  private $expressions;
  private $values = array ();

  public function __construct ($expressions=null) {
    $values = null;

    if (is_array ($expressions)) {
      $glue = func_num_args () > 1 ? func_get_arg (1) : ' AND ';
      list ($expressions,$values) = $this->build_sql_from_hash ($expressions,$glue);
    }

    if ($expressions != '') {
      if (!$values) $values = array_slice (func_get_args (), 1);
      $this->values = $values;
      $this->expressions = $expressions;
    }
  }

  
  public function bind ($parameter_number, $value) {
    if ($parameter_number <= 0)
      throw new ExpressionsException ("Invalid parameter index: $parameter_number");

    $this->values[$parameter_number-1] = $value;
  }
  
  public function bind_values ($values) { $this->values = $values; }
  public function values () { return $this->values; }
  
  public function to_s ($substitute=false, &$options=null) {
    if (!$options) $options = array ();
    
    $values = array_key_exists ('values',$options) ? $options['values'] : $this->values;

    $ret = "";
    $replace = array ();
    $num_values = count ($values);
    $len = strlen ($this->expressions);
    $quotes = 0;

    for ($i=0,$n=strlen ($this->expressions),$j=0; $i<$n; ++$i) {
      $ch = $this->expressions[$i];

      if ($ch == self::ParameterMarker) {
        if ($quotes % 2 == 0) {
          if ($j > $num_values-1)
            throw new ExpressionsException ("No bound parameter for index $j");
          $ch = $this->substitute ($values,$substitute,$i,$j++);
        }
      }
      elseif ($ch == '\'' && $i > 0 && $this->expressions[$i-1] != '\\') ++$quotes;

      $ret .= $ch;
    }
    return $ret;
  }

  private function build_sql_from_hash (&$hash, $glue) {
    $sql = $g = "";

    foreach ($hash as $name => $value) {
      $name = Connection::instance ()->quote_name ($name);

      if (is_array ($value)) $sql .= "$g$name IN(?)";
      elseif (is_null ($value)) $sql .= "$g$name IS ?";
      else $sql .= "$g$name=?";

      $g = $glue;
    }
    return array ($sql,array_values ($hash));
  }

  private function substitute (&$values, $substitute, $pos, $parameter_index) {
    $value = $values[$parameter_index];

    if (is_array ($value)) {
      $value_count = count ($value);

      if ($value_count === 0)
        if ($substitute) return 'NULL';
        else return self::ParameterMarker;

      if ($substitute) {
        $ret = '';
        for ($i=0, $n=$value_count; $i<$n; ++$i) $ret .= ($i > 0 ? ',' : '') . $this->stringify_value ($value[$i]);
        return $ret;
      }
      return join (',',array_fill (0,$value_count,self::ParameterMarker));
    }

    if ($substitute) return $this->stringify_value ($value);

    return $this->expressions[$pos];
  }

  private function stringify_value ($value) {
    if (is_null ($value)) return "NULL";
    return is_string ($value) ? $this->quote_string ($value) : $value;
  }

  private function quote_string ($value) {
    return Connection::instance ()->escape ($value);
    return "'" . str_replace ("'","''",$value) . "'";
  }
}