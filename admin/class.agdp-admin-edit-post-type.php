<?php

/**
 * AgendaPartage Admin -> Edit -> Post_type implement
 * Custom post type for WordPress in Admin UI.
 * 
 * Classe d'implémentation pour l'édition d'un post quel que soit son type 
 */
abstract class Agdp_Admin_Edit_Post_Type {

	const post_type = false; //must inherit
	
	private static $initiated = false;

	static $the_post_is_new = false;

	public static $can_duplicate = false; //can inherit
	static $can_import = true; //can inherit
	static $can_export = true; //can inherit
	private static $post_types_capabilities = [];
	private static $capabilities = [
		'duplicate', 
		'import', 
		'export'
	];
	private static $actions = [
		'duplicate' => 'Dupliquer',
		'export' => 'Exporter'
	];

	public static function init() {
		static::$the_post_is_new = basename($_SERVER['PHP_SELF']) == 'post-new.php';
		
		self::$post_types_capabilities[static::post_type] = [];
		
		foreach( self::$capabilities as $cap ){
			$var = 'can_' . $cap;
			self::$post_types_capabilities[static::post_type][$cap] = static::$$var;
		}
		
		if( ! self::$initiated ){
			add_action( 'current_screen', array( __CLASS__, 'init_hooks' ));
			//self::init_hooks();
			self::$initiated = true;
		}
	}
	
	public static function init_hooks() {
		global $pagenow;
		if( current_user_can('edit_posts') ){
			switch ( $pagenow ){
			case 'edit.php' :
				add_filter( 'post_row_actions', array( __CLASS__, 'duplicateButtonLink' ), 10, 2 );
				add_filter( 'page_row_actions', array( __CLASS__, 'duplicateButtonLink' ), 10, 2 );
				$action = 'export';
				foreach(self::$post_types_capabilities as $post_type => $capabilities){
					if( $capabilities[$action] )
						add_filter( 'bulk_actions-edit-'.$post_type, array( __CLASS__, 'on_bulk_actions_edit' ), 10, 1 );
						add_filter( 'handle_bulk_actions-edit-'.$post_type, array( __CLASS__, 'on_handle_bulk_actions_edit'), 10, 3 );
				}
				break;
			case 'post.php' :
				add_action( 'post_submitbox_start', array( __CLASS__, 'add_post_actions_buttons') );
				break;
			case 'admin.php' :
				foreach( self::$actions as $action => $action_label ){
					add_action( sprintf('admin_action_%s', static::get_action_name( $action ))
						, array( __CLASS__, 'on_action_post_' . $action ) );
				}
				break;
			}
		}
	}
	
