<?php

/**
 * AgendaPartage -> Forum
 * Page liée à une mailbox
 * 
 * Définition des shortcodes 
 *
 *	
 */
class Agdp_Forum_Shortcodes {


	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;

			self::init_hooks();
			self::init_shortcodes();
		}
	}

	/**
	 * Hook
	 */
	public static function init_hooks() {
		
		add_action( 'wp_ajax_'.AGDP_TAG.'_shortcode', array(__CLASS__, 'on_wp_ajax_forum_shortcode_cb') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_shortcode', array(__CLASS__, 'on_wp_ajax_forum_shortcode_cb') );
	}

	/////////////////
 	// shortcodes //
 	/**
 	 * init_shortcodes
 	 */
	public static function init_shortcodes(){

		add_shortcode( 'forum', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'forum-prop', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'agdpforum-messages', array(__CLASS__, 'shortcodes_callback') );

	}

	/**
	* Callback des shortcodes
	*/
	public static function shortcodes_callback($atts, $content = '', $shortcode = null){

		if(is_admin() 
		&& ! wp_doing_ajax()
		&& ! Agdp_Newsletter::is_sending_email())
			return;
		
		if( ! is_array($atts)){
			$atts = array();
		}
		
		//champs sans valeur transformer en champ=true
		foreach($atts as $key=>$value){
			if(is_numeric($key) && ! array_key_exists($value, $atts)){
				$atts[$value] = true;
				unset($atts[$key]);
			}
		}
		if(array_key_exists('toggle-ajax', $atts)){
			$atts['toggle'] = $atts['toggle-ajax'];
			$atts['ajax'] = true;
			unset($atts['toggle-ajax']);
		}
		
		$key = 'ajax';
		if(array_key_exists($key, $atts)){
			$ajax = $atts[$key] ? $atts[$key] : true;
			unset($atts[$key]);
		}
		else{
			$ajax = false;
			$key = 'post_id';
			if(array_key_exists($key, $atts)){
				global $post;
				if(!$post){
					$post = get_post($atts[$key]);
					
					//Nécessaire pour WPCF7 pour affecter une valeur à _wpcf7_container_post
					global $wp_query;
					if($post)
						$wp_query->in_the_loop = true;
				}
				$_POST[$key] = $_REQUEST[$key] = $atts[$key];
				unset($atts[$key]);
			}
		}
		// Si attribut toggle [forum-details toggle="Contactez-nous !"]
		// Fait un appel récursif si il y a l'attribut "ajax"
		// TODO Sauf shortcode conditionnel
		if(array_key_exists('toggle', $atts)){
			
			$shortcode_atts = '';
			foreach($atts as $key=>$value){
				if($key == 'toggle'){
					$title = array_key_exists('title', $atts) && $atts['title'] 
						? $atts['title']
						: ( $atts['toggle'] 
							? $atts['toggle'] 
							: __($shortcode, AGDP_TAG)) ;
				}
				elseif( ! is_numeric($key) ){
					if(is_numeric($value))
						$shortcode_atts .= sprintf('%s=%s ', $key, $value);
					else
						$shortcode_atts .= sprintf('%s="%s" ', $key, esc_attr($value));
				}
			}
			
			//Inner
			$html = sprintf('[%s %s]%s[/%s]', $shortcode, $shortcode_atts , $content, $shortcode);
			
			if( ! $ajax){
				$html = do_shortcode($html);
			}
			else{
				$ajax = sprintf('ajax="%s"', esc_attr($ajax));
				$html = esc_attr(str_replace('"', '\\"', $html));
			}
			//toggle
			//Bugg du toggle qui supprime des éléments
			$guid = uniqid(AGDP_TAG);
			$toogler = do_shortcode(sprintf('[toggle title="%s" %s]%s[/toggle]'
				, esc_attr($title)
				, $ajax 
				, $guid
			));
			return str_replace($guid, $html, $toogler);
		}

		//De la forme [agdpforum-messages]
		if($shortcode == 'agdpforum-messages'){
			return self::shortcodes_messages_callback($atts, $content, $shortcode);
		}

		return self::shortcodes_forum_callback($atts, $content, $shortcode);
	}
	
	/**
	* [forum info=titre|description]
	* [forum-titre]
	* [forum-description]
	* [forum-prop hide_comments=0 mark_as_ended=0 reply_link=0 comment_form=0]
	*/
	private static function shortcodes_forum_callback($atts, $content = '', $shortcode = null){
		$page = Agdp_Forum::get_page();
		if( ! ( $mailbox = Agdp_Mailbox::get_mailbox_of_page($page) ) ){
			return $content;
		}
		$label = isset($atts['label']) ? $atts['label'] : '' ;
				
		$html = '';
		
		foreach($atts as $key=>$value){
			if(is_numeric($key)){
				$atts[$value] = true;
				if($key != '0')
					unset($atts[$key]);
			}
		}
		
		if($shortcode == 'forum'
		&& count($atts) > 0){
			
			$specificInfos = [];
			if(array_key_exists('info', $atts)
			&& in_array($atts['info'], $specificInfos))
				$shortcode .= '-' . $atts['info'];
			if( ! is_associative_array($atts))
				if(is_numeric($atts['0']))
					$atts['post_id'] = $atts['0'];
				elseif( ! array_key_exists('info', $atts))
					if(in_array($atts['0'], $specificInfos))
						$shortcode .= '-' . $atts['0'];
					else
						$atts['info'] = $atts['0'];
		}
		$no_html = isset($atts['no-html']) && $atts['no-html']
				|| isset($atts['html']) && $atts['html'] == 'no';
		
		
		switch($shortcode){
			case 'forum-prop':
				foreach($atts as $att_key => $att_value){
					if( is_int($att_key) ){
						$att_key = $att_value;
						$att_value = true;
					}
					Agdp_Forum::set_property($att_key, $att_value);
				}
				break;
				
			case 'forum':
			
				$meta_name = $atts['info'] ;
				if(!$meta_name)
					return '<div class="error">Le paramètre info="xxx" du shortcode "forum" n\'est pas fourni.</div>';
				$val = false;
				switch($meta_name){
					case 'imap_email' :
					case 'email' :
						$meta_name = 'imap_email';
						$val = Agdp_Mailbox::get_page_email($page);
						break;
				}
				if($val === false)
					$val = get_post_meta($page->ID, $meta_name, true, false);
				
				if($val)
					switch($meta_name){
						case 'imap_email' :
							$val = antispambot(esc_html($val), -0.5);
							if( isset($atts['mailto']) && $atts['mailto'])
								$val = sprintf('<a href="mailto:%s">%s</a>', $val,
									$atts['mailto'] === '1' || $atts['mailto'] === true ? $val : $atts['mailto']
								);
							return $val;
							break;
					}
				if($val || $content){
					if($label)
						return '<div class="agdp-forum">'
							. ($label ? '<span class="label"> '.$label.'<span>' : '')
							. do_shortcode( $val . wp_kses_post($content))
							. '</div>';
					return do_shortcode( $val . wp_kses_post($content));
				}
				break;
				
			default:
			
				return '<div class="error">Le shortcode "'.$shortcode.'" inconnu.</div>';
		}
	}
 	
	/**
	 * Get code secret from Ajax query, redirect to post url
	 */
	public static function on_wp_ajax_forum_shortcode_cb() {
		$ajax_response = '';
		$data = $_POST['data'];
		if($data){ 
			if( is_string( $data ) )
				$data  = str_replace('\\"', '"', wp_specialchars_decode( $data ));
			
			$ajax_response = do_shortcode( $data );
			
		}
		// Make your array as json
		wp_send_json($ajax_response);
	 
		// Don't forget to stop execution afterward.
		wp_die();
	}
	
	
	
	/**
	* [agdp-comments "nom du forum"]
	* [agdpforum-messages] le forum est défini dans la configuration de la newsletter
	* [agdpforum-messages mode:liste|list|email forum:"nom du forum"]
	*/
	public static function shortcodes_messages_callback($atts, $content = '', $shortcode = null){
		
		$page = Agdp_Forum::get_page();
		if( ! $page )
			return '<div class="error">Impossible de retrouver le forum via ['.$shortcode.' '.print_r($atts, true).']. Page courante inconnue.</div>';
		if( $page->post_type === Agdp_Newsletter::post_type )
			$page = Agdp_Newsletter::get_forum_of_newsletter( $page );
		if( ! $page
		 || ! ( $mailbox = Agdp_Mailbox::get_mailbox_of_page($page) ) ){
			return '<div class="error">Impossible de retrouver le forum via ['.$shortcode.' '.print_r($atts, true).'] inconnu.</div>';
		}
		
		if($shortcode == 'agdpforum-messages'
		&& count($atts) > 0){
			if(array_key_exists('mode', $atts))
				$shortcode .= '-' . $atts['mode'];
			elseif(array_key_exists('0', $atts))
				$shortcode .= '-' . $atts['0'];
		}
		
		switch($shortcode){
			case 'agdpforum-messages-list':
				
				return Agdp_Comments::get_list_html( $mailbox, $page, $content );
				
			case 'agdpforum-messages-email':
				
				$html = Agdp_Comments::get_list_for_email( $mailbox, $page, $content );
				return $html;

			default:
				return '<div class="error">Le shortcode "'.$shortcode.'" inconnu.</div>';
		}
	}
	
	public static function shortcodes_agdpstats_callback($atts, $content = '', $shortcode = null){
		require_once(AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-stats.php');
		if( count($atts)) {
			if( in_array('forumscounters', $atts) )
				return Agdp_Admin_Stats::forums_stats_forumscounters() . $content;
		}
		return Agdp_Admin_Stats::get_stats_result() . $content;
	}
	
	// shortcodes //
	///////////////
}