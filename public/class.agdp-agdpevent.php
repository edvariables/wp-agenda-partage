<?php

/**
 * AgendaPartage -> Event
 * Custom post type for WordPress.
 * 
 * Définition du Post Type agdpevent
 * Définition de la taxonomie ev_category
 * Redirection des emails envoyés depuis une page Évènement
 * A l'affichage d'un évènement, le Content est remplacé par celui de l'évènement Modèle
 * En Admin, le bloc d'édition du Content est masqué d'après la définition du Post type : le paramètre 'supports' qui ne contient pas 'editor'
 *
 * Voir aussi Agdp_Admin_Event
 */
class Agdp_Event extends Agdp_Post {

	const post_type = 'agdpevent';
	const taxonomy_ev_category = 'ev_category';
	const taxonomy_city = 'ev_city';
	const taxonomy_diffusion = 'ev_diffusion';
	const shortcode = self::post_type;
	
	const icon = 'calendar-alt';

	const secretcode_argument = AGDP_EVENT_SECRETCODE;
	const field_prefix = 'ev-';

	const postid_argument = AGDP_ARG_EVENTID;
	const posts_page_option = 'agenda_page_id';
	const newsletter_option = 'events_nl_post_id';
	
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
		
		add_action( 'wp_ajax_agdpevent_action', array(__CLASS__, 'on_wp_ajax_agdpevent_action_cb') );
		add_action( 'wp_ajax_nopriv_agdpevent_action', array(__CLASS__, 'on_wp_ajax_agdpevent_action_cb') );
		
