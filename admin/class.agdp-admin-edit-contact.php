<?php

/**
 * AgendaPartage Admin -> Edit -> Contact
 * Custom post type for WordPress in Admin UI.
 * 
 * Edition d'un contact
 * Définition des metaboxes et des champs personnalisés des Contacts 
 *
 * Voir aussi Agdp_Contact, Agdp_Admin_Contact
 */
class Agdp_Admin_Edit_Contact extends Agdp_Admin_Edit_Post_Type {

	const post_type = Agdp_Contact::post_type; 
	static $can_duplicate = true;

	public static function init() {
		parent::init();

		self::init_hooks();
	}
	
	public static function init_hooks() {
		
		if(basename($_SERVER['PHP_SELF']) === 'post.php'
		&& isset($_POST['post_type'])
			&& $_POST['post_type'] === static::post_type 
		&& isset($_POST['post_status'])
			&& ! in_array($_POST['post_status'], [ 'trash', 'trashed' ]) ){
			add_filter( 'wp_insert_post_data', array(__CLASS__, 'on_wp_insert_post_data'), 10, 2 );
		}
		
		if( in_array( basename($_SERVER['PHP_SELF']), [ 'revision.php', 'admin-ajax.php' ])) {
			add_filter( 'wp_get_revision_ui_diff', array(__CLASS__, 'on_wp_get_revision_ui_diff_cb'), 10, 3 );		
		}
		
		if(array_key_exists('post_type', $_POST)
		&& $_POST['post_type'] === static::post_type){
			/** validation du post_content **/
			add_filter( 'content_save_pre', array(__CLASS__, 'on_post_content_save_pre'), 10, 1 );

			/** save des meta values et + **/
			if(basename($_SERVER['PHP_SELF']) === 'post.php'){
				add_action( 'save_post_' . static::post_type, array(__CLASS__, 'save_post_contact_cb'), 10, 3 );
			}
		}
		add_action( 'add_meta_boxes_' . static::post_type, array( __CLASS__, 'register_contact_metaboxes' ), 10, 1 ); //edit
	}
	/****************/

	/**
	 * Callback lors de l'enregistrement d'un contact.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function on_wp_insert_post_data ($data, $postarr ){
		if($data['post_type'] != Agdp_Contact::post_type)
			return $data;
		
		if( array_key_exists('cont-create-user', $postarr) && $postarr['cont-create-user'] ){
			$data = self::create_user_on_save($data, $postarr);
		}
		
		//On sauve les révisions de meta_values
		$post_id = empty($postarr['post_ID']) ? $postarr['ID'] : $postarr['post_ID'];
		Agdp_Contact_Edit::save_post_revision($post_id, $postarr, true);
		
		return $data;
	}
	
	/**
	 * Callback lors de l'enregistrement du post_content d'un contact.
	 */
	public static function on_post_content_save_pre($value){
		// &amp; &gt; ...
		if( preg_match('/\&\w+\;/', $value ) !== false){
			$value = html_entity_decode( $value );
		}
		
		return $value;
	}
	/**
	 * Callback lors de l'enregistrement d'un contact.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function save_post_contact_cb ($post_id, $post, $is_update){
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
		/* $email = array_key_exists('cont-email', $postarr) ? $postarr['cont-email'] : false;
		if(!$email || !is_email($email)) {
			Agdp_Admin::add_admin_notice("Il manque l'adresse e-mail de l\'contact du contact ou elle est incorrecte.", 'error');
			return $data;
		}
		$user_name = array_key_exists('cont-contact', $postarr) ? $postarr['cont-contact'] : false;
		$user_login = array_key_exists('cont-create-user-slug', $postarr) ? $postarr['cont-create-user-slug'] : false;
	
		$user_data = array(
			'description' => 'Contact ' . $data['post_title'],
		);
		$user = Agdp_User::create_user_for_contact($email, $user_name, $user_login, $user_data, 'subscriber');
		if( is_wp_error($user)) {
			Agdp_Admin::add_admin_notice($user, 'error');
			return;
		}
		if($user){
			unset($_POST['cont-create-user']);
			unset($postarr['cont-create-user']);

			$data['post_author'] = $user->ID;
			//Agdp_Admin::add_admin_notice(debug_print_backtrace(), 'warning');
			Agdp_Admin::add_admin_notice("Désormais, l'auteur de la page est {$user->display_name}", 'success');
		} */

