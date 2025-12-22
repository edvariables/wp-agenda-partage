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
	
	const icon = 'email';
		
	// const user_role = 'author';

	private static $initiated = false;
	public static $cron_state = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;
			
			define('AUTO_MAILBOX', '*');

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
		
		global $pagenow;
		if ( $pagenow !== 'edit.php' && $pagenow !== 'post.php') {
			add_action( 'post_class', array(__CLASS__, 'on_post_class_cb'), 10, 3);
		}
	}
	/*
	 **/
	/**
	*/
	public static function on_post_class_cb( $classes, $css_class, $post_id ){
		if( get_post_type($post_id) !== self::post_type )
			return $classes;
		
		$mailbox = get_post($post_id);
		
		add_filter( 'the_content', array(__CLASS__, 'the_content'), 10, 1 );
		
		return $classes;
	}

	/**
	 * Hook
	 */
 	public static function the_content( $content ) {
 		global $post;
		// debug_log('the_content', $post);
		if( ! $post	){
 			return $content;
		}
		
		if( current_user_can('manage_options') ){
			$comments = get_comments([
				'fields' => 'name',
				'post_id' => $post->ID,
				'post_type' => 'any',
				'status' => 'all',
			]);
			if( $comments ) {
				$content .= '<h3 class="toggle-trigger">Messages non affectés</h3>';
				$content .= '<ul class="toggle-container">';
				foreach($comments as $comment){
					$metas = get_comment_meta($comment->comment_ID, '', true);
					$content .= sprintf('<li><h4 class="toggle-trigger">%s</h4><code>De %s à %s</code><br><code>Le %s à %s (envoyé à %s)</code><div class="toggle-container"><pre>%s</pre><div>%s</div></div></li>'
						, $metas['title'][0]
						, $comment->comment_author_email
						, empty($metas['to']) ? '#' : $metas['to'][0]
						, date('d/m/Y', strtotime($comment->comment_date))
						, date('H:i', strtotime($comment->comment_date))
						, date('H:i', strtotime($metas['send_date'][0]))
						, $comment->comment_content
						, Agdp_Comment::get_attachments_links( $comment )
						// , print_r($metas, true)
					);
				}
				$content .= '</ul>';
				// $content .= sprintf('<pre>%s</pre>', print_r( $comments, true ));
				// debug_log(__FUNCTION__,  $comments);
			}
		}
		
		foreach( static::get_forums( $post->ID ) as $forum_id => $forum ){
			if( ! current_user_can('moderate_comments')
			&& Agdp_Forum::get_forum_comment_approved( $forum ) !== 'publish' )
				continue;
			$comments = get_comments([
				'fields' => 'name',
				'post_id' => $forum->ID,
				'post_type' => 'any',
				'status' => 'all',
			]);
			if( $comments ) {
				$content .= sprintf('<h3 class="toggle-trigger">%s</h3>', $forum->post_title );
				$content .= '<ul class="toggle-container">';
				foreach($comments as $comment){
					$metas = get_comment_meta($comment->comment_ID, '', true);
					$content .= sprintf('<li><h4 class="toggle-trigger">%s</h4><code>De %s à %s</code><br><code>Le %s à %s (envoyé à %s)</code><div class="toggle-container"><pre>%s</pre><div>%s</div></div></li>'
						, $metas['title'][0]
						, $comment->comment_author_email //, empty($metas['from']) ? '#' : $metas['from'][0]
						, empty($metas['to']) ? '#' : $metas['to'][0]
						, date('d/m/Y', strtotime($comment->comment_date))
						, date('H:i', strtotime($comment->comment_date))
						, empty($metas['send_date']) ? '' : date('H:i', strtotime($metas['send_date'][0]))
						, $comment->comment_content
						, Agdp_Comment::get_attachments_links( $comment )
						// , print_r($metas, true)
					);
				}
				$content .= '</ul>';
				// $content .= sprintf('<pre>%s</pre>', print_r( $comments, true ));
				// debug_log(__FUNCTION__,  $comments);
			}
		}
	    return $content;
	}
	
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
	 * Retourne un tableau de pages de forum
	 */
	public static function get_forums( $mailbox_id = false, $args = false){
		$default_args = [
			'post_type' => Agdp_Forum::post_type,
			'post_status' => 'publish',
			'numberposts' => -1,
		];
		if( ! $mailbox_id )
			$default_args['meta_query'] = [
				'relation' => 'OR',
				[
					'key' => 'agdpmailbox',
					'value' => '0',
					'compare' => '>'
				],[
					'key' => 'agdpmailbox',
					'value' => AUTO_MAILBOX,
					'compare' => '='
				]
			];
		else{
			$default_args['meta_key'] = 'agdpmailbox';
			$default_args['meta_value'] = $mailbox_id;
			$default_args['meta_compare'] = '=';
		}
		
		if( is_array($args) ) 
			$args = array_merge( $default_args, $args );
		else
			$args = $default_args;
		//Forums
		$posts = get_posts( $args );
		// debug_log("get_forums", count($posts), $args);
		$pages = [];
		foreach($posts as $page){
			if( is_numeric($page) )
				$pages[] = $page;
			else {
				$post_id = $page->ID;
				$pages[ $post_id . '' ] = $page;
			}
		}
		return $pages;
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
		elseif( ! isset($page) ) {
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
		$query = [ 'post_type' => self::post_type, 'numberposts' => -1 ];
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
		if( $mailbox === AUTO_MAILBOX )
			return $mailbox;
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
		if( self::$cron_state && strpos( self::$cron_state , '0 e-mail(s)' ) === false ){
			debug_log('[agdpmailbox-cron state]' . self::$cron_state);
		}
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
				$cron_time = strtotime( wp_date('Y-m-d H:i:s') . ' + ' . $next_time . ' second');
			else
				$cron_time = $next_time;
			$result = wp_schedule_single_event( $cron_time, self::cron_hook, [], true );
			// debug_log('[agdpmailbox::init_cron] wp_schedule_single_event', date('H:i:s', $cron_time - time()));
		}
		if( $cron_time === false ){
			$next_time = strtotime( wp_date('Y-m-d H:i:s') . ' + 1 Hour');
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
	public static function on_cron_exec( $if_scheduled = false){
		if( $if_scheduled ){
			$timestamp = wp_next_scheduled( self::cron_hook );
			if( $timestamp && ( $timestamp > time() ) )
				return;
		}
		debug_log( sprintf('[blog %d]%s::%s', get_current_blog_id(), __CLASS__, __FUNCTION__, $if_scheduled ));
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
			if( is_numeric($cron_period)
			 && $cron_period_min > $cron_period )
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
		else
			self::$cron_state = false;
		if( ! $simulate){
			self::log_cron_state();
		}
		
		return true;
	}
	
	/**
	 * Retourne les paramètres de distribution des e-mails.
	 * $destination_filter est une page de forum
	 */
	public static function get_emails_dispatch( $mailbox_id = false, $destination_filter = false, $forum_email = false ){
		if( is_a($mailbox_id, 'WP_POST') )
			$mailbox_id = $mailbox_id->ID;
		if( is_a($destination_filter, 'WP_POST') )
			$destination_filter = $destination_filter->ID;
		
	    global $wpdb;
		$sql = 
			'SELECT page.ID as page_id, page.post_title as page_title
				, mailbox_id.meta_value AS mailbox_id, mailbox.post_title AS mailbox_title, mailbox_email.meta_value AS mailbox_email
				, email.meta_value AS email, rights.meta_value AS rights, moderate.meta_value AS moderate 
			FROM ' . $wpdb->posts . ' AS page
			INNER JOIN '.$wpdb->postmeta. ' AS mailbox_id
				ON page.ID = mailbox_id.post_id 
				AND mailbox_id.meta_key = "' . AGDP_PAGE_META_MAILBOX . '"
			LEFT JOIN '.$wpdb->posts. ' AS mailbox
				ON mailbox.ID = mailbox_id.meta_value 
			LEFT JOIN '.$wpdb->postmeta. ' AS mailbox_email
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
				AND mailbox_id.meta_value = \'' . $mailbox_id . '\'
			';
		}
		if( $destination_filter ){
			$sql .= '
				AND page.ID  = ' . $destination_filter . '
			';
		}
		if( $forum_email ){
			$sql .= '
				AND ( email.meta_value  = \'' . $forum_email . '\'
					OR CONCAT( email.meta_value, \'@\', SUBSTRING_INDEX( mailbox_email.meta_value, \'@\', -1) )  = \'' . $forum_email . '\' )
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
			$mailbox_domain = $dbrow->mailbox_email ? explode('@', $dbrow->mailbox_email)[1] : $_SERVER['HTTP_HOST'];
			if( ! $email )
				continue;
				// $email = '';
			elseif( strpos($email, '@') === false )
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
		if( ! $destination_filter
			&& $mailbox_id !== false )
			$dispatches[ $email = AUTO_MAILBOX ] = [
				'email' => $email,
				'type' => Agdp_Mailbox::post_type/* 'page' */,
				'id' => $mailbox_id,
				'page_title' => false,
				'mailbox' => false,
				'mailbox_title' => false,
				'mailbox_email' => false,
				'mailbox_domain' => false,
				'rights' => 'M', //Agdp_Forum::rights
				'moderate' => true
			];
		
		// debug_log('get_emails_dispatch', $sql, $dispatches );
		
		$dispatches = array_merge( $dispatches, static::get_emails_redirections( $mailbox_id ) );
		return $dispatches;
	}
	
	/**
	 * Retourne les paramètres de redirections des e-mails.
	 * $destination_filter est une page de forum
	 */
	public static function get_emails_redirections( $mailbox_id = false ){
		if( ! $mailbox_id || $mailbox_id === AUTO_MAILBOX )
			return [];
		if( is_a($mailbox_id, 'WP_POST') )
			$mailbox_id = $mailbox_id->ID;
		$redirections = get_post_meta($mailbox_id, 'redirections', true);
		if( ! $redirections )
			return [];
		$matches = [];
		if( ! preg_match_all( '/(([a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4})\s*>\s*([a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4})\s*)+/', $redirections, $matches ) )
			return [];
		$redirections = [];
		for( $match = 0; $match < count($matches[0]); $match++){
			$email = strtolower($matches[2][$match]);
			$redir_to = strtolower($matches[3][$match]);
			if( isset($redirections[$email]) ){
				debug_log(__FUNCTION__, $email . ' existe dans un forum ET dans une redirection.' );
			}
			$redirections[$email] = [
				'email' => $email,
				'type' => 'redirection',
				'mailbox' => $mailbox_id,
				// 'mailbox_title' => $dbrow->mailbox_title,
				// 'mailbox_email' => $dbrow->mailbox_email,
				// 'mailbox_domain' => $mailbox_domain,
				'rights' => 'P', //Agdp_Forum::rights
				'moderate' => false,
				'redir_to' => $redir_to,
			];
		}
		return $redirections;
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
			switch($dispatch['type']){
				case Agdp_Mailbox::post_type :
				case 'page':
					$page_id = $dispatch['id'].'';
					break;
				case Agdp_Evenement::post_type :
					$page_id = Agdp::get_option('agenda_page_id');
					break;
				case Agdp_Covoiturage::post_type :
					$page_id = Agdp::get_option('covoiturages_page_id');
					break;
				default:
					debug_log(__CLASS__ . '::get_pages_dispatches'
						, sprintf('Erreur de configuration de la mailbox %s, le type "%s" est inconnu.'
							, is_a($mailbox_id, 'WP_Post') ? $mailbox_id->post_title : $mailbox_id
							, $dispatch['type']));
					continue 2;
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
		// debug_log('get_pages_dispatches $pages', $pages);
		return $pages;
	}
	
	/**
	 * Retourne le répertoire de stockage des fichiers attachés aux messages
	 * $post_type ou 'comment'
	 * $mailbox_id
	 */
	public static function get_attachments_path($post_type, $mailbox_id){
		$upload_dir = wp_upload_dir();
		
		$mailbox_dirname = str_replace('\\', '/', $upload_dir['basedir']);
		// if( is_multisite())
			// $mailbox_dirname .= '/sites/' . get_current_blog_id();
		
		if( $post_type === 'comment' )
			$post_type = self::post_type;
		if( $post_type === self::post_type )
			$mailbox_dirname .= sprintf('/%s/%s/%d/%d/', self::post_type, $mailbox_id, date('Y'), date('m'));
		else
			$mailbox_dirname .= sprintf('/%s/%d/%d/', $post_type, date('Y'), date('m'));
		
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
		if( ! $mailbox
		|| ($mailbox === AUTO_MAILBOX) )
			return true;
			
		$mailbox = self::get_mailbox($mailbox);
		
		$time = get_post_meta($mailbox->ID, self::sync_time_meta_key, true);
		if( $time && $time >= strtotime('- ' . AGDP_MAILBOX_SYNC_DELAY . ' second'))
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
			if( $message === null){
				debug_log(__CLASS__ . '::import_messages $message === null', $messages);
				continue;
			}
			
			$email_to = false;
			foreach($message['to'] as $to)
				if( isset($dispatch[strtolower($to->email)]) ){
					$email_to = strtolower($to->email);
					break;
				}
			if( ! $email_to && isset($message['cc']))
				foreach($message['cc'] as $to)
					if( isset($dispatch[strtolower($to->email)]) ){
						$email_to = strtolower($to->email);
						break;
					}
			if( ! $email_to && isset($message['from']))//chained mailing lists
				foreach($message['from'] as $from)
					if( isset($dispatch[strtolower($from)]) ){
						$email_to = strtolower($from);
						break;
					}
			if( ! $email_to )
				if( isset($dispatch[AUTO_MAILBOX]) )
					$email_to = AUTO_MAILBOX;
				else {
					debug_log(__CLASS__ . '::import_messages', sprintf("Un e-mail ne peut pas être importé. Il est destiné à une adresse non référencée comme forum : %s.", print_r($message['to'], true)));
					continue;
				}
					
			switch( $dispatch[$email_to]['type'] ){
				case Agdp_Mailbox::post_type ://Message sans attribution
					remove_filter('pre_comment_approved', array(__CLASS__, 'on_import_pre_comment_approved'), 10, 2);
					add_filter('pre_comment_approved', function() {return '0';}); //disapprove
					// add_filter( 'duplicate_comment_id', '__return_false', 99, 2);//DEBUG
					debug_log(__CLASS__ . '::import_messages', sprintf("Un e-mail est enregistré par défaut comme commentaire de la boite e-mails. Il est destiné à une adresse non référencée comme forum : %s.", print_r($message['to'], true)));
					
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
					// debug_log(__FUNCTION__.' $message', $message);
					$comment = self::import_message_to_comment( $mailbox, $message, $page );
					// debug_log(__FUNCTION__.' $comment', $comment);
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
					
				case 'redirection' :
					Agdp_Mailbox_IMAP::redirect_message( $mailbox, $message, $dispatch[$email_to]['redir_to'] );
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
			'send_date' => $email_date,
			'mailbox_id' => $mailbox->ID,
		];
		
		$page = Agdp_Mailbox::get_forum_page($post_type);
		$post_status = Agdp_Forum::get_forum_post_status( $page, false, $user_email, $post_source );
		
		$data = [
			'post_status' => $post_status,
			'meta_input' => $meta_input,
		];
		
		//TODO attention aux boucles infinies de mails qui partent et reviennent
		if( ! empty($message['attachments']) ){
			$attachments = [];
			$ics = [];//post exports
			foreach($message['attachments'] as $attachment)
				if( '.ics' === substr($attachment, -4) )
					$ics[] = $attachment;
				else
					$attachments[] = $attachment;
			
			$meta_input['attachments'] = $attachments;
			
			$posts = [];
			foreach($ics as $attachment){
				$p = Agdp_Post::import_post_type_ics($post_type, $attachment, $data);
				if( $p ){
					$posts[] = $p;
					unlink($attachment);
				}
			}
			return $posts;
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
				'post_content' => Agdp_Mailbox_IMAP::get_imap_message_content($mailbox->ID, $message, $post_parent, $page),
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
			debug_log('import_message_to_comment  ! $mailbox');
			return false;
		}
		
		// debug_log('import_message_to_comment', $mailbox->ID, $mailbox->ID === $page->ID);
		$message['subject'] = trim($message['subject']);
		$is_cancel_notification = strpos( $message['subject'], AGDP_SUBJECT_CANCELED ) === 0;
		if( $is_cancel_notification )
			$message['subject'] = trim( substr( $message['subject'], strlen(AGDP_SUBJECT_CANCELED) ) );
		$is_update_notification = strpos( $message['subject'], AGDP_SUBJECT_UPDATED ) === 0;
		if( $is_update_notification )
			$message['subject'] = trim( substr( $message['subject'], strlen(AGDP_SUBJECT_UPDATED) ) );
		
		//TODO forum sans Title : merge subject + body
		
		$comment = false;
		$comment_date = wp_date('Y-m-d H:i:s');
		$comment_date_gmt = date('Y-m-d H:i:s');
		$comment_id = self::get_existing_comment( $page, $message );
		if( $comment_id ){
			if( $is_cancel_notification ){
				update_comment_meta( $comment_id, AGDP_SUBJECT_CANCELED, sprintf('%s::%s %s', __CLASS__, __FUNCTION__, wp_date('Y-m-d H:i:s')));
				debug_log('import_message_to_comment get existing_comment AGDP_SUBJECT_CANCELED wp_delete_comment '.$comment_id);
				wp_delete_comment( $comment_id );
				
				return 0;
			}
			$comment = get_comment($comment_id);
			if( $is_update_notification ){
				//TODO AGDP_IMPORT_REFUSED
				
				/* $comment_parent = false;// $comment->comment_parent;
				$comment_content = Agdp_Mailbox_IMAP::get_imap_message_content($mailbox->ID, $message, $comment_parent, $page);
				debug_log(__FUNCTION__ . ' update', $comment_content);
				$title = $message['subject'];
				$commentdata = [
					'comment_ID' => $comment_id,
					'comment_content' => $comment_content,
					'comment_meta' => [
						'title' => $title,
					],
				];
				$result = wp_update_comment( $commentdata, true ); */
				
				
				//wp_update_comment filtre le html de comment_content donc on supprime pour ajouter. TODO
				
				$comment_date = $comment->comment_date;
				$comment_date_gmt = $comment->comment_date_gmt;
				
				wp_delete_comment( $comment_id );
				
				$comment = false;
				
				debug_log('import_message_to_comment update_notification === delete+new of id='.$comment_id);
			}
			else {
				debug_log('import_message_to_comment existing_comment id='.$comment_id);
			}
		}
		elseif( $is_cancel_notification || $is_update_notification ){
			debug_log('import_message_to_comment AGDP_SUBJECT_CANCELED|UPDATED > ! existing_comment = ignore ');
			return 0;
		}
		if( ! $comment ){
			$imap_server = get_post_meta($mailbox->ID, 'imap_server', true);
			$imap_email = get_post_meta($mailbox->ID, 'imap_email', true);
			$enable_duplicate_comment = Agdp_Forum::get_forum_enable_duplicate_comment($page->ID);
			
			$comment_parent = self::find_comment_parent( $page, $message );
			// var_dump($message);
			if( ! isset($message['reply_to']) || ! $message['reply_to'] )
				$message['reply_to'] = [ $message['from'] ];
			
			$user_email = strtolower($message['reply_to'][0]->email);
			
			if( Agdp_Forum::is_email_blacklisted( $page, $user_email) ){
				debug_log(__CLASS__.'::'.__FUNCTION__ . ' is_email_blacklisted ' . $user_email);
				return 0;
			}
				
			
			$user_name = $message['reply_to'][0]->name ? $message['reply_to'][0]->name : $user_email;
			
			if( ($pos = strpos($user_name, '@')) !== false)
				$user_name = substr( $user_name, 0, $pos);
			$user_name = trim( $user_name, '" ');
			
			$user_id = is_user_logged_in() 
				? get_current_user_id()
				: email_exists($user_email);
			
			$dateTime = $message['date'];
			$email_date = wp_date('Y-m-d H:i:s', $dateTime->getTimestamp());
			//On conserve en date de référence la date d'importation (plus logique pour le traitement des newsletters)
			$commentdata = [
				'comment_post_ID' => $page->ID,
				'comment_author' => $user_name,
				'comment_author_url' => 'mailto:' . $user_email,
				'comment_author_email' => $user_email,
				'comment_content' => Agdp_Mailbox_IMAP::get_imap_message_content($mailbox->ID, $message, $comment_parent, $page),
				'comment_date' => $comment_date,
				'comment_date_gmt' => $comment_date_gmt,
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
					'title' => $message['subject'],
					'attachments' => $message['attachments'],
					'send_date' => $email_date,
					'mailbox_id' => $mailbox->ID,
				]
			];
			
			if( ! empty($message[AGDP_IMPORT_UID]))
				$commentdata['comment_meta'][AGDP_IMPORT_UID] = $message[AGDP_IMPORT_UID];
			
			//sinon, quand il n'y a pas d'utilisateur connecté, le comment_content est purgé du html
			$has_wp_filter_kses = has_filter( 'pre_comment_content', 'wp_filter_kses' );
			remove_filter( 'pre_comment_content', 'wp_filter_kses' );
			
			if( $enable_duplicate_comment )
				add_filter( 'duplicate_comment_id', '__return_false' );
			
			//wp_new_comment
			$comment = wp_new_comment($commentdata, true);
			
			if( $has_wp_filter_kses )
				add_filter( 'pre_comment_content', 'wp_filter_kses' );
			
			if( $enable_duplicate_comment )
				remove_filter( 'duplicate_comment_id', '__return_false' );
			
			// debug_log(__FUNCTION__, ! is_wp_error($comment), $message, $commentdata['comment_content']);
			// echo '<pre>'; var_dump($message, $commentdata/* , $comment */);echo '</pre>'; 
			if( is_wp_error($comment) ){
				// if( get_post_meta($mailbox->ID, 'imap_mark_as_read', true)
				// || ! in_array('comment_duplicate', $comment->get_error_codes())
				// )
				if( in_array('comment_duplicate', $comment->get_error_codes()) )
					debug_log(__FUNCTION__.' !wp_new_comment : comment_duplicate existing_comment : id=', $comment->get_error_data('comment_duplicate'), $message['subject']);
				else
					debug_log(__FUNCTION__.' !wp_new_comment error : ', $comment, $message);
				if( is_admin()
				&& class_exists('Agdp_Admin'))
					Agdp_Admin::add_admin_notice(__CLASS__.'::'.__FUNCTION__.'(). wp_new_comment returns ',
						in_array('comment_duplicate', $comment->get_error_codes()) 
							? sprintf('Duplication %s : "%s"', $user_email, $commentdata['comment_meta']['title'])
							: print_r($comment, true));
			}
			else
				self::import_comment_set_default_date( $comment, $page->ID );
			
		}
		
		return $comment;
	}
	
	
	/********************
	 * Posts
	 */
	//Cherche un message déjà importé
	private static function import_comment_set_default_date( $comment_id, $forum_id ){
		$date_field = Agdp_Forum::get_forum_sort_date_field($forum_id);
		if( ! $date_field )
			return;
		if( is_string($date_field) && strpos( $date_field, ',') != false )
			$date_field = explode(',', $date_field);
		if( is_array($date_field) )
			$date_field = $date_field[0];
		if( $date_field && ! get_comment_meta( $comment_id, $date_field, true ) )
			update_comment_meta( $comment_id, $date_field, date('Y-m-d') );
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
			return true;
		
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
		
		
		return $comment_approved;
	}
	//Cherche un message déjà importé
	private static function get_existing_comment( $page, $message ){
		
		if( isset($message[AGDP_IMPORT_UID]) ){
			$comments = get_comments([
				'post_id' => $page->ID
				, 'meta_key' => AGDP_IMPORT_UID
				, 'meta_value' => $message[AGDP_IMPORT_UID]
				, 'meta_compare' => '='
				, 'number' => 1
			]);
		}
		else {
			$user_email = strtolower($message['reply_to'][0]->email);
			$subject = $message['subject'];
			$comments = get_comments([
				'post_id' => $page->ID
				, 'comment_approved' => ['0','1']
				, 'author_email' => $user_email
				, 'meta_query' => [
					'relation' => 'AND',
					[
						'key' => 'title',
						'value' => $subject,
						'compare' => '=',
					],
					[
						'key' => 'send_date',
						'value' => $message['date']->format('Y-m-d H:i:s' ),
						'compare' => '=',
					]
				]
				// , 'date_query' => [
					// 'after' => $message['date']->format('Y-m-d H:i:s' ),
					// 'before' => $message['date']->format('Y-m-d H:i:s'),
					// 'inclusif' => true,
				// ]
				, 'number' => 1
			]);
		}
		foreach($comments as $comment){
			return $comment->comment_ID;
		}
		return 0;
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
			if( ! $_POST['_wpcf7_container_post']
			 || ! ($post = get_post($_POST['_wpcf7_container_post']) ) )
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
					
					//TODO
					$properties = $contact_form->get_properties();
					$mail_properties = $properties['mail'];
					$email_to = strtolower($mail_properties['recipient']);
					if( $email_to === 'commentaire@evenement.agdp'
					 || $email_to === 'comment@agdpevent.agdp'
					 || $email_to === 'commentaire@covoiturage.agdp'
					 || $email_to === 'comment@covoiturage.agdp'
					){
						return self::import_wpcf7_to_comment($contact_form, $abort, $submission, false, false, $post);
					}
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
		// debug_log(__CLASS__.'::import_wpcf7_to_comment');
		
		$properties = $contact_form->get_properties();
		$posted_data = $submission->get_posted_data();
		$mail_properties = $properties['mail'];
		$email_to = strtolower($mail_properties['recipient']);
		
		// debug_log(__FUNCTION__, $posted_data, $mail_properties);
		
		if( isset($posted_data['is-public'])
		&& $posted_data['is-public'] ){
			if( is_array($posted_data['is-public']) )
				$posted_data['is-public'] = $posted_data['is-public'][0];
			$posted_data['is-public'] = ! in_array( strtolower($posted_data['is-public']), ['non', '0', 'false']);
		}
		
		//TODO
		if( $email_to === 'commentaire@evenement.agdp'
		 || $email_to === 'comment@agdpevent.agdp'
		 || $email_to === 'commentaire@covoiturage.agdp'
		 || $email_to === 'comment@covoiturage.agdp'
		){
		}
		else {		
			$emails = self::get_emails_dispatch();
			if( ! isset($emails[$email_to]) )
				return;
		}
		debug_log(__FUNCTION__, $email_to);
		
		//$mail_properties['additional_headers'] de la forme Reply-To: "[abonne-nom]"<[abonne-email]>
		$email_replyto = wpcf7_mail_replace_tags(strtolower($mail_properties['additional_headers']));
		// debug_log(__FUNCTION__ . ' email_replyto', $email_replyto);
		$matches = [];
		if( preg_match_all('/^[\s\S]*reply-to\s*:\s*("(.*)"\s*)?\<?([a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4})\>?[\s\S]*$/', $email_replyto, $matches)) {
			$user_name = $matches[2][0];
			$email_replyto = $matches[3][0];
		}
		else {
			$user_name = false;
			$email_replyto = false;
		}
		// debug_log(__FUNCTION__ . ' user', $user_name, $email_replyto);
		$subject = wpcf7_mail_replace_tags($mail_properties['subject'], $mail_properties);
		$body = wpcf7_mail_replace_tags($mail_properties['body'], $mail_properties);
		// debug_log(__FUNCTION__, $subject, $body);
		
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
		if( isset($_POST['_update_comment_id']) ){
			$update_comment = $_POST['_update_comment_id'];
		}
		else
			$update_comment = false;
		
		//attachments
		self::import_wpcf7_to_comment_attachments($submission, $mailbox_id, $update_comment, $posted_data, $commentdata);
		
		//posted_data
		foreach( $posted_data as $key=>$value )
			$commentdata['comment_meta']['posted_data_' . $key] = $value;
		
		//update comment
		if( $update_comment ){
			$commentdata['comment_ID'] = $update_comment;
			$comment = wp_update_comment($commentdata, true);
			if( $comment && ! is_wp_error( $comment ) )
				$comment = $commentdata['comment_ID'];
		}
		//add new comment
		else {
			$enable_duplicate_comment = Agdp_Forum::get_forum_enable_duplicate_comment($page->ID);
			
			if( $enable_duplicate_comment )
				add_filter( 'duplicate_comment_id', '__return_false' );
			
			add_filter('pre_comment_approved', array(__CLASS__, 'on_import_pre_comment_approved'), 10, 2 );
			
			//wp_new_comment
			$comment = wp_new_comment($commentdata, true);
			
			remove_filter('pre_comment_approved', array(__CLASS__, 'on_import_pre_comment_approved'), 10, 2 );
			
			if( $enable_duplicate_comment )
				remove_filter( 'duplicate_comment_id', '__return_false' );
		}
		if(	is_wp_error( $comment ) ){
			$message = 'Erreur : ' . $comment->get_error_message();
			$submission->set_response($message);
			
			$abort = true;
		}
		else {
			if( ! empty($_POST['nl-period-agdpforum']) || ! empty($_POST['nl-period-agdpforum[]']) ){
				if( $newsletter = Agdp_Page::get_page_main_newsletter( $page ) )
					Agdp_Newsletter::update_subscription( $email_replyto, PERIOD_DAYLY, $newsletter);
			}
			//$html = wp_list_comments(['echo'=>false], [ get_comment($comment) ]);
			$nonce = Agdp_Comment::get_nonce( $comment );
			$messages = ($contact_form->get_properties())['messages'];
			$messages['mail_sent_ok'] = str_replace("\t", '', str_replace("\n", '', 
				sprintf('js:(function( id ){
					show_new_comment( id, "%s" );
					return "%s";
				})(%d)'
				, $nonce
				, str_replace('"', '\\"', $messages['mail_sent_ok'])
				, $comment
			)));
			$contact_form->set_properties(array('messages' => $messages));
		}

		add_filter('wpcf7_skip_mail', array(__CLASS__, 'wpcf7_skip_mail_forced'), 10, 2);
	} 
	
	/*
	 * Intégration des fichiers attachés
	 */
	private static function import_wpcf7_to_comment_attachments($submission, $mailbox_id, $is_update, &$posted_data, &$commentdata){
		self::submit_wpcf7_save_attachments($submission, 'comment', $is_update, $posted_data, $commentdata);
		self::submit_wpcf7_attachment_add($submission, 'comment', $mailbox_id, $is_update, $posted_data, $commentdata);
	}
	
	/*
	 * Intégration des fichiers attachés dans les évènements
	 */
	public static function import_wpcf7_save_post_type_attachments($submission, $post_type, $post_id, $is_update, &$posted_data){
		$data = [];
		self::submit_wpcf7_save_attachments($submission, $post_type, $post_id, $posted_data, $data);
		self::submit_wpcf7_attachment_add($submission, $post_type, $post_id, $is_update, $posted_data, $data);
	}
	
	/*
	 * Submit depuis le attachments_manager (cf .js) d'un wpcf7
	 * $submission : wpcf7
	 * $post_type : may be 'comment'
	 * $item_id == $post_id | $comment_id
	 * $is_update : null or @item_id
	 */
	public static function submit_wpcf7_save_attachments($submission, $post_type, $item_id, &$posted_data, &$commentdata){
		if( isset($posted_data['attachments']) ){
			
			$attachments = $posted_data['attachments'];
			if( $attachments && in_array( $attachments[0], ['[', '{'] ) ){
				$upload_dir = wp_upload_dir();
				$upload_dir = str_replace("\\", '/', $upload_dir['basedir']);
				//Modifiés par attachments_manager (cf .js)
				$input = json_decode($attachments);
				$attachments = [];
				foreach($input as $attachment){
					$data = explode('|', $attachment);
					$data[0] = str_replace("//", '/', str_replace("\\", '/', $data[0]));
					//Contrôle de sécurité sur le dossier (sinon, on pourrait trasher n'importe quel fichier)
					if( strcasecmp( $upload_dir, substr( $data[0], 0, strlen($upload_dir) ) ) !== 0 ){
						debug_log(__FUNCTION__, 'Erreur de répertoire', $data, $upload_dir  );
						continue;
					}
					if( count($data) > 1 ){
						if( $data[1] === 'DELETE' ){
							if( file_exists($data[0]) )
								unlink($data[0]);
							continue;
						}
					}
					$attachments[] = $data[0];
				}
				if( $post_type === 'comment' ){
					$commentdata['comment_meta']['attachments'] = $attachments;
				}
				else {
					$commentdata['post_meta']['attachments'] = $attachments;
					update_post_meta( $item_id, 'attachments', $attachments );
				}
			}
			unset($posted_data['attachments']);
		}
	}
	
	/*
	 * Submit d'un ajout de fichier attaché
	 * $submission : wpcf7
	 * $post_type : may be 'comment'
	 * $item_id == $post_id | $comment_id
	 * $is_update : null or @item_id
	 */
	private static function submit_wpcf7_attachment_add($submission, $post_type, $item_id, $is_update, &$posted_data, &$commentdata){
		$uploaded_files = $submission->uploaded_files();
		if( $uploaded_files 
		&& ! empty($uploaded_files['attachment_add']) ){
			
			if( ! class_exists('Agdp_Mailbox_IMAP') )
				require_once( AGDP_PLUGIN_DIR . "/public/class.agdp-mailbox-imap.php");
			$uploaded_files = Agdp_Mailbox_IMAP::sanitize_attachments( $uploaded_files, 'attachment_add' );
			
			$dest_dir = self::get_attachments_path( $post_type, $item_id );
			
			if( $is_update ){
				if( $post_type === 'comment' ){
					if( isset($commentdata['comment_meta']['attachments'] ) )
						$files = $commentdata['comment_meta']['attachments'];
					else
						$files = get_comment_meta($is_update, 'attachments', false);
				}
				else {
					if( $commentdata && isset($commentdata['post_meta']) && ! empty($commentdata['post_meta']['attachments'] ) ){
						$files = $commentdata['post_meta']['attachments'];
						debug_log(__FUNCTION__, "commentdata['post_meta']['attachments']");
					}
					else{
						$files = get_post_meta($item_id, 'attachments', false);
						debug_log(__FUNCTION__, "get_post_meta attachments");
					}
					debug_log(__FUNCTION__, '$files', $files);
				}
				
				if( ! $files )
					$files = [];
				elseif( ! is_array($files) ){
					$files = [ $files ];
				}
				elseif( count($files) && is_array($files[0]) ){
					$files = $files[ 0 ];
				}
			}
			else
				$files = [];
			
			foreach( $uploaded_files['attachment_add'] as $upfile ){
				$file = path_join( $dest_dir, basename($upfile) );
				$index = 1;
				while( file_exists($file) ) {
					$file = path_join( $dest_dir, pathinfo($upfile, PATHINFO_FILENAME) . '(' . ($index++) . ').' . pathinfo($upfile, PATHINFO_EXTENSION) );
				}
				rename( $upfile, $file );
				$files[] = $file;
			}
			
			if( $post_type === 'comment' ){
				if( isset($posted_data['attachment_add']) )
					unset($posted_data['attachment_add']);
				$commentdata['comment_meta']['attachments'] = $files;
			}
			else {
				update_post_meta( $item_id, 'attachments', $files );
			}
		}
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
		$diagram = [ 
			'mailbox' => $mailbox, 
		];
		
		if( is_a($mailbox, 'WP_Post') ){
			$mailbox_id = $mailbox->ID;
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
			
			
		}
		
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
				, is_a($mailbox, 'WP_Post') ? $mailbox->ID : $mailbox
				, Agdp::icon('edit show-mouse-over')
			) : '';
			
		$html = '';
		$icon = 'email';
		
		if( is_a($mailbox, 'WP_Post') ){
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
		}
		elseif( $mailbox === AUTO_MAILBOX )
			$html .= '<div>Boîte e-mails interne</div>';
		
		return $html;
	}
}
?>