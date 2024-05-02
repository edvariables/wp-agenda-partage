<?php
class AgendaPartage_Admin_Users {
	
	private static $forums = false;
	private static $newsletters = false;
	private static $subscription_periods = false;


	public static function init() {

		self::init_hooks();
	}

	public static function init_hooks() {
		
		if( ! is_network_admin()
		&& (basename($_SERVER['PHP_SELF']) === 'users.php')) {

			//add custom columns for list view
			add_filter( 'manage_users_columns', array( __CLASS__, 'manage_columns' ) );
			add_action( 'manage_users_custom_column', array( __CLASS__, 'manage_custom_columns' ), 10, 3 );
			add_action( 'manage_users_sortable_columns', array( __CLASS__, 'manage_sortable_columns' ), 10, 3 );
			add_action( 'manage_users_extra_tablenav', array( __CLASS__, 'manage_users_extra_tablenav') );
			add_action( 'pre_get_users', array( __CLASS__, 'pre_get_users'), 99, 1 );
		}
	}

	public static function manage_users_extra_tablenav() {
		
		echo '<form method="GET">'
			. '<div class="alignleft actions custom-user-filters">';
		
		if( $forums = AgendaPartage_Forum::get_forums() ) {
			$selected_forum_id = isset($_REQUEST[AgendaPartage_Forum::tag] ) ? $_REQUEST[AgendaPartage_Forum::tag] : false;
			echo sprintf('<select name="%s">', AgendaPartage_Forum::tag);
			echo sprintf('<option value="">(tous les forums)</option>');
			foreach( $forums as $forum ){
				echo sprintf('<option value="%d" %s>%s</option>'
					, $forum->ID
					, $selected_forum_id == $forum->ID ? 'selected' : ''
					, $forum->post_title);
			}
			echo '</select>';
		}
		
		$selected_newsletter_id = isset($_REQUEST[AgendaPartage_Newsletter::post_type] ) ? $_REQUEST[AgendaPartage_Newsletter::post_type] : false;
		echo sprintf('<select name="%s">', AgendaPartage_Newsletter::post_type);
		echo sprintf('<option value="">(toutes les lettres-infos)</option>');
		foreach(AgendaPartage_Newsletter::get_newsletters_names() as $newsletter_id =>  $newsletter_name){
			echo sprintf('<option value="%d" %s>%s</option>'
				, $newsletter_id
				, $selected_newsletter_id == $newsletter_id ? 'selected' : ''
				, $newsletter_name);
		}
		echo '</select>';
		echo '<input type="submit" class="button action" value="Filtrer">';
		echo '</div></form>';
	}
	
	public static function pre_get_users( $query ) {
		$meta_queries = [];
		if( isset($_REQUEST[AgendaPartage_Forum::tag] )
		&& $_REQUEST[AgendaPartage_Forum::tag]){
			$subscription_meta_key = AgendaPartage_Forum::get_subscription_meta_key($_REQUEST[AgendaPartage_Forum::tag]);
			$meta_queries[] = array(
				array(
					'key' => $subscription_meta_key,
					'value' => ['administrator', 'moderator', 'subscriber']
				)
			);

		}
		if( isset($_REQUEST[AgendaPartage_Newsletter::post_type] )
		&& $_REQUEST[AgendaPartage_Newsletter::post_type]){
			$subscription_meta_key = AgendaPartage_Newsletter::get_subscription_meta_key($_REQUEST[AgendaPartage_Newsletter::post_type]);
			$meta_queries[] = array(
				array(
					'key' => $subscription_meta_key,
					'value' => 'none',
					'compare' => '!=',
				)
			);

		}
		if($meta_queries){
			if( count($meta_queries) > 1 ){
				$meta_queries = array_merge( array( 'relation' => 'AND' ), $meta_queries );
			}
			$query->set( 'meta_query', $meta_queries );
		}
		return;
	}
	
	/**
	 * Pots list view
	 */
	public static function manage_columns( $columns ) {
		$new_columns = [];
		foreach($columns as $key=>$column){
			if($key === 'posts')
				break;
			$new_columns[$key] = $column;
			unset($columns[$key]);
		}
		$new_columns[AGDP_TAG] = __( 'Forums', AGDP_TAG ) . ' / ' . __( 'Lettres-infos', AGDP_TAG );
		return array_merge($new_columns, $columns);
	}
	public static function manage_sortable_columns( $columns ) {
		// $columns[AgendaPartage_Forum::tag] = AgendaPartage_Forum::tag;
		return $columns;
	}
	
	/**
	*/
	public static function manage_custom_columns( $output, $column_name, int $user_id ) {
		// debug_log(__CLASS__.'::manage_custom_columns', $output, $column_name, $user_id);
		switch ( $column_name ) {
			case AGDP_TAG :
				$user_infos = '';
				foreach(self::get_forums() as $forum){
					$subscription_meta_key = AgendaPartage_Forum::get_subscription_meta_key($forum);
					if( $subscription = get_user_meta($user_id, $subscription_meta_key, true) ){
						if( isset( AgendaPartage_Forum::subscription_roles[$subscription]) )
							$subscription = AgendaPartage_Forum::subscription_roles[$subscription];
						
						$user_infos .= sprintf('<a href="/wp-admin/user-edit.php?user_id=%d#forums">%s : %s</a>'
							, $user_id, $forum->post_title, $subscription);
					}
				}
				foreach(self::get_newsletters() as $newsletter){
					$subscription_meta_key = AgendaPartage_Newsletter::get_subscription_meta_key($newsletter);
					if( ($subscription = get_user_meta($user_id, $subscription_meta_key, true))
					&& $subscription !== 'none'	){
						$subscription_periods = self::get_subscription_periods();
						if( isset( $subscription_periods[$subscription]) )
							$subscription = $subscription_periods[$subscription];
						
						if( $user_infos ) $user_infos .= '<br>';
						$user_infos .= sprintf('<a href="/wp-admin/user-edit.php?user_id=%d#newsletters">%s : %s</a>'
							, $user_id, $newsletter->post_title, $subscription);
					}
				}
				$output .= $user_infos;
				break;
			default:
				break;
		}
		return $output;
	}

	public static function get_forums(){
		if( self::$forums )
			return self::$forums;
		return self::$forums = AgendaPartage_Forum::get_forums();
	}

	public static function get_newsletters(){
		if( self::$newsletters )
			return self::$newsletters;
		return self::$newsletters = AgendaPartage_Newsletter::get_newsletters();
	}
	public static function get_subscription_periods(){
		if( self::$subscription_periods )
			return self::$subscription_periods;
		return self::$subscription_periods = AgendaPartage_Newsletter::subscription_periods();
	}
}
