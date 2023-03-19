<?php 
/**
 * AgendaPartage Admin -> Lettre-info
 * Custom post type for WordPress in Admin UI.
 * 
 * Capabilities
 * Colonnes de la liste des traces mail
 * Dashboard
 *
 * Voir aussi AgendaPartage_Maillog
 */
class AgendaPartage_Admin_Maillog {

	public static function init() {

		self::init_hooks();
	}

	public static function init_hooks() {
		add_action( 'admin_head', array(__CLASS__, 'init_post_type_supports'), 10, 4 );
		add_filter( 'map_meta_cap', array(__CLASS__, 'map_meta_cap'), 10, 4 );
		
		add_action( 'wp_dashboard_setup', array(__CLASS__, 'add_dashboard_widgets'), 10 ); //dashboard
		
		
	}
	
	/**
	 * N'affiche le bloc Auteur qu'en Archive (liste) / modification rapide
	 * N'affiche l'éditeur que pour l'trace mail modèle ou si l'option AgendaPartage::agdpmaillog_show_content_editor
	 */
	public static function init_post_type_supports(){
		global $post;
		if( current_user_can('manage_options') ){
			if(is_archive()){
				add_post_type_support( 'agdpmaillog', 'author' );
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
		if($cap == 'edit_agdpmaillogs'){
			//var_dump($cap, $caps);
					$caps = array();
					//$caps[] = ( current_user_can('manage_options') ) ? 'read' : 'do_not_allow';
					$caps[] = 'read';
			return $caps;
		}
		/* If editing, deleting, or reading an event, get the post and post type object. */
		if ( 'edit_agdpmaillog' == $cap || 'delete_agdpmaillog' == $cap || 'read_agdpmaillog' == $cap ) {
			$post = get_post( $args[0] );
			$post_type = get_post_type_object( $post->post_type );

			/* Set an empty array for the caps. */
			$caps = array();
		}

		/* If editing an event, assign the required capability. */
		if ( 'edit_agdpmaillog' == $cap ) {
			if ( $user_id == $post->post_author )
				$caps[] = $post_type->cap->edit_posts;
			else
				$caps[] = $post_type->cap->edit_others_posts;
		}

		/* If deleting an event, assign the required capability. */
		elseif ( 'delete_agdpmaillog' == $cap ) {
			if ( $user_id == $post->post_author )
				$caps[] = $post_type->cap->delete_posts;
			else
				$caps[] = $post_type->cap->delete_others_posts;
		}

		/* If reading a private event, assign the required capability. */
		elseif ( 'read_agdpmaillog' == $cap ) {

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
		if(! current_user_can('manage_options')
		&& ! current_user_can('agdpmaillog'))
			return;
		
	    global $wp_meta_boxes;
		add_meta_box( 'dashboard_agdpmaillogs',
			__('Traces mail', AGDP_TAG),
			array(__CLASS__, 'on_dashboard_agdpmaillogs'),
			'dashboard',
			'normal',
			'high');
	}

	/**
	 * Callback
	 */
	public static function on_dashboard_agdpmaillogs($post , $widget) {
		?><ul class="dashboard-agdpmaillogs"><?php
		foreach(array('' => 'Aujourd\'hui', '- 7 day' => 'Sur 7 jours') as $timelaps => $time_name){
			
			?><li>
			<header class="entry-header"><?php 
				echo sprintf('<h3 class="entry-title">%s</h3>', $time_name);
			?></header>
			<table><tr><?php
			
			foreach(['publish' => 'Succès', 'draft' => 'En erreur', 'pending' => 'En cours !'] as $post_status => $status_name){
				$agdpmaillogs = new WP_Query( array( 
					'post_type' => AgendaPartage_Maillog::post_type,
					'fields' => 'ids',
					'post_status' => $post_status,
					'date_query' => array(
						'column'  => 'post_date',
						'after' => date('Y-m-d', strtotime(date('Y-m-d') . ' ' . $timelaps)),
						'inclusive' => true
					),
					'relation' => 'AND',
					'nopaging' => true
				));
				if( ! is_a($agdpmaillogs, 'WP_Query'))
					continue;
				//;
				debug_log($agdpmaillogs);
				echo '<td>';
				echo sprintf('<h3 class="entry-title">%s</h3>%d email(s)', $status_name, $agdpmaillogs->found_posts);
				?></header><?php
				echo '</td>';
				
			}
		
			?></tr></table></li><?php
		}
		?></ul><?php
	}

	/**
	 * Callback
	 */
	/* public static function dashboard_my_agdpmaillogs_cb($post , $widget) {
		$agdpmaillogs = $widget['args']['agdpmaillogs'];
		$edit_url = current_user_can('manage_options');
		?><ul><?php
		foreach($agdpmaillogs as $agdpmaillog){
			echo '<li>';
			?><header class="entry-header"><?php 
				if($edit_url)
					$url = get_edit_post_link($agdpmaillog);
				else
					$url = AgendaPartage_Maillog::get_post_permalink($agdpmaillog);
				echo sprintf( '<h3 class="entry-title"><a href="%s">%s</a></h3>', $url, AgendaPartage_Maillog::get_agdpmaillog_title($agdpmaillog) );
				the_terms( $agdpmaillog->ID, 'type_agdpmaillog', 
					sprintf( '<div><cite class="entry-terms">' ), ', ', '</cite></div>' );
				$the_date = get_the_date('', $agdpmaillog);
				$the_modified_date = get_the_modified_date('', $agdpmaillog);
				$html = sprintf('<span>ajouté le %s</span>', $the_date) ;
				if($the_date != $the_modified_date)
					$html .= sprintf('<span>, mis à jour le %s</span>', $the_modified_date) ;		
				echo sprintf( '<cite>%s</cite><hr>', $html);		
			?></header><?php
			?><div class="entry-summary">
				<?php echo get_the_excerpt($agdpmaillog); //TODO empty !!!!? ?>
			</div><?php 
			echo '</li>';
			
		}
		?></ul><?php
	} */

	/**
	 * Callback
	 */
	/* public static function dashboard_all_agdpmaillogs_cb($post , $widget) {
		$agdpmaillogs = $widget['args']['agdpmaillogs'];
		$today_date = date(get_option( 'date_format' ));
		$max_rows = 4;
		$post_statuses = get_post_statuses();
		?><ul><?php
		foreach($agdpmaillogs as $agdpmaillog){
			echo '<li>';
			edit_post_link( AgendaPartage_Maillog::get_agdpmaillog_title($agdpmaillog), '<h3 class="entry-title">', '</h3>', $agdpmaillog );
			$the_date = get_the_date('', $agdpmaillog);
			$the_modified_date = get_the_modified_date('', $agdpmaillog);
			$html = '';
			if($agdpmaillog->post_status != 'publish')
				$html .= sprintf('<b>%s</b> - ', $post_statuses[$agdpmaillog->post_status]) ;
			$html .= sprintf('<span>ajouté le %s</span>', $the_date) ;
			if($the_date != $the_modified_date)
				$html .= sprintf('<span>, mis à jour le %s</span>', $the_modified_date) ;		
			edit_post_link( $html, '<cite>', '</cite><hr>', $agdpmaillog );			
			echo '</li>';

			if( --$max_rows <= 0 && $the_modified_date != $today_date )
				break;
		}
		echo sprintf('<li><a href="%s">%s...</a></li>', get_home_url( null, 'wp-admin/edit.php?post_type=' . AgendaPartage_Maillog::post_type), __('Tous les traces mail', AGDP_TAG));
		?>
		</ul><?php
	} */

}
?>