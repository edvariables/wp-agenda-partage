<?php

/**
 * AgendaPartage -> Report
 * Custom post type for WordPress.
 * 
 * Définition du Post Type agdpreport
 *
 * Voir aussi Agdp_Admin_Edit_Report
 *
 */
class Agdp_Report extends Agdp_Post {

	const post_type = 'agdpreport';
	const taxonomy_report_style = 'report_style';
	const taxonomy_sql_function = 'sql_function';
	const shortcode = 'report';
		
	// const user_role = 'author';
	
	private static $initiated = false;
	
	public static $wpdb = false;

	public static function init() {
		if ( ! self::$initiated ) {
			parent::init();
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
		
		define( 'MAX_VAR_DBRESULTS_ROWS', 99); 
	}

	/**
	 * Hook
	 */
	public static function init_hooks() {
		parent::init_hooks();
		
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
		self::$wpdb = new Agdp_wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		
		global $wpdb;
		self::$wpdb->set_blog_id( $wpdb->blogid, $wpdb->siteid );
		$global_wpdb = $wpdb;
			$wpdb = self::$wpdb;
			wp_set_wpdb_vars();
		$wpdb = $global_wpdb;
		
		self::$wpdb->suppress_errors( true );
		return self::$wpdb;
	}

	/**
	 * SQL
	 */
 	public static function get_sql( $report = false, $sql = false, $sql_variables = false, &$options = false ) {
		if( is_numeric($report) )
			$report = get_post($report);
		$report_id = $report->ID;
		
		if( ! is_array($options) )
			$options = [];
		
		//sql
		if( ! $sql )
			$sql = get_post_meta( $report_id, 'sql', true );
		
		$sqls = self::get_sql_as_array( $sql );
		
		// debug_log( __FUNCTION__, 'sqls ',$sqls);
		
		if( count($sqls) !== 1 ){
			$sqls_ne = [];
			foreach($sqls as $sql_u ){
				if( $sql_u )
					$sql_u = self::get_sql( $report, $sql_u, $sql_variables, $options );
				if( strcasecmp( $sql_u, 'STOP' ) === 0 ){
					$options['_no_table_columns'] = true;
					break;
				}
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
		
		$sql = self::get_sql_prepare( $report, $sql, $sql_variables, $options );
		
		return $sql;
	}
	
	/**
	 * Prepare SQL
	 */
 	private static function get_sql_prepare( $report, $sql, $sql_variables, &$options ) {
		$report_id = $report->ID;
		
		// debug_log( __FUNCTION__ 
			// . ( empty($options[__FUNCTION__.':stack']) ? '' : '  >> ' . count($options[__FUNCTION__.':stack']) )
			// , $sql/* , $sql_variables */);
		
		$wpdb = self::wpdb();
		//blog_prefix : @.
		$blog_prefix = $wpdb->get_blog_prefix();
	    $sql = static::replace_sql_tables_prefix( $sql );
		
		$sql_variables = Agdp_Report_Variables::normalize_sql_variables( $report, $sql_variables, $options );
		
		//comments
		$sql = self::remove_sql_comments( $sql );
				
		//strings ""
		//BUGG !!! si CONCAT('<a class="dbquote" href="', `url`, '">')
		$matches = [];
		$pattern = '/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/s';
		$strings_prefix = uniqid('__sqlstr_');
		$sql_strings = [];
		while( preg_match( $pattern, $sql, $matches ) ){
			$string = $matches[1];
			$string = str_replace('\"', '"', $string);
			$variable = sprintf('%s_%d_', $strings_prefix, count($sql_strings));//suffixe important
			$sql_strings[$variable] = $string;
			$sql = str_replace( $matches[0], ':' . $variable, $sql );
		}
		
		//Variables
		$matches = [];
		$allowed_format = '(?:[1-9][0-9]*[$])?[-+0-9]*(?: |0|\'.)?[-+0-9]*(?:\.[0-9]+)?'; //cf /wp-includes/class-wpdb.php
		$pattern = "/((?<!\\\\)\:|\@)([a-zA-Z_][a-zA-Z0-9_]*)(%($allowed_format)?[sdfFiIKJ][NLRTK]?(V)?)?/";
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
					//------------------------
					
					$format_IN = $format === '%IN';
					$format_Inject = $format === '%I';
					$format_mode = substr( $format, strlen($format_args) + 1, 1 );
					$format_LIKE = $format_mode  === 'K';
					$format_JSON = $format_mode  === 'J';
					
					$variable_type = $sql_variables && isset($sql_variables[$variable]) && isset($sql_variables[$variable]['type']) ? $sql_variables[$variable]['type'] : null;
					switch($variable_type){
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
							
						case 'report_sql': 
							$error = '';
							if( is_numeric($variables[$variable])
							 && ( $sub_report = get_post($variables[$variable]) )){
								if( ! isset($options[__FUNCTION__.':stack']) )
									$options[__FUNCTION__.':stack'] = [];
								elseif( $sub_report->ID == $report_id
								|| in_array( $report_id, $options[__FUNCTION__.':stack'] ) ){
									$errors[] = $error = sprintf('Le rapport "%d" provoque un appel récursif infini.', $variables[$variable]);
									debug_log_callstack(__FUNCTION__, $error);
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
									case 'report_sql' :
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
						
						case '%JKV' ://{key: value, key: value, key: value, ...}
						case '%JT' : //[{col1: value, col2: value, col3: value}, ...]
							$rows = '*';
							$value = $variables[$variable];
							if( $value && is_string( $value ) ){
								$value = json_decode( $value, true );
							}
							if( ! $value ){
								$value = '[]';
								$str_columns = '';
							}
							else {
								if( is_numeric($format_args) )
									$rows = $format_args;
								// tableau d'objets ou objet ?
								$is_object = false;
								$columns = [];
								foreach( $value as $index => $item ){
									if( $index !== 0 ){//tableau brut
										$is_object = true;
									}
									break;
								}
								if( $is_object ){
									if( '%JKV' === $format ){
										$columns[] = 'key';
										$columns[] = 'value';
										$keys_values = [];
										foreach( $value as $k => $v ){
											$keys_values[] = [ 'key' => $k, 'value' => $v ];
										}
										$value = json_encode( $keys_values, JSON_UNESCAPED_UNICODE );
									}
									else {
										foreach( $value as $key => $item )
											$columns[] = $key;
										$value = sprintf('[%s]', json_encode( $value, JSON_UNESCAPED_UNICODE ) );
									}
								}
								else {
									foreach( $value as $object ){
										if( ! is_array($object) )
											continue;
										foreach( $object as $key => $item )
											$columns[] = $key;
										break;
									}
									//Tableau de données brutes, sans clé de colonne
									if( count($columns) === 0 && count($value) > 0 ){
										if( '%JKV' === $format ){
											$columns[] = 'key';
											$columns[] = 'value';
											$keys_values = [];
											foreach( $value as $index => $item ){
												$keys_values[] = [ 'key' => $index . '', 'value' => $item ];
											}
											$value = $keys_values;
										}
										else {
											$columns[] = 'item';
											$values = [];
											foreach( $value as $index => $item ){
												$values[] = [ $columns[0] => $item ];
											}
											$value = $values;
										}
									}
									$value = json_encode( $value, JSON_UNESCAPED_UNICODE );
									$value = sprintf('[%s]', substr($value, 1, strlen($value) - 2) );
								}
								$str_columns = '';
								foreach( $columns as $column ){
									if( $str_columns )
										$str_columns .= ', ';
									$str_columns .= sprintf('`%s` TEXT PATH "$.%s"', $column, $column);
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
					//----------
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
					else {
						if( is_string($variables[$variable]) ){
							// $variables[$variable] = str_replace("\n", '',  str_replace("\r", '', $variables[$variable]));
							if( strpos($variables[$variable], '"') ){
								//debug_log(__FUNCTION__, 'Contient double-quote', $variables[$variable] );
								//TODO est-ce bien raisonnable ? NON en json
								// $variables[$variable] = str_replace('"', '&quot;', $variables[$variable]);
						}}
						
						$prepare[] = $variables[$variable];
					}
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
			foreach( $prepare as $i => $value ){
				if( is_string($value) ){
					if( strpos($value, '%') !== false ){
						$prepare[$i] = str_replace( '%', $escape_flag, $value );
					}
				}
			}
			//wpdb prepare
			if( count($prepare) ){
				$dbquote_escape = false;
				// if( strpos($sql, '"') !== false ){
					// if( ! $dbquote_escape )
						// $dbquote_escape = uniqid('__dbquote__');
					// $sql = str_replace( '"', $dbquote_escape, $sql );
				// }
				try {
					$sql = $wpdb->prepare($sql, $prepare);
					if( $dbquote_escape )
						$sql = str_replace( $dbquote_escape, '"', $sql );
				}
				catch( Exception $exception ){
					$msg = $exception->getMessage();
					if( $dbquote_escape )
						$msg = str_replace( $dbquote_escape, '"', $msg );
					$errors[] = sprintf('Erreur lors de la préparation des variables : %s', $msg);
				}
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
		
		//Suppression de ; finaux ainsi que des lignes de commentaires en fin
		$sql = preg_replace('/(([;]\s*)+|(-- [^\n]*\n?))$/', '', $sql);
		
		return $sql;
	}

	/**
	 * SQL as array
	 * clear SQL comments
	 */
 	public static function get_sql_as_array( $sql ) {
		
		if( ! $sql )
			return [];
		
		//sql multiples
		if( is_array($sql) ){
			$sql = implode(";\n", $sql);
		}
		$sql = self::remove_sql_comments( $sql, true );
		
		$sqls = preg_split( '/[;]\s*\n/', $sql, -1, PREG_SPLIT_NO_EMPTY );
		
		return $sqls;
	}

	/**
	 * Clear comments in SQL
	 */
 	private static function remove_sql_comments( $sql, $remove_single_line_comments = false ) {
		
		$pattern = "/\\/\\*(.*?)\\*\\//us"; 
		$sql = preg_replace( $pattern, '', $sql );
		
		if( $remove_single_line_comments ){
			$pattern = "/(^|;\s*\n)\s*--\s[^\n]*\n/u"; 
			$sql = preg_replace( $pattern, '$1', $sql );
		}
		
		return trim( $sql, " \r\n" );
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
		
		if( $used_variables ){
			$sql = self::check_dynamic_vars_needed( $sql, $used_variables, $options );
		}
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
				
				case AGDP_VAR_POST_STATUSES :
					$post_statuses = get_post_statuses();
					$post_statuses['trash'] = 'Corbeille';
					$post_statuses['inherit'] = 'Hérité';
					$post_statuses['auto-draft'] = 'Enregistrement auto';
					// foreach( get_post_stati() as $status )
						// $post_statuses[ $status ] = __($status);
					$var_sqls[] = sprintf('SET %s = CAST( "%s" AS JSON )'
						, $variable
						, addslashes( json_encode( $post_statuses, JSON_UNESCAPED_UNICODE ) )
					);
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
	
	/**
	 * get_render_sql
	 * Wrap sql in a SELECT scripts FROM ( sql )
	 */
 	public static function get_render_sql( $report, $sql, $sql_variables, &$options, $table_render ) {
		
		if( ! $table_render
		|| empty( $table_render['columns'] ) )
			return $sql;
		
		
		$table_columns = $table_render['columns'];
		
		$select = '';
		
		//sort by column index
		$column_index = 0;
		//add missing 'index' field
		foreach( $table_columns as $column => $column_data ){
			if( ! isset($table_columns[ $column ][ 'index' ])
			 || ! is_numeric($table_columns[ $column ][ 'index' ]))
				$table_columns[ $column ][ 'index' ] = $column_index;
			$column_index++;
		}
		
		$column_indexes = array_column($table_columns, 'index');
		array_multisort($column_indexes, SORT_ASC, $table_columns);
		
		foreach( $table_columns as $column => $column_data ){
			if( isset($table_columns[ $column ][ 'class' ]) ){
				$class = $table_columns[ $column ][ 'class' ];
				if( self::is_a_class_script( $class ) ){
					$class_sql = trim($class, " ,;\n\r");
					if( $select )
						$select .= ', ';
					$select .= sprintf('%s AS `__class__%s`', $class_sql, $column);
				}
			}
			if( empty($column_data['script']) )
				$column_sql = sprintf('`%s`', $column );
			else{
				$column_sql = $column_data['script'];
				// debug_log( __FUNCTION__, 'sprintf(\'%s AS `%s`\', $column_sql, $column)',sprintf('%s AS `%s`', $column_sql, $column));
			}
			if( $select )
				$select .= ', ';
			$select .= sprintf('%s AS `%s`', $column_sql, $column);
		}
		//wrap
		if( $select ){
			$select = sprintf('SELECT %s', $select);
			$select = static::get_sql($report, $select, $sql_variables, $options);
			$selects = explode(";\n", $select);
			if( count($selects) > 1 ){
				$select = '';//$selects[ count($select) - 1 ];
				for( $i = 0; $i < count($selects); $i++){
					if( $select )
						$select .= ";\n";
					$select .= $selects[$i];
				}
			}
			$select .= sprintf("\nFROM (%s) _render", str_replace("\n", "\n  ", $sql));
		}
		else
			$select = $sql;
		
		// sql_before_render
		$sql_before = self::get_sql_before_render( $report, $options );
		if( $sql_before ){
			$sql_before = self::get_sql( $report, $sql_before, $sql_variables, $options);
			if( is_array($sql_before) )
				$sql_before = implode(";\n", $sql_before);
			return "-- Sql avant rendu :;\n" . trim($sql_before, "\r\n ;") . ";\n-- --;\n" . $select;
		}
		
		return $select;
	}
	
	/**
	 * get_render_caption_dbresults
	 * [`__class__`, `caption`]
	 */
 	public static function get_render_caption_dbresults( $report, $sql_variables, &$options, $table_render ) {
		$sql = $options['_sqls'][ count($options['_sqls']) - 1 ];
		$sql = static::get_render_caption_sql( $report, $sql, $sql_variables, $options, $table_render );
		if( $sql ){
			$options['_no_table_columns'] = true;
			$dbresults = static::get_sql_dbresults( $report, $sql, $sql_variables, $options, $table_render );
			array_push( $options['_sqls'], $sql );
			if( is_a($dbresults, 'Exception') ){
				$dbresults = sprintf('[SQL pour %s] : %s ()', 'caption', htmlentities($dbresults->getMessage()));
				return $dbresults;
			}
			if( is_array( $dbresults ) && count( $dbresults ) ){
				return [ 'content' => isset($dbresults[0]->content) ? $dbresults[0]->content : ''
					,  'class' => isset($dbresults[0]->class) ? $dbresults[0]->class : '' 
					// ,  'script' => $sql
				];
			}
		}
		return [ 'content' => $report->post_title, 'class' => '' ];
	}
	
	/**
	 * get_render_caption_sql
	 * SELECT {caption_class} AS `class`, {caption_script} AS `content` FROM {sql} LIMIT 1
	 */
 	public static function get_render_caption_sql( $report, $sql, $sql_variables, &$options, $table_render ) {
		if( ! $table_render
		|| empty( $table_render['caption'] ))
			return '';
		
		$table_caption = $table_render['caption'];
		
		$select = '';
		
		if( ! empty($table_caption[ 'class' ]) ){
			$class = $table_caption[ 'class' ];
			$class_sql = trim($class, " ,;\n\r");
			if( self::is_a_class_script( $class ) ){
				$select .= sprintf('%s AS `class`', $class_sql);
			}
			else {
				$select .= sprintf('"%s" AS `class`', str_replace('"', '\\"', $class_sql));
			}
		}
		if( ! empty($table_caption['script']) ){
			$script = $table_caption['script'];
			if( $select )
				$select .= ', ';
			$select .= sprintf('%s AS `content`', $script);
		}
		//wrap
		if( $select ){
			$select = sprintf('SELECT %s', $select);
			$select = static::get_sql($report, $select, $sql_variables, $options);
			$selects = explode(";\n", $select);
			if( count($selects) > 1 ){
				$select = '';//$selects[ count($select) - 1 ];
				for( $i = 0; $i < count($selects); $i++){
					if( $select )
						$select .= ";\n";
					$select .= $selects[$i];
				}
			}
			$select .= sprintf(' FROM (%s) _render LIMIT 1', $sql);
		}
		else
			$select = $sql;
		return $select;
	}
	
	/**
	 * get_default_table
	 * Returns a html table based on $table_columns in case of sql error
	 */
 	public static function get_default_table( $report, $sql, $table_render, $sql_variables, &$options ) {
		self::wpdb( TRUE );
		
		$table_columns = $table_render && isset($table_render['columns']) ? $table_render['columns'] : false;
		$table_caption = $report ? $report->post_title : '';
		$html = sprintf('<table class="error"><caption>Erreur dans <var>%s</var></caption><thead><tr>', $table_caption);
		if( ! is_array($table_columns) )
			return $html . '<th></th></tr></thead></table>';
		
		//Query sans formatage de colonne
		$false = false;
		if( ! is_array($sql_variables) )
			$sql_variables = [];
		$sql_variables['LIMIT'] = 1; //TODO
		$dbresults_no_render = self::get_sql_dbresults( $report, $sql, $sql_variables, $options, $false );
		if( is_array($dbresults_no_render) and count($dbresults_no_render) ){
			$table_columns_missing = [];
			foreach( $dbresults_no_render[0] as $column_name => $column_data ){
				if( ! array_key_exists( $column_name, $table_columns ) )
					$table_columns_missing[$column_name] = [
						'label' => $column_name,
						'error' => 'Colonne manquante !'
					];
			}
			foreach( $table_columns as $column_name => $column_data ){
				if( ! isset( $dbresults_no_render[0]->$column_name ) ){
					if( empty($table_columns[$column_name]) )
						$table_columns[$column_name] = ['label' => $column_name];
					elseif( ! is_array($table_columns[$column_name]) )
						$table_columns[$column_name] = ['label' => $table_columns[$column_name]];
					if( empty($table_columns[$column_name]['label']) )
						$table_columns[$column_name]['label'] = $column_name;
					
					if( $table_columns[$column_name]['label'] != $column_name )
						$column_error = sprintf('Colonne [%s] hors SQL !', $column_name);
					else
						$column_error = 'Colonne hors SQL !';
					$table_columns[$column_name]['error'] = $column_error;
				}
			}
			
			if( $table_columns_missing ){
				//Les colonnes manquantes apparaissent en 1er
				foreach( $table_columns as $column_name => $column_data ){
					$table_columns_missing[$column_name] = $column_data;
				}
				$table_columns = $table_columns_missing;
			}
		}
		
		//thead
		foreach( $table_columns as $column_name => $column_data ){
			if( substr($column_name, 0, 2) === '__' )
				continue;
			$column_label = $column_name;
			$column_visible = true;
			$class = '';
			if( is_array($column_data) ){
				$column_label = $column_data[ 'label' ];
				$column_visible = ! isset($column_data[ 'visible' ]) || $column_data[ 'visible' ];
				$column_error = empty($column_data[ 'error' ]) ? '' : $column_data[ 'error' ];
			}
			else {
				$column_label = $column_data;
				$column_error = '';
			}
			if( $column_error )
				$column_error = sprintf('<br><span class="dashicons-before dashicons-warning color-red">%s</span>', $column_error);
			
			if( ! $column_visible && ! $column_error )
				$class .= ' hidden';
			
			$html .= sprintf('<th %s column="%s">%s%s</th>'
				, $class ? 'class="' . trim($class) . '"' : ''
				, $column_name
				, $column_label
				, $column_error
			);
		}
		$html .= '</tr></thead>';
		//tbody
		$html .= '<tbody><tr>';
		foreach( $table_columns as $column => $column_data ){
			if( substr($column_name, 0, 2) === '__' )
				continue;
			$column_visible = true;
			$class = '';
			$attributes = '';
			if( is_array($column_data) ){
				$column_visible = ! isset($column_data[ 'visible' ]) || $column_data[ 'visible' ];
			}
			if( ! $column_visible )
				$class .= ' hidden';
			$html .= sprintf('<td %s%s>%s</td>'
				, $class ? 'class="' . trim($class) . '"' : ''
				, $attributes ? ' ' . trim($attributes) : ''
				, '#erreur'
			);
		}
		
		$html .= '</tr></tbody></table>';
		return $html;
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
 	public static function get_sql_dbresults( $report = false, $sql = false, $sql_variables = false, &$options = false, &$table_render = false ) {
		
		if( is_numeric($report) )
			$report = get_post($report);
		
		$table_columns = $table_render && isset($table_render['columns']) ? $table_render['columns'] : false;
		
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
		$sql = Agdp_Report_Variables::add_sql_global_vars( $report, $sql, $sql_variables, $options );
		
		if( empty($options['_sqls'] ) )
			$options['_sqls'] = [];
			
		if( count($sql) > 1 )
			$sql = array_filter( $sql, function( $value ){ 
						return trim($value, " ;\n\r") !== '';
			} );
			
		$result = false;
		foreach( $sql as $index => $sql_u ){
			
			$matches = [];
			if( ! preg_match( '/^(?:\(|\s)*([a-zA-Z_]+)(?:\s|$|;)/', $sql_u, $matches ) ){
				$sql_command = false;
				debug_log(__FUNCTION__ . ' > Syntaxe de commande incorrecte.', $sql_u);
			}
			else 
				$sql_command = $matches[1];
			
			$is_last_sql = ( count($sql) === $index + 1 )
							&& strcasecmp( $sql_command, 'SELECT' ) === 0;
			
			$sqls_u = self::get_sql( $report, $sql_u, $sql_variables, $options );
			$sqls_u = self::get_sql_as_array( $sqls_u );
			foreach( $sqls_u as $sql_uu_index => $sql_uu ){
				if( $stop_requiered = ( strcasecmp( $sql_uu, 'STOP' ) === 0 ) ){
					break;
				}
				
				//get_render_sql for ultimate
				if( $is_last_sql
				&& $table_columns 
				&& empty($options['_no_table_columns'])
				&& ( count($sqls_u) === $sql_uu_index + 1 )){
					$sql_uu = self::get_render_sql( $report, $sql_uu, $sql_variables, $options, $table_render );
					if( strpos($sql_uu, ";\n") )
						$sql_uu = explode(";\n", str_replace( "\r", '', $sql_uu ));
				}
				if( ! is_array( $sql_uu ) )
					$sql_uu = [$sql_uu];
			
				foreach( $sql_uu as $sql_uuu ){
					if( ! $sql_uuu )
						continue;
					//$wpdb->get_results
					$result_u = $wpdb->get_results($sql_uuu);
				
					array_push( $options['_sqls'], $sql_uuu );
					if( $wpdb->last_error ){
						// debug_log( __FUNCTION__ . ' $result_u ', $result_u, $wpdb->last_error, $wpdb->last_query);
						$wpdb->last_error .= sprintf(' (%d)<br>%s', $index, $wpdb->last_query);
						$result = $result_u;
						break 3;
					}
				}
			}

			if( $result_u ){
				$result = $result_u;
				//Si ce n'est pas la dernière requête, SET @DBRESULTS = CAST( "%s" AS JSON )
				if( ! $wpdb->last_error
					&& ! $is_last_sql
				)
					self::set_previous_results_variable( $result_u );
			}
			
			if( ! empty($stop_requiered) )
				 break;
		}/* foreach( $sql as $index => $sql_u )
		 .........*/
					
		
		if($wpdb->last_error){
			if( ! is_a($result, 'WP_Error') )
				$result = new Exception( $wpdb->last_error );
		}
		
		return $result;
	}

	/**
	 * set_previous_results_variable
	 * SET @DBRESULTS = CAST( $results AS JSON )
	 */
 	private static function set_previous_results_variable( $results ) {
		$wpdb = self::wpdb();
		$max_results_in_previous = MAX_VAR_DBRESULTS_ROWS;
		if( count( $results ) > $max_results_in_previous )
			$json = json_encode( array_slice( $results, 0, $max_results_in_previous ), JSON_UNESCAPED_UNICODE );
		else
			$json = json_encode( $results, JSON_UNESCAPED_UNICODE );
		$sql_json = sprintf('SET %s = CAST( "%s" AS JSON )', AGDP_VAR_DBRESULTS, addslashes($json) );
		$result_json = $wpdb->get_results($sql_json);
		if( $wpdb->last_error ){
			$json = json_encode( $result_u, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			if( strlen($json) > 255 )
				$json = substr( $json, 0, 255 ) . '...';
			$sql_json = sprintf('SET %s = CAST( "%s" AS JSON )', AGDP_VAR_DBRESULTS, $json );
			$wpdb->last_error .= sprintf('SET %s = CAST AS JSON of %s', AGDP_VAR_DBRESULTS, $sql_json);
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
		
		$meta_key = 'table_render';
		$table_render = isset($options[$meta_key]) ? $options[$meta_key] : get_post_meta( $report_id, $meta_key, true );
		if( $table_render && is_string($table_render) ){
			$table_render = json_decode($table_render, true);
		}
		
		//table_columns
		$table_columns = isset($table_render['columns']) ? $table_render['columns'] : [];
		if( Agdp::is_admin_referer() ){
			$meta_key = 'report_admin_no_render';
			$report_admin_no_render = isset($options[$meta_key]) ? $options[$meta_key] : get_post_meta( $report_id, $meta_key, true );
			if( $report_admin_no_render )
				$options['_no_table_columns'] = true;			
		}
		$options_copy = $options;
		
		$wpdb = self::wpdb( TRUE ); //reset des variables. TODO Quid d'appels imbriqués ?
		$wpdb->last_error = false;
		$dbresults = static::get_sql_dbresults( $report, $sql, $sql_variables, $options, $table_render );
		
		$sql_prepared ='';
		//report_show_sql
		if( current_user_can('manage_options')){
			$meta_key = 'report_show_sql';
			if( isset($options[$meta_key]) )
				$report_show_sql = $options[$meta_key];
			else
				$report_show_sql = get_post_meta( $report_id, $meta_key, true );
			if( $report_show_sql
			&& ! Agdp::is_admin_referer() ){
				$meta_key = 'report_show_sql_public';
				if( isset($options[$meta_key]) )
					$report_show_sql_public = $options[$meta_key];
				else
					$report_show_sql_public = get_post_meta( $report_id, $meta_key, true );
				if( ! $report_show_sql_public )
					$report_show_sql = false;
			}
			if( $report_show_sql ){
				$sql_prepared = $options['_sqls'];
				$meta_key = 'report_show_vars';
				$report_show_vars = isset($options[$meta_key]) ? $options[$meta_key] : get_post_meta( $report_id, $meta_key, true );
				if( $report_show_vars ){
					// $sql_prepared = Agdp_Report_Variables::add_sql_global_vars( $report, $sql_prepared, $sql_variables, $options );
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
				$sql_prepared = sprintf('<pre class="sql_prepared">%s</pre>', htmlspecialchars($sql_prepared));
			}	
		}
		
		if( ! $dbresults )
			return sprintf('<div class="agdpreport" agdp_report="%d">(aucun résultat)%s</div>', $report_id, $sql_prepared);
		
		$content = '';
		
		if( is_a($dbresults, 'Exception') ){
			$content = sprintf('<div class="agdpreport error" agdp_report="%d"><pre>%s</pre><pre>%s</pre></div>'
				, $report_id
				, $dbresults->getMessage()
				, $sql_prepared
			);
			$content .= sprintf('<div class="agdpreport" agdp_report="%d">%s</div>'
				, $report_id
				, static::get_default_table( $report, $sql, $table_render, $sql_variables, $options )
			);
			return $content;
		}
		if( ! is_array($dbresults) )
			return sprintf('<div class="agdpreport error" agdp_report="%d"><pre>%s</pre><pre>%s</pre></div>'
				, $report_id, $dbresults, $sql_prepared);		
		
		$tag_id =sprintf( 'report_%s', Agdp::get_secret_code( 6 ) );
		
		$content .= sprintf('<div id="%s" class="agdpreport" agdp_report="%d"><table>',
				$tag_id,
				$report_id
		);
		
		$meta_key = 'report_admin_no_escape';
		if( ! is_admin() )
			$report_admin_no_escape = false;
		elseif( isset($options[$meta_key]) )
			$report_admin_no_escape = $options[$meta_key];
		else
			$report_admin_no_escape = get_post_meta( $report_id, $meta_key, true );
		
		$meta_key = 'report_show_indexes';
		if( isset($options[$meta_key]) )
			$report_show_indexes = $options[$meta_key];
		else
			$report_show_indexes = get_post_meta( $report_id, $meta_key, true );
		
		$meta_key = 'report_show_footer';
		if( isset($options[$meta_key]) )
			$report_show_footer = $options[$meta_key];
		else
			$report_show_footer = get_post_meta( $report_id, $meta_key, true );
		
		$meta_key = 'report_show_caption';
		if( isset($options[$meta_key]) )
			$report_show_caption = $options[$meta_key];
		else
			$report_show_caption = get_post_meta( $report_id, $meta_key, true );
		if( $report_show_caption ){
			$table_caption = static::get_render_caption_dbresults($report, $sql_variables, $options, $table_render);
			if( is_string($table_caption) ){
				$content .= sprintf('<caption class="error">#Erreur : %s</caption>', $table_caption );
			}
			elseif( is_array($table_caption) ){
				$content .= sprintf('<caption class="%s">%s</caption>', $table_caption['class'], $table_caption['content'] );
			}
		}
		//thead
		$content .= '<thead><tr class="report_fields">';
		foreach($dbresults as $row){
			if( $report_show_indexes )
				$content .= sprintf('<th>#</th>');
			$column_class = '';
			foreach($row as $column_name => $column_value){
				if( substr($column_name, 0, 2) === '__' ){
					if( substr($column_name, 0, 9) === '__class__' ){
						// if( ! empty($table_columns[ $column_name ])
						 // && ! empty($table_columns[ $column_name ][ 'class' ])
						// ){
							// $value = $table_columns[ $column_name ][ 'class' ];
							// if( ! self::is_a_class_script( $value ) )
								// $column_class .= ' ' . $value;
						// }
					}
					continue;
				}
				$column_label = $column_name;
				$column_visible = true;
				$class = $column_class;
				$column_class = '';
				$attributes = '';
				if( $table_columns && isset($table_columns[ $column_name ] ) ){
					$table_column = $table_columns[ $column_name ];
					if( is_array($table_column) ){
						$column_label = empty($table_column[ 'label' ]) ? $column_name : $table_column[ 'label' ];
						$column_visible = ! isset($table_column[ 'visible' ]) || $table_column[ 'visible' ];
						if( ! empty($table_column[ 'class' ]) ){
							$value = $table_column[ 'class' ];
							if( ! self::is_a_class_script( $value )  )
								$class .= ' ' . $value;
						}
						// if( ! empty($table_column[ 'script' ]) )
							// $attributes .= ' column_script="' . esc_attr($table_column[ 'script' ]) . '"';
					}
					else {
						$column_label = $table_column;
					}
				}
				if( ! $column_visible )
					$class .= ' hidden';
				$content .= sprintf('<th %s column="%s" %s>%s</th>'
					, $class ? 'class="' . trim($class) . '"' : ''
					, $column_name
					, $attributes ? ' ' . trim($attributes) : ''
					, $column_label
				);
			}
			break;
		}
		$content .= '</tr></thead>';
		
		//tbody
		$content .= '<tbody>';
		if( $report_admin_no_escape )
			$escape_function = false;
		else
			$escape_function = Agdp::is_admin_referer() ? 'htmlspecialchars' : 'nl2br';
		foreach($dbresults as $row_index => $row){
			$content .= '<tr>';
			if( $report_show_indexes )
				$content .= sprintf('<th>%d</th>', $row_index+1);
			$column_class = '';
			foreach($row as $column_name => $column_value){
				if( substr($column_name, 0, 2) === '__' ){
					if( substr($column_name, 0, 9) === '__class__' ){
						$column_class = $column_value;
					}
					continue;
				}
				$column_visible = true;
				$class = $column_class;
				$column_class = '';
				$attributes = '';
				if( $table_columns
				&& isset($table_columns[ $column_name ] ) 
				&& is_array($table_columns[ $column_name ]) ){
					$table_column = $table_columns[ $column_name ];
					$column_visible = ! isset($table_column[ 'visible' ]) || $table_column[ 'visible' ];
					if( ! empty($table_column[ 'class' ]) ){
						$value = $table_column[ 'class' ];
						if( ! self::is_a_class_script( $value ) ) 
							$class .= ' ' . $value;
					}
					// if( ! empty($table_column[ 'script' ]) )
						// $attributes .= ' column_script="' . esc_attr($table_column[ 'script' ]) . '"';
				}
				if( ! $column_visible )
					$class .= ' hidden';
				if( $column_value === null )
					$column_value = '';
				$content .= sprintf('<td %s%s>%s</td>'
					, $class ? 'class="' . trim($class) . '"' : ''
					, $attributes ? ' ' . trim($attributes) : ''
					, $escape_function ? $escape_function( $column_value ) : $column_value
				);
			}
			$content .= '</tr>';
			
		}
	    $content .= '</tbody>';
		
		
		//tfoot
		if( $report_show_footer ){
			$content .= '<tfoot><tr>';
			foreach($dbresults as $row){
				if( $report_show_indexes )
					$content .= sprintf('<th>#</th>');
				$column_class = '';
				foreach($row as $column_name => $column_value){
					if( substr($column_name, 0, 2) === '__' ){
						if( substr($column_name, 0, 9) === '__class__' ){
							// if( ! empty($table_columns[ $column_name ])
							 // && ! empty($table_columns[ $column_name ][ 'class' ])
							// ){
								// $value = $table_columns[ $column_name ][ 'class' ];
								// if( ! self::is_a_class_script( $value ) )
									// $column_class .= ' ' . $value;
							// }
						}
						continue;
					}
					$column_label = $column_name;
					$column_visible = true;
					$class = $column_class;
					$column_class = '';
					if( $table_columns && isset($table_columns[ $column_name ] ) ){
						$table_column = $table_columns[ $column_name ];
						if( is_array($table_column) ){
							$column_label = empty($table_column[ 'label' ]) ? $column_name : $table_column[ 'label' ];
							$column_visible = ! isset($table_column[ 'visible' ]) || $table_column[ 'visible' ];
							if( ! empty($table_column[ 'class' ]) ){
								$value = $table_column[ 'class' ];
								if( ! self::is_a_class_script( $value )  )
									$class .= ' ' . $value;
							}
						}
						else {
							$column_label = $table_column;
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
			$content .= '</tr></tfoot>';
	    } //$report_show_footer
		
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
	 * Retourne le script sql exécuté avant tout si le render est activé
	 */
	public static function get_sql_before_render( $report_id, $options = false ) {
		if( is_array( $options )
		&& isset( $options['sql_before_render'] ) )
			return $options['sql_before_render'];
			
		if( is_a( $report_id, 'WP_Post') )
			$report_id = $report_id->ID;
		
		$sql = get_post_meta( $report_id, 'sql_before_render', true );
	    
		return $sql;
	}
	
	/**
	 * Hook the_content
	 */
 	public static function the_content( $content ) {
 		global $post;
		if( ! empty($_GET['mode'])
		&& $_GET['mode'] === 'shortcode' ){
			if( ! current_user_can('manage_options')){
				// global $wp_query;
				// $wp_query->set_404();
				// status_header( 404 );
				die( "Accès par url réservé aux administrateurs" );
			}
			$shortcode = empty($_GET['shortcode']) ? '' : $_GET['shortcode'];
			if( ! $shortcode )
				$shortcode = sprintf('[%s %s]', self::shortcode, $post->ID);
			$html = sprintf("<h3>Evaluation de l'expression <code>%s</code></h3><br>%s"
				, str_replace('[', htmlentities('[', ENT_QUOTES | ENT_HTML5), htmlentities( $shortcode ) )
				, $shortcode
			);
			return $html;
		}
		
		$content = $post->post_content; // TODO quid de $content dans l'argument ?
		
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
	 * Requête Ajax d'obtention du rapport en html
	 */
	public static function on_ajax_action_report_html($data) {
		if( isset($data['report_id']) )
			$report_id = $data['report_id'];
		elseif( is_admin() && isset($_POST['post_id']) )
			$report_id = $_POST['post_id'];
		else
			$report_id = false;
		//slug
		if( is_string($report_id) && ! is_numeric($report_id) ){
			//chemin relatif
			if( isset($data['parent_post']) ){
				$relative_to = $data['parent_post'];
			}
			elseif( isset($_POST['post_ref']) ){
				if( is_array($_POST['post_ref']) )
					$relative_to = $_POST['post_ref']['id'];
				else
					$relative_to = $_POST['post_ref'];
			}
			else
				$relative_to = false;
			$report = get_relative_page($report_id, $relative_to, self::post_type);
				
			if( ! is_a( $report, 'WP_Post') )
				return sprintf('%s : rapport introuvable', $report_id);
			$report_id = $report->ID;
		}
			
		if( $report_id
		&& empty($_REQUEST['report_id']) )
			$_REQUEST['report_id'] = $report_id;

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
	 * Requête Ajax d'obtention des colonnes du report
	 */
	public static function on_ajax_action_get_report_columns($data) {
		if( isset($data['report_id']) )
			$report_id = $data['report_id'];
		elseif( is_admin() && isset($_POST['post_id']) )
			$report_id = $_POST['post_id'];
		else
			$report_id = false;
		if( isset($data['sql_variables']) ){
			$sql_variables = $data['sql_variables'];
			if( ! $sql_variables
			|| $sql_variables === 'false' )
				$sql_variables = [];
		}
		else
			$sql_variables = [];
		if( isset($data['sql']) ){
			$sql = $data['sql'];
			if( $sql === 'false' )
				$sql = false;
		}
		else
			$sql = false;
		return self::get_report_columns( $report_id, $sql, $sql_variables, $data );
	}
	
	/**
	 * Returns SQL and render columns
	 */
 	public static function get_report_columns( $report = false, $sql = false, $sql_variables = false, $options = false ) {
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
		
		// Rendu du tableau
		$meta_key = 'table_render';
		$table_render = isset($data[$meta_key]) ? $data[$meta_key] : get_post_meta( $report_id, $meta_key, true );
		if( $table_render && is_string($table_render) ){
			$table_render = json_decode($table_render, true);
		}
		$table_render_columns = isset($table_render['columns']) ? $table_render['columns'] : [];
		
		// Exécution de la requête sans mise en forme
		$false = false;
		$sql_variables['LIMIT'] = 1; //TODO
		
		$dbresults = static::get_sql_dbresults( $report, $sql, $sql_variables, $false, $false );
		
		if( is_a($dbresults, 'Exception') )
			return "Désolé, la requête SQL comporte une erreur.";
		
		if( is_array($dbresults) and count($dbresults) ){
			$table_columns = [];
			foreach( $dbresults[0] as $column_name => $column_data ){
				$table_columns[$column_name] = [
					'label' => $column_name
				];
				if( is_array($table_render_columns) ){
					if( empty($table_render_columns[$column_name]) )
						$table_columns[$column_name]['render'] = false;
					elseif( isset($table_render_columns[$column_name]['visible'])
					&& $table_render_columns[$column_name]['visible'] === false )
						$table_columns[$column_name]['render'] = 'hidden';
				}
			}
			if( is_array($table_render_columns) ){
				foreach( $table_render_columns as $column_name => $column_data ){
					if( ! $column_data )
						$column_label = $column_name;
					elseif( is_string($column_data) )
						$column_label = $column_data;
					elseif( ! empty($column_data['label']) )
						$column_label = $column_data['label'];
					if( empty($table_columns[$column_name]) ){
						$table_columns[$column_name] = [
							'label' => $column_label,
							'info' => "renommé (absent des champs de la requête SQL)",
						];
					}
					if( isset($column_data['visible']) && ! $column_data['visible'] )
						$table_columns[$column_name]['render'] = 'hidden';
				}
			}
			$ajax_response = $table_columns;
		}
		else
			$ajax_response = "Désolé, la requête ne retourne aucun résultat pour analyser les colonnes.";
		
		return $ajax_response;
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
	
	/**
	 * Teste si une valeur d'attribut class est un script ou une valeur directe de classe
	 */
	public static function is_a_class_script( $class ){
		return $class && str_replace( ['@', '(', '"', '\''], '', $class ) !== $class;
	}
}
?>