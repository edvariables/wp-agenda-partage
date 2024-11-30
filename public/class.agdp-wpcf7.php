<?php

/**
 * AgendaPartage -> WPCF7
 * Tools for WPCF7
 */
class Agdp_WPCF7 {
	
	private static $initiated = false;
	
	const icon = 'feedback';

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
		
		add_filter( 'wpcf7_form_class_attr', array(static::class, 'on_wpcf7_form_class_attr_cb'), 10, 1 ); 
		
		if( self::may_skip_recaptcha() ){
			// add_filter( 'wpcf7_load_js', '__return_false' );
			// add_filter( 'wpcf7_load_css', '__return_false' );
		}
		
		add_filter( 'wpcf7_spam', array(__CLASS__, 'wpcf7_spam_cb'), 10, 2);
		
		//wpcf7_before_send_mail : mise à jour des données avant envoi (ou annulation) de mail
		add_filter( 'wpcf7_before_send_mail', array(__CLASS__, 'wpcf7_before_send_mail'), 10, 3);

		//Contact Form 7 hooks
		add_filter( 'wp_mail', array(__CLASS__, 'wp_mail_check_headers_cb'), 10,1);
		
		add_filter( 'wp_mail', array(__CLASS__, 'wp_mail'), 10,1);

		//Contrôle de l'envoi effectif des mails	
		add_filter('wpcf7_skip_mail', array(__CLASS__, 'wpcf7_skip_mail'), 10, 2);
		
