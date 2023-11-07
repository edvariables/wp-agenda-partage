<?php

/**
 * AgendaPartage -> Covoiturage -> Edition
 * Edition d'un covoiturage en ligne dans le site, avec ou sans utilisateur wp
 * 
 * Définition du Html d'édition.
 * Enregistrement de l'édition.
 *
 * Appelé par le shortcode [covoiturage-edit]
 * 
 * TODO : 
 * - Attention si on crée un covoiturage à partir d'un autre (is_new_post())
 */
class AgendaPartage_Covoiturage_Edit {


	private static $initiated = false;
	private static $changes_for_revision = null;
	public static $revision_fields = [ 
				'cov-date-debut',
				'cov-organisateur', 
				'cov-email',
				'cov-depart',
				'cov-arrivee',
				'cov-description'
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
		
		//wp_mail depuis Contact Form 7
		add_filter( 'wp_mail', array(__CLASS__, 'wp_mail'), 10,1);
		//Maintient de la connexion de l'utilisateur pendant l'envoi du mail
		// add_filter( 'wpcf7_verify_nonce', array(__CLASS__, 'wpcf7_verify_nonce_cb' ));	
		add_filter( 'wpcf7_verify_nonce', '__return_true' );
		
		//Validation des valeurs
		add_filter( 'wpcf7_validate_text', array(__CLASS__, 'wpcf7_validate_fields_cb'), 10, 2);
		add_filter( 'wpcf7_validate_text*', array(__CLASS__, 'wpcf7_validate_fields_cb'), 10, 2);
		add_filter( 'wpcf7_validate_date', array(__CLASS__, 'wpcf7_validate_fields_cb'), 10, 2);
		add_filter( 'wpcf7_validate_date*', array(__CLASS__, 'wpcf7_validate_fields_cb'), 10, 2);
		add_filter( 'wpcf7_posted_data_text', array(__CLASS__, 'wpcf7_posted_data_fields_cb'), 10, 3);
		add_filter( 'wpcf7_spam', array(__CLASS__, 'wpcf7_spam_cb'), 10, 2);
		
		//Fenêtre de réinitialisation de mot de passe
		add_action( 'resetpass_form', array(__CLASS__, 'resetpass_form' ));
		
		add_action( 'wp_ajax_'.AGDP_TAG.'_'.AGDP_COVOIT_SECRETCODE, array(__CLASS__, 'on_wp_ajax_covoiturage_code_secret_cb') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_'.AGDP_COVOIT_SECRETCODE, array(__CLASS__, 'on_wp_ajax_covoiturage_code_secret_cb') );
	}
 	/////////////
	
	/**
	* Retourne le post actuel si c'est bien du type covoiturage
	*
	*/
	public static function get_post($covoiturage_id = false) {
		return AgendaPartage_Covoiturage::get_post($covoiturage_id);
	}
	
 	/**
	* Retourne faux si le post actuel de type covoiturage a déjà été enregistré (ID différent de 0).
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
 	 * Retourne le titre de la page
 	 */
	public static function get_post_title( ) {
 		if( $post = self::get_post()){
			$post_id = $post->ID;
			if($post_id){
				$post_title = isset( $post->post_title ) ? $post->post_title : '';
			
				$html = AgendaPartage_Covoiturage::get_covoiturage_dates_text( $post_id )
					. '<br>' . $post_title;
				return $html;
			}
		}
		return "Nouveau covoiturage";
	}
 
 	/**
 	 * Retourne la valeur part défaut d'un champ
 	 */
	public static function get_default_value( $field_name ) {
 		
		switch($field_name){
			case 'cov-phone-show' :
				return 0;
				
			case 'cov-nb-places' :
				return 1;
				
			case 'cov-' . AGDP_COVOIT_SECRETCODE :
				return AgendaPartage::get_secret_code(4, 'num');
			
			case 'cov-organisateur':			
				if(($user = wp_get_current_user())
				&& $user->ID !== 0)
					return $user->user_nicename;
				return '';
			case 'cov-email' :
				if(($user = wp_get_current_user())
				&& $user->ID !== 0)
					return $user->user_email;
				return '';
			default:
				throw new Exception( sprintf('L\'argument "%s" n\'est pas reconnu.', $field_name));
		}
		
	}
 
 	/**
 	 * Initialise les champs du formulaire
 	 */
	public static function get_covoiturage_edit_content( ) {
		global $post;
		
		$form_id = AgendaPartage::get_option('covoiturage_edit_form_id');
		if(!$form_id){
			return AgendaPartage::icon('warning', '', 'agdp-error-light'
				, 'Le formulaire de modification du covoiturage n\'est pas défini dans les réglages de AgendaPartage.', 'div');
		}
		
		$attrs = [];
		$post = self::get_post();
		
		//Action
		$duplicate_from_id = false;
 		if( ! $post && array_key_exists('action', $_GET) ){
			if($_GET['action'] === 'duplicate'
			&& array_key_exists(AGDP_ARG_COVOITURAGEID, $_GET)){
				$duplicate_from_id = $_GET[AGDP_ARG_COVOITURAGEID];
				$post = get_post($duplicate_from_id);
			}
		}
		
 		if( $post ){
 			$post_id = $post->ID;
			if( ! AgendaPartage_Covoiturage::user_can_change_post($post)){
				return self::get_covoiturage_edit_content_forbidden( $post );
			}
			$covoiturage_exists = ! $duplicate_from_id;
			$meta_name = 'cov-email' ;
			$email = AgendaPartage_Covoiturage::get_post_meta($post_id, $meta_name, true, false);
			
			/*if(!$email) {
				return AgendaPartage::icon('warning'
					, 'Vous ne pouvez pas modifier ce covoiturage, l\'initiateur n\'a pas associé d\'adresse email.'
					, 'agdp-error-light', 'div');
			}*/
			$attrs['cov-email'] = $email;
			$attrs['cov-description'] = $post->post_content;
			
			foreach(['cov-date-debut',
					'cov-heure-debut',
					'cov-heure-fin',
					'cov-intention',
					'cov-depart',
					'cov-arrivee',
					'cov-phone',
					'cov-phone-show',
					'cov-' . AGDP_COVOIT_SECRETCODE,
					'cov-organisateur',
					'cov-nb-places'
			] as $meta_name){
				$attrs[$meta_name] = AgendaPartage_Covoiturage::get_post_meta($post_id, $meta_name, true, false);
			}
		}
		else{
			$covoiturage_exists = false;
			$post_id = 0;
			
			foreach( [
				'cov-organisateur',
				'cov-email',
				'cov-nb-places',
				'cov-phone-show',
				'cov-' . AGDP_COVOIT_SECRETCODE
			] as $meta_name)
				$attrs[$meta_name] = self::get_default_value($meta_name);
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
		$input = sprintf('<input type="hidden" class="covoiturage_edit_form_data" data="%s"/>', $attrs);
		if($duplicate_from_id){
			$title = AgendaPartage_Covoiturage::get_post_title($post, true);
			$url = AgendaPartage_Covoiturage::get_post_permalink( $post_id, AGDP_COVOIT_SECRETCODE);
			$html = sprintf('<p class="info"> Duplication de covoiturage <a href="%s">%s</a></p>'
					, $url, $title)
				. $html;
			$input .= sprintf('<input type="hidden" name="covoiturage_duplicated_from" value="%s"/>', $duplicate_from_id);
		}
		elseif($post_id){
			//nécessaire en cas de 404 (hors connexion)
			$input .= sprintf('<input type="hidden" name="post_id" value="%s"/>', $post_id);
			
			//Maintient la transmission du code secret
			$ekey = AgendaPartage_Covoiturage::get_secretcode_in_request($post_id);		
			if($ekey){
				$input .= sprintf('<input type="hidden" name="%s" value="%s"/>', AGDP_COVOIT_SECRETCODE, $ekey);
			}
		}
		$html = str_ireplace('</form>', $input.'</form>', $html);
		
		if($covoiturage_exists){
			$html .= self::get_edit_toolbar($post);
		}
		return $html;
	}
	
	/**
	 * Html du bas de la zone de modification : dupliquer, supprimer, ...
	 */
	public static function get_edit_toolbar($post){
		$post_id = $post->ID;
		
		$html = '<div class="covoiturage-edit-toolbar">';
		
		$url = get_page_link( AgendaPartage::get_option('contact_page_id'));
		$url = add_query_arg(AGDP_ARG_COVOITURAGEID, $post_id, $url );
		$html .= sprintf('<span class="covoiturage-tool"><a href="%s" title="%s">%s%s</a></span>'
				, esc_url($url)
				, __('Ecrivez-nous pour signaler un problème avec ce covoiturage', AGDP_TAG)
				, AgendaPartage::icon('email-alt')
				, __('Un problème ?', AGDP_TAG)
		);
				
		if($post->post_status == 'publish')
			$html .= sprintf('<span class="covoiturage-tool">%s</span>', AgendaPartage_Covoiturage::get_covoiturage_action_link($post_id, 'unpublish', true));
		elseif( current_user_can('manage_options')
		|| (! AgendaPartage_Covoiturage::waiting_for_activation($post_id)
			&& AgendaPartage_Covoiturage::user_can_change_post($post_id))){
			$html .= sprintf('<span class="covoiturage-tool">%s</span>', AgendaPartage_Covoiturage::get_covoiturage_action_link($post_id, 'publish', true));
		}
		if(current_user_can('manage_options')
		|| current_user_can('covoiturage')
		|| AgendaPartage_Covoiturage::user_can_change_post($post_id))
			$html .= sprintf('<span class="covoiturage-tool">%s</span>', AgendaPartage_Covoiturage::get_covoiturage_action_link($post_id, 'duplicate', true));
		$html .= sprintf('<span class="covoiturage-tool">%s</span>', AgendaPartage_Covoiturage::get_covoiturage_action_link($post_id, 'remove', true));
		$html .= sprintf('<span class="covoiturage-tool">%s</span>', AgendaPartage_Covoiturage::get_covoiturage_contact_email_link($post_id, true));
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
		
		foreach( AgendaPartage_Covoiturage_Post_type::get_taxonomies() as $tax_name => $taxonomy){
		
			if($post){
				$post_terms = array();
				foreach(wp_get_post_terms($post->ID, $tax_name, []) as $term)
					$post_terms[ $term->term_id . ''] = $term->name;
			}
			else {
				$post_terms = false;
			}
			$all_terms = AgendaPartage_Covoiturage_Post_type::get_all_terms($tax_name);
			$checkboxes = '';
			$selected = '';
			$free_text = false;
			$index = 0;
			$titles = [];
			foreach($all_terms as $term){
				$checkboxes .= sprintf(' "%s|%d"', $term->name, $term->term_id);
				if($post_terms && array_key_exists($term->term_id . '', $post_terms)){
					$selected .= sprintf('%d_', $index+1);
				}
				elseif( ! $post && $term->default_checked)
					$selected .= sprintf('%d_', $index+1);
				
				if($term->description)
					$titles[$term->name] = $term->description;
				
				$index++;
			}
			$input_name = $taxonomy['input'];
					
			switch($tax_name){
				case AgendaPartage_Covoiturage::taxonomy_city :
					// $checkboxes .= '"(autre)|0"';
					// $free_text = 'free_text';
					break;
				case AgendaPartage_Covoiturage::taxonomy_diffusion :
					
					// debug_log($all_terms);
					break;
			}
			if( count($titles) === 0 )
				$titles = '';
			else
				//cf agendapartage.js
				$titles = sprintf('<span class="covoiturages-tax_titles hidden" input="%s" titles="%s"></span>'
							, 'cov-' . $tax_name . 's[]'
							, esc_attr(json_encode($titles))
				);
			$html = preg_replace('/\[(checkbox '.$input_name.')[^\]]*[\]]/'
								, sprintf('[$1 %s use_label_element %s %s]%s'
									, $free_text
									, $selected ? 'default:' . rtrim($selected, '_') : ''
									, $checkboxes
									, $titles)
								, $html);
								
		}
		
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
 	 * Contenu de la page d'édition en cas d'interdiction de modification d'un covoiturage
 	 */
	private static function get_covoiturage_edit_content_forbidden( $post ) {
		$post_id = $post->ID;
		
		$html = '<div class="covoiturage-forbidden">';
		$html .= '<div>' . AgendaPartage::icon('lock'
				, 'Vous n\'êtes pas autorisé à modifier ce covoiturage.', '', 'h4');
		
		if($post->post_status == 'trash'){
				$html .= 'Le covoiturage a été supprimé.';
		}
		else {
			$html .= '<ul>Pour pouvoir modifier un covoiturage vous devez remplir l\'une de ces conditions :';
			
			$html .= '<li>disposer d\'un code secret reçu par e-mail selon l\'adresse associée au covoiturage.';
			$html .= '<br>' . AgendaPartage_Covoiturage::get_covoiturage_contact_email_link($post, true);
			
			//Formulaire de saisie du code secret
			$url = AgendaPartage_Covoiturage::get_post_permalink( $post );
			$query = [
				'post_id' => $post_id,
				'action' => AGDP_TAG . '_' . AGDP_COVOIT_SECRETCODE
			];
			$html .= sprintf('<br>Vous connaissez le code secret de ce covoiturage :&nbsp;'
				. '<form class="agdp-ajax-action" data="%s">'
				. wp_nonce_field(AGDP_TAG . '-' . AGDP_COVOIT_SECRETCODE, AGDP_TAG . '-' . AGDP_COVOIT_SECRETCODE, true, false)
				.'<input type="text" placeholder="ici le code" name="'.AGDP_COVOIT_SECRETCODE.'" size="7"/>
				<input type="submit" value="Valider" /></form>'
					, esc_attr(json_encode($query)));
			$html .= '</li>';
			
			$html .= '<li>utiliser la même session internet qu\'à la création du covoiturage et, ce, le même jour.';

			$url = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
			$url = '/wp-login.php?redirect_to=' . sanitize_url($url);
			$html .= sprintf('<li>avoir un compte utilisateur sur le site, être <a href="%s">%sconnecté(e)</a> et avoir des droits suffisants.'
				, $url
				, AgendaPartage::icon('unlock')
			);
			if(is_user_logged_in()){
				global $current_user;
				//Rôle autorisé
				if(	! $current_user->has_cap( 'edit_posts' ) )
					$html .= '<br><i>De fait, vous êtes connecté(e) mais vous n\'avez pas les droits et le mail associé au covoiturage n\'est pas le vôtre.</i>';
			}
			$html .= '</li>';
			
			$html .= '<li>avoir un compte sur le site et être l\'initiateur du covoiturage.</li>';
			
			$html .= '<li>vous pouvez nous écrire pour signaler un problème ou demander une modification.';
			$url = get_page_link( AgendaPartage::get_option('contact_page_id'));
			$url = add_query_arg(AGDP_ARG_COVOITURAGEID, $post_id, $url );
			$html .= sprintf('<br><a href="%s">%s cliquez ici pour nous écrire à propos de ce covoiturage.</a>'
					, esc_url($url)
					, AgendaPartage::icon('email-alt'));
			
			$html .= '</ul>';
		}
		$html .= '</div>';
		$html .= '</div>';
		return $html;
	}
	
	
	/**
	 * Get code secret from Ajax query, redirect to post url
	 */
	public static function on_wp_ajax_covoiturage_code_secret_cb() {
		$ajax_response = '0';
		if(array_key_exists("post_id", $_POST)){
			$post = get_post($_POST['post_id']);
			if($post->post_type != AgendaPartage_Covoiturage::post_type)
				return;
			$input = $_POST[AGDP_COVOIT_SECRETCODE];
			$codesecret = AgendaPartage_Covoiturage::get_post_meta($post, 'cov-' . AGDP_COVOIT_SECRETCODE, true);
			if(strcasecmp( $codesecret, $input) == 0){
				//TODO : transient plutot que dans l'url
				$url = AgendaPartage_Covoiturage::get_post_permalink($post, AGDP_COVOIT_SECRETCODE . '=' . $codesecret);
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

	///////////
 	

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
			//Formulaire spécifique pour les covoiturages
			case AgendaPartage::get_option('covoiturage_edit_form_id') :
				return self::wp_mail_emails_fields($args);
				
			default:
				break;
		}
		
		return $args;
	}
	
	/**
	 * Redéfinit les adresses emails des pages de covoiturages vers le mail de l'organisateur de covoiturage ou, à défaut, vers l'auteur de la page.
	 * Le email2, email de copie, ne subit pas la redirection.
	 */
	private static function wp_mail_emails_fields($args){
		if( ! ($post = self::get_post()))
			return $args;
		$to_emails = parse_emails($args['to']);
		$headers_emails = parse_emails($args['headers']);
		$emails = array();
		//[ [source, header, name, user, domain], ]
		// 'user' in ['covoiturage', 'client', 'admin']
		//Dans la config du mail WPCF7, on a, par exemple, "To: [e-mail-ou-telephone]<client@agendapartage.net>"
		//on remplace client@agendapartage.net par l'email extrait de [e-mail-ou-telephone]
		//Ce qui veut dire que la forme complète "[e-mail-ou-telephone]<client@agendapartage.net>" doit apparaitre pour deviner l'email du client
		foreach (array_merge($to_emails, $headers_emails) as $value) {
			if($value['domain'] === AGDP_EMAIL_DOMAIN
			&& ! array_key_exists($value['user'], $emails)) {
				switch($value['user']){
					case 'covoiturage':
						$emails[$value['user']] = self::get_covoiturage_email_address($post);
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
		&& $password_message = self::new_password_link($post->post_author)){
			$args['message'] .= "\r\n<br>" . $password_message;
		}
		return $args;
	}

	/**
	 * Dans un email au contact de covoiturage, ajoute une invitation à saisir un nouveaui mot de passe.
	 * Returns a string to add to email for user to reset his password.
	 */
	private static function new_password_link($user_id){
		if(! array_key_exists( "new-password", $_POST)
		|| is_super_admin($user_id)
		|| $user_id == AgendaPartage_User::get_blog_admin_id()
		)
			return;
		$user = new WP_USER($user_id);
		$redirect_to = get_home_url( get_current_blog_id(), sprintf("wp-login.php?login=%s", rawurlencode( $user->user_login )), 'login' );
		$message = AgendaPartage_User::new_password_link($user, $redirect_to);
		return $message;
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


	/**
	 * Correction de caractères spéciaux
	 */
	public static function email_specialchars($args){
		$args['subject'] = str_replace('&#039;', "'", $args['subject']);
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
	 * Email de l'organisateur de covoiturage ou de l'auteur de la page Covoiturage
	 */
	public static function get_covoiturage_email_address($post){
		if(is_numeric($post)){
			$post_id = $post;
			$post = false;
		}
		else
			$post_id = $post->ID;
		if(!$post_id)
			return false;

		// Change l'adresse du destinataire
		$email = get_post_meta($post_id, 'cov-email', true);

		// 2ème email ?
		if( ! is_email($email)){
			$email = get_post_meta($post_id, 'cov-email2', true);
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
	 * Create a new covoiturage or update an existing one
	 * Called before email is sent
	 */
	public static function submit_covoiturage_form($contact_form, &$abort, $submission){
		$error_message = false;
		
		if( ! array_key_exists('covoiturage_duplicated_from', $_POST)){
			$post = self::get_post();
			if( ! is_object($post)){
				$post = false;
			}
			elseif( ! AgendaPartage_Covoiturage::user_can_change_post($post)){
				$abort = true;
				$error_message = sprintf('Vous n\'êtes pas autorisé à modifier ce covoiturage.');
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
				'cov-intention' => 1,
				'cov-depart' => 1,
				'cov-arrivee' => 1,
				'post_content' => 'cov-description',
				'cov-date-debut' => 1,
				'cov-heure-debut' => 1,
				'cov-heure-fin' => 1,
				'cov-organisateur' => 1,
				'cov-email' => 1,
				'cov-phone' => 1,
				'cov-nb-places' => 1,
				'cov-'.AGDP_COVOIT_SECRETCODE => 1
				) as $post_field => $input_field){
					if($input_field === 1) $input_field = $post_field;
					if(is_array($inputs[$input_field]))
						$data[$post_field] = trim($inputs[$input_field][0]);
					else
						$data[$post_field] = trim($inputs[$input_field]);
			}
			//checkboxes
			foreach(array(
				// 'cov-date-journee-entiere',
				// 'cov-message-contact'
				'cov-phone-show'
				) as $field){
				if(array_key_exists($field, $inputs)){
					if( is_array( $inputs[$field] ) )
						$data[$field] = $inputs[$field][0];
					else
						$data[$field] = $inputs[$field];
				}
			}
			
			//categories, communes et diffusions
			$tax_terms = [];
			foreach( AgendaPartage_Covoiturage_Post_type::get_taxonomies() as $tax_name => $taxonomy){
				$field = $taxonomy['input'];
			
				$tax_terms[ $tax_name ] = [];
				$all_terms = AgendaPartage_Covoiturage_Post_type::get_all_terms($tax_name, 'name'); //indexé par $term->name
				
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
			// $error_message = var_export( $tax_terms, true); 
		}
		elseif( ! is_array($contact_form)
			 || ! array_key_exists( 'title', $contact_form) ){
			return;
		}
		else {
			$data = $contact_form;
		}
		
		$data['cov-organisateur-show'] = 1;//TODO
		$data['cov-email-show'] = 0;//TODO
		
		// $meta_name = 'cov-'.AGDP_COVOIT_SECRETCODE;
		// if( $post && get_post_meta($post->ID, $meta_name, true))
			// unset($data[$meta_name]);
		// else {
			// $data[$meta_name] = AgendaPartage::get_secret_code(6);
		// }
		
		$meta_name = 'cov-sessionid';
		if( $post && get_post_meta($post->ID, $meta_name, true))
			unset($data[$meta_name]);
		else {
			$data[$meta_name] = AgendaPartage::get_session_id();
		}
		
		if( ($user = wp_get_current_user())
		&& $user->ID){
		    $post_author = $user->ID;
		}
		else {
			$post_author = AgendaPartage_User::get_blog_admin_id();
		}
		
		//Nouveau covoiturage et pas d'utilisateur connected, activation nécessaire par email
		$new_post_need_validation = AgendaPartage::get_option('covoiturage_need_validation', false);
		if( ! $post && ! $post_author ){
			$data['activation_key'] = $new_post_need_validation;
		}
		
		$post_title = AgendaPartage_Covoiturage::get_post_title($post, true, $data);
		$post_content = $data['post_content'];
		unset($data['post_content']);
		
		$postarr = array(
			'post_title' => $post_title,
			'post_name' => sanitize_title( $post_title ),
			'post_type' => AgendaPartage_Covoiturage::post_type,
			'post_author' => $post_author,
			'meta_input' => $data,
			'post_content' => $post_content,
			//'tax_input' => $tax_terms cf plus loin
		);
			/* var_export($field);echo ("\r\n");
			echo json_encode( $postarr);echo ("\r\n");
			// var_export($categories);echo ("\r\n");
			die(); */
		
		if( ! $error_message){
			
			if( $post_is_new = ! $post){
					
				if( is_user_logged_in() ){
					$postarr['post_status'] = 'publish';
					AgendaPartage::$skip_mail = true;
				}
				else {
					$postarr['post_status'] = $new_post_need_validation ? 'pending' : 'publish';
					AgendaPartage::$skip_mail = false;
				}
		
				//Check doublon
				$doublon = self::get_post_idem($post_title, $inputs);
				// var_dump($post_title, $inputs['cov-date-debut'], get_post_meta( $doublon, 'cov-date-debut', true));
				// die();
				if($doublon){
					if(is_a($doublon, 'WP_Post')){
						$url = AgendaPartage_Covoiturage::get_post_permalink($doublon);
						$error_message = sprintf('<br>Le covoiturage <a href="%s"><b>%s</b></a> existe déjà à la même date et pour le même lieu.', $url, htmlentities($doublon->post_title));
					}
					else
						$error_message = sprintf('<br>La recherche de covoiturage ayant le même titre, la même date et pour le même lieu indique une erreur : <br><pre>%s</pre>', $doublon);
				}
				
				if( ! $error_message){
					//Création du post
					$post_id = wp_insert_post( $postarr, true );
				}
				else
					$post_id = false;
			}
			else{
				
				$prev_email = get_post_meta($post->ID, 'cov-email', true);
				
				self::save_post_revision($post, $postarr);
				
				$postarr['ID'] = $post->ID;
				$post_id = wp_update_post( $postarr, true );
				
				AgendaPartage::$skip_mail = true;
			}
		
			if(is_wp_error($post_id)){
				AgendaPartage::$skip_mail = true;
				$error_message = $post_id->get_error_message();
				$post_id = $post ? $post->ID : false;
			}
		}
		
		
		//Changement des messages pour inclure le lien vers le nouveau post
		if($error_message){
			$abort = true;
			$error_message = sprintf('Le covoiturage n\'a pas été enregistré. %s', $error_message);
			$submission->set_response($error_message);
			return false;
		}
		else {
			
			//Taxonomies
			//Si on est pas connecté, les valeurs de tax_input ne sont pas mises à jour (wp_insert_post : current_user_can( $taxonomy_obj->cap->assign_terms )
			foreach($tax_terms as $tax_name => $tax_inputs){
				$result = wp_set_object_terms($post_id, $tax_inputs, $tax_name, false);
				if(is_a($result, 'WP_Error') || is_string($result)){
					$error_message = is_string($result) ? $result : $result->get_error_message();
					$abort = true;
					$error_message = sprintf('Erreur d\'enregistrement des catégories (%s). %s. \r\n%s', $tax_name, $error_message, var_export($tax_inputs, true));
					$submission->set_response($error_message);
					return false;
				}
			}
		}
				
		//Gestion interne du mail
		AgendaPartage::$skip_mail = true;
		
		if( $post_is_new && ! is_user_logged_in()){
			if( $data['cov-email']) {
				$result = AgendaPartage_Covoiturage::send_validation_email($post_id, false, false, 'bool');
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
		&& $data['cov-email'] && empty($prev_email)
		&& AgendaPartage_Covoiturage::waiting_for_activation($post)){
			$result = AgendaPartage_Covoiturage::send_validation_email($post_id, false, false, 'bool');
			
			//En cas de succès, on recharge la page dans laquelle on affichera un message.
			if($result)
				set_transient(AGDP_TAG . '_email_sent_' . $post_id, $post_id, 20);
		}
		
		$url = AgendaPartage_Covoiturage::get_post_permalink($post_id, AGDP_COVOIT_SECRETCODE);
		
		$messages = ($contact_form->get_properties())['messages'];
	
		$messages['mail_sent_ok'] = sprintf('redir:%s', $url);
		$messages['mail_sent_ng'] = sprintf('%s<br>Le covoiturage a bien été enregistré mais l\'e-mail n\'a pas pu être envoyé.<br><a href="%s">Afficher la page de le covoiturage</a>'
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
		
		if($post->post_type != AgendaPartage_Covoiturage::post_type){
			
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
		if($post->post_type != AgendaPartage_Covoiturage::post_type)
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
			case 'cov-heure-debut':
			case 'cov-heure-fin':
				$heure = isset( $_POST[$tag->name] ) ? trim( $_POST[$tag->name] ) : '';
				if(!$heure){
					break;
				}
				if(! preg_match("/^([0-1]?[0-9]|2[0-3])([hH:]([0-5][0-9])?)?$/", $heure, $matches)){
					$result->invalidate( $tag, "Heure incorrecte, elle doit être de la forme hh:mm." );
					break;
				}
				if($matches){
					$heure = sprintf('%s:%s',
						(strlen($matches[1]) == 1 ? '0' : '').$matches[1],
						count($matches) >= 3 && $matches[3] ? $matches[3] : '00'
						);
						// $submission = WPCF7_Submission::get_instance();
					$_POST[$tag->name] = $heure;
				}
				if($tag->name == 'cov-heure-fin'){
					$heure_debut = isset( $_POST['cov-heure-debut'] ) ? trim( $_POST['cov-heure-debut'] ) : '';
					if( $heure_debut && $heure < $heure_debut
					&& (! $_POST['cov-date-fin'] 
						|| $_POST['cov-date-fin'] == $_POST['cov-date-debut'])) {
						$result->invalidate( $tag, sprintf("Heure de fin incorrecte (%s), elle ne peut pas être antérieure à l'heure de début (%s). Elle peut être vide.", $heure, $heure_debut) );
						break;
					}
				}
				
				break;
			case 'cov-date-debut':
			case 'cov-date-fin':
				$strDate = isset( $_POST[$tag->name] ) ? trim( $_POST[$tag->name] ) : '';
				if(!$strDate){
					break;
				}
				$date = strtotime($strDate);
				$today = strtotime(date("Y-m-d"));
				$invalide_date = $date < $today;
				if($invalide_date) {
					if($tag->name == 'cov-date-debut'){
						$date_fin = isset( $_POST['cov-date-fin'] ) ? trim( $_POST['cov-date-fin'] ) : '';
						if($date_fin){
							$date_fin = strtotime($date_fin);
							$invalide_date = $date_fin < $date_fin;
						}
					}
					if($invalide_date)
						$result->invalidate( $tag, sprintf("Date incorrecte (%s), elle doit être supérieure ou égale à aujourd'hui (%s).", date("d/m/Y", $date), date("d/m/Y") ) );
					break;
				}
				else {
					$to_late = strtotime(date("Y-m-d") . ' + 2 year');
					$invalide_date = $date > $to_late;
					if($invalide_date) {
						$result->invalidate( $tag, sprintf("Date incorrecte (%s), elle ne peut être aussi éloignée d'aujourd'hui (%s maxi).", date("d/m/Y", $date), date("d/m/Y", $to_late) ) );
						break;
					}
				}
				if($tag->name == 'cov-date-fin'){
					$date_debut = isset( $_POST['cov-date-debut'] ) ? trim( $_POST['cov-date-debut'] ) : '';
					if( $date < strtotime($date_debut)) {
						$result->invalidate( $tag, sprintf("Date de fin incorrecte (%s), elle ne peut pas être antérieure à la date de début (%s). Elle peut être vide.", date("d/m/Y", $date), date("d/m/Y", $date_debut) ) );
						break;
					}
				}
				break;
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
			case 'cov-heure-debut':
			case 'cov-heure-fin':
				$heure = isset( $_POST[$tag->name] ) ? trim( $_POST[$tag->name] ) : '';
				if(!$heure){
					break;
				}
				if(! preg_match("/^([0-1]?[0-9]|2[0-3])([hH:]([0-5][0-9])?)?$/", $heure, $matches)){
					break;
				}
				if($matches){
					$value = sprintf('%sh%s',
						// (strlen($matches[1]) == 1 ? '0' : '').$matches[1],
						strlen($matches[1]) == 2 && $matches[1][0] === '0'  ? $matches[1][1] : $matches[1],
						count($matches) >= 3 && $matches[3] 
							? ($matches[3] == '0' || $matches[3]  == '00' ? '' : $matches[3])
							: ''//'00'
					);
				}
				break;
				
			// case 'cov-localisation' :
				// if( ! $_POST[$tag->name]
				// && isset( $_POST['cov-cities'])
				// && $_POST['cov-cities'] ){
					// return is_array($_POST['cov-cities']) ? implode (', ', $_POST['cov-cities']) : $_POST['cov-cities'];
				// }
				// break;
			default:
				break;
		}
  
		return $value;
	}

	
	/**
	 * Recherche de covoiturage identique
	 */
	public static function get_post_idem($post_title, $meta_values){
		if( ! is_array($meta_values))
			throw new TypeError('$meta_values should be an array.');
		$args = AgendaPartage_Covoiturages::get_posts_query( 
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
				[ 'key' => 'cov-date-debut', 'value' => $meta_values['cov-date-debut']],
				[ 'key' => 'cov-depart', 'value' => $meta_values['cov-depart']],
				[ 'key' => 'cov-arrivee', 'value' => $meta_values['cov-arrivee']]
		];
		if($meta_values['cov-heure-debut'])
			$args['meta_query'][] = [ 'key' => 'cov-heure-debut', 'value' => $meta_values['cov-heure-debut']];
				
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
}
