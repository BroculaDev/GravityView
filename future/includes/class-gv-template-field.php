<?php
namespace GV;

/** If this file is called directly, abort. */
if ( ! defined( 'GRAVITYVIEW_DIR' ) ) {
	die();
}

/**
 * Load up the Gamajo Template Loader.
 *
 * @see https://github.com/GaryJones/Gamajo-Template-Loader
 */
if ( ! class_exists( 'Gamajo_Template_Loader' ) ) {
	require gravityview()->plugin->dir( 'future/lib/class-gamajo-template-loader.php' );
}

/**
 * The Field Template class.
 *
 * Attached to a \GV\Field and used by a \GV\Field_Renderer.
 */
abstract class Field_Template extends Template {
	/**
	 * Prefix for filter names.
	 * @var string
	 */
	protected $filter_prefix = 'gravityview/future/template/fields';

	/**
	 * Directory name where custom templates for this plugin should be found in the theme.
	 * @var string
	 */
	protected $theme_template_directory = 'gravityview/future/fields/';

	/**
	 * Directory name where the default templates for this plugin are found.
	 * @var string
	 */
	protected $plugin_template_directory = 'future/templates/fields/';

	/**
	 * @var \GV\Field The field connected to this template.
	 */
	public $field;

	/**
	 * @var \GV\View The view context.
	 */
	public $view;

	/**
	 * @var \GV\Source The source context.
	 */
	public $source;

	/**
	 * @var \GV\Entry The entry context.
	 */
	public $entry;

	/**
	 * @var \GV\Request The request context.
	 */
	public $request;

	/**
	 * @var string The template slug to be loaded (like "table", "list")
	 */
	public static $slug;

	/**
	 * Initializer.
	 *
	 * @param \GV\Field $field The field about to be rendered.
	 * @param \GV\View $view The view in this context, if applicable.
	 * @param \GV\Source $source The source (form) in this context, if applicable.
	 * @param \GV\Entry $entry The entry in this context, if applicable.
	 * @param \GV\Request $request The request in this context, if applicable.
	 */
	public function __construct( Field $field, View $view = null, Source $source = null, Entry $entry = null, Request $request = null ) {
		$this->field = $field;
		$this->view = $view;
		$this->source = $source;
		$this->entry = $entry;
		$this->request = $request;

		/** Add granular overrides. */
		add_filter( $this->filter_prefix . '_get_template_part', array( $this, 'add_id_specific_templates' ), 10, 3 );

		parent::__construct();
	}

