<?php

if (is_file('../vendor/autoload.php')) {
  require '../vendor/autoload.php';
}

$styleguide = new cultusrego(array(
  'source' => 'demo.css',
  'template_folder' => 'vendor/cultusrego_theme_default/template',
  'twig_cache' => FALSE,
));
$styleguide->render();
