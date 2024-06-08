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
			if( Agdp::get_option('covoiturage_managed')
			 && ( $post_id = Agdp::get_option($option) )){
				$parent_slug = sprintf('edit.php?post_type=%s', Agdp_Newsletter::post_type) ;
				$page_title =  Agdp::get_option_label($option);
				$menu_slug = sprintf('post.php?post=%s&action=edit', $post_id);
				add_submenu_page( $parent_slug, $page_title, 'Covoiturages à venir', $capability, $menu_slug);
			}
			
			//Menu Evènements
			$parent_slug = sprintf('edit.php?post_type=%s', Agdp_Evenement::post_type) ;
			$page_title =  'Evènements en attente de validation';
			$menu_slug = $parent_slug . '&post_status=pending';
			add_submenu_page( $parent_slug, $page_title, 'En attente', $capability, $menu_slug, '', 1);
			
			//Menus Covoiturages
			$parent_slug = sprintf('edit.php?post_type=%s', Agdp_Covoiturage::post_type) ;
			$page_title =  'Covoiturages en attente de validation';
			$menu_slug = $parent_slug . '&post_status=pending';
			add_submenu_page( $parent_slug, $page_title, 'En attente', $capability, $menu_slug, '', 1);
			
			//Menu Agenda partagé
			$parent_slug = AGDP_TAG;
			$page_title = get_post_type_object(Agdp_Mailbox::post_type)->labels->menu_name;
			$menu_slug = sprintf('edit.php?post_type=%s', Agdp_Mailbox::post_type);
			add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, false, null);
			
			$parent_slug = AGDP_TAG;
			$page_title =  'Règles de publications';
			$menu_slug = $parent_slug . '-rights';
			add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, 
				array('Agdp_Admin_Options', 'agdp_rights_page_html'), null);
			
			if( class_exists('Agdp_Maillog') ){
				$parent_slug = AGDP_TAG;
				$page_title = get_post_type_object(Agdp_Maillog::post_type)->labels->menu_name;
				$menu_slug = sprintf('edit.php?post_type=%s', Agdp_Maillog::post_type);
				add_submenu_page( $parent_slug, $page_title, $page_title, $capability, $menu_slug, false, null);
			}
			
			$parent_slug = AGDP_TAG;
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
		
		self::add_admin_bar_posts_menu( $wp_admin_bar, 'Agdp_Evenement' );
		
		if( Agdp::get_option('covoiturage_managed') )
			self::add_admin_bar_posts_menu( $wp_admin_bar, 'Agdp_Covoiturage' );
		
	}
	/**
	 * Adds edit posts link with awaiting moderation count bubble.
	 *
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
	 */
	public static function add_admin_bar_posts_menu( $wp_admin_bar, $post_type_class ) {
		if( ! ($pending_posts  = $post_type_class::get_pending_posts()) )
			return;
		
		$postType = get_post_type_object($post_type_class::post_type);
    
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
				'id'    => $post_type_class::post_type . 's',
				'title' => $icon . $title,
				'href'  => add_query_arg([
								'post_status' => 'pending',
								'post_type' => $post_type_class::post_type
							], admin_url( 'edit.php')),
			)
		);
	}
}

?>