<?php 

/**
 * Chargé lors du hook admin_menu qui est avant le admin_init
 * Edition des options du plugin
 */
class Agdp_Admin_Menu {

	static $initialized = false;

	public static function init() {
		if(self::$initialized)
			return;
		self::$initialized = true;
		self::init_includes();
		self::init_hooks();
		self::init_admin_menu();
	}

	public static function init_includes() {
	}

	public static function init_hooks() {

		//TODO
		// Le hook admin_menu est avant le admin_init
		//add_action( 'admin_menu', array( __CLASS__, 'init_admin_menu' ), 5 ); 
		add_action('wp_dashboard_setup', array(__CLASS__, 'init_dashboard_widgets') );

		add_action( 'admin_bar_menu', array(__CLASS__, 'on_wp_admin_bar_posts_menu'), 64 );
		
	}

	/**
	 * top level menu
	 */
	public static function init_admin_menu() {
		if( is_network_admin())
			return;
		
		// add top level menu page
		add_menu_page(
			__('Paramètres de l\'Agenda partagé', AGDP_TAG),
			'Agenda Partagé',
			'manage_options',
			AGDP_TAG,
			array('Agdp_Admin_Options', 'agdp_options_page_html'),
			'dashicons-lightbulb',
			25
		);
		
		$capability = 'manage_options';
		if(! current_user_can( $capability )){

		    $user = wp_get_current_user();
		    $roles = ( array ) $user->roles;
		    if( ! in_array('agdpevent', $roles)) {
				remove_menu_page('posts');//TODO
				remove_menu_page('wpcf7');
			}
		}
		else {
			
			//Menu Newsletters
			$option = 'admin_nl_post_id';
			if ( $post_id = Agdp::get_option($option) ){
				$parent_slug = sprintf('edit.php?post_type=%s', Agdp_Newsletter::post_type) ;
				$page_title =  Agdp::get_option_label($option);
				$menu_slug = sprintf('post.php?post=%s&action=edit', $post_id);
				add_submenu_page( $parent_slug, $page_title, 'Administrateurices', $capability, $menu_slug);
			}
			$option = 'events_nl_post_id';
			if ( $post_id = Agdp::get_option($option) ){
				$parent_slug = sprintf('edit.php?post_type=%s', Agdp_Newsletter::post_type) ;
				$page_title =  Agdp::get_option_label($option);
				$menu_slug = sprintf('post.php?post=%s&action=edit', $post_id);
				add_submenu_page( $parent_slug, $page_title, 'Evènements à venir', $capability, $menu_slug);
			}
			$option = 'covoiturages_nl_post_id';
			if( Agdp_Covoiturage::is_managed() 
			 && ( $post_id = Agdp::get_option($option) )){
				$parent_slug = sprintf('edit.php?post_type=%s', Agdp_Newsletter::post_type) ;
				$page_title =  Agdp::get_option_label($option);
				$menu_slug = sprintf('post.php?post=%s&action=edit', $post_id);
				add_submenu_page( $parent_slug, $page_title, 'Covoiturages à venir', $capability, $menu_slug);
			}
			
			//Menu Evènements et Covoiturages
			foreach( Agdp_Post::get_post_types() as $post_type ){
				$parent_slug = sprintf('edit.php?post_type=%s', $post_type);
				
				$type_title = get_post_type_object($post_type)->labels->menu_name;
				$page_title =  $type_title . ' en attente de validation';
				$menu_slug = $parent_slug . '&post_status=pending';
				add_submenu_page( $parent_slug, $page_title, 'En attente', $capability, $menu_slug, '', 1);
			
				$obsolatable = in_array( $post_type, [ Agdp_Event::post_type, Agdp_Covoiturage::post_type ] );
				if( $obsolatable){
					$page_title =  $type_title . ' obsolètes';
					$menu_slug = $parent_slug . '&deletable=1';
					add_submenu_page( $parent_slug, $page_title, 'Obsolètes', $capability, $menu_slug, '', 2);
				}
			}
			
			//Menu Agenda partagé
			if( true ){
				$parent_slug = AGDP_TAG;
				
				//Mailboxes		
				$page_title = get_post_type_object(Agdp_Mailbox::post_type)->labels->menu_name;
				$menu_slug = sprintf('edit.php?post_type=%s', Agdp_Mailbox::post_type);
				add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, false, null);
				
				$page_title =  'Règles de publications';
				$menu_slug = $parent_slug . '-rights';
				add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, 
					array(__CLASS__, 'agdp_rights_page_html'), null);
				
				//Import		
				$page_title = 'Importer';
				$menu_slug = $parent_slug . '-import';
				add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, 
					array(__CLASS__, 'agdp_import_page_html'), null);
				
