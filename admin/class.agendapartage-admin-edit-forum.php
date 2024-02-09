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

		/* page */
		add_action( 'add_meta_boxes_page', array( __CLASS__, 'register_page_metaboxes' ), 10, 1 ); //edit
		add_action( 'save_post', array(__CLASS__, 'save_page_cb'), 10, 3 );

	}
	/****************/
	
	/**
	 * Register Meta Boxes (boite en édition de l'forum)
	 */
	public static function register_page_metaboxes($post){
		// 
		// add_action( 'filter_block_editor_meta_boxes', array(__CLASS__, 'on_page_filter_block_editor_meta_boxes'), 10, 1);
		// add_action( 'post_comment_status_meta_box-options', array(__CLASS__, 'on_page_comment_status_meta_box_options'), 10, 1);
		add_meta_box('agdp_forum', __('Association d\'un forum', AGDP_TAG), array(__CLASS__, 'page_metabox_callback'), 'page', 'side', 'high');
	}
	/**
	 * Callback
	 */
	public static function page_metabox_callback($post, $metabox){
		//var_dump(func_get_args());
		
		switch ($metabox['id']) {
			
			case 'agdp_forum':
				
				parent::metabox_html( self::get_page_metabox_forum_fields(), $post, $metabox );
				break;
			
			default:
				break;
		}
	}
	public static function get_page_metabox_forum_fields(){
		$fields = [];
		
		$meta_name = 'agdpforum';
		$forums = [ '' => '(aucun)'];
		foreach( AgendaPartage_Forum::get_forums() as $forum_id => $forum )
			$forums[$forum->ID] = $forum->post_name;
		
		$fields[] = array(
				'name' => $meta_name,
				'label' => __('Forum associé', AGDP_TAG),
				'input' => 'select',
				'values' => $forums,
				'learn-more' => "Une seule page peut s'associer à un forum car les messages ne sont lus et importés qu'une seule fois."
			);
		return $fields;
				
	}
	// public static function on_page_filter_block_editor_meta_boxes(array $wp_meta_boxes){
		// debug_log($wp_meta_boxes['page']['normal']['core']['commentstatusdiv']); 
		// $wp_meta_boxes['page']['normal']['core']['commentstatusdiv']['callback'] = array(__CLASS__, 'on_page_comment_status_meta_box_cb');
		// unset($wp_meta_boxes['page']['normal']['core']['commentstatusdiv']);
		// return $wp_meta_boxes;
	// }
	// public static function on_page_comment_status_meta_box_cb( $post ){
		// echo '<p>ICICICI</p>';
	// }
	// public static function on_page_comment_status_meta_box_options( $post ){
		// debug_log('on_page_comment_status_meta_box_options');
		// ? >
		// <label for="agdpforum" class="selectit">Forum associé</label><select name="agdpforum"  id="agdpforum"></select><br />
	// < ?php
	// }
	
	/**
	 * Callback lors de l'enregistrement d'une page (et non d'un forum).
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function save_page_cb ($page_id, $page, $is_update){
		if( $page->post_status == 'trashed' ){
			return;
		}
		self::save_metaboxes($page_id, $page, ['fields'=> self::get_page_metabox_forum_fields()]);
	}
	/****************/
	/****************/
	
	/**
	 * Register Meta Boxes (boite en édition de l'forum)
	 */
	public static function register_forum_metaboxes($post){
				
		add_meta_box('agdp_forum-imap', __('Synchronisation depuis une boîte mails', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Forum::post_type, 'normal', 'high');
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
			
			case 'agdp_forum-imap':
				parent::metabox_html( self::get_metabox_imap_fields(), $post, $metabox );
				break;
			
			default:
				break;
		}
	}

	public static function get_metabox_all_fields(){
		return array_merge(
			self::get_metabox_imap_fields(),
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
	
	public static function get_metabox_imap_fields(){
		
		$fields = [
			[	'name' => 'imap_server',
				'label' => __('Serveur IMAP', AGDP_TAG),
				'type' => 'text',
				'learn-more' => "De la forme {ssl0.ovh.net:993/ssl} ou {imap.free.fr:143/notls}."
			],
			[	'name' => 'imap_email',
				'label' => __('Adresse email', AGDP_TAG),
				'type' => 'text'
			],
			[	'name' => 'imap_password',
				'label' => __('Mot de passe', AGDP_TAG),
				'type' => 'password'
			],
			[	'name' => 'clear_signature',
				'label' => __('Effacer la signature', AGDP_TAG),
				'type' => 'text',
				'learn-more' => "Entrez ici le début du texte de la signature"
			],
			[	'name' => 'clear_raw',
				'label' => __('Effacer des lignes inutiles', AGDP_TAG),
				'type' => 'text',
				'learn-more' => "Entrez ici le début du texte (par exemple \"Envoyé à partir de\".)"
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
	}
	
}
?>