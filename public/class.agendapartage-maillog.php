<?php

/**
 * AgendaPartage -> Maillog
 * Custom post type for WordPress.
 * 
 * Définition du Post Type agdpmaillog
 *
 * Voir aussi AgendaPartage_Admin_Maillog
 */
class AgendaPartage_Maillog {

	const post_type = 'agdpmaillog';

	private static $initiated = false;

	private static $sending_mail = [];

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
		add_filter( 'the_title', array(__CLASS__, 'the_agdpmaillog_title'), 10, 2 );
		
		add_filter( 'the_content', array(__CLASS__, 'the_agdpmaillog_content'), 10, 1 );
		
		if(AgendaPartage::maillog_enable()){
			add_filter( 'wp_mail', array(__CLASS__, 'on_wp_mail'), 20, 1 );
			add_filter( 'wp_mail_succeeded', array(__CLASS__, 'on_wp_mail_succeeded'), 20, 1 );
			add_filter( 'wp_mail_failed', array(__CLASS__, 'wp_mail_failed'), 20, 1 );
		}
	}
	 
	/**
	 * on_wp_mail
	 */
	public static function on_wp_mail( array $mail_data ){
		
		// debug_log('maillog wp_mail', $mail_data);
		
		$meta_input = [
			'to' => $mail_data['to'],
			'headers' => $mail_data['headers'],
			'mail_data' => var_export($mail_data, true),
			'_SERVER' => var_export($_SERVER, true)
		];
		if( isset($_REQUEST) )
			$meta_input['_REQUEST'] = var_export($_REQUEST, true);
		
		if( ! is_array($meta_input['headers']) ){
			$meta_input['headers'] = explode("\n", $meta_input['headers']);
		}
		foreach($meta_input['headers'] as $header){
			$matches = [];
			if(preg_match_all('/^(from|bcc|cc|reply\-to)\:(.*)$/', strtolower($header), $matches)){
				if( ! empty($meta_input[$matches[1][0]]) ){
					if( is_array($meta_input[$matches[1][0]]) )
						$meta_input[$matches[1][0]][] = trim($matches[2][0], " \r\n;,");
					else
						$meta_input[$matches[1][0]] .= ', ' . trim($matches[2][0], " \r\n;,");
				}
				else
					$meta_input[$matches[1][0]] = trim($matches[2][0], " \r\n;,");
			}
		}
		
		if(is_array($meta_input['to']))
			$meta_input['to'] = implode(', ', $meta_input['to']);
		// if(isset($meta_input['headers']['bcc']))
			// $meta_input['bcc'] = implode(', ', $meta_input['headers']['bcc']);
		// if(isset($meta_input['headers']['From']))
			// $meta_input['from'] = $meta_input['headers']['From'];
		// elseif(isset($meta_input['headers']['from']))
			// $meta_input['from'] = $meta_input['headers']['from'];
			
		$mail_data['subject'] = base64_decode_if_needed($mail_data['subject']);
		// debug_log('wp_mail subject post : ', $mail_data['subject']);
		$postarr = [
			'post_type' => self::post_type,
			'post_title' => $mail_data['subject'],
			'post_content' => $mail_data['message'],
			'post_status' => 'pending',
			'meta_input' => $meta_input
		];
		
		$maillog = wp_insert_post($postarr, true);
		if(is_a($maillog, 'WP_Error')){
			debug_log('wp_mail Error $maillog', $maillog);
			return;
		}
		array_push(self::$sending_mail, $maillog);
		
		// debug_log('wp_mail maillog', $maillog);
		
	}
	 
	 
	/**
	 * on_wp_mail_succeeded
	 */
	public static function on_wp_mail_succeeded( array $mail_data ){
		// debug_log('on_wp_mail_succeeded', $mail_data);
		
		$maillog = array_pop( self::$sending_mail );
		if($maillog === null){
			error_log('on_wp_mail_succeeded $maillog === null');
			return;
		}
		$postarr = [
			'ID' => $maillog,
			'post_status' => 'publish'
		];
		$result = wp_update_post($postarr, true);
		
		// debug_log('on_wp_mail_succeeded wp_update_post ', $result);
		
	}
	 
	 
	/**
	 * wp_mail_failed
	 */
	public static function wp_mail_failed( WP_Error $error ){
		// debug_log('wp_mail_failed', $error);
		
		$mail_data = $error->error_data['wp_mail_failed'];
		
		$maillog = array_pop( self::$sending_mail );
		if($maillog === null){
			// debug_log('wp_mail_failed $maillog === null');
			return;
		}
		elseif(is_a($maillog, 'WP_Error')){
			// debug_log('wp_mail_failed $maillog', $maillog);
			return;
		}
		
		$error_message = implode(', ', $error->errors['wp_mail_failed']);
		
		$postarr = [
			'ID' => $maillog,
			'post_status' => 'draft',
			'meta_input' => [
				'error' => $error_message,
				'headers' => $mail_data['headers']
			]
		];
		$result = wp_update_post($postarr, true);
		
		// debug_log('wp_mail_failed', $result);
	}
	
	/***************
	 * the_title()
	 */
 	public static function the_agdpmaillog_title( $title, $post_id ) {
 		global $post;
 		if( ! $post
 		|| $post->ID != $post_id
 		|| $post->post_type != self::post_type){
 			return $title;
		}
	    return self::get_agdpmaillog_title( $post );
	}

	/**
	 * Hook
	 */
 	public static function the_agdpmaillog_content( $content ) {
 		global $post;
 		if( ! $post
 		|| $post->post_type != self::post_type){
 			return $content;
		}
		
		if( ! current_user_can('manage_options')) {
			return '<p class="error">Vous devez être connecté pour visualiser ces informations.</p>';
		}
		
	    return self::get_agdpmaillog_content( $post );
	}
	
 	/**
 	 * Retourne le titre de la page
 	 */
	public static function get_agdpmaillog_title( $agdpmaillog = null, $no_html = false) {
 		if( ! isset($agdpmaillog) || ! is_object($agdpmaillog)){
			global $post;
			$agdpmaillog = $post;
		}
		
		$post_title = isset( $agdpmaillog->post_title ) ? $agdpmaillog->post_title : '';
		$bcc = get_post_meta($agdpmaillog->ID, 'bcc', true);
		$html = $post_title
			. ' - ' . get_post_meta($agdpmaillog->ID, 'from', true)
			. ' >> ' . ($bcc ? $bcc : get_post_meta($agdpmaillog->ID, 'to', true));
		if($error = get_post_meta($agdpmaillog->ID, 'error', true))
			$html = '[Erreur] ' . $html;
		return $html;
	}
	
 	/**
 	 * Retourne le Content du mail
 	 */
	public static function get_agdpmaillog_content( $agdpmaillog = null ) {
		global $post;
 		if( ! isset($agdpmaillog) || ! is_a($agdpmaillog, 'WP_Post')){
			$agdpmaillog = $post;
		}
		
		$html = sprintf('<pre style="max-height: 20em;">%s</pre>', $agdpmaillog->post_content);
		
		return $html;
	}
}
