<?php 
/**
 * AgendaPartage Admin -> Forum
 * Custom post type for WordPress in Admin UI.
 * 
 * Capabilities
 * Colonnes de la liste des mailboxes
 * Dashboard
 *
 * Voir aussi AgendaPartage_Forum
 */
class AgendaPartage_Admin_Forum {

	public static function init() {

		self::init_hooks();
	}

	public static function init_hooks() {
		//add custom columns for list view
		add_filter( 'manage_' . AgendaPartage_Forum::post_type . '_posts_columns', array( __CLASS__, 'manage_columns' ) );
		add_action( 'manage_' . AgendaPartage_Forum::post_type . '_posts_custom_column', array( __CLASS__, 'manage_custom_columns' ), 10, 2 );
		add_filter( 'manage_edit-' . AgendaPartage_Forum::post_type . '_sortable_columns', array( __CLASS__, 'manage_sortable_columns' ) );
		if(basename($_SERVER['PHP_SELF']) === 'edit.php'
		&& isset($_GET['post_type']) && $_GET['post_type'] === AgendaPartage_Forum::post_type)
			add_action( 'pre_get_posts', array( __CLASS__, 'on_pre_get_posts'), 10, 1);

	}
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
		$new_columns[AgendaPartage_Forum::tag] = __( 'Forum', AGDP_TAG );
		return array_merge($new_columns, $columns);
	}
	/**
	*/
	public static function manage_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case AgendaPartage_Forum::tag :
				if( ! ( $mailbox = AgendaPartage_Mailbox::get_mailbox_of_page( $post_id ) ) )
					return;
				
				echo sprintf('<a href="/wp-admin/post.php?post=%d&action=edit">%s</a>', $mailbox->ID, $mailbox->post_title);
				
				$emails = '';
				foreach( AgendaPartage_Mailbox::get_emails_dispatch( $mailbox->ID, $post_id ) as $email => $dispatch ){
					$emails .= '<br>';
					$emails .= sprintf('%s (%s)'
						, $email
						, $dispatch['rights'] . ($dispatch['moderate'] && ! in_array($dispatch['rights'], ['X', 'M']) ? ' - modéré' : ''));
				}
				if( $emails )
					echo sprintf('<code>%s</code>', $emails);
				break;
			default:
				break;
		}
	}
	public static function manage_sortable_columns( $columns ) {
		$columns[AgendaPartage_Forum::tag] = AgendaPartage_Forum::tag;
		return $columns;
	}
	/**
	 * Sort custom column
	 */
	public static function on_pre_get_posts( $query ) {
		global $wpdb;
		if(empty($query->query_vars)
		|| empty($query->query_vars['orderby']))
			return;
		debug_log('on_pre_get_posts', $query->query_vars['orderby']);
		switch( $query->query_vars['orderby']) {
			case AgendaPartage_Forum::tag:
				$query->set('meta_key', AGDP_PAGE_META_MAILBOX);  
				$query->set('orderby','meta_value'); 
				break;
		}
	}
}
?>