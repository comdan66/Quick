<?php

/**
 * @author      OA Wu <comdan66@gmail.com>
 * @copyright   Copyright (c) 2016 OA Wu Design
 */

if (!function_exists ('array_2d_to_1d')) {
  function array_2d_to_1d ($array) {
    $messages = array ();
    foreach ($array as $key => $value)
      if (is_array ($value)) $messages = array_merge ($messages, $value);
      else array_push ($messages, $value);
    return $messages;
  }
}

if (!function_exists ('column_array')) {
  function column_array ($objects, $key) {
    return array_map (function ($object) use ($key) {
      return !is_array ($object) ? is_object ($object) ? $object->$key : $object : $object[$key];
    }, $objects);
  }
}

if (!function_exists ('read_file')) {
  function read_file ($file) {
    if (!file_exists ($file)) return false;
    if (function_exists ('file_get_contents')) return file_get_contents ($file);
    if (!$fp = @fopen ($file, 'rb')) return false;

    $data = '';
    flock ($fp, LOCK_SH);
    if (filesize ($file) > 0) $data =& fread ($fp, filesize ($file));
    flock($fp, LOCK_UN);
    fclose($fp);

    return $data;
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
if (!function_exists ('write_asset')) {
  function write_asset ($temp, $type) {
    write_file ($path = (ASSET . $type . DIRECTORY_SEPARATOR . ($name = md5 ($temp) . '.' . $type)), "\xEF\xBB\xBF". $temp);

    $oldmask = umask (0);
    @mkdir (dirname ($path), 0777, true);
    umask ($oldmask);

    return '/' . ASSET_NAME . $type . '/' . $name;
  }
}
if (!function_exists ('merge_asset')) {
  function merge_asset (&$list, &$temp, $type) {
    if ($temp) array_push ($list, write_asset ($temp, $type));
    $temp = '';
    return true;
  }
}

if (!function_exists ('oasort')) {
  function oasort ($n, $b = true) {
    if ($n == 0) return array ();
    if ($n == 1) return array (1);
    if ($n == 2) return array (2);
    if ($n == 3) return array (3);
    if (!($n % 3) && ($n / 3) < 4) return array_merge (array (3), oasort ($n - 3));
    $s = $b ? 2 : 3;
    $v = $n - $s;
    return array_merge (array ($s), oasort ($v, !$b));
  }
}

if (!function_exists ('base_url')) {
  function base_url () {
    $uri = array_filter (func_get_args ());
    return '/' . (defined('ENV') ? '' : 'controllers/') . implode ('/', $uri);
  }
}
if (!function_exists ('img_url')) {
  function img_url () {
    $uri = array_filter (func_get_args ());
    return '/' . 'assets/img' . implode ('/', $uri);
  }
}

if (!function_exists ('oa_meta')){
  function oa_meta ($attributes = array ()) {
    return $attributes ? '<meta ' . implode (' ', array_map (function ($attribute, $value) { return $attribute . '="' . $value . '"'; }, array_keys ($attributes), $attributes)) . ' />' : '';
  }
}

if (!function_exists ('remove_ckedit_tag')) {
  function remove_ckedit_tag ($text) {
    return preg_replace ("/ +/", " ", preg_replace ("/&#?[a-z0-9]+;/i", "", str_replace ('▲', '', trim (strip_tags ($text)))));
  }
}
if (!function_exists ('merge_js_css')) {
  function merge_js_css ($js_list, $css_list) {
    if (!defined ('ENV')) {
      $js_list = array_map (function ($t) { return $t['url']; }, $js_list);
      $css_list = array_map (function ($t) { return $t['url']; }, $css_list);
    } else {
      $temp = '';
      $bom = pack ('H*','EFBBBF');
      $js_list = $css_list = array ();

      foreach ($this->js_list as $js) 
        if ($js['merge']) $temp .= preg_replace("/^$bom/", '', read_file (FCPATH . $js['url'])) . "\n";
        else merge_asset ($js_list, $temp, 'js') && array_push ($js_list, $js['url']);
      
      merge_asset ($js_list, $temp, 'js');

      foreach ($this->css_list as $css)
        if ($css['merge']) $temp .= preg_replace("/^$bom/", '', read_file (FCPATH . $css['url'])) . "\n";
        else merge_asset ($css_list, $temp, 'css') && array_push ($css_list, $css['url']);
      
      merge_asset ($css_list, $temp, 'css');
    }
    return array (
        'js' => $js_list,
        'css' => $css_list,
      );
  }
}