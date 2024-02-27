<?php

/**
 * AgendaPartage -> Forum -> IMAP
 * Custom post type for WordPress.
 * 
 * Complète AgendaPartage_Forum pour ses fonctions IMAP
 */
class AgendaPartage_Forum_IMAP {

	/********************************************/
	/****************  IMAP  ********************/
	/********************************************/
	/**
	 * Get messages from linked email via imap
	 */
	public static function import_imap_messages($forum, $page){
		$forum = AgendaPartage_Forum::get_forum($forum);
		if( ! $forum ){
			return false;
		}
		if( ! ($messages = self::get_imap_messages($forum))){
			return is_array($messages);
		}
		if( is_a($messages, 'WP_ERROR') )
			return $messages;
		
		$imap_server = get_post_meta($forum->ID, 'imap_server', true);
		$imap_email = get_post_meta($forum->ID, 'imap_email', true);
		
		add_filter('pre_comment_approved', array(__CLASS__, 'on_imap_pre_comment_approved'), 10, 2 );
		foreach( $messages as $message ){
			if( ($comment = self::get_existing_comment( $page, $message )) ){
			}
			else {
				// break;
				$comment_parent = self::find_comment_parent( $page, $message );
				$user_id = 0;
				// var_dump($message);
				$user_email = $message['reply_to'][0]->email;
				$user_name = $message['reply_to'][0]->name ? $message['reply_to'][0]->name : $user_email;
				if( ($pos = strpos($user_name, '@')) !== false)
					$user_name = substr( $user_name, 0, $pos);
				
				$dateTime = $message['date'];
				$date = wp_date('Y-m-d H:i:s', $dateTime->getTimestamp());
				$date_gmt = $dateTime->format('Y-m-d H:i:s');
				
				$commentdata = [
					'comment_post_ID' => $page->ID,
					'comment_author' => $user_name,
					'comment_author_url' => 'mailto:' . $user_email,
					'comment_author_email' => $user_email,
					'comment_content' => self::get_imap_message_content($forum->ID, $message, $comment_parent),
					'comment_date' => $date,
					'comment_date_gmt' => $date_gmt,
					'comment_parent' => $comment_parent,
					'comment_agent' => $imap_email . '@' . $imap_server,
					'comment_approved' => true,
					'user_id' => $user_id,
					'comment_meta' => [
						'source' => 'imap',
						'source_server' => $imap_server,
						'source_email' => $imap_email,
						'source_id' => $message['id'],
						'source_no' => $message['msgno'],
						'from' => $message['from']->email,
						'title' => trim($message['subject']),
						'attachments' => $message['attachments'],
						'import_date' => wp_date(DATE_ATOM),
					],
					'forum_id' => $forum->ID
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
		if ( ! self::user_email_approved( $commentdata['comment_author_email'], $commentdata['forum_id'] ) )
			return false;
		return true;
	}
	//Cherche un message déjà importé
	private static function get_existing_comment( $page, $message ){
	}
	/**
	 * Cherche un message existant qui serait le parent du message
	 * Retourne l'identifiant du parent ou 0
	 */
	private static function find_comment_parent( $page, $message ){
		$subject = $message['subject'];
		$prefix = 're:';
		if( strcasecmp($prefix, substr($subject, 0, strlen($prefix)) === 0 ) ){
			$title = trim(substr($subject, strlen($prefix)));
			$comments = get_comments([
				'post_id' => $page->ID
				, 'meta_key' => 'title'
				, 'meta_value' => $title
				, 'meta_compare' => '='
				, 'number' => 1
				, 'orderby' => 'comment_date'
			]);
			foreach($comments as $comment){
				return $comment->comment_ID;
			}
			
		}
		return 0;
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
			// debug_log($email->custom_headers);
			
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
	private static function get_imap_message_content($forum_id, $message, $comment_parent){
		$content = ' '
				. (empty($message['text_plain']) 
					? html_to_plain_text( $message['text_html'] )
					: $message['text_plain']);
		if( $clear_signatures = get_post_meta($forum_id, 'clear_signature', true))
			foreach( explode("\n", str_replace("\r", '', $clear_signatures)) as $clear_signature ){
				if ( ($pos = strpos( $content, $clear_signature) ) > 0)
					$content = substr( $content, 0, $pos);
			}
		
		$clear_raws = get_post_meta($forum_id, 'clear_raw', true);
		foreach( explode("\n", str_replace("\r", '', $clear_raws)) as $clear_raw ){
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
		
		if( $comment_parent ){
			// echo "<br><pre>$content</pre><br><br><br>";
			$content = preg_replace( '/[\n\r]+Le\s[\S\s]+a\sécrit\s\:\s*([\n\r]+\>\s)/', '$1', $content);
			// echo "<pre>$content</pre><br><br><br>";
			$content = preg_replace( '/^\>\s.*$/m', '', $content);
			$content = preg_replace( '/\s+$/', '', $content);
			// debug_log($content);
			// echo "<pre>$content</pre>";
			// die();
		}
		
		return trim($content);
	}
	
	private static function user_email_approved( $user_email, $forum_id ) {
		//L'origine du mail est l'adresse de la boite imap (bouclage)
		$source_email = get_post_meta($forum_id, 'imap_email', true);
		if( $user_email === $source_email )
			return false;
		//L'origine du mail est l'adresse d'envoi de newsletter de ce site
		$source_email = AgendaPartage_Newsletter::get_mail_sender();
		if( $user_email === $source_email )
			return false;
		return true;
	}
	
}
?>