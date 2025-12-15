<?php

/**
 * AgendaPartage -> Report -> Agdp_Report_Variables
 * Variables des requêtes. Issue de la meta sql_variables
 * 
 * Voir Agdp_Report
 */
class Agdp_Report_Variables {

	const meta_name = 'sql_variables';
		
	// const user_role = 'author';
	
	public static $sql_global_vars;
	private static $sql_global_vars_init;
	
	private static $initiated = false;
	

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;

			self::init_constants();
		}
	}

	/**
	 * Constants
	 */
	public static function init_constants() {
		
		define( 'AGDP_VAR_BLOG_ID', '@BLOGID'); 
		define( 'AGDP_VAR_BLOG_NAME', '@BLOGNAME'); 
		define( 'AGDP_VAR_BLOG_URL', '@BLOGURL'); 
		define( 'AGDP_VAR_POST_ID', '@POSTID'); 
		define( 'AGDP_VAR_PARENT_ID', '@PARENTID'); 
		define( 'AGDP_VAR_POST_TYPE', '@POSTTYPE'); 
		define( 'AGDP_VAR_REPORT_ID', '@REPORTID'); 
		define( 'AGDP_VAR_REPORT_TITLE', '@REPORTTITLE'); 
		define( 'AGDP_REPORT_VAR_PREFIX', 'rv_');  
		define( 'AGDP_VAR_DBRESULTS', '@DBRESULTS'); 
		//dynamics
		define( 'AGDP_VAR_TAX_TERMS', '@TAX_TERMS'); 
		define( 'AGDP_VAR_POST_STATUSES', '@POST_STATUSES'); 
		
		self::$sql_global_vars = [
			AGDP_VAR_BLOG_ID => null,
			AGDP_VAR_BLOG_NAME => null,
			AGDP_VAR_BLOG_URL => null,
			AGDP_VAR_POST_ID => null,
			AGDP_VAR_PARENT_ID => null,
			AGDP_VAR_POST_TYPE => null,
			AGDP_VAR_REPORT_ID => null,
			AGDP_VAR_REPORT_TITLE => null,
		];
		self::$sql_global_vars_init = false;
	}

	/**
	 * SQL variables
	 */
 	public static function normalize_sql_variables( $report, $sql_variables, &$options ) {
		$report_id = $report->ID;
		
		if( ! $options )
			$options = [];
		elseif( is_string($options) )
			$options = [ 'mode' => $options ];
		elseif( ! empty($options['_normalize_sql_variables_done_' . $report_id ]) )
			return $options['_normalize_sql_variables_done_' . $report_id ];
		
		if( ! isset($options['mode']) )
			$options['mode'] = '';
			
		//valeurs des variables
		if( ! $sql_variables )
			$sql_variables = [];
		elseif( is_string($sql_variables) )
			$sql_variables = json_decode($sql_variables, true);
		if( $report_id && ! empty( $options['_default_sql_variables_' . $report_id ] ) )
			$default_sql_variables = $options['_default_sql_variables_' . $report_id ];
		else{
			$default_sql_variables = get_post_meta( $report_id, 'sql_variables', true );
			$options['_default_sql_variables_' . $report_id ] = $default_sql_variables;//cache
		}
		if( $default_sql_variables && is_string($default_sql_variables) ){
			$default_sql_variables = json_decode($default_sql_variables, true);
		}
		else
			$default_sql_variables = [];
		if( ! is_array($sql_variables) )
			$sql_variables = $default_sql_variables;
		else {
			$sql_variables = array_merge($default_sql_variables, $sql_variables);
		}
		//normalize sql_variables
		foreach($sql_variables as $var=>$value){
			if( ! is_array($value) ){
				if( isset($default_sql_variables[$var]) ){
					$sql_variables[$var] = $default_sql_variables[$var];
					$sql_variables[$var]['value'] = $value;
				}
				else {
					$sql_variables[$var] = [ 'value' => $value ];
				}
			}
		}
		$options['_normalize_sql_variables_done_' . $report_id ] = $sql_variables;
		return $sql_variables;
	}
	
	/**
	 * returns sql_global_vars initiated
	 */
 	public static function sql_global_vars( $report, $options = false ) {
		if( self::$sql_global_vars_init )
			return static::$sql_global_vars;
		
		global $post;
		if( ! $report ){
			if( ! empty( $_REQUEST['report_id'] ) ){
				$report_id = $_REQUEST['report_id'];
				$report = get_post($report_id);
			}
			elseif( $post && $post->post_type === self::post_type ){
				$report = $post;
				$report_id = $post->ID;
			}
			if( ! empty( $_REQUEST['post_id'] ) ){
				$post_id = $_REQUEST['post_id'];
				$post = get_post( $post_id );
				if( empty($report)
				&& $post && $post->post_type === self::post_type ){
					$report = $post;
					$report_id = $post->ID;
				}
			}
		}
		else
			$report_id = $report->ID;
		
		if( ! $post )
			$post = $report;
		
		static::$sql_global_vars_init = true;
		static::$sql_global_vars = array_merge( static::$sql_global_vars, [
			AGDP_VAR_BLOG_ID /* @BLOGID */ => get_current_blog_id(),
			AGDP_VAR_BLOG_NAME /* @BLOGNAME */ => get_bloginfo('name'),
			AGDP_VAR_BLOG_URL /* @BLOGURL */ => get_bloginfo('url'),
			AGDP_VAR_POST_ID => $post ? $post->ID : false,
			AGDP_VAR_PARENT_ID => $post ? $post->post_parent : false,
			AGDP_VAR_POST_TYPE => $post ? $post->post_type : false,
			AGDP_VAR_REPORT_ID => isset($report_id) ? $report_id : false,
			AGDP_VAR_REPORT_TITLE => isset($report) ? $report->post_title : false,
		]);
				
		return static::$sql_global_vars;
	}


	/**
	/**
	 * add_sql_global_vars
	 */
 	public static function add_sql_global_vars( $report, $sql, $sql_variables = false, &$options = false ) {
		if( ! $sql
		|| ! empty($options['_add_sql_global_vars']) )
			return Agdp_Report::get_sql_as_array( $sql );
		
		$options['_add_sql_global_vars'] = true;
		
		$vars = static::sql_global_vars( $report, $options );
		
		//get_global_vars_sql
		$set_vars =  static::get_global_vars_sql( $vars, $sql_variables, $options );
		
		// array_shift
		if( ! is_array($sql) )
			$sql = [ $set_vars, $sql ];
		else
			$sql = array_merge( [ $set_vars ], $sql );
		
		//Purge des éléments vides
		$sql = array_filter( $sql, function( $value ){ return trim($value, " ;\n\r") !== ''; } );
				
		return $sql;
	}
	
	/**
	 * get_global_vars_sql
	 */
 	public static function get_global_vars_sql( $vars, $sql_variables = false, $options = false ) {
		$set_vars = '';
		foreach( $vars as $var => $value ){
			if( $set_vars )
				$set_vars .= ', ';
			$set_vars .= sprintf(' %s = "%s"', $var, esc_attr( $value ));
		}
		// sql_variables commençant par @
		if( is_array($sql_variables) )
			foreach( $sql_variables as $var => $value ){
				if( $var[0] !== '@' )
					continue;
				if( $set_vars )
					$set_vars .= ', ';
				if( ! is_numeric($value) )
					$value = sprintf('"%s"', esc_attr($value) );
				$set_vars .= sprintf(' %s = %s', $var, $value);
			}
		if( $set_vars ){
			$set_vars = sprintf('SET %s', $set_vars);
		}
		
		return $set_vars;
	}
	
	
}
?>