	/**
	 * Enable granular template overrides based on current post, view, form, field types, etc.
	 *
	 * The hierarchy is as follows:
	 *
	 * - post-[ID of post of page where view is embedded]-view-[View ID]-field-[Field type]-html.php
	 * - post-[ID of post of page where view is embedded]-view-[View ID]-field-[Field inputType]-html.php
	 * - post-[ID of post of page where view is embedded]-view-[View ID]-field-html.php
	 * - post-[ID of post of page where view is embedded]-field-[Field type]-html.php
	 * - post-[ID of post of page where view is embedded]-field-[Field inputType]-html.php
	 * - post-[ID of post of page where view is embedded]-field-html.php
	 * - post-[ID of post of page where view is embedded]-view-[View ID]-field-[Field type].php
	 * - post-[ID of post of page where view is embedded]-view-[View ID]-field-[Field inputType].php
	 * - post-[ID of post of page where view is embedded]-view-[View ID]-field.php
	 * - post-[ID of post of page where view is embedded]-field-[Field type].php
	 * - post-[ID of post of page where view is embedded]-field-[Field inputType].php
	 * - post-[ID of post of page where view is embedded]-field.php
	 * - form-[Form ID]-field-[Field ID]-html.php
	 * - form-[Form ID]-field-[Field ID].php
	 * - form-[Form ID]-field-[Field type]-html.php
	 * - form-[Form ID]-field-[Field inputType]-html.php
	 * - form-[Form ID]-field-[Field type].php
	 * - form-[Form ID]-field-[Field inputType].php
	 * - view-[View ID]-field-[Field type]-html.php
	 * - view-[View ID]-field-[Field inputType]-html.php
	 * - view-[View ID]-field-[Field type].php
	 * - view-[View ID]-field-[Field inputType].php
	 * - field-[Field type]-html.php
	 * - field-[Field inputType]-html.php
	 * - field-[Field type].php
	 * - field-[Field inputType].php
	 * - field-html.php
	 * - field.php
	 *
	 * @see  Gamajo_Template_Loader::get_template_file_names() Where the filter is
	 * @param array $templates Existing list of templates.
	 * @param string $slug      Name of the template base, example: `html`, `json`, `xml`
	 * @param string $name      Name of the template part.
	 *
	 * @return array $templates Modified template array, merged with existing $templates values
	 */
	public function add_id_specific_templates( $templates, $slug, $name ) {

		$specifics = array();

		list( $slug_dir, $slug_name ) = self::split_slug( $slug, $name );

		global $post;

		if ( ! $this->request->is_view() && $post ) {
			if ( $this->field && $this->field->type ) {
				$specifics []= sprintf( '%spost-%d-view-%d-field-%s-%s.php', $slug_dir, $post->ID, $this->view->ID, $this->field->type, $slug_name );
				$this->field->inputType && $specifics []= sprintf( '%spost-%d-view-%d-field-%s-%s.php', $slug_dir, $post->ID, $this->view->ID, $this->field->inputType, $slug_name );
				$specifics []= sprintf( '%spost-%d-view-%d-field-%s.php', $slug_dir, $post->ID, $this->view->ID, $this->field->type );
				$this->field->inputType && $specifics []= sprintf( '%spost-%d-view-%d-field-%s.php', $slug_dir, $post->ID, $this->view->ID, $this->field->inputType );
				$specifics []= sprintf( '%spost-%d-field-%s-%s.php', $slug_dir, $post->ID, $this->field->type, $slug_name );
				$this->field->inputType && $specifics []= sprintf( '%spost-%d-field-%s-%s.php', $slug_dir, $post->ID, $this->field->inputType, $slug_name );
				$specifics []= sprintf( '%spost-%d-field-%s.php', $slug_dir, $post->ID, $this->field->type );
				$this->field->inputType &&  $specifics []= sprintf( '%spost-%d-field-%s.php', $slug_dir, $post->ID, $this->field->inputType );
			}

			$specifics []= sprintf( '%spost-%d-view-%d-field-%s.php', $slug_dir, $post->ID, $this->view->ID, $slug_name );
			$specifics []= sprintf( '%spost-%d-view-%d-field.php', $slug_dir, $post->ID, $this->view->ID );
			$specifics []= sprintf( '%spost-%d-field-%s.php', $slug_dir, $post->ID, $slug_name );
			$specifics []= sprintf( '%spost-%d-field.php', $slug_dir, $post->ID );
		}
		
		/** Field-specific */
		if ( $this->field ) {

			if ( $this->field->ID ) {
				$specifics []= sprintf( '%sform-%d-field-%d-%s.php', $slug_dir, $this->view->form->ID, $this->field->ID, $slug_name );
				$specifics []= sprintf( '%sform-%d-field-%d.php', $slug_dir, $this->view->form->ID, $this->field->ID );
			}

			if ( $this->field->type ) {
				$specifics []= sprintf( '%sform-%d-field-%s-%s.php', $slug_dir, $this->view->form->ID, $this->field->type, $slug_name );
				$this->field->inputType && $specifics []= sprintf( '%sform-%d-field-%s-%s.php', $slug_dir, $this->view->form->ID, $this->field->inputType, $slug_name );
				$specifics []= sprintf( '%sform-%d-field-%s.php', $slug_dir, $this->view->form->ID, $this->field->type );
				$this->field->inputType && $specifics []= sprintf( '%sform-%d-field-%s.php', $slug_dir, $this->view->form->ID, $this->field->inputType );

				$specifics []= sprintf( '%sview-%d-field-%s-%s.php', $slug_dir, $this->view->ID, $this->field->type, $slug_name );
				$this->field->inputType && $specifics []= sprintf( '%sview-%d-field-%s-%s.php', $slug_dir, $this->view->ID, $this->field->inputType, $slug_name );
				$specifics []= sprintf( '%sview-%d-field-%s.php', $slug_dir, $this->view->ID, $this->field->type );
				$this->field->inputType && $specifics []= sprintf( '%sview-%d-field-%s.php', $slug_dir, $this->view->ID, $this->field->inputType );

				$specifics []= sprintf( '%sfield-%s-%s.php', $slug_dir, $this->field->type, $slug_name );
				$this->field->inputType && $specifics []= sprintf( '%sfield-%s-%s.php', $slug_dir, $this->field->inputType, $slug_name );
				$specifics []= sprintf( '%sfield-%s.php', $slug_dir, $this->field->type );
				$this->field->inputType && $specifics []= sprintf( '%sfield-%s.php', $slug_dir, $this->field->inputType );
			}
		}

		/** Generic field templates */
		$specifics []= sprintf( '%sview-%d-field-%s.php', $slug_dir, $this->view->ID, $slug_name );
		$specifics []= sprintf( '%sform-%d-field-%s.php', $slug_dir, $this->view->form->ID, $slug_name );

		$specifics []= sprintf( '%sview-%d-field.php', $slug_dir, $this->view->ID );
		$specifics []= sprintf( '%sform-%d-field.php', $slug_dir, $this->view->form->ID );

		$specifics []= sprintf( '%sfield-%s.php', $slug_dir, $slug_name );
		$specifics []= sprintf( '%sfield.php', $slug_dir );


		return array_merge( $specifics, $templates );
	}

