<?php

/**
 * AgendaPartage -> Contact
 * Custom post type for WordPress.
 * 
 * Définition du Post Type contact
 * Définition de la taxonomie ev_category
 * En Admin, le bloc d'édition du Content est masqué d'après la définition du Post type : le paramètre 'supports' qui ne contient pas 'editor'
 *
 * Voir aussi Agdp_Admin_Contact
 */
class Agdp_Contact extends Agdp_Post {

	const post_type = 'agdpcontact';
	const taxonomy_city = 'cont_city';
	const taxonomy_category = 'cont_category';
	const shortcode = self::post_type;
	
	const field_prefix = 'cont-';
	
	const icon = 'businessperson';

	const postid_argument = AGDP_ARG_CONTACTID;
	const posts_page_option = 'contacts_page_id';

	protected static $initiated = false;
	public static function init() {
		if ( ! self::$initiated ) {
			parent::init();
			self::$initiated = true;

			self::init_hooks();
		}
	}

	/**
	 * Hook
	 */
	public static function init_hooks() {
		parent::init_hooks();
		
		add_action( 'wp_ajax_contact_action', array(__CLASS__, 'on_wp_ajax_contact_action_cb') );
		add_action( 'wp_ajax_nopriv_contact_action', array(__CLASS__, 'on_wp_ajax_contact_action_cb') );
	}
 
 	/**
 	 * Retourne le titre de la page
 	 */
	public static function get_post_title( $contact = null, $no_html = false, $data = false) {
 		if( ! isset($contact) || ! is_object($contact)){
			global $post;
			$contact = $post;
		}
		$contact_id = is_object($contact) ? $contact->ID : false;
		$categories = self::get_contact_categories( $contact_id );
		$categories = implode( ', ', $categories );
		if( !  $no_html){
			$categories = "<small>$categories</small>";
		}
		else {
			$categories = '';
		}
		$separator = $no_html ? ', ' : '<br>';
		$html = $contact->post_title
			. $separator . $categories;
		return $html;
	}
	
