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
		global $pagenow;
		if ( $pagenow === 'post.php' ) {
			add_action( 'add_meta_boxes_' . AgendaPartage_Forum::post_type, array( __CLASS__, 'register_forum_metaboxes' ), 10, 1 ); //edit
			add_action( 'save_post_' . AgendaPartage_Forum::post_type, array(__CLASS__, 'save_post_forum_cb'), 10, 3 );
			add_action( 'admin_notices', array(__CLASS__, 'on_admin_notices_cb'), 10);
		}
	}
	/****************/
		
	/**
	 * Register Meta Boxes (boite en édition du forum)
	 */
	public static function on_admin_notices_cb(){
		global $post;
		if( ! $post )
			return;
		switch($post->post_type){
			//forum
			case AgendaPartage_Forum::post_type:
				$meta_key = AGDP_FORUM_META_PAGE;
				if( $page_id = get_post_meta( $post->ID, $meta_key, true)){
					$page = get_post($page_id);
					if( $page->post_status != 'publish' )
						AgendaPartage_Admin::add_admin_notice_now(sprintf('Attention, la page associée n\'est pas publiée.'
							. ' <a href="/wp-admin/post.php?post=%s&action=edit">Cliquez ici pour afficher la page</a>.', $page_id)
							, [ 'type' => 'warning', 
								'actions' => [
									'url' => sprintf('/wp-admin/post.php?post=%s&action=edit', $page_id)
								]
							]);
					elseif( $page->comment_status != 'open' )
						AgendaPartage_Admin::add_admin_notice_now(sprintf('Attention, la page associée ne gère pas les commentaires.'
							. ' <a href="/wp-admin/post.php?post=%s&action=edit">Cliquez ici pour afficher la page</a>.', $page_id)
							, [ 'type' => 'error', 
								'actions' => [
									'url' => sprintf('/wp-admin/post.php?post=%s&action=edit', $page_id)
								]
							]);
					else
						AgendaPartage_Admin::add_admin_notice_now(sprintf('<a href="/wp-admin/post.php?post=%s&action=edit">Cliquez ici pour afficher la page de commentaires associée</a>.', $page_id)
							, [ 'type' => 'info', 
								'actions' => [
									'url' => sprintf('/wp-admin/post.php?post=%s&action=edit', $page_id)
								]
							]);
							
					if ( ! get_post_meta($post->ID, 'imap_mark_as_read', true) )
						AgendaPartage_Admin::add_admin_notice_now('Attention, l\'option "Marquer les messages comme étant lus" n\'est pas cochée.'
							, ['type' => 'warning']);
				}
				elseif( $post->post_status == 'publish') {
					AgendaPartage_Admin::add_admin_notice_now('Attention, aucune page de commentaires associée.'
						, ['type' => 'warning']);
				}
				break;
				
			// page
			case 'page':
				$meta_key = AGDP_PAGE_META_FORUM;
				if( $forum_id = get_post_meta( $post->ID, $meta_key, true)){
					$forum = get_post($forum_id);
					if( $forum->post_status != 'publish' )
						AgendaPartage_Admin::add_admin_notice_now('Attention, le forum associé n\'est pas publié.'
							. sprintf(' <a href="/wp-admin/post.php?post=%s&action=edit">Cliquez ici pour modifier le forum</a>.', $forum_id)
							, ['type' => 'warning', 
								'actions' => [
									'url' => sprintf('/wp-admin/post.php?post=%s&action=edit', $forum_id)
								]
							]);
					else
						AgendaPartage_Admin::add_admin_notice_now(sprintf('<a href="/wp-admin/post.php?post=%s&action=edit">Cliquez ici pour afficher le forum associé</a>.', $forum_id)
							, ['type' => 'info', 
								'actions' => [
									'url' => sprintf('/wp-admin/post.php?post=%s&action=edit', $forum_id)
								]
							]);
				}
				break;
		}
	}
		
	/**
	 * Register Meta Boxes (boite en édition du forum)
	 */
	public static function register_forum_metaboxes($post){
		add_meta_box('agdp_forum-page', __('Page pour l\'affichage', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Forum::post_type, 'normal', 'high');
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
			
			case 'agdp_forum-page':
				parent::metabox_html( self::get_metabox_page_fields(), $post, $metabox );
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
			self::get_metabox_page_fields(),
			self::get_metabox_imap_fields(),
			self::get_metabox_test_fields(),
		);
	}	
	
	public static function get_metabox_page_fields(){
		$fields = [];
		
		$meta_name = AGDP_FORUM_META_PAGE;
		$pages = [ '' => '(désactivé)'];
		foreach( get_pages() as $page )
			$pages[$page->ID] = $page->post_name
				. ($page->comment_status == 'open' ? '' : ' (commentaires fermés !)');
		
		$fields[] = array(
				'name' => $meta_name,
				'label' => __('Page associée', AGDP_TAG),
				'input' => 'select',
				'values' => $pages,
				'learn-more' => "La page doit activer sa gestion des commentaires."
			);
		return $fields;
				
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
			[	'name' => 'imap_mark_as_read',
				'label' => __('Marquer les messages comme étant lus', AGDP_TAG),
				'type' => 'checkbox',
				'default' => true,
				'learn-more' => "Cette option doit être cochée en fonctionnement normal"
			],
			[	'name' => 'clear_signature',
				'label' => __('Effacer la signature', AGDP_TAG),
				'type' => 'text',
				'input' => 'textarea',
				'learn-more' => "Entrez ici les débuts des textes de signatures à reconnaitre."
							. "\nCeci tronque le message depuis la signature jusqu'à la fin."
							. "\nMettre ci-dessous une recherche par ligne."
			],
			[	'name' => 'clear_raw',
				'label' => __('Effacer des lignes inutiles', AGDP_TAG),
				'type' => 'text',
				'input' => 'textarea',
				'learn-more' => "Entrez ici les débuts des textes (par exemple \"Envoyé à partir de\".)"
							. "\nCeci tronque le message d'une seule ligne."
							. "\nMettre ci-dessous une recherche par ligne."
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
		
		self::synchronise_forums_pages_meta();
	}
	
	/**
	 * Synchroniser les meta des pages et des forums.
	 * 	Forum->meta[AGDP_FORUM_META_PAGE] = $page->ID
	 * 	Page->meta[AGDP_PAGE_META_FORUM] = $forum->ID
	 */
	public static function synchronise_forums_pages_meta(){
		
		//Supprime les meta des pages
		$meta_name = AGDP_PAGE_META_FORUM;
		foreach( get_posts([
			'post_type' => 'page'
			, 'meta_key' => $meta_name
			, 'meta_value' => '0'
			, 'meta_compare' => '>'
			]) as $page)
			update_post_meta($page->ID, $meta_name, 0);
			
			
		$meta_name = AGDP_FORUM_META_PAGE;
		foreach( get_posts([
			'post_type' => AgendaPartage_Forum::post_type
			, 'post_status' => 'publish'
			, 'meta_key' => $meta_name
			, 'meta_value' => '0'
			, 'meta_compare' => '!='
			]) as $forum){
			$page_ID = get_post_meta( $forum->ID, $meta_name, true);
			
			update_post_meta($page_ID, AGDP_PAGE_META_FORUM, $forum->ID);
		}
	}
}
?>