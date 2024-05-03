<?php

/**
 * AgendaPartage -> Post -> Edit abstract
 * Extension des éditions des custom post type.
 * Uilisé par AgendaPartage_Evenement_Edit et AgendaPartage_Covoiturage_Edit
 * 
 */
abstract class AgendaPartage_Post_Edit_Abstract {

	private static $initiated = false;
	private static $changes_for_revision = null;
	public static $revision_fields = [];//Muse override

	const post_type_class = false;
	public static $post_types = [];

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;

			self::init_hooks();
		}
		self::$post_types[] = static::post_type_class::post_type;
	}

	/**
	 * Hook
	 */
	public static function init_hooks() {
		
		//wp_mail depuis Contact Form 7
		add_filter( 'wp_mail', array(__CLASS__, 'wp_mail'), 10,1);
		
		//Validation des valeurs
		add_filter( 'wpcf7_validate_text', array(__CLASS__, 'wpcf7_validate_fields_cb'), 10, 2);
		add_filter( 'wpcf7_validate_text*', array(__CLASS__, 'wpcf7_validate_fields_cb'), 10, 2);
		add_filter( 'wpcf7_validate_date', array(__CLASS__, 'wpcf7_validate_fields_cb'), 10, 2);
		add_filter( 'wpcf7_validate_date*', array(__CLASS__, 'wpcf7_validate_fields_cb'), 10, 2);
		add_filter( 'wpcf7_posted_data_text', array(__CLASS__, 'wpcf7_posted_data_fields_cb'), 10, 3);
		add_filter( 'wpcf7_posted_data_date', array(__CLASS__, 'wpcf7_posted_data_fields_cb'), 10, 3);
		
		add_filter( 'wpcf7_spam', array(__CLASS__, 'wpcf7_spam_cb'), 10, 2);
		
		//Fenêtre de réinitialisation de mot de passe
		add_action( 'resetpass_form', array(__CLASS__, 'resetpass_form' ));
		
		//Maintient de la connexion de l'utilisateur pendant l'envoi du mail
		// add_filter( 'wpcf7_verify_nonce', array(__CLASS__, 'wpcf7_verify_nonce_cb' ));	
		add_filter( 'wpcf7_verify_nonce', '__return_true' ); //TODO
	}
 	/////////////
	
	/**
	 * Retourne la classe enfant
	 */
	public static function get_static_class(){
		
		if( static::post_type_class )
			return static::post_type_class . '_Edit';
		if( ! isset($_POST['_wpcf7']) )
			return false;
		
		$form_id = $_POST['_wpcf7'];
		
		switch($form_id){
			//Formulaire spécifique pour les évènements ou covoiturages
			case AgendaPartage::get_option('agdpevent_edit_form_id') :
				return 'AgendaPartage_Evenement_Edit';
			case AgendaPartage::get_option('covoiturage_edit_form_id') :
				return 'AgendaPartage_Covoiturage_Edit';
				
			default:
				return false;
		}
	}
	/**
	 * Retourne la classe enfant
	 */
	public static function get_post_type_class(){
		if( $static_class = self::get_static_class() )
			return $static_class::post_type_class;
	}
	/////////////
	
	/**
	* Retourne le post actuel si c'est bien du bon type 
	*
	*/
	public static function get_post($post_id = false) {
		return self::get_post_type_class()::get_post($post_id);
	}
	
 	/**
	* Retourne faux si le post actuel de bon type a déjà été enregistré (ID différent de 0).
	*
	*/
	public static function is_new_post() {
		global $post;
 		if( ! ($post = self::get_post()))
 			return true;
		
		return ! $post->ID;
	}
	

	/**
	* Vérifie les nonce
	*
	*/
	public static function check_nonce() {
		foreach([AGDP_TAG . '-' . AGDP_COVOIT_SECRETCODE,
				AGDP_TAG . '-' . AGDP_EVENT_SECRETCODE,
				AGDP_TAG . '-send-email'] as $nonce){
			if ( isset( $_POST[$nonce] ) 
				&& ! wp_verify_nonce( $_POST[$nonce], $nonce ) 
			) {
				print 'Désolé, la clé de sécurité n\a pas été vérifiée.';
				exit;
			}
		}
	}

	/**
	 * Fenêtre de réinitialisation de mot de passe
	 */
	public static function resetpass_form( $user ){
		//insert html code
		// redirect_to
		if ( isset( $_REQUEST['redirect_to'] ) ) {
			$url = $_REQUEST['redirect_to'];
		}
		else
			$url = false;
		if( ! $url) {
			$url = get_home_url( AgendaPartage_User::get_current_or_default_blog_id($user), sprintf("wp-admin/"), 'admin' );
		}
		echo sprintf('<input type="hidden" name="%s" value="%s"/>', 'redirect_to', $url );
	}

	////////////////////////
	/**
	 * Interception du formulaire avant que les shortcodes ne soient analysés.
	 * Affectation des valeurs par défaut.
	 * Affectation des listes de taxonomies
	 */
 	public static function wpcf7_form_init_tags_cb( $form_class ) { 
		$form = WPCF7_ContactForm::get_current();
		$html = $form->prop('form');//avec shortcodes du wpcf7
		$post = get_post();
		
		$html = static::post_type_class::init_wpcf7_form_html( $html, $post );
		
		/** e-mail non-obligatoire si connecté **/
		if(($user = wp_get_current_user())
			&& $user->ID !== 0){
			// $html = preg_replace('/' . preg_quote('<span class="required">*</span>') . '(\s*\[email)\*/', '$1', $html);
			// var_dump(substr( preg_replace('/(\[email)\*/', '$1', $html), strpos($html, '[email')-30));
			// die();
			$html = preg_replace('/(\[email)\*/', '$1', $html);
		}
		
		/** reCaptcha */
		if( AgendaPartage_WPCF7::may_skip_recaptcha() ){
			//TODO
			// $html = preg_replace('/\[recaptcha[^\]]*[\]]/'
								// , ''
								// , $html);
		}
					
		$form->set_properties(array('form'=>$html));
		
		return $form_class;
	}
	
	/**
	* Validation des champs des formulaires WPCF7
	*/
	public static function wpcf7_validate_fields_cb( $result, $tag ) {
		return self::get_static_class()::wpcf7_validate_fields_cb( $result, $tag );
	}
	/**
	 * Correction des valeurs envoyées depuis le formulaire.
	 */
	public static function wpcf7_posted_data_fields_cb( $value, $value_orig, $tag ) {
		return self::get_static_class()::wpcf7_posted_data_fields_cb( $value, $value_orig, $tag );
	}
	
	/**
	 * Modifie le texte d'erreur 'spam' du wpcf7
	 * Le composant wpcf7-recaptacha  provoque une indication de spam lors de requêtes trop rapprochées.
	 */
	public static function wpcf7_spam_cb($spam, $submission){
		if($spam){
			$contact_form = $submission->get_contact_form();
			$messages = ($contact_form->get_properties())['messages'];
		
			$messages['spam'] = __("Désolé vous avez peut-être été trop rapide. Veuillez essayer à nouveau.", AGDP_TAG);
				
			$contact_form->set_properties(array('messages' => $messages));
		}
		return $spam;
	}
 	/////////////////////
 	// redirect email //

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
			//Formulaire spécifique pour les évènements ou covoiturages
			case AgendaPartage::get_option('agdpevent_edit_form_id') :
			case AgendaPartage::get_option('covoiturage_edit_form_id') :
				return self::wp_mail_emails_fields($args);
				
			default:
				break;
		}
		
		return $args;
	}

	/**
	 * Correction de caractères spéciaux
	 */
	public static function email_specialchars($args){
		$args['subject'] = str_replace('&#039;', "'", $args['subject']);
		return $args;
	}

	/**
	 * Redéfinit les adresses emails des pages d'évènements ou covoiturages vers le mail de l'organisateur ou, à défaut, vers l'auteur de la page.
	 * Le email2, email de copie, ne subit pas la redirection.
	 */
	private static function wp_mail_emails_fields($args){
		if( ! ($post = self::get_post()))
			return $args;
		$to_emails = parse_emails($args['to']);
		$headers_emails = parse_emails($args['headers']);
		$emails = array();
		//[ [source, header, name, user, domain], ]
		// 'user' in ['agdpevent', 'client', 'admin']
		//Dans la config du mail WPCF7, on a, par exemple, "To: [e-mail-ou-telephone]<client@agendapartage.net>"
		//on remplace client@agendapartage.net par l'email extrait de [e-mail-ou-telephone]
		//Ce qui veut dire que la forme complète "[e-mail-ou-telephone]<client@agendapartage.net>" doit apparaitre pour deviner l'email du client
		foreach (array_merge($to_emails, $headers_emails) as $value) {
			if($value['domain'] === AGDP_EMAIL_DOMAIN
			&& ! array_key_exists($value['user'], $emails)) {
				switch($value['user']){
					case 'agdpevent':
					case 'covoiturage':
						$emails[$value['user']] = static::get_post_email_address($post);
						break;
					case 'admin':
						$emails[$value['user']] = get_bloginfo('admin_email');
						break;
					case 'client':
						$real_email = parse_emails($value['name']);
						if(count($real_email)){
							$emails['client'] = $real_email[0]['email'];
						}
						else {
							//on peut être ici si, dans le formulaire, on a "client@agendapartage.net" et non "[e-mail-ou-telephone]<client@agendapartage.net>"
							//TODO bof
							$real_email = parse_emails($_POST['e-mail-ou-telephone']);
							if(count($real_email)){
								$emails['client'] = $real_email[0]['email'];
							}	
						}
						break;
					case 'user':
					case 'utilisateur':
						if(is_user_logged_in()){
							global $current_user;
							wp_get_current_user();
							$email = $current_user->user_email;
							if( is_email($email)){
								$user_name = $current_user->display_name;
								$site_title = get_bloginfo( 'name', 'display' );

								$user_emails = parse_emails($email);

								$emails['user'] = $user_emails[0]['email'];
							}
						}
						break;
					}
			}
		}

		//Cherche à détecter si on est dans le mail de copie
		if(isset($wpcf7_mailcounter))
			$wpcf7_mailcounter++;
		else
			$wpcf7_mailcounter = 1;

		if( empty( $emails['client'] )
		|| ! is_email($emails['client'])
		|| ( $emails['client'] == 'client@agendapartage.net' ) ){//TODO
			// 2ème mail à destination du client mais email invalide
			if($wpcf7_mailcounter >= 2) {
				//Cancels email without noisy error and clear log
				$args["to"] = '';
				$args["subject"] = 'client précédent sans email';
				$args["message"] = '';
				$args['headers'] = '';
				return $args;	
			}

			$emails['client'] = 'NePasRepondre@agendapartage.net';
		}

		foreach ($to_emails as $email_data) {
			if(array_key_exists($email_data['user'], $emails)
			&& $emails[$email_data['user']]) {
				$args['to'] = str_ireplace($email_data['user'].'@'.$email_data['domain'], $emails[$email_data['user']], $args['to']);
				$args['message'] = str_ireplace($email_data['user'].'@'.$email_data['domain'], $emails[$email_data['user']], $args['message']);
			}
		}
		foreach ($headers_emails as $email_data) {
			if(array_key_exists($email_data['user'], $emails)
			&& $emails[$email_data['user']]) {
				$args['headers'] = str_ireplace($email_data['user'].'@'.$email_data['domain'], $emails[$email_data['user']], $args['headers']);
				$args['message'] = str_ireplace($email_data['user'].'@'.$email_data['domain'], $emails[$email_data['user']], $args['message']);
			}
		}

		//remplace "XY<commande@agendapartage.net>" par "XY@agendapartage.net<NePasRepondre@agendapartage.net>"
		/*$args['headers'] = str_ireplace(
								  '"<commande@'.AGDP_EMAIL_DOMAIN.'>'
								, '.'.AGDP_EMAIL_DOMAIN.'"<commande@'.AGDP_EMAIL_DOMAIN.'>'
								, $args['headers']);
		$args['headers'] = str_ireplace(
								  AGDP_EMAIL_DOMAIN.'.'.AGDP_EMAIL_DOMAIN
								, AGDP_EMAIL_DOMAIN
								, $args['headers']);*/
		/*print_r($args['headers']);
		echo "\n";
		print_r( preg_replace('/@?([\w.]*)("?\<commande@agendapartage.net\>)/', '.$1@agendapartage.net$2', $args['headers']));
		echo "\n";
		echo array_flip(array_filter(get_defined_constants(true)['pcre'], function($v) { return is_integer($v); }))[preg_last_error()];
		die();*/
		$args['headers'] = preg_replace('/@?([\w.]*)("?\<commande@agendapartage.net\>)/', '_$1@agendapartage.net$2', $args['headers']);


		if($post
		&& $password_message = self::new_password_link($post->post_author)){
			$args['message'] .= "\r\n<br>" . $password_message;
		}
		return $args;
	}
	
	
	/**
	 * Get code secret from Ajax query, redirect to post url
	 */
	public static function on_wp_ajax_post_code_secret_cb() {
		$ajax_response = '0';
		if(array_key_exists("post_id", $_POST)){
			$post = get_post($_POST['post_id']);
			if($post->post_type != static::post_type_class::post_type)
				return;
			$input = $_POST[static::post_type_class::secretcode_argument];
			$meta_key = static::post_type_class::field_prefix . static::post_type_class::secretcode_argument;
			$codesecret = static::post_type_class::get_post_meta($post, $meta_key, true);
			if(strcasecmp( $codesecret, $input) == 0){
				//TODO : transient plutot que dans l'url
				$url = static::post_type_class::get_post_permalink($post, static::post_type_class::secretcode_argument . '=' . $codesecret);
				$ajax_response = sprintf('redir:%s', $url);
			}
			else{
				$ajax_response = '<div class="alerte">Code incorrect</div>'/* .$codesecret . '.'.$input */;
			}
		}
		
		// Make your array as json
		wp_send_json($ajax_response);
	 
		// Don't forget to stop execution afterward.
		wp_die();
	}
	
	/***********
	 * REVISIONS
	 * Mémorise les besoins de création d'une révision d'un post si certains champs sont modifiés
	 */
	public static function save_post_revision($post, $new_data){
		if(is_numeric($post)){
			$post_id = $post;
			$post = get_post($post_id);
		}
		elseif($post)
			$post_id = $post->ID;
		if(!$post)
			return false;
		
		
		$post_revisions = wp_get_post_revisions($post_id);
					
		$changes = [];
		$old_values = get_post_meta($post_id, '', true);
		foreach(self::$revision_fields as $field){
			$old_value = isset($old_values[ $field ]) ? $old_values[ $field ] : null;
			if(is_array($old_value))
				$old_value = implode(', ', $old_value);
			$new_value = isset($new_data['meta_input']) 
				? (isset($new_data['meta_input'][$field]) ? $new_data['meta_input'][$field] : null)
				: (isset($new_data[$field]) ? $new_data[$field] : null);
			
			if(count($post_revisions) === 0
			|| $old_value != $new_value){
				$changes[$field] = $new_value;
			}
		}
		
		self::$changes_for_revision = $changes;
		
		// error_log('self::$changes_for_revision : ' . var_export(self::$changes_for_revision, true));
		
		if( count($changes) ){
			
			add_filter( 'wp_save_post_revision_check_for_changes', array(__CLASS__, 'wp_save_post_revision_check_for_changes'), 10, 3);
			add_filter('_wp_put_post_revision', array(__CLASS__, 'on_wp_put_post_revision_cb'), 10, 1);
			
			
		}
		return false;
	}
	/**
	 * Force la création d'une révision depuis l'appel de save_post_revision()
	 */
	public static function wp_save_post_revision_check_for_changes( bool $check_for_changes, WP_Post $latest_revision, WP_Post $post ){
		
		if($post->post_type != self::get_post_type_class()::post_type){
			
			// error_log('wp_save_post_revision_check_for_changes : $post->post_type = ' . var_export($post->post_type, true));
		
			return $check_for_changes;
		}
		
		// error_log('wp_save_post_revision_check_for_changes : ' . var_export(self::$changes_for_revision, true));
		
		if($check_for_changes
		&& self::$changes_for_revision
		&& count(self::$changes_for_revision))
			return false;
		return $check_for_changes;
	}
	
	/**
	 * Complète les informations d'une révision
	 */
	public static function on_wp_put_post_revision_cb( int $revision_id ){
		
		// error_log('on_wp_put_post_revision_cb (' . $revision_id . ') : ' . var_export(self::$changes_for_revision, true));
		
		if(! self::$changes_for_revision
		|| count(self::$changes_for_revision) === 0)
			return;
			
		$revision = get_post($revision_id);
		$post_id = $revision->post_parent;
		$post = get_post($post_id);
		if($post->post_type != self::get_post_type_class()::post_type)
			return;
		
		// $post_revisions = wp_get_post_revisions($post_id);
		
		// if(count($post_revisions) === 1){
			// foreach($post_revisions as $a_revision){
				// $first_revision = $a_revision;
				// break;
			// }
			// error_log('first_revision : ' . var_export($first_revision->ID, true));
		// }
		$changes = self::$changes_for_revision;
		
		// error_log('on_wp_put_post_revision_cb (' . $revision_id . ') changes : ' . var_export($changes, true));
		foreach($changes as $field => $value){
			if( ! in_array( $field, self::$revision_fields ))
				continue;
			if(is_array($value))
				$value = implode(', ', $value);
			// error_log('update_metadata('.$revision_id.') : ' . $field . ' = ' . var_export($value, true));
			update_metadata( 'post', $revision_id, $field, $value );
		}
		self::$changes_for_revision = null;
		return;
	}
	/*
	 * /REVISIONS
	 ************/
}
?>