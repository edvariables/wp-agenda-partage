<?php 
/**
 * AgendaPartage Admin -> Lettre-info
 * Custom post type for WordPress in Admin UI.
 * 
 * Capabilities
 * Colonnes de la liste des évènements
 * Dashboard
 *
 * Voir aussi AgendaPartage_Newsletter
 */
class AgendaPartage_Admin_Newsletter {

	public static function init() {

		self::init_hooks();
	}

	public static function init_hooks() {
		add_action( 'admin_head', array(__CLASS__, 'init_post_type_supports'), 10, 4 );
		add_filter( 'map_meta_cap', array(__CLASS__, 'map_meta_cap'), 10, 4 );
		
		//add custom columns for list view
		// add_filter( 'manage_' . AgendaPartage_Newsletter::post_type . '_posts_columns', array( __CLASS__, 'manage_columns' ) );
		// add_action( 'manage_' . AgendaPartage_Newsletter::post_type . '_posts_custom_column', array( __CLASS__, 'manage_custom_columns' ), 10, 2 );
		// // set custom columns sortable
		// add_filter( 'manage_edit-' . AgendaPartage_Newsletter::post_type . '_sortable_columns', array( __CLASS__, 'manage_sortable_columns' ) );

		add_action( 'wp_dashboard_setup', array(__CLASS__, 'add_dashboard_widgets'), 10 ); //dashboard
		
		
	}
	/****************/

	/**
	 * Liste de évènements
	 */
	/* public static function manage_columns( $columns ) {
		unset( $columns );
		$columns = array(
			'cb'     => __( 'Sélection', AGDP_TAG ),
			'titre'     => __( 'Titre', AGDP_TAG ),
			'dates'     => __( 'Date(s)', AGDP_TAG ),
			'details'     => __( 'Détails', AGDP_TAG ),
			'type_agdpnl'     => __( 'Catégories', AGDP_TAG ),
			'organisateur'     => __( 'Organisateur', AGDP_TAG ),
			'publication'      => __( 'Publication', AGDP_TAG ),
			'author'        => __( 'Auteur', AGDP_TAG ),
			'date'      => __( 'Date', AGDP_TAG )
		);
		return $columns;
	}
	public static function manage_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'titre' :
				//Evite la confusion avec AgendaPartage_Newsletter::the_agdpnl_title
				$post = get_post( $post_id );
				echo $post->post_title;
				
				break;
			case 'organisateur' :
				$organisateur = get_post_meta( $post_id, 'ev-organisateur', true );
				$email = get_post_meta( $post_id, 'ev-email', true );
				echo $organisateur . ($email && $organisateur ? ' - ' : '' ) . $email;
				
				break;
			case 'type_agdpnl' :
				the_terms( $post_id, 'type_agdpnl', '<cite class="entry-terms">', ', ', '</cite>' );
				break;
			case 'dates' :
				echo AgendaPartage_Newsletter::get_event_dates_text( $post_id );
				break;
			case 'publication' :
				the_terms( $post_id, 'publication', '<cite class="entry-terms">', ', ', '</cite>' );
				break;
			case 'details' :
				$post = get_post( $post_id );
				$localisation = get_post_meta( $post_id, 'ev-localisation', true );
				if(strlen($localisation)>20)
						$localisation = trim(substr($localisation, 0, 20)) . '...';
				// $organisateur = get_post_meta( $post_id, 'ev-organisateur', true );
				$description = $post->post_content;
				if(strlen($description)>20)
						$description = trim(substr($description, 0, 20)) . '...';
				$siteweb    = get_post_meta( $post_id, 'ev-siteweb', true );
				echo trim(
					  ($localisation ? $localisation . ' - ' : '')
					// . ($organisateur ? $organisateur . ' - ' : '')
					. ($description ? $description . ' - ' : '')
					. ($siteweb ? make_clickable( esc_html($siteweb) ) : '')
				);
				break;
			default:
				break;
		}
	}

	public static function manage_sortable_columns( $columns ) {
		$columns['titre']    = 'titre';
		$columns['author']    = 'author';
		$columns['dates'] = 'dates';
		$columns['details'] = 'details';
		$columns['publication'] = 'publication';
		$columns['organisateur'] = 'organisateur';
		return $columns;
	} */
	/****************/

