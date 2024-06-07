<?php 
/**
 * AgendaPartage Admin -> Forum
 * Custom post type for WordPress in Admin UI.
 * 
 * Capabilities
 * Colonnes de la liste des mailboxes
 * Dashboard
 *
 * Voir aussi Agdp_Forum
 */
class Agdp_Admin_Forum {
	
	public static $forums_parent_id = false;

	public static function init() {

		self::init_hooks();
		
	}

	public static function init_hooks() {
		//add custom columns for list view
		add_filter( 'manage_' . Agdp_Forum::post_type . '_posts_columns', array( __CLASS__, 'manage_columns' ) );
		add_action( 'manage_' . Agdp_Forum::post_type . '_posts_custom_column', array( __CLASS__, 'manage_custom_columns' ), 10, 2 );
		add_filter( 'manage_edit-' . Agdp_Forum::post_type . '_sortable_columns', array( __CLASS__, 'manage_sortable_columns' ) );
		if(basename($_SERVER['PHP_SELF']) === 'edit.php'
		&& isset($_GET['post_type']) && $_GET['post_type'] === Agdp_Forum::post_type)
			add_action( 'pre_get_posts', array( __CLASS__, 'on_pre_get_posts'), 10, 1);

		add_action( 'wp_dashboard_setup', array(__CLASS__, 'add_dashboard_widgets'), 10 ); //dashboard
	}
	
	public static function get_forums_parent_id(){
		if( ! self::$forums_parent_id )
			self::$forums_parent_id = Agdp::get_option('forums_parent_id');
		return self::$forums_parent_id;
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
		$new_columns[Agdp_Forum::tag] = __( 'Forum', AGDP_TAG );
		return array_merge($new_columns, $columns);
	}
	/**
	*/
	public static function manage_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case Agdp_Forum::tag :
				if( $post_id == self::get_forums_parent_id() ){
					echo '<i>Racine des forums<br>&nbsp;&nbsp;réceptacle des commentaires pour e-mail non-attribuable</i>';
					return;
				}
				if( ! ( $mailbox = Agdp_Mailbox::get_mailbox_of_page( $post_id ) ) ){
					echo sprintf('<div class="row-actions"><span class="edit"><a href="/wp-admin/post.php?post=%d&action=edit&%s=1">Activer un forum</a></span></div>',
						$post_id, Agdp_Forum::tag
					);
					return;
				}
				
				$is_suspended = Agdp_Mailbox::is_suspended( $mailbox );
					
				echo sprintf('<a href="/wp-admin/post.php?post=%d&action=edit">%s%s</a>'
					, $mailbox->ID
					, $mailbox->post_title
					, $is_suspended ? ' ' . Agdp::icon('warning', 'Suspendu !') : ''
				);
				
				$emails = '';
				foreach( Agdp_Mailbox::get_emails_dispatch( $mailbox->ID, $post_id ) as $email => $dispatch ){
					$emails .= '<br>';
					$emails .= sprintf('%s (%s)'
						, $email
						, $dispatch['rights'] . ($dispatch['moderate'] && ! in_array($dispatch['rights'], ['X', 'M']) ? ' - modéré' : ''));
				}
				if( $emails )
					echo sprintf('%s', $emails);
				break;
			default:
				break;
		}
	}
	public static function manage_sortable_columns( $columns ) {
		$columns[Agdp_Forum::tag] = Agdp_Forum::tag;
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
		switch( $query->query_vars['orderby']) {
			case Agdp_Forum::tag:
				$query->set('meta_key', AGDP_PAGE_META_MAILBOX);  
				$query->set('orderby','meta_value'); 
				break;
		}
	}
	
	/**
	 * Init dashboard_widgets
	 */
	public static function add_dashboard_widgets() {
	    global $wp_meta_boxes;
		if( ! current_user_can('manage_options') ){
			$comments = self::get_my_comments(10);
			if( count($comments) ) {
				add_meta_box( 'dashboard_my_comments',
					__('Mes messages', AGDP_TAG),
					array(__CLASS__, 'dashboard_my_comments_cb'),
					'dashboard',
					'normal',
					'high',
					array('comments' => $comments) );
			}
		}
	}

	/**
	 * Callback
	 */
	public static function dashboard_my_comments_cb($post , $widget) {
		$comments = $widget['args']['comments'];
		$forums = [];
		$edit_url = current_user_can('manage_options');
		?><ul><?php
		foreach($comments as $comment){
			echo '<li>';
			?><header class="entry-header"><?php 
				if( ! isset($forums[$comment->comment_post_ID.'']) )
					$forums[$comment->comment_post_ID.''] = get_post($comment->comment_post_ID);
				$forum = $forums[$comment->comment_post_ID.''];
				$url = get_edit_comment_link($comment->comment_ID);
				echo sprintf( '<h3 class="entry-title"><a href="%s">%s</a> dans <a href="%s">%s</a></h3>'
					, $url, $comment->comment_title, get_post_permalink($forum->ID), $forum->post_title );
				$the_date = $comment->comment_date;
				$html = sprintf('<span>ajouté le %s</span>', $the_date) ;
				if($comment->comment_approved != 1)
					$html .= sprintf('<br><b>%s</b>', Agdp::icon( 'warning', 'en attente de modération')) ;		
				echo sprintf( '<cite>%s</cite>', $html);		
			?></header><?php
			echo '<hr></li>';
			
		}
		?></ul><?php
	}
	/**
	 * Init
	 */
	public static function get_my_comments($num_comments = 5) {
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
	    $current_user = wp_get_current_user();
		$user_id = $current_user->ID;
		$user_email = $current_user->user_email;
		
		$sql = "SELECT comment.comment_post_ID, comment.comment_ID, post.post_title, post.post_name"
			. ", comment.comment_approved"
			. ", comment_title.meta_value AS comment_title, IFNULL(comment_send_date.meta_value, comment.comment_date) AS comment_date"
			. "\n FROM {$blog_prefix}posts post"
			. "\n INNER JOIN {$blog_prefix}comments comment"
			. "\n ON comment.comment_post_ID = post.ID"
			. "\n INNER JOIN {$blog_prefix}commentmeta comment_title"
			. "\n ON comment_title.comment_id = comment.comment_ID"
			. "\n AND comment_title.meta_key = 'title'"
			. "\n LEFT JOIN {$blog_prefix}commentmeta comment_send_date"
			. "\n ON comment_send_date.comment_id = comment.comment_ID"
			. "\n AND comment_send_date.meta_key = 'send_date'"
			. "\n WHERE post.post_type = '".Agdp_Forum::post_type."'"
			. "\n AND post.post_status = 'publish'"
			. "\n AND comment.comment_approved IN ('0','1')"
			. "\n AND comment.comment_author_email = '{$user_email}'"
			. "\n ORDER BY IFNULL(comment_send_date.meta_value, comment.comment_date) DESC"
			. "\n LIMIT {$num_comments}"
			;
		$dbresults = $wpdb->get_results($sql);
		if( is_a($dbresults, 'WP_Error') )
			throw $dbresults;
		return $dbresults;
	}
}
?>