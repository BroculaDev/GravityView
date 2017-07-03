<?php
namespace GV\Mocks;

/**
 * This file contains mock code for deprecated functions.
 */

/**
 * @see \GravityView_View_Data::add_view
 * @internal
 * @since future
 *
 * @return array|false The old array data, or false on error.
 */
function GravityView_View_Data_add_view( $view_id, $atts, $_this ) {
	/** Handle array of IDs. */
	if ( is_array( $view_id ) ) {
		foreach ( $view_id as $id ) {
			call_user_func( __FUNCTION__, $id, $atts, $_this );
		}

		if ( ! $_this->views->count() ) {
			return array();
		}

		return array_combine(
			array_map( function( $view ) { return $view->ID; }, $_this->views->all() ),
			array_map( function( $view ) { return $view->as_data(); }, $_this->views->all() )
		);
	}

	/** View has been set already. */
	if ( $view = $_this->views->get( $view_id ) ) {
		do_action( 'gravityview_log_debug', sprintf( 'GravityView_View_Data[add_view] Returning; View #%s already exists.', $view_id ) );
		return $view->as_data();
	}

	$view = \GV\View::by_id( $view_id );
	if ( ! $view ) {
		do_action( 'gravityview_log_debug', sprintf( 'GravityView_View_Data[add_view] Returning; View #%s does not exist.', $view_id ) );
		return false;
	}

	/** Doesn't have a connected form. */
	if ( ! $view->form ) {
		do_action( 'gravityview_log_debug', sprintf( 'GravityView_View_Data[add_view] Returning; Post ID #%s does not have a connected form.', $view_id ) );
		return false;
	}

	/** Update the settings */
	if ( is_array( $atts ) ) {
		$view->settings->update( $atts );
	}

	$_this->views->add( $view );

	return $view->as_data();
}

/**
 * @see \GravityView_frontend::get_view_entries
 * @internal
 * @since future
 *
 * @return array The old associative array data as returned by
 *  \GravityView_frontend::get_view_entries(), the paging parameters
 *  and a total count of all entries.
 */
function GravityView_frontend_get_view_entries( $args, $form_id, $parameters, $count ) {
	$form = \GV\GF_Form::by_id( $form_id );

	/**
	 * Kick off all advanced filters.
	 *
	 * Parameters and criteria are pretty much the same thing here, just
	 *  different naming, where `$parameters` are the initial parameters
	 *  calculated for hte view, and `$criteria` are the filtered ones
	 *  retrieved via `GVCommon::calculate_get_entries_criteria`.
	 */
	$criteria = \GVCommon::calculate_get_entries_criteria( $parameters, $form->ID );

	do_action( 'gravityview_log_debug', '[gravityview_get_entries] Final Parameters', $criteria );

	/** ...and all the (now deprectated) filters that usually follow `gravityview_get_entries` */

	/**
	 * @deprecated
	 * Do not use this filter anymore.
	 */
	$entries = apply_filters( 'gravityview_before_get_entries', null, $criteria, $parameters, $count );
	if ( ! is_null( $entries ) ) {
		/**
		 * We've been given an entries result that we can return,
		 *  just set the paging and we're good to go.
		 */
		$paging = rgar( $parameters, 'paging' );
	} else {
		$entries = $form->entries
			->filter( \GV\GF_Entry_Filter::from_search_criteria( $criteria['search_criteria'] ) )
			->offset( $args['offset'] )
			->limit( $criteria['paging']['page_size'] )
			->page( ( ( $criteria['paging']['offset'] - $args['offset'] ) / $criteria['paging']['page_size'] ) + 1 );
		if ( ! empty( $criteria['sorting'] ) ) {
			$field = new \GV\Field();
			$field->ID = $criteria['sorting']['key'];
			$direction = strtolower( $criteria['sorting']['direction'] ) == 'asc' ? \GV\Entry_Sort::ASC : \GV\Entry_Sort::DESC;
			$mode = $criteria['sorting']['is_numeric'] ? \GV\Entry_Sort::NUMERIC : \GV\Entry_Sort::ALPHA;
			$entries = $entries->sort( new \GV\Entry_Sort( $field, $direction, $mode ) );
		}

		/** Set paging, count and unwrap the entries. */
		$paging = array(
			'offset' => ( $entries->current_page - 1 ) * $entries->limit,
			'page_size' => $entries->limit,
		);
		$count = $entries->total();
		$entries = array_map( function( $e ) { return $e->as_entry(); }, $entries->all() );
	}

	/** Just one more filter, for compatibility's sake! */

	/**
	 * @deprecated
	 * Do not use this filter anymore.
	 */
	$entries = apply_filters( 'gravityview_entries', $entries, $criteria, $parameters, $count );

	return array( $entries, $paging, $count );
}

