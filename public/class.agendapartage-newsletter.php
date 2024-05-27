<?php
/**
 * AgendaPartage -> Newsletter
 * Custom post type for WordPress.
 * 
 * Définition du Post Type agdpnl
 * Mise en forme du formulaire Lettres-info par wpcf7
 * 	- Le html du wpcf7 est modifié
		- dans la base de données 
			- pour contenir tous les termes de taxonomies en bouton radio + en premier et masqué, un bouton radio (no_change)
		- au moment de l'affichage
			- masquer/afficher les onglets (administrateurice et forums) suivant l'utilisateur connecté
 *
 * Voir aussi AgendaPartage_Admin_Newsletter
 */
class AgendaPartage_Newsletter {

	const post_type = 'agdpnl';
	const taxonomy_period = 'period';

	private const cron_hook = 'agdpnl_cron_hook';
	private static $cron_hook = false;

	const default_mailing_hour = 2;
	
	// const user_role = 'author';

	private static $initiated = false;
	private static $sending_email = false;
	private static $sending_email_to = false;
	private static $sending_email_data = false;
	private static $content_is_empty = false;

	public static $cron_state = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;

			define('ALL_PERIODS', '*');
			define('PERIOD_DAYLY', 'd');
			define('PERIOD_WEEKLY', 'w');
			define('PERIOD_BIWEEKLY', '2w');
			define('PERIOD_MONTHLY', 'm');
			
			define('ANTERIORITY_ALL', ALL_PERIODS);
			define('ANTERIORITY_ONEDAY', PERIOD_DAYLY);
			define('ANTERIORITY_TWODAYS', '2'.ANTERIORITY_ONEDAY);
			define('ANTERIORITY_ONEWEEK', PERIOD_WEEKLY);
			define('ANTERIORITY_TWOWEEKS', '2'.PERIOD_WEEKLY);
			define('ANTERIORITY_THREEWEEKS', '3'.PERIOD_WEEKLY);
			define('ANTERIORITY_ONEMONTH', PERIOD_MONTHLY);
			define('ANTERIORITY_TWOMONTHS', '2'.ANTERIORITY_ONEMONTH);
			
