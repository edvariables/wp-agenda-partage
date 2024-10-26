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

	static $can_duplicate = false; //can inherit
	private static $can_duplicate_post_types = [];

	public static function init() {
		static::$the_post_is_new = basename($_SERVER['PHP_SELF']) == 'post-new.php';
		
		if( static::$can_duplicate )
			self::$can_duplicate_post_types[] = static::post_type;
		
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
				break;
			case 'post.php' :
				add_action( 'post_submitbox_start', array( __CLASS__, 'addPostDuplicateButton') );
				break;
			case 'admin.php' :
				add_action( 'admin_action_njt_duplicate_page_save_as_new_post', array( __CLASS__, 'duplicateNewPageAction' ) );
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
			$meta_value = $post->post_content;
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
		$icon = ! array_key_exists ( 'icon', $field ) || ! $field['icon'] ? false : $field['icon'];
		$input = ! array_key_exists ( 'input', $field ) || ! $field['input'] ? '' : $field['input'];
		$input_type = ! array_key_exists ( 'type', $field ) || ! $field['type'] ? 'text' : $field['type'];
		$style = ! array_key_exists ( 'style', $field ) || ! $field['style'] ? '' : $field['style'];
		$class = ! array_key_exists ( 'class', $field ) || ! $field['class'] ? '' : $field['class'];
		$container_class = ! array_key_exists ( 'container_class', $field ) || ! $field['container_class'] ? '' : $field['container_class'];
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
		
		?><div class="<?php echo trim($container_class);?>"><?php

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

			default:
				if(!$input_type)
					$input_type = 'text';
				break;
		}
		
		if( $icon )
			$icon = Agdp::icon($icon) . ' ';
		else
			$icon = '';

		// Label , sous pour checkbox
		if($label && ! in_array( $input, ['label', 'link', 'checkbox'])) {
			echo '<label for="'.$name.'">' . $icon . htmlentities($label) . ' : </label>';
		}

		switch ($input) {
			////////////////
			case 'label':
				echo '<label id="'.$id.'" for="'.$name.'"'
					. ($class ? ' class="'.str_replace('"', "'", $class).'"' : '') 
					. ($style ? ' style="'.str_replace('"', "'", $style).'"' : '') 
					. ($input_attributes ? ' '.$input_attributes : '')
					. '>' . $icon . htmlentities($label).'</label>'
				;
				break;

			////////////////
			case 'link':
				echo '<label id="'.$id.'" for="'.$name.'"'
					. ($class ? ' class="'.str_replace('"', "'", $class).'"' : '') 
					. ($style ? ' style="'.str_replace('"', "'", $style).'"' : '') 
					. ($input_attributes ? ' '.$input_attributes : '')
					. '>' . $icon . $label.'</label>';
				break;

			////////////////
			case 'textarea':
				if( $is_array_field && is_array($val) )
					$val = implode("\n", $val);
				echo '<textarea id="'.$id.'" name="'.$name.'"'
					. ($readonly ? ' readonly ' : '')
					. ($class ? ' class="'.str_replace('"', "'", $class).'"' : '') 
					. ($style ? ' style="'.str_replace('"', "'", $style).'"' : '') 
					. ($input_attributes ? ' '.$input_attributes : '')
					.'>'
					. htmlentities($val).'</textarea>'
					. ($unit ? ' ' . $unit : '');;
				break;
			
			////////////////
			case 'tinymce':
				$editor_settings = ! array_key_exists ( 'settings', $field ) || ! $field['settings'] ? null : $field['settings'];
				$editor_settings = wp_parse_args($editor_settings, array( //valeurs par défaut
					'textarea_rows' => 10,
					'readonly' => $readonly
				));
				wp_editor( $val, $id, $editor_settings);
				break;
			
			
			////////////////
			case 'select':
				echo '<select id="'.$id.'"'
					. ($class ? ' class="'.str_replace('"', "'", $class).'"' : '') 
					. ($style ? ' style="'.str_replace('"', "'", $style).'"' : '') 
					.' name="' . $name . '"'
					. ($readonly ? ' readonly ' : '')
					. ($input_attributes ? ' '.$input_attributes : '')
					. '">';

				$values = ! array_key_exists ( 'values', $field ) || ! $field['values'] ? false : $field['values'];
				if(is_array($values)){
					$is_associative = is_associative_array($values);
					foreach($values as $item_key => $item_label){
						if( is_a($item_label, 'WP_Post') ){
							$item_key = $item_label->ID;
							$item_label = $item_label->post_title;
						}
						elseif( ! $is_associative )
							$item_key = $item_label;
						echo sprintf('<option %s value="%s">%s</option>', selected( $val, $item_key, false ), $item_key, htmlentities($item_label));
					}
				}
				echo '</select>'
					. ($unit ? ' ' . $unit : '');
				break;
			
			////////////////
			case 'checkbox':
				echo '<label for="'.$name.'">';
				echo '<input id="'.$id.'" type="checkbox" name="'.$name.'" '
					. (($val && $val !== 'unchecked') || ( $val === '' && $default_val) ? ' checked="checked"' : '')
					. ($class ? ' class="'.str_replace('"', "'", $class).'"' : '') 
					. ($style ? ' style="'.str_replace('"', "'", $style).'"' : '') 
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
					. ($class ? ' class="'.str_replace('"', "'", $class).'"' : '') 
					. ($style ? ' style="'.str_replace('"', "'", $style).'"' : '') 
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
					. ($class ? ' class="'.str_replace('"', "'", $class).'"' : '') 
					. ($style ? ' style="'.str_replace('"', "'", $style).'"' : '') 
					. '>' . $options
					. ' </select>';
				break;*/
			case 'time':
				echo '<input id="'.$id.'"'
					. ' type="' . $input_type .'"'
					. ' name="'.$name.'"'
					. ' value="'.htmlentities($val) .'"'
					. ($class ? ' class="'.str_replace('"', "'", $class).'"' : '') 
					. ($style ? ' style="'.str_replace('"', "'", $style).'"' : '')
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
				//TODO phone, email, checkbox, number, int, bool, yes|no, ...
				if( ! $val && $default_val) $val = $default_val;
				echo '<input id="'.$id.'"'
					. ' type="' . $input_type .'"'
					. ' name="'.$name.'"'
					. ' value="'.htmlentities($val) .'"'
					. ($class ? ' class="'.str_replace('"', "'", $class).'"' : '') 
					. ($style ? ' style="'.str_replace('"', "'", $style).'"' : '')
					. ($readonly ? ' readonly ' : '')
					. ($input_attributes ? ' '.$input_attributes : '')
					. '/>'
					. ($unit ? ' ' . $unit : '');
				break;
		}
		
		$need_label = ! in_array( $input, ['input', 'checkbox', 'textarea'] );
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
	
		
		?></div><?php
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
			if(!isset($field['type']) || $field['type'] !== 'label'){
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
						$value = trim($value, "\r\t ");
						if($value)
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
	
	/**
	 * Duplicate
	 * Sources : https://plugins.trac.wordpress.org/browser/wp-duplicate-page, https://plugins.trac.wordpress.org/browser/duplicate-pp/
	 */
	/**
	 * duplicateButtonLink in posts list
	 */
	public static function duplicateButtonLink( $actions, $post ) {
		if( in_array( $post->post_type, self::$can_duplicate_post_types ) ){
			$duplicateTextLink             = 'Dupliquer';
			$actions['njt_duplicate_page'] = sprintf(
				'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
				self::getDuplicateLink( $post->ID ),
				esc_attr( __( 'Dupliquer' ) ),
				/* translators: %s: Button Duplicate text. */
				esc_html( sprintf( __( ' %s ' ), $duplicateTextLink ) )
			);
		}
		return $actions;
	}
	/**
	 * getDuplicateLink
	 */
	public static function getDuplicateLink( $postId = 0 ) {

		// if ( ! Utils::isCurrentUserAllowedToCopy() ) {
			// return;
		// }

		if ( ! $post = get_post( $postId ) ) {
			return;
		}

		// if ( ! Utils::checkPostTypeDuplicate( $post->post_type ) ) {
			// return;
		// }

		$action_name = 'njt_duplicate_page_save_as_new_post';
		$action      = '?action=' . $action_name . '&amp;post=' . $post->ID;
		$postType    = get_post_type_object( $post->post_type );

		if ( ! $postType ) {
			return;
		}

		return wp_nonce_url( admin_url( 'admin.php' . $action ), 'njt-duplicate-page_' . $post->ID );
	}
	/**
	 * Duplicate Post
	 */
	public static function duplicateNewPageAction() {
		global $pagenow;
		if ( ! current_user_can('edit_posts') ) 
			wp_die('No allowed to duplicate');
		
		if ( ! ( isset( $_GET['post'] ) || isset( $_POST['post'] ) || ( isset( $_REQUEST['action'] ) && 'njt_duplicate_page_save_as_new_post' === $_REQUEST['action'] ) ) ) {
			wp_die( esc_html__( 'No post to duplicate!', 'wp-duplicate-page' ) );
		}

		// Get the original post
		// $postId = ( isset( $_GET['post'] ) ? sanitize_text_field( $_GET['post'] ) : sanitize_text_field( $_POST['post'] ) );
		
		/*
		 * get the original post id
		 */
		$dpp_post_id = (isset($_GET['post']) ? absint(sanitize_text_field($_GET['post'])) : absint(sanitize_text_field($_POST['post'])));

		check_admin_referer( 'njt-duplicate-page_' . $dpp_post_id );
		
		global $wpdb;

		/*
		 * and all the original post data then
		 */
		$dpp_post = get_post($dpp_post_id);

		/*
		 * if you don't want the current user to be the new post author,
		 * then change the next couple of lines to this: $new_post_author = $post->post_author;
		 */
		$dpp_current_user = wp_get_current_user();
		$dpp_new_post_author = $dpp_current_user->ID;

		/*
		 * if post data exists, create the post duplicate
		 */
		if (isset($dpp_post) && $dpp_post != null) {

			/*
			 * new post data array
			 */
			$dpp_args = array(
				'comment_status' => $dpp_post->comment_status,
				'ping_status'    => $dpp_post->ping_status,
				'post_author'    => $dpp_new_post_author,
				'post_content'   => $dpp_post->post_content,
				'post_excerpt'   => $dpp_post->post_excerpt,
				'post_name'      => sprintf('%s_copie', $dpp_post->post_name),
				'post_parent'    => $dpp_post->post_parent,
				'post_password'  => $dpp_post->post_password,
				'post_status'    => 'draft',
				'post_title'     => sprintf('%s (copie)', $dpp_post->post_title),
				'post_type'      => $dpp_post->post_type,
				'to_ping'        => $dpp_post->to_ping,
				'menu_order'     => $dpp_post->menu_order
			);

			/*
			 * insert the post by wp_insert_post() function
			 */
			$dpp_new_post_id = wp_insert_post($dpp_args);

			/*
			 * get all current post terms and set them to the new post draft
			 */
			$dpp_taxonomies = get_object_taxonomies($dpp_post->post_type); // returns an array of taxonomy names for the post type, e.g., array("category", "post_tag");
			foreach ($dpp_taxonomies as $ddp_taxonomy) {
				$dpp_post_terms = wp_get_object_terms($dpp_post_id, $ddp_taxonomy, array('fields' => 'slugs'));
				wp_set_object_terms($dpp_new_post_id, $dpp_post_terms, $ddp_taxonomy, false);
			}

			// Duplicate all post meta
			$dpp_post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$dpp_post_id");
			if (count($dpp_post_meta_infos) != 0) {
				foreach ($dpp_post_meta_infos as $dpp_meta_info) {
					$dpp_meta_key = $dpp_meta_info->meta_key;
					if ($dpp_meta_key == '_wp_old_slug') continue;
					$dpp_meta_value = $dpp_meta_info->meta_value;
					update_post_meta($dpp_new_post_id, $dpp_meta_key, $dpp_meta_value); // Copy the post meta to the new post
				}
			}

			/*
			 * finally, redirect to the edit post screen for the new draft
			 */
			$dpp_all_post_types = get_post_types([], 'names');

			foreach ($dpp_all_post_types as $dpp_key => $dpp_value) {
				$dpp_names[] = $dpp_key;
			}

			$dpp_current_post_type =  get_post_type($dpp_post_id);
			
			$postType        = $dpp_post->post_type;
			$newPostId       = $dpp_new_post_id;
			$redirect        = sprintf('/wp-admin/post.php?post=%d&action=edit">',$dpp_new_post_id);
			
			wp_safe_redirect( $redirect );

			exit;
		} else {
			wp_die('Failed. Not Found Post: ' . $dpp_post_id);
		}
	}
	
	/**
	 * addPostDuplicateButton in post.php
	 */
	public static function addPostDuplicateButton(){
		global $post;
		if( ! $post || ! $post->ID )
			return;
		if( in_array( $post->post_type, self::$can_duplicate_post_types ) ){
			$duplicateTextLink = 'Dupliquer';
			
			$html  = '<div>';
			$html .= sprintf(
				'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
				self::getDuplicateLink( $post->ID ),
				esc_attr( __( 'Dupliquer' ) ),
				/* translators: %s: Button Duplicate text. */
				esc_html( sprintf( __( ' %s ' ), $duplicateTextLink ) )
			);
			$html .= '</div>';
		}
        echo $html;
	}
}
?>