/**
 * The old function does a bit too much, not only does it retrieve
 *  the value for a field, but it also renders some output. We are
 *  stubbing the plain value part of it, the rendering will follow once
 *  our field renderers are ready.
 *
 * @see \GravityView_API::field_value
 * @deprecated Use \GV\Field_Template::render()
 * @internal
 * @since future
 *
 * @return null|string The value of a field in an entry.
 */
function GravityView_API_field_value( $entry, $field_settings, $format ) {
	if ( empty( $entry['form_id'] ) || empty( $field_settings['id'] ) ) {
		gravityview()->log->error( 'No entry or field_settings[id] supplied', array( 'data' => array( func_get_args() ) ) );
		return null;
	}

	if ( empty( $entry['id'] ) || ! $entry = \GV\GF_Entry::by_id( $entry['id'] ) ) {
		gravityview()->log->error( 'Invalid \GV\GF_Entry supplied', array( 'data' => $entry ) );
		return null;
	}

	/**
	 * Determine the source backend.
	 *
	 * Fields with a numeric ID are Gravity Forms ones.
	 */
	$source = is_numeric( $field_settings['id'] ) ? \GV\Source::BACKEND_GRAVITYFORMS : \GV\Source::BACKEND_INTERNAL;;

	/** Initialize the future field. */
	switch ( $source ):
		/** The Gravity Forms backend. */
		case \GV\Source::BACKEND_GRAVITYFORMS:
			if ( ! $form = \GV\GF_Form::by_id( $entry['form_id'] ) ) {
				gravityview()->log->error( 'No form Gravity Form found for entry', array( 'data' => $entry ) );
				return null;
			}

			if ( ! $field = $form::get_field( $form, $field_settings['id'] ) ) {
				return null;
			}

			break;

		/** Our internal backend. */
		case \GV\Source::BACKEND_INTERNAL:
			if ( ! $field = \GV\Internal_Source::get_field( $field_settings['id'] ) ) {
				return null;
			}

			break;

		/** An unidentified backend. */
		default:
			gravityview()->log->error( 'Could not determine source for entry', array( 'data' => array( func_get_args() ) ) );
			return null;
	endswitch;

	$field_type = $field->type;

	/** Add the field settings. */
	$field->update_configuration( $field_settings );

	/** Get the value. */
	$display_value = $value = $field->get_value( /** View */ null, /** Source */ null, $entry );

	/** Alter the display value according to Gravity Forms. */
	if ( $source == \GV\Source::BACKEND_GRAVITYFORMS ) {
		// Prevent any PHP warnings that may be generated
		ob_start();

		$display_value = \GFCommon::get_lead_field_display( $field->field, $value, $entry['currency'], false, $format );

		if ( $errors = ob_get_clean() ) {
			gravityview()->log->error( 'Errors when calling GFCommon::get_lead_field_display()', array( 'data' => $errors ) );
		}

		$display_value = apply_filters( 'gform_entry_field_value', $display_value, $field->field, $entry->as_entry(), $form->form );

		// prevent the use of merge_tags for non-admin fields
		if ( !empty( $field->field->adminOnly ) ) {
			$display_value = \GravityView_API::replace_variables( $display_value, $form->form, $entry->as_entry() );
		}
	}

	$gravityview_view = \GravityView_View::getInstance();

	// Check whether the field exists in /includes/fields/{$field_type}.php
	// This can be overridden by user template files.
	$field_path = $gravityview_view->locate_template("fields/{$field_type}.php");

	// Set the field data to be available in the templates
	$gravityview_view->setCurrentField( array(
		'form' => isset( $form ) ? $form->form : $gravityview_view->getForm(),
		'field_id' => $field->ID,
		'field' => $field->field,
		'field_settings' => $field->as_configuration(),
		'value' => $value,
		'display_value' => $display_value,
		'format' => $format,
		'entry' => $entry->as_entry(),
		'field_type' => $field_type, /** {@since 1.6} */
		'field_path' => $field_path, /** {@since 1.16} */
	) );

	if ( ! empty( $field_path ) ) {
		gravityview()->log->debug( 'Rendering {template}', array( 'template' => $field_path ) );

		ob_start();
		load_template( $field_path, false );
		$output = ob_get_clean();
	} else {
		// Backup; the field template doesn't exist.
		$output = $display_value;
	}

	// Get the field settings again so that the field template can override the settings
	$field_settings = $gravityview_view->getCurrentField( 'field_settings' );

	/**
	 * @filter `gravityview_field_entry_value_{$field_type}_pre_link` Modify the field value output for a field type before Show As Link setting is applied. Example: `gravityview_field_entry_value_number_pre_link`
	 * @since 1.16
	 * @param string $output HTML value output
	 * @param array  $entry The GF entry array
	 * @param array  $field_settings Settings for the particular GV field
	 * @param array  $field Field array, as fetched from GravityView_View::getCurrentField()
	 */
	$output = apply_filters( "gravityview_field_entry_value_{$field_type}_pre_link", $output, $entry->as_entry(), $field_settings, $gravityview_view->getCurrentField() );

	/**
	 * Link to the single entry by wrapping the output in an anchor tag
	 *
	 * Fields can override this by modifying the field data variable inside the field. See /templates/fields/post_image.php for an example.
	 *
	 */
	if ( ! empty( $field_settings['show_as_link'] ) && ! \gv_empty( $output, false, false ) ) {

		$link_atts = empty( $field_settings['new_window'] ) ? array() : array( 'target' => '_blank' );
		$output = \GravityView_API::entry_link_html( $entry->as_entry(), $output, $link_atts, $field_settings );
	}

	/**
	 * @filter `gravityview_field_entry_value_{$field_type}` Modify the field value output for a field type. Example: `gravityview_field_entry_value_number`
	 * @since 1.6
	 * @param string $output HTML value output
	 * @param array  $entry The GF entry array
	 * @param  array $field_settings Settings for the particular GV field
	 * @param array $field Current field being displayed
	 */
	$output = apply_filters( "gravityview_field_entry_value_$field_type", $output, $entry->as_entry(), $field_settings, $gravityview_view->getCurrentField() );

	/**
	 * @filter `gravityview_field_entry_value` Modify the field value output for all field types
	 * @param string $output HTML value output
	 * @param array  $entry The GF entry array
	 * @param  array $field_settings Settings for the particular GV field
	 * @param array $field_data  {@since 1.6}
	 */
	$output = apply_filters( 'gravityview_field_entry_value', $output, $entry->as_entry(), $field_settings, $gravityview_view->getCurrentField() );

	return $output;
}

