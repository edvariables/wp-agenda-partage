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
class Agdp_Report {

	const post_type = 'agdpreport';
		
	// const user_role = 'author';

	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;

			self::init_hooks();
		}
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
	 * SQL
	 */
 	public static function get_sql( $report = false, $sql = false, $sql_variables = false ) {
		
		$report_id = $report->ID;
		
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
	    // $current_user = wp_get_current_user();
		// $user_id = $current_user->ID;
		// $user_email = $current_user->user_email;
		
		//sql
		if( ! $sql )
			$sql = get_post_meta( $report_id, 'sql', true );
		
		//blog_prefix
		$sql = str_replace( AGDP_BLOG_PREFIX, $blog_prefix, $sql);
		
		//valeurs des variables
		if( ! $sql_variables )
			$sql_variables = [];
		elseif( is_string($sql_variables) )
			$sql_variables = json_decode($sql_variables, true);
		$default_sql_variables = get_post_meta( $report_id, 'sql_variables', true );
		if( $default_sql_variables && is_string($default_sql_variables) ){
			if( ! $sql_variables )
				$sql_variables = json_decode($default_sql_variables, true);
			else
				$sql_variables = array_merge(json_decode($default_sql_variables, true), $sql_variables);
		}
			
		//comments
		$pattern = "/\\/\\*(.*?)\\*\\//us"; 
		$sql = preg_replace( $pattern, '', $sql );
		
		//strings
		$matches = [];
		$pattern = "/\"([^\"]+)\"/"; //TODO simple quote
		$strings_prefix = uniqid('__sqlstr_');
		$sql_strings = [];
		while( preg_match( $pattern, $sql, $matches ) ){
			$string = $matches[1];
			$variable = sprintf('%s_%d', $strings_prefix, count($sql_strings));
			$sql_strings[$variable] = $string;
			$sql = str_replace( $matches[0], ':' . $variable, $sql );
		}
		
		//Variables
		$matches = [];
		$allowed_format = '(?:[1-9][0-9]*[$])?[-+0-9]*(?: |0|\'.)?[-+0-9]*(?:\.[0-9]+)?'; //cf /wp-includes/class-wpdb.php
		$pattern = "/\:([a-zA-Z0-9_]+)(%(?:$allowed_format)?[sdfFiN])?/";
		if( preg_match_all( $pattern, $sql, $matches ) ){
			$variables = [];
			$prepare = [];
			foreach($matches[1] as $index => $variable){
				//value
				if( ! isset( $variables[$variable] )){
					if( strpos($variable, $strings_prefix ) === 0 ){
						$value = $sql_strings[$variable];
					}
					else {
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
				//format
				$src = $matches[0][$index];
				$format = $matches[2][$index];
				$format_IN = false;
				if( $sql_variables && isset($sql_variables[$variable]) && isset($sql_variables[$variable]['type']) ){
					switch($sql_variables[$variable]['type']){
						case 'checkboxes': 
							if( $format === '%N' ){
								$format_IN = true;
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
						default:
					}
				}
				if( ! $format )
					$format = '%s';
				$sql = preg_replace( '/' . preg_quote($src) . '(?!%)/', $format, $sql );
									
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
				else
					$prepare[] = $variables[$variable];
			}
			//prepare
			$sql = $wpdb->prepare($sql, $prepare);
		}
		
		return $sql;
	}

	/**
	 * SQL results
	 */
 	public static function get_sql_dbresults( $report = false, $sql = false, $sql_variables = false ) {
		$sql = self::get_sql( $report, $sql, $sql_variables );
		if( ! $sql )
			return;
		
		global $wpdb;
		$wpdb->suppress_errors(true);
		$result = $wpdb->get_results($sql);
		$wpdb->suppress_errors(false);
		if($wpdb->last_error)
			$result = new Exception($wpdb->last_error);
		return $result;
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
		if( ! is_array($options) )
			$options = [];
		
		$report_id = $report->ID;
		
		$sql_prepared ='';
		if( is_admin() && current_user_can('manage_options')){
			$meta_key = 'report_show_sql';
			if( isset($options[$meta_key]) )
				$report_show_sql = $options[$meta_key];
			else
				$report_show_sql = get_post_meta( $report_id, $meta_key, true );
			if( $report_show_sql )
				$sql_prepared = sprintf('<div class="sql_prepared">%s</pre>', self::get_sql( $report, $sql, $sql_variables ));
		}	
		
		$dbresults = self::get_sql_dbresults( $report, $sql, $sql_variables );
		
		if( ! $dbresults )
			return sprintf('<div class="agdpreport" agdp_report="%d">?%s</div>', $report_id, $sql_prepared);
		
		if( is_a($dbresults, 'Exception') )
			return sprintf('<div class="agdpreport error" agdp_report="%d"><pre>%s</pre>%s</div>'
				, $report_id, $dbresults->getMessage()
				, $sql_prepared);
		if( ! is_array($dbresults) )
			return sprintf('<div class="agdpreport error" agdp_report="%d"><pre>%s</pre>%s</div>'
				, $report_id, $dbresults, $sql_prepared);
		
		$content = sprintf('<div class="agdpreport" agdp_report="%d"><table>',
				$report_id
		);
		$content .= '<thead><tr>';
		foreach($dbresults as $row){
			$content .= sprintf('<th>#</th>');
			foreach($row as $field_name => $field_value){
				$content .= sprintf('<th>%s</th>', $field_name);
			}
			break;
		}
		$content .= '</tr></thead>';
		$content .= '<tbody>';
		foreach($dbresults as $row_index => $row){
			$content .= '<tr>';
			$content .= sprintf('<th>%d</th>', $row_index+1);
			foreach($row as $field_name => $field_value){
				$content .= sprintf('<td>%s</td>', htmlspecialchars( $field_value ));
			}
			$content .= '</tr>';
			
		}
	    $content .= '</tbody>';
	    $content .= '</table>';
		
		if( $sql_prepared ) 
			$content .= $sql_prepared;
	    $content .= '</div>';
		
		return $content;
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
		
		global $wpdb;
		
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