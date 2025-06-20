<?php

/**
 * AgendaPartage Admin -> Edit -> Evenement
 * Custom post type for WordPress in Admin UI.
 * 
 * Edition d'un évènement
 * Définition des metaboxes et des champs personnalisés des Évènements 
 *
 * Voir aussi Agdp_Evenement, Agdp_Admin_Evenement
 */
class Agdp_Admin_Edit_Evenement extends Agdp_Admin_Edit_Post_Type {

	const post_type = Agdp_Evenement::post_type;

	public static function init() {
		parent::init();

		self::init_hooks();
	}
	
	public static function init_hooks() {
		
		if(basename($_SERVER['PHP_SELF']) === 'post.php'
		&& isset($_POST['post_type'])
			&& $_POST['post_type'] === Agdp_Evenement::post_type 
		&& isset($_POST['post_status'])
			&& ! in_array($_POST['post_status'], [ 'trash', 'trashed' ]) ){
			add_filter( 'wp_insert_post_data', array(__CLASS__, 'wp_insert_post_data_cb'), 10, 2 );
		}
		
		if( in_array( basename($_SERVER['PHP_SELF']), [ 'revision.php', 'admin-ajax.php' ])) {
			add_filter( 'wp_get_revision_ui_diff', array(__CLASS__, 'on_wp_get_revision_ui_diff_cb'), 10, 3 );		
		}
		
		if(array_key_exists('post_type', $_POST)
		&& $_POST['post_type'] === Agdp_Evenement::post_type){
			/** validation du post_content **/
			add_filter( 'content_save_pre', array(__CLASS__, 'on_post_content_save_pre'), 10, 1 );

			/** save des meta values et + **/
			if(basename($_SERVER['PHP_SELF']) === 'post.php'){
				add_action( 'save_post_agdpevent', array(__CLASS__, 'save_post_agdpevent_cb'), 10, 3 );
			}
			/** initialisation des diffusions par défaut pour les nouveaux évènements */
			if(basename($_SERVER['PHP_SELF']) === 'post-new.php'){
				add_filter( 'wp_terms_checklist_args', array( __CLASS__, "on_wp_terms_checklist_args" ), 10, 2 ); 
			}
		}
		add_action( 'add_meta_boxes_' . Agdp_Evenement::post_type, array( __CLASS__, 'register_agdpevent_metaboxes' ), 10, 1 ); //edit
	}
	/****************/

	/**
	 * Callback lors de l'enregistrement d'un évènement.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function wp_insert_post_data_cb ($data, $postarr ){
		if($data['post_type'] != Agdp_Evenement::post_type)
			return $data;
		
		if( array_key_exists('ev-create-user', $postarr) && $postarr['ev-create-user'] ){
			$data = self::create_user_on_save($data, $postarr);
		}
		
		//On sauve les révisions de meta_values
		$post_id = empty($postarr['post_ID']) ? $postarr['ID'] : $postarr['post_ID'];
		Agdp_Evenement_Edit::save_post_revision($post_id, $postarr, true);
		
		return $data;
	}
	
	/**
	 * Callback lors de l'enregistrement du post_content d'un évènement.
	 */
	public static function on_post_content_save_pre($value){
		// &amp; &gt; ...
		if( preg_match('/\&\w+\;/', $value ) !== false){
			$value = html_entity_decode( $value );
		}
		
		return $value;
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
		/* $email = array_key_exists('ev-user-email', $postarr) ? $postarr['ev-user-email'] : false;
		if(!$email || !is_email($email)) {
			Agdp_Admin::add_admin_notice("Il manque l'adresse e-mail de l\'organisateur de l\'évènement ou elle est incorrecte.", 'error');
			return $data;
		}
		$user_name = array_key_exists('ev-organisateur', $postarr) ? $postarr['ev-organisateur'] : false;
		$user_login = array_key_exists('ev-create-user-slug', $postarr) ? $postarr['ev-create-user-slug'] : false;
	
		$user_data = array(
			'description' => 'Évènement ' . $data['post_title'],
		);
		$user = Agdp_User::create_user($email, $user_name, $user_login, $user_data, 'subscriber');
		if( is_wp_error($user)) {
			Agdp_Admin::add_admin_notice($user, 'error');
			return;
		}
		if($user){
			unset($_POST['ev-create-user']);
			unset($postarr['ev-create-user']);

			$data['post_author'] = $user->ID;
			//Agdp_Admin::add_admin_notice(debug_print_backtrace(), 'warning');
			Agdp_Admin::add_admin_notice("Désormais, l'auteur de la page est {$user->display_name}", 'success');
		}
		*/
		return $data; 
	}

