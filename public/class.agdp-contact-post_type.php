<?php

/**
 * AgendaPartage -> Contact
 * Custom post type for WordPress.
 * Custom user role for WordPress.
 * 
 * Définition du Post Type 'contact'
 * Définition du rôle utilisateur 'contact'
 * A l'affichage d'un contact, le Content est remplacé par celui du contact Modèle
 * En Admin, le bloc d'édition du Content est masqué d'après la définition du Post type : le paramètre 'supports' qui ne contient pas 'editor', see Agdp_Admin_Contact::init_PostType_Supports
 *
 * Voir aussi Agdp_Admin_Contact
 */
class Agdp_Contact_Post_type {
	
	/**
	 * Contact post type.
	 */
	public static function register_post_type() {

		$labels = array(
			'name'                  => _x( 'Contacts', 'Post Type General Name', AGDP_TAG ),
			'singular_name'         => _x( 'Contact', 'Post Type Singular Name', AGDP_TAG ),
			'menu_name'             => __( 'Annuaire', AGDP_TAG ),
			'name_admin_bar'        => __( 'Contact', AGDP_TAG ),
			'archives'              => __( 'Contacts', AGDP_TAG ),
			'attributes'            => __( 'Attributs', AGDP_TAG ),
			'parent_item_colon'     => __( 'Contact parent:', AGDP_TAG ),
			'all_items'             => __( 'Tous les contacts', AGDP_TAG ),
			'add_new_item'          => __( 'Ajouter un contact', AGDP_TAG ),
			'add_new'               => __( 'Ajouter', AGDP_TAG ),
			'new_item'              => __( 'Nouveau contact', AGDP_TAG ),
			'edit_item'             => __( 'Modifier', AGDP_TAG ),
			'update_item'           => __( 'Mettre à jour', AGDP_TAG ),
			'view_item'             => __( 'Afficher', AGDP_TAG ),
			'view_items'            => __( 'Voir les contacts', AGDP_TAG ),
			'search_items'          => __( 'Rechercher des contacts', AGDP_TAG ),
			'items_list'            => __( 'Liste de contacts', AGDP_TAG ),
			'items_list_navigation' => __( 'Navigation dans la liste de contacts', AGDP_TAG ),
			'filter_items_list'     => __( 'Filtrer la liste des contacts', AGDP_TAG ),
		);
		$capabilities = self::post_type_capabilities();
		
		$is_managed = true;//Agdp_Contact::is_managed();
			
		$args = array(
			'label'                 => __( 'Contact', AGDP_TAG ),
			'description'           => __( 'Contact de l\'agenda partagé', AGDP_TAG ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'thumbnail', 'revisions' ),//, 'author', 'editor' see Agdp_Admin_Contact::init_PostType_Supports
			'taxonomies'            => array( Agdp_Contact::taxonomy_city
											, Agdp_Contact::taxonomy_category ),
			'hierarchical'          => false,
			'public'                => $is_managed,
			'show_ui'               => $is_managed,
			'show_in_menu'          => $is_managed,
			'menu_icon'				=> 'dashicons-' . Agdp_Contact::icon,
			'menu_position'         => 26,
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => true,
			'can_export'            => true,
			'has_archive'           => true,
			'delete_with_user'		=> false,
			'exclude_from_search'   => false,
			'publicly_queryable'    => true,
			//'capabilities'			=> $capabilities,
			'capability_type'       => 'post'
		);
		register_post_type( Agdp_Contact::post_type, $args );
		
		if(WP_POST_REVISIONS >= 0)
			add_filter( 'wp_revisions_to_keep', array(__CLASS__, 'wp_revisions_to_keep'), 10, 2);
		// add_filter( '_wp_post_revision_fields', array(__CLASS__, '_wp_post_revision_fields'), 10, 2);
	}
	public static function wp_revisions_to_keep( int $num, WP_Post $post ) {
		if($post->post_type === Agdp_Contact::post_type)
			return -1;
		return $num;
	}
	
	/**
	 * Register Custom Taxonomy
	 */
	public static function register_taxonomy_cont_city() {

		$labels = array(
			'name'                       => _x( 'Commune', 'Taxonomy General Name', AGDP_TAG ),
			'singular_name'              => _x( 'Commune', 'Taxonomy Singular Name', AGDP_TAG ),
			'menu_name'                  => __( 'Communes', AGDP_TAG ),
			'all_items'                  => __( 'Toutes les communes', AGDP_TAG ),
			'parent_item'                => __( 'Secteur parent', AGDP_TAG ),
			'parent_item_colon'          => __( 'Secteur parent:', AGDP_TAG ),
			'new_item_name'              => __( 'Nouvelle commune', AGDP_TAG ),
			'add_new_item'               => __( 'Ajouter une commune', AGDP_TAG ),
			'edit_item'                  => __( 'Modifier', AGDP_TAG ),
			'update_item'                => __( 'Mettre à jour', AGDP_TAG ),
			'view_item'                  => __( 'Afficher', AGDP_TAG ),
			'separate_items_with_commas' => __( 'Séparer les éléments par une virgule', AGDP_TAG ),
			'add_or_remove_items'        => __( 'Ajouter ou supprimer des éléments', AGDP_TAG ),
			'choose_from_most_used'      => __( 'Choisir la plus utilisée', AGDP_TAG ),
			'popular_items'              => __( 'Communes les plus utilisées', AGDP_TAG ),
			'search_items'               => __( 'Rechercher', AGDP_TAG ),
			'not_found'                  => __( 'Introuvable', AGDP_TAG ),
			'no_terms'                   => __( 'Aucune commune', AGDP_TAG ),
			'items_list'                 => __( 'Liste des communes', AGDP_TAG ),
			'items_list_navigation'      => __( 'Navigation parmi les communes', AGDP_TAG ),
		);
		$args = array(
			'labels'                     => $labels,
			'hierarchical'               => true,
			'public'                     => true,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
		);
		register_taxonomy( Agdp_Contact::taxonomy_city, array( Agdp_Contact::post_type ), $args );

	}
	
