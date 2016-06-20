<?php

/**
 * @author      OA Wu <comdan66@gmail.com>
 * @copyright   Copyright (c) 2016 OA Wu Design
 */

include_once 'defines.php';
include_once 'functions.php';

Class Controller {
  // protected
  private $file = null;
  private $vars = array ();
  private $js_list = array ();
  private $css_list = array ();
  private $metas = array ();
  private $json_ld = array ();
  private $frame = '';

  public function __construct ($frame = '_frame') {
    if (!(($trace = debug_backtrace (DEBUG_BACKTRACE_PROVIDE_OBJECT)) && isset ($trace[1]['file']) && ($this->file = pathinfo (pathinfo ($trace[1]['file'], PATHINFO_BASENAME), PATHINFO_FILENAME))))
      exit ('debug_backtrace error!');

    $this->frame = file_exists (VIEW . ($frame = trim ($frame, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) . CONTENT_NAME . EXT) ? $frame : '';
         
    $this->add_meta (array ('http-equiv' => 'Content-Language', 'content' => 'zh-tw'))
         ->add_meta (array ('http-equiv' => 'Content-type', 'content' => 'text/html; charset=utf-8'))
         ->add_meta (array ('name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui'))
        ;
  }

  public static function load ($frame = '_frame') {
    return new self ($frame);
  }
  public function set_json_ld ($json_ld) {
    $this->json_ld = $json_ld;
    return $this;
  }
  public function add_meta ($attributes) {
    if (isset ($attributes['name']))
      $this->metas = array_filter ($this->metas, function ($meta) use ($attributes) { return !isset ($meta['name']) || ($meta['name'] != $attributes['name']);});

    if (isset ($attributes['property']) && !in_array($attributes['property'], array ('article:author', 'article:tag', 'og:see_also')))
      $this->metas = array_filter ($this->metas, function ($meta) use ($attributes) { return !isset ($meta['property']) || ($meta['property'] != $attributes['property']) || isset ($meta['tag']) && ($meta['tag'] != $attributes['tag']);});

    array_push ($this->metas, $attributes);
    return $this;
  }
  public function add_js ($url, $merge = true) {
    if (!$url) return $this;
    if (preg_match ('/^https?:\/\//', $url)) $merge = false;
    array_push ($this->js_list, array ('url' => $url, 'merge' => $merge));
    return $this;
  }
  public function add_css ($url, $merge = true) {
    if (!$url) return $this;
    array_push ($this->css_list, array ('url' => $url, 'merge' => $merge));
    return $this;
  }
  public function add ($key, $value) {
    $this->vars[$key] = $value;
    return $this;
  }

  public function view ($setting) {
    if (!file_exists ($view_path = VIEW . $this->file . DIRECTORY_SEPARATOR . CONTENT_NAME . EXT))
      exit ('No view file');

    if ($return = $setting ($this))
      foreach ($return as $key => $value)
        $this->add ($key, $value);

    $this->add_js (file_exists (VIEW . ($temp = $this->file . DIRECTORY_SEPARATOR . CONTENT_NAME) . '.js') ? VIEW_NAME . $temp . '.js' : null);
    $this->add_css (file_exists (VIEW . $temp . '.css') ? VIEW_NAME . $temp . '.css' : null);

    if ($this->frame)
      $this->add_js (file_exists (VIEW . $this->frame . CONTENT_NAME . '.js') ? VIEW_NAME . $this->frame . CONTENT_NAME . '.js' : null)
           ->add_css (file_exists (VIEW . $this->frame . CONTENT_NAME . '.css') ? VIEW_NAME . $this->frame . CONTENT_NAME . '.css' : null);

    $js_css = merge_js_css ($this->js_list, $this->css_list);
    
    $return = $this->add ('_f_metas', $this->metas)
                   ->add ('_f_js_css', $js_css)
                   ->add ('_f_json_ld', $this->json_ld)
                   ->load_view ($view_path, $this->vars);

    if (!$this->frame) return $return;
    $return = $this->add ('_f_content', $return)
                   ->load_view (VIEW . $this->frame . CONTENT_NAME . EXT, $this->vars);

    return $return;
  }
  private function load_view ($__o__p__ = '', $__o__d__ = array ()) {
    if (!$__o__p__) return '';

    extract ($__o__d__);
    ob_start ();
    if (((bool)@ini_get ('short_open_tag') === FALSE) && (false == TRUE)) echo eval ('?>'.preg_replace ("/;*\s*\?>/", "; ?>", str_replace ('<?=', '<?php echo ', file_get_contents ($__o__p__))));
    else include $__o__p__;
    $buffer = ob_get_contents ();
    @ob_end_clean ();

    return preg_replace ('/{<{<{([\n| ])/i', '<?php$1', $buffer);
  }
}