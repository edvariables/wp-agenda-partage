<?php 
/**
 * AgendaPartage Admin -> Report
 * Custom post type for WordPress in Admin UI.
 * 
 * Capabilities
 * Colonnes de la liste des reportes
 * Dashboard
 *
 * Voir aussi Agdp_Report
 */
class Agdp_Admin_Report {

	public static function init() {

		self::init_hooks();
	}

	public static function init_hooks() {
		add_action( 'admin_head', array(__CLASS__, 'init_post_type_supports'), 10, 4 );
		add_filter( 'map_meta_cap', array(__CLASS__, 'map_meta_cap'), 10, 4 );
		
		add_action( 'wp_dashboard_setup', array(__CLASS__, 'add_dashboard_widgets'), 10 ); //dashboard
		
		//add custom columns for list view
		add_filter( 'manage_' . Agdp_Report::post_type . '_posts_columns', array( __CLASS__, 'manage_columns' ) );
		add_action( 'manage_' . Agdp_Report::post_type . '_posts_custom_column', array( __CLASS__, 'manage_custom_columns' ), 10, 2 );
		
	}
	/**
	 * N'affiche le bloc Auteur qu'en Archive (liste) / modification rapide
	 * N'affiche l'éditeur que pour l'évènement modèle ou si l'option Agdp::agdpreport_show_content_editor
	 */
	public static function init_post_type_supports(){
		global $post;
		if( current_user_can('manage_options') ){
			if(is_archive()){
				add_post_type_support( Agdp_Report::post_type, 'author' );
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
		if($cap == 'edit_agdpreportes'){
			//var_dump($cap, $caps);
					$caps = array();
					//$caps[] = ( current_user_can('manage_options') ) ? 'read' : 'do_not_allow';
					$caps[] = 'read';
			return $caps;
		}
		/* If editing, deleting, or reading an event, get the post and post type object. */
		if ( 'edit_agdpreport' == $cap || 'delete_agdpreport' == $cap || 'read_agdpreport' == $cap ) {
			$post = get_post( $args[0] );
			$post_type = get_post_type_object( $post->post_type );

			/* Set an empty array for the caps. */
			$caps = array();
		}

		/* If editing an event, assign the required capability. */
		if ( 'edit_agdpreport' == $cap ) {
			if ( $user_id == $post->post_author )
				$caps[] = $post_type->cap->edit_posts;
			else
				$caps[] = $post_type->cap->edit_others_posts;
		}

		/* If deleting an event, assign the required capability. */
		elseif ( 'delete_agdpreport' == $cap ) {
			if ( $user_id == $post->post_author )
				$caps[] = $post_type->cap->delete_posts;
			else
				$caps[] = $post_type->cap->delete_others_posts;
		}

		/* If reading a private event, assign the required capability. */
		elseif ( 'read_agdpreport' == $cap ) {

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
		// $new_columns['associated-page'] = __( 'Page associée', AGDP_TAG );
		$new_columns['sql'] = __( 'Requête', AGDP_TAG );
		return array_merge($new_columns, $columns);
	}
	/**
	*/
	public static function manage_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'associated-page' :
				$post = get_post( $post_id );
				if($post->post_status != 'publish'){
					$post_statuses = get_post_statuses();
					echo sprintf('%sstatut "%s"<br>', Agdp::icon('warning'), $post_statuses[$post->post_status]);
				}
				// $emails = '';
				// foreach( Agdp_Report::get_emails_dispatch( $post_id ) as $email => $dispatch ){
					// if( $emails ) $emails .= '<br>';
					// $emails .= sprintf('%s > %s (%s)'
						// , $email
						// , sprintf('<a href="/wp-admin/post.php?post=%d&action=edit">%s</a>', $dispatch['id'], $dispatch['page_title'])
						// , $dispatch['rights'] . ($dispatch['moderate'] ? ' - modéré' : ''));
				// }
				// if( $emails )
					// echo sprintf('<code>%s</code>', $emails);
				break;
			case 'sql' :
				$meta_key = 'sql';
				$value = get_post_meta( $post_id, $meta_key, true );
				if( is_array($value) ){
					debug_log(__FUNCTION__, $value);
					$value = implode( "\n", $value );
				}
				if( strlen($value) > 200 )
					$value = substr( $value, 0, 200) . '...';
				echo sprintf('<code>%s</code>', $value);
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
	    // global $wp_meta_boxes;
		// $current_user = wp_get_current_user();
	}
}
?>