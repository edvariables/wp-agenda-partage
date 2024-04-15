<?php

/**
 * AgendaPartage Admin -> Edit -> Newsletter
 * Custom post type for WordPress in Admin UI.
 * 
 * Edition d'une lettre-info
 * Définition des metaboxes et des champs personnalisés des Lettres-info 
 *
 * Voir aussi AgendaPartage_Newsletter, AgendaPartage_Admin_Newsletter
 */
class AgendaPartage_Admin_Edit_Newsletter extends AgendaPartage_Admin_Edit_Post_Type {

	public static function init() {
		parent::init();

		self::init_hooks();
	}
	
	public static function init_hooks() {

		add_action( 'add_meta_boxes_' . AgendaPartage_Newsletter::post_type, array( __CLASS__, 'register_newsletter_metaboxes' ), 10, 1 ); //edit

		if(basename($_SERVER['PHP_SELF']) === 'post.php'
		&& array_key_exists('post_type', $_POST)
		&& $_POST['post_type'] == AgendaPartage_Newsletter::post_type)
			add_action( 'save_post_' . AgendaPartage_Newsletter::post_type, array(__CLASS__, 'save_post_newsletter_cb'), 10, 3 );

	}
	/****************/
	
	/**
	 * Register Meta Boxes (boite en édition de l'lettre-info)
	 */
	public static function register_newsletter_metaboxes($post){
				
		add_meta_box('agdp_newsletter-source', __('Source liée', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Newsletter::post_type, 'normal', 'high');
		add_meta_box('agdp_newsletter-test', __('Test d\'envoi', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Newsletter::post_type, 'normal', 'high');
		add_meta_box('agdp_newsletter-mailing', __('Envoi automatique', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Newsletter::post_type, 'normal', 'high');
		add_meta_box('agdp_newsletter-subscribers', __('Abonnements', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Newsletter::post_type, 'normal', 'high');
	}

	/**
	 * Callback
	 */
	public static function metabox_callback($post, $metabox){
		//var_dump(func_get_args());
		
		switch ($metabox['id']) {
			
			case 'agdp_newsletter-source':
				parent::metabox_html( self::get_metabox_source_fields(), $post, $metabox );
				break;
			
			case 'agdp_newsletter-subscribers':
				self::get_metabox_subscribers();
				break;
			
			case 'agdp_newsletter-test':
				self::get_metabox_test();
				break;
			
			case 'agdp_newsletter-mailing':
				parent::metabox_html( self::get_metabox_mailing_fields(), $post, $metabox );
				break;
			
			default:
				break;
		}
	}

	public static function get_metabox_all_fields(){
		return array_merge(
			self::get_metabox_source_fields(),
			self::get_metabox_mailing_fields(),
			self::get_metabox_subscribers_fields(),
			self::get_metabox_nl_test_fields(),
		);
	}	

	public static function get_metabox_test(){
		global $current_user;
		$newsletter = get_post();
		$newsletter_id = $newsletter->ID;
		$periods = AgendaPartage_Newsletter::subscription_periods($newsletter);
		
		$meta_name = 'send-nl-test-email';
		$meta_value = get_post_meta($newsletter_id, $meta_name, true);
		if( is_array($meta_value) ) $meta_value = $meta_value[0];
		if( $meta_value )
			$email = $meta_value;
		else
			$email = $current_user->user_email;
		echo sprintf('<label><input type="checkbox" name="send-nl-test">Envoyer la lettre-info pour test</label>');
		echo sprintf('<br><br><label>Destinataire(s) : </label><input type="email" name="send-nl-test-email" value="%s">', $email);
		
	}
	public static function get_metabox_nl_test_fields(){
		$fields = [];
		$meta_name = 'send-nl-test-email';
		$fields[] = array('name' => $meta_name,
						'label' => __('Adresse de test', AGDP_TAG),
						'type' => 'email'
		);
		return $fields;
				
	}
	
	public static function get_metabox_mailing_fields(){
		$newsletters = AgendaPartage_Newsletter::get_active_newsletters();
		$newsletter = get_post();
		
		if($newsletter->post_status != 'publish')
			$warning = 'Cette lettre-info n\'est pas enregistrée comme étant "Publiée", elle ne peut donc pas être automatisée.';
		else
			$warning = false;
		
		$cron_exec = get_post_meta($newsletter->ID, 'cron_exec', true);
		if($cron_exec){
			delete_post_meta($newsletter->ID, 'cron_exec', 1);
			$cron_exec_comment = 'Exécution réelle du cron effectuée';
		}
		else{
			$cron_exec_comment = AgendaPartage_Newsletter::get_cron_time_str();
		}
		$simulate = ! $cron_exec; //Keep true !
		AgendaPartage_Newsletter::cron_exec( $simulate );
		if( $cron_state = AgendaPartage_Newsletter::get_cron_state() ){
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
				'name' => 'mailing-enable',
				'label' => __('Activer l\'envoi automatique', AGDP_TAG),
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
			[	'name' => '',
				'input' => 'label'
			],
			[	'name' => 'mailing-month-day',
				'label' => __('Jour du mois', AGDP_TAG),
				'unit' => 'entre 1 et 28, pour l\'abonnement "Tous les mois"',
				'type' => 'number'
			],
			[	'name' => 'mailing-2W1-day',
				'label' => __('Jour de 1ère quinzaine', AGDP_TAG),
				'unit' => 'entre 1 et 14, pour l\'abonnement "Tous les quinze jours"',
				'type' => 'number'
			],
			[	'name' => 'mailing-2W2-day',
				'label' => __('Jour de 2ème quinzaine', AGDP_TAG),
				'unit' => 'entre 15 et 28, pour l\'abonnement "Tous les quinze jours"',
				'type' => 'number'
			],
			[	'name' => 'mailing-week-day',
				'label' => __('Jour de la semaine', AGDP_TAG),
				'unit' => 'pour l\'abonnement "Toutes les semaines"',
				'input' => 'select',
				'values' => [1=>'lundi', 2=>'mardi', 3=>'mercredi', 4=>'jeudi', 5=>'vendredi', 6=>'samedi', 0=>'dimanche']
			],
			[	'name' => 'mailing-hour',
				'label' => __('Heure d\'envoi', AGDP_TAG),
				'input' => 'time'
			],
			[	'name' => 'mailing-num-users-per-mail',
				'label' => __('Destinataires par e-mail', AGDP_TAG),
				'unit' => __('adresse(s) par e-mail', AGDP_TAG),
				'learn-more' => [sprintf(__('Si vous choississez plus d\'une adresse de destinataire par e-mail, elles seront en copie cachée et le destinataire principal sera "%s".', AGDP_TAG),
									AgendaPartage_Newsletter::get_bcc_mail_sender()),
								__('Les destinataires multiples ne permettent pas de personnaliser le message envoyé.', AGDP_TAG)],
				'type' => 'number'
			],
			[	'name' => 'mailing-num-emails-per-loop',
				'label' => __('Par boucle de traitement', AGDP_TAG),
				'unit' => __('e-mail(s) envoyé(s)', AGDP_TAG),
				'learn-more' => __('Le nombre de destinataires traités par boucle est la multiplication du nombre de destinataires par le nombre d\'e-mails.', AGDP_TAG),
				'type' => 'number'
			],
			[	'name' => 'mailing-loops-interval',
				'label' => __('Interval de temps', AGDP_TAG),
				'unit' => __('minutes entre deux boucles', AGDP_TAG),
				'learn-more' => [__('Le délai ne doit pas être trop petit. Le risque est d\'être considéré comme spammeur par l\'hébergeur du site.', AGDP_TAG)
							, __('Tous les mails doivent être traités avant minuit sinon les destinataires restant ne seront pas traités.', AGDP_TAG)],
				'type' => 'number'
			]
		];
		return $fields;
				
	}
	
	
	public static function get_metabox_source_fields(){
		$newsletter = get_post();
		
		$fields = [];
		
		$meta_name = 'source';
		$sources = [ AgendaPartage_Evenement::post_type => 'Evènements'
				, AgendaPartage_Covoiturage::post_type => 'Covoiturages'
				, 'agdpstats' => 'Statistiques pour administrateurices'];
		//Mailboxes
		foreach(get_posts([ 'post_type' => AgendaPartage_Mailbox::post_type]) as $mailbox){
			foreach( AgendaPartage_Mailbox::get_emails_dispatch($mailbox->ID) as $email_to => $destination)
				if( $destination['type'] === 'page'){
					if( $page = get_post($destination['id']) ){
						$title = $page->post_title . ' ('. $email_to . ')';
						if( stripos($title, 'forum') === false)
							$title = 'Forum ' . $title;
						if( $page->comment_status != 'open' )
							$title .= ' (Commentaires fermés !)';
						$sources[ 'page.' . $page->ID ] = $title;
					}
				}
		}
		$sources[''] = '(autre)';
		$fields[] = array('name' => $meta_name,
						'label' => __('Source des données', AGDP_TAG),
						'input' => 'select',
						'values' => $sources
		);
		return $fields;
				
	}
	
	
	public static function get_metabox_subscribers_fields(){
		$newsletter = get_post();
		$periods = AgendaPartage_Newsletter::subscription_periods($newsletter);
		$fields = [];
		foreach($periods as $period => $period_name){
			$meta_name = sprintf('next_date_%s', strtoupper( $period ));
			$fields[] = array('name' => $meta_name,
							'label' => __($period_name, AGDP_TAG),
							'input' => 'date'
			);
		}
		return $fields;
				
	}
	
	public static function get_metabox_subscribers(){
		$newsletter = get_post();
		$newsletter_id = $newsletter->ID;
		$periods = AgendaPartage_Newsletter::subscription_periods($newsletter);
		$subscription_meta_key = AgendaPartage_Newsletter::get_subscription_meta_key($newsletter);
		$mailing_meta_key = AgendaPartage_Newsletter::get_mailing_meta_key($newsletter);
		$today = strtotime(wp_date('Y-m-d'));
		
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		$user_prefix = $wpdb->get_blog_prefix( 1 );
		
		foreach($periods as $period => $period_name){
			$periods[$period] = array(
					'name' => $period_name
					, 'subscribers_count' => 0
					, 'subscribers' => []
					, 'mailing' => []
				);
		}
		/** En attente d'envoi **/
		$has_subscribers = false;
		foreach(['aujourd\'hui' => 0, 'demain' => strtotime(wp_date('Y-m-d') . ' + 1 day')]
			as $date_name => $date){
			$subscribers = AgendaPartage_Newsletter::get_today_subscribers($newsletter, $date);
			if($subscribers){
				$has_subscribers = true;
				echo sprintf('<div><h3 class="%s">%d abonné.e(s) en attente d\'envoi <u>%s</u></h3>'
					, $date === 0 ? 'alert' : 'info'
					, count($subscribers)
					, $date_name
				);
				foreach(array_slice($subscribers, 0, 20) as $user /* => $data */){
					if( isset($periods[$user->period]) )
						$period_name = $periods[$user->period]['name'];
					else
						$period_name =  AgendaPartage::icon('warning', $user->period . ' ?!');
					echo sprintf("<a href='/wp-admin/user-edit.php?user_id=%d' title=\"%s\">%s</a> (%s), "
								, $user->ID, $user->user_nicename, $user->user_email, $period_name);
						
				}
				echo '</div>';
			}
		}
		if($has_subscribers)
			echo '<hr>';
		
		/** Nombre d'abonnés **/
		$sql = "SELECT usermeta.meta_value AS period, COUNT(usermeta.umeta_id) AS count"
			. "\n FROM {$user_prefix}users user"
			. "\n INNER JOIN {$user_prefix}usermeta usermeta"
			. "\n ON user.ID = usermeta.user_id"
			. "\n AND usermeta.meta_key = '{$subscription_meta_key}'"
			// . "\n INNER JOIN {$user_prefix}usermeta usermetacap"
			// . "\n ON user.ID = usermetacap.user_id"
			// . "\n AND usermetacap.meta_key = '{$blog_prefix}capabilities'"
			// . "\n AND usermetacap.meta_value != 'a:0:{}'"
			. "\n GROUP BY usermeta.meta_value";
		$sql .= " UNION ";
		$sql .= "SELECT '0', COUNT(ID) AS count"
			. "\n FROM {$user_prefix}users user"
			. "\n INNER JOIN {$user_prefix}usermeta usermetacap"
			. "\n ON user.ID = usermetacap.user_id"
			. "\n AND usermetacap.meta_key = '{$blog_prefix}capabilities'"
			. "\n AND usermetacap.meta_value LIKE '%subscriber%'"
			. "\n LEFT JOIN {$user_prefix}usermeta usermeta"
			. "\n ON user.ID = usermeta.user_id"
			. "\n AND usermeta.meta_key = '{$subscription_meta_key}'"
			. "\n WHERE usermeta.meta_key IS NULL";
		$dbresults = $wpdb->get_results($sql);
		foreach($dbresults as $dbresult)
			if(isset($periods[$dbresult->period]))
				$periods[$dbresult->period]['subscribers_count'] = $dbresult->count;
		
		/** Liste d'abonnés **/
		$sql = "SELECT usermeta.meta_value AS period, user.ID, user.user_email, user.user_nicename"
			. "\n FROM {$user_prefix}users user"
			// . "\n INNER JOIN {$user_prefix}usermeta usermetacap"
			// . "\n ON user.ID = usermetacap.user_id"
			// . "\n AND usermetacap.meta_key = '{$blog_prefix}capabilities'"
			// . "\n AND usermetacap.meta_value != 'a:0:{}'"
			. "\n INNER JOIN {$user_prefix}usermeta usermeta"
			. "\n ON user.ID = usermeta.user_id"
			. "\n WHERE usermeta.meta_key = '{$subscription_meta_key}'"
			. "\n ORDER BY user.user_email"
			. "\n LIMIT 50";
		$dbresults = $wpdb->get_results($sql);
		foreach($dbresults as $dbresult)
			if(isset($periods[$dbresult->period]))
				$periods[$dbresult->period]['subscribers'][] = $dbresult;
			
		echo sprintf("<ul>");
		 // var_dump($periods);
		foreach($periods as $period => $data){
			echo sprintf("<li><h3><u>%s</u> : %d %s</h3>"
				, $data['name']
				, $data['subscribers_count']
				, $period === 'none' ? ' non-abonné.e(s)' : ' abonné.e(s)'
				);
			
			if($period !== 'none'){
				$meta_name = sprintf('next_date_%s', strtoupper( $period ));
				echo '<ul>';
				// if(count($data['mailing']) == 0){
					$next_date = get_post_meta($newsletter_id, $meta_name, true);
					if( $next_date)
						$next_date = strtotime($next_date);
					else
						$next_date = AgendaPartage_Newsletter::get_next_date($period, $newsletter);
					echo sprintf('<li>Prochain envoi : <input type="date" name="%s" value="%s"/></li>'
							, $meta_name, wp_date('Y-m-d', $next_date));
				// } else {
					// $now = time();
					// foreach($data['mailing'] as $mailing){
						// $mailing_date = strtotime($mailing->mailing_date);
						// if($mailing_date > $now)
							// echo sprintf('<li><input type="date" name="%s" value="%s"/> : %d inscrit(s)</li>'
								// , $meta_name, wp_date('Y-m-d', strtotime($mailing->mailing_date)), $mailing->count);
						// else
							// echo sprintf("<li>%s : %d envoi(s)</li>", $mailing->mailing_date, $mailing->count);
					// }
				// }
				echo '</ul>';
				echo '<div><code>';
				if(count($data['subscribers'])){
					$index = 0;
					foreach($data['subscribers'] as $user)
						echo sprintf("<a href='/wp-admin/user-edit.php?user_id=%d' title=\"%s\">%s</a>, ", $user->ID, $user->user_nicename, $user->user_email);
						if($index++ > 20){
							echo ', et plus...';
							break;
						}
				} else {
					echo '(aucun)';
				}
				echo '</code></div>';
			}
			echo '</li>';
		}
				
		/** Historique **/
		$two_months_before_mysql = wp_date('Y-m-d', strtotime(wp_date('Y-m-01', $today) . ' - 2 month'));
		$sql = "SELECT mailing.meta_value AS mailing_date, COUNT(user.ID) AS count"
			. "\n FROM {$user_prefix}users user"
			// . "\n INNER JOIN {$user_prefix}usermeta usermetacap"
			// . "\n ON user.ID = usermetacap.user_id"
			// . "\n AND usermetacap.meta_key = '{$blog_prefix}capabilities'"
			// . "\n AND usermetacap.meta_value != 'a:0:{}'"
			. "\n INNER JOIN {$user_prefix}usermeta mailing"
				. "\n ON user.ID = mailing.user_id"
				. "\n AND mailing.meta_key = '{$mailing_meta_key}'"
				. "\n AND mailing.meta_value >= '{$two_months_before_mysql}'"
			. "\n GROUP BY mailing.meta_value"
			. "\n ORDER BY mailing.meta_value DESC";
		$dbresults = $wpdb->get_results($sql);
		$mailings = [];
		$users = 0;
		foreach($dbresults as $dbresult){
			$mailings[] = ['date' => $dbresult->mailing_date, 'count' => $dbresult->count];
			$users += $dbresult->count;
		}
		if( count($mailings) ){
			echo sprintf("<li><h3><u>Envois depuis le %s</u> : %s %s</h3>"
				, wp_date('d/m/Y', strtotime($two_months_before_mysql))
				, $users
				, $users ? ' destinataire(s)' : '');
				
			echo '<ul>';
			foreach($mailings as $data)
				echo sprintf("<li>Lettre-info du %s : %d %s</h3>"
					, wp_date('d/m/Y', strtotime($data['date']))
					, $data['count']
					, $data['count'] ? ' destinataire(s)' : ''
					);
			echo '</ul>';
		}
	
		if( $post_type = AgendaPartage_Newsletter::get_newsletter_posts_post_type($newsletter) )
			if( $date = AgendaPartage_Newsletter::get_newsletter_posts_last_change($newsletter) ) 
				echo sprintf('<li><h3>Date du dernier changement parmi les %s : %s (%s)</h3></li>'
					, mb_strtolower( get_post_type_object( $post_type )->labels->name )
					, date_diff_text($date, true)
					, $date
				);

		echo '</ul>';
	}
	
	/**
	 * Callback lors de l'enregistrement du post.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function save_post_newsletter_cb ($newsletter_id, $newsletter, $is_update){
		if( $newsletter->post_status == 'trashed' ){
			return;
		}
		self::save_metaboxes($newsletter_id, $newsletter);
		self::send_test_email($newsletter_id, $newsletter, $is_update);
		self::set_next_cron_time($newsletter);
	}
	
	/**
	 * Met à jour le cron pour dans un délai de [mailing_loops_interval] minutes, au plus.
	 */
	public static function set_next_cron_time ($newsletter){
		if( ! AgendaPartage_Newsletter::is_active($newsletter) )
			return false;
		$mailing_loops_interval = get_post_meta($newsletter->ID, 'mailing-loops-interval', true);
		$next_time = $cron_time = strtotime( date('Y-m-d H:i:s') . ' + ' . ($mailing_loops_interval * 60) . ' second');
		$cron_time = AgendaPartage_Newsletter::get_cron_time();
		if($cron_time === 0
		|| $cron_time > $next_time)
			AgendaPartage_Newsletter::init_cron($next_time);
		return $cron_time;
	}
	
	/**
	 * Envoie un mail de test si demandé dans le $_POST.
	 */
	public static function send_test_email ($newsletter_id, $newsletter, $is_update){
		if( ! array_key_exists('send-nl-test', $_POST)
		|| ! $_POST['send-nl-test']
		|| ! array_key_exists('send-nl-test-email', $_POST))
			return;
		
		$email = sanitize_email($_POST['send-nl-test-email']);
		if( ! is_email($email)){
			AgendaPartage_Admin::add_admin_notice("Il manque l'adresse e-mail pour le test d'envoi.", 'error');
			return;
		}
		
		AgendaPartage_Newsletter::send_email($newsletter, [$email]);
			
	}
	
}
?>