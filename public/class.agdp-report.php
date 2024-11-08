<?php

/**
 * AgendaPartage -> Report
 * Custom post type for WordPress.
 * 
 * Définition du Post Type agdpreport
 *
 * Voir aussi Agdp_Admin_Report
 *
 * Une report dispatche les mails vers des posts ou comments.
 * Agdp_Forum traite les commentaires.
 */
class Agdp_Report extends Agdp_Post {

	const post_type = 'agdpreport';
	const taxonomy_report_style = 'report_style';
		
	// const user_role = 'author';
	
	public static $sql_global_vars;
	private static $sql_global_vars_init;
	
	private static $initiated = false;
	
	public static $wpdb = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;

			self::init_constants();
			
			self::init_hooks();
		}
	}

	/**
	 * Constants
	 */
	public static function init_constants() {
			
		define( 'AGDP_BLOG_PREFIX', '@.'); 
		define( 'AGDP_VAR_BLOG_ID', '@BLOGID'); 
		define( 'AGDP_VAR_BLOG_NAME', '@BLOGNAME'); 
		define( 'AGDP_VAR_BLOG_URL', '@BLOGURL'); 
		define( 'AGDP_VAR_POST_ID', '@POSTID'); 
		define( 'AGDP_VAR_POST_TYPE', '@POSTTYPE'); 
		define( 'AGDP_VAR_REPORT_ID', '@REPORTID'); 
		define( 'AGDP_REPORT_VAR_PREFIX', 'rv_');  
		define( 'AGDP_VAR_TAX_TERMS', '@TAX_TERMS'); 
		
		self::$sql_global_vars = [
			AGDP_VAR_BLOG_ID => null,
			AGDP_VAR_BLOG_NAME => null,
			AGDP_VAR_BLOG_URL => null,
			AGDP_VAR_POST_ID => null,
			AGDP_VAR_POST_TYPE => null,
			AGDP_VAR_REPORT_ID => null,
		];
		self::$sql_global_vars_init = false;
	}

	/**
	 * Hook
	 */
	public static function init_hooks() {
		
		global $pagenow;
		if ( $pagenow !== 'edit.php' && $pagenow !== 'post.php') {
			add_action( 'post_class', array(__CLASS__, 'on_post_class_cb'), 10, 3);
		}
		add_action( 'wp_ajax_'.AGDP_TAG.'_report_action', array(__CLASS__, 'on_ajax_action') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_report_action', array(__CLASS__, 'on_ajax_action') );
	}
	/*
	 **/
	/**
	*/
	public static function on_post_class_cb( $classes, $css_class, $post_id ){
		if( get_post_type($post_id) !== self::post_type )
			return $classes;
		
		$report = get_post($post_id);
		
		add_filter( 'the_content', array(__CLASS__, 'the_content'), 10, 1 );
		
		return $classes;
	}
	
	/**
	 * Retourne les styles possibles d'un rapport
	 */
	public static function get_report_styles( $post_id, $args = 'names' ) {
		return self::get_post_terms( self::taxonomy_report_style, $post_id, $args);
	}
	
	/**
	 * Retourne un objet $wpdb propre, sans risque de variables résiduelles.
	 */
	public static function wpdb( $reset = false) {
		if( ! $reset && self::$wpdb )
			return self::$wpdb;
		self::$wpdb = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		global $wpdb;
		self::$wpdb->set_blog_id( $wpdb->blogid, $wpdb->siteid );
		$global_wpdb = $wpdb;
			$wpdb = self::$wpdb;
			wp_set_wpdb_vars();
		$wpdb = $global_wpdb;
		return self::$wpdb;
	}

	/**
	 * SQL
	 */
 	public static function get_sql( $report = false, $sql = false, $sql_variables = false, &$options = false ) {
		
		$report_id = $report->ID;
		
		if( ! is_array($options) )
			$options = [];
		
		//sql
		if( ! $sql )
			$sql = get_post_meta( $report_id, 'sql', true );
		
		$sqls = self::get_sql_as_array( $sql );
		
		if( count($sqls) !== 1 ){
			$sqls_ne = [];
			foreach($sqls as $sql_u ){
				if( $sql_u )
					$sql_u = self::get_sql( $report, $sql_u, $sql_variables, $options );
				if( strcasecmp( $sql_u, 'STOP' ) === 0 )
					break;
				if( $sql_u )
					$sqls_ne = array_merge($sqls_ne, self::get_sql_as_array( $sql_u ));
			}
			switch( count($sqls_ne) ){
			case 0:
				return '';
			case 1:
				return $sqls_ne[0];
			default:
				return $sqls_ne;
			}
		}
		$sql = $sqls[0];
		
		if( ! empty( $options['_skip_sql_prepare'] ) )
			return $sql;
		
		// debug_log( __FUNCTION__ 
			// . ( empty($options[__FUNCTION__.':stack']) ? '' : '  >> ' . count($options[__FUNCTION__.':stack']) )
			// , $sql/* , $sql_variables */);
		
		$wpdb = self::wpdb();
		//blog_prefix : @.
		$blog_prefix = $wpdb->get_blog_prefix();
	    $sql = static::replace_sql_tables_prefix( $sql );
		
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
			if( ! $sql_variables )
				$sql_variables = json_decode($default_sql_variables, true);
			else
				$sql_variables = array_merge(json_decode($default_sql_variables, true), $sql_variables);
		}
			
		//comments
		$sql = self::remove_sql_comments( $sql );
		
		//strings ""
		$matches = [];
		$pattern = '/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/s';////"/\"(.{2,})(?<!\\\\)\"/"; //TODO simple quote
		$strings_prefix = uniqid('__sqlstr_');
		$sql_strings = [];
		while( preg_match( $pattern, $sql, $matches ) ){
			$string = $matches[1];
			$string = str_replace('\"', '"', $string);
			$variable = sprintf('%s_%d', $strings_prefix, count($sql_strings));
			$sql_strings[$variable] = $string;
			$sql = str_replace( $matches[0], ':' . $variable, $sql );
		}
							
		//Variables
		$matches = [];
		$allowed_format = '(?:[1-9][0-9]*[$])?[-+0-9]*(?: |0|\'.)?[-+0-9]*(?:\.[0-9]+)?'; //cf /wp-includes/class-wpdb.php
		$pattern = "/((?<!\\\\)\:|\@)([a-zA-Z_][a-zA-Z0-9_]*)(%($allowed_format)?[sdfFiIKJ][NLRT]?)?/";
		if( preg_match_all( $pattern, $sql, $matches ) ){
			$errors = [];
			$variables = [];
			$prepare = [];
			foreach($matches[2] as $index => $variable){
				//format
				$src = $matches[0][$index];
				$var_domain = $matches[1][$index];
				// :var
				if( $var_domain === ':' ) {
					//value
					if( ! isset( $variables[$variable] )){
						if( strpos($variable, $strings_prefix ) === 0 ){
							$value = $sql_strings[$variable];
						}
						else {
							//TODO faire mieux (tableau, json, ...)
							$request_key = sprintf('%s%s', AGDP_REPORT_VAR_PREFIX, $variable);
							if( isset($_REQUEST[ $request_key ]) )
								$value = $_REQUEST[ $request_key ];
							elseif( $sql_variables && isset($sql_variables[$variable]) && isset($sql_variables[$variable]['value']) )
								$value = $sql_variables[$variable]['value'];
							else
								$value = null;
						}
						$variables[$variable] = $value;
					}
					
					$format = $matches[3][$index];
					$format_args = $matches[4][$index];
					$format_IN = $format === '%IN';
					$format_Inject = $format === '%I';
					$format_LIKE = substr( $format, strlen($format_args) + 1, 1 ) === 'K';
					$format_JSON = substr( $format, strlen($format_args) + 1, 1 ) === 'J';
					if( $sql_variables && isset($sql_variables[$variable]) && isset($sql_variables[$variable]['type']) ){
						switch($sql_variables[$variable]['type']){
							case 'range': 
							case 'numeric':
							case 'number':
							case 'integer':
								if( ! $format )
									$format = '%d';
								break;
							case 'decimal':
							case 'float':
								if( ! $format )
									$format = '%f';
								break;
							case 'field':
								if( ! $format )
									$format = '%i';
								break;
							case 'table':
								if( ! $format )
									$format_Inject = $format = '%I';
								if( ! $variables[$variable] )
									$variables[$variable] = '';
								else
									$variables[$variable] = self::add_table_blog_prefix( $variables[$variable] );
								break;
							case 'column':
								if( ! $format )
									$format_Inject = $format = '%I';
								break;
							case 'checkboxes': 
								if( $format_IN ){
									if( $variables[$variable] ){
										if( is_string( $variables[$variable] ) ){
											if( $variables[$variable][0] === '|' )
												$variables[$variable] = explode( '|', substr($variables[$variable], 1, strlen($variables[$variable]) - 2 ));
											else
												$variables[$variable] = explode( "\n", $variables[$variable] );
										}
										$format = implode(', ', array_fill(0, count($variables[$variable]), '%s'));
									}
									else {
										$format = '%s';
									}
								}
								else {
									// ( :post_status = '' || :post_status = post.post_status || :post_status LIKE CONCAT('%|', post.post_status, '|%' )
									if( $variables[$variable] && is_string($variables[$variable]) && $variables[$variable][0] !== '|' ){
										$variables[$variable] = "|" . str_replace("\n", "|", $variables[$variable]) . "|";
									}
								}
								break;
							case 'asc_desc': 
								if( $variables[$variable] === '' )
									$variables[$variable] = 'ASC';
								elseif( ! $variables[$variable] )
									$variables[$variable] = 'DESC';
								$sql = preg_replace( '/' . preg_quote($src) . '(?!%)/', $variables[$variable], $sql );
								//skip prepare
								continue 2;
								
							case 'report': 
								$error = '';
								if( is_numeric($variables[$variable])
								 && ( $sub_report = get_post($variables[$variable]) )){
									if( ! isset($options[__FUNCTION__.':stack']) )
										$options[__FUNCTION__.':stack'] = [];
									elseif( $sub_report->ID == $report_id
									|| in_array( $report_id, $options[__FUNCTION__.':stack'] ) ){
										$errors[] = $error = sprintf('Le rapport "%d" provoque un appel récursif infini.', $variables[$variable]);
										$variables[$variable] = $error;
									}
									if( ! $error ){
										// if( isset($sql_variables[$variable]['report_sql']) )
											// $sub_sql = $sql_variables[$variable]['report_sql'];
										// else {
											array_push( $options[__FUNCTION__.':stack'], $report_id );
											// debug_log( __FUNCTION__ . ' sub_report' );
											$sub_sql = self::get_sql( $sub_report, false, $sql_variables, $options );
											// debug_log( __FUNCTION__ . ' sub_report DONE' );
											array_pop( $options[__FUNCTION__.':stack'] );
											$sql_variables[$variable]['report_sql'] = $sub_sql;
										// }
										
										if( ! $format ) { 
											//is SQL SET
											if( preg_match( '/^(?:\(|\s)*SET\s\@/i', $sql ) !== 0 ){
												// and not SELECT
												if( preg_match( '/\sSELECT\s/i', $sql ) === 0 )
													$format = $format_JSON = '%J';
											}
										}
										if( $format === '%d' ){
											//$variables[$variable] retourne l'id
										}
										elseif( ! $format_JSON ) {
											$sub_sql = self::sanitize_sub_report_sql( $sub_sql );
											
											$sql = preg_replace( '/' . preg_quote($src) . '(?!%)/', $sub_sql, $sql );
											//skip prepare
											continue 2;
										}
									}
								}				 
								else {
									$errors[] = $error = sprintf('Le rapport "%d" est introuvable.', $variables[$variable]);
									$variables[$variable] = $error;
								}
								break;
							default:
						}
					}
					
					//Format
					if( ! $format )
						$format = '%s';
					elseif( $format_LIKE ){
						$format_LIKE = $format;
						$format = '%s';
					}
					if( $format_JSON ){
						// debug_log(__FUNCTION__ . ' format', $format, $variable );
							
						$format_Inject = true;
						if( $format_args !== '' ){
							$format = '%' . substr( $format, strlen($format_args) + 1 );
						}
						switch( $format ){
						case '%J' :
							
							$value = $variables[$variable];
						
							if( $sql_variables && isset($sql_variables[$variable]) && isset($sql_variables[$variable]['type']) ){
								switch($sql_variables[$variable]['type']){
									case 'report' :
										//Injecte le résultat de la sous-requête sous forme JSON
										// if( isset($sql_variables[$variable]['report_json']) ){
											// $value = $sql_variables[$variable]['report_json'];
										// }
										// else {
											$report_sql = $sql_variables[$variable]['report_sql'];
											// debug_log( __FUNCTION__ . ' sub_report get_sql_dbresults');
											// array_push( $options[__FUNCTION__.':stack'], $report_id );
											$result = static::get_sql_dbresults( $sub_report, $report_sql, $sql_variables, $options );
											// debug_log( __FUNCTION__ . ' sub_report get_sql_dbresults DONE');
											// array_pop( $options[__FUNCTION__.':stack'] );
											if( is_a($result, 'Exception') )
												$result = [ 
													'error' => $result->getMessage(),
													'source' => __FUNCTION__,
													'sub_report' => $sub_report->ID,
													'sub_report_title' => $sub_report->post_title,
												];
											
											$value = json_encode($result, JSON_UNESCAPED_UNICODE);
											// $sql_variables[$variable]['report_json'] = $value;
											
										// }
										break;
								}
							}
							$value = sprintf('CAST( "%s" AS JSON )',
								str_replace("\n", '', addslashes( $value )),
							);
							
							$sql = preg_replace( '/' . preg_quote($src) . '(?!%)/',  $value, $sql );
							continue 2;
						
						case '%JT' :
							$rows = '*';
							$value = $variables[$variable];
							if( $value && is_string( $value ) ){
								$value = json_decode( $value, true );
							}
							if( ! $value )
								$value = '[]';
							else {
								if( is_numeric($format_args) )
									$rows = $format_args;
								// tableau d'objets ou objet ?
								$is_object = false;
								$columns = [];
								foreach( $value as $index => $item ){
									if( $index !== 0 ){
										$is_object = true;
									}
									break;
								}
								if( $is_object ){
									foreach( $value as $key => $item )
										$columns[] = $key;
									$value = sprintf('[%s]', json_encode( $value, JSON_UNESCAPED_UNICODE ) );
								}
								else {
									foreach( $value as $object ){
										foreach( $object as $key => $item )
											$columns[] = $key;
										break;
									}
									$value = json_encode( $value, JSON_UNESCAPED_UNICODE );
									$value = sprintf('[%s]', substr($value, 1, strlen($value) - 2) );
								}
								$str_columns = '';
								foreach( $columns as $column ){
									if( $str_columns )
										$str_columns .= ', ';
									$str_columns .= sprintf('%s VARCHAR(512) PATH "$.%s"', $column, $column);
								}
							}
							
							$var_name = sprintf('@_%s_%s', $variable, Agdp::get_secret_code(6));
							$sql = sprintf("SET %s = CAST(\"%s\" AS JSON);\n%s", 
								$var_name,
								str_replace("\n", '', addslashes( $value )),
								$sql,
							);
							
							$value = sprintf('JSON_TABLE( %s , "$[%s]" COLUMNS( %s ) )'
								, $var_name//addslashes( $value )
								, $rows
								, $str_columns
							);
							
							$sql = preg_replace( '/' . preg_quote($src) . '(?!%)/',  $value, $sql );
							continue 2;
						}
					}
					if( $format_Inject ){
						if( ($value = $variables[$variable]) === null )
							$value = '';
						$sql = preg_replace( '/' . preg_quote($src) . '(?!%)/',  $value, $sql );
						continue;
					}
					
					//Remplacement de la variable par %format
					$sql = preg_replace( '/(?<!\\\\)' . preg_quote($src) . '(?!%)/', $format, $sql );
					
					//Ajoute la valeur à $prepare[]
					if( is_array($variables[$variable]) ){
						if( $format_IN ) {
							foreach($variables[$variable] as $opt){
								$prepare[] = $opt;
							}
						}
						else {
							$value = '';
							foreach($variables[$variable] as $opt){
								$value .= '|' . $opt;
							}
							if( $value )
								$value = $value . '|';
							$prepare[] = $value;
						}
					}
					elseif( $variables[$variable] === null )
						$prepare[] = '';
					elseif( $format_LIKE ){
						if( strpos( $variables[$variable], '_' ) !== false )
							$variables[$variable] = str_replace('_', '\_', $variables[$variable]);
						switch($format_LIKE){ //sic : switch fails when $format_LIKE===true
							case '%KL' :
								$prepare[] = $variables[$variable].'%';
								break;
							case '%KR' :
								$prepare[] = '%'.$variables[$variable];
								break;
							default :
								$prepare[] = '%'.$variables[$variable].'%';
								break;
						}
					}
					else
						$prepare[] = $variables[$variable];
				}
				elseif( $var_domain === '@' ){
				}
			}
			/* foreach variables
			 *******************/
			
			//escape '%' (wpdb could manage but we lost SQL readability)
			$escape_flag = uniqid('__esc__');
			//- sql
			$sql = str_replace( '\%', $escape_flag, $sql );
			//- values
			foreach( $prepare as $i => $value )
				if( is_string($value)
				 && strpos($value, '%') !== false )
					$prepare[$i] = str_replace( '%', $escape_flag, $value );
					
			//wpdb prepare
			if( count($prepare) )
				try {
					$sql = $wpdb->prepare($sql, $prepare);
				}
				catch( Exception $exception ){
					$errors[] = sprintf('Erreur lors de la préparation des variables : %s', $exception->getMessage());
				}
			
			//unescape
			$sql = str_replace( $escape_flag, '%', $sql );
			
			//JSON notations (@json[0].label devient @json->>"$[0].label" )
			$sql = self::replace_sql_json_syntax( $sql, $options );
			if( is_a($sql, 'Exception') ){
				// $errors[] = $sql->getMessage();
				return $sql;
			}
			if( count($errors) ){
				$sql .= sprintf("\n/** %s\n**/", implode("\n", $errors));
				debug_log(__FUNCTION__, 'errors', $sql );
				
			}
		}
		
		//Suppression de ; finaux
		$sql = preg_replace('/([;]\s*)+$/', '', $sql);
		
		return $sql;
	}

	/**
	 * SQL as array
	 * clear SQL comments
	 */
 	private static function get_sql_as_array( $sql ) {
		
		if( ! $sql )
			return [];
		
		//sql multiples
		if( is_array($sql) ){
			$sql = implode(";\n", $sql);
		}
		$sql = self::remove_sql_comments( $sql );
		
		$sqls = preg_split( '/[;]\s*\n/', $sql);
		
		return $sqls;
	}

	/**
	 * Clear comments in SQL
	 */
 	private static function remove_sql_comments( $sql ) {
		
		$pattern = "/\\/\\*(.*?)\\*\\//us"; 
		return preg_replace( $pattern, '', $sql );
	}

	/**
	 * Clear JSON[] notations in SQL, for @variables
	 */
 	private static function replace_sql_json_syntax( $sql, &$options = false ) {
		$used_variables = [];
		
		//@TERMS[`slug`]
		//@TERMS[@term_id]
		//@TERMS.`slug`
		//@TERMS.:column
		//@TERMS[1]['name']
		//@TERMS[1].name
		$matches = [];
		$pattern = '/(\@+[a-zA-Z_][a-zA-Z0-9_]*)(?:(\[[^\]]+\])?((?:(?:\.|\[)(?:\'|`)?([a-zA-Z0-9_:@%][a-zA-Z0-9_]*)(?:\'|`)?[\]]?)+))?/';
		if( preg_match_all( $pattern, $sql, $matches ) ){
			$previous_row = [];
			foreach($matches[1] as $index => $variable){
				
				$src = $matches[0][$index];
				$row = $matches[2][$index];
				$col = $matches[3][$index];
				
				$used_variables[ $variable ] = $src;
				
				if( $row === '' && $col === '' )
					continue;
				
				if( $col[0] === '.' ){
					$col = substr($col, 1);
					$separator = '.';
				}
				elseif( $col[0] === '[' && $col[strlen($col)-1] === ']'){
					$col = str_replace( '][', '.', $col); //many
					$col = substr($col, 1, strlen($col) - 2);
					$separator = '.';
				}
				else
					$separator = '.';
				
				if( $col[0] === '\'' && $col[strlen($col)-1] === '\''){
					$col = substr($col, 1, strlen($col) - 2);
				}
				if( ! $row ){
					if( is_numeric($col) ){
						$row = '[' . $col . ']';
						$col = '';//'[*]';
						$separator = '';
					}
					elseif( ! empty ($previous_row[$variable]) )
						$row = $previous_row[$variable];
					else
						$row = '[0]';
					// debug_log( __FUNCTION__ . ' ! $row', $row, $col);
				}
				$concat_col = false;
				$concat_row = false;
				if( preg_match( '/\[\'[0-9\.]+\'\]/', $row ) )
					$row = str_replace('^\'', '', $row );
				
				elseif( preg_match( '/^\[\s*[`@]/', $row ) ){
					$concat_row = true;
				}
				if( preg_match( '/^[`@]/', $col ) ){
					$concat_col = true;
				}
				
				//Le champ $col ne doit pas commencer par un chiffre ou autre caractère spécial. L'échappement se fait par un encadrement entre ".
				
				if( $concat_col || $concat_row ){
					if( $concat_col && ! $concat_row ){
						$path = sprintf('CONCAT("$%s%s\"", %s, "\"")'
							, $row
							, $separator
							, $col);
					}
					elseif( ! $concat_col && $concat_row ){
						$path = sprintf('CONCAT("$[", %s, "]%s%s")'
							, substr( $row, 1, strlen($row) - 2 )
							, $separator
							, $col);
					}
					else {
						$path = sprintf('CONCAT("$[", %s, "]%s\"", %s, "\"")'
							, substr( $row, 1, strlen($row) - 2 )
							, $separator
							, $col);
					}
				
					$extract = str_replace('$', '\$', 
						sprintf('JSON_UNQUOTE(JSON_EXTRACT(%s, %s))'
							, $variable
							, $path
						)
					);
				}
				else{
					$path = sprintf('"$%s%s%s"'
						, $row
						, $separator
						, $col);
				
					$extract = str_replace('$', '\$', 
						sprintf('JSON_VALUE(%s, %s)'
							, $variable
							, $path
						)
					);
				}
					// debug_log( __FUNCTION__ . ' sql', $sql, $src);
					// debug_log( __FUNCTION__ . ' extract', $extract);
				$sql = preg_replace( '/' . preg_quote($src) . '(?!%)/', $extract, $sql );
					// debug_log( __FUNCTION__ . ' sql', $sql);
				$previous_row[$variable] = $row;
			}
		}
		
		// SELECT to JSON
		// SET @SLUGS = SELECT slug, name FROM :termes t;
		if( strpos($sql, 'SET ') !== false && strpos($sql, ' SELECT ') ){
			$matches = [];
			$pattern = '/(?:^SET\s+)(\@+[a-zA-Z_][a-zA-Z0-9_]*)\s?\=\s?SELECT\s+(`?[a-zA-Z_][a-zA-Z0-9_]*`?)(?:\s*,\s*)(`?[a-zA-Z_][a-zA-Z0-9_]*`?)/i';
			if( preg_match( $pattern, $sql, $matches ) ){
				$variable = $matches[1];
				$src = $matches[0];
				$key_field = $matches[2];
				$value_field = $matches[3];
				$sub_sql = trim(substr( $sql, strpos($sql, '=') + 1 ), ' ;');
				$table_name = sprintf('_t%s', Agdp::get_secret_code(6));
				$sql = sprintf('SELECT JSON_OBJECTAGG(%s, %s) INTO %s FROM (%s) %s;'
						// , $variable
						, $key_field
						, $value_field
						, $variable
						, $sub_sql
						, $table_name
				);
				
				$used_variables[ $variable ] = $matches;
			}
			else {
				$sql = new Exception( sprintf('Erreur dans le format <code>SET @var = SELECT `key`, `value` FROM table t;</code><br> %s',
					$sql));
			}
		}
		
		// Dynamic global vars
		// debug_log( __FUNCTION__ .' used_variables', $sql, $used_variables );
		if( $used_variables ){
			$sql = self::check_dynamic_vars_needed( $sql, $used_variables, $options );
		}
		return $sql;
	}
	
	/**
	 * replace_sql_tables_prefix
	 */
 	public static function replace_sql_tables_prefix( $sql, $check_global_tables = true ) {
		//@.
		//TODO preg_replace
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		if( $check_global_tables
		 && $wpdb->blogid > 1 ){
			$site_prefix = $wpdb->get_blog_prefix( 1 );
			$tables =  array_merge( $wpdb->global_tables, $wpdb->ms_global_tables );
			foreach( $tables as $table )
				$sql = str_replace( AGDP_BLOG_PREFIX . $table, $site_prefix . $table, $sql);
		}
		//$wpdb::$tables
		$sql = str_replace( AGDP_BLOG_PREFIX, $blog_prefix, $sql);
		
		return $sql;
	}

	/**
	 * add_table_blog_prefix
	 */
 	private static function add_table_blog_prefix( $table ){
		$wpdb = self::wpdb();
		if( $wpdb->blogid	> 1 ){
			if( in_array( $table, $wpdb->global_tables ) 
			 || in_array( $table, $wpdb->ms_global_tables )
			){
				$site_prefix = $wpdb->get_blog_prefix( 1 );
				if( $site_prefix && substr( $table, 0, strlen($site_prefix ) ) !== $site_prefix )
					return $site_prefix . $table;
				else
					return $table;
			 }
		}
		$blog_prefix = $wpdb->get_blog_prefix();
		if( $blog_prefix && substr( $table, 0, strlen($blog_prefix ) ) !== $blog_prefix ){
			//TODO tables user et usermeta doivent être préfixé de @site_prefix (::get_blog_prefix( 1 ))
			return $blog_prefix . $table;
		}
		return $table;
	}
	/**
	 * returns sql_global_vars initiated
	 */
 	public static function sql_global_vars( $options = false ) {
		if( self::$sql_global_vars_init )
			return static::$sql_global_vars;
		
		global $post;
		if( $post && $post->post_type === self::post_type )
			$report_id = $post->ID;
		elseif( ! empty( $_REQUEST['post_id'] ) ){
			$post_id = $_REQUEST['post_id'];
			$post = get_post( $post_id );
			if( $post && $post->post_type === self::post_type )
				$report_id = $post->ID;
		}
		self::$sql_global_vars_init = true;
		static::$sql_global_vars = array_merge( static::$sql_global_vars, [
			AGDP_VAR_BLOG_ID /* @BLOGID */ => get_current_blog_id(),
			AGDP_VAR_BLOG_NAME /* @BLOGNAME */ => get_bloginfo('name'),
			AGDP_VAR_BLOG_URL /* @BLOGURL */ => get_bloginfo('url'),
			AGDP_VAR_POST_ID => $post ? $post->ID : false,
			AGDP_VAR_POST_TYPE => $post ? $post->post_type : false,
			AGDP_VAR_REPORT_ID => isset($report_id) ? $report_id : false,
		]);
				
		return static::$sql_global_vars;
	}


	/**
	 * add_sql_global_vars
	 */
 	public static function add_sql_global_vars( $sql, $sql_variables = false, &$options = false ) {
		if( ! $sql
		|| ! empty($options['_add_sql_global_vars']) )
			return $sql;
		
		$options['_add_sql_global_vars'] = true;
		
		$vars = static::sql_global_vars( $options );
		
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
	 * check_dynamic_vars_needed
	 */
 	public static function check_dynamic_vars_needed( $sql, $variables = false, &$options = false ) {
		if( ! $sql || ! $variables )
			return $sql;
		
		// debug_log( __FUNCTION__, $sql, $variables );
		$var_sqls = [];
		foreach( $variables as $variable => $matches ){
			if( ! empty( $options['_dynamic_vars'] )
			 && ! empty( $options['_dynamic_vars'][$variable] ) )
				continue;
				
			//$prefixable
			$var_ext = '';
			foreach( [ 
				AGDP_VAR_TAX_TERMS,
			] as $prefixable ){
				if( $prefixable === substr( $variable, 0, strlen($prefixable) ) ){
					if( strlen($variable) > strlen($prefixable)
					&& $variable[strlen($prefixable)] === '_' ){
						$var_ext = substr( $variable, strlen($prefixable) + 1 );
						$variable = $prefixable;
						break;
					}
				}
			}
			switch( $variable ){
				case AGDP_VAR_TAX_TERMS :
					if( $var_ext ){
						if( isset($options['_dynamic_vars'][ $variable ]) ){
							// debug_log( __FUNCTION__.' _dynamic_vars', $options['_dynamic_vars'], $variable );
							$var_sqls[] = self::replace_sql_tables_prefix( 
								sprintf('SET %s_%s = JSON_UNQUOTE(JSON_EXTRACT(%s, \'$.%s\'))'
									, $variable
									, $var_ext
									, $variable
									, $var_ext
								),
								false
							);
						}
						else
							$var_sqls[] = self::replace_sql_tables_prefix( 
								sprintf('SELECT JSON_OBJECTAGG(slug, name) INTO %s_%s'
									. ' FROM @.term_taxonomy term_tax'
									. ' INNER JOIN @.terms term'
									. ' ON term.term_id = term_tax.term_id'
									. ' WHERE term_tax.taxonomy = \'%s\''
									, $variable
									, $var_ext
									, $var_ext
								),
								false
							);
					}
					else {
						$var_sqls[] = self::replace_sql_tables_prefix( 
							sprintf('SELECT JSON_OBJECTAGG(taxonomy, terms) INTO %s'
								. ' FROM ('
									. ' SELECT term_tax.taxonomy, JSON_OBJECTAGG(slug, name) AS terms'
									. ' FROM @.term_taxonomy term_tax'
									. ' INNER JOIN @.terms term'
									. ' ON term.term_id = term_tax.term_id'
									. ' GROUP BY term_tax.taxonomy'
								. ' ) tt'
								, $variable
							),
							false
						);
					}
					break;
					
				default:
					/* TODO add global vars on need only 
					if( isset( self::$sql_global_vars[ $variable ] ){
					}
					else */
						continue 2;
			}
			if( empty( $options['_dynamic_vars'] ) )
				$options['_dynamic_vars'] = [];
			$options['_dynamic_vars'][ $variable . ( $var_ext ? '_' . $var_ext : '' ) ] = true;
		}
		
		if( $var_sqls )
			$sql = sprintf("%s;\n%s", implode(";\n", $var_sqls), $sql );
		
				
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
		if( $set_vars ){
			$set_vars = sprintf('SET %s', $set_vars);
		}

		return $set_vars;
	}
	
	/**
	 * get_render_sql
	 * Wrap sql in a SELECT scripts FROM ( sql )
	 */
 	public static function get_render_sql( $report, $sql, $sql_variables, &$options, $table_columns ) {
		if( ! $table_columns )
			return $sql;
		$select = '';
		foreach( $table_columns as $column => $column_data ){
			if( empty($column_data['script']) )
				$column_sql = sprintf('`%s`', $column );
			else
				$column_sql = $column_data['script'];
			if( $select )
				$select .= ', ';
			$select .= sprintf('%s AS `%s`', $column_sql, $column);
		}
		if( $select ){
			$select = sprintf('SELECT %s', $select);
			$select = static::get_sql($report, $select, $sql_variables, $options);
			$select .= sprintf(' FROM (%s) _render', $sql);
			debug_log( __FUNCTION__, $select);
		}
		else
			$select = $sql;
		return $select;
	}

	/**
	 * sanitize_sub_report_sql
	 */
 	public static function sanitize_sub_report_sql( $sql = false ) {
		if( is_array($sql) ){
			// foreach( $sql as $index => $sql_u )
				// $sql[$index] = self::sanitize_sub_report_sql( $sql_u );
			// return $sql;
			$sql = $sql[ count($sql) - 1 ];
		}
		//Sub query does not support LIMIT clause
		$sql = preg_replace('/\sLIMIT\s.*(\n|$)/i', '', $sql);
		//TODO or not TODO
		//$sql = preg_replace('/\sORDER BY\s.*(\n|$)/i', '', $sql);
		
		$sql = str_replace( "\n", "\n\t", $sql );
		return "( $sql )";
	}

	/**
	 * SQL results
	 */
 	public static function get_sql_dbresults( $report = false, $sql = false, $sql_variables = false, &$options = false, $table_columns = false ) {
		
		if( ! is_array($options) )
			$options = [];
		
		$_skip_sql_prepare_prev = ! empty($options['_skip_sql_prepare']) && $options['_skip_sql_prepare'];
		$options['_skip_sql_prepare'] = true;
		$sql = self::get_sql( $report, $sql, $sql_variables, $options );
		$options['_skip_sql_prepare'] = $_skip_sql_prepare_prev;
		
		if( ! $sql )
			return;
			
		$wpdb = self::wpdb();
		
		//global vars : @BLOGID, ...
		$sql = static::add_sql_global_vars( $sql, $sql_variables, $options );
		
		if( empty($options['_sqls'] ) )
			$options['_sqls'] = [];
		
		if( is_array($sql) ){
			
			if( count($sql) > 1 )
				$sql = array_filter( $sql, function( $value ){ return trim($value, " ;\n\r") !== ''; } );
			
			foreach( $sql as $index => $sql_u ){
				
				$is_last_sql = ( count($sql) === $index + 1 )
					&& ( preg_match( '/^(?:\(|\s)*SET\s\@/i', $sql_u ) === 0 );
					
				$sqls_u = self::get_sql( $report, $sql_u, $sql_variables, $options );
				$sqls_u = self::get_sql_as_array( $sqls_u );
				foreach( $sqls_u as $sql_uu_index => $sql_uu ){
					//get_render_sql for ultimate
					if( $is_last_sql
					&& $table_columns 
					&& ( count($sqls_u) === $sql_uu_index + 1 ))
						$sql_uu = self::get_render_sql( $report, $sql_uu, $sql_variables, $options, $table_columns );
						
					//$wpdb->get_results
					$result_u = $wpdb->get_results($sql_uu);
					// debug_log( __FUNCTION__, $sql_uu);
					array_push( $options['_sqls'], $sql_uu );
					if( $wpdb->last_error ){
						// debug_log( __FUNCTION__ . ' $result_u ', $result_u, $wpdb->last_error, $wpdb->last_query);
						$wpdb->last_error .= sprintf('(%d)<br>%s', $index, substr($wpdb->last_query, 0, 100));
						$result = $result_u;
						break 2;
					}
				}
				//Si c'est la dernière requête, SET @PREVIOUS = CAST( "%s" AS JSON )
				if( ! $wpdb->last_error
					&& $is_last_sql
				)
					self::set_previous_results_variable( $result_u );
								
				if( $result_u )
					$result = $result_u;
			}
		}
		else{
			if( $table_columns )
				$sql = self::get_render_sql( $report, $sql, $sql_variables, $options, $table_columns );
			$result = $wpdb->get_results($sql);
			array_push( $options['_sqls'], $sql );
		}
		if($wpdb->last_error){
			if( ! is_a($result, 'WP_Error') )
				$result = new Exception( $wpdb->last_error );
		}
		
		if( ! isset($result) )
			return false;
		return $result;
	}

	/**
	 * set_previous_results_variable
	 * SET @PREVIOUS = CAST( $results AS JSON )
	 */
 	private static function set_previous_results_variable( $results ) {
		$wpdb = self::wpdb();
		$max_results_in_previous = 99;
		if( count( $results ) > $max_results_in_previous )
			$json = json_encode( array_slice( $results, 0, $max_results_in_previous ), JSON_UNESCAPED_UNICODE );
		else
			$json = json_encode( $results, JSON_UNESCAPED_UNICODE );
		$sql_json = sprintf('SET @PREVIOUS = CAST( "%s" AS JSON )', addslashes($json) );
		$result_json = $wpdb->get_results($sql_json);
		if( $wpdb->last_error ){
			$json = json_encode( $result_u, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			if( strlen($json) > 255 )
				$json = substr( $json, 0, 255 ) . '...';
			$sql_json = sprintf('SET @PREVIOUS = CAST( "%s" AS JSON )', $json );
			$wpdb->last_error .= sprintf('SET @PREVIOUS = CAST AS JSON of %s', $sql_json);
		}
		return $result_json;
	}

	/**
	 * SQL results as html table
	 */
 	public static function get_report_html( $report = false, $sql = false, $sql_variables = false, $options = false ) {
		if( ! $report ){
			global $post;
			if( ! $post || $post->post_type !== self::post_type)
				return false;
			$report = $post;
		}
		elseif( is_numeric($report) ){
			$report = get_post($report);
			if( ! $report || $report->post_type !== self::post_type)
				return false;
		}
		
		$report_id = $report->ID;
		
		if( ! is_array($options) )
			$options = [];
		
		$meta_key = 'table_columns';
		if( isset($options[$meta_key]) )
			$table_columns = $options[$meta_key];
		else
			$table_columns = get_post_meta( $report_id, $meta_key, true );
		
		if( $table_columns && is_string($table_columns) ){
			$table_columns = json_decode($table_columns, true);
		}
		
		$wpdb = self::wpdb( TRUE ); //reset des variables
		$wpdb->suppress_errors(true);
		$wpdb->last_error = false;
		$dbresults = static::get_sql_dbresults( $report, $sql, $sql_variables, $options, $table_columns );
		$wpdb->suppress_errors(false);
		
		$sql_prepared ='';
		//report_show_sql
		if( Agdp::is_admin_referer() && current_user_can('manage_options')){
			$meta_key = 'report_show_sql';
			if( isset($options[$meta_key]) )
				$report_show_sql = $options[$meta_key];
			else
				$report_show_sql = get_post_meta( $report_id, $meta_key, true );
			if( $report_show_sql ){
				$sql_prepared = $options['_sqls'];
				if( $report_show_sql === 'vars' ){
					// $sql_prepared = static::add_sql_global_vars( $sql_prepared, $sql_variables, $options );
				}
				else {
					if( is_array($sql_prepared) )
						array_shift($sql_prepared);
					// $sql_prepared = preg_replace('/^'.preg_quote('/**sql_global_vars**/').'\n/', '', $sql_prepared );
					// if( $pos = strpos( $sql_prepared, AGDP_GLOBAL_VARS_FLAG ) )
						// $sql_prepared = substr($sql_prepared, $pos + strlen(AGDP_GLOBAL_VARS_FLAG) + 2);
				}
				if( is_array($sql_prepared) )
					$sql_prepared = implode( ";\n", $sql_prepared );
				$sql_prepared = sprintf('<div class="sql_prepared">%s</pre>', htmlspecialchars($sql_prepared));
			}
		}
		
		if( ! $dbresults )
			return sprintf('<div class="agdpreport" agdp_report="%d">(aucun résultat)%s</div>', $report_id, $sql_prepared);
		
		if( is_a($dbresults, 'Exception') ){
			return sprintf('<div class="agdpreport error" agdp_report="%d"><pre>%s</pre><pre>%s</pre></div>'
				, $report_id, $dbresults->getMessage()
				, $sql_prepared
			);
		}
		if( ! is_array($dbresults) )
			return sprintf('<div class="agdpreport error" agdp_report="%d"><pre>%s</pre><pre>%s</pre></div>'
				, $report_id, $dbresults, $sql_prepared);		
		
		$tag_id =sprintf( 'report_%s', Agdp::get_secret_code( 6 ) );
		
		$content = sprintf('<div id="%s" class="agdpreport" agdp_report="%d"><table>',
				$tag_id,
				$report_id
		);
		
		$meta_key = 'report_show_indexes';
		if( isset($options[$meta_key]) )
			$report_show_indexes = $options[$meta_key];
		else
			$report_show_indexes = get_post_meta( $report_id, $meta_key, true );
		
		$meta_key = 'report_show_caption';
		if( isset($options[$meta_key]) )
			$report_show_caption = $options[$meta_key];
		else
			$report_show_caption = get_post_meta( $report_id, $meta_key, true );
		if( $report_show_caption ){
			$table_caption = $report->post_title;
			if( $table_caption ){
				$content .= sprintf('<caption>%s</caption>', $table_caption );
			}
		}
		$content .= '<thead><tr class="report_fields">';
		foreach($dbresults as $row){
			if( $report_show_indexes )
				$content .= sprintf('<th>#</th>');
			foreach($row as $column_name => $column_value){
				$column_label = $column_name;
				$column_visible = true;
				$class = '';
				if( $table_columns && isset($table_columns[ $column_name ] ) ){
					if( is_array($table_columns[ $column_name ]) ){
						$column_label = $table_columns[ $column_name ][ 'label' ];
						$column_visible = ! isset($table_columns[ $column_name ][ 'visible' ]) || $table_columns[ $column_name ][ 'visible' ];
					}
					else {
						$column_label = $table_columns[ $column_name ];
					}
				}
				if( ! $column_visible )
					$class .= ' hidden';
				$content .= sprintf('<th %s column="%s">%s</th>'
					, $class ? 'class="' . trim($class) . '"' : ''
					, $column_name
					, $column_label
				);
			}
			break;
		}
		$content .= '</tr></thead>';
		$content .= '<tbody>';
		
		$escape_function = Agdp::is_admin_referer() ? 'htmlspecialchars' : 'nl2br';
		foreach($dbresults as $row_index => $row){
			$content .= '<tr>';
			if( $report_show_indexes )
				$content .= sprintf('<th>%d</th>', $row_index+1);
			foreach($row as $column_name => $field_value){
				$column_visible = true;
				$class = '';
				if( $table_columns
				&& isset($table_columns[ $column_name ] ) 
				&& is_array($table_columns[ $column_name ]) ){
					$column_visible = ! isset($table_columns[ $column_name ][ 'visible' ]) || $table_columns[ $column_name ][ 'visible' ];
				}
				if( ! $column_visible )
					$class .= ' hidden';
				$content .= sprintf('<td %s>%s</td>'
					, $class ? 'class="' . trim($class) . '"' : ''
					, $escape_function( $field_value )
				);
			}
			$content .= '</tr>';
			
		}
	    $content .= '</tbody>';
		
		//TODO tfoot
	    // $content .= '<tfoot><tr>';
		// foreach($dbresults as $row){
			// $content .= sprintf('<td>%s ligne%s</td>', count($dbresults), count($dbresults)>1 ? 's' : '');
			// foreach($row as $field_name => $field_value){
				// $field_label = $field_name;//TODO
				// $content .= sprintf('<td field="%s"></td>', $field_name/* , $field_label */);
			// }
			// break;
		// }
	    // $content .= '</tr></tfoot>';
	    
		$content .= '</table>';
		
		if( $sql_prepared ) 
			$content .= $sql_prepared;
		
		if( empty($options['skip_styles']) ) {
			$content .= sprintf( "\n<style>%s</style>\n", self::get_report_css( $report_id, $tag_id ) );
		}
		
		$content .= '</div>';
	    
		return $content;
	}

	/**
	 * Retourne les styles possibles d'un rapport
	 */
	public static function get_report_css( $report_id, $tag_id ) {
		$css = get_post_meta( $report_id, 'report_css', true );
		foreach( self::get_report_styles( $report_id, 'all' ) as $term ){
			if( $term->description )
				$css .= "\n" . $term->description;
		}
		if( $css && $tag_id ){
			$css = preg_replace( '/(\s|,\s?)(table(\s|.))/', '$1#' . $tag_id . ' > $2', $css );
		}
		
		$css = str_replace( '&gt;', '>', $css);
	    
		return $css;
	}
	
	/**
	 * Hook the_content
	 */
 	public static function the_content( $content ) {
 		global $post;
		$content = $post->post_content;
		
		if( ! $post	){
 			return $content;
		}
		
		$wpdb = self::wpdb();
		
		$html = self::get_report_html( $post );
		if( ! $html )
			return $content;
		
		if( is_a($html, 'WP_Error') )
			throw $html;
		
		return $html;
	}
	
	
	
	/**
	 * Returns posts where post_status == $published_only ? 'publish' : * && meta['cron-enable'] == $cron_enable_only
	 */
	 public static function get_reports( $published_only = true){
		$posts = [];
		$query = [ 'post_type' => self::post_type, 'numberposts' => -1 ];
		if( $published_only )
			$query[ 'post_status' ] = 'publish';
		else
			$query[ 'post_status' ] = ['publish', 'pending', 'draft'];
		
		foreach( get_posts($query) as $post)
			$posts[$post->ID . ''] = $post;
		return $posts;
	}
	
	/**
	 * Retourne l'objet report.
	 */
	public static function get_report($report = false){
		$report = get_post($report);
		if(is_a($report, 'WP_Post')
		&& $report->post_type == self::post_type)
			return $report;
		return false;
	}
	
	/**
	 * Requête Ajax sur les commentaires
	 */
	public static function on_ajax_action() {
		if( ! Agdp::check_nonce() )
			wp_die('nonce error');
		if( empty($_POST['method']))
			wp_die('method missing');
		
		$ajax_response = '';
		
		$method = $_POST['method'];
		$data = isset($_POST['data']) ? $_POST['data'] : [];
		
		if( $data && is_string($data) && ! empty($_POST['contentType']) && strpos( $_POST['contentType'], 'json' ) )
			$data = json_decode(stripslashes( $data), true);
		
		try {
			//cherche une fonction du nom "on_ajax_action_{method}"
			$function = array(get_called_class(), sprintf('on_ajax_action_%s', $method));
			$ajax_response = call_user_func( $function, $data);
		}
		catch( Exception $e ){
			$ajax_response = sprintf('Erreur dans l\'exécution de la fonction :%s', var_export($e, true));
		}
		
		// Make your array as json
		wp_send_json($ajax_response);
	 
		// Don't forget to stop execution afterward.
		wp_die();
	}

	/**
	 * Requête Ajax de changement d'état du commentaire
	 */
	public static function on_ajax_action_report_html($data) {
		if( isset($data['report_id']) )
			$report_id = $data['report_id'];
		elseif( is_admin() && isset($_POST['post_id']) )
			$report_id = $_POST['post_id'];
		else
			$report_id = false;
		if( isset($data['sql_variables']) ){
			$sql_variables = $data['sql_variables'];
			if( $sql_variables === 'false' )
				$sql_variables = false;
		}
		else
			$sql_variables = false;
		if( isset($data['sql']) ){
			$sql = $data['sql'];
			if( $sql === 'false' )
				$sql = false;
		}
		else
			$sql = false;
		
		return self::get_report_html($report_id, $sql, $sql_variables, $data);
	}
	
	/**
	 * Retourne l'analyse du forum
	 */
	public static function get_diagram( $blog_diagram, $report ){
		$diagram = [ 
			'report' => $report, 
		];
		
		if( is_a($report, 'WP_Post') ){
			$report_id = $report->ID;
			//post_status
			$diagram['post_status'] = $report->post_status;
			
			//imap_email
			// $meta_key = 'imap_suspend';
			// $diagram[$meta_key] = get_post_meta($report_id, $meta_key, true);
		}
		
		return $diagram;
	}
	/**
	 * Rendu Html d'un diagram
	 */
	public static function get_diagram_html( $report, $diagram = false, $blog_diagram = false ){
		if( ! $diagram ){
			if( ! $blog_diagram )
				throw new Exception('$blog_diagram doit être renseigné si $diagram ne l\'est pas.');
			$diagram = self::get_diagram( $blog_diagram, $report );
		}
		$admin_edit = is_admin() ? sprintf(' <a href="/wp-admin/post.php?post=%d&action=edit">%s</a>'
				, is_a($report, 'WP_Post') ? $report->ID : $report
				, Agdp::icon('edit show-mouse-over')
			) : '';
			
		$html = '';
		$icon = 'media-spreadsheet';
		
		if( is_a($report, 'WP_Post') ){
			$html .= sprintf('<div>Rapport <a href="%s">%s</a>%s</div>'
				, get_permalink($report)
				, $report->post_title
				, $admin_edit
			);
			// $meta_key = 'imap_mark_as_read';
			// if( empty($diagram[$meta_key]) ){
				// $icon = 'warning';
				// $html .= sprintf('<div>%s L\'option "Marquer les messages comme étant lus" n\'est pas cochée. Les e-mails seront lus indéfiniment.</div>'
						// , Agdp::icon($icon)
				// );
			// }
		}
		
		return $html;
	}
}
?>