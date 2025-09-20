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
	public static function get_imap_messages($mailbox, $imap = null){
		
		if( ! $imap ){
			$imap = self::get_ImapReader($mailbox->ID);
			
			// $search = date("j F Y", strtotime("-1 days"));
			$imap
				// ->limit(1) //DEBUG
				//->sinceDate($search)
				->orderASC()
				->unseen()
			;
		}
		
		$imap->get();
		
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
				'attachments' => $email->attachments,
				'text_plain' => $email->text_plain,
				'text_html' => $email->text_html,
				'headers' => $agdp_headers,
				'__imap_email' => $email,
			];
			
			$message = self::sanitize_attachments($message);
			
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
	public static function get_imap_message_content($mailbox_id, $message, $comment_parent, $page){
		//import_plain_text
		if( $page ){
			$meta_key = 'import_plain_text';
			$import_plain_text = get_post_meta($page->ID, $meta_key, true);
		}
		else
			$import_plain_text = false;
		if( $import_plain_text ){
			$content = ' '
				. (empty($message['text_plain']) 
					? html_to_plain_text( $message['text_html'] )
					: html_entity_decode($message['text_plain'], ENT_QUOTES));
		}
		else {
	/* 		$content = ' '
					. ( empty($message['text_plain']) 
						? $message['text_html']
						: html_entity_decode($message['text_plain'], ENT_QUOTES));*/
			$content = ' '
					. ( ! empty($message['text_html']) 
						? html_inner_body($message['text_html'])
						: html_entity_decode($message['text_plain'], ENT_QUOTES));
		}
		// debug_log(__FUNCTION__, $message['text_html'], 'content', $content);
		$meta_key = 'clear_signature';
		if( $clear_signatures = get_post_meta($mailbox_id, $meta_key, true))
			foreach( explode("\n", str_replace("\r", '', $clear_signatures)) as $clear_signature ){
				if ( ($pos = strpos( $content, $clear_signature) ) > 0)
					$content = substr( $content, 0, $pos);
			}
			
		$meta_key = 'clear_raw';
		$clear_raws = get_post_meta($mailbox_id, $meta_key, true);
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
		
		//comment_parent
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
		
		// debug_log(__FUNCTION__, $content);
		return trim($content);
	}
	
	/**
	 * Fait le ménage dans les fichiers attachés
	 */
	public static function sanitize_attachments( $message ){
		$attachments = $message['attachments'];
		if( ! $attachments || count($attachments) === 0 )
			return $message;
			
		$files = [];
		foreach($attachments as $attachment){
			if( is_string($attachment) ){
				$filename = $attachment;
				$attachment = new stdClass();
				$attachment->file_path = $filename;
				$attachment->id = date('YmdHis') . microtime() . count($files);
			}
			$filename = $attachment->file_path;
			if ( ! file_exists( $filename ) )
				continue;
			$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
			switch($extension){
				case 'exe':
				case 'sh':
				case 'cmd':
				case 'bat':
				case 'vbs':
				case 'js':
				case 'css':
				case 'php':
					unlink($filename);
					continue 2;
			}
			
			$filename = image_reduce($filename, AGDP_IMG_MAX_WIDTH, AGDP_IMG_MAX_HEIGHT, false );
			
			//Remplace les sources des balises img
			if( ! empty($message['text_html']) ){
				$text_html = $message['text_html'];
				$pattern = sprintf('/\<img\s[^>]*src="cid\:%s"([^>]+)\>/', preg_quote($attachment->id, '/'));
				$url = upload_file_url( $filename );
				$replace = sprintf('<a href="%s"><img src="%s"$1/></a>', $url, $url);
				$text_html = preg_replace( $pattern, $replace, $text_html );
				$message['text_html'] = $text_html;
			}
			$files[] = $filename;
			
		}
		$message['attachments'] = $files;
		return $message;
	}

	
	/**
	 * Redirige un email vers une autre adresse
	 TODO inachevé
	 */
	public static function redirect_message( $mailbox, $message, $redir_to ){
		$imap = self::get_ImapReader($mailbox->ID);
		
		if( is_numeric($message) ){
			$imap->id( $message );
			$messages = static::get_imap_messages( $mailbox, $imap );
			if( count($messages) === 0 )
				return false;
			
			$message = $messages[0];
		}
		
		$email = $message['__imap_email'];
		// debug_log( __FUNCTION__, $message);
		
		$envelope = [];
		$body = [];
		
		$envelope["date"] = $email->date;
		$envelope["from"] = $email->reply_to ? $email->reply_to : $email->from;
		$envelope["reply_to"] = $email->reply_to ? $email->reply_to : $email->from;
		$envelope["to"] = $redir_to;
		if ($email->cc) {
			$envelope["cc"] = $email->cc;
		}
		//emails as object to string
		foreach(['from', 'to', 'cc', 'reply_to'] as $field){
			if( ! isset($envelope[$field]) )
				continue;
			if( is_array($envelope[$field]) ){
				if( count($envelope[$field]) ){
					$envelope[$field] = $envelope[$field][0];
				}
				else
					$envelope[$field] = false;
			}
			if( is_object($envelope[$field]) ){
				// if( $envelope[$field]->name )
					// $envelope[$field] = sprintf('"%s"<%s>', $envelope[$field]->name, $envelope[$field]->email); Bugg Name UTF8
				// else
					$envelope[$field] = $envelope[$field]->email;
			}
		}
		
		// subject
		$prefix = '';
		$prefix = sprintf('[Agdp-fw][%s]', wp_date(DATE_RFC2822)); //DEBUG
		$envelope["subject"] = $prefix . $email->subject;
		
		// $custom_headers
		$custom_headers = [];
		$email_headers = array_change_key_case($email->headers);
		foreach([
			'MIME-Version',
			'Date',
			// 'From',
			'In-Reply-To',
			'References',
			// 'User-Agent',
			'X-Sender',
			'Content-Type',
			'Content-Transfer-Encoding',
		] as $header_name){
			if( empty($email_headers[strtolower($header_name)]) )
				continue;
			$custom_headers[$header_name] = $email_headers[strtolower($header_name)];
		}
		
		$header_name = 'Reply-to';
		$custom_headers[$header_name] = $envelope["reply_to"];
		
		$header_name = 'X-Redir-' . AGDP_TAG;
		$custom_headers[$header_name] = get_bloginfo('url');
		
		
		$envelope["custom_headers"] = [];
		foreach($custom_headers as $header_name => $header_value){
			$header_value = explode( "\r\n", $header_value);
			$envelope["custom_headers"][] = $header_name . ': ' . $header_value[0];
			for($i = 1; $i < count($header_value); $i++)
				$envelope["custom_headers"][] = $header_value[$i];
		}
		
		$envelope["attachments"] = [];
		// foreach( $email->attachments as $attachment){
			// if( file_exists($attachment->file_path) )
				// $envelope["attachments"][] = $attachment->file_path;
		// }
		
		if( false) {
		//Rappel : $message['attachments'] = sanitize( $email->attachments )
		// if ($message['attachments']) {
			// $multipart["type"] = TYPEMULTIPART;
			// $multipart["subtype"] = "alternative";
			// $body[] = $multipart;
			
			// foreach ($email->attachments as $attach) {
				// $filename = $attach->file_path;
				// if( empty( $message['attachments'][$filename] ) )
					// continue;
				
				// $part = array();
				
				// $file_size = filesize($filename);
					
				// if ( $file_size > 0) {
					// $fp = fopen($filename, "rb");
					// $part["id"] = $attach->id;
					// $part["type"] = $attach->type;
					// $part["encoding"] = $attach->encoding;
					// $part["subtype"] = $attach->subtype;
					// $part["description"] = basename($filename);
					// $part['disposition.type'] = 'attachment';
					// $part['disposition'] = array('filename' => basename($filename));
					// $part['type.parameters'] = array('name' => $attach->name ? $attach->->name : basename($filename));
					// $part["description"] = '';
					// $part["contents.data"] = base64_encode(fread($fp, $file_size));
					// $body[] = $part;
					// fclose($fp);
				// }
			// }
		// }
		
		// if ( $email->text_html ) {
			// $part = array();
			// $part["type"] = "TEXT";
			// $part["subtype"] = "html";
			// $part["description"] = '';
			// $part["contents.data"] = $email->text_html;
			// $body[] = $part;
		// }
		// if ( $email->text_plain ) {
			// $part = array();
			// $part["type"] = "TEXT";
			// $part["subtype"] = "html";
			// $part["description"] = '';
			// $part["contents.data"] = $email->text_plain;
			// $body[] = $part;
		// }
		}
		
		$body = $email->raw_parts;
		// $body = $email->raw_body;
		debug_log(__FUNCTION__, $envelope['to'],
				$envelope['subject'],
				$envelope["custom_headers"], $body/* , $envelope, $email_headers*/,
				isset($envelope["attachments"]) ? $envelope['attachments'] : false );
		// return;

		// $msg = imap_mail_compose($envelope, $body);
		// if (imap_append($imap->stream(), "INBOX", $msg) === false) {
			
		// add_filter( 'wp_mail_content_type', array( __CLASS__, 'on_redir_wp_mail_content_type') );
		
		$success = wp_mail(
				$envelope['to'],
				'=?UTF-8?B?' . base64_encode($envelope['subject']). '?=',
				$body,
				$envelope["custom_headers"],
				isset($envelope["attachments"]) ? $envelope['attachments'] : false
			);
			
		// remove_filter( 'wp_mail_content_type', array( __CLASS__, 'on_redir_wp_mail_content_type') );
			
		if ( $success === false) {
			$error = imap_last_error();
			debug_log(__FUNCTION__, "Erreur de redirection d'un mail par IMAP : " . $error);
			
			if( class_exists('Agdp_Admin') )
				Agdp_Admin::add_admin_notice_now("Erreur de redirection d'un mail par IMAP : " . $error, ['type' => 'error']);
			return FALSE;
		} else {
			
			if( class_exists('Agdp_Admin') )
				Agdp_Admin::add_admin_notice_now(sprintf("Redirection d'un mail par IMAP effectuée : %s > %s", 
						$envelope['subject'], $envelope['to'])
					, ['type' => 'info']);
			return TRUE;
		}
		
	}
	
	/**
	 * On sending mail, add plain text content
	 */
    // public static function on_redir_wp_mail_content_type( $content_type ) {
		// return $content_type;
    // }
	
}
?>