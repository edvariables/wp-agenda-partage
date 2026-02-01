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
		if(static::post_type){ //inherited
			add_action( 'wp_ajax_'.static::post_type.'_show_more', array(get_called_class(), 'on_wp_ajax_posts_show_more_cb') );
			add_action( 'wp_ajax_nopriv_'.static::post_type.'_show_more', array(get_called_class(), 'on_wp_ajax_posts_show_more_cb') );
			
			add_action( 'wp_ajax_'.AGDP_TAG.'_'.static::post_type.'s_action', array(get_called_class(), 'on_wp_ajax_posts') );
			add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_'.static::post_type.'s_action', array(get_called_class(), 'on_wp_ajax_posts') );

			add_action( 'wp_ajax_'.AGDP_TAG.'_'.static::post_type.'s_download_action', array(get_called_class(), 'on_wp_ajax_posts_download') );
			add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_'.static::post_type.'s_download_action', array(get_called_class(), 'on_wp_ajax_posts_download') );
		}
		else {
			add_action( 'wp_ajax_'.AGDP_TAG.'_posts_action', array(__CLASS__, 'on_wp_ajax_posts') );
		}
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
		// var_dump($all);
		// var_dump($queries);
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
				add_filter('posts_clauses', array(__CLASS__, 'on_posts_clauses_filters'),10,2);
				$posts_where_filters = true;
				// debug_log('get_posts $posts_where_filters = true;');
				break;
			}
		$query = self::get_posts_query(...$queries);

		add_filter( 'posts_clauses', array(__CLASS__, 'on_posts_clauses_meta_query'), 10, 2 );
		
		// debug_log(__FUNCTION__, 'get_posts $queries ', $query/* , $queries */);
		$the_query = new WP_Query( $query );
		// debug_log(__FUNCTION__, 'get_posts ' . '<pre>'.$the_query->request.'</pre>', $query, count($the_query->posts));
        
		remove_filter( 'posts_clauses', array(__CLASS__, 'on_posts_clauses_meta_query'), 10, 2 );
		
		if( ! empty($posts_where_filters))
			remove_filter('posts_clauses', array(__CLASS__, 'on_posts_clauses_filters'),10,2);
		
		return $the_query->posts; 
    }
	
	/**
	* Filtre WP_Query sur une requête
	*/
	public static function on_posts_clauses_filters($clauses, $wp_query){
		// debug_log(__FUNCTION__, $clauses , $wp_query->get( 'posts_where_filters' ));
		if($filters_sql = $wp_query->get( 'posts_where_filters' )){
			global $wpdb;
			$alias = uniqid('posts_filtered');
			$clauses['join'] .= sprintf("
			INNER JOIN (%s) %s
			ON %s.ID = %s.ID"
				, $filters_sql
				, $alias
				, $wpdb->posts
				, $alias
			);
			// debug_log(__FUNCTION__, '$clauses[\'join\']', $clauses['join']);
		}
		return $clauses;
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
		$posts = static::get_posts([
			'fields' => 'ids',
			'post_type' => $post_type,
			'numberposts' => -1,
			'post_status' => 'pending',
			'suppress_filters' => true,
		]);
		
		return $posts;
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
			wp_die('nonce error');
		
		$ajax_response = '';
		
		$method = $_POST['method'];
		$data = $_POST['data'];
		
		if( $data && is_string($data) && ! empty($_POST['contentType']) && strpos( $_POST['contentType'], 'json' ) )
			$data = json_decode(stripslashes( $data), true);
		
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
	
	/**
	 * get_posts_with_path
	 *
	 * $post_ids is sorted by post_parent hierarchy
	 */
	public static function get_posts_with_path( $post_ids, $post_type ) {
		$all_posts = get_posts([ 'include'=>$post_ids, 'post_type'=>$post_type ]);
		$posts = [];
		foreach( $all_posts as $post ){
			$posts[ $post->ID.'' ] = $post;
		}
		$posts_path = [];
		foreach( $post_ids as $post_id ){
			$post = $posts[ $post_id.'' ];
			if( is_a($post, 'WP_Post') ){
				if( $post->post_parent ){
					if( empty( $posts_path[ $post->post_parent.''] ) ){
						debug_log( __FUNCTION__, "Parent introuvable $post->post_parent", $post->ID, $post->post_parent);
						$current_path = $post->post_name;
					}
					else {
						$parent_path = $posts_path[ $post->post_parent.''];
						$current_path = $parent_path . '/' . $post->post_name;
					}
				}
				else
					$current_path = '/' . $post->post_name;
				$posts_path[ $post->ID.''] = $current_path;
				// debug_log(__FUNCTION__, $post_id, $current_path);
				$post->post_path = $current_path;
			}
		}
		return $posts;
	}
	
	/**
	 * Requête Ajax de récupération de liste de posts
	 */
	public static function on_ajax_action_get_posts( $data ) {
		
		/* $query = [
			'post_type' => 'post'
			, 'fields' => false
			, 'numberposts' => 99
		];
		if( is_array($data) )
			$query = array_merge($query, $data);
		
		$post_type = $data['post_type'];
		$post_statuses = false;
		if( ! empty($data['post_status']) )
			$post_statuses = $data['post_status'];
		if( ! $post_statuses )
			$post_statuses = ['publish'];
		
		$post_ids = self::get_posts_and_descendants( $post_type, $post_statuses, false, [0] );
		// debug_log(__FUNCTION__, '$post_ids', $post_ids);
		
		$posts = self::get_posts_with_path($post_ids, $post_type);
		
		$items = [];
		foreach( $post_ids as $post_id ){
			$post = $posts[ $post_id.'' ];
			$item = [];
				
			if( is_a($post, 'WP_Post') ){
				$post_id = $post->ID;
				if( $field = $query['fields'] )
					$item = $post->$field;
				else
					foreach( ['post_title', 'post_name', 'post_content', 'post_parent', 'post_path'] as $field )
						$item[$field] = $post->$field;
			}
			else {
				$item = $post;
			}
			$items[$post_id] = $item;
		}
		// debug_log(__FUNCTION__, '$items', $items);
		return $items;
		 */
		
		
		$query = [
			'post_type' => 'post'
			, 'fields' => 'post_title'
			, 'numberposts' => 99
		];
		if( is_array($data) )
			$query = array_merge($query, $data);
		
		$posts = [];
		foreach( get_posts($query) as $post_id => $post ){
			$item = [];
				
			if( is_a($post, 'WP_Post') ){
				$post_id = $post->ID;
				if( $field = $query['fields'] )
					$item = $post->$field;
				else
					foreach( ['post_title', 'post_name', 'post_content', 'parent_post'] as $field )
						$item[$field] = $post->$field;
			}
			else {
				$item = $post;
			}
			$posts[$post_id] = $item;
		}
		return $posts;
	}
	
	/**
	 * Retourne tous les posts à partir de parents.
	 */
	public static function get_posts_and_descendants( $post_type, $post_statuses, $post_ids = false, $parent_post_ids = false, $max_deep = 32 ) {
		
		//TODO Use of get_ancestors( id, $post_type, 'post_type') and WP_Post->ancestors
		
		if( $max_deep < 0 )
			return [];
			
		global $wpdb;
		if( $post_ids && is_array($post_ids) )
			$str_post_ids = implode(', ', $post_ids);
		else {
			if( $parent_post_ids === false )
				throw new Exception(__CLASS__.'::'.__FUNCTION__ . '($post_type, $post_ids, $parent_post_ids) : un des deux arguments ids doit être fourni.');
			if( $post_ids ){
				$str_post_ids = $post_ids;
				$post_ids = [ $post_ids ];
			}
		}
		if( $parent_post_ids !== false ){
			if( is_array($parent_post_ids) )
				$str_parent_post_ids = implode(', ', $parent_post_ids);
			else{
				$str_parent_post_ids = $parent_post_ids;
				$parent_post_ids = [ $parent_post_ids ];
			}
		}
		if( ! is_array($post_statuses) )
			$post_statuses = [ $post_statuses ];
		$str_post_statuses = '"' . implode('", "', $post_statuses) . '"';
		
		if( $post_ids )
			$sql = "SELECT post.`ID`
				FROM $wpdb->posts `post`
				WHERE post.post_type = '$post_type'
				AND post.post_status IN ( $str_post_statuses )
				AND ( post.ID IN ( $str_post_ids )
				  OR post.post_parent IN ( $str_post_ids ) )"
			;
		else
			$sql = "SELECT post.`ID`
				FROM $wpdb->posts `post`
				WHERE post.post_type = '$post_type'
				AND post.post_status IN ( $str_post_statuses )
				AND post.post_parent IN (" . $str_parent_post_ids . ")";
		
		$dbresult = $wpdb->get_results( $sql );
		
		if( is_wp_error($dbresult) )
			throw $dbresult;
		
		$ids = [];
		$parent_ids = [];
		foreach( $dbresult as $dbrow ){
			$post_id = $dbrow->ID;
			$ids[] = $post_id * 1;
			// $ids[$post_id.''] = '[' . $deep . ']' . $dbrow->post_name;
			if( ! $post_ids
			|| ! in_array( $post_id, $post_ids ) ){
				$parent_ids[] = $post_id;
			}
		}
		if( $max_deep > 0
		 && count($parent_ids) )
			$ids = array_merge( $ids, self::get_posts_and_descendants( $post_type, $post_statuses, false, $parent_ids, $max_deep - 1));
		
		return $ids;
	}
	
	
	
}
