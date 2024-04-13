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
					if( $email === '*@*')
						$email = 'toutes les autres adresses';
					else
						$email = sprintf('<u>%s</u>', $email);
					switch($destination['type']){
						case 'page':
							$page_id = $destination['id'];
							$page = get_post($page_id);
							$links[] = sprintf('E-mails vers %s publiés dans <a href="/wp-admin/post.php?post=%s&action=edit">%s</a>.', $email, $page_id, $page->post_title);
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
				
			// Edition d'une page de forum
			case 'page':
				$meta_key = AGDP_PAGE_META_MAILBOX;
				if( $mailbox_id = get_post_meta( $post->ID, $meta_key, true)){
					$mailbox = get_post($mailbox_id);
					
					$emails = AgendaPartage_Mailbox::get_emails_dispatch( false, $post->ID );
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
					else
						AgendaPartage_Admin::add_admin_notice_now(sprintf('<a href="/wp-admin/post.php?post=%s&action=edit">Cliquez ici pour afficher la boîte e-mails associée</a>. ', $mailbox_id)
								. sprintf('<br>E-mail(s) associé(s) : %s.', $emails)
							, ['type' => 'info', 
								'actions' => [
									'url' => sprintf('/wp-admin/post.php?post=%s&action=edit', $mailbox_id)
								]
							]);

				}
	
				break;
				
		}
	}
		
	/**
	 * Register Meta Boxes (boite en édition du mailbox)
	 */
	public static function register_mailbox_metaboxes($post){
		add_meta_box('agdp_mailbox-imap', __('Synchronisation depuis une boîte e-mails', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Mailbox::post_type, 'normal', 'high');
		add_meta_box('agdp_mailbox-cron', __('Automate d\'importation', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Mailbox::post_type, 'normal', 'high');
		add_meta_box('agdp_mailbox-dispatch', __('Distribution des e-mails reçus en fonction du destinataire de l\'e-mail', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Mailbox::post_type, 'normal', 'high');
	}

	/**
	 * Callback
	 */
	public static function metabox_callback($post, $metabox){
		//var_dump(func_get_args());
		
		switch ($metabox['id']) {
			
			case 'agdp_mailbox-dispatch':
				self::get_metabox_dispatch( $post, $metabox );
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
	
	public static function get_metabox_dispatch($post, $metabox){
		echo '<ul style="margin-left: 12em;">';
		foreach( AgendaPartage_Mailbox::get_pages_dispatch( $post ) as $page_id => $dispatches ){
			if( is_int($page_id) ){
				if( $page = get_post($page_id))
					$page_title = $page->post_title;
				else
					$page_title = sprintf('(page %d inconnue)', $page_id);
			}
			else {
				$page_title = $page_id;
			}
			echo sprintf('<li>Page <a href="/wp-admin/post.php?post=%s&action=edit">%s</a>', $page_id, $page_title);
			//echo print_r($dispatches);
			echo '<ul style="margin-left: 1em;">';
			foreach($dispatches as $dispatch){
				if($dispatch['email'] === '*@*')
					$dispatch['email'] = 'Toutes les autres adresses';
				echo sprintf('<li>Email : <b>%s</b>', $dispatch['email']);
				echo sprintf(' - (%s - %s)', $dispatch['rights'], AgendaPartage_Mailbox::get_right_label($dispatch['rights']));
				echo '</li>';
			}
			echo '</ul>';
			
			echo '</li>';
		}
		echo '</ul>';
		
		parent::metabox_html( self::get_metabox_dispatch_fields(), $post, $metabox );
		
	}
	
	public static function get_metabox_dispatch_fields(){
		$rights = [];
		foreach(AgendaPartage_Mailbox::get_all_rights() as $right)
			$rights[] = sprintf('<b>%s</b> %s', $right, AgendaPartage_Mailbox::get_right_label($right));
		$rights = implode(', ', $rights);
		
		$fields = [
			[	'name' => 'emails_dispatch',
				'label' => __('Distribution des e-mails', AGDP_TAG),
				'type' => 'text',
				'input' => 'textarea',
				'input_attributes' => ['rows="5"'],
				'learn-more' => 'Chaque ligne est de la forme <code>%e-mail_to% > %post_type%[.%post_id%][ | %droits%]</code>.',
				'comments' => ['<code>%e-mail_to%</code> peut ne pas contenir le domaine si c\'est le même que celui de la boîte e-mails.'
								, 'Par exemple : <code>info-partage.nord-ardeche@agenda-partage.fr > page.3574</code> (création de commentaires)'
								, 'ou : <code>evenement.nord-ardeche > agdpevent</code>'
								, 'ou : <code>covoiturage.nord-ardeche@agenda-partage.fr > covoiturage</code>'
								, 'ou : <code> > page.3255</code> (l\'adresse principale est utilisée)'
								, 'ou : <code>*@* > page.3255</code> (toutes les adresses)'
								, 'Droits : <code>* > page.3574 | P</code> ('.$rights.')'
								
							]
			]
		];
		return $fields;
				
	}
	
	public static function get_metabox_imap_fields(){
		
		$mailbox = get_post();
		
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
			[	'name' => 'imap_test_connexion',
				'label' => __('Tester la connexion', AGDP_TAG),
				'type' => 'checkbox',
				$test_connexion_success ? 'learn-more' : 'warning' => $test_connexion_comment
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
		
		self::update_related_pages($mailbox_id, 'delete');
		
		self::save_metaboxes($mailbox_id, $mailbox);
		
		self::update_related_pages($mailbox_id);
	}
	/**
	 * Efface ou définit les références des pages à cette mailbox.
	 */
	public static function update_related_pages ($mailbox_id, $delete = false){
		$dispatch = AgendaPartage_Mailbox::get_emails_dispatch($mailbox_id);
		foreach($dispatch as $email => $destination)
			switch($destination['type']){
				case 'page':
					$page_id = $destination['id'];
	
					$meta_key = AGDP_PAGE_META_MAILBOX;
					
					if($delete){
						delete_post_meta( $page_id, $meta_key, $mailbox_id );
					}
					else
						update_post_meta( $page_id, $meta_key, $mailbox_id );
					break;
			}	
	}
}
?>