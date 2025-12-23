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
		'cell' => [ 'default_attr' => 'cell' ],
		'open',
		'loop',
		'next'
	];
	
	const default_attr = 'report_id';
	
	private static $initiated = false;
	
	public static $report_stack = [];

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
		$open_report = true;
		
		// debug_log(__CLASS__.'::'.__FUNCTION__, $shortcode, $atts/* , $_REQUEST */);
		// wp_die( __CLASS__ .'::'. __FUNCTION__ );
		
		if( ! empty($atts['report_id']) )
			$report = static::get_post( $atts['report_id'], static::post_type );
		elseif( count(self::$report_stack) > 0 ){
			$report = self::$report_stack[count(self::$report_stack)-1]['report'];
			$open_report = false;
		}
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
				$val = Agdp_Report::get_report_html( $report_id, false, $sql_variables );
				if($val)
					return '<div class="agdp-report">'
						. ($label ? '<span class="label"> '.$label.'<span>' : '')
						. do_shortcode( $val . wp_kses_post($content))
						. '</div>';
				break;
				
			case 'cell' :
				$val = null;
				$row = 0;
				if( $open_report && $report_id ){
					$options = [];
					$dbResults = Agdp_Report::get_report_results( $report, false, $sql_variables, $options );
				}
				else{
					if( count(self::$report_stack) === 0 ){
						$val = sprintf('Aucun rapport défini.');
					}
					else {
						$row = self::$report_stack[count(self::$report_stack)-1]['row'];
						$dbResults = self::$report_stack[count(self::$report_stack)-1]['dbResults'];
					}
				}
				if( $val === null
				&& is_array($dbResults) ){
					if( empty($atts['cell']) )
						$cell = 0;
					elseif( is_numeric($atts['cell']) )
						$cell = $atts['cell'] * 1;
					else
						$cell = $atts['cell'];
					if( empty($atts['cell']) )
						$cell = 0;
					elseif( is_numeric($atts['cell']) )
						$cell = $atts['cell'] * 1;
					else
						$cell = $atts['cell'];
					$field_index = 0;
					$field_found = false;
					foreach( $dbResults[$row] as $field => $field_value ){
						if( ($cell == $field)
						|| ($cell === $field_index) ){
							$val = $field_value;
							$field_found = true;
							break;
						}
						$field_index++;
					}
					if( ! $field_found ){
						debug_log( __FUNCTION__, "Colonne \"$cell\" introuvable", $dbResults[$row]);
					}
					elseif($label)
						$val = '<span class="label"> '.$label.'<span>' . $val;
				}
				break;
				
			case 'loop' :
			case 'open' :
				$options = [];
				$dbResults = Agdp_Report::get_report_results( $report, false, $sql_variables, $options );
				self::$report_stack[] = [
					'report_id' => $report_id,
					'report' => $report,
					'sql_variables' => $sql_variables,
					'options' => $options,
					'dbResults' => $dbResults,
					'count' => count($dbResults),
					'row' => 0,
				];
				$val = null;
				if( $content ){
					$html = '<div class="agdp-report">'
						. ($label ? '<span class="label"> '.$label.'<span>' : '');
					if( $meta_name === 'loop' ){
						for( $row = 0; $row < count($dbResults); $row++ ){
							self::$report_stack[count(self::$report_stack)-1]['row'] = $row;
							$html .= do_shortcode( wp_kses_post($content));
						}
					}
					else
						$html .= do_shortcode( wp_kses_post($content));
					$html .= '</div>';
					return $html;
				}
				break;
				
			case 'close' :
				array_pop( self::$report_stack );
				$val = null;
				break;
				
			case 'next' :
				if( count(self::$report_stack) === 0 ){
					$val = sprintf('Aucun rapport ouvert. Le shortcode [report-next] doit suivre un shortcode [report-open].');
				}
				elseif( $report_id 
				&& self::$report_stack[count(self::$report_stack)-1]['report_id'] !== $report_id ){
					$val = sprintf('Rapport ouvert différent ( %s != %s', self::$report_stack[count(self::$report_stack)-1]['report_id'], $report_id);
				}
				else {
					self::$report_stack[count(self::$report_stack)-1]['row'] += 1;
					if( self::$report_stack[count(self::$report_stack)-1]['row'] >= self::$report_stack[count(self::$report_stack)-1]['count'] ){
						
					}
				}
				break;
		}
		if($val === false)
			$val = get_post_meta( $report_id, $meta_name, true, false);
					
		if($val || $content){
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
