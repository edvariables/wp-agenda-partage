<?php

class AgendaPartage {

	const REQUIRED_PLUGINS = array(
		'Contact Form 7'     => 'contact-form-7/wp-contact-form-7.php'
	);
	
	public static $skip_mail = false;

	public static function init() {
		
		self::init_includes();
		self::init_hooks();
		self::load_modules();

		do_action( 'agendapartage-init' );
	}

	public static function admin_init() {
		if(! class_exists('AgendaPartage_Admin')){
			require_once( AGDP_PLUGIN_DIR . '/admin/class.agendapartage-admin.php' );
			AgendaPartage_Admin::init();
		}
	}

	public static function init_includes() {
			
		//TODO seulemet à l'activation / desactivation, non ? pourtant sans ça, le menu Évènements n'est plus visible
		self::register_post_types();

		if(!function_exists('antispam_shortcode_cb'))
			require_once( AGDP_PLUGIN_DIR . '/public/shortcode.antispam.php' );
		if(!function_exists('toggle_shortcode_cb'))
			require_once( AGDP_PLUGIN_DIR . '/public/shortcode.toggle.php' );

		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-user.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_User', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-agdpevent.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Evenement', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-agdpevent-edit.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Evenement_Edit', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-agdpevents.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Evenements', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-newsletter.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Newsletter', 'init' ) );

		if(self::maillog_enable()){
			require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-maillog.php' );
			add_action( 'agendapartage-init', array( 'AgendaPartage_Maillog', 'init' ) );
		}
		
		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-agdpevent-shortcodes.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Evenement_Shortcodes', 'init' ) );
		
	}

	public static function init_hooks() {

		add_action( 'wp_enqueue_scripts', array(__CLASS__, 'register_plugin_styles'));
		add_action( 'wp_enqueue_scripts', array(__CLASS__, 'register_plugin_js') ); 
		add_action( 'plugins_loaded', array(__CLASS__, 'load_plugin_textdomain') );
		if( self::may_skip_recaptcha() ){
			// add_filter( 'wpcf7_load_js', '__return_false' );
			// add_filter( 'wpcf7_load_css', '__return_false' );
		}
		
		//wpcf7_before_send_mail : mise à jour des données avant envoi (ou annulation) de mail
		add_filter( 'wpcf7_before_send_mail', array(__CLASS__, 'wpcf7_before_send_mail'), 10, 3);
		// if( WP_DEBUG && in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', 'pstfe.ed2020', '::1' ) ) ) {
			// add_filter( 'wpcf7_mail_failed', array(__CLASS__, 'wpcf7_mail_sent'), 10,1);
		// }

		//Contact Form 7 hooks
		add_filter( 'wp_mail', array(__CLASS__, 'wp_mail_check_headers_cb'), 10,1);

		//Contrôle de l'envoi effectif des mails	
		add_filter('wpcf7_skip_mail', array(__CLASS__, 'wpcf7_skip_mail'), 10, 2);
			
		// Interception des emails en localhost
		if( WP_DEBUG && in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', 'pstfe.ed2020', '::1' ) ) ) {
			// add_filter( 'wp_mail', array(__CLASS__, 'wp_mail_localhost'), 100, 1);
		}

		add_action( 'validate_password_reset', array(__CLASS__, 'validate_password_reset'), 100, 2 );
	}
	
	// define the wpcf7_skip_mail callback 
	public static function wpcf7_skip_mail( $skip_mail, $contact_form ){ 
		if($contact_form->id() == self::get_option('newsletter_events_register_form_id'))
			return true;
		return $skip_mail || self::$skip_mail;
	} 
	
	// 
	public static function may_skip_recaptcha( ){ 
	   return current_user_can('manage_options');
	} 
	
