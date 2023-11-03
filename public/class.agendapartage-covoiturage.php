<?php

/**
 * AgendaPartage -> Covoiturage
 * Custom post type for WordPress.
 * 
 * Définition du Post Type covoiturage
 * Définition de la taxonomie ev_category
 * Redirection des emails envoyés depuis une page Covoiturage
 * A l'affichage d'un covoiturage, le Content est remplacé par celui du covoiturage Modèle
 * En Admin, le bloc d'édition du Content est masqué d'après la définition du Post type : le paramètre 'supports' qui ne contient pas 'editor'
 *
 * Voir aussi AgendaPartage_Admin_Covoiturage
 */
class AgendaPartage_Covoiturage {

	const post_type = 'covoiturage';
	const taxonomy_city = 'cov_city';
	const taxonomy_diffusion = 'cov_diffusion';

	const user_role = 'author';

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
		self::init_hooks_for_search();
		add_filter( 'the_title', array(__CLASS__, 'the_title'), 10, 2 );
		add_filter( 'the_content', array(__CLASS__, 'the_content'), 10, 1 );
		add_filter( 'navigation_markup_template', array(__CLASS__, 'on_navigation_markup_template_cb'), 10, 2 );
		
		add_filter( 'pre_handle_404', array(__CLASS__, 'on_pre_handle_404_cb'), 10, 2 );
		add_filter( 'redirect_canonical', array(__CLASS__, 'on_redirect_canonical_cb'), 10, 2);
		
		add_action( 'wp_ajax_covoiturage_action', array(__CLASS__, 'on_wp_ajax_covoiturage_action_cb') );
		add_action( 'wp_ajax_nopriv_covoiturage_action', array(__CLASS__, 'on_wp_ajax_covoiturage_action_cb') );
		
		add_filter( 'wpcf7_form_class_attr', array(__CLASS__, 'on_wpcf7_form_class_attr_cb'), 10, 1 ); 

		add_action( 'wp_ajax_'.AGDP_TAG.'_'.AGDP_EMAIL4PHONE, array(__CLASS__, 'on_wp_ajax_covoiturage_email4phone_cb') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_'.AGDP_EMAIL4PHONE, array(__CLASS__, 'on_wp_ajax_covoiturage_email4phone_cb') );
	}
	
	/**
	 * Hook navigation template
	 * Supprime la navigation dans les posts de covoiturages
	 */
	public static function on_navigation_markup_template_cb( $template, $class){
		if($class == 'post-navigation'){
			// var_dump($template, $class);
			global $post;
			if( $post
			 && $post->post_type == self::post_type){
					$template = '<!-- no nav -->';
				};
		}
		return $template;
	}
	
	/**
	 * Hook d'une page introuvable.
	 * Il peut s'agir d'un covoiturage qui vient d'être créé et que seul son créateur peut voir.
	 */
	public static function on_pre_handle_404_cb($preempt, $query){
		if( ! have_posts()){
			//var_dump($query);
			//Dans le cas où l'agenda est la page d'accueil, l'url de base avec des arguments ne fonctionne pas
			if(is_home()){
				if( (! isset($query->query_vars['post_type'])
					|| $query->query_vars['post_type'] === '')
				&& isset($query->query[AGDP_ARG_COVOITURAGEID])){
					$page = AgendaPartage::get_option('agenda_page_id');
					$query->query_vars['post_type'] = 'page';
					$query->query_vars['page_id'] = $page;
					global $wp_query;
					$wp_query = new WP_Query($query->query_vars);
					return false;
						
				}
			}
			
			//Dans le cas d'une visualisation d'un covoiturage non publié, pour le créateur non connecté
			if(isset($query->query['post_type'])
			&& $query->query['post_type'] == self::post_type){
				foreach(['p', 'post', 'post_id', self::post_type] as $key){
					if( array_key_exists($key, $query->query)){
						if(is_numeric($query->query[$key]))
							$post = get_post($query->query[$key]);
						else{
							//Ne fonctionne pas en 'pending', il faut l'id
							$post = get_page_by_path(self::post_type . '/' . $query->query[$key]);
						}
						if(!$post)
							return false;
		
						if(in_array($post->post_status, ['draft','pending','future'])){
							
							$query->query_vars['post_status'] = $post->post_status;
							global $wp_query;
							$wp_query = new WP_Query($query->query_vars);
							return false;
							
							// self::on_covoiturage_404( $post ); // call exit() inside
						
						}
						return true;
					}
				}
			}
		}
	}
	