	/**
	 * N'affiche le bloc Auteur qu'en Archive (liste) / modification rapide
	 * N'affiche l'éditeur que pour l'évènement modèle ou si l'option AgendaPartage::agdpnl_show_content_editor
	 */
	public static function init_post_type_supports(){
		global $post;
		if( current_user_can('manage_options') ){
			if(is_archive()){
				add_post_type_support( 'agdpnl', 'author' );
			}
		}
	}

	/**
	 * map_meta_cap
	 TODO all
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {

		if( 0 ) {
			echo "<br>\n-------------------------------------------------------------------------------";
			print_r(func_get_args());
			/*echo "<br>\n-----------------------------------------------------------------------------";
			print_r($caps);*/
		}
		if($cap == 'edit_agdpnls'){
			//var_dump($cap, $caps);
					$caps = array();
					//$caps[] = ( current_user_can('manage_options') ) ? 'read' : 'do_not_allow';
					$caps[] = 'read';
			return $caps;
		}
		/* If editing, deleting, or reading an event, get the post and post type object. */
		if ( 'edit_agdpnl' == $cap || 'delete_agdpnl' == $cap || 'read_agdpnl' == $cap ) {
			$post = get_post( $args[0] );
			$post_type = get_post_type_object( $post->post_type );

			/* Set an empty array for the caps. */
			$caps = array();
		}

		/* If editing an event, assign the required capability. */
		if ( 'edit_agdpnl' == $cap ) {
			if ( $user_id == $post->post_author )
				$caps[] = $post_type->cap->edit_posts;
			else
				$caps[] = $post_type->cap->edit_others_posts;
		}

		/* If deleting an event, assign the required capability. */
		elseif ( 'delete_agdpnl' == $cap ) {
			if ( $user_id == $post->post_author )
				$caps[] = $post_type->cap->delete_posts;
			else
				$caps[] = $post_type->cap->delete_others_posts;
		}

		/* If reading a private event, assign the required capability. */
		elseif ( 'read_agdpnl' == $cap ) {

			if ( 'private' != $post->post_status )
				$caps[] = 'read';
			elseif ( $user_id == $post->post_author )
				$caps[] = 'read';
			else
				$caps[] = $post_type->cap->read_private_posts;
		}

		/* Return the capabilities required by the user. */
		return $caps;
	}

	/**
	 * dashboard_widgets
	 */

	/**
	 * Init
	 */
	public static function add_dashboard_widgets() {
	    global $wp_meta_boxes;
		$current_user = wp_get_current_user();
		//TODO : trier par les derniers ajoutés
		//TODO : author OR email
		/* $agdpnls = AgendaPartage_Newsletters::get_posts(5
			, array( 
				// 'author' => $current_user->ID,
				// 'relation' => 'OR',
				'meta_query' => [
					'key' => 'ev-email',
					'value' => $current_user->user_email,
					'compare' => '='
				]
			));
	    if( count($agdpnls) ) {
			add_meta_box( 'dashboard_my_agdpnls',
				__('Mes évènements', AGDP_TAG),
				array(__CLASS__, 'dashboard_my_agdpnls_cb'),
				'dashboard',
				'normal',
				'high',
				array('agdpnls' => $agdpnls) );
		}

	    if( WP_DEBUG || is_multisite()){
			$blogs = AgendaPartage_Admin_Multisite::get_other_blogs_of_user();
			if( $blogs && count($blogs) ) {
				add_meta_box( 'dashboard_my_blogs',
					__('Mes autres sites AgendaPartage', AGDP_TAG),
					array(__CLASS__, 'dashboard_my_blogs_cb'),
					'dashboard',
					'normal',
					'high',
					array('blogs' => $blogs) );
			}
		}
		
		if(current_user_can('manage_options')
		|| current_user_can('agdpnl')){
		    $agdpnls = AgendaPartage_Newsletters::get_posts(10);
			if( count($agdpnls) ) {
				add_meta_box( 'dashboard_all_agdpnls',
					__('Les évènements', AGDP_TAG),
					array(__CLASS__, 'dashboard_all_agdpnls_cb'),
					'dashboard',
					'side',
					'high',
					array('agdpnls' => $agdpnls) );
			}
		} */
	}

	/**
	 * Callback
	 */
	/* public static function dashboard_my_blogs_cb($post , $widget) {
		$blogs = $widget['args']['blogs'];
		?><ul><?php
		foreach($blogs as $blog){
			//;
			echo '<li>';
			?><header class="entry-header"><?php 
				echo sprintf('<h3 class="entry-title"><a href="%s/wp-admin"><img src="%s" class="coo-favicon"/>%s</a></h3>', $blog->siteurl, $blog->siteurl . '/favicon.ico', $blog->blogname);
			?></header><?php
			echo '</li>';
			
		}
		?></ul><?php
	} */

	/**
	 * Callback
	 */
	/* public static function dashboard_my_agdpnls_cb($post , $widget) {
		$agdpnls = $widget['args']['agdpnls'];
		$edit_url = current_user_can('manage_options');
		?><ul><?php
		foreach($agdpnls as $agdpnl){
			echo '<li>';
			?><header class="entry-header"><?php 
				if($edit_url)
					$url = get_edit_post_link($agdpnl);
				else
					$url = AgendaPartage_Newsletter::get_post_permalink($agdpnl);
				echo sprintf( '<h3 class="entry-title"><a href="%s">%s</a></h3>', $url, AgendaPartage_Newsletter::get_agdpnl_title($agdpnl) );
				the_terms( $agdpnl->ID, 'type_agdpnl', 
					sprintf( '<div><cite class="entry-terms">' ), ', ', '</cite></div>' );
				$the_date = get_the_date('', $agdpnl);
				$the_modified_date = get_the_modified_date('', $agdpnl);
				$html = sprintf('<span>ajouté le %s</span>', $the_date) ;
				if($the_date != $the_modified_date)
					$html .= sprintf('<span>, mis à jour le %s</span>', $the_modified_date) ;		
				echo sprintf( '<cite>%s</cite><hr>', $html);		
			?></header><?php
			?><div class="entry-summary">
				<?php echo get_the_excerpt($agdpnl); //TODO empty !!!!? ?>
			</div><?php 
			echo '</li>';
			
		}
		?></ul><?php
	} */

	/**
	 * Callback
	 */
	/* public static function dashboard_all_agdpnls_cb($post , $widget) {
		$agdpnls = $widget['args']['agdpnls'];
		$today_date = date(get_option( 'date_format' ));
		$max_rows = 4;
		$post_statuses = get_post_statuses();
		?><ul><?php
		foreach($agdpnls as $agdpnl){
			echo '<li>';
			edit_post_link( AgendaPartage_Newsletter::get_agdpnl_title($agdpnl), '<h3 class="entry-title">', '</h3>', $agdpnl );
			$the_date = get_the_date('', $agdpnl);
			$the_modified_date = get_the_modified_date('', $agdpnl);
			$html = '';
			if($agdpnl->post_status != 'publish')
				$html .= sprintf('<b>%s</b> - ', $post_statuses[$agdpnl->post_status]) ;
			$html .= sprintf('<span>ajouté le %s</span>', $the_date) ;
			if($the_date != $the_modified_date)
				$html .= sprintf('<span>, mis à jour le %s</span>', $the_modified_date) ;		
			edit_post_link( $html, '<cite>', '</cite><hr>', $agdpnl );			
			echo '</li>';

			if( --$max_rows <= 0 && $the_modified_date != $today_date )
				break;
		}
		echo sprintf('<li><a href="%s">%s...</a></li>', get_home_url( null, 'wp-admin/edit.php?post_type=' . AgendaPartage_Newsletter::post_type), __('Tous les évènements', AGDP_TAG));
		?>
		</ul><?php
	} */

}