/**
 * Mock out the \GravityView_API::field_label method
 *
 * Uses the new \GV\Field::get_label methods
 *
 * @see \GravityView_API::field_label
 * @internal
 * @since future
 *
 * @return string The label of a field in an entry.
 */
function GravityView_API_field_label( $form, $field_settings, $entry, $force_show_label = false ) {

	/** A bail condition. */
	$bail = function( $label, $field_settings, $entry, $force_show_label, $form ) {
		if ( ! empty( $field_settings['show_label'] ) || $force_show_label ) {

			$label = isset( $field_settings['label'] ) ? $field_settings['label'] : '';

			// Use Gravity Forms label by default, but if a custom label is defined in GV, use it.
			if ( ! empty( $field_settings['custom_label'] ) ) {
				$label = \GravityView_API::replace_variables( $field_settings['custom_label'], $form, $entry );
			}

			/**
			 * @filter `gravityview_render_after_label` Append content to a field label
			 * @param[in,out] string $appended_content Content you can add after a label. Empty by default.
			 * @param[in] array $field GravityView field array
			 */
			$label .= apply_filters( 'gravityview_render_after_label', '', $field_settings );
		}

		/**
		 * @filter `gravityview/template/field_label` Modify field label output
		 * @since 1.7
		 * @param[in,out] string $label Field label HTML
		 * @param[in] array $field GravityView field array
		 * @param[in] array $form Gravity Forms form array
		 * @param[in] array $entry Gravity Forms entry array
		 */
		$label = apply_filters( 'gravityview/template/field_label', $label, $field_settings, $form, $entry );

		return $label;
	};

	$label = '';

	if ( empty( $entry['form_id'] ) || empty( $field_settings['id'] ) ) {
		gravityview()->log->error( 'No entry or field_settings[id] supplied', array( 'data' => array( func_get_args() ) ) );
		return $bail( $label, $field_settings, $entry, $force_show_label, $form );
	}

	if ( empty( $entry['id'] ) || ! $gv_entry = \GV\GF_Entry::by_id( $entry['id'] ) ) {
		gravityview()->log->error( 'Invalid \GV\GF_Entry supplied', array( 'data' => $entry ) );
		return $bail( $label, $field_settings, $entry, $force_show_label, $form );
	}

	$entry = $gv_entry;

	/**
	 * Determine the source backend.
	 *
	 * Fields with a numeric ID are Gravity Forms ones.
	 */
	$source = is_numeric( $field_settings['id'] ) ? \GV\Source::BACKEND_GRAVITYFORMS : \GV\Source::BACKEND_INTERNAL;;

	/** Initialize the future field. */
	switch ( $source ):
		/** The Gravity Forms backend. */
		case \GV\Source::BACKEND_GRAVITYFORMS:
			if ( ! $gf_form = \GV\GF_Form::by_id( $entry['form_id'] ) ) {
				gravityview()->log->error( 'No form Gravity Form found for entry', array( 'data' => $entry ) );
				return $bail( $label, $field_settings, $entry->as_entry(), $force_show_label, $form );
			}

			if ( ! $field = $gf_form::get_field( $gf_form, $field_settings['id'] ) ) {
				gravityview()->log->error( 'No field found for specified form and field ID #{field_id}', array( 'field_id' => $field_settings['id'], 'data' => $form ) );
				return $bail( $label, $field_settings, $entry->as_entry(), $force_show_label, $gf_form->form );
			}
			/** The label never wins... */
			$field_settings['label'] = '';
			break;

		/** Our internal backend. */
		case \GV\Source::BACKEND_INTERNAL:
			if ( ! $field = \GV\Internal_Source::get_field( $field_settings['id'] ) ) {
				return $bail( $label, $field_settings, $entry->as_entry(), $force_show_label, $form );
			}
			break;

		/** An unidentified backend. */
		default:
			gravityview()->log->error( 'Could not determine source for entry. Using empty field.', array( 'data' => array( func_get_args() ) ) );
			$field = new \GV\Field();
			break;
	endswitch;

	/** Add the field settings. */
	$field->update_configuration( $field_settings );

	if ( ! empty( $field->show_label ) || $force_show_label ) {

		$label = $field->get_label( null, isset( $gf_form ) ? $gf_form : null, $entry );

		/**
		 * @filter `gravityview_render_after_label` Append content to a field label
		 * @param[in,out] string $appended_content Content you can add after a label. Empty by default.
		 * @param[in] array $field GravityView field array
		 */
		$label .= apply_filters( 'gravityview_render_after_label', '', $field->as_configuration() );

	}

	/**
	 * @filter `gravityview/template/field_label` Modify field label output
	 * @since 1.7
	 * @param[in,out] string $label Field label HTML
	 * @param[in] array $field GravityView field array
	 * @param[in] array $form Gravity Forms form array
	 * @param[in] array $entry Gravity Forms entry array
	 */
	return apply_filters( 'gravityview/template/field_label', $label, $field->as_configuration(), isset( $gf_form ) ? $gf_form->form : $form, $entry->as_entry() );
}


