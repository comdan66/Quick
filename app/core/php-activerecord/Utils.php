<?php
namespace ActiveRecord;
use \Closure;

if (!function_exists ('read_file')) {
  function read_file ($file) {
    if (!file_exists ($file)) return false;
    if (function_exists ('file_get_contents')) return file_get_contents ($file);
    if (!$fp = @fopen ($file, 'rb')) return false;

    $data = '';
    flock ($fp, LOCK_SH);
    if (filesize ($file) > 0) $data =& fread ($fp, filesize ($file));
    flock ($fp, LOCK_UN);
    fclose ($fp);

    return $data;
  }
}
if (!function_exists ('delete_files')) {
  function delete_files ($path, $del_dir = false, $level = 0) {
    $path = rtrim ($path, DIRECTORY_SEPARATOR);

    if (!$current_dir = @opendir ($path)) return false;

    while (false !== ($filename = @readdir ($current_dir)))
      if (($filename != '.') && ($filename != '..'))
        if (is_dir ($path . DIRECTORY_SEPARATOR . $filename))
          if (substr ($filename, 0, 1) != '.')
            delete_files ($path . DIRECTORY_SEPARATOR . $filename, $del_dir, $level + 1);
          else;
        else
          @unlink ($path . DIRECTORY_SEPARATOR . $filename);

    @closedir ($current_dir);

    if (($del_dir == true) && ($level > 0)) return @rmdir ($path);

    return true;
  }
}

if (!function_exists ('write_file')) {
  function write_file ($path, $data, $mode = 'wb') {
    if (!$fp = @fopen ($path, $mode)) return false;

    flock($fp, LOCK_EX);
    fwrite($fp, $data);
    flock($fp, LOCK_UN);
    fclose($fp);

    return true;
  }
}
if (!function_exists ('classify')) {
  function classify ($class_name, $singularize=false) {
    if ($singularize) $class_name = Utils::singularize ($class_name);
    $class_name = Inflector::instance ()->camelize ($class_name);
    return ucfirst ($class_name);
  }
}
if (!function_exists ('array_flatten')) {
  function array_flatten (array $array) {
    $i = 0;
    while ($i < count ($array))
      if (is_array ($array[$i])) array_splice ($array, $i, 1, $array[$i]);
      else ++$i;
    return $array;
  }
}
if (!function_exists ('is_hash')) {
  function is_hash (&$array) {
    if (!is_array ($array)) return false;
    $keys = array_keys ($array);
    return @is_string ($keys[0]) ? true : false;
  }
}

if (!function_exists ('denamespace')) {
  function denamespace ($class_name) {
    if (is_object ($class_name)) $class_name = get_class ($class_name);

    if (has_namespace ($class_name)) {
      $parts = explode ('\\', $class_name);
      return end ($parts);
    }
    return $class_name;
  }
}

if (!function_exists ('get_namespaces')) {
  function get_namespaces ($class_name) {
    if (has_namespace ($class_name)) return explode ('\\', $class_name);
    return null;
  }
}

if (!function_exists ('has_namespace')) {
  function has_namespace ($class_name) {
    if (strpos ($class_name, '\\') !== false) return true;
    return false;
  }
}

if (!function_exists ('has_absolute_namespace')) {
  function has_absolute_namespace ($class_name) {
    if (strpos ($class_name, '\\') === 0) return true;
    return false;
  }
}

if (!function_exists ('all_condition')) {
  function all_condition ($needle, array $haystack) {
    foreach ($haystack as $value) if ($value !== $needle) return false;
    return true;
  }
}

if (!function_exists ('collect')) {
  function collect (&$enumerable, $name_or_closure) {
    $ret = array ();

    foreach ($enumerable as $value)
      if (is_string ($name_or_closure)) array_push ($ret, is_array ($value) ? $value[$name_or_closure] : $value->$name_or_closure);
      elseif ($name_or_closure instanceof Closure) array_push ($ret, $name_or_closure ($value));
    return $ret;
  }
}

if (!function_exists ('wrap_strings_in_arrays')) {
  function wrap_strings_in_arrays (&$strings) {
    if (!is_array ($strings)) $strings = array (array ($strings));
    else foreach ($strings as &$str) if (!is_array ($str)) $str = array ($str);
    return $strings;
  }
}

class Utils {
  public static function extract_options ($options) {
    return is_array (end ($options)) ? end ($options) : array ();
  }

  public static function add_condition (&$conditions = array (), $condition, $conjuction = 'AND') {
    if (is_array ($condition)) {
      if (empty ($conditions))
        $conditions = array_flatten ($condition);
      else {
        $conditions[0] .= ' ' . $conjuction . ' ' . array_shift ($condition);
        $conditions[] = array_flatten ($condition);
      }
    }
    elseif (is_string ($condition))
      $conditions[0] .= ' ' . $conjuction . ' ' . $condition;

    return $conditions;
  }

