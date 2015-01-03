<?php

if (is_file('vendor/autoload.php')) {
  require 'vendor/autoload.php';
}

if (!class_exists('Twig_Loader_Filesystem')) {
  die('Twig not loaded');
}

$loader = new Twig_Loader_Filesystem('/path/to/templates');
$styleguide = new styleguide('src/avalanche.scss');

class styleguide {
  private $code;
  private $file_extensions = array('css', 'scss');
  private $styleguide_definitions = array();

  function __construct($source) {
    $this->code = $this->load_code($source);
    $this->find_styleguide_definitions();
//print $this->code;
  }

  private function find_styleguide_definitions() {
    preg_match_all('#\/\*((?:(?!\*\/).)*)\*\/#s', $this->code, $matches);

    foreach ($matches[1] as $match) {
      if (strpos($match, '@name') !== FALSE) {
        $definition = array();

        $definition['name'] = $this->parse_match('@name', $match);;

        if (strpos($match, '@level') !== FALSE) {
          $definition['level'] = $this->parse_match('@level', $match);
        }

        if (strpos($match, '@description') !== FALSE) {
          $definition['description'] = $this->parse_match('@description', $match);
        }

        if (strpos($match, '@markup') !== FALSE) {
          $definition['markup'] = $this->parse_match('@markup', $match);
        }

        $this->styleguide_definitions[] = $definition;

print_r($definition);
      }
    }
  }

  private function parse_match($label, $match) {
    $match = trim($match);
    if (!preg_match("#$label (.*?)\n#", $match, $detail_match)) {
      preg_match("#$label\n \*   (.*?)(\n \*\n|$)#s", $match, $detail_match);
      $detail_match[1] = str_replace(' *   ', '', $detail_match[1]);
    }
    return $detail_match[1];
  }

  private function load_code($source) {
    $rel_path = dirname($source);
    $code = file_get_contents($source);
    return $this->load_imports($code, $rel_path);
  }

  private function load_imports($code, $rel_path = '') {
    preg_match_all('#@import (.*?);#i', $code, $matches);
    $imports = str_replace(array(' ', '\'', '"'), '', $matches[1]);
    foreach ($imports as $k => $import_path) {
      $full_import_path = $this->get_full_import_path($import_path, $rel_path);
      $import_code = $this->load_code($full_import_path);
      $code = str_replace($matches[0][$k], $import_code, $code);
    }
    return $code;
  }

  private function get_full_import_path($import_path, $rel_path = '') {
    if (is_file($rel_path . '/' . $import_path)) {
      return $rel_path . '/' . $import_path;
    }

    $import_dirname = dirname($import_path);
    $import_basename = basename($import_path);
    foreach ($this->file_extensions as $file_extension) {
      $import_path_extended = $rel_path . '/' . $import_dirname . '/' . $import_basename . '.' . $file_extension;
      if (is_file($import_path_extended)) {
        return $import_path_extended;
      }

      $import_path_extended_scss = $rel_path . '/' . $import_dirname . '/_' . $import_basename . '.' . $file_extension;
      if (is_file($import_path_extended_scss)) {
        return $import_path_extended_scss;
      }
    }

    return FALSE;
  }
}