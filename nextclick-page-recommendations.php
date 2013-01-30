<?php
/*
Plugin Name: Nextclick Page Recommendations
Plugin URI: http://www.nextclick.pl/
Description: Generates a Nextclick Widget on your WP posts and pages. You need to have valid <a target="_blank" href="http://www.nextclick.pl">Nextclick</a> account.
Author: LeadBullet S.A
Version: 1.0.0
Author URI: http://www.leadbullet.pl
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/*

Copyright 2013 LeadBullet S.A (kontakt@leadbullet.pl)

this program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

 */

class Nextclick_Page_Recommendations extends WP_Widget {

  // Available Nextclick widget types
  const TYPE_STANDARD_BOX = 'recommendation';
  const TYPE_FLOATING_BOX = 'floating';
  
  const FORM_PARAM_WIDGET_KEY= 'nextclickWidgetKey';
  const FORM_PARAM_WIDGET_TYPE = 'nextclickWidgetType';
  
  public static $FORM_ATTRIBUTES = Array(
      self::FORM_PARAM_WIDGET_KEY => 'Klucz widgeta',
      self::FORM_PARAM_WIDGET_TYPE => 'Typ widgeta',
  );

  public static $WIDGET_TYPES = array(
    self::TYPE_STANDARD_BOX => 'Standardowy, umieszczony w środku strony',
    self::TYPE_FLOATING_BOX => 'Pływający, umieszczony w dolnej części okna',
  );

  /*
   * Nextclick Widget required variables
   */
  private $websiteHost;
  private $widgetKey;
  private $widgetType;
  private $widgetCollectMode = 0;
  private $ncPageVariables = Array();

  /**
	 * Initializes WP_Widget object.
	 * @see parent::__construct()
	 */
  public function __construct()
  {
    parent::__construct(
        'nextclick_page_recommendations',
        'Nextclick Page Recommendations',
        Array(
          'description' => __( 'Wyświetl widget Nextclick na artykułach i podstronach Twojego serwisu', 'nextclick_page_recommendations')
        )
    );

    $this->websiteHost = $_SERVER['HTTP_HOST'];
    $this->ncPageVariables = Array(
      '__NC_PAGE_IMAGE_URL__' => '',
      '__NC_PAGE_TITLE__' => '',
      '__NC_PAGE_DESCRIPTION__' => '',
      '__NC_PAGE_CREATED_AT__' => '',
    );
  }

  /**
   * Front-end display of widget.
   *
   * @see WP_Widget::widget()
   *
   * @param array $arguments     Widget arguments.
   * @param array $instance Saved values from database.
   */
  public function widget($arguments, $instance)
  {
    extract($arguments, EXTR_SKIP);

    $this->widgetKey = apply_filters( 'nextclickWidgetKey', $instance['nextclickWidgetKey'] );
    $this->widgetType = apply_filters( 'nextclickWidgetType', $instance['nextclickWidgetType'] );

    if (!$this->validateDisplayConditions()) {
      return;
    }

    if ($this->widgetCollectMode) {
      global $wp_query;

      $post = $wp_query->get_queried_object();

      $this->ncPageVariables = Array(
        '__NC_PAGE_IMAGE_URL__' => wp_get_attachment_url(get_post_thumbnail_id($post->ID)),
        '__NC_PAGE_TITLE__' => esc_js($post->post_title),
        '__NC_PAGE_DESCRIPTION__' => esc_js(neatest_trim($post->post_content, 360)),
        '__NC_PAGE_CREATED_AT__' => $post->post_date,
      );
    }

    $widgetScript = $this->loadWidget();

    echo $before_widget;
    echo $widgetScript;
    echo $after_widget;
  }

  /**
   * Sanitize widget form values as they are saved.
   *
   * @see WP_Widget::update()
   *
   * @param array $new_instance Values just sent to be saved.
   * @param array $old_instance Previously saved values from database.
   *
   * @return array Updated safe values to be saved.
   */
  public function update($new_instance, $old_instance)
  {
    $instance = $old_instance;

    foreach ($new_instance as $property => $value) {
      $instance[$property] = $value;
      
      unset($instance['errors'][$property]);
      
      if (empty($instance[$property])) {
        $instance['errors'][$property] = "(Pole wymagane)";
      }
    }

    return $instance;
  }

  /**
   * Back-end widget form.
   *
   * @see WP_Widget::form()
   *
   * @param array $instance Previously saved values from database.
   */
  public function form($instance)
  {
    $formAttributesPanel =
      "<p>
        <label for=\"" . $this->get_field_id(self::FORM_PARAM_WIDGET_KEY) . "\">
          " . self::$FORM_ATTRIBUTES[self::FORM_PARAM_WIDGET_KEY] . ": <span style=\"color: red;\">" . $instance['errors'][self::FORM_PARAM_WIDGET_KEY] . "</span><input class=\"widefat\" id=\"" . $this->get_field_id(self::FORM_PARAM_WIDGET_KEY) . "\" name=\"" . $this->get_field_name(self::FORM_PARAM_WIDGET_KEY) . "\" type=\"text\" value=\"" . $instance[self::FORM_PARAM_WIDGET_KEY] . "\" />
        </label>
      </p>
      <p>
        <label for=\"". $this->get_field_id(self::FORM_PARAM_WIDGET_TYPE) . "\">
          Wybierz typ umieszczanego widgeta:
          <select id=\"". $this->get_field_id(self::FORM_PARAM_WIDGET_TYPE) . "\" name=\"". $this->get_field_name(self::FORM_PARAM_WIDGET_TYPE) . "\" style=\"width: 200px;\">";

          foreach (self::$WIDGET_TYPES as $widgetType => $label) {
            $selected = $instance[self::FORM_PARAM_WIDGET_TYPE] == $widgetType ? "selected" : '';

            $formAttributesPanel .= "<option value=\"" . $widgetType . "\" $selected>$label</option>";
          }

    $formAttributesPanel .=
        "</select>
      </label>
    </p>";

    echo $formAttributesPanel;
  }

  /**
   * Checks whether widget can be displayed on a current site
   * and in which collection mode (either 0 or 1)
   * 
   * @return bool
   */
  private function validateDisplayConditions()
  {
    if (is_singular() && !current_user_can('manage_options')) {
      $this->widgetCollectMode = 1;
    }

    return !empty($this->widgetKey);
  }

  /**
   * Generate widget javascript
   * 
   * @return String
   */
  private function loadWidget()
  {
    $widgetScript = '';
    $widgetScriptFilename = dirname(__FILE__) . '/' . '_widgetScript.txt';
    
    if (is_readable($widgetScriptFilename)) {
      $widgetScript = file_get_contents($widgetScriptFilename);      
      $widgetScript = str_replace(
                        Array('__WEBSITE_HOST__', '__WIDGET_KEY__', '__WIDGET_TYPE__', '__WIDGET_COLLECT_MODE__'),
                        Array($this->websiteHost, $this->widgetKey, $this->widgetType, $this->widgetCollectMode),
                        $widgetScript
                      );

      // Replace nc_page_ variables
      foreach ($this->ncPageVariables as $key => $value) {
        $widgetScript = str_replace($key, $value, $widgetScript);
      }
    }

    return $widgetScript;
  }
}

add_action( 'widgets_init', create_function( '', 'register_widget("nextclick_page_recommendations");'));