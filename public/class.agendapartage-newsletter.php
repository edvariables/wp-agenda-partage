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
	const taxonomy_period = 'period';

	const cron_hook = 'agdpnl_cron_hook';

	const default_mailing_hour = 2;
	
	// const user_role = 'author';

	private static $initiated = false;
	private static $sending_email = false;
	private static $sending_email_data = false;

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
		add_filter( 'wpcf7_form_class_attr', array(__CLASS__, 'on_wpcf7_form_class_attr_cb'), 10, 1 ); 
		
		add_action( 'wp_ajax_agdpnl_get_subscription', array(__CLASS__, 'on_wp_ajax_agdpnl_get_subscription') );
		add_action( 'wp_ajax_nopriv_agdpnl_get_subscription', array(__CLASS__, 'on_wp_ajax_agdpnl_get_subscription') );
		
		add_action( self::cron_hook, array(__CLASS__, 'on_cron_exec') );
		
		self::init_cron(); //SIC : register_activation_hook( 'AgendaPartage_Newsletter', 'init_cron'); ne suffit pas
	}
	/*
	 **/
	 
	/**
	 * Retourne l'adresse d'expéditeur des mails 
	 */
	public static function get_mail_sender(){
		$email = get_bloginfo('admin_email');
		return $email;
	}
	 
	/**
	 * Retourne l'adresse à laquelle on envoie les mails groupés avec destinataires en 'bcc'
	 */
	public static function get_bcc_mail_sender(){
		$email = self::get_mail_sender();
		return $email;
	}
	 
	/**
	 * Retourne l'adresse de réponse à laquelle on envoie les mails groupés avec destinataires en 'bcc'
	 */
	public static function get_replyto_mail_sender(){
		$email = self::get_mail_sender();
		return $email;
	}
	 
	/**
	 * Retourne les périodes d'abonnement possibles
	 */
	public static function subscription_periods($newsletter = false){
		if($newsletter === false){
			$terms = get_terms( array( 'taxonomy' => AgendaPartage_Newsletter::taxonomy_period, 'hide_empty' => false) );
		} else {
			$newsletter = self::get_newsletter($newsletter);
			$terms = wp_get_post_terms($newsletter->ID, AgendaPartage_Newsletter::taxonomy_period);
		
			if( ! $terms || count($terms) === 0)
				$terms = get_terms( array( 'taxonomy' => AgendaPartage_Newsletter::taxonomy_period,'hide_empty' => false) );
		
		}
		$periods = [];
		foreach($terms as $term_id => $term)
			if( is_a ($term, 'WP_Term') )
				$periods[$term->slug] = $term->name;
			else
				$periods[$term_id] = $term;
		// debug_log('$periods[$term_id] = $term', $periods, $terms);
		return $periods;
	}
	 
	/**
	 * Retourne les libellés des périodes d'abonnement possibles
	 */
	public static function subscription_period_name($period, $false_if_none = false){
		$periods = self::subscription_periods();
		if( isset($periods[$period]) )
			return $periods[$period];
		if($false_if_none)
			return false;
		foreach( $periods as $period => $label)
			return $label;
	}
	
	public static function get_subscription_meta_key($newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		return sprintf('%s_subscr_%d_%d', self::post_type, get_current_blog_id(), $newsletter->ID);
	}
	
	public static function get_mailing_meta_key($newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		return sprintf('%s_mailing_%d_%d', self::post_type, get_current_blog_id(), $newsletter->ID);
	}
	
	/**
	 * Returns true if post_status == 'publish' && meta['mailing-enable'] == true
	 */
	 public static function is_active($newsletter){
		$newsletter = self::get_newsletter($newsletter);
		if( $newsletter->post_status !== 'publish')
			return false;
		return get_post_meta($newsletter->ID, 'mailing-enable', true) == 1;
	}
	
	/**
	 * Returns array of ID=>post_title
	 */
	 public static function get_newsletters_names(){
		$newsletters = [];
		foreach( get_posts([
			'post_type' => AgendaPartage_Newsletter::post_type
			, 'fields' => 'post_title'
			]) as $post)
			$newsletters[ $post->ID . '' ] = $post->post_title;
		return $newsletters;
	}
	
	/**
	 * Returns posts where post_status == 'publish' && meta['mailing-enable'] == true
	 */
	 public static function get_active_newsletters(){
		$posts = [];
		foreach( get_posts([
			'post_type' => AgendaPartage_Newsletter::post_type
			, 'post_status' => 'publish'
			, 'meta_key' => 'mailing-enable'
			, 'meta_value' => '1'
			, 'meta_compare' => '='
			]) as $post)
			$posts[$post->ID . ''] = $post;
		return $posts;
	}
	
	/**
	 * Retourne l'heure d'envoi 
	 */
	public static function get_mailing_hour($newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		
		$meta_name = 'mailing-hour';
		$value = get_post_meta($newsletter->ID, $meta_name, true);
		if($value)
			return (int)$value;
		return self::default_mailing_hour;
	}
	
	/**
	 * Enregistre la prochaine date d'envoi pour une période donnée
	 *
	 * $period : false|M|2W|W
	 *     si false, cherche toutes les périodes dont la prochaine date est aujourd'hui ou avant
	 */
	public static function set_next_date($period, $newsletter, $next_date){
		if( $period === 'none')
			throw new Exception('set_next_date("none") ne devrait pas être.');
		
		if( ! $period){
			$today = strtotime(wp_date('Y-m-d'));
			foreach(self::subscription_periods($newsletter) as $period=>$period_name)
				if( $period && $period !== 'none'){
					if( self::get_next_date( $period, $newsletter ) <= $today)
						self::set_next_date($period, $newsletter, $next_date);
				}
			return;
		}
		$newsletter = self::get_newsletter($newsletter);
		$meta_name = sprintf('next_date_%s', $period);
		if( is_int($next_date) )
			$next_date = date('Y-m-d', $next_date);
		update_post_meta($newsletter->ID, $meta_name, $next_date);
	}
	/**
	 * Retourne la prochaine date d'envoi pour une période donnée
	 */
	public static function get_next_date($period = false, $newsletter = false, $after_date = false){
		$newsletter = self::get_newsletter($newsletter);
		if( ! $newsletter )
			return false;
		if( ! $period){
			$min_date = 0;
			foreach(self::subscription_periods($newsletter) as $period=>$period_name)
				if( $period && $period !== 'none')
					if( $min_date === 0 )
						$min_date = self::get_next_date($period, $newsletter, $after_date);
					else
						$min_date = min( $min_date, self::get_next_date($period, $newsletter, $after_date));
			return $min_date;
		}
		
		if( $after_date )
			$today = strtotime(wp_date('Y-m-d', $after_date) . ' + 1 day');
		else
			$today = strtotime(wp_date('Y-m-d'));

		if( ! $after_date ){
			$meta_name = sprintf('next_date_%s', $period);
			$value = get_post_meta($newsletter->ID, $meta_name, true);
			if($value){
				$value = strtotime($value);
				//TODO 
				// $value respecte les règles ci-dessous ?
				if($value >= $today)
					return $value;
			}
		}
		
		switch($period){
				
			case 'd':
				return $today;
				
			case 'm':
				$month_day = get_post_meta($newsletter->ID, 'mailing-month-day', true);
				if( $month_day === '')
					return $today;
				if(wp_date('d', $today) > $month_day){
					if($month_day > 28
					&& wp_date('m', strtotime(wp_date('Y-m-' . $month_day, $today)) )
						!=  wp_date('m', $today) )
						$date = strtotime(wp_date('Y-m-d', $today));
					else
						$date = strtotime(wp_date('Y-m-d', $today) . ' + 28 day');
					return strtotime(wp_date('Y-m-' . $month_day, $date));
				}
				return strtotime(wp_date('Y-m-' . $month_day, $today));
				
			case '2w':
				//TODO et le 28 ?
				$today_month_day = wp_date('d', $today);
				$half2_month_day = get_post_meta($newsletter->ID, 'mailing-2W2-day', true);
				$half1_month_day = get_post_meta($newsletter->ID, 'mailing-2W1-day', true);
				if( $half1_month_day === '' || $half2_month_day === '')
					return $today;
				if($today_month_day > $half2_month_day){
					$date = strtotime(wp_date('Y-m-d', $today) . ' + 28 day');
					return strtotime(wp_date('Y-m-' . $half1_month_day, $date));
				}
				if($today_month_day > $half1_month_day)
					return strtotime(wp_date('Y-m-' . $half2_month_day, $today));
				return strtotime(wp_date('Y-m-' . $half1_month_day, $today));
				
			case 'w':
				$week_day = get_post_meta($newsletter->ID, 'mailing-week-day', true);
				if( $week_day === '')
					return $today;
				$today_week_day = wp_date('w', $today);
				if( $week_day >= $today_week_day )
					$date = strtotime(wp_date('Y-m-d', $today) . ' + ' . ($week_day - $today_week_day) . ' day');
				else
					$date = strtotime(wp_date('Y-m-d', $today) . ' + ' . ($week_day - $today_week_day + 7) . ' day');
			
				return $date;
				
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
		$current_nl = self::get_newsletter();
		$current_nl_id = $current_nl ? $current_nl->ID : 0;
		
		$form = WPCF7_ContactForm::get_current();
		$html = $form->prop('form');//avec shortcodes du wpcf7
		
		$email = self::get_email();
		
		/** périodicité de l'abonnement **/
		$input_name = 'nl-period';
		$subscription_periods = self::subscription_periods(true);
		if(isset($_REQUEST['action']))
			switch($_REQUEST['action']){
				case 'unsubscribe':
				case 'desinscription':
					$user_subscription = 'none';
					$subscription_periods[$user_subscription] = 'Désinscription à valider';
					break;
				default:
					break;
			}
		if( ! isset($user_subscription))
			$user_subscription = self::get_subscription($email);
		if( ! $user_subscription)
			$user_subscription = 'none';
		
		$checkboxes = '';
		$selected = '';
		$index = 0;
		foreach( $subscription_periods as $subscribe_code => $label){
			$checkboxes .= sprintf(' "%s|%s"', $label, $subscribe_code);
			if($user_subscription == $subscribe_code){
				$selected = sprintf('default:%d', $index+1);
			}
			$index++;
		} 
		/** nl_id **/
		$html .= "<input class='hidden' name='".AGDP_ARG_NEWSLETTERID."' value='{$current_nl_id}'/>";
		
		
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
			$html = preg_replace('/\<div\s+class="if-not-connected"/'
								, '$0 style="display: none"'
								, $html);
		}
		
		/** reCaptcha */
		if( AgendaPartage_WPCF7::may_skip_recaptcha() ){
			//TODO
			// $html = preg_replace('/\[recaptcha[^\]]*[\]]/'
								// , ''
								// , $html);
		}
		
		/** admin **/
		if( current_user_can('manage_options')){
			$urls = [];
			$nls = self::get_newsletters_names();
			$basic_url = get_post_permalink();
					
			if( count($nls) > 1){
				$html .= '<br><br><div>';
				foreach($nls as $nl_id=>$nl_name)
					if($nl_id === $current_nl_id)
						$html .= "<br>Administratriceur, vous êtes sur la page de la lettre-info \"{$nl_name}\".";
				foreach($nls as $nl_id=>$nl_name)
					if($nl_id !== $current_nl_id){
						$url = add_query_arg( AGDP_ARG_NEWSLETTERID, $nl_id, $basic_url);
						$html .= "<br>Basculer vers la lettre-info \"<a href=\"{$url}\">{$nl_name}</a>\".";
					}				
				$html .= '</div>';
			}
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
			$email = trim($_REQUEST['email']);
			if(is_email($email))
				return $email;
		}
		return self::get_user_email();
	}
	
	public static function get_newsletter($newsletter = false){
		if( ! $newsletter || $newsletter === true){
			if( empty($_REQUEST[AGDP_ARG_NEWSLETTERID]))
				$newsletter = AgendaPartage::get_option('newsletter_post_id');
			else
				$newsletter = $_REQUEST[AGDP_ARG_NEWSLETTERID];
		}
		$newsletter = get_post($newsletter);
		if(is_a($newsletter, 'WP_Post')
		&& $newsletter->post_type == AgendaPartage_Newsletter::post_type)
			return $newsletter;
		return false;
	}
	
	/**
	 * Crée un utilisateur si nécessaire.
	 */
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
	
	/**
	 * Retourne le meta value d'abonnement pour l'utilisateur
	 */
	public static function get_subscription( $email, $newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		
		$user_id = email_exists( sanitize_email($email) );
		if( ! $user_id)
			return false;
		
		$meta_name = self::get_subscription_meta_key($newsletter);
		$meta_value = get_user_meta($user_id, $meta_name, true);
		return $meta_value;
	}
	/**
	 * Supprime le meta value d'abonnement pour l'utilisateur
	 */
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
	/**
	 * Ajoute ou met à jour le meta value d'abonnement pour l'utilisateur
	 */
	public static function update_subscription($email, $period, $newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		
		$user_id = email_exists( $email );
		if( ! $user_id){
			if( ! $period || $period == 'none')
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
	
	 
	/**
	 * Répond à une requête ajax sur l'abonnement d'une adresse email.
	 * cf public/js/agendapartage.js
	 */
	public static function on_wp_ajax_agdpnl_get_subscription(){
		
		if( ! AgendaPartage::check_nonce())
			wp_die();
		
		$ajax_response = '0';
		if(array_key_exists("post_id", $_POST)
		&& array_key_exists("email", $_POST)){
			$subscription = self::get_subscription($_POST['email'], $_POST['post_id']);
			if($subscription === false)
				$ajax_response = [];
			else
				$ajax_response = ['subscription' => $subscription, 'subscription_name' => self::subscription_period_name($subscription)];
			if( email_exists($_POST['email']))
				$ajax_response['is_user'] = true;
		}
		
		wp_send_json($ajax_response);
		
		wp_die();
	}
	
	/**
	 * Ajoute ou met à jour le meta value de l'utilisateur de l'état de l'envoi
	 */
	public static function set_user_mailing_status($newsletter, $user_id, $status){
		if(is_email($user_id))
			$user_id = email_exists($user_id);
		$mailing_status_meta_name = self::get_mailing_meta_key($newsletter) . '-status';
		update_user_meta($user_id, $mailing_status_meta_name, $status);
				
	}
	/**
	 * Supprime le meta value de l'utilisateur de l'état de l'envoi
	 */
	public static function delete_user_mailing_status($newsletter, $user_id){
		if(is_email($user_id))
			$user_id = email_exists($user_id);
		$mailing_status_meta_name = self::get_mailing_meta_key($newsletter) . '-status';
		delete_user_meta($user_id, $mailing_status_meta_name);
				
	}
	/**
	 * Retourne l'historique
	 */
	public static function get_user_mailings($email, $newsletter_id = false){
		
		$user_id = email_exists( $email );
		if( ! $user_id)
			return;
		
		$user_metas = get_user_meta($user_id, '', true);
		
		$meta_key_like = sprintf('%s_mailing_%d_', self::post_type, get_current_blog_id());
		$history = [];
		if($newsletter_id){
			$newsletter = self::get_newsletter($newsletter_id);
			$newsletters = [$newsletter->ID => $newsletter->post_title];
		}
		else
			$newsletters = self::get_newsletters_names();
		foreach($user_metas as $meta_key => $meta_value)
			if(str_starts_with($meta_key, $meta_key_like)){
				$newsletter_id = substr($meta_key, strlen($meta_key_like));
				if ( ! empty($newsletters[$newsletter_id]) )
					$history[ sprintf('%d|%s', $newsletter_id, $newsletters[$newsletter_id]) ] 
						= is_array($meta_value) ? implode(', ', $meta_value) : $meta_value;
			}
		return array_reverse($history, true);//TODO sort
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
		
		$subscription_periods = self::subscription_periods($newsletter);
		
		$email = sanitize_email( $inputs['nl-email'] );
		if(! is_email($email)){
			$abort = true;
			$error_message = sprintf('Désolé, nous ne reconnaissons pas le format de votre adresse (%s).', $inputs['nl-email']);
			$submission->set_response($error_message);
			return false;
		}
		
		$period = $inputs['nl-period'];
		
		if(is_array($period))
			$period = count($period) && $period[0] ? $period[0] : 'none';//wpcf7 is strange with first radio option
		
		if( ! ($found = is_numeric($period)))
			foreach($subscription_periods as $key=>$label)
				if( $found = ($label === $period) ){
					$period = $key;
					break;
				}
		if( ! $found)
			$period = 'none';

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
		
		if($period === 'none'){
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
			$messages['mail_sent_ok'] = sprintf('L\'adresse %s est désormais inscrite à la lettre-info "%s".', $email, $subscription_periods[$period]);
			if($user_is_new)
				$messages['mail_sent_ok'] .= sprintf("\r\nVotre compte %s a été créé, vous allez recevoir un e-mail de connexion. Bienvenue.", $email);
			elseif( $create_user )
				$messages['mail_sent_ok'] .= sprintf("\r\nUn compte existe déjà avec cette adresse %s. Vous pouvez vous connecter.\r\nDemandez un nouveau mot de passe si vous l'avez oublié.", $email);
		}
		
		$send_newsletter_now = isset($inputs['nl-send_newsletter-now']) && $inputs['nl-send_newsletter-now'][0];
		if($send_newsletter_now){
			if(self::send_email($newsletter, $email))
				$messages['mail_sent_ok'] .= sprintf("\r\nLa lettre-info a été envoyée.", $email);
			else
				$messages['mail_sent_ok'] .= sprintf("\r\nDésolé, la lettre-info n'a pas pu être envoyée.", $email);
		}
		
		$contact_form->set_properties(array('messages' => $messages));
		
		
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
		else
			return sprintf('Prochaine évaluation dans %s - %s'
					, wp_date('H:i:s', $cron_time - current_time('timestamp'))
					, wp_date('d/m/Y H:i:s', $cron_time)); 
	}
	
	/**
	 * Log l'état du cron
	 */
	public static function log_cron_state(){
		debug_log('[agdpnl-cron state]' . self::$cron_state);
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
			// debug_log('[agdpnl-init_cron] wp_schedule_single_event', date('H:i:s', $cron_time - time()));
		}
		if( $cron_time === false ){
			$cron_time = wp_schedule_event( time(), 'hourly', self::cron_hook );
			// debug_log('[agdpnl-init_cron] wp_schedule_event', $cron_time);
			register_deactivation_hook( __CLASS__, 'deactivate_cron' ); 
		}
		else {
			// debug_log('[agdpnl-init_cron] next in ' . date('H:i:s', $cron_time - time()));
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
	public static function cron_exec($simulate = false){
		$newsletters = self::get_active_newsletters();
		if( ! $newsletters || count($newsletters) === 0){
			self::deactivate_cron();
			self::$cron_state = '0|Aucune lettres-info actives';
			return;
		}
		$today = strtotime(wp_date('Y-m-d'));
		$hour = (int)wp_date('H');
		$all_subscribers = [];
		$newsletters_data = [];
		$next_dates = [];
		foreach($newsletters as $newsletter){
			$next_date = self::get_next_date(false, $newsletter);
			//TODO un mailing qui traine au delà de minuit
			if( $next_date > $today ){
				$next_dates[] = wp_date('d/m/Y', $next_date) . ' ';
				continue;
			}
			$next_hour = self::get_mailing_hour($newsletter);
			if( $next_hour > $hour ){
				$next_dates[count($next_dates) - 1] .= $next_hour . 'H (il est ' . $hour . 'H)';
				continue;
			}
			$subscribers = self::get_today_subscribers($newsletter, $today);
			if( $subscribers ) {
				$all_subscribers = array_merge($all_subscribers, $subscribers);
				$newsletters_data[$newsletter->ID] = [
					'newsletter' => $newsletter,
					'subscribers' => $subscribers,
				];
			}
			if( ! $subscribers ) {
				self::$cron_state .= sprintf(' | Prochaine date passe de %s', $next_date);
				$next_date = self::get_next_date(false, $newsletter, $today);
				self::$cron_state .= sprintf(' à %s', $next_date);
				$next_dates[] = wp_date('d/m/Y', $next_date) . ' ' . $next_hour . 'H';
				self::set_next_date(false, $newsletter, $next_date);
			}
		}
		if( count($all_subscribers) === 0){
			self::$cron_state = '1|Rien à faire avant : ' . implode(', ', $next_dates);
			return;
		}
		
		self::$cron_state = sprintf('1|%d abonnés à traiter', count($all_subscribers));

		if( ! $simulate){
			add_filter( 'wp_mail', array(__CLASS__, 'on_wp_mail'), 11, 1 );
			add_filter( 'wp_mail_succeeded', array(__CLASS__, 'on_wp_mail_succeeded'), 11, 1 );
			add_filter( 'wp_mail_failed', array(__CLASS__, 'on_wp_mail_failed'), 11, 1 );
		}

		$time_start = time();
		$all_mails_counter = 0;
		$have_more_mails = false;
		$subscribers_done = [];
		foreach($newsletters_data as $newsletter_id => $newsletter_data){
			$newsletter = $newsletter_data['newsletter'];
			$subscribers = $newsletter_data['subscribers'];
			$mails_counter = 0;
			$mails_counter_max = get_post_meta($newsletter_id, 'mailing-num-emails-per-loop', true);
			$users_per_mail = get_post_meta($newsletter_id, 'mailing-num-users-per-mail', true);
			$mailing_meta_name = self::get_mailing_meta_key($newsletter);
			for(; $mails_counter < $mails_counter_max; $mails_counter++){
				
				$user_emails = [];
				foreach($subscribers as $subscriber){
					if( in_array( $subscriber->ID, $subscribers_done ) )
						continue;
					$user_emails[ $subscriber->user_email ] = $subscriber;
					if( count( $user_emails ) >= $users_per_mail )
						break;
				}
				
				if( count( $user_emails ) === 0 )
					break;
				
				self::$cron_state .= sprintf(' | mail #%d : %d destinataire(s) %s', $mails_counter, count($user_emails), implode(', ', array_keys( $user_emails )));
				
				if( ! $simulate){
					self::set_user_mailing_status($newsletter, $subscriber->ID, 'prepared');
					self::send_email($newsletter, array_keys( $user_emails ));
				}
				
				foreach($user_emails as $user_email => $subscriber){
					if( ! $simulate ){
						update_user_meta($subscriber->ID, $mailing_meta_name, date('Y-m-d'));
					}
					$subscribers_done[] = $subscriber->ID;
				}
			}
			
			if($mails_counter === 0){
				self::$cron_state .= sprintf(' | Prochaine date passe de %s', self::get_next_date(false, $newsletter));
	
				$next_date = self::get_next_date(false, $newsletter, $today);
				
				self::$cron_state .= sprintf(' à %s', $next_date);
				
				if( ! $simulate){
					self::set_next_date(false, $newsletter, $next_date);
				}
			}
			else {
				$all_mails_counter += $mails_counter;
				if($all_mails_counter >= $mails_counter_max){
					$have_more_mails = true;
					$mailing_loops_interval = get_post_meta($newsletter_id, 'mailing-loops-interval', true);
					break;
				}
			}
		}

		self::$cron_state .= sprintf(' | Au final, %d mail(s) envoyé(s) à %d destinataire(s) en %s sec. Identifiants : #%s.%s'
			, $all_mails_counter
			, count($subscribers_done)
			, time() - $time_start
			, implode(', #', $subscribers_done )
			, $simulate ? ' << Simulation <<' : ''
		);

		if( ! $simulate){
			remove_filter( 'wp_mail', array(__CLASS__, 'on_wp_mail'), 10 );
			remove_filter( 'wp_mail_succeeded', array(__CLASS__, 'on_wp_mail_succeeded'), 10 );
			remove_filter( 'wp_mail_failed', array(__CLASS__, 'on_wp_mail_failed'), 10 );
			self::log_cron_state();
		
			if($have_more_mails){
				if( ! isset($mailing_loops_interval) )
					throw new Exception('$mailing_loops_interval n\'est pas défini !');
				self::init_cron($mailing_loops_interval * 60);
			}
		}
		
		return true;
	}
	
	/**
	 * Send email
	 */
	public static function send_email($newsletter, $emails){
		$newsletter = self::get_newsletter($newsletter);
		
		self::$sending_email = $newsletter;
		
		$subject = get_the_title(false, false, $newsletter);
		if( ! $subject){
			$subject = $newsletter->post_title;
			if( ! $subject){
				// debug_log($newsletter);
				return false;
			}
		}
		$subject = sprintf('[%s] %s', get_bloginfo( 'name', 'display' ), $subject);
		$message = do_shortcode( get_the_content(false, false, $newsletter) );
		
		$message = '<!DOCTYPE html><html>'
				. '<head>'
					. '<meta name="viewport" content="width=device-width">'
					. '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
/* 					. '<style>body {
font-family: \'Segoe UI\', Arial;
font-size: 14px;
height: 100% !important;
line-height: 1.5;
-ms-text-size-adjust: 100%;
-webkit-text-size-adjust: 100%;
width: 100% !important;
background-color: #fff;
margin: 0;
padding: 0;
white-space: pre;
						}</style>' */
				. '</head>'
				//TODO white-space does'nt work with my android mailbox
				. sprintf('<body style="white-space: pre-line;">%s</body>', $message)
				. '</html>'
		;
			
		if(is_array($emails))
			$to = implode(', ', $emails);
		else
			if( ! ($to = sanitize_email($emails)) )
				return false;
		
		if( strpos( $to, ',' ) !== false
		|| strpos( $to, ';' ) !== false
		){
			$bcc = $to;
			$to = self::get_bcc_mail_sender();
		}
		else
			$bcc = false;
		
		
		$headers = array();
		$attachments = array();
		
		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-type: text/html; charset=utf-8';
		$headers[] = 'Content-Transfer-Encoding: quoted-printable';
		
		if( $bcc ){
			$headers[] = sprintf('Bcc: %s', $bcc);
		}
		
		$headers[] = sprintf('From: %s', self::get_mail_sender());
		$headers[] = sprintf('Reply-to: %s', self::get_replyto_mail_sender());
		
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
	 
	 
	 /**
	  * Retourne les emails des abonnés pour un envoi ce jour et à qui l'envoi n'a pas encore été fait.
	  */
	 public static function get_today_subscribers($newsletter, $today = 0){
		if( ! $today )
			$today = strtotime(wp_date('Y-m-d'));
		$newsletter = self::get_newsletter($newsletter);
		$periods = [];
		$periods_in = '';
		foreach(AgendaPartage_Newsletter::subscription_periods($newsletter) as $period => $period_name){
			if( $period === 'none'
			|| self::get_next_date($period, $newsletter) > $today )
				continue;
			$periods[$period] = [
				'ID' => $period,
				'name' => $period_name,
			];
			$periods_in .= ($periods_in ? ', ' : '') . "'".$period."'";
		}
		if(count($periods) === 0)
			return false;
		
		$newsletter_id = $newsletter->ID;
		$subscription_meta_key = AgendaPartage_Newsletter::get_subscription_meta_key($newsletter);
		$mailing_meta_key = AgendaPartage_Newsletter::get_mailing_meta_key($newsletter);
		
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		$user_prefix = $wpdb->get_blog_prefix( 1 );
		
		/** Liste d'abonnés **/
		//$meta_name = sprintf('next_date_%s', $period);
		$meta_next_date = 'next_date_';
		$today_mysql = wp_date('Y-m-d', $today);
		$sql = "SELECT subscription.meta_value AS period, user.ID, user.user_email, user.user_nicename"
			. "\n FROM {$user_prefix}users user"
			// . "\n INNER JOIN {$user_prefix}usermeta usermetacap"
			// . "\n ON user.ID = usermetacap.user_id"
			// . "\n AND usermetacap.meta_key = '{$blog_prefix}capabilities'"
			// . "\n AND usermetacap.meta_value != 'a:0:{}'"
			. "\n INNER JOIN {$user_prefix}usermeta subscription"
				. "\n ON user.ID = subscription.user_id"
				. "\n AND subscription.meta_key = '{$subscription_meta_key}'"
				. "\n AND subscription.meta_value IN ({$periods_in})"
			. "\n INNER JOIN {$blog_prefix}postmeta next_date"
				. "\n ON next_date.post_id = {$newsletter_id}"
				. "\n AND next_date.meta_key = CONCAT( '{$meta_next_date}', subscription.meta_value)"
				. "\n AND next_date.meta_value = '{$today_mysql}'"
			. "\n LEFT JOIN {$user_prefix}usermeta mailing"
				. "\n ON user.ID = mailing.user_id"
				. "\n AND mailing.meta_key = '{$mailing_meta_key}'"
				. "\n AND mailing.meta_value = '{$today_mysql}'"
			. "\n WHERE mailing.meta_key IS NULL"
			. "\n ORDER BY user.user_email";
// debug_log($sql);

		$dbresults = $wpdb->get_results($sql);
		// foreach($dbresults as $dbresult)
			// if(isset($periods[$dbresult->period]))
				// $periods[$dbresult->period]['subscribers'][] = $dbresult;
		 return count($dbresults) ? $dbresults : false;
	 }
	 
	 
	/**
	 * on_wp_mail_action
	 */
	public static function on_wp_mail_action( $hook, array $mail_data, $newsletter = false, $email = false ){
		// debug_log('on_wp_mail_action', $hook, $email);
		if( ! $newsletter){
			$newsletter = self::is_sending_email();
			if( ! $newsletter )
				return $mail_data;
		}
		
		if( ! $email ){
		
			foreach($mail_data['headers'] as $header){
				$matches = [];
				if(preg_match_all('/^(from|bcc|cc|reply\-to)\:(.*)$/', strtolower($header), $matches)){
					$mail_data[$matches[1][0]] = trim($matches[2][0]);
				}
			}
			
			if(isset($mail_data['bcc']))
				$emails = $mail_data['bcc'];
			else
				$emails = $mail_data['to'];
			if( is_string($emails) )
				$emails = explode( ',', $emails );
			if( count($emails) === 0)
				debug_log('on_wp_mail_action(' . $hook . ') NO EMAIL !');
			
			foreach( $emails as $email )
				if( trim($email) )
					self::on_wp_mail_action($hook, $mail_data, $newsletter, trim($email) );
			return $mail_data;
		}
		
		$user_id = email_exists($email);
		if( ! $user_id ){
			debug_log(sprintf('[%s] on_%s("%s") Le destinataire n\'existe pas comme utilisateur !!', self::post_type, $hook, $email));
			return $mail_data;
		}
		
		switch($hook){
			case 'wp_mail' :
				self::set_user_mailing_status($newsletter, $user_id, 'sending');
				break;
			case 'wp_mail_succeeded' :
				self::delete_user_mailing_status($newsletter, $user_id);
				break;
			case 'wp_mail_failed' :
				self::set_user_mailing_status($newsletter, $user_id, sprintf('Echec : %s', $mail_data['error_message']));
				break;
		}
		
		return $mail_data;
	}
	
	/**
	 * on_wp_mail
	 */
	public static function on_wp_mail( $mail_data ){
		if( $mail_data === null){
			debug_log('on_wp_mail( $mail_data === NULL ! )');
			return;
		}
		$mail_data = self::on_wp_mail_action('wp_mail', $mail_data);
		return self::$sending_email_data = $mail_data;
	}
	 
	 
	/**
	 * on_wp_mail_succeeded
	 */
	public static function on_wp_mail_succeeded( $mail_data ){
		return self::on_wp_mail_action('wp_mail_succeeded', array_merge(self::$sending_email_data, $mail_data));
	}
	 
	 
	/**
	 * wp_mail_failed
	 */
	public static function on_wp_mail_failed( WP_Error $error ){
		$mail_data = $error->error_data['wp_mail_failed'];
		$mail_data['error_message'] = implode(', ', $error->errors['wp_mail_failed']);
		self::on_wp_mail_action('wp_mail_failed', array_merge(self::$sending_email_data, $mail_data));
		return $error;
	}
}
?>