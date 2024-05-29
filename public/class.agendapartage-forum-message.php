<?php

/**
 * AgendaPartage -> Forum -> Message
 * Edition de message dans un forum.
 *
 * Voir aussi AgendaPartage_Forum
 *
 */
class AgendaPartage_Forum_Message {

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
		global $pagenow;
		add_action( 'wp_ajax_'.AGDP_TAG.'_comment_action', array(__CLASS__, 'on_wp_ajax_comment') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_comment_action', array(__CLASS__, 'on_wp_ajax_comment') );
		add_filter('pre_comment_approved', array(__CLASS__, 'on_pre_comment_approved'), 10, 2 );
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
		
		add_filter('comment_form_defaults', array(__CLASS__, 'on_comment_form_defaults') );
		add_filter('comment_form_fields', array(__CLASS__, 'on_comment_form_fields') );
		add_filter('comment_text', array(__CLASS__, 'on_comment_text'), 10, 3 );
		add_filter('get_comment_time', array(__CLASS__, 'on_get_comment_time'), 10, 5 );
		add_filter('comment_reply_link', array(__CLASS__, 'on_comment_reply_link'), 10, 4 );
		add_filter('get_comment_author_link', array(__CLASS__, 'on_get_comment_author_link'), 10, 3 );
		
		// if( self::get_current_forum_rights( $page ) != 'P' )
			AgendaPartage_Forum::get_current_forum_rights( $page );//set current_forum $page
			if( AgendaPartage_Forum::user_can_see_forum_details( $page ) )
				add_filter('get_comment_author_link', array(__CLASS__, 'on_get_comment_author_status'), 11, 3 );
		
		return true;
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
		if( $comment_css = AgendaPartage_Forum::get_property('comment_css') ){
			echo '<style>'.  $comment_css . '</style>';
		}
		
		if( ( ! isset($_REQUEST['replytocom']) && AgendaPartage_Forum::get_property_is_value('comment_form', false))
		|| ! AgendaPartage_Forum::user_can_post_comment()	){
			$fields['comment'] = '<script>jQuery("#respond.comment-respond").remove();</script>';
			return $fields;
		}
		
		$title_field = '<p class="comment-form-title"><label for="title">Titre <span class="required">*</span></label>'
			// . '<label><input name="title-prefix" type="radio">Je propose</label>'
			// . '<label><input name="title-prefix" type="radio">Je cherche</label>'
			. '<input id="title" name="title" type="text" maxlength="255" required></p>';
		$send_email_field = '<div class="comment-form-send-email if-respond"><label for="send-email">'
			. '<input id="send-email" name="send-email" type="checkbox">'
			. ' Envoyez votre réponse par e-mail à l\'auteur du message</label></div>';
		$is_private_field = '<p class="comment-form-is-private if-respond"><label for="is-private">'
			. '<input id="is-private" name="is-private" type="checkbox">'
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
		if( ! ($mailbox = AgendaPartage_Mailbox::get_mailbox_of_page($commentdata['comment_post_ID']) ))
			return $approved;
		if( ! AgendaPartage_Forum::get_forum_comment_approved($commentdata['comment_post_ID']) )
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
		if( ! AgendaPartage_Forum::get_property_is_value('comment_title', false) ){
			$title = get_comment_meta($comment->comment_ID, 'title', true);	
			echo sprintf('<h3 class="comment-title">%s</h3>', $title);
		}
		if( ! AgendaPartage_Forum::user_can_see_forum_details() )
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
		if( ! AgendaPartage_Forum::user_can_see_forum_details() ){
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
		
		if( AgendaPartage_Forum::get_property_is_value('reply_link', false) )
			$comment_reply_link = $comment_actions;
		else
			$comment_reply_link = preg_replace('/(\<\/div>)$/', $comment_actions . '$1', $comment_reply_link);
		
		return $comment_reply_link;
	}
	
	/**
	 * Retourne le html d'action pour marqué un message comme étant terminé
	 */
	private static function get_comment_mark_as_ended_link($comment_id){
		if( AgendaPartage_Forum::get_property_is_value('mark_as_ended', false) )
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
	
	/**
	 * Affichage de l'auteur du commentaire
	 */
	public static function on_get_comment_author_link( $comment_author_link, $comment_author, $comment_id ){
		if( ! AgendaPartage_Forum::user_can_see_forum_details() )
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
				if( AgendaPartage_Forum::get_forum_right_need_subscription() ){
					switch($subscription = AgendaPartage_Forum::get_subscription( $comment->comment_author_email, AgendaPartage_Forum::$current_forum )){
						case false:
						case '':
							$status = 'non-membre';
							break;
						default:
							$subscription_roles = AgendaPartage_Forum::subscription_roles;
							$status = isset($subscription_roles[$subscription]) ? strtolower($subscription_roles[$subscription]) : '?'.$subscription;
							break;
					}
				}
			}
		}
		else
			$status = 'inconnu-e';
		if( $status ){
			if( $user_id )
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
			AgendaPartage_Forum::init_page(false, $comment->comment_post_ID);
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
	
}
?>