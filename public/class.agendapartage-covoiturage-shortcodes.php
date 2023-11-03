<?php

/**
 * AgendaPartage -> Covoiturage
 * Custom post type for WordPress.
 * 
 * Définition des shortcodes 
 *
 * Voir aussi AgendaPartage_Admin_Covoiturage
 */
class AgendaPartage_Covoiturage_Shortcodes {


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
		
		add_action( 'wp_ajax_'.AGDP_TAG.'_shortcode', array(__CLASS__, 'on_wp_ajax_covoiturage_shortcode_cb') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_shortcode', array(__CLASS__, 'on_wp_ajax_covoiturage_shortcode_cb') );
	}

	/////////////////
 	// shortcodes //
 	/**
 	 * init_shortcodes
 	 */
	public static function init_shortcodes(){

		add_shortcode( 'covoiturage-titre', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'covoiturage-categories', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'covoiturage-cities', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'covoiturage-diffusions', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'covoiturage-description', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'covoiturage-dates', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'covoiturage-localisation', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'covoiturage-details', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'covoiturage-message-contact', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'covoiturage', array(__CLASS__, 'shortcodes_callback') );
		add_shortcode( 'covoiturage-avec-email', array(__CLASS__, 'shortcodes_callback') );
		
		add_shortcode( 'covoiturage-modifier-covoiturage', array(__CLASS__, 'shortcodes_callback') );
		
		add_shortcode( 'covoiturages', array(__CLASS__, 'shortcodes_callback') );

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
			$key = AGDP_COVOIT_SECRETCODE ;
			if(array_key_exists($key, $atts)){
				$_POST[$key] = $_REQUEST[$key] = $atts[$key];
				unset($atts[$key]);
			}
		}
		// Si attribut toggle [covoiturage-details toggle="Contactez-nous !"]
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

