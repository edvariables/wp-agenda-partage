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

        add_action( 'admin_notices', array(__CLASS__,'show_admin_notices') );
		
		add_action( 'pre_update_option_' . AGDP_TAG, array(__CLASS__,'on_pre_update_option'), 10, 3 );
		add_action( 'update_option_' . AGDP_TAG, array(__CLASS__,'on_updated_option'), 10, 3 );
		
		if(class_exists('WPCF7_ContactForm')){
			add_action( 'wpcf7_admin_notices', array( __CLASS__, 'wpcf7_admin_notices' ), 10, 3 ); //edit
		}

	}

	/**
	 * Registers a stylesheet.
	 */
	public static function register_plugin_styles() {
	    wp_register_style( AGDP_TAG, plugins_url( 'agenda-partage/admin/css/agendapartage-admin.css' ), array(), AGDP_VERSION , 'all'  );
	    wp_enqueue_style( AGDP_TAG);
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
				else
					self::import_ics($fileName, $post_status, $original_file_name );
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
	
	/**
	* import_ics
	*/
	public static function import_ics($file_name, $default_post_status = 'publish', $original_file_name = null){
		 // die("import_ics " . $default_post_status);
		require_once( AGDP_PLUGIN_DIR . '/admin/class.ical.php' );				
		
		//TODO try {}
		$iCal = new iCal($file_name);
		
		$import_source = 'import_ics_' . $iCal->description;
		
		$post_statuses = get_post_statuses();
		$today = strtotime(date("Y-m-d"));
		$successCounter = 0;
		$failCounter = 0;
		$ignoreCounter = 0;
		$log = array();
		$log[] = sprintf('<ul><b>Importation ICS "%s", %s</b>'
			, isset($original_file_name) && $original_file_name ? $original_file_name : basename( $file_name )
			, date_i18n('Y-m-d H:i'));
		if(!$default_post_status)
			$default_post_status = 'publish';
		
		if($user = wp_get_current_user()
		&& $user->ID){
		    $post_author = $user->ID;
		}
		else {
			$post_author = AgendaPartage_User::get_blog_admin_id();
		}
	
		foreach($iCal->events() as $event){
			
			switch(strtoupper($event->status)){
				case 'CONFIRMED':
				case 'TENTATIVE':
					$post_status = $default_post_status;
					break;
				case 'DRAFT':
					$post_status = 'draft';
					break;
				case 'CANCELLED':
					$post_status = 'trash';//TODO signaler
					break;
				default: 
					$ignoreCounter++;
					continue 2;
			}
			// if(($successCounter + $ignoreCounter) > 5) break;//debug
			
			$dateStart = $event->dateStart;
			$dateEnd = $event->dateEnd;
			$timeStart = substr($dateStart, 11, 5);//TODO
			$timeEnd = substr($dateEnd, 11, 5);//TODO 
			if($timeStart == '00:00')
				$timeStart = '';
			if($timeEnd == '00:00')
				$timeEnd = '';
			$dateStart = substr($dateStart, 0, 10);
			$dateEnd = substr($dateEnd, 0, 10);
			
			if(strtotime($dateStart) < $today) {
				$ignoreCounter++;
				continue;
			}
			
			$inputs = array(
				'ev-date-debut' => $dateStart,
				'ev-date-fin' => $dateEnd,
				'ev-heure-debut' =>$timeStart,
				'ev-heure-fin' => $timeEnd,
				'ev-localisation' => $event->location,
				'ev-date-journee-entiere' => $timeStart ? '' : '1',
				'ev-codesecret' => AgendaPartage::get_secret_code(6),
				'post-source' => $import_source
			);
						
			$post_title = $event->summary;
			$post_content = $event->description;
			if ($post_content === null) $post_content = '';
			
			//Check doublon
			$doublon = AgendaPartage_Evenement_Edit::get_post_idem($post_title, $inputs);
			if($doublon){
				//var_dump($doublon);var_dump($post_title);var_dump($inputs);
				$ignoreCounter++;
				$url = AgendaPartage_Evenement::get_post_permalink($doublon);
				$log[] = sprintf('<li><a href="%s">%s</a> existe déjà, avec le statut "%s".</li>', $url, htmlentities($doublon->post_title), $post_statuses[$doublon->post_status]);
				continue;				
			}
			
			$postarr = array(
				'post_title' => $post_title,
				'post_name' => sanitize_title( $post_title ),
				'post_type' => AgendaPartage_Evenement::post_type,
				'post_author' => $post_author,
				'meta_input' => $inputs,
				'post_content' =>  $post_content,
				'post_status' => $post_status
			);
			
			$post_id = wp_insert_post( $postarr, true );
			
			if(!$post_id || is_wp_error( $post_id )){
				$failCounter++;
				$log[] = '<li class="error">Erreur de création de l\'évènement</li>';
				if(is_wp_error( $post_id)){
					$log[] = sprintf('<pre>%s</pre>', var_export($post_id, true));
				}
				$log[] = sprintf('<pre>%s</pre>', var_export($event, true));
				$log[] = sprintf('<pre>%s</pre>', var_export($postarr, true));
			}
			else{
				$successCounter++;
				$post = get_post($post_id);
				$url = AgendaPartage_Evenement::get_post_permalink($post);
				$log[] = sprintf('<li><a href="%s">%s</a> a été importé avec le statut "%s"%s</li>'
						, $url, htmlentities($post->post_title)
						, $post_statuses[$post->post_status]
						, $post->post_status != $default_post_status ? ' !' : '.'
				);
			}
		}
		
		$log[] = sprintf('<li><b>%d importation(s), %d échec(s), %d ignorée(s)</b></li>', $successCounter, $failCounter, $ignoreCounter);
		$log[] = '</ul>';
		self::set_import_report ( $log );
		
		return $successCounter;
	}

	//import
	public static function set_import_report($logs){
		self::add_admin_notice(implode("\r\n", $logs), 'success', true);
	}
	public static function get_import_report($clear = false){
		self::show_admin_notices();
	}
	/*
	**/
	
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
	

}
?>