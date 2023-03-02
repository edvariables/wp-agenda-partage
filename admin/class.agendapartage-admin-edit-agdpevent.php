<?php

/**
 * AgendaPartage Admin -> Edit -> Evenement
 * Custom post type for WordPress in Admin UI.
 * 
 * Edition d'un évènement
 * Définition des metaboxes et des champs personnalisés des Évènements 
 *
 * Voir aussi AgendaPartage_Evenement, AgendaPartage_Admin_Evenement
 */
class AgendaPartage_Admin_Edit_Evenement extends AgendaPartage_Admin_Edit_Post_Type {
	static $the_post_is_new = false;

	public static function init() {
		self::$the_post_is_new = basename($_SERVER['PHP_SELF']) == 'post-new.php';

		self::init_hooks();
	}
	
	public static function init_hooks() {
		
		if(basename($_SERVER['PHP_SELF']) === 'post.php'
		&& isset($_POST['post_type'])
			&& $_POST['post_type'] == AgendaPartage_Evenement::post_type 
		&& isset($_POST['post_status'])
			&& ! in_array($_POST['post_status'], [ 'trash', 'trashed' ]) ){
			add_filter( 'wp_insert_post_data', array(__CLASS__, 'wp_insert_post_data_cb'), 10, 2 );
		}
		
		if( in_array( basename($_SERVER['PHP_SELF']), [ 'revision.php', 'admin-ajax.php' ])) {
			add_filter( 'wp_get_revision_ui_diff', array(__CLASS__, 'on_wp_get_revision_ui_diff_cb'), 10, 3 );		
		}

		if(basename($_SERVER['PHP_SELF']) === 'post.php'
		&& array_key_exists('post_type', $_POST)
		&& $_POST['post_type'] == AgendaPartage_Evenement::post_type)
			add_action( 'save_post_agdpevent', array(__CLASS__, 'save_post_agdpevent_cb'), 10, 3 );

		add_action( 'add_meta_boxes_' . AgendaPartage_Evenement::post_type, array( __CLASS__, 'register_agdpevent_metaboxes' ), 10, 1 ); //edit
	}
	/****************/

