<?php

/**
 * AgendaPartage -> Newsletter
 * Custom post type for WordPress.
 * Custom user role for WordPress.
 * 
 * Définition du Post Type 'agdpnl'
 * Définition du rôle utilisateur 'agdpnl'
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
		
			// $capabilities = array(
				// 'read' => true,
				// 'edit_posts' => true,
				// 'edit_agdpnls' => true,
				// 'wpcf7_read_contact_forms' => false,

				// 'publish_agdpnls' => true,
				// 'delete_posts' => true,
				// 'delete_published_posts' => true,
				// 'edit_published_posts' => true,
				// 'publish_posts' => true,
				// 'upload_files ' => true,
				// 'create_posts' => false,
				// 'create_agdpnls' => false,
			// );
			// add_role( AgendaPartage_Newsletter::post_type, __('Lettre-info', AGDP_TAG ),  $capabilities);
	}
	
	/**
	 * Register Custom Taxonomy
	 */
	public static function register_taxonomy_period() {

		$labels = array(
			'name'                       => _x( 'Période', 'Taxonomy General Name', AGDP_TAG ),
			'singular_name'              => _x( 'Période', 'Taxonomy Singular Name', AGDP_TAG ),
			'menu_name'                  => __( 'Périodes', AGDP_TAG ),
			'all_items'                  => __( 'Toutes les périodes', AGDP_TAG ),
			'parent_item'                => __( 'Secteur parent', AGDP_TAG ),
			'parent_item_colon'          => __( 'Secteur parent:', AGDP_TAG ),
			'new_item_name'              => __( 'Nouvelle période', AGDP_TAG ),
			'add_new_item'               => __( 'Ajouter une période', AGDP_TAG ),
			'edit_item'                  => __( 'Modifier', AGDP_TAG ),
			'update_item'                => __( 'Mettre à jour', AGDP_TAG ),
			'view_item'                  => __( 'Afficher', AGDP_TAG ),
			'separate_items_with_commas' => __( 'Séparer les éléments par une virgule', AGDP_TAG ),
			'add_or_remove_items'        => __( 'Ajouter ou supprimer des éléments', AGDP_TAG ),
			'choose_from_most_used'      => __( 'Choisir la plus utilisée', AGDP_TAG ),
			'popular_items'              => __( 'Périodes les plus utilisées', AGDP_TAG ),
			'search_items'               => __( 'Rechercher', AGDP_TAG ),
			'not_found'                  => __( 'Introuvable', AGDP_TAG ),
			'no_terms'                   => __( 'Aucune période', AGDP_TAG ),
			'items_list'                 => __( 'Liste des périodes', AGDP_TAG ),
			'items_list_navigation'      => __( 'Navigation parmi les périodes', AGDP_TAG ),
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
		register_taxonomy( AgendaPartage_Newsletter::taxonomy_period, array( AgendaPartage_Newsletter::post_type ), $args );

	}
	
	public static function plugin_activation(){
		/** initialise les périodes **/
		
		$terms = array(
			'none'=>'Aucun abonnement',
			'm'=>'Tous les mois',
			'2w'=>'Tous les quinze jours',
			'w'=>'Toutes les semaines',
			'd'=>'Tous les jours',
		);
		
		$existings = [];
		foreach(get_terms(AgendaPartage_Newsletter::taxonomy_period) as $existing){
			if( array_key_exists( $existing->slug, $terms) )
				$existings[$existing->slug] = $existing->name;
		}
		debug_log(__CLASS__ . ' plugin_activation', array_diff($terms, $existings));
		foreach( array_diff($terms, $existings) as $new_slug => $new_name)
			wp_insert_term($new_name, AgendaPartage_Newsletter::taxonomy_period
				, array(
					'slug' => (string)$new_slug
				)
			);
		
		register_activation_hook( 'AgendaPartage_Newsletter', 'init_cron');
	}
}
