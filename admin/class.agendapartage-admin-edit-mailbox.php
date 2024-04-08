<?php

/**
 * AgendaPartage Admin -> Edit -> Mailbox
 * Custom post type for WordPress in Admin UI.
 * 
 * Edition d'un mailbox
 * Définition des metaboxes et des champs personnalisés des Mailboxes 
 *
 * Voir aussi AgendaPartage_Mailbox, AgendaPartage_Admin_Mailbox
 */
class AgendaPartage_Admin_Edit_Mailbox extends AgendaPartage_Admin_Edit_Post_Type {

	public static function init() {
		parent::init();

		self::init_hooks();
	}
	
	public static function init_hooks() {
		global $pagenow;
		if ( $pagenow === 'post.php' ) {
			add_action( 'add_meta_boxes_' . AgendaPartage_Mailbox::post_type, array( __CLASS__, 'register_mailbox_metaboxes' ), 10, 1 ); //edit
			add_action( 'save_post_' . AgendaPartage_Mailbox::post_type, array(__CLASS__, 'save_post_mailbox_cb'), 10, 3 );
		}
	}
	/****************/
		
	/**
	 * Register Meta Boxes (boite en édition du mailbox)
	 */
	public static function register_mailbox_metaboxes($post){
		add_meta_box('agdp_mailbox-imap', __('Synchronisation depuis une boîte e-mails', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Mailbox::post_type, 'normal', 'high');
		add_meta_box('agdp_mailbox-cron', __('Automate d\'importation', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Mailbox::post_type, 'normal', 'high');
		add_meta_box('agdp_mailbox-dispatch', __('Distribution des e-mails reçus', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Mailbox::post_type, 'normal', 'high');
	}

	/**
	 * Callback
	 */
	public static function metabox_callback($post, $metabox){
		//var_dump(func_get_args());
		
		switch ($metabox['id']) {
			
			case 'agdp_mailbox-dispatch':
				parent::metabox_html( self::get_metabox_dispatch_fields(), $post, $metabox );
				break;
			
			case 'agdp_mailbox-imap':
				parent::metabox_html( self::get_metabox_imap_fields(), $post, $metabox );
				break;
			
			case 'agdp_mailbox-cron':
				parent::metabox_html( self::get_metabox_cron_fields(), $post, $metabox );
				break;
			
			default:
				break;
		}
	}

	public static function get_metabox_all_fields(){
		return array_merge(
			self::get_metabox_imap_fields(),
			self::get_metabox_cron_fields(),
			self::get_metabox_dispatch_fields(),
		);
	}
	
	public static function get_metabox_dispatch_fields(){
		
		$fields = [
			[	'name' => 'emails_dispatch',
				'label' => __('Distribution des e-mails', AGDP_TAG),
				'type' => 'text',
				'input' => 'textarea',
				'class' => 'NOT-hidden',
				'learn-more' => 'Chaque ligne est de la forme <code>%e-mail_to% > %post_type%[.%post_id%]</code>.',
				'comments' => ['<code>%e-mail_to%</code> peut ne pas contenir le domaine si c\'est le même que celui de la boîte e-mails.'
								, 'Par exemple : <code>info-partage.nord-ardeche@agenda-partage.fr > agdpforum.3574</code>'
								, 'ou : <code>evenement.nord-ardeche > agdpevent</code>'
								, 'ou : <code>covoiturage.nord-ardeche@agenda-partage.fr > covoiturage</code>'
							]
			]
		];
		return $fields;
				
	}
	
	public static function get_metabox_imap_fields(){
		
		$fields = [
			[	'name' => 'imap_server',
				'label' => __('Serveur IMAP', AGDP_TAG),
				'type' => 'text',
				'learn-more' => "De la forme {ssl0.ovh.net:993/ssl} ou {imap.free.fr:143/notls}."
			],
			[	'name' => 'imap_email',
				'label' => __('Compte email', AGDP_TAG),
				'type' => 'text'
			],
			[	'name' => 'imap_password',
				'label' => __('Mot de passe', AGDP_TAG),
				'type' => 'password'
			],
			[	'name' => 'imap_mark_as_read',
				'label' => __('Marquer les messages comme étant lus', AGDP_TAG),
				'type' => 'checkbox',
				'default' => true,
				'learn-more' => "Cette option doit être cochée en fonctionnement normal"
			],
			[	'name' => 'clear_signature',
				'label' => __('Effacer la signature', AGDP_TAG),
				'type' => 'text',
				'input' => 'textarea',
				'learn-more' => "Entrez ici les débuts des textes de signatures à reconnaitre."
							. "\nCeci tronque le message depuis la signature jusqu'à la fin."
							. "\nMettre ci-dessus une recherche par ligne."
			],
			[	'name' => 'clear_raw',
				'label' => __('Effacer des lignes inutiles', AGDP_TAG),
				'type' => 'text',
				'input' => 'textarea',
				'learn-more' => "Entrez ici les débuts des textes (par exemple \"Envoyé à partir de\".)"
							. "\nCeci tronque le message d'une seule ligne."
							. "\nMettre ci-dessus une recherche par ligne."
			],
		];
		return $fields;
				
	}
	
	public static function get_metabox_cron_fields(){
		$mailbox = get_post();
		
		if($mailbox->post_status != 'publish')
			$warning = 'Cette boîte e-mails n\'est pas enregistrée comme étant "Publiée", elle ne peut donc pas être automatisée.';
		else
			$warning = false;
		
		$cron_exec = get_post_meta($mailbox->ID, 'cron_exec', true);
		if($cron_exec){
			delete_post_meta($mailbox->ID, 'cron_exec', 1);
			$cron_exec_comment = 'Exécution réelle du cron effectuée';
		}
		else{
			$cron_exec_comment = AgendaPartage_Mailbox::get_cron_time_str();
		}
		
		$simulate = ! $cron_exec; //Keep true !
		AgendaPartage_Mailbox::cron_exec( $simulate, true );
		
		if( $cron_last = get_post_meta($mailbox->ID, 'cron-last', true) )
			$cron_exec_comment .= sprintf(' - précédente exécution : %s', $cron_last);
		
		if( $cron_state = AgendaPartage_Mailbox::get_cron_state() ){
			$cron_comment = substr($cron_state, 2);
			$cron_state = str_starts_with( $cron_state, '1|') 
							? 'Actif' 
							: (str_starts_with( $cron_state, '0|')
								? 'A l\'arrêt'
								: $cron_state);
		}
		else
			$cron_comment = '';
		
		$fields = [
			[ 
				'name' => 'cron-enable',
				'label' => __('Activer l\'importation automatique', AGDP_TAG),
				'input' => 'checkbox',
				'warning' => $warning
			],
			// [	'name' => 'sep',
				// 'input' => 'label'
			// ],
			[ 
				'name' => 'cron_state',
				'label' => __('Etat de l\'automate', AGDP_TAG) . ' : ' 
					. $cron_state 
					. ($cron_comment ? ' >> ' . $cron_comment : ''),
				'input' => 'label'
			],
			[ 
				'name' => 'cron_exec',
				'label' => __('Exécution maintenant d\'une boucle de traitement', AGDP_TAG) ,
				'input' => 'checkbox',
				'value' => 'unchecked', //keep unchecked
				'readonly' => ! current_user_can('manage_options'),
				'learn-more' => $cron_exec_comment
			],
			[	'name' => 'cron-period',
				'label' => __('Périodicité', AGDP_TAG),
				'unit' => 'minutes',
				'type' => 'number'
			],
		];
		return $fields;
				
	}
	
	/**
	 * Callback lors de l'enregistrement du post.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function save_post_mailbox_cb ($mailbox_id, $mailbox, $is_update){
		if( $mailbox->post_status == 'trashed' ){
			return;
		}
		self::save_metaboxes($mailbox_id, $mailbox);
	}
}
?>