/**
 * A manager of legacy global states and contexts.
 *
 * Handles mocking of:
 * - \GravityView_View_Data
 * - \GravityView_View
 * - \GravityView_frontend
 *
 * Allows us to set a specific state globally using the old
 *  containers, then reset it. Useful for legacy code that keeps
 *  on depending on these variables.
 *
 * Some examples right now include template files, utility functions,
 *  some actions and filters that expect the old contexts to be set.
 */
final class Legacy_Context {
	private static $stack = array();

	/**
	 * Set the state depending on the provided configuration.
	 *
	 * Saves current global state and context.
	 *
	 * Configuration keys:
	 * 
	 * - \GV\View	view:		sets \GravityView_View::atts, \GravityView_View::view_id,
	 *								 \GravityView_View::back_link_label
	 *								 \GravityView_frontend::context_view_id,
	 *								 \GravityView_View::form, \GravityView_View::form_id
	 * - \GV\Field	field:		sets \GravityView_View::_current_field, \GravityView_View::field_data, 
	 * - \GV\Entry	entry:		sets \GravityView_View::_current_entry, \GravityView_frontend::single_entry,
	 *								 \GravityView_frontend::entry
	 * - \WP_Post	post:		sets \GravityView_View::post_id, \GravityView_frontend::post_id,
	 *								 \GravityView_frontend::is_gravityview_post_type,
	 *								 \GravityView_frontend::post_has_shortcode
	 * - array		paging:		sets \GravityView_View::paging
	 * - array		sorting:	sets \GravityView_View::sorting
	 * - array		template:	sets \GravityView_View::template_part_slug, \GravityView_View::template_part_name
	 *
	 * - boolean	in_the_loop sets $wp_actions['loop_start'] and $wp_query::in_the_loop
	 *
	 * also:
	 *
	 * - \GV\Request	request:	sets \GravityView_frontend::is_search, \GravityView_frontend::single_entry,
	 *									 \GravityView_View::context, \GravityView_frontend::entry
	 *
	 * - \GV\View_Collection	views:		sets \GravityView_View_Data::views
	 * - \GV\Field_Collection	fields:		sets \GravityView_View::fields
	 * - \GV\Entry_Collection	entries:	sets \GravityView_View::entries, \GravityView_View::total_entries
	 *
	 * and automagically:
	 *
	 * - \GravityView_View		data:		sets \GravityView_frontend::gv_output_data
	 *
	 * @param array $configuration The configuration.
	 *
	 * @return void
	 */
	public static function push( $configuration ) {
		array_push( self::$stack, self::freeze() );
		self::load( $configuration );
	}