	/**
	 * HTML render in metaboxes
	 */
	public static function metabox_html($fields, $post, $metabox, $parent_field = null){
		if( ! is_array($fields) )
			return;
		foreach ($fields as $field) 
			static::metabox_field_html($field, $post, $metabox, $parent_field);
	}
	/**
	 * HTML render of a metaboxe field
	 */
	public static function metabox_field_html($field, $post, $metabox, $parent_field = null){
		$name = empty($field['name']) ? '' : $field['name'];
		$is_array_field = strpos( $name, '[]' ) !== false;//TODO pour autre que textarea
		if($parent_field !== null)
			$name = sprintf($name, $parent_field['name']);
		if($name === 'post_content')
			$meta_value = $post ? $post->post_content : '';
		elseif( ! $post )
			$meta_value = '';
		elseif( $name ){
			if( is_a($post, 'WP_Term'))
				$meta_value = get_term_meta($post->term_id, $name, true);
			elseif( $name && $is_array_field ){
				$meta_key = str_replace('[]', '', $name);
				$meta_value = get_post_meta($post->ID, $meta_key, false);
			}
			else
				$meta_value = get_post_meta($post->ID, $name, true);
		}
		else
			$meta_value = '';
		$id = ! array_key_exists ( 'id', $field ) || ! $field['id'] ? $name : $field['id'];
		if($parent_field !== null){
			$parent_id = array_key_exists('id', $parent_field) ? $parent_field['id'] : $parent_field['name'];
			if( $parent_id )
				$id = sprintf('%s.%s', $id, $parent_id); //TODO A vérifier à l'enregistrement
		}
		$val = ! array_key_exists ( 'value', $field ) || ! $field['value'] ? $meta_value : $field['value'];
		$default_val = ! array_key_exists ( 'default', $field ) || ! $field['default'] ? null : $field['default'];
		$label = ! array_key_exists ( 'label', $field ) || ! $field['label'] ? false : $field['label'];
		$label_class = ! array_key_exists ( 'label_class', $field ) || ! $field['label_class'] ? false : $field['label_class'];
		$label_toggler = ! array_key_exists ( 'label_toggler', $field ) || ! $field['label_toggler'] ? false : $field['label_toggler'];
		$icon = ! array_key_exists ( 'icon', $field ) || ! $field['icon'] ? false : $field['icon'];
		$input = ! array_key_exists ( 'input', $field ) || ! $field['input'] ? '' : $field['input'];
		$input_type = ! array_key_exists ( 'type', $field ) || ! $field['type'] ? 'text' : $field['type'];
		$style = ! array_key_exists ( 'style', $field ) || ! $field['style'] ? '' : $field['style'];
		$class = ! array_key_exists ( 'class', $field ) || ! $field['class'] ? '' : $field['class'];
		$container_class = ! array_key_exists ( 'container_class', $field ) || ! $field['container_class'] ? '' : $field['container_class'];
		$container_tag = ! array_key_exists ( 'container_tag', $field ) || ! $field['container_tag'] ? 'div' : $field['container_tag'];
		$input_attributes = ! array_key_exists ( 'input_attributes', $field ) || ! $field['input_attributes'] ? '' : $field['input_attributes'];
		$readonly = ! array_key_exists ( 'readonly', $field ) || ! $field['readonly'] ? false : $field['readonly'];
		$unit = ! array_key_exists ( 'unit', $field ) || ! $field['unit'] ? false : $field['unit'];
		$learn_more = ! array_key_exists ( 'learn-more', $field ) || ! $field['learn-more'] ? false : $field['learn-more'];
		if( $learn_more && ! is_array($learn_more))
			$learn_more = [$learn_more];
		$comments = ! array_key_exists ( 'comments', $field ) || ! $field['comments'] ? false : $field['comments'];
		if( $comments && ! is_array($comments))
			$comments = [$comments];
		$warning = ! array_key_exists ( 'warning', $field ) || ! $field['warning'] ? false : $field['warning'];
		if( $warning && ! is_array($warning))
			$warning = [$warning];
		
		$container_class .= ' agdp-metabox-row';
		$container_class .= ' is' . ( current_user_can('manage_options') ? '' : '_not') . '_admin';
		if($parent_field != null)
			$container_class .= ' agdp-metabox-subfields';

		$attributes_str = '';
		if( is_array($input_attributes) ){
			foreach($input_attributes as $key=>$value){
				if( $attributes_str )
					$attributes_str .= ' ';
				if( is_int($key) )
					$attributes_str .= $value;
				else
					$attributes_str .= $key . '="' . esc_attr( $value ) . '"';
			}
			$input_attributes = $attributes_str;
		}
		
		?><<?php echo trim($container_tag);?> class="<?php echo trim($container_class);?>"><?php

		switch ($input_type) {
			case 'number' :
			case 'int' :
				$input = 'text';
				$input_type = 'number';
				break;

			case 'checkbox' :
			case 'bool' :
				$input = 'checkbox';
				break;

			case 'toggle' :
				$input = 'none';
				break;

			default:
				if( ! $input_type)
					$input_type = 'text';
				break;
		}
		
		if( $icon )
			$icon = Agdp::icon($icon) . ' ';
		else
			$icon = '';
		
		if( is_array($unit) )
			$unit = implode("\n", $unit );

		// Label, sous pour checkbox
		if($label && ! in_array( $input, ['label', 'link', 'checkbox'])) {
			$label = htmlentities($label);
			if( $label_toggler || $input_type === 'toggle' ){
				$label_class .= 'toggle-trigger' . ( $val ? ' active' : '' );
				$label = sprintf('<a href="#">%s : </a>', $label);
			}
			else
				$label .= ': ';
			echo sprintf('<label for="%s" %s>%s%s</label>'
				, $name
				, $label_class ? sprintf(' class="%s"', $label_class) : ''
				, $label
				, $icon
			);
		}

		switch ($input) {
			case 'none':
				if( $unit )
					echo $unit;
				break;
			////////////////
			case 'label':
				echo '<label id="'.$id.'" for="'.$name.'"'
					. ($class ? ' class="'.esc_attr( $class).'"' : '') 
					. ($style ? ' style="'.esc_attr( $style).'"' : '') 
					. ($input_attributes ? ' '.$input_attributes : '')
					. '>' . $icon . htmlentities($label).'</label>'
				;
				break;

			////////////////
			case 'link':
				echo '<label id="'.$id.'" for="'.$name.'"'
					. ($class ? ' class="'.esc_attr( $class).'"' : '') 
					. ($style ? ' style="'.esc_attr( $style).'"' : '') 
					. ($input_attributes ? ' '.$input_attributes : '')
					. '>' . $icon . $label.'</label>';
				break;

			////////////////
			case 'textarea':
				if( ! $val && $default_val) $val = $default_val;
				if( /* $is_array_field && */ is_array($val) ){
					//debug_log(__FUNCTION__ . ' is_array($val)', $val);
					$val = implode("\n", $val);
				}
				echo '<textarea id="'.$id.'" name="'.$name.'"'
					. ($readonly ? ' readonly ' : '')
					. ($class ? ' class="'.esc_attr( $class).'"' : '') 
					. ($style ? ' style="'.esc_attr( $style).'"' : '') 
					. ($input_attributes ? ' '.$input_attributes : '')
					. ($input_type === 'json' ? ' data-type="json"' : '')
					.'>'
					. htmlentities($val).'</textarea>'
					. ($unit ? ' ' . $unit : '');;
				break;
			
			////////////////
			case 'tinymce':
				if( ! $val && $default_val) $val = $default_val;
				$editor_settings = ! array_key_exists ( 'settings', $field ) || ! $field['settings'] ? null : $field['settings'];
				$editor_settings = wp_parse_args($editor_settings, array( //valeurs par défaut
					'textarea_rows' => 10,
					'readonly' => $readonly
				));
				wp_editor( $val, $id, $editor_settings);
				break;
			
			
			////////////////
			case 'select':
			case 'multiselect':
			case 'multicheckbox':
				$values = ! array_key_exists ( 'values', $field ) || ! $field['values'] ? false : $field['values'];
				if( $input === 'select'
				 || ( $input === 'multiselect' && is_array($values) && count($values) > 4)
				){
					echo '<select id="'.$id.'"'
						. ($class ? ' class="'.esc_attr($class).'"' : '') 
						. ($style ? ' style="'.esc_attr($style).'"' : '') 
						// . ($val ? ' value="'.esc_attr($val).'"' : '') 
						.' name="' . $name
							. ($input === 'multiselect' ? '[]' : '')
							. '"'
						. ($readonly ? ' readonly ' : '')
						. ($input === 'multiselect' ? ' multiple ' : '')
						. ($input_attributes ? ' '.$input_attributes : '')
						. '">';
					if(is_array($values)){
						$is_associative = is_associative_array($values);
						foreach($values as $item_key => $item_label){
							if( is_a($item_label, 'WP_Post') ){
								$item_key = $item_label->ID;
								$item_label = $item_label->post_title;
							}
							elseif( ! $is_associative )
								$item_key = $item_label;
							
							if( is_array($val) )
								$selected_item = in_array( $item_key, $val ) ? selected( '1', '1', false ) : '';
							else
								$selected_item = selected( $val, $item_key, false );
							
							echo sprintf('<option %s value="%s">%s</option>', $selected_item, $item_key, htmlentities($item_label));
						}
					}
					echo '</select>'
						. (is_array($val) && count($val) ? ' <span>' . implode(', ', $val) . '</span>' : '')
						. ($unit ? ' <span>' . $unit : '</span>')
					;
				}
				else { //multicheckbox || multiselect / checkboxes
					echo '<span id="'.$id.'"'
						. ' class="multiselect-wrap ' . ($class ? ' '.esc_attr($class) : '') . '"'
						. ($style ? ' style="'.esc_attr($style).'"' : '') 
						. '">';
					if(is_array($values)){
						$is_associative = is_associative_array($values);
						foreach($values as $item_key => $item_label){
							if( is_a($item_label, 'WP_Post') ){
								$item_key = $item_label->ID;
								$item_label = $item_label->post_title;
							}
							elseif( ! $is_associative )
								$item_key = $item_label;
							
							if( is_array($val) )
								$selected_item = in_array( $item_key, $val );
							else
								$selected_item = selected( $val, $item_key, false );
							
							echo sprintf('<label><input type="checkbox" name="%s[]" %s%s%s value="%s">%s</label>'
								, $name
								, $selected_item ? ' checked="checked"' : ''
								, ($readonly ? ' readonly ' : '')
								, ($input_attributes ? ' '.$input_attributes : '')
								, $item_key
								, htmlentities($item_label));
						}
					}
					echo '</span>'
						. ($unit ? ' <span>' . $unit : '</span>');
				}
				break;
			
			////////////////
			case 'checkbox':
				echo '<label for="'.$id.'">';
				echo '<input id="'.$id.'" type="checkbox" name="'.$name.'" '
					. (($val && $val !== 'unchecked') || ( $val === '' && $default_val) ? ' checked="checked"' : '')
					. ($class ? ' class="'.esc_attr( $class).'"' : '') 
					. ($style ? ' style="'.esc_attr( $style).'"' : '') 
					. ($readonly ? '  onclick="return false" ' : '')
					. ($input_attributes ? ' '.$input_attributes : '')
					. ' value="1" />';
				echo htmlentities($label) . '</label>'
					. ($unit ? ' ' . $unit : '');
				break;
			
			////////////////
			case 'date':
				//<input class="wpcf7-form-control wpcf7-date wpcf7-validates-as-required wpcf7-validates-as-date" aria-required="true" aria-invalid="false" value="" type="date" name="event-date-debut">
				$class = " wpcf7-date" . ($class ? " $class" : "");
				echo '<input id="'.$id.'" type="date" name="'.$name.'" '
					. ($val ? ' value="'.htmlentities($val) .'"' : '')
					. ($class ? ' class="'.esc_attr( $class).'"' : '') 
					. ($style ? ' style="'.esc_attr( $style).'"' : '') 
					. ($readonly ? ' readonly ' : '')
					. ($input_attributes ? ' '.$input_attributes : '')
					. ' />'
					. ($unit ? ' ' . $unit : '');
				break;
			
			////////////////
			/*case 'time':
				//<input class="wpcf7-form-control wpcf7-date wpcf7-validates-as-required wpcf7-validates-as-date" aria-required="true" aria-invalid="false" value="" type="date" name="event-date-debut">
				$class = " time-picker" . ($class ? " $class" : "");
				$options = '';
				for($h = 0; $h < 24; $h++)
					for($m = 0; $m < 4; $m++){
						$option = sprintf("%02d", $h) . ':' .sprintf("%02d", $m*15);
						$options .= '<option value="$option"'. ($option == $val ? ' selected' : '') . ">$option</option>";
					}
				echo '<select id="'.$id.'" name="'.$name.'" '
					. ($val ? ' value="'.htmlentities($val) .'"' : '')
					. ($class ? ' class="'.esc_attr( $class).'"' : '') 
					. ($style ? ' style="'.esc_attr( $style).'"' : '') 
					. '>' . $options
					. ' </select>';
				break;*/
			case 'time':
				echo '<input id="'.$id.'"'
					. ' type="' . $input_type .'"'
					. ' name="'.$name.'"'
					. ' value="'.htmlentities($val) .'"'
					. ($class ? ' class="'.esc_attr( $class).'"' : '') 
					. ($style ? ' style="'.esc_attr( $style).'"' : '')
					. ' placeholder="hh:mm"'
					. ' maxlength="5" size="5"'
					. ($readonly ? ' readonly ' : '')
					. ($input_attributes ? ' '.$input_attributes : '')
					. '/>'
					. ($unit ? ' ' . $unit : '');
				break;
			
			////////////////
			case 'input':
			default:
				if( $input === '' )
					$input = 'input';
				//TODO phone, email, checkbox, number, int, bool, yes|no, ...
				if( ! $val && $default_val) $val = $default_val;
				echo '<input id="'.$id.'"'
					. ' type="' . $input_type .'"'
					. ' name="'.$name.'"'
					. ' value="'.htmlentities($val) .'"'
					. ($class ? ' class="'.esc_attr( $class).'"' : '') 
					. ($style ? ' style="'.esc_attr( $style).'"' : '')
					. ($readonly ? ' readonly ' : '')
					. ($input_attributes ? ' '.$input_attributes : '')
					. '/>'
					. ($unit ? ' ' . $unit : '');
				break;
		}
		
		$need_label = ! in_array( $input, ['input', 'checkbox', 'textarea', 'tinymce'] );
		if($learn_more)
			foreach($learn_more as $comment){
				echo '<br>';
				if( $need_label )
					echo '<label></label>';
				?><span class="dashicons-before dashicons-welcome-learn-more"><?=$comment?></span><?php
			}
		
		if($comments)
			foreach($comments as $comment){
				echo '<br>';
				if( $need_label )
					echo '<label></label>';
				?><span><?=$comment?></span><?php
			}
		
		if($warning)
			foreach($warning as $comment){
				echo '<br>';
				if( $need_label )
					echo '<label></label>';
				?><span class="dashicons-before dashicons-warning"><?=$comment?></span><?php
			}
	

		//sub fields
		if( array_key_exists('fields', $field) && is_array($field['fields'])){
			self::metabox_html($field['fields'], $post, $metabox, $field);
		}
	
		
		?></<?php echo trim($container_tag);?>><?php
	}
	