	/**
	 * Callback lors de l'enregistrement d'un évènement.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function wp_insert_post_data_cb ($data, $postarr ){
		if($data['post_type'] != AgendaPartage_Evenement::post_type)
			return $data;
		
		if( array_key_exists('ev-create-user', $postarr) && $postarr['ev-create-user'] ){
			$data = self::create_user_on_save($data, $postarr);
		}
		
		//On sauve les révisions de meta_values
		AgendaPartage_Evenement_Edit::save_post_revision($postarr['post_ID'], $postarr, true);
		
		return $data;
	}
	/**
	 * Callback lors de l'enregistrement d'un évènement.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function save_post_agdpevent_cb ($post_id, $post, $is_update){
		if( $post->post_status == 'trashed' ){
			return;
		}

		self::save_metaboxes($post_id, $post);
	}

	/**
	 * Lors du premier enregistrement, on crée l'utilisateur
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function create_user_on_save ($data, $postarr){
		$email = array_key_exists('ev-email', $postarr) ? $postarr['ev-email'] : false;
		if(!$email || !is_email($email)) {
			AgendaPartage_Admin::add_admin_notice("Il manque l'adresse e-mail de l\'organisateur de l\'évènement ou elle est incorrecte.", 'error');
			return $data;
		}
		$user_name = array_key_exists('ev-organisateur', $postarr) ? $postarr['ev-organisateur'] : false;
		$user_login = array_key_exists('ev-create-user-slug', $postarr) ? $postarr['ev-create-user-slug'] : false;
	
		$user_data = array(
			'description' => 'Évènement ' . $data['post_title'],
		);
		$user = AgendaPartage_User::create_user_for_agdpevent($email, $user_name, $user_login, $user_data, 'subscriber');
		if( is_wp_error($user)) {
			AgendaPartage_Admin::add_admin_notice($user, 'error');
			return;
		}
		if($user){
			unset($_POST['ev-create-user']);
			unset($postarr['ev-create-user']);

			$data['post_author'] = $user->ID;
			//AgendaPartage_Admin::add_admin_notice(debug_print_backtrace(), 'warning');
			AgendaPartage_Admin::add_admin_notice("Désormais, l'auteur de la page est {$user->display_name}", 'success');
		}

		return $data;
	}

	/**
	 * Register Meta Boxes (boite en édition de l'évènement)
	 */
	public static function register_agdpevent_metaboxes($post){
		add_meta_box('agdp_agdpevent-dates', __('Dates de l\'évènement', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Evenement::post_type, 'normal', 'high');
		add_meta_box('agdp_agdpevent-description', __('Description', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Evenement::post_type, 'normal', 'high');
		add_meta_box('agdp_agdpevent-organisateur', __('Organisateur de l\'évènement', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Evenement::post_type, 'normal', 'high');
		add_meta_box('agdp_agdpevent-general', __('Informations générales', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Evenement::post_type, 'normal', 'high');
				
		if( current_user_can('manage_options') ){
			self::register_metabox_admin();
		}
	}

	/**
	 * Register Meta Box pour un nouvel évènement.
	 Uniquement pour les admins
	 */
	public static function register_metabox_admin(){
		$title = self::$the_post_is_new ? __('Nouvel évènement', AGDP_TAG) : __('Évènement', AGDP_TAG);
		add_meta_box('agdp_agdpevent-admin', $title, array(__CLASS__, 'metabox_callback'), AgendaPartage_Evenement::post_type, 'side', 'high');
	}

	/**
	 * Callback
	 */
	public static function metabox_callback($post, $metabox){
		//var_dump(func_get_args());
		
		switch ($metabox['id']) {
			
			case 'agdp_agdpevent-dates':
				parent::metabox_html( self::get_metabox_dates_fields(), $post, $metabox );
				break;
			
			case 'agdp_agdpevent-description':
				parent::metabox_html( self::get_metabox_description_fields(), $post, $metabox );
				break;
			
			case 'agdp_agdpevent-organisateur':
				parent::metabox_html( self::get_metabox_organisateur_fields(), $post, $metabox );
				break;
			
			case 'agdp_agdpevent-general':
				parent::metabox_html( self::get_metabox_general_fields(), $post, $metabox );
				break;
			
			case 'agdp_agdpevent-admin':
				self::post_author_metabox_field( $post );
				parent::metabox_html( self::get_metabox_admin_fields(), $post, $metabox );
				break;
			
			default:
				break;
		}
	}

	public static function get_metabox_all_fields(){
		return array_merge(
			// self::get_metabox_titre_fields(),
			self::get_metabox_dates_fields(),
			self::get_metabox_description_fields(),
			self::get_metabox_organisateur_fields(),
			self::get_metabox_general_fields()
		);
	}	

	// public static function get_metabox_titre_fields(){
		// return array(
			// array('name' => 'ev-titre',
				// 'label' => false )
		// );
	// }	

	public static function get_metabox_dates_fields(){
		return array(
			array('name' => 'ev-date-debut',
				'label' => __('Date de début', AGDP_TAG),
				'input' => 'date',
				'fields' => array(array(
					'name' => 'ev-date-journee-entiere',
					'label' => __('toute la journée', AGDP_TAG),
					'type' => 'checkbox',
					'default' => '0'
				))
			),
			array('name' => 'ev-heure-debut',
				'label' => __('Heure de début', AGDP_TAG),
				'input' => 'time'
			),
			array('name' => 'ev-date-fin',
				'label' => __('Date de fin', AGDP_TAG),
				'input' => 'date'
			),
			array('name' => 'ev-heure-fin',
				'label' => __('Heure de fin', AGDP_TAG),
				'input' => 'time'
			)
		);
	}

	public static function get_metabox_description_fields(){
		
		return array(
			array('name' => 'post_content',
				'label' => false,
				'input' => 'tinymce',
				'settings' => array (
					'textarea_rows' => 7
				)
			),
			array('name' => 'ev-siteweb',
				'label' => __('Site Web de l\'évènement', AGDP_TAG),
				'type' => 'url'
			),
			array('name' => 'ev-localisation',
				'label' => __('Lieu de l\'évènement', AGDP_TAG),
				'input' => 'text'
			)
		);
	}	

	public static function get_metabox_organisateur_fields(){

		$field_show = array(
			'name' => '%s-show',
			'label' => __('afficher sur le site', AGDP_TAG),
			'type' => 'checkbox',
			'default' => '1'
		);
				
		$fields = array(
			array('name' => 'ev-organisateur',
				'label' => __('Organisateur', AGDP_TAG),
				'fields' => array($field_show)
			),
			array('name' => 'ev-email',
				'label' => __('Email', AGDP_TAG),
				'type' => 'email',
				'fields' => array($field_show)
			),
			/*,
			array('name' => 'ev-gps',
				'label' => __('Coord. GPS', AGDP_TAG),
				'type' => 'gps',
				'fields' => array($field_show)
			)*/
		);
		//codesecret
		$field = array('name' => 'ev-'.AGDP_SECRETCODE ,
			'label' => 'Code secret pour cet évènement',
			'type' => 'input' ,
			'readonly' => true ,
			'class' => 'readonly' 
		);
		if(self::$the_post_is_new)
			$field['value'] = AgendaPartage::get_secret_code(6);
		$fields[] = $field;
  
		// sessionid
		// if(self::$the_post_is_new){
			// $fields[] = array('name' => 'ev-sessionid',
				// 'type' => 'hidden',
				// 'value' => AgendaPartage::get_session_id()
			// );
		// }
		return $fields;
	}

	public static function get_metabox_general_fields(){
		$fields = array();

		$fields[] =
			array('name' => 'ev-message-contact',
				'label' => __('Les visiteurs peuvent envoyer un e-mail.', AGDP_TAG),
				'type' => 'bool',
				'default' => 'checked'
			)
		;

		// $fields[] =
			// array('name' => 'ev-publication',
				// 'label' => __('Publication de cet évènement (Bulle verte, ...)', AGDP_TAG),
				// 'type' => 'bool',
				// 'default' => 'unchecked'
			// )
		// ;
		return $fields;
	}

	/**
	 * Ces champs ne sont PAS enregistrés car get_metabox_all_fields ne les retourne pas dans save_metaboxes
	 */
	public static function get_metabox_admin_fields(){
		global $post;
		$fields = array();
		if( ! self::$the_post_is_new ){
			$user_info = get_userdata($post->post_author);
			$user_email = $user_info->user_email;
		}
 		if(self::$the_post_is_new
		|| $user_email != get_post_meta($post->ID, 'ev-email', true) ) {
			$fields[] = array(
				'name' => 'ev-create-user',
				'label' => __('Créer l\'utilisateur d\'après l\'e-mail', AGDP_TAG),
				'input' => 'checkbox',
				'default' => 'checked',
				'container_class' => 'side-box'
			);
			/*$fields[] = array(
				'name' => 'ev-create-user-slug',
				'label' => __('Identifiant du nouvel utilisateur', AGDP_TAG),
				'input' => 'text',
				'container_class' => 'side-box'
			);*/
		}
		// multi-sites
		/* if( ! self::$the_post_is_new && ( WP_DEBUG || is_multisite() )) {//
			$blogs = AgendaPartage_Admin_Multisite::get_other_blogs_of_user($post->post_author);
			if(count($blogs) > 1){
				$field = array(
					'name' => 'ev-multisite-synchronise',
					// 'label' => __('Synchroniser cette page vers', AGDP_TAG),
					// 'input' => 'checkbox',
					'label' => __('Vos autres sites', AGDP_TAG),
					'input' => 'label',
					'fields' => array()
				);
				foreach($blogs as $blog){
					$field['fields'][] = 
						array('name' => sprintf('ev-multisite[%s]', $blog->userblog_id),
							//'label' => preg_replace('/AgendaPartage\sd[eu]s?\s/', '', $blog->blogname),
							'label' => sprintf('<a href="%s/wp-admin">%s</a>', $blog->siteurl, preg_replace('/AgendaPartage\sd[eu]s?\s/', '', $blog->blogname)),
							'input' => 'link',
							//'input' => 'label',
							'container_class' => 'description'
						)
					;	
				}
				$fields[] = $field;
			}
		} */
		
		return $fields;
	}

	/**
	 * Remplace la metabox Auteur par un liste déroulante dans une autre metabox
	 */
	private static function post_author_metabox_field( $post ) {
		global $user_ID;
		?><label for="post_author_override"><?php _e( 'Utilisateur' ); ?></label><?php
		wp_dropdown_users(
			array(
				'capability'       => 'authors',
				'name'             => 'post_author_override',
				'selected'         => empty( $post->ID ) ? $user_ID : $post->post_author,
				'include_selected' => true,
				'show'             => 'display_name_with_login',
			)
		);
	}

	/**
	 * Save metaboxes' input values
	 * Field can contain sub fields
	 */
	public static function save_metaboxes($post_ID, $post, $parent_field = null){
		if($parent_field === null){
			$fields = self::get_metabox_all_fields();
		}
		else
			$fields = $parent_field['fields'];
		foreach ($fields as $field) {
			if(!isset($field['type']) || $field['type'] != 'label'){
				$name = $field['name'];
				if($parent_field !== null)
					$name = sprintf($name, $parent_field['name']);//TODO check
				// remember : a checkbox unchecked does not return any value
				if( array_key_exists($name, $_POST)){
					$val = $_POST[$name];
				}
				else {
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
				if($name == 'ev-description')
					update_post_content($post_ID, $name, $val);
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
	 * Dans la visualisation des différences entre révisions, ajoute les meta_value
	 */
	public static function on_wp_get_revision_ui_diff_cb($return, $compare_from, $compare_to ){
		$metas_from = is_object($compare_from) ? get_post_meta($compare_from->ID, '', true) : [];
		$metas_to = get_post_meta($compare_to->ID, '', true);
		$meta_names = array_keys(array_merge($metas_from, $metas_to));
		
		$row_index = 0;
		foreach($meta_names as $meta_name){
			$from_exists = isset($metas_from[$meta_name]) && count($metas_from[$meta_name]) ;
			$from_value = $from_exists ? implode(', ', $metas_from[$meta_name]) : null;
			$to_exists = isset($metas_to[$meta_name]) && count($metas_to[$meta_name]);
			$post_value = $to_exists ? implode(', ', $metas_to[$meta_name]) : null;
			$return[] = array (
				'id' => $meta_name,
				'name' => $meta_name,
				'diff' => sprintf( "<table class='diff is-split-view'>
<tbody>
<tr><td class='%s'><span aria-hidden='true' class='dashicons dashicons-%s'></span><span class='screen-reader-text'>Avant </span><del>%s</del>
</td><td class='%s'><span aria-hidden='true' class='dashicons dashicons-%s'></span><span class='screen-reader-text'>Après </span><ins>%s</ins>
</td></tr>
</tbody>
</table>"
				, $from_exists ? 'diff-deletedline' : 'hide-children'
				, $from_exists && $post_value !== null ? 'minus' : 'arrow-left'
				, $from_value === null ? '' : htmlentities(var_export($from_value, true))
				, $to_exists ? 'diff-addedline' : ''
				, $to_exists && $from_exists ? 'plus' : 'yes'
				, $post_value === null ? '(idem)' : htmlentities(var_export($post_value, true))
			));
			$row_index++;
		}
		return $return;
	}
	
}
?>