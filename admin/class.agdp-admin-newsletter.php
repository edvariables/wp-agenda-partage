<?php 
/**
 * AgendaPartage Admin -> Lettre-info
 * Custom post type for WordPress in Admin UI.
 * 
 * Capabilities
 * Colonnes de la liste des évènements
 * Dashboard
 *
 * Voir aussi Agdp_Newsletter
 */
class Agdp_Admin_Newsletter {

	public static function init() {

		self::init_hooks();
	}

	public static function init_hooks() {
		add_action( 'admin_head', array(__CLASS__, 'init_post_type_supports'), 10, 4 );
		add_filter( 'map_meta_cap', array(__CLASS__, 'map_meta_cap'), 10, 4 );
		
		add_action( 'wp_dashboard_setup', array(__CLASS__, 'add_dashboard_widgets'), 10 ); //dashboard
		
		//add custom columns for list view
		add_filter( 'manage_' . Agdp_Newsletter::post_type . '_posts_columns', array( __CLASS__, 'manage_columns' ) );
		add_action( 'manage_' . Agdp_Newsletter::post_type . '_posts_custom_column', array( __CLASS__, 'manage_custom_columns' ), 10, 2 );
		
	}
	/**
	 * N'affiche le bloc Auteur qu'en Archive (liste) / modification rapide
	 * N'affiche l'éditeur que pour l'évènement modèle ou si l'option Agdp::agdpnl_show_content_editor
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
	/****************/

	/**
	 * Pots list view
	 */
	public static function manage_columns( $columns ) {
		$new_columns = [];
		foreach($columns as $key=>$column){
			$new_columns[$key] = $column;
			unset($columns[$key]);
			if($key === 'title')
				break;
		}
		$new_columns['mailing-enable'] = __( 'Active', AGDP_TAG );
		return array_merge($new_columns, $columns);
	}
	/**
	*/
	public static function manage_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'mailing-enable' :
				if( Agdp_Newsletter::is_active($post_id))
					_e('active', AGDP_TAG);
				else{
					//Evite la confusion avec Agdp_Event::the_title
					$post = get_post( $post_id );
					if($post->post_status != 'publish')
						echo 'non, statut "' . __($post->post_status) . '"';
					else
						_e('non', AGDP_TAG);
				}
				break;
			default:
				break;
		}
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
		|| current_user_can('Agdp_Newsletter::post_type')){
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
		if( is_network_admin())
			return;
		
		foreach(Agdp_Newsletter::get_active_newsletters() as $newsletter){
			$periods = Agdp_Newsletter::subscription_periods($newsletter);
			
			/** En attente d'envoi **/	
			foreach(['aujourd\'hui' => 0, 'demain' => strtotime(wp_date('Y-m-d') . ' + 1 day')]
				as $date_name => $date){
				$subscribers = Agdp_Newsletter::get_today_subscribers($newsletter, $date);
				// debug_log($subscribers);
				if($subscribers){
					$post_title = preg_replace('/\(\[.*\]\)\s?/', '', $newsletter->post_title);
					echo sprintf('<div><h3 class="%s">&nbsp;<a href="/wp-admin/post.php?post=%s&action=edit">%s</a><br>&nbsp;&nbsp;%d abonné.e(s) en attente d\'envoi <u>%s</u></h3></div>'
						, $date === 0 ? 'alert' : 'info'
						, $newsletter->ID
						, $post_title
						, count($subscribers)
						, $date_name
					);
				}
			}
		}
	} 
}
?>