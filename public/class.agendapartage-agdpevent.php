<?php

/**
 * AgendaPartage -> Evenement
 * Custom post type for WordPress.
 * 
 * Définition du Post Type agdpevent
 * Définition de la taxonomie type_agdpevent
 * Redirection des emails envoyés depuis une page Évènement
 * A l'affichage d'un évènement, le Content est remplacé par celui de l'évènement Modèle
 * En Admin, le bloc d'édition du Content est masqué d'après la définition du Post type : le paramètre 'supports' qui ne contient pas 'editor'
 *
 * Voir aussi AgendaPartage_Admin_Evenement
 */
class AgendaPartage_Evenement {

	const post_type = 'agdpevent';
	const taxonomy_type_agdpevent = 'type_agdpevent';
	const taxonomy_city = 'city';
	const taxonomy_publication = 'publication';

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
		add_filter( 'the_title', array(__CLASS__, 'the_agdpevent_title'), 10, 2 );
		add_filter( 'the_content', array(__CLASS__, 'the_agdpevent_content'), 10, 1 );
		add_filter( 'navigation_markup_template', array(__CLASS__, 'on_navigation_markup_template_cb'), 10, 2 );
		
		add_filter( 'pre_handle_404', array(__CLASS__, 'on_pre_handle_404_cb'), 10, 2 );
		add_filter( 'redirect_canonical', array(__CLASS__, 'on_redirect_canonical_cb'), 10, 2);
		
		// add_action( 'wp_ajax_'.AGDP_TAG.'_send_email', array(__CLASS__, 'on_wp_ajax_agdpevent_send_email_cb') );
		// add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_send_email', array(__CLASS__, 'on_wp_ajax_agdpevent_send_email_cb') );
		
		add_action( 'wp_ajax_'.AGDP_TAG.'_action', array(__CLASS__, 'on_wp_ajax_agdpevent_action_cb') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_action', array(__CLASS__, 'on_wp_ajax_agdpevent_action_cb') );
		
		add_filter( 'wpcf7_form_class_attr', array(__CLASS__, 'on_wpcf7_form_class_attr_cb'), 10, 1 ); 
	}
	
