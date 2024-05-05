<?php

/**
 * AgendaPartage -> Forum
 * Structurellement, un forum est une page dont les commentaires sont alimentés et dont les commentaires sont utilisés dans une newsletter.
 * Gestion des commentaires d'une page. Les commentaires sont importés depuis une boîte e-mails ou une interception de mail sortant.
 *
 * Voir aussi AgendaPartage_Mailbox
 *
 * Un forum est une page qui doit afficher ses commentaires.
 */
class AgendaPartage_Forum {

	const post_type = 'page';
	const tag = 'agdpforum';
	const page_class = 'use-agdpforum';
	
	// const user_role = 'author';

	private static $initiated = false;
	
	public static $properties = [];
	
	private static $current_forum;
	private static $current_forum_rights;
		
		
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
		'CO' => 'Inscription cooptée et connexion requise',
		'A' => 'Adhésion requise',
		'AO' => 'Adhésion cooptée requise',
		'M' => 'Modérateurice',
		'X' => 'Administrateurice',
	];
	
	const subscription_roles = [
			'' => '(non défini)',
			'administrator' => 'Administrateurice',
			'moderator' => 'Modérateurice',
			'subscriber' => 'Membre',
			'banned' => 'Banni-e',
		];

	/**
	 * Hook
	 */
	public static function init_hooks() {
		
		global $pagenow;
		if ( $pagenow !== 'edit.php' && $pagenow !== 'post.php' ) {
			add_action( 'post_class', array(__CLASS__, 'on_post_class_cb'), 10, 3);
		}
		add_action( 'wp_ajax_'.AGDP_TAG.'_comment_action', array(__CLASS__, 'on_wp_ajax_comment') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_comment_action', array(__CLASS__, 'on_wp_ajax_comment') );
		
		if ( $pagenow === 'wp-comments-post.php' ) {
			add_filter('pre_comment_approved', array(__CLASS__, 'on_pre_comment_approved'), 10, 2 );
			add_filter('preprocess_comment', array(__CLASS__, 'on_preprocess_comment') );
			add_filter('comment_post', array(__CLASS__, 'on_comment_post'), 10, 3 );
		}
		
		add_filter('wp_get_nav_menu_items', array(__CLASS__, 'on_wp_get_nav_menu_items'), 10, 3);
		
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
			if( ! (self::$current_forum_rights = AgendaPartage_Mailbox::get_page_rights( $page )) )
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
	public static function get_hidden_forums( $args = false){
		if( current_user_can('moderate_comments') ) 
			return self::get_forums();
		
		$hidden_forums = [];
		
		$current_user = self::get_current_user();
		foreach( AgendaPartage_Mailbox::get_emails_dispatch() as $email => $dispatch){
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
	}
	
	
	
	/**
	 * Retourne les newsletters utilisant un forum.
	 * $exclude_sub_forums exclut les newsletters qui utilise les abonnés d'une autre lettre-info
	 */
	public static function get_newsletters($forum_id, $exclude_sub_forums = false){
		if( is_a($forum_id, 'WP_Post') )
			$forum_id = $forum_id->ID;
		
		$query = new WP_Query([
			'post_type' => AgendaPartage_Newsletter::post_type,
			'meta_key' => 'source',
			'meta_value' => sprintf('page.%d', $forum_id),
		]);
		$posts = $query->get_posts();
		if( ! $exclude_sub_forums )
			return $posts;
		
		$meta_key = 'subscription_parent';
		$newsletters = [];
		foreach( $posts as $newsletter )
			if( ! get_post_meta( $newsletter->ID, $meta_key, true ) )
				$newsletters[] = $newsletter;
		return $newsletters;
	}
	
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
		$mailbox = AgendaPartage_Mailbox::get_mailbox_of_page($post_id);
		if ( $mailbox ){
			$classes[] = self::page_class;
			
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
			$mailbox = AgendaPartage_Mailbox::get_mailbox($mailbox);
			
			$import_result = AgendaPartage_Mailbox::synchronize($mailbox, $page);
		}
		else
			$import_result = true;
		
		add_action('pre_get_comments', array(__CLASS__, 'on_pre_get_comments'), 10, 1 );
		add_action('comments_pre_query', array(__CLASS__, 'on_comments_pre_query'), 10, 2 );
		add_filter('comment_form_defaults', array(__CLASS__, 'on_comment_text_before') );
		// add_filter('comment_form_default_fields', array(__CLASS__, 'on_comment_form_fields') );
		add_filter('comment_form_fields', array(__CLASS__, 'on_comment_form_fields') );
		add_filter('comment_text', array(__CLASS__, 'on_comment_text'), 10, 3 );
		add_filter('get_comment_time', array(__CLASS__, 'on_get_comment_time'), 10, 5 );
		add_filter('comment_reply_link', array(__CLASS__, 'on_comment_reply_link'), 10, 4 );
		// add_filter('comment_reply_link_args', array(__CLASS__, 'on_comment_reply_link_args'), 10, 3 );
		add_filter('get_comment_author_link', array(__CLASS__, 'on_get_comment_author_link'), 10, 3 );
		
		// if( self::get_current_forum_rights( $page ) != 'P' )
			self::get_current_forum_rights( $page );//set current_forum $page
			if( self::user_can_see_forum_details( $page ) )
				add_filter('get_comment_author_link', array(__CLASS__, 'on_get_comment_author_status'), 11, 3 );
		
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
		 
	/**
	 * Adaptation du formulaire de commentaire
	 */
	public static function on_comment_text_before($defaults){
		foreach($defaults as $key=>$value)
			$defaults[$key] = str_replace('Commentaire', 'Message', 
							 str_replace('commentaire', 'message', $value));
		$defaults['class_form'] .= ' agdp-forum';
		return $defaults;
	}
	
	/**
	 * Ajout du champ Titre au formulaire de commentaire
	 */
	public static function on_comment_form_fields($fields){
		if( $comment_css = self::get_property('comment_css') ){
			echo '<style>'.  $comment_css . '</style>';
		}
		
		if( ( ! isset($_REQUEST['replytocom']) && self::get_property_is_value('comment_form', false))
		|| ! self::user_can_post_comment()	){
			$fields['comment'] = '<script>jQuery("#respond.comment-respond").remove();</script>';
			return $fields;
		}
		
		$title_field = '<p class="comment-form-title"><label for="title">Titre <span class="required">*</span></label> <input id="title" name="title" type="text" maxlength="255" required></p>';
		//ED240505 suppression de la classe .if-respond masquée en css.
		$send_email_field = '<div class="comment-form-send-email"><label for="send-email">'
			. '<input id="send-email" name="send-email" type="checkbox">'
			. ' Envoyez votre réponse par e-mail à l\'auteur du message</label></div>';
		$is_private_field = '<p class="comment-form-is-private"><label for="is-private">'
			. '<input id="is-private" name="is-private" type="checkbox">'
			. ' Ce message est privé, entre vous et l\'auteur du message. Sinon, il est visible par tous sur ce site.</label></p>';
		$fields['comment'] = $title_field
							. (isset($fields['comment']) ? $fields['comment'] : '')
							. $send_email_field
							. $is_private_field;
		unset($fields['url']);
		return $fields;
	}
	
	/*********************************
	 * Enregistrement d'un Commentaire
	 */
	/**
	 * Vérifie les données lors de l'enregistrement du commentaire
	 */
	public static function on_pre_comment_approved( $approved, $commentdata ){
		if( ! ($mailbox = AgendaPartage_Mailbox::get_mailbox_of_page($commentdata['comment_post_ID']) ))
			return $approved;
		if( ! self::get_forum_comment_approved($commentdata['comment_post_ID']) )
			return 0;
		
		if( empty( $_POST['title'] )
		&& empty($commentdata['comment_meta'])
		&& empty($commentdata['comment_meta']['title']) )
			return new WP_Error( 'require_valid_comment', __( '<strong>Erreur :</strong> Veuillez indiquer un titre à votre message.' ), 200 );

		return $approved;
	}
	
	/**
	 * Ajout des metas (title, send-email, is-private) lors de l'enregistrement du commentaire
	 */
	public static function on_preprocess_comment($commentdata ){
		// debug_log('on_preprocess_comment');
		
		if( ! ($mailbox = AgendaPartage_Mailbox::get_mailbox_of_page($commentdata['comment_post_ID']) ))
			return $commentdata;
		// debug_log($commentdata);
		
		if( empty($commentdata['comment_meta']) )
			$commentdata['comment_meta'] = [];
		
		if( empty( $_POST['title'] )){
			if($commentdata['comment_parent']){
				$parent_subject = get_comment_meta( $commentdata['comment_parent'], 'title', true);
				$_POST['title'] = sprintf('Re: %s', $parent_subject);
			}
			else {
				//on_pre_comment_approved se charge de l'erreur
				return $commentdata;
			}
		}
		$commentdata['comment_meta'] = array_merge([ 'title' => $_POST['title'] ], $commentdata['comment_meta']);
		
		if( ! empty( $_POST['send-email'] )){
			$commentdata['comment_meta'] = array_merge([ 'send-email' => $_POST['send-email'] ], $commentdata['comment_meta']);
		}
		if( ! empty( $_POST['is-private'] )){
			$parent_comment = get_comment( $commentdata['comment_parent']);
			//Mémorise l'email de l'auteur du commentaire parent pour le filtrage
			$commentdata['comment_meta']['is-private'] = $parent_comment->comment_author_email;
		}
		
		return $commentdata;
	}
	
	/**
	 * Après l'enregistrement du commentaire
	 */
	public static function on_comment_post($comment_id, $comment_approved, $commentdata ){
		if( empty($commentdata['comment_meta']['send-email'])
		|| $commentdata['comment_meta']['send-email'] === 'done')
			return;
		self::send_response_email($comment_id );
	}
	/**
	 * Envoie l'email de réponse
	 */
	public static function send_response_email($comment_id ){
		$comment = get_comment($comment_id);
		
		if( ! ($mailbox = AgendaPartage_Mailbox::get_mailbox_of_page($comment->comment_post_ID) ))
			return;
		$page_link = get_post_permalink($comment->comment_post_ID);
		
		if( empty(($comment->comment_parent))
		|| ! ($parent_comment = get_comment($comment->comment_parent) ))
			return;
		
		$email_to = $parent_comment->comment_author_email;
		
		$email_replyto = $comment->comment_author_email;
		
		$subject = get_comment_meta( $comment_id, 'title', true);
		$parent_subject = get_comment_meta( $parent_comment->comment_ID, 'title', true);
		$parent_date = wp_date('d/m/Y à H:i', strtotime($parent_comment->comment_date_gmt));
		
		$message = $comment->comment_content;
		
		$message .= "\n\n----------------";
		$message .= sprintf("\nCe message a été envoyé depuis le site <a href=\"%s\">%s</a>"
				, $page_link, get_bloginfo( 'name' ) ) ;
		$message .= sprintf("\nIl est a l'initiative de %s (<a href=\"mailto:%s\">%s</a>)."
				, $comment->comment_author 
				, $comment->comment_author_email
				, $comment->comment_author_email ) ;
		$message .= sprintf("\nVous recevez ce message en réponse au votre, intitulé \"%s\" et daté du %s."
				, $parent_subject, $parent_date) ;
		$message .= sprintf("\n\nVous pouvez nous écrire pour signaler tout abus ou manquement aux règles du forum : <a href=\"mailto:%s\">%s</a>."
				, get_bloginfo( 'admin_email' )
				, get_bloginfo( 'admin_email' )) ;
		$message .= sprintf("\nBonne journée.") ;
		
		$message = str_replace("\n", "\n<br>", $message);
		
		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-type: text/html; charset=utf-8';
		$headers[] = 'Content-Transfer-Encoding: quoted-printable';
		
		$headers[] = sprintf('From: %s', get_bloginfo( 'admin_email' ));
		$headers[] = sprintf('Reply-to: %s', $email_replyto);
		
		if($success = wp_mail( $email_to
			, '=?UTF-8?B?' . base64_encode($subject). '?='
			, $message
			, $headers )){
			
			update_comment_meta( $comment_id, 'send-email', wp_date('d/m/Y H:i:s'));
		}
		else{
		}
		
	}
	
	/********************************************/
	
	/**
	 * Affichage du commentaire
	 */
	public static function on_comment_text($comment_text, $comment, $args ){
		
		$send_email = get_comment_meta($comment->comment_ID, 'send-email', true);
		if( $send_email ) {
			echo sprintf('<p class="agdp-notif">%s<code>Un e-mail a été envoyé : %s</code></p>'
				, '<span class="dashicons dashicons-email-alt"></span>'
				, $send_email);
			
			delete_comment_meta( $comment->comment_ID, 'send-email');
		}
		
		if( $comment->comment_approved == 0
		&& current_user_can('moderate_comments')){
			echo sprintf('<div class="unapproved-action-links">%s</div>',
				implode('&nbsp;&nbsp;&nbsp;', self::get_comment_approve_links($comment->comment_ID)));
		}
		if( ! self::get_property_is_value('comment_title', false) ){
			$title = get_comment_meta($comment->comment_ID, 'title', true);	
			echo sprintf('<h3 class="comment-title">%s</h3>', $title);
		}
		if( ! self::user_can_see_forum_details() )
			return '';
		
		return $comment_text;
	}
	/**
	 * Affichage de l'heure du commentaire
	 */
	public static function on_get_comment_time( $comment_time, $format, $gmt, $translate, $comment ){
		$comment_date = $gmt ? $comment->comment_date_gmt : $comment->comment_date;

		$comment_time .= ' (' . date_diff_text($comment_date) . ')';
		
		return $comment_time;
	}
	
	/**
	 * Affichage des attachments
	 */
	public static function get_attachments_links($comment){
		$html = '';
		
		$attachments = get_comment_meta($comment->comment_ID, 'attachments', true);
		if($attachments){
			$upload_dir_info = wp_upload_dir();
			$upload_dir_info['basedir'] = str_replace('\\', '/', $upload_dir_info['basedir']);
			$html .= '<ul class="attachments">';
			foreach($attachments as $attachment){
				if( ! file_exists($attachment) )
					continue;
				$html .= '<li>';
				$extension = strtolower(pathinfo($attachment, PATHINFO_EXTENSION));
				$url = str_replace($upload_dir_info['basedir'], $upload_dir_info['baseurl'], $attachment);
				switch($extension){
					case 'png':
					case 'jpg':
					case 'jpeg':
					case 'bmp':
					case 'tiff':
						$html .= sprintf('<a href="%s"><img src="%s"/></a>', $url, $url);
						break;
					default:
						$html .= sprintf('<a href="%s">%sTélécharger %s</a>'
							, $url
							, '<span class="dashicons-before dashicons-download"></span>'
							, pathinfo($attachment, PATHINFO_BASENAME));
						break;
				}
				$html .= '</li>';
			}
			$html .= '</ul>';
		}
		return $html;
	}
	
	/**
	 * Affichage du commentaire, lien "Répondre"
	 */
	public static function on_comment_reply_link($comment_reply_link, $args, $comment, $post ){
		if( ! self::user_can_see_forum_details() ){
			return '';
		}
		
		$attachments_links = self::get_attachments_links($comment);
		echo $attachments_links;
		
		$user_can_change_comment = self::user_can_change_comment($comment);
		 
		//Statut du message (mark_as_ended). 
		$comment_actions = self::get_comment_mark_as_ended_link($comment->comment_ID);
		
		if( $user_can_change_comment ){
			$comment_actions .= self::get_comment_delete_link($comment->comment_ID);
		}
		
		$comment_actions = sprintf('<span class="comment-agdp-actions">%s</span>', $comment_actions);
		
		if( self::get_property_is_value('reply_link', false) )
			$comment_reply_link = $comment_actions;
		else
			$comment_reply_link = preg_replace('/(\<\/div>)$/', $comment_actions . '$1', $comment_reply_link);
		
		return $comment_reply_link;
	}
	
	/**
	 * Retourne le html d'action pour marqué un message comme étant terminé
	 */
	private static function get_comment_mark_as_ended_link($comment_id){
		if( self::get_property_is_value('mark_as_ended', false) )
			return '';
			
		$data = [
			'comment_id' => $comment_id
		];
		
		$status = get_comment_meta($comment_id, 'status', true);
		$status_class = $status == 'ended' ? 'comment-mark_as_ended' : 'comment-not-mark_as_ended';
		$comment_actions = sprintf('<a href="#mark_as_ended" class="comment-agdp-action comment-agdp-action-mark_as_ended %s comment-reply-link">%s</a>'
			, $status_class
			, "Toujours d'actualité ?");
			
		switch($status){
			case 'ended' :
				$caption = '<span class="mark_as_ended">N\'est plus d\'actualité</span>';
				$title = "Vous pouvez rétablir ce message comme étant toujours d'actualité";
				$icon = 'dismiss';
				break;
			default:
				$caption = "Toujours d'actualité ?";
				$title = "Vous pouvez indiquer si ce message n'est plus d'actualité";
				$icon = 'admin-post'; //info-outline';
		}
		if ( $status )
			$data['status'] = $status;
		//La confirmation est gérée dans public/js/agendapartage.js Voir plus bas : on_ajax_action_mark_as_ended
		return AgendaPartage::get_ajax_action_link(false, ['comment','mark_as_ended'], $icon, $caption, $title, false, $data);
	}
	/**
	 * Retourne le html d'action pour supprimer un message 
	 */
	private static function get_comment_delete_link($comment_id){
		
		$data = [
			'comment_id' => $comment_id
		];
			
		$caption = "Supprimer";
		$title = "Vous pouvez supprimer définitivement ce message";
		$icon = 'trash';

		//La confirmation est gérée dans public/js/agendapartage.js Voir plus bas : on_ajax_action_delete
		return AgendaPartage::get_ajax_action_link(false, ['comment','delete'], $icon, $caption, $title, true, $data);
	}
	/**
	 * Retourne le html d'action pour approuver un message 
	 */
	private static function get_comment_approve_links($comment_id){
		$links = [];
		
		$data = [
			'comment_id' => $comment_id
		];
			
		$caption = "Approuver";
		$title = "Approuver ce message";
		$icon = 'admin-post';
		//La confirmation est gérée dans public/js/agendapartage.js Voir plus bas : on_ajax_action_approve
		$links[] = AgendaPartage::get_ajax_action_link(false, ['comment','approve'], $icon, $caption, $title, true, $data);

		$caption = "Supprimer";
		$title = "Mettre ce message à la corbeille";
		$icon = 'trash';
		//La confirmation est gérée dans public/js/agendapartage.js Voir plus bas : on_ajax_action_delete
		$links[] = AgendaPartage::get_ajax_action_link(false, ['comment','delete'], $icon, $caption, $title, true, $data);

		$caption = "Envoyer un message";
		$title = "Envoyer un message à l'auteur";
		$icon = 'email-alt';
		//La confirmation est gérée dans public/js/agendapartage.js Voir plus bas : on_ajax_action_mailto_author
		$links[] = AgendaPartage::get_ajax_action_link(false, ['comment','mailto_author'], $icon, $caption, $title, false, $data);

		return $links;
	}
	
	// /**
	 // * Affichage du commentaire, lien "Répondre"
	 // */
	// public static function on_comment_reply_link_args($args, $comment, $post ){
		
		// debug_log('on_comment_reply_link_args', $args, $comment);
		
		// return $args;
	// }
	
	/**
	 * Affichage de l'auteur du commentaire
	 */
	public static function on_get_comment_author_link( $comment_author_link, $comment_author, $comment_id ){
		if( ! self::user_can_see_forum_details() )
			return '';
		
		if( ! strpos( $comment_author_link, ' href=' ) 
		 && ! strpos( $comment_author_link, '@' ) ){
			$comment = get_comment($comment_id);
			
			if( $comment->comment_author_email ) {
				$comment_author_link = sprintf('<a href="mailto:%s">%s</a>', $comment->comment_author_email, $comment_author);
			}
		}
		return $comment_author_link;
	}
	/**
	 * Affichage du statut de l'auteur du commentaire
	 */
	public static function on_get_comment_author_status( $comment_author_link, $comment_author, $comment_id ){
		$status = false;
		$comment = get_comment($comment_id);
		
		if( $comment->comment_author_email 
		  && ( $user_id = email_exists($comment->comment_author_email) )
		  && ( $user = new WP_User($user_id) )){
			if( user_can( $user, 'manage_options' ) ){
				$status = 'administrateurice';
			}
			elseif( user_can( $user, 'moderate_comments' ) ){
				$status = 'peut modérer';
			}
			else {
				if( self::get_forum_right_need_subscription() ){
					switch($subscription = self::get_subscription( $comment->comment_author_email, self::$current_forum )){
						case false:
						case '':
							$status = 'non-membre';
							break;
						default:
							$status = isset(self::subscription_roles[$subscription]) ? strtolower(self::subscription_roles[$subscription]) : '?'.$subscription;
							break;
					}
				}
			}
		}
		else
			$status = 'inconnu-e';
		if( $status )
			if( $user_id )
				$comment_author_link .= sprintf('<a href="/wp-admin/user-edit.php?user_id=%d#forums" class="comment-user-status">(%s)</a>'
					, $user_id, $status);
			else
				$comment_author_link .= sprintf('<span class="comment-user-status">(%s)</span>', $status);
		return $comment_author_link;
	}
	
	/**
	 * Requête Ajax sur les commentaires
	 */
	public static function on_wp_ajax_comment() {
		if( ! AgendaPartage::check_nonce()
		|| empty($_POST['method']))
			wp_die();
		
		$ajax_response = '';
		
		$method = $_POST['method'];
		$data = $_POST['data'];
		
		try{
			//cherche une fonction du nom "on_ajax_action_{method}"
			$function = array(__CLASS__, sprintf('on_ajax_action_%s', $method));
			$ajax_response = call_user_func( $function, $data);
		}
		catch( Exception $e ){
			$ajax_response = sprintf('Erreur dans l\'exécution de la fonction :%s', var_export($e, true));
		}
		
		// Make your array as json
		wp_send_json($ajax_response);
	 
		// Don't forget to stop execution afterward.
		wp_die();
	}

	/**
	 * Requête Ajax de changement d'état du commentaire
	 */
	public static function on_ajax_action_mark_as_ended($data) {
		if( isset($data['status']) )
			$status = $data['status'] === 'ended' ? 'open' : 'ended';
		else
			$status = 'ended';
		if (update_comment_meta($data['comment_id'], 'status', $status))
			return 'replace:' . self::get_comment_mark_as_ended_link($data['comment_id']);
		return false;
	}
	/**
	 * Requête Ajax de suppression du commentaire
	 */
	public static function on_ajax_action_delete($data) {
		update_comment_meta($data['comment_id'], 'deleted', wp_date(DATE_ATOM));
		
		$args = ['comment_ID' => $data['comment_id'], 'comment_approved' => 'trash'];
		$comment = wp_update_comment($args, true);
		if ( ! is_a($comment, 'WP_Error') ){		
			$attachments = get_comment_meta($data['comment_id'], 'attachments', true);
			if($attachments){
				foreach($attachments as $attachment){
					if( file_exists($attachment) )
						unlink($attachment);
				}
			}
			return 'js:$actionElnt.parents(\'.comment:first\').remove();';
		}
		
		return $comment->get_error_message();
	}
	/**
	 * Requête Ajax d'approbation du commentaire
	 */
	public static function on_ajax_action_approve($data) {
		if( current_user_can('moderate_comments') ) {
			$args = ['comment_ID' => $data['comment_id'], 'comment_approved' => 1];
			$comment = wp_update_comment($args, true);
			if ( is_a($comment, 'WP_Error') )
				return $comment->get_error_message();
			update_comment_meta($data['comment_id'], 'approved'
				, sprintf('%s / %s', wp_date(DATE_ATOM), (wp_get_current_user())->name)
			);
				
			// return 'replace:<h4 class="text-green">Approuvé</h4>';
			$comment = get_comment($data['comment_id']);
			self::init_page(false, $comment->comment_post_ID);
			return sprintf('js:jQuery("#comment-%d").replaceWith("%s");'
				, $comment->comment_ID
				, str_replace("\n", '', str_replace('"', '\"', wp_list_comments(['echo'=>false], [ $comment ]))));
		}
		return 'Vous n\'êtes pas autorisé à exécuter cette action.';
	}
	/**
	 * Requête Ajax de désapprobation du commentaire avec réponse
	 */
	public static function on_ajax_action_disapprove($data) {
		return self::on_ajax_action_delete($data);
	}
	/**
	 * Requête Ajax de récupération d'un (nouveau) commentaire
	 */
	public static function on_ajax_action_get($data) {
		return wp_list_comments(['echo'=>false], [ get_comment($data['comment_id']) ]);
	}
	/**
	 * Requête Ajax d'envoi de message à l'auteur du commentaire
	 */
	public static function on_ajax_action_mailto_author($data) {
		if( current_user_can('moderate_comments') ) {
			$comment = get_comment($data['comment_id']);
			$email = $comment->comment_author_email;
			$title = get_comment_meta($comment->comment_ID, 'title', true);
			$send_date = get_comment_meta($comment->comment_ID, 'send_date', true);
			$send_date = date('d/m/Y à H:i', strtotime($send_date));
			$subject = sprintf('[Modération] %s', $title);
			$url = get_post_permalink($comment->comment_post_ID, true);
			$message = sprintf("Bonjour,"
."\nEn réponse à votre message \"%s\" du %s,"
."\n"
."\nCordialement,"
."\nL'équipe de modération de <a href=\"%s\">%s</a>."
	, $title, $send_date, $url, get_bloginfo('name'));

			$query = [ 
				'data' => [
					'comment_id' => $comment->comment_ID,
					'email' => $email
				],
				'action' => AGDP_TAG . '_comment_action',
				'method' => 'mailto_author_send'
			];
			$html = '<div class="wrapper"><div class="ajax_action-response">'
				.'Votre message à l\'auteurice :&nbsp;'
				. '<span class="dashicons dashicons-no-alt close-box" onclick="jQuery(this).parents(\'.wrapper:first\').remove();"></span>'
				. sprintf('<form class="agdp-ajax-action" data="%s">', esc_attr(json_encode($query)))
				. wp_nonce_field(AGDP_TAG . '-comment_mailto_author', AGDP_TAG . '-comment_mailto_author_send', true, false)
				.sprintf('<input type="text" name="mail_subject" size="7" value="%s"/>', esc_attr($subject))
				.sprintf('<textarea name="mail_body" rows="7">%s</textarea>', $message)
				.sprintf('<input type="submit" value="Envoyer à %s" />', $email)
				.'</form></div><br></div>'
			;
			return sprintf('js:jQuery(this).parents("article:first").after("%s");$spinner.remove();'
				, str_replace("\n", '\n', str_replace('"', '\"', $html)));
		}
		return 'Vous n\'êtes pas autorisé à exécuter cette action.';
	}
	/**
	 * Envoi du mail à l'auteur
	 */
	public static function on_ajax_action_mailto_author_send($data) {
		if( current_user_can('moderate_comments') ) {
			$comment = get_comment($data['comment_id']);
			$email = $data['email'];
			$subject = $_POST['mail_subject'];
			$message = $_POST['mail_body'];
			$message = str_replace("\\'", "'", str_replace('\"', '"', $message));
			
			$page = get_post($comment->comment_post_ID);
			$url = sprintf('<a href="%s#comment-%d">%s</a>', get_post_permalink($page, true), $comment->comment_ID, $page->post_title);
			
			$message .= "\n\n-------------\n<i>Votre message original :</i>\n\n"
				. $comment->comment_content
				. "\n\n-------------\n"
				. $url;
			
			$message = str_replace("\n",'<br>', $message);
			
			$message = '<!DOCTYPE html><html>'
				. '<head>'
					. '<title>' . $subject . '</title>'
				. '</head>'
				. sprintf('<body style="white-space: pre-line;">%s</body>', $message)
				. '</html>';
			$headers = array();
			$attachments = array();
			
			$headers[] = 'MIME-Version: 1.0';
			$headers[] = 'Content-type: text/html; charset=utf-8';
			$headers[] = 'Content-Transfer-Encoding: quoted-printable';
			
			$headers[] = sprintf('From: %s', get_bloginfo('admin_email'));
			$headers[] = sprintf('Reply-to: %s', get_bloginfo('admin_email'));
			
			$to = $email;
			if($success = wp_mail( $to
				, '=?UTF-8?B?' . base64_encode($subject). '?='
				, $message
				, $headers, $attachments )){
				return 'Le message a été envoyé.' . AgendaPartage::icon('success');
			}
			else{
				return "L'e-mail n'a pas pu être envoyé" . AgendaPartage::icon('error');
			}
		}
		return 'Vous n\'êtes pas autorisé à exécuter cette action.';
	}

	/*************************************************/
	
	
	public static function get_page($page = false){
		if( ! $page )
			$page = self::$current_forum;
		if(is_a($page, 'WP_Post'))
			return $page;
		
		return get_post($page);
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
	 * L'utilisateur peut voir le détail des commentaires
	 */
	public static function user_can_see_forum_details($page = false, $user = false){
		return self::user_can_post_comment( $page, $user );
	}
	/**
	 * Teste l'autorisation de modifier un commentaire selon l'utilisateur connecté
	 */
	public static function user_can_change_comment($comment){
		if(!$comment)
			return false;
		if(is_numeric($comment))
			$comment = get_comment($comment);
		
		if($comment->comment_approved == 'trash'){
			return false;
		}
		
		//Admin : ok 
		//TODO check is_admin === interface ou user
		//TODO user can edit only his own posts
		if( is_admin() && !wp_doing_ajax()){
			return true;
		}		
		
		if(is_user_logged_in()){
			global $current_user;
			//Rôle autorisé
			if(	$current_user->has_cap( 'moderate_comments' ) ){
				return true;
			}
			
			//Utilisateur associé
			if(	$current_user->ID == $comment->comment_author ){
				return true;
			}
			
			$user_email = $current_user->user_email;
			if( is_email($user_email)
			 && $user_email == $comment->comment_author_email ){
				return true;
			}
		}
		
		return false;
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
	public static function update_subscription($email, $period, $page = false){
		// $page = self::get_page($page);
		
		$user_id = email_exists( $email );
		if( ! $user_id){
			if( ! $period || $period == 'none')
				return true;
			$user = self::create_subscriber_user($email, false, false);
			if( ! $user )
				return false;
			$user_id = $user->ID;
		}
		$meta_name = self::get_subscription_meta_key($page);
		update_user_meta($user_id, $meta_name, $period);
		return true;
	}
	
	
	
	public static function post_is_forum( $post ){
		if($post && $post->post_type === 'page'){
			$field_id = 'forums_parent_id';
			if( $post->post_parent
			&& $post->post_parent == AgendaPartage::get_option($field_id) )
				return true;
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
		return AgendaPartage_Mailbox::get_page_rights( $page );
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
	 public static function get_forum_post_status($page = false, $user = false, $email = false) {
		
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
		if( !$user && $email ){
			if( $user_id = email_exists($email) )
				$user = new WP_USER($user_id);
		}
		if( ! $user)
			return 'pending';

		if( $user && ! $email )
			$email = $user->user_email;
		
		if( user_can( $user, 'moderate_comments') )
			return 'publish';
		
		$user_subscription = self::get_subscription($user->user_email, $page);
		// debug_log('get_forum_post_status $user_subscription', $page->post_title, $user_subscription);
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
						return 'pending';
					default:
						return 'draft';
				}
		}
		return false;
	}
	
	/**
	 * Avant affichage, filtre ou ajoute des commentaires
	 */
	public static function on_comments_array($comments, $post_id){
		// debug_log('on_comments_array IN', $post_id, count($comments));
			
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
	 * wp_nav_menu hook
	 * Filtre les menus suivants l'utilisateur et ses abonnements aux forums
	 TODO Cache
	 */
	public static function on_wp_get_nav_menu_items( $items, $menu, $args ){
		// debug_log('on_wp_get_nav_menu_items', $items/* , $menu, $args */ );
		if( current_user_can('moderate_comments') )
			return $items;
		// on cherche plutôt à afficher les seuls titres de Commentaires
		// $hidden_forums_ids = self::get_hidden_forums( ['fields' => 'ids'] );
		// $hidden_menu_items_ids = [];
		// foreach( $items as $index => $menu_item ){
			// if( in_array($menu_item->menu_item_parent, $hidden_menu_items_ids)
			// || ( $menu_item->object === 'page' 
				// && in_array($menu_item->object_id, $hidden_forums_ids))
			// ){
				// $hidden_menu_items_ids[] = $menu_item->ID;
				// unset($items[$index]);
			// }
		// }
		return $items;
	}
	
}
?>