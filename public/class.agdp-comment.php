<?php

/**
 * AgendaPartage -> Forum -> Message
 * Edition de commentaire dans un forum.
 *
 * Voir aussi Agdp_Forum
 *
 */
class Agdp_Comment {

	private static $initiated = false;
	
	const field_prefix = 'msg';
	const secretcode_argument = AGDP_COMMENT_SECRETCODE;
		
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
		global $pagenow;
		add_action( 'wp_ajax_'.AGDP_TAG.'_comment_action', array(__CLASS__, 'on_ajax_action') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_comment_action', array(__CLASS__, 'on_ajax_action') );
		add_filter('pre_comment_approved', array(__CLASS__, 'on_pre_comment_approved'), 10, 2 );
		add_filter('delete_comment', array(__CLASS__, 'on_delete_comment'), 10, 2 );
		if ( $pagenow === 'wp-comments-post.php' ) {
			add_filter('preprocess_comment', array(__CLASS__, 'on_preprocess_comment') );
			add_filter('comment_post', array(__CLASS__, 'on_comment_post'), 10, 3 );
			add_filter('comment_post_redirect', array(__CLASS__, 'on_comment_post_redirect'), 10, 2 );
		}
	}
	/*
	 **/
	
	/***************************/
	
	/**
	 * Associe le forum et les commentaires de la page.
	 * Fonction appelée par le shortcode [forum "nom du forum"]
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
		
		add_filter('comment_form_defaults', array(__CLASS__, 'on_comment_form_defaults') );
		add_filter('comment_form_fields', array(__CLASS__, 'on_comment_form_fields') );
		add_filter('comment_text', array(__CLASS__, 'on_comment_text'), 10, 3 );
		add_filter('get_comment_time', array(__CLASS__, 'on_get_comment_time'), 10, 5 );
		add_filter('comment_reply_link', array(__CLASS__, 'on_comment_reply_link'), 10, 4 );
		add_filter('get_comment_author_link', array(__CLASS__, 'on_get_comment_author_link'), 10, 3 );
		
		// if( self::get_current_forum_rights( $page ) != 'P' )
			Agdp_Forum::get_current_forum_rights( $page );//set current_forum $page
			if( Agdp_Forum::user_can_see_forum_details( $page ) )
				add_filter('get_comment_author_link', array(__CLASS__, 'on_get_comment_author_status'), 11, 3 );
		
		return true;
	}
	
	
	/********************************************/
	/**
	 * Retourne un nonce pour un commentaire 
	 */
	public static function get_nonce_name($comment_id){
		return sprintf('%s|%s|%s', AGDP_TAG, 'comment', $comment_id);
	}
	/**
	 * Retourne un nonce pour un commentaire 
	 */
	public static function get_nonce($comment_id){
		$nonce_name = self::get_nonce_name( $comment_id );
		return wp_create_nonce($nonce_name);
	}
	/**
	 * Vérifie un nonce pour un commentaire 
	 */
	public static function verify_nonce($nonce, $comment_id){
		$nonce_name = self::get_nonce_name( $comment_id );
		return wp_verify_nonce($nonce, $nonce_name);
	}
	
	/********************************************/
	/**
	 * Retourne le code secret du commentaire 
	 */
	public static function get_comment_codesecret($comment_id){
		if(!$comment_id)
			return false;
		if(is_a($comment_id, 'WP_Comment'))
			$comment_id = $comment_id->comment_ID;
		
		$meta_name = self::field_prefix . self::secretcode_argument;
		$codesecret = get_comment_meta($comment_id, $meta_name, true);
		// debug_log( __FUNCTION__, $meta_name, $comment_id, $codesecret);
		if( ! $codesecret ){
			$codesecret = Agdp::get_secret_code(6,'num');
			update_comment_meta($comment_id, $meta_name, $codesecret);
		}
		
		return $codesecret;
	}
	
	/********************************************/
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
		
		$codesecret = self::get_comment_codesecret($comment);
		$argument = self::secretcode_argument;
		if( isset($_REQUEST[ $argument ]) ){
			if( $codesecret === trim($_REQUEST[$argument]) )
				return true;
		}
		if( isset($_REQUEST['data']) && isset($_REQUEST['data'][ $argument ]) ){
			if( $codesecret === trim($_REQUEST['data'][$argument]) )
				return true;
		}
		
