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
		
		if(current_user_can('manage_options')
		|| current_user_can('AgendaPartage_Newsletter::post_type')){
			add_meta_box( 'dashboard_crontab',
				__('Programmation de la lettre-info', AGDP_TAG),
				array(__CLASS__, 'on_dashboard_crontab'),
				'dashboard',
				'side',
				'high',
				null
				);
		}
	}

	/**
	 * Callback
	 */
	public static function on_dashboard_crontab($post , $widget) {
		$newsletter = AgendaPartage_Newsletter::get_newsletter();
		$periods = AgendaPartage_Newsletter::subscribe_periods();
		
		/** En attente d'envoi **/	
		foreach(['aujourd\'hui' => 0, 'demain' => strtotime(date('Y-m-d') . ' + 1 day')]
			as $date_name => $date){
			$subscribers = AgendaPartage_Newsletter::get_today_subscribers($newsletter, $date);
			debug_log($subscribers);
			if($subscribers){
				echo sprintf('<div><h3 class="%s">%d abonné.e(s) en attente d\'envoi <u>%s</u></h3></div>'
					, $date === 0 ? 'alert' : 'info'
					, count($subscribers)
					, $date_name
				);
			}
		}
		
		?><ul><?php
		/* $crons = _get_cron_array();
		foreach($crons as $cron_time => $cron_data){
			//;
			echo '<li>';
			?><header class="entry-header"><?php 
				echo sprintf('<h3 class="entry-title">%s</h3><pre>%s</pre>', date('d/m/Y H:i:s', $cron_time), var_export($cron_data, true));
			?></header><?php
			echo '</li>';
			
		}
		$crons = wp_get_schedules();
		foreach($crons as $cron_name => $cron_data){
			//;
			echo '<li>';
			?><header class="entry-header"><?php 
				echo sprintf('<h3 class="entry-title">%s</h3><pre>%s</pre>', $cron_name, var_export($cron_data, true));
			?></header><?php
			echo '</li>';
			
		} */
		?></ul><?php
	} 
}