	/**
	 * Output some HTML.
	 *
	 * @todo Move to \GV\Field_HTML_Template, but call filters here?
	 *
	 * @return void
	 */
	public function render() {

		/** Retrieve the value. */
		$display_value = $value = $this->field->get_value( $this->view, $this->source, $this->entry );

		if ( empty( $value ) ) {
			/**
			 * @filter `gravityview_empty_value` What to display when a field is empty
			 * @deprecated Use the `gravityview/field/value/empty` filter instead
			 * @param string $value (empty string)
			 */
			$value = apply_filters( 'gravityview_empty_value', '' );

			/**
			 * @filter `gravityview/field/value/empty` What to display when this field is empty.
			 * @param string $value The value to display (Default: empty string)
			 * @param \GV\Field_Template The template this is being called from.
			 */
			$value = apply_filters( 'gravityview/field/value/empty', $value, $this );
		}

		$source = $this->source;
		$source_backend = $source ? $source::$backend : null;

		/** Alter the display value according to Gravity Forms. */
		if ( $source_backend == \GV\Source::BACKEND_GRAVITYFORMS ) {
			/** Prevent any PHP warnings that may be generated. */
			ob_start();

			$display_value = \GFCommon::get_lead_field_display( $this->field->field, $value, $this->entry['currency'], false, 'html' );

			if ( $errors = ob_get_clean() ) {
				gravityview()->log->error( 'Errors when calling GFCommon::get_lead_field_display()', array( 'data' => $errors ) );
			}

			/** Call the Gravity Forms field value filter. */
			$display_value = apply_filters( 'gform_entry_field_value', $display_value, $this->field->field, $this->entry->as_entry(), $this->source->form );

			/** Replace merge tags for admin-only fields. */
			if ( ! empty( $this->field->field->adminOnly ) ) {
				$display_value = \GravityView_API::replace_variables( $display_value, $this->form->form, $this->entry->as_entry() );
			}
		}

		/**
		 * Make various pieces of data available to the template
		 *  under the $gravityview scoped variable.
		 *
		 * @filter `gravityview/template/field/data`
		 * @param array $data The default data available to all Field templates.
		 * @param \GV\Field_Template $template The current template.
		 * @since future
		 */
		$this->push_template_data( apply_filters( 'gravityview/template/field/data', array(

			'template' => $this,

			'value' => $value,
			'display_value' => $display_value,

			/** Shortcuts */
			'field' => $this->field,
			'view' => $this->view,
			'source' => $this->source,
			'entry' => $this->entry,
			'request' => $this->request,

		), $this ), 'gravityview' );

		/** Bake the template. */
		ob_start();
		$located_template = $this->get_template_part( static::$slug );
		$output = ob_get_clean();

		gravityview()->log->info( 'Field template for field #{field_id} loaded: {located_template}', array( 'field_id' => $this->field->ID, 'located_template' => $located_template ) );

		$this->pop_template_data( 'gravityview' );

		/** A compatibility array that's required by some of the deprecated filters. */
		$field_compat = array(
			'form' => $source_backend == \GV\Source::BACKEND_GRAVITYFORMS ? $this->source->form : null,
			'field_id' => $this->field->ID,
			'field' => $this->field->field,
			'field_settings' => $this->field->as_configuration(),
			'value' => $value,
			'display_value' => $display_value,
			'format' => 'html',
			'entry' => $this->entry->as_entry(),
			'field_type' => $this->field->type,
			'field_path' => $located_template,
		);

		$pre_link_compat_callback = function( $output, $template ) use ( $field_compat ) {
			$field = $template->field;

			/**
			 * @filter `gravityview_field_entry_value_{$field_type}_pre_link` Modify the field value output for a field type before Show As Link setting is applied. Example: `gravityview_field_entry_value_number_pre_link`
			 * @since 1.16
			 * @param string $output HTML value output
			 * @param array  $entry The GF entry array
			 * @param array  $field_settings Settings for the particular GV field
			 * @param array  $field Field array, as fetched from GravityView_View::getCurrentField()
			 *
			 * @deprecated Use the `gravityview/field/{$field_type}/output` or `gravityview/field/output` filters instead.
			 */
			$output = apply_filters( "gravityview_field_entry_value_{$field->type}_pre_link", $output, $template->entry->as_entry(), $field->as_configuration(), $field_compat );

			/**
			 * Link to the single entry by wrapping the output in an anchor tag
			 *
			 * Fields can override this by modifying the field data variable inside the field. See /templates/fields/post_image.php for an example.
			 */
			if ( ! empty( $field->show_as_link ) && ! \gv_empty( $output, false, false ) ) {
				$link_atts = empty( $field->new_window ) ? array() : array( 'target' => '_blank' );

				$permalink = $template->entry->get_permalink( $template->view, $template->request );
				$output = \gravityview_get_link( $permalink, $output, $link_atts );
				
				/**
				 * @filter `gravityview_field_entry_link` Modify the link HTML
				 * @param string $link HTML output of the link
				 * @param string $href URL of the link
				 * @param array  $entry The GF entry array
				 * @param  array $field_settings Settings for the particular GV field
				 */
				$output = apply_filters( 'gravityview_field_entry_link', $output, $permalink, $template->entry->as_entry(), $field->as_configuration() );
			}

			return $output;
		};

		$post_link_compat_callback = function( $output, $template ) use ( $field_compat ) {
			$field = $template->field;

			/**
			 * @filter `gravityview_field_entry_value_{$field_type}` Modify the field value output for a field type. Example: `gravityview_field_entry_value_number`
			 * @since 1.6
			 * @param string $output HTML value output
			 * @param array  $entry The GF entry array
			 * @param  array $field_settings Settings for the particular GV field
			 * @param array $field Current field being displayed
			 *
			 * @deprecated Use the `gravityview/field/{$field_type}/output` or `gravityview/field/output` filters instead.
			 */
			$output = apply_filters( "gravityview_field_entry_value_{$field->type}", $output, $template->entry->as_entry(), $field->as_configuration(), $field_compat );

			/**
			 * @filter `gravityview_field_entry_value` Modify the field value output for all field types
			 * @param string $output HTML value output
			 * @param array  $entry The GF entry array
			 * @param  array $field_settings Settings for the particular GV field
			 * @param array $field_data  {@since 1.6}
			 *
			 * @deprecated Use the `gravityview/field/{$field_type}/output` or `gravityview/field/output` filters instead.
			 */
			$output = apply_filters( 'gravityview_field_entry_value', $output, $template->entry->as_entry(), $field->as_configuration(), $field_compat );

			/**
			 * @filter `gravityview/field/{$field_type}/output` Modify the field output for a field type.
			 *
			 * @since future
			 *
			 * @param string $output The current output.
			 * @param \GV\Field_Template The template this is being called from.
			 */
			return apply_filters( "gravityview/field/{$field->type}/output", $output, $template );
		};

		/**
		 * Okay, what's this whole pre/post_link compat deal, huh?
		 *
		 * Well, the `gravityview_field_entry_value_{$field_type}_pre_link` filter
		 *  is expected to be applied before the value is turned into an entry link.
		 *
		 * And then `gravityview_field_entry_value_{$field_type}` and `gravityview_field_entry_value`
		 *  are called afterwards.
		 *
		 * So we're going to use filter priorities to make sure this happens inline with
		 *  our new filters, in the correct sequence. Pre-link called with priority 5 and
		 *  post-link called with priority 9. Then everything else.
		 *
		 * If a new code wants to alter the value before it is hyperlinked (hyperlinkified?),
		 *  it should hook into a priority between -inf. and 8. Afterwards: 10 to +inf.
		 */
		add_filter( 'gravityview/field/output', $pre_link_compat_callback, 5, 2 );
		add_filter( 'gravityview/field/output', $post_link_compat_callback, 9, 2 );

		/**
		 * @filter `gravityview/field/output` Modify the field output for a field.
		 *
		 * @since future
		 *
		 * @param string $output The current output.
		 * @param \GV\Field_Template The template this is being called from.
		 */
		echo apply_filters( "gravityview/field/output", $output, $this );

		remove_filter( 'gravityview/field/output', $pre_link_compat_callback, 5 );
		remove_filter( 'gravityview/field/output', $post_link_compat_callback, 9 );
	}
}

/** Load implementations. */
require gravityview()->plugin->dir( 'future/includes/class-gv-template-field-html.php' );