		add_filter(self::post_type . '_send_for_diffusion_mailto', array(__CLASS__, 'on_send_for_diffusion_mailto'), 10, 1 );
	}
 
 	/**
 	 * Retourne le titre de la page
 	 */
	public static function get_post_title( $agdpevent = null, $no_html = false) {
 		if( is_numeric($agdpevent))
			$agdpevent = get_post($agdpevent);
		if( ! isset($agdpevent) || ! is_object($agdpevent)){
			global $post;
			$agdpevent = $post;
		}
		if( ! is_object($agdpevent) )
			return '<span title="'.__CLASS__.'::'.__FUNCTION__.'()">?</span>';
		
		$post_title = isset( $agdpevent->post_title ) ? $agdpevent->post_title : '';
		$separator = $no_html ? ', ' : '<br>';
		$html = $post_title
			. $separator . self::get_event_dates_text( $agdpevent->ID )
			. $separator . get_post_meta($agdpevent->ID, 'ev-localisation', true);
		return $html;
	}
	
 	/**
 	 * Retourne le Content de la page de l'évènement
 	 */
	public static function get_post_content( $agdpevent = null ) {
		global $post;
 		if( ! isset($agdpevent) || ! is_a($agdpevent, 'WP_Post')){
			$agdpevent = $post;
		}

		$codesecret = self::get_secretcode_in_request($agdpevent);
		
		$html = '[agdpevent-categories label="Catégories : "]
		[agdpevent-cities label="à "]
		[agdpevent-description]
		[agdpevent info="organisateur" label="Organisateur : "][agdpevent-cree-depuis][/agdpevent]
		[agdpevent info="phone" label="Téléphone : "]
		[agdpevent info="siteweb"]
		[agdpevent info="attachments"]
		';
		if( Agdp_Event_Post_type::is_diffusion_managed() )
			$html .='[agdpevent-diffusions label="Diffusion (sous réserve) : "]';
		
		
		$field_id = 'add_content_in_' . Agdp_Event::post_type;
		$add_content = Agdp::get_option($field_id);
		if( $add_content ){
			$html .= $add_content;
		}

		$html .= sprintf('[agdpevent-covoiturage no-ajax post_id="%d" %s]'
			, $agdpevent->ID
			, $codesecret ? self::secretcode_argument . '=' . $codesecret : ''
		);
		
		$html .= self::get_post_imported( $agdpevent );
		
		$meta_name = 'ev-email' ;
		$email = get_post_meta($agdpevent->ID, $meta_name, true);
		if(is_email($email)){
			$meta_name = 'ev-message-contact';
			$message_contact = get_post_meta($agdpevent->ID, $meta_name, true);
			if($message_contact){
				$html .= sprintf('[agdpevent-message-contact toggle="Envoyez un message à l\'organisateur" no-ajax post_id="%d" %s]'
						, $agdpevent->ID
						, $codesecret ? self::secretcode_argument . '=' . $codesecret : ''
				);
			}
		}
		else
			$email = false;
		
		$html .= sprintf('[agdpevent-modifier-evenement toggle="Modifier cet évènement" no-ajax post_id="%d" %s]'
			, $agdpevent->ID
			, $codesecret ? self::secretcode_argument . '=' . $codesecret : ''
		);
		
		if( $email && current_user_can('manage_options') ){
			$form_id = Agdp::get_option('admin_message_contact_form_id');
			if(! $form_id){
				return '<p class="">Le formulaire de message à l\'organisateur d\'évènement n\'est pas défini dans le paramétrage de AgendaPartage.</p>';
			}
			$user = wp_get_current_user();
			$html .= sprintf('[toggle title="Message de l\'administrateur (%s) à l\'organisateur de l\'évènement" no-ajax] [contact-form-7 id="%s"] [/toggle]'
				, $user->display_name, $form_id);
		}
				
		if($email_sent = get_transient(AGDP_TAG . '_email_sent_' . $agdpevent->ID)){
			delete_transient(AGDP_TAG . '_email_sent_' . $agdpevent->ID);
		}
		elseif($no_email = get_transient(AGDP_TAG . '_no_email_' . $agdpevent->ID)){
			delete_transient(AGDP_TAG . '_no_email_' . $agdpevent->ID);
			if(empty($codesecret))
				$secretcode = get_post_meta($post->ID, self::field_prefix.self::secretcode_argument, true);
		}
		
		$status = false;
		switch($post->post_status){
			case 'pending':
				$status = 'En attente de relecture';
			case 'future':
				if(empty($status)) $status = 'Pour le futur';
			case 'draft':
				if(empty($status)) $status = 'Brouillon';
				$alerte = sprintf('<p class="alerte">Cet évènement est <b>en attente de validation</b>, il a le statut "%s".'
					.'<br>Il n\'est <b>pas visible</b> dans l\'agenda.'
					. '</p>'
					. (isset($email_sent) && $email_sent ? '<div class="info">Un e-mail a été envoyé pour permettre la validation de ce nouvel évènement. Vérifiez votre boîte mails, la rubrique spam aussi.</div>' : '')
					. (isset($no_email) && $no_email ? '<div class="alerte">Vous n\'avez pas indiqué d\'adresse mail pour permettre la validation de ce nouvel évènement.'
											. '<br>Vous devrez attendre la validation par un modérateur pour que cet évènement soit public.'
											. '<br>Vous pouvez encore modifier cet évènement et renseigner l\'adresse mail.'
											. '<br>Le code secret de cet évènement est : <b>'.$secretcode.'</b>'
											. '</div>' : '')
					, $status);
				$html = $alerte . $html;
				break;
				
			case 'publish': 
				if(isset($email_sent) && $email_sent){
					$info = '<div class="info">Cet évènement est désormais public.'
							. '<br>Un e-mail a été envoyé pour mémoire. Vérifiez votre boîte mails, la rubrique spam aussi.'
						.'</div>';
					$html = $info . $html;
				}
				elseif( isset($no_email) && $no_email) {
					$info = '<div class="alerte">Cet évènement est désormais public.</div>';
					$html = $info . $html;
				}
				break;
		}
		
		switch($post->post_status){
			case 'pending':
				if( ! current_user_can('moderate_comments') )
					break;
				
			case 'publish': 
				$page_id = Agdp::get_option('agenda_page_id');
				if($page_id){
					$url = self::get_post_permalink($page_id, self::secretcode_argument);
					$url = add_query_arg( self::postid_argument, $agdpevent->ID, $url);
					$url .= '#' . self::postid_argument . $agdpevent->ID;
					$html .= sprintf('<br><br>Pour voir cet évènement dans l\'agenda, <a href="%s">cliquez ici %s</a>.'
					, $url
					, Agdp::icon('calendar-alt'));
				}
				break;
		}
		
			
		if(is_user_logged_in()){
			global $current_user;
			//Rôle autorisé
			if(	$current_user->has_cap( 'edit_posts' ) ){
			
				$creator = new WP_User($agdpevent->post_author);
				if(($user_name = $creator->get('display_name'))
				|| ($user_name = $creator->get('user_login')))
					$html .= '<p>modifié par "' . $user_name . '"</p>';
			}
		}
		return $html;
	}
	
 	/**
 	 * Retourne le Content de la page de l'évènement
 	 */
	public static function get_agdpevent_covoiturage( $agdpevent = null ) {
		if( ! Agdp_Covoiturage::is_managed() )
			return '';
		global $post;
 		if( ! isset($agdpevent) || ! is_a($agdpevent, 'WP_Post')){
			$agdpevent = $post;
		}
		
		$covoiturages = get_posts([
			'post_type' => Agdp_Covoiturage::post_type,
			'post_status' => 'publish',
			'numberposts' => -1,
			'meta_key' => 'related_'.self::post_type,
			'meta_value' => $agdpevent->ID,
			'meta_compare' => '=',
		]);
		$html = sprintf('<ul class="agdp-covoiturages-list">');
		//Ajouter
		if ( ! Agdp_Newsletter::is_sending_email() )
			$new_link = sprintf('<a href="%s">Cliquez ici pour créer un %s covoiturage associé</a>'
				,  add_query_arg( 
					AGDP_ARG_EVENTID, $agdpevent->ID
					, get_permalink(Agdp::get_option('new_covoiturage_page_id')))
				, count($covoiturages) ? 'autre' : 'nouveau'
			);
		else
			$new_link = false;
		if( count($covoiturages) ){
			$html .= sprintf('<label>%s %s covoiturage%s associé%s</label>', Agdp::icon('car'), count($covoiturages), count($covoiturages) > 1 ? 's' : '', count($covoiturages) > 1 ? 's' : '');
			foreach($covoiturages as $covoiturage){
				$html .= sprintf('<li><a href="%s">%s %s</a></li>'
					, get_post_permalink($covoiturage)
					, Agdp::icon('controls-play')
					, Agdp_Covoiturage::get_post_title( $covoiturage, true )
				);
			}
			if( $new_link )
				$html .= sprintf('<li>%s%s</li>'
					, Agdp::icon('welcome-add-page')
					, $new_link);
		}
		elseif( $new_link )
			$html .= sprintf('<label>%s %s</label>'
				, Agdp::icon('car')
				, $new_link
			);
			
		
		$html .= '</ul>';
		
		return $html;
	}
	
 	/**
 	 * Retourne le Content de la page de l'évènement
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
			$val = sprintf('Cet évènement provient de <a href="%s://%s/blog/%s?p=%d">%s</a>', 
				'https', $source_site, $source_post_type, $source_id, $source_site);
			if($no_html)
				return $val;
			
			$meta_name = AGDP_IMPORT_REFUSED;
			if( $import_refused = get_post_meta( $post_id, $meta_name, true ) ){			
				$val .= ' ' . Agdp::icon('warning', 'Refusé', 'color-red');
			}
			return sprintf('<div class="agdp-agdpevent agdp-%s">%s %s%s</div>'
					, $meta_name
					, Agdp::icon( $add_alert ? 'warning' : 'admin-multisite')
					, $val
					, $add_alert && $add_alert !== true ? $add_alert : ''
			);
		}
		return '';
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
	public static function get_agdpevent_action_link($post, $method, $icon = false, $caption = null, $title = false, $confirmation = null, $data = null){
		$need_can_user_change = true;
		switch($method)
		{
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
					$confirmation = 'Confirmez-vous de rendre public l\'évènement ?';
				
				break;
				
			case 'send_email':
				$need_can_user_change = false;
				$meta_name = 'ev-user-email' ;
				if( ! ($email = self::get_post_meta($post, $meta_name, true)) )
					if( ! ($email = self::get_post_meta($post, 'ev-email', true)) )
						break;
				$email_parts = explode('@', $email);
				if( count($email_parts) < 2 )
					throw new Exception('$email incorrect : ' . print_r($email, true));
				$email_trunc = substr($email, 0, min(strlen($email_parts[0]), 3)) . str_repeat('*', max(0, strlen($email_parts[0])-3));
				if($caption === null){
					$caption = 'E-mail de validation';
					$title = sprintf('Cliquez ici pour envoyer un e-mail de validation de l\'évènement à l\'adresse %s@%s', $email_trunc, $email_parts[1]);
				}
				if($icon === true)
					$icon = 'email-alt';
				if($confirmation === null || $confirmation === true)
					$confirmation = sprintf('Confirmez-vous l\'envoi d\'un e-mail à l\'adresse %s@%s', $email_trunc, $email_parts[1]);
				break;
				
			case 'refuse_import':
				if($caption === null)
					if( is_array($data) && ! empty($data['cancel']) )
						$caption = __('Annuler le refus', AGDP_TAG);	
					else
						$caption = __('Refuser l\'importation', AGDP_TAG);
				if($icon === true)
					if( is_array($data) && ! empty($data['cancel']) )
						$icon = 'undo';
					else
						$icon = 'lock';
				if($confirmation === null || $confirmation === true)
					if( is_array($data) && ! empty($data['cancel']) )
						$confirmation = 'Confirmez-vous l\'annulation du refus d\'importer l\'évènement ?';
					else
						$confirmation = 'Confirmez-vous le refus d\'importer l\'évènement ?';
				
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
			$html .= '<p class="alerte">Cet évènement ne peut pas être modifié par vos soins.</p>';
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
	public static function on_wp_ajax_agdpevent_action_cb() {
		
		// debug_log('on_wp_ajax_agdpevent_action_cb', func_get_args());	
		
		if( ! Agdp::check_nonce())
			wp_die();
		
		$ajax_response = '0';
		if(!array_key_exists("method", $_POST)){
			wp_die();
		}
		$method = $_POST['method'];
		if(array_key_exists("post_id", $_POST)){
			try{
				//cherche une fonction du nom "agdpevent_action_{method}"
				$function = array(__CLASS__, sprintf('agdpevent_action_%s', $method));
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
	 * Refuse imported post
	 */
	public static function agdpevent_action_refuse_import($post_id) {
		$cancel = isset($_POST['data']) && ! empty($_POST['data']['cancel']);
		if ( self::do_refuse_import($post_id, ! $cancel) )
			return 'redir:' . self::get_post_permalink($post_id, true); 
		return 'Impossible de modifier cet évènement.';
	}
	
	/**
	 * Remove event
	 */
	public static function agdpevent_action_remove($post_id) {
		if ( self::do_remove($post_id) )
			return 'redir:' . Agdp_Events::get_url(); //TODO add month in url
		return 'Impossible de supprimer cet évènement.';
	}
	
	/**
	 * Duplicate event
	 */
	public static function agdpevent_action_duplicate($post_id) {
		if ( self::user_can_change_post($post_id) )
			return 'redir:' . add_query_arg(
				'action', 'duplicate'
				, add_query_arg(self::postid_argument, $post_id
					, get_page_link(Agdp::get_option('new_agdpevent_page_id'))
				)
			);
		return 'Impossible de retrouver cet évènement.';
	}
	
	/**
	 * Unpublish event
	 */
	public static function agdpevent_action_unpublish($post_id) {
		$post_status = 'pending';
		if( self::change_post_status($post_id, $post_status) ){
			self::send_for_diffusion( $post_id );
			return 'redir:' . self::get_post_permalink($post_id, true, self::secretcode_argument, 'etat=en-attente');
		}
		return 'Impossible de modifier cet évènement.';
	}
	/**
	 * Publish event
	 */
	public static function agdpevent_action_publish($post_id) {
		$meta_name = AGDP_IMPORT_REFUSED;
		if( get_post_meta( $post_id, $meta_name, true ) )
			return 'Impossible de modifier le statut.<br>Cet évènement importé est marqué comme refusé.';
			
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
	public static function agdpevent_action_send_email($post_id) {
		if(isset($_POST['data']) && is_array($_POST['data'])
		&& isset($_POST['data']['force-new-activation']) && $_POST['data']['force-new-activation']){
			self::get_activation_key($post_id, true); //reset
		}
		return self::send_validation_email($post_id);
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
		
		$codesecret = self::get_post_meta($post, self::field_prefix . self::secretcode_argument, true);
		
		$meta_name = 'ev-user-email' ;
		$email = self::get_post_meta($post, $meta_name, true);
		$to = $email;
		
		$site = get_bloginfo( 'name' );
		
		$subject = sprintf('[%s][Validation] %s', $site, $subject ? $subject : $post->post_title);
		
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
				
				//TODO if( self::waiting_for_activation($post) ){
					$activation_url = add_query_arg(self::secretcode_argument, $codesecret, $url);
					$activation_url = add_query_arg('action', 'activation', $activation_url);
					$activation_url = add_query_arg('ak', self::get_activation_key($post), $activation_url);
					$activation_url = add_query_arg('etat', 'en-attente', $activation_url);
				// }
				
				$message .= sprintf('<br><br><a href="%s"><b>Cliquez ici pour rendre cet évènement public dans l\'agenda</b></a>.<br>', $activation_url);
				break;
			case 'trash':
				$message .= sprintf('<br><br>Cet évènement a été SUPPRIMÉ.');
				break;
		}
		
		$message .= sprintf('<br><br>Le code secret de cet évènement est : %s', $codesecret);
		// $args = self::secretcode_argument .'='. $codesecret;
		// $codesecret_url = $url . (strpos($url,'?')>0 || strpos($args,'?') ? '&' : '?') . $args;			
		$codesecret_url = add_query_arg(self::secretcode_argument, $codesecret, $url);
		$message .= sprintf('<br><br>Pour modifier cet évènement, <a href="%s">cliquez ici</a>', $codesecret_url);
		
		$url = self::get_post_permalink($post, true);
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
			if($return_html_result === 'bool')
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
		$meta_name = 'ev-phone';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Téléphone', htmlentities(get_post_meta($post_id, $meta_name, true)));
		$meta_name = 'ev-siteweb';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Site web', htmlentities(get_post_meta($post_id, $meta_name, true)));
		$meta_name = 'ev-email';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Email', get_post_meta($post_id, $meta_name, true));
		$meta_name = 'ev-email-show';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Afficher l\'e-mail', get_post_meta($post_id, $meta_name, true) ? 'oui' : 'non');
		$meta_name = 'ev-message-contact';
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Recevoir des messages', get_post_meta($post_id, $meta_name, true) ? 'oui' : 'non');
		$html .= sprintf('<tr><td>%s : </td><td>%s</td></tr>', 'Diffusion (sous réserve)', htmlentities(implode(', ', self::get_event_diffusions ($post_id, 'names'))));
		
		$html .= '</tbody></table>';
		return $html;
		
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
		if(mysql2date( 'j', $date_debut ) === '1')
			$format_date_debut = 'l j\e\r M Y';
		else
			$format_date_debut = 'l j M Y';
		if($date_fin && mysql2date( 'j', $date_fin ) === '1')
			$format_date_fin = 'l j\e\r M Y';
		else
			$format_date_fin = 'l j M Y';
		if( $heure_debut )
			$heure_debut = str_replace(':', 'h', str_replace(':00', 'h', $heure_debut));
		if( $heure_fin )
			$heure_fin = str_replace(':', 'h', str_replace(':00', 'h', $heure_fin));
		return mb_strtolower( trim(
			  ($date_fin && $date_fin != $date_debut ? 'du ' : '')
			. ($date_debut ? str_ireplace(' mar ', ' mars ', mysql2date( $format_date_debut, $date_debut )) : '')
			. (/* !$date_jour_entier && */ $heure_debut 
				? ($heure_fin ? ' de ' : ' à ') . $heure_debut : '')
			. ($date_fin && $date_fin != $date_debut ? ' au ' . str_ireplace(' mar ', ' mars ', mysql2date( $format_date_fin, $date_fin )) : '')
			. (/* !$date_jour_entier && */ $heure_fin 
				? ($heure_debut ? ' à ' : ' jusqu\'à ')  . $heure_fin
				: '')
		));
	}
	
	/**
	 * Retourne les catégories d'un évènement
	 */
	public static function get_event_categories( $post_id, $args = 'names' ) {
		return self::get_post_terms( self::taxonomy_ev_category, $post_id, $args);
	}
	/**
	 * Retourne les communes d'un évènement
	 */
	public static function get_event_cities( $post_id, $args = 'names' ) {
		return self::get_post_terms( self::taxonomy_city, $post_id, $args);
	}
	/**
	 * Retourne les diffusions possibles d'un évènement
	 */
	public static function get_event_diffusions( $post_id, $args = 'names' ) {
		return self::get_post_terms( self::taxonomy_diffusion, $post_id, $args);
	}
	/**
	 * Retourne la localisation complète d'un évènement
	 */
	public static function get_event_localisation_and_cities($post_id, $html = true){
		$localisation = get_post_meta($post_id, 'ev-localisation', true);
		if( $html )
			$localisation = htmlentities($localisation);
		if($cities = Agdp_Event::get_event_cities ($post_id, 'names')){
			$cities = implode(', ', $cities);
			if( $html )
				$cities = htmlentities($cities);
			if(self::cities_in_localisation( $cities, $localisation ) === false)
				if( $html ) 
					$localisation .= sprintf('<div class="agdpevent-cities" title="%s"><i>%s</i></div>', 'Communes', $cities);
				else
					$localisation .= sprintf(', %s', $cities);
		}
		return $localisation;
	}
	
	/**
	 * Vérifie si la commune est déjà ennoncé dans la localisation
	 */
	public static function cities_in_localisation( $cities, $localisation ){
		//$terms_like = Agdp_Event_Post_type::get_terms_like($tax_name, $tax_data['IN']);
		$cities = str_ireplace('saint', 'st'
					, preg_replace('/\s|-/', '', $cities)
		);
		$localisation = str_ireplace('saint', 'st'
					, preg_replace('/\s|-/', '', $localisation)
		);
		//TODO accents ?
		return stripos( $localisation, $cities );
	}
	
 	/**
	 * Pré-remplit le formulaire "Contactez nous" avec les informations d'un évènement
	 */
	public static function wpcf7_contact_form_init_tags( $form ) { 
		$html = $form->prop('form');//avec shortcodes du wpcf7
		$requested_id = isset($_REQUEST[self::postid_argument]) ? $_REQUEST[self::postid_argument] : false;
		if( ! ($agdpevent = self::get_post($requested_id)))
			return;
		
		/** init message **/
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
	 * on_send_for_diffusion_mailto
	 */
	public static function on_send_for_diffusion_mailto( $data ){
		if( isset($data['attributes']['format']) ){
			switch( $data['attributes']['format']) {
				case 'text' :
				case 'message' :
					$post = get_post($data['post_id']);
					
					$meta_name = 'ev-organisateur' ;
					$organisateur = get_post_meta($post->ID, $meta_name, true);
					$meta_name = 'ev-email' ;
					if( $email = get_post_meta($post->ID, $meta_name, true) ){
						foreach($data['headers'] as $index => $header)
							if( stripos($header, 'Reply_to') === 0 )
								unset($data['headers'][$index]);
						if($organisateur)
							$email = sprintf('"%s"<%s>', $organisateur, $email );
						$data['headers'][] = sprintf('Reply-to: %s', $email);
					}
					$data['subject'] = static::get_post_title( $post, true );
					$data['message'] = Agdp_Events::get_list_item_html( $post, false, ['mode' => 'email'] );
					
					switch( $data['attributes']['format']) {
						case 'text' :
							$data['message'] = html_to_plain_text( $data['message'], true );
							break;
					}
					
					break;
			}
		}
		return $data;
	}
	
	/**
	 * has_diffusion_openagenda
	 */
	public static function has_diffusion_openagenda( ){
		
		$query_args = array(
			'hide_empty' => false,
			'taxonomy' => self::get_taxonomies_diffusion(),
			'meta_key' => 'connexion',
			'meta_compare' => 'LIKE',
			'meta_value' => 'openagenda',
			'fields' => 'slugs',
		);
		$terms = get_terms( $query_args );
		foreach( $terms as $term ){
			return $term;
		}
		return false;
	}
}