				if( class_exists('Agdp_Maillog') ){
					$page_title = get_post_type_object(Agdp_Maillog::post_type)->labels->menu_name;
					$menu_slug = sprintf('edit.php?post_type=%s', Agdp_Maillog::post_type);
					add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, false, null);
				}
			
				if ( current_user_can( 'manage_network_plugins' ) ) {
					$page_title =  'Mise à jour de l\'extension';
					$menu_slug = $parent_slug . '-plugin-update';
					add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, 
						array(__CLASS__, 'agdp_plugin_update_page_html'), null);
				}
				
				if( current_user_can( 'manage_options' ) ){
					if( Agdp::get_option('can_generate_packages') )
						$page_title =  'Génération des packages';
					else
						$page_title =  'Packages';
					$menu_slug = $parent_slug . '-packages';
					add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, 
						array(__CLASS__, 'agdp_packages_page_html'), null);
				}
				
				$page_title =  'Arborescence du site';
				$menu_slug = $parent_slug . '-diagram';
				add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, 
					array(__CLASS__, 'agdp_diagram_page_html'), null);
			}
			
			//Menu Pages
			if( true ){
				$capability = 'moderate_comments';
				$parent_slug = 'edit.php?post_type=page';
				
				$page_title = 'Forums';
				$menu_slug = sprintf('edit.php?post_type=%s&orderby=%s&order=asc', Agdp_Forum::post_type, Agdp_Forum::tag);
				add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, false, null);
				
				$page_title = 'Ajouter un forum';
				$menu_slug = sprintf('post-new.php?post_type=%s&%s=1', Agdp_Forum::post_type, Agdp_Forum::tag);
				add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, false, null);
				
				foreach([
					// 'blog_presentation_page_id' => 'Présentation du site',
					// 'contact_page_id' => 'Contactez-nous',
					// 'newsletter_subscribe_page_id' => 'S\'abonner aux lettres-infos',
					// 'agenda_page_id' => 'Agenda et évènements',
					// 'new_agdpevent_page_id' => 'Nouvel évènement',
					// 'covoiturages_page_id' => 'Covoiturages',
					// 'new_covoiturage_page_id' => 'Nouveau covoiturage',
				] as $option => $option_label ){
					if( $page_id = Agdp::get_option( $option ) ){
						$page_title = sprintf('<span class="dashicons-before dashicons-admin-page"></span>&nbsp;%s'
							, $option_label );
						$menu_slug = sprintf('post.php?post=%d&action=edit', $page_id);
						add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, false, null);
					}
				}
			}
			
			//Menu Rapports
			if( true ){
				$parent_slug = sprintf('edit.php?post_type=%s', Agdp_Report::post_type) ;
				foreach ( get_posts( [ 'post_type' => Agdp_Report::post_type, 'post_parent' => '0' ] ) as $root_report ){
					$page_title =  $root_report->post_title;
					$menu_slug = sprintf('edit.php?post_type=%s&post_parent=%d', $root_report->post_type, $root_report->ID);
					add_submenu_page( $parent_slug, $page_title, 'Rapports ' . $page_title, $capability, $menu_slug);
				}
			}
			
			//Menu Annuaire / Contacts
			if( true ){
				$capability = 'moderate_comments';
				$parent_slug = sprintf('edit.php?post_type=%s', Agdp_Contact::post_type) ;
				
				$page_title = 'Importer';
				$menu_slug = $parent_slug . '-import';
				add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, 
					array(__CLASS__, 'agdp_contacts_import_page_html'), null);
			}
		}
		
		//Replace wpcf7 menu title
		global $menu;
		foreach($menu as $menu_index => $menu_data)
			if($menu_data[2] === 'wpcf7'){
				$menu[$menu_index][0]	 = 'Formulaires';
				break;
			}
	}	

	/**
	 * Adds edit posts link with awaiting moderation count bubble.
	 *
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
	 */
	public static function on_wp_admin_bar_posts_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		
		self::add_admin_bar_posts_menu( $wp_admin_bar, 'Agdp_Events' );
		
		if( Agdp_Covoiturage::is_managed() )
			self::add_admin_bar_posts_menu( $wp_admin_bar, 'Agdp_Covoiturages' );
		
	}
	/**
	 * Adds edit posts link with awaiting moderation count bubble.
	 *
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
	 */
	public static function add_admin_bar_posts_menu( $wp_admin_bar, $posts_type_class ) {
		if( ! ($pending_posts  = $posts_type_class::get_pending_posts()) )
			return;
		
		$postType = get_post_type_object($posts_type_class::post_type);
		
		$pending_text = sprintf(
			'%s %s%s en attente',
			count($pending_posts),
			$postType->labels->singular_name,
			count($pending_posts) > 1 ? 's' : '',
		);

		$icon   = '<span class="ab-icon" aria-hidden="true"></span>';
		$title  = '<span class="ab-label awaiting-mod posts-pending-count count-' . count($pending_posts) . '" aria-hidden="true">' . count($pending_posts) . '</span>';
		$title .= '<span class="screen-reader-text posts-in-moderation-text">' . $pending_text . '</span>';

		$wp_admin_bar->add_node(
			array(
				'id'    => $posts_type_class::post_type . 's',
				'title' => $icon . $title,
				'href'  => add_query_arg([
								'post_status' => 'pending',
								'post_type' => $posts_type_class::post_type
							], admin_url( 'edit.php')),
			)
		);
	}
	
	
	/**
	* top level menu:
	* callback functions
	*/
	public static function agdp_rights_page_html() {
		require_once(AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-edit-rights.php');
		Agdp_Admin_Edit_Rights::init();
		Agdp_Admin_Edit_Rights::agdp_rights_page_html();
	}
	
	
	/**
	* top level menu:
	* callback functions
	*/
	public static function agdp_contacts_import_page_html() {
		require_once(AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-contact-import.php');
		Agdp_Admin_Contact_Import::init();
		Agdp_Admin_Contact_Import::import_page_html();
	}
	
	
	/**
	* top level menu:
	* callback functions
	*/
	public static function agdp_diagram_page_html() {
		
		//DEBUG
		// self::test_code();
		
		/* $filename = 'C:\Arbeit\www\agenda-partage/wp-content/uploads/agdpmailbox/3621/2024/6/4F6A9F2D-1CAC-40E2-B66F-3EF5F07B1C9E@home-IMG_2336.jpeg';
		$filename = str_replace('\\', '/', $filename);
		if( file_exists($filename) ){
			echo sprintf('<h3>%s</h3><img src="%s">', $filename, upload_file_url( $filename ));
			
			$filename = image_reduce($filename, 200, 300, true );
			echo sprintf('<h3>%s</h3><img src="%s">', $filename, upload_file_url( $filename ));
		} */
		
		echo sprintf('<pre>%s</pre>', Agdp::blog_diagram_html());
	}
	
	/***********************
	* top level menu:
	* callback functions
	*/
	
	/**
	 * Update du plugin via GIT
	 */
	public static function agdp_plugin_update_page_html() {
		if( ! class_exists('Agdp_Admin_Update') ){
			require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-update.php' );
			Agdp_Admin_Update::init();
		}
		Agdp_Admin_Update::update_form();
		return;
	}
	
	/**
	 * Generate packages
	 */
	public static function agdp_packages_page_html() {
		if( ! class_exists('Agdp_Admin_Packages') ){
			require_once( AGDP_PLUGIN_DIR . "/admin/class.agdp-admin-packages.php");
			Agdp_Admin_Packages::init();
		}
		
		echo sprintf('<h1>Génération des packages</h1>' );
		
		echo Agdp_Admin_Packages::generate_form();
	}
	
	/**
	 * Import Action
	 */
	public static function agdp_import_page_html() {
		if( ! class_exists('Agdp_Admin_Posts_Import') ){
			require_once( AGDP_PLUGIN_DIR . "/admin/class.agdp-admin-posts-import.php");
			Agdp_Admin_Posts_Import::init();
		}
		return Agdp_Admin_Posts_Import::agdp_import_page_html();
	}
	
	/**
	 * dashboard
	 */
	public static function init_dashboard_widgets() {
	    self::remove_dashboard_widgets();
	    global $wp_meta_boxes;
		if( current_user_can('manage_options') ){
			add_meta_box( 'dashboard_my_comments',
				__('Arborescence du site', AGDP_TAG),
				array(__CLASS__, 'blog_diagram_cb'),
				'dashboard',
				'normal',
				'high' );
		}
	}

	/**
	 * Callback
	 */
	public static function blog_diagram_cb($post , $widget) {
		echo Agdp::blog_simple_diagram_html();
	}

	// TODO parametrage initiale pour chaque utilisateur
	public static function remove_dashboard_widgets() {
	    global $wp_meta_boxes, $current_user;
	    /*var_dump($wp_meta_boxes['dashboard']);*/
		if( ! in_array('administrator',(array)$current_user->roles) ) {
			remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
			remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
		}
		remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
		
		if( ! current_user_can('moderate_comments') )
			remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
	}

}

?>