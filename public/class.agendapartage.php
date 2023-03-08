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
			
		//TODO seulemet à l'activation / desactivation, non ? pourtant sans ça, le menu du plugin n'est plus visible
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

		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-wpcf7.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_WPCF7', 'init' ) );
		
		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-agdpevent-shortcodes.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Evenement_Shortcodes', 'init' ) );
		
	}

	public static function init_hooks() {

		add_action( 'wp_enqueue_scripts', array(__CLASS__, 'register_plugin_styles'));
		add_action( 'wp_enqueue_scripts', array(__CLASS__, 'register_plugin_js') ); 
		add_action( 'plugins_loaded', array(__CLASS__, 'load_plugin_textdomain') );

		add_action( 'validate_password_reset', array(__CLASS__, 'validate_password_reset'), 100, 2 );
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
		wp_register_script( AGDP_TAG . '-tools', plugins_url( 'agenda-partage/includes/js/agendapartage-tools.js' ), array(), AGDP_VERSION , 'all' );
		wp_enqueue_script( AGDP_TAG . '-tools' );
		
	    
		wp_localize_script( AGDP_TAG, 'agendapartage_ajax', array( 
			'ajax_url' => admin_url('admin-ajax.php')
			, 'check_nonce' => wp_create_nonce('agdp-nonce')) );
		
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
			add_option( 'Activated_AgendaPartage', true ); //sic
		}
		self::register_user_roles();
		self::register_post_types();
		AgendaPartage_Post_Types::plugin_activation();
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
	public static function is_plugin_active( $main_file_path ) {
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
			// debug_log($_SERVER['REQUEST_URI'], 'include_and_init('.$class_name.')');
			switch($class_name){
				case 'AgendaPartage_Post_Types':
		 			$file = AGDP_PLUGIN_DIR . '/public/class.agendapartage-post_types.php';
					break;

				case 'AgendaPartage_Evenement_Post_type':
		 			$file = AGDP_PLUGIN_DIR . '/public/class.agendapartage-agdpevent-post_type.php';
					break;

				case 'AgendaPartage_Newsletter_Post_type':
		 			$file = AGDP_PLUGIN_DIR . '/public/class.agendapartage-newsletter-post_type.php';
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
		 return self::get_option(AGDP_MAILLOG_ENABLE);
	 }
	 
	 /**
	 * debug_log actif
	 */
	public static function debug_log_enable(){
		 return WP_DEBUG || self::get_option(AGDP_DEBUGLOG_ENABLE);
	 }
	 
	 
	
	/**
	 * Retourne un lien html pour une action générique
	 */
	public static function get_ajax_action_link($post, $method, $icon = false, $caption = null, $title = false, $confirmation = null, $data = null){
		
		if(is_array($method)){
			$class = $method[0];
			$method = $method[1];
		}
		else 
			$class = is_admin() ? 'admin' : '';
		
		if($caption === null)
			$caption = __($method, AGDP_TAG);
				
		if(!$title)
			$title = $caption;
		
		if($icon === true)
			$icon = $method;
		$html = '';
		
		$query = [
			'action' => sprintf('%s%s_action'
				, AGDP_TAG
				, ($class ? '_' : '') . $class
			),
			'method' => $method
		];
		if( ! $post){
			
		} elseif( is_a($post, 'WP_User')){
			$post_id = $post->ID;
			$query['user_id'] = $post_id;
		} else {
			$post_id = is_object($post) ? $post->ID : $post;
			$query['post_id'] = $post_id;
		}
		if($data)
			$query['data'] = $data;
			
		if($confirmation){
			$query['confirm'] = $confirmation;
		}
		if($icon)
			$icon = self::html_icon($icon);
		$html .= sprintf('<span><a href="#" title="%s" class="agdp-ajax-action agdp-ajax-%s" data="%s">%s%s</a></span>'
			, $title ? $title : ''
			, $method
			, esc_attr( json_encode($query) )
			, $icon ? $icon . ' ' : ''
			, $caption);
		
		return $html;
	}
	
	
	
	/**
	*/
	public static function check_nonce(){
		if( ! isset($_POST['_nonce']))
			return false;
		return wp_verify_nonce( $_POST['_nonce'], 'agdp-nonce' );
	}
}
