<?php

/**
 * AgendaPartage -> Forum
 * Structurellement, un forum est une page dont les commentaires sont alimentés et dont les commentaires sont utilisés dans une newsletter.
 * Gestion des commentaires d'une page. Les commentaires sont importés depuis une boîte e-mails ou une interception de mail sortant.
 *
 * Voir aussi Agdp_Mailbox
 *
 * Un forum est une page qui doit afficher ses commentaires.
 */
class Agdp_Forum extends Agdp_Page {

	const posts_type = 'comment';
	const page_type = 'page';
	
	const post_type = 'page';
	const tag = 'agdpforum';
	const page_html_class = 'use-agdpforum';
	
	// const user_role = 'author';

	private static $initiated = false;
	
	public static $properties = [];
	
	public static $current_forum;
	public static $current_forum_rights;
		
		
	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;

			self::init_hooks();
		}
	}
	
	const rights = [
		'P' => 'Public',
		'E' => 'Validation par e-mail',
		'C' => 'Connexion requise',
		'CO' => 'Inscription cooptée (todo) et connexion requise',
		'A' => 'Adhésion requise',
		'AO' => 'Adhésion cooptée (todo) requise',
		'M' => 'Modérateurice',
		'X' => 'Administrateurice',
	];
	
	const subscription_roles = [
			'' => '(non membre)',
			'subscriber' => 'Membre',
			'moderator' => 'Modérateurice',
			'administrator' => 'Administrateurice',
			'banned' => 'Banni-e',
		];

	
	const show_comments_modes = [
			'' => '(par défaut)',
			'never' => 'Jamais, personne.',
			'admin' => 'Les administrateurices seul-es',
			'moderator' => '+ Les modérateurices',
			'subscribers' => '+ Les membres du forum (non banni-es)',
			'connected' => '+ Les utilisateurices connecté-es',
			'public' => 'Tout le monde',
		];

	/**
	 * Hook
	 */
	public static function init_hooks() {
		global $pagenow;
		if ( $pagenow !== 'edit.php' && $pagenow !== 'post.php') {
			add_action( 'post_class', array(__CLASS__, 'on_post_class_cb'), 10, 3);
		}
		
		// add_filter('wp_get_nav_menu_items', array(__CLASS__, 'on_wp_get_nav_menu_items'), 10, 3);
		
		add_filter('comments_array', array(__CLASS__, 'on_comments_array'), 10, 2);
	}
	/*
	 **/
	
	/**
	 * Properties
	 */
	public static function set_property($key, $value){
		self::$properties[$key] = $value;
	}
	public static function get_property($key){
		if( isset(self::$properties[$key]) )
			return self::$properties[$key];
		$meta_key = 'forum_' . $key;
		if( ! ($forum = self::get_page() ) )
			return null;
		self::set_property( $key, $meta_value = get_post_meta($forum->ID, $meta_key, true));
		return $meta_value;
	}
	public static function get_property_is_value($key, $value){
		$property_value = self::get_property($key, $value);
		return $property_value == $value;
	}
	
	/**
	 * get_current_forum_rights
	 */
	public static function get_current_forum_rights( $page = false ){
		if( ! self::$current_forum_rights
		&& $page ){
			self::$current_forum = $page;
			if( ! (self::$current_forum_rights = Agdp_Mailbox::get_page_rights( $page )) )
				self::$current_forum_rights = 'P';
		}
		return self::$current_forum_rights;
	}
		
	
	/**
	 * Retourne un tableau de pages de forum
	 */
	public static function get_forums( $args = false){
		$default_args = [
			'post_type' => self::post_type,
			'post_status' => 'publish',
			'numberposts' => -1,
			'meta_query' => [[
				'key' => 'agdpmailbox',
				'value' => '0',
				'compare' => '>'
			]]
		];
		if( is_array($args) ) 
			$args = array_merge( $default_args, $args );
		else
			$args = $default_args;
		//Forums
		$posts = get_posts( $args );
		// debug_log("get_forums", count($posts), $args);
		$pages = [];
		foreach($posts as $page){
			if( is_numeric($page) )
				$pages[] = $page;
			else {
				$post_id = $page->ID;
				$pages[ $post_id . '' ] = $page;
			}
		}
		return $pages;
	}
	/**
	 * Retourne un tableau de pages de forum masquées à l'utilisateur courant
	 */
	/* public static function get_hidden_forums( $args = false){
		if( current_user_can('moderate_comments') ) 
			return self::get_forums();
		
		$hidden_forums = [];
		
		$current_user = self::get_current_user();
		foreach( Agdp_Mailbox::get_emails_dispatch() as $email => $dispatch){
			if( $dispatch['type'] !== 'page'
			|| in_array($dispatch['id'], $hidden_forums))
				continue;
			if( ! isset($dispatch['rights'])
				|| ! $dispatch['rights']
				|| in_array($dispatch['rights'], ['P', 'E']) //public ou validation par email
			)
				continue;
			if( $current_user && $dispatch['rights'] === 'C')
				continue;
			// debug_log('user get_subscription', $current_user, $dispatch, self::get_subscription( $current_user, $dispatch['id'] ));
			if( ! $current_user )
				$hidden_forums[] = $dispatch['id'];
			else
				switch( self::get_subscription( $current_user, $dispatch['id'] ) ){
					case 'administrator':
					case 'moderator':
					case 'subscriber':
						continue 2;
					default:
						$hidden_forums[] = $dispatch['id'];
						break;
				}
		}
		
		if( ! $args )
			$args = [];
		$args['include'] = $hidden_forums;
		$pages = self::get_forums( $args );
		return $pages;
	} */
	
	/***************************/
	/******** Droits ***********/
	
	/**
	 * Retourne tous les droits
	 */
	public static function get_all_rights( ){
		return array_keys( self::rights );
	}
	/**
	 * Retourne tous les libellés des droits
	 */
	public static function get_all_rights_labels( ){
		return self::rights;
	}
	/**
	 * Retourne le libellé des droits
	 */
	public static function get_right_label( $right ){
		if( array_key_exists( $right, self::rights ) )
			return self::rights[$right];
		return '';
	}
	/***************************/
	
	/**
	*/
	public static function on_post_class_cb( $classes, $css_class, $post_id ){
		if( get_post_type($post_id) !== self::post_type )
			return $classes;
		
		$mailbox = Agdp_Mailbox::get_mailbox_of_page($post_id);
		if ( $mailbox ){
			$classes[] = self::page_html_class;
			
			// Initialise la page et importe les nouveaux messages
			$messages = self::init_page($mailbox, $post_id);
			if( is_wp_error($messages)  )
				$error = $messages->get_error_message();
			elseif (is_a($messages, 'Exception'))
				$error = $messages->getMessage();
			else
				$error = false;
			if($error){
				echo sprintf('<code class="error"><h3>%s</h3>%s</code>', 'Synchronisation du forum', $error);
			}
		}
		return $classes;
	}
	
	/**
	 * Associe le forum et les commentaires de la page.
	 * Fonction appelée par le shortcode [forum "nom du forum"]
	 * Appelle la synchronisation IMAP.
	 */
	public static function init_page($mailbox, $page = false){
		if( is_numeric( $page )){
			if (!($page = get_post($page)))
				return false;
		}
		elseif (! $page ){
			debug_log(__CLASS__ . '::init_page', '! $page');
			return false;
		}

		if( ! current_user_can('moderate_comments')
		&& $page->comment_status === 'closed' )
			return false;
		
		if( $mailbox ){
			$import_result = Agdp_Mailbox::synchronize($mailbox);
		}
		else
			$import_result = true;
		
		add_action('pre_get_comments', array(__CLASS__, 'on_pre_get_comments'), 10, 1 );
		add_action('comments_pre_query', array(__CLASS__, 'on_comments_pre_query'), 10, 2 );
		
		Agdp_Comment::init_page($mailbox, $page);
		
		return $import_result;
	}
	
	/**
	 * show_comments
	 */
	public static function user_can_see_comments( $forum = false, $user = null, $show_comments = null, $user_subscription = null){
		
		if( ! ($forum = self::get_page( $forum ) ) )
			return null; //TODO
			
		if( $show_comments === null ){
			$meta_key = 'forum_show_comments';
			$show_comments = get_post_meta( $forum->ID, $meta_key, true );
		}
		switch($show_comments){
			case 'never':// => 'Jamais',
				return false;
			case 'admin':// => 'Les administrateurices',
				if( $user === null) $user = wp_get_current_user();
				if( ! $user ) return false;
				if( user_can( $user, 'manage_options') )
					return true;
				//If forum subscriber as admin
				$user_subscription = self::get_user_subscription( $forum, $user );
				return $user_subscription === 'administrator';
				
			case 'moderator':// => 'Les modérateurices',
				if( $user === null) $user = wp_get_current_user();
				if( ! $user ) return false;
				if( user_can( $user, 'moderate_comments') )
					return true;
				//If forum subscriber as moderator or admin
				$user_subscription = self::get_user_subscription( $forum, $user );
				return in_array( $user_subscription, [ 'administrator', 'moderator' ] );
				
				break;
			case 'subscribers':// => 'Les membres du forum',
				if( $user === null) $user = wp_get_current_user();
				if( ! $user ) return false;
				if( user_can( $user, 'moderate_comments') )
					return true;
				$user_subscription = self::get_user_subscription( $forum, $user );
				return in_array( $user_subscription, [ 'administrator', 'moderator', 'subscriber' ] );
			
			case 'connected':// => 'Les utilisateurs connectés',
		
				if( $user === null) $user = get_current_user_id();
				
				return !! $user;
				
			case 'public':
				return true;
			case '':// '(par défaut)',
			default:
		}
	}
	
	/**
	 * 
	 */
	public static function on_pre_get_comments($wp_query){
		if( ! empty($wp_query->query_vars['parent__in'] ) ){
			// debug_log('pre_get_comments parent__in', $wp_query);
			if( !  current_user_can('manage_options') )
				add_action('comments_clauses', array(__CLASS__, 'on_sub_comments_clauses'), 10, 2);
			return;
		}
		
		if( ! self::user_can_see_comments() ){
			$wp_query->query_vars['post__in'] = [ 0 ];
			return;
		}

		$current_user_can_moderate_comments = current_user_can('moderate_comments');
		// debug_log('on_pre_get_comments IN', "current_user_can_moderate_comments $current_user_can_moderate_comments", $wp_query->query_vars);
		if( $current_user_can_moderate_comments ) {
			//Adds pending comments
			$wp_query->query_vars['status'] = ['0', '1'];
		}
		
		
		/* Dans le paramétrage de WP, Réglages / Commentaires :
			Diviser les commentaires en pages, avec N commentaires de premier niveau par page et la PREMIERE page affichée par défaut
			Les commentaires doivent être affichés avec le plus ANCIEN en premier
		*/
		$wp_query->query_vars['orderby'] = 'comment_date_gmt';
		$wp_query->query_vars['order'] = 'DESC';
		
		
	}
	
	public static function on_comments_pre_query($comment_data, $wp_query){
		$current_user_can_moderate_comments = current_user_can('moderate_comments');
		// debug_log('on_comments_pre_query IN', $comment_data, "current_user_can_moderate_comments $current_user_can_moderate_comments");
		if( $current_user_can_moderate_comments ) {
		}
		elseif( ! self::user_can_see_comments() )
			return [];
		
		if( ! empty($wp_query->query_vars['parent__in'] ) ){
			// debug_log('on_comments_pre_query parent__in', $comment_data, $wp_query);
			return;
		}
	}
	
	public static function on_sub_comments_clauses($clauses, $wp_query){
		global $wpdb, $current_user;
		$user_email = $current_user ? $current_user->user_email : false;
		$blog_prefix = $wpdb->get_blog_prefix();
		$clauses['join'] .= " LEFT JOIN {$blog_prefix}commentmeta meta_is_private"
							. " ON meta_is_private.comment_id = {$blog_prefix}comments.comment_ID"
							. " AND meta_is_private.meta_key = 'is-private'"
							. " AND meta_is_private.meta_value != ''";
		if( ! $user_email )
			$clauses['where'] .= " AND meta_is_private.comment_id IS NULL";
		else
			$clauses['where'] .= " AND ( meta_is_private.comment_id IS NULL"
								. " OR {$blog_prefix}comments.comment_author_email = '{$user_email}'"
								. " OR meta_is_private.meta_value =  '{$user_email}')";
		remove_action('comments_clauses', array(__CLASS__, 'on_sub_comments_clauses'), 10, 2);
		return $clauses;
	}
	
	/********************************************/
	
	public static function get_page($page = false){
		if( ! $page )
			$page = self::$current_forum;
		if(is_a($page, 'WP_Post'))
			return $page;
		
		if( ! ($page = get_post($page)) ){
			//Ajax
			if( isset($_REQUEST['data']) && isset($_REQUEST['data']['comment_id']) ){
				$comment = get_comment($_REQUEST['data']['comment_id']);
				return get_post($comment->comment_post_ID);
			}
			else
				$page = Agdp_Newsletter::is_sending_email();
		}
		if( $page->post_type === Agdp_Newsletter::post_type)
			$page = Agdp_Mailbox::get_forum_page($page);
		return $page;
	}
	
	/**
	 * utilisateur
	 */
	public static function get_current_user(){
		$current_user = wp_get_current_user();
		if($current_user && $current_user->ID){
			return $current_user;
		}
		return false;
	}
	public static function get_user_email( $user = false){
		if( ! $user )
			$user = self::get_current_user();
		if($user){
			$email = $user->data->user_email;
			if(is_email($email))
				return $email;
		}
		return false;
	}
	
	/**
	 * user_can_post_comment
	 */
	public static function user_can_post_comment( $page = false, $user = false){
		if( ! current_user_can('moderate_comments')
		&& self::get_forum_right_need_subscription($page)
		&& ( ! ($subscription = self::get_user_subscription( $page, $user) )
			|| $subscription === 'banned')){
			return false;
		}
		return true;
	}
	
	/**
	 * user_can_moderate
	 */
	public static function user_can_moderate( $page = false, $user = false){
		if( ! current_user_can('moderate_comments')
		&& self::get_forum_right_need_subscription($page)
		&& ( ! ($subscription = self::get_user_subscription( $page, $user) )
			|| $subscription === 'subscriber'
			|| $subscription === 'banned')){
			return false;
		}
		return true;
	}
	
	/**
	 * user_can_update_subcription
	 */
	public static function user_can_update_subcription( $page = false, $current_user = false, $user = false, $new_role = 'subscriber'){
		if( current_user_can('moderate_comments') )
			return true;
		$current_user_subscription = self::get_user_subscription( $page, $current_user );		
		switch( $current_user_subscription ){
			case 'administrator' :
				return true;
			case 'moderator' :
				$user_current_subscription = self::get_user_subscription( $page, $user );
				return $user_current_subscription !== 'administrator'
					&& $new_role !== 'administrator';
			case 'banned' :
				return ! Agdp_User::is_same_user( $current_user, $user )
						/* && current_user_can('moderate_comments') */;
			case 'subscriber' :
			default :
				if( ! self::get_forum_right_need_subscription($page) )
					return true;
				return Agdp_User::is_same_user( $current_user, $user )
					&& ( ! $new_role || in_array($new_role, ['none', 'subscriber']) );
		}
		
		return true;
	}
	
	/**
	 * L'utilisateur peut voir le détail des commentaires
	 */
	public static function user_can_see_forum_details($page = false, $user = false){
		return self::user_can_post_comment( $page, $user );
	}
	/**
	 * Option d'abonnement de l'utilisateur
	 */
	public static function get_user_subscription($page = false, $user = false){
		if( ! $user )
			$user = self::get_user_email();
		return self::get_subscription($user, $page);
	}
	
	public static function get_subscription_meta_key($page = false){
		$page = self::get_page($page);
		return sprintf('%s_subscr_%d_%d', self::tag, get_current_blog_id(), $page->ID);
	}
	
	/**
	 * Retourne le meta value d'abonnement pour l'utilisateur
	 * Parameter $user : WP_User | int | email
	 */
	public static function get_subscription( $user, $page = false){
		// $page = self::get_page($page);
		if( is_a($user, 'WP_User') )
			$user_id = $user->ID;
		elseif( is_numeric($user) )
			$user_id = $user;
		else
			$user_id = email_exists( sanitize_email($user) );
		if( ! $user_id)
			return false;
		
		$meta_name = self::get_subscription_meta_key($page);
		$meta_value = get_user_meta($user_id, $meta_name, true);
		return $meta_value;
	}
	/**
	 * Supprime le meta value d'abonnement pour l'utilisateur
	 */
	public static function remove_subscription($email, $page = false){
		// $page = self::get_page($page);
		
		$user_id = email_exists( $email );
		if( ! $user_id)
			return true;
		
		$meta_name = self::get_subscription_meta_key($page);
		delete_user_meta($user_id, $meta_name, null);
		
		return true;
	}
	/**
	 * Ajoute ou met à jour le meta value d'abonnement pour l'utilisateur
	 */
	public static function update_subscription($email, $role, $page = false){
		if( ! self::user_can_update_subcription( $page, true, $email, $role ) ){
			debug_log(__CLASS__ . '::update_subscription ! user_can_update_subcription', $email, get_current_user_id(), $page ? $page->post_tile : 'no page');
			return false;
		}
		// debug_log(__CLASS__ . '::update_subscription', $email, $role, get_current_user_id(), $page ? $page->post_tile : 'no page');
			
		$user_id = email_exists( $email );
		if( ! $user_id){
			if( ! $role || $role == 'none')
				return false;
			$user = self::create_subscriber_user($email, false, false);
			if( ! $user )
				return false;
			$user_id = $user->ID;
		} elseif( $role ) {
				$user = new WP_User( $user_id );
				Agdp_User::promote_user_to_blog($user);
		}
		$meta_name = self::get_subscription_meta_key($page);
		$previous_role = get_user_meta($user_id, $meta_name, true);
		if( $previous_role === $role )
			return false;
		update_user_meta($user_id, $meta_name, $role);
		return true;
	}
	
	/**
	 * Teste si la page est un forum
	 */
	public static function post_is_forum( $post ){
		if( is_numeric($post) )
			$post = get_post($post);
		if($post && $post->post_type === 'page'){
			$meta_key = AGDP_PAGE_META_MAILBOX;
			if( $mailbox_id = get_post_meta( $post->ID, $meta_key, true)){
				return true;
			}
			
		}
		return false;
	}
	
	/**
	 * Retourne l'état d'un commentaire à importer selon l'utilisateur lié
	 */
	public static function get_forum_comment_approved($page = false, $user = false, $email = false) {
		$post_status = self::get_forum_post_status($page, $user, $email);
		switch( $post_status ){
			case 'publish':
				return 1;
			case 'draft':
				return 0;
			case 'pending':
				return 0;
			case 'trash':
			case 'spam':
				return $post_status;
			default:
				return 0;
		}
	}
	
	/**
	 * Retourne le paramètre de droit d'un forum
	 */
	public static function get_forum_right( $page = false ) {
		//Cache sur current_forum
		if( self::$current_forum ) {
			if( ! $page || (self::$current_forum === $page) ){
				return self::$current_forum_rights;
			}
			elseif( is_a(self::$current_forum, 'WP_Post') ){
				if( is_a($page, 'WP_Post') ){
					if( self::$current_forum->ID === $page->ID )
						return self::$current_forum_rights;
				}
				elseif( self::$current_forum->ID === $page )
					return self::$current_forum_rights;
			}
			else {
				if( is_a($page, 'WP_Post') ){
					if( self::$current_forum === $page->ID )
						return self::$current_forum_rights;
				}
				elseif( self::$current_forum === $page )
					return self::$current_forum_rights;
			}
		}
		if( ! $page )
			if( ! ( $page = get_post() ) )
				return false;
		return Agdp_Mailbox::get_page_rights( $page );
	}
	
	/**
	 * Indique que le droit sur le forum nécessite une adhésion (right A ou AO)
	 */
	public static function get_forum_right_need_subscription( $page = false, $right = false ) {
		if( ! $right )
			$right = self::get_forum_right( $page );
		return in_array( $right, ['A', 'AO'] );
	}

	/**
	 * Retourne l'état d'un post à importer selon l'utilisateur lié
	 */
	 public static function get_forum_post_status($page = false, $user = false, $email = false, $post_source = false) {
		
		$right = self::get_forum_right( $page );
		if( ! $right || $right === 'P' )
			return 'publish';
		
		if( ! $user && ! $email )
			$user = self::get_current_user();
		
		if(is_a($user, 'WP_User')){
			$user_id = $user->ID;
		}
		elseif( is_numeric($user) ){
			$user_id = $user;
			$user = new WP_USER($user_id);
		}
		else
			$user = false;
		if( ! $user && $email ){
			if( $user_id = email_exists($email) ){				
				if( is_multisite() ){
					$blogs = get_blogs_of_user($user_id, false);
					if( ! isset( $blogs[ get_current_blog_id() ] ) )
						$user_id = false;
				}
				if( $user_id )
					$user = new WP_USER($user_id);
			}
		}
		if( ! $user){
			if( $email && $right === 'E' //'Validation par e-mail'
			&& $post_source && in_array( $post_source, [ 'imap', 'email', 'mailbox'] ) )
				return 'publish';
			return 'pending';
		}
		
		if( $user && ! $email )
			$email = $user->user_email;
		
		if( user_can( $user, 'moderate_comments') )
			return 'publish';
		
		$user_subscription = self::get_subscription($user->user_email, $page);
		// debug_log('get_forum_post_status $user_subscription', $page->post_title, $user_subscription, $right, $post_source);
		switch($user_subscription){
			case 'administrator' :
			case 'moderator' :
				return 'publish';
			case 'subscriber' :
				switch( $right ){
					case '';
					case false;
					case 'P' : //Public;
					case 'E' : //'Validation par e-mail';
					case 'C' : //'Connexion requise';
					case 'A' : //'Adhésion requise';
						return 'publish';
					case 'CO' : //'Inscription cooptée et connexion requise';
					case 'AO' : //'Adhésion cooptée requise';
						if( $user_id === get_current_user_id() )
							return 'publish';
					default:
						return 'pending';
				}
			case 'banned' :
				return 'draft';
			default:
				switch( $right ){
					case '' : 
					case false : 
					case 'P' : //Public;
						return 'publish';
					case 'E' : //'Validation par e-mail';
						if( $post_source && in_array( $post_source, [ 'imap', 'email', 'mailbox'] ))
							return 'publish';
						return 'pending';
					default:
						return 'draft';
				}
		}
		return false;
	}
	
	/**
	 * Retourne les adresses email associées comme source du forum
	 */
	public static function get_forum_source_emails($forum_id){
		if( is_a($forum_id, 'WP_Post') )
			$forum_id = $forum_id->ID;
		$mailbox_id = get_post_meta( $forum_id, AGDP_PAGE_META_MAILBOX, true);
		return array_keys(Agdp_Mailbox::get_emails_dispatch( $mailbox_id, $forum_id ));
	}
	
	/**
	 * Retourne les forums associés à une adresse email
	 */
	public static function get_forums_of_email($email){
		return self::get_forums( [
					'meta_query' => [[
						'key' => 'agdpmailbox',
						'value' => '0',
						'compare' => '>'
					], [
						'key' => 'forum_email',
						'value' => $email,
						'compare' => '='
					]]
				]);
	}
	
	/**
	 * Avant affichage, filtre ou ajoute des commentaires
	 */
	public static function on_comments_array($comments, $post_id){
		debug_log('on_comments_array IN', $post_id, count($comments));
			
		if( current_user_can('moderate_comments') ) 
			return $comments;
		
		
		global $current_user;
		$current_user_email = $current_user->user_email;
		// is-public != false
		$public_comments = [];
		foreach($comments as $comment){
			$meta_key = 'posted_data_is-public';
			if( $comment->comment_author_email != $current_user_email 
			//TODO && (! $parent_comment || $parent_comment->comment_author_email != $current_user_email)
			// && ($posted_data = get_comment_meta($comment->comment_ID, $meta_key, true)) ){
			&& ( $is_public = get_comment_meta($comment->comment_ID, $meta_key, true) ) !== null 
			&& $is_public !== ''
			&& ! $is_public
			){
				// if(isset($posted_data['is-public'])
				// && ! $posted_data['is-public'])
					continue;
			}
			$public_comments[] = $comment;
		}
		return $public_comments;
	}
	
	/**
	 * Retourne l'analyse du forum
	 */
	public static function get_diagram( $blog_diagram, $forum ){
		$posts_pages = $blog_diagram['posts_pages'];
		$forum_id = $forum->ID;
		$diagram = [ 
			'id' => $forum_id, 
			'page' => $forum, 
		];
		
		if( $mailbox = Agdp_Mailbox::get_mailbox_of_page( $forum_id ) ){
			$diagram['mailbox'] = $mailbox;
			$diagram['emails'] = self::get_forum_source_emails( $forum_id );
			$diagram['right'] = self::get_forum_right( $forum_id );
		}
		
		//posts_page
		foreach( $posts_pages as $post_type => $posts_page){
			if( $forum_id === $posts_page['id'] ){
				$diagram['posts_page'] = $posts_page['page'];
				$diagram['posts_type'] = $post_type;
				
				$diagram = array_merge( Agdp_Page::get_diagram( $blog_diagram, $forum )
									, $diagram );
				break;
			}
		}
		if( empty( $diagram['posts_page'] ) ){
			$diagram['posts_page'] = $forum;
			$diagram['posts_type'] = $forum->post_type;
				
			$diagram = array_merge( Agdp_Page::get_diagram( $blog_diagram, $forum )
								, $diagram );
		}
		
		if( $mailbox ){
			$meta_key = 'forum_moderate';
			$diagram[ $meta_key ] = get_post_meta($forum_id, $meta_key, true);
			if( $diagram['posts_type'] === 'page' ){
				$meta_key = 'forum_show_comments';
				$diagram[ $meta_key ] = get_post_meta($forum_id, $meta_key, true);
				
				$diagram[ 'comment_status' ] = $forum->comment_status;
			}
		}
		
		return $diagram;
	}
	/**
	 * Rendu Html d'un diagram
	 */
	public static function get_diagram_html( $page, $diagram = false, $blog_diagram = false ){
		if( ! $diagram ){
			if( ! $blog_diagram )
				throw new Exception('$blog_diagram doit être renseigné si $diagram ne l\'est pas.');
			$diagram = self::get_diagram( $blog_diagram, $page );
		}
		$html = '';
		
		$html .= parent::get_diagram_html( $page, $diagram, $blog_diagram );
		
		if( empty($diagram['mailbox']) )
			return $html;
		
		$property = 'right';
		if( $diagram[ $property ] ){
			$admin_edit = is_admin() ? sprintf(' <a href="/wp-admin/post.php?post=%d&action=edit#agdp_forum-properties">%s</a>'
					, $page->ID
					, Agdp::icon('edit show-mouse-over')
				) : '';
			$html .= sprintf('<div>%s Droits du forum : %s%s</div>'
					, Agdp::icon($diagram[ $property ] === 'P' ? 'unlock' : 'lock')
					, self::get_right_label( $diagram[ $property ] )
					, $admin_edit
				);
		}
		
		$property = 'forum_moderate';
		if( $diagram[ $property ] )
			$html .= sprintf('<div>%s Modération systématique</div>'
					, Agdp::icon('lock')
				);
		
		$property = 'forum_show_comments';
		if( ! empty($diagram[ $property ]) ){
			$html .= sprintf('<div>%s Affichage des commentaires : %s</div>'
					, Agdp::icon('visibility')
					, self::show_comments_modes[$diagram[ $property ]]
				);
			if( $diagram[ $property ] !== 'never' ){
				$property = 'comment_status';
				if( $diagram[ $property ] !== 'open' )
					$html .= sprintf('<div>%s Les commentaires ne sont pas affichés</div>'
							, Agdp::icon('lock')
						);
			}
		}

		$emails = '';
		foreach( $diagram['emails'] as $email ){
			//TODO find taxonomy diffusion avec connexion mailto:$email
			
			if( $emails )
				$emails .= sprintf('<small> ou %s</small>', $email);
			else
				$emails = $email;
		}
		if( $emails ){
			$html .= sprintf('<h3 class="toggle-trigger">%s Par e-mail : %s</h3>'
					, Agdp::icon('welcome-add-page')
					, $emails
				);
			$html .= '<div class="toggle-container">';
						
				if( $diagram['mailbox'] ){
					$html .= Agdp_Mailbox::get_diagram_html( $diagram['mailbox'], false, $blog_diagram );
				}
			$html .= '</div>';
		}
		
		return $html;
	}
	
}
?>