 	/**
 	 * Retourne le Content de la page du contact
 	 */
	public static function get_post_content( $contact = null ) {
		global $post;
 		if( ! isset($contact) || ! is_a($contact, 'WP_Post')){
			$contact = $post;
		}
		
		$html = '[' . self::shortcode . '-description]
		[' . self::shortcode . ' info="organisateur" label="Initiateur : "][' . self::shortcode . '-cree-depuis][/' . self::shortcode . ']
		[' . self::shortcode . ' info="phone" label="Téléphone : "]
		[' . self::shortcode . '-webpage label="Page web : "]';
		
		$html .= Agdp_Contact_Edit::get_agdpevents_list( $contact );
		
		$html .= self::get_post_imported( $contact );
		
		$meta_name = 'cont-email' ;
		$email = get_post_meta($contact->ID, $meta_name, true);
		if( ! is_email($email))
			$email = false;
		
		$html .= sprintf('[' . self::shortcode . '-modifier-contact toggle="Modifier ce contact" no-ajax post_id="%d"]'
			, $contact->ID
		);
		
		if( $email && current_user_can('manage_options') ){
			$form_id = Agdp::get_option('admin_message_contact_form_id');
			if(! $form_id){
				return '<p class="">Le formulaire de message à l\'organisateur du contact n\'est pas défini dans le paramétrage de AgendaPartage.</p>';
			}
			$user = wp_get_current_user();
			$html .= sprintf('[toggle title="Message de l\'administrateur (%s) à l\'organisateur du contact" no-ajax] [contact-form-7 id="%s"] [/toggle]'
				, $user->display_name, $form_id);
		}
				
		if($email_sent = get_transient(AGDP_TAG . '_email_sent_' . $contact->ID)){
			delete_transient(AGDP_TAG . '_email_sent_' . $contact->ID);
		}
		elseif($no_email = get_transient(AGDP_TAG . '_no_email_' . $contact->ID)){
			delete_transient(AGDP_TAG . '_no_email_' . $contact->ID);
		}
		if(empty($codesecret))
			$secretcode = get_post_meta($post->ID, self::field_prefix . self::secretcode_argument, true);
		
		$status = false;
		switch($post->post_status){
			case 'pending':
				$status = 'En attente de relecture';
			case 'future':
				if(!$status) $status = 'Pour le futur';
			case 'draft':
				if(!$status) $status = 'Brouillon';
				
				$alerte = sprintf('<p class="alerte">Ce contact est <b>en attente de validation</b>, il a le statut "%s".'
					.'<br>Il n\'est <b>pas visible</b> dans l\'agenda.'
					. '</p>'
					. (isset($email_sent) && $email_sent ? '<div class="info">Un e-mail a été envoyé pour permettre la validation de ce nouveau contact. Vérifiez votre boîte mails, la rubrique spam aussi.</div>' : '')
					. (isset($no_email) && $no_email ? '<div class="alerte">Vous n\'avez pas indiqué d\'adresse mail pour permettre la validation de ce nouveau contact.'
											. '<br>Vous devrez attendre la validation par un modérateur pour que ce contact soit public.'
											. '<br>Vous pouvez encore modifier ce contact et renseigner l\'adresse mail.'
											. '<br>Le code secret de ce contact est : <b>'.$secretcode.'</b>'
											. '</div>' : '')
					, $status);
				$html = $alerte . $html;
				break;
				
			case 'publish': 
			
				if(isset($email_sent) && $email_sent){
					$info = '<div class="info">Ce contact est désormais public.'
							. '<br>Un e-mail a été envoyé pour mémoire. Vérifiez votre boîte mails, la rubrique spam aussi.'
							. '<br>Le code secret de ce contact est : <b>'.$secretcode.'</b>'
						.'</div>';
					$html = $info . $html;
				}
				elseif( isset($no_email) && $no_email) {
					$info = '<div class="alerte">Ce contact est désormais public.</div>'
							. '<br>Le code secret de ce contact est : <b>'.$secretcode.'</b>';
					$html = $info . $html;
				}
				
				$page_id = Agdp::get_option(self::posts_page_option);
				if($page_id){
					$url = self::get_post_permalink($page_id, self::secretcode_argument);
					$url = add_query_arg( self::postid_argument, $contact->ID, $url);
					$url .= '#' . self::postid_argument . $contact->ID;
					$html .= sprintf('<br><br>Pour voir ce contact dans la liste, <a href="%s">cliquez ici %s</a>.'
					, $url
					, Agdp::icon( self::icon ));
				}
				break;
		}
		
			
		if(is_user_logged_in()){
			global $current_user;
			//Rôle autorisé
			if(	$current_user->has_cap( 'edit_posts' ) ){
			
				$creator = new WP_User($contact->post_author);
				if(($user_name = $creator->get('display_name'))
				|| ($user_name = $creator->get('user_login')))
					$html .= '<p>modifié par "' . $user_name . '"</p>';
			}
		}
		return $html;
	}
	
 	/**
 	 * Retourne le Content de la page du contact
 	 */
	public static function get_post_imported( $post_id = null, $no_html = false, $add_alert = false ) {
		global $post;
 		if( is_a($post_id, 'WP_Post')){
			$post_id = $post_id->ID;
		}
		$meta_name = AGDP_IMPORT_UID;
		$val = get_post_meta($post_id, $meta_name, true);
		if($val){
			$matches = [];
			preg_match_all('/^(\w+)\[(\d+)\]@(.*)$/', $val, $matches);
			$source_post_type = $matches[1][0];
			$source_id = $matches[2][0];
			$source_site = $matches[3][0];
			$val = sprintf('Ce contact provient de <a href="%s://%s/blog/%s?p=%d">%s</a>', 
				'https', $source_site, $source_post_type, $source_id, $source_site);
			if($no_html)
				return $val;
			
			$meta_name = AGDP_IMPORT_REFUSED;
			if( $import_refused = get_post_meta( $post_id, $meta_name, true ) ){			
				$val .= ' ' . Agdp::icon('warning', 'Refusé', 'color-red');
			}
			return sprintf('<div class="agdp-contact agdp-%s">%s %s%s</div>'
					, $meta_name
					, Agdp::icon( $add_alert ? 'warning' : 'admin-multisite')
					, $val
					, $add_alert && $add_alert !== true ? $add_alert : ''
			);
		}
		return '';
	}
	
