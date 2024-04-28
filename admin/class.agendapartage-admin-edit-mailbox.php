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
			add_action( 'admin_notices', array(__CLASS__, 'on_admin_notices_cb'), 10);
		}
	}
	/****************/
		
	/**
	 * Register Meta Boxes (boite en édition du forum)
	 */
	public static function on_admin_notices_cb(){
		global $post;
		if( ! $post )
			return;
		switch($post->post_type){
			// Edition d'une mailbox
			case AgendaPartage_Mailbox::post_type:
				$alerts = [];
				if( $post->post_status != 'publish')
					$alerts[] = sprintf('Attention, cette page est marquée "%s".', (get_post_statuses())[$post->post_status]);
				if ( ! get_post_meta($post->ID, 'imap_mark_as_read', true) )
					$alerts[] = 'Attention, l\'option "Marquer les messages comme étant lus" n\'est pas cochée.';
				if( $alerts )
					AgendaPartage_Admin::add_admin_notice_now( implode('<br>', $alerts)
						, ['type' => 'warning']);
						
				$dispatch = AgendaPartage_Mailbox::get_emails_dispatch($post->ID);
						
				$links = [];
				foreach($dispatch as $email => $destination){
					if( $email === '*' )
						$email = 'toutes les autres adresses';
					else
						$email = sprintf('<u>%s</u>', $email);
					switch($destination['type']){
						case 'page':
							$page_id = $destination['id'];
							$page = get_post($page_id);
							$links[] = sprintf('E-mails vers %s publiés dans <a href="/wp-admin/post.php?post=%s&action=edit">%s</a> (%s).'
								, $email, $page_id, $page->post_title
								, AgendaPartage_Forum::get_right_label( $destination['rights'])
									. ($destination['moderate'] && !in_array($destination['rights'], ['M', 'X']) ? ' - Modération' : '')
							);
							break;
						case AgendaPartage_Evenement::post_type:
							$post_id = AgendaPartage::get_option('agenda_page_id');
							$links[] = sprintf('E-mails vers %s publiés dans <a href="/wp-admin/post.php?post=%s&action=edit">Evénements</a>.', $email, $post_id);
							break;
						case AgendaPartage_Covoiturage::post_type:
							$post_id = AgendaPartage::get_option('covoiturages_page_id');
							$links[] = sprintf('E-mails vers %s publiés dans <a href="/wp-admin/post.php?post=%s&action=edit">Covoiturages</a>.', $email, $post_id);
							break;
						default:
							AgendaPartage_Admin::add_admin_notice_now(sprintf('Destination de distribution inconnue : %s > %s', $email, print_r( $destination, true ))
								, ['type' => 'error']);
					}
				}
				if( $links )
					AgendaPartage_Admin::add_admin_notice_now(sprintf('<ul><li>%s</li></ul>', implode('</li><li>', $links))
						, ['type' => 'info']);
				break;
		}
	}
		
	/**
	 * Register Meta Boxes (boite en édition du mailbox)
	 */
	public static function register_mailbox_metaboxes($post){
		add_meta_box('agdp_mailbox-imap', __('Synchronisation depuis une boîte e-mails', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Mailbox::post_type, 'normal', 'high');
		add_meta_box('agdp_mailbox-cron', __('Automate d\'importation', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Mailbox::post_type, 'normal', 'high');
	}

	/**
	 * Callback
	 */
	public static function metabox_callback($post, $metabox){
		//var_dump(func_get_args());
		
		switch ($metabox['id']) {
			
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
		);
	}
	
	public static function get_metabox_imap_fields(){
		
		$mailbox = get_post();
		
		//imap_test_connexion
		$meta_key = 'imap_test_connexion';
		$test_connexion = get_post_meta($mailbox->ID, $meta_key, true);
		if($test_connexion){
			delete_post_meta($mailbox->ID, $meta_key, 1);
			$test_connexion_comment = 'Test de connexion';
			$result = AgendaPartage_Mailbox::check_connexion($mailbox->ID);
			if( is_wp_error($result) ){
				$test_connexion_comment .= ' : Echec : ' . $result->get_error_message();
				$test_connexion_success = false;
			}
			elseif( is_a($result, 'Exception') ){
				$test_connexion_comment .= ' : Echec : ' . $result->getMessage();
				$test_connexion_success = false;
			}
			elseif( $result || is_array($result) ){
				$test_connexion_comment .= ' : Succés ';
				$test_connexion_success = true;
			}
			else {
				$test_connexion_comment .= ' : Echec';
				$test_connexion_success = false;
			}
		}
		else {
			$test_connexion_success = false;
			$test_connexion_comment = false;
		}
		
		//imap_mark_as_read
		$meta_key = 'imap_mark_as_read';
		$imap_mark_as_read = get_post_meta($mailbox->ID, $meta_key, true);
		
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
			[	'name' => 'imap_encoding',
				'label' => __('Encodage', AGDP_TAG),
				'input' => 'select',
				'values' => ['UTF-8', 'US-ASCII']
			],
			[	'name' => 'imap_mark_as_read',
				'label' => __('Marquer les messages comme étant lus', AGDP_TAG),
				'type' => 'checkbox',
				'default' => true,
				($imap_mark_as_read ? 'learn-more hidden' : 'warning') => "Cette option doit être cochée en fonctionnement normal"
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
			[	'name' => 'imap_test_connexion',
				'label' => __('Tester la connexion', AGDP_TAG),
				'type' => 'checkbox',
				($test_connexion_success ? 'learn-more' : 'warning') => $test_connexion_comment
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
		
		$meta_key = 'cron_exec';
		$cron_exec = get_post_meta($mailbox->ID, $meta_key, true);
		if($cron_exec){
			delete_post_meta($mailbox->ID, $meta_key, 1);
			$cron_exec_comment = 'Exécution réelle du cron effectuée. ';
		}
		else
			$cron_exec_comment = '';
		$cron_exec_comment .= AgendaPartage_Mailbox::get_cron_time_str();
		
		$simulate = ! $cron_exec; //Keep true !
		AgendaPartage_Mailbox::cron_exec( $simulate, $mailbox );
		
		if( $cron_last = get_post_meta($mailbox->ID, 'cron-last', true) )
			$cron_exec_comment .= sprintf(' - précédente exécution : %s', $cron_last);/*date('d/m/Y H:i:s', strtotime($cron_last))*/
		
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