<?php
/**
 * Add Gravity Forms scripts and styles to GravityView no-conflict list
 *
 * @file      class-gravityview-plugin-hooks-gravity-forms.php
 * @package   GravityView
 * @license   GPL2+
 * @author    Katz Web Services, Inc.
 * @link      http://gravityview.co
 * @copyright Copyright 2015, Katz Web Services, Inc.
 *
 * @since 1.15.2
 */

/**
 * @inheritDoc
 */
class GravityView_Plugin_Hooks_Gravity_Forms extends GravityView_Plugin_and_Theme_Hooks {

	/**
	 * @inheritDoc
	 */
	protected $style_handles = array(
		'gform_tooltip',
		'gform_font_awesome',
	);

	/**
	 * @inheritDoc
	 */
	protected $script_handles = array(
		'gform_tooltip_init',
		'gform_field_filter',
		'gform_forms',
	);
}

new GravityView_Plugin_Hooks_Gravity_Forms;