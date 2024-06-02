<?php
class AgendaPartage_User {

	public static function init() {
		self::init_hooks();
	}

	public static function init_hooks() {
		//TODO semble abusif
		if( is_login() && ! isset($_REQUEST['action']) ){
			if( ! isset($_REQUEST['redirect_to']) )
				$_REQUEST['redirect_to'] = $_GET['redirect_to'] = get_bloginfo( 'url' );
		}
		
		//Fenêtre de réinitialisation de mot de passe
		add_action( 'resetpass_form', array(__CLASS__, 'resetpass_form' ));
		
		//Email de validation d'un nouvel utilisateur
		add_filter( 'invited_user_email', array(__CLASS__, 'on_invited_user_email' ), 20, 4);
		if( is_multisite() ) {
			add_filter( 'wpmu_signup_user_notification_subject', array(__CLASS__, 'wpmu_signup_user_notification_subject' ), 20, 5);
			add_filter( 'update_welcome_user_subject', array(__CLASS__, 'on_update_welcome_user_subject' ), 20, 1);
		}
		
		// Masque le menu Mes sites
		if( is_multisite() ) {
			add_action( 'add_admin_bar_menus', array(__CLASS__, 'on_add_admin_bar_menus'), 1 );
		}
	}

	/**
	 * Masque le menu Mes sites
	 */
	public static function on_add_admin_bar_menus( ){
		if(current_user_can('manage_network'))
			return;
		
		//See wp-includes/class-wp-admin-bar.php, function add_menus(). $priority must match to be removed.
		remove_action('admin_bar_menu', 'wp_admin_bar_wp_menu', 10 );
		
		$blogs = get_blogs_of_user(get_current_user_id());
		if( count($blogs) <= 1 )
			remove_action('admin_bar_menu', 'wp_admin_bar_my_sites_menu', 20 );
	}


	/**
	 * Filtre avant envoi de l'email de validation d'un nouvel utilisateur
	 */
	public static function wpmu_signup_user_notification_subject( string $subject, string $user_login, string $user_email, string $key, array $meta ){
		add_filter( 'wp_mail', array(__CLASS__, 'on_wp_mail_set_reply_to' ), 10, 1);
		return preg_replace('/^\[.*\]/', '[' . get_bloginfo('blogname') . ']', $subject);
	}
	/**
	 * Filtre avant envoi de l'email de validation d'un nouvel utilisateur
	 */
	public static function on_invited_user_email( $new_user_email, $user_id, $role, $newuser_key ){
		$new_user_email['subject'] = preg_replace('/^\[.*\]/', '[' . get_bloginfo('blogname') . ']');
		if( stripos($new_user_email['headers'], 'Reply-to:') === false )
			$new_user_email['headers'] .= "\n".sprintf('Reply-to: "%s"<%s>', get_bloginfo('blogname'), get_bloginfo('admin_email'));
		return $new_user_email;
	}
	/**
	 * Filtre avant envoi de l'email l'adresse de réponse
	 */
	public static function on_update_welcome_user_subject($subject){
		$current_network = get_network();
		$subject = str_replace( $current_network->site_name, get_bloginfo('blogname'), $subject);
		add_filter( 'wp_mail', array(__CLASS__, 'on_wp_mail_set_reply_to' ), 10, 1);
		return $subject;
	}
	/**
	 * Filtre avant envoi de l'email l'adresse de réponse
	 */
	public static function on_wp_mail_set_reply_to($args){
		if( is_multisite() ){
			$current_network = get_network();
			if( stripos($args['headers'], 'From:') !== false ){
				$args['headers'] = preg_replace( '/From\:.*(\n|$)/i', sprintf('From: "%s" <%s>$1', get_bloginfo('blogname'), get_bloginfo('admin_email')), $args['headers']);
			}
			else {
				$args['headers'].= "\n".sprintf('From: "%s" <%s>', get_bloginfo('blogname'), get_bloginfo('admin_email'));
			}
		}
		
		if( stripos($args['headers'], 'Reply-to:') == false )
			$args['headers'] .= "\n".sprintf('Reply-to: "%s" <%s>', get_bloginfo('blogname'), get_bloginfo('admin_email'));
		$args['headers'] = str_replace("\n\n", "\n", $args['headers']);
		debug_log_callstack('on_wp_mail_set_reply_to', $args);
		return $args;
	}

