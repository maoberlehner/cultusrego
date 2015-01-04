<?php

$styleguide = new styleguide(array(
  'source' => array('avalanche/dist/avalanche.css'),
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
      'cache' => /*$this->template_folder . '/twig_cache'*/FALSE,
    ));
    print $twig->render('index.html', array(
      'title' => $this->title,
      'description' => $this->description,
      'source' => $this->source,
      'sections' => $this->sections,
    ));
  }

  private function find_sections() {
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

        $this->sections[$elements['level']] = $elements;
        $last_section = $elements['level'];
      }
      else if (!empty($elements)) {
        foreach ($elements as $element_label => $element_value) {
          $this->sections[$last_section][$element_label] = isset($this->sections[$last_section][$element_label])
            ? $this->sections[$last_section][$element_label] . "\n" . $element_value
            : $element_value;
        }
        //$this->sections[$last_section] = array_merge($this->sections[$last_section], $elements);
      }
    }
    ksort($this->sections);
  }

  private function parse_match($match) {
    $match = trim($match);
    $element_values = array();
    foreach ($this->elements as $element_label) {
      if (strpos($match, '@' . $element_label) !== FALSE) {
        if ($element_label == 'markup') {
          $element_values['markup_language'] = 'html';
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

print rand(1, 1000);