	/**
	 * Restores last saved state and context.
	 *
	 * @return void
	 */
	public static function pop() {
		self::thaw( array_pop( self::$stack ) );
	}

	/**
	 * Serializes the current configuration as needed.
	 *
	 * @return array The configuration.
	 */
	public static function freeze() {
		global $wp_actions, $wp_query;

		return array(
			'\GravityView_View::atts' => \GravityView_View::getInstance()->getAtts(),
			'\GravityView_View::view_id' => \GravityView_View::getInstance()->getViewId(),
			'\GravityView_View::back_link_label' => \GravityView_View::getInstance()->getBackLinkLabel( false ),
			'\GravityView_View_Data::views' => \GravityView_View_Data::getInstance()->views,
			'\GravityView_View::entries' => \GravityView_View::getInstance()->getEntries(),
			'\GravityView_View::form' => \GravityView_View::getInstance()->getForm(),
			'\GravityView_View::form_id' => \GravityView_View::getInstance()->getFormId(),
			'\GravityView_View::context' => \GravityView_View::getInstance()->getContext(),
			'\GravityView_View::total_entries' => \GravityView_View::getInstance()->getTotalEntries(),
			'\GravityView_View::post_id' => \GravityView_View::getInstance()->getPostId(),
			'\GravityView_frontend::post_id' => \GravityView_frontend::getInstance()->getPostId(),
			'\GravityView_frontend::context_view_id' => \GravityView_frontend::getInstance()->get_context_view_id(),
			'\GravityView_frontend::is_gravityview_post_type' => \GravityView_frontend::getInstance()->isGravityviewPostType(),
			'\GravityView_frontend::post_has_shortcode' => \GravityView_frontend::getInstance()->isPostHasShortcode(),
			'\GravityView_frontend::gv_output_data' => \GravityView_frontend::getInstance()->getGvOutputData(),
			'\GravityView_View::paging' => \GravityView_View::getInstance()->getPaging(),
			'\GravityView_View::sorting' => \GravityView_View::getInstance()->getSorting(),
			'\GravityView_frontend::is_search' => \GravityView_frontend::getInstance()->isSearch(),
			'\GravityView_frontend::single_entry' => \GravityView_frontend::getInstance()->getSingleEntry(),
			'\GravityView_frontend::entry' => \GravityView_frontend::getInstance()->getEntry(),
			'\GravityView_View::_current_entry' => \GravityView_View::getInstance()->getCurrentEntry(),
			'wp_actions[loop_start]' => empty( $wp_actions['loop_start'] ) ? 0 : $wp_actions['loop_start'],
			'wp_query::in_the_loop' => $wp_query->in_the_loop,
		);
	}

