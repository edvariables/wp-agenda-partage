<?php

/**
 * AgendaPartage -> Mailbox -> IMAP
 * Custom post type for WordPress.
 * 
 * Complète Agdp_Mailbox pour ses fonctions IMAP
 */
class Agdp_Mailbox_IMAP {

	/********************************************/
	/****************  IMAP  ********************/


	
	/**
	 * Teste la connexion IMAP.
	 */
	public static function check_connexion($mailbox){
		
		$imap = self::get_ImapReader($mailbox->ID, false);
		
		try {
			$imap
				->limit(1)
				->get();
			
			return true;
		}
		catch(Exception $exception){
			return $exception;
		}
	}
	
	/**
	 * Récupère les messages non lus depuis un serveur imap
	 */
	public static function get_imap_messages($mailbox){
		
		$imap = self::get_ImapReader($mailbox->ID);
		
		$search = date("j F Y", strtotime("-1 days"));
		$imap
			// ->limit(1) //DEBUG
			//->sinceDate($search)
			->orderASC()
			->unseen()
			->get();
			
		$messages = [];
		foreach($imap->emails() as $email){
			$agdp_headers = [];
			$agdp_header = 'X-' . AGDP_TAG;
			foreach($email->custom_headers as $header => $header_content){
				if( preg_match('/-SPAMCAUSE$/', $header) )
					$email->custom_headers[$header] = decode_spamcause( $header_content );
				elseif( strpos( $header, $agdp_header ) === 0 )
					$agdp_headers[ substr($header,2) /* X- */] = $header_content;
			}
		
			$message = [
				'id' => $email->id,
				'msgno' => $email->msgno,
				'date' => $email->date,
				'udate' => $email->udate,
				'subject' => $email->subject,
				'to' => $email->to,
				'cc' => $email->cc,
				'from' => $email->from,
				'reply_to' => $email->reply_to,
				'attachments' => self::sanitize_attachments($email->attachments),
				'text_plain' => $email->text_plain,
				'text_html' => $email->text_html,
				'headers' => $agdp_headers
			];
			
			$agdp_header_uid = AGDP_TAG . '-UID';
			if( isset($agdp_headers[$agdp_header_uid]) )
				$message[AGDP_IMPORT_UID] = $agdp_headers[$agdp_header_uid];
			
			$messages[] = $message;
				
		}
		return $messages;
	}
	
	/**
	 * Retourne une instance du lecteur IMAP.
	 */
	private static function get_ImapReader($mailbox_id, $mark_as_read = null){
		require_once( AGDP_PLUGIN_DIR . "/includes/phpImapReader/Reader.php");
		require_once( AGDP_PLUGIN_DIR . "/includes/phpImapReader/Email.php");
		require_once( AGDP_PLUGIN_DIR . "/includes/phpImapReader/EmailAttachment.php");
		
		$server = get_post_meta($mailbox_id, 'imap_server', true);
		$email = get_post_meta($mailbox_id, 'imap_email', true);
		$password = get_post_meta($mailbox_id, 'imap_password', true);
		if( $mark_as_read !== false )
			$mark_as_read = get_post_meta($mailbox_id, 'imap_mark_as_read', true);
		
		$encoding = get_post_meta($mailbox_id, 'imap_encoding', true);
		if( ! $encoding )
			$encoding = 'UTF-8';
		$content_encoding = 'UTF-8';
		
		$attachment_path = Agdp_Mailbox::get_attachments_path($mailbox_id);
		
		$imap = new benhall14\phpImapReader\Reader($server, $email, $password, $attachment_path, $mark_as_read, $encoding, $content_encoding);

		return $imap;
	}
	
	/**
	 * Retourne le contenu expurgé depuis un email.
	 */
	public static function get_imap_message_content($mailbox_id, $message, $comment_parent){
		/* $content = ' '
				. (empty($message['text_plain']) 
					? html_to_plain_text( $message['text_html'] )
					: html_entity_decode($message['text_plain'], ENT_QUOTES)); */
/* 		$content = ' '
				. ( empty($message['text_plain']) 
					? $message['text_html']
					: html_entity_decode($message['text_plain'], ENT_QUOTES));*/
		$content = ' '
				. ( ! empty($message['text_html']) 
					? html_inner_body($message['text_html'])
					: html_entity_decode($message['text_plain'], ENT_QUOTES));
		debug_log(__FUNCTION__, $message['text_html'], 'content', $content);
		if( $clear_signatures = get_post_meta($mailbox_id, 'clear_signature', true))
			foreach( explode("\n", str_replace("\r", '', $clear_signatures)) as $clear_signature ){
				if ( ($pos = strpos( $content, $clear_signature) ) > 0)
					$content = substr( $content, 0, $pos);
			}
			
		$clear_raws = get_post_meta($mailbox_id, 'clear_raw', true);
		foreach( explode("\n", str_replace("\r", '', $clear_raws)) as $clear_raw ){
			if( ! $clear_raw )
				continue;
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
	
	/**
	 * Fait le ménage dans les fichiers attachés
	 */
	private static function sanitize_attachments($attachments){
		if( ! $attachments || count($attachments) === 0 )
			return null;
		$files = [];
		foreach($attachments as $attachment){
			if ( ! file_exists( $attachment->file_path ) )
				continue;
			$extension = strtolower(pathinfo($attachment->file_path, PATHINFO_EXTENSION));
			switch($extension){
				case 'exe':
				case 'sh':
				case 'cmd':
				case 'bat':
				case 'vbs':
				case 'js':
				case 'php':
					unlink($attachment->file_path);
					continue 2;
			}
			$files[] = $attachment->file_path;
		}
		return $files;
	}
}
?>