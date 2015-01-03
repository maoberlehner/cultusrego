<?php

$styleguide = new styleguide(array(
  'source' => array('avalanche/src/avalanche.scss'),
));
$styleguide->render();

class styleguide {
  public $source;
  public $title = 'cultusrego Styleguide';
  public $description = 'PHP Styleguide Generator';
  public $template_folder = 'templates';
  public $section_htags = array(
    1 => 'h2',
    2 => 'h3',
    3 => 'h4',
    4 => 'h5',
    5 => 'h6',
  );

  private $code = '';
  private $file_extensions = array('css', 'less', 'scss');
  private $sections = array();

  function __construct() {
    $arguments = func_get_args();
    if (!empty($arguments)) {
      foreach ($arguments[0] as $key => $property) {
        if (property_exists($this, $key)) {
          $this->{$key} = $property;
        }
      }
    }

    if (is_file('vendor/autoload.php')) {
      require 'vendor/autoload.php';
    }

    if (!class_exists('Twig_Loader_Filesystem')) {
      die('Twig not loaded');
    }

    $this->load_code($this->source);
    $this->find_sections();
//print $this->code;
  }

  public function render() {
    $twig_loader = new Twig_Loader_Filesystem($this->template_folder);
    $twig = new Twig_Environment($twig_loader, array(
      'cache' => $this->template_folder . '/twig_cache',
    ));
    print $twig->render('index.html', array(
      'title' => $this->title,
      'description' => $this->description,
      'sections' => $this->sections,
    ));
  }

  private function find_sections() {
    preg_match_all('#\/\*((?:(?!\*\/).)*)\*\/#s', $this->code, $matches);

    foreach ($matches[1] as $match) {
      if (strpos($match, '@name') !== FALSE) {
        $section = array();

        $section['name'] = $this->parse_match('@name', $match);
        $section['htag'] = 'h2';

        if (strpos($match, '@level') !== FALSE) {
          $section['level'] = $this->parse_match('@level', $match);
          $level_depth_arr = array_filter(explode('.', $section['level']));
          $level_depth = count($level_depth_arr);
          $section['htag'] = 'h6';
          if (isset($this->section_htags[$level_depth])) {
            $section['htag'] = $this->section_htags[$level_depth];
          }
        }

        if (strpos($match, '@description') !== FALSE) {
          $section['description'] = $this->parse_match('@description', $match);
        }

        if (strpos($match, '@markup') !== FALSE) {
          $section['markup'] = $this->parse_match('@markup', $match);
        }

        $this->sections[] = $section;

//print_r($section);
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
    $source = is_array($source) ? $source : array($source);
    foreach ($source as $source_file) {
      $rel_path = dirname($source_file);
      $code = file_get_contents($source_file);
      $this->code .= $this->load_imports($code, $rel_path);
    }
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

print rand(1, 1000);