	/**
	* Hook de wp_mail
	* Interception des emails en localhost
	* TODO Vérifier avec plug-in Smtp
	*/		
	public static function wp_mail_localhost($args){
		echo "<h1>Interception des emails en localhost.</h1>";

		print_r(sprintf("%s : %s<br>\n", 'To', $args["to"]));
		print_r(sprintf("%s : %s<br>\n", 'Subject', $args["subject"]));
		print_r(sprintf("%s : <code>%s</code><br>\n", 'Message', preg_replace('/html\>/', 'code>', $args["message"] )));
		print_r(sprintf("%s : %s<br>\n", 'Headers', $args['headers']));
		//Cancels email without noisy error and clear log
		$args["to"] = '';
		$args["subject"] = '(localhost)';
		$args["message"] = '';
		$args['headers'] = '';
	    return $args;
	}

	/*
	* Hook de validate_password_reset
	* En retour de validation d'un nouveau mot de passe, redirige. Utile en multisites.
	*/
	public static function validate_password_reset ( $errors, $user ){
		if ( isset($_GET['action']) && $_GET['action'] == 'resetpass' 
		&&	( ! $errors->has_errors() )
		&& isset( $_POST['pass1'] ) && ! empty( $_POST['pass1'] ) 
		&& isset( $_POST['redirect_to'] ) && ! empty( $_POST['redirect_to'] ) ) {
			//code from wp-login.php
			list( $rp_path ) = explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$rp_cookie       = 'wp-resetpass-' . COOKIEHASH;
			reset_password( $user, $_POST['pass1'] );
			setcookie( $rp_cookie, ' ', time() - YEAR_IN_SECONDS, $rp_path, COOKIE_DOMAIN, is_ssl(), true );

			wp_redirect( $_POST['redirect_to'] );
			/*login_header( __( 'Password Reset' ), '<p class="message reset-pass">' . __( 'Your password has been reset.' ) . ' <a href="' . esc_url( wp_login_url() ) . '">' . __( 'Log in' ) . '</a></p>' );
			login_footer();*/
			exit;
		}
	}

	/**
	* Hook de wp_mail
	* Définition du Headers.From = admin_email
	*/		
	public static function wp_mail_check_headers_cb($args) {
		if( ! $args['headers'])
			$args['headers'] = [];
		if(is_array($args['headers'])){
			$from_found = false;
			foreach($args['headers'] as $index=>$header)
				if($from_found = str_starts_with($header, 'From:'))
					break;
			if( ! $from_found)
				$args['headers'][] = sprintf('From: %s<%s>', get_bloginfo('name'), get_bloginfo('admin_email'));
		}
		return $args;
	}
	
	/**
	 * Intercepte les emails de formulaires wpcf7.
	 * Appel une classe spécifique suivant l'id du formulaire que l'utilisateur vient de valider
	 */
	public static function wpcf7_before_send_mail ($contact_form, &$abort, $submission){

		$form_id = $contact_form->id();

		switch($form_id){
			case self::get_option('agdpevent_edit_form_id') :
				AgendaPartage_Evenement_Edit::submit_agdpevent_form($contact_form, $abort, $submission);
				break;
			case self::get_option('newsletter_events_register_form_id') :
				AgendaPartage_Newsletter::submit_subscription_form($contact_form, $abort, $submission);
				break;
				
		}		

		return;
	}

	public static function load_plugin_textdomain() {

	    load_plugin_textdomain( AGDP_PLUGIN_NAME, FALSE, AGDP_PLUGIN_DIR . '/languages/' );
	}

	public static function load_modules() {
		//self::load_module( 'agdpevent' );
	}

	protected static function load_module( $mod ) {
		$dir = AGDP_PLUGIN_MODULES_DIR;

		if ( empty( $dir ) or ! is_dir( $dir ) ) {
			return false;
		}

		$file = path_join( $dir, $mod . '.php' );

		if ( file_exists( $file ) ) {
			include_once $file;
		}
	}

