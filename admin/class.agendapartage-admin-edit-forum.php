<?php

/**
 * AgendaPartage Admin -> Edit -> Forum
 * Custom properties of pages in Admin UI.
 * 
 * Edition d'un page gérée comme forum (liée à une boîte e-mails)
 * Définition des metaboxes et des champs personnalisés des pages 
 *
 * Voir aussi AgendaPartage_Forum, AgendaPartage_Admin_Mailbox
 */
class AgendaPartage_Admin_Edit_Forum extends AgendaPartage_Admin_Edit_Post_Type {

	public static function init() {
		parent::init();

		self::init_hooks();
	}
	
	public static function init_hooks() {
		global $pagenow;
		if ( $pagenow === 'post.php' ) {
			//TODO theme without block-editor
			add_filter('use_block_editor_for_post', array( __CLASS__, 'on_use_block_editor_for_post_cb'), 10, 2);
			add_action( 'save_post_' . AgendaPartage_Forum::post_type, array(__CLASS__, 'save_post_forum_cb'), 10, 3 );
		}
	}
	/****************/
	
	public static function on_use_block_editor_for_post_cb($use_block_editor, $post){
		
		if( (isset($_REQUEST[AgendaPartage_Forum::tag]) && $_REQUEST[AgendaPartage_Forum::tag])
			|| AgendaPartage_Forum::post_is_forum( $post ) ){
			add_action( 'add_meta_boxes_' . AgendaPartage_Forum::post_type, array( __CLASS__, 'register_forum_metaboxes' ), 10, 1 ); //edit
			add_action( 'admin_notices', array(__CLASS__, 'on_admin_notices_cb'), 10);
			// return false;
		}
		return $use_block_editor;
	}
		
	/**
	 * Register Meta Boxes (boite en édition du forum)
	 */
	public static function on_admin_notices_cb(){
		global $post;
		if( $mailbox = AgendaPartage_Mailbox::get_mailbox_of_page( $post ) ){
			$mailbox_id = $mailbox->ID;
			$emails = '';
			foreach( AgendaPartage_Mailbox::get_emails_dispatch( false, $post->ID ) as $email=>$dispatch){
				if( $emails ) $emails .= ', ';
				if( $email === '*' ) $email = '(toutes les autres adresses)';
				$emails .= sprintf('%s (%s)', $email, $dispatch['rights']);
			}
			if( is_array($emails) ) 
				$emails = implode( ', ', array_keys($emails));
			
			if ( ! get_post_meta($mailbox_id, 'imap_mark_as_read', true) )
				AgendaPartage_Admin::add_admin_notice_now(sprintf('Attention, l\'option "Marquer les messages comme étant lus" n\'est pas cochée.'
					. ' pour <a href="/wp-admin/post.php?post=%s&action=edit">la boîte e-mails <u>%s</u></a>.', $mailbox_id, $mailbox->post_title)
					, ['type' => 'warning', 
						'actions' => [
							'url' => sprintf('/wp-admin/post.php?post=%s&action=edit', $mailbox_id)
						]
					]);
				
			if( $mailbox->post_status != 'publish' )
				AgendaPartage_Admin::add_admin_notice_now('Attention, la boîte e-mails associée n\'est pas publiée.'
					. sprintf(' <a href="/wp-admin/post.php?post=%s&action=edit">Cliquez ici pour modifier la boîte e-mails</a>. ', $mailbox_id)
					. sprintf('<br>E-mail(s) associé(s) : %s.', $emails)
					, ['type' => 'warning', 
						'actions' => [
							'url' => sprintf('/wp-admin/post.php?post=%s&action=edit', $mailbox_id)
						]
					]);

		}
	}
		
	/**
	 * Register Meta Boxes (boite en édition du mailbox)
	 */
	public static function register_forum_metaboxes($post){
		$box_name = sprintf('<span>%s <a href="%s" class="no-flex">%s</a></span>'
			, __('Forum', AGDP_TAG)
			, sprintf('/wp-admin/users.php?s&action=-1&%s=%d', AgendaPartage_Forum::tag, $post->ID)
			, AgendaPartage::icon('info', self::get_subscribers_counters($post)['label'])) ;
		add_meta_box('agdp_forum-properties', $box_name, array(__CLASS__, 'metabox_callback'), AgendaPartage_Forum::post_type, 'normal', 'high');
	}