	/*******************
	 * Actions via Ajax
	 *******************/
	/**
	 * Retourne un lien html pour l'envoi d'un mail à l'organisateur
	 */
	public static function get_contact_contact_email_link($post, $icon = false, $message = null, $title = false, $confirmation = null){
		$html = '';
		$meta_name = 'cont-email' ;
		$email = self::get_post_meta($post, $meta_name, true);
		if(!$email){
			$html .= '<p class="alerte">Ce contact n\'a pas d\'adresse e-mail associée.</p>';
		}
		else {
			if(current_user_can('manage_options'))
				$data = [ 'force-new-activation' => true ];
			else
				$data = null;
			$html = self::get_contact_action_link($post, 'send_email', $icon, $message, $title, $confirmation, $data);
		}
		return $html;
	}
	
	/**
	 * Retourne un lien html pour une action générique
	 */
	public static function get_contact_action_link($post, $method, $icon = false, $caption = null, $title = false, $confirmation = null, $data = null){
		$need_can_user_change = true;
		switch($method){
			case 'remove':
				if($caption === null)
					$caption = __('Supprimer', AGDP_TAG);
				if($confirmation === null || $confirmation === true)
					$confirmation = 'Confirmez-vous la suppression définitive du contact ?';
				break;
			case 'duplicate':
				if($caption === null)
					$caption = __('Dupliquer', AGDP_TAG);
				if($confirmation === true)
					$confirmation = 'Confirmez-vous la duplication du contact ?';
				if($icon === true)
					$icon = 'admin-page';
				break;
			case 'unpublish':
				if($caption === null)
					$caption = __('Masquer dans l\'agenda', AGDP_TAG);
				if($icon === true)
					$icon = 'hidden';
				if($confirmation === null || $confirmation === true)
					$confirmation = 'Confirmez-vous que le contact ne sera plus visible ?';

				break;
			case 'publish':
				if($caption === null)
					$caption = __('Rendre public dans les contacts', AGDP_TAG);
				if($icon === true)
					$icon = 'visibility';
				if($confirmation === null || $confirmation === true)
					$confirmation = 'Confirmez-vous de rendre visible le contact ?';
				
				break;
			case 'send_phone_number':
				$need_can_user_change = false;
				if($caption === null)
					$caption = __('Obtenir le n° de téléphone', AGDP_TAG);
				if($icon === true)
					$icon = 'phone';
				if($confirmation === null || $confirmation === true)
					$confirmation = 'Confirmez-vous l\'envoi d\'un email à votre adresse ?';
				
				break;
			case 'send_email':
				$need_can_user_change = false;
				$meta_name = 'cont-email' ;
				$email = self::get_post_meta($post, $meta_name, true);
				$email_parts = explode('@', $email);
				$email_trunc = substr($email, 0, min(strlen($email_parts[0]), 3)) . str_repeat('*', max(0, strlen($email_parts[0])-3));
				if($caption === null){
					$caption = 'E-mail de validation';
					$title = sprintf('Cliquez ici pour envoyer un e-mail de validation du contact à l\'adresse %s@%s', $email_trunc, $email_parts[1]);
				}
				if($icon === true)
					$icon = 'email-alt';
				if($confirmation === null || $confirmation === true)
					$confirmation = sprintf('Confirmez-vous l\'envoi d\'un e-mail à l\'adresse %s@%s', $email_trunc, $email_parts[1]);
				break;
			default:
				if(!$caption)
					$caption = __($method, AGDP_TAG);
				
				break;
		}
		if(!$title)
			$title = $caption;
		
		if($icon === true)
			$icon = $method;
		$html = '';
		if($need_can_user_change && ! self::user_can_change_post($post)){
			$html .= '<p class="alerte">Ce contact ne peut pas être modifié par vos soins.</p>';
		}
		else {
			
			$post_id = is_object($post) ? $post->ID : $post;
			$query = [
				'post_id' => $post_id,
				'action' => self::post_type . '_action',
				'method' => $method
			];
			if($data)
				$query['data'] = $data;
				
			//Maintient la transmission du code secret
			$ekey = self::get_secretcode_in_request($post_id);
			if($ekey)
				$query[self::secretcode_argument] = $ekey;

			if($confirmation){
				$query['confirm'] = $confirmation;
			}
			if($icon)
				$icon = Agdp::icon($icon);
			$html .= sprintf('<span><a href="#" title="%s" class="agdp-ajax-action agdp-ajax-%s" data="%s">%s%s</a></span>'
				, $title ? $title : ''
				, $method
				, esc_attr( json_encode($query) )
				, $icon ? $icon . ' ' : ''
				, $caption);
		}
		return $html;
	}
	
