<?php

use Aptoma\Twig\Extension\MarkdownExtension;
use Aptoma\Twig\Extension\MarkdownEngine;

class cultusrego {
  public $source;
  public $title = 'cultusrego Styleguide';
  public $description = 'PHP Styleguide Generator';
  public $template_folder = __DIR__ . '/templates';
  public $twig_cache;
  public $base_path;
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
    'variables',
    'font',
  );

  function __construct() {
    $this->twig_cache = $this->template_folder . '/twig_cache';
    $this->base_path = substr(str_replace('\\', '/', realpath(dirname(__FILE__))), strlen(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])))) . '/';

    $arguments = func_get_args();
    if (!empty($arguments)) {
      foreach ($arguments[0] as $key => $property) {
        if (property_exists($this, $key)) {
          $this->{$key} = $property;
        }
      }
    }

    $this->source = is_array($this->source) ? $this->source : array($this->source);
    $this->load_code($this->source);
    $this->find_sections();
  }

  public function render() {
    $engine = new MarkdownEngine\MichelfMarkdownEngine();
    $twig_loader = new Twig_Loader_Filesystem($this->template_folder);
    $twig = new Twig_Environment($twig_loader, array(
      'cache' => $this->twig_cache,
    ));
    $twig->addExtension(new MarkdownExtension($engine));

    print $twig->render('index.html', array(
      'title' => $this->title,
      'description' => $this->description,
      'source' => $this->source,
      'sections' => $this->sections,
      'base_path' => $this->base_path,
    ));
  }

  private function find_sections() {
    $sections = array();
    $level_memory = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
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

        // Reset the level memory to the depth of sub levels
        $level_memory = array_slice($level_memory, 0, $level_depth);
        $level_memory = array_pad($level_memory, 10, 0);

        // Set / unset and count up the levels
        foreach ($level_depth_arr as $key => $level) {
          if (is_numeric($level)) {
            $level_memory[$key] = $level;
          }
          else {
            // Only count up the last level
            if ($key > 0 && $key + 1 == $level_depth) {
              $level_memory[$key]++;
            }
            // Set the level depth
            $level_depth_arr[$key] = $level_memory[$key];
          }
        }
        $elements['level'] = implode('.', $level_depth_arr) . '.';

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
      if (strpos($match, '@' . $element_label) === FALSE) { continue; }

      switch ($element_label) {
        case 'markup':
          $element_values['markup_language'] = 'markup';
          if (preg_match("#\@markup \[(.*?)\]\n#s", $match, $markup_language_match)) {
            $element_values['markup_language'] = $markup_language_match[1];
            $match = str_replace(' [' . $markup_language_match[1] . ']', '', $match);
          }
          $element_values[$element_label] = $this->parse_element_value($element_label, $match);
          break;

        case 'colors':
          $colorsets = array();
          $value = $this->parse_element_value($element_label, $match);
          $colorset_elements = explode("\n", $value);
          foreach ($colorset_elements as $colorset) {
            $color_elements = explode(' ', $colorset);
            $colors = array();
            foreach ($color_elements as $color) {
              $color_arr = explode('|', $color);
              if (count($color_arr) > 1) {
                $colors[] = array(
                  'name' => $color_arr[0],
                  'value' => $color_arr[1],
                );
              }
              else {
                $colors[] = array('value' => $color_arr[0]);
              }
            }
            $colorsets[] = $colors;
          }
          $element_values[$element_label] = $colorsets;
          break;

        case 'variables':
          $variables = $this->parse_element_value($element_label, $match);
          $variables = explode(' ', $variables);
          $variables = str_replace('|', ': ', $variables);
          $element_values[$element_label] = '- ' . implode(";\n- ", $variables) . ';';
          break;

        case 'font':
          $element_values['font_family'] = '';
          if (preg_match("#\@font \[(.*?)\]\n#s", $match, $font_family_match)) {
            $element_values['font_family'] = $font_family_match[1];
            $match = str_replace(' [' . $font_family_match[1] . ']', '', $match);
          }
          $element_values[$element_label] = $this->parse_element_value($element_label, $match);
          break;

        default:
          $element_values[$element_label] = $this->parse_element_value($element_label, $match);
          break;
      }
    }
    return $element_values;
  }

  private function parse_element_value($element_label, $match) {
    if (!preg_match_all("#\@$element_label (.*?)(\n|$)#s", $match, $detail_match)) {
      preg_match_all("#\@$element_label\n +\*   (.*?)(\n +\*\n|$)#s", $match, $detail_match);
      $detail_match[1] = preg_replace('#( *)\*   #', '', $detail_match[1]);
    }
    return implode("\n\n", $detail_match[1]);
  }

  private function load_code($source) {
    foreach ($source as $source_file) {
      $this->code .= file_get_contents($source_file);
    }
  }
}
