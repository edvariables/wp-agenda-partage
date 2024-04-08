<?php

/**
 * AgendaPartage -> Forum
 * Custom post type for WordPress.
 * 
 * Définition du Post Type agdpforum
 * Mise en forme du formulaire Forum
 *
 * Voir aussi AgendaPartage_Admin_Forum
 *
 * Un forum est associé à une page qui doit afficher ses commentaires.
 * Le forum gère l'importation d'emails en tant que commentaires de la page.
 */
class AgendaPartage_Forum {

	const post_type = 'agdpforum';
	
	// const user_role = 'author';

	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;

			self::init_hooks();
		}
	}

	/**
	 * Hook
	 */
	public static function init_hooks() {
		
		add_action( 'post_class', array(__CLASS__, 'on_post_class_cb'), 10, 3);
		
		add_action( 'wp_ajax_'.AGDP_TAG.'_comment_action', array(__CLASS__, 'on_wp_ajax_comment') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_comment_action', array(__CLASS__, 'on_wp_ajax_comment') );
		
		global $pagenow;
		if ( $pagenow === 'wp-comments-post.php' ) {
			add_filter('pre_comment_approved', array(__CLASS__, 'on_pre_comment_approved'), 10, 2 );
			add_filter('preprocess_comment', array(__CLASS__, 'on_preprocess_comment') );
			add_filter('comment_post', array(__CLASS__, 'on_comment_post'), 10, 3 );
		}
	}
	/*
	 **/
	
	/**
	*/
	public static function on_post_class_cb( $classes, $css_class, $post_id ){
		$forum = self::get_forum_of_page($post_id);
		if ( $forum ){
			$classes[] = 'use-' . self::post_type;
			$classes[] = self::post_type . '-' . $forum->ID;
			
			// Initialise la page et importe les nouveaux messages
			$messages = self::init_page($forum, $post_id);
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
	public static function init_page($forum, $page = false){
		if( ! $page ){
			if (!($page = self::get_page_of_forum( $forum ))){
				debug_log('init_page get_page_of_forum === FALSE', $forum);
				return false;
			}
		}
		elseif( is_int( $page ))
			if (!($page = get_post($page)))
				return false;
		
		
		$forum = self::get_forum($forum);
		
		$import_result = self::synchronize($forum, $page);
		
		add_action('pre_get_comments', array(__CLASS__, 'on_pre_get_comments'), 10, 1 );
		add_action('comments_pre_query', array(__CLASS__, 'on_comments_pre_query'), 10, 2 );
		add_filter('comment_form_defaults', array(__CLASS__, 'on_comment_text_before') );
		add_filter('comment_form_fields', array(__CLASS__, 'on_comment_form_fields') );
		add_filter('comment_text', array(__CLASS__, 'on_comment_text'), 10, 3 );
		add_filter('get_comment_time', array(__CLASS__, 'on_get_comment_time'), 10, 5 );
		add_filter('comment_reply_link', array(__CLASS__, 'on_comment_reply_link'), 10, 4 );
		// add_filter('comment_reply_link_args', array(__CLASS__, 'on_comment_reply_link_args'), 10, 3 );
		add_filter('get_comment_author_link', array(__CLASS__, 'on_get_comment_author_link'), 10, 3 );
		
		return $import_result;
	}
	
	/**
	 * Appelle la synchronisation IMAP.
	 */
	public static function synchronize($forum, $page = false){
		if( ! $page ){
			if (!($page = self::get_page_of_forum( $forum ))){
				debug_log('synchronize get_page_of_forum === FALSE', $forum);
				return false;
			}
		}
		elseif( is_int( $page ))
			if (!($page = get_post($page)))
				return false;
		
		
		$forum = self::get_forum($forum);
		
		$meta_key = 'imap_sync_time';
		$time = get_post_meta($forum->ID, $meta_key, true);
		if( $time && $time >= strtotime('- 10 second'))
			return true;
		
		try {
			require_once( AGDP_PLUGIN_DIR . "/public/class.agendapartage-forum-imap.php");
			$import_result = AgendaPartage_Forum_IMAP::import_imap_messages($forum, $page);
		}
		catch(Exception $exception){
			return $exception;
		}
		
		update_post_meta($forum->ID, $meta_key, time());
		
		return $import_result;
	}
	
	/**
	 * Retourne l'objet forum.
	 */
	public static function get_forum($forum = false){
		$forum = get_post($forum);
		if(is_a($forum, 'WP_Post')
		&& $forum->post_type == self::post_type)
			return $forum;
		return false;
	}
	
	/**
	 * Retourne le forum du nom donné.
	 */
	public static function get_forum_by_name($forum_name){
		if( is_int($forum_name) )
			$forum = get_post($forum_name);
		else
			$forum = get_page_by_path($forum_name, 'OBJECT', self::post_type);
		if(is_a($forum, 'WP_Post')
		&& $forum->post_type == self::post_type)
			return $forum;
		return false;
	}
	
	/**
	 * Retourne le forum associé à une page.
	 */
	public static function get_forum_of_page($page_id){
		if( is_a($page_id, 'WP_Post') ){
			if($page_id->post_type === AgendaPartage_Newsletter::post_type)
				return self::get_forum_of_newsletter($page_id);
			if($page_id->post_type != 'page')
				return false;
			$page_id = $page_id->ID;
		}
		if($forum_id = get_post_meta( $page_id, AGDP_PAGE_META_FORUM, true))
			return self::get_forum($forum_id);
		return false;
	}
	
	/**
	 * Retourne la page associée à un forum.
	 */
	public static function get_page_of_forum($forum_id){
		if( is_a($forum_id, 'WP_Post') ){
			if($forum_id->post_type != self::post_type)
				return false;
			$forum_id = $forum_id->ID;
		}
		if($page_id = get_post_meta( $forum_id, AGDP_FORUM_META_PAGE, true))
			return get_post($page_id);
		return false;
	}
	
	/**
	 * Retourne le forum associé à une newsletter.
	 */
	public static function get_forum_of_newsletter($newsletter_id){
		if( is_a($newsletter_id, 'WP_Post') ){
			if($newsletter_id->post_type != AgendaPartage_Newsletter::post_type)
				return false;
			$newsletter_id = $newsletter_id->ID;
		}
		
		if( $source = AgendaPartage_Newsletter::get_content_source($newsletter_id, true)){
			if( $source[0] === self::post_type ){
				if( $forum = get_post( $source[1] )){
					return $forum;
				}
			}
		}
		return false;
	}
	
	/**
	 * Returns posts where post_status == 'publish'
	 */
	 public static function get_forums(){
		$posts = [];
		foreach( get_posts([
			'post_type' => self::post_type
			, 'post_status' => 'publish'
			]) as $post)
			$posts[$post->ID . ''] = $post;
		return $posts;
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
			if(	$current_user->has_cap( 'edit_posts' ) ){
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
			
		// debug_log( $current_user->has_cap( 'edit_posts' ), $user_email, $comment->comment_author, $comment->comment_author_email );
		}
		
		return false;
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
		
		
		/* Dans le paramétrage de WP, Réglages / Commentaires :
			Diviser les commentaires en pages, avec N commentaires de premier niveau par page et la PREMIERE page affichée par défaut
			Les commentaires doivent être affichés avec le plus ANCIEN en premier
		*/
		$wp_query->query_vars['orderby'] = 'comment_date_gmt';
		$wp_query->query_vars['order'] = 'DESC';
		
		
	}
	
	public static function on_comments_pre_query($comment_data, $wp_query){
		if( ! empty($wp_query->query_vars['parent__in'] ) ){
			// debug_log('on_comments_pre_query parent__in', $comment_data, $wp_query);
			return;
		}
	}
	
	public static function on_sub_comments_clauses($clauses, $wp_query){
		debug_log('on_sub_comments_clauses parent__in', $clauses, $wp_query);
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
		$title_field = '<p class="comment-form-title"><label for="title">Titre <span class="required">*</span></label> <input id="title" name="title" type="text" maxlength="255" required></p>';
		$send_email_field = '<div class="comment-form-send-email if-respond"><label for="send-email">'
			. '<input id="send-email" name="send-email" type="checkbox">'
			. ' Envoyez votre réponse par e-mail à l\'auteur du message</label></div>';
		$is_private_field = '<p class="comment-form-is-private if-respond"><label for="is-private">'
			. '<input id="is-private" name="is-private" type="checkbox">'
			. ' Ce message est privé, entre vous et l\'auteur du message. Sinon, il est visible par tous sur ce site.</label></p>';
		$fields['comment'] = $title_field
							. $fields['comment']
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
		
		if( ! ($forum = self::get_forum_of_page($commentdata['comment_post_ID']) ))
			return $approved;
		
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
		
		if( ! ($forum = self::get_forum_of_page($commentdata['comment_post_ID']) ))
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
		
		if( ! ($forum = self::get_forum_of_page($comment->comment_post_ID) ))
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
			echo sprintf('<p>%s<code>Un e-mail a été envoyé : %s</code></p>'
				, '<span class="dashicons dashicons-email-alt"></span>'
				, $send_email);
		}
		
		$title = get_comment_meta($comment->comment_ID, 'title', true);	
		
		echo sprintf('<h3>%s</h3>', $title);
		
		return $comment_text;
	}
	/**
	 * Affichage de l'heure du commentaire
	 */
	public static function on_get_comment_time( $comment_time, $format, $gmt, $translate, $comment ){
		$comment_date = $gmt ? $comment->comment_date_gmt : $comment->comment_date;
		return $comment_time . ' (' . date_diff_text($comment_date) . ')';
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
		$attachments_links = self::get_attachments_links($comment);
		echo $attachments_links;
		
		$user_can_change_comment = self::user_can_change_comment($comment);
		 
		//Statut du message (mark_as_ended). 
		$comment_actions = self::get_comment_mark_as_ended_link($comment->comment_ID);
		
		if( $user_can_change_comment ){
			$comment_actions .= self::get_comment_delete_link($comment->comment_ID);
		}
		
		$comment_actions = sprintf('<span class="comment-agdp-actions">%s</span>', $comment_actions);
		$comment_reply_link = preg_replace('/(\<\/div>)$/', $comment_actions . '$1', $comment_reply_link);
		// debug_log('on_comment_reply_link', $comment_reply_link, $args, $comment);
		
		return $comment_reply_link;
	}
	/**
	 * Retourne le html d'action pour marqué un message comme étant terminé
	 */
	private static function get_comment_mark_as_ended_link($comment_id){
		
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
		
		// debug_log('on_get_comment_author_link', $comment_author_link, $comment_author);
		if( strpos( $comment_author_link, ' href=' ) 
		 || strpos( $comment_author_link, '@' ) )
			return $comment_author_link;
			
		$comment = get_comment($comment_id);
		if( $comment->comment_author_email )
			return sprintf('<a href="mailto:%s">%s</a>', $comment->comment_author_email, $comment_author);
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
	 * Retourne le répertoire de stockage des fichiers attachés aux messages
	 */
	public static function get_attachments_path($forum_id){
		$upload_dir = wp_upload_dir();
		
		$forum_dirname = str_replace('\\', '/', $upload_dir['basedir']);
		// if( is_multisite())
			// $forum_dirname .= '/sites/' . get_current_blog_id();
		
		$forum_dirname .= sprintf('/%s/%d/%d/%d/', self::post_type, $forum_id, date('Y'), date('m'));
		
		if ( ! file_exists( $forum_dirname ) ) {
			wp_mkdir_p( $forum_dirname );
		}

		return $forum_dirname;
	}
}
?>