	/**
	 * Action required from Ajax query
	 * 
	 */
	public static function on_wp_ajax_contact_action_cb() {
		
		// debug_log('on_wp_ajax_contact_action_cb');	
		
		if( ! Agdp::check_nonce())
			wp_die();
		
		$ajax_response = '0';
		if(!array_key_exists("method", $_POST)){
			wp_die();
		}
		$method = $_POST['method'];
		if(array_key_exists("post_id", $_POST)){
			try{
				//cherche une fonction du nom "contact_action_{method}"
				$function = array(__CLASS__, sprintf('contact_action_%s', $method));
				$ajax_response = call_user_func( $function, $_POST['post_id']);
			}
			catch( Exception $e ){
				$ajax_response = sprintf('Erreur dans l\'exécution de la fonction :%s', var_export($e, true));
			}
		}
		echo $ajax_response;
		
		// Make your array as json
		//wp_send_json($ajax_response);
	 
		// Don't forget to stop execution afterward.
		wp_die();
	}
	
	/**
	 * Remove event
	 */
	public static function contact_action_remove($post_id) {
		if ( self::do_remove($post_id) )
			return 'redir:' . Agdp_Contacts::get_url(); //TODO add month in url
		return 'Impossible de supprimer ce contact.';
	}
	
	/**
	 * Duplicate event
	 */
	public static function contact_action_duplicate($post_id) {
		if ( self::user_can_change_post($post_id) )
			return 'redir:' . add_query_arg(
				'action', 'duplicate'
				, add_query_arg(self::postid_argument, $post_id
					, get_page_link(Agdp::get_option('new_contact_page_id'))
				)
			);
		return 'Impossible de retrouver ce contact.';
	}
	
	/**
	 * Unpublish event
	 */
	public static function contact_action_unpublish($post_id) {
		$post_status = 'pending';
		if( self::change_post_status($post_id, $post_status) ){
			self::send_for_diffusion( $post_id );
			return 'redir:' . self::get_post_permalink($post_id, true, self::secretcode_argument, 'etat=en-attente');
		}
		return 'Impossible de modifier ce contact.';
	}
	/**
	 * Publish event
	 */
	public static function contact_action_publish($post_id) {
		$post_status = 'publish';
		if( (! self::waiting_for_activation($post_id)
			|| current_user_can('manage_options') )
		&& self::change_post_status($post_id, $post_status) ){
			self::send_for_diffusion( $post_id );
			
			if(isset($_POST['data']) && is_array($_POST['data'])){
				if( isset($_POST['data']['redir'])
				&& ( $redir = $_POST['data']['redir'] )
				)
					return 'redir:' . $redir;
					
				elseif(isset($_POST['data']['reload'])
				&& ( $redir = $_POST['data']['reload'] )
				)
					return 'reload:' . $redir;
			}
			return self::get_post_permalink($post_id, self::secretcode_argument);
		}
		return 'Impossible de modifier le statut.<br>Ceci peut être effectué depuis l\'e-mail de validation.';
	}
	/**
	 * Send contact email
	 */
	public static function contact_action_send_email($post_id) {
		if(isset($_POST['data']) && is_array($_POST['data'])
		&& isset($_POST['data']['force-new-activation']) && $_POST['data']['force-new-activation']){
			self::get_activation_key($post_id, true); //reset
		}
		return self::send_validation_email($post_id);
	}
	
