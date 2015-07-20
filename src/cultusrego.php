<?php

use Aptoma\Twig\Extension\MarkdownExtension;
use Aptoma\Twig\Extension\MarkdownEngine;
use gossi\docblock\Docblock;

class cultusrego {
  public $source;
  public $title = 'cultusrego Styleguide';
  public $description = 'PHP Styleguide Generator';
  public $template_folder = __DIR__ . '/template';
  public $base_path = '';
  public $twig_cache;
  public $section_htags = array(
    1 => 'h2',
    2 => 'h3',
    3 => 'h4',
    4 => 'h5',
    5 => 'h6',
  );

  private $code = '';
  private $sections = array();

  function __construct() {
    $this->twig_cache = $this->template_folder . '/twig_cache';

    // Override the default variables with the values
    // that are provided when the class is initialized
    $arguments = func_get_args();
    if (!empty($arguments)) {
      foreach ($arguments[0] as $key => $property) {
        if (property_exists($this, $key)) {
          $this->{$key} = $property;
        }
      }
    }

    // Make an array out of $this->source if a string is provided
    $this->source = is_array($this->source) ? $this->source : array($this->source);
    
    $this->load_code($this->source);
    $this->find_sections();
  }

  /**
   * Loads Markdown and Twig engines and renders the styleguide
   * @return rendered HTML
   */
  public function render() {
    // Initialize Markdown and Twig
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

  /**
   * Parse the provided code for valid styleguide sections
   */
  private function find_sections() {
    $sections = array();
    $level_memory = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
    $last_section = FALSE;

    // Match multiline comments
    preg_match_all('#\/\*((?:(?!\*\/).)*)\*\/#s', $this->code, $matches);

    foreach ($matches[1] as $match) {
      $section = array();
      $elements = $this->parse_match($match);

      if (isset($elements['title']) && isset($elements['level'])) {
        // Default htag to the lowest level htag
        $elements['htag'] = 'h6';

        // Find the depth of the level value
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
      // If no title or level is provided scan for other styleguide elements
      // and append them to the previous section
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

  /**
   * Build a nested array according to the section levels
   * @param  array $sections
   */
  private function build_section_tree($sections) {
    $level_tree = array();

    foreach ($sections as $level => $section) {
      // array_filter removes the last, empty value
      // from the level array that is created by the explode function
      $level_depth_arr = array_filter(explode('.', $level));

      $level_depth = count($level_depth_arr);

      // Get the parent level
      array_pop($level_depth_arr);
      $level_parent = $level_depth > 1 ? implode('.', $level_depth_arr) . '.' : $level;

      // Add the current section to the level tree
      $level_tree[$level_depth][$level_parent][$level] = $section;
    }

    // Run over all main sections in $level_tree[1]
    foreach ($level_tree[1] as $section_level => $section) {
      // Add corresponding sub levels to the main level
      $section[$section_level]['sub'] = $this->build_section_sub_tree($level_tree, 2, $section_level);

      $this->sections += $section;
    }
  }

  /**
   * Build an array of sub sections for the given section
   * @param  array   $main_tree
   * @param  integer $tree_level
   * @param  string  $section_level
   * @return array                  Sub sections for the given section
   */
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

  /**
   * Find styleguide elements and their values in a comment section
   * @param  string $match The contents of a matched comment section
   * @return array         Values of the found styleguide elements
   */
  private function parse_match($match) {
    $match = trim($match);
    $docblock = new Docblock($match);
    $tags = $docblock->getTags();
    $element_values = array();

    if ($title = $docblock->getShortDescription()) {
      // Skip text strings that start with #
      // (e.g. source map comment)
      if (strpos($title, '#') === 0) {
        return $element_values;
      }
      
      $element_values['title'] = $title;
    }

    if ($description = $docblock->getLongDescription()) {
      $element_values['description'] = $description;
    }

    foreach ($tags as $tag) {
      $tag_name = $tag->getTagName();
      $tag_description = $tag->getDescription();
      switch ($tag_name) {
        case 'code':
        case 'markup':
          // Default code language is markup
          $element_values['code_language'] = 'markup';
          // Hide rendered output by default
          $element_values['code_render'] = FALSE;
          // If tag name is markup render by default
          if ($tag_name == 'markup') {
            $element_values['code_render'] = TRUE;
          }
          // Look if a other code language is defined
          if (preg_match("#^\[(.*?)\]\n#s", $tag_description, $code_language_match)) {
            $element_values['code_language'] = $code_language_match[1];
            $tag_description = str_replace('[' . $code_language_match[1] . ']', '', $tag_description);
          }
          $element_values['code'] = trim($tag_description);
          break;

        case 'color':
          $colorsets = array();
          $colorset_elements = explode("\n", $tag_description);
          foreach ($colorset_elements as $colorset) {
            $color_elements = explode('|', $colorset);
            $colors = array();
            foreach ($color_elements as $color) {
              $color_arr = explode(':', $color);
              if (count($color_arr) > 1) {
                $colors[] = array(
                  'name' => trim($color_arr[0]),
                  'value' => trim($color_arr[1]),
                );
              }
              else {
                $colors[] = array('value' => $color_arr[0]);
              }
            }
            $colorsets[] = $colors;
          }
          $element_values['color'] = $colorsets;
          break;

        case 'variable':
          $variables = explode('|', $tag_description);
          $element_values['variable'] = '- ' . implode(";\n- ", $variables) . ';';
          break;

        default:
          $element_values[$tag_name] = $tag_description;
          break;
      }
    }
    return $element_values;
  }

  private function load_code($source) {
    foreach ($source as $source_file) {
      $this->code .= file_get_contents($source_file);
    }
  }
}