	/**
	 * Interception des redirections "post_type=covoiturage&p=1837" vers "/covoiturage/nom-de-l-covoiturage" si il a un post_status != 'publish'
	 */
	public static function on_redirect_canonical_cb ( $redirect_url, $requested_url ){
		$query = parse_url($requested_url, PHP_URL_QUERY);
		parse_str($query, $query);
		//var_dump($query, $redirect_url, $requested_url);
		if(isset($query['post_type']) && $query['post_type'] == self::post_type
		&& isset($query['p']) && $query['p']){
			$post = get_post($query['p']);
			if($post){
				if($post->post_status != 'publish'){
					// die();
					return false;
				}
				else{
					$redirect_url = str_replace('&etat=en-attente', '', $redirect_url);
				}
				//TODO nocache_headers();
			}
		}
		return $redirect_url;
	}
	
	
	/***************
	 * the_title()
	 */
 	public static function the_title( $title, $post_id ) {
 		global $post;
 		if( ! $post
 		|| $post->ID != $post_id
 		|| $post->post_type != self::post_type){
 			return $title;
		}
	    return self::get_post_title( $post );
	}

	/**
	 * Hook
	 */
 	public static function the_content( $content ) {
 		global $post;
 		if( ! $post
 		|| $post->post_type != self::post_type){
 			return $content;
		}
			
		if(isset($_GET['action']) && $_GET['action'] == 'activation'){
			$post = self::do_post_activation($post);
		}
		
	    return self::get_post_content( $post );
	}
	
	/**
	 * Returns, par exemple, le meta cov-siteweb. Mais si $check_show_field, on teste si le meta cov-siteweb-show est vrai.
	 */
	public static function get_post_meta($post_id, $meta_name, $single = false, $check_show_field = null){
		if(is_a($post_id, 'WP_Post'))
			$post_id = $post_id->ID;
		if($check_show_field){
			if(is_bool($check_show_field))
				$check_show_field = '-show';
			if( ! get_post_meta($post_id, $meta_name . $check_show_field, true))
				return;
		}
		return get_post_meta($post_id, $meta_name, true);

	}
 
 	/**
 	 * Retourne l'ID du post servant de message
 	 */
	public static function get_covoiturage_message_contact_form_id( ) {
		$option_id = 'covoiturage_message_contact_form_id';
		return AgendaPartage::get_option($option_id);
	}
 
 	/**
 	 * Retourne le titre de la page
 	 */
	public static function get_post_title( $covoiturage = null, $no_html = false, $data = false) {
 		if( ! isset($covoiturage) || ! is_object($covoiturage)){
			global $post;
			$covoiturage = $post;
		}
		
		// $post_title = isset( $covoiturage->post_title ) ? $covoiturage->post_title : '';
		$intentionid = $data ? $data['cov-intention'] : get_post_meta($covoiturage->ID, 'cov-intention', true);
		$intention = AgendaPartage_Covoiturage_Post_type::get_intention_label($intentionid);
		$le = "Le";
		$dates = self::get_covoiturage_dates_text( $covoiturage->ID, $data );
		$de = "de";
		$depart = $data ? $data['cov-depart'] : get_post_meta($covoiturage->ID, 'cov-depart', true);
		$vers = "vers";
		$arrivee = $data ? $data['cov-arrivee'] : get_post_meta($covoiturage->ID, 'cov-arrivee', true);
		if( !  $no_html){
			$intention = sprintf('<span class="cov-intention cov-intention-%s">%s</span>', $intentionid, $intention);
			$dates = preg_replace('/^(\w+\s)([0-9]+)/', '$1<span class="cov-date-jour-num">$2</span>', $dates);
			$depart = sprintf('<span class="cov-depart">%s</span>', htmlentities($depart));
			$arrivee = sprintf('<span class="cov-arrivee">%s</span>', htmlentities($arrivee));
			$le = sprintf('<span class="title-prep">%s</span>', $le);
			$de = sprintf('<span class="title-prep">%s</span>', $de);
			$vers = sprintf('<span class="title-prep">%s</span>', $vers);
		}
		$separator = $no_html ? ', ' : '<br>';
		$html = $intention . $separator
			. $le . ' ' . $dates . $separator
			. $de . ' ' . $depart . $separator
			. $vers . ' ' . $arrivee;
		return $html;
	}

	/**
	 * Cherche le code secret dans la requête et le compare à celui du post
	 */
	public static function get_secretcode_in_request( $covoiturage ) {
		// Ajax : code secret
		if(array_key_exists(AGDP_COVOIT_SECRETCODE, $_REQUEST)){
			$meta_name = 'cov-'.AGDP_COVOIT_SECRETCODE;
			$codesecret = self::get_post_meta($covoiturage, $meta_name, true);		
			if($codesecret
			&& (strcasecmp( $codesecret, $_REQUEST[AGDP_COVOIT_SECRETCODE]) !== 0)){
				$codesecret = '';
			}
		}
		else 
			$codesecret = false;
		return $codesecret;
	}
	
