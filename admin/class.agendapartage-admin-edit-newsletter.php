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
	static $the_post_is_new = false;

	public static function init() {

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
		add_meta_box('agdp_newsletter-subscribers', __('Abonnements', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Newsletter::post_type, 'normal', 'high');
	}

	/**
	 * Callback
	 */
	public static function metabox_callback($post, $metabox){
		//var_dump(func_get_args());
		
		switch ($metabox['id']) {
			
			case 'agdp_newsletter-subscribers':
				parent::metabox_html( self::get_metabox_subscribers_fields(), $post, $metabox );
				break;
			
			case 'agdp_newsletter-test':
				parent::metabox_html( self::get_metabox_test_fields(), $post, $metabox );
				break;
			
			default:
				break;
		}
	}

	public static function get_metabox_test_fields(){
		global $current_user;
		$newsletter = get_post();
		$newsletter_id = $newsletter->ID;
		$periods = AgendaPartage_Newsletter::subscribe_periods();
		$email = $current_user->user_email;
		echo sprintf('<label><input type="checkbox" name="send-nl-test">Envoyer la lettre-info pour test</label>');
		echo sprintf('<br><label>Destinataire(s) :</label><input type="email" name="send-nl-test-email" value="%s">', $email);
		
		return [];
	}
	
	public static function get_metabox_subscribers_fields(){
		$newsletter = get_post();
		$newsletter_id = $newsletter->ID;
		$periods = AgendaPartage_Newsletter::subscribe_periods();
		$subscription_meta_key = AgendaPartage_Newsletter::get_subscription_meta_key();
		$mailing_meta_key = AgendaPartage_Newsletter::get_mailing_meta_key();
		
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		
		
		foreach($periods as $period => $period_name){
			$periods[$period] = array(
					'name' => $periods[$period]
					, 'subscribers_count' => 0
					, 'subscribers' => []
					, 'mailing' => []
				);
		}
		
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
			. "\n ORDER BY user.user_email";
		$dbresults = $wpdb->get_results($sql);
		foreach($dbresults as $dbresult)
			if(isset($periods[$dbresult->period]))
				$periods[$dbresult->period]['subscribers'][] = $dbresult;
		
		/** Historique **/
		$sql = "SELECT SUBSTR(postmeta.meta_key, LENGTH('{$mailing_meta_key}_')) AS period"
			. ", postmeta.meta_value AS mailing_date"
			. ", COUNT(usermeta.umeta_id) AS count"
			. "\n FROM {$blog_prefix}postmeta postmeta"
			. "\n LEFT JOIN {$blog_prefix}usermeta usermeta"
			. "\n ON usermeta.meta_key = CONCAT(postmeta.meta_key, '_', postmeta.meta_value)"
			. "\n WHERE postmeta.post_id = {$newsletter_id}"
			. "\n AND postmeta.meta_key LIKE '{$mailing_meta_key}_%'"
			. "\n GROUP BY SUBSTR(postmeta.meta_key, LENGTH('{$mailing_meta_key}_')), postmeta.meta_value"
			. "\n ORDER BY mailing_date DESC";
		$dbresults = $wpdb->get_results($sql);
		foreach($dbresults as $dbresult)
			if(isset($periods[$dbresult->period]))
				$periods[$dbresult->period]['mailing'][] = ['date' => $dbresult->mailing_date, 'count' => $dbresult->count];
			
		echo sprintf("<ul>");
		// var_dump($periods);
		foreach($periods as $period => $data){
			echo sprintf("<li><h3>%s : %d %s</h3>"
				, $data['name']
				, $data['subscribers_count']
				, $period == 0 ? ' non-abonné.e(s)' : ' abonné.e(s)'
				);
			
			if($period !== 0){
				echo '<ul>';
				if(count($data['mailing']) == 0){
					$next_date = AgendaPartage_Newsletter::get_next_date($period);
					echo sprintf('<li>Prochain envoi : <input type="date" name="%s_%s" value="%s"/></li>'
							, $mailing_meta_key, $period, date('Y-m-d', $next_date));
				} else {
					$now = strtotime(date('Y-m-d H:i:s'));
					foreach($data['mailing'] as $mailing){
						$mailing_date = strtotime($mailing->mailing_date);
						if($mailing_date > $now)
							echo sprintf('<li><input type="date" name="%s_%s" value="%s"/> : %d inscrit(s)</li>'
								, $mailing_meta_key, $period, date('Y-m-d', strtotime($mailing->mailing_date)), $mailing->count);
						else
							echo sprintf("<li>%s : %d envoi(s)</li>", $mailing->mailing_date, $mailing->count);
					}
				}
				echo '</ul>';
				echo '<div><code>';
				if(count($data['subscribers'])){
					foreach($data['subscribers'] as $user)
						echo sprintf("<a href='/wp-admin/user-edit.php?user_id=%d' title=\"%s\">%s</a>, ", $user->ID, $user->user_nicename, $user->user_email);
				} else {
					echo '(aucun)';
				}
				echo '</code></div>';
			}
			echo '</li>';
		}
		echo '</ul>';
		
		return [];
	}
	
	/**
	 * Callback lors de l'enregistrement du post.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function save_post_newsletter_cb ($newsletter_id, $newsletter, $is_update){
		if( $newsletter->post_status == 'trashed' ){
			return;
		}
		// self::save_metaboxes($newsletter_id, $newsletter, $is_update);
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