		//Maintient de la connexion de l'utilisateur pendant l'envoi du mail
		// add_filter( 'wpcf7_verify_nonce', array(__CLASS__, 'wpcf7_verify_nonce_cb' ));	
		add_filter( 'wpcf7_verify_nonce', '__return_true' );
	}
	
	// define the wpcf7_skip_mail callback 
	public static function wpcf7_skip_mail( $skip_mail, $contact_form ){ 
		if($skip_mail
		|| ! empty( $_POST['nl-email'] ) //champ présent dans tous les formulaires d'abonnement
		)
			return true;
			
		return Agdp::$skip_mail;
	} 

	/**
	 * Registers js files.
	 */
	public static function register_plugin_js() {			
		
		if(Agdp::is_plugin_active('wpcf7-recaptcha/wpcf7-recaptcha.php')){
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
	
	/**
	 * Interception du formulaire avant que les shortcodes ne soient analysés.
	 * Affectation des valeurs par défaut.
	 */
 	public static function on_wpcf7_form_class_attr_cb( $form_class ) { 
		global $post;
		$form = WPCF7_ContactForm::get_current();
		// debug_log(__CLASS__.'::on_wpcf7_form_class_attr_cb() : ', $form->title());
		
		$preventdefault_reset = false;
		switch($form->id()){
			//formulaires de contact à propos d'un évènement ou d'un covoiturage
			case Agdp::get_option('agdpevent_message_contact_form_id') :
			case Agdp::get_option('contact_form_id') :
			case Agdp::get_option('admin_message_contact_form_id') :
				// debug_log(__CLASS__.'::on_wpcf7_form_class_attr_cb() : contact_form_id');
				if( isset($_REQUEST[Agdp_Evenement::postid_argument]) )
					Agdp_Evenement::wpcf7_contact_form_init_tags( $form );
				elseif( isset($_REQUEST[Agdp_Covoiturage::postid_argument]) )
					Agdp_Covoiturage::wpcf7_contact_form_init_tags( $form );
				elseif( isset($_REQUEST[AGDP_ARG_COMMENTID]) )
					Agdp_Comment::wpcf7_contact_form_init_tags( $form );
				elseif( $post ){
					if( $post->post_type === Agdp_Evenement::post_type )
						Agdp_Evenement::wpcf7_contact_form_init_tags( $form );
					elseif( $post->post_type === Agdp_Covoiturage::post_type )
						Agdp_Covoiturage::wpcf7_contact_form_init_tags( $form );
				}
				// else
					// debug_log(__CLASS__.'::on_wpcf7_form_class_attr_cb() : BUT NONE', $_REQUEST);
				$preventdefault_reset = true;
				break;
			case Agdp::get_option('agdpforum_subscribe_form_id') :
				$form_class = Agdp_Newsletter::on_wpcf7_form_class_attr_cb( $form_class );
				break;
			default:
				break;
		}
		if( $preventdefault_reset ){
			if( strpos($form_class, ' preventdefault-reset') === false)
				$form_class .= ' preventdefault-reset';
			else
				debug_log(__CLASS__.'::on_wpcf7_form_class_attr_cb() : appels multiples ! TODO');
		}
		return $form_class;
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

		$form_id = $contact_form->id();

		$is_newsletter_subscription = ! empty( $_POST['nl-email'] );
		if( ! $is_newsletter_subscription ){
			switch($form_id){
				case Agdp::get_option('agdpevent_edit_form_id') :
				case Agdp::get_option('covoiturage_edit_form_id') :
					break;
				default:
					if( self::check_submission_is_abuse($submission) ){
						$abort = true;
						return;
					}
					break;
			}
		}
		
		if( $is_newsletter_subscription ){
			Agdp_Newsletter::submit_subscription_form($contact_form, $abort, $submission);
		}
		else {
			switch($form_id){
				case Agdp::get_option('admin_message_contact_form_id') :
					Agdp_Evenement::change_email_recipient($contact_form);
					Agdp_Covoiturage::change_email_recipient($contact_form);
					break;
				case Agdp::get_option('agdpevent_edit_form_id') :
					Agdp_Evenement_Edit::submit_agdpevent_form($contact_form, $abort, $submission);
					break;
				case Agdp::get_option('covoiturage_edit_form_id') :
					Agdp_Covoiturage_Edit::submit_covoiturage_form($contact_form, $abort, $submission);
					break;
			}
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
		
		if(Agdp::maillog_enable()){
			$postarr = [
				'post_type' => Agdp_Maillog::post_type,
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
	
	
	/**
	 * Modifie le texte d'erreur 'spam' du wpcf7
	 * Le composant wpcf7-recaptacha  provoque une indication de spam lors de requêtes trop rapprochées.
	 */
	public static function wpcf7_spam_cb($spam, $submission){
		if($spam){
			$contact_form = $submission->get_contact_form();
			$messages = ($contact_form->get_properties())['messages'];
		
			$messages['spam'] = __("Désolé vous avez peut-être été trop rapide. Veuillez essayer à nouveau ou rechargez la page et recommencez.", AGDP_TAG);
				
			$contact_form->set_properties(array('messages' => $messages));
		}
		return $spam;
	}


	/**
	 * Correction de caractères spéciaux
	 */
	public static function email_specialchars($args){
		$args['subject'] = str_replace('&#039;', "'", $args['subject']);
		return $args;
	}
	

	/**
	 * Interception des envois de mail
	 */
	public static function wp_mail($args){
		if(array_key_exists('_wpcf7', $_POST))
			return self::wp_mail_wpcf7($args);
		
		return $args;
	}
	
	/**
	 * Interception des envois de mail du plugin wpcf7
	 */
	public static function wp_mail_wpcf7($args){

		$args = self::email_specialchars($args);
		
		$form_id = $_POST['_wpcf7'];
		
		switch($form_id){
			//Formulaire spécifique pour les évènements
			case Agdp::get_option('agdpevent_edit_form_id') :
				return Agdp_Evenement_Edit::wp_mail_emails_fields($args);
			//Formulaire spécifique pour les covoiturages
			case Agdp::get_option('covoiturage_edit_form_id') :
				return Agdp_Covoiturage_Edit::wp_mail_emails_fields($args);
				
			default:
				break;
		}
		
		return $args;
	}

	/**
	 * Recherche de wpcf7 dans un page->post_content
	 */
	public static function get_page_wpcf7($page){
		$posts = [];
		switch($page->ID){
			case Agdp::get_option('agenda_page_id'):
				$post_id = Agdp::get_option('agdpevent_edit_form_id');
				$posts[ $post_id.'' ] = get_post( $post_id );
				break;
			case Agdp::get_option('covoiturages_page_id'):
				$post_id = Agdp::get_option('covoiturage_edit_form_id');
				$posts[ $post_id.'' ] = get_post( $post_id );
				break;
			default:
		}
		
		$content = $page->post_content;
		
		$matches = [];
		$pattern = sprintf('/%s([0-9a-z]+)\"/', preg_quote('[contact-form-7 id="'));
		if( preg_match_all( $pattern, $content, $matches ) ){
			foreach( $matches[1] as $wpcf7_id ){
				if( is_numeric($wpcf7_id) )
					$post = get_post($wpcf7_id);
				else
					if( $wpcf7 = wpcf7_get_contact_form_by_hash($wpcf7_id) )
						$post = get_post( $wpcf7->id() );
				if( $post )
					$posts[ $post->ID.'' ] = $post;
			}
		}
		return $posts;
	}
	/**
	 * Retourne l'analyse de la page des évènements ou covoiturages
	 * Fonction appelable via Agdp_Evenement, Agdp_Covoiturage ou une page quelconque
	 */
	public static function get_diagram( $blog_diagram, $post ){
		
		$diagram = [
			'id' => $post->ID,
			'name' => $post->post_title,
		];
		
		$wpcf7 = wpcf7_contact_form( $post );
		if( $wpcf7 === null ){
			var_dump($post);
			return null;
		}
		
		$properties = $wpcf7->get_properties();
		$mail = [];
		$no_mail_sent = in_array($wpcf7->id(), [
			$agdpforum_subscribe_form_id = Agdp::get_option('agdpforum_subscribe_form_id'),
			$newsletter_subscribe_form_id = Agdp::get_option('newsletter_subscribe_form_id'),
			$covoiturage_edit_form_id = Agdp::get_option('covoiturage_edit_form_id'),
			$agdpevent_edit_form_id = Agdp::get_option('agdpevent_edit_form_id')] );
		foreach($properties['mail'] as $property => $value )
			switch($property){
				case 'recipient':
					if( $no_mail_sent ){
						switch( $wpcf7->id() ){
							case $agdpevent_edit_form_id :
								$mail[] = 'Genère un évènement';
								break;
							case $covoiturage_edit_form_id :
								$mail[] = 'Genère un covoiturage';
								break;
							case $agdpforum_subscribe_form_id :
								$mail[] = 'Abonnement';
								break;
							case $newsletter_subscribe_form_id :
								$mail[] = 'Abonnements aux lettres-infos';
								break;
						}
					}
					else{
						if( $dispatches = Agdp_Mailbox::get_emails_dispatch( false, false, $value ) ){
							foreach($dispatches as $email=>$dispatch)
								if( $email === $value ){
									$no_mail_sent = true;
									$mail[] = 'Genère un commentaire ou message';
									break;
								}
						}
						if( ! $no_mail_sent )
							$mail['Envoyé à'] = $value;
					}
					break;
				case 'additional_headers':
					$matches = [];
					if( ! $no_mail_sent ){
						if( preg_match( '/reply\-to\:\s?(.*)/i', $value, $matches ) )
							$mail['Répondre à '] = $matches[1];
					}
					break;
			}
		$diagram['mail'] = $mail;
		
		return $diagram;
	}
	/**
	 * Rendu Html d'un diagram
	 */
	public static function get_diagram_html( $post, $diagram = false, $blog_diagram = false ){
		
		if( ! $diagram ){
			if( ! $blog_diagram )
				throw new Exception('$blog_diagram doit être renseigné si $diagram ne l\'est pas.');
			$diagram = self::get_diagram( $blog_diagram, $post );
		}
		$admin_edit = is_admin() ? sprintf(' <a href="/wp-admin/admin.php?page=wpcf7&post=%d&action=edit">%s</a>'
			, $post->ID
			, Agdp::icon('edit show-mouse-over')
		) : '';
		
		$html = '';
		
		$html .= sprintf('<div>%s Formulaire %s%s</div>'
			, Agdp::icon(self::icon)
			, $post->post_title
			, $admin_edit
		);
			
		$icon = 'email-alt2';
		if( ! empty( $diagram['mail'] ) ){
			foreach( $diagram['mail'] as $property => $value ){
				if( is_numeric($property) ) $property = '';
				$html .= sprintf('<div>%s %s</div>'
					, Agdp::icon($icon)
					, trim(sprintf('%s %s %s'
						, $property
						, $value && $property ? ':' : ''
						, $value
				)));
			}
		}
		return $html;
	}

}