 	/**
 	 * Retourne le Content de la page du covoiturage
 	 */
	public static function get_post_content( $covoiturage = null ) {
		global $post;
 		if( ! isset($covoiturage) || ! is_a($covoiturage, 'WP_Post')){
			$covoiturage = $post;
		}

		$codesecret = self::get_secretcode_in_request($covoiturage);
		
		$html = '[covoiturage-description]
		[covoiturage info="organisateur" label="Initiateur : "]
		[covoiturage info="phone" label="Téléphone : "]';
		if( AgendaPartage_Covoiturage_Post_type::is_diffusion_managed() )
			$html .='[covoiturage-diffusions label="Diffusion (sous réserve) : "]';

		$meta_name = 'cov-email' ;
		$email = get_post_meta($covoiturage->ID, $meta_name, true);
		if( ! is_email($email))
			$email = false;
		
		$html .= sprintf('[covoiturage-modifier-covoiturage toggle="Modifier ce covoiturage" no-ajax post_id="%d" %s]'
			, $covoiturage->ID
			, $codesecret ? AGDP_COVOIT_SECRETCODE . '=' . $codesecret : ''
		);
		
		if( $email && current_user_can('manage_options') ){
			$form_id = AgendaPartage::get_option('admin_message_contact_form_id');
			if(! $form_id){
				return '<p class="">Le formulaire de message à l\'organisateur du covoiturage n\'est pas défini dans le paramétrage de AgendaPartage.</p>';
			}
			$user = wp_get_current_user();
			$html .= sprintf('[toggle title="Message de l\'administrateur (%s) à l\'organisateur du covoiturage" no-ajax] [contact-form-7 id="%s"] [/toggle]'
				, $user->display_name, $form_id);
		}
		switch($post->post_status){
			case 'pending':
				$status = 'En attente de relecture';
			case 'future':
				if(!$status) $status = 'Pour le futur';
			case 'draft':
				if(!$status) $status = 'Brouillon';
				
				if($email_sent = get_transient(AGDP_TAG . '_email_sent_' . $covoiturage->ID)){
					delete_transient(AGDP_TAG . '_email_sent_' . $covoiturage->ID);
				}
				elseif($no_email = get_transient(AGDP_TAG . '_no_email_' . $covoiturage->ID)){
					delete_transient(AGDP_TAG . '_no_email_' . $covoiturage->ID);
					if(empty($codesecret))
						$secretcode = get_post_meta($post->ID, 'cov-'.AGDP_COVOIT_SECRETCODE, true);
				}
				
				$alerte = sprintf('<p class="alerte">Ce covoiturage est <b>en attente de validation</b>, il a le statut "%s".'
					.'<br>Il n\'est <b>pas visible</b> dans l\'agenda.'
					. '</p>'
					. (isset($email_sent) && $email_sent ? '<div class="info">Un e-mail a été envoyé pour permettre la validation de ce nouveau covoiturage. Vérifiez votre boîte mails, la rubrique spam aussi.</div>' : '')
					. (isset($no_email) && $no_email ? '<div class="alerte">Vous n\'avez pas indiqué d\'adresse mail pour permettre la validation de ce nouveau covoiturage.'
											. '<br>Vous devrez attendre la validation par un modérateur pour que ce covoiturage soit public.'
											. '<br>Vous pouvez encore modifier ce covoiturage et renseigner l\'adresse mail.'
											. '<br>Le code secret de ce covoiturage est : <b>'.$secretcode.'</b>'
											. '</div>' : '')
					, $status);
				$html = $alerte . $html;
				break;
			case 'publish': 
				$page_id = AgendaPartage::get_option('covoiturages_page_id');
				if($page_id){
					$url = self::get_post_permalink($page_id, AGDP_COVOIT_SECRETCODE);
					$url = add_query_arg( AGDP_ARG_COVOITURAGEID, $covoiturage->ID, $url);
					$url .= '#' . AGDP_ARG_COVOITURAGEID . $covoiturage->ID;
					$html .= sprintf('<br><br>Pour voir ce covoiturage dans la liste, <a href="%s">cliquez ici %s</a>.'
					, $url
					, AgendaPartage::icon('car'));
				}
				break;
		}
		
			
		if(is_user_logged_in()){
			global $current_user;
			//Rôle autorisé
			if(	$current_user->has_cap( 'edit_posts' ) ){
			
				$creator = new WP_User($covoiturage->post_author);
				$html .= '<p>créé par "' . $creator->get('user_nicename') . '"</p>';
			}
		}
		return $html;
	}
	
	/*******************
	 * Actions via Ajax
	 *******************/
	/**
	 * Retourne un lien html pour l'envoi d'un mail à l'organisateur
	 */
	public static function get_covoiturage_contact_email_link($post, $icon = false, $message = null, $title = false, $confirmation = null){
		$html = '';
		$meta_name = 'cov-email' ;
		$email = self::get_post_meta($post, $meta_name, true);
		if(!$email){
			$html .= '<p class="alerte">Ce covoiturage n\'a pas d\'adresse e-mail associée.</p>';
		}
		else {
			if(current_user_can('manage_options'))
				$data = [ 'force-new-activation' => true ];
			else
				$data = null;
			$html = self::get_covoiturage_action_link($post, 'send_email', $icon, $message, $title, $confirmation, $data);
		}
		return $html;
	}
	