	/**
	 * Registers stylesheets.
	 */
	public static function register_plugin_styles() {
	    wp_register_style( AGDP_TAG, plugins_url( 'agenda-partage/public/css/agendapartage.css' ), array(), AGDP_VERSION , 'all' );
	    wp_enqueue_style( AGDP_TAG);
	    wp_register_style( AGDP_TAG . '_cal', plugins_url( 'agenda-partage/public/css/agendapartage.calendrier.css' ), array(), AGDP_VERSION , 'all' );
	    wp_enqueue_style( AGDP_TAG . '_cal');
		
		if(!is_admin())
			wp_enqueue_style('dashicons');
	}

	/**
	 * Registers js files.
	 */
	public static function register_plugin_js() {
		wp_enqueue_script("jquery");
		
	    wp_register_script( AGDP_TAG, plugins_url( 'agenda-partage/public/js/agendapartage.js' ), array(), AGDP_VERSION , 'all' );
		
	    wp_enqueue_script( AGDP_TAG );
		wp_localize_script( AGDP_TAG, 'agendapartage_ajax', array( 
			'ajax_url' => admin_url('admin-ajax.php')
			, 'check_nonce' => wp_create_nonce('agdp-nonce')) );
			
		
		if(self::is_plugin_active('wpcf7-recaptcha/wpcf7-recaptcha.php')){
			if( self::may_skip_recaptcha() ){
				// wp_dequeue_script( 'google-recaptcha' );   
				// wp_dequeue_script('wpcf7-recaptcha');
				// wp_dequeue_style('wpcf7-recaptcha');
				// add_filter( 'wpcf7_load_js', '__return_false' );
				// add_filter( 'wpcf7_load_css', '__return_false' );
				// remove_action( 'wp_enqueue_scripts', 'wpcf7_recaptcha_enqueue_scripts', 20 );
			}
			else {
				wp_register_script( 'wpcf7-recaptcha-controls-ajaxable.js', plugins_url( 'agenda-partage/includes/js/wpcf7-recaptcha-controls-ajaxable.js' ), array(), AGDP_VERSION , true );
				wp_enqueue_script( 'wpcf7-recaptcha-controls-ajaxable.js' );
			}
		}
	}


	/**
	 * Retourne la valeur d'un paramétrage.
	 * Cf AgendaPartage_Admin_Menu
	 */
	public static function get_option( $name, $default = false ) {
			
		$options = get_option( AGDP_TAG );

		if ( false === $options ) {
			return $default;
		}

		if ( isset( $options[$name] ) ) {
			return $options[$name];
		} else {
			return $default;
		}
	}

	/**
	 * Retourne le libellé  d'un paramétrage.
	 */
	public static function get_option_label( $name  ) {
		switch($name){
			case 'admin_message_contact_form_id':
				return __( 'Message de la part de l\'administrateur', AGDP_TAG );
			case 'newsletter_events_register_form_id':
				return __( 'Formulaire de lettre-info', AGDP_TAG );
			case 'newsletter_post_id':
				return __( 'Lettre-info à diffuser', AGDP_TAG );
			case 'newsletter_subscribe_page_id':
				return __( 'Page d\'inscription à la lettre-info', AGDP_TAG );
			case 'agdpevent_edit_form_id':
				return __( 'Formulaire d\'ajout et de modification d\'évènement', AGDP_TAG );
			case 'contact_page_id':
				return __( 'Page "Ecrivez-nous"', AGDP_TAG );
			case 'contact_form_id':
				return __( 'Formulaire "Ecrivez-nous"', AGDP_TAG );
			case 'agdpevent_message_contact_post_id':
				return __( 'Message aux organisateurs dans les pages des évènements', AGDP_TAG );
			case 'agdpevent_import_ics':
				return __( 'Importation d\'un fichier ICS', AGDP_TAG );
			case 'agenda_page_id':
				return __( 'Page contenant l\'agenda', AGDP_TAG );
			case 'new_agdpevent_page_id':
				return __( 'Page "Ajouter un évènement"', AGDP_TAG );
			default:
				return "[{$name}]";
		}
	}