	/**
	 * Register Meta Boxes (boite en édition de l'évènement)
	 */
	public static function register_agdpevent_metaboxes($post){
		add_meta_box('agdp_agdpevent-dates', __('Dates de l\'évènement', AGDP_TAG), array(__CLASS__, 'metabox_callback'), Agdp_Evenement::post_type, 'normal', 'high');
		add_meta_box('agdp_agdpevent-description', __('Description', AGDP_TAG), array(__CLASS__, 'metabox_callback'), Agdp_Evenement::post_type, 'normal', 'high');
		add_meta_box('agdp_agdpevent-organisateur', __('Organisateur de l\'évènement', AGDP_TAG), array(__CLASS__, 'metabox_callback'), Agdp_Evenement::post_type, 'normal', 'high');
		add_meta_box('agdp_agdpevent-general', __('Informations générales', AGDP_TAG), array(__CLASS__, 'metabox_callback'), Agdp_Evenement::post_type, 'normal', 'high');
				
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
		add_meta_box('agdp_agdpevent-admin', $title, array(__CLASS__, 'metabox_callback'), Agdp_Evenement::post_type, 'normal', 'high');
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
			self::get_metabox_general_fields(),
			self::get_metabox_admin_fields(),
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
			array('name' => 'ev-phone',
				'label' => __('Téléphone', AGDP_TAG),
				'type' => 'text'
			),
			array('name' => 'ev-email',
				'label' => __('Email de l\'organisateur', AGDP_TAG),
				'type' => 'email',
				'fields' => array($field_show)
			),
			array('name' => 'ev-user-email',
				'label' => __('Email de validation', AGDP_TAG),
				'type' => 'email'
			),
			/*,
			array('name' => 'ev-gps',
				'label' => __('Coord. GPS', AGDP_TAG),
				'type' => 'gps',
				'fields' => array($field_show)
			)*/
		);
		//codesecret
		$field = array('name' => 'ev-'.AGDP_EVENT_SECRETCODE ,
			'label' => 'Code secret pour cet évènement',
			'type' => 'input' ,
			'readonly' => true ,
			'class' => 'readonly' 
		);
		if(self::$the_post_is_new)
			$field['value'] = Agdp::get_secret_code(6);
		$fields[] = $field;
  
		// sessionid
		// if(self::$the_post_is_new){
			// $fields[] = array('name' => 'ev-sessionid',
				// 'type' => 'hidden',
				// 'value' => Agdp::get_session_id()
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
		return $fields;
	}

	/**
	 * Ces champs ne sont PAS enregistrés car get_metabox_all_fields ne les retourne pas dans save_metaboxes
	 */
	public static function get_metabox_admin_fields(){
		global $post;
		$fields = array();
		
		$meta_name = AGDP_IMPORT_UID;
 		if( ! self::$the_post_is_new
		&& ($imported = get_post_meta($post->ID, $meta_name, true)) ) {
			$import_refused = get_post_meta($post->ID, AGDP_IMPORT_REFUSED, true);
			
			$fields[] = array(
				'name' => $meta_name,
				'label' => __('Evènement importé', AGDP_TAG),
				'icon' => 'admin-multisite',
				'input' => 'text',
				'value' => $imported,
				'container_class' => 'side-box',
				'fields' => [ array(
								'name' => AGDP_IMPORT_REFUSED,
								'label' => __('Refusé', AGDP_TAG),
								'input' => 'checkbox',
								'type' => 'bool',
								'value' => $import_refused,
								'container_class' => 'side-box' . ($import_refused ? ' color-red' : ''),
							)]
			);
		}
		
		if( ! self::$the_post_is_new ){
			$user_info = get_userdata($post->post_author);
			if( is_object($user_info) )
				$user_email = $user_info->user_email;
			else
				$user_email = false;
		}
		
		if(self::$the_post_is_new
		|| ($user_email != get_post_meta($post->ID, 'ev-user-email', true)) ) {
			/* TODO $fields[] = array(
				'name' => 'ev-create-user',
				'label' => __('Créer l\'utilisateur d\'après l\'e-mail', AGDP_TAG),
				'input' => 'checkbox',
				'default' => 'checked',
				'container_class' => 'side-box'
			); */
			/*$fields[] = array(
				'name' => 'ev-create-user-slug',
				'label' => __('Identifiant du nouvel utilisateur', AGDP_TAG),
				'input' => 'text',
				'container_class' => 'side-box'
			);*/
		}
		// multi-sites
		/* if( ! self::$the_post_is_new && ( WP_DEBUG || is_multisite() )) {//
			$blogs = Agdp_Admin_Multisite::get_other_blogs_of_user($post->post_author);
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
				// 'capability'       => 'authors',
				'name'             => 'post_author_override',
				'selected'         => empty( $post->ID ) ? $user_ID : $post->post_author,
				'include_selected' => true,
				'show'             => 'display_name_with_login',
			)
		);
	}
	
	public static function on_wp_terms_checklist_args($args, int $post_id){
		if( in_array( $args['taxonomy'], [ Agdp_Evenement::taxonomy_city, Agdp_Evenement::taxonomy_diffusion ] )){
			$meta_name = 'default_checked';
			$args['selected_cats'] = [];
			foreach($args['popular_cats'] as $term_id)
				if( get_term_meta($term_id, $meta_name, true) )
					$args['selected_cats'][] = $term_id;
		}
		return $args;
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