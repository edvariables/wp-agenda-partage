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
			self::get_metabox_mailing_fields(),
			self::get_metabox_subscribers_fields(),
		);
	}	

	public static function get_metabox_test(){
		global $current_user;
		$newsletter = get_post();
		$newsletter_id = $newsletter->ID;
		$periods = AgendaPartage_Newsletter::subscribe_periods();
		$email = $current_user->user_email;
		echo sprintf('<label><input type="checkbox" name="send-nl-test">Envoyer la lettre-info pour test</label>');
		echo sprintf('<br><br><label>Destinataire(s) : </label><input type="email" name="send-nl-test-email" value="%s">', $email);
		
	}
	
	public static function get_metabox_mailing_fields(){
		$newsletters = AgendaPartage_Newsletter::get_active_newsletters();
		$newsletter = get_post();
		$newsletter_is_active = AgendaPartage_Newsletter::is_active($newsletter);
		if( (count($newsletters) > 1 && $newsletter_is_active)
		|| (count($newsletters) >= 1 && ! $newsletter_is_active) ){
			$many_active = 'Une autre lettre-info est active pour l\'envoi automatique.';
			foreach($newsletters as $post)
				if($newsletter->ID != $post->ID)
					$many_active .= sprintf(' <a href="%s">%s</a>', get_post_permalink( $post->ID),  $post->post_title);
		}
		else
			$many_active = false;
		if($newsletter->post_status != 'publish')
			$many_active .= ($many_active ? '<br>' : '') . 'Cet lettre-info n\'est pas enregistrée comme étant "Publiée", elle ne peut donc pas être automatisée.';
		
		$fields = [
			[ 
				'name' => 'mailing-enable',
				'label' => __('Activer l\'envoi automatique', AGDP_TAG),
				'input' => 'checkbox',
				'warning' => $many_active
			],
			[	'name' => 'sep',
				'input' => 'label'
			],
			[	'name' => 'mailing-month-day',
				'label' => __('Jour du mois', AGDP_TAG),
				'unit' => 'entre 1 et 28, pour l\'abonnement "Tous les mois"',
				'type' => 'number'
			],
			[	'name' => 'mailing-2W1-day',
				'label' => __('Jour de la 1ère quinzaine', AGDP_TAG),
				'unit' => 'entre 1 et 14, pour l\'abonnement "Tous les quinze jours"',
				'type' => 'number'
			],
			[	'name' => 'mailing-2W2-day',
				'label' => __('Jour de la 2ème quinzaine', AGDP_TAG),
				'unit' => 'entre 15 et 28, pour l\'abonnement "Tous les quinze jours"',
				'type' => 'number'
			],
			[	'name' => 'mailing-week-day',
				'label' => __('Jour de la semaine', AGDP_TAG),
				'unit' => 'pour l\'abonnement "Toutes les semaines"',
				'input' => 'select',
				'values' => [0=>'lundi', 1=>'mardi', 2=>'mercredi', 3=>'jeudi', 4=>'vendredi', 5=>'samedi', 6=>'dimanche']
			],
			[	'name' => 'mailing-hour',
				'label' => __('Heure d\'envoi', AGDP_TAG),
				'input' => 'time'
			],
			[	'name' => 'mailing-num-users-per-mail',
				'label' => __('Destinataires par e-mail', AGDP_TAG),
				'unit' => __('adresse(s) par e-mail', AGDP_TAG),
				'learn-more' => __('Si vous choississez plus d\'une adresse de destinataire par e-mail, elles seront en copie cachée et le destinataire principal sera l\'administrateur de ce site.', AGDP_TAG),
				'type' => 'number'
			],
			[	'name' => 'mailing-num-emails-per-loop',
				'label' => __('Par boucle de traitement', AGDP_TAG),
				'unit' => __('e-mail(s) envoyé(s)', AGDP_TAG),
				'learn-more' => __('Si vous choississez plusieurs destinataires par e-mail.', AGDP_TAG),
				'type' => 'number'
			],
			[	'name' => 'mailing-loops-interval',
				'label' => __('Interval de temps', AGDP_TAG),
				'unit' => __('minutes entre deux boucles', AGDP_TAG),
				'learn-more' => __('Le délai ne doit pas être trop petit. Le risque est d\'être considéré comme spammeur par l\'hébergeur du site.', AGDP_TAG),
				'type' => 'number'
			]
		];
		return $fields;
				
	}
	
	public static function get_metabox_subscribers_fields(){
		$periods = AgendaPartage_Newsletter::subscribe_periods();
		$fields = [];
		foreach($periods as $period => $period_name){
			$meta_name = sprintf('next_date_%s', $period);
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
		$periods = AgendaPartage_Newsletter::subscribe_periods();
		$subscription_meta_key = AgendaPartage_Newsletter::get_subscription_meta_key($newsletter);
		$mailing_meta_key = AgendaPartage_Newsletter::get_mailing_meta_key($newsletter);
		$today = strtotime(date('Y-m-d'));
		
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		
		
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
		foreach(['aujourd\'hui' => 0, 'demain' => strtotime(date('Y-m-d') . ' + 1 day')]
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
					echo sprintf("<a href='/wp-admin/user-edit.php?user_id=%d' title=\"%s\">%s</a> (%s), "
								, $user->ID, $user->user_nicename, $user->user_email, $periods[$user->period]['name']);
						
				}
				echo '</div>';
			}
		}
		if($has_subscribers)
			echo '<hr>';
		
		/** Nombre d'abonnés **/
		$sql = "SELECT usermeta.meta_value AS period, COUNT(usermeta.umeta_id) AS count"
			. "\n FROM {$blog_prefix}users user"
			. "\n INNER JOIN {$blog_prefix}usermeta usermeta"
			. "\n ON user.ID = usermeta.user_id"
			. "\n AND usermeta.meta_key = '{$subscription_meta_key}'"
			// . "\n INNER JOIN {$blog_prefix}usermeta usermetacap"
			// . "\n ON user.ID = usermetacap.user_id"
			// . "\n AND usermetacap.meta_key = '{$blog_prefix}capabilities'"
			// . "\n AND usermetacap.meta_value != 'a:0:{}'"
			. "\n GROUP BY usermeta.meta_value";
		$sql .= " UNION ";
		$sql .= "SELECT '0', COUNT(ID) AS count"
			. "\n FROM {$blog_prefix}users user"
			. "\n INNER JOIN {$blog_prefix}usermeta usermetacap"
			. "\n ON user.ID = usermetacap.user_id"
			. "\n AND usermetacap.meta_key = '{$blog_prefix}capabilities'"
			. "\n AND usermetacap.meta_value LIKE '%subscriber%'"
			. "\n LEFT JOIN {$blog_prefix}usermeta usermeta"
			. "\n ON user.ID = usermeta.user_id"
			. "\n AND usermeta.meta_key = '{$subscription_meta_key}'"
			. "\n WHERE usermeta.meta_key IS NULL";
		$dbresults = $wpdb->get_results($sql);
		foreach($dbresults as $dbresult)
			if(isset($periods[$dbresult->period]))
				$periods[$dbresult->period]['subscribers_count'] = $dbresult->count;
		
		/** Liste d'abonnés **/
		$sql = "SELECT usermeta.meta_value AS period, user.ID, user.user_email, user.user_nicename"
			. "\n FROM {$blog_prefix}users user"
			// . "\n INNER JOIN {$blog_prefix}usermeta usermetacap"
			// . "\n ON user.ID = usermetacap.user_id"
			// . "\n AND usermetacap.meta_key = '{$blog_prefix}capabilities'"
			// . "\n AND usermetacap.meta_value != 'a:0:{}'"
			. "\n INNER JOIN {$blog_prefix}usermeta usermeta"
			. "\n ON user.ID = usermeta.user_id"
			. "\n WHERE usermeta.meta_key = '{$subscription_meta_key}'"
			. "\n ORDER BY user.user_email"
			. "\n LIMIT 50";
		$dbresults = $wpdb->get_results($sql);
		foreach($dbresults as $dbresult)
			if(isset($periods[$dbresult->period]))
				$periods[$dbresult->period]['subscribers'][] = $dbresult;
		
		/** Historique **/
		//TODO cf get_today_subscribers
		$sql = "SELECT SUBSTR(nl_meta.meta_key, LENGTH('{$mailing_meta_key}_')) AS period"
			. ", nl_meta.meta_value AS mailing_date"
			. ", COUNT(usermeta.umeta_id) AS count"
			. "\n FROM {$blog_prefix}postmeta nl_meta"
			. "\n LEFT JOIN {$blog_prefix}usermeta usermeta"
			. "\n ON usermeta.meta_key = CONCAT(nl_meta.meta_key, '_', nl_meta.meta_value)"
			. "\n WHERE nl_meta.post_id = {$newsletter_id}"
			. "\n AND nl_meta.meta_key LIKE '{$mailing_meta_key}_%'"
			. "\n GROUP BY SUBSTR(nl_meta.meta_key, LENGTH('{$mailing_meta_key}_')), nl_meta.meta_value"
			. "\n ORDER BY mailing_date DESC";
		$dbresults = $wpdb->get_results($sql);
		foreach($dbresults as $dbresult)
			if(isset($periods[$dbresult->period]))
				$periods[$dbresult->period]['mailing'][] = ['date' => $dbresult->mailing_date, 'count' => $dbresult->count];
			
		echo sprintf("<ul>");
		// var_dump($periods);
		foreach($periods as $period => $data){
			echo sprintf("<li><h3><u>%s</u> : %d %s</h3>"
				, $data['name']
				, $data['subscribers_count']
				, $period === 0 ? ' non-abonné.e(s)' : ' abonné.e(s)'
				);
			
			if($period !== 0){
				$meta_name = sprintf('next_date_%s', $period);
				echo '<ul>';
				if(count($data['mailing']) == 0){
					$next_date = AgendaPartage_Newsletter::get_next_date($period, $newsletter);
					echo sprintf('<li>Prochain envoi : <input type="date" name="%s" value="%s"/></li>'
							, $meta_name, date('Y-m-d', $next_date));
				} else {
					$now = strtotime(date('Y-m-d H:i:s'));
					foreach($data['mailing'] as $mailing){
						$mailing_date = strtotime($mailing->mailing_date);
						if($mailing_date > $now)
							echo sprintf('<li><input type="date" name="%s" value="%s"/> : %d inscrit(s)</li>'
								, $meta_name, date('Y-m-d', strtotime($mailing->mailing_date)), $mailing->count);
						else
							echo sprintf("<li>%s : %d envoi(s)</li>", $mailing->mailing_date, $mailing->count);
					}
				}
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
	}
	
	/**
	 * Callback lors de l'enregistrement du post.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
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
		
		AgendaPartage_Newsletter::send_email($newsletter, 'test', [$email]);
			
	}
	
}
?>