	/**
	 * Enregistre la valeur d'un paramétrage.
	 * Cf AgendaPartage_Admin_Menu
	 */
	public static function update_option( $name, $value ) {
		$options = get_option( AGDP_TAG );
		$options = ( false === $options ) ? array() : (array) $options;
		$options = array_merge( $options, array( $name => $value ) );
		update_option( AGDP_TAG, $options );
	}
		
	/**
	* Fournit un code aléatoire sur une longueur déterminée.
	* Utilisé pour les champs AGDP_SECRETCODE
	*/
	public static function get_secret_code ($length = 6, $alphanum = true){
		$numeric = !$alphanum || str_starts_with( $alphanum, 'num');
		if ($length < 1)
			$length = 1;
		elseif ($numeric && $length > 9)
			$length = 9;
		
		if($numeric)
			$chars = '12345689';
		elseif(str_starts_with( $alphanum, 'text')
			|| ($alphanum == 'alpha'))
			$chars = 'ABCDEFGHIJKLMNPRSTUVWXYZ';
		else
			$chars = 'ABCDEFGHIJKLMNPRSTUVWXYZ12345689';
		$rand = '';
		for($i = 0; $i < $length; $i++){
			$rand[$i] = $chars[ intval(rand( 0, strlen($chars)-1)) ];
		}
			
		return $rand;
	}
		
	/**
	* Fournit l'identifiant de session actuelle.
	* WP n'autorise pas l'utilisation de session php. On utilise l'adresse IP du client pour la journée.
	*/
	public static function get_session_id (){
		$nonce_name = sprintf('%s|%s|%s|%s', AGDP_TAG, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], date('Y-m-d'));
		// $cookie_value = isset($_COOKIE[$cookie_name]) ? $_COOKIE[$cookie_name] : null;
		$nonce_value = wp_create_nonce($nonce_name);
		// var_dump($nonce_value);
		return $nonce_value;
		/* 
		//TODO voir session tokens 
		if($nonce_value){
			// if(str_ends_with($cookie_value, date('Y-m-d')))
			if(wp_verify_nonce($nonce_value, $nonce_name))
				return $nonce_value;
		}
		
		$nonce_value = wp_create_nonce($nonce_name);
		// $expires = mktime(0,0,0, date('m'), date('d'), date('Y')) + 24 * 3600;
		// setcookie(
			// $cookie_name,
			// $cookie_value,
			// $expires,
			// "/",
			// $_SERVER['SERVER_NAME']
		// );
		return $nonce_value; */
	}

