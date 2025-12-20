<?php

/**
 * AgendaPartage -> Forum
 * Page liée à une mailbox
 * 
 * Définition des shortcodes 
 *
 *	
 */
class Agdp_Report_Shortcodes extends Agdp_Shortcodes {

	const post_type = AGDP_Report::post_type;
	
	const info_shortcodes = [ 
		'cell',
		'open',
		'next'
	];
	
	const default_attr = 'report_id';
	
	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {
			
			self::$initiated = true;
			
			parent::init();

			static::add_shortcodes();
		}
	}
	
	/**
	* [report info=titre|description]
	* [report-titre]
	* [report-description]
	* [report-prop hide_comments=0 mark_as_ended=0 reply_link=0 comment_form=0]
	*/
	protected static function do_shortcode($atts, $content = '', $shortcode = null){
		$label = isset($atts['label']) ? $atts['label'] : '' ;
		
		$html = '';
		
		// debug_log(__CLASS__.'::'.__FUNCTION__, $shortcode, $atts, $_REQUEST);
		// wp_die( __CLASS__ .'::'. __FUNCTION__ );
		
		if( ! empty($atts['report_id']) )
			$report = static::get_post( $atts['report_id'], static::post_type );
		else
			$report = false;
		
		if( ! $report ) {
			return sprintf('Référence du rapport incorrect : %s. <code>%s</code>', @$atts['report_id'], print_r($atts, true));
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
				
			case 'cell' :
			case 'cellule' :
				$options = [];
				$dbResults = Agdp_Report::get_report_results( $report_id, false, $sql_variables, $options );
				$val = null;
				if( is_array($dbResults) ){
					foreach( $dbResults[0] as $val )
						break;
				}
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
	}
 	
	/**
	 * build_shortcode
	 */
 	public static function build_shortcode($report, $sql_variables = false){
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
		
		$shortcode = parent::build_shortcode( $report, $variables );
		
		return $shortcode;
	}
	
}