	/**
	 * Callback
	 */
	public static function metabox_callback($post, $metabox){
		//var_dump(func_get_args());
		
		switch ($metabox['id']) {
			
			case 'agdp_forum-properties':
				self::check_comment_status( $post );
				parent::metabox_html( self::get_metabox_properties_fields(), $post, $metabox );
				break;
			
			default:
				break;
		}
	}
	
	public static function get_metabox_all_fields(){
		return array_merge(
			self::get_metabox_properties_fields(),
		);
	}
	
	private static function check_comment_status( $post ){
		if( $post->comment_status !== 'open' ){
			echo AgendaPartage::icon('warning','Les commentaires de cette page ne sont pas activés.');
		}
	}
	
	public static function get_metabox_properties_fields(){
		global $post;
		$fields = [];
		
		//Boîte imap 
		$meta_key = AGDP_PAGE_META_MAILBOX;
		$values = ['' => '(aucune gestion de forum)'];
		$post_statuses = get_post_statuses();
		foreach( AgendaPartage_Mailbox::get_mailboxes( false ) as $mailbox_id => $mailbox ){
			$values[$mailbox_id] = $mailbox->post_title
				. ($mailbox->post_status != 'publish' ? sprintf(' (%s)', $post_statuses[$mailbox->post_status]) : '');
		}
		$fields[] = [
			'name' => $meta_key,
			'label' => __('Boîte e-mails associée', AGDP_TAG),
			'input' => 'select',
			'values' => $values,
			'unit' => sprintf('<a href="#" href_mod="/wp-admin/post.php?post=[post_id]&action=edit" onclick="%s">Afficher la boîte e-mails</a>.'
				, esc_attr('javascript:var $this=jQuery(this);'
					. ' var post_id=$this.parents("div:first").find("select").val(); if( ! post_id ) return false;'
					. ' var href=$this.attr("href_mod").replace("[post_id]", post_id);'
					. ' $this.attr("href", href); ')
				)
		];
		
		//Adresses e-mails
		$fields[] = [
			'name' => 'forum_email[]',
			'label' => __('Comptes e-mail', AGDP_TAG),
			'input' => 'textarea'
		];
		
		//Droits
		$rights = ['' => '(non défini = public)'];
		foreach(AgendaPartage_Forum::get_all_rights() as $right)
			$rights[$right] = sprintf('%s (%s)', AgendaPartage_Forum::get_right_label($right), $right);
		$fields[] = [
			'name' => 'forum_rights',
			'label' => __('Droits et restrictions', AGDP_TAG),
			'input' => 'select',
			'values' => $rights,
		];
		
		//Modération
		$fields[] = [
			'name' => 'forum_moderate',
			'label' => __('Modération de tous les messages', AGDP_TAG),
			'input' => 'checkbox'
		];
		
		//Newsletters
		foreach( AgendaPartage_Forum::get_newsletters($post) as $newsletter)
			$fields[] = [
				'name' => '',
				'label' => __('Lettre-info', AGDP_TAG)
					. sprintf(' <a href="post.php?post=%d&action=edit">%s</a>', $newsletter->ID, $newsletter->post_title),
				'input' => 'link'
			];
		return $fields;
	}
	
	
	public static function get_subscribers_counters($post){
		$subscription_meta_key = AgendaPartage_Forum::get_subscription_meta_key($post);
		
		$subscribers = get_users([ 
			'meta_key' => $subscription_meta_key,
			'meta_value' => ['administrator', 'moderator', 'subscriber'],
		]);
		// debug_log('$subscribers',$subscribers);
		if( ! $subscribers ){
			return [ 'counter' => 0, 'label' => '<i>aucun adhérent</i>' ];
		}
		else {
			return [ 'counter' => count($subscribers), 'label' => sprintf('%d adhérent.e(s)', count($subscribers))];
		}
	}
	
	
	/**
	 * Callback lors de l'enregistrement du post.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function save_post_forum_cb ($forum_id, $forum, $is_update){
		
		self::save_metaboxes($forum_id, $forum);
	}
	
}
?>