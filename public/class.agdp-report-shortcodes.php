<?php

/**
 * AgendaPartage -> Forum
 * Page liée à une mailbox
 * 
 * Définition des shortcodes 
 *
 *	
 */
class Agdp_Report_Shortcodes {


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
		
		add_action( 'wp_ajax_'.AGDP_TAG.'_shortcode', array(__CLASS__, 'on_wp_ajax_report_shortcode_cb') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_shortcode', array(__CLASS__, 'on_wp_ajax_report_shortcode_cb') );
	}

	/////////////////
 	// shortcodes //
 	/**
 	 * init_shortcodes
 	 */
	public static function init_shortcodes(){

		add_shortcode( 'report', array(__CLASS__, 'shortcodes_callback') );

	}

	/**
	* Callback des shortcodes
	*/
	public static function shortcodes_callback($atts, $content = '', $shortcode = null){

		if( is_admin() 
		&& ! wp_doing_ajax()
		&& ! Agdp_Newsletter::is_sending_email())
			return;
		
		if( ! is_array($atts)){
			$atts = array();
		}
		// debug_log(__CLASS__.'::'.__FUNCTION__, $shortcode, $atts);
		
		//champs sans valeur transformer en champ=true
		foreach($atts as $key=>$value){
			if(is_numeric($key)){
				if( $key == 0 )
					$atts['report_id']=$value;
				elseif( ! array_key_exists($value, $atts)){
					unset($atts[$key]);
					if( ($i = strpos($value, '=', 1)) > 0
					&& ( $value[0] === ':' || $value[0] === '@' )){
						$key = substr($value, 0, $i);
						$value = substr($value, $i + 1);
						if( strlen($value) > 1 && $value[0] === '"' && $value[strlen($value)-1] === '"' )
							$value = substr($value, 1, strlen($value) - 2);
						$atts[ $key ] = $value;
					}
					else
						$atts[$value] = true;
				}
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
			$key = 'report_id';
			if(array_key_exists($key, $atts)){
			}
		}
		// Si attribut toggle [report-details toggle="Contactez-nous !"]
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
		return self::shortcodes_report_callback($atts, $content, $shortcode);
	}
	
	/**
	* [report info=titre|description]
	* [report-titre]
	* [report-description]
	* [report-prop hide_comments=0 mark_as_ended=0 reply_link=0 comment_form=0]
	*/
	private static function shortcodes_report_callback($atts, $content = '', $shortcode = null){
		$label = isset($atts['label']) ? $atts['label'] : '' ;
		
		$html = '';
		
		foreach($atts as $key=>$value){
			if(is_numeric($key)){
				$atts[$value] = true;
				if($key != '0')
					unset($atts[$key]);
			}
		}
		// debug_log(__CLASS__.'::'.__FUNCTION__, $shortcode, $atts, $_REQUEST);
		if($shortcode == 'report'
		&& count($atts) > 0){
			
			$specificInfos = [];
			if(array_key_exists('info', $atts)
			&& in_array($atts['info'], $specificInfos))
				$shortcode .= '-' . $atts['info'];
			if( ! is_associative_array($atts))
				if(is_numeric($atts['0']))
					$atts['report_id'] = $atts['0'];
				elseif( ! array_key_exists('info', $atts))
					if(in_array($atts['0'], $specificInfos))
						$shortcode .= '-' . $atts['0'];
					else
						$atts['info'] = $atts['0'];
		}
		$no_html = isset($atts['no-html']) && $atts['no-html']
				|| isset($atts['html']) && $atts['html'] == 'no';
		
		// report_id
		if( ! empty($atts['report_id']) )
			$report_id = $atts['report_id'];
		if( empty($report_id) ){
			global $post;
			if( $post && $post->post_type === Agdp_Report::post_type ){
				$report = $post;
				$report_id = $post->ID;
			}
		}
		if( empty($report) ){
			if( ! $report_id ){
				return sprintf('Shortcode %s : il manque la référence du report. <code>%s</code>', $shortcode, print_r($atts, true));
			}
			if( is_numeric($report_id) ){
				$report = get_post( $report_id );
				if( $report && $report->post_type !== Agdp_Report::post_type ){
					return sprintf('Le document %d n\'est pas du type %s. <code>%s</code>', $report->ID, Agdp_Report::post_type, print_r($atts, true));
				}
			}
			elseif( is_string($report_id) ){
				if( strpos($report_id, '|') && is_numeric( substr($report_id, 0, strpos($report_id, '|') ) ) )
					$report_id = substr($report_id, 0, strpos($report_id, '|') );
				else
					$report_id = trim( $report_id, '"\'' );
				$relative_to = false;
				$report = get_relative_page( $report_id, $relative_to, Agdp_Report::post_type );
			}
		}
		if( ! $report ) {
			return sprintf('Référence du rapport incorrect : %s. <code>%s</code>', $report_id, print_r($atts, true));
		}
		$report_id = $report->ID;
			
		
		$sql_variables = [];
		foreach($atts as $key=>$value){
			if( ! is_numeric($key) )
				if( $key[0] === ':' )
					$sql_variables[ substr($key,1) ] = $value;
				elseif( $key[0] === '@' )
					$sql_variables[ $key ] = $value;
		}
		switch($shortcode){
				
			case 'report':
				$val = false;
				
				// infos
				if( empty($atts['info']) ){
					$atts['info'] = 'table';
				}
				$meta_name = $atts['info'] ;
				switch($meta_name){
					case 'table' :
					case 'results' :
						$val = Agdp_Report::get_report_html( $report_id, false, $sql_variables );
						break;
				}
				if($val === false)
					$val = get_post_meta( $report_id, $meta_name, true, false);
				
				if($val || $content){
					if($label)
						return '<div class="agdp-report">'
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
	public static function on_wp_ajax_report_shortcode_cb() {
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
	 * get_shortcode
	 */
 	public static function get_shortcode($report, $sql_variables = false){
		$variables = [];
		if( $sql_variables && is_string( $sql_variables ) ){
			$variables = $sql_variables; 
		}
		else {
			$options = 'shortcode';
			$sql_variables = Agdp_Report_Variables::normalize_sql_variables( $report, $sql_variables, $options );
			foreach($sql_variables as $var=>$variable){
				if( ! is_array($variable) )
					$value = /* '('.gettype($variable) . ')' . */ print_r($variable, true);
				else {
					if( isset($variable['is_private'])
					&& $variable['is_private']
					&& $variable['is_private'] !== '0'
					)
						continue;
					
					if( ! isset($variable['value']) )
						$value = '';
					elseif( is_numeric($variable['value']) )
						$value = $variable['value'];
					elseif( is_array($variable['value']) )
						$value = $variable['value'] ? implode( '|', $variable['value'] ) : '';
					else
						$value = '"'. str_replace("\n", '|',$variable['value']) . '"';
				}
				$variables[$var] = ':' . $var . '='. $value;
			}
			$variables = implode( ' ', $variables );
		}
		$shortcode = sprintf('[%s %d|%s %s]'
					, Agdp_Report::shortcode
					, $report->ID
					, get_post_path($report, '/')
					, $variables
				);
		return $shortcode;
	}
	
	// shortcodes //
	///////////////
}
