<?php

class AgendaPartage_Admin {

	public static function init() {
		self::init_includes();
		self::init_hooks();

		do_action( 'agendapartage-admin_init' );
	}

	public static function init_includes() {	

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agendapartage-admin-menu.php' );
		AgendaPartage_Admin_Menu::init();

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agendapartage-admin-user.php' );
		add_action( 'agendapartage-admin_init', array( 'AgendaPartage_Admin_User', 'init' ) );

		if( WP_DEBUG || is_multisite()){
			require_once( AGDP_PLUGIN_DIR . '/admin/class.agendapartage-admin-multisite.php' );
			add_action( 'agendapartage-admin_init', array( 'AgendaPartage_Admin_Multisite', 'init' ) );
		}
		require_once( AGDP_PLUGIN_DIR . '/admin/class.agendapartage-admin-agdpevent.php' );
		add_action( 'agendapartage-admin_init', array( 'AgendaPartage_Admin_Evenement', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agendapartage-admin-newsletter.php' );
		add_action( 'agendapartage-admin_init', array( 'AgendaPartage_Admin_Newsletter', 'init' ) );

		if(AgendaPartage::maillog_enable()){
			require_once( AGDP_PLUGIN_DIR . '/admin/class.agendapartage-admin-maillog.php' );
			add_action( 'agendapartage-admin_init', array( 'AgendaPartage_Admin_Maillog', 'init' ) );
		}
		
		require_once( AGDP_PLUGIN_DIR . '/admin/class.agendapartage-admin-edit-post-type.php' );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agendapartage-admin-edit-agdpevent.php' );
		add_action( 'agendapartage-admin_init', array( 'AgendaPartage_Admin_Edit_Evenement', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agendapartage-admin-edit-publication.php' );
		add_action( 'agendapartage-admin_init', array( 'AgendaPartage_Admin_Edit_Publication', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agendapartage-admin-edit-newsletter.php' );
		add_action( 'agendapartage-admin_init', array( 'AgendaPartage_Admin_Edit_Newsletter', 'init' ) );

		if(AgendaPartage::maillog_enable()){
			require_once( AGDP_PLUGIN_DIR . '/admin/class.agendapartage-admin-edit-maillog.php' );
			add_action( 'agendapartage-admin_init', array( 'AgendaPartage_Admin_Edit_Maillog', 'init' ) );
		}
	}

	public static function init_hooks() {

	    add_action( 'admin_enqueue_scripts', array(__CLASS__, 'register_plugin_styles') );
		add_action( 'admin_enqueue_scripts', array(__CLASS__, 'register_plugin_js') ); 

        add_action( 'admin_notices', array(__CLASS__,'show_admin_notices') );
		
		add_action( 'pre_update_option_' . AGDP_TAG, array(__CLASS__,'on_pre_update_option'), 10, 3 );
		add_action( 'update_option_' . AGDP_TAG, array(__CLASS__,'on_updated_option'), 10, 3 );
		
		if(class_exists('WPCF7_ContactForm')){
			add_action( 'wpcf7_admin_notices', array( __CLASS__, 'wpcf7_admin_notices' ), 10, 3 ); //edit
		}
		
		add_action( 'wp_ajax_'.AGDP_TAG.'_admin_action', array(__CLASS__, 'on_wp_ajax_admin_action_cb') );
	}

	/**
	 * Registers a stylesheet.
	 */
	public static function register_plugin_styles() {
	    wp_register_style( AGDP_TAG, plugins_url( 'agenda-partage/admin/css/agendapartage-admin.css' ), array(), AGDP_VERSION , 'all'  );
	    wp_enqueue_style( AGDP_TAG);
	}

	/**
	 * Registers js files.
	 */
	public static function register_plugin_js() {
		wp_enqueue_script("jquery");
		
	    wp_register_script( AGDP_TAG . '-tools', plugins_url( 'agenda-partage/includes/js/agendapartage-tools.js' ), array(), AGDP_VERSION , 'all' );
		wp_localize_script( AGDP_TAG . '-tools', 'agendapartage_ajax', array( 
			'ajax_url' => admin_url('admin-ajax.php')
			, 'check_nonce' => wp_create_nonce('agdp-admin-nonce')
			, 'is_admin' => true )
		);
	    wp_enqueue_script( AGDP_TAG . '-tools' );
	}

	/**
	 * admin_notices tag
	 */
	private static function admin_notices_tag(){
		return AGDP_TAG . '_ADMIN_NOTICES_' . get_current_user_id();
	}
	/**
	 *
	 * $type : success, warning, error
	 */
	public static function add_admin_notice( $msg, $type = 'success', $is_html = false){
		if( ! is_admin())
			return;
		
		$notices = get_transient(self::admin_notices_tag());
		if( ! is_array($notices))
			$notices = array();
		$notices[] = array(
			'message' => $is_html ? $msg : esc_html($msg),
			'type' => $type,
		);
		set_transient(self::admin_notices_tag(), $notices, 5);
	}
	public static function show_admin_notices(){
		$notices = get_transient(self::admin_notices_tag());
		if(is_array($notices)){
			foreach($notices as $notice){
				$class = 'notice notice-' . $notice['type'];
	    		$message = __( $notice['message'], AGDP_TAG );
	    		if( is_wp_error($message)) {
					$message = $message->get_error_messages(); 
				}
				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message ); 
			}
		}
		self::clear_admin_notices();
	}

	public static function clear_admin_notices(){
		delete_transient(self::admin_notices_tag());
	}
	
	/**
	* Hook de mise à jour d'option
	*/
	public static function on_updated_option( $old_values, $values, $option ) {

		//Import d'un fichier
		// !!! à cause du <form enctype="multipart/form-data">, le nom du fichier n'apparait plus dans $values
		$option_key = 'agdpevent_import_ics';
		if( count($_FILES)
			&& array_key_exists( AGDP_TAG, $_FILES)
			&& array_key_exists( 'name', $_FILES[AGDP_TAG])
			&& array_key_exists( $option_key, $_FILES[AGDP_TAG]['tmp_name'])
		){
			$fileName = $_FILES[AGDP_TAG]['tmp_name'][$option_key];
			if($fileName){
				$original_file_name = $_FILES[AGDP_TAG]['name'][$option_key];
				if(array_key_exists($option_key . '-post_status', $values)){
					$post_status = $values[$option_key . '-post_status'];
				}
				else
					$post_status = 'publish';
				if( ! array_key_exists($option_key . '-confirm', $_POST[AGDP_TAG])){
					self::set_import_report(sprintf('<div class="error notice"><p><strong>%s</strong></p></div>', 
							__('Vous n\'avez pas confirmé l\'importation.', AGDP_TAG)));
					var_dump($_POST);
				}
				else{
					require_once(AGDP_PLUGIN_DIR . '/public/class.agendapartage-agdpevents-import.php');
					AgendaPartage_Evenements_Import::import_ics($fileName, $post_status, $original_file_name );
				}
			}
		}
	}
	/**
	* Hook avant mise à jour d'option
	*/
	public static function on_pre_update_option( $values, $old_values, $option ) {
		//clear confirmation
		$option_key = 'agdpevent_import_ics';
		if(array_key_exists($option_key . '-confirm', $values)){
			//force un changement de valeur pour s'assurer de provoquer la mise à jour et passer par le hook update_option
			$values[$option_key . '-confirm'] = rand();
		}
		return $values;
	}
	
	//import
	public static function set_import_report($logs){
		if( is_array($logs))
			self::add_admin_notice(implode("\r\n", $logs), 'success', true);
		else
			self::add_admin_notice($logs, 'success', true);
	}
	public static function get_import_report($clear = false){
		self::show_admin_notices();
	}
	
	public static function wpcf7_admin_notices($tag, $action, $contact_form){
		if( ! is_object($contact_form))
			return;
		foreach(['agdpevent_edit_form_id'
				, 'admin_message_contact_form_id'
				, 'agdpevent_message_contact_post_id'
				, 'contact_form_id'
				, 'newsletter_events_register_form_id'] as $option){
			if($contact_form->id() == AgendaPartage::get_option($option)){
				$label = AgendaPartage::get_option_label($option);
				break;
			}
		}
		if(isset($label) && $label){
			?><br><div class="notice notice-info dashicons-before dashicons-warning">&nbsp;Ce formulaire est utilisé par l'Agenda partagé pour son paramètre "<?=$label?>".</div><?php
		}
	}
	
	/**
	* Logs
	*/
	//file
	/* public static function get_log_file($log_name){
		return sys_get_temp_dir() . '/' . AGDP_TAG . '-'.$log_name.'.log';
	}
	//save
	public static function save_log($logs, $log_name){
		$f = self::get_log_file($log_name);
		if($logs === null){
			if(file_exists($f))
				unlink($f);
			return;
		}
		if(is_array($logs))
			$logs = implode("\r\n", $logs);
		file_put_contents($f, $logs);
	}
	//get
	public static function get_log($log_name, $clear = false){
		$f = self::get_log_file($log_name);
		if(!file_exists($f)) return;
		$logs = file_get_contents($f);
		if($clear)
			self::set_import_report(null);
		return $logs;
	} */
	
	/**
	*/
	public static function check_nonce(){
		if( ! isset($_POST['_nonce']))
			return false;
		return wp_verify_nonce( $_POST['_nonce'], 'agdp-admin-nonce' );
	}
	
	/**
	 * Action required from Ajax query
	 * 
	 */
	public static function on_wp_ajax_admin_action_cb() {
		if( ! self::check_nonce())
			wp_die();
			
		$ajax_response = '0';
		if(!array_key_exists("method", $_POST)){
			wp_die();
		}
		$method = $_POST['method'];
		if(array_key_exists("post_id", $_POST)){
			// try{
				// cherche une fonction du nom "agdpevent_action_{method}"
				// $function = array(__CLASS__, sprintf('agdpevent_action_%s', $method));
				// $ajax_response = call_user_func( $function, $_POST['post_id']);
			// }
			// catch( Exception $e ){
				// $ajax_response = sprintf('Erreur dans l\'exécution de la fonction :%s', var_export($e, true));
			// }
		}
		elseif(array_key_exists("user_id", $_POST)){
			try{
				//cherche une fonction du nom "user_action_{method}"
				$function = array('AgendaPartage_Admin_User', sprintf('user_action_%s', $method));
				$ajax_response = call_user_func( $function, $_POST['user_id']);
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
}
?>