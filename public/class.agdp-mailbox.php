<?php

/**
 * AgendaPartage -> Mailbox
 * Custom post type for WordPress.
 * 
 * Définition du Post Type agdpmailbox
 *
 * Voir aussi Agdp_Admin_Mailbox
 *
 * Une mailbox dispatche les mails vers des posts ou comments.
 * Agdp_Forum traite les commentaires.
 */
class Agdp_Mailbox {

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
		
			self::init_cron(); //SIC : register_activation_hook( 'Agdp_Mailbox', 'init_cron'); ne suffit pas. Pblm de multisites ?
		}
	}

	/**
	 * Hook
	 */
	public static function init_hooks() {
		//Interception des mails
		add_filter('wpcf7_before_send_mail', array(__CLASS__, 'wpcf7_before_send_mail'), 10, 3);
		
		add_action( self::cron_hook, array(__CLASS__, 'on_cron_exec') );
	}
	/*
	 **/
	
	/**
	 * Retourne la boîte e-mails associée à une page.
	 */
	public static function get_mailbox_of_page($page_id){
		if( ! ($page = self::get_forum_page($page_id)) )
			return false;
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
		elseif( $page_id === Agdp_Evenement::post_type )
			return get_post( Agdp::get_option('agenda_page_id') );
		elseif( $page_id === Agdp_Covoiturage::post_type )
			return get_post( Agdp::get_option('covoiturages_page_id') );
		else {
			$page = get_post($page_id);
		}
		if( $page->post_type === Agdp_Newsletter::post_type){
			if( $source = Agdp_Newsletter::get_content_source($page_id, true)){
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
	 * Returns posts where post_status == $published_only ? 'publish' : * && meta['cron-enable'] == $cron_enable_only
	 */
	 public static function get_mailboxes( $published_only = true, $cron_enable_only = false){
		$posts = [];
		$query = [ 'post_type' => self::post_type ];
		if( $published_only )
			$query[ 'post_status' ] = 'publish';
		else
			$query[ 'post_status' ] = ['publish', 'pending', 'draft'];
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
	 * Retourne si la mailbox est marquée comme suspendue.
	 */
	public static function is_suspended($mailbox_id = false){
		if( is_a($mailbox_id, 'WP_Post') )
			$mailbox_id = $mailbox_id->ID;
		elseif( ! $mailbox_id )
			if( $mailbox = self::get_mailbox($mailbox_id) )
				$mailbox_id = $mailbox->ID;
		if( $mailbox_id ){
			$meta_key = 'imap_suspend';
			return get_post_meta($mailbox_id, $meta_key, true);
		}
		return null;
	}
	
	/**
	 * Teste la connexion IMAP.
	 */
	public static function check_connexion($mailbox = false){
		$mailbox = self::get_mailbox($mailbox);
		
		try {
			require_once( AGDP_PLUGIN_DIR . "/public/class.agdp-mailbox-imap.php");
			
			if( ! ($messages = Agdp_Mailbox_IMAP::check_connexion($mailbox))){
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
			$next_time = strtotime( date('Y-m-d H:i:s') . ' + 1 Hour');
			$cron_time = wp_schedule_event( $next_time, 'hourly', self::cron_hook );
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
		debug_log( sprintf('[blog %d]%s::%s', get_current_blog_id(), __CLASS__, __FUNCTION__ ));
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
			self::log_cron_state();
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
	 * Retourne les paramètres de distribution des e-mails.
	 * $destination_filter est une page de forum
	 */
	public static function get_emails_dispatch( $mailbox_id = false, $destination_filter = false ){
		if( is_a($mailbox_id, 'WP_POST') )
			$mailbox_id = $mailbox_id->ID;
		if( is_a($destination_filter, 'WP_POST') )
			$destination_filter = $destination_filter->ID;
		
	    global $wpdb;
		$sql = 
			'SELECT page.ID as page_id, page.post_title as page_title
				, mailbox.ID AS mailbox_id, mailbox.post_title AS mailbox_title, mailbox_email.meta_value AS mailbox_email
				, email.meta_value AS email, rights.meta_value AS rights, moderate.meta_value AS moderate 
			FROM ' . $wpdb->posts . ' AS page
			INNER JOIN '.$wpdb->postmeta. ' AS mailbox_id
				ON page.ID = mailbox_id.post_id 
				AND mailbox_id.meta_key = "' . AGDP_PAGE_META_MAILBOX . '"
			INNER JOIN '.$wpdb->posts. ' AS mailbox
				ON mailbox.ID = mailbox_id.meta_value 
			INNER JOIN '.$wpdb->postmeta. ' AS mailbox_email
				ON mailbox.ID = mailbox_email.post_id
				AND mailbox_email.meta_key = "imap_email"
			LEFT JOIN '.$wpdb->postmeta. ' AS email
				ON page.ID = email.post_id
				AND email.meta_key = "forum_email"
			LEFT JOIN '.$wpdb->postmeta. ' AS rights
				ON page.ID = rights.post_id
				AND rights.meta_key = "forum_rights"
			LEFT JOIN '.$wpdb->postmeta. ' AS moderate
				ON page.ID = moderate.post_id
				AND moderate.meta_key = "forum_moderate"
			WHERE page.post_status != "trash"
		';
		if( $mailbox_id !== false ){
			$sql .= '
				AND mailbox.ID = ' . $mailbox_id . '
			';
		}
		if( $destination_filter ){
			$sql .= '
				AND page.ID  = ' . $destination_filter . '
			';
		}
		$sql .= '
			ORDER BY page_id, mailbox_id
		';
		
		$post_type_pages = [
			''.Agdp::get_option('agenda_page_id') => Agdp_Evenement::post_type,
			''.Agdp::get_option('covoiturages_page_id') => Agdp_Covoiturage::post_type,
		];
		$dispatches = [];
		foreach( $wpdb->get_results( $sql ) as $dbrow )	{
			$email = $dbrow->email;
			$mailbox_domain = explode('@', $dbrow->mailbox_email)[1];
			if( strpos($email, '@') === false )
				$email .= '@' . $mailbox_domain;
			elseif( strpos($email, '@*') !== false )
				$email = str_replace('@*', '@' . $mailbox_domain );
			if( ! ($rights = $dbrow->rights) )
				$rights = 'P';
			if( $dbrow->moderate === '0')
				$dbrow->moderate = false;
			
			$page_id = $dbrow->page_id;
			if( isset( $post_type_pages[''.$page_id]) )
				$type = $post_type_pages[''.$page_id];
			else
				$type = 'page';
			
			$dispatches[ $email ] = [
				'email' => $email,
				'type' => $type,
				'id' => $page_id,
				'page_title' => $dbrow->page_title,
				'mailbox' => $dbrow->mailbox_id,
				'mailbox_title' => $dbrow->mailbox_title,
				'mailbox_email' => $dbrow->mailbox_email,
				'mailbox_domain' => $mailbox_domain,
				'rights' => $rights,
				'moderate' => $dbrow->moderate
			];
		}
		if( ! $destination_filter )
			$dispatches[ $email = '*' ] = [
				'email' => $email,
				'type' => 'page',
				'id' => Agdp::get_option('forums_parent_id'),
				'page_title' => false,
				'mailbox' => false,
				'mailbox_title' => false,
				'mailbox_email' => false,
				'mailbox_domain' => false,
				'rights' => 'M',
				'moderate' => true
			];
		
		// debug_log('get_emails_dispatch', $sql, $dispatches );
		
		return $dispatches;
	}
	
	/**
	 * Retourne les droits d'un forum
	 */
	public static function get_page_rights( $page_id = false, $mailbox_id = false ){
		if( $page_dispatch = self::get_page_dispatch( $page_id, $mailbox_id )){
			return $page_dispatch[0]['rights'];
		}
		return [];
	}
	
	/**
	 * Retourne les paramètres de distribution d'une page (forum / agdpevent / covoiturage)
	 */
	public static function get_page_dispatch( $page_id = false, $mailbox_id = false ){
		if( is_a($page_id, 'WP_POST') )
			$page_id = $page_id->ID;
		if( ($pages = self::get_pages_dispatches( $mailbox_id, $page_id ))
		&& isset($pages[$page_id.'']) )
			return $pages[$page_id.''];
		return [];
	}
	
	/**
	 * Retourne les paramètres de distribution par page (forum + agdpevent + covoiturage)
	 */
	public static function get_pages_dispatches( $mailbox_id = false, $page_id_only = false ){
		if( is_a($page_id_only, 'WP_POST') )
			$page_id_only = $page_id_only->ID;
		$pages = [];
		$all_dispatches = self::get_emails_dispatch($mailbox_id, $page_id_only);
		// debug_log('get_pages_dispatches $all_dispatches', $all_dispatches);
		foreach( $all_dispatches as $email => $dispatch ){
			if($dispatch['type'] === 'page')
				$page_id = $dispatch['id'].'';
			//TODO
			elseif( $dispatch['type'] === Agdp_Evenement::post_type ){
				$page_id = Agdp::get_option('agenda_page_id');
			}
			elseif( $dispatch['type'] === Agdp_Covoiturage::post_type ){
				$page_id = Agdp::get_option('covoiturages_page_id');
			}
			else {
				debug_log(__CLASS__ . '::get_pages_dispatches'
					, sprintf('Erreur de configuration de la mailbox %s, le type "%s" est inconnu.'
						, is_a($mailbox_id, 'WP_Post') ? $mailbox_id->post_title : $mailbox_id
						, $dispatch['type']));
				continue;
			}
			if( $page_id_only &&
			$page_id_only != $page_id)
				continue;
				
			$dispatch['email'] = $email;
			if( ! isset($pages[$page_id.'']) )
				$pages[$page_id.''] = [ $dispatch ];
			else
				$pages[$page_id.''][] = $dispatch;
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
		
		if( self::is_suspended( $mailbox ) ){
			// debug_log( __CLASS__ . '::' . __FUNCTION__ . '(' . $mailbox->post_title . ') is_suspended');
			return false;
		}
		
		try {
			if( ! class_exists('Agdp_Mailbox_IMAP') )
				require_once( AGDP_PLUGIN_DIR . "/public/class.agdp-mailbox-imap.php");
			
			if( ! ($messages = Agdp_Mailbox_IMAP::get_imap_messages($mailbox))){
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
		
		$pages = [];//cache
		
		add_filter('pre_comment_approved', array(__CLASS__, 'on_import_pre_comment_approved'), 10, 2 );
		add_filter('comment_flood_filter', '__return_false');
		foreach( $messages as $message ){
			$email_to = false;
			foreach($message['to'] as $to)
				if( isset($dispatch[$to->email]) ){
					$email_to = strtolower($to->email);
					break;
				}
			if( ! $email_to )
				if( isset($dispatch['*']) )
					$email_to = '*';
				else {
					debug_log(__CLASS__ . '.import_messages', sprintf("Un e-mail ne peut pas être importé. Il est destiné à une adresse non référencée : %s.", print_r($message['to'], true)));
					continue;
				}

			switch( $dispatch[$email_to]['type'] ){
				case 'page' :
					$page_id = $dispatch[$email_to]['id'];
					if( ! $page_id )
						throw new Exception(sprintf("La configuration de la boîte e-mails indique une page sans préciser son identifiant : %s.", $email_to));
					if( isset($pages['' . $page_id]) )
						$page = $pages['' . $page_id];
					elseif( ! ($page = get_post($page_id)) )
						throw new Exception(sprintf("La configuration de la boîte e-mails indique une page introuvable : %s > %s.%s.", $email_to, $dispatch[$email_to]['type'], $page_id));
					else
						$pages['' . $page_id] = $page;
					$comment = self::import_message_to_comment( $mailbox, $message, $page );
					if($comment && ! is_wp_error($comment))
						$imported[] = $comment;
					break;
				case Agdp_Evenement::post_type :
					$post = self::import_message_to_post_type( $mailbox, $message, $dispatch[$email_to]['type'] );
					if($post && ! is_wp_error($post))
						$imported[] = $post;
					break;
				case Agdp_Covoiturage::post_type :
					$post = self::import_message_to_post_type( $mailbox, $message, $dispatch[$email_to]['type'] );
					if($post && ! is_wp_error($post))
						$imported[] = $post;
					break;
				default:
					throw new Exception(sprintf("La configuration de la boîte e-mails indique un type inconnu : %s.", $dispatch[$email_to]['type']));
			}
		}
		remove_filter('pre_comment_approved', array(__CLASS__, 'on_import_pre_comment_approved'), 10);
		
		return $imported;
	}
	
	/**
	 * Import as post
	 */	
	public static function import_message_to_post_type($mailbox, $message, $post_type){

		$mailbox = self::get_mailbox($mailbox);
		if( ! $mailbox ){
			return false;
		}
		
		$imap_server = get_post_meta($mailbox->ID, 'imap_server', true);
		$imap_email = get_post_meta($mailbox->ID, 'imap_email', true);
		$post_parent = false;
		$user_id = 0;
		// var_dump($message);
		if( ! isset($message['reply_to']) || ! $message['reply_to'] )
			$message['reply_to'] = [ $message['from'] ];
		$user_email = strtolower($message['reply_to'][0]->email);
		$user_name = $message['reply_to'][0]->name ? $message['reply_to'][0]->name : $user_email;
		if( ($pos = strpos($user_name, '@')) !== false)
			$user_name = substr( $user_name, 0, $pos);
		
		$dateTime = $message['date'];
		$email_date = wp_date('Y-m-d H:i:s', $dateTime->getTimestamp());
		
		$post_source = 'imap';
		
		$meta_input = [
			'source' => $post_source,
			'source_server' => $imap_server,
			'source_email' => $imap_email,
			'source_id' => $message['id'],
			'source_no' => $message['msgno'],
			'from' => strtolower($message['from']->email),
			'to' => strtolower($message['to'][0]->email),
			'title' => trim($message['subject']),
			'attachments' => $message['attachments'],
			'send_date' => $email_date,
			'mailbox_id' => $mailbox->ID,
		];
		
		$page = Agdp_Mailbox::get_forum_page($post_type);
		$post_status = Agdp_Forum::get_forum_post_status( $page, false, $user_email, $post_source );
		
		$data = [
			'post_status' => $post_status,
			'meta_input' => $meta_input,
		];
		
		//TODO attention aux boucles infinies
		if( ! empty($message['attachments']) ){					
			foreach($message['attachments'] as $attachment){
				if( '.ics' === substr($attachment, -4) ){
					$posts = Agdp_Post::import_post_type_ics($post_type, $attachment, $data);
					return $posts;
				}
			}
		}
		return false;
		
		//TODO
		if( ($post = self::get_existing_post( $post_type, $message )) ){
		}
		else {
			$imap_server = get_post_meta($mailbox->ID, 'imap_server', true);
			$imap_email = get_post_meta($mailbox->ID, 'imap_email', true);
		
			$post_parent = false;
			// var_dump($message);
			if( ! isset($message['reply_to']) || ! $message['reply_to'] )
				$message['reply_to'] = [ $message['from'] ];
			$user_email = strtolower($message['reply_to'][0]->email);
			$user_name = $message['reply_to'][0]->name ? $message['reply_to'][0]->name : $user_email;
			if( ($pos = strpos($user_name, '@')) !== false)
				$user_name = substr( $user_name, 0, $pos);
			
			if( $user_id = email_exists($user_email) ){
				if( is_multisite() ){
					$blogs = get_blogs_of_user($user_id, false);
					if( ! isset( $blogs[ get_current_blog_id() ] ) )
						$user_id = 0;
				}
			}
			
			$dateTime = $message['date'];
			$email_date = wp_date('Y-m-d H:i:s', $dateTime->getTimestamp());
			$date = wp_date('Y-m-d H:i:s');
			$date_gmt = date('Y-m-d H:i:s');
			$postdata = [
				'post_type' => $post_type,
				'post_author' => $user_name,
				'post_author_url' => 'mailto:' . $user_email,
				'post_author_email' => $user_email,
				'post_content' => Agdp_Mailbox_IMAP::get_imap_message_content($mailbox->ID, $message, $post_parent),
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
					'from' => strtolower($message['from']->email),
					'to' => strtolower($message['to'][0]->email),
					'title' => trim($message['subject']),
					'attachments' => $message['attachments'],
					'send_date' => $email_date,
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
			// debug_log('import_message_to_comment  ! $mailbox');
			return false;
		}

		if( ($comment = self::get_existing_comment( $page, $message )) ){
			// debug_log('import_message_to_comment get_existing_comment');
		}
		else {
			$imap_server = get_post_meta($mailbox->ID, 'imap_server', true);
			$imap_email = get_post_meta($mailbox->ID, 'imap_email', true);
		
			$comment_parent = self::find_comment_parent( $page, $message );
			$user_id = 0;
			// var_dump($message);
			if( ! isset($message['reply_to']) || ! $message['reply_to'] )
				$message['reply_to'] = [ $message['from'] ];
			
			$user_email = strtolower($message['reply_to'][0]->email);
			$user_name = $message['reply_to'][0]->name ? $message['reply_to'][0]->name : $user_email;
			
			if( ($pos = strpos($user_name, '@')) !== false)
				$user_name = substr( $user_name, 0, $pos);
			
			$dateTime = $message['date'];
			$email_date = wp_date('Y-m-d H:i:s', $dateTime->getTimestamp());
			//On conserve en date de référence la date d'importation (plus logique pour le traitement des newsletters)
			$date = wp_date('Y-m-d H:i:s');
			$date_gmt = date('Y-m-d H:i:s');
			$commentdata = [
				'comment_post_ID' => $page->ID,
				'comment_author' => $user_name,
				'comment_author_url' => 'mailto:' . $user_email,
				'comment_author_email' => $user_email,
				'comment_content' => Agdp_Mailbox_IMAP::get_imap_message_content($mailbox->ID, $message, $comment_parent),
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
					'from' => strtolower($message['from']->email),
					'to' => strtolower($message['to'][0]->email),
					'title' => trim($message['subject']),
					'attachments' => $message['attachments'],
					'send_date' => $email_date,
					'mailbox_id' => $mailbox->ID,
				]
			];
				
			// var_dump($commentdata);
			$comment = wp_new_comment($commentdata, true);
			if( is_wp_error($comment) ){
				if( ! in_array('comment_duplicate', $comment->get_error_codes())
				 || get_post_meta($mailbox->ID, 'imap_mark_as_read', true))
				debug_log('import_message_to_comment !wp_new_comment : ', $comment);
				if( is_admin()
				&& class_exists('Agdp_Admin'))
					Agdp_Admin::add_admin_notice(__CLASS__.'::import_message_to_comment(). wp_new_comment returns ',
						in_array('comment_duplicate', $comment->get_error_codes()) 
							? sprintf('Duplication %s : "%s"', $user_email, $commentdata['comment_meta']['title'])
							: print_r($comment, true));
			}
			
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
	/**
	 * Force l'approbation du commentaire pendant la boucle d'importation
	 */
	public static function on_import_pre_comment_approved($approved, $commentdata){
		$meta_key = 'forum_moderate';
		if( get_post_meta($commentdata['comment_post_ID'], $meta_key, true) )//TODO cache
			return 0;
		$user_email_approved = self::user_email_approved( $commentdata['comment_author_email'], $commentdata['comment_meta']['mailbox_id'], $commentdata['comment_meta']['to'] );
		if ( ! $user_email_approved )
			return 0;
		return $user_email_approved;
	}
	/**
	 *
	 * $approved :
	 * 0 (int) comment is marked for moderation as "Pending"
	 * 1 (int) comment is marked for immediate publication as "Approved"
	 * 'spam' (string) comment is marked as "Spam"
	 * 'trash' (string) comment is to be put in the Trash
	 */
	private static function user_email_approved( $user_email, $mailbox_id, $email_to ) {
		// debug_log('user_email_approved', "user_email $user_email", "mailbox_id $mailbox_id");
		
		//L'origine du mail est l'adresse de la boite imap (bouclage)
		$source_email = get_post_meta($mailbox_id, 'imap_email', true);
		if( $user_email === $source_email )
			return false;
		//L'origine du mail est l'adresse d'envoi de newsletter de ce site
		$source_email = Agdp_Newsletter::get_mail_sender();
		if( $user_email === $source_email )
			return false;
		
		// debug_log('user_email_approved', "email_to $email_to");
		
		if( ! ($dispatches = self::get_emails_dispatch($mailbox_id))
		|| ! isset($dispatches[$email_to]))
			return true;
		$dispatch = $dispatches[$email_to];
		// debug_log('user_email_approved', "dispatch ", $dispatch);
		if( $dispatch['type'] === 'page' )
			$page = get_post($page_id = $dispatch['id']);
		else
			$page = $page_id = $dispatch['type'];
		
		$user_subscription = Agdp_Forum::get_subscription($user_email, $page_id);
		if( $user = email_exists($user_email) )
			$user = new WP_User($user);
		
		$comment_approved = Agdp_Forum::get_forum_comment_approved($page, $user, $user_email);
		
		// debug_log('user_email_approved', "page_id $page_id", "user_subscription", $user_subscription, "user " . ($user ? $user->name : 'NON'), "comment_approved $comment_approved");
		
		return $comment_approved;
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
		
	/*
	 * wpcf7_skip_mail callback 
	 * Les emails sortant à destination d'une adresse de forum sont interceptés
	 */
	public static function wpcf7_before_send_mail ($contact_form, &$abort, $submission){ 
		// debug_log(__CLASS__.'::wpcf7_before_send_mail', $abort, isset($_POST['_wpcf7_container_post']) ? $_POST['_wpcf7_container_post'] : ' ! _wpcf7_container_post');
		if($abort)
			return;
		
		if( isset($_POST['_wpcf7_container_post']) ){
			if( ! ($post = get_post($_POST['_wpcf7_container_post']) ) )
				return;
			
			switch($post->post_type){
				case Agdp_Evenement::post_type:
				case Agdp_Covoiturage::post_type:
					//TODO
		
					// $properties = $contact_form->get_properties();
					// $posted_data = $submission->get_posted_data();
					// $mail_properties = $properties['mail'];
					// $email_to = strtolower($mail_properties['recipient']);
					
					// if( ($forums = Agdp_Forum::get_forums_of_email ($email_to))
					// && count($forums) )
						// return self::import_wpcf7_to_post_type($mailbox_id, $post, $post->post_type);
					
					break;
				case Agdp_Forum::post_type:
					$meta_key = AGDP_PAGE_META_MAILBOX;
					if( $mailbox_id = get_post_meta( $post->ID, $meta_key, true)){
						if( $dispatch = self::get_page_dispatch( $post->ID ) ){
							return self::import_wpcf7_to_comment($contact_form, $abort, $submission, $mailbox_id, $dispatch, $post);
						}
					}
					break;
			}
		
		}
	} 
	/*
	 * Les emails sortant à destination d'une adresse de mailbox sont interceptés
	 */
	public static function import_wpcf7_to_comment($contact_form, &$abort, $submission, $mailbox_id, $dispatch, $page){
		debug_log(__CLASS__.'::import_wpcf7_to_comment');
		
		$properties = $contact_form->get_properties();
		$posted_data = $submission->get_posted_data();
		$mail_properties = $properties['mail'];
		$email_to = strtolower($mail_properties['recipient']);
		
		if( isset($posted_data['is-public'])
		&& $posted_data['is-public'] ){
			if( is_array($posted_data['is-public']) )
				$posted_data['is-public'] = $posted_data['is-public'][0];
			$posted_data['is-public'] = strtolower($posted_data['is-public']) !== 'non';
		}
		$emails = self::get_emails_dispatch();
		if( ! isset($emails[$email_to]) )
			return;
		// debug_log('$emails[$email]', $emails[$email_to]);
		
		//$mail_properties['additional_headers'] de la forme Reply-To: "[abonne-nom]"<[abonne-email]>
		$email_replyto = wpcf7_mail_replace_tags(strtolower($mail_properties['additional_headers']));
		$matches = [];
		preg_match_all('/^[\s\S]*reply-to\s*:\s*("(.*)"\s*)?\<?([a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4})\>?[\s\S]*$/', $email_replyto, $matches);
		//$email_replyto = preg_replace('/^[\s\S]*reply-to\s*:\s*([a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4})[\s\S]*$/', '$1', $email_replyto);
		$user_name = $matches[2][0];
		$email_replyto = $matches[3][0];
		$subject = wpcf7_mail_replace_tags($mail_properties['subject'], $mail_properties);
		$body = wpcf7_mail_replace_tags($mail_properties['body'], $mail_properties);
		// debug_log('wpcf7_before_send_mail', $subject, $body);
		
		if( $user_id = email_exists($email_replyto) ){
			$user = new WP_User($user_id);
			if( ! $user_name)
				$user_name = $user->slug;
		}
		elseif( ! $user_name ) {
			$user_name = preg_replace('/^(.*)@.*$/', '$1', $email_replyto);
		}
		
		$comment_approved = true;
		
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
			'comment_approved' => $comment_approved,
			'user_id' => $user_id,
			'comment_meta' => [
				'source' => 'wpcf7',
				'from' => $email_replyto,
				'to' => $email_to,
				'title' => trim($subject),
				'mailbox_id' => $mailbox_id
			]
		];
		foreach( $posted_data as $key=>$value )
			$commentdata['comment_meta']['posted_data_' . $key] = $value;
			
		add_filter('pre_comment_approved', array(__CLASS__, 'on_import_pre_comment_approved'), 10, 2 );
		$comment = wp_new_comment($commentdata, true);
		if(	is_wp_error( $comment ) ){
			$message = 'Erreur : ' . $comment->get_error_message();
			$submission->set_response($message);
			
			$abort = true;
		}
		else {					
			//$html = wp_list_comments(['echo'=>false], [ get_comment($comment) ]);
			$messages = ($contact_form->get_properties())['messages'];
			$messages['mail_sent_ok'] = str_replace("\t", '', str_replace("\n", '', 
				sprintf('js:(function( id ){
					show_new_comment( id );
					return "%s";
				})(%d)'
				, str_replace('"', '\\"', $messages['mail_sent_ok'])
				, $comment
			)));
			$contact_form->set_properties(array('messages' => $messages));
		}

		add_filter('wpcf7_skip_mail', array(__CLASS__, 'wpcf7_skip_mail_forced'), 10, 2);
	} 
	
	/*
	 * wpcf7_skip_mail callback 
	 * Skip forced
	 */
	public static function wpcf7_skip_mail_forced( $skip_mail, $contact_form ){ 
		remove_filter('wpcf7_skip_mail', array(__CLASS__, 'wpcf7_skip_mail_forced'), 10, 2);
		return true;
	}
	
	/**
	 * Retourne l'analyse du forum
	 */
	public static function get_diagram( $blog_diagram, $mailbox ){
		$mailbox_id = $mailbox->ID;
		$diagram = [ 
			'mailbox' => $mailbox, 
		];
		
		//post_status
		$diagram['post_status'] = $mailbox->post_status;
		
		//imap
		$meta_key = 'imap_server';
		$diagram[$meta_key] = get_post_meta($mailbox_id, $meta_key, true);
		$meta_key = 'imap_email';
		$diagram[$meta_key] = get_post_meta($mailbox_id, $meta_key, true);
		
		$meta_key = 'cron-enable';
		$diagram[ $meta_key ] = get_post_meta($mailbox_id, $meta_key, true);
		if( $diagram[ $meta_key ] ){
			if( $cron_state = self::get_cron_state() ){
				$cron_comment = substr($cron_state, 2);
				$diagram[ 'cron_state' ] = str_starts_with( $cron_state, '1|') 
								? 'Actif' 
								: (str_starts_with( $cron_state, '0|')
									? 'A l\'arrêt'
									: $cron_state);
			}
		}
		
		//imap_email
		$meta_key = 'imap_suspend';
		$diagram[$meta_key] = get_post_meta($mailbox_id, $meta_key, true);
		
		//imap_mark_as_read
		$meta_key = 'imap_mark_as_read';
		$diagram[$meta_key] = get_post_meta($mailbox_id, $meta_key, true);
		
		
		return $diagram;
	}
	/**
	 * Rendu Html d'un diagram
	 */
	public static function get_diagram_html( $mailbox, $diagram = false, $blog_diagram = false ){
		if( ! $diagram ){
			if( ! $blog_diagram )
				throw new Exception('$blog_diagram doit être renseigné si $diagram ne l\'est pas.');
			$diagram = self::get_diagram( $blog_diagram, $mailbox );
		}
		$admin_edit = is_admin() ? sprintf(' <a href="/wp-admin/post.php?post=%d&action=edit">%s</a>'
				, $mailbox->ID
				, Agdp::icon('edit show-mouse-over')
			) : '';
			
		$html = '';
		$icon = 'email';
				
		$html .= sprintf('<div>Boîte e-mails <a href="%s">%s</a>%s</div>'
			, get_permalink($mailbox)
			, $mailbox->post_title
			, $admin_edit
		);
			
		$meta_key = 'imap_server';
		if( ! empty($diagram[$meta_key]) ){
			$imap_server = $diagram[$meta_key];
			$meta_key = 'imap_email';
			$imap_email = $diagram[$meta_key];				
			$html .= sprintf('<div>Connexion à %s via %s</div>'
				, $imap_email
				, $imap_server
			);
		}
			
		$meta_key = 'imap_suspend';
		if( ! empty($diagram[$meta_key]) ){
			$icon = 'warning';
			$html .= sprintf('<div>%s La connexion est suspendue.</div>'
					, Agdp::icon($icon)
			);
		}
		else {
			$meta_key = 'cron-enable';
			if( $diagram[ $meta_key ] ){
				if( ! empty($diagram[ 'cron_state' ]) )
					$html .= sprintf('<div>%s %s</div>'
						, Agdp::icon('controls-repeat')
						, $diagram[ 'cron_state' ]
					);
			}
			else
				$html .= sprintf('<div>%s L\'importation automatique n\'est pas active</div>'
					, Agdp::icon('warning')
				);
		}
		$meta_key = 'imap_mark_as_read';
		if( empty($diagram[$meta_key]) ){
			$icon = 'warning';
			$html .= sprintf('<div>%s L\'option "Marquer les messages comme étant lus" n\'est pas cochée. Les e-mails seront lus indéfiniment.</div>'
					, Agdp::icon($icon)
			);
		}
		
		return $html;
	}
	
}
?>