	/**
	 * Envoye le mail à l'organisateur du contact
	 */
	public static function send_validation_email($post, $subject = false, $message = false, $return_html_result = false){
		if(is_numeric($post)){
			$post_id = $post;
			$post = get_post($post_id);
		}
		else
			$post_id = $post->ID;
		
		if(!$post_id)
			return false;
		
		$codesecret = self::get_post_meta($post, self::field_prefix . self::secretcode_argument, true);
		
		$meta_name = 'cont-email' ;
		$email = self::get_post_meta($post, $meta_name, true);
		$to = $email;
		
		$site = get_bloginfo( 'name' );
		
		$subject = sprintf('[%s][Validation] %s', $site, $subject ? $subject : $post->post_title);
		
		$headers = array();
		$attachments = array();
		
		if( ! $message){
			$message = sprintf('Bonjour,<br>Vous recevez ce message suite la création du contact ci-dessous ou à une demande depuis le site et parce que votre e-mail est associé au contact.');

		}
		else
			$message .= '<br>'.str_repeat('-', 20);
		
		$url = self::get_post_permalink($post, true);
		
		switch($post->post_status){
			case 'pending':
				$status = 'En attente de relecture';
			case 'draft':
				if(!$status) $status = 'Brouillon';
				$message .= sprintf('<br><br>Ce contact n\'est <b>pas visible</b> en ligne, il est marqué comme "%s".', $status);
				
				if( self::waiting_for_activation($post) ){
					$activation_url = add_query_arg(self::secretcode_argument, $codesecret, $url);
					$activation_url = add_query_arg('action', 'activation', $activation_url);
					$activation_url = add_query_arg('ak', self::get_activation_key($post), $activation_url);
					$activation_url = add_query_arg('etat', 'en-attente', $activation_url);
				}
				
				$message .= sprintf('<br><br><a href="%s"><b>Cliquez ici pour rendre ce contact public dans l\'agenda</b></a>.<br>', $activation_url);
				break;
			case 'trash':
				$message .= sprintf('<br><br>Ce contact a été SUPPRIMÉ.');
				break;
		}
		
		$message .= sprintf('<br><br>Le code secret de ce contact est : %s', $codesecret);
		// $args = self::secretcode_argument .'='. $codesecret;
		// $codesecret_url = $url . (strpos($url,'?')>0 || strpos($args,'?') ? '&' : '?') . $args;			
		$codesecret_url = add_query_arg(self::secretcode_argument, $codesecret, $url);
		$message .= sprintf('<br><br>Pour modifier ce contact, <a href="%s">cliquez ici</a>', $codesecret_url);
		
		$url = self::get_post_permalink($post);
		$message .= sprintf('<br><br>La page publique de ce contact est : <a href="%s">%s</a>', $url, $url);

		$message .= '<br><br>Bien cordialement,<br>L\'équipe de l\'Agenda partagé.';
		
		$message .= '<br>'.str_repeat('-', 20);
		$message .= sprintf('<br><br>Détails du contact :<br><code>%s</code>', self::get_post_details_for_email($post));
		
		$message = quoted_printable_encode(str_replace('\n', '<br>', $message));

		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-type: text/html; charset=utf-8';
		$headers[] = 'Content-Transfer-Encoding: quoted-printable';

		if($success = wp_mail( $to
			, '=?UTF-8?B?' . base64_encode($subject). '?='
			, $message
			, $headers, $attachments )){
			$html = '<div class="info email-send">L\'e-mail a été envoyé.</div>';
		}
		else{
			$html = sprintf('<div class="email-send alerte">L\'e-mail n\'a pas pu être envoyé.</div>');
		}
		if($return_html_result){
			if($return_html_result === 'bool')
				return $success;
			else
				return $html;
		}
		echo $html;
	}
	