////////////////////////////////////////////////////////////////////////////////////////////
/**
* 
*/

	/**
	 * Attached to activate_{ plugin_basename( __FILES__ ) } by register_activation_hook()
	 * @static
	 */
	public static function plugin_activation() {
		$missing_plugins = array();

		if ( version_compare( $GLOBALS['wp_version'], AGDP_MINIMUM_WP_VERSION, '<' ) ) {
			load_plugin_textdomain( AGDP_TAG );
			
			$message = '<strong>'.sprintf(esc_html__( 'AgendaPartage %s nécessite une version de WordPress %s ou plus.' , AGDP_TAG), AGDP_VERSION, AGDP_MINIMUM_WP_VERSION ).'</strong> ';

			die( sprintf('<div class="error notice"><p>%s</p></div>', $message) );

		// TODO les autres plugins ne sont pas encore activés, on ne peut pas tester get_active_plugins()
		// } elseif ( count($missing_plugins = self::get_missing_plugins_list()) ) {
			// load_plugin_textdomain( AGDP_TAG );
			
			// $message = '<strong>'.sprintf(esc_html__( 'AgendaPartage nécessite l\'extension "%s"' , AGDP_TAG), $missing_plugins[0] ).'</strong> ';
			// $message .= '<br>Extensions chargées : ' . implode('<br>', self::get_active_plugins());
			
			// die( sprintf('<div class="error notice"><p>%s</p></div>', $message) );
		
		} elseif ( ! empty( $_SERVER['SCRIPT_NAME'] ) && false !== strpos( $_SERVER['SCRIPT_NAME'], '/wp-admin/plugins.php' ) ) {
			add_option( 'Activated_AgendaPartage', true );
		}
		self::register_user_roles();
		self::register_post_types();
	}

	/**
	 * @return string[] Names of plugins that we require, but that are inactive.
	 */
	private static function get_missing_plugins_list() {
		$missing_plugins = array();
		foreach ( self::REQUIRED_PLUGINS as $plugin_name => $main_file_path ) {
			if ( ! self::is_plugin_active( $main_file_path ) ) {
				$missing_plugins[] = $plugin_name;
			}
		}
		return $missing_plugins;
	}

	/**
	 * @param string $main_file_path Path to main plugin file, as defined in self::REQUIRED_PLUGINS.
	 *
	 * @return bool
	 */
	private static function is_plugin_active( $main_file_path ) {
		return in_array( $main_file_path, self::get_active_plugins() );
	}

	/**
	 * @return string[] Returns an array of active plugins' main files.
	 */
	private static function get_active_plugins() {
		return apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
	}

	/**
	 * Removes all connection options
	 * @static
	 */
	public static function plugin_deactivation( ) {
		
		// Remove any scheduled cron jobs.
		$agendapartage_cron_events = array(
			'agendapartage_schedule_cron_recheck',
			'agendapartage_scheduled_delete',
		);
		
		foreach ( $agendapartage_cron_events as $agendapartage_cron_event ) {
			$timestamp = wp_next_scheduled( $agendapartage_cron_event );
			
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $agendapartage_cron_event );
			}
		}
		self::unregister_post_types();
		self::unregister_user_roles();
	}

	/**
	 * register_post_types
	 */
	private static function include_and_init($class_name){
		if(! class_exists($class_name)){
			switch($class_name){
				case 'AgendaPartage_Post_Types':
		 			$file = AGDP_PLUGIN_DIR . '/public/class.agendapartage-post_types.php';
					break;

				case 'AgendaPartage_Evenement_Post_type':
		 			$file = AGDP_PLUGIN_DIR . '/public/class.agendapartage-agdpevent-post_type.php';
					break;
				
				default:
					var_dump($class_name);//show calls stack
					die(sprintf('include_and_init("%s") : Classe inconnue', $class_name));
			}
			require_once( $file );
			if(method_exists($class_name, 'init'))
				$class_name::init();
		}
	}

	/**
	 * Register user roles
	 */
	private static function register_user_roles(){
		self::include_and_init('AgendaPartage_Evenement_Post_type');
		AgendaPartage_Evenement_Post_type::register_user_role();
		self::include_and_init('AgendaPartage_Newsletter_Post_type');
		AgendaPartage_Newsletter_Post_type::register_user_role();
	}

	/**
	 * Unregister user roles
	 */
	private static function unregister_user_roles(){
		remove_role( 'agdpevent');
	}

	/**
	 * register_post_types
	 */
	private static function register_post_types(){
		self::include_and_init('AgendaPartage_Post_Types');
		AgendaPartage_Post_Types::register_post_types();
	}

	/**
	 * unregister_post_types
	 */
	private static function unregister_post_types(){
		self::include_and_init('AgendaPartage_Post_Types');
		AgendaPartage_Post_Types::unregister_post_types();
	}
	
	/**
	 * HTML tools
	 */
	 public static function html_icon($icon, $class = '', $inner = '', $tag = 'span'){
		 return sprintf('<%s class="dashicons-before dashicons-%s %s">%s</%s>', $tag, $icon, $class, $inner, $tag);
	 }
	 
	 /**
	 * Maillog actif
	 */
	 public static function maillog_enable(){
		 return AgendaPartage::get_option(AGDP_MAILLOG_ENABLE);
	 }
}