	/**
	* Should be overrided
	**/
	abstract public static function get_metabox_all_fields();
	
	/**
	 * Save metaboxes' input values
	 * Field can contain sub fields
	 */
	public static function save_metaboxes($post_ID, $post, $parent_field = null){
		if($parent_field === null){
			$fields = static::get_metabox_all_fields();
		}
		else
			$fields = $parent_field['fields'];
		// debug_log(__FUNCTION__, $_POST, '', $fields );
		foreach ($fields as $field) {
			if( ! isset($field['type'] ) || $field['type'] !== 'label'){
				$name = $field['name'];
				if($parent_field !== null && isset($parent_field['name']))
					$name = sprintf($name, $parent_field['name']);//TODO check
				
				if( $is_array_field = strpos( $name, '[]' ) !== false){
					$name = substr($name, 0, strlen($name)-2);
				}
				// remember : a checkbox unchecked does not return any value
				if( array_key_exists($name, $_POST)){
					$val = $_POST[$name];
				}
				else {
					// TODO "remember : a checkbox unchecked does not return any value" so is 'default' = true correct ?
					// debug_log(__FUNCTION__
						// , $field
						// , (isset($field['input']) && ($field['input'] === 'checkbox' || $field['input'] === 'bool'))
						// , (isset($field['type'])  && ($field['type']  === 'checkbox' || $field['type']  === 'bool'))
					// );
					if(self::$the_post_is_new
					&& isset($field['default']) && $field['default'])
						$val = $field['default'];
					elseif( (isset($field['input']) && ($field['input'] === 'checkbox' || $field['input'] === 'bool'))
						 || (isset($field['type'])  && ($field['type']  === 'checkbox' || $field['type']  === 'bool')) ) {
						$val = '0';
					}
					else
						$val = null;
				}
				
				// if( isset($field['type'] )
				// && $field['type'] === 'json'
				// && $val
				// && is_string($val)
				// ){
					// $val = addcslashes($val, true);
				// }
						
				if( $is_array_field ){
					delete_post_meta($post_ID, $name);
					//TODO pour autre que textarea
					if( isset($field['input']) && $field['input'] === 'textarea' && is_array($val) )
						$val = $val[0];
					if( is_string($val) )
						$val = explode("\n", $val);
					elseif( ! is_array($val) )
						$val = [$val];
					foreach( $val as $value){
						if( $value
						 && ($value = trim($value, "\r\t ")) )
							add_post_meta($post_ID, $name, $value);
					}
				}
				else
					update_post_meta($post_ID, $name, $val);
			}

			//sub fields
			if(isset($field['fields']) && is_array($field['fields'])){
				self::save_metaboxes($post_ID, $post, $field);
			}
		}
		
		return false;
	}
	
