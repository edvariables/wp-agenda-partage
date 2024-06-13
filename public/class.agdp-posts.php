<?php

/**
 * AgendaPartage -> Posts
 * Collection de posts de type agdp
 */
abstract class Agdp_Posts {

	const post_type = false; //Must override
	const page_id_option = false; //Must override
	const postid_argument = false; //Must override
	const newsletter_diffusion_term_id = false; //Must override
	
	private static $initiated = false;
	
	protected static $default_posts_per_page = 30;
	
	protected static $filters_summary = null;

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
		
		add_action( 'wp_ajax_'.static::post_type.'_show_more', array(get_called_class(), 'on_wp_ajax_posts_show_more_cb') );
		add_action( 'wp_ajax_nopriv_'.static::post_type.'_show_more', array(get_called_class(), 'on_wp_ajax_posts_show_more_cb') );
		
		add_action( 'wp_ajax_'.AGDP_TAG.'_'.static::post_type.'s_action', array(get_called_class(), 'on_wp_ajax_posts') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_'.static::post_type.'s_action', array(get_called_class(), 'on_wp_ajax_posts') );
		
		add_action( 'wp_ajax_'.AGDP_TAG.'_'.static::post_type.'s_download_action', array(get_called_class(), 'on_wp_ajax_posts_download') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_'.static::post_type.'s_download_action', array(get_called_class(), 'on_wp_ajax_posts_download') );
	}
	/*
	 * Hook
	 ******/
	
	public static function get_url( $post = false ){
		$url = get_permalink(Agdp::get_option( static::page_id_option ));
		if( $post ) {
			if( is_a($post, 'WP_Post') ) 
				$post_id = $post->ID;
			else
				$post_id = $post;
			$url = add_query_arg( static::postid_argument, $post_id, $url);
			$url .= '#' . static::postid_argument . $post_id;
		}
		else
			$url .= '#main';
		// $url = home_url();
		return $url;
	}
	
	public static function init_default_posts_query() {
		throw new Exception(__CLASS__.'::'.__FUNCTION__.'() must be inherited');
	}
	
	/**
	 * Newsletter ID d'envois des posts
	 * Retourne la valeur de l'option.
	 */
	public static function get_newsletter_diffusion_term_id() {
		Agdp::get_option( static::newsletter_diffusion_term_id);
	}
	
	/**
	* Retourne les paramètres pour WP_Query avec les paramètres par défaut.
	* N'inclut pas les filtres.
	*/
	public static function get_posts_query(...$queries){
		$all = static::$default_posts_query;
		// echo "<div style='margin-left: 15em;'>";
		foreach ($queries as $query) {
			if( ! is_array($query)){
				if(is_numeric($query))
					$query = array('posts_per_page' => $query);
				else
					$query = array();
			}
			if(isset($query['meta_query'])){
				if(isset($all['meta_query'])){
					$all['meta_query'] = array(
						(string)uniqid()=> $all['meta_query']
						, (string)uniqid()=> $query['meta_query']
						, 'relation' => 'AND'
					);
				}
				else
					$all['meta_query'] = $query['meta_query'];
				
				unset($query['meta_query']);
			}
			$all = array_merge($all, $query);
		}
		// var_dump($all['meta_query']);
		// echo "</div>";
		return $all;
		
	}
	
	/**
	 * Recherche de évènements
	 */
	public static function get_posts(...$queries){
		foreach($queries as $query)
			if(is_array($query) && array_key_exists('posts_where_filters', $query)){
				if( ! $query['posts_where_filters']){
					unset($query['posts_where_filters']);
					continue;
				}
				add_filter('posts_where', array(__CLASS__, 'on_posts_where_filters'),10,2);
				$posts_where_filters = true;
				// debug_log('get_posts $posts_where_filters = true;');
				break;
			}
		$query = self::get_posts_query(...$queries);

		add_filter( 'posts_clauses', array(__CLASS__, 'on_posts_clauses_meta_query'), 10, 2 );
		
		// debug_log(__FUNCTION__, 'get_posts $queries ', $queries);
		$the_query = new WP_Query( $query );
		// debug_log(__FUNCTION__, 'get_posts ' . '<pre>'.$the_query->request.'</pre>', $query, count($the_query->posts));
        
		remove_filter( 'posts_clauses', array(__CLASS__, 'on_posts_clauses_meta_query'), 10, 2 );
		
		if( ! empty($posts_where_filters))
			remove_filter('posts_where', array(__CLASS__, 'on_posts_where_filters'),10,2);
		
		return $the_query->posts; 
    }
	/**
	* Filtre WP_Query sur une requête
	*/
	public static function on_posts_where_filters($where, $wp_query){
		// debug_log('on_posts_where_filters', $where , $wp_query->get( 'posts_where_filters' ));
		if($filters_sql = $wp_query->get( 'posts_where_filters' )){
			global $wpdb;
			$where .= ' AND ' . $wpdb->posts . '.ID IN ('.$filters_sql.')';
		}
		return $where;
	}
	
	/***********************************************************/
	/**
	 * WP_Query hack for meta_query
	 * Duplicate "meta_key = 'xxx'" from WHERE clause into JOIN clause for each postmeta
	 */
	public static function on_posts_clauses_meta_query( $clauses, $query){
	    global $wpdb;
		
		$postmeta = $wpdb->postmeta;
		
		//JOIN clause aliases
		$matches = [];
		$pattern = sprintf('/(%s)(\sAS\s(\w+))?\sON\s/i', preg_quote($postmeta));
		if( preg_match_all( $pattern, $clauses['join'], $matches ) ){
			$aliases = [];
			$aliases_pattern = '';
			foreach( $matches[3] as $index => $alias ){
				if( $alias === '' )
					$alias = $postmeta;
				if( array_key_exists($alias, $aliases) )
					continue;
				$aliases[$alias] = $matches[0][$index];
				if( $aliases_pattern )
					$aliases_pattern .= '|';
				$aliases_pattern .= preg_quote($alias);
			}
			
			// search in WHERE clause for alias.meta_key = 'xxx'
			$pattern = sprintf('/(%s)\.meta_key\s=\s\'([^\']+)\'/', $aliases_pattern);
			if( preg_match_all( $pattern, $clauses['where'], $matches ) ){
				foreach( $matches[1] as $index => $alias ){
					/*INNER JOIN wor5504_postmeta AS mt1 ON ( wor5504_posts.ID = mt1.post_id )  
					becomes
					INNER JOIN wor5504_postmeta AS mt1 ON  mt1.meta_key = 'ev-date-end' AND ( wor5504_posts.ID = mt1.post_id )  */
					$join_clause = sprintf('%s %s AND ', $aliases[$alias], $matches[0][$index]);
					if( strpos( $clauses['join'], $join_clause ) === false )
						$clauses['join'] = str_replace( $aliases[$alias], $join_clause, $clauses['join']);
				}
			}
		}
		
		return $clauses;
	}

	/**
	 * Retourne les filtres
	 */
	public static function get_filters($filters = null){
		// debug_log('get_filters IN $_REQUEST', $_REQUEST);
		if( ! $filters){
			if(isset($_REQUEST['action'])
			&& $_REQUEST['action'] === 'filters'){
				$filters = $_REQUEST;
				unset($filters['action']);
			}
			elseif( isset($_REQUEST['data']) &&  isset($_REQUEST['data']['filters'])){
				return $_REQUEST['data']['filters'];
			}
			else
				return [];
			//possible aussi avec $_SERVER['referer']
			
		}
		if( isset($filters['data']) &&  isset($filters['data']['filters']))
			return $filters['data']['filters'];
		// debug_log('get_filters RETURN $filters', $filters);
		return $filters;
	}

	/**
	 * Ajoute un filtre sur une taxonomie
	 */
	public static function add_tax_filter($taxonomy, $term_id){
		if($term_id == -1)
			return;
		if(empty($_REQUEST['data']))
			$_REQUEST['data'] = ['filters'=>[]];
		elseif(empty($_REQUEST['data']['filters']))
			$_REQUEST['data']['filters'] = [];
		if(empty($_REQUEST['data']['filters'][$taxonomy . 's']))
			$_REQUEST['data']['filters'][$taxonomy . 's'] = [];
		$_REQUEST['data']['filters'][$taxonomy . 's'][$term_id . ''] = 'on';
	}
	
	
		
	/**
	* Retourne les posts en attente
	*
	*/
	public static function get_pending_posts( $post_type = false) {
		if( ! $post_type ){
			if( ! static::post_type )
				$post_type = Agdp_Post::get_post_types();
			else
				$post_type = static::post_type;
		}
		return static::get_posts([
			'fields' => 'ids',
			'post_type' => $post_type,
			'numberposts' => -1,
			'post_status' => 'pending',
		]);
	}

	/**
	 * Show more
	 */
	public static function on_wp_ajax_posts_show_more_cb() {
		if(! array_key_exists("data", $_POST)){
			$ajax_response = '';
		}
		else {
			$data = $_POST['data'];
			if( array_key_exists("month", $data)){
				$ajax_response = static::get_month_posts_list_html($data['month']);
			}
			elseif( array_key_exists("week", $data)){ //TODO check covoiturages
				$ajax_response = static::get_week_posts_list_html($data['week']);
			}
		}
		
		// Make your array as json
		wp_send_json($ajax_response);
	 
		// Don't forget to stop execution afterward.
		wp_die();
	}

	/**
	 * Requête Ajax
	 */
	public static function on_wp_ajax_posts() {
		if( ! Agdp::check_nonce()
		|| empty($_POST['method']))
			wp_die('no-nonce');
		
		$ajax_response = '';
		
		$method = $_POST['method'];
		$data = $_POST['data'];
		
		try{
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
	 * Requête Ajax
	 */
	public static function on_wp_ajax_posts_download(){
		//debug_log('on_wp_ajax_agdpevents_download', $_REQUEST);
		if( ! isset($_REQUEST['data']) )
			wp_die('missing &data');
		$data = $_REQUEST['data'];
		$content = static::on_ajax_action_download_file($data, 'data');
		$filename = sprintf('%s.%s', static::post_type.'s', $data['file_format']);
		
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . strlen($content));
		flush(); // Flush system output buffer
		echo $content;
		wp_die();
	}
}
