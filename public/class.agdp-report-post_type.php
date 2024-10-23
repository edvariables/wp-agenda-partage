<?php

/**
 * AgendaPartage -> Report
 * Custom post type for WordPress.
 * Custom user role for WordPress.
 * 
 * Définition du Post Type 'agdpreport'
 * Définition du rôle utilisateur 'agdpreport'
 *
 * Voir aussi Agdp_Admin_Report
 */
class Agdp_Report_Post_type {
	
	/**
	 * Report post type.
	 */
	public static function register_post_type() {

		$labels = array(
			'name'                  => _x( 'Rapports', 'Post Type General Name', AGDP_TAG ),
			'singular_name'         => _x( 'Rapport', 'Post Type Singular Name', AGDP_TAG ),
			'menu_name'             => __( 'Rapports', AGDP_TAG ),
			'name_admin_bar'        => __( 'Rapport', AGDP_TAG ),
			'archives'              => __( 'Rapports', AGDP_TAG ),
			'attributes'            => __( 'Attributs', AGDP_TAG ),
			'parent_item_colon'     => __( 'Rapport parente:', AGDP_TAG ),
			'all_items'             => __( 'Tous les rapports', AGDP_TAG ),
			'add_new_item'          => __( 'Ajouter un rapport', AGDP_TAG ),
			'add_new'               => __( 'Ajouter', AGDP_TAG ),
			'new_item'              => __( 'Nouvelle rapport', AGDP_TAG ),
			'edit_item'             => __( 'Modifier', AGDP_TAG ),
			'update_item'           => __( 'Mettre à jour', AGDP_TAG ),
			'view_item'             => __( 'Afficher', AGDP_TAG ),
			'view_items'            => __( 'Voir les rapports', AGDP_TAG ),
			'search_items'          => __( 'Rechercher des rapports', AGDP_TAG ),
			'items_list'            => __( 'Liste de rapports', AGDP_TAG ),
			'items_list_navigation' => __( 'Navigation dans la liste de rapports', AGDP_TAG ),
			'filter_items_list'     => __( 'Filtrer la liste des rapports', AGDP_TAG ),
		);
		$capabilities = self::post_type_capabilities();
		$args = array(
			'label'                 => __( 'Rapport', AGDP_TAG ),
			'description'           => __( 'Rapport  dans l\'agenda partagé', AGDP_TAG ),
			'labels'                => $labels,
			'supports'              => array( 'title' ),
			'taxonomies'            => array(  ),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => false,
			'menu_icon'				=> 'dashicons-media-spreadsheet',
			'menu_position'         => 26,
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => true,
			'delete_with_user'		=> false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => true,
			//'capabilities'			=> $capabilities,
			'capability_type'       => 'page'
		);
		register_post_type( Agdp_Report::post_type, $args );
		
	}
	
	private static function post_type_capabilities(){
		return array(
			'create_agdpreports' => 'create_posts',
			'edit_agdpreports' => 'edit_posts',
			'edit_others_agdpreports' => 'edit_others_posts',
			'publish_agdpreports' => 'publish_posts',
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
				// 'edit_agdpreports' => true,
				// 'wpcf7_read_contact_forms' => false,

				// 'publish_agdpreports' => true,
				// 'delete_posts' => true,
				// 'delete_published_posts' => true,
				// 'edit_published_posts' => true,
				// 'publish_posts' => true,
				// 'upload_files ' => true,
				// 'create_posts' => false,
				// 'create_agdpreports' => false,
			// );
			// add_role( Agdp_Report::post_type, __('Report', AGDP_TAG ),  $capabilities);
	}
}