	/**
	 *
	 */
	public static function create_user($email = false, $user_name = false, $user_login = false, $data = false, $user_role = false){

		if( ! $email){
			$post = get_post();
			$email = get_post_meta($post->ID, 'ev-email', true);
		}
		
		$user_id = email_exists( $email );
		if($user_id){
			return self::promote_user_to_blog(new WP_User( $user_id ));
		}

		if(!$user_login) {
			$user_login = preg_replace( '/[éèêëÉÈÊË]/','e', 
							preg_replace( '/[àäâÀÄÂ]/','a',
							preg_replace( '/[ôöÔÖ]/','o',
							preg_replace( '/[ûüÛÜ]/','u',
						$user_name ? $user_name : $email ))));
			$user_login = sanitize_key( $user_login );
		}
		if(!$user_id && $user_login) {
			$i = 2;
			while(username_exists( $user_login)){
				$user_login .= $i++;
			}
		}

		// Generate the password and create the user
		$password = wp_generate_password( 12, false );
		$user_id = wp_create_user( $user_login ? $user_login : $email, $password, $email );

		if( is_wp_error($user_id) ){
			return $user_id;
		}

		if( ! is_array($data))
			$data = array();
		$data = array_merge($data, 
			array(
				'ID'				=>	$user_id,
				'nickname'			=>	$user_name ? $user_name : ($user_login ? $user_login : $email),
				'first_name'			=>	$user_name ? $user_name : ($user_login ? $user_login : $email),
				'display_name'			=>	$user_name ? $user_name : ($user_login ? $user_login : $email)

			)
		);

		wp_update_user($data);

		// Set the role
		$user = new WP_User( $user_id );
		if($user) {
			if( ! $user_role)
				$user_role = 'subscriber';
			$user->set_role( $user_role );
			/*if($user->Errors){

			}
			else {
				// Email the user
				//wp_mail( $email_address, 'Welcome!', 'Your Password: ' . $password );
			}*/		
		}
		
		return self::promote_user_to_blog($user);
	}

	public static function promote_user_to_blog( WP_User $user, $blog = false, $role = 'subscriber' ){
		if( ! $blog )
			$blog_id = get_current_blog_id();
		elseif(is_object($blog))//TODO
			$blog_id = $blog->ID;
		else //TODO
			$blog_id = $blog;

		//copie from wp-admin/user-new.php ligne 64
		// Adding an existing user to this blog.
		if ( ! array_key_exists( $blog_id, get_blogs_of_user( $user->ID, false ) ) ) {

			if( current_user_can( 'promote_user', $user->ID )  ){
				$result = add_existing_user_to_blog(
					array(
						'user_id' => $user->ID,
						'role'    => $role,
					)
				);
				if(is_wp_error($result)){
					AgendaPartage_Admin::add_admin_notice( sprintf(__("L'utilisateur %s n'a pas accès à ce site web pour la raison suivante : %s", AGDP_TAG), $user->display_name, $result->get_error_message()), 'error');
				}
				else {
					AgendaPartage_Admin::add_admin_notice( sprintf(__("Désormais, l'utilisateur %s a accès à ce site web en tant qu'organisateur d'évènements.", AGDP_TAG), $user->display_name), 'success');
				}
			}
			else{
				AgendaPartage_Admin::add_admin_notice( sprintf(__("L'utilisateur %s n'a pas accès à ce site web et vous n'avez pas l'autorisation de le lui accorder. Contactez un administrateur de niveau supérieur.", AGDP_TAG), $user->display_name), 'warning');
			}
		}
		return $user;
	}

	public static function get_blog_admin_id(){
		$email = get_bloginfo('admin_email');
		return email_exists( $email );
	}

	/**
	 * Retourne un blog auquel appartient l'utilisateur et en priorité le blog en cours
	 */
	public static function get_current_or_default_blog_id($user){
		$blog_id = get_current_blog_id();
		if($user){
			$blogs = get_blogs_of_user($user->ID, false);
			if( ! array_key_exists($blog_id, $blogs))
				foreach($blogs as $blog){
					$blog_id = $blog->userblog_id;
					break;
				}
		}
		return $blog_id;
	}

	/**
	 * Dans un email à un utilisateur, ajoute une invitation à saisir un nouveau mot de passe.
	 * Returns a string to add to email for user to reset his password.
	 */
	public static function new_password_link($user_id, $redirect_to = false){
		if(is_a($user_id, 'WP_User')){
			$user = $user_id;
			$user_id = $user->ID;
		}
		if(is_super_admin($user_id)
		|| $user_id == AgendaPartage_User::get_blog_admin_id()
		)
			return;
		if( ! isset($user))
			$user = new WP_USER($user_id);
		$password_key = get_password_reset_key($user);
		if( ! $password_key)
			return;
		if(!$redirect_to)
			$redirect_to = get_home_url();
		$url = sprintf("%s?action=rp&key=%s&login=%s&redirect_to=%s", wp_login_url(), $password_key, rawurlencode( $user->user_login ), esc_url($redirect_to));
		// $url = network_site_url( $url );
		$message = sprintf(__( 'Pour définir votre mot de passe, <a href="%s">vous devez cliquer ici</a>.', AGDP_TAG) , $url ) . "\r\n";
		return $message;
	}
	
	
	
