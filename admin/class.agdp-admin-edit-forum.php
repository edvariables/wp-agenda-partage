<?php

/**
 * AgendaPartage Admin -> Edit -> Forum
 * Custom properties of pages in Admin UI.
 * 
 * Edition d'un page gérée comme forum (liée à une boîte e-mails)
 * Définition des metaboxes et des champs personnalisés des pages 
 *
 * Voir aussi Agdp_Forum, Agdp_Admin_Mailbox
 */
class Agdp_Admin_Edit_Forum extends Agdp_Admin_Edit_Post_Type {

	private static $block_editor_is_used;

	public static function init() {
		parent::init();

		self::init_hooks();
	}
	
	public static function init_hooks() {
		global $pagenow;
		if ( $pagenow === 'post.php'
		 || $pagenow === 'post-new.php') {
			//TODO theme without block-editor
			add_filter('use_block_editor_for_post', array( __CLASS__, 'on_use_block_editor_for_post_cb'), 10, 2);
			add_action( 'save_post_' . Agdp_Forum::post_type, array(__CLASS__, 'save_post_forum_cb'), 10, 3 );
		}
	}
	/****************/
	
	public static function on_use_block_editor_for_post_cb($use_block_editor, $post){
		
		if( (isset($_REQUEST[Agdp_Forum::tag]) && $_REQUEST[Agdp_Forum::tag])
			|| Agdp_Forum::post_is_forum( $post ) ){
			add_action( 'add_meta_boxes_' . Agdp_Forum::post_type, array( __CLASS__, 'register_forum_metaboxes' ), 10, 1 ); //edit
			add_action( 'admin_notices', array(__CLASS__, 'on_admin_notices_cb'), 10);
			if( isset($_REQUEST['block-editor']) && $_REQUEST['block-editor'] == false ){
				add_filter('wp_redirect', function($location, $status){
											return $location . '&block-editor=0';}, 10, 2);
				return self::$block_editor_is_used = false;
			}
		}
		return self::$block_editor_is_used = $use_block_editor;
	}
		
