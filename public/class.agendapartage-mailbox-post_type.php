<?php

/**
 * AgendaPartage -> Mailbox
 * Custom post type for WordPress.
 * Custom user role for WordPress.
 * 
 * Définition du Post Type 'agdpmailbox'
 * Définition du rôle utilisateur 'agdpmailbox'
 *
 * Voir aussi AgendaPartage_Admin_Mailbox
 */
class AgendaPartage_Mailbox_Post_type {
	
	/**
	 * Mailbox post type.
	 */
	public static function register_post_type() {

		$labels = array(
			'name'                  => _x( 'Boîtes e-mails', 'Post Type General Name', AGDP_TAG ),
			'singular_name'         => _x( 'Boîte e-mails', 'Post Type Singular Name', AGDP_TAG ),
			'menu_name'             => __( 'Boîtes e-mails', AGDP_TAG ),
			'name_admin_bar'        => __( 'Boîte e-mails', AGDP_TAG ),
			'archives'              => __( 'Boîtes e-mails', AGDP_TAG ),
			'attributes'            => __( 'Attributs', AGDP_TAG ),
			'parent_item_colon'     => __( 'Boîte e-mails parente:', AGDP_TAG ),
			'all_items'             => __( 'Tous les boîtes', AGDP_TAG ),
			'add_new_item'          => __( 'Ajouter une boîte', AGDP_TAG ),
			'add_new'               => __( 'Ajouter', AGDP_TAG ),
			'new_item'              => __( 'Nouvelle boîte e-mails', AGDP_TAG ),
			'edit_item'             => __( 'Modifier', AGDP_TAG ),
			'update_item'           => __( 'Mettre à jour', AGDP_TAG ),
			'view_item'             => __( 'Afficher', AGDP_TAG ),
			'view_items'            => __( 'Voir les boîtes e-mails', AGDP_TAG ),
			'search_items'          => __( 'Rechercher des boîtes e-mails', AGDP_TAG ),
			'items_list'            => __( 'Liste de boîtes e-mails', AGDP_TAG ),
			'items_list_navigation' => __( 'Navigation dans la liste de boîtes e-mails', AGDP_TAG ),
			'filter_items_list'     => __( 'Filtrer la liste des boîtes e-mails', AGDP_TAG ),
		);
		$capabilities = self::post_type_capabilities();
		$args = array(
			'label'                 => __( 'Boîte e-mails', AGDP_TAG ),
			'description'           => __( 'Boîte e-mails  dans l\'agenda partagé', AGDP_TAG ),
			'labels'                => $labels,
			'supports'              => array( 'title' ),
			'taxonomies'            => array(  ),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => false,
			// 'menu_icon'				=> 'dashicons-email',
			// 'menu_position'         => 26,
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => true,
			'delete_with_user'		=> false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => true,
			//'capabilities'			=> $capabilities,
			'capability_type'       => 'page'
		);
		register_post_type( AgendaPartage_Mailbox::post_type, $args );
		
	}
	
	private static function post_type_capabilities(){
		return array(
			'create_agdpmailboxes' => 'create_posts',
			'edit_agdpmailboxes' => 'edit_posts',
			'edit_others_agdpmailboxes' => 'edit_others_posts',
			'publish_agdpmailboxes' => 'publish_posts',
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
				// 'edit_agdpmailboxes' => true,
				// 'wpcf7_read_contact_forms' => false,

				// 'publish_agdpmailboxes' => true,
				// 'delete_posts' => true,
				// 'delete_published_posts' => true,
				// 'edit_published_posts' => true,
				// 'publish_posts' => true,
				// 'upload_files ' => true,
				// 'create_posts' => false,
				// 'create_agdpmailboxes' => false,
			// );
			// add_role( AgendaPartage_Mailbox::post_type, __('Mailbox', AGDP_TAG ),  $capabilities);
	}
}