	/*************************
	** Actions
	**********/
	/**
	 * on_bulk_actions_edit in posts list
	 */
	public static function on_bulk_actions_edit( $actions ) {
		$post_type = empty($_REQUEST['post_type']) ? false : $_REQUEST['post_type'];
		$action = 'export';
		$action_label = 'Exporter';
		if( static::has_cap( $action, $post_type ) ){
			$actions[$action] = $action_label;
		}
		return $actions;
	}
	/**
	 * on_handle_bulk_actions_edit
	 */
	public static function on_handle_bulk_actions_edit( $redirect, $doaction, $object_ids ) {
		// debug_log(__FUNCTION__, $redirect, $doaction, $object_ids );
		
		switch( $doaction ){
			case 'export' :

				$data = static::get_posts_export($object_ids );
				
				header('Content-Type: application/json');
				//echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
				echo json_encode($data);

				exit;
		}

		return $redirect;
	}
/**
	 * duplicateButtonLink in posts list
	 */
	public static function duplicateButtonLink( $actions, $post ) {
		$action = 'duplicate';
		if( static::has_cap( $action, $post->post_type ) ){
			$action_label = 'Dupliquer';
			$action_name = static::get_action_name( $action, $post->ID );
			$actions[$action_name] = static::get_post_action_button($action, $action_label, $action);
			if( ! $actions[$action_name] )
				unset($actions[$action_name]);
			// $actions[$action_name] = sprintf(
				// '<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
				// self::get_action_link( $action, $post->ID ),
				// esc_attr( __( 'Dupliquer' ) ),
				// /* translators: %s: Button Duplicate text. */
				// esc_html( sprintf( __( ' %s ' ), $action_label ) )
			// );
		}
		return $actions;
	}
	/**
	 * get_action_link
	 */
	public static function get_action_link( $action, $postId = 0 ) {

		// if ( ! Utils::isCurrentUserAllowedToCopy() ) {
			// return;
		// }

		if ( ! $post = get_post( $postId ) ) {
			return;
		}

		// if ( ! Utils::checkPostTypeDuplicate( $post->post_type ) ) {
			// return;
		// }
		$postType    = get_post_type_object( $post->post_type );

		if ( ! $postType ) {
			return;
		}

		$action_name = static::get_action_name( $action );
		$action_args      = '?action=' . $action_name . '&amp;post=' . $post->ID;

		// debug_log( __FUNCTION__, static::get_nonce_name( $action_name, $post->ID )  );
		return wp_nonce_url( admin_url( 'admin.php' . $action_args ), static::get_nonce_name( $action, $post->ID ) );
	}
	
