<?php

/**
 * AgendaPartage -> Contact -> Edition
 * Edition d'un contact en ligne dans le site, avec ou sans utilisateur wp
 * 
 * Définition du Html d'édition.
 * Enregistrement de l'édition.
 *
 * Appelé par le shortcode [contact-edit]
 * 
 * TODO : 
 * - Attention si on crée un contact à partir d'un autre (is_new_post())
 */
class Agdp_Contact_Edit {


	private static $initiated = false;
	private static $changes_for_revision = null;
	public static $revision_fields = [ 
			'cont-organisateur', 
			'cont-email',
			'cont-address',
			'cont-description',
		];

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;
			
			self::check_nonce();

			self::init_hooks();
		}
	}

	/**
	 * Hook
	 */
	public static function init_hooks() {
		
		//Validation des valeurs
		add_filter( 'wpcf7_validate_text', array(__CLASS__, 'wpcf7_validate_fields_cb'), 10, 2);
		add_filter( 'wpcf7_validate_text*', array(__CLASS__, 'wpcf7_validate_fields_cb'), 10, 2);
		add_filter( 'wpcf7_validate_date', array(__CLASS__, 'wpcf7_validate_fields_cb'), 10, 2);
		add_filter( 'wpcf7_validate_date*', array(__CLASS__, 'wpcf7_validate_fields_cb'), 10, 2);
		add_filter( 'wpcf7_posted_data_text', array(__CLASS__, 'wpcf7_posted_data_fields_cb'), 10, 3);
		add_filter( 'wpcf7_posted_data_date', array(__CLASS__, 'wpcf7_posted_data_fields_cb'), 10, 3);
	}
 	/////////////
	
	/**
	* Retourne le post actuel si c'est bien du type contact
	*
	*/
	public static function get_post($contact_id = false) {
		return Agdp_Contact::get_post($contact_id);
	}
	
 	/**
	* Retourne faux si le post actuel de type contact a déjà été enregistré (ID différent de 0).
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
		foreach([AGDP_TAG . '-send-email'] as $nonce){
			if ( isset( $_POST[$nonce] ) 
				&& ! wp_verify_nonce( $_POST[$nonce], $nonce ) 
			) {
				print 'Désolé, la clé de sécurité n\a pas été vérifiée.';
				exit;
			}
		}
	}
 
 	/**
 	 * Retourne le titre de la page
 	 */
	public static function get_post_title( ) {
 		if( $post = self::get_post()){
			$post_id = $post->ID;
			if($post_id){
				$post_title = isset( $post->post_title ) ? $post->post_title : '';
				return $html;
			}
		}
		return "Nouveau contact";
	}
 
 	/**
 	 * Retourne la valeur part défaut d'un champ
 	 */
	public static function get_default_value( $field_name ) {
 		
		switch($field_name){
			default:
				return '';
				// throw new Exception( sprintf('L\'argument "%s" n\'est pas reconnu.', $field_name));
		}
		
	}
 
 	/**
 	 * Initialise les champs du formulaire
 	 */
	public static function get_contact_edit_content( ) {
		global $post;
		
		$form_id = Agdp::get_option('agdpcontact_edit_form_id');
		if(!$form_id){
			return Agdp::icon('warning', 'Le formulaire de modification du contact n\'est pas défini dans les réglages de AgendaPartage.', 'agdp-error-light', 'div');
		}
		
		$attrs = [];
		$post = self::get_post();
		
		//Action
		$duplicate_from_id = false;
 		if( ! $post && array_key_exists('action', $_GET) ){
			if($_GET['action'] === 'duplicate'
			&& array_key_exists(AGDP_ARG_CONTACTID, $_GET)){
				$duplicate_from_id = $_GET[AGDP_ARG_CONTACTID];
				$post = get_post($duplicate_from_id);
			}
		}
		
 		if( $post ){
 			$post_id = $post->ID;
			if( ! Agdp_Contact::user_can_change_post($post)){
				return self::get_contact_edit_content_forbidden( $post );
			}
			$contact_exists = ! $duplicate_from_id;
			$meta_name = 'cont-email' ;
			$email = Agdp_Contact::get_post_meta($post_id, $meta_name, true, false);
			
			/*if(!$email) {
				return Agdp::icon('warning'
					, 'Vous ne pouvez pas modifier ce contact, l\'initiateur n\'a pas associé d\'adresse email.'
					, 'agdp-error-light', 'div');
			}*/
			$attrs['cont-email'] = $email;
			$attrs['cont-description'] = $post->post_content;
			
			foreach(['cont-date-debut',
					'cont-heure-debut',
					'cont-heure-fin',
					'cont-intention',
					'cont-depart',
					'cont-arrivee',
					'cont-phone',
					'cont-phone-show',
					'cont-' . AGDP_COVOIT_SECRETCODE,
					'cont-organisateur',
					'cont-nb-places',
					'cont-periodique',
					'cont-periodique-label',
					'cont-date-fin'
			] as $meta_name){
				$attrs[$meta_name] = Agdp_Contact::get_post_meta($post_id, $meta_name, true, false);
			}
		}
		else{
			$contact_exists = false;
			$post_id = 0;
			
			foreach( [
				'cont-organisateur',
				'cont-email',
				'cont-nb-places',
				'cont-phone-show',
				'cont-' . AGDP_COVOIT_SECRETCODE
			] as $meta_name)
				$attrs[$meta_name] = self::get_default_value($meta_name);
			
			//Nouveau contact pour un évènement
			if( isset($_REQUEST[AGDP_ARG_EVENTID]) 
			 && ($agdpevent_id = $_REQUEST[AGDP_ARG_EVENTID])){
				foreach([
					'cont-date-debut' => 'ev-date-debut',
				] as $dest_field => $src_field)
					$attrs[$dest_field] = get_post_meta( $agdpevent_id, $src_field, true );
				$url = get_post_permalink( $agdpevent_id );
				$attrs['cont-arrivee'] = Agdp_Event::get_event_localisation_and_cities( $agdpevent_id, false );
				$attrs['cont-description'] = sprintf('En lien avec l\'évènement "%s" (<a href="%s">%s</a>).'
					, Agdp_Event::get_post_title( $agdpevent_id, true  )
					, $url
					, $url
				);
			}
		}
		//Les catégories, communes et diffusions sont traitées dans wpcf7_form_init_tags_cb
		
		// Génère le formulaire
		// Interception du formulaire avant la génération du html
		add_filter( 'wpcf7_form_class_attr', array(__CLASS__, 'wpcf7_form_init_tags_cb'), 10, 1 ); 
		$html = sprintf('[contact-form-7 id="%s"]', $form_id);
		$html = do_shortcode( wp_kses_post($html));
		if(! $html)
			return;
		remove_filter( 'wpcf7_form_class_attr', array(__CLASS__, 'wpcf7_form_init_tags_cb'), 10); 
		
		// Ajoute les données à affecter aux inputs via javascript.
		// cf agendapartage.js
		$attrs = str_replace('"', "&quot;", htmlentities( json_encode($attrs) ));
		$input = sprintf('<input type="hidden" class="contact_edit_form_data" data="%s"/>', $attrs);
		if($duplicate_from_id){
			$title = Agdp_Contact::get_post_title($post, true);
			$url = Agdp_Contact::get_post_permalink( $post_id, AGDP_COVOIT_SECRETCODE);
			$html = sprintf('<p class="info"> Duplication de contact <a href="%s">%s</a></p>'
					, $url, $title)
				. $html;
			$input .= sprintf('<input type="hidden" name="contact_duplicated_from" value="%s"/>', $duplicate_from_id);
		}
		elseif($post_id){
			//nécessaire en cas de 404 (hors connexion)
			$input .= sprintf('<input type="hidden" name="post_id" value="%s"/>', $post_id);
			
			//Maintient la transmission du code secret
			$ekey = Agdp_Contact::get_secretcode_in_request($post_id);		
			if($ekey){
				$input .= sprintf('<input type="hidden" name="%s" value="%s"/>', AGDP_COVOIT_SECRETCODE, $ekey);
			}
		}
		
		$html = str_ireplace('</form>', $input.'</form>', $html);
		
		$input = self::get_agdpevents_edit( $post_id );
		if( $input ){
			$html = preg_replace('/(\<p\>\s*\<input.*type="submit")/', '<p>'.$input.'</p>$1', $html);
		}
		
		if($contact_exists){
			//Is imported
			if( $is_imported = Agdp_Contact::get_post_imported( $post, false, true ) ){
				if( current_user_can('moderate_comments') ){
					$meta_name = AGDP_IMPORT_REFUSED;
					$import_refused = get_post_meta( $post_id, $meta_name, true );
					
					$html .= sprintf('<div class="agdppost-edit-toolbar post-is-imported">%s<span class="agdppost-tool">%s</span><br>%s</div>'
						, $is_imported
						, Agdp_Event::get_agdpevent_action_link(
							$post_id, 'refuse_import', true, null, false, null, $import_refused ? ['cancel'=>true] : null)
						, 'Vos modifications seront sans doute écrasées ultérieurement.'
					);
					
				}
				else
					$html .= $is_imported;
			}
			$html .= self::get_edit_toolbar($post);
		}
		return $html;
	}
	
	/**
	 * Html du bas de la zone de modification : dupliquer, supprimer, ...
	 */
	public static function get_edit_toolbar($post){
		$post_id = $post->ID;
		
		$html = '<div class="agdppost-edit-toolbar">';
		
		$url = get_page_link( Agdp::get_option('contact_page_id'));
		$url = add_query_arg(AGDP_ARG_CONTACTID, $post_id, $url );
		$html .= sprintf('<span class="agdppost-tool"><a href="%s" title="%s">%s%s</a></span>'
				, esc_url($url)
				, __('Ecrivez-nous pour signaler un problème avec ce contact', AGDP_TAG)
				, Agdp::icon('email-alt')
				, __('Un problème ?', AGDP_TAG)
		);
				
		if($post->post_status == 'publish')
			$html .= sprintf('<span class="agdppost-tool">%s</span>', Agdp_Contact::get_contact_action_link($post_id, 'unpublish', true));
		elseif( current_user_can('manage_options')
		|| (! Agdp_Contact::waiting_for_activation($post_id)
			&& Agdp_Contact::user_can_change_post($post_id))){
			$html .= sprintf('<span class="agdppost-tool">%s</span>', Agdp_Contact::get_contact_action_link($post_id, 'publish', true));
		}
		if(current_user_can('manage_options')
		|| current_user_can('contact')
		|| Agdp_Contact::user_can_change_post($post_id))
			$html .= sprintf('<span class="agdppost-tool">%s</span>', Agdp_Contact::get_contact_action_link($post_id, 'duplicate', true));
		$html .= sprintf('<span class="agdppost-tool">%s</span>', Agdp_Contact::get_contact_action_link($post_id, 'remove', true));
		$html .= sprintf('<span class="agdppost-tool">%s</span>', Agdp_Contact::get_contact_contact_email_link($post_id, true));
		$html .= '</div>';
		
		return $html;
	}
	
	/**
	 * Interception du formulaire avant que les shortcodes ne soient analysés.
	 * Affectation des valeurs par défaut.
	 * Affectation des listes de taxonomies
	 */
 	public static function wpcf7_form_init_tags_cb( $form_class ) { 
	
		$form = WPCF7_ContactForm::get_current();
		$html = $form->prop('form');//avec shortcodes du wpcf7
		$post = get_post();
		
		$html = Agdp_Contact::init_wpcf7_form_html( $html, $post );
		
		/** e-mail non-obligatoire si connecté **/
		if(($user = wp_get_current_user())
			&& $user->ID !== 0){
			$html = preg_replace('/(\[email)\*/', '$1', $html);
		}
		
		/** reCaptcha */
		if( Agdp_WPCF7::may_skip_recaptcha() ){
			//TODO
			// $html = preg_replace('/\[recaptcha[^\]]*[\]]/'
								// , ''
								// , $html);
		}
					
		$form->set_properties(array('form'=>$html));
		
		return $form_class;
	}
	
 	/**
 	 * Retourne les évènements liés au contact
 	 */
	public static function get_agdpevents_list( $contact ) {
		
		if( ! $contact )
			return '';
		if( ! Agdp::get_option('contact_managed') )
			return '';
		
		$meta_name = 'related_' . Agdp_Event::post_type;
		
		$related_agdpevents = get_post_meta( $contact->ID, $meta_name, false );
		if( count($related_agdpevents) === 1 && !$related_agdpevents[0] )
			$related_agdpevents = [];
		
		if( count($related_agdpevents) )
			$agdpevents = [get_post($related_agdpevents[0])];
		else
			$agdpevents = [];
		$html = sprintf('<ul class="agdp-agdpevents-list">');
		if( count($agdpevents) === 1 ){
			$agdpevent = $agdpevents[0];
			$html .= sprintf('<label><a href="%s?%s=%d">%s Évènement associé : %s</a></label>'
					, get_post_permalink($agdpevent)
					, AGDP_ARG_CONTACTID, $contact->ID
					, Agdp::icon('calendar-alt')
					, Agdp_Event::get_post_title($agdpevent, true)
			);
		}
		elseif( count($agdpevents) ){
			$html .= sprintf('<label>%s %s évènement%s associé%s</label>', Agdp::icon('calendar-alt'), count($agdpevents), count($agdpevents) > 1 ? 's' : '', count($agdpevents) > 1 ? 's' : '');
			foreach($agdpevents as $agdpevent){
				$html .= sprintf('<li><a href="%s?%s=%d">%s</a></li>'
					, get_post_permalink($agdpevent)
					, AGDP_ARG_CONTACTID, $contact->ID
					, $agdpevent->post_title
				);
			}
		}
		// else
			// $html .= sprintf('<label>%s Évènement</label>', Agdp::icon('calendar-alt'));
			
		// if( count($related_agdpevents) === 0 ){
			// // Ajouter
			// $html .= sprintf('<li>%s<a href="%s">Cliquer ici pour créer un nouvel évènement associé au contact</a></li>'
				// , Agdp::icon('welcome-add-page')
				// , get_post_permalink(Agdp::get_option('new_agdpevent_page_id'))
			// );
		// }
		
		$html .= '</ul>';
		
		return $html;
	}
	
 	/**
 	 * Complète le formulaire pour l'affectation d'évènements liés au contact
 	 */
	public static function get_agdpevents_edit( $contact ) {
		if( ! Agdp::get_option('contact_managed') )
			return '';
		
		if( $contact ){
			$contact = self::get_post($contact);
			
			$meta_name = 'related_' . Agdp_Event::post_type;
			$related_agdpevents = get_post_meta( $contact->ID, $meta_name, false );
			if( count($related_agdpevents) === 1 && ! $related_agdpevents[0])
				$related_agdpevents = [];
		}
		elseif( isset($_REQUEST[AGDP_ARG_EVENTID]) )
			$related_agdpevents[] = $_REQUEST[AGDP_ARG_EVENTID];
		else
			$related_agdpevents = [];
		if( $contact ){
			$meta_name = 'cont-date-debut';
			$date_debut = get_post_meta( $contact->ID, $meta_name, true );
			if( $date_debut < date('Y-m-d') )
				$date_debut = date('Y-m-d');
			$meta_name = 'cont-date-debut';
			$meta_query = [
				'relation' => 'AND',
				[
					'key' => 'ev-date-debut',
					'value' => date('Y-m-d', strtotime($date_debut . ' - 1 day')),
					'compare' => '>=',
					'type' => 'DATE'
				],[
					'relation' => 'OR',
					[
						'key' => 'ev-date-fin',
						'value' => date('Y-m-d', strtotime($date_debut)),
						'compare' => '>=',
						'type' => 'DATE'
					],[
						'key' => 'ev-date-debut',
						'value' => date('Y-m-d', strtotime($date_debut . ' + 3 days')),
						'compare' => '<=',
						'type' => 'DATE'
					]
				]
			];
		}
		else
			$meta_query = [[
				'key' => 'ev-date-debut',
				'value' => date('Y-m-d'),
				'compare' => '>=',
				'type' => 'DATE'
			]];
		$agdpevents = get_posts([
			'post_type' => Agdp_Event::post_type,
			'post_status' => 'publish',
			'meta_query' => $meta_query,
			'numberposts' => 30,
			'orderby' => [
				'ev-date-debut' => 'ASC',
				'ev-heure-debut' => 'ASC',
			],
		]);
		if( $related_agdpevents ){
			$related_exists = false;
			foreach($agdpevents as $agdpevent)
				if( in_array($agdpevent->ID, $related_agdpevents)){
					$related_exists = true;
					break;
				}
			if( ! $related_exists )
				foreach($related_agdpevents as $related_agdpevent)
					$agdpevents[] = get_post($related_agdpevent);
		}
		$html = sprintf('<label>%s Évènement de l\'agenda associé au contact</label>', Agdp::icon('calendar-alt'));
		//TODO multiselect
		$html .= sprintf('<select name="related_%s"><option value="">(aucun)</option>', Agdp_Event::post_type);
		foreach($agdpevents as $agdpevent){
			if( ! $agdpevent )
				continue;
			if( ! is_object($agdpevent) ){
				debug_log_callstack( '! is_object($agdpevent)', $agdpevent );
				continue;
			}
			$html .= sprintf('<option value="%s" %s>%s (%s)</option>'
				, $agdpevent->ID
				, in_array( $agdpevent->ID, $related_agdpevents ) ? 'selected="selected"' : ''
				, $agdpevent->post_title
				, Agdp_Event::get_event_dates_text($agdpevent)
			);
		}
		$html .= '</select>';
		
		return $html;
	}
	
	/**
 	 * Contenu de la page d'édition en cas d'interdiction de modification d'un contact
 	 */
	private static function get_contact_edit_content_forbidden( $post ) {
		$post_id = $post->ID;
		
		$html = '<div class="agdp-edit-forbidden">';
		$html .= '<div>' . Agdp::icon('lock'
				, 'Vous n\'êtes pas autorisé à modifier ce contact.', '', 'h4');
		
		if($post->post_status == 'trash'){
				$html .= 'Le contact a été supprimé.';
		}
		else {
			$html .= '<ul>Pour pouvoir modifier un contact vous devez remplir l\'une de ces conditions :';
			
			$html .= '<li>utiliser la même session internet qu\'à la création du contact et, ce, le même jour.';

			$url = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
			$url = wp_login_url( sanitize_url($url) );
			$html .= sprintf('<li>avoir un compte utilisateur sur le site, être <a href="%s">%sconnecté(e)</a> et avoir des droits suffisants.'
				, $url
				, Agdp::icon('unlock')
			);
			if(is_user_logged_in()){
				global $current_user;
				//Rôle autorisé
				if(	! $current_user->has_cap( 'edit_posts' ) )
					$html .= '<br><i>De fait, vous êtes connecté(e) mais vous n\'avez pas les droits et le mail associé au contact n\'est pas le vôtre.</i>';
			}
			$html .= '</li>';
			
			$html .= '<li>avoir un compte sur le site et être l\'initiateur du contact.</li>';
			
			$html .= '<li>vous pouvez nous écrire pour signaler un problème ou demander une modification.';
			$url = get_page_link( Agdp::get_option('contact_page_id'));
			$url = add_query_arg(AGDP_ARG_CONTACTID, $post_id, $url );
			$html .= sprintf('<br><a href="%s">%s cliquez ici pour nous écrire à propos de ce contact.</a>'
					, esc_url($url)
					, Agdp::icon('email-alt'));
			
			$html .= '</ul>';
		}
		$html .= '</div>';
		$html .= '</div>';
		return $html;
	}

	///////////
 	

 	/////////////////////
 	// email //
	
	/**
	 * Redéfinit les adresses emails des pages de contacts vers le mail de l'organisateur de contact ou, à défaut, vers l'auteur de la page.
	 * Le email2, email de copie, ne subit pas la redirection.
	 */
	public static function wp_mail_emails_fields($args){
		if( ! ($post = self::get_post()))
			return $args;
		$to_emails = parse_emails($args['to']);
		$headers_emails = parse_emails($args['headers']);
		$emails = array();
		//[ [source, header, name, user, domain], ]
		// 'user' in ['contact', 'client', 'admin']
		//Dans la config du mail WPCF7, on a, par exemple, "To: [e-mail-ou-telephone]<client@agendapartage.net>"
		//on remplace client@agendapartage.net par l'email extrait de [e-mail-ou-telephone]
		//Ce qui veut dire que la forme complète "[e-mail-ou-telephone]<client@agendapartage.net>" doit apparaitre pour deviner l'email du client
		foreach (array_merge($to_emails, $headers_emails) as $value) {
			if($value['domain'] === AGDP_EMAIL_DOMAIN
			&& ! array_key_exists($value['user'], $emails)) {
				switch($value['user']){
					case 'contact':
						$emails[$value['user']] = self::get_contact_email_address($post);
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
		|| ( $emails['client'] == 'client@agendapartage.net' ) ){
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
		&& $password_message = Agdp_User::new_password_link($post->post_author)){
			$args['message'] .= "\r\n<br>" . $password_message;
		}
		return $args;
	}

	// public static function wpcf7_verify_nonce_cb($is_active){
		//TODO
		// keep connected at mail send time
			// return is_user_logged_in();
		// }
 	// redirect email //
	///////////////////

	/**
	 * Email de l'organisateur de contact ou de l'auteur de la page Contact
	 */
	public static function get_contact_email_address($post){
		if(is_numeric($post)){
			$post_id = $post;
			$post = false;
		}
		else
			$post_id = $post->ID;
		if(!$post_id)
			return false;

		// Change l'adresse du destinataire
		$email = get_post_meta($post_id, 'cont-email', true);

		// 2ème email ?
		if( ! is_email($email)){
			$email = get_post_meta($post_id, 'cont-email2', true);
		}

		if( ! is_email($email)){
			if( ! $post)
				$post = get_post($post_id);
			// Email de l'auteur du post
			$email = get_the_author_meta('email', $post->post_author);
		}
		return $email;
	}

	/***********************************************************/

	/**
	 * Create a new contact or update an existing one
	 * Called before email is sent
	 */
	public static function submit_contact_form($contact_form, &$abort, $submission){
		$error_message = false;
		
		if( ! array_key_exists('contact_duplicated_from', $_POST)){
			$post = self::get_post();
			if( ! is_object($post)){
				$post = false;
			}
			elseif( ! Agdp_Contact::user_can_change_post($post)){
				$abort = true;
				$error_message = sprintf('Vous n\'êtes pas autorisé à modifier ce contact.');
				$submission->set_response($error_message);
				return false;
			}	
		}
		else {
			$post = false;
		}
		
		$inputs = $submission->get_posted_data();
		
		// var_dump($inputs);
		// die();
		
		if(is_object($contact_form) && is_a($contact_form, 'WPCF7_ContactForm', true)){ //contact form 7 -> wp_mail -> $args['message']
			$form = $contact_form;
			$data = array();
		
			foreach(array(
					'cont-intention' => 1,
					'cont-depart' => 1,
					'cont-arrivee' => 1,
					'post_content' => 'cont-description',
					'cont-date-debut' => 1,
					'cont-heure-debut' => 1,
					'cont-heure-fin' => 1,
					'cont-organisateur' => 1,
					'cont-email' => 1,
					'cont-phone' => 1,
					'cont-nb-places' => 1,
					'cont-'.AGDP_COVOIT_SECRETCODE => 1,
					'cont-periodique-label' => 1,
					'cont-date-fin' => 1,
					'related_' . Agdp_Event::post_type => 1,
				) as $post_field => $input_field){
					if($input_field === 1) $input_field = $post_field;
					if(isset($inputs[$input_field]))
						if(is_array($inputs[$input_field]))
							$data[$post_field] = trim($inputs[$input_field][0]);
						else
							$data[$post_field] = trim($inputs[$input_field]);
			}
			//checkboxes
			foreach(array(
				// 'cont-date-journee-entiere',
				// 'cont-message-contact'
				'cont-periodique',
				'cont-phone-show'
				) as $field){
				if(array_key_exists($field, $inputs)){
					if( is_array( $inputs[$field] ) )
						$data[$field] = $inputs[$field][0];
					else
						$data[$field] = $inputs[$field];
					if( $data[$field] === 'false')
						$data[$field] = false;
				}
			}
			debug_log( __FUNCTION__, $inputs);
			//categories, communes et diffusions
			$tax_terms = [];
			foreach( Agdp_Contact_Post_type::get_taxonomies() as $tax_name => $taxonomy){
				$field = $taxonomy['input'];
			
				$tax_terms[ $tax_name ] = [];
				$all_terms = Agdp_Contact::get_all_terms($tax_name, 'name'); //indexé par $term->name
				
				if(array_key_exists($field, $inputs)){
					if( is_array( $inputs[$field] ) ){
						$tax_terms[$tax_name] = array_map( //En théorie, wpcf7 retourne les identifiants mais comme on modifie à la volé les valeurs, il ne retourne que le nom
												function($term) use ($all_terms){ 
													if(is_numeric($term)) return $term;
													if(array_key_exists($term, $all_terms))
														return $all_terms[$term]->term_id;
													return false;
												}
												, $inputs[$field]);
					}
					elseif(is_numeric($inputs[$field]))
						$tax_terms[$tax_name][] = $inputs[$field];
					elseif(array_key_exists($inputs[$field], $all_terms))
						$tax_terms[$tax_name][] = $all_terms[$inputs[$field]]->term_id;
				}
			}
			
			// $error_message = var_export( $tax_terms, true) . var_export( $inputs, true); //debug
		}
		elseif( ! is_array($contact_form)
			 || ! array_key_exists( 'title', $contact_form) ){
			return;
		}
		else {
			$data = $contact_form;
		}
		
		$data['cont-organisateur-show'] = 1;//TODO
		$data['cont-email-show'] = 0;//TODO
		
		// $meta_name = 'cont-'.AGDP_COVOIT_SECRETCODE;
		// if( $post && get_post_meta($post->ID, $meta_name, true))
			// unset($data[$meta_name]);
		// else {
			// $data[$meta_name] = Agdp::get_secret_code(6);
		// }
		
		$meta_name = 'cont-sessionid';
		if( $post && get_post_meta($post->ID, $meta_name, true))
			unset($data[$meta_name]);
		else {
			$data[$meta_name] = Agdp::get_session_id();
		}
		
		if( ($user = wp_get_current_user())
		&& $user->ID){
		    $post_author = $user->ID;
		}
		else {
			$post_author = Agdp_User::get_blog_admin_id();
		}
		
		//Nouveau contact et pas d'utilisateur connected, activation nécessaire par email
		$new_post_need_validation = Agdp::get_option('contact_need_validation', false);
		if( ! $post && ! $post_author ){
			$data['activation_key'] = $new_post_need_validation;
		}
		
		$post_title = Agdp_Contact::get_post_title($post, true, $data);
		$post_content = $data['post_content'];
		unset($data['post_content']);
		
		$postarr = array(
			'post_title' => $post_title,
			'post_name' => sanitize_title( $post_title ),
			'post_type' => Agdp_Contact::post_type,
			'post_author' => $post_author,
			'meta_input' => $data,
			'post_content' => $post_content,
			//'tax_input' => $tax_terms cf plus loin
		);
		/* echo json_encode( $postarr);echo ("\r\n");
		die(); 	 */
		if( ! $error_message){
			
			if( $post_is_new = ! $post){
					
				if( is_user_logged_in() ){
					$postarr['post_status'] = 'publish';
					Agdp::$skip_mail = true;
				}
				else {
					$postarr['post_status'] = $new_post_need_validation ? 'pending' : 'publish';
					Agdp::$skip_mail = false;
				}
		
				//Check doublon
				$doublon = self::get_post_idem($post_title, $inputs);
				// var_dump($post_title, $inputs['cont-date-debut'], get_post_meta( $doublon, 'cont-date-debut', true));
				// die();
				if($doublon){
					if(is_a($doublon, 'WP_Post')){
						$url = Agdp_Contact::get_post_permalink($doublon);
						$error_message = sprintf('<br>Le contact <a href="%s"><b>%s</b></a> existe déjà à la même date et pour le même lieu.', $url, htmlentities($doublon->post_title));
					}
					else
						$error_message = sprintf('<br>La recherche de contact ayant le même titre, la même date et pour le même lieu indique une erreur : <br><pre>%s</pre>', $doublon);
				}
				
				if( ! $error_message){
					//Création du post
					$post_id = wp_insert_post( $postarr, true );
				}
				else
					$post_id = false;
			}
			else{
				
				$prev_email = get_post_meta($post->ID, 'cont-email', true);
				
				self::save_post_revision($post, $postarr);
				
				$postarr['ID'] = $post->ID;
				$post_id = wp_update_post( $postarr, true );
				
				Agdp::$skip_mail = true;
			}
		
			if(is_wp_error($post_id)){
				Agdp::$skip_mail = true;
				$error_message = $post_id->get_error_message();
				$post_id = $post ? $post->ID : false;
			}
		}
				
		//Changement des messages pour inclure le lien vers le nouveau post
		if($error_message){
			$abort = true;
			$error_message = sprintf('Le contact n\'a pas été enregistré. %s', $error_message);
			$submission->set_response($error_message);
			return false;
		}
		
		$previous_terms = [];
		//Taxonomies
		//Si on est pas connecté, les valeurs de tax_input ne sont pas mises à jour (wp_insert_post : current_user_can( $taxonomy_obj->cap->assign_terms )
		foreach($tax_terms as $tax_name => $tax_inputs){
			if( $tax_name === Agdp_Contact::taxonomy_diffusion )
				$previous_terms[$tax_name] = wp_get_post_terms($post_id, $tax_name);
			
			$result = wp_set_post_terms($post_id, $tax_inputs, $tax_name, false);
			if(is_a($result, 'WP_Error') || is_string($result)){
				$error_message = is_string($result) ? $result : $result->get_error_message();
				$abort = true;
				$error_message = sprintf('Erreur d\'enregistrement des catégories (%s). %s. \r\n%s', $tax_name, $error_message, var_export($tax_inputs, true));
				$submission->set_response($error_message);
				return false;
			}
		}
				
		//Gestion interne du mail
		Agdp::$skip_mail = true;
		
		if( $post_is_new && ! is_user_logged_in()){
			if( $data['cont-email']) {
				$result = Agdp_Contact::send_validation_email($post_id, false, false, 'bool');
				//TODO what to do if mail problem ?
				
				//En cas de succès, on recharge la page dans laquelle on affichera un message.
				if($result)
					set_transient(AGDP_TAG . '_email_sent_' . $post_id, $post_id, 20);
			} else {
				//Aucun email saisi
				set_transient(AGDP_TAG . '_no_email_' . $post_id, $post_id, 20);
			}
		}
		// Modification d'un post en attente et qui n'avait pas d'e-mail associé
		elseif( ! $post_is_new && ! is_user_logged_in()
		&& $post->post_status === 'pending'
		&& $data['cont-email'] && empty($prev_email)
		&& Agdp_Contact::waiting_for_activation($post)){
			$result = Agdp_Contact::send_validation_email($post_id, false, false, 'bool');
			
			//En cas de succès, on recharge la page dans laquelle on affichera un message.
			if($result)
				set_transient(AGDP_TAG . '_email_sent_' . $post_id, $post_id, 20);
		}
		
		if( ! ( $post_is_new && $postarr['post_status'] === 'pending' ) ){
			//Taxonomie Diffusion
			$tax_name = Agdp_Contact::taxonomy_diffusion;
			if( isset($tax_terms[$tax_name])){
				$tax_inputs = $tax_terms[$tax_name];
				$previous_tax_inputs = $previous_terms[$tax_name];
				Agdp_Contact::send_for_diffusion( $post_id, $tax_name, $tax_inputs, $previous_tax_inputs );
			}
		}
		
		$url = Agdp_Contact::get_post_permalink($post_id, AGDP_COVOIT_SECRETCODE);
		
		$messages = ($contact_form->get_properties())['messages'];
	
		$messages['mail_sent_ok'] = sprintf('redir:%s', $url);
		$messages['mail_sent_ng'] = sprintf('%s<br>Le contact a bien été enregistré mais l\'e-mail n\'a pas pu être envoyé.<br><a href="%s">Afficher la page de le contact</a>'
			, $messages['mail_sent_ng'], $url);
			
		$contact_form->set_properties(array('messages' => $messages));
		
		return $post_id;
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
		
		if($post->post_type != Agdp_Contact::post_type){
			
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
		if($post->post_type != Agdp_Contact::post_type)
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
  
	/**
	* Validation des champs des formulaires WPCF7
	*/
	public static function wpcf7_validate_fields_cb( $result, $tag ) {
		
		switch($tag->name){
			default:
				break;
		}
  
		return $result;
	}
	/**
	 * Correction des valeurs envoyées depuis le formulaire.
	 */
	public static function wpcf7_posted_data_fields_cb( $value, $value_orig, $tag ) {
		switch($tag->name){
			default:
				break;
		}
  
		return $value;
	}

	
	/**
	 * Recherche de contact identique
	 */
	public static function get_post_idem($post_title, $meta_values){
		if( ! is_array($meta_values))
			throw new TypeError('$meta_values should be an array.');
		$args = Agdp_Contacts::get_posts_query( 
			array(
				'post_status' => array( 'pending', 'publish', 'future' ),
				'posts_per_page' => 1
			)
		);
		
		
		//Même titre
		$args['title_query_filter'] = $post_title;
			
		//Même date de début
		//Même lieu
		$args['meta_query'] = [
				[ 'key' => 'cont-date-debut', 'value' => $meta_values['cont-date-debut']],
				[ 'key' => 'cont-depart', 'value' => empty($meta_values['cont-depart']) ? '' : $meta_values['cont-depart'] ],
				[ 'key' => 'cont-arrivee', 'value' => empty($meta_values['cont-arrivee']) ? '' : $meta_values['cont-arrivee'] ]
		];
		if($meta_values['cont-heure-debut'])
			$args['meta_query'][] = [ 'key' => 'cont-heure-debut', 'value' => $meta_values['cont-heure-debut']];
				
        //var_dump($args);
		add_filter('posts_where', array(__CLASS__, 'title_query_filter'),10,2);
		$the_query = new WP_Query( $args );
		remove_filter('posts_where',array(__CLASS__, 'title_query_filter'),10,2);
		
		//return var_export($the_query, true);
		
		if ( $the_query->have_posts() ) {
			return $the_query->posts[0]; 
		}
		// debug_log($the_query);
		return false;
    }
	/**
	* Filtre sur le titre dans un WP_Query
	*/
	public static function title_query_filter($where, $wp_query){
		global $wpdb;
		if($search_term = $wp_query->get( 'title_query_filter' )){
			// $search_term = $wpdb->esc_like($search_term); //instead of esc_sql()
			$search_term = "'" . esc_sql($search_term) . "'";
			// $title_filter_relation = (strtoupper($wp_query->get( 'title_filter_relation'))=='OR' ? 'OR' : 'AND');
			// $where .= ' '.$title_filter_relation.' ' . $wpdb->posts . '.post_title = '.$search_term;
			$where .= ' AND ' . $wpdb->posts . '.post_title = '.$search_term;
		}
		return $where;
	}
}