  public static function human_attribute ($attr) {
    $inflector = Inflector::instance ();
    $inflected = $inflector->variablize ($attr);
    $normal = $inflector->uncamelize ($inflected);

    return ucfirst (str_replace ('_', ' ', $normal));
  }


  public static function is_a ($type, $var) {
    switch ($type) { case 'range': if (is_array ($var) && (int)$var[0] < (int)$var[1]) return true; }

    return false;
  }

  public static function is_odd ($number) { return $number & 1; }
  public static function is_blank ($var) { return 0 === strlen ($var); }
  public static function pluralize_if ($count, $string) { return $count == 1 ? $string : self::pluralize ($string); }
  public static function squeeze ($char, $string) { return preg_replace ("/$char+/", $char, $string); }
  public static function add_irregular ($singular, $plural) { self::$irregular[$singular] = $plural; }

  private static $plural = array (
      '/(quiz)$/i'               => "$1zes",
      '/^(ox)$/i'                => "$1en",
      '/([m|l])ouse$/i'          => "$1ice",
      '/(matr|vert|ind)ix|ex$/i' => "$1ices",
      '/(x|ch|ss|sh)$/i'         => "$1es",
      '/([^aeiouy]|qu)y$/i'      => "$1ies",
      '/(hive)$/i'               => "$1s",
      '/(?:([^f])fe|([lr])f)$/i' => "$1$2ves",
      '/(shea|lea|loa|thie)f$/i' => "$1ves",
      '/sis$/i'                  => "ses",
      '/([ti])um$/i'             => "$1a",
      '/(tomat|potat|ech|her|vet)o$/i'=> "$1oes",
      '/(bu)s$/i'                => "$1ses",
      '/(alias)$/i'              => "$1es",
      '/(octop)us$/i'            => "$1i",
      '/(cris|ax|test)is$/i'     => "$1es",
      '/(us)$/i'                 => "$1es",
      '/s$/i'                    => "s",
      '/$/'                      => "s"
    );

  private static $singular = array (
    '/(quiz)zes$/i'             => "$1",
    '/(matr)ices$/i'            => "$1ix",
    '/(vert|ind)ices$/i'        => "$1ex",
    '/^(ox)en$/i'               => "$1",
    '/(alias)es$/i'             => "$1",
    '/(octop|vir)i$/i'          => "$1us",
    '/(cris|ax|test)es$/i'      => "$1is",
    '/(shoe)s$/i'               => "$1",
    '/(o)es$/i'                 => "$1",
    '/(bus)es$/i'               => "$1",
    '/([m|l])ice$/i'            => "$1ouse",
    '/(x|ch|ss|sh)es$/i'        => "$1",
    '/(m)ovies$/i'              => "$1ovie",
    '/(s)eries$/i'              => "$1eries",
    '/([^aeiouy]|qu)ies$/i'     => "$1y",
    '/([lr])ves$/i'             => "$1f",
    '/(tive)s$/i'               => "$1",
    '/(hive)s$/i'               => "$1",
    '/(li|wi|kni)ves$/i'        => "$1fe",
    '/(shea|loa|lea|thie)ves$/i'=> "$1f",
    '/(^analy)ses$/i'           => "$1sis",
    '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i'  => "$1$2sis",
    '/([ti])a$/i'               => "$1um",
    '/(n)ews$/i'                => "$1ews",
    '/(h|bl)ouses$/i'           => "$1ouse",
    '/(corpse)s$/i'             => "$1",
    '/(us)es$/i'                => "$1",
    '/(us|ss)$/i'               => "$1",
    '/s$/i'                     => ""
  );

  private static $irregular = array (
    'move'   => 'moves',
    'foot'   => 'feet',
    'goose'  => 'geese',
    'sex'    => 'sexes',
    'child'  => 'children',
    'man'    => 'men',
    'tooth'  => 'teeth',
    'person' => 'people'
  );

  private static $uncountable = array (
    'sheep',
    'fish',
    'deer',
    'series',
    'species',
    'money',
    'rice',
    'information',
    'equipment'
  );

  public static function pluralize ($string) {
    if (in_array (strtolower ($string), self::$uncountable))
      return $string;

    foreach (self::$irregular as $pattern => $result)
      if (preg_match ($pattern = '/' . $pattern . '$/i', $string))
        return preg_replace ($pattern, $result, $string);

    foreach (self::$plural as $pattern => $result)
      if (preg_match ($pattern, $string))
        return preg_replace ($pattern, $result, $string);

    return $string;
  }

  public static function singularize ($string) {
    if (in_array (strtolower ($string), self::$uncountable))
      return $string;

    foreach (self::$irregular as $result => $pattern)
      if (preg_match ($pattern = '/' . $pattern . '$/i', $string))
        return preg_replace ( $pattern, $result, $string);

    foreach (self::$singular as $pattern => $result)
      if (preg_match ($pattern, $string))
        return preg_replace ($pattern, $result, $string);

    return $string;
  }
}