			self::init_hooks();
		}
	}

	/**
	 * Hook
	 */
	public static function init_hooks() {
		
		AgendaPartage::add_action_on_queried_object( 'page', 'newsletter_subscribe_page_id', array(__CLASS__, 'on_page_newsletter_subscribe'), 10, 2 );
		
		add_filter( 'the_title', array(__CLASS__, 'the_title'), 10, 2 );
		
		add_action( 'wp_ajax_agdpnl_get_subscription', array(__CLASS__, 'on_wp_ajax_agdpnl_get_subscription') );
		add_action( 'wp_ajax_nopriv_agdpnl_get_subscription', array(__CLASS__, 'on_wp_ajax_agdpnl_get_subscription') );
		
		add_action( self::get_cron_hook(), array(__CLASS__, 'on_cron_exec') );
		
		self::init_cron(); //SIC : register_activation_hook( 'AgendaPartage_Newsletter', 'init_cron'); ne suffit pas. Pblm de multisite ?
	}
	
	public static function on_page_newsletter_subscribe( $page_id, $page ){
		add_filter( 'wpcf7_form_class_attr', array(__CLASS__, 'on_wpcf7_form_class_attr_cb'), 10, 1 ); 
		add_action( 'wp_enqueue_scripts', array(__CLASS__, 'register_plugin_js') ); 
		add_action('wp_head', array(__CLASS__, 'init_js_sections_tabs'));
	}
	/*
	 **/
	 
	public static function get_cron_hook(){
		if( self::$cron_hook )
			return self::$cron_hook;
		return self::$cron_hook = self::cron_hook . '.' . get_current_blog_id();
	}

	/**
	 * Registers js files.
	 */
	public static function register_plugin_js() {
		wp_enqueue_script("jquery-ui-tabs");
	}
	
	
	/***********
	 * the_title()
	 */
 	public static function the_title( $title, $post_id ) {
 		global $post;
 		if( ! $post
 		|| $post->ID != $post_id
 		|| $post->post_type != self::post_type){
 			return $title;
		}
	    return self::get_post_title( $post );
	}
 
 	/**
 	 * Retourne le titre de la page
 	 */
	public static function get_post_title( $newsletter = null, $no_html = false) {
 		if( ! isset($newsletter) || ! is_object($newsletter)){
			global $post;
			$newsletter = $post;
		}
		
		$post_title = isset( $newsletter->post_title ) ? $newsletter->post_title : '';
		//$separator = $no_html ? ', ' : '<br>';
		$html = do_shortcode($post_title);
		return $html;
	}
	/*
	 **********/
	 
	 /**
	  * Antériorité des messages
	  */
	public static function get_anteriorities(){
		return [
			ANTERIORITY_ALL => 'Tous',
			ANTERIORITY_ONEDAY => 'Un jour (24h)',
			ANTERIORITY_TWODAYS => 'Deux jours',
			ANTERIORITY_ONEWEEK => 'Une semaine',
			ANTERIORITY_TWOWEEKS => 'Deux semaines',
			ANTERIORITY_THREEWEEKS => 'Trois semaines',
			ANTERIORITY_ONEMONTH => 'Un mois',
			ANTERIORITY_TWOMONTHS => 'Deux mois',
		];
	}
	
	/**
	* Affecte l'option d'anteriorité
	*
	*/
	public static function filter_anteriority_option($options, $anteriority = false, $default_anteriority = ANTERIORITY_ALL, $newsletter = false){
		if( ! (isset($options['months']) || isset($options['weeks']) || isset($options['days']) || isset($options['hours'])) ){
			if( ! $anteriority ){
				if( ! $newsletter )
					$newsletter = AgendaPartage_Newsletter::get_newsletter();
				if( ! ($anteriority = AgendaPartage_Newsletter::get_anteriority_max($newsletter)) )
					$anteriority = $default_anteriority;
			}
			switch( $anteriority ){
				case ANTERIORITY_ALL:
					$options['months'] = 24;
					break;
				case ANTERIORITY_ONEDAY:
					$options['hours'] = 24;
					break;
				case ANTERIORITY_TWODAYS:
					$options['days'] = 2;
					break;
				case ANTERIORITY_ONEWEEK:
					$options['weeks'] = 1;
					break;
				case ANTERIORITY_TWOWEEKS:
					$options['weeks'] = 2;
					break;
				case ANTERIORITY_THREEWEEKS:
					$options['weeks'] = 3;
					break;
				case ANTERIORITY_ONEMONTH:
					$options['months'] = 1;
					break;
				case ANTERIORITY_TWOMONTHS:
					$options['months'] = 2;
					break;
				default:
					$options['months'] = 2; 
			}
		}
		return $options;
	}
	
	/**
	* Retourne la date correspondant au paramètre d'anteriorité.
	* Parameter $is_posterity : si true, retourne une date dans le futur, sinon dans le passé. (pour agdevents)
	*/
	public static function get_anteriority_date( $newsletter = false, $limit_date = false, $is_posterity = false ){
		if( ! $newsletter )
			$newsletter = AgendaPartage_Newsletter::get_newsletter();
		$anteriority = AgendaPartage_Newsletter::get_anteriority_max($newsletter);
		$direction = $is_posterity ? '+' : '-';
		switch( $anteriority ){
			case ANTERIORITY_ALL:
				$date = date('Y-m-d',strtotime($direction."2 Year"));
				break;
			case ANTERIORITY_ONEDAY:
				$date = date('Y-m-d',strtotime($direction."24 Hour"));
				break;
			case ANTERIORITY_TWODAYS:
				$date = date('Y-m-d',strtotime($direction."2 Day"));
				break;
			case ANTERIORITY_ONEWEEK:
				$date = date('Y-m-d',strtotime($direction."1 Week"));
				break;
			case ANTERIORITY_TWOWEEKS:
				$date = date('Y-m-d',strtotime($direction."2 Week"));
				break;
			case ANTERIORITY_THREEWEEKS:
				$date = date('Y-m-d',strtotime($direction."3 Week"));
				break;
			case ANTERIORITY_ONEMONTH:
				$date = date('Y-m-d',strtotime($direction."1 Month"));
				break;
			case ANTERIORITY_TWOMONTHS:
			default:
				$date = date('Y-m-d',strtotime($direction."2 Month"));
		}
		if( $limit_date
			&& ($is_posterity && ($limit_date < $date )
			 || ! $is_posterity && ($limit_date > $date ))
		){
			return $limit_date;
		}
		return $date;
	}
	 
	/**
	 * Retourne l'adresse d'expéditeur des mails 
	 */
	public static function get_mail_sender(){
		$email = get_bloginfo('admin_email');
		return $email;
	}
	 
	/**
	 * Retourne l'adresse à laquelle on envoie les mails groupés avec destinataires en 'bcc'
	 */
	public static function get_bcc_mail_sender(){
		$email = '';// self::get_mail_sender();
		return $email;
	}
	 
	/**
	 * Retourne l'adresse de réponse à laquelle on envoie les mails 
	 */
	public static function get_replyto_mail_sender(){
		
		if( $forum = self::get_forum_of_newsletter() ){
			$emails = AgendaPartage_Forum::get_forum_source_emails($forum);
			if( count($emails) )
				return $emails[0];
		}
		$email = self::get_mail_sender();
		return $email;
	}
	
	/**
	 * Retourne la source de données du contenu de la newsletter.
	 * Retourne un type de post (adgpevent, covoiturage) ou forum.{id} ou 'agdpstats'
	 */
	public static function get_content_source($newsletter_id, $explode = false){
		if( is_a($newsletter_id, 'WP_Post') )
			$newsletter_id = $newsletter_id->ID;
				
		$source = get_post_meta( $newsletter_id, 'source', true);
		
		if( $explode )
			return explode('.', $source);
	
		return $source;
	}
	
	/**
	 * Retourne l'antériorité maximale des messages à publier de la newsletter.
	 */
	public static function get_anteriority_max($newsletter_id){
		if( is_a($newsletter_id, 'WP_Post') )
			$newsletter_id = $newsletter_id->ID;
				
		return get_post_meta( $newsletter_id, 'anteriority_max', true);
	}
	
	/**
	 * Retourne les périodes d'abonnement possibles
	 */
	public static function subscription_periods($newsletter = false){
		//TODO cache
		if($newsletter === false){
			$terms = get_terms( array( 'taxonomy' => self::taxonomy_period, 'hide_empty' => false) );
		} else {
			$newsletter = self::get_newsletter($newsletter);
			$terms = wp_get_post_terms($newsletter->ID, self::taxonomy_period);
		
			if( ! $terms || count($terms) === 0)
				$terms = get_terms( array( 'taxonomy' => self::taxonomy_period,'hide_empty' => false) );
		
		}
		$periods = [];
		foreach($terms as $term_id => $term)
			if( is_a ($term, 'WP_Term') )
				$periods[$term->slug] = $term->name;
			else
				$periods[$term_id] = $term;
		// debug_log('$periods[$term_id] = $term', $periods, $terms);
		return $periods;
	}
	 
	/**
	 * Retourne les libellés des périodes d'abonnement possibles
	 */
	public static function subscription_period_name($period, $false_if_none = false){
		$periods = self::subscription_periods();
		if( isset($periods[$period]) )
			return $periods[$period];
		if($false_if_none)
			return false;
		foreach( $periods as $period => $label)
			return $label;
	}
	
	public static function get_subscription_parent($newsletter_id = false){
		if( is_a($newsletter_id, 'WP_Post') )
			$newsletter_id = $newsletter_id->ID;
		$meta_name = 'subscription_parent';
		if( $subscription_parent = get_post_meta($newsletter_id, $meta_name, true) )
			return self::get_newsletter($subscription_parent);
		return false;
	}
	
	public static function get_subscription_meta_key($newsletter = false){
		if( $subscription_parent = self::get_subscription_parent($newsletter) )
			$newsletter = $subscription_parent;
		else
			$newsletter = self::get_newsletter($newsletter);
		return sprintf('%s_subscr_%d_%d', self::post_type, get_current_blog_id(), $newsletter->ID);
	}
	
	public static function get_mailing_meta_key($newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		return sprintf('%s_mailing_%d_%d', self::post_type, get_current_blog_id(), $newsletter->ID);
	}
	
	/**
	 * Returns true if post_status == 'publish' && meta['mailing-enable'] == true
	 */
	 public static function is_active($newsletter){
		$newsletter = self::get_newsletter($newsletter);
		if( $newsletter->post_status !== 'publish')
			return false;
		return get_post_meta($newsletter->ID, 'mailing-enable', true) == 1;
	}
	
	/**
	 * Returns array of ID=>post_title
	 */
	 public static function get_newsletters_names(){
		$newsletters = [];
		foreach( get_posts([
			'post_type' => self::post_type
			, 'fields' => 'post_title'
			]) as $post)
			$newsletters[ $post->ID . '' ] = $post->post_title;
		return $newsletters;
	}
	
	/**
	 * Returns posts where post_status == 'publish' && meta['mailing-enable'] == true
	 */
	 public static function get_active_newsletters(){
		$posts = [];
		foreach( get_posts([
			'post_type' => self::post_type
			, 'post_status' => 'publish'
			, 'meta_key' => 'mailing-enable'
			, 'meta_value' => '1'
			, 'meta_compare' => '='
			]) as $post)
			$posts[$post->ID . ''] = $post;
			
		return $posts;
	}
	
	/**
	 * Retourne le type de posts concernés par la newsletter
	 */
	 public static function get_newsletter_posts_post_type($newsletter){
		switch($newsletter->ID){
			case AgendaPartage::get_option(AgendaPartage_Evenement::newsletter_option) :
				return AgendaPartage_Evenement::post_type;
			case AgendaPartage::get_option(AgendaPartage_Covoiturage::newsletter_option) :
				return AgendaPartage_Covoiturage::post_type;
			default:
				$source = self::get_content_source( $newsletter->ID, true);
				return $source[0];
		}
	 }
	/**
	 * Retourne la date du dernier post concerné par la newsletter
	 */
	 public static function get_newsletter_posts_last_change($newsletter){
		if( ! ($post_type = self::get_newsletter_posts_post_type($newsletter)) )
			return false;
		
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		if( $post_type == 'page'){
			//Comments
			$source = self::get_content_source($newsletter, true);
			$page_id = $source[1];
			$mailbox = AgendaPartage_Mailbox::get_mailbox_of_page( $page_id );
			if($mailbox && $page = get_post($page_id) ){
				//Synchronise avant de tester la date du dernier commentaire
				AgendaPartage_Mailbox::synchronize($mailbox, $page);
				$sql = "SELECT MAX(comments.comment_date) as date_max
						FROM {$blog_prefix}comments comments
						WHERE comments.comment_approved = 1
							AND comments.comment_post_ID = ". $page->ID ."
				";
			}
		}
		else {
			$sql = "SELECT MAX(posts.post_date) as date_max
					FROM {$blog_prefix}posts posts
					WHERE posts.post_status IN('publish', 'prepend')
						AND posts.post_type = '". $post_type ."'
			";
		}
		if( ! isset($sql) || ! $sql)
			throw new Exception(sprintf("Impossible de trouver l'origine des données pour %s.", $newsletter->post_title));
		$result = $wpdb->get_results($sql);
		if( is_a($result,'WP_Error') )
			throw new Exception(var_export($result, true));
		if(count($result))
			return $result[0]->date_max;
		return false;
	}
	
	/**
	 * Retourne l'heure d'envoi 
	 */
	public static function get_mailing_hour($newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		
		$meta_name = 'mailing-hour';
		$value = get_post_meta($newsletter->ID, $meta_name, true);
		if($value)
			return (int)$value;
		return self::default_mailing_hour;
	}
	
	/**
	 * Enregistre la prochaine date d'envoi pour une période donnée
	 *
	 * $period : false|M|2W|W
	 *     si false, cherche toutes les périodes dont la prochaine date est aujourd'hui ou avant
	 */
	public static function set_next_date($period, $newsletter, $next_date){
		if( $period === 'none')
			throw new Exception('set_next_date("none") ne devrait pas être.');
		
		if( ! $period){
			$today = strtotime(wp_date('Y-m-d'));
			foreach(self::subscription_periods($newsletter) as $period=>$period_name)
				if( $period && $period !== 'none'){
					if( self::get_next_date( $period, $newsletter ) <= $today)
						self::set_next_date($period, $newsletter, $next_date);
				}
			return;
		}
		$newsletter = self::get_newsletter($newsletter);
		$meta_name = sprintf('next_date_%s', $period);
		if( is_int($next_date) )
			$next_date = date('Y-m-d', $next_date);
		update_post_meta($newsletter->ID, $meta_name, $next_date);
	}
	
	/**
	 * Retourne la prochaine date d'envoi pour chaque période.
	 */
	public static function get_periods_next_dates($newsletter = false, $after_date = false){
		$newsletter = self::get_newsletter($newsletter);
		if( ! $newsletter )
			return false;
		$periods_next_dates = [];
		$min_date = 0;
		foreach(self::subscription_periods($newsletter) as $period=>$period_name)
			if( $period && $period !== 'none'){
				$period_next_date = self::get_next_date($period, $newsletter, $after_date);
				$periods_next_dates[$period] = $period_next_date;
				if( $min_date === 0 )
					$min_date = $period_next_date;
				else
					$min_date = min( $min_date, $period_next_date);
			}
		$periods_next_dates[ALL_PERIODS] = $min_date;
		return $periods_next_dates;
	}
	/**
	 * Retourne la prochaine date d'envoi pour une période donnée
	 */
	public static function get_next_date($period = false, $newsletter = false, $after_date = false){
		$newsletter = self::get_newsletter($newsletter);
		if( ! $newsletter )
			return false;
		if( ! $period ){
			$min_date = 0;
			$periods_next_dates = self::get_periods_next_dates($newsletter, $after_date);
			return $periods_next_dates[ALL_PERIODS];
		}
		
		if( $after_date )
			$today = strtotime(wp_date('Y-m-d', $after_date) . ' + 1 day');
		else
			$today = strtotime(wp_date('Y-m-d'));

		if( ! $after_date ){
			$meta_name = sprintf('next_date_%s', $period);
			$value = get_post_meta($newsletter->ID, $meta_name, true);
			if($value){
				$value = strtotime($value);
				if($value >= $today)
					return $value;
			}
		}
		
		switch($period){
				
			case PERIOD_DAYLY:
				return $today;
				
			case PERIOD_MONTHLY:
				$month_day = get_post_meta($newsletter->ID, 'mailing-month-day', true);
				if( ! is_int($month_day) )
					return $today;
				if(wp_date('d', $today) > $month_day){
					if($month_day > 28
					&& wp_date('m', strtotime(wp_date('Y-m-' . $month_day, $today)) )
						!=  wp_date('m', $today) )
						$date = strtotime(wp_date('Y-m-d', $today));
					else
						$date = strtotime(wp_date('Y-m-d', $today) . ' + 28 day');
					return strtotime(wp_date('Y-m-' . $month_day, $date));
				}
				return strtotime(wp_date('Y-m-' . $month_day, $today));
				
			case PERIOD_BIWEEKLY:
				//TODO et le 28 ?
				$today_month_day = wp_date('d', $today);
				$half2_month_day = get_post_meta($newsletter->ID, 'mailing-2W2-day', true);
				$half1_month_day = get_post_meta($newsletter->ID, 'mailing-2W1-day', true);
				if( $half1_month_day === '' || $half2_month_day === '')
					return $today;
				if($today_month_day > $half2_month_day){
					$date = strtotime(wp_date('Y-m-d', $today) . ' + 28 day');
					return strtotime(wp_date('Y-m-' . $half1_month_day, $date));
				}
				if($today_month_day > $half1_month_day)
					return strtotime(wp_date('Y-m-' . $half2_month_day, $today));
				return strtotime(wp_date('Y-m-' . $half1_month_day, $today));
				
			case PERIOD_WEEKLY:
				$week_day = get_post_meta($newsletter->ID, 'mailing-week-day', true);
				if( ! is_int($week_day) )
					return $today;
				$today_week_day = wp_date('w', $today);
				if( $week_day >= $today_week_day )
					$date = strtotime(wp_date('Y-m-d', $today) . ' + ' . ($week_day - $today_week_day) . ' day');
				else
					$date = strtotime(wp_date('Y-m-d', $today) . ' + ' . ($week_day - $today_week_day + 7) . ' day');
			
				return $date;
				
			default:
				return null;
		}
	}
	
	/**
	 * Initialisation javascript des onglets
	 */
	public static function init_js_sections_tabs() {
		if( $default_tab = self::get_newsletter() ){
			$field_extension = self::get_form_newsletter_field_extension($default_tab);
			$input_name = 'nl-period-' . $field_extension;
			$default_tab = $default_tab->ID;
		}
		else
			$default_tab = $input_name = 0;
				
		?><script type="text/javascript">
	jQuery(document).ready(function(){
		jQuery('form div.agdp-tabs-wrap:first').each(function(){
			var $wrapper = jQuery(this);
			var id = 'agdp-tabs-' + Math.floor( Math.random()*1000);
			var class_name = 'agdp-tabs';
			var tabs_counter = 0;
			var $tabs_contents = [];
			var $tabs = jQuery('<div class="' + class_name + '"/>');
			var $nav = jQuery('<ul class="' + class_name + '-nav"/>').appendTo($tabs);
			var $contents = jQuery('<ul/>').appendTo($tabs);
			var default_tab_input_name = '<?=$input_name?>';
			var default_tab = 0;
			jQuery(this).find('div.agdp-tabs-wrap > h2').each(function(){
				var $hidden_parent;
				if( ($hidden_parent = jQuery(this).parents('.hidden:first')).length ){
					$hidden_parent.insertAfter($wrapper);
					return;
				}
				tabs_counter++;
				$nav.append('<li><a href="#' + id + '-' + tabs_counter + '">' + this.innerText + '</a></li>');
				var $content = jQuery('<div id="' + id + '-' + tabs_counter + '" class="agdp-panel"><div/>');
				jQuery(this).parent().children().appendTo($content);
				$contents.append($content);
				if( default_tab_input_name && $content.find( '[data-name=' + default_tab_input_name + ']').length )
					default_tab = tabs_counter - 1;
			});
			$wrapper.html( $tabs.tabs({
				  active: default_tab
			}));
		});
	});</script>
		<?php
	}

	/**
	 * Interception du formulaire avant que les shortcodes ne soient analysés.
	 * Affectation des valeurs par défaut.
	 */
 	public static function on_wpcf7_form_class_attr_cb( $form_class ) { 
		$form = WPCF7_ContactForm::get_current();
		switch($form->id()){
			case AgendaPartage::get_option('newsletter_subscribe_form_id') :
				self::wpcf7_newsletter_form_init_tags( $form );
				$form_class .= ' preventdefault-reset events_newsletter_register';
				break;
			default:
				break;
		}
		return $form_class;
	}
	
 	private static function wpcf7_newsletter_form_init_tags( $form ) { 
		// $current_nl = self::get_newsletter();
		// $current_nl_id = $current_nl ? $current_nl->ID : 0;
		
		$form = WPCF7_ContactForm::get_current();
		$html = $form->prop('form');//avec shortcodes du wpcf7
		
		$email = self::get_email();
		
		$newsletters = self::get_newsletters();
		
		$html = self::init_wpcf7_form_html( $html, $newsletters, $email );
		
		$option_id = 'admin_nl_post_id';
		if( ! isset($newsletters[$option_id]) ){
			//Hide tab (javascript skips hidden elements)
			$html = preg_replace('/\<div\s*class="admin-user-only/'
					, '$0 hidden'
					, $html);
		}
		
		//$hidden_newsletters : Selon le droit de certains forums, il faut être connecté et membre pour voir certains onglets
		if( ! current_user_can('moderate_comments') ){
			global $current_user;
			foreach( $newsletters as $newsletter_id => $newsletter)
				if( is_int($newsletter_id)
				&& ( $forum = self::get_forum_of_newsletter($newsletter_id) )){
					$approved = AgendaPartage_Forum::get_forum_comment_approved( $forum, $current_user, false );
					if( $approved !== 1 ){
						$field_extension = self::get_form_newsletter_field_extension($newsletter_id, $forum);
						//Hide tab (javascript skips hidden elements)
						$html = preg_replace('/\<div\s*class="[^"]*'.$field_extension.'/'
								, '$0 hidden'
								, $html);
						
					}
				}
		}
		
		/** email **/
		$input_name = 'nl-email';
		if($email){
			$html = preg_replace('/\[(((email|text)\*?)\s+'.$input_name.'[^\]]*)[\]]/'
								, sprintf('[$1 value="%s"]'
									, $email)
								, $html);
		}
		
		/** Create account **/
		if( self::get_current_user()){
			$html = preg_replace('/\<div\s+class="if-not-connected"/'
								, '$0 style="display: none"'
								, $html);
		}
		
		/** reCaptcha */
		if( AgendaPartage_WPCF7::may_skip_recaptcha() ){
			//TODO
			// $html = preg_replace('/\[recaptcha[^\]]*[\]]/'
								// , ''
								// , $html);
		}
				
		$form->set_properties(array('form'=>$html));
				
	}
	
	/**
	 * Complète le html d'un formulaire WPCF7 avec les radios et checkboxes à jour en fonction des taxonomies
	 * Si $post est fourni, modifie les valeurs sélectionnées.
	 */
 	public static function init_wpcf7_form_html( $html, $newsletters = false, $email = false ) { 
	
		if( ! $newsletters )
			$newsletters = self::get_newsletters();
		
		// foreach newsletter type (agdpevent, covoiturage, forums, admin)
		foreach($newsletters as $newsletter_option => $newsletter){
			$field_extension = self::get_form_newsletter_field_extension($newsletter_option);
			/** périodicité de l'abonnement **/
			$input_name = 'nl-period-' . $field_extension;
			$subscription_periods = self::subscription_periods($newsletter);
			$user_subscription = isset($_REQUEST[$input_name]) ? $_REQUEST[$input_name] : null;
			
			if(isset($_REQUEST['action']))
				switch($_REQUEST['action']){
					case 'unsubscribe':
					case 'desinscription':
						$user_subscription = 'none';
						$subscription_periods[$user_subscription] = 'Désinscription à valider';
						break;
					default:
						break;
				}
			if( $user_subscription === null && $email)
				$user_subscription = self::get_subscription($email, $newsletter);
			if( ! $user_subscription)
				$user_subscription = 'none';
			
			$checkboxes = sprintf('"%s"', AGDP_WPCF7_RADIO_NO_CHANGE);//option masquée
			$selected = '';
			$index = 1;
			foreach( $subscription_periods as $subscribe_code => $label){
				$checkboxes .= sprintf(' "%s|%s"', $label, $subscribe_code);
				if($user_subscription == $subscribe_code){
					$selected = sprintf('default:%d', $index+1);
				}
				$index++;
			} 
			if( ! $selected)
				$selected = 'default:1'; //(no-change) hidden option
		
			$html = preg_replace('/\[(radio\s+'.$input_name.')[^\]]*[\]]/'
								, sprintf('[$1 %s use_label_element %s]'
									, $selected
									, $checkboxes)
								, $html);
		}
		
		//Administrateurice masqué : wpcf7 râle car aucune option sélectionnée. On en ajoute une, cochée : AGDP_WPCF7_RADIO_NO_CHANGE.
		$option_id = 'admin_nl_post_id';
		if( ! isset($newsletters[$option_id]) ){
			$field_extension = self::get_form_newsletter_field_extension($option_id);
			$input_name = 'nl-period-' . $field_extension;
			$selected = sprintf('default:1');
			$html = preg_replace('/(\[radio\s+'.$input_name.'\s[^\]]*)(default:[0-9]+)/'
								, '$1'.$selected
								, $html);
		}
		
		return $html;
	}
	
	/**
	 * utilisateur
	 */
	public static function get_current_user(){
		$current_user = wp_get_current_user();
		if($current_user && $current_user->ID){
			return $current_user;
		}
		return false;
	}
	public static function get_user_email(){
		$current_user = self::get_current_user();
		if($current_user){
			$email = $current_user->data->user_email;
			if(is_email($email))
				return $email;
		}
		return false;
	}
	public static function get_email(){
		if(isset($_REQUEST['email'])){
			$email = trim($_REQUEST['email']);
			if(is_email($email))
				return $email;
		}
		return self::get_user_email();
	}
	
	public static function get_newsletter($newsletter = false){
		if( ! $newsletter || $newsletter === true){
			$newsletter = self::is_sending_email();
			if( ! $newsletter
			 && empty($_REQUEST[AGDP_ARG_NEWSLETTERID])){
				if( empty($_REQUEST['post_ID'])){
					if( ! ($newsletter = get_post())
					|| $newsletter->post_type !== self::post_type){
						debug_log_callstack('get_newsletter !?', 'default newsletter : events_nl_post_id');
						$newsletter = AgendaPartage::get_option('events_nl_post_id');
					}
				}
				else
					$newsletter = get_post($_REQUEST['post_ID']);
			}
		}
		if(is_a($newsletter, 'WP_Post')
		&& $newsletter->post_type === self::post_type)
			return $newsletter;
		//slug
		if( is_string($newsletter) && ! is_numeric($newsletter) ){
			if ( $posts = get_posts( array( 
				'name' => $newsletter, 
				'post_type' => self::post_type,
				'posts_per_page' => 1
			) ) )
				return $posts[0];
			else
				return false;
		}
		
		$newsletter = get_post($newsletter);
		if(is_a($newsletter, 'WP_Post')
		&& $newsletter->post_type == self::post_type)
			return $newsletter;
		return false;
	}
	
	
	/**
	 * Retourne le forum associé à une newsletter.
	 */
	public static function get_forum_of_newsletter($newsletter_id = false){
		if( ! $newsletter_id )
			$newsletter_id = self::get_newsletter();
		if( is_a($newsletter_id, 'WP_Post') ){
			if($newsletter_id->post_type != AgendaPartage_Newsletter::post_type)
				return false;
			$newsletter_id = $newsletter_id->ID;
		}
		
		if( $source = self::get_content_source($newsletter_id, true)){
			if( $source[0] === 'page' ){
				if( $forum = get_post( $source[1] )){
					return $forum;
				}
			}
		}
		return false;
	}
	
	/**
	 * Les champs du formulaire contiennent une extension correspondant au type de posts associés à la lettre-info ('-admin', '-agdpevent', '-covoiturage')
	 */
	public static function get_form_newsletter_field_extension($newsletter_option, $forum = false){
		if( is_a($forum, 'WP_Post') )
			return AgendaPartage_Forum::tag . '-' . $forum->post_name;
			
		if(is_a($newsletter_option, 'WP_Post')){
			$newsletter = $newsletter_option;
			
			// TODO if( $source = self::get_content_source($newsletter->ID, true) )
				// return 
			$newsletter_option = false;
			foreach(['events_nl_post_id', 'covoiturages_nl_post_id', 'admin_nl_post_id'] as $option)
				if( $newsletter->ID == AgendaPartage::get_option($option) ){
					$newsletter_option = $option;
					break;
				}
			if( ! $newsletter_option){
				if( ($source = self::get_content_source($newsletter->ID, true))
				&& count($source) > 1
				&& ($forum = get_post($source[1])) ){
					return AgendaPartage_Forum::tag . '-' . $forum->post_name;
				}
				throw new Exception('L\'argument $newsletter_option contient une référence inconnue : ' . var_export($newsletter_option, true));
			}
		}
		//Numérique : un forum dont on cherche le nom
		if( is_numeric($newsletter_option) ){
			if( $forum = self::get_forum_of_newsletter( $newsletter_option ) )
				return AgendaPartage_Forum::tag . '-' . $forum->post_name;
		}
		
		switch($newsletter_option){
			case 'admin_nl_post_id' :
				return 'admin';
			case 'events_nl_post_id' :
				return AgendaPartage_Evenement::post_type;
			case 'covoiturages_nl_post_id' :
				return AgendaPartage_Covoiturage::post_type;
			default:
				throw new Exception('La variable $newsletter_option contient une valeur inconnue : ' . var_export($newsletter_option, true));
		}
	}
	
	public static function get_newsletters(){
		$newsletters = array();
		
		$option_id = 'events_nl_post_id';
		$newsletters[ $option_id ] = get_post(AgendaPartage::get_option($option_id));
		
		$option_id = 'covoiturages_nl_post_id';
		$newsletters[ $option_id ] = get_post(AgendaPartage::get_option($option_id));
		
		//Forums
		$all_newsletters = get_posts([
			'post_type' => self::post_type,
			'post_status' => 'publish',
			'meta_query' => [[
				'key' => 'source',
				'value' => 'page.',
				'compare' => 'LIKE'
			]]
		]);
		foreach($all_newsletters as $forum_newsletter){
			$nl_id = $forum_newsletter->ID;
			$newsletters[ $nl_id . '' ] = $forum_newsletter;
		}
		
		/** admin **/
		if( current_user_can('manage_options')){
			$option_id = 'admin_nl_post_id';
			$newsletters[ $option_id ] = get_post(AgendaPartage::get_option($option_id));
		}
		
		return $newsletters;
	}
	
	/**
	 * Crée un utilisateur si nécessaire.
	 */
	public static function create_subscriber_user($email, $user_name, $send_email = true){
		
		$user_id = email_exists( $email );
		if($user_id){
			return new WP_User($user_id);
		}
		
		$user = AgendaPartage_User::create_user($email, $user_name, false, false, 'subscriber');
		
		if( ! $user)
			return false;

		if($send_email)
			$html = AgendaPartage_User::send_welcome_email($user, false, false, true);
		return $user;
	}
	
	/**
	 * Option d'abonnement de l'utilisateur
	 */
	public static function get_user_subscription(){
		return self::get_subscription(self::get_user_email());
	}
	
	/**
	 * Retourne le meta value d'abonnement pour l'utilisateur
	 */
	public static function get_subscription( $email, $newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		
		$user_id = email_exists( sanitize_email($email) );
		if( ! $user_id)
			return false;
		
		$meta_name = self::get_subscription_meta_key($newsletter);
		$meta_value = get_user_meta($user_id, $meta_name, true);
		return $meta_value;
	}
	/**
	 * Supprime le meta value d'abonnement pour l'utilisateur
	 */
	public static function remove_subscription($email, $newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		
		$user_id = email_exists( $email );
		if( ! $user_id)
			return true;
		
		$meta_name = self::get_subscription_meta_key($newsletter);
		delete_user_meta($user_id, $meta_name, null);
		//TODO remove user si jamais connecté
		
		return true;
	}
	/**
	 * Ajoute ou met à jour le meta value d'abonnement pour l'utilisateur
	 */
	public static function update_subscription($email, $period, $newsletter = false){
		$newsletter = self::get_newsletter($newsletter);
		
		$user_id = email_exists( $email );
		if( ! $user_id){
			if( ! $period || $period == 'none')
				return true;
			$user = self::create_subscriber_user($email, false, false);
			if( ! $user )
				return false;
			$user_id = $user->ID;
		}
		$meta_name = self::get_subscription_meta_key($newsletter);
		update_user_meta($user_id, $meta_name, $period);
		return true;
	}
	
	 
	/**
	 * Répond à une requête ajax sur l'abonnement d'une adresse email.
	 * cf public/js/agendapartage.js
	 */
	public static function on_wp_ajax_agdpnl_get_subscription(){
		
		if( ! AgendaPartage::check_nonce())
			wp_die();
		
		$ajax_response = '0';
		if(array_key_exists("email", $_POST)){
			if( $is_user = email_exists($_POST['email'])){
				if( ! self::current_user_can_change_subscription($_POST['email']) ){
					wp_send_json('Vous n\'avez pas l\'autorisation de modifier cet utilisateur. Veuillez vous connecter avec cette adresse email.');
					wp_die();
				}
			}
			$subscriptions = array();
			foreach( self::get_newsletters() as $newsletter_option => $newsletter){
				$subscription = self::get_subscription($_POST['email'], $newsletter);
				if($subscription !== false)
					$subscriptions[$newsletter_option] = [
						'subscription' => $subscription
						, 'subscription_name' => self::subscription_period_name($subscription)
						, 'field_extension' => self::get_form_newsletter_field_extension($newsletter_option)
					];
			}
			$ajax_response = $subscriptions;
			$ajax_response['is_user'] = $is_user;
		}
		
		wp_send_json($ajax_response);
		
		wp_die();
	}
	
	/**
	 * Ajoute ou met à jour le meta value de l'utilisateur de l'état de l'envoi
	 */
	public static function set_user_mailing_status($newsletter, $user_id, $status, $mailing_status_meta_name = false){
		if(is_array($user_id)){
			$mailing_status_meta_name = self::get_mailing_meta_key($newsletter) . '-status';
			foreach($user_id as $single_user_id){
				if( is_a($single_user_id, 'stdClass') )
					$single_user_id = $single_user_id->ID;
				self::set_user_mailing_status($newsletter, $single_user_id, $status, $mailing_status_meta_name);
			}
			return;
		}
			
		if(is_email($user_id))
			$user_id = email_exists($user_id);
		if( ! $mailing_status_meta_name )
			$mailing_status_meta_name = self::get_mailing_meta_key($newsletter) . '-status';
		update_user_meta($user_id, $mailing_status_meta_name, $status);
				
	}
	/**
	 * Supprime le meta value de l'utilisateur de l'état de l'envoi
	 */
	public static function delete_user_mailing_status($newsletter, $user_id, $mailing_status_meta_name = false){
		if(is_array($user_id)){
			$mailing_status_meta_name = self::get_mailing_meta_key($newsletter) . '-status';
			foreach($user_id as $single_user_id)
				self::delete_user_mailing_status($newsletter, $single_user_id, $status, $mailing_status_meta_name);
			return;
		}
		if(is_email($user_id))
			$user_id = email_exists($user_id);
		if( ! $mailing_status_meta_name )
			$mailing_status_meta_name = self::get_mailing_meta_key($newsletter) . '-status';
		delete_user_meta($user_id, $mailing_status_meta_name);
				
	}
	/**
	 * Retourne pour un utilisateur l'historique d'envoi d'une newsletter 
	 */
	public static function get_user_mailings($email, $newsletter_id = false){
		
		$user_id = email_exists( $email );
		if( ! $user_id)
			return;
		
		$user_metas = get_user_meta($user_id, '', true);
		
		$meta_key_like = sprintf('%s_mailing_%d_', self::post_type, get_current_blog_id());
		$history = [];
		if($newsletter_id){
			$newsletter = self::get_newsletter($newsletter_id);
			$newsletters = [$newsletter->ID => $newsletter->post_title];
		}
		else
			$newsletters = self::get_newsletters_names();
		foreach($user_metas as $meta_key => $meta_value)
			if(str_starts_with($meta_key, $meta_key_like)){
				$newsletter_id = substr($meta_key, strlen($meta_key_like));
				if ( ! empty($newsletters[$newsletter_id]) )
					$history[ sprintf('%d|%s', $newsletter_id, $newsletters[$newsletter_id]) ] 
						= is_array($meta_value) ? implode(', ', $meta_value) : $meta_value;
			}
		return array_reverse($history, true);//TODO sort
	}
	/**
	 * Retourne pour un ou plusieurs utilisateurs la date de dernier envoi.
	 */
	public static function get_user_mailing_date($email, $newsletter, $min_date = false){
		
		if( is_array($email) ){
			$users_min_date = false;
			foreach($email as $single_email){
				$date = self::get_user_mailing_date($single_email, $newsletter);
				if ( $date !== false
				&& ( $users_min_date === false || $date < $users_min_date )
				&& ( ! $min_date || $date >= $min_date )
				)
					$users_min_date = $date;
			}
			return $users_min_date;
		}
		
		$user_id = email_exists( $email );
		if( ! $user_id)
			return false;
		
		$meta_key = sprintf('%s_mailing_%d_%d', self::post_type, get_current_blog_id(), $newsletter->ID);
		
		$date = get_user_meta($user_id, $meta_key, true);
		
		if( ! $date )
			return false;
		if( ! $min_date || $date >= $min_date )
			return $date;
		return $min_date;
	}
	
	/***********************************************************/

	/**
	 * Mise à jour ou création des abonnements aux newsletters
	 */
	public static function submit_subscription_form($contact_form, &$abort, $submission){
		$error_message = false;
		
		$inputs = $submission->get_posted_data();
		// nl-email, nl-period-{newsletter_option}, nl-create-user, nl-user-name
				
		$newsletters = self::get_newsletters();
		
		$email = sanitize_email( $inputs['nl-email'] );
		if(! is_email($email)){
			$abort = true;
			$error_message = sprintf('Désolé, nous ne reconnaissons pas le format de votre adresse (%s).', $inputs['nl-email']);
			$submission->set_response($error_message);
			return false;
		}
		
		/** create user */
		$user_is_new = false;
		$create_user = isset($inputs['nl-create-user'])
			&& $inputs['nl-create-user']
			&& ( ! is_array($inputs['nl-create-user'])
				|| (count($inputs['nl-create-user'])
					&& $inputs['nl-create-user'][0]));
		if( $create_user ){
			if( ! email_exists( $email ) ) {
				$user_is_new = true;
				$user_name = $inputs['nl-user-name'];
				$user = self::create_subscriber_user( $email, $user_name);
				if( ! $user ){
					$abort = true;
					$error_message = sprintf('Désolé, une erreur est survenue, nous n\'avons pas pu créer votre compte (%s).', $email);
					$submission->set_response($error_message);
					return false;
				}
			}
		}
		
		$messages = ($contact_form->get_properties())['messages'];
		$messages['mail_sent_ng'] = sprintf("%s\r\nDésolé, une erreur est survenue, la modification de votre inscription n'a pas fonctionné.", $messages['mail_sent_ng']);
		if($user_is_new)
			$messages['mail_sent_ok'] = sprintf("Votre compte %s a été créé, vous allez recevoir un e-mail de connexion.\r\nVous n'avez aucun abonnement à la lettre-info.", $email);
		elseif( $create_user )
			$messages['mail_sent_ok'] = sprintf("Un compte existe déjà avec cette adresse %s. Vous pouvez vous connecter.\r\nDemandez un nouveau mot de passe si vous l'avez oublié.", $email);
		else
			$messages['mail_sent_ok'] = '';
		
		// foreach newsletter type (events, covoiturage, admin)
		foreach($newsletters as $newsletter_option => $newsletter){
			
			$field_extension = self::get_form_newsletter_field_extension($newsletter_option);
			
			if( empty($inputs['nl-period-' . $field_extension]) )
				continue;
			
			$period = $inputs['nl-period-' . $field_extension];
			
			if( $period === AGDP_WPCF7_RADIO_NO_CHANGE )
				continue;
			
			if(is_array($period))
				$period = count($period) && $period[0] ? $period[0] : 'none';//wpcf7 is strange with first radio option
			
			$subscription_periods = self::subscription_periods($newsletter);
			
			if( ! ($found = is_numeric($period)))
				foreach($subscription_periods as $key=>$label)
					if( $found = ($label === $period) || ($key === $period) ){
						$period = $key;
						break;
					}
			if( ! $found)
				$period = 'none';
			
			if( is_numeric($newsletter_option) )
				$newsletter_name = $newsletter->post_title;
			else
				$newsletter_name = AgendaPartage::get_option_label($newsletter_option);
			
			$user_subscription = self::get_subscription( $email, $newsletter );
			if( ! $user_subscription )
				$user_subscription = 'none';
			
			if( $user_subscription !== $period ){
				
				if($period === 'none'){
					if( ! self::remove_subscription( $email, $newsletter) ) {
						$abort = true;
						$error_message = sprintf('Désolé, une erreur est survenue, la suppression de votre abonnement à "%s" n\'a pas été enregistrée.', $newsletter_name);
						$submission->set_response($error_message);
						return false;
					}
					$messages['mail_sent_ok'] .= "\r\n".sprintf('L\'adresse %s n\'est plus inscrite à "%s". Bonne continuation.', $email, $newsletter_name);
				}
				else {
					if( ! self::update_subscription( $email, $period, $newsletter ) ) {
						$abort = true;
						$error_message = sprintf('Désolé, une erreur est survenue, la modification de votre inscription n\'a pas été enregistrée pour "%s".', $newsletter_name);
						$submission->set_response($error_message);
						return false;
					}
					$messages['mail_sent_ok'] .= "\r\n".sprintf('L\'adresse %s est désormais inscrite à "%s", %s.', $email, $newsletter_name, $subscription_periods[$period]);
				}
			}
			
			$field_name = 'nl-send_newsletter-now-' . $field_extension;
			$send_newsletter_now = isset($inputs[$field_name]) && count($inputs[$field_name]) && $inputs[$field_name][0];
			if($send_newsletter_now){
				if(self::send_email($newsletter, $email))
					$messages['mail_sent_ok'] .= sprintf("\r\n\"%s\" a été envoyée à %s.", $newsletter_name, $email);
				elseif( self::content_is_empty() ) {
					$post_type_obj = get_post_type_object( self::get_newsletter_posts_post_type( $newsletter ) );					
					$messages['mail_sent_ok'] .= sprintf("\r\nLe message n'a pas été envoyé car la liste des \"%s\" est vide.", $post_type_obj->labels->name);
				}
				else
					$messages['mail_sent_ok'] .= sprintf("\r\nDésolé, \"%s\" n'a pas pu être envoyée à %s.", $newsletter_name, $email);
			}
		}
		
		if( empty($messages['mail_sent_ok']) )
			$messages['mail_sent_ok'] = 'Aucun changement. Bonne continuation.';
		
		$contact_form->set_properties(array('messages' => $messages));
		
		return true;
	}
	
	/**
	 * Etat du cron
	 */
	public static function get_cron_state(){
		if( ! self::$cron_state){
			$cron_time = wp_next_scheduled( self::get_cron_hook() );
			if( $cron_time === false )
				self::$cron_state = sprintf('0|'); 
			else
				self::$cron_state = self::get_cron_time_str($cron_time); 
		}
		return self::$cron_state;
	}
	public static function get_cron_time(){
		return wp_next_scheduled( self::get_cron_hook() );
	}
	public static function get_cron_time_str($cron_time = false){
		if( ! $cron_time )
			$cron_time = wp_next_scheduled( self::get_cron_hook() );
		if( $cron_time === false )
			return '(cron inactif)'; 
		else{
			$delay = $cron_time - time();
			return sprintf('Prochaine évaluation dans %s - %s'
					, date('H:i:s', $delay)	
					, wp_date('d/m/Y H:i:s', $cron_time)); 
		}
	}
	
	/**
	 * Log l'état du cron
	 */
	public static function log_cron_state(){
		debug_log('[agdpnl-cron state]' . self::$cron_state);
	}
	
	/**
	 * Active le cron
	 * $next_time in seconds or timestamp
	 */
	public static function init_cron($next_time = false){
		$cron_time = wp_next_scheduled( self::get_cron_hook() );
		if($next_time){
			if( $cron_time !== false )
				wp_unschedule_event( $cron_time, self::get_cron_hook() );
			if( $next_time < 1024 )
				$cron_time = strtotime( date('Y-m-d H:i:s') . ' + ' . $next_time . ' second');
			else
				$cron_time = $next_time;
			$result = wp_schedule_single_event( $cron_time, self::get_cron_hook(), [], true );
			// debug_log('[agdpnl-init_cron] wp_schedule_single_event', date('H:i:s', $cron_time - time()));
		}
		if( $cron_time === false ){
			$next_time = strtotime( date('Y-m-d H:i:s') . ' + 1 Hour');
			$cron_time = wp_schedule_event( $next_time, 'hourly', self::get_cron_hook() );
			// debug_log('[agdpnl-init_cron] wp_schedule_event', $cron_time);
			register_deactivation_hook( __CLASS__, 'deactivate_cron' ); 
		}
		else {
			// debug_log('[agdpnl-init_cron] next in ' . date('H:i:s', $cron_time - time()));
		}
		return self::get_cron_state(); 
	}

	/**
	 * Désactive le cron
	 */
	public static function deactivate_cron(){
		$timestamp = wp_next_scheduled( self::get_cron_hook() );
		wp_unschedule_event( $timestamp, self::get_cron_hook() );
		self::$cron_state = '0|Désactivé'; 
	}
	/**
	 * A l'exécution du cron, cherche des destinataires pour ce jour
	 */
	public static function on_cron_exec(){
		self::cron_exec(false);
	}
	
	/**
	 * A l'exécution du cron, cherche des destinataires pour ce jour
	 */
	public static function cron_exec($simulate = false){
		$newsletters = self::get_active_newsletters();
		if( ! $newsletters || count($newsletters) === 0){
			self::deactivate_cron();
			self::$cron_state = '0|Aucune lettre-info active';
			self::log_cron_state();
			return;
		}
		$today = strtotime(wp_date('Y-m-d'));
		$hour = (int)wp_date('H');
		$all_subscribers = [];
		$newsletters_data = [];
		$next_dates = [];
		foreach($newsletters as $newsletter){
			$periods_next_dates = self::get_periods_next_dates($newsletter);
			$next_date = $periods_next_dates[ALL_PERIODS];
			if( $next_date > $today ){
				$next_dates[] = sprintf('%s : %s', $newsletter->post_title, wp_date('d/m/Y', $next_date));
				continue;
			}
			$next_hour = self::get_mailing_hour($newsletter);
			if( $next_hour > $hour ){
				if(count($next_dates))
					$next_dates[count($next_dates) - 1] .= ' ' . $next_hour . 'H'
															. ($next_date == $today ? ' (il est ' . $hour . 'H)' : '');
				else
					$next_dates[] = sprintf('%s : %s', $newsletter->post_title, $next_hour . 'H (il est ' . $hour . 'H)');
				continue;
			}
			$subscribers = self::get_today_subscribers($newsletter, $today);
			if( $subscribers ) {
				$all_subscribers = array_merge($all_subscribers, $subscribers);
				$newsletters_data[$newsletter->ID] = [
					'newsletter' => $newsletter,
					'subscribers' => $subscribers,
				];
			}
			if( ! $subscribers ) {
				foreach($periods_next_dates as $period => $next_date){
					if($period === ALL_PERIODS)
						continue;
					//Pour les envois quotidiens, on ne change la date que le lendemain
					if( $period !== PERIOD_DAYLY || $next_date < $today ){
						self::$cron_state .= sprintf(' | Prochaine date (%s) passe de %s', $period, $next_date);
						$next_date = self::get_next_date($period, $newsletter, $today);
						self::$cron_state .= sprintf(' à %s', $next_date);
						$next_dates[] = wp_date('d/m/Y', $next_date) . ' ' . $next_hour . 'H';
						self::set_next_date($period, $newsletter, $next_date);
					}
				}
			}
		}
		if( count($all_subscribers) === 0){
			self::$cron_state = '1|Rien à faire avant : ' . implode(', ', $next_dates);
			return;
		}
		
		self::$cron_state = sprintf('1|%d abonnés à traiter', count($all_subscribers));

		if( ! $simulate){
			add_filter( 'wp_mail', array(__CLASS__, 'on_wp_mail'), 11, 1 );
			add_filter( 'wp_mail_succeeded', array(__CLASS__, 'on_wp_mail_succeeded'), 11, 1 );
			add_filter( 'wp_mail_failed', array(__CLASS__, 'on_wp_mail_failed'), 11, 1 );
		}

		$time_start = time();
		$all_mails_counter = 0;
		$have_more_mails = false;
		$subscribers_done = [];
		foreach($newsletters_data as $newsletter_id => $newsletter_data){
			$newsletter = $newsletter_data['newsletter'];
			$subscribers = $newsletter_data['subscribers'];
			$mails_counter = 0;
			$mails_counter_max = get_post_meta($newsletter_id, 'mailing-num-emails-per-loop', true);
			$users_per_mail = get_post_meta($newsletter_id, 'mailing-num-users-per-mail', true);
			$mailing_meta_name = self::get_mailing_meta_key($newsletter);
			for(; $mails_counter < $mails_counter_max; $mails_counter++){
				
				$user_emails = [];
				foreach($subscribers as $subscriber){
					if( in_array( $subscriber->ID, $subscribers_done ) )
						continue;
					$user_emails[ $subscriber->user_email ] = $subscriber;
					if( count( $user_emails ) >= $users_per_mail )
						break;
				}
				
				if( count( $user_emails ) === 0 )
					break;
				
				self::$cron_state .= sprintf(' | mail #%d : %d destinataire(s) %s', $mails_counter, count($user_emails), implode(', ', array_keys( $user_emails )));
				
				if( ! $simulate){
					self::set_user_mailing_status($newsletter, $user_emails, 'prepared');
					$success = self::send_email($newsletter, array_keys( $user_emails ));
					if( ! $success && self::content_is_empty() )
						$success = true;
				}
				
				foreach($user_emails as $user_email => $subscriber){
					if( ! $simulate ){
						update_user_meta($subscriber->ID, $mailing_meta_name, wp_date('Y-m-d H:i:s'));
					}
					$subscribers_done[] = $subscriber->ID;
				}
			}
			
			if($mails_counter === 0){
				self::$cron_state .= sprintf(' | Prochaine date passe de %s', self::get_next_date(false, $newsletter));
	
				$next_date = self::get_next_date(false, $newsletter, $today);
				
				self::$cron_state .= sprintf(' à %s', $next_date);
				
				if( ! $simulate){
					self::set_next_date(false, $newsletter, $next_date);
				}
			}
			else {
				$all_mails_counter += $mails_counter;
				if($all_mails_counter >= $mails_counter_max){
					$have_more_mails = true;
					$mailing_loops_interval = get_post_meta($newsletter_id, 'mailing-loops-interval', true);
					break;
				}
			}
		}

		self::$cron_state .= sprintf(' | Au final, %d mail(s) envoyé(s) à %d destinataire(s) en %s sec. Identifiants : #%s.%s'
			, $all_mails_counter
			, count($subscribers_done)
			, time() - $time_start
			, implode(', #', $subscribers_done )
			, $simulate ? ' << Simulation <<' : ''
		);

		if( ! $simulate){
			remove_filter( 'wp_mail', array(__CLASS__, 'on_wp_mail'), 10 );
			remove_filter( 'wp_mail_succeeded', array(__CLASS__, 'on_wp_mail_succeeded'), 10 );
			remove_filter( 'wp_mail_failed', array(__CLASS__, 'on_wp_mail_failed'), 10 );
			self::log_cron_state();
		
			if($have_more_mails){
				if( ! isset($mailing_loops_interval) )
					throw new Exception('$mailing_loops_interval n\'est pas défini !');
				self::init_cron($mailing_loops_interval * 60);
			}
		}
		
		return true;
	}
	
	/**
	 * Send newsletter email
	 */
	public static function send_email($newsletter, $emails){
		$newsletter = self::get_newsletter($newsletter);
		
		self::$sending_email = $newsletter;
		self::$sending_email_to = $emails;
		
		$subject = get_the_title($newsletter);
		if( ! $subject){
			$subject = $newsletter->post_title;
			if( ! $subject){
				// debug_log($newsletter);
				self::$sending_email = self::$sending_email_to = false;
				return false;
			}
		}
		
		$subject = wp_strip_all_tags( do_shortcode( $subject ) );
		
		if( self::get_newsletter_posts_post_type( $newsletter) === AgendaPartage_Covoiturage::post_type )
			$subject = sprintf('[%s]', $subject);
		else
			$subject = sprintf('[%s] %s', get_bloginfo( 'name', 'display' ), $subject);
		
		$message = do_shortcode( get_the_content(false, false, $newsletter) );
		
		if( ! self::content_is_empty() ) {
			//TODO move <script>
			$message = '<!DOCTYPE html><html>'
					. '<head>'
						. '<title>' . $subject . '</title>'
						. '<meta name="viewport" content="width=device-width">'
						. '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
	/* 					. '<style>body {
	font-family: \'Segoe UI\', Arial;
	font-size: 14px;
	height: 100% !important;
	line-height: 1.5;
	-ms-text-size-adjust: 100%;
	-webkit-text-size-adjust: 100%;
	width: 100% !important;
	background-color: #fff;
	margin: 0;
	padding: 0;
	white-space: pre;
							}</style>' */
					. '</head>'
					//TODO white-space does'nt work with my android mailbox
					. sprintf('<body style="white-space: pre-line;">%s</body>', $message)
					. '</html>'
			;
			$emails_separator = ',';
			
			if(is_array($emails))
				$to = implode($emails_separator, $emails);
			else
				if( ! ($to = str_replace(';', $emails_separator , sanitize_email($emails))) )
					return false;
			
			if( strpos( $to, ',' ) !== false
			|| strpos( $to, ';' ) !== false
			){
				$bcc = $to;
				$to = self::get_bcc_mail_sender();
			}
			else
				$bcc = false;
			
			
			$headers = array();
			$attachments = array();
			
			$headers[] = 'MIME-Version: 1.0';
			$headers[] = 'Content-type: text/html; charset=utf-8';
			$headers[] = 'Content-Transfer-Encoding: quoted-printable';
			
			if( $bcc ){
				foreach( explode($emails_separator, $bcc) as $email )
					$headers[] = sprintf('Bcc: %s', trim($email));
				//$headers[] = sprintf('Bcc: %s', $bcc);
			}
			
			$headers[] = sprintf('From: %s', self::get_mail_sender());
			$headers[] = sprintf('Reply-to: %s', self::get_replyto_mail_sender());
			
			add_action( 'phpmailer_init', array( __CLASS__, 'on_sending_add_plain_text_body' ));
			
			if($success = wp_mail( $to
				, '=?UTF-8?B?' . base64_encode($subject). '?='
				, $message
				, $headers, $attachments )){
				if(class_exists('AgendaPartage_Admin', false))
					AgendaPartage_Admin::add_admin_notice(sprintf("La lettre-info a été envoyé à %d destinataire(s).", count($emails)), 'info');
			}
			else{
				if(class_exists('AgendaPartage_Admin', false))
					AgendaPartage_Admin::add_admin_notice(sprintf("L'e-mail n'a pas pu être envoyé"), 'error');
			}
			
			remove_action( 'phpmailer_init', array( __CLASS__, 'on_sending_add_plain_text_body' ));
			
		}
		else {
			$success = false;
			if(class_exists('AgendaPartage_Admin', false))
				AgendaPartage_Admin::add_admin_notice(sprintf("Le contenu est vide (aucun élément). Le mail n'a pas été envoyé."), 'warning');
		}
		
		self::$sending_email = self::$sending_email_to = false;
		
		return $success;
	 }
	/**
	 * Retourne la newsletter en cours d'envoi d'email
	 */
	public static function is_sending_email(){
		return self::$sending_email;
	}
	public static function is_sending_email_to(){
		return self::$sending_email_to;
	}
	public static function content_is_empty($empty = null){
		if( $empty !== null)
			self::$content_is_empty = $empty;
		return self::$content_is_empty;
	}
	
	/**
	 * On sending mail, add plain text content
	 */
    public static function on_sending_add_plain_text_body( $phpmailer ) {
		// debug_log('on_sending_add_plain_text_body', $phpmailer->Body);
        // don't run if sending plain text email already
        // don't run if altbody is set
        if( 'text/plain' === $phpmailer->ContentType || ! empty( $phpmailer->AltBody ) ) {
            return;
        }
        $phpmailer->AltBody = html_to_plain_text( $phpmailer->Body );
    }
	 
	 /**
	  * Retourne les emails des abonnés pour un envoi ce jour et à qui l'envoi n'a pas encore été fait.
	  */
	 public static function get_today_subscribers($newsletter, $today = 0){
		if( ! $today )
			$today = strtotime(wp_date('Y-m-d'));
		$newsletter = self::get_newsletter($newsletter);
		
		$periods = [];
		$periods_in = '';
		foreach(self::subscription_periods($newsletter) as $period => $period_name){
			if( $period === 'none'
			|| self::get_next_date($period, $newsletter) > $today )
				continue;
			$periods[$period] = [
				'ID' => $period,
				'name' => $period_name,
			];
			$periods_in .= ($periods_in ? ', ' : '') . "'".$period."'";
		}
		if(count($periods) === 0)
			return false;
		
		$newsletter_id = $newsletter->ID;
		$subscription_meta_key = self::get_subscription_meta_key($newsletter);
		$mailing_meta_key = self::get_mailing_meta_key($newsletter);
		
		if( $today === strtotime(wp_date('Y-m-d')) )
			$posts_last_change = self::get_newsletter_posts_last_change($newsletter);
		else
			$posts_last_change = false;
		
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		$user_prefix = $wpdb->get_blog_prefix( 1 );
		
		/** Liste d'abonnés **/
		//$meta_name = sprintf('next_date_%s', $period);
		$meta_next_date = 'next_date_';
		$today_mysql = wp_date('Y-m-d', $today);
		$sql = "SELECT subscription.meta_value AS period, user.ID, user.user_email, user.user_nicename"
			. "\n FROM {$user_prefix}users user"
			// . "\n INNER JOIN {$user_prefix}usermeta usermetacap"
			// . "\n ON user.ID = usermetacap.user_id"
			// . "\n AND usermetacap.meta_key = '{$blog_prefix}capabilities'"
			// . "\n AND usermetacap.meta_value != 'a:0:{}'"
			. "\n INNER JOIN {$user_prefix}usermeta subscription"
				. "\n ON user.ID = subscription.user_id"
				. "\n AND subscription.meta_key = '{$subscription_meta_key}'"
				. "\n AND subscription.meta_value IN ({$periods_in})"
			. "\n INNER JOIN {$blog_prefix}postmeta next_date"
				. "\n ON next_date.post_id = {$newsletter_id}"
				. "\n AND next_date.meta_key = CONCAT( '{$meta_next_date}', subscription.meta_value)"
				. "\n AND next_date.meta_value <= '{$today_mysql}'"
			. "\n LEFT JOIN {$user_prefix}usermeta mailing"
				. "\n ON user.ID = mailing.user_id"
				. "\n AND mailing.meta_key = '{$mailing_meta_key}'"
				. "\n AND (DATE_FORMAT(mailing.meta_value, '%Y-%m-%d') = '{$today_mysql}'"
				. ( $posts_last_change ? "\n OR mailing.meta_value > '{$posts_last_change}'" : '')
				. ')'
			. "\n WHERE mailing.meta_key IS NULL"
			. "\n ORDER BY mailing.meta_value ASC, user.ID DESC";
// debug_log($sql);

		$dbresults = $wpdb->get_results($sql);
		// foreach($dbresults as $dbresult)
			// if(isset($periods[$dbresult->period]))
				// $periods[$dbresult->period]['subscribers'][] = $dbresult;
		 return count($dbresults) ? $dbresults : false;
	 }
	 
	 
	/**
	 * on_wp_mail_action
	 */
	public static function on_wp_mail_action( $hook, array $mail_data, $newsletter = false, $email = false ){
		// debug_log('on_wp_mail_action', $hook, $email);
		if( ! $newsletter){
			$newsletter = self::is_sending_email();
			if( ! $newsletter )
				return $mail_data;
		}
		
		if( ! $email ){
		
			foreach($mail_data['headers'] as $header){
				$matches = [];
				if(preg_match_all('/^(from|bcc|cc|reply\-to)\:(.*)$/', strtolower($header), $matches)){
				if( ! empty($mail_data[$matches[1][0]]) ){
					if( is_array($mail_data[$matches[1][0]]) )
						$mail_data[$matches[1][0]][] = trim($matches[2][0], " \r\n;,");
					else
						$mail_data[$matches[1][0]] .= ',' . trim($matches[2][0], " \r\n;,");
				}
				else
					$mail_data[$matches[1][0]] = trim($matches[2][0], " \r\n;,");
				}
			}
			
			if(isset($mail_data['bcc']))
				$emails = $mail_data['bcc'];
			else
				$emails = $mail_data['to'];
			if( is_string($emails) )
				$emails = explode( ',', $emails );
			if( count($emails) === 0)
				debug_log('on_wp_mail_action(' . $hook . ') NO EMAIL !');
			
			foreach( $emails as $email )
				if( trim($email) )
					self::on_wp_mail_action($hook, $mail_data, $newsletter, trim($email) );
			return $mail_data;
		}
		
		$user_id = email_exists($email);
		if( ! $user_id ){
			debug_log(sprintf('[%s] on_%s("%s") Le destinataire n\'existe pas comme utilisateur !!', self::post_type, $hook, $email));
			return $mail_data;
		}
		
		switch($hook){
			case 'wp_mail' :
				self::set_user_mailing_status($newsletter, $user_id, 'sending');
				break;
			case 'wp_mail_succeeded' :
				self::delete_user_mailing_status($newsletter, $user_id);
				break;
			case 'wp_mail_failed' :
				self::set_user_mailing_status($newsletter, $user_id, sprintf('Echec : %s', $mail_data['error_message']));
				break;
		}
		
		return $mail_data;
	}
	
	/**
	 * on_wp_mail
	 */
	public static function on_wp_mail( $mail_data ){
		if( $mail_data === null){
			debug_log('on_wp_mail( $mail_data === NULL ! )');
			return;
		}
		$mail_data = self::on_wp_mail_action('wp_mail', $mail_data);
		return self::$sending_email_data = $mail_data;
	}
	 
	 
	/**
	 * on_wp_mail_succeeded
	 */
	public static function on_wp_mail_succeeded( $mail_data ){
		return self::on_wp_mail_action('wp_mail_succeeded', array_merge(self::$sending_email_data, $mail_data));
	}
	 
	 
	/**
	 * wp_mail_failed
	 */
	public static function on_wp_mail_failed( WP_Error $error ){
		$mail_data = $error->error_data['wp_mail_failed'];
		$mail_data['error_message'] = implode(', ', $error->errors['wp_mail_failed']);
		self::on_wp_mail_action('wp_mail_failed', array_merge(self::$sending_email_data, $mail_data));
		return $error;
	}
	 
	 
	/**
	 * current_user_can_change_subscription
	 */
	public static function current_user_can_change_subscription( $subscription_email ){
		
		if(is_user_logged_in()){
			$current_user = wp_get_current_user();

			if( $subscription_email === $current_user->user_email){
				return true;
			}
			if(	array_key_exists('administrator', $current_user->caps) ){
				return true;
			}
		}
		//Not connected or is a simple subscriber
		//Depends of email user capabilities
		if( ! ($subscriber = get_user_by('email', $subscription_email)))
			return true;
		$roles = get_user_meta($subscriber->ID, 'roles', true);
		if( ! $roles
		|| count($roles) === 0
		|| array_key_exists('subscriber', $roles) )
			return true;
		return false;
	}
	
	
}
?>