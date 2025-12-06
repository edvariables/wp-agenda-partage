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
			'parent_item_colon'     => __( 'Rapport parent:', AGDP_TAG ),
			'all_items'             => __( 'Tous les rapports', AGDP_TAG ),
			'add_new_item'          => __( 'Ajouter un rapport', AGDP_TAG ),
			'add_new'               => __( 'Ajouter', AGDP_TAG ),
			'new_item'              => __( 'Nouveau rapport', AGDP_TAG ),
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
			'description'           => __( 'Rapport par requête', AGDP_TAG ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'page-attributes' ),
			'taxonomies'            => array(  ),
			'hierarchical'          => true,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => true,
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
	/**
	 * Register Custom Taxonomy
	 */
	public static function register_taxonomy_report_style() {

		$labels = array(
			'name'                       => _x( 'Style de rapport', 'Taxonomy General Name', AGDP_TAG ),
			'singular_name'              => _x( 'Style de rapport', 'Taxonomy Singular Name', AGDP_TAG ),
			'menu_name'                  => __( 'Styles de rapport', AGDP_TAG ),
			'all_items'                  => __( 'Tous les styles', AGDP_TAG ),
			'parent_item'                => __( 'Style parent', AGDP_TAG ),
			'parent_item_colon'          => __( 'Style parent:', AGDP_TAG ),
			'new_item_name'              => __( 'Nouveau style', AGDP_TAG ),
			'add_new_item'               => __( 'Ajouter un nouveau style', AGDP_TAG ),
			'edit_item'                  => __( 'Modifier', AGDP_TAG ),
			'update_item'                => __( 'Mettre à jour', AGDP_TAG ),
			'view_item'                  => __( 'Afficher', AGDP_TAG ),
			'separate_items_with_commas' => __( 'Séparer les éléments par une virgule', AGDP_TAG ),
			'add_or_remove_items'        => __( 'Ajouter ou supprimer des éléments', AGDP_TAG ),
			'choose_from_most_used'      => __( 'Choisir le plus utilisé', AGDP_TAG ),
			'popular_items'              => __( 'Styles populaires', AGDP_TAG ),
			'search_items'               => __( 'Rechercher', AGDP_TAG ),
			'not_found'                  => __( 'Introuvable', AGDP_TAG ),
			'no_terms'                   => __( 'Aucune style', AGDP_TAG ),
			'items_list'                 => __( 'Liste des styles', AGDP_TAG ),
			'items_list_navigation'      => __( 'Navigation parmi les styles', AGDP_TAG ),
		);
		$args = array(
			'labels'                     => $labels,
			'hierarchical'               => true,
			'public'                     => true,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => false,
			'show_tagcloud'              => false,
		);
		register_taxonomy( Agdp_Report::taxonomy_report_style, array( Agdp_Report::post_type ), $args );

	}
	
	/**
	 * Register Custom Taxonomy
	 */
	public static function register_taxonomy_sql_function() {

		$labels = array(
			'name'                       => _x( 'Fonction SQL', 'Taxonomy General Name', AGDP_TAG ),
			'singular_name'              => _x( 'Fonction SQL', 'Taxonomy Singular Name', AGDP_TAG ),
			'menu_name'                  => __( 'Fonctions SQL', AGDP_TAG ),
			'all_items'                  => __( 'Toutes les fonctions', AGDP_TAG ),
			'parent_item'                => __( 'Fonction parente', AGDP_TAG ),
			'parent_item_colon'          => __( 'Fonction parente:', AGDP_TAG ),
			'new_item_name'              => __( 'Nouvelle fonction SQL', AGDP_TAG ),
			'add_new_item'               => __( 'Ajouter une nouvelle fonction SQL', AGDP_TAG ),
			'edit_item'                  => __( 'Modifier', AGDP_TAG ),
			'update_item'                => __( 'Mettre à jour', AGDP_TAG ),
			'view_item'                  => __( 'Afficher', AGDP_TAG ),
			'separate_items_with_commas' => __( 'Séparer les éléments par une virgule', AGDP_TAG ),
			'add_or_remove_items'        => __( 'Ajouter ou supprimer des éléments', AGDP_TAG ),
			'choose_from_most_used'      => __( 'Choisir le plus utilisé', AGDP_TAG ),
			'popular_items'              => __( 'Fonctions populaires', AGDP_TAG ),
			'search_items'               => __( 'Rechercher', AGDP_TAG ),
			'not_found'                  => __( 'Introuvable', AGDP_TAG ),
			'no_terms'                   => __( 'Aucune fonction', AGDP_TAG ),
			'items_list'                 => __( 'Liste des fonctions', AGDP_TAG ),
			'items_list_navigation'      => __( 'Navigation parmi les fonctions', AGDP_TAG ),
		);
		$args = array(
			'labels'                     => $labels,
			'hierarchical'               => false,
			'public'                     => false,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => false,
			'show_tagcloud'              => false,
		);
		register_taxonomy( Agdp_Report::taxonomy_sql_function, array( Agdp_Report::post_type ), $args );

	}
	
	/**
	 * Taxonomies
	 */
	public static function get_taxonomies ( $except = false ){
		$taxonomies = [];
		
		$tax_name = Agdp_Report::taxonomy_report_style;
		if( ! $except || strpos($except, $tax_name ) === false)
			$taxonomies[$tax_name] = array(
				'name' => $tax_name,
				'input' => $tax_name,
				'filter' => 'styles',
				'label' => 'Style',
				'plural' => 'Styles',
				'all_label' => '(tous)',
				'none_label' => '(sans style)'
			);
		
		$tax_name = Agdp_Report::taxonomy_sql_function;
		if( ! $except || strpos($except, $tax_name ) === false)
			$taxonomies[$tax_name] = array(
				'name' => $tax_name,
				'input' => $tax_name,
				'filter' => 'fonctions SQL',
				'label' => 'Fonction SQL',
				'plural' => 'Fonctions SQL',
				'all_label' => '(toutes)',
				'none_label' => '(sans fonction SQL)'
			);
				
		return $taxonomies;
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
