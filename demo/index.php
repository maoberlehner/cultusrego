<?php

if (is_file('../vendor/autoload.php')) {
  require '../vendor/autoload.php';
}

$styleguide = new cultusrego(array(
  'source' => 'demo.css',
  'twig_cache' => FALSE,
));
$styleguide->render();