	/**
	 * Détails du contact pour insertion dans un email
	 */
	public static function get_post_details_for_email($post, $to_author = true){
		if(is_numeric($post)){
			$post = get_post($post);
		}
		$post_id = $post->ID;
		$html = '<table><tbody>';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Titre', htmlentities($post->post_title));
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Dates', self::get_contact_dates_text($post_id));
		$meta_name = 'cont-depart';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Départ', htmlentities(get_post_meta($post_id, $meta_name, true)));
		$meta_name = 'cont-arrivee';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Destination', htmlentities(get_post_meta($post_id, $meta_name, true)));
		$html .= sprintf('<tr><td>%s : </td><td><pre>%s</pre></td></tr>', 'Description', htmlentities($post->post_content));
		$meta_name = 'cont-organisateur';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Organisateur', htmlentities(get_post_meta($post_id, $meta_name, true)));
		$meta_name = 'cont-phone';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Téléphone', htmlentities(get_post_meta($post_id, $meta_name, true)));
		if($to_author) {
			$meta_name = 'cont-phone-show';
			$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Afficher publiquement le n° de téléphone', get_post_meta($post_id, $meta_name, true) ? 'oui' : 'non');
		}
		$meta_name = 'cont-email';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Email', get_post_meta($post_id, $meta_name, true));
		
		$html .= '</tbody></table>';
		return $html;
		
	}
	
	
	/**
	 * Retourne le texte des dates et heures d'un contact
	 */
	public static function get_contact_dates_text( $post_id, $data = false ) {
		if($post_id && is_object($post_id))
			$post_id = $post_id->ID;
		$date_debut    = $data ? $data['cont-date-debut'] : get_post_meta( $post_id, 'cont-date-debut', true );
		$heure_debut    = $data ? $data['cont-heure-debut'] : get_post_meta( $post_id, 'cont-heure-debut', true );
		$date_fin    = $date_debut;
		$heure_fin    = $data ? $data['cont-heure-fin'] : get_post_meta( $post_id, 'cont-heure-fin', true );
		if(mysql2date( 'j', $date_debut ) === '1')
			$format_date_debut = 'l j\e\r M Y';
		else
			$format_date_debut = 'l j M Y';
		if($date_fin && mysql2date( 'j', $date_fin ) === '1')
			$format_date_fin = 'l j\e\r M Y';
		else
			$format_date_fin = 'l j M Y';
		return mb_strtolower( trim(
			  ($date_fin && $date_fin != $date_debut ? 'du ' : '')
			. ($date_debut ? str_ireplace(' mar ', ' mars ', mysql2date( $format_date_debut, $date_debut )) : '')
			. ($heure_debut ? ' à ' . $heure_debut : '')
			// . (/* !$date_jour_entier && */ $heure_debut 
				// ? ($heure_fin ? ' de ' : ' à ') . $heure_debut : '')
			. ($date_fin && $date_fin != $date_debut ? ' au ' . str_ireplace(' mar ', ' mars ', mysql2date( $format_date_fin, $date_fin )) : '')
			. (/* !$date_jour_entier && */ $heure_fin 
				? ', retour à ' . $heure_fin
				: '')
		));
	}
	/**
	 * Retourne les catégories d'un contact
	 */
	public static function get_contact_categories( $post_id, $args = 'names' ) {
		return self::get_post_terms( self::taxonomy_category, $post_id, $args);
	}
	
