<?php

/**
 * AgendaPartage -> WPCF7
 * Tools for WPCF7
 */
class AgendaPartage_WPCF7 {
	
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
		add_action( 'wp_enqueue_scripts', array(__CLASS__, 'register_plugin_js') ); 
		
		if( self::may_skip_recaptcha() ){
			// add_filter( 'wpcf7_load_js', '__return_false' );
			// add_filter( 'wpcf7_load_css', '__return_false' );
		}
		
		//wpcf7_before_send_mail : mise à jour des données avant envoi (ou annulation) de mail
		add_filter( 'wpcf7_before_send_mail', array(__CLASS__, 'wpcf7_before_send_mail'), 10, 3);
		// if( WP_DEBUG && in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', 'pstfe.ed2020', '::1' ) ) ) {
			// add_filter( 'wpcf7_mail_failed', array(__CLASS__, 'wpcf7_mail_sent'), 10,1);
		// }

		//Contact Form 7 hooks
		add_filter( 'wp_mail', array(__CLASS__, 'wp_mail_check_headers_cb'), 10,1);

		//Contrôle de l'envoi effectif des mails	
		add_filter('wpcf7_skip_mail', array(__CLASS__, 'wpcf7_skip_mail'), 10, 2);
			
		// Interception des emails en localhost
		if( WP_DEBUG && in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', 'pstfe.ed2020', '::1' ) ) ) {
			// add_filter( 'wp_mail', array(__CLASS__, 'wp_mail_localhost'), 100, 1);
		}
	}
	
	// define the wpcf7_skip_mail callback 
	public static function wpcf7_skip_mail( $skip_mail, $contact_form ){ 
		if($skip_mail
		|| $contact_form->id() == AgendaPartage::get_option('newsletter_events_register_form_id'))
			return true;
		return $skip_mail || AgendaPartage::$skip_mail;
	} 

	/**
	 * Registers js files.
	 */
	public static function register_plugin_js() {			
		
		if(AgendaPartage::is_plugin_active('wpcf7-recaptcha/wpcf7-recaptcha.php')){
			if( self::may_skip_recaptcha() ){
				// wp_dequeue_script( 'google-recaptcha' );   
				// wp_dequeue_script('wpcf7-recaptcha');
				// wp_dequeue_style('wpcf7-recaptcha');
				// add_filter( 'wpcf7_load_js', '__return_false' );
				// add_filter( 'wpcf7_load_css', '__return_false' );
				// remove_action( 'wp_enqueue_scripts', 'wpcf7_recaptcha_enqueue_scripts', 20 );
			}
			else {
				wp_register_script( 'wpcf7-recaptcha-controls-ajaxable.js', plugins_url( 'agenda-partage/includes/js/wpcf7-recaptcha-controls-ajaxable.js' ), array(), AGDP_VERSION , true );
				wp_enqueue_script( 'wpcf7-recaptcha-controls-ajaxable.js' );
			}
		}
	}
	
	// 
	public static function may_skip_recaptcha( ){ 
	   return current_user_can('manage_options');
	} 
	
	/**
	* Hook de wp_mail
	* Interception des emails en localhost
	* TODO Vérifier avec plug-in Smtp
	*/		
	public static function wp_mail_localhost($args){
		echo "<h1>Interception des emails en localhost.</h1>";

		print_r(sprintf("%s : %s<br>\n", 'To', $args["to"]));
		print_r(sprintf("%s : %s<br>\n", 'Subject', $args["subject"]));
		print_r(sprintf("%s : <code>%s</code><br>\n", 'Message', preg_replace('/html\>/', 'code>', $args["message"] )));
		print_r(sprintf("%s : %s<br>\n", 'Headers', $args['headers']));
		//Cancels email without noisy error and clear log
		$args["to"] = '';
		$args["subject"] = '(localhost)';
		$args["message"] = '';
		$args['headers'] = '';
	    return $args;
	}

	/**
	* Hook de wp_mail
	* Définition du Headers.From = admin_email
	*/		
	public static function wp_mail_check_headers_cb($args) {
		if( ! $args['headers'])
			$args['headers'] = [];
		if(is_array($args['headers'])){
			$from_found = false;
			foreach($args['headers'] as $index=>$header)
				if($from_found = str_starts_with($header, 'From:'))
					break;
			if( ! $from_found)
				$args['headers'][] = sprintf('From: %s<%s>', get_bloginfo('name'), get_bloginfo('admin_email'));
		}
		return $args;
	}
	
	/**
	 * Intercepte les emails de formulaires wpcf7.
	 * Appel une classe spécifique suivant l'id du formulaire que l'utilisateur vient de valider
	 */
	public static function wpcf7_before_send_mail ($contact_form, &$abort, $submission){
		if( self::check_submission_is_abuse($submission) ){
			$abort = true;
			return;
		}

		$form_id = $contact_form->id();

		switch($form_id){
			case AgendaPartage::get_option('admin_message_contact_form_id') :
				AgendaPartage_Evenement::change_email_recipient($contact_form);
				break;
			case AgendaPartage::get_option('agdpevent_edit_form_id') :
				AgendaPartage_Evenement_Edit::submit_agdpevent_form($contact_form, $abort, $submission);
				break;
			case AgendaPartage::get_option('newsletter_events_register_form_id') :
				AgendaPartage_Newsletter::submit_subscription_form($contact_form, $abort, $submission);
				break;
				
		}		

		return;
	}
	
	 
	/**
	 *
	 */
	public static function check_submission_is_abuse($submission){
		// debug_log_clear('check_email_is_abuse', $submission);
		$emails = self::get_input_emails($submission);
		$message = self::get_input_message($submission);
		if( ! self::check_email_is_abuse( $emails, $submission ))
			if( ! self::check_message_is_abuse( $message, $submission ))
				return false;
		self::log_email_abuse($submission, $emails, $message);
		return true;
	}
	
	public static function get_input_emails($submission){
		$emails = [];
		$contact_form = $submission->get_contact_form();
		foreach($contact_form->scan_form_tags() as $form_tag){
			if( str_starts_with($form_tag->type, 'email')){
				if( is_email($submission->get_posted_data($form_tag->name)))
					$emails[] = $submission->get_posted_data($form_tag->name);
		}}
		return $emails;
	}
	public static function get_input_message($submission){
		$contact_form = $submission->get_contact_form();
		$message = '';
		foreach($contact_form->scan_form_tags() as $form_tag)
			if( str_starts_with($form_tag->type, 'textarea'))
				if( $submission->get_posted_data($form_tag->name) )
					$message .= ($message ? "\r\n" : '') . $submission->get_posted_data($form_tag->name);
		return $message;
	}
	
	/**
	 * Contrôle la forme de l'email
	 * 		hello.b.u.y.mycar@gnagna.sw
	 */
	public static function check_email_is_abuse($email, $submission){
		if( ! $email )
			return false;
			
			
		if( is_array($email) ){
			foreach($email as $single)
				if( self::check_email_is_abuse($single, $submission) )
					return true;
			return false;
		}
		
		// $matches = [];
		// $pattern = '/^([^.]+)([.][^.]+)+[@](.*)$/';
		// if(preg_match_all( $pattern, $email, $matches )){
		$matches = explode('.', explode('@', $email)[0]);
		if( count($matches) > 3){
			
			// More than 4 parts : abuse
			if( count($matches) > 4){
				debug_log('email_is_abuse : too much email parts');
				return true;
			}
			
			// Very small parts : absuse
			$part_size = 0;
			for($i = 1; $i < count($matches); $i++)
				$part_size += strlen($matches[$i]);
			$part_size /= count($matches) -1;
			if( $part_size <= 2){
				debug_log('email_is_abuse : too small email parts');
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Contrôle le contenu du message
	 * 		<a href="https://hello.b.u.y.mycar.gnagna.sw">clic me</a>
	 */
	public static function check_message_is_abuse($message, $submission){
		$suspect = false;
		
		$message = preg_replace("/^[^\n]+\n/", '', //skip first ligne
					str_replace(get_bloginfo('name'), '', 
						str_replace(get_bloginfo('url'), ''
			, $message)));
			
		if( strlen($message) < 200)
			return false;
		
		$forbidden_words = ['http']; //TODO
		foreach($forbidden_words as $word)
			if( strpos($message, $word) !== false ){
				$error_message = sprintf('Veuillez retirer le terme "%s" de votre message.', $word);
				$submission->set_response($error_message);
				return true;
			}
			
		$suspectlist = ['marketing']; //TODO
		foreach($suspectlist as $word)
			if( strpos($message, $word) !== false ){
				$suspect = $word;
				break;
			}
		if($suspect){
			if(self::check_message_is_not_french($message)){
				debug_log('email_is_abuse : not french language');
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Contrôle le contenu du message
	 * 		texte en anglais
	 */
	public static function check_message_is_not_french($message){
		/* if( ! $message )
			return false;
		
		require_once(AGDP_PLUGIN_DIR . '/includes/LanguageDetector/LanguageDetector.php');
        $detector = (new LanguageDetector\LanguageDetector(null, ['en', 'fr']))->evaluate($message);
		debug_log('$detector->getSupportedLanguages()', $detector->getSupportedLanguages());
		debug_log('$detector->getScores()', $detector->getScores());
		debug_log('$winner', array_keys($detector->getScores())[0]);


		return true;

		$blacklist = ['to', 'the', 'marketing', 'website', 'and', 'we', 'you']; //TODO
		$greenlist = ['bonjour', 'le', 'les', 'nous', 'je']; //TODO
		
		foreach($blacklist as $abuse)
			if( strpos($message, $abuse) !== false ){
				debug_log('message_is_abuse : contains black listed word : ' . $abuse);
				return true;
			}
		 */
		return false;
	}
	
	/**
	 * Store
	 */
	public static function log_email_abuse($submission, $emails, $message){
		
		if(AgendaPartage::maillog_enable()){
			$postarr = [
				'post_type' => AgendaPartage_Maillog::post_type,
				'post_status' => 'pending',
				'post_title' => '[Abuse] from ' . implode(', ', $emails),
				'post_content' => $message,
				'meta_input' => array(
					'posted_data' => var_export($submission->get_posted_data(), true)
					, '_SERVER' => var_export($_SERVER, true)
				)
			];
			$post = wp_insert_post( $postarr, true );
			if( is_a($post, 'WP_Error') )
				debug_log('log_email_abuse insert_post', $post);
		}
		else
			debug_log('[Abuse] log_email_abuse', $submission->get_posted_data());
	}
}
