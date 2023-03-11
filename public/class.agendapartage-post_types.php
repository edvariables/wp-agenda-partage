<?php
/**
 * Register provider post types and taxonomies.
 * ED200325
 */
class AgendaPartage_Post_Types {

	public static function init() {
		self::init_includes();
	}

	public static function init_includes() {
		if(!class_exists('AgendaPartage_Evenement'))
			require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-agdpevent.php' );
		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-agdpevent-post_type.php' );
		if(!class_exists('AgendaPartage_Newsletter'))
			require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-newsletter.php' );
		require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-newsletter-post_type.php' );
		if(AgendaPartage::maillog_enable()){
			if(!class_exists('AgendaPartage_Maillog'))
				require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-maillog.php' );
			require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage-maillog-post_type.php' );
		}
	}

	/**
	 * Register post types and taxonomies.
	 */
	public static function register_post_types() {

		do_action( 'agendapartage_register_post_types' );

		AgendaPartage_Evenement_Post_Type::register_post_type();
		AgendaPartage_Evenement_Post_Type::register_taxonomy_type_agdpevent();
		AgendaPartage_Evenement_Post_Type::register_taxonomy_city();
		AgendaPartage_Evenement_Post_Type::register_taxonomy_publication();
		
		AgendaPartage_Newsletter_Post_Type::register_post_type();
		AgendaPartage_Newsletter_Post_Type::register_taxonomy_period();
		
		if(AgendaPartage::maillog_enable()){
			AgendaPartage_Maillog_Post_Type::register_post_type();
		}
		
	    // clear the permalinks after the post type has been registered
	    flush_rewrite_rules();

		do_action( 'agendapartage_after_register_post_types' ); 
	}

	/**
	 * Unregister post types and taxonomies.
	 */
	public static function unregister_post_types() {

		do_action( 'agendapartage_unregister_post_types' );
		
		
		foreach( AgendaPartage_Evenement_Post_Type::get_taxonomies() as $tax_name => $taxonomy){
			if ( post_type_exists( $tax_name ) ) 
				unregister_post_type($tax_name);
		}

		unregister_post_type(AgendaPartage_Evenement::post_type);
		unregister_post_type(AgendaPartage_Newsletter::post_type);
		
		if(AgendaPartage::maillog_enable()){
			unregister_post_type(AgendaPartage_Maillog::post_type);
		}
		
		// clear the permalinks to remove our post type's rules from the database
    	flush_rewrite_rules();

		do_action( 'agendapartage_after_unregister_post_types' );
	}
	
	public static function plugin_activation(){
		AgendaPartage_Newsletter_Post_Type::plugin_activation();
	}
}