	/**
	 * get_nonce_name
	 */
	public static function get_nonce_name( $action_name, $post_id ){
		return sprintf('%s_%d', $action_name, $post_id );
	}
		
	/**
	 * get_action_name
	 */
	public static function get_action_name( $action ){
		return sprintf('%s_post_%s', AGDP_TAG, $action );
	}
		
	/**
	 * check_rights
	 */
	public static function check_rights( $action, $post ){
		
		check_admin_referer( static::get_nonce_name( $action, $post ? $post->ID : 0 ) );
		
		if( ! static::has_cap( $action )
		|| ! current_user_can('manage_options') )
			check_admin_referer('dirty');
		
		return true;
	}
	
	/**
	 * Export Action
	 */
	public static function on_action_post_export( ) {
		$action = 'export';
		
		$post_id = empty($_REQUEST['post']) ? false : $_REQUEST['post'];
		
		$post = get_post( $post_id );

		static::check_rights( $action, $post );
		
		$data = static::get_posts_export( [ $post ] );
		
		header('Content-Type: application/json');
		//echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
		echo json_encode($data);
		
	}
	
	/**
	 * Export posts
	 */
	public static function get_posts_export( $posts ) {
		$action = 'export';
		
		$data = [];
		$post_type_taxonomies = [];
		$taxonomies = [];
		$used_terms = [];
		foreach( $posts as $post_id ){
			if( is_a($post_id, 'WP_Post') ){
				$post = $post_id;
				$post_id = $post->ID;
			}
			else
				$post = get_post( $post_id );
			
			$meta_input = get_post_meta($post->ID, '', true);//TODO ! true
			$metas = [];
			if( ! isset($meta_input['error'])){
				foreach($meta_input as $meta_name => $meta_value){
					if( $meta_name[0] === '_' )
						continue;
					
					// $meta_value = implode("\r\n", $meta_value);
					// if(is_serialized($meta_value))
						// $meta_value = var_export(unserialize($meta_value), true);
					if( is_array($meta_value) && count($meta_value) === 1 )
						$meta_value = $meta_value[0];
					$metas[ $meta_name ] = $meta_value;
				}
			}
			$post->post_password = null;
			$post_data = [
				'post' => $post,
				'metas' => $metas,
			];
			
			$post_type = $post->post_type;
			if( isset( $post_type_taxonomies[$post_type] ) )
				$post_taxonomies = $post_type_taxonomies[$post_type];
			else {
				$post_taxonomies = get_taxonomies([ 'object_type' => [$post_type] ], 'objects');
				$post_type_taxonomies[$post_type] = $post_taxonomies;
				$taxonomies = array_merge( $taxonomies, $post_taxonomies );
			}
			$post_terms = [];
			foreach( $post_taxonomies as $tax_name => $taxonomy ){
				$tax_terms = wp_get_post_terms($post_id, $tax_name);;
				foreach( $tax_terms as $term ){
					if( ! isset( $used_terms[ $term->term_id.'' ] ) )
						$used_terms[ $term->term_id.'' ] = $term;
					
					if( ! isset( $post_terms[$tax_name] ) )
						$post_terms[$tax_name] = [];
					if( ! isset( $post_terms[$tax_name][$term->term_id.''] ) )
						$post_terms[$tax_name][ $term->term_id.'' ] = $term->slug;
				}
			}
			if( $post_terms )
				$post_data['terms'] = $post_terms;
			
			$data[] = $post_data;
		}
		// echo json_encode( $data );
		
		if( $used_terms ){
			$data['terms'] = static::get_terms_export( $used_terms );
			if( $data['terms'] ){
				$data['taxonomies'] = [];
				foreach($used_terms as $term)
					if( ! isset($data['taxonomies'][$term->taxonomy]) ){
						$taxonomies[$term->taxonomy] = json_decode(json_encode($taxonomies[$term->taxonomy]), true);
						foreach( ['name_field_description', 
							'slug_field_description', 
							'parent_field_description', 
							'desc_field_description',
						 ] as $field )
							unset($taxonomies[$term->taxonomy]['labels'][$field]);
							
						$data['taxonomies'][$term->taxonomy] = $taxonomies[$term->taxonomy];
					}
			}
		}
		
		return $data;
		
	}
	
