<?php

/**
 * AgendaPartage -> Mailbox
 * Custom post type for WordPress.
 * 
 * Définition du Post Type agdpmailbox
 *
 * Voir aussi AgendaPartage_Admin_Mailbox
 *
 * Une mailbox dispatche les mails vers des posts ou comments.
 * AgendaPartage_Forum traite les commentaires.
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
		//Interception des mails
		add_filter('wpcf7_before_send_mail', array(__CLASS__, 'wpcf7_before_send_mail'), 10, 3);
		
		add_action( self::cron_hook, array(__CLASS__, 'on_cron_exec') );
		
		self::init_cron(); //SIC : register_activation_hook( 'AgendaPartage_Mailbox', 'init_cron'); ne suffit pas
	}
	/*
	 **/
	
	
	
	/**
	 * Retourne la boîte e-mails associée à une page.
	 */
	public static function get_mailbox_of_page($page_id){
		$page = self::get_forum_page($page_id);
		$page_id = $page->ID;
		
		if($mailbox_id = get_post_meta( $page_id, AGDP_PAGE_META_MAILBOX, true))
			return self::get_mailbox($mailbox_id);
		return false;
	}
	
	/**
	 * Retourne la page associée.
	 */
	public static function get_forum_page($page_id){
		if( is_a($page_id, 'WP_Post') ){
			$page = $page_id;
			$page_id = $page->ID;
		}
		else {
			$page = get_post($page_id);
		}
		if( $page->post_type === AgendaPartage_Newsletter::post_type){
			if( $source = AgendaPartage_Newsletter::get_content_source($page_id, true)){
				if( $source[0] === 'page' ){
					return self::get_forum_page($source[1]);
				}
			}
			return false;
		}
		return $page;
	}
	
	/**
	 * Retourne l'adresse e-mail associée à une page.
	 */
	public static function get_page_email($page_id){
		$page = self::get_forum_page($page_id);
		$page_id = $page->ID;
		
		$mailbox = self::get_mailbox_of_page($page_id);
		foreach( self::get_emails_dispatch($mailbox) as $email => $destination )
			if( $destination['type'] == $page_id
			|| $destination['id'] == $page_id)
				return $email;
		return false;
	}
	
	/**
	 * Retourne le forum associé à une newsletter.
	 */
	public static function get_mailbox_of_newsletter($newsletter_id){
		if( is_a($newsletter_id, 'WP_Post') ){
			if($newsletter_id->post_type != AgendaPartage_Newsletter::post_type)
				return false;
			$newsletter_id = $newsletter_id->ID;
		}
		//TODO
		if( $source = AgendaPartage_Newsletter::get_content_source($newsletter_id, true)){
			if( $source[0] === self::post_type ){
				if( $mailbox = get_post( $source[1] )){
					return $mailbox;
				}
			}
		}
		return false;
	}
	
	/**
	 * Returns posts where post_status == $published_only ? 'publish' : * && meta['cron-enable'] == $cron_enable_only
	 */
	 public static function get_mailboxes( $published_only = true, $cron_enable_only = false){
		$posts = [];
		$query = [ 'post_type' => self::post_type ];
		if( $published_only )
			$query[ 'post_status' ] = 'publish';
		if( $cron_enable_only )
			$query = array_merge( $query, [
				'meta_key' => 'cron-enable'
				, 'meta_value' => '1'
				, 'meta_compare' => '='
			]);
		
		foreach( get_posts($query) as $post)
			$posts[$post->ID . ''] = $post;
		return $posts;
	}
	
	/**
	 * Returns posts where post_status == 'publish' && meta['cron-enable'] == true
	 */
	 public static function get_active_mailboxes(){
		return self::get_mailboxes( true, true );
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
	 * Teste la connexion IMAP.
	 */
	public static function check_connexion($mailbox = false){
		$mailbox = self::get_mailbox($mailbox);
		
		try {
			require_once( AGDP_PLUGIN_DIR . "/public/class.agendapartage-mailbox-imap.php");
			
			if( ! ($messages = AgendaPartage_Mailbox_IMAP::check_connexion($mailbox))){
				return $messages;
			}
			if( is_a($messages, 'Exception') )
				return $messages;
			
		}
		catch(Exception $exception){
			return $exception;
		}
		
		return true;
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
			$delay = $cron_time - time();
			return sprintf('Prochaine évaluation dans %s - %s'
					, date('H:i:s', $delay)	
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
		// debug_log('init_cron', $cron_time);
		if($next_time){
			// debug_log_callstack('init_cron next_time', $next_time);
			if( $cron_time !== false )
				wp_unschedule_event( $cron_time, self::cron_hook );
			if( $next_time < 1024 )
				$cron_time = strtotime( date('Y-m-d H:i:s') . ' + ' . $next_time . ' second');
			else
				$cron_time = $next_time;
			$result = wp_schedule_single_event( $cron_time, self::cron_hook, [], true );
			// debug_log('[agdpmailbox::init_cron] wp_schedule_single_event', date('H:i:s', $cron_time - time()));
		}
		if( $cron_time === false ){
			$cron_time = wp_schedule_event( time(), 'hourly', self::cron_hook );
			// debug_log('[agdpmailbox::init_cron] wp_schedule_event', $cron_time);
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
		// debug_log(__CLASS__.'::on_cron_exec');
		self::cron_exec(false);
	}
	
	/**
	 * A l'exécution du cron, cherche des destinataires pour ce jour
	 */
	public static function cron_exec($simulate = false, $mailbox = false){
		if( $mailbox )
			$mailboxes = [$mailbox];
		else
			$mailboxes = self::get_active_mailboxes();
		if( ! $mailboxes || count($mailboxes) === 0){
			self::deactivate_cron();
			self::$cron_state = '0|Aucune boîte e-mails active';
			return;
		}
				
		$time_start = time();
					
		self::$cron_state = sprintf('1|%d boîte(s) e-mails à traiter', count($mailboxes));
		
		$imported = false;
		$cron_period_min = 60;
		foreach($mailboxes as $mailbox){
			$cron_period = get_post_meta($mailbox->ID, 'cron-period', true);
			if( $cron_period_min > $cron_period )
				$cron_period_min = $cron_period;
		
			if( ! $simulate )
				$imported = self::synchronize($mailbox);
		
			if( ! $simulate ){
				update_post_meta($mailbox->ID, 'cron-last', wp_date('Y-m-d H:i:s'));
				// debug_log("cron_exec update_post_meta(".$mailbox->ID.", 'cron-last', ".wp_date('Y-m-d H:i:s') . ( $forced ? ' (forcé)' : ''));
			}
		}
		if( ! $simulate )
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
	public static function get_emails_dispatch( $mailbox_id = false, $destination_filter = false ){
		$dispatch = [];
		if( $mailbox_id === false ){
			foreach( self::get_mailboxes() as $mailbox )
				$dispatch = array_merge( $dispatch, self::get_emails_dispatch( $mailbox, $destination_filter) );
			return $dispatch;
		}
		if( is_a($mailbox_id, 'WP_POST') )
			$mailbox_id = $mailbox_id->ID;
		
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
				if( ! $email )
					$email .= $mailbox_email; 
				elseif( strpos($email, '@' ) === false )
					$email .= '@' . $mailbox_domain; 
				$destination = trim($dispatch_raw[1]);
				$rights = 'P';//Public par défaut
				if( strpos($destination, '|' ) !== false ){
					$destination = explode('|', $destination);
					$rights = strtoupper(trim($destination[1]));
					$destination = trim($destination[0]);
				}
				if( strpos($destination, '.' ) !== false ){
					$destination = explode('.', $destination);
					$destination_id = $destination[1];
					$destination = $destination[0];
				}
				else
					$destination_id = false;
				
				if( $destination_filter ){
					if( ! (  (is_int($destination_filter) && $destination_id == $destination_filter)
						  || (is_a($destination_filter, 'WP_POST') && $destination_id == $destination_filter->ID)
						  || ($destination_id === false && $destination === $destination_filter)
					))
						continue;
				}
				$dispatch[ $email ] = [
					'type' => $destination,
					'id' => $destination_id,
					'mailbox' => $mailbox_id,
					'rights' => $rights
				];
			}
		}
		
		return $dispatch;
	}
	
	/**
	 * Retourne les paramètres de distribution d'une page (forum / agdpevent / covoiturage)
	 */
	public static function get_page_dispatch( $mailbox_id = false, $page_id = false ){
		if( is_a($page_id, 'WP_POST') )
			$page_id = $page_id->ID;
		if( ($pages = self::get_pages_dispatch( $mailbox_id, $page_id ))
		&& isset($pages[$page_id.'']) )
			return $pages[$page_id.''];
		return [];
	}
	
	/**
	 * Retourne les paramètres de distribution par page (forum + agdpevent + covoiturage)
	 */
	public static function get_pages_dispatch( $mailbox_id = false, $page_id_only = false ){
		if( is_a($page_id_only, 'WP_POST') )
			$page_id_only = $page_id_only->ID;
		$pages = [];
		$all_dispatches = self::get_emails_dispatch($mailbox_id);
		// debug_log('get_pages_dispatch $all_dispatches', $all_dispatches);
		foreach( $all_dispatches as $email => $dispatch ){
			if($dispatch['type'] === 'page')
				$page_id = $dispatch['id'].'';
			elseif( $dispatch['type'] === AgendaPartage_Evenement::post_type ){
				$page_id = AgendaPartage::get_option('agenda_page_id');
			}
			elseif( $dispatch['type'] === AgendaPartage_Covoiturage::post_type ){
				$page_id = AgendaPartage::get_option('covoiturages_page_id');
			}
			else
				continue;
			if( $page_id_only &&
			$page_id_only != $page_id)
				continue;
				
			$dispatch['email'] = $email;
			if( ! isset($pages[$page_id]) )
				$pages[$page_id] = [ $dispatch ];
			else
				$pages[$page_id][] = $dispatch;
		}
		return $pages;
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
		// debug_log( __CLASS__ . '::synchronize()');
		
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
		
		$posts = [];//cache
		
		add_filter('pre_comment_approved', array(__CLASS__, 'on_imap_pre_comment_approved'), 10, 2 );
		foreach( $messages as $message ){
			$email_to = false;
			foreach($message['to'] as $to)
				if( isset($dispatch[$to->email]) ){
					$email_to = strtolower($to->email);
					break;
				}
			if( ! $email_to )
				if( isset($dispatch['*@*']) )
					$email_to = '*@*';
				else {
					debug_log(__CLASS__ . '.import_messages', sprintf("Un e-mail ne peut pas être importé. Il est destiné à une adresse non référencée : %s.", print_r($message['to'], true)));
					continue;
				}
			
			switch( $dispatch[$email_to]['type'] ){
				case 'page' :
					$page_id = $dispatch[$email_to]['id'];
					if( ! $page_id )
						throw new Exception(sprintf("La configuration de la boîte e-mails indique une page sans préciser son identifiant : %s.", $email_to));
					if( isset($posts['' . $page_id]) )
						$page = $posts['' . $page_id];
					elseif( ! ($page = get_post($page_id)) )
						throw new Exception(sprintf("La configuration de la boîte e-mails indique une page introuvable : %s > %s.%s.", $email_to, $dispatch[$email_to]['type'], $page_id));
					else
						$posts['' . $page_id] = $page;
					$comment = self::import_message_to_comment( $mailbox, $message, $page );
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
			return false;
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
			if( ! isset($message['reply_to']) || ! $message['reply_to'] )
				$message['reply_to'] = [ $message['from'] ];
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
					'to' => $message['to'][0]->email,
					'title' => trim($message['subject']),
					'attachments' => $message['attachments'],
					'import_date' => wp_date(DATE_ATOM),
					'mailbox_id' => $mailbox->ID,
				]
			];
				
			// var_dump($postdata);
			$post = wp_new_post($postdata, true);
		}
		
		return $post;
	}
	
	/**
	 * Import as comment
	 */
	public static function import_message_to_comment($mailbox, $message, $page){
		// debug_log('import_message_to_comment', $message, 'page '.$page->ID);
		$mailbox = self::get_mailbox($mailbox);
		if( ! $mailbox ){
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
			if( ! isset($message['reply_to']) || ! $message['reply_to'] )
				$message['reply_to'] = [ $message['from'] ];
			
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
					'to' => $message['to'][0]->email,
					'title' => trim($message['subject']),
					'attachments' => $message['attachments'],
					'import_date' => wp_date(DATE_ATOM),
					'mailbox_id' => $mailbox->ID,
				]
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
		if ( ! self::user_email_approved( $commentdata['comment_author_email'], $commentdata['comment_meta']['mailbox_id'], $commentdata['comment_meta']['to'] ) )
			return false;
		return true;
	}
	
	private static function user_email_approved( $user_email, $mailbox_id, $email_to ) {
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
	
	/***************************/
	/******** Droits ***********/
	
	/**
	 * Retourne tous les droits
	 */
	public static function get_all_rights( ){
		return [
			'P',
			'E',
			'C',
			'A',
			'CO',
			'AO',
		];
	}
	/**
	 * Retourne tous les libellés des droits
	 */
	public static function get_all_rights_labels( ){
		$all_rights = [];
		foreach( self::get_all_rights() as $right)
			$all_rights[$right] = self::get_right_label( $right );
		return $all_rights;
	}
	/**
	 * Retourne le libellé des droits
	 */
	public static function get_right_label( $right ){
		switch( $right ){
			case 'P' : 
				return 'Public';
			case 'E' : 
				return 'Validation par e-mail';
			case 'C' : 
				return 'Connexion requise';
			case 'CO' : 
				return 'Inscription cooptée et connexion requise';
			case 'A' : 
				return 'Adhésion requise';
			case 'AO' : 
				return 'Adhésion cooptée requise';
		}
		return '';
	}
	
	
	/*
	 * wpcf7_skip_mail callback 
	 * Les emails sortant à destination d'une adresse de mailbox sont interceptés
	 */
	public static function wpcf7_before_send_mail ($contact_form, &$abort, $submission){ 
		if($abort)
			return;
		if( isset($_POST['_wpcf7_container_post']) ){
			$page = get_post($_POST['_wpcf7_container_post']);
			$meta_key = AGDP_PAGE_META_MAILBOX;
			if( $mailbox_id = get_post_meta( $page->ID, $meta_key, true)){
				if( $dispatch = self::get_page_dispatch( false, $page->ID) ){
					
					$properties = $contact_form->get_properties();
					$posted_data = $submission->get_posted_data();
					$mail_properties = $properties['mail'];
					$email_to = strtolower($mail_properties['recipient']);
					
					if( isset($posted_data['is-public'])
					&& $posted_data['is-public'] ){
						if( is_array($posted_data['is-public']) )
							$posted_data['is-public'] = $posted_data['is-public'][0];
						$posted_data['is-public'] = strtolower($posted_data['is-public']) !== 'non';
						debug_log('is-public', $posted_data['is-public']);
					}
					$emails = self::get_emails_dispatch();
					if( ! isset($emails[$email_to]) )
						return;
					// debug_log('$emails[$email]', $emails[$email_to]);
					
					$email_replyto = wpcf7_mail_replace_tags(strtolower($mail_properties['additional_headers']));
					$email_replyto = preg_replace('/^[\s\S]*reply-to\s*:\s*([a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4})[\s\S]*$/', '$1', $email_replyto);
					// debug_log('$email_replyto', $email_replyto);
					
					$subject = wpcf7_mail_replace_tags($mail_properties['subject'], $mail_properties);
					$body = wpcf7_mail_replace_tags($mail_properties['body'], $mail_properties);
					// debug_log('wpcf7_before_send_mail', $subject, $body);
					
					if( $user_id = email_exists($email_replyto) ){
						$user = new WP_User($user_id);
						$user_name = $user->slug;
					}
					else {
						$user_name = preg_replace('/^(.*)@.*$/', '$1', $email_replyto);
					}
					
					$date = wp_date('Y-m-d H:i:s');
					$date_gmt = date('Y-m-d H:i:s');
					$commentdata = [
						'comment_post_ID' => $page->ID,
						'comment_author' => $user_name,
						'comment_author_url' => 'mailto:' . $email_replyto,
						'comment_author_email' => $email_replyto,
						'comment_content' => $body,
						'comment_date' => $date,
						'comment_date_gmt' => $date_gmt,
						'comment_parent' => false,
						'comment_agent' => sprintf('wpcf7.%s@page.%d', $contact_form->id(), $page->ID),
						'comment_approved' => true,
						'user_id' => $user_id,
						'comment_meta' => [
							'source' => 'wpcf7',
							'from' => $email_replyto,
							'to' => $email_to,
							'title' => trim($subject),
							'mailbox_id' => $mailbox_id,
							'posted_data' => $posted_data
						]
					];
						
					$comment = wp_new_comment($commentdata, true);
					if(	is_wp_error( $comment ) )
						debug_log('wpcf7_before_send_mail', $comment);
					
					add_filter('wpcf7_skip_mail', array(__CLASS__, 'wpcf7_skip_mail_forced'), 10, 2);
				}
			}
		}
	} 
	
	/*
	 * wpcf7_skip_mail callback 
	 * Skip forced
	 */
	public static function wpcf7_skip_mail_forced( $skip_mail, $contact_form ){ 
		return true;
	}
	
}
?>