 	/**
	 * Pré-remplit le formulaire "Contactez nous" avec les informations d'un contact
	 */
 	public static function wpcf7_contact_form_init_tags( $form ) { 
		$html = $form->prop('form');//avec shortcodes du wpcf7
		$requested_id = isset($_REQUEST[self::postid_argument]) ? $_REQUEST[self::postid_argument] : false;
		$contact = self::get_post($requested_id);
		if( ! $contact)
			return;
		
		/** init message **/
		$message = sprintf("Bonjour,\r\nJe vous écris à propos de \"%s\".\r\n%s\r\n\r\n-"
			, $contact->post_title
			, get_post_permalink($contact)
		);
		$matches = [];
		if( ! preg_match_all('/(\[textarea[^\]]*\])([\s\S]*)(\[\/textarea)?/', $html, $matches))
			return;
		for($i = 0; $i < count($matches[0]); $i++){
			if( strpos( $matches[2][$i], "[/textarea") === false ){
				$message .= '[/textarea]';
			}
			$html = str_replace( $matches[1][$i]
					, sprintf('%s%s', $matches[1][$i], $message)
					, $html);
		}
		$user = wp_get_current_user();
		if( $user ){
		
			/** init name **/	
			$html = preg_replace( '/(autocomplete\:name[^\]]*)\]/'
					, sprintf('$1 "%s"]', $user->display_name)
					, $html);
		
			/** init email **/	
			$html = preg_replace( '/(\[email[^\]]*)\]/'
					, sprintf('$1 "%s"]', $user->user_email)
					, $html);
		}
		
		/** set **/
		$form->set_properties(array('form'=>$html));
		
	}
	
	/**
	* Retourne le numéro de téléphone ou le formulaire pour l'obtenir par email.
	**/
	public static function get_phone_html($post_id){
		$meta_name = 'cont-phone';
		$val = self::get_post_meta($post_id, $meta_name, true, false);
		if( /*! is_user_logged_in()
		&&*/ ! get_post_meta($post_id, 'cont-phone-show', true)){
			// $val = sprintf('<span class="agdppost-tool">%s</span>'
				// , self::get_contact_action_link($post_id, 'send_phone_number', true));
				//Formulaire de saisie du code secret
			$url = self::get_post_permalink( $post_id );
			$query = [
				'post_id' => $post_id,
				'action' => AGDP_TAG . '_' . AGDP_EMAIL4PHONE
			];
			$html = '<a id="email4phone-title">' 
					. Agdp::icon('phone') . ' masqué > cliquez ici'
				. '</a>'
				. '<div id="email4phone-form">'
					. 'La personne ayant déposé l\'annonce a souhaité restreindre la lecture de son numéro de téléphone.'
					. ' Vous pouvez le recevoir par email.'
					. '<br>Veuillez saisir votre adresse email :&nbsp;'
					. sprintf('<form class="agdp-ajax-action" data="%s">', esc_attr(json_encode($query)))
					. wp_nonce_field(AGDP_TAG . '-' . AGDP_EMAIL4PHONE, AGDP_TAG . '-' . AGDP_EMAIL4PHONE, true, false)
					.'<input type="text" placeholder="ici votre email" name="'.AGDP_EMAIL4PHONE.'" size="20"/>
					<input type="submit" value="Envoyer" /></form>'
				. '</div>'
			;
			return $html;
		}
		return antispambot(esc_html($val));
	}

	
	/**
	 * Send email with phone number from Ajax query
	 */
	public static function on_wp_ajax_contact_email4phone_cb() {
		$ajax_response = '0';
		if(array_key_exists("post_id", $_POST)){
			$post = get_post($_POST['post_id']);
			if($post->post_type != self::post_type)
				return;
			$email = sanitize_email($_POST[AGDP_EMAIL4PHONE]);
			if( ! $email ){
				$ajax_response = sprintf('<div class="email-send alerte">L\'adresse "%s" n\'est pas valide. Vérifiez votre saisie.</div>', $_POST[AGDP_EMAIL4PHONE]);
			}
			else {
				$result = self::send_email4phone($post, $email);
				if( $result === true ){
					$ajax_response = sprintf('<div class="info">Le numéro de téléphone vous a été envoyé par email à l\'adresse %s.</div>', $email);
				}
				elseif( $result === false ){
					$ajax_response = sprintf('<div class="email-send alerte">Désolé, l\'e-mail n\'a pas pu être envoyé à l\'adresse "%s".</div>', $email);
				}
				else
					$ajax_response = $result;
			}
		}
		
		// Make your array as json
		wp_send_json($ajax_response);
	 
		// Don't forget to stop execution afterward.
		wp_die();
	}
	
