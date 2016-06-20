<?php

/**
 * @author      OA Wu <comdan66@gmail.com>
 * @copyright   Copyright (c) 2016 OA Wu Design
 */

define ('ENV', 'build');

include_once 'libs/functions.php';

echo "\n" . str_repeat ('=', 80) . "\n";

echo ' ' . color ('◎ 執行開始 ◎', 'P') . "\n";
echo str_repeat ('-', 80) . "\n";

// ========================================================================
// ========================================================================
// ========================================================================

echo ' ➜ ' . color ('初始化 S3 工具', 'g');
$s3_config = include_once ('configs/s3_key.php');
include_once 'libs/s3.php';
S3::init ($s3_config['access_key'], $s3_config['secret_key']);
echo ' - ' . color ('初始化成功！', 'C') . "\n";
echo str_repeat ('-', 80) . "\n";

// ========================================================================
// ========================================================================
// ========================================================================

echo ' ➜ ' . color ('列出 S3 上所有檔案', 'g');
$s3_files = S3::getBucket ($s3_config['bucket']);
echo color ('(' . ($c = count ($s3_files)) . ')', 'g') . ' - 100% - ' . color ('取得檔案成功！', 'C') . "\n";
echo str_repeat ('-', 80) . "\n";

// ========================================================================
// ========================================================================
// ========================================================================

$i = 0;
$c = 6;
$local_files = array ();
echo ' ➜ ' . color ('列出即將上傳所有檔案', 'g');

$files = array ();
merge_array_recursive (directory_list ('..'), $files, '..');
$files = array_filter ($files, function ($file) { return in_array (pathinfo ($file, PATHINFO_EXTENSION), array ('html', 'txt')); });
$files = array_map (function ($file) { return array ('path' => $file, 'md5' => md5_file ($file), 'uri' => preg_replace ('/^(\.\.\/)/', '', $file)); }, $files);
echo "\r ➜ " . color ('列出即將上傳所有檔案', 'g') . color ('(' . count ($local_files = array_merge ($local_files, $files)) . ')', 'g') . ' - ' . sprintf ('% 3d%% ', (100 / $c) * ++$i);

$files = array ();
merge_array_recursive (directory_map ('../assets/css'), $files, '../assets/css');
$files = array_filter ($files, function ($file) { return in_array (pathinfo ($file, PATHINFO_EXTENSION), array ('css')); });
$files = array_map (function ($file) { return array ('path' => $file, 'md5' => md5_file ($file), 'uri' => preg_replace ('/^(\.\.\/)/', '', $file)); }, $files);
echo "\r ➜ " . color ('列出即將上傳所有檔案', 'g') . color ('(' . count ($local_files = array_merge ($local_files, $files)) . ')', 'g') . ' - ' . sprintf ('% 3d%% ', (100 / $c) * ++$i);

$files = array ();
merge_array_recursive (directory_map ('../assets/js'), $files, '../assets/js');
$files = array_filter ($files, function ($file) { return in_array (pathinfo ($file, PATHINFO_EXTENSION), array ('js')); });
$files = array_map (function ($file) { return array ('path' => $file, 'md5' => md5_file ($file), 'uri' => preg_replace ('/^(\.\.\/)/', '', $file)); }, $files);
echo "\r ➜ " . color ('列出即將上傳所有檔案', 'g') . color ('(' . count ($local_files = array_merge ($local_files, $files)) . ')', 'g') . ' - ' . sprintf ('% 3d%% ', (100 / $c) * ++$i);

$files = array ();
merge_array_recursive (directory_map ('../assets/font'), $files, '../assets/font');
$files = array_filter ($files, function ($file) { return in_array (pathinfo ($file, PATHINFO_EXTENSION), array ('eot', 'svg', 'ttf', 'woff')); });
$files = array_map (function ($file) { return array ('path' => $file, 'md5' => md5_file ($file), 'uri' => preg_replace ('/^(\.\.\/)/', '', $file)); }, $files);
echo "\r ➜ " . color ('列出即將上傳所有檔案', 'g') . color ('(' . count ($local_files = array_merge ($local_files, $files)) . ')', 'g') . ' - ' . sprintf ('% 3d%% ', (100 / $c) * ++$i);

$files = array ();
merge_array_recursive (directory_map ('../assets/img'), $files, '../assets/img');
$files = array_filter ($files, function ($file) { return in_array (pathinfo ($file, PATHINFO_EXTENSION), array ('png', 'jpg', 'jpeg', 'gif', 'svg')); });
$files = array_map (function ($file) { return array ('path' => $file, 'md5' => md5_file ($file), 'uri' => preg_replace ('/^(\.\.\/)/', '', $file)); }, $files);
echo "\r ➜ " . color ('列出即將上傳所有檔案', 'g') . color ('(' . count ($local_files = array_merge ($local_files, $files)) . ')', 'g') . ' - ' . sprintf ('% 3d%% ', (100 / $c) * ++$i);

