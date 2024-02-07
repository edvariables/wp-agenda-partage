<?php

/**
 * AgendaPartage -> Forum
 * Custom post type for WordPress.
 * 
 * Définition du Post Type agdpforum
 * Mise en forme du formulaire Forum
 *
 * Voir aussi AgendaPartage_Admin_Forum
 */
class AgendaPartage_Forum {

	const post_type = 'agdpforum';
	const taxonomy_period = 'period';
	
	// const user_role = 'author';

	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;

			self::init_hooks();
		}
	}

	/**
	 * Hook
	 */
	public static function init_hooks() {
		add_filter( 'the_title', array(__CLASS__, 'the_title'), 10, 2 );
		
		add_filter( 'wpcf7_form_class_attr', array(__CLASS__, 'on_wpcf7_form_class_attr_cb'), 10, 1 );
		
		add_action( 'wp_enqueue_scripts', array(__CLASS__, 'register_plugin_js') ); 
		
	}
	/*
	 **/

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
	public static function get_post_title( $forum = null, $no_html = false) {
 		if( ! isset($forum) || ! is_object($forum)){
			global $post;
			$forum = $post;
		}
		
		$post_title = isset( $forum->post_title ) ? $forum->post_title : '';
		//$separator = $no_html ? ', ' : '<br>';
		$html = do_shortcode($post_title);
		return $html;
	}
	/*
	 **********/
	 
	/**
	 * Returns true if post_status == 'publish' && meta['mailing-enable'] == true
	 */
	 public static function is_active($forum){
		$forum = self::get_forum($forum);
		if( $forum->post_status !== 'publish')
			return false;
		return get_post_meta($forum->ID, 'mailing-enable', true) == 1;
	}
	
	/**
	 * Returns array of ID=>post_title
	 */
	 public static function get_forums_names(){
		$forums = [];
		foreach( get_posts([
			'post_type' => self::post_type
			, 'fields' => 'post_title'
			]) as $post)
			$forums[ $post->ID . '' ] = $post->post_title;
		return $forums;
	}
	
	/**
	 * Returns posts where post_status == 'publish' && meta['mailing-enable'] == true
	 */
	 public static function get_active_forums(){
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
	 * Retourne le type de posts concernés par le forum
	 */
	 public static function get_forum_posts_post_type($forum){
		switch($forum->ID){
			// case AgendaPartage::get_option(AgendaPartage_Evenement::forum_option) :
				// return AgendaPartage_Evenement::post_type;
			// case AgendaPartage::get_option(AgendaPartage_Covoiturage::forum_option) :
				// return AgendaPartage_Covoiturage::post_type;
			default:
				return false;
		}
	 }
	/**
	 * Retourne la date du dernier post concerné par le forum
	 */
	 public static function get_forum_posts_last_change($forum){
		if( ! ($post_type = self::get_forum_posts_post_type($forum)) )
			return false;
		
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		$sql = "SELECT MAX(posts.post_date) as post_date_max
				FROM {$blog_prefix}posts posts
				WHERE posts.post_status IN('publish', 'prepend')
					AND posts.post_type = '". $post_type ."'
		";
		
		$result = $wpdb->get_results($sql);
		if( is_a($result,'WP_Error') )
			throw new Exception(var_export($result, true));
		if(count($result))
			return $result[0]->post_date_max;
		return false;
	}
	
	/**
	 * Interception du formulaire avant que les shortcodes ne soient analysés.
	 * Affectation des valeurs par défaut.
	 */
 	public static function on_wpcf7_form_class_attr_cb( $form_class ) { 
		$form = WPCF7_ContactForm::get_current();
		switch($form->id()){
			case AgendaPartage::get_option('forum_subscribe_form_id') :
				self::wpcf7_forum_form_init_tags( $form );
				$form_class .= ' preventdefault-reset events_forum_register';
				break;
			default:
				break;
		}
		return $form_class;
	}
	
 	private static function wpcf7_forum_form_init_tags( $form ) { 
		// $current_nl = self::get_forum();
		// $current_nl_id = $current_nl ? $current_nl->ID : 0;
		
		$form = WPCF7_ContactForm::get_current();
		$html = $form->prop('form');//avec shortcodes du wpcf7
		
		$email = self::get_email();
		
		$forums = self::get_forums();
		
		// foreach forum type (events, covoiturage, admin)
		foreach($forums as $forum_option => $forum){
			
			// $user_subscription = null;
			// $field_extension = self::get_form_forum_field_extension($forum_option);
			
			// /** périodicité de l'abonnement **/
			// $input_name = 'nl-period-' . $field_extension;
			// $subscription_periods = self::subscription_periods($forum);
			
			// if(isset($_REQUEST['action']))
				// switch($_REQUEST['action']){
					// case 'unsubscribe':
					// case 'desinscription':
						// $user_subscription = 'none';
						// $subscription_periods[$user_subscription] = 'Désinscription à valider';
						// break;
					// default:
						// break;
				// }
			// if( $user_subscription === null)
				// $user_subscription = self::get_subscription($email, $forum);
			// if( ! $user_subscription)
				// $user_subscription = 'none';
			
			// $checkboxes = '';
			// $selected = '';
			// $index = 0;
			// foreach( $subscription_periods as $subscribe_code => $label){
				// $checkboxes .= sprintf(' "%s|%s"', $label, $subscribe_code);
				// if($user_subscription == $subscribe_code){
					// $selected = sprintf('default:%d', $index+1);
				// }
				// $index++;
			// } 
			// /** nl_id **/
			$html .= "<input class='hidden' name='".AGDP_ARG_NEWSLETTERID."' value='{$current_nl_id}'/>";
		
		
			// $html = preg_replace('/\[(radio\s+'.$input_name.')[^\]]*[\]]/'
								// , sprintf('[$1 %s use_label_element %s]'
									// , $selected
									// , $checkboxes)
								// , $html);
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
		
		/** admin **/
		// if( current_user_can('manage_options')){
			// $urls = [];
			// $nls = self::get_forums_names();
			// $basic_url = get_post_permalink();
					
			// if( count($nls) > 1){
				// $html .= '<ul class="forum-change-nl">';
				// foreach($nls as $nl_id=>$nl_name)
					// if($nl_id === $current_nl_id){
						// $html .= "<li>Administratriceur, vous êtes sur la page du forum \"{$nl_name}\".</li>";
						// break;
					// }
				// foreach($nls as $nl_id=>$nl_name)
					// if($nl_id !== $current_nl_id){
						// $url = add_query_arg( AGDP_ARG_NEWSLETTERID, $nl_id, $basic_url);
						// $html .= sprintf("<li>Basculer vers le forum \"<a href=\"%s\">%s</a>\".</li>", esc_attr($url), $nl_name);
					// }				
				// $html .= '</ul>';
			// }
		// }
		$form->set_properties(array('form'=>$html));
				
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
	
	public static function get_forum($forum = false){
		// if( ! $forum || $forum === true){
			// if( empty($_REQUEST[AGDP_ARG_NEWSLETTERID]))
				// $forum = AgendaPartage::get_option('default_forum_post_id');
			// else
				// $forum = $_REQUEST[AGDP_ARG_NEWSLETTERID];
		// }
		$forum = get_post($forum);
		if(is_a($forum, 'WP_Post')
		&& $forum->post_type == self::post_type)
			return $forum;
		return false;
	}
	
	public static function get_messages($forum = false){
		$forum = self::get_forum($forum);
		if( ! $forum )
			return false;
		
		
		require_once( AGDP_PLUGIN_DIR . "/includes/phpImapReader/Reader.php");
		require_once( AGDP_PLUGIN_DIR . "/includes/phpImapReader/Email.php");
		require_once( AGDP_PLUGIN_DIR . "/includes/phpImapReader/EmailAttachment.php");
		$imap = self::get_ImapReader($forum->ID);
		
		$search = date("j F Y", strtotime("-1 days"));
		$search = date("j F Y", strtotime("-4 hours"));
		$imap
			->sinceDate($search)
			->get();
		
		// if( ! $box->connect() )
			// return new Exception(imap_last_error());
		
		//$box->fetchSearchHeaders('INBOX', $search);
		// $box->fetchAllHeaders();
		$messages = [];
		foreach($imap->emails() as $email){
			foreach($email->custom_headers as $header => $header_content){
				if( preg_match('/-SPAMCAUSE$/', $header) )
					$email->custom_headers[$header] = self::decode_spamcause( $header_content );
			}
			$messages[] = [
				'id' => $email->id,
				'msgno' => $email->msgno,
				'date' => $email->date,
				'udate' => $email->udate,
				'subject' => $email->subject,
				'to' => $email->to,
				'from' => $email->from,
				'reply_to' => $email->reply_to,
				'attachments' => $email->attachments,
				'text_plain' => $email->text_plain,
				'text_html' => $email->text_html
			];
		}
		
/*		
		
		$emails = self::get_imap_messages($imapResource);

		// If the $emails variable is not a boolean FALSE value or
		// an empty array.
		if(!empty($emails)){
			// Loop through the emails.
			foreach($emails as $email){
				$data = [];
				// Fetch an overview of the email.
				$overview = imap_fetch_overview($imapResource, $email);
				$overview = $overview[0];
				
				// $data['data'] = $overview;
				$data['ID'] = $email;
				
				// Print out the subject of the email.
				$subject = $overview->subject;
				$prefix = '=?utf-8?B?';
				$suffix = '?=';
				if( strcmp( substr($subject, 0, strlen($prefix)), $prefix ) === 0
				&& strcmp( substr($subject, strlen($subject) - strlen($suffix)), $suffix ) === 0)
					$subject = base64_decode( substr($subject, strlen($prefix), strlen($subject) - strlen($prefix) - strlen($suffix)) );
				$data['subject'] = htmlentities($subject);
				
				$data['date'] = strtotime($overview->date);
				$data['date_locale'] = date( "le d/m/Y à H:i:s", $overview->date );
				
				// Print out the sender's email address / from email address.
				$data['From'] = $overview->from;
				
				// Get the body of the email.
				$message = imap_fetchbody($imapResource, $email, 1, FT_PEEK);
				$data['message'] = imap_utf8( $message );
				
				$messages[] = $data;
				
				break;
			}
		}
		*/
		return $messages;
	}
	
	private static function get_ImapReader($forum_id){
		$server = get_post_meta($forum_id, 'imap_server', true);
		// $port = get_post_meta($forum_id, 'imap_server', true);
		$email = get_post_meta($forum_id, 'imap_email', true);
		$password = get_post_meta($forum_id, 'imap_password', true);
		
		define('ATTACHMENT_PATH', false);//__DIR__ . '/attachments');
		$mark_as_read = false;
		$encoding = 'UTF-8';
		
		$imap = new benhall14\phpImapReader\Reader($server, $email, $password, ATTACHMENT_PATH, $mark_as_read, $encoding);

		return $imap;
	}
	
	private static function decode_spamcause($msg){
		$text = "";
		for ($i = 0; $i < strlen($msg); $i+=2)
			$text .= self::decode_spamcause_unrot(substr($msg, $i, 2), floor($i / 2));                    # add position as extra parameter
		return $text;
	}

	private static function decode_spamcause_unrot($pair, $pos, $key = false){
	// def unrot(pair, pos, key=ord('x')):
		if( $key === false )
			$key = ord('x');
		if ($pos % 2 == 0)                                           # "even" position => 2nd char is offset
			$pair = $pair[1] . $pair[0];                               # swap letters in pair
		$offset = (ord('g') - ord($pair[0])) * 16;                     # treat 1st char as offset
		return chr(ord($pair[0]) + ord($pair[1]) - $key - $offset);        # map to original character
	}
}
?>