	/**
	 * Envoi d'un email contenant les coordonnées associées au contact
	 */
	private static function send_email4phone($post, $dest_email, $return_html_result = true){
		if(is_numeric($post)){
			$post_id = $post;
			$post = get_post($post_id);
		}
		else
			$post_id = $post->ID;
		
		if(!$post_id)
			return false;
		
		$meta_name = 'cont-organisateur' ;
		$organisateur = self::get_post_meta($post, $meta_name, true);
		$meta_name = 'cont-email' ;
		$email = self::get_post_meta($post, $meta_name, true);
		$meta_name = 'cont-phone' ;
		$phone = self::get_post_meta($post, $meta_name, true);
		
		$site = get_bloginfo( 'name' );
		
		$subject = sprintf('[%s][Coordonnées] %s', $site, $post->post_title);
		
		$headers = array();
		$attachments = array();
		
		$message = sprintf('Bonjour,<br>Vous avez demandé à recevoir les coordonnées associées au contact ci-dessous.');
		$message .= sprintf('<br>Initiateur : %s', $organisateur);
		$message .= sprintf('<br>Téléphone : %s', $phone);
		$message .= sprintf('<br>Email : %s', $email);
		
		$url = self::get_post_permalink($post, true);
		
		switch($post->post_status){
			case 'pending':
				$status = 'En attente de relecture';
			case 'draft':
				if(empty($status)) $status = 'Brouillon';
				$message .= sprintf('<br><br>Ce contact n\'est <b>pas visible</b> en ligne, il est marqué comme "%s".', $status);
				break;
			case 'trash':
				$message .= sprintf('<br><br>Ce contact a été SUPPRIMÉ.');
				break;
		}
		
		
		$url = self::get_post_permalink($post);
		$message .= sprintf('<br><br>La page de ce contact est : <a href="%s">%s</a>', $url, $url);

		$message .= '<br><br>Bien cordialement,<br>L\'équipe de l\'Agenda partagé.';
		
		$message .= '<br>'.str_repeat('-', 20);
		$message .= sprintf('<br><br>Détails du contact :<br><code>%s</code>', self::get_post_details_for_email($post, false));
		
		$message = quoted_printable_encode(str_replace('\n', '<br>', $message));

		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-type: text/html; charset=utf-8';
		$headers[] = 'Content-Transfer-Encoding: quoted-printable';
		$headers[] = sprintf('Reply-to: %s', $email);

		if($success = wp_mail( $dest_email
			, '=?UTF-8?B?' . base64_encode($subject). '?='
			, $message
			, $headers, $attachments )){
			$html = sprintf('<div class="info email-send">L\'e-mail a été envoyé à l\'adresse "%s".</div>', $dest_email);
		}
		else{
			$html = sprintf('<div class="email-send alerte">L\'e-mail n\'a pas pu être envoyé à l\'adresse "%s".</div>', $dest_email);
		}
		
		/* debug_log($return_html_result, $success, $html
			, $dest_email
			, '=?UTF-8?B?' . base64_encode($subject). '?='
			, $message
			, $headers, $attachments ); */
		
		if($return_html_result){
			if($return_html_result === 'bool')
				return $success;
			else
				return $html;
		}
		echo $html;
	}
}