		return false;
	}
		 
	/**
	 * Adaptation du formulaire de commentaire
	 */
	public static function on_comment_form_defaults($defaults){
		foreach($defaults as $key=>$value)
			$defaults[$key] = str_replace('Commentaire', 'Message', 
							 str_replace('commentaire', 'message', $value));
		$defaults['class_form'] .= ' agdp-forum';
		$defaults['label_submit'] = 'Envoyer';
		return $defaults;
	}
	
	/**
	 * Ajout du champ Titre au formulaire de commentaire
	 */
	public static function on_comment_form_fields($fields){
		$comment_form = Agdp_Forum::get_property('comment_form');
		$reply_link = Agdp_Forum::get_property('reply_link');
		
		if( ! Agdp_Forum::user_can_post_comment()
		 || ( ! $comment_form && ! $reply_link ) ){
			$fields['comment'] = '<script>jQuery("#respond.comment-respond").remove();</script>';
			return $fields;
		}
		if( ! isset($_REQUEST['replytocom'])
		 && ! $comment_form ){
			//TODO remplacer remove() par hide(). Dans .js : .show() ou gestion / css + secure via nonce
			$fields['comment'] = ( empty($fields['comment']) ? '' : $fields['comment'] )
				. '<script>jQuery("#respond.comment-respond").hide();</script>';
			// return $fields;
		}
		
		if( Agdp_Forum::get_property_equals('comment_title', true) ){
			$title_field = '<p class="comment-form-title"><label for="title">Titre <span class="required">*</span></label>'
				. '<input id="title" name="title" type="text" maxlength="255" required></p>';
		}
		else
			$title_field = '';
		
		$visible = Agdp_Forum::get_property_equals('reply_email', true);
		$checked = Agdp_Forum::get_property_equals('reply_email_default', true);
		$send_email_field 
			= sprintf('<div class="comment-form-send-email if-respond %s"><label for="send-email">'
				, $visible ? '' : 'hidden cache')
			. sprintf('<input id="send-email" name="send-email" type="checkbox" %s>'
				, $checked ? ' checked="checked"' : '')
			. ' Envoyez votre réponse par e-mail à l\'auteur du message</label></div>';
		
		$visible = Agdp_Forum::get_property_equals('reply_is_private', true);
		$checked = Agdp_Forum::get_property_equals('reply_is_private_default', true);
		$is_private_field 
			= sprintf('<p class="comment-form-is-private if-respond %s"><label for="is-private">'
				, $visible ? '' : 'hidden')
			. sprintf('<input id="is-private" name="is-private" type="checkbox" %s>'
				, $checked ? ' checked="checked"' : '')
			. ' Ce message est privé, entre vous et l\'auteur du message. Sinon, il est visible par tous les membres sur ce site.</label></p>';
		
		$fields['comment'] = $title_field
							. (isset($fields['comment']) ? $fields['comment'] : '')
							. $send_email_field
							. $is_private_field
							;
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
		
		if( ! ($mailbox = Agdp_Mailbox::get_mailbox_of_page($commentdata['comment_post_ID']) ))
			return $approved;
		if( ! Agdp_Forum::get_forum_comment_approved($commentdata['comment_post_ID']) )
			return 0;
		
		if( empty( $_POST['title'] )
		 && empty($commentdata['comment_meta'])
		 && empty($commentdata['comment_meta']['title'])
		 && Agdp_Forum::get_property_equals( 'comment_title', true, $commentdata['comment_post_ID'])
		)
			return new WP_Error( 'require_valid_comment', __( '<strong>Erreur :</strong> Veuillez indiquer un titre à votre message.' ), 200 );

		return $approved;
	}
	
	/**
	 * Enregistrement du commentaire depuis wp-comments-post.php.
	 * Ajout des metas (title, send-email, is-private) lors de l'enregistrement du commentaire
	 */
	public static function on_preprocess_comment($commentdata ){
		$update_comment_id = isset($_POST['update_comment_id']) ? $_POST['update_comment_id'] : false;
		
		if( ! $update_comment_id
		 && ! Agdp_Mailbox::get_mailbox_of_page($commentdata['comment_post_ID']) )
			return $commentdata;
		
		// update
		if( $update_comment_id ){
			$nonce = isset($_POST['update_comment_nonce']) ? $_POST['update_comment_nonce'] : false;
			if( ! self::verify_nonce($nonce, $update_comment_id) ){
				$commentdata['comment_approved'] = new WP_Error('comment_nonce_error'
						, sprintf('Impossible de valider l\'enregistrement (%s).', $update_comment_id));
				return $commentdata;
			}
				
			$comment = get_comment($update_comment_id);
			if( ! $comment ){
				do_action( 'comment_id_not_found', $update_comment_id );
				$commentdata['comment_approved'] = new WP_Error('comment_id_not_found'
						, sprintf('Impossible de retrouver l\'enregistrement d\'origine (%s).', $update_comment_id));
				return $commentdata;
			}
			$commentdata['comment_ID'] = $update_comment_id;
			$commentdata['_update_comment'] = true;
			$commentdata['comment_parent'] = $comment->comment_parent;
			$commentdata['user_id'] = $comment->user_id;
			$commentdata['comment_author'] = $comment->comment_author;
			$commentdata['comment_author_email'] = $comment->comment_author_email;
			$commentdata['comment_author_url'] = $comment->comment_author_url;
			$commentdata['comment_author_IP'] = $comment->comment_author_IP;
		}
		
		if( empty($commentdata['comment_meta']) )
			$commentdata['comment_meta'] = [];
		
		/* var_dump($_POST); 
		var_dump($commentdata); 
		var_dump( ! Agdp_Forum::get_property_equals( 'comment_title', true, $commentdata['comment_post_ID'] )); 
		die(); */
		
		if( empty( $_POST['title'] )){
			if($commentdata['comment_parent']){
				$parent_subject = get_comment_meta( $commentdata['comment_parent'], 'title', true);
				$_POST['title'] = sprintf('Re: %s', $parent_subject);
			}
			elseif( Agdp_Forum::get_property_equals( 'comment_title', true, $commentdata['comment_post_ID'] ) ) {
				//on_pre_comment_approved se charge de l'erreur
				return $commentdata;
			}
		}
		if( ! empty( $_POST['title'] )){
			$commentdata['comment_meta'] = array_merge([ 'title' => $_POST['title'] ], $commentdata['comment_meta']);
		}
		
		if( $commentdata['comment_parent'] ){
			if( ! empty( $_POST['send-email'] )){
				$commentdata['comment_meta'] = array_merge([ 'send-email' => $_POST['send-email'] ], $commentdata['comment_meta']);
			}
			if( ! empty( $_POST['is-private'] )){
				$parent_comment = get_comment( $commentdata['comment_parent']);
				//Mémorise l'email de l'auteur du commentaire parent pour le filtrage
				$commentdata['comment_meta']['is-private'] = $parent_comment->comment_author_email;
			}
		}
		
		if( $update_comment_id ){
			$update = wp_update_comment($commentdata, true);
			if( is_wp_error($update) )				
				$commentdata['comment_approved'] = $update;
			else {
				//Ce qui suit est la copie de wp-comments-post.php
				$user            = wp_get_current_user();
				$cookies_consent = ( isset( $_POST['wp-comment-cookies-consent'] ) );
				do_action( 'set_comment_cookies', $comment, $user, $cookies_consent );
				$location = empty( $_POST['redirect_to'] ) ? get_comment_link( $comment ) : $_POST['redirect_to'] . '#comment-' . $comment->comment_ID;
				if ( ! $cookies_consent && 'unapproved' === wp_get_comment_status( $comment ) && ! empty( $comment->comment_author_email ) ) {
					$location = add_query_arg(
						array(
							'unapproved'      => $comment->comment_ID,
							'moderation-hash' => wp_hash( $comment->comment_date_gmt ),
						),
						$location
					);
				}
				$location = apply_filters( 'comment_post_redirect', $location, $comment );
				wp_safe_redirect( $location );
				exit;
			}
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
	 * Après l'enregistrement du commentaire, lien de redirection.
	 * WP::get_page_of_comment() est buggué si 'oldest' === get_option( 'default_comments_page' )
	 */
	public static function on_comment_post_redirect($location, $comment ){
		// debug_log('on_comment_post_redirect', $location, $comment->comment_parent, get_option( 'default_comments_page' ), preg_replace('/\/comment-page-[0-9]+/', '', $location));
		if('oldest' === get_option( 'default_comments_page' )){
			if( $comment->comment_parent == 0) //not integer
				return preg_replace('/\/comment-page-[0-9]+/', '', $location);
			//TODO en cas de réponse
			return preg_replace('/\/comment-page-[0-9]+/', '', $location);
		}
		// debug_log('on_comment_post_redirect DEFAULT' );
		return $location;
	}
	/**
	 * Envoie l'email de réponse
	 */
	public static function send_response_email($comment_id ){
		$comment = get_comment($comment_id);
		
		if( ! ($mailbox = Agdp_Mailbox::get_mailbox_of_page($comment->comment_post_ID) ))
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
		if( ! Agdp_Forum::get_property_equals('comment_title', false) ){
			$title = get_comment_meta($comment->comment_ID, 'title', true);	
			echo sprintf('<h3 class="comment-title">%s</h3>', $title);
		}
		if( ! Agdp_Forum::user_can_see_forum_details() )
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
			foreach($attachments as $attachment){
				if( ! file_exists($attachment) )
					continue;
				
				$extension = strtolower(pathinfo($attachment, PATHINFO_EXTENSION));
				$url = upload_file_url( $attachment );
				switch($extension){
					case 'png':
					case 'jpg':
					case 'jpeg':
					case 'bmp':
					case 'tiff':
						//Vérifie que l'image n'est pas déjà intégré dans le message
						$pattern = sprintf('/\<img\s[^>]*src="%s"/', preg_quote($url, '/'));
						// debug_log( __FUNCTION__, $url, $pattern, $comment->comment_content);
						$matches = [];
						if( ! preg_match( $pattern, $comment->comment_content, $matches ) )
							$html .= sprintf('<li><a href="%s"><img src="%s"/></a></li>', $url, $url);
						break;
					default:
						$html .= sprintf('<li><a href="%s">%sTélécharger %s</a></li>'
							, $url
							, '<span class="dashicons-before dashicons-download"></span>'
							, pathinfo($attachment, PATHINFO_BASENAME));
						break;
				}
			}
			if( $html )
				$html = '<ul class="attachments">' . $html . '</ul>';
		}
		return $html;
	}
	
	/**
	 * Affichage du commentaire, lien "Répondre"
	 */
	public static function on_comment_reply_link($comment_reply_link, $args, $comment, $post ){
		if( ! Agdp_Forum::user_can_see_forum_details() ){
			return '';
		}
		
		$attachments_links = self::get_attachments_links($comment);
		echo $attachments_links;
		 
		//Statut du message (mark_as_ended). 
		$comment_actions = self::get_comment_mark_as_ended_link($comment->comment_ID);
		
		$comment_actions .= self::get_comment_delete_link($comment->comment_ID);
		$comment_actions .= self::get_comment_edit_link($comment->comment_ID);
		
		$comment_actions = sprintf('<span class="comment-agdp-actions">%s</span>', $comment_actions);
		
		if( Agdp_Forum::get_property_equals('reply_link', false) )
			$comment_reply_link = $comment_actions;
		else
			$comment_reply_link = preg_replace('/(\<\/div>)$/', $comment_actions . '$1', $comment_reply_link);
		
		return $comment_reply_link;
	}
	
	/**
	 * Retourne le html d'action pour marqué un message comme étant terminé
	 */
	private static function get_comment_mark_as_ended_link($comment_id){
		if( Agdp_Forum::get_property_equals('mark_as_ended', false) )
			return '';
			
		$data = [
			'comment_id' => $comment_id
		];
		
		$status = get_comment_meta($comment_id, 'status', true);
		// $status_class = $status == 'ended' ? 'comment-mark_as_ended' : 'comment-not-mark_as_ended';
		// $comment_actions = sprintf('<a href="#mark_as_ended" class="comment-agdp-action comment-agdp-action-mark_as_ended %s comment-reply-link">%s</a>'
			// , $status_class
			// , "Toujours d'actualité ?");
			
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
		return Agdp::get_ajax_action_link(false, ['comment','mark_as_ended'], $icon, $caption, $title, false, $data);
	}
	/**
	 * Retourne le html d'action pour éditer un message 
	 */
	private static function get_comment_edit_link($comment_id){
		
		//TODO Modifier sans formulaire : formulaire de base des commentaires
		
		$edit_message = Agdp_Forum::get_property('edit_message');
		if( $edit_message )
			$edit_message = Agdp_Forum::get_property('edit_message_form');
		if( ! $edit_message ){
			$reply_link = Agdp_Forum::get_property('reply_link');
			if( ! $reply_link )
				return '';
		}
		
		$data = [
			'comment_id' => $comment_id,
		];
		if( isset( $_REQUEST[self::secretcode_argument] ) )
			$data[self::secretcode_argument] = $_REQUEST[self::secretcode_argument];
			
		$caption = "Modifier";
		$title = "Modifier ce message";
		$icon = 'edit';

		$confirm = false;
		return Agdp::get_ajax_action_link(false, ['comment','edit'], $icon, $caption, $title, $confirm, $data);
	}
	/**
	 * Retourne le html d'action pour supprimer un message 
	 */
	private static function get_comment_delete_link($comment_id){
		
		$data = [
			'comment_id' => $comment_id
		];
		if( isset( $_REQUEST[self::secretcode_argument] ) )
			$data[self::secretcode_argument] = $_REQUEST[self::secretcode_argument];
			
		$caption = "Supprimer";
		$title = "Vous pouvez supprimer définitivement ce message";
		$icon = 'trash';

		//La confirmation est gérée dans public/js/agendapartage.js Voir plus bas : on_ajax_action_delete
		if( self::user_can_change_comment($comment_id) )
			$confirm = 'Etes-vous sûr de vouloir supprimer ce message ?';
		else
			$confirm = false;
		return Agdp::get_ajax_action_link(false, ['comment','delete'], $icon, $caption, $title, $confirm, $data);
	}
	/**
	 * Retourne un lien html pour l'envoi d'un mail à l'auteur
	 */
	public static function get_message_validation_email_link($comment){
		$html = '';
		$email = $comment->comment_author_email;
		if(!$email){
			$html .= '<p class="alerte">Ce message n\'a pas d\'adresse e-mail associée.</p>';
		}
		else {
			if(current_user_can('manage_options'))
				$data = [ 'force-new-activation' => true ];
			else
				$data = [];
			$data['comment_id'] = $comment->comment_ID;
			
			$need_can_user_change = false;
			
			$email_parts = explode('@', $email);
			$email_trunc = substr($email, 0, min(strlen($email_parts[0]), 3)) . str_repeat('*', max(2, strlen($email_parts[0])-3));
			$caption = 'E-mail de modification';
			$title = sprintf('Cliquez ici pour envoyer un e-mail de modification du message à l\'adresse %s@%s', $email_trunc, $email_parts[1]);
			$icon = 'email-alt';
			$confirm = sprintf('Confirmez-vous l\'envoi d\'un e-mail à l\'adresse %s@%s', $email_trunc, $email_parts[1]);
			
			$html = Agdp::get_ajax_action_link(false, ['comment','send_validation_email'], $icon, $caption, $title, $confirm, $data);
		}
		return $html;
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
		$confirm = 'Approuver ce message ?';
		$links[] = Agdp::get_ajax_action_link(false, ['comment','approve'], $icon, $caption, $title, $confirm, $data);

		$caption = "Supprimer";
		$title = "Mettre ce message à la corbeille";
		$icon = 'trash';
		//La confirmation est gérée dans public/js/agendapartage.js Voir plus bas : on_ajax_action_delete
		$confirm = 'Etes-vous sûr de vouloir supprimer ce message ?';
		$links[] = Agdp::get_ajax_action_link(false, ['comment','delete'], $icon, $caption, $title, $confirm, $data);

		$caption = "Envoyer un message";
		$title = "Envoyer un message à l'auteur";
		$icon = 'email-alt';
		//La confirmation est gérée dans public/js/agendapartage.js Voir plus bas : on_ajax_action_mailto_author
		$links[] = Agdp::get_ajax_action_link(false, ['comment','mailto_author'], $icon, $caption, $title, false, $data);

		return $links;
	}
	
	/**
	 * Affichage de l'auteur du commentaire
	 */
	public static function on_get_comment_author_link( $comment_author_link, $comment_author, $comment_id ){
		if( ! Agdp_Forum::user_can_see_forum_details() )
			return '';
		
		$comment = get_comment($comment_id);
		
		if( current_user_can('moderate_comments') )
			$comment_author_email = true;
		else {	
			if( ($current_user = wp_get_current_user())
			 && $current_user->user_email === $comment->comment_author_email )
				$comment_author_email = true;
			else
				switch( Agdp_Forum::get_property('comment_author_email') ){
					case '0':
						$comment_author_email = false;
						break;
					case 'M':
						switch( Agdp_Forum::get_user_subscription( $comment->comment_post_ID ) ){
							case 'subscriber':
							case 'moderator':
							case 'administrator':
								$comment_author_email = true;
								break;
							default:
								$comment_author_email = false;
						}
						break;
					
					case '1':
					default:
						$comment_author_email = true;
				}
		}
		if( ! $comment_author_email ){
			$comment_author_link = sprintf('<a href="#">%s</a>', $comment_author);
		}
		elseif( ! strpos( $comment_author_link, ' href=' ) 
		 && ! strpos( $comment_author_link, '@' ) ){
			
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
				if( Agdp_Forum::get_forum_right_need_subscription() ){
					switch($subscription = Agdp_Forum::get_subscription( $comment->comment_author_email, Agdp_Forum::$current_forum )){
						case false:
						case '':
							$status = 'non-membre';
							break;
						default:
							$subscription_roles = Agdp_Forum::subscription_roles;
							$status = isset($subscription_roles[$subscription]) ? strtolower($subscription_roles[$subscription]) : '?'.$subscription;
							break;
					}
				}
			}
		}
		else
			$status = 'inconnu-e';
		if( $status ){
			if( ! empty($user_id) )
				$status = sprintf('<a href="/wp-admin/user-edit.php?user_id=%d#forums" class="comment-user-status">(%s)</a>'
					, $user_id, $status);
			else
				$status = sprintf('<span class="comment-user-status">(%s)</span>', $status);
			if ( '0' == $comment->comment_approved )
				echo $status . ' ';
			else
				$comment_author_link .= $status;
		}
		return $comment_author_link;
	}
	
	/**
	 * Action lors de la suppression d'un commentaire
	 * Supprime les fichiers attachés
	 */
	public static function on_delete_comment($comment_id, $comment) {

		$attachments = get_comment_meta($comment_id, 'attachments', true);
		if($attachments){
			foreach($attachments as $attachment){
				if( ! file_exists($attachment) )
					continue;
				unlink($attachment);
			}
		}
	}
	
	/**
	 * Requête Ajax sur les commentaires
	 */
	public static function on_ajax_action() {
		if( ! Agdp::check_nonce() )
			wp_die('nonce error');
		if( empty($_POST['method']))
			wp_die('method missing');
		
		$ajax_response = '';
		
		$method = $_POST['method'];
		$data = isset($_POST['data']) ? $_POST['data'] : [];
		
		if( $data && is_string($data) && ! empty($_POST['contentType']) && strpos( $_POST['contentType'], 'json' ) )
			$data = json_decode(stripslashes( $data), true);
		
		try{
			//cherche une fonction du nom "on_ajax_action_{method}"
			$function = array(get_called_class(), sprintf('on_ajax_action_%s', $method));
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
	 * Requête Ajax d'édition du commentaire
	 * La page du forum doit être paramétré avec un forumlaire de modification
	 */
	public static function on_ajax_action_edit($data, $user_can_change_comment = null) {
		$comment_id = $data['comment_id'];
		$comment = get_comment($comment_id);
		if( ! $comment )
			return '?';
		
		if( $user_can_change_comment === null ){
			$user_can_change_comment = self::user_can_change_comment($comment);
			if( ! $user_can_change_comment ){
				//get codesecret
				return self::get_message_edit_content_forbidden( $comment, 'edit' );
			}
		}

		Agdp_Forum::init_page(false, $comment->comment_post_ID);
		
		if( $comment->comment_parent ){
			$edit_message = false;
		}
		else {
			$edit_message = Agdp_Forum::get_property('edit_message');
			if( $edit_message )
				$edit_message = Agdp_Forum::get_property('edit_message_form');
		}
		if( ! $edit_message ){
			//Edition via le formulaire de base des commentaires
			$nonce_value = self::get_nonce( $comment_id );
			
			return 'js:'
				. 'jQuery("#div-comment-'.$comment_id.' a.comment-reply-link")'
					. '.trigger("click")'
					. sprintf('.trigger("forum_comment_edit", {"nonce":"%s", "user_name":"%s", "user_email":"%s"})', $nonce_value, $comment->comment_author, $comment->comment_author_email )
				. ';'
				. '$actionElnt.parents(".ajax_action-response:first").remove();'
			;
		}
		
		$edit_message = sprintf('contact-form-7 id=%d', $edit_message);
		
		if( ! $edit_message ){
			return 'Désolé, cette page n\'est pas configurée pour modifier les messages.';
		}
		
		//Nécessaire pour WPCF7 pour affecter une valeur à _wpcf7_container_post
		global $wp_query, $post;
		$post = $comment->comment_post_ID;
		$wp_query->in_the_loop = true;
		
		//Génération du formulaire
		$html = do_shortcode( '[' . $edit_message . ']');
		
		if( ! $html
		 || ( substr($html, 0, 1) === '[' ) ){
			return 'Désolé, cette page n\'est pas configurée pour modifier les messages ([forum-prop edit-message="contact-form-7 id=3dc0507"]).';
		}
		
		$attrs = [];
		foreach( get_comment_meta( $comment_id ) as $meta_key => $meta_value){
			if( str_starts_with( $meta_key, 'posted_data_' ) ){
				$attrs[ substr($meta_key, strlen('posted_data_')) ] = maybe_unserialize($meta_value[0]);
				//TODO count( $meta_value ) > 1
			}
		}
		
		//Dans le cas d'un message provenant d'un email, le contenu du mail est affecté à un champ "message"
		if( count($attrs) === 0 ){
			if( $value = html_to_plain_text( $comment->comment_content ) )
				$attrs[ 'message' ] = $value;
			if( $value = get_comment_meta( $comment->comment_ID, 'title', true ))
				$attrs[ 'title' ] = $value;
			$attrs[ 'user-name' ] = $comment->comment_author;
			$attrs[ 'user-email' ] = $comment->comment_author_email;
			if( $value = Agdp_User::get_user_meta($comment->comment_author_email, 'city') )
				$attrs[ 'user-city' ] = $value;
		}
		
		// debug_log(__FUNCTION__, $comment_id, $attrs);
		$attrs = str_replace('"', "&quot;", htmlentities( json_encode($attrs) ));
		$input = sprintf('<input type="hidden" class="agdpmessage_edit_form_data" data="%s"/>', $attrs);
		
		foreach( [
			'_update_comment_id' => $comment_id,
		] as $argument => $value){
			$input .= sprintf('<input type="hidden" name="%s" value="%s"/>', $argument, esc_attr( $value ));
		}
		
		$html = str_ireplace('</form>', $input.'</form>', $html);
		
		$html_id = uniqid();
		
		$script = 'jQuery("body").trigger("wpcf7_form_fields-init");';
		$script .= sprintf('wpcf7.init(jQuery("#%s form").get(0));', $html_id);
		
		$html = sprintf('<div id="%s">%s</div><script>%s</script>'
			, $html_id
			, $html
			, $script);
		
		return 'replace_previous_response:' . $html;
	}
	
	/**
	 * Requête Ajax de suppression du commentaire
	 */
	public static function on_ajax_action_delete($data, $user_can_change_comment = null) {
		$comment = get_comment($data['comment_id']);
		if( ! $comment )
			return '';
		if( $user_can_change_comment === null ){
			$user_can_change_comment = self::user_can_change_comment($comment);
			if( ! $user_can_change_comment ){
				//get codesecret
				return self::get_message_edit_content_forbidden( $comment, 'delete' );
			}
		}
		update_comment_meta($data['comment_id'], 'deleted', wp_date(DATE_ATOM));
		
		$args = ['comment_ID' => $data['comment_id'], 'comment_approved' => 'trash'];
		$comment = wp_update_comment($args, true);
		if ( ! is_a($comment, 'WP_Error') ){
			//La suppression des fichiers attachés se passe dans on_delete_comment
			return 'js:$actionElnt.parents(\'.comment:first\').remove();';
		}
		
		return $comment->get_error_message();
	}
	
	/**
	 * Requête Ajax de saisie du code secret du commentaire
	 * Redirige vers le callback
	 */
	public static function on_ajax_action_codesecret($data) {
		$comment = get_comment($data['comment_id']);
		if( ! $comment
		|| ! isset( $_POST[self::secretcode_argument] ))
			return ;
		
		$codesecret = self::get_comment_codesecret($comment);
		if( $codesecret !== trim($_POST[self::secretcode_argument]) ){
			return 'Code secret incorrect '/* .$codesecret.' !== '.$_POST[self::secretcode_argument] */;
		}
		
		if( isset( $data['callback'] ) ){
			$callback = $data['callback'];
			return self::$callback( $data, true );
		}
		return '';
	}
	
	/**
 	 * Contenu de la page d'édition en cas d'interdiction de modification d'un message
 	 */
	private static function get_message_edit_content_forbidden( $comment, $action ) {
		$comment_id = $comment->comment_ID;
		
		switch($action){
			case 'delete' :
				$callback = 'on_ajax_action_delete';
				$title = 'Vous n\'êtes pas autorisé à supprimer ce message.';
				break;
			default :
				$callback = 'on_ajax_action_edit';
				$title = 'Vous n\'êtes pas autorisé à modifier ce message.';
		}
		
		$html = '<div class="agdp-edit-forbidden">';
		$html .= '<div>' . Agdp::icon('lock', $title, '', 'h4');
		
		if($comment->comment_approved == 'trash'){
			$html .= 'Le message a été supprimé.';
		}
		else {
			$html .= '<ul>Pour pouvoir modifier un message vous devez remplir l\'une de ces conditions :';
			
			$html .= '<li>disposer d\'un code secret reçu par e-mail selon l\'adresse associée au message.';
			$html .= '<br>' . self::get_message_validation_email_link($comment, true);
			
			//Formulaire de saisie du code secret
			$query = [
				'action' => AGDP_TAG . '_comment_action',
				'method' => AGDP_COMMENT_SECRETCODE,
				'data' => [
					'comment_id' => $comment_id,
					'callback' => $callback,
				],
			];
			$html .= sprintf('<br>Vous connaissez le code secret de ce message :&nbsp;'
				. '<form class="agdp-ajax-action" data="%s">'
				. wp_nonce_field(AGDP_TAG . '-' . AGDP_COMMENT_SECRETCODE, AGDP_TAG . '-' . AGDP_COMMENT_SECRETCODE, true, false)
				.'<input type="text" placeholder="ici le code" name="'.AGDP_COMMENT_SECRETCODE.'" size="7"/>
				<input type="submit" value="Valider" /></form>'
					, esc_attr(json_encode($query)));
			$html .= '</li>';
			
			$html .= '<li>utiliser la même session internet qu\'à la création du message et, ce, le même jour.';

			$url = get_comment_link( $comment );
			$url = wp_login_url( sanitize_url($url) );
			$html .= sprintf('<li>avoir un compte utilisateur sur le site, être <a href="%s">%sconnecté(e)</a> et avoir des droits suffisants.'
				, $url
				, Agdp::icon('unlock')
			);
			if(is_user_logged_in()){
				global $current_user;
				//Rôle autorisé
				if(	! $current_user->has_cap( 'edit_posts' ) )
					$html .= '<br><i>De fait, vous êtes connecté(e) mais vous n\'avez pas les droits et le mail associé au message n\'est pas le vôtre.</i>';
			}
			$html .= '</li>';
			
			$html .= '<li>avoir un compte sur le site et être l\'initiateur du message.</li>';
			
			$html .= '<li>vous pouvez nous écrire pour signaler un problème ou demander une modification.';
			$url = get_page_link( Agdp::get_option('contact_page_id'));
			$url = add_query_arg(AGDP_ARG_COMMENTID, $comment_id, $url );
			$html .= sprintf('<br><a href="%s">%s cliquez ici pour nous écrire à propos de ce message.</a>'
					, esc_url($url)
					, Agdp::icon('email-alt'));
			
			$html .= '</ul>';
		}
		$html .= '</div>';
		$html .= '</div>';
		return $html;
	}
	
	/**
	 * Send validation email
	 */
	public static function on_ajax_action_send_validation_email( $data ) {
		if( ! is_array($data)
		|| ! isset($data['comment_id']) )
			return '?';
		$comment_id = $data['comment_id'];
		if(isset($_POST['data']) && is_array($_POST['data'])
		&& isset($_POST['data']['force-new-activation']) && $_POST['data']['force-new-activation']){
			self::get_activation_key($comment_id, true); //reset
		}
		return self::send_validation_email($comment_id);
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
			Agdp_Forum::init_page(false, $comment->comment_post_ID);
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
		// debug_log(__FUNCTION__, $data, wp_list_comments(['echo'=>false], [ get_comment($data['comment_id']) ]));
		$comment = get_comment($data['comment_id']);
		if( ! $comment )
			return '';
		if( ! empty($data['nonce'])
		 && self::verify_nonce( $data['nonce'], $comment->comment_ID ) ){
			$_REQUEST[self::secretcode_argument] = self::get_comment_codesecret($comment->comment_ID);
		}
			
		$page = $comment->comment_post_ID;
		self::init_page( false, $page );
		return wp_list_comments(['echo'=>false], [ $comment ]);
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
			$url = get_permalink($comment->comment_post_ID);
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
			$url = sprintf('<a href="%s#comment-%d">%s</a>', get_permalink($page), $comment->comment_ID, $page->post_title);
			
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
				return 'Le message a été envoyé.' . Agdp::icon('success');
			}
			else{
				return "L'e-mail n'a pas pu être envoyé" . Agdp::icon('error');
			}
		}
		return 'Vous n\'êtes pas autorisé à exécuter cette action.';
	}

	/*************************************************/
	
	
	
	/**
	 * Envoye le mail à l'auteur du message
	 */
	public static function send_validation_email($comment, $subject = false, $message = false, $return_html_result = false){
		if(is_numeric($comment)){
			$comment_id = $comment;
			$comment = get_comment($comment_id);
		}
		else
			$comment_id = $comment->comment_ID;
		
		if(!$comment_id)
			return false;
		
		$page = get_post( $comment->comment_post_ID );
		$title = $page->post_title;
		
		$codesecret = self::get_comment_codesecret($comment_id);
			
		$email = $comment->comment_author_email;
		$to = $email;
		
		$site = get_bloginfo( 'name' );
		
		$subject = sprintf('[%s][Validation] %s', $site, $subject ? $subject : $title);
		
		$headers = array();
		$attachments = array();
		
		if( ! $message){
			$message = sprintf('Bonjour,<br>Vous recevez ce message suite la création du message ci-dessous ou à une demande depuis le site et parce que votre e-mail est associé au message.');

		}
		else
			$message .= '<br>'.str_repeat('-', 20);
		
		$url = get_comment_link( $comment );
		
		$status = false;
		switch($comment->comment_approved){
			case '0':
				$status = 'En attente de relecture';
			case 'draft':
				if(!$status) $status = 'Brouillon';
				$message .= sprintf('<br><br>Ce message n\'est <b>pas visible</b> en ligne, il est marqué comme "%s".', $status);
				
				if( self::waiting_for_activation($post) ){
					$activation_url = add_query_arg(self::secretcode_argument, $codesecret, $url);
					$activation_url = add_query_arg('action', 'activation', $activation_url);
					$activation_url = add_query_arg('ak', self::get_activation_key($post), $activation_url);
					$activation_url = add_query_arg('etat', 'en-attente', $activation_url);
				}
				
				$message .= sprintf('<br><br><a href="%s"><b>Cliquez ici pour rendre ce message public dans la page</b></a>.<br>', $activation_url);
				break;
			case 'trash':
				$message .= sprintf('<br><br>Ce message a été SUPPRIMÉ.');
				break;
		}
		
		$message .= sprintf('<br><br>Le code secret de ce message est : %s', $codesecret);
		// $args = self::secretcode_argument .'='. $codesecret;
		// $codesecret_url = $url . (strpos($url,'?')>0 || strpos($args,'?') ? '&' : '?') . $args;			
		$codesecret_url = add_query_arg(self::secretcode_argument, $codesecret, $url);
		$message .= sprintf('<br><br>Pour modifier ce message, <a href="%s">cliquez ici</a>', $codesecret_url);
		
		$message .= sprintf('<br><br>La page publique de ce message est : <a href="%s">%s</a>', $url, $url);

		$message .= '<br><br>Bien cordialement,<br>L\'équipe de l\'Agenda partagé.';//TODO aussi dans covoiturage, event
		
		$message .= '<br>'.str_repeat('-', 20);
		$message .= sprintf('<br><br>Détails du message :<br><code>%s</code>', 'todo '.__FUNCTION__/* self::get_post_details_for_email($post) */);
		
		$message = quoted_printable_encode(str_replace('\n', '<br>', $message));

		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-type: text/html; charset=utf-8';
		$headers[] = 'Content-Transfer-Encoding: quoted-printable';

		if($success = wp_mail( $to
			, '=?UTF-8?B?' . base64_encode($subject). '?='
			, $message
			, $headers, $attachments )){
			$html = '<div class="info email-send">L\'e-mail a été envoyé.</div>';
		}
		else{
			$html = sprintf('<div class="email-send alerte">L\'e-mail n\'a pas pu être envoyé.</div>');
		}
		if($return_html_result){
			if($return_html_result === 'bool')
				return $success;
			else
				return $html;
		}
		return $html;
	}
	
	/**
	 * Clé d'activation depuis le mail pour basculer en 'publish'
	 */
	public static function get_activation_key($comment_id, $force_new = false){
		if(is_a($comment_id, 'WP_Comment'))
			$comment_id = $comment_id->comment_ID;
		$meta_name = 'activation_key';
		
		$value = get_comment_meta($comment_id, $meta_name, true);
		if($value && $value != 1 && ! $force_new)
			return $value;
		
		$guid = uniqid();
		
		$value = crypt($guid, AGDP_TAG . '-' . $meta_name);
		
		update_comment_meta($comment_id, $meta_name, $value);
		
		return $value;
		
	}
	
 	/**
	 * Pré-remplit le formulaire "Contactez nous" avec les informations d'un commentaire
	 */
	public static function wpcf7_contact_form_init_tags( $form ) { 
		$html = $form->prop('form');//avec shortcodes du wpcf7
		$requested_id = isset($_REQUEST[AGDP_ARG_COMMENTID]) ? $_REQUEST[AGDP_ARG_COMMENTID] : false;
		if( ! ($comment = get_comment($requested_id)))
			return;
		$url = get_comment_link( $comment );
		
		$date_debut = $comment->comment_date;
		if(mysql2date( 'j', $date_debut ) === '1')
			$format_date_debut = 'l j\e\r M Y';
		else
			$format_date_debut = 'l j M Y';
		
		$forum = get_post( $comment->comment_post_ID );
		/** init message **/
		$message = sprintf("Bonjour,\r\nJe vous écris à propos d'un message publié dans \"%s\" le %s à %s.\r\n%s\r\n\r\n-"
			, $forum->post_title
			, str_replace(' mar ', ' mars ', strtolower(mysql2date( $format_date_debut, $date_debut)))
			, date('H:i', strtotime($date_debut))
			, $url
		);
		$matches = [];
		if( ! preg_match_all('/(\[textarea[^\]]*\])([\s\S]*)(\[\/textarea)?/', $html, $matches))
			return;
		for($i = 0; $i < count($matches[0]); $i++){
			if( strpos( $matches[2][$i], "[/textarea") === false ){
				$message .= '[/textarea]';
			}
			$html = str_replace( $matches[1][$i]
					, sprintf('%s%s', $matches[1][$i], $message)
					, $html);
		}
		$user = wp_get_current_user();
		if( $user ){
		
			/** init name **/	
			$html = preg_replace( '/(autocomplete\:name[^\]]*)\]/'
					, sprintf('$1 "%s"]', $user->display_name)
					, $html);
		
			/** init email **/	
			$html = preg_replace( '/(\[email[^\]]*)\]/'
					, sprintf('$1 "%s"]', $user->user_email)
					, $html);
		}
		
		/** set **/
		$form->set_properties(array('form'=>$html));
		
	}
}
?>