	/**
	 * Retourne un lien html pour une action générique
	 */
	public static function get_covoiturage_action_link($post, $method, $icon = false, $caption = null, $title = false, $confirmation = null, $data = null){
		$need_can_user_change = true;
		switch($method){
			case 'remove':
				if($caption === null)
					$caption = __('Supprimer', AGDP_TAG);
				if($confirmation === null || $confirmation === true)
					$confirmation = 'Confirmez-vous la suppression définitive du covoiturage ?';
				break;
			case 'duplicate':
				if($caption === null)
					$caption = __('Dupliquer', AGDP_TAG);
				if($confirmation === true)
					$confirmation = 'Confirmez-vous la duplication du covoiturage ?';
				if($icon === true)
					$icon = 'admin-page';
				break;
			case 'unpublish':
				if($caption === null)
					$caption = __('Masquer dans l\'agenda', AGDP_TAG);
				if($icon === true)
					$icon = 'hidden';
				if($confirmation === null || $confirmation === true)
					$confirmation = 'Confirmez-vous que le covoiturage ne sera plus visible ?';

				break;
			case 'publish':
				if($caption === null)
					$caption = __('Rendre public dans les covoiturages', AGDP_TAG);
				if($icon === true)
					$icon = 'visibility';
				if($confirmation === null || $confirmation === true)
					$confirmation = 'Confirmez-vous de rendre visible le covoiturage ?';
				
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
				$meta_name = 'cov-email' ;
				$email = self::get_post_meta($post, $meta_name, true);
				$email_parts = explode('@', $email);
				$email_trunc = substr($email, 0, 3) . str_repeat('*', strlen($email_parts[0])-3);
				if($caption === null){
					$caption = 'E-mail de validation';
					$title = sprintf('Cliquez ici pour envoyer un e-mail de validation du covoiturage à l\'adresse %s@%s', $email_trunc, $email_parts[1]);
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
		if($need_can_user_change && ! self::user_can_change_covoiturage($post)){
			$html .= '<p class="alerte">Ce covoiturage ne peut pas être modifié par vos soins.</p>';
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
				$query[AGDP_COVOIT_SECRETCODE] = $ekey;

			if($confirmation){
				$query['confirm'] = $confirmation;
			}
			if($icon)
				$icon = AgendaPartage::icon($icon);
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
	public static function on_wp_ajax_covoiturage_action_cb() {
		
		// debug_log('on_wp_ajax_covoiturage_action_cb');	
		
		if( ! AgendaPartage::check_nonce())
			wp_die();
		
		$ajax_response = '0';
		if(!array_key_exists("method", $_POST)){
			wp_die();
		}
		$method = $_POST['method'];
		if(array_key_exists("post_id", $_POST)){
			try{
				//cherche une fonction du nom "covoiturage_action_{method}"
				$function = array(__CLASS__, sprintf('covoiturage_action_%s', $method));
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
	public static function covoiturage_action_remove($post_id) {
		if ( self::do_remove($post_id) )
			return 'redir:' . AgendaPartage_Covoiturages::get_url(); //TODO add month in url
		return 'Impossible de supprimer ce covoiturage.';
	}
	
	/**
	 * Duplicate event
	 */
	public static function covoiturage_action_duplicate($post_id) {
		if ( self::user_can_change_covoiturage($post_id) )
			return 'redir:' . add_query_arg(
				'action', 'duplicate'
				, add_query_arg(AGDP_ARG_COVOITURAGEID, $post_id
					, get_page_link(AgendaPartage::get_option('new_covoiturage_page_id'))
				)
			);
		return 'Impossible de retrouver ce covoiturage.';
	}
	
	/**
	 * Unpublish event
	 */
	public static function covoiturage_action_unpublish($post_id) {
		$post_status = 'pending';
		if( self::change_post_status($post_id, $post_status) )
			return 'redir:' . self::get_post_permalink($post_id, true, AGDP_COVOIT_SECRETCODE, 'etat=en-attente');
		return 'Impossible de modifier ce covoiturage.';
	}
	/**
	 * Publish event
	 */
	public static function covoiturage_action_publish($post_id) {
		$post_status = 'publish';
		if( (! self::waiting_for_activation($post_id)
			|| current_user_can('manage_options') )
		&& self::change_post_status($post_id, $post_status) )
			return 'redir:' . self::get_post_permalink($post_id, AGDP_COVOIT_SECRETCODE);
		return 'Impossible de modifier le statut.<br>Ceci peut être effectué depuis l\'e-mail de validation.';
	}
	/**
	 * Send contact email
	 */
	public static function covoiturage_action_send_email($post_id) {
		if(isset($_POST['data']) && is_array($_POST['data'])
		&& isset($_POST['data']['force-new-activation']) && $_POST['data']['force-new-activation']){
			self::get_activation_key($post_id, true); //reset
		}
		return self::send_validation_email($post_id);
	}
	
	/**
	 * Remove event
	 */
	public static function do_remove($post_id) {
		if(self::user_can_change_covoiturage($post_id)){
			// $post = wp_delete_post($post_id);
			$post = self::change_post_status($post_id, 'trash');
			return ! is_a($post, 'WP_Error');
		}
		// echo self::user_can_change_covoiturage($post_id, false, true);
		return false;
	}
	
	/**
	 * Change post status
	 */
	public static function change_post_status($post_id, $post_status) {
		if($post_status == 'publish')
			$ignore = 'sessionid';
		else
			$ignore = false;
		if(self::user_can_change_covoiturage($post_id, $ignore)){
			$postarr = ['ID' => $post_id, 'post_status' => $post_status];
			$post = wp_update_post($postarr, true);
			return ! is_a($post, 'WP_Error');
		}
		// echo self::user_can_change_covoiturage($post_id, $ignore, true);
		return false;
	}
	
	/**
	 * Envoye le mail à l'organisateur du covoiturage
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
		
		$codesecret = self::get_post_meta($post, 'cov-' . AGDP_COVOIT_SECRETCODE, true);
		
		$meta_name = 'cov-email' ;
		$email = self::get_post_meta($post, $meta_name, true);
		$to = $email;
		
		$site = get_bloginfo( 'name' );
		
		$subject = sprintf('[%s][Validation] %s', $site, $subject ? $subject : $post->post_title);
		
		$headers = array();
		$attachments = array();
		
		if( ! $message){
			$message = sprintf('Bonjour,<br>Vous recevez ce message suite la création du covoiturage ci-dessous ou à une demande depuis le site et parce que votre e-mail est associé au covoiturage.');

		}
		else
			$message .= '<br>'.str_repeat('-', 20);
		
		$url = self::get_post_permalink($post, true);
		
		switch($post->post_status){
			case 'pending':
				$status = 'En attente de relecture';
			case 'draft':
				if(!$status) $status = 'Brouillon';
				$message .= sprintf('<br><br>Ce covoiturage n\'est <b>pas visible</b> en ligne, il est marqué comme "%s".', $status);
				
				if( self::waiting_for_activation($post) ){
					$activation_url = add_query_arg(AGDP_COVOIT_SECRETCODE, $codesecret, $url);
					$activation_url = add_query_arg('action', 'activation', $activation_url);
					$activation_url = add_query_arg('ak', self::get_activation_key($post), $activation_url);
					$activation_url = add_query_arg('etat', 'en-attente', $activation_url);
				}
				
				$message .= sprintf('<br><br><a href="%s"><b>Cliquez ici pour rendre ce covoiturage public dans l\'agenda</b></a>.<br>', $activation_url);
				break;
			case 'trash':
				$message .= sprintf('<br><br>Ce covoiturage a été SUPPRIMÉ.');
				break;
		}
		
		$message .= sprintf('<br><br>Le code secret de ce covoiturage est : %s', $codesecret);
		// $args = AGDP_COVOIT_SECRETCODE .'='. $codesecret;
		// $codesecret_url = $url . (strpos($url,'?')>0 || strpos($args,'?') ? '&' : '?') . $args;			
		$codesecret_url = add_query_arg(AGDP_COVOIT_SECRETCODE, $codesecret, $url);
		$message .= sprintf('<br><br>Pour modifier ce covoiturage, <a href="%s">cliquez ici</a>', $codesecret_url);
		
		$url = self::get_post_permalink($post);
		$message .= sprintf('<br><br>La page publique de ce covoiturage est : <a href="%s">%s</a>', $url, $url);

		$message .= '<br><br>Bien cordialement,<br>L\'équipe de l\'Agenda partagé.';
		
		$message .= '<br>'.str_repeat('-', 20);
		$message .= sprintf('<br><br>Détails du covoiturage :<br><code>%s</code>', self::get_post_details_for_email($post));
		
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
	 * Détails du covoiturage pour insertion dans un email
	 */
	public static function get_post_details_for_email($post, $to_author = true){
		if(is_numeric($post)){
			$post = get_post($post);
		}
		$post_id = $post->ID;
		$html = '<table><tbody>';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Titre', htmlentities($post->post_title));
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Dates', self::get_covoiturage_dates_text($post_id));
		$meta_name = 'cov-depart';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Départ', htmlentities(get_post_meta($post_id, $meta_name, true)));
		$meta_name = 'cov-arrivee';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Destination', htmlentities(get_post_meta($post_id, $meta_name, true)));
		$html .= sprintf('<tr><td>%s : </td><td><pre>%s</pre></td></tr>', 'Description', htmlentities($post->post_content));
		$meta_name = 'cov-organisateur';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Organisateur', htmlentities(get_post_meta($post_id, $meta_name, true)));
		$meta_name = 'cov-phone';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Téléphone', htmlentities(get_post_meta($post_id, $meta_name, true)));
		if($to_author) {
			$meta_name = 'cov-phone-show';
			$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Afficher publiquement le n° de téléphone', get_post_meta($post_id, $meta_name, true) ? 'oui' : 'non');
		}
		$meta_name = 'cov-email';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Email', get_post_meta($post_id, $meta_name, true));
		
		$html .= '</tbody></table>';
		return $html;
		
	}
	/**
	 * Clé d'activation depuis le mail pour basculer en 'publish'
	 */
	public static function get_activation_key($post, $force_new = false){
		if(is_numeric($post)){
			$post = get_post($post);
		}
		$post_id = $post->ID;
		$meta_name = 'activation_key';
		
		$value = get_post_meta($post_id, $meta_name, true);
		if($value && $value != 1 && ! $force_new)
			return $value;
		
		$guid = uniqid();
		
		$value = crypt($guid, AGDP_TAG . '-' . $meta_name);
		
		update_post_meta($post_id, $meta_name, $value);
		
		return $value;
		
	}
	/**
	 * Indique que l'activation depuis le mail n'a pas été effectuée
	 */
	public static function waiting_for_activation($post_id){
		if(is_a($post_id, 'WP_Post'))
			$post_id = $post_id->ID;
		$meta_name = 'activation_key';
		$value = get_post_meta($post_id, $meta_name, true);
		return !! $value;
		
	}
	
	/**
	 * Contrôle de la clé d'activation 
	 */
	public static function check_activation_key($post, $value){
		if(is_numeric($post)){
			$post = get_post($post);
		}
		$post_id = $post->ID;
		$meta_name = 'activation_key';
		$meta_value = get_post_meta($post_id, $meta_name, true);
		return hash_equals($value, $meta_value);
	}
	
	/**
	 * Effectue l'activation du post
	 */
	public static function do_post_activation($post){
		if(is_numeric($post)){
			$post_id = $post;
			$post = get_post($post);
		}
		else
			$post_id = $post->ID;
		if(isset($_GET['ak']) 
		&& (! self::waiting_for_activation($post_id)
			|| self::check_activation_key($post, $_GET['ak']))){
			if($post->post_status != 'publish'){
				$result = wp_update_post(array('ID' => $post->ID, 'post_status' => 'publish'));
				$post->post_status = 'publish';
				if(is_wp_error($result)){
					var_dump($result);
				}
				echo '<p class="info">Le covoiturage est désormais activé et visible dans l\'agenda</p>';
			}
			$meta_name = 'activation_key';
			delete_post_meta($post->ID, $meta_name);
		}
		return $post;
	}
 	
	/***********************************************************/
	/**
	 * Extend WordPress search to include custom fields
	 *
	 * https://adambalee.com
	 */
	private static function init_hooks_for_search(){
		add_filter('posts_join', array(__CLASS__, 'cf_search_join' ));
		add_filter( 'posts_where', array(__CLASS__, 'cf_search_where' ));
		add_filter( 'posts_distinct', array(__CLASS__, 'cf_search_distinct' ));
	}
	/**
	 * Join posts and postmeta tables
	 *
	 * http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_join
	 */
	public static function cf_search_join( $join ) {
	    global $wpdb;

	    if ( is_search() ) {    
	        $join .=' LEFT JOIN '.$wpdb->postmeta. ' ON '. $wpdb->posts . '.ID = ' . $wpdb->postmeta . '.post_id ';
	    }

	    return $join;
	}

	/**
	 * Modify the search query with posts_where
	 *
	 * http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_where
	 */
	public static function cf_search_where( $where ) {
	    global $pagenow, $wpdb;

	    if ( is_search() ) {
	        $where = preg_replace(
	            "/\(\s*".$wpdb->posts.".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
	            "(".$wpdb->posts.".post_title LIKE $1) OR (".$wpdb->postmeta.".meta_value LIKE $1)", $where );
	    }

	    return $where;
	}

	/**
	 * Prevent duplicates
	 *
	 * http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_distinct
	 */
	public static function cf_search_distinct( $where ) {
	    global $wpdb;

	    if ( is_search() ) {
	        return "DISTINCT";
	    }

	    return $where;
	}
	
	/**
	 * Retourne le texte des dates et heures d'un covoiturage
	 */
	public static function get_covoiturage_dates_text( $post_id, $data = false ) {
		if($post_id && is_object($post_id))
			$post_id = $post_id->ID;
		$date_debut    = $data ? $data['cov-date-debut'] : get_post_meta( $post_id, 'cov-date-debut', true );
		$heure_debut    = $data ? $data['cov-heure-debut'] : get_post_meta( $post_id, 'cov-heure-debut', true );
		$date_fin    = $date_debut;
		$heure_fin    = $data ? $data['cov-heure-fin'] : get_post_meta( $post_id, 'cov-heure-fin', true );
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
	 * Retourne les communes d'un covoiturage
	 */
	public static function get_covoiturage_cities( $post_id, $args = 'names' ) {
		return self::get_covoiturage_terms( self::taxonomy_city, $post_id, $args);
	}
	/**
	 * Retourne les diffusions possibles d'un covoiturage
	 */
	public static function get_covoiturage_diffusions( $post_id, $args = 'names' ) {
		return self::get_covoiturage_terms( self::taxonomy_diffusion, $post_id, $args);
	}
	
	/**
	 * Retourne les éléments d'une taxonomy d'un covoiturage
	 */
	public static function get_covoiturage_terms( $tax_name, $post_id, $args = 'names' ) {
		if(is_object($post_id))
			$post_id = $post_id->ID;
		if( ! is_array($args)){
			if(is_string($args))
				$args = array( 'fields' => $args );
			else
				$args = array();
		}
		if(!$post_id){
			throw new ArgumentException('get_covoiturage_terms : $post_id ne peut être null;');
		}
		return wp_get_post_terms($post_id, $tax_name, $args);
	}
	
	/**
	 * get_post_permalink
	 * Si le premier argument === true, $leave_name = true
	 * Si un argument === AGDP_COVOIT_SECRETCODE, ajoute AGDP_COVOIT_SECRETCODE=codesecret si on le connait
	 * 
	 */
	public static function get_post_permalink( $post, ...$url_args){
		if(is_numeric($post))
			$post = get_post($post);
		$post_status = $post->post_status;
		$leave_name = (count($url_args) && $url_args[0] === true);
		if( ! $leave_name
		&& $post->post_status == 'publish' ){
			$url = get_post_permalink( $post->ID);
			
		}
		else {
			if(count($url_args) && $url_args[0] === true)
				$url_args = array_slice($url_args, 1);
			$post_link = add_query_arg(
				array(
					'post_type' => $post->post_type,
					'p'         => $post->ID
				), ''
			);
			$url = home_url( $post_link );
		}
		foreach($url_args as $args){
			if($args){
				if(is_array($args))
					$args = add_query_arg($args);
				elseif($args == AGDP_COVOIT_SECRETCODE){			
					//Maintient la transmission du code secret
					$ekey = self::get_secretcode_in_request($post->ID);		
					if($ekey){
						$args = AGDP_COVOIT_SECRETCODE . '=' . $ekey;
					}
					else 
						continue;
				}
				if($args
				&& strpos($url, $args) === false)
					$url .= (strpos($url,'?')>0 || strpos($args,'?') ? '&' : '?') . $args;
			}
		}
		return $url;
	}


	
	/**
	* Définit si l'utilsateur courant peut modifier le covoiturage
	*/
	public static function user_can_change_covoiturage($post, $ignore = false, $verbose = false){
		if(!$post)
			return false;
		if(is_numeric($post))
			$post = get_post($post);
		
		if($post->post_status == 'trash'){
			return false;
		}
		$post_id = $post->ID;
		
		//Admin : ok 
		//TODO check is_admin === interface ou user
		//TODO user can edit only his own events
		if( is_admin() && !wp_doing_ajax()){
			die("is_admin");
			return true;
		}		
		
		//Session id de création du post identique à la session en cours
		
		if($ignore !== 'sessionid'){
			$meta_name = 'cov-sessionid' ;
			$sessionid = self::get_post_meta($post_id, $meta_name, true, false);

			if($sessionid
			&& $sessionid == AgendaPartage::get_session_id()){
				return true;
			}
			if($verbose){
				echo sprintf('<p>Session : %s != %s</p>', $sessionid, AgendaPartage::get_session_id());
			}
		}
		
		if(is_user_logged_in()){
			global $current_user;
			//Rôle autorisé
			if(	$current_user->has_cap( 'edit_posts' ) ){
				return true;
			}
			
			//Utilisateur associé
			if(	$current_user->ID == $post->post_author ){
				return true;
			}
			
			$user_email = $current_user->user_email;
			if( ! is_email($user_email)){
				$user_email = false;
			}
		}
		else {
			$user_email = false;
			if($verbose)
				echo sprintf('<p>Non connecté</p>');
		}
		
		$meta_name = 'cov-email' ;
		$email = get_post_meta($post_id, $meta_name, true);
		//Le mail de l'utilisateur est le même que celui du covoiturage
		if($email
		&& $user_email == $email){
			return true;
		}
		if($verbose){
			echo sprintf('<p>Email : %s != %s</p>', $email, $user_email);
		}

		//Requête avec clé de déblocage
		$ekey = self::get_secretcode_in_request($post_id);
		if($ekey){
			return true;
		}
		if($verbose){
			echo sprintf('<p>Code secret : %s != %s</p>', $ekey, $_REQUEST[AGDP_COVOIT_SECRETCODE]);
		}
		
		return false;
		
	}
	
	/**
	 * Interception du formulaire avant que les shortcodes ne soient analysés.
	 * Affectation des valeurs par défaut.
	 */
 	public static function on_wpcf7_form_class_attr_cb( $form_class ) { 
			
		$form = WPCF7_ContactForm::get_current();
		
		switch($form->id()){
			case AgendaPartage::get_option('contact_form_id') :
			case AgendaPartage::get_option('admin_message_contact_form_id') :
			case AgendaPartage::get_option('covoiturage_message_contact_form_id') :
				self::wpcf7_contact_form_init_tags( $form );
				$form_class .= ' preventdefault-reset';
				break;
			default:
				break;
		}
		return $form_class;
	}
	
 	private static function wpcf7_contact_form_init_tags( $form ) { 
		$html = $form->prop('form');//avec shortcodes du wpcf7
		$requested_id = isset($_REQUEST[AGDP_ARG_COVOITURAGEID]) ? $_REQUEST[AGDP_ARG_COVOITURAGEID] : false;
		$covoiturage = AgendaPartage_Covoiturage_Edit::get_covoiturage_post($requested_id);
		if( ! $covoiturage)
			return;
		
		/** init message **/
		$message = sprintf("Bonjour,\r\nJe vous écris à propos de \"%s\".\r\n%s\r\n\r\n-"
			, $covoiturage->post_title
			, get_post_permalink($covoiturage)
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
	
	public static function change_email_recipient($contact_form){
		$mail_data = $contact_form->prop('mail');
		
		$requested_id = isset($_REQUEST[AGDP_ARG_COVOITURAGEID]) ? $_REQUEST[AGDP_ARG_COVOITURAGEID] : false;
		$covoiturage = AgendaPartage_Covoiturage_Edit::get_covoiturage_post($requested_id);
		
		$meta_name = 'cov-email' ;
		$mail_data['recipient'] = self::get_post_meta($covoiturage, $meta_name, true);
		
		$contact_form->set_properties(array('mail'=>$mail_data));
	}
	
	/**
	* Retourne le numéro de téléphone ou le formulaire pour l'obtenir par email.
	**/
	public static function get_phone_html($post_id){
		$meta_name = 'cov-phone';
		$val = self::get_post_meta($post_id, $meta_name, true, false);
		if( /*! is_user_logged_in()
		&&*/ ! get_post_meta($post_id, 'cov-phone-show', true)){
			// $val = sprintf('<span class="covoiturage-tool">%s</span>'
				// , self::get_covoiturage_action_link($post_id, 'send_phone_number', true));
				//Formulaire de saisie du code secret
			$url = self::get_post_permalink( $post_id );
			$query = [
				'post_id' => $post_id,
				'action' => AGDP_TAG . '_' . AGDP_EMAIL4PHONE
			];
			$html = '<a id="email4phone-title">' 
					. AgendaPartage::icon('phone') . ' masqué > cliquez ici'
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
		return antispambot(esc_html($val), -0.5);
	}

	
	/**
	 * Send email with phone number from Ajax query
	 */
	public static function on_wp_ajax_covoiturage_email4phone_cb() {
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
	 * Envoi d'un email contenant les coordonnées associées au covoiturage
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
		
		$meta_name = 'cov-organisateur' ;
		$organisateur = self::get_post_meta($post, $meta_name, true);
		$meta_name = 'cov-email' ;
		$email = self::get_post_meta($post, $meta_name, true);
		$meta_name = 'cov-phone' ;
		$phone = self::get_post_meta($post, $meta_name, true);
		
		$site = get_bloginfo( 'name' );
		
		$subject = sprintf('[%s][Coordonnées] %s', $site, $post->post_title);
		
		$headers = array();
		$attachments = array();
		
		$message = sprintf('Bonjour,<br>Vous avez demandé à recevoir les coordonnées associées au covoiturage ci-dessous.');
		$message .= sprintf('<br>Initiateur : %s', $organisateur);
		$message .= sprintf('<br>Téléphone : %s', $phone);
		$message .= sprintf('<br>Email : %s', $email);
		
		$url = self::get_post_permalink($post, true);
		
		switch($post->post_status){
			case 'pending':
				$status = 'En attente de relecture';
			case 'draft':
				if(empty($status)) $status = 'Brouillon';
				$message .= sprintf('<br><br>Ce covoiturage n\'est <b>pas visible</b> en ligne, il est marqué comme "%s".', $status);
				break;
			case 'trash':
				$message .= sprintf('<br><br>Ce covoiturage a été SUPPRIMÉ.');
				break;
		}
		
		
		$url = self::get_post_permalink($post);
		$message .= sprintf('<br><br>La page de ce covoiturage est : <a href="%s">%s</a>', $url, $url);

		$message .= '<br><br>Bien cordialement,<br>L\'équipe de l\'Agenda partagé.';
		
		$message .= '<br>'.str_repeat('-', 20);
		$message .= sprintf('<br><br>Détails du covoiturage :<br><code>%s</code>', self::get_post_details_for_email($post, false));
		
		$message = quoted_printable_encode(str_replace('\n', '<br>', $message));

		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-type: text/html; charset=utf-8';
		$headers[] = 'Content-Transfer-Encoding: quoted-printable';

		if($success = wp_mail( $dest_email
			, '=?UTF-8?B?' . base64_encode($subject). '?='
			, $message
			, $headers, $attachments )){
			$html = sprintf('<div class="info email-send">L\'e-mail a été envoyé à l\'adresse "%s".</div>', $dest_email);
		}
		else{
			$html = sprintf('<div class="email-send alerte">L\'e-mail n\'a pas pu être envoyé à l\'adresse "%s".</div>', $dest_email);
		}
		
		debug_log($return_html_result, $success, $html
			, $dest_email
			, '=?UTF-8?B?' . base64_encode($subject). '?='
			, $message
			, $headers, $attachments );
		
		if($return_html_result){
			if($return_html_result === 'bool')
				return $success;
			else
				return $html;
		}
		echo $html;
	}
}
