<?php

/**
 * AgendaPartage Admin -> Edit -> Forum Comment
 * 
 * Edition d'un commentaire de forum
 * Définition des metaboxes et des champs personnalisés des commentaires de forum
 *
 */
class AgendaPartage_Admin_Edit_Forum_Comment {

	public static function init() {

		self::init_hooks();
	}
	
	public static function init_hooks() {
		add_action( 'add_meta_boxes_comment', array( __CLASS__, 'on_add_meta_boxes_comment_cb' ), 10, 1 ); //edit
	}
	
	public static function is_forum_comment($comment){
		if( $mailbox = AgendaPartage_Mailbox::get_mailbox_of_page($comment->comment_post_ID))
			return $mailbox;
		return false;
	}
	
	/****************/
	
	public static function on_add_meta_boxes_comment_cb($comment){
		if ( ! self::is_forum_comment( $comment ) )
			return;
		/* $title = get_comment_meta($comment->comment_ID, 'title', true);
		$metas = [
			'source',
			'source_server',
			'source_email',
			'source_id',
			'source_no',
			'from',
			'to',
			'import_date',
			'mailbox_id',
			'attachments', 
		]; */
		$metas = get_comment_meta($comment->comment_ID, '', true);
		$title = $metas['title'][0];
		?>
		<div id="namediv" class="stuffbox">
		<table class="form-table editcomment" role="presentation">
		<tbody>
			<tr>
				<td class="first"><label for="comment_meta[title]">Titre</label></td>
				<td><input type="text" name="comment_meta[title]" size="30" value="<?php echo $title?>" id="meta_title"></td>
			</tr>
			<?php
			foreach($metas as $meta => $value){
				if( $meta[0] === '_')
					continue;
				if( is_array($value = maybe_unserialize($value[0])) )
					$value = $value[0];
				if($value /* = get_comment_meta($comment->comment_ID, $meta, true) */){
					if( is_array($value) ) $value = print_r($value, true);
					?>
					<tr>
					<td><label><?php
						if( strpos($meta, 'posted_data_') === 0 )
							echo sprintf('<code>%s</code> %s', 'posted_data', substr($meta, strlen('posted_data_')));
						else
							echo $meta;?></label></td>
					<td><?php 
						switch( $meta ) {
							case 'attachments' :
								echo AgendaPartage_Forum::get_attachments_links($comment);
								break;
							case 'mailbox_id' :
								if( $value && ( $mailbox = get_post($value) ) )
									echo sprintf('<a href="/wp-admin/post.php?post=%s&action=edit">%s</a>', $mailbox->ID, $mailbox->post_title);
								break;
						
						default:
							echo htmlentities($value);
						}
					?></td>
					</tr>
				<?php }
			}
			?>
		</tbody>
		</table>
		</div>
		<?php
	}
}
?>