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
}
?>