	/**
	 * Unserializes a saved configuration. Modifies the global state.
	 *
	 * @param array $data Saved configuration from self::freeze()
	 */
	public static function thaw( $data ) {
		foreach ( (array)$data as $key => $value ) {
			switch ( $key ):
				case '\GravityView_View::atts':
					\GravityView_View::getInstance()->setAtts( $value );
					break;
				case '\GravityView_View::view_id':
					\GravityView_View::getInstance()->setViewId( $value );
					break;
				case '\GravityView_View::back_link_label':
					\GravityView_View::getInstance()->setBackLinkLabel( $value );
					break;
				case '\GravityView_View_Data::views':
					\GravityView_View_Data::getInstance()->views = $value;
					break;
				case '\GravityView_View::entries':
					\GravityView_View::getInstance()->setEntries( $value );
					break;
				case '\GravityView_View::form':
					\GravityView_View::getInstance()->setForm( $value );
					break;
				case '\GravityView_View::form_id':
					\GravityView_View::getInstance()->setFormId( $value );
					break;
				case '\GravityView_View::context':
					\GravityView_View::getInstance()->setContext( $value );
					break;
				case '\GravityView_View::total_entries':
					\GravityView_View::getInstance()->setTotalEntries( $value );
					break;
				case '\GravityView_View::post_id':
					\GravityView_View::getInstance()->setPostId( $value );
					break;
				case '\GravityView_frontend::post_id':
					\GravityView_frontend::getInstance()->setPostId( $value );
					break;
				case '\GravityView_frontend::context_view_id':
					$frontend = \GravityView_frontend::getInstance();
					$frontend->context_view_id = $value;
					break;
				case '\GravityView_frontend::is_gravityview_post_type':
					\GravityView_frontend::getInstance()->setIsGravityviewPostType( $value );
					break;
				case '\GravityView_frontend::post_has_shortcode':
					\GravityView_frontend::getInstance()->setPostHasShortcode( $value );
					break;
				case '\GravityView_frontend::gv_output_data':
					\GravityView_frontend::getInstance()->setGvOutputData( $value );
					break;
				case '\GravityView_View::paging':
					\GravityView_View::getInstance()->setPaging( $value );
					break;
				case '\GravityView_View::sorting':
					\GravityView_View::getInstance()->setSorting( $value );
					break;
				case '\GravityView_frontend::is_search':
					\GravityView_frontend::getInstance()->setIsSearch( $value );
					break;
				case '\GravityView_frontend::single_entry':
					\GravityView_frontend::getInstance()->setSingleEntry( $value );
					break;
				case '\GravityView_frontend::entry':
					\GravityView_frontend::getInstance()->setEntry( $value );
					break;
				case '\GravityView_View::_current_entry':
					\GravityView_View::getInstance()->setCurrentEntry( $value );
					break;
				case 'wp_actions[loop_start]':
					global $wp_actions;
					$wp_actions['loop_start'] = $value;
					break;
				case 'wp_query::in_the_loop':
					global $wp_query;
					$wp_query->in_the_loop = $value;
					break;
			endswitch;
		}
	}