	/**
	 * Hook navigation template
	 * Supprime la navigation dans les posts d'évènements
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
	 * Il peut s'agir d'un évènement qui vient d'être créé et que seul son créateur peut voir.
	 */
	public static function on_pre_handle_404_cb($preempt, $query){
		if( ! have_posts()){
			//var_dump($query);
			//Dans le cas où l'agenda est la page d'accueil, l'url de base avec des arguments ne fonctionne pas
			if(is_home()){
				if( (! isset($query->query_vars['post_type'])
					|| $query->query_vars['post_type'] === '')
				&& isset($query->query[AGDP_ARG_EVENTID])){
					$page = AgendaPartage::get_option('agenda_page_id');
					$query->query_vars['post_type'] = 'page';
					$query->query_vars['page_id'] = $page;
					global $wp_query;
					$wp_query = new WP_Query($query->query_vars);
					return false;
						
				}
			}
			
			//Dans le cas d'une visualisation d'un évènement non publié, pour le créateur non connecté
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
							
							// self::on_agdpevent_404( $post ); // call exit() inside
						
						}
						return true;
					}
				}
			}
		}
	}
	
	/**
	 * Interception des redirections "post_type=agdpevent&p=1837" vers "/agdpevent/nom-de-l-evenement" si il a un post_status != 'publish'
	 */
	public static function on_redirect_canonical_cb ( $redirect_url, $requested_url ){
		$query = parse_url($requested_url, PHP_URL_QUERY);
		parse_str($query, $query);
		// var_dump($query, $redirect_url, $requested_url);
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
 	public static function the_agdpevent_title( $title, $post_id ) {
 		global $post;
 		if( ! $post
 		|| $post->ID != $post_id
 		|| $post->post_type != self::post_type){
 			return $title;
		}
	    return self::get_agdpevent_title( $post );
	}

	/**
	 * Hook
	 */
 	public static function the_agdpevent_content( $content ) {
 		global $post;
 		if( ! $post
 		|| $post->post_type != self::post_type){
 			return $content;
		}
			
		if(isset($_GET['action']) && $_GET['action'] == 'activation'){
			$post = self::do_post_activation($post);
		}
		
	    return self::get_agdpevent_content( $post );
	}
	
	/**
	 * Returns, par exemple, le meta ev-siteweb. Mais si $check_show_field, on teste si le meta ev-siteweb-show est vrai.
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
	public static function get_agdpevent_message_contact_post_id( ) {
		$option_id = 'agdpevent_message_contact_post_id';
		return AgendaPartage::get_option($option_id);
	}
 
 	/**
 	 * Retourne le titre de la page
 	 */
	public static function get_agdpevent_title( $agdpevent = null, $no_html = false) {
 		if( ! isset($agdpevent) || ! is_object($agdpevent)){
			global $post;
			$agdpevent = $post;
		}
		
		$post_title = isset( $agdpevent->post_title ) ? $agdpevent->post_title : '';
		$separator = $no_html ? ', ' : '<br>';
		$html = $post_title
			. $separator . self::get_event_dates_text( $agdpevent->ID )
			. $separator . get_post_meta($agdpevent->ID, 'ev-localisation', true);
		return $html;
	}

	/**
	 * Cherche le code secret dans la requête et le compare à celui du post
	 */
	public static function get_secretcode_in_request( $agdpevent ) {
		// Ajax : code secret
		if(array_key_exists(AGDP_SECRETCODE, $_REQUEST)){
			$meta_name = 'ev-'.AGDP_SECRETCODE;
			$codesecret = self::get_post_meta($agdpevent, $meta_name, true);		
			if($codesecret
			&& (strcasecmp( $codesecret, $_REQUEST[AGDP_SECRETCODE]) !== 0)){
				$codesecret = '';
			}
		}
		else 
			$codesecret = false;
		return $codesecret;
	}
	
 	/**
 	 * Retourne le Content de la page de l'évènement
 	 */
	public static function get_agdpevent_content( $agdpevent = null ) {
		global $post;
 		if( ! isset($agdpevent) || ! is_a($agdpevent, 'WP_Post')){
			$agdpevent = $post;
		}

		$codesecret = self::get_secretcode_in_request($agdpevent);
		
		$html = '[agdpevent-categories label="Catégories : "]
		[agdpevent-cities label="à "]
		[agdpevent-publications label="Publication (sous réserve) : "]
		[agdpevent-description]
<div>[agdpevent info="organisateur" label="Organisateur : "]</div>
[agdpevent info="siteweb"]';

		$meta_name = 'ev-email' ;
		$email = get_post_meta($agdpevent->ID, $meta_name, true);
		if(is_email($email)){
			$meta_name = 'ev-message-contact';
			$message_contact = get_post_meta($agdpevent->ID, $meta_name, true);
			if($message_contact){
				$html .= sprintf('[agdpevent-message-contact toggle="Envoyez un message à l\'organisateur" no-ajax post_id="%d" %s]'
						, $agdpevent->ID
						, $codesecret ? AGDP_SECRETCODE . '=' . $codesecret : ''
				);
			}
		}
		else
			$email = false;
		
		$html .= sprintf('[agdpevent-modifier-evenement toggle="Modifier cet évènement" no-ajax post_id="%d" %s]'
			, $agdpevent->ID
			, $codesecret ? AGDP_SECRETCODE . '=' . $codesecret : ''
		);
		
		if( $email && current_user_can('manage_options') ){
			$form_id = AgendaPartage::get_option('admin_message_contact_form_id');
			if(! $form_id){
				return '<p class="">Le formulaire de message à l\'organisateur d\'évènement n\'est pas défini dans le paramétrage de AgendaPartage.</p>';
			}
			$user = wp_get_current_user();
			$html .= sprintf('[toggle title="Message de l\'administrateur (%s) à l\'organisateur de l\'évènement" no-ajax] [contact-form-7 id="%s"] [/toggle]'
				, $user->display_name, $form_id);
		}
		switch($post->post_status){
			case 'pending':
				$status = 'En attente de relecture';
			case 'future':
				if(!$status) $status = 'Pour le futur';
			case 'draft':
				if(!$status) $status = 'Brouillon';
				
				if($email_sent = get_transient(AGDP_TAG . '_email_sent_' . $agdpevent->ID)){
					delete_transient(AGDP_TAG . '_email_sent_' . $agdpevent->ID);
				}
				
				$alerte = sprintf('<p class="alerte">Cet évènement est <b>en attente de validation</b>, il a le statut "%s".'
					.'<br>Il n\'est <b>pas visible</b> dans l\'agenda.'
					. '</p>'
					. (isset($email_sent) && $email_sent ? '<div class="info">Un e-mail a été envoyé pour permettre la validation de ce nouvel évènement. Vérifiez votre boîte mails, la rubrique spam aussi.</div>' : '')
					, $status);
				$html = $alerte . $html;
				break;
			case 'publish': 
				$page_id = AgendaPartage::get_option('agenda_page_id');
				if($page_id){
					$url = self::get_post_permalink($page_id, AGDP_SECRETCODE);
					$url = add_query_arg( AGDP_ARG_EVENTID, $agdpevent->ID, $url);
					$url .= '#' . AGDP_ARG_EVENTID . $agdpevent->ID;
					$html .= sprintf('<br><br>Pour voir cet évènement dans l\'agenda, <a href="%s">cliquez ici %s</a>.'
					, $url
					, AgendaPartage::html_icon('calendar-alt'));
				}
				break;
		}
		return $html;
	}
		
 
 	/**
	 * Dans le cas où WP considère le post comme inaccessible car en statut 'pending' ou 'draft'
	 * alors que le créateur peut le modifier.
 	 */
	/* public static function on_agdpevent_404( $agdpevent ) {
		global $post;
		$post = $agdpevent;
		//Nécessaire pour WPCF7 pour affecter une valeur à _wpcf7_container_post
		global $wp_query;
		$wp_query->in_the_loop = true;
		
		get_header(); ?>

<div class="wrap">
	<div id="primary" class="content-area">
		<main id="main" class="site-main">
			<?php
				get_template_part( 'template-parts/page/content', 'page' );
			?>
		</main><!-- #main -->
	</div><!-- #primary -->
</div><!-- .wrap -->

<?php
		get_footer();
		exit();
	} */
	
	/*******************
	 * Actions via Ajax
	 *******************/
	/**
	 * Retourne un lien html pour l'envoi d'un mail à l'organisateur
	 */
	public static function get_agdpevent_contact_email_link($post, $icon = false, $message = null, $title = false, $confirmation = null){
		$html = '';
		$meta_name = 'ev-email' ;
		$email = self::get_post_meta($post, $meta_name, true);
		if(!$email){
			$html .= '<p class="alerte">Cet évènement n\'a pas d\'adresse e-mail associée.</p>';
		}
		else {
			if(current_user_can('manage_options'))
				$data = [ 'force-new-activation' => true ];
			else
				$data = null;
			$html = self::get_agdpevent_action_link($post, 'send_email', $icon, $message, $title, $confirmation, $data);
		}
		return $html;
	}
	
	/**
	 * Retourne un lien html pour une action générique
	 */
	public static function get_agdpevent_action_link($post, $action, $icon = false, $caption = null, $title = false, $confirmation = null, $data = null){
		$need_can_user_change = true;
		switch($action){
			case 'remove':
				if($caption === null)
					$caption = __('Supprimer', AGDP_TAG);
				if($confirmation === null || $confirmation === true)
					$confirmation = 'Confirmez-vous la suppression définitive de l\'évènement ?';
				break;
			case 'duplicate':
				if($caption === null)
					$caption = __('Dupliquer', AGDP_TAG);
				if($confirmation === true)
					$confirmation = 'Confirmez-vous la duplication de l\'évènement ?';
				if($icon === true)
					$icon = 'admin-page';
				break;
			case 'unpublish':
				if($caption === null)
					$caption = __('Masquer dans l\'agenda', AGDP_TAG);
				if($icon === true)
					$icon = 'hidden';
				if($confirmation === null || $confirmation === true)
					$confirmation = 'Confirmez-vous que l\'évènement ne sera plus visible ?';

				break;
			case 'publish':
				if($caption === null)
					$caption = __('Rendre public dans l\'agenda', AGDP_TAG);
				if($icon === true)
					$icon = 'visibility';
				if($confirmation === null || $confirmation === true)
					$confirmation = 'Confirmez-vous de rendre visible l\'évènement ?';
				
				break;
			case 'send_email':
				$need_can_user_change = false;
				$meta_name = 'ev-email' ;
				$email = self::get_post_meta($post, $meta_name, true);
				$email_parts = explode('@', $email);
				$email_trunc = substr($email, 0, 3) . str_repeat('*', strlen($email_parts[0])-3);
				if($caption === null){
					$caption = 'E-mail de validation';
					$title = sprintf('Cliquez ici pour envoyer un e-mail de validation de l\'évènement à l\'adresse %s@%s', $email_trunc, $email_parts[1]);
				}
				if($icon === true)
					$icon = 'email-alt';
				if($confirmation === null || $confirmation === true)
					$confirmation = sprintf('Confirmez-vous l\'envoi d\'un e-mail à l\'adresse %s@%s', $email_trunc, $email_parts[1]);
				break;
			default:
				if(!$caption)
					$caption = __($action, AGDP_TAG);
				
				break;
		}
		if(!$title)
			$title = $caption;
		
		if($icon === true)
			$icon = $action;
		$html = '';
		if($need_can_user_change && ! self::user_can_change_agdpevent($post)){
			$html .= '<p class="alerte">Cet évènement ne peut pas être modifié par vos soins.</p>';
		}
		else {
			//Envoyer le mail contenant le code secret à l'organisateur
			$url = self::get_post_permalink($post, AGDP_SECRETCODE);
			
			$post_id = is_object($post) ? $post->ID : $post;
			$query = [
				'post_id' => $post_id,
				'action' => AGDP_TAG . '_action',
				'method' => $action
			];
			if($data)
				$query['data'] = $data;
				
			//Maintient la transmission du code secret
			$ekey = self::get_secretcode_in_request($post_id);
			if($ekey)
				$query[AGDP_SECRETCODE] = $ekey;

			if($confirmation){
				$query['confirm'] = $confirmation;
			}
			if($icon)
				$icon = AgendaPartage::html_icon($icon);
			$html .= sprintf('<span><a href="#" title="%s" class="agdp-ajax-action agdp-ajax-%s" data="%s">%s%s</a></span>'
				, $title ? $title : ''
				, $action
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
	public static function on_wp_ajax_agdpevent_action_cb() {
		$ajax_response = '0';
		if(!array_key_exists("method", $_POST)){
			wp_die();
		}
		$action = $_POST['method'];
		if(array_key_exists("post_id", $_POST)){
			try{
				//cherche une fonction du nom "agdpevent_action_{method}"
				$function = array(__CLASS__, sprintf('agdpevent_action_%s', $action));
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
	public static function agdpevent_action_remove($post_id) {
		if ( self::do_remove($post_id) )
			return 'redir:' . AgendaPartage_Evenements::get_url(); //TODO add month in url
		return 'Impossible de supprimer cet évènement.';
	}
	
	/**
	 * Duplicate event
	 */
	public static function agdpevent_action_duplicate($post_id) {
		if ( self::user_can_change_agdpevent($post_id) )
			return 'redir:' . add_query_arg(
				'action', 'duplicate'
				, add_query_arg(AGDP_ARG_EVENTID, $post_id
					, get_page_link(AgendaPartage::get_option('new_agdpevent_page_id'))
				)
			);
		return 'Impossible de retrouver cet évènement.';
	}
	
	/**
	 * Unpublish event
	 */
	public static function agdpevent_action_unpublish($post_id) {
		$post_status = 'pending';
		if( self::change_post_status($post_id, $post_status) )
			return 'redir:' . self::get_post_permalink($post_id, true, AGDP_SECRETCODE, 'etat=en-attente');
		return 'Impossible de modifier cet évènement.';
	}
	/**
	 * Publish event
	 */
	public static function agdpevent_action_publish($post_id) {
		$post_status = 'publish';
		if( (! self::waiting_for_activation($post_id)
			|| current_user_can('manage_options') )
		&& self::change_post_status($post_id, $post_status) )
			return 'redir:' . self::get_post_permalink($post_id, AGDP_SECRETCODE);
		return 'Impossible de modifier le statut.<br>Ceci peut être effectué depuis l\'e-mail de validation.';
	}
	/**
	 * Send contact email
	 */
	public static function agdpevent_action_send_email($post_id) {
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
		if(self::user_can_change_agdpevent($post_id)){
			$post = wp_delete_post($post_id);
			return is_a($post, 'WP_Post');
		}
		// echo self::user_can_change_agdpevent($post_id, false, true);
		return false;
	}
	
	/**
	 * Change post status
	 */
	public static function change_post_status($post_id, $post_status) {
		if($post_status == 'publish')
			$ignore = 'sessionid';
		if(self::user_can_change_agdpevent($post_id, $ignore)){
			$postarr = ['ID' => $post_id, 'post_status' => $post_status];
			$post = wp_update_post($postarr, true);
			return ! is_a($post, 'WP_Error');
		}
		// echo self::user_can_change_agdpevent($post_id, $ignore, true);
		return false;
	}
	
	/**
	 * Envoye le mail à l'organisateur de l'évènement
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
		
		$codesecret = self::get_post_meta($post, 'ev-' . AGDP_SECRETCODE, true);
		
		$meta_name = 'ev-email' ;
		$email = self::get_post_meta($post, $meta_name, true);
		$to = $email;
		
		$site = get_bloginfo( 'name' );
		
		$subject = sprintf('[%s] %s', $site, $subject ? $subject : $post->post_title);
		
		$headers = array();
		$attachments = array();
		
		if( ! $message){
			$message = sprintf('Bonjour,<br>Vous recevez ce message suite la création de l\'évènement ci-dessous ou à une demande depuis le site et parce que votre e-mail est associé à l\'évènement.');

		}
		else
			$message .= '<br>'.str_repeat('-', 20);
		
		$url = self::get_post_permalink($post, true);
		
		switch($post->post_status){
			case 'pending':
				$status = 'En attente de relecture';
			case 'draft':
				if(!$status) $status = 'Brouillon';
				$message .= sprintf('<br><br>Cet évènement n\'est <b>pas visible</b> en ligne, il est marqué comme "%s".', $status);
				
				if( self::waiting_for_activation($post) ){
					$activation_url = add_query_arg(AGDP_SECRETCODE, $codesecret, $url);
					$activation_url = add_query_arg('action', 'activation', $activation_url);
					$activation_url = add_query_arg('ak', self::get_activation_key($post), $activation_url);
					$activation_url = add_query_arg('etat', 'en-attente', $activation_url);
				}
				
				$message .= sprintf('<br><br><a href="%s"><b>Cliquez ici pour rendre cet évènement public dans l\'agenda</b></a>.<br>', $activation_url);
				break;
			case 'trash':
				$message .= sprintf('<br><br>Cet évènement a été SUPPRIMÉ.');
				break;
		}
		
		$message .= sprintf('<br><br>Le code secret de cet évènement est : %s', $codesecret);
		// $args = AGDP_SECRETCODE .'='. $codesecret;
		// $codesecret_url = $url . (strpos($url,'?')>0 || strpos($args,'?') ? '&' : '?') . $args;			
		$codesecret_url = add_query_arg(AGDP_SECRETCODE, $codesecret, $url);
		$message .= sprintf('<br><br>Pour modifier cet évènement, <a href="%s">cliquez ici</a>', $codesecret_url);
		
		$url = self::get_post_permalink($post);
		$message .= sprintf('<br><br>La page publique de cet évènement est : <a href="%s">%s</a>', $url, $url);

		$message .= '<br><br>Bien cordialement,<br>L\'équipe de l\'Agenda partagé.';
		
		$message .= '<br>'.str_repeat('-', 20);
		$message .= sprintf('<br><br>Détails de l\'évènement :<br><code>%s</code>', self::get_post_details_for_email($post));
		
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
			if($return_html_result == 'bool')
				return $success;
			else
				return $html;
		}
		echo $html;
	}
	
	/**
	 * Détails de l'évènement pour insertion dans un email
	 */
	public static function get_post_details_for_email($post){
		if(is_numeric($post)){
			$post = get_post($post);
		}
		$post_id = $post->ID;
		$html = '<table><tbody>';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Titre', htmlentities($post->post_title));
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Dates', self::get_event_dates_text($post_id));
		$meta_name = 'ev-localisation';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Lieu', htmlentities(get_post_meta($post_id, $meta_name, true)));
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Communes', htmlentities(implode(', ', self::get_event_cities ($post_id, 'names'))));
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Catégories', htmlentities(implode(', ', self::get_event_categories ($post_id, 'names'))));
		$html .= sprintf('<tr><td>%s : </td><td><pre>%s</pre></td></tr>', 'Description', htmlentities($post->post_content));
		$meta_name = 'ev-organisateur';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Organisateur', htmlentities(get_post_meta($post_id, $meta_name, true)));
		$meta_name = 'ev-siteweb';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Site web', htmlentities(get_post_meta($post_id, $meta_name, true)));
		$meta_name = 'ev-email';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Email', get_post_meta($post_id, $meta_name, true));
		$meta_name = 'ev-email-show';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Afficher l\'e-mail', get_post_meta($post_id, $meta_name, true) ? 'oui' : 'non');
		$meta_name = 'ev-message-contact';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Recevoir des messages', get_post_meta($post_id, $meta_name, true) ? 'oui' : 'non');
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Publication (sous réserve)', htmlentities(implode(', ', self::get_event_publications ($post_id, 'names'))));
		
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
			$post = get_post($post);
		}
		if(isset($_GET['ak']) 
		&& (! self::waiting_for_activation($post_id)
			|| self::check_activation_key($post, $_GET['ak']))){
			if($post->post_status != 'publish'){
				$result = wp_update_post(array('ID' => $post->ID, 'post_status' => 'publish'));
				$post->post_status = 'publish';
				if(is_wp_error($result)){
					var_dump($result);
				}
				echo '<p class="info">L\'évènement est désormais activé et visible dans l\'agenda</p>';
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
	 * Retourne le texte des dates et heures d'un évènement
	 */
	public static function get_event_dates_text( $post_id ) {
		if(is_object($post_id))
			$post_id = $post_id->ID;
		$date_debut    = get_post_meta( $post_id, 'ev-date-debut', true );
		$date_jour_entier    = get_post_meta( $post_id, 'ev-date-journee-entiere', true );
		$heure_debut    = get_post_meta( $post_id, 'ev-heure-debut', true );
		$date_fin    = get_post_meta( $post_id, 'ev-date-fin', true );
		$heure_fin    = get_post_meta( $post_id, 'ev-heure-fin', true );
		return mb_strtolower( trim(
			  ($date_fin && $date_fin != $date_debut ? 'du ' : '')
			. ($date_debut ? mysql2date( 'D. j M Y', $date_debut ) : '')
			. (/* !$date_jour_entier && */ $heure_debut 
				? ($heure_fin ? ' de ' : ' à ') . $heure_debut : '')
			. ($date_fin && $date_fin != $date_debut ? ' au ' . mysql2date( 'D. j M Y', $date_fin ) : '')
			. (/* !$date_jour_entier && */ $heure_fin 
				? ($heure_debut ? ' à ' : ' jusqu\'à ')  . $heure_fin
				: '')
		));
	}
	
	/**
	 * Retourne les catégories d'un évènement
	 */
	public static function get_event_categories( $post_id, $args = 'names' ) {
		return self::get_event_terms( self::taxonomy_type_agdpevent, $post_id, $args);
	}
	/**
	 * Retourne les communes d'un évènement
	 */
	public static function get_event_cities( $post_id, $args = 'names' ) {
		return self::get_event_terms( self::taxonomy_city, $post_id, $args);
	}
	/**
	 * Retourne les publications possibles d'un évènement
	 */
	public static function get_event_publications( $post_id, $args = 'names' ) {
		return self::get_event_terms( self::taxonomy_publication, $post_id, $args);
	}
	
	/**
	 * Retourne les éléments d'une taxonomy d'un évènement
	 */
	public static function get_event_terms( $tax_name, $post_id, $args = 'names' ) {
		if(is_object($post_id))
			$post_id = $post_id->ID;
		if( ! is_array($args)){
			if(is_string($args))
				$args = array( 'fields' => $args );
			else
				$args = array();
		}
		if(!$post_id){
			throw new ArgumentException('get_event_terms : $post_id ne peut être null;');
		}
		return wp_get_post_terms($post_id, $tax_name, $args);
	}
	
	/**
	 * get_post_permalink
	 * Si le premier argument === true, $leave_name = true
	 * Si un argument === AGDP_SECRETCODE, ajoute AGDP_SECRETCODE=codesecret si on le connait
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
			if($url_args[0] === true)
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
				elseif($args == AGDP_SECRETCODE){			
					//Maintient la transmission du code secret
					$ekey = self::get_secretcode_in_request($post->ID);		
					if($ekey){
						$args = AGDP_SECRETCODE . '=' . $ekey;
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
	* Définit si l'utilsateur courant peut modifier l'évènement
	*/
	public static function user_can_change_agdpevent($post, $ignore = false, $verbose = false){
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
			$meta_name = 'ev-sessionid' ;
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
		
		$meta_name = 'ev-email' ;
		$email = get_post_meta($post_id, $meta_name, true);
		//Le mail de l'utilisateur est le même que celui de l'évènement
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
			echo sprintf('<p>Code secret : %s != %s</p>', $ekey, $_REQUEST[AGDP_SECRETCODE]);
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
			case AgendaPartage::get_option('agdpevent_message_contact_post_id') :
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
		$requested_id = isset($_REQUEST[AGDP_ARG_EVENTID]) ? $_REQUEST[AGDP_ARG_EVENTID] : false;
		$agdpevent = AgendaPartage_Evenement_Edit::get_agdpevent_post($requested_id);
		if( ! $agdpevent)
			return;
		
		$message = sprintf("Bonjour,\r\nJe vous écris à propos de \"%s\" (%s) du %s.\r\n%s\r\n\r\n-"
			, $agdpevent->post_title
			, self::get_post_meta($agdpevent, 'ev-localisation', true)
			, self::get_event_dates_text($agdpevent)
			, get_post_permalink($agdpevent)
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
		
		$form->set_properties(array('form'=>$html));
		
	}
}