	/**
	 * Register Custom Taxonomy
	 */
	public static function register_taxonomy_cont_category() {

		$labels = array(
			'name'                       => _x( 'Catégorie', 'Taxonomy General Name', AGDP_TAG ),
			'singular_name'              => _x( 'Catégorie', 'Taxonomy Singular Name', AGDP_TAG ),
			'menu_name'                  => __( 'Catégories', AGDP_TAG ),
			'all_items'                  => __( 'Toutes les catégories', AGDP_TAG ),
			'parent_item'                => __( 'Secteur parent', AGDP_TAG ),
			'parent_item_colon'          => __( 'Secteur parent:', AGDP_TAG ),
			'new_item_name'              => __( 'Nouvelle catégorie', AGDP_TAG ),
			'add_new_item'               => __( 'Ajouter une catégorie', AGDP_TAG ),
			'edit_item'                  => __( 'Modifier', AGDP_TAG ),
			'update_item'                => __( 'Mettre à jour', AGDP_TAG ),
			'view_item'                  => __( 'Afficher', AGDP_TAG ),
			'separate_items_with_commas' => __( 'Séparer les éléments par une virgule', AGDP_TAG ),
			'add_or_remove_items'        => __( 'Ajouter ou supprimer des éléments', AGDP_TAG ),
			'choose_from_most_used'      => __( 'Choisir la plus utilisée', AGDP_TAG ),
			'popular_items'              => __( 'Catégories les plus utilisées', AGDP_TAG ),
			'search_items'               => __( 'Rechercher', AGDP_TAG ),
			'not_found'                  => __( 'Introuvable', AGDP_TAG ),
			'no_terms'                   => __( 'Aucune catégorie', AGDP_TAG ),
			'items_list'                 => __( 'Liste des catégories', AGDP_TAG ),
			'items_list_navigation'      => __( 'Navigation parmi les catégories', AGDP_TAG ),
		);
		$args = array(
			'labels'                     => $labels,
			'hierarchical'               => true,
			'public'                     => true,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
		);
		register_taxonomy( Agdp_Contact::taxonomy_category, array( Agdp_Contact::post_type ), $args );

	}
	
	private static function post_type_capabilities(){
		return array(
			'create_contacts' => 'create_posts',
			'edit_contacts' => 'edit_posts',
			'edit_others_contacts' => 'edit_others_posts',
			'publish_contacts' => 'publish_posts',
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
			// 'edit_contacts' => true,
			// 'wpcf7_read_contact_forms' => false,

			// 'publish_contacts' => true,
			// 'delete_posts' => true,
			// 'delete_published_posts' => true,
			// 'edit_published_posts' => true,
			// 'publish_posts' => true,
			// 'upload_files ' => true,
			// 'create_posts' => false,
			// 'create_contacts' => false,
		// );
		// add_role( Agdp_Contact::post_type, __('Contact', AGDP_TAG ),  $capabilities);
	}
	
	public static function get_all_cities($array_keys_field = 'term_id', $query_args = []){
		return Agdp_Covoiturage::get_all_terms(Agdp_Covoiturage::taxonomy_city, $array_keys_field, $query_args);
	}
	public static function get_all_categories($array_keys_field = 'term_id', $query_args = []){
		return Agdp_Contact::get_all_terms(Agdp_Contact::taxonomy_category, $array_keys_field, $query_args);
	}
	
	/**
	 * Taxonomies
	 */
	public static function get_taxonomies ( $except = false ){
		$taxonomies = [];
		
		$tax_name = Agdp_Contact::taxonomy_city;
		if( ! $except || strpos($except, $tax_name ) === false)
			$taxonomies[$tax_name] = array(
				'name' => $tax_name,
				'input' => 'cont-cities',
				'filter' => 'cities',
				'label' => 'Commune',
				'plural' => 'Commune',
				'all_label' => '(toutes)',
				'none_label' => '(sans commune)'
			);
		
		$tax_name = Agdp_Contact::taxonomy_category;
		if( ! $except || strpos($except, $tax_name ) === false)
			$taxonomies[$tax_name] = array(
				'name' => $tax_name,
				'input' => 'cont-categories',
				'filter' => 'categories',
				'label' => 'Catégorie',
				'plural' => 'Catégories',
				'all_label' => '(toutes)',
				'none_label' => '(sans catégorie)'
			);
		
		return $taxonomies;
	}
	
	/**
	 * Nom abusif. Teste si le paramètre du terme de diffusion ("la-lettre-info, par exemple) est définie
	 */
	public static function is_diffusion_managed(){
		return false;
	}
	
	/**
	 * Retourne les termes d'une taxonomie avec leurs alternatives syntaxiques pour un like.
	 * Utilisée pour chercher les catégories dans la meta_value 'cont-localisation'.
	 */
	public static function get_terms_like($tax_name, $term_ids){
		$like = [];
		foreach( get_terms(array(
				'taxonomy' => $tax_name,
				'hide_empty' => false,
		)) as $term){
			if( in_array($term->term_id, $term_ids)){
				$like[] = $term->name;
			}
		}
		return $like;
	}
}
