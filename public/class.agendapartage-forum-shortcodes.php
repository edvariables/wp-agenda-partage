<?php

/**
 * AgendaPartage -> Forum
 * Custom post type for WordPress.
 * 
 * Définition des shortcodes 
 *
 * Voir aussi AgendaPartage_Admin_Forum
 */
class AgendaPartage_Forum_Shortcodes {


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
		add_shortcode( 'forum-titre', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'forum-description', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'agdpforum-messages', array(__CLASS__, 'shortcodes_callback') );

	}

	/**
	* Callback des shortcodes
	*/
	public static function shortcodes_callback($atts, $content = '', $shortcode = null){

		if(is_admin() 
		&& ! wp_doing_ajax()
		&& ! AgendaPartage_Newsletter::is_sending_email())
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
		// Fait un appel récursif si si il y a l'attribut "ajax"
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

		//De la forme [agdpforum-messages] ou [agdpforum-messages-list]
		if($shortcode == 'agdpforum-messages' || str_starts_with($shortcode, 'agdpforum-messages-')){
			return self::shortcodes_messages_callback($atts, $content, $shortcode);
		}

		return self::shortcodes_forum_callback($atts, $content, $shortcode);
	}
	
	/**
	* [forum info=titre|description]
	* [forum-titre]
	* [forum-description]
	*/
	private static function shortcodes_forum_callback($atts, $content = '', $shortcode = null){
		if( ! ( $post = AgendaPartage_Forum::get_forum_of_page(get_post()) ) )
			return $content;
		
		if($post)
			$post_id = $post->ID;
		
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
			
			$specificInfos = ['titre', 'localisation', 'description', 'dates', 'message-contact', 'modifier-forum', 'details'];
			if(array_key_exists('info', $atts)
			&& in_array($atts['info'], $specificInfos))
				$shortcode .= '-' . $atts['info'];
			if(array_key_exists('0', $atts))
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
			case 'forum-titre':

				$val = isset( $post->post_title ) ? $post->post_title : '';
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-forum agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				
			case 'forum-description':

				$val = isset( $post->post_content ) ? $post->post_content : '';
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-forum agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
								
			case 'forum':
			
				$meta_name = $atts['info'] ;
				if(!$meta_name)
					return '<div class="error">Le paramètre info="'.$meta_name.'" du shortcode "forum" est inconnu.</div>';
				switch($meta_name){
					case 'email' :
						$meta_name = 'imap_email';
						break;
				}
				$val = get_post_meta($post_id, $meta_name, true, false);
				
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
	* [agdpforum-messages "nom du forum"]
	* [agdpforum-messages] le forum est défini dans la configuration de la newsletter
	* [agdpforum-messages mode:liste|list|email forum:"nom du forum"]
	*/
	public static function shortcodes_messages_callback($atts, $content = '', $shortcode = null){
		
		$forum = false;
		foreach($atts as $att_key=>$att_value){
			if( $att_key === ' forum' ){
				$forum = AgendaPartage_Forum::get_forum_by_name($att_key);
				break;
			}
		}
		if( ! $forum ){
			global $post;
			if( $post && $post->post_type === AgendaPartage_Newsletter::post_type )
				$newsletter = $post;
			else
				$newsletter = AgendaPartage_Newsletter::is_sending_email();
			if( $newsletter ){
				$source = AgendaPartage_Newsletter::get_content_source( $newsletter->ID, true);
				if( $source[0] == AgendaPartage_Forum::post_type ){
					$forum = AgendaPartage_Forum::get_forum_of_newsletter( $newsletter->ID );
				}
			}
			
		}
		if( ! $forum ){
			foreach($atts as $att_key=>$att_value){
				if( $att_value == 1 ){
					$forum = AgendaPartage_Forum::get_forum_by_name($att_key);
					break;
				}
			}
		}
		if( ! $forum ){
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
			case 'agdpforum-messages-liste':
				$shortcode = 'agdpevents-list';
			case 'agdpforum-messages-list':
				
				return AgendaPartage_Forum_Messages::get_list_html( $forum, $content );
				
			case 'agdpforum-messages-email':
				
				$html = AgendaPartage_Forum_Messages::get_list_for_email( $forum, $content );
				return $html;

			default:
				return '<div class="error">Le shortcode "'.$shortcode.'" inconnu.</div>';
		}
	}
	
	public static function shortcodes_agdpstats_callback($atts, $content = '', $shortcode = null){
		require_once(AGDP_PLUGIN_DIR . '/admin/class.agendapartage-admin-stats.php');
		if( count($atts)) {
			if( in_array('forumscounters', $atts) )
				return AgendaPartage_Admin_Stats::forums_stats_forumscounters() . $content;
		}
		return AgendaPartage_Admin_Stats::get_stats_result() . $content;
	}
	
	// shortcodes //
	///////////////
}
