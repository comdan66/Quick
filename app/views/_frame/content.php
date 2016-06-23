<!DOCTYPE html>
<html lang="tw">
  <head>
<?php echo implode ('', array_map('oa_meta', $_f_metas)); ?>
    <link rel="canonical" href="" />
    <link rel="alternate" href="" hreflang="zh-Hant" />

    <title><?php echo isset ($title) && is_string ($title) && $title ? $title : '';?></title>

<?php if (isset ($_f_js_css['css']) && $_f_js_css['css'])
        foreach ($_f_js_css['css'] as $css) { ?>
          <link href="<?php echo $css;?>" rel="stylesheet" type="text/css" />
  <?php }
      if (isset ($_f_js_css['js']) && $_f_js_css['js'])
        foreach ($_f_js_css['js'] as $js) { ?>
          <script src="<?php echo $js;?>" language="javascript" type="text/javascript" ></script>
  <?php } 
      if ($_f_json_ld) { ?>
        <script type="application/ld+json">
    <?php echo json_encode ($_f_json_ld, defined ('ENV') ? JSON_UNESCAPED_SLASHES : JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);?>
        </script>
<?php }?>

  </head>
  <body lang="zh-tw">
    <?php echo $_f_content;?>
  </body>
</html>
