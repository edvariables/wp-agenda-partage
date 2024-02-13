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
	 */
	public static function init_page($forum, $page = false){
		if( ! $page ){
			if (!($page = self::get_page_of_forum( $forum )))
				return false;
		}
		elseif( is_int( $page ))
			if (!($page = get_post($page)))
				return false;
		
		
		$forum = self::get_forum($forum);
		
		add_filter('comment_form_defaults', array(__CLASS__, 'on_comment_text_before') );
		add_filter('comment_form_fields', array(__CLASS__, 'on_comment_form_fields') );
		add_filter('preprocess_comment', array(__CLASS__, 'on_preprocess_comment') );
		add_filter('comment_text', array(__CLASS__, 'on_comment_text'), 10, 3 );
		add_filter('comment_reply_link', array(__CLASS__, 'on_comment_reply_link'), 10, 4 );
		// add_filter('comment_reply_link_args', array(__CLASS__, 'on_comment_reply_link_args'), 10, 3 );
		add_filter('get_comment_author_link', array(__CLASS__, 'on_get_comment_author_link'), 10, 3 );
		
		try {
			require_once( AGDP_PLUGIN_DIR . "/public/class.agendapartage-forum-imap.php");
			return AgendaPartage_Forum_IMAP::import_imap_messages($forum, $page);
		}
		catch(Exception $exception){
			return $exception;
		}
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
	
	/********************************************/
		 
	/**
	 * Modification du formulaire de commentaire
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
		$title_field = '<p class="comment-form-title"><label for="title">Titre <span class="required">*</span></label> <input id="title" name="title" type="text" maxlength="255" required></textarea></p>';
		$fields['comment'] = $title_field . $fields['comment'];
		unset($fields['url']);
		return $fields;
	}
	
	/**
	 * Ajout du meta title lors de l'enregistrement du commentaire
	 */
	public static function on_preprocess_comment($commentdata ){
		
		if( ! self::get_forum($commentdata['comment_post_ID']) )
			return $commentdata;
		
		if( empty( $_POST['title'] )){
			if( isset($commentdata['comment_meta']) && isset($commentdata['comment_meta']['title']))
				return $commentdata;
			echo 'Le titre ne peut être vide.';
			die();
		}
		if( empty($commentdata['comment_meta']) )
			$commentdata['comment_meta'] = [];
		$commentdata['comment_meta'] = array_merge([ 'title' => $_POST['title'] ], $commentdata['comment_meta']);
		
		return $commentdata;
	}
	/********************************************/
	
	/**
	 * Affichage du commentaire
	 */
	public static function on_comment_text($comment_text, $comment, $args ){
		
		$title = get_comment_meta($comment->comment_ID, 'title', true);	
		
		echo sprintf('<h3>%s</h3>', $title);
		
		return $comment_text;
	}
	/**
	 * Affichage du commentaire, lien "Répondre"
	 */
	public static function on_comment_reply_link($comment_reply_link, $args, $comment, $post ){
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
				$icon = 'info-outline';
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
		if ( ! is_a($comment, 'WP_Error') )
			return 'js:$actionElnt.parents(\'.comment:first\').remove();';
		return $comment->get_error_message();
	}
	
	
}
?>