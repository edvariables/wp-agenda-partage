<?php

/**
 * AgendaPartage Admin -> Edit -> Forum
 * Custom post type for WordPress in Admin UI.
 * 
 * Edition d'un forum
 * Définition des metaboxes et des champs personnalisés des Forums 
 *
 * Voir aussi AgendaPartage_Forum, AgendaPartage_Admin_Forum
 */
class AgendaPartage_Admin_Edit_Forum extends AgendaPartage_Admin_Edit_Post_Type {

	public static function init() {
		parent::init();

		self::init_hooks();
	}
	
	public static function init_hooks() {

		add_action( 'add_meta_boxes_' . AgendaPartage_Forum::post_type, array( __CLASS__, 'register_forum_metaboxes' ), 10, 1 ); //edit

		if(basename($_SERVER['PHP_SELF']) === 'post.php'
		&& array_key_exists('post_type', $_POST)
		&& $_POST['post_type'] == AgendaPartage_Forum::post_type)
			add_action( 'save_post_' . AgendaPartage_Forum::post_type, array(__CLASS__, 'save_post_forum_cb'), 10, 3 );

	}
	/****************/
	
	/**
	 * Register Meta Boxes (boite en édition de l'forum)
	 */
	public static function register_forum_metaboxes($post){
				
		add_meta_box('agdp_forum-config', __('Configuration', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Forum::post_type, 'normal', 'high');
		add_meta_box('agdp_forum-test', __('Test d\'envoi', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Forum::post_type, 'normal', 'high');
	}

	/**
	 * Callback
	 */
	public static function metabox_callback($post, $metabox){
		//var_dump(func_get_args());
		
		switch ($metabox['id']) {
			
			case 'agdp_forum-test':
				self::get_metabox_test();
				break;
			
			case 'agdp_forum-config':
				parent::metabox_html( self::get_metabox_config_fields(), $post, $metabox );
				break;
			
			default:
				break;
		}
	}

	public static function get_metabox_all_fields(){
		return array_merge(
			self::get_metabox_config_fields(),
			self::get_metabox_test_fields(),
		);
	}	

	public static function get_metabox_test(){
		global $current_user;
		$forum = get_post();
		$forum_id = $forum->ID;
		
		$meta_name = 'send-forum-test-email';
		$meta_value = get_post_meta($forum_id, $meta_name, true);
		if( is_array($meta_value) ) $meta_value = $meta_value[0];
		if( $meta_value )
			$email = $meta_value;
		else
			$email = $current_user->user_email;
		echo sprintf('<label><input type="checkbox" name="send-forum-test">Envoyer le forum pour test</label>');
		echo sprintf('<br><br><label>Destinataire(s) : </label><input type="email" name="send-forum-test-email" value="%s">', $email);
		
	}
	public static function get_metabox_test_fields(){
		$fields = [];
		$meta_name = 'send-forum-test-email';
		$fields[] = array('name' => $meta_name,
						'label' => __('Adresse de test', AGDP_TAG),
						'type' => 'email'
		);
		return $fields;
				
	}
	
	public static function get_metabox_config_fields(){
		
		$fields = [
			[	'name' => 'imap_server',
				'label' => __('Serveur IMAP', AGDP_TAG),
				'type' => 'text'
			],
			[	'name' => 'imap_port',
				'label' => __('Port IMAP', AGDP_TAG),
				'type' => 'text'
			],
			[	'name' => 'imap_email',
				'label' => __('Adresse email', AGDP_TAG),
				'type' => 'text'
			],
			[	'name' => 'imap_password',
				'label' => __('Mot de passe', AGDP_TAG),
				'type' => 'password'
			],
		];
		return $fields;
				
	}
	
	/**
	 * Callback lors de l'enregistrement du post.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function save_post_forum_cb ($forum_id, $forum, $is_update){
		if( $forum->post_status == 'trashed' ){
			return;
		}
		self::save_metaboxes($forum_id, $forum);
		self::send_test_email($forum_id, $forum, $is_update);
	}
	
	/**
	 * Envoie un mail de test si demandé dans le $_POST.
	 */
	public static function send_test_email ($forum_id, $forum, $is_update){
		if( ! array_key_exists('send-forum-test', $_POST)
		|| ! $_POST['send-forum-test']
		|| ! array_key_exists('send-forum-test-email', $_POST))
			return;
		
		$email = sanitize_email($_POST['send-forum-test-email']);
		if( ! is_email($email)){
			AgendaPartage_Admin::add_admin_notice("Il manque l'adresse e-mail pour le test d'envoi.", 'error');
			return;
		}
		
		// AgendaPartage_Forum::send_email($forum, [$email]);
			
	}
	
}
?>