$files = array ();
merge_array_recursive (directory_map ('../sitemap'), $files, '../sitemap');
$files = array_filter ($files, function ($file) { return in_array (pathinfo ($file, PATHINFO_EXTENSION), array ('xml')); });
$files = array_map (function ($file) { return array ('path' => $file, 'md5' => md5_file ($file), 'uri' => preg_replace ('/^(\.\.\/)/', '', $file)); }, $files);
echo "\r ➜ " . color ('列出即將上傳所有檔案', 'g') . color ('(' . count ($local_files = array_merge ($local_files, $files)) . ')', 'g') . ' - ' . sprintf ('% 3d%% ', (100 / $c) * 6);
echo '- ' . color ('取出即將上傳所有檔案成功！', 'C') . "\n";
echo str_repeat ('-', 80) . "\n";

// ========================================================================
// ========================================================================
// ========================================================================


echo ' ➜ ' . color ('過濾需要上傳檔案', 'g');
$i = 0;
$c = count ($local_files);
$upload_files = array_filter ($local_files, function ($local_file) use ($s3_files, &$i, $c) {
  foreach ($s3_files as $s3_file)
    if (($s3_file['name'] == $local_file['uri']) && ($s3_file['hash'] == $local_file['md5']))
      return false;
  echo sprintf ("\r" . ' ➜ ' . color ('過濾需要上傳檔案', 'g') . color ('(' . ($i + 1) . ')', 'g') . " - % 3d%% ", ceil ((++$i * 100) / $c));
  return $local_file;
});
echo sprintf ("\r" . ' ➜ ' . color ('過濾需要上傳檔案', 'g') . color ('(' . count ($upload_files) . ')', 'g') . " - % 3d%% ", 100);
echo '- ' . color ('過濾需要上傳檔案成功！', 'C') . "\n";
echo str_repeat ('-', 80) . "\n";

// ========================================================================
// ========================================================================
// ========================================================================

echo sprintf ("\r" . ' ➜ ' . color ('上傳檔案', 'g') . color ('(' . ($c = count ($upload_files)) . ')', 'g') . " - % 3d%% ", $c ? ceil ((++$i * 100) / $c) : 100);
$i = 0;
if (array_filter (array_map (function ($file) use ($s3_config, &$i, $c) {
  echo sprintf ("\r" . ' ➜ ' . color ('上傳檔案', 'g') . color ('(' . $c . ')', 'g') . " - % 3d%% ", ceil ((++$i * 100) / $c));
  return !S3::putFile ($file['path'], $s3_config['bucket'], $file['uri']);
}, $upload_files))) {
  echo '- ' . color ('上傳發生錯誤！', 'r') . "\n";
  echo str_repeat ('=', 80) . "\n";
  return;
}
echo '- ' . color ('上傳成功！', 'C') . "\n";
echo str_repeat ('-', 80) . "\n";

// ========================================================================
// ========================================================================
// ========================================================================

echo ' ➜ ' . color ('過濾需要刪除檔案', 'g');
$i = 0;
$c = count ($s3_files);
$delete_files = array_filter ($s3_files, function ($s3_file) use ($local_files, &$i, $c) {
  foreach ($local_files as $local_file) if ($s3_file['name'] == $local_file['uri']) return false;
  echo sprintf ("\r" . ' ➜ ' . color ('過濾需要刪除檔案', 'g') . color ('(' . ($i + 1) . ')', 'g') . " - % 3d%% ", ceil ((++$i * 100) / $c));
  return true;
});
echo sprintf ("\r" . ' ➜ ' . color ('過濾需要刪除檔案', 'g') . color ('(' . count ($delete_files) . ')', 'g') . " - % 3d%% ", 100);
echo '- ' . color ('過濾需要刪除檔案成功！', 'C') . "\n";
echo str_repeat ('-', 80) . "\n";

// ========================================================================
// ========================================================================
// ========================================================================

echo sprintf ("\r" . ' ➜ ' . color ('刪除 S3 上需要刪除的檔案(' . ($c = count ($delete_files)) . ')', 'g'));
$i = 0;
echo '- ' . (array_filter (array_map (function ($file) use ($s3_config, &$i, $c) {
  echo sprintf ("\r" . ' ➜ ' . color ('刪除 S3 上需要刪除的檔案(' . $c . ')', 'g') . " - % 3d%% ", ceil ((++$i * 100) / $c));
  return !S3::deleteObject ($s3_config['bucket'], $file['name']);
}, $delete_files)) ? color ('刪除 S3 上需要刪除的檔案失敗！', 'r') : color ('刪除 S3 上需要刪除的檔案成功！', 'C')) . "\n";
echo str_repeat ('-', 80) . "\n";

// ========================================================================
// ========================================================================
// ========================================================================

echo ' ' . color ('◎ 執行結束 ◎', 'P') . "\n";
echo str_repeat ('=', 80) . "\n";
echo "\n";