	/**
	 * Register Meta Boxes (boite en édition du forum)
	 */
	public static function on_admin_notices_cb(){
		global $post;
		
		self::on_admin_notices_import_result( $post );
		
		if( ( $mailbox = Agdp_Mailbox::get_mailbox_of_page( $post ) )
		 && ( $mailbox !== AUTO_MAILBOX ) ){
			$mailbox_id = $mailbox->ID;
			// is_suspended
			if( Agdp_Mailbox::is_suspended( $mailbox ) )
				Agdp_Admin::add_admin_notice_now(sprintf('Attention, la connexion est suspendue'
					. ' pour <a href="/wp-admin/post.php?post=%s&action=edit">la boîte e-mails <u>%s</u></a>.', $mailbox_id, $mailbox->post_title)
					, ['type' => 'warning', 
						'actions' => [
							'url' => sprintf('/wp-admin/post.php?post=%s&action=edit', $mailbox_id)
						]
					]);
			
			//emails_dispatch summary
			$emails = '';
			foreach( Agdp_Mailbox::get_emails_dispatch( false, $post->ID ) as $email=>$dispatch){
				if( $emails ) $emails .= ', ';
				if( $email === '*' ) $email = '(toutes les autres adresses)';
				$emails .= sprintf('%s (%s)', $email, $dispatch['rights']);
			}
			if( is_array($emails) ) 
				$emails = implode( ', ', array_keys($emails));
			
			//imap_mark_as_read
			if ( ! get_post_meta($mailbox_id, 'imap_mark_as_read', true) )
				Agdp_Admin::add_admin_notice_now(sprintf('Attention, l\'option "Marquer les messages comme étant lus" n\'est pas cochée.'
					. ' pour <a href="/wp-admin/post.php?post=%s&action=edit">la boîte e-mails <u>%s</u></a>.', $mailbox_id, $mailbox->post_title)
					, ['type' => 'warning', 
						'actions' => [
							'url' => sprintf('/wp-admin/post.php?post=%s&action=edit', $mailbox_id)
						]
					]);
				
			if( $mailbox->post_status != 'publish' )
				Agdp_Admin::add_admin_notice_now('Attention, la boîte e-mails associée n\'est pas publiée.'
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
		$subscribers_counter = self::get_subscribers_counters($post);
		if( $subscribers_counter['counter'] === 0
		 && in_array(Agdp_Forum::get_forum_right( $post ), ['P', 'E']) )
			$subscribers_counter = false;
			
		$box_name = sprintf('<span>%s <a href="%s" class="no-flex">%s</a></span>'
			, __('Forum', AGDP_TAG)
			, sprintf('/wp-admin/users.php?s&action=-1&%s=%d', Agdp_Forum::tag, $post->ID)
			, $subscribers_counter ? Agdp::icon('info', $subscribers_counter['label']) : ''
		) ;
		add_meta_box('agdp_forum-properties', $box_name, array(__CLASS__, 'metabox_callback'), Agdp_Forum::post_type, 'normal', 'high');

		if( Agdp_Forum::get_forum_right_need_subscription( $post ) )
			add_meta_box('agdp_forum-subscribers-add', __('Ajout de membres au forum', AGDP_TAG), array(__CLASS__, 'metabox_callback'), Agdp_Forum::post_type, 'normal', 'high');
		
		add_meta_box('agdp_forum-render', 'Affichage du forum', array(__CLASS__, 'metabox_callback'), Agdp_Forum::post_type, 'normal', 'high');
		
		
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
			case 'agdp_forum-subscribers-add':
				parent::metabox_html( self::get_metabox_subscribers_fields(), $post, $metabox );
				break;
			case 'agdp_forum-render':
				parent::metabox_html( self::get_metabox_render_fields(), $post, $metabox );
				break;
			
			default:
				break;
		}
		
		if( isset($_REQUEST['block-editor']) && $_REQUEST['block-editor'] == false ){
			echo '<input type="hidden" name="block-editor" value="0">';
		}
	}
	
	public static function get_metabox_all_fields(){
		global $post;
		$fields = array_merge(
				self::get_metabox_properties_fields(),
				self::get_metabox_render_fields()
			);
		if( Agdp_Forum::get_forum_right_need_subscription( $post ) )
			$fields = array_merge(
				$fields,
				self::get_metabox_subscribers_fields()
			);
			
		return $fields;
	}
	
	private static function check_comment_status( $post ){
		//is_suspended
		if( ( $mailbox = Agdp_Mailbox::get_mailbox_of_page( $post ) )
		 && Agdp_Mailbox::is_suspended( $mailbox ) ){
			echo sprintf('<div>%s</div>', Agdp::icon('warning','La connexion est suspendue.'));
		}
		//comment_status
		if( $post->comment_status !== 'open'
		&& ! Agdp_Page::is_agdp_post_type($post->ID)){
			echo sprintf('<div>%s</div>', Agdp::icon('warning','Les commentaires de cette page ne sont pas activés.'));
		}
	}
	
	private static function on_admin_notices_import_result( $post ){
		// debug_log('on_admin_notices_import_result');
		$meta_key = '_new-subscribers-emails';
		if( $import_result = get_post_meta( $post->ID, $meta_key, true ) ){
			// debug_log('on_admin_notices_import_result', '$import_result', $import_result);
			delete_post_meta( $post->ID, $meta_key);
			
			Agdp_Admin::add_admin_notice_now(sprintf('Résultat de l\'ajout de membre(s) : %s', $import_result)
				, ['type' => 'info', 
					'actions' => [
						'url' => sprintf('/wp-admin/users.php?s&action=-1&%s=%d', Agdp_Forum::tag, $post->ID)
					]
				]);
		}
	}
	
	public static function get_metabox_properties_fields(){
		global $post;
		$fields = [];
		
		//Boîte imap 
		$meta_key = AGDP_PAGE_META_MAILBOX;
		$values = ['' => '(aucune gestion de forum)', AUTO_MAILBOX => '(forum interne)'];
		$post_statuses = get_post_statuses();
		foreach( Agdp_Mailbox::get_mailboxes( false ) as $mailbox_id => $mailbox ){
			$is_suspended = Agdp_Mailbox::is_suspended( $mailbox );
			$values[$mailbox_id] = $mailbox->post_title
				. ($mailbox->post_status != 'publish' ? sprintf(' (%s)', $post_statuses[$mailbox->post_status]) : '')
				. ( $is_suspended ? ' - Suspendu !' : '')
			;
		}
		$fields[] = [
			'name' => $meta_key,
			'label' => __('Boîte e-mails associée', AGDP_TAG),
			'input' => 'select',
			'values' => $values,
			'unit' => sprintf('<a href="#" %s="%s" onclick="%s">Afficher la boîte e-mails</a>.'
				, 'href_mod', '/wp-admin/post.php?post=[post_id]&action=edit'
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
			'input' => 'textarea',
			'unit' => sprintf('Par exemple, %s@%s', $post ? $post->post_name : 'ce-forum', $_SERVER['HTTP_HOST']),
		];
		
		
		//Droits
		$rights = ['' => '(non défini = public)'];
		foreach(Agdp_Forum::get_all_rights() as $right)
			$rights[$right] = sprintf('%s (%s)', Agdp_Forum::get_right_label($right), $right);
		$fields[] = [
			'name' => 'forum_rights',
			'label' => __('Droits et restrictions', AGDP_TAG),
			'input' => 'select',
			'values' => $rights,
		];
		
		
		//Modération
		if( Agdp_Forum::get_property_equals('forum_moderate', false, $post)
		 && get_option( 'comment_moderation' ) == 1)
			$warning = sprintf('La <a href="%s">configuration des commentaires</a> indique : "Le commentaire doit être approuvé manuellement "'
						, '/wp-admin/options-discussion.php#comment_moderation');
		else
			$warning = false;
		$fields[] = [
			'name' => 'forum_moderate',
			'label' => __('Modération de tous les messages', AGDP_TAG),
			'input' => 'checkbox',
			'warning' => $warning,
		];
		
		//text/html
		$fields[] = [
			'name' => 'import_plain_text',
			'label' => __('Importation des messages en texte brut', AGDP_TAG),
			'input' => 'checkbox'
		];
		
		//Newsletters
		foreach( Agdp_Page::get_page_newsletters($post) as $newsletter)
			$fields[] = [
				'name' => '',
				'label' => __('Lettre-info', AGDP_TAG)
					. sprintf(' <a href="post.php?post=%d&action=edit">%s</a>', $newsletter->ID, $newsletter->post_title),
				'input' => 'link'
			];
		
		//purge
		$fields[] = [
			'name' => 'comments_purge_delay',
			'label' => __('Purge automatique des messages', AGDP_TAG),
			'input' => 'select',
			'values' => [
				'' => '(jamais)',
				'm' => '1 mois',
				'2m' => '2 mois',
				'6m' => '6 mois',
				'12m' => '12 mois',
			],
		];
		return $fields;
	}
		
	public static function get_metabox_render_fields(){
		global $post;
		$fields = [];
		
		//Visibilité les messages
		$fields[] = [
			'name' => 'forum_show_comments',
			'label' => __('Visibilité des messages', AGDP_TAG),
			'input' => 'select',
			'values' => Agdp_Forum::show_comments_modes,
			'learn-more' => 'Dans le cas des forums avec Adhésion, l\'affichage aux non-membres se limite aux titres des messages.'
		];
		
		//Affichage du mail de l'auteur
		$fields[] = [
			'name' => 'forum_comment_author_email',
			'label' => __('Visibilité de l\'e-mail des auteurs des messages', AGDP_TAG),
			'input' => 'select',
			'values' => [ '0' => 'Masqué', '1' => 'Public', 'M' => 'Réservé aux membres' ],
			'default' => '1',
		];
		
		//Affichage du titre
		$fields[] = [
			'name' => 'forum_comment_title',
			'label' => __('Gérer un titre pour les messages / commentaires', AGDP_TAG),
			'input' => 'checkbox',
			'default' => true,
		];
		
		//Affichage du lien mark_as_ended
		$fields[] = [
			'name' => 'forum_mark_as_ended',
			'label' => __('Afficher le bouton "Toujours d\'actualité ?"', AGDP_TAG),
			'input' => 'checkbox',
			'default' => true,
		];
		
		//Affichage du lien modifier
		$forms = get_posts(
			array(
				'nopaging' => true,
				'post_type'=> WPCF7_ContactForm::post_type
				//'author__in' => self::get_admin_ids(),
			)
		);
		$forms = array_merge([ '' => '(par défaut)' ], $forms);
		$fields[] = [
			'name' => 'forum_edit_message',
			'label' => __('Afficher le bouton "Modifier"', AGDP_TAG),
			'input' => 'checkbox',
			'fields' => [
				//Affichage de la case à cocher
				[
					'name' => 'forum_edit_message_form',
					'label' => __('Formulaire', AGDP_TAG),
					'input' => 'select',
					'values' => $forms,
					'unit' => sprintf('<a href="#" %s="%s" onclick="%s">Editer</a>.'
						, 'href_mod', '/wp-admin/post.php?page=wpcf7&post=[post_id]&action=edit'
						, esc_attr('javascript:var $this=jQuery(this);'
							. ' var post_id=$this.parents("div:first").find("select").val(); if( ! post_id ) return false;'
							. ' var href=$this.attr("href_mod").replace("[post_id]", post_id);'
							. ' $this.attr("href", href);')
						)
				],
			],
		];
		
		//Afficher le formulaire
		$fields[] = [
			'name' => 'forum_comment_form',
			'label' => __('Afficher le formulaire de base "Laisser un message"', AGDP_TAG),
			'input' => 'checkbox',
			'default' => true,
		];
		
		//Affichage du lien reply
		$fields[] = [
			'name' => 'forum_reply_link',
			'label' => __('Afficher le bouton "Répondre"', AGDP_TAG),
			'input' => 'checkbox',
			'default' => true,
		];
		
		//Affichage de la case à cocher  Envoyez votre réponse par e-mail à l'auteur du message
		$fields[] = [
			'name' => '',
			'label' => __('En réponse, option "Envoyez votre réponse par e-mail..."', AGDP_TAG),
			'input' => 'label',
			'fields' => [
				//Affichage de la case à cocher
				[
					'name' => 'forum_reply_email',
					'label' => __('Afficher', AGDP_TAG),
					'input' => 'checkbox',
					'default' => true,
				],
				//Valeur par défault de la case à cocher
				[
					'name' => 'forum_reply_email_default',
					'label' => __('Cocher par défaut', AGDP_TAG),
					'input' => 'checkbox',
					'default' => false,
				],
			],
		];
		
		//Affichage de la case à cocher Ce message est privé...
		$fields[] = [
			'name' => '',
			'label' => __('En réponse, option "Ce message est privé..."', AGDP_TAG),
			'input' => 'label',
			'fields' => [
				//Affichage de la case à cocher
				[
					'name' => 'forum_reply_is_private',
					'label' => __('Afficher', AGDP_TAG),
					'input' => 'checkbox',
					'default' => true,
				],
				//Valeur par défault de la case à cocher
				[
					'name' => 'forum_reply_is_private_default',
					'label' => __('Cocher par défaut', AGDP_TAG),
					'input' => 'checkbox',
					'default' => true,
				],
			],
		];
		
		//Style css
		$fields[] = [
			'name' => 'forum_comment_css',
			'label' => __('Styles au format css', AGDP_TAG),
			'input' => 'textarea',
			'style' => 'margin-top: 1.2em;',
		];
		
		return $fields;
	}
	
	public static function get_metabox_subscribers_fields(){
		global $post;
		
		$fields = [];
		
		if( self::$block_editor_is_used )
			$fields[] = [
				'name' => false,
				'input' => 'link',
				'label' => sprintf('%s Vous visualiserez mieux le résultat de la '
									.'<a href="%s&block-editor=0#agdp_forum-subscribers-add">'
									.'création des membres en passant par cet affichage</a>.<br><br>'
								, Agdp::icon('info')
								, get_edit_post_link( $post )
							),
			];
		
		$meta_key = '_new-subscribers-emails';
		$fields[] = [
			'name' => $meta_key,
			'label' => __('Adresse(s) e-mail', AGDP_TAG),
			'input' => 'text',
			'learn-more' => 'les adresses doivent être séparées d\'une virgule ou d\'un point-virgule.',
		];
		
		$newsletters = [ '' => ''];
		$meta_key = '_new-subscribers-newsletter';
		foreach( Agdp_Page::get_page_newsletters($post->ID, true) as $newsletter ) {
			$newsletters[ $newsletter->ID.''] = $newsletter->post_title;
		}
		if( count($newsletters) > 1 )
			$fields[] = [
				'name' => $meta_key,
				'label' => sprintf('Abonner à la lettre-info'),
				'input' => 'select',
				'values' => $newsletters,
			];
		
		return $fields;
	}
	
	
	public static function get_subscribers_counters($post){
		$subscription_meta_key = Agdp_Forum::get_subscription_meta_key($post);
		
		$subscribers = get_users([ 
			'meta_key' => $subscription_meta_key,
			'meta_value' => ['administrator', 'moderator', 'subscriber'],
		]);
		// debug_log('$subscribers',$subscribers);
		if( ! $subscribers ){
			return [ 'counter' => 0, 'label' => '<i>aucun membre</i>' ];
		}
		else {
			return [ 'counter' => count($subscribers)
				, 'label' => sprintf('%d membre%s', count($subscribers), count($subscribers)>1 ? 's' : '')];
		}
	}
	
	
	/**
	 * Callback lors de l'enregistrement du post.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function save_post_forum_cb ($forum_id, $forum, $is_update){
		$meta_key = '_new-subscribers-emails';
		$force_reload = false;
		if( isset($_POST[$meta_key]) && $_POST[$meta_key] ){
			$newsletter = isset($_POST['_new-subscribers-newsletter']) ? $_POST['_new-subscribers-newsletter'] : false;
			$result = self::add_new_subscribers($forum_id, $forum, $_POST[$meta_key], $newsletter);
			// unset($_POST[$meta_key]);
			
			if( $result )
				Agdp_Admin::add_admin_notice("Nouvelle(s) adhésion(s) : " . $result, 'success', true); 
			unset($_POST[$meta_key]);// = $result;
			unset($_POST['_new-subscribers-newsletter']);
			
			$force_reload = true;
		}
		
		self::save_metaboxes($forum_id, $forum);
		
		//TODO block-editor ne recharge pas la page et les notifications ne sont pas affichées
		//debug_log('save_post_forum_cb TODO', $force_reload, admin_url(sprintf('post.php?post=%d&action=edit', $forum_id) ) );
		if( $force_reload ){
			wp_redirect(admin_url(sprintf('post.php?post=%d&action=edit', $forum_id) ), 302);
			exit;
		}
	}
	/**
	 * Importe de nouveaux emails en tant que membres du forum et abonné.e.s de la lettre-info
	 */
	public static function add_new_subscribers($forum_id, $forum, $emails, $newsletter_id){
		$result = '';
		
		if( $newsletter_id ){
			$nl_subscription_meta_key = Agdp_Newsletter::get_subscription_meta_key($newsletter_id);
			$nl_periods = Agdp_Newsletter::subscription_periods($newsletter_id);
		}
		else
			$nl_subscription_meta_key = false;
		
		$subscription_meta_key = Agdp_Forum::get_subscription_meta_key($forum_id);
		$emails = self::sanitize_emails($emails);
		foreach($emails as $email => $user_name){
			if( $user_is_new = ! ($user_id = email_exists($email) ) ){
				$user = Agdp_Newsletter::create_subscriber_user($email, $user_name, false);
				$user_id = $user->ID;
				$emails_added[$email] = $user_id;
				$result .= sprintf("\n".'<li>L\'utilisateur <a href="/wp-admin/user-edit.php?user_id=%d#forums">"%s" &lt;%s&gt;</a> a été créé.</li>', $user->ID, $user->display_name, $email);
				update_user_meta($user->ID, $subscription_meta_key, 'subscriber');
			}
			else{
				if( ! is_user_member_of_blog($user_id) )
					add_existing_user_to_blog(['user_id'=>$user_id, 'role'=>'subscriber']);
				$user = get_user_by('id', $user_id);
				if( $subscription = get_user_meta( $user_id, $subscription_meta_key, true))
					$result .= sprintf("\n".'<li>L\'utilisateur <a href="/wp-admin/user-edit.php?user_id=%d#forums">"%s" &lt;%s&gt;</a> existe déjà comme "%s".</li>', $user_id, $user->display_name, $email, Agdp_Forum::subscription_roles[$subscription]);
				else {
					update_user_meta($user_id, $subscription_meta_key, 'subscriber');
					$result .= sprintf("\n".'<li>L\'utilisateur <a href="/wp-admin/user-edit.php?user_id=%d#forums">"%s" &lt;%s&gt;</a> est désormais membre de la liste.</li>', $user_id, $user->display_name, $email);
				}
			}
			if( $nl_subscription_meta_key ){
				if( $subscription = get_user_meta( $user_id, $nl_subscription_meta_key, true)){
					$subscription = isset($nl_periods[$subscription]) ? $nl_periods[$subscription] : $subscription;
					$result .= sprintf("\n".'<li><a href="/wp-admin/user-edit.php?user_id=%d#newsletters">L\'abonnement à la lettre-info</a> est déjà "%s".</li>', $user_id, $subscription);
				} else {
					$subscription = PERIOD_DAYLY;
					update_user_meta($user_id, $nl_subscription_meta_key, $subscription);
					$subscription = isset($nl_periods[$subscription]) ? $nl_periods[$subscription] : $subscription;
					$result .= sprintf("\n".'<li><a href="/wp-admin/user-edit.php?user_id=%d#newsletters">- Abonnement à la lettre-info</a> : %s.</li>', $user_id, $subscription);
				}
			}
		}
		if($result)
			$result = sprintf('<ul>%s</ul>', $result);
		// debug_log('add_new_subscribers', $emails, $result);
		return $result;
	}
	/**
	 * Sanitize emails
	 */
	public static function sanitize_emails($emails){
		$sanitized_emails = [];
		$matches = [];
		if( preg_match_all('/(\s*("([^,\;\n]*)"\s*)?\<?(([a-zA-Z0-9._-]+)@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4})\>?\s*([,\;\n]|$))/', $emails, $matches) ){
			foreach($matches[4] as $index=>$email){
				if( ! $matches[3][$index] )
					$matches[3][$index] = $matches[5][$index];
				$sanitized_emails[ strtolower($email) ] = trim($matches[3][$index], '\ ');
			}
		}
		return $sanitized_emails;
	}
	
}
?>