	/**
	 * Envoye le mail de bienvenu après inscription et renouvellement de mot de passe
	 */
	public static function send_welcome_email($user_id, $subject = false, $message = false, $return_html_result = false){
		if(is_a($user_id, 'WP_User')){
			$user = $user_id;
			$user_id = $user->ID;
		}
		
		if(!$user_id)
			return false;
		
		if( ! isset($user))
			$user = new WP_USER($user_id);
		
		$email = $user->user_email;
		$to = $email;
		
		$site = get_bloginfo( 'name' );
		
		$subject = sprintf('[%s] %s', $site, $subject ? $subject : 'Inscription de votre compte');
		
		$headers = array();
		$attachments = array();
		
		if( ! $message){
			$message = sprintf('Bonjour,<br>Vous recevez ce message suite la création votre compte.');

		}
		else
			$message .= '<br>'.str_repeat('-', 20);
		
		$message .= "<br><br>" . self::new_password_link($user_id);
		
		$subscriptions = [];
		foreach( AgendaPartage_Newsletter::get_newsletters() as $newsletter){
			$subscription_period_name = AgendaPartage_Newsletter::subscription_period_name(AgendaPartage_Newsletter::get_subscription($email, $newsletter), $newsletter);
			if($subscription_period_name){
				$page = AgendaPartage_Newsletter::get_content_source_page( $newsletter->ID );
				if( $page ) 
					$subscriptions[] = sprintf('<br>Votre abonnement à la lettre-info <a href="%s">%s</a> : %s.'
						, get_permalink( $page )
						, $page->post_title
						, $subscription_period_name);
			}
		}
		if( count($subscriptions) )
			$message .= '<br>' . implode("\n", $subscriptions);
		
		$url = get_permalink(AgendaPartage::get_option('newsletter_subscribe_page_id'));
		$url = add_query_arg('email', $email, $url);
		$message .= sprintf('<br>Vous pouvez modifier votre inscription aux lettres-infos en <a href="%s">cliquant ici</a>.', $url);
		
		$subscriptions = [];
		foreach( AgendaPartage_Forum::get_forums() as $forum){
			if( ( $subscription_role = AgendaPartage_Forum::get_user_subscription($forum, $email) )
			&& isset( AgendaPartage_Forum::subscription_roles[$subscription_role] )
			&& ( $subscription_role !== 'banned' )
		){
				$subscription_role_name = AgendaPartage_Forum::subscription_roles[$subscription_role];
				$subscriptions[] = sprintf('<br>Votre adhésion à <a href="%s">%s</a> : %s.'
					, get_permalink($forum)
					, $forum->post_title
					, $subscription_role_name);
			}
		}
		if( count($subscriptions) )
			$message .= '<br>' . implode("\n", $subscriptions);
		
		$message .= '<br><br>Bien cordialement,<br>L\'équipe de l\'Agenda partagé.';
		
		
		$message = quoted_printable_encode(str_replace('\n', '<br>', $message));

		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-type: text/html; charset=utf-8';
		$headers[] = 'Content-Transfer-Encoding: quoted-printable';
		$headers[] = sprintf('From: %s<%s>', $site, get_bloginfo('admin_email'));
		$headers[] = sprintf('Reply-to: %s<%s>', $site, get_bloginfo('admin_email'));
		
		if($success = wp_mail( $to
			, '=?UTF-8?B?' . base64_encode($subject). '?='
			, $message
			, $headers, $attachments )){
			$html = '<div class="info email-send">L\'e-mail a été envoyé.</div>';
		}
		else{
			$html = sprintf('<div class="email-send alerte">L\'e-mail n\'a pas pu être envoyé.</div>');
			error_log(sprintf("send_welcome_email : L'e-mail n'a pas pu être envoyé à %s.\r\nHeaders : %s\r\Subject : %s\r\nMessage : %s", $email, var_export($headers), $subject, $message));
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
			$url = get_home_url( self::get_current_or_default_blog_id($user), sprintf("wp-admin/"), 'admin' );
		}
		echo sprintf('<input type="hidden" name="%s" value="%s"/>', 'redirect_to', $url );
	}
	
	/**
	 * Compare des utilisateurs.
	 *	is_same_user( WP_Post|int|email|current|true $user1, WP_Post|int|email|current|true $user2 )
	 */
	public static function is_same_user( ...$users ) {
		$first_user_id = false;
		foreach($users as $user){
			if( ! $user || $user === true || $user === 'current' )
				$user_id = get_current_user_id();
			elseif( is_a($user, 'WP_User') )
				$user_id = $user->ID;
			elseif( is_numeric( $user ) )
				$user_id = $user;
			elseif( ! ($user_id = email_exists( $user ) ) )
				return false;
				
			if( $first_user_id ){
				if( $first_user_id != $user_id )
					return false;
			}
			else
				$first_user_id = $user_id;
		}
		return true;
	}
}