		//De la forme [covoiturages liste] ou [covoiturages-calendrier]
		if($shortcode == 'covoiturages' || str_starts_with($shortcode, 'covoiturages-')){
			return self::shortcodes_covoiturages_callback($atts, $content, $shortcode);
		}
		return self::shortcodes_covoiturage_callback($atts, $content, $shortcode);
	}
	
	/**
	* [covoiturage info=titre|description|dates|localisation|details|message-contact|modifier-covoiturage]
	* [covoiturage-titre]
	* [covoiturage-description]
	* [covoiturage-dates]
	* [covoiturage-localisation]
	* [covoiturage-details]
	* [covoiturage-message-contact]
	* [covoiturage-avec-email]
	* [covoiturage-modifier-covoiturage]
	*/
	private static function shortcodes_covoiturage_callback($atts, $content = '', $shortcode = null){
		
		$post = AgendaPartage_Covoiturage_Edit::get_covoiturage_post();
		
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
		
		if($shortcode == 'covoiturage'
		&& count($atts) > 0){
			
			$specificInfos = ['titre', 'localisation', 'description', 'dates', 'message-contact', 'modifier-covoiturage', 'details'];
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
			case 'covoiturage-titre':

				$meta_name = 'cov-' . substr($shortcode, strlen('covoiturage-')) ;
				$val = isset( $post->post_title ) ? $post->post_title : '';
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-covoiturage agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'covoiturage-description':

				$meta_name = 'cov-' . substr($shortcode, strlen('covoiturage-')) ;
				$val = isset( $post->post_content ) ? $post->post_content : '';
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-covoiturage agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'covoiturage-dates':

				$meta_name = 'cov-' . substr($shortcode, strlen('covoiturage-')) ;
				$val = AgendaPartage_Covoiturage::get_covoiturage_dates_text( $post_id );
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-covoiturage agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'covoiturage-diffusions':
				$tax_name = AgendaPartage_Covoiturage::taxonomy_diffusion;
			case 'covoiturage-cities':
				if(!isset($tax_name) || !$tax_name)
					$tax_name = AgendaPartage_Covoiturage::taxonomy_city;
			case 'covoiturage-message-contact':
				
				$meta_name = 'cov-organisateur' ;
				$organisateur = AgendaPartage_Covoiturage::get_post_meta($post_id, $meta_name, true, false);
				if( ! $organisateur) {
					return;
				}

				$meta_name = 'cov-email' ;
				$email = AgendaPartage_Covoiturage::get_post_meta($post_id, $meta_name, true, false);
				if(!$email) {
					return AgendaPartage::icon('warning'
						, 'Vous ne pouvez pas envoyer de message, le covoiturage n\'a pas d\'adresse email associé.', 'agdp-error-light', 'div');
				}

				$form_id = AgendaPartage::get_option('covoiturage_message_contact_form_id');
				if(!$form_id){
					return AgendaPartage::icon('warning'
						, 'Un formulaire de message aux organisteurs du covoiturage n\'est pas défini dans les réglages de AgendaPartage.', 'agdp-error-light', 'div');
				}

				$val = sprintf('[contact-form-7 id="%s" title="*** message à l\'organisateur du covoiturage ***"]', $form_id);
				return '<div class="agdp-covoiturage agdp-'. $shortcode .'">'
					. do_shortcode( $val)
					. '</div>';


			case 'covoiturage-modifier-covoiturage':

				return AgendaPartage_Covoiturage_Edit::get_covoiturage_edit_content();

			case 'covoiturage-details':

				$html = '';
				$val = isset( $post->post_title ) ? $post->post_title : '';
					if($val)
						$html .= esc_html($val) . '</br>';
					
				$meta_name = 'cov-dates'; 
					$val = AgendaPartage_Covoiturage::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= esc_html($val) . '</br>';

				$meta_name = 'cov-organisateur'; 
					$val = AgendaPartage_Covoiturage::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= esc_html($val) . '</br>';

				$meta_name = 'cov-email';
					$val = AgendaPartage_Covoiturage::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= make_mailto($val) . '</br>';

				$meta_name = 'cov-phone-show';
					$val = AgendaPartage_Covoiturage::get_post_meta($post_id, $meta_name, true, false);
					$show_email = !! $val;

				$meta_name = 'cov-phone';
				if( $show_email ){ //TODO sans contrainte si envoyé à l'auteur
					$val = AgendaPartage_Covoiturage::get_post_meta($post_id, $meta_name, true, false);
					if($val)
						$html .= antispambot($val) . '</br>';
				}
				
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
					$html .= sprintf('<span>covoiturage créé le %s</span>', get_the_date()) ;
					if(get_the_date() != get_the_modified_date())
						$html .= sprintf('<span>, mise à jour du %s</span>', get_the_modified_date()) ;
					$html .= '</div>' ;
					$html = do_shortcode( wp_kses_post($html.$content));
					$html = '<div class="agdp-covoiturage agdp-'. $shortcode .'">'
						. ($label ? '<span class="label"> '.$label.'<span>' : '')
						. $html
						. '</div>';
				}
				return $html;
				
			case 'covoiturage':
				$meta_name = $atts['info'] ;
				if(!$meta_name)
					return '<div class="error">Le paramètre info="'.$meta_name.'" du shortcode "covoiturage" est inconnu.</div>';
				$val = AgendaPartage_Covoiturage::get_post_meta($post_id, 'cov-' . $meta_name, true, false);
				
				if($val)
					switch($meta_name){
						case 'phone' :
							$val = AgendaPartage_Covoiturage::get_phone_html($post_id);
							break;
						case 'email' :
							$val = antispambot(esc_html($val), -0.5);
							break;
					}
				if($val || $content){
					return '<div class="agdp-covoiturage">'
						. ($label ? '<span class="label"> '.$label.'<span>' : '')
						. do_shortcode( $val . wp_kses_post($content))
						. '</div>';
				}
				break;

			// shortcode conditionnel
			case 'covoiturage-condition':
				$meta_name = $atts['info'] ;
				if(!$meta_name)
					return '<div class="error">Le paramètre "info" du shortcode "covoiturage-condition" est inconnu.</div>';
				$val = AgendaPartage_Covoiturage::get_post_meta($post_id, 'cov-' . $meta_name, true, false);
				if($val || $content){
					return do_shortcode( wp_kses_post($val . $content));
				}
				break;


			// shortcode conditionnel sur email
			case 'covoiturage-avec-email':
				$meta_name = 'cov-email' ;
				$email = AgendaPartage_Covoiturage::get_post_meta($post_id, $meta_name, true, false);
				if(is_email($email)){
					return do_shortcode( wp_kses_post($content));
				}
				return '';
				

			default:
			
				return '<div class="error">Le shortcode "'.$shortcode.'" inconnu.</div>';
		}
	}
	
	/**
	* [covoiturages]
	* [covoiturages liste|list|calendar|calendrier|week|semaine|ics]
	* [covoiturages mode:liste|list|calendar|calendrier|week|semaine|ics]
	*/
	public static function shortcodes_covoiturages_callback($atts, $content = '', $shortcode = null){
		
		if($shortcode == 'covoiturages'
		&& count($atts) > 0){
			if(array_key_exists('mode', $atts))
				$shortcode .= '-' . $atts['mode'];
			elseif(array_key_exists('0', $atts))
				$shortcode .= '-' . $atts['0'];
		}

		switch($shortcode){
			case 'covoiturages-liste':
				$shortcode = 'covoiturages-list';
			case 'covoiturages-list':
				
				return AgendaPartage_Covoiturages::get_list_html( $content );
				
			case 'covoiturages-email':
				
				$html = AgendaPartage_Covoiturages::get_list_for_email( $content );
				return $html;

			default:
				return '<div class="error">Le shortcode "'.$shortcode.'" inconnu.</div>';
		}
	}

 	
	/**
	 * Get code secret from Ajax query, redirect to post url
	 */
	public static function on_wp_ajax_covoiturage_shortcode_cb() {
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
			if( in_array('covoituragescounters', $atts) )
				return AgendaPartage_Admin_Stats::covoiturages_stats_covoituragescounters() . $content;
		}
		return AgendaPartage_Admin_Stats::get_stats_result() . $content;
	}
	
	// shortcodes //
	///////////////
}
