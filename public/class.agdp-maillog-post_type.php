<?php

/**
 * AgendaPartage -> Maillog
 * Custom post type for WordPress.
 * Custom user role for WordPress.
 * 
 * Définition du Post Type 'agdpmaillog'
 * Définition du rôle utilisateur 'agdpmaillog'
 *
 * Voir aussi Agdp_Admin_Maillog
 */
class Agdp_Maillog_Post_type {
	
	/**
	 * Maillog post type.
	 */
	public static function register_post_type() {

		$labels = array(
			'name'                  => _x( 'Traces mails', 'Post Type General Name', AGDP_TAG ),
			'singular_name'         => _x( 'Trace mail', 'Post Type Singular Name', AGDP_TAG ),
			'menu_name'             => __( 'Traces mails', AGDP_TAG ),
			'name_admin_bar'        => __( 'Trace mails', AGDP_TAG ),
			'archives'              => __( 'Traces mails', AGDP_TAG ),
			'attributes'            => __( 'Attributs', AGDP_TAG ),
			'parent_item_colon'     => __( 'Trace mail parent:', AGDP_TAG ),
			'all_items'             => __( 'Toutes les traces mails', AGDP_TAG ),
			'add_new_item'          => __( 'Ajouter une trace mails', AGDP_TAG ),
			'add_new'               => __( 'Ajouter', AGDP_TAG ),
			'new_item'              => __( 'Nouvelle trace mail', AGDP_TAG ),
			'edit_item'             => __( 'Modifier', AGDP_TAG ),
			'update_item'           => __( 'Mettre à jour', AGDP_TAG ),
			'view_item'             => __( 'Afficher', AGDP_TAG ),
			'view_items'            => __( 'Voir les traces mails', AGDP_TAG ),
			'search_items'          => __( 'Rechercher des traces mails', AGDP_TAG ),
			'items_list'            => __( 'Liste de traces mails', AGDP_TAG ),
			'items_list_navigation' => __( 'Navigation dans la liste de traces mails', AGDP_TAG ),
			'filter_items_list'     => __( 'Filtrer la liste des traces mails', AGDP_TAG ),
		);
		$capabilities = self::post_type_capabilities();
		$args = array(
			'label'                 => __( 'Trace mail', AGDP_TAG ),
			'description'           => __( 'Trace mail de l\'agenda partagé', AGDP_TAG ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', 'thumbnail', 'revisions' ),//, 'author', 'editor' see Agdp_Admin_Maillog::init_PostType_Supports
			'taxonomies'            => array(  ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => false,
			'menu_icon'				=> 'dashicons-database',
			'menu_position'         => 29,
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
		register_post_type( Agdp_Maillog::post_type, $args );
		
	}
	
	private static function post_type_capabilities(){
		return array(
			'create_agdpmaillogs' => 'create_posts',
			'edit_agdpmaillogs' => 'edit_posts',
			'edit_others_agdpmaillogs' => 'edit_others_posts',
			'publish_agdpmaillogs' => 'publish_posts',
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
			// 'edit_agdpmaillogs' => true,
			// 'wpcf7_read_contact_forms' => false,

			// 'publish_agdpmaillogs' => true,
			// 'delete_posts' => true,
			// 'delete_published_posts' => true,
			// 'edit_published_posts' => true,
			// 'publish_posts' => true,
			// 'upload_files ' => true,
			// 'create_posts' => false,
			// 'create_agdpmaillogs' => false,
		// );
		// add_role( Agdp_Maillog::post_type, __('Trace mail', AGDP_TAG ),  $capabilities);
	}
}
