<?php
namespace ActiveRecord;

interface DateTimeInterface {
  public function attribute_of ($model, $attribute_name);
  public function format ($format=null);
  public static function createFromFormat ($format, $time, $tz = null);
}
