<?php

class Agdp_Admin {

	public static function init() {
		self::init_includes();
		self::init_hooks();

		do_action( 'agendapartage-admin_init' );
	}

	public static function init_includes() {	

		// Agdp_Admin_Menu loaded and initialized in agenda-partage.php, admin_menu hook.

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-options.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Options', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-user.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_User', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-users.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Users', 'init' ) );

		if( is_multisite()){
			require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-multisite.php' );
			add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Multisite', 'init' ) );
		}
		
		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-edit-post-type.php' );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-mailbox.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Mailbox', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-edit-mailbox.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Edit_Mailbox', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-report.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Report', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-edit-report.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Edit_Report', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-edit-sql-function.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Edit_SQL_Function', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-forum.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Forum', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-edit-forum.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Edit_Forum', 'init' ) );
		
		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-agdpevent.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Evenement', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-newsletter.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Newsletter', 'init' ) );

		if(Agdp::maillog_enable()){
			require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-maillog.php' );
			add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Maillog', 'init' ) );
		}
		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-covoiturage.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Covoiturage', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-edit-agdpevent.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Edit_Evenement', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-edit-covoiturage.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Edit_Covoiturage', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-edit-diffusion.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Edit_Diffusion', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-edit-location.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Edit_Location', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-edit-newsletter.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Edit_Newsletter', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-comments.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Comments', 'init' ) );

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-edit-comment.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Edit_Comment', 'init' ) );

		if(Agdp::maillog_enable()){
			require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-edit-maillog.php' );
			add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Edit_Maillog', 'init' ) );
		}

		require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-edit-wpcf7.php' );
		add_action( 'agendapartage-admin_init', array( 'Agdp_Admin_Edit_WPCF7', 'init' ) );
	}

	public static function init_hooks() {
		
		if ( current_user_can( 'manage_network_plugins' ) ) {
			add_filter( 'plugin_action_links_' . AGDP_PLUGIN_BASENAME, array(__CLASS__, 'plugin_action_links'), 10, 4 );
			add_filter( 'network_admin_plugin_action_links_' . AGDP_PLUGIN_BASENAME, array(__CLASS__, 'plugin_action_links'), 10, 4 );
		}

	    add_action( 'admin_enqueue_scripts', array(__CLASS__, 'register_plugin_styles') );
		add_action( 'admin_enqueue_scripts', array(__CLASS__, 'register_plugin_js') ); 

        add_action( 'admin_notices', array(__CLASS__,'show_admin_notices') );
		
		add_action( 'wp_ajax_'.AGDP_TAG.'_admin_action', array(__CLASS__, 'on_wp_ajax_admin_action_cb') );
	}

	/**
	 * Registers a stylesheet.
	 */
	public static function register_plugin_styles() {
		wp_enqueue_style (  'wp-jquery-ui-dialog');
	    wp_register_style( AGDP_TAG, plugins_url( 'agenda-partage/admin/css/agendapartage-admin.css' ), array(), AGDP_VERSION, false  );
	    wp_enqueue_style( AGDP_TAG);
	    wp_register_style( AGDP_TAG . '_ui', plugins_url( 'agenda-partage/includes/css/agendapartage-ui.css' ), array(), AGDP_VERSION, false );
	    wp_enqueue_style( AGDP_TAG . '_ui');
	    wp_register_style( AGDP_TAG . '_edwp', plugins_url( 'agenda-partage/includes/css/edwp.css' ), array(), AGDP_VERSION, false );
	    wp_enqueue_style( AGDP_TAG . '_edwp');
	}

	/**
	 * Registers js files.
	 */
	public static function register_plugin_js() {
		wp_enqueue_script(array( 'jquery', 'jquery-ui-tabs', 'jquery-ui-dialog' ));
		
	    wp_register_script( AGDP_TAG . '-tools', plugins_url( 'agenda-partage/includes/js/agendapartage-tools.js' ), array('jquery'), AGDP_VERSION , false );
		wp_localize_script( AGDP_TAG . '-tools', 'agdp_ajax', array( 
			'ajax_url' => admin_url('admin-ajax.php')
			, 'check_nonce' => wp_create_nonce('agdp-admin-nonce')
			, 'is_admin' => true )
		);
	    wp_enqueue_script( AGDP_TAG . '-tools' );
	}
	
	/**
	 *
	 */
	public static function plugin_action_links( $actions, $plugin_file, $plugin_data, $context ) {
		if( file_exists(AGDP_PLUGIN_DIR . '/.git') ){
			$action = 'git_update_' . AGDP_TAG;
			$url = '/wp-admin/admin.php?page=agendapartage-git-update';
			$actions[ $action ] = sprintf('<a href="%s" id="%s" aria-label="Mise à jour .git">Mise à jour .git</a>',
				wp_nonce_url( $url, AGDP_TAG),
				$action,
			);
		}
		return $actions;
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
		if( is_array($msg))
			$msg = implode("\r\n", $msg);
		$notices[] = array(
			'message' => $is_html ? $msg : esc_html($msg),
			'type' => $type,
		);
		$result = set_transient(self::admin_notices_tag(), $notices);
		
		return $result;
		
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
	 * Affiche une notification dans l'administration
	 * Gère avec ou sans block-editor.
	 */
	public static function add_admin_notice_now($message, $attrs){
		$current_screen = get_current_screen();
		$is_block_editor = method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor();
		if($is_block_editor){
?><script>( function( wp ) {
    wp.data.dispatch('core/notices').createNotice(
        '<?php echo $attrs['type']?>',
        "<?php echo str_replace('"', '\"', strip_tags($message) )?>",
		{
            isDismissible: true
            <?php if(isset($attrs['actions'])){
			?>, actions: [
                {
                    url: '<?php echo $attrs['actions']['url']?>',
                    label: "<?php echo str_replace('"', '\"', empty($attrs['actions']['label']) ? 'Afficher' : $attrs['actions']['label'])?>"
                }
            ]<?php }?>
        }
    );
} )( window.wp );
</script><?php
		}
		else
			wp_admin_notice($message, $attrs);
	}
	
	//import
	public static function set_import_report($logs){
		if( is_array($logs)){
			self::add_admin_notice( implode("\r\n", $logs), 'success', true);
		}
		else
			self::add_admin_notice($logs, 'success', true);
	}
	public static function get_import_report($clear = false){
		self::show_admin_notices();
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
		$data = empty($_POST['data']) ? [] : $_POST['data'];
		if(array_key_exists("user_id", $_POST)){
			try{
				//cherche une fonction du nom "user_action_{method}"
				$function = array('Agdp_Admin_User', sprintf('user_action_%s', $method));
				$ajax_response = call_user_func( $function, $_POST['user_id']);
			}
			catch( Exception $e ){
				$ajax_response = sprintf('Erreur dans l\'exécution de la fonction :%s', var_export($e, true));
			}
		}
		else{
			if( is_array($data) && array_key_exists("post_type", $data))
				$post_type = $data['post_type'];
			else
				$post_type = false;
			try{
				switch($post_type){
					case Agdp_Evenement::post_type :
						$class = 'Agdp_Admin_Evenement';
						break;
					default:
						if( $post_type ){
							$method = $data['post_type'] . '_' . $method;
							$class = __CLASS__;
						}
						elseif( strpos( $method, '::' ) !== false ){
							$class = substr( $method, 0, strpos( $method, '::' ) );
							$method = substr( $method, strlen($class) + 2 );
						}
						else
							throw new Exception('Impossible de reconnaître la classe appelée.');
						break;
				}
				// cherche une fonction du nom "{$post_type}_action_{method}"
				$function = array($class, sprintf('on_wp_ajax_action_%s', $method));
				$ajax_response = call_user_func( $function, $data );
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