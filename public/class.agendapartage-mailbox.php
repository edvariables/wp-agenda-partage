<?php

/**
 * AgendaPartage -> Mailbox
 * Custom post type for WordPress.
 * 
 * Définition du Post Type agdpmailbox
 * Mise en forme du formulaire Mailbox
 *
 * Voir aussi AgendaPartage_Admin_Mailbox
 *
 * Une mailbox dispatche les mails vers des posts ou comments.
 */
class AgendaPartage_Mailbox {

	const post_type = 'agdpmailbox';

	const cron_hook = 'agdpmailbox_cron_hook';
	
	const sync_time_meta_key = 'imap_sync_time';
		
	// const user_role = 'author';

	private static $initiated = false;
	public static $cron_state = false;

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
		add_action( self::cron_hook, array(__CLASS__, 'on_cron_exec') );
		
		self::init_cron(); //SIC : register_activation_hook( 'AgendaPartage_Mailbox', 'init_cron'); ne suffit pas
	}
	/*
	 **/
	
	
	/**
	 * Returns posts where post_status == 'publish' && meta['cron-enable'] == true
	 */
	 public static function get_active_mailboxes(){
		$posts = [];
		foreach( get_posts([
			'post_type' => self::post_type
			, 'post_status' => 'publish'
			, 'meta_key' => 'cron-enable'
			, 'meta_value' => '1'
			, 'meta_compare' => '='
			]) as $post)
			$posts[$post->ID . ''] = $post;
		return $posts;
	}
	
	/**
	 * Retourne l'objet mailbox.
	 */
	public static function get_mailbox($mailbox = false){
		$mailbox = get_post($mailbox);
		if(is_a($mailbox, 'WP_Post')
		&& $mailbox->post_type == self::post_type)
			return $mailbox;
		return false;
	}
	
	/**
	 * Etat du cron
	 */
	public static function get_cron_state(){
		if( ! self::$cron_state){
			$cron_time = wp_next_scheduled( self::cron_hook );
			if( $cron_time === false )
				self::$cron_state = sprintf('0|'); 
			else
				self::$cron_state = self::get_cron_time_str($cron_time); 
		}
		return self::$cron_state;
	}
	public static function get_cron_time(){
		return wp_next_scheduled( self::cron_hook );
	}
	public static function get_cron_time_str($cron_time = false){
		if( ! $cron_time )
			$cron_time = wp_next_scheduled( self::cron_hook );
		if( $cron_time === false )
			return '(cron inactif)'; 
		else{
			$delay = $cron_time - current_time('timestamp');
			return sprintf('Prochaine évaluation dans %s - %s'
					, wp_date('H:i:s', $delay)	
					, wp_date('d/m/Y H:i:s', $cron_time)); 
		}
	}
	
	/**
	 * Log l'état du cron
	 */
	public static function log_cron_state(){
		debug_log('[agdpmailbox-cron state]' . self::$cron_state);
	}
	
	/**
	 * Active le cron
	 * $next_time in seconds or timestamp
	 */
	public static function init_cron($next_time = false){
		$cron_time = wp_next_scheduled( self::cron_hook );
		if($next_time){
			if( $cron_time !== false )
				wp_unschedule_event( $cron_time, self::cron_hook );
			if( $next_time < 1024 )
				$cron_time = strtotime( date('Y-m-d H:i:s') . ' + ' . $next_time . ' second');
			else
				$cron_time = $next_time;
			$result = wp_schedule_single_event( $cron_time, self::cron_hook, [], true );
			// debug_log('[agdpmailbox-init_cron] wp_schedule_single_event', date('H:i:s', $cron_time - time()));
		}
		if( $cron_time === false ){
			$cron_time = wp_schedule_event( time(), 'hourly', self::cron_hook );
			// debug_log('[agdpmailbox-init_cron] wp_schedule_event', $cron_time);
			register_deactivation_hook( __CLASS__, 'deactivate_cron' ); 
		}
		else {
			// debug_log('[agdpmailbox-init_cron] next in ' . date('H:i:s', $cron_time - time()));
		}
		return self::get_cron_state(); 
	}

	/**
	 * Désactive le cron
	 */
	public static function deactivate_cron(){
		$timestamp = wp_next_scheduled( self::cron_hook );
		wp_unschedule_event( $timestamp, self::cron_hook );
		self::$cron_state = sprintf('0|Désactivé'); 
	}
	/**
	 * A l'exécution du cron, cherche des destinataires pour ce jour
	 */
	public static function on_cron_exec(){
		self::cron_exec(false);
	}
	
	/**
	 * A l'exécution du cron, cherche des destinataires pour ce jour
	 */
	public static function cron_exec($simulate = false, $forced = false){
		$mailboxes = self::get_active_mailboxes();
		if( ! $mailboxes || count($mailboxes) === 0){
			self::deactivate_cron();
			self::$cron_state = '0|Aucune boîte e-mails active';
			return;
		}
		$today = strtotime(wp_date('Y-m-d'));
		$hour = (int)wp_date('H');
				
		$time_start = time();
					
		self::$cron_state = sprintf('1|%d boîte(s) e-mails à traiter', count($mailboxes));
		
		$imported = false;
		$cron_period_min = 0;
		foreach($mailboxes as $mailbox){
			$cron_period = get_post_meta($mailbox->ID, 'cron-period', true);
			if( $cron_period_min > $cron_period )
				$cron_period_min = $cron_period;
		
			if( ! $simulate )
				$imported = self::synchronize($mailbox);
		
			if( ! $simulate )
				update_post_meta($mailbox->ID, 'cron-last', wp_date('Y-m-d H:i:s') . ( $forced ? ' (forcé)' : ''));
		}
		self::init_cron($cron_period_min * 60);
		
		if( is_array($imported) )
			self::$cron_state .= sprintf(' | Au final, %d e-mail(s) importé(s) en %s sec. %s'
				, count($imported)
				, time() - $time_start
				, $simulate ? ' << Simulation <<' : ''
			);
		elseif( $imported )
			self::$cron_state .= sprintf(' | La synchronisation indique : %s. %s'
				, print_r($imported, true)
				, $simulate ? ' << Simulation <<' : ''
			);

		if( ! $simulate){
			self::log_cron_state();
		}
		
		return true;
	}
	
	/**
	 * Retourne les paramètres de distribution des e-mails
	 */
	public static function get_emails_dispatch($mailbox_id){
		if( is_a($mailbox_id, 'WP_POST') )
			$mailbox_id = $mailbox_id->ID;
		$dispatch = [];
		
		$meta_name = 'imap_email';
		$mailbox_email = get_post_meta($mailbox_id, $meta_name, true);
		$mailbox_domain = trim(substr($mailbox_email, (int) strpos($mailbox_email, '@') + 1));
		
		$meta_name = 'emails_dispatch';
		$dispatch_data = get_post_meta($mailbox_id, $meta_name, true);
		if( $dispatch_data ){
			foreach( explode("\n", $dispatch_data) as $dispatch_raw ){
				$dispatch_raw = trim( $dispatch_raw, "\n\r ");
				if( strpos($dispatch_raw, '>') === false )
					continue;
				$dispatch_raw = explode('>', $dispatch_raw);
				$email = strtolower(trim($dispatch_raw[0]));
				if( strpos($email, '@' ) === false )
					$email .= '@' . $mailbox_domain; 
				$destination = trim($dispatch_raw[1]);
				if( strpos($destination, '.' ) !== false ){
					$destination = explode('.', $destination);
					$destination_id = $destination[1];
					$destination = $destination[0];
				}
				else
					$destination_id = false;
				
				$dispatch[ $email ] = [
					'type' => $destination,
					'id' => $destination_id
				];
			}
		}
		
		return $dispatch;
	}
	
	/**
	 * Retourne le répertoire de stockage des fichiers attachés aux messages
	 */
	public static function get_attachments_path($mailbox_id){
		$upload_dir = wp_upload_dir();
		
		$mailbox_dirname = str_replace('\\', '/', $upload_dir['basedir']);
		// if( is_multisite())
			// $mailbox_dirname .= '/sites/' . get_current_blog_id();
		
		$mailbox_dirname .= sprintf('/%s/%d/%d/%d/', self::post_type, $mailbox_id, date('Y'), date('m'));
		
		if ( ! file_exists( $mailbox_dirname ) ) {
			wp_mkdir_p( $mailbox_dirname );
		}

		return $mailbox_dirname;
	}
	
	
	
	/**
	 * Appelle la synchronisation IMAP.
	 */
	public static function synchronize($mailbox){
		debug_log( __CLASS__ . '::synchronize()');
		
		$mailbox = self::get_mailbox($mailbox);
		
		$time = get_post_meta($mailbox->ID, self::sync_time_meta_key, true);
		if( $time && $time >= strtotime('- 5 second'))
			return true;
		
		try {
			require_once( AGDP_PLUGIN_DIR . "/public/class.agendapartage-mailbox-imap.php");
			
			if( ! ($messages = AgendaPartage_Mailbox_IMAP::get_imap_messages($mailbox))){
				return $messages;
			}
			if( is_a($messages, 'WP_ERROR') )
				return $messages;
			
			$import_result = self::import_messages($mailbox, $messages);
		}
		catch(Exception $exception){
			return $exception;
		}
		
		update_post_meta($mailbox->ID, self::sync_time_meta_key, time());
		
		return $import_result;
	}
	
	/********************************************/
	/**
	 * Imports e-mails to comments or posts
	 */
	public static function import_messages($mailbox, $messages){
		$mailbox = self::get_mailbox($mailbox);
		if( ! $mailbox ){
			return false;
		}
		
		$imported = [];
		
		$imap_server = get_post_meta($mailbox->ID, 'imap_server', true);
		$imap_email = get_post_meta($mailbox->ID, 'imap_email', true);
		
		$dispatch = self::get_emails_dispatch($mailbox);
		debug_log('$dispatch', $dispatch);
		
		$forums = [];//cache
		
		add_filter('pre_comment_approved', array(__CLASS__, 'on_imap_pre_comment_approved'), 10, 2 );
		foreach( $messages as $message ){
			$email_to = false;
			foreach($message['to'] as $to)
				if( isset($dispatch[$to->email]) ){
					$email_to = strtolower($to->email);
					break;
				}
			if( ! $email_to ){
				debug_log(__CLASS__ . '.import_messages', sprintf("Un e-mail ne peut pas être importé. Il est destiné à une adresse non référencée : %s.", print_r($message['to'], true)));
				continue;
			}
			
			switch( $dispatch[$email_to]['type'] ){
				case 'agdpforum' :
					$forum_id = $dispatch[$email_to]['id'];
					if( ! $forum_id )
						throw new Exception(sprintf("La configuration de la boîte e-mails indique un forum sans préciser son identifiant : %s.", $email_to));
					if( isset($forums['' . $forum_id]) )
						$forum = $forums['' . $forum_id];
					elseif( ! ($forum = AgendaPartage_Forum::get_forum($forum_id)) )
						throw new Exception(sprintf("La configuration de la boîte e-mails indique un forum introuvable : %s > %s.%s.", $email_to, $dispatch[$email_to]['type'], $forum_id));
					else
						$forums['' . $forum_id] = $forum;
					$comment = self::import_message_to_comment( $mailbox, $message, $forum );
					if($comment)
						$imported[] = $comment;
					break;
				case 'agdpevent' :
					$post = self::import_message_to_post( $mailbox, $message, 'AgendaPartage_Evenement' );
					if($post)
						$imported[] = $post;
					break;
				case 'covoiturage' :
					$post = self::import_message_to_post( $mailbox, $message, 'AgendaPartage_Covoiturage' );
					if($post)
						$imported[] = $post;
					break;
				default:
					throw new Exception(sprintf("La configuration de la boîte e-mails indique un type inconnu : %s.", $dispatch[$email_to]['type']));
			}
		}
		remove_filter('pre_comment_approved', array(__CLASS__, 'on_imap_pre_comment_approved'), 10);
		
		return $imported;
	}
	
	/**
	 * Import as post
	 */	
	public static function import_message_to_post_type($mailbox, $message, $post_class){
		debug_log('import_message_to_post_type', $message, $post_class);
		$mailbox = self::get_mailbox($mailbox);
		if( ! $mailbox ){
			return false;
		}
		$post_type = $post_class::post_type;
		
		if( ($post = self::get_existing_post( $post_type, $message )) ){
		}
		else {
			$imap_server = get_post_meta($mailbox->ID, 'imap_server', true);
			$imap_email = get_post_meta($mailbox->ID, 'imap_email', true);
		
			$post_parent = false;
			$user_id = 0;
			// var_dump($message);
			$user_email = $message['reply_to'][0]->email;
			$user_name = $message['reply_to'][0]->name ? $message['reply_to'][0]->name : $user_email;
			if( ($pos = strpos($user_name, '@')) !== false)
				$user_name = substr( $user_name, 0, $pos);
			
			$dateTime = $message['date'];
			$date = wp_date('Y-m-d H:i:s', $dateTime->getTimestamp());
			$date_gmt = $dateTime->format('Y-m-d H:i:s');
			$postdata = [
				'post_type' => $post_type,
				'post_author' => $user_name,
				'post_author_url' => 'mailto:' . $user_email,
				'post_author_email' => $user_email,
				'post_content' => AgendaPartage_Mailbox_IMAP::get_imap_message_content($mailbox->ID, $message, $post_parent),
				'post_date' => $date,
				'post_date_gmt' => $date_gmt,
				'post_parent' => $post_parent,
				'post_agent' => $imap_email . '@' . $imap_server,
				'post_approved' => false,
				'user_id' => $user_id,
				'post_meta' => [
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
				
			// var_dump($postdata);
			$post = wp_new_post($postdata, true);
		}
		
		return $post;
	}
	
	/**
	 * Import as comment
	 */
	public static function import_message_to_comment($mailbox, $message, $forum){
		debug_log('import_message_to_comment', $message, 'forum '.$forum->ID);
		$mailbox = self::get_mailbox($mailbox);
		if( ! $mailbox ){
			return false;
		}
		
		if (!($page = AgendaPartage_Forum::get_page_of_forum( $forum ))){
			debug_log('AgendaPartage_Forum::get_page_of_forum() ne retourne aucune page', $forum);
			return false;
		}

		if( ($comment = self::get_existing_comment( $page, $message )) ){
		}
		else {
			$imap_server = get_post_meta($mailbox->ID, 'imap_server', true);
			$imap_email = get_post_meta($mailbox->ID, 'imap_email', true);
		
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
				'comment_content' => AgendaPartage_Mailbox_IMAP::get_imap_message_content($mailbox->ID, $message, $comment_parent),
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
		}
		
		return $comment;
	}
	
	
	/********************
	 * Posts
	 */
	//Cherche un message déjà importé
	private static function get_existing_post( $post_type, $message ){
	}
	
	/********************
	 * Comments
	 */
	//Force l'approbation du commentaire pendant la boucle d'importation
	public static function on_imap_pre_comment_approved($approved, $commentdata){
		if ( ! self::user_email_approved( $commentdata['comment_author_email'], $commentdata['forum_id'] ) )
			return false;
		return true;
	}
	
	private static function user_email_approved( $user_email, $mailbox_id ) {
		//L'origine du mail est l'adresse de la boite imap (bouclage)
		$source_email = get_post_meta($mailbox_id, 'imap_email', true);
		if( $user_email === $source_email )
			return false;
		//L'origine du mail est l'adresse d'envoi de newsletter de ce site
		$source_email = AgendaPartage_Newsletter::get_mail_sender();
		if( $user_email === $source_email )
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
}
?>