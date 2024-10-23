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

		if(! current_user_can('manage_options')){

		    $user = wp_get_current_user();
		    $roles = ( array ) $user->roles;
		    if(!in_array('agdpevent', $roles)) {
				remove_menu_page('posts');//TODO
				remove_menu_page('wpcf7');
			}
		}
		else {
			$capability = 'manage_options';
			
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
				$type_title = get_post_type_object($post_type)->labels->menu_name;
				$parent_slug = sprintf('edit.php?post_type=%s', $post_type) ;
				$page_title =  $type_title . ' en attente de validation';
				$menu_slug = $parent_slug . '&post_status=pending';
				add_submenu_page( $parent_slug, $page_title, 'En attente', $capability, $menu_slug, '', 1);
			
				$parent_slug = sprintf('edit.php?post_type=%s', $post_type) ;
				$page_title =  $type_title . ' obsolètes d\'un mois';
				$menu_slug = $parent_slug . '&date_max=' . wp_date('Y-m-d', strtotime('-1 Month'));
				add_submenu_page( $parent_slug, $page_title, 'Obsolètes', $capability, $menu_slug, '', 2);
			}
			
			//Menu Agenda partagé
			//Mailboxes
			$parent_slug = AGDP_TAG;			
			$page_title = get_post_type_object(Agdp_Mailbox::post_type)->labels->menu_name;
			$menu_slug = sprintf('edit.php?post_type=%s', Agdp_Mailbox::post_type);
			add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, false, null);
			
			$parent_slug = AGDP_TAG;
			$page_title =  'Règles de publications';
			$menu_slug = $parent_slug . '-rights';
			add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, 
				array('Agdp_Admin_Options', 'agdp_rights_page_html'), null);
			
			//Report
			$parent_slug = AGDP_TAG;			
			$page_title = get_post_type_object(Agdp_Report::post_type)->labels->menu_name;
			$menu_slug = sprintf('edit.php?post_type=%s', Agdp_Report::post_type);
			add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, false, null);
			
			if( class_exists('Agdp_Maillog') ){
				$parent_slug = AGDP_TAG;
				$page_title = get_post_type_object(Agdp_Maillog::post_type)->labels->menu_name;
				$menu_slug = sprintf('edit.php?post_type=%s', Agdp_Maillog::post_type);
				add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, false, null);
			}
		
			$parent_slug = AGDP_TAG;
			if ( current_user_can( 'manage_network_plugins' ) ) {
				$page_title =  'Mise à jour';
				$menu_slug = $parent_slug . '-git-update';
				add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, 
					array('Agdp_Admin_Options', 'agdp_git_update_page_html'), null);
			}
			
			$page_title =  'Arborescence du site';
			$menu_slug = $parent_slug . '-diagram';
			add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, 
				array('Agdp_Admin_Options', 'agdp_diagram_page_html'), null);
			
			//Menu Pages
			$capability = 'moderate_comments';
			$parent_slug = 'edit.php?post_type=page';
			
			$page_title = 'Forums';
			$menu_slug = sprintf('edit.php?post_type=%s&orderby=%s&order=asc', Agdp_Forum::post_type, Agdp_Forum::tag);
			add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, false, null);
			
			$page_title = 'Ajouter un forum';
			$menu_slug = sprintf('post-new.php?post_type=%s&%s=1', Agdp_Forum::post_type, Agdp_Forum::tag);
			add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, false, null);
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
		
		self::add_admin_bar_posts_menu( $wp_admin_bar, 'Agdp_Evenements' );
		
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