	/**
	 * Hydrates the legacy context globals as needed.
	 *
	 * @see self::push() for format.
	 *
	 * @return void
	 */
	public static function load( $configuration ) {
		foreach ( (array)$configuration as $key => $value ) {
			switch ( $key ):
				case 'view':
					$views = new \GV\View_Collection();
					$views->add( $value );

					self::thaw( array(
						'\GravityView_View::atts' => $value->settings->as_atts(),
						'\GravityView_View::view_id' => $value->ID,
						'\GravityView_View::back_link_label' => $value->settings->get( 'back_link_label', null ),
						'\GravityView_View::form' => $value->form ? $value->form->form : null,
						'\GravityView_View::form_id' => $value->form ? $value->form->ID : null,

						'\GravityView_View_Data::views' => $views,
						'\GravityView_frontend::gv_output_data' => \GravityView_View_Data::getInstance(),
						'\GravityView_frontend::context_view_id' => $value->ID,
					) );
					break;
				case 'post':
					$has_shortcode = false;
					foreach ( \GV\Shortcode::parse( $value->post_content ) as $shortcode ) {
						if ( $shortcode->name == 'gravityview' ) {
							$has_shortcode = true;
							break;
						}
					}
					self::thaw( array(
						'\GravityView_View::post_id' => $value->ID,
						'\GravityView_frontend::post_id' => $value->ID,
						'\GravityView_frontend::is_gravityview_post_type' => $value->post_type == 'gravityview',
						'\GravityView_frontend::post_has_shortcode' => $has_shortcode,
					) );
					break;
				case 'views':
					self::thaw( array(
						'\GravityView_View_Data::views' => $value,
						'\GravityView_frontend::gv_output_data' => \GravityView_View_Data::getInstance(),
					) );
					break;
				case 'entries':
					self::thaw( array(
						'\GravityView_View::entries' => array_map( function( $e ) { return $e->as_entry(); }, $value->all() ),
						'\GravityView_View::total_entries' => $value->total(),
					) );
					break;
				case 'entry':
					self::thaw( array(
						'\GravityView_frontend::single_entry' => $value->ID,
						'\GravityView_frontend::entry' => $value->ID,
						'\GravityView_View::_current_entry' => $value->as_entry(),
					) );
					break;
				case 'request':
					self::thaw( array(
						'\GravityView_View::context' => (
							$value->is_entry() ? 'single' :
								$value->is_edit_entry() ? 'edit' :
									$value->is_view() ? 'directory': null
						),
						'\GravityView_frontend::is_search' => $value->is_search(),
					) );

					if ( ! $value->is_entry() ) {
						self::thaw( array(
							'\GravityView_frontend::single_entry' => 0,
							'\GravityView_frontend::entry' => 0
						) );
					}
					break;
				case 'paging':
					self::thaw( array(
						'\GravityView_View::paging' => $value,
					) );
					break;
				case 'sorting':
					self::thaw( array(
						'\GravityView_View::sorting' => $value,
					) );
					break;
				case 'in_the_loop':
					self::thaw( array(
						'wp_query::in_the_loop' => $value,
						'wp_actions[loop_start]' => $value ? 1 : 0,
					) );
					break;
			endswitch;
		}
	}

