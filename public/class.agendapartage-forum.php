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
				debug_log($messages);
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
			global $post;
			if (!($page = $post))
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
		add_filter('get_comment_author', array(__CLASS__, 'on_get_comment_author'), 10, 3 );
		
		try {
			return self::import_imap_messages($forum, $page);
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
	 * Affichage de l'auteur du commentaire
	 */
	public static function on_get_comment_author($comment_author, $comment_id, $comment ){
		if( $comment->comment_author_email )
			return sprintf('<a href="mailto:%s">%s</a>', $comment->comment_author_email, $comment_author);
		return $comment_author;
	}
	
	/********************************************/
	/****************  IMAP  ********************/
	/********************************************/
	/**
	 * Get messages from linked email via imap
	 */
	public static function import_imap_messages($forum, $page){
		$forum = self::get_forum($forum);
		if( ! $forum )
			return false;
		
		if( ! ($messages = self::get_imap_messages($forum)))
			return false;
		if( is_a($messages, 'WP_ERROR') )
			return false;
		if( count($messages) === 0 )
			return false;
		
		$imap_server = get_post_meta($forum->ID, 'imap_server', true);
		$imap_email = get_post_meta($forum->ID, 'imap_email', true);
		
		add_filter('pre_comment_approved', array(__CLASS__, 'on_imap_pre_comment_approved'), 10, 2 );
		
		foreach( $messages as $message ){
			if( ($comment = self::get_existing_comment( $forum, $message )) ){
			}
			else {
				
				$comment_parent = 0;
				$user_id = 0;
				// var_dump($message);
				$user_email = $message['reply_to'][0]->email;
				$user_name = $message['reply_to'][0]->name ? $message['reply_to'][0]->name : $user_email;
				$comment_approved = true;
				
				$commentdata = [
					'comment_post_ID' => $page->ID,
					'comment_author' => $user_name,
					'comment_author_url' => 'mailto:' . $user_email,
					'comment_author_email' => $user_email,
					'comment_content' => self::get_imap_message_content($forum->ID, $message),
					'comment_date' => date(DATE_ATOM, $message['udate']),
					'comment_parent' => $comment_parent,
					'comment_agent' => $imap_email . '@' . $imap_server,
					'comment_approved' => $comment_approved,
					'user_id' => $user_id,
					'comment_meta' => [
						'source' => 'imap',
						'source_server' => $imap_server,
						'source_email' => $imap_email,
						'source_id' => $message['id'],
						'source_no' => $message['msgno'],
						'from' => $message['from']->email,
						'title' => $message['subject'],
						'attachments' => $message['attachments'],
					]
				];
				// var_dump($commentdata);
				$comment = wp_new_comment($commentdata, true);
				if( is_a($comment, 'WP_ERROR') ){
					continue;
				}
			}
		}
		remove_filter('pre_comment_approved', array(__CLASS__, 'on_imap_pre_comment_approved'), 10);
		
		return $messages;
	}
	//Force l'approbation du commentaire pendant la boucle d'importation
	public static function on_imap_pre_comment_approved($approved, $commentdata){
		return true;
	}
	//Cherche un message déjà importé
	private static function get_existing_comment( $forum, $message ){
	}
	/**
	 * Récupère les messages non lus depuis un serveur imap
	 */
	public static function get_imap_messages($forum){
		
		
		require_once( AGDP_PLUGIN_DIR . "/includes/phpImapReader/Reader.php");
		require_once( AGDP_PLUGIN_DIR . "/includes/phpImapReader/Email.php");
		require_once( AGDP_PLUGIN_DIR . "/includes/phpImapReader/EmailAttachment.php");
		$imap = self::get_ImapReader($forum->ID);
		
		$search = date("j F Y", strtotime("-1 days"));
		$imap
			// ->limit(1) //DEBUG
			//->sinceDate($search)
			->orderASC()
			->unseen()
			->get();
			
		$messages = [];
		foreach($imap->emails() as $email){
			foreach($email->custom_headers as $header => $header_content){
				if( preg_match('/-SPAMCAUSE$/', $header) )
					$email->custom_headers[$header] = decode_spamcause( $header_content );
			}
			$messages[] = [
				'id' => $email->id,
				'msgno' => $email->msgno,
				'date' => $email->date,
				'udate' => $email->udate,
				'subject' => $email->subject,
				'to' => $email->to,
				'from' => $email->from,
				'reply_to' => $email->reply_to,
				'attachments' => $email->attachments,
				'text_plain' => $email->text_plain,
				'text_html' => $email->text_html
			];
		}
		
		return $messages;
	}
	
	/**
	 * Retourne une instance du lecteur IMAP.
	 */
	private static function get_ImapReader($forum_id){
		$server = get_post_meta($forum_id, 'imap_server', true);
		$email = get_post_meta($forum_id, 'imap_email', true);
		$password = get_post_meta($forum_id, 'imap_password', true);
		$mark_as_read = get_post_meta($forum_id, 'imap_mark_as_read', true);
		
		$encoding = 'UTF-8';
		
		$imap = new benhall14\phpImapReader\Reader($server, $email, $password, AGDP_FORUM_ATTACHMENT_PATH, $mark_as_read, $encoding);

		return $imap;
	}
	
	/**
	 * Retourne le contenu expurgé depuis un email.
	 */
	private static function get_imap_message_content($forum_id, $message){
		$content = empty($message['text_plain']) 
				? preg_replace('/^.*\<html.*\>([\s\S]*)\<\/html\>.*$/i', '$1', $message['text_html'])
				: $message['text_plain'];
		
		if( $clear_signature = get_post_meta($forum_id, 'clear_signature', true)){
			if ( ($pos = strpos( $content, $clear_signature) ) > 0)
				$content = substr( $content, 0, $pos);
		}
		
		$clear_raws = get_post_meta($forum_id, 'clear_raw', true);
		foreach( explode('|', $clear_raws) as $clear_raw ){
			$raw_start = -1;
			$raw_end = -1;
			$offset = 0;
			while ( $offset < strlen($content)
			&& ( $raw_start = strpos( $content, $clear_raw, $offset) ) >= 0
			&& $raw_start !== false)
			{
				if ( ($raw_end = strpos( $content, "\n", $raw_start + strlen($clear_raw)-1)) == false)
					$raw_end = strlen($content)-1;
				$offset = $raw_start;
				$content = substr( $content, 0, $raw_start) . substr( $content, $raw_end + 1);
			}
		}
		return trim($content);
	}
	
}
?>