		return $data;
	}

	/**
	 * Register Meta Boxes (boite en édition de contact)
	 */
	public static function register_contact_metaboxes($post){
		add_meta_box('agdp_contact-description', __('Description', AGDP_TAG), array(__CLASS__, 'metabox_callback'), Agdp_Contact::post_type, 'normal', 'high');
		add_meta_box('agdp_contact-horaires', __('Heures d\'ouverture', AGDP_TAG), array(__CLASS__, 'metabox_callback'), Agdp_Contact::post_type, 'normal', 'high');
		add_meta_box('agdp_contact-contact', __('Contact', AGDP_TAG), array(__CLASS__, 'metabox_callback'), Agdp_Contact::post_type, 'normal', 'high');
				
		if( current_user_can('manage_options') ){
			self::register_metabox_admin();
		}
	}

	/**
	 * Register Meta Box pour un nouveau contact.
	 Uniquement pour les admins
	 */
	public static function register_metabox_admin(){
		$title = self::$the_post_is_new ? __('Nouveau contact', AGDP_TAG) : __('Contact', AGDP_TAG);
		add_meta_box('agdp_contact-admin', $title, array(__CLASS__, 'metabox_callback'), Agdp_Contact::post_type, 'side', 'high');
	}

	/**
	 * Callback
	 */
	public static function metabox_callback($post, $metabox){
		//var_dump(func_get_args());
		
		switch ($metabox['id']) {
			
			case 'agdp_contact-horaires':
				parent::metabox_html( self::get_metabox_horaires_fields(), $post, $metabox );
				break;
			
			case 'agdp_contact-description':
				parent::metabox_html( self::get_metabox_description_fields(), $post, $metabox );
				break;
			
			case 'agdp_contact-contact':
				parent::metabox_html( self::get_metabox_contact_fields(), $post, $metabox );
				break;
			
			case 'agdp_contact-admin':
				self::post_author_metabox_field( $post );
				parent::metabox_html( self::get_metabox_admin_fields(), $post, $metabox );
				break;
			
			default:
				break;
		}
	}

	public static function get_metabox_all_fields(){
		return array_merge(
			self::get_metabox_horaires_fields(),
			self::get_metabox_description_fields(),
			self::get_metabox_contact_fields(),
		);
	}	

	// public static function get_metabox_titre_fields(){
		// return array(
			// array('name' => 'cont-titre',
				// 'label' => false )
		// );
	// }	

	public static function get_metabox_horaires_fields(){
		global $post;
		return array(
			array('name' => 'cont-horaires',
				'label' => __('Horaires', AGDP_TAG),
				'input' => 'textarea',
			),
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
			)
		);
	}	

	public static function get_metabox_contact_fields(){

		$field_show = array(
			'name' => '%s-show',
			'label' => __('afficher sur le site', AGDP_TAG),
			'type' => 'checkbox',
			'default' => '1'
		);
				
		$fields = array(
			array('name' => 'cont-userid',
				'type' => 'userid',
				'container_class' => 'hidden',
			),
			array('name' => 'cont-contact',
				'label' => __('Nom du contact', AGDP_TAG),
				'fields' => array($field_show)
			),
			array('name' => 'cont-webpage',
				'label' => __('Page Web', AGDP_TAG),
				'type' => 'url',
				'fields' => array($field_show)
			),
			array('name' => 'cont-phone',
				'label' => __('Téléphone', AGDP_TAG),
				'type' => 'text',
				'fields' => array($field_show)
			),
			array('name' => 'cont-email',
				'label' => __('Email', AGDP_TAG),
				'type' => 'email',
				'fields' => array($field_show)
			),
			array('name' => 'cont-address',
				'label' => __('Adresse', AGDP_TAG),
				'input' => 'textarea',
				'attributes' => array (
					'rows' => 4
				),
				'fields' => array($field_show)
			),
			array('name' => 'cont-gps',
				'label' => __('Coord. GPS', AGDP_TAG),
				'type' => 'gps',
				'fields' => array($field_show)
			),
			array('name' => 'cont-pagejaune',
				'label' => __('Page Jaune', AGDP_TAG),
				'type' => 'url',
				'fields' => array($field_show)
			),
		);
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
			if( is_object($user_info) )
				$user_email = $user_info->user_email;
			else
				$user_email = false;
		}
 		if(self::$the_post_is_new
		|| $user_email != get_post_meta($post->ID, 'cont-email', true) ) {
			$fields[] = array(
				'name' => 'cont-create-user',
				'label' => __('Créer l\'utilisateur d\'après l\'e-mail', AGDP_TAG),
				'input' => 'checkbox',
				'default' => 'checked',
				'container_class' => 'side-box'
			);
			$fields[] = array(
				'name' => 'cont-create-user-slug',
				'label' => __('Identifiant du nouvel utilisateur', AGDP_TAG),
				'input' => 'text',
				'container_class' => 'side-box'
			);
		}
		
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
				// 'capability'       => 'authors',
				'name'             => 'post_author_override',
				'selected'         => empty( $post->ID ) ? $user_ID : $post->post_author,
				'include_selected' => true,
				'show'             => 'display_name_with_login',
			)
		);
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