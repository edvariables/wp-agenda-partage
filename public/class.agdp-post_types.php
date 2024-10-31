<?php
/**
 * Register provider post types and taxonomies.
 * ED200325
 */
class Agdp_Post_Types {

	public static function init() {
		self::init_includes();
	}

	public static function init_includes() {
		//TODO est-ce bien nécessaire ?
		
		if(!class_exists('Agdp_Post'))
			require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-post.php' );
		
		if(!class_exists('Agdp_Posts'))
			require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-posts.php' );
		
		if(!class_exists('Agdp_Page'))
			require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-page.php' );
		
		if(!class_exists('Agdp_Mailbox'))
			require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-mailbox.php' );
		require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-mailbox-post_type.php' );
		
		if(!class_exists('Agdp_Report'))
			require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-report.php' );
		require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-report-post_type.php' );
		
		if(!class_exists('Agdp_Evenement'))
			require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-agdpevent.php' );
		require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-agdpevent-post_type.php' );
		if(!class_exists('Agdp_Newsletter'))
			require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-newsletter.php' );
		require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-newsletter-post_type.php' );
		if(Agdp::maillog_enable()){
			if(!class_exists('Agdp_Maillog'))
				require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-maillog.php' );
			require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-maillog-post_type.php' );
		}
		if(!class_exists('Agdp_Covoiturage'))
			require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-covoiturage.php' );
		require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-covoiturage-post_type.php' );
		
		if(!class_exists('Agdp_Forum'))
			require_once( AGDP_PLUGIN_DIR . '/public/class.agdp-forum.php' );
	}

	/**
	 * Register post types and taxonomies.
	 */
	public static function register_post_types() {

		do_action( 'agdp_register_post_types' );

		Agdp_Mailbox_Post_type::register_post_type();
		
		Agdp_Report_Post_type::register_post_type();
		Agdp_Report_Post_type::register_taxonomy_report_style();
		
		Agdp_Evenement_Post_type::register_post_type();
		Agdp_Evenement_Post_type::register_taxonomy_ev_category();
		Agdp_Evenement_Post_type::register_taxonomy_city();
		Agdp_Evenement_Post_type::register_taxonomy_diffusion();
		
		Agdp_Newsletter_Post_type::register_post_type();
		Agdp_Newsletter_Post_type::register_taxonomy_period();
		
		if(Agdp::maillog_enable()){
			Agdp_Maillog_Post_type::register_post_type();
		}

		Agdp_Covoiturage_Post_type::register_post_type();
		Agdp_Covoiturage_Post_type::register_taxonomy_city();
		Agdp_Covoiturage_Post_type::register_taxonomy_diffusion();
				
	    // clear the permalinks after the post type has been registered
	    flush_rewrite_rules();

		do_action( 'agdp_after_register_post_types' ); 
	}

	/**
	 * Unregister post types and taxonomies.
	 */
	public static function unregister_post_types() {

		do_action( 'agdp_unregister_post_types' );
		
		
		unregister_post_type(Agdp_Mailbox::post_type);
		
		foreach( Agdp_Evenement_Post_type::get_taxonomies() as $tax_name => $taxonomy){
			if ( post_type_exists( $tax_name ) ) 
				unregister_post_type($tax_name);
		}

		unregister_post_type(Agdp_Evenement::post_type);
		unregister_post_type(Agdp_Newsletter::post_type);
		
		if(Agdp::maillog_enable()){
			unregister_post_type(Agdp_Maillog::post_type);
		}
		
		unregister_post_type(Agdp_Covoiturage::post_type);
		
		// clear the permalinks to remove our post type's rules from the database
    	flush_rewrite_rules();

		do_action( 'agdp_after_unregister_post_types' );
	}
	
	public static function plugin_activation(){
		Agdp_Newsletter_Post_type::plugin_activation();
	}
}
