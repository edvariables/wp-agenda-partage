<?php

/**
 * AgendaPartage -> Newsletter
 * Custom post type for WordPress.
 * 
 * Définition du Post Type agdpnl
 * Mise en forme du formulaire Lettre-info
 *
 * Voir aussi AgendaPartage_Admin_Newsletter
 */
class AgendaPartage_Newsletter {

	const post_type = 'agdpnl';

	// const user_role = 'author';

	private static $initiated = false;
	private static $sending_email = false;

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
		add_filter( 'wpcf7_form_class_attr', array(__CLASS__, 'on_wpcf7_form_class_attr_cb'), 10, 1 ); 
	}
	
	public static function subscribe_periods(){
		return array(
			'0' => 'Aucun abonnement',
			'M' => 'Tous les mois',
			'2W' => 'Tous les quinze jours',
			'W' => 'Toutes les semaines',
		);
	}
	
	public static function subscribe_period_name($period, $false_if_none = false){
		$periods = self::subscribe_periods();
		if( isset($periods[$period]) )
			return $periods[$period];
		if($empty_if_none)
			return false;
		foreach( $periods as $period => $label)
			return $label;
	}
	
	public static function get_subscription_meta_key($newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		return sprintf('%s_subscr_%s', self::post_type, $newsletter->ID);
	}
	
	public static function get_mailing_meta_key($newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		return sprintf('%s_mailing_%s', self::post_type, $newsletter->ID);
	}
	
	public static function get_next_date($period, $newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		$today = strtotime(date('Y-m-d H:i:s'));
		switch($period){
			case 'M':
				//TODO et le 28 ?
				if(date('d', $today) > 28){
					$date = strtotime(date('Y-m-d + 4 day'));
					return strtotime(date('Y-m-28', $date));
				}
				return strtotime(date('Y-m-28', $today));
			case '2W':
				//TODO et le 28 ?
				if(date('d', $today) > 15){
					$date = strtotime(date('Y-m-d + 20 day'));
					return strtotime(date('Y-m-1', $date));
				}
				if(date('d', $today) > 1){
					return strtotime(date('Y-m-15', $today));
				}
				return $today;
			case 'W':
				//TODO et le 28 ?
				if(date('d', $today) > 22){
					$date = strtotime(date('Y-m-d + 20 day'));
					return strtotime(date('Y-m-1', $date));
				}
				if(date('d', $today) > 15){
					return strtotime(date('Y-m-22', $today));
				}
				if(date('d', $today) > 8){
					return strtotime(date('Y-m-15', $today));
				}
				if(date('d', $today) > 1){
					return strtotime(date('Y-m-8', $today));
				}
				return $today;
			default:
				return null;
		}
	}

	/**
	 * Interception du formulaire avant que les shortcodes ne soient analysés.
	 * Affectation des valeurs par défaut.
	 */
 	public static function on_wpcf7_form_class_attr_cb( $form_class ) { 
		$form = WPCF7_ContactForm::get_current();
		switch($form->id()){
			case AgendaPartage::get_option('newsletter_events_register_form_id') :
				self::wpcf7_newsletter_form_init_tags( $form );
				$form_class .= ' preventdefault-reset';
				break;
			default:
				break;
		}
		return $form_class;
	}
	
 	private static function wpcf7_newsletter_form_init_tags( $form ) { 
		
		$form = WPCF7_ContactForm::get_current();
		$html = $form->prop('form');//avec shortcodes du wpcf7
		
		$email = self::get_email();
		
		/** périodicité de l'abonnement **/
		$input_name = 'nl-period';
		$subscribe_periods = self::subscribe_periods();
		
		if(isset($_REQUEST['action']))
			switch($_REQUEST['action']){
				case 'unsubscribe':
				case 'desinscription':
					$user_subscription = '0';
					$subscribe_periods['0'] = 'Désinscription à valider';
					break;
				default:
					break;
			}
		if( ! isset($user_subscription))
			$user_subscription = self::get_subscription($email);
		if( ! $user_subscription)
			$user_subscription = '0';
		
		$checkboxes = '';
		$selected = '';
		$index = 0;
		foreach( $subscribe_periods as $subscribe_code => $label){
			$checkboxes .= sprintf(' "%s|%d"', $label, $subscribe_code);
			if($user_subscription == $subscribe_code){
				$selected = sprintf('default:%d', $index+1);
			}
			$index++;
		}
		
		$html = preg_replace('/\[(radio\s+'.$input_name.')[^\]]*[\]]/'
							, sprintf('[$1 %s use_label_element %s]'
								, $selected
								, $checkboxes)
							, $html);
		
		/** email **/
		$input_name = 'nl-email';
		if($email){
			$html = preg_replace('/\[(((email|text)\*?)\s+'.$input_name.'[^\]]*)[\]]/'
								, sprintf('[$1 value="%s"]'
									, $email)
								, $html);
		}
		
		/** Create account **/
		if( self::get_current_user()){
			$html = preg_replace('/\<div class="if-not-connected">[\s\S]*\<\/div\>/'
								, ''
								, $html);
		}
		
		/** reCaptcha */
		if( AgendaPartage::may_skip_recaptcha() ){
			//TODO
			// $html = preg_replace('/\[recaptcha[^\]]*[\]]/'
								// , ''
								// , $html);
		}
					
		$form->set_properties(array('form'=>$html));
				
	}
	
	/**
	 * utilisateur
	 */
	public static function get_current_user(){
		$current_user = wp_get_current_user();
		if($current_user && $current_user->ID){
			return $current_user;
		}
		return false;
	}
	public static function get_user_email(){
		$current_user = self::get_current_user();
		if($current_user){
			$email = $current_user->data->user_email;
			if(is_email($email))
				return $email;
		}
		return false;
	}
	public static function get_email(){
		if(isset($_REQUEST['email'])){
			$email = $_REQUEST['email'];
			if(is_email($email))
				return $email;
		}
		return self::get_user_email();
	}
	
	public static function get_newsletter($newsletter = false){
		if(!$newsletter)
			$newsletter = AgendaPartage::get_option('newsletter_post_id');
		$newsletter = get_post($newsletter);
		if(is_a($newsletter, 'WP_Post')
		&& $newsletter->post_type == AgendaPartage_Newsletter::post_type)
			return $newsletter;
		return false;
	}
	
	public static function create_subscriber_user($email, $user_name, $send_email = true){
		
		$user_id = email_exists( $email );
		if($user_id){
			return new WP_User($user_id);
		}
		
		$user = AgendaPartage_User::create_user_for_agdpevent($email, $user_name, false, false, 'subscriber');
		
		if( ! $user)
			return false;

		if($send_email)
			$html = AgendaPartage_User::send_welcome_email($user, false, false, true);
		return $user;
	}
	
	/**
	 * Option d'abonnement de l'utilisateur
	 */
	public static function get_user_subscription(){
		return self::get_subscription(self::get_user_email());
	}
	
	public static function get_subscription( $email, $newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		
		$user_id = email_exists( $email );
		if( ! $user_id)
			return false;
		
		$meta_name = self::get_subscription_meta_key($newsletter);
		$meta_value = get_user_meta($user_id, $meta_name, true);
		return $meta_value;
	}
	public static function remove_subscription($email, $newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		
		$user_id = email_exists( $email );
		if( ! $user_id)
			return true;
		
		$meta_name = self::get_subscription_meta_key($newsletter);
		delete_user_meta($user_id, $meta_name, true);
		
		//TODO remove user si jamais connecté
		
		return true;
	}
	public static function update_subscription($email, $period, $newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		
		$user_id = email_exists( $email );
		if( ! $user_id){
			if($period == '0')
				return true;
			$user = self::create_subscriber_user($email, false, false);
			if( ! $user )
				return false;
			$user_id = $user->ID;
		}
		$meta_name = self::get_subscription_meta_key($newsletter);
		update_user_meta($user_id, $meta_name, $period);
		return true;
	}
	
	/***********************************************************/

	/**
	 * Mise à jour ou création d'un abonnement à la newsletter
	 */
	public static function submit_subscription_form($contact_form, &$abort, $submission){
		$error_message = false;
		
		$inputs = $submission->get_posted_data();
		// nl-email, nl-period, nl-create-user, nl-user-name
				
		$newsletter = self::get_newsletter();
		
		$subscribe_periods = self::subscribe_periods();
		
		$email = sanitize_email( $inputs['nl-email'] );
		if(! is_email($email)){
			$abort = true;
			$error_message = sprintf('Désolé, nous ne reconnaissons pas le format de votre adresse (%s).', $inputs['nl-email']);
			$submission->set_response($error_message);
			return false;
		}
		
		$period = $inputs['nl-period'];
		
		if(is_array($period))
			$period = count($period) ? $period[0] : '0';
		//$period vaut la clé si elle est numérique sinon le libellé
		if( ! ($found = is_numeric($period)))
			foreach($subscribe_periods as $key=>$label)
				if( $found = ($label == $period) ){
					$period = $key;
					break;
				}
		if( ! $found){
			$period = '0';
		}

		/** create user */
		$user_is_new = false;
		$create_user = isset($inputs['nl-create-user'])
			&& $inputs['nl-create-user']
			&& ( ! is_array($inputs['nl-create-user'])
				|| (count($inputs['nl-create-user'])
					&& $inputs['nl-create-user'][0]));
		if( $create_user ){
			if( ! email_exists( $email ) ) {
				$user_is_new = true;
				$user_name = $inputs['nl-user-name'];
				$user = self::create_subscriber_user( $email, $user_name);
				if( ! $user ){
					$abort = true;
					$error_message = sprintf('Désolé, une erreur est survenue, nous n\'avons pas pu créer votre compte (%s).', $email);
					$submission->set_response($error_message);
					return false;
				}
			}
		}

		
		$messages = ($contact_form->get_properties())['messages'];
		$messages['mail_sent_ng'] = sprintf("%s\r\nDésolé, une erreur est survenue, la modification de votre inscription n'a pas fonctionné.", $messages['mail_sent_ng']);
		
		if($period === '0'){
			if( ! self::remove_subscription( $email, $newsletter) ) {
				$abort = true;
				$error_message = sprintf('Désolé, une erreur est survenue, la suppression de votre abonnement n\'a pas été enregistrée.');
				$submission->set_response($error_message);
				return false;
				
			}
			if($user_is_new)
				$messages['mail_sent_ok'] = sprintf("Votre compte %s a été créé, vous allez recevoir un e-mail de connexion.\r\nVous n'avez aucun abonnement à la lettre-info.", $email);
			elseif( $create_user )
				$messages['mail_sent_ok'] = sprintf("Un compte existe déjà avec cette adresse %s. Vous pouvez vous connecter.\r\nDemandez un nouveau mot de passe si vous l'avez oublié.", $email);
			else
				$messages['mail_sent_ok'] = sprintf('L\'adresse %s n\'est plus inscrite à la lettre-info. Bonne continuation.', $email);
		}
		else {
			if( ! self::update_subscription( $email, $period, $newsletter ) ) {
				$abort = true;
				$error_message = sprintf('Désolé, une erreur est survenue, la modification de votre inscription n\'a pas été enregistrée.');
				$submission->set_response($error_message);
				return false;
			}
			$messages['mail_sent_ok'] = sprintf('L\'adresse %s est désormais inscrite à la lettre-info "%s".', $email, $subscribe_periods[$period]);
			if($user_is_new)
				$messages['mail_sent_ok'] .= sprintf("\r\nVotre compte %s a été créé, vous allez recevoir un e-mail de connexion. Bienvenue.", $email);
			elseif( $create_user )
				$messages['mail_sent_ok'] .= sprintf("\r\nUn compte existe déjà avec cette adresse %s. Vous pouvez vous connecter.\r\nDemandez un nouveau mot de passe si vous l'avez oublié.", $email);
		}
		
		$send_newsletter_now = isset($inputs['nl-send_newsletter-now']);
		
		if($send_newsletter_now){
			if(self::send_email($newsletter, 'now', $email))
				$messages['mail_sent_ok'] .= sprintf("\r\nLa lettre-info a été envoyée.", $email);
			else
				$messages['mail_sent_ok'] .= sprintf("\r\nDésolé, la lettre-info n'a pas pu être envoyée.", $email);
		}
		
		$contact_form->set_properties(array('messages' => $messages));
		
		
		return true;
	}
	
	/**
	 * Send email
	 */
	 public static function send_email($newsletter, $period, $emails){
		$newsletter = self::get_newsletter($newsletter);
		
		self::$sending_email = true;
		
		$subject = get_the_title(false, false, $newsletter);
		if( ! $subject){
			$subject = $newsletter->post_title;
			if( ! $subject){
				debug_log($newsletter);
				return false;
			}
		}
		$subject = sprintf('[%s] %s', get_bloginfo( 'name', 'display' ), $subject);
		$message = get_the_content(false, false, $newsletter);
		$message = '<div style=\'white-space: pre\'>' . do_shortcode( $message ) . '</div>';
		
		if(is_array($emails))
			$to = implode('; ', $emails);
		else
			if( ! ($to = sanitize_email($emails)) )
				return false;
		
		$headers = array();
		$attachments = array();
		
		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-type: text/html; charset=utf-8';
		$headers[] = 'Content-Transfer-Encoding: quoted-printable';

		// if($period == 'test'){
			// $success = true;
			// debug_log_clear($subject, $message);
			// AgendaPartage_Admin::add_admin_notice($message, 'info');
		// }
		// else
		if($success = wp_mail( $to
			, '=?UTF-8?B?' . base64_encode($subject). '?='
			, $message
			, $headers, $attachments )){
			if(class_exists('AgendaPartage_Admin', false))
				AgendaPartage_Admin::add_admin_notice(sprintf("La lettre-info a été envoyé à %d destinataire(s).", count($emails)), 'info');
		}
		else{
			if(class_exists('AgendaPartage_Admin', false))
				AgendaPartage_Admin::add_admin_notice(sprintf("L\'e-mail n\'a pas pu être envoyé"), 'error');
		}
		
		self::$sending_email = false;
		
		return $success;
	 }
	 /**
	  *
	  */
	 public static function is_sending_email(){
		 return self::$sending_email;
	 }
}
