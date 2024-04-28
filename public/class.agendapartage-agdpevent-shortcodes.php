<?php

/**
 * AgendaPartage -> Évènement
 * Custom post type for WordPress.
 * 
 * Définition des shortcodes 
 *
 * Voir aussi AgendaPartage_Admin_Evenement
 */
class AgendaPartage_Evenement_Shortcodes {


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
		
		add_action( 'wp_ajax_'.AGDP_TAG.'_shortcode', array(__CLASS__, 'on_wp_ajax_agdpevent_shortcode_cb') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_shortcode', array(__CLASS__, 'on_wp_ajax_agdpevent_shortcode_cb') );
		add_filter('wpcf7_mail_components', array(__CLASS__, 'on_wpcf7_mail_components'), 10, 3);
	}

	/////////////////
 	// shortcodes //
 	/**
 	 * init_shortcodes
 	 */
	public static function init_shortcodes(){

		add_shortcode( 'agdpevent-titre', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'agdpevent-categories', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'agdpevent-cities', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'agdpevent-diffusions', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'agdpevent-description', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'agdpevent-dates', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'agdpevent-localisation', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'agdpevent-details', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'agdpevent-message-contact', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'agdpevent', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'agdpevent-avec-email', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'agdpevent-cree-depuis', array(__CLASS__, 'shortcodes_callback') );
		
		add_shortcode( 'agdpevent-modifier-evenement', array(__CLASS__, 'shortcodes_callback') );
		
		add_shortcode( 'agdpevents', array(__CLASS__, 'shortcodes_callback') );
		
		add_shortcode( 'agdpstats', array(__CLASS__, 'shortcodes_callback') );
		
		add_shortcode( 'post', array(__CLASS__, 'shortcode_post_callback') );

	}

	/**
	 * [post]
	 * [post info="ev-email"]
	 * [post info="ev-telephone"]
	 * [post info="mailto"]
	 * [post info="uri"] [post info="url"]
	 * [post info="a"] [post info="link"]
	 * [post info="post_type"]
	 * [post info="dump"]
	 */
	public static function shortcode_post_callback($atts, $content = '', $shortcode = null){
		$post = get_post();
		if(!$post){
			echo $content;
			return;
		}

		if( ! is_array($atts)){
			$atts = array();
		}

		if(! array_key_exists('info', $atts)
		|| ! ($info = $atts['info']))
			$info = 'post_title';

		switch($info){
			case 'uri':
			case 'url':
				return $_SERVER['HTTP_REFERER'];
			case 'link':
			case 'a':
				return sprintf('<a href="%s">%s - %s</a>', AgendaPartage_Evenement::get_post_permalink($post), 'AgendaPartage', $post->post_title);

			case 'mailto':
				$email = get_post_meta( $post->ID, 'ev-email', true);
				return sprintf('<a href="mailto:%s">%s</a>', antispambot(sanitize_email($email)), $post->post_title);//TODO anti-spam

			case 'dump':
				return sprintf('<pre>%s</pre>', 'shortcodes dump : ' . var_export($post, true));

			case 'title':
				$info = 'post_title';

			default :
				if(isset($post->$info))
					return $post->$info;
				return get_post_meta( $post->ID, $info, true);

		}
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
			$key = AGDP_EVENT_SECRETCODE ;
			if(array_key_exists($key, $atts)){
				$_POST[$key] = $_REQUEST[$key] = $atts[$key];
				unset($atts[$key]);
			}
		}
		// Si attribut toggle [agdpevent-details toggle="Contactez-nous !"]
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

