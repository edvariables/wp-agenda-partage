<?php 
/**
 * AgendaPartage Admin -> Forum Page -> Comments
 */
class Agdp_Admin_Comments {

	public static function init() {
		self::init_hooks();
	}

	public static function init_hooks() {
		global $pagenow;
		if ( $pagenow === 'edit-comments.php' ) {
			add_filter( 'get_comment', array( __CLASS__, 'on_get_comment_cb' ), 10, 1 );
		}
		
	}
	public static function on_get_comment_cb( $comment ){
		if( $title = get_comment_meta($comment->comment_ID, 'title', true) ){
			$title = sprintf('<b>%s</b><br>', $title);
			if( strpos($comment->comment_content, $title) !== 0)
				$comment->comment_content = $title . $comment->comment_content;
		}
		return $comment;
	}
}?>