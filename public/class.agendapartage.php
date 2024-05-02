<?php

class AgendaPartage {

	const REQUIRED_PLUGINS = array(
		'Contact Form 7'     => 'contact-form-7/wp-contact-form-7.php'
	);
	
	public static $skip_mail = false;

	public static function init() {
		self::update_db();//TODO : trop fréquent
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
		add_action( 'agendapartage-init', array( __CLASS__, 'register_post_types' ) );
		// self::register_post_types();

		if(!function_exists('toggle_shortcode_cb'))
			require_once( AGDP_PLUGIN_DIR . '/includes/shortcode.toggle.php' );
		if( ! is_admin() ){
			if(!function_exists('antispam_shortcode_cb'))
				require_once( AGDP_PLUGIN_DIR . '/includes/shortcode.antispam.php' );
			if(!function_exists('style_shortcode_cb'))
				require_once( AGDP_PLUGIN_DIR . '/includes/shortcode.style.php' );
			if(!function_exists('icon_shortcode_cb'))
				require_once( AGDP_PLUGIN_DIR . '/includes/shortcode.icon.php' );
		}
		
		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-user.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_User', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-post-abstract.php' );
		
		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-mailbox.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Mailbox', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-agdpevent.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Evenement', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-agdpevent-edit.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Evenement_Edit', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-agdpevents.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Evenements', 'init' ) );
		

		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-covoiturage.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Covoiturage', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-covoiturage-edit.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Covoiturage_Edit', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-covoiturages.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Covoiturages', 'init' ) );
		

		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-newsletter.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Newsletter', 'init' ) );
		
		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-forum.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Forum', 'init' ) );
		
		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-forum-messages.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Forum_Messages', 'init' ) );
		
		if(self::maillog_enable()){
			require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-maillog.php' );
			add_action( 'agendapartage-init', array( 'AgendaPartage_Maillog', 'init' ) );
		}

		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-wpcf7.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_WPCF7', 'init' ) );
		
		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-agdpevent-shortcodes.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Evenement_Shortcodes', 'init' ) );
		
		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-covoiturage-shortcodes.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Covoiturage_Shortcodes', 'init' ) );
		
		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-forum-shortcodes.php' );
		add_action( 'agendapartage-init', array( 'AgendaPartage_Forum_Shortcodes', 'init' ) );
		
	}

	public static function init_hooks() {

		add_action( 'wp_enqueue_scripts', array(__CLASS__, 'register_plugin_styles'));
		add_action( 'wp_enqueue_scripts', array(__CLASS__, 'register_plugin_js') ); 
		add_action( 'plugins_loaded', array(__CLASS__, 'load_plugin_textdomain') );

		add_action( 'validate_password_reset', array(__CLASS__, 'validate_password_reset'), 100, 2 );
		
		//Définit les paramètres d'url autorisés
		add_filter( 'query_vars', array(__CLASS__, 'on_query_var_cb' ), 10, 1 );
		
		
		add_filter( 'wp_nav_menu_items', array(__CLASS__, 'register_custom_menus' ), 10, 2 );
	}
 	
	/**
	 * Définit les paramètres d'url autorisés
	 */
	public static function on_query_var_cb( $vars ){
		$vars[] = AGDP_ARG_EVENTID;
		$vars[] = AGDP_ARG_NEWSLETTERID;
		$vars[] = AGDP_ARG_COVOITURAGEID;
		$vars[] = AGDP_ARG_COMMENTID;
		return $vars;
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
	    wp_register_style( AGDP_TAG, plugins_url( 'agenda-partage/public/css/agendapartage.css' ), array(), AGDP_VERSION, false );
	    wp_enqueue_style( AGDP_TAG);
	    wp_register_style( AGDP_TAG . '_ui', plugins_url( 'agenda-partage/includes/css/agendapartage-ui.css' ), array(), AGDP_VERSION, false );
	    wp_enqueue_style( AGDP_TAG . '_ui');
	    wp_register_style( AGDP_TAG . '_cal', plugins_url( 'agenda-partage/public/css/agendapartage.calendrier.css' ), array(), AGDP_VERSION, false );
	    wp_enqueue_style( AGDP_TAG . '_cal');
		wp_register_style( AGDP_TAG . '_cov', plugins_url( 'agenda-partage/public/css/agendapartage.covoiturage.css' ), array(), AGDP_VERSION, false );
	    wp_enqueue_style( AGDP_TAG . '_cov');
		
		if(!is_admin())
			wp_enqueue_style('dashicons');
	}

	/**
	 * Registers js files.
	 */
	public static function register_plugin_js() {
		wp_enqueue_script('jquery');
		wp_register_script( AGDP_TAG, plugins_url( 'agenda-partage/public/js/agendapartage.js' ), array('jquery', 'contact-form-7', 'swv'), AGDP_VERSION, false );
		wp_enqueue_script( AGDP_TAG );
		wp_register_script( AGDP_TAG . '-tools', plugins_url( 'agenda-partage/includes/js/agendapartage-tools.js' ), array(), AGDP_VERSION, false );
		wp_enqueue_script( AGDP_TAG . '-tools' );
		
	    
		wp_localize_script( AGDP_TAG, 'agendapartage_ajax', array( 
			'ajax_url' => admin_url('admin-ajax.php')
			, 'check_nonce' => wp_create_nonce('agdp-nonce')) );
		
	}


	/**
	 * Retourne la valeur d'un paramétrage.
	 * Cf AgendaPartage_Admin_Menu
	 */
	public static function get_option( $name = false, $default = false ) {
			
		$options = get_option( AGDP_TAG );

		if ( false === $options ) {
			return $default;
		}

		if ( $name === false ) {
			return $options;
		} elseif ( isset( $options[$name] ) ) {
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
			case 'admin_nl_post_id':
				return __( 'Lettre-info des statistiques d\'administration', AGDP_TAG );
				
			case 'newsletter_subscribe_form_id':
				return __( 'Formulaire de lettre-info', AGDP_TAG );
			case 'events_nl_post_id':
				return __( 'Lettre-info des évènements à diffuser', AGDP_TAG );
			case 'newsletter_subscribe_page_id':
				return __( 'Page d\'inscription à la lettre-info', AGDP_TAG );
			
			case 'agdpevent_edit_form_id':
				return __( 'Formulaire d\'ajout et de modification d\'évènement', AGDP_TAG );
			case 'contact_page_id':
				return __( 'Page "Ecrivez-nous"', AGDP_TAG );
			case 'contact_form_id':
				return __( 'Formulaire "Ecrivez-nous"', AGDP_TAG );
			case 'agdpevent_message_contact_form_id':
				return __( 'Message aux organisateurs dans les pages des évènements', AGDP_TAG );
			case 'agdpevent_import_ics':
				return __( 'Importation d\'un fichier ICS', AGDP_TAG );
			case 'agenda_page_id':
				return __( 'Page contenant l\'agenda', AGDP_TAG );
			case 'new_agdpevent_page_id':
				return __( 'Page "Ajouter un évènement"', AGDP_TAG );
			case 'blog_presentation_page_id':
				return __( 'Page "Page de présentation du site"', AGDP_TAG );
			case 'newsletter_diffusion_term_id':
				return __( 'Diffusion "Lettre-info"', AGDP_TAG );
				
			case 'covoiturage_edit_form_id':
				return __( 'Formulaire d\'ajout et de modification de covoiturage', AGDP_TAG );
			case 'new_covoiturage_page_id':
				return __( 'Page "Ajouter un covoiturage"', AGDP_TAG );
			case 'covoiturages_page_id':
				return __( 'Page contenant les covoiturages', AGDP_TAG );
			case 'covoiturages_nl_post_id':
				return __( 'Lettre-info des covoiturages à diffuser', AGDP_TAG );
				
			case 'agdpevent_need_validation':
				return __( 'Les nouveaux évènements doivent être validés par email (sauf utilisateur connecté)', AGDP_TAG );
			case 'covoiturage_need_validation':
				return __( 'Les nouveaux covoiturages doivent être validés par email (sauf utilisateur connecté)', AGDP_TAG );
				
			case 'forums_parent_id':
				return __( 'Page parente des forums', AGDP_TAG );
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
		if( $value === null){
			if( isset($options[$name]))
				unset($options[$name]);
		}
		else
			$options = array_merge( $options, array( $name => $value ) );
		
		$result = update_option( AGDP_TAG, $options );
	}
		
	/**
	* Fournit un code aléatoire sur une longueur déterminée.
	* Utilisé pour les champs AGDP_EVENT_SECRETCODE
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
	 * require_once(class) then class::init()
	 */
	private static function include_and_init($class_name){
		if(! class_exists($class_name)){
			// debug_log($_SERVER['REQUEST_URI'], 'include_and_init('.$class_name.')');
			switch($class_name){
				case 'AgendaPartage_Post_Types':
		 			$file = AGDP_PLUGIN_DIR . '/public/class.agendapartage-post_types.php';
					break;

				case 'AgendaPartage_Mailbox_Post_type':
		 			$file = AGDP_PLUGIN_DIR . '/public/class.agendapartage-mailbox-post_type.php';
					break;

				case 'AgendaPartage_Evenement_Post_type':
		 			$file = AGDP_PLUGIN_DIR . '/public/class.agendapartage-agdpevent-post_type.php';
					break;

				case 'AgendaPartage_Newsletter_Post_type':
		 			$file = AGDP_PLUGIN_DIR . '/public/class.agendapartage-newsletter-post_type.php';
					break;

				case 'AgendaPartage_Covoiturage_Post_type':
		 			$file = AGDP_PLUGIN_DIR . '/public/class.agendapartage-covoiturage-post_type.php';
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
		self::include_and_init('AgendaPartage_Mailbox_Post_type');
		AgendaPartage_Mailbox_Post_type::register_user_role();
		self::include_and_init('AgendaPartage_Evenement_Post_type');
		AgendaPartage_Evenement_Post_type::register_user_role();
		self::include_and_init('AgendaPartage_Newsletter_Post_type');
		AgendaPartage_Newsletter_Post_type::register_user_role();
		self::include_and_init('AgendaPartage_Covoiturage_Post_type');
		AgendaPartage_Covoiturage_Post_type::register_user_role();
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
	public static function register_post_types(){
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
	 * HTML icon
	 */
	public static function icon($icon, $content = '', $class = '', $tag = 'span', $title = false){
		 return sprintf('<%s class="dashicons-before dashicons-%s %s"%s>%s</%s>'
			, $tag
			, $icon
			, $class
			, $title ? ' title="' . esc_attr($title) . '"' : ''
			, $content
			, $tag);
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
	public static function get_ajax_action_link($post, $method, $icon = false, $caption = null, $title = false
												, $confirmation = null, $data = null, $href = '#'){
		
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
		if( ! $href )
			$href = sprintf('/wp-admin/admin-ajax.php/?action=%s&method=%s&%s'
				, $query['action']
				, $method
				, http_build_query(['data' => $query['data']]) );
		if($icon)
			$icon = self::icon($icon);
		$html .= sprintf('<span><a href="%s" title="%s" class="agdp-ajax-action agdp-ajax-%s" data="%s">%s%s</a></span>'
			, $href
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

	/**
	 * Blog db version
	 */
	public static function get_db_version_option_name(){
		return AGDP_TAG.'_db_version';
	}
	public static function get_db_version(){
		return get_option(self::get_db_version_option_name());
	}
	/**
	 * Update DB based on blog db version
	 */
	public static function update_db(){
		$current_version = self::get_db_version();
		foreach([ '1.0.22'
				, '1.0.23'
				, '1.1.1'
			] as $version){
			if( $current_version && version_compare($current_version, $version, '>='))
				continue;
			if( ! self::update_db_version($version))
				break;
			debug_log('update_db_version ' . $version . ' done (from '. $current_version . ')');
			$current_version = $version;
		}
		return $current_version;
	}
	public static function update_db_version($version){
		require_once(AGDP_PLUGIN_DIR . '/public/class.agendapartage-db-update.php');
		$function = 'update_db_' . str_replace('.', '_', $version); //eg, function update_db_1.0.22
		if ( ! method_exists( AgendaPartage_DB_Update::class, $function ) )
			throw new BadFunctionCallException('AgendaPartage_DB_Update::' . $function . __(' n\'existe pas.', AGDP_TAG));
		if ( ! AgendaPartage_DB_Update::$function() )
			return false;
		update_option(self::get_db_version_option_name(), $version);
		return $version;
	}
	
	
	/**
	 * Custom menus
	 * - Menu Se connecter / Se déconnecter
	 */
	public static function register_custom_menus( $items, $args ){
		if ( $args->theme_location == 'top' ) {
			
			if( self::get_option(AGDP_CONNECT_MENU_ENABLE) ){
				if(is_user_logged_in()){
					$url = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
					$url = wp_login_url($url, true) . '&action=logout';
					$label = 'Se déconnecter';
				}
				else{
					$url = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
					$url = wp_login_url($url);
					$label = 'Se connecter';
				}
				
				$items .= sprintf('<li><a href="%s">%s</a></li>', $url, $label);
			}
		}
		return $items;
	}
	
	/**
	 * Get current post type
	 */
	public static function get_current_post_type(){
		global $post;
		if( $post )
			return $post->post_type;
		if(isset($_REQUEST['post_type']))
			return $_REQUEST['post_type'];
		
		if(function_exists('get_current_sreen'))
			return get_current_sreen()->post_type;
		
		$page = get_queried_object_id();
		if( ! $page )
			if( $page = get_page_by_path($_SERVER['REQUEST_URI']))
				$page = $page->ID;
		
		if( $page )
		switch($page){
			case self::get_option('new_agdpevent_page_id'):
				return AgendaPartage_Evenement::post_type;
			case self::get_option('new_covoiturage_page_id'):
				return AgendaPartage_Covoiturage::post_type;
			default:
				break;
		}
		return false;
	}
}
