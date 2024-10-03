<?php 
/**
 * AgendaPartage Admin -> Forum Page -> Comments
 */
class Agdp_Admin_Comments {
	
	public const PAGE_ARG = 'p';

	public static function init() {
		self::init_hooks();
	}

	public static function init_hooks() {
		global $pagenow;
		if ( $pagenow === 'edit-comments.php' ) {
			add_filter( 'get_comment', array( __CLASS__, 'on_get_comment_cb' ), 10, 1 );
			add_action( 'manage_comments_nav', array( __CLASS__, 'on_manage_comments_nav' ), 9, 2 );
			
		}
		
	}
	/**
	 * Filtre le contenu en ajoutant le champ title s'il existe
	 */
	public static function on_get_comment_cb( $comment ){
		if( $title = get_comment_meta($comment->comment_ID, 'title', true) ){
			$title = sprintf('<b>%s</b><br>', $title);
			if( strpos($comment->comment_content, $title) !== 0)
				$comment->comment_content = $title . $comment->comment_content;
		}
		return $comment;
	}
	/**
	 * Ajout de filtres dans la liste des commentaires
	 */
	public static function on_manage_comments_nav( $comment_status, $which ){
		
		if ($which !== 'top') 
			return;
		
		echo '<div class="alignleft actions custom-comments-filters">';
		
		if( $forums = Agdp_Forum::get_forums() ) {
			$selected_forum_id = isset($_REQUEST[static::PAGE_ARG] ) ? $_REQUEST[static::PAGE_ARG] : false;
			echo sprintf('<select name="%s">', static::PAGE_ARG);
			echo sprintf('<option value="">(toutes les pages)</option>');
			foreach( $forums as $forum ){
				echo sprintf('<option value="%d" %s>%s</option>'
					, $forum->ID
					, $selected_forum_id == $forum->ID ? 'selected' : ''
					, $forum->post_title);
			}
			echo '</select>';
		}
		echo '</div>';
	}
}?>