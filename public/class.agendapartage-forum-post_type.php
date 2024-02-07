<?php

/**
 * AgendaPartage -> Forum
 * Custom post type for WordPress.
 * Custom user role for WordPress.
 * 
 * Définition du Post Type 'agdpforum'
 * Définition du rôle utilisateur 'agdpforum'
 *
 * Voir aussi AgendaPartage_Admin_Forum
 */
class AgendaPartage_Forum_Post_type {
	
	/**
	 * Forum post type.
	 */
	public static function register_post_type() {

		$labels = array(
			'name'                  => _x( 'Forums', 'Post Type General Name', AGDP_TAG ),
			'singular_name'         => _x( 'Forum', 'Post Type Singular Name', AGDP_TAG ),
			'menu_name'             => __( 'Forums', AGDP_TAG ),
			'name_admin_bar'        => __( 'Forum', AGDP_TAG ),
			'archives'              => __( 'Forums', AGDP_TAG ),
			'attributes'            => __( 'Attributs', AGDP_TAG ),
			'parent_item_colon'     => __( 'Forum parent:', AGDP_TAG ),
			'all_items'             => __( 'Toutes les forums', AGDP_TAG ),
			'add_new_item'          => __( 'Ajouter une forum', AGDP_TAG ),
			'add_new'               => __( 'Ajouter', AGDP_TAG ),
			'new_item'              => __( 'Nouveau forum', AGDP_TAG ),
			'edit_item'             => __( 'Modifier', AGDP_TAG ),
			'update_item'           => __( 'Mettre à jour', AGDP_TAG ),
			'view_item'             => __( 'Afficher', AGDP_TAG ),
			'view_items'            => __( 'Voir les forums', AGDP_TAG ),
			'search_items'          => __( 'Rechercher des forums', AGDP_TAG ),
			'items_list'            => __( 'Liste de forums', AGDP_TAG ),
			'items_list_navigation' => __( 'Navigation dans la liste de forums', AGDP_TAG ),
			'filter_items_list'     => __( 'Filtrer la liste des forums', AGDP_TAG ),
		);
		$capabilities = self::post_type_capabilities();
		$args = array(
			'label'                 => __( 'Forum', AGDP_TAG ),
			'description'           => __( 'Forum de l\'agenda partagé', AGDP_TAG ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', 'thumbnail', 'revisions' ),//, 'author', 'editor' see AgendaPartage_Admin_Forum::init_PostType_Supports
			'taxonomies'            => array(  ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_icon'				=> 'dashicons-buddicons-forums',
			'menu_position'         => 25,
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => true,
			'can_export'            => true,
			'has_archive'           => true,
			'delete_with_user'		=> false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => true,
			//'capabilities'			=> $capabilities,
			'capability_type'       => 'page'
		);
		register_post_type( AgendaPartage_Forum::post_type, $args );
		
	}
	
	private static function post_type_capabilities(){
		return array(
			'create_agdpforums' => 'create_posts',
			'edit_agdpforums' => 'edit_posts',
			'edit_others_agdpforums' => 'edit_others_posts',
			'publish_agdpforums' => 'publish_posts',
		);
	}

	/**
	 *
	 */
	public static function register_user_role(){
		return;
		
			// $capabilities = array(
				// 'read' => true,
				// 'edit_posts' => true,
				// 'edit_agdpforums' => true,
				// 'wpcf7_read_contact_forms' => false,

				// 'publish_agdpforums' => true,
				// 'delete_posts' => true,
				// 'delete_published_posts' => true,
				// 'edit_published_posts' => true,
				// 'publish_posts' => true,
				// 'upload_files ' => true,
				// 'create_posts' => false,
				// 'create_agdpforums' => false,
			// );
			// add_role( AgendaPartage_Forum::post_type, __('Forum', AGDP_TAG ),  $capabilities);
	}
}