		//De la forme [agdpevents liste] ou [agdpevents-calendrier]
		if($shortcode == 'agdpevents' || str_starts_with($shortcode, 'agdpevents-')){
			return self::shortcodes_agdpevents_callback($atts, $content, $shortcode);
		}
		//De la forme [agdpstats stat] ou [agdpstats-stat]
		if($shortcode == 'agdpstats' || str_starts_with($shortcode, 'agdpstats-')){
			return self::shortcodes_agdpstats_callback($atts, $content, $shortcode);
		}
		return self::shortcodes_agdpevent_callback($atts, $content, $shortcode);
	}
	
	/**
	* [agdpevent info=titre|description|dates|localisation|details|message-contact|modifier-evenement]
	* [agdpevent-titre]
	* [agdpevent-description]
	* [agdpevent-dates]
	* [agdpevent-localisation]
	* [agdpevent-details]
	* [agdpevent-message-contact]
	* [agdpevent-avec-email]
	* [agdpevent-modifier-evenement]
	*/
	private static function shortcodes_agdpevent_callback($atts, $content = '', $shortcode = null){
		
		$post = AgendaPartage_Evenement::get_post();
		
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
		
		if($shortcode == 'agdpevent'
		&& count($atts) > 0){
			
			$specificInfos = ['titre', 'localisation', 'description', 'dates', 'message-contact', 'modifier-evenement', 'details', 'categories'];
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
			case 'agdpevent-titre':

				$meta_name = 'ev-' . substr($shortcode, strlen('agdpevent-')) ;
				$val = isset( $post->post_title ) ? $post->post_title : '';
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'agdpevent-description':

				$meta_name = 'ev-' . substr($shortcode, strlen('agdpevent-')) ;
				$val = isset( $post->post_content ) ? $post->post_content : '';
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'agdpevent-localisation':

				$meta_name = 'ev-' . substr($shortcode, strlen('agdpevent-')) ;
				$val = get_post_meta($post_id, $meta_name, true);
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'agdpevent-dates':

				$meta_name = 'ev-' . substr($shortcode, strlen('agdpevent-')) ;
				$val = AgendaPartage_Evenement::get_event_dates_text( $post_id );
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'agdpevent-cree-depuis':

				$val = date_diff_text($post->post_date);
				
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'agdpevent-diffusions':
				$tax_name = AgendaPartage_Evenement::taxonomy_diffusion;
			case 'agdpevent-cities':
				if(!isset($tax_name) || !$tax_name)
					$tax_name = AgendaPartage_Evenement::taxonomy_city;
			case 'agdpevent-categories':
				if(!isset($tax_name) || !$tax_name)
					$tax_name = AgendaPartage_Evenement::taxonomy_ev_category;
				$meta_name = 'ev-' . substr($shortcode, strlen('agdpevent-')) ;
				$terms = AgendaPartage_Evenement::get_post_terms( $tax_name, $post_id, 'names');
				if($terms){
					$val = implode(', ', $terms);
					if($no_html)
						$html = $val;
					else{
						$html = '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
							. ($label ? '<span class="label"> '.$label.'<span>' : '')
							. htmlentities($val)
							. '</div>';
					}
				}
				return $html;
				break;

			case 'agdpevent-message-contact':
				
				$meta_name = 'ev-organisateur' ;
				$organisateur = AgendaPartage_Evenement::get_post_meta($post_id, $meta_name, true, false);
				if( ! $organisateur) {
					return;
				}

				$meta_name = 'ev-email' ;
				$email = AgendaPartage_Evenement::get_post_meta($post_id, $meta_name, true, false);
				if(!$email) {
					return AgendaPartage::icon('warning'
						, 'Vous ne pouvez pas envoyer de message, l\'évènement n\'a pas indiqué d\'adresse email.', 'agdp-error-light', 'div');
				}

				$form_id = AgendaPartage::get_option('agdpevent_message_contact_form_id');
				if(!$form_id){
					return AgendaPartage::icon('warning'
						, 'Un formulaire de message aux organisteurs d\'évènement n\'est pas défini dans les réglages de AgendaPartage.', 'agdp-error-light', 'div');
				}

				$val = sprintf('[contact-form-7 id="%s" title="*** message à l\'organisateur d\'évènement ***"]', $form_id);
				return '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
					. do_shortcode( $val)
					. '</div>';


			case 'agdpevent-modifier-evenement':

				return AgendaPartage_Evenement_Edit::get_agdpevent_edit_content();

			case 'agdpevent-details':

				$html = '';
				$val = isset( $post->post_title ) ? $post->post_title : '';
					if($val)
						$html .= esc_html($val) . '<br>';
					
				$meta_name = 'ev-dates'; 
					$val = AgendaPartage_Evenement::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= esc_html($val) . '<br>';

				$meta_name = 'ev-organisateur'; 
					$val = AgendaPartage_Evenement::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= esc_html($val) . '<br>';

				$meta_name = 'ev-localisation';
					$val = AgendaPartage_Evenement::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= esc_html($val) . '<br>';

				$meta_name = 'ev-email';
					$val = AgendaPartage_Evenement::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= make_mailto($val) . '<br>';

				$meta_name = 'ev-siteweb';
					$val = AgendaPartage_Evenement::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= make_clickable(esc_html($val)) . '<br>';

				$meta_name = 'ev-phone';
					$val = AgendaPartage_Evenement::get_post_meta($post_id, $meta_name, true, false);
					if($val)
						$html .= antispambot($val) . '<br>';
				
				if(! $html )
					return '';
				
				if($no_html){
					$html = do_shortcode( wp_kses_post($html.$content));
					$html = str_ireplace('<br>', "\r\n", $html);
					//TODO
					$html .= sprintf('Evènement créé le %s.\r\n', get_the_date()) ;
					
				}
				else {
					
					// date de création
					$html .= '<div class="entry-details">' ;
					$html .= sprintf('<span>évènement créé le %s</span>', get_the_date()) ;
					if(get_the_date() != get_the_modified_date())
						$html .= sprintf('<span>, mise à jour du %s</span>', get_the_modified_date()) ;
					$html .= '</div>' ;
					$html = do_shortcode( wp_kses_post($html.$content));
					$html = '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
						. ($label ? '<span class="label"> '.$label.'<span>' : '')
						. $html
						. '</div>';
				}
				return $html;
				
			case 'agdpevent':
				$meta_name = $atts['info'] ;
				if(!$meta_name)
					return '<div class="error">Le paramètre info="'.$meta_name.'" du shortcode "agdpevent" est inconnu.</div>';
				$val = AgendaPartage_Evenement::get_post_meta($post_id, 'ev-' . $meta_name, true, false);
				
				if($val)
					switch($meta_name){
						case 'siteweb' :
							$val = make_clickable(esc_html($val));
							break;
						case 'phone' :
						case 'email' :
							$val = antispambot(esc_html($val), -0.5);
							break;
					}
				if($val || $content){
					return '<div class="agdp-agdpevent">'
						. ($label ? '<span class="label"> '.$label.'<span>' : '')
						. do_shortcode( wp_kses_post($val . $content))
						. '</div>';
				}
				break;

			// shortcode conditionnel
			case 'agdpevent-condition':
				$meta_name = $atts['info'] ;
				if(!$meta_name)
					return '<div class="error">Le paramètre "info" du shortcode "agdpevent-condition" est inconnu.</div>';
				$val = AgendaPartage_Evenement::get_post_meta($post_id, 'ev-' . $meta_name, true, false);
				if($val || $content){
					return do_shortcode( wp_kses_post($val . $content));
				}
				break;


			// shortcode conditionnel sur email
			case 'agdpevent-avec-email':
				$meta_name = 'ev-email' ;
				$email = AgendaPartage_Evenement::get_post_meta($post_id, $meta_name, true, false);
				if(is_email($email)){
					return do_shortcode( wp_kses_post($content));
				}
				return '';
				

			default:
			
				return '<div class="error">Le shortcode "'.$shortcode.'" inconnu.</div>';
		}
	}
	
	/**
	* [agdpevents]
	* [agdpevents liste|list|calendar|calendrier|week|semaine|ics]
	* [agdpevents mode:liste|list|calendar|calendrier|week|semaine|ics]
	*/
	public static function shortcodes_agdpevents_callback($atts, $content = '', $shortcode = null){
		
		if($shortcode == 'agdpevents'
		&& count($atts) > 0){
			if(array_key_exists('mode', $atts))
				$shortcode .= '-' . $atts['mode'];
			elseif(array_key_exists('0', $atts))
				$shortcode .= '-' . $atts['0'];
		}

		switch($shortcode){
			case 'agdpevents-liste':
				$shortcode = 'agdpevents-list';
			case 'agdpevents-list':
				
				return AgendaPartage_Evenements::get_list_html( $content );
				
			case 'agdpevents-email':
				
				$html = AgendaPartage_Evenements::get_list_for_email( $content );
				return $html;

			default:
				return '<div class="error">Le shortcode "'.$shortcode.'" inconnu.</div>';
		}
	}

 	
	/**
	 * Get code secret from Ajax query, redirect to post url
	 */
	public static function on_wp_ajax_agdpevent_shortcode_cb() {
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
	
	
	public static function shortcodes_agdpstats_callback($atts, $content = '', $shortcode = null){
		require_once(AGDP_PLUGIN_DIR . '/admin/class.agendapartage-admin-stats.php');
		if( count($atts)) {
			if( in_array('postscounters', $atts) )
				return AgendaPartage_Admin_Stats::posts_stats_counters() . $content;
			if( in_array('eventscounters', $atts) )
				return AgendaPartage_Admin_Stats::agdpevents_stats_counters() . $content;
			if( in_array('covoituragescounters', $atts) )
				return AgendaPartage_Admin_Stats::agdpevents_stats_counters() . $content;
		}
		return AgendaPartage_Admin_Stats::get_stats_result() . $content;
	}
	
	// shortcodes //
	///////////////
	
	// define the wpcf7_mail_components callback 
	public static function on_wpcf7_mail_components( $components, $wpcf7_get_current_contact_form, $instance ){ 
		$components['body'] = do_shortcode($components['body']);
		return $components;
	} 
}