	/**
	 * Resets the global state completely.
	 *
	 * Use with utmost care, as filter and action callbacks
	 *  may be added again.
	 *
	 * Does not touch the context stack.
	 *
	 * @return void
	 */
	public static function reset() {
		\GravityView_View::$instance = null;
		\GravityView_frontend::$instance = null;
		\GravityView_View_Data::$instance = null;

		global $wp_query, $wp_actions;

		$wp_query->in_the_loop = false;
		$wp_actions['loop_start'] = 0;
	}
}


/** Add some global fix for field capability discrepancies. */
add_filter( 'gravityview/configuration/fields', function( $fields ) {
	if ( empty( $fields  ) ) {
		return $fields;
	}

	/**
	 * Each view field is saved in a weird capability state by default.
	 *
	 * With loggedin set to false, but a capability of 'read' it introduces
	 *  some logical issues and is not robust. Fix this behavior throughout
	 *  core by making sure capability is '' if log in is not required.
	 *
	 * Perhaps in the UI a fix would be to unite the two fields (as our new
	 *  \GV\Field class already does) into one dropdown:
	 *
	 * Anyone, Logged In Only, ... etc. etc.
	 *
	 * The two "settings" should be as tightly coupled as possible to avoid
	 *  split logic scenarios. Uniting them into one field is the way to go.
	 */

	foreach ( $fields as $position => &$_fields ) {

		if ( empty( $_fields ) ) {
			continue;
		}

		foreach ( $_fields as $uid => &$_field ) {
			if ( ! isset( $_field['only_loggedin'] ) ) {
				continue;
			}
			/** If we do not require login, we don't require a cap. */
			$_field['only_loggedin'] != '1' && ( $_field['only_loggedin_cap'] = '' );
		}
	}
	return $fields;
} );


/** Add a future fix to make sure field configurations include the form ID. */
add_filter( 'gravityview/view/fields/configuration', function( $fields, $view ) {
	if ( ! $view || empty( $fields ) ) {
		return $fields;
	}

	if ( ! $view->form || ! $view->form->ID ) {
		return $fields;
	}

	/**
	 * In order to instantiate the correct \GV\Field implementation
	 *  we need to provide a form_id inside the configuration.
	 *
	 * @todo Make sure this actually happens in the admin side
	 *  when saving the views.
	 */
	foreach ( $fields as $position => &$_fields ) {
		if ( empty( $_fields ) ) {
			continue;
		}

		foreach ( $_fields as $uid => &$_field ) {
			if ( ! empty( $_field['id'] ) && is_numeric( $_field['id'] ) && empty( $_field['form_id'] ) ) {
				$_field['form_id'] = $view->form->ID;
			}
		}
	}

	return $fields;
}, 10, 2 );


/** Make sure the non-configured notice is not output twice. */
add_action( 'gravityview/template/after', function( $gravityview = null ) {
	if ( defined( 'GRAVITYVIEW_FUTURE_CORE_ALPHA_ENABLED' ) && class_exists( '\GravityView_frontend' ) ) {
		global $wp_filter;

		if ( empty( $wp_filter['gravityview_after'] ) ) {
			return;
		}

		/** WordPress 4.6 and lower compatibility, when WP_Hook classes were still absent. */
		if ( is_array( $wp_filter['gravityview_after'] ) ) {
			if ( ! empty( $wp_filter['gravityview_after'][10] ) ) {
				foreach ( $wp_filter['gravityview_after'][10] as $function_key => $callback ) {
					if ( strpos( $function_key, 'context_not_configured_warning' ) ) {
						unset( $wp_filter['gravityview_after'][10][ $function_key ] );
					}
				}
			}
			return;
		}

		foreach ( $wp_filter['gravityview_after']->callbacks[10] as $function_key => $callback ) {
			if ( strpos( $function_key, 'context_not_configured_warning' ) ) {
				unset( $wp_filter['gravityview_after']->callbacks[10][ $function_key ] );
			}
		}
	}
} );