	/**
	 * Export taxonomies
	 */
	public static function get_terms_export( $terms ) {
		$action = 'export';
		
		$data = [];
		foreach( $terms as $term_id ){
			if( is_a($term_id, 'WP_Term') ){
				$term = $term_id;
				$term_id = $term->term_id;
			}
			else
				$term = get_term( $term_id );
			
			$meta_input = get_term_meta($term->term_id, '', true);//TODO ! true
			$metas = [];
			if( ! isset($meta_input['error'])){
				foreach($meta_input as $meta_name => $meta_value){
					if( $meta_name[0] === '_' )
						continue;
					if( is_array($meta_value) && count($meta_value) === 1 )
						$meta_value = $meta_value[0];
					$metas[ $meta_name ] = $meta_value;
				}
			}
			$data[] = [
				'term' => $term,
				'metas' => $metas,
			];
		}
		
		return $data;
	}
	
	/**
	 * Duplicate Post
	 */
	public static function on_action_post_duplicate() {
		global $pagenow;
		if ( ! current_user_can('edit_posts') ) 
			wp_die('No allowed to duplicate');
		
		if ( ! ( isset( $_GET['post'] ) || isset( $_POST['post'] ) 
			|| ( isset( $_REQUEST['action'] ) /* && 'njt_duplicate_page_save_as_new_post' === $_REQUEST['action'] */ ) ) ) {
			wp_die( esc_html__( 'No post to duplicate!', 'wp-duplicate-page' ) );
		}

		// Get the original post
		// $postId = ( isset( $_GET['post'] ) ? sanitize_text_field( $_GET['post'] ) : sanitize_text_field( $_POST['post'] ) );
		
		/*
		 * get the original post id
		 */
		$post_id = (isset($_GET['post']) ? absint(sanitize_text_field($_GET['post'])) : absint(sanitize_text_field($_POST['post'])));

		// debug_log( __FUNCTION__, static::get_nonce_name( 'duplicate', $post_id )  );
		check_admin_referer( static::get_nonce_name( 'duplicate', $post_id ) );
		
		global $wpdb;

		/*
		 * and all the original post data then
		 */
		$post = get_post($post_id);

		/*
		 * if you don't want the current user to be the new post author,
		 * then change the next couple of lines to this: $new_post_author = $post->post_author;
		 */
		$current_user = wp_get_current_user();
		$new_post_author = $current_user->ID;

		/*
		 * if post data exists, create the post duplicate
		 */
		if (isset($post) && $post != null) {

			/*
			 * new post data array
			 */
			$args = array(
				'comment_status' => $post->comment_status,
				'ping_status'    => $post->ping_status,
				'post_author'    => $new_post_author,
				'post_content'   => $post->post_content,
				'post_excerpt'   => $post->post_excerpt,
				'post_name'      => sprintf('%s_copie', $post->post_name),
				'post_parent'    => $post->post_parent,
				'post_password'  => $post->post_password,
				'post_status'    => 'draft',
				'post_title'     => sprintf('%s (copie)', $post->post_title),
				'post_type'      => $post->post_type,
				'to_ping'        => $post->to_ping,
				'menu_order'     => $post->menu_order
			);

			/*
			 * insert the post by wp_insert_post() function
			 */
			$new_post_id = wp_insert_post($args);

			/*
			 * get all current post terms and set them to the new post draft
			 */
			$taxonomies = get_object_taxonomies($post->post_type); // returns an array of taxonomy names for the post type, e.g., array("category", "post_tag");
			foreach ($taxonomies as $ddp_taxonomy) {
				$post_terms = wp_get_object_terms($post_id, $ddp_taxonomy, array('fields' => 'slugs'));
				wp_set_object_terms($new_post_id, $post_terms, $ddp_taxonomy, false);
			}

			// Duplicate all post meta
			$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
			if (count($post_meta_infos) != 0) {
				foreach ($post_meta_infos as $meta_info) {
					$meta_key = $meta_info->meta_key;
					if ( substr($meta_key, 0, strlen('_wp_old') ) === '_wp_old' )
						continue;
					//TODO addslashes ? (nécessaire pour les json mais fait disparaitre \ dans un title)
					$meta_value = addslashes($meta_info->meta_value);
					// debug_log(__FUNCTION__, $meta_key, $meta_value, get_debug_type( $meta_value ));
					
					update_post_meta($new_post_id, $meta_key, $meta_value); // Copy the post meta to the new post
				}
			}

			$current_post_type =  get_post_type($post_id);
			
			$postType        = $post->post_type;
			$newPostId       = $new_post_id;
			$redirect        = sprintf('/wp-admin/post.php?post=%d&action=edit">',$new_post_id);
			
			wp_safe_redirect( $redirect );

			exit;
		} else {
			wp_die('Failed. Not Found Post: ' . $post_id);
		}
	}
	
	/**
	 * add_post_actions_buttons in post.php
	 */
	public static function add_post_actions_buttons(){
		foreach( self::$actions as $action => $action_label ){
			$html = static::get_post_action_button( $action, $action_label, $action );
			if( $html )
				echo sprintf('<div id="%s-action">%s</div>', $action, $html);
		}
	}
	
	/**
	 * get_post_action_button
	 */
	public static function get_post_action_button( $action, $action_label, $cap){
		global $post;
		if( ! $post || ! $post->ID )
			return;
		if( ! static::has_cap( $cap, $post->post_type ) )
			return;
		
		$actionTextLink = $action_label;
		
		$html = sprintf(
			'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
			self::get_action_link( $action, $post->ID ),
			esc_attr( __( $action_label ) ),
			esc_html( sprintf( ' %s ', $actionTextLink ) )
		);
        return $html;
	}
	
	/**
	 * Has capability
	 */
	public static function has_cap( $capability, $post_type = null ) {
		if( $post_type === null )
			$post_type = static::post_type;
		
		if( isset( self::$post_types_capabilities[$post_type][$capability] ) )
			return self::$post_types_capabilities[$post_type][$capability];
		
		if( $post_type )
			return self::has_cap( $capability, false );
		
		return false;
	}
}
?>