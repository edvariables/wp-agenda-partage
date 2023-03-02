<?php

/**
 * AgendaPartage -> Newsletter
 * Custom post type for WordPress.
 * Custom user role for WordPress.
 * 
 * Définition du Post Type 'agdpnl'
 * Définition du rôle utilisateur 'agdpnl'
 * A l'affichage d'un Lettre-info, le Content est remplacé par celui de l'Lettre-info Modèle
 * En Admin, le bloc d'édition du Content est masqué d'après la définition du Post type : le paramètre 'supports' qui ne contient pas 'editor', see AgendaPartage_Admin_Newsletter::init_PostType_Supports
 *
 * Voir aussi AgendaPartage_Admin_Newsletter
 */
class AgendaPartage_Newsletter_Post_type {
	
	/**
	 * Newsletter post type.
	 */
	public static function register_post_type() {

		$labels = array(
			'name'                  => _x( 'Lettres-info', 'Post Type General Name', AGDP_TAG ),
			'singular_name'         => _x( 'Lettre-info', 'Post Type Singular Name', AGDP_TAG ),
			'menu_name'             => __( 'Lettres-info', AGDP_TAG ),
			'name_admin_bar'        => __( 'Lettre-info', AGDP_TAG ),
			'archives'              => __( 'Lettres-info', AGDP_TAG ),
			'attributes'            => __( 'Attributs', AGDP_TAG ),
			'parent_item_colon'     => __( 'Lettre-info parent:', AGDP_TAG ),
			'all_items'             => __( 'Toutes les lettres-info', AGDP_TAG ),
			'add_new_item'          => __( 'Ajouter une lettre-info', AGDP_TAG ),
			'add_new'               => __( 'Ajouter', AGDP_TAG ),
			'new_item'              => __( 'Nouvelle lettre-info', AGDP_TAG ),
			'edit_item'             => __( 'Modifier', AGDP_TAG ),
			'update_item'           => __( 'Mettre à jour', AGDP_TAG ),
			'view_item'             => __( 'Afficher', AGDP_TAG ),
			'view_items'            => __( 'Voir les lettres-info', AGDP_TAG ),
			'search_items'          => __( 'Rechercher des lettres-info', AGDP_TAG ),
			'items_list'            => __( 'Liste de lettres-info', AGDP_TAG ),
			'items_list_navigation' => __( 'Navigation dans la liste de lettres-info', AGDP_TAG ),
			'filter_items_list'     => __( 'Filtrer la liste des lettres-info', AGDP_TAG ),
		);
		$capabilities = self::post_type_capabilities();
		$args = array(
			'label'                 => __( 'Lettre-info', AGDP_TAG ),
			'description'           => __( 'Lettre-info de l\'agenda partagé', AGDP_TAG ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', 'thumbnail', 'revisions' ),//, 'author', 'editor' see AgendaPartage_Admin_Newsletter::init_PostType_Supports
			'taxonomies'            => array(  ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_icon'				=> 'dashicons-email-alt',
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
		register_post_type( AgendaPartage_Newsletter::post_type, $args );
		
	}
	
	private static function post_type_capabilities(){
		return array(
			'create_agdpnls' => 'create_posts',
			'edit_agdpnls' => 'edit_posts',
			'edit_others_agdpnls' => 'edit_others_posts',
			'publish_agdpnls' => 'publish_posts',
		);
	}

	/**
	 *
	 */
	public static function register_user_role(){
		return;
		
		$capabilities = array(
			'read' => true,
			'edit_posts' => true,
			'edit_agdpnls' => true,
			'wpcf7_read_contact_forms' => false,

			'publish_agdpnls' => true,
			'delete_posts' => true,
			'delete_published_posts' => true,
			'edit_published_posts' => true,
			'publish_posts' => true,
			'upload_files ' => true,
			'create_posts' => false,
			'create_agdpnls' => false,
		);
		add_role( AgendaPartage_Newsletter::post_type, __('Lettre-info', AGDP_TAG ),  $capabilities);
	}
}
