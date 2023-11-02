<?php

/**
 * AgendaPartage -> Covoiturage
 * Custom post type for WordPress.
 * Custom user role for WordPress.
 * 
 * Définition du Post Type 'covoiturage'
 * Définition du rôle utilisateur 'covoiturage'
 * A l'affichage d'un covoiturage, le Content est remplacé par celui du covoiturage Modèle
 * En Admin, le bloc d'édition du Content est masqué d'après la définition du Post type : le paramètre 'supports' qui ne contient pas 'editor', see AgendaPartage_Admin_Covoiturage::init_PostType_Supports
 *
 * Voir aussi AgendaPartage_Admin_Covoiturage
 */
class AgendaPartage_Covoiturage_Post_type {
	
	/**
	 * Covoiturage post type.
	 */
	public static function register_post_type() {

		$labels = array(
			'name'                  => _x( 'Covoiturages', 'Post Type General Name', AGDP_TAG ),
			'singular_name'         => _x( 'Covoiturage', 'Post Type Singular Name', AGDP_TAG ),
			'menu_name'             => __( 'Covoiturages', AGDP_TAG ),
			'name_admin_bar'        => __( 'Covoiturage', AGDP_TAG ),
			'archives'              => __( 'Covoiturages', AGDP_TAG ),
			'attributes'            => __( 'Attributs', AGDP_TAG ),
			'parent_item_colon'     => __( 'Covoiturage parent:', AGDP_TAG ),
			'all_items'             => __( 'Tous les covoiturages', AGDP_TAG ),
			'add_new_item'          => __( 'Ajouter un covoiturage', AGDP_TAG ),
			'add_new'               => __( 'Ajouter', AGDP_TAG ),
			'new_item'              => __( 'Nouveau covoiturage', AGDP_TAG ),
			'edit_item'             => __( 'Modifier', AGDP_TAG ),
			'update_item'           => __( 'Mettre à jour', AGDP_TAG ),
			'view_item'             => __( 'Afficher', AGDP_TAG ),
			'view_items'            => __( 'Voir les covoiturages', AGDP_TAG ),
			'search_items'          => __( 'Rechercher des covoiturages', AGDP_TAG ),
			'items_list'            => __( 'Liste de covoiturages', AGDP_TAG ),
			'items_list_navigation' => __( 'Navigation dans la liste de covoiturages', AGDP_TAG ),
			'filter_items_list'     => __( 'Filtrer la liste des covoiturages', AGDP_TAG ),
		);
		$capabilities = self::post_type_capabilities();
		$args = array(
			'label'                 => __( 'Covoiturage', AGDP_TAG ),
			'description'           => __( 'Covoiturage de l\'agenda partagé', AGDP_TAG ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'thumbnail', 'revisions' ),//, 'author', 'editor' see AgendaPartage_Admin_Covoiturage::init_PostType_Supports
			'taxonomies'            => array( AgendaPartage_Covoiturage::taxonomy_city
											, AgendaPartage_Covoiturage::taxonomy_diffusion ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_icon'				=> 'dashicons-car',
			'menu_position'         => 27,
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
		register_post_type( AgendaPartage_Covoiturage::post_type, $args );
		
		if(WP_POST_REVISIONS >= 0)
			add_filter( 'wp_revisions_to_keep', array(__CLASS__, 'wp_revisions_to_keep'), 10, 2);
		// add_filter( '_wp_post_revision_fields', array(__CLASS__, '_wp_post_revision_fields'), 10, 2);
	}
	public static function wp_revisions_to_keep( int $num, WP_Post $post ) {
		if($post->post_type === AgendaPartage_Covoiturage::post_type)
			return -1;
		return $num;
	}
	
	/**
	 * Register Custom Taxonomy
	 */
	public static function register_taxonomy_city() {

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
		register_taxonomy( AgendaPartage_Covoiturage::taxonomy_city, array( AgendaPartage_Covoiturage::post_type ), $args );

	}
	
	/**
	 * Register Custom Taxonomy
	 */
	public static function register_taxonomy_diffusion() {

		$labels = array(
			'name'                       => _x( 'Diffusion', 'Taxonomy General Name', AGDP_TAG ),
			'singular_name'              => _x( 'Diffusion', 'Taxonomy Singular Name', AGDP_TAG ),
			'menu_name'                  => __( 'Diffusions', AGDP_TAG ),
			'all_items'                  => __( 'Toutes les diffusions', AGDP_TAG ),
			'parent_item'                => __( 'Diffusion parente', AGDP_TAG ),
			'parent_item_colon'          => __( 'Diffusion parente:', AGDP_TAG ),
			'new_item_name'              => __( 'Nouvelle diffusion', AGDP_TAG ),
			'add_new_item'               => __( 'Ajouter une diffusion', AGDP_TAG ),
			'edit_item'                  => __( 'Modifier', AGDP_TAG ),
			'update_item'                => __( 'Mettre à jour', AGDP_TAG ),
			'view_item'                  => __( 'Afficher', AGDP_TAG ),
			'separate_items_with_commas' => __( 'Séparer les éléments par une virgule', AGDP_TAG ),
			'add_or_remove_items'        => __( 'Ajouter ou supprimer des éléments', AGDP_TAG ),
			'choose_from_most_used'      => __( 'Choisir la plus utilisée', AGDP_TAG ),
			'popular_items'              => __( 'Diffusions les plus communes', AGDP_TAG ),
			'search_items'               => __( 'Rechercher', AGDP_TAG ),
			'not_found'                  => __( 'Introuvable', AGDP_TAG ),
			'no_terms'                   => __( 'Aucune diffusion', AGDP_TAG ),
			'items_list'                 => __( 'Liste des diffusions', AGDP_TAG ),
			'items_list_navigation'      => __( 'Navigation parmi les diffusions', AGDP_TAG ),
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
		register_taxonomy( AgendaPartage_Covoiturage::taxonomy_diffusion, array( AgendaPartage_Covoiturage::post_type ), $args );

	}
	
	private static function post_type_capabilities(){
		return array(
			'create_covoiturages' => 'create_posts',
			'edit_covoiturages' => 'edit_posts',
			'edit_others_covoiturages' => 'edit_others_posts',
			'publish_covoiturages' => 'publish_posts',
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
			// 'edit_covoiturages' => true,
			// 'wpcf7_read_contact_forms' => false,

			// 'publish_covoiturages' => true,
			// 'delete_posts' => true,
			// 'delete_published_posts' => true,
			// 'edit_published_posts' => true,
			// 'publish_posts' => true,
			// 'upload_files ' => true,
			// 'create_posts' => false,
			// 'create_covoiturages' => false,
		// );
		// add_role( AgendaPartage_Covoiturage::post_type, __('Covoiturage', AGDP_TAG ),  $capabilities);
	}

	/**
	 * Retourne tous les termes
	 */
	public static function get_all_intentions($array_keys_field = 'term_id'){
		return [
			'1' => 'Propose',
			'2' => 'Cherche'
		];
	}
	public static function get_all_cities($array_keys_field = 'term_id'){
		return self::get_all_terms(AgendaPartage_Covoiturage::taxonomy_city, $array_keys_field);
	}
	public static function get_all_diffusions($array_keys_field = 'term_id'){
		return self::get_all_terms(AgendaPartage_Covoiturage::taxonomy_diffusion, $array_keys_field );
	}

	/**
	 * Retourne tous les termes
	 */
	public static function get_all_terms($taxonomy, $array_keys_field = 'term_id'){
		$terms = get_terms( array('hide_empty' => false, 'taxonomy' => $taxonomy) );
		if($array_keys_field){
			$_terms = [];
			foreach($terms as $term){
				if( ! isset($term->$array_keys_field) )
					continue;
				$_terms[$term->$array_keys_field . ''] = $term;
			}
			$terms = $_terms;
		}
		
		$meta_names = [];
		switch($taxonomy){
			case AgendaPartage_Covoiturage::taxonomy_diffusion :
				$meta_names[] = 'default_checked';
				$meta_names[] = 'download_link';
				break;
		}
		foreach($meta_names as $meta_name){
			foreach($terms as $term)
				$term->$meta_name = get_term_meta($term->term_id, $meta_name, true);
		}
		return $terms;
	}
	
	/**
	 * Taxonomies
	 */
	public static function get_taxonomies ( $except = false ){
		$taxonomies = [];
		
		$tax_name = AgendaPartage_Covoiturage::taxonomy_city;
		if( ! $except || strpos($except, $tax_name ) === false)
			$taxonomies[$tax_name] = array(
				'name' => $tax_name,
				'input' => 'cov-cities',
				'filter' => 'cities',
				'label' => 'Commune',
				'plural' => 'Commune',
				'all_label' => '(toutes)',
				'none_label' => '(sans commune)'
			);
		
		$tax_name = AgendaPartage_Covoiturage::taxonomy_diffusion;
		if( ! $except || strpos($except, $tax_name ) === false)
			$taxonomies[$tax_name] = array(
				'name' => $tax_name,
				'input' => 'cov-diffusions',
				'filter' => 'diffusions',
				'label' => 'Diffusion',
				'plural' => 'Diffusions',
				'all_label' => '(toutes)',
				'none_label' => '(sans diffusion)'
			);
		
		return $taxonomies;
	}
	
	/**
	 *
	 */
	public static function is_diffusion_managed(){
		return false;//TODO AgendaPartage::get_option('newsletter_diffusion_term_id') != -1;
	}
	
	/**
	 * Retourne les termes d'une taxonomie avec leurs alternatives syntaxiques pour un like.
	 * Utilisée pour chercher les communes dans la meta_value 'cov-localisation'.
	 */
	public static function get_terms_like($tax_name, $term_ids){
		$like = [];
		foreach( get_terms(array(
				'taxonomy' => $tax_name,
				'hide_empty' => false,
		)) as $term){
			if( in_array($term->term_id, $term_ids)){
				$like[] = $term->name;
				foreach(['-'=>' ', 'saint'=>'st', 'saint-'=>'st-', 'sainte-'=>'ste-'] as $search=>$replace){
					$alt_term = str_ireplace($search, $replace, $term->name);
					if($alt_term !== $term->name)
						$like[] = $alt_term;
				}
			}
		}
		return $like;
	}
	
	/**
	 * Intention
	 */
	 public static function get_intention_label($intention_id){
		 switch($intention_id){
			case '1' :
				$intention = 'PROPOSE';
				break;
			case '2' :
				$intention = 'CHERCHE';
				break;
			default :
				$intention = 'CHERCHE ou PROPOSE';
				break;
		}
		return $intention;
	 }
}
