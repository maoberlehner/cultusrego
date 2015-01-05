<?php

class styleguide {
  public $source;
  public $title = 'cultusrego Styleguide';
  public $description = 'PHP Styleguide Generator';
  public $template_folder = 'templates';
  public $twig_cache = 'templates/twig_cache';
  public $section_htags = array(
    1 => 'h2',
    2 => 'h3',
    3 => 'h4',
    4 => 'h5',
    5 => 'h6',
  );

  private $code = '';
  private $sections = array();
  private $elements = array(
    'name',
    'level',
    'description',
    'markup',
    'colors',
  );

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
  }

  public function render() {
    $twig_loader = new Twig_Loader_Filesystem($this->template_folder);
    $twig = new Twig_Environment($twig_loader, array(
      'cache' => $this->twig_cache,
    ));
    print $twig->render('index.html', array(
      'title' => $this->title,
      'description' => $this->description,
      'source' => $this->source,
      'sections' => $this->sections,
    ));
  }

  private function find_sections() {
    $sections = array();
    $last_section = FALSE;
    preg_match_all('#\/\*((?:(?!\*\/).)*)\*\/#s', $this->code, $matches);

    foreach ($matches[1] as $match) {
      $section = array();
      $elements = $this->parse_match($match);

      if (isset($elements['name']) && isset($elements['level'])) {
        $elements['htag'] = 'h6';

        $level_depth_arr = array_filter(explode('.', $elements['level']));
        $level_depth = count($level_depth_arr);
        if (isset($this->section_htags[$level_depth])) {
          $elements['htag'] = $this->section_htags[$level_depth];
        }

        $sections[$elements['level']] = $elements;
        $last_section = $elements['level'];
      }
      else if (!empty($elements)) {
        foreach ($elements as $element_label => $element_value) {
          $sections[$last_section][$element_label] = isset($sections[$last_section][$element_label])
            ? $sections[$last_section][$element_label] . "\n" . $element_value
            : $element_value;
        }
      }
    }
    ksort($sections);
    $this->build_section_tree($sections);
  }

  private function build_section_tree($sections) {
    $level_tree = array();

    foreach ($sections as $level => $section) {
      $level_depth_arr = array_filter(explode('.', $level));
      $level_depth = count($level_depth_arr);
      array_pop($level_depth_arr);
      $level_parent = $level_depth > 1 ? implode('.', $level_depth_arr) . '.' : $level;
      $level_tree[$level_depth][$level_parent][$level] = $section;
    }

    foreach ($level_tree[1] as $section_level => $section) {
      $section[$section_level]['sub'] = $this->build_section_sub_tree($level_tree, 2, $section_level);
      $this->sections += $section;
    }
  }

  private function build_section_sub_tree($main_tree, $tree_level, $section_level) {
    $sub_sections = array();
    if (isset($main_tree[$tree_level]) && isset($main_tree[$tree_level][$section_level])) {
      $sub_sections = $main_tree[$tree_level][$section_level];
      $sub_tree_level = $tree_level + 1;
      foreach ($sub_sections as $sub_section_level => $sub_section) {
        $sub_sections[$sub_section_level]['sub'] = $this->build_section_sub_tree($main_tree, $sub_tree_level, $sub_section_level);
      }
    }
    return $sub_sections;
  }

  private function parse_match($match) {
    $match = trim($match);
    $element_values = array();
    foreach ($this->elements as $element_label) {
      if (strpos($match, '@' . $element_label) !== FALSE) {
        if ($element_label == 'markup') {
          $element_values['markup_language'] = 'markup';
          if (preg_match("#\@markup \[(.*?)\]\n#s", $match, $markup_language_match)) {
            $element_values['markup_language'] = $markup_language_match[1];
            $match = str_replace(' [' . $markup_language_match[1] . ']', '', $match);
          }
        }
        if (!preg_match("#\@$element_label (.*?)(\n|$)#s", $match, $detail_match)) {
          preg_match("#\@$element_label\n +\*   (.*?)(\n +\*\n|$)#s", $match, $detail_match);
          $detail_match[1] = str_replace(' *   ', '', $detail_match[1]);
        }
        $element_values[$element_label] = $detail_match[1];
      }
    }
    return $element_values;
  }

  private function load_code($source) {
    $source = is_array($source) ? $source : array($source);
    foreach ($source as $source_file) {
      $this->code .= file_get_contents($source_file);
    }
  }
}