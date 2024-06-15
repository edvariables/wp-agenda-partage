<?php

/**
 * AgendaPartage -> Post abstract
 * Extension des custom post type.
 * Uilisé par Agdp_Evenement et Agdp_Covoiturage
 * 
 */
abstract class Agdp_Post {

	const post_type = false; //Must override
	const taxonomy_diffusion = false;//Must override
	const secretcode_argument = false; //Must override
	const field_prefix = false; //Must override

	const postid_argument = false; //Must override
	const posts_page_option = false; //Must override
	const newsletter_option = false; //Must override
	
	private static $send_for_diffusion_history = [];
	
	private static $post_types = [];
	
	private static $taxonomies_diffusion = [];

	private static $initiated = false;
	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;

			self::init_hooks_for_self();
		}
			
		self::$post_types[] = static::post_type;
		if( static::taxonomy_diffusion ){
			self::$taxonomies_diffusion[static::post_type] = static::taxonomy_diffusion;
		}
	}

	/**
	 * Hooks pour Agdp_Post (static::post_type === false)
	 */
	private static function init_hooks_for_self() {
		self::init_hooks_for_search();

		add_filter( 'the_title', array(__CLASS__, 'the_title'), 10, 2 );
		add_filter( 'the_content', array(__CLASS__, 'the_content'), 10, 1 );
		
		add_filter( 'pre_handle_404', array(static::class, 'on_pre_handle_404_cb'), 10, 2 );
		add_filter( 'redirect_canonical', array(static::class, 'on_redirect_canonical_cb'), 10, 2);
	}

	/**
	 * Hooks pour les classes enfants (static::post_type !== false)
	 *	Les hooks sont appelés 2 fois (par Agdp_Evenement et Agdp_Covoiturage)
	 */
	public static function init_hooks() {
		// debug_log(static::post_type . ' init_hooks', Agdp::get_current_post_type());
		
		add_filter( 'navigation_markup_template', array(static::class, 'on_navigation_markup_template_cb'), 10, 2 );
	}
	
	/**
	 * Retourne la classe qui hérite de celle-ci correspondant au post_type donné
	 */
	public static function abstracted_class($post_type = false){
		if( ! $post_type )
			if( ! ($post_type = static::post_type) )
				throw new ArgumentException('post_type argument is empty');
		switch ($post_type){
			case Agdp_Evenement::post_type :
				return 'Agdp_Evenement';
			case Agdp_Covoiturage::post_type :
				return 'Agdp_Covoiturage';
			default:
				throw new ArgumentException('post_type argument is unknown : ' . var_export($post_type, true));
		}
	}
	
	/**
	 * Retourne la classe de Post_Type qui hérite de celle-ci correspondant au post_type donné
	 */
	public static function abstracted_post_type_class($post_type = false){
		return self::abstracted_class($post_type) . '_Post_Type';
	}
	
	/**
	 * Retourne les post_types agdp
	 */
	public static function get_post_types(){
		return self::$post_types;
	}
	
	/**
	 * Retourne les labels du post_type
	 */
	public static function get_post_type_labels($post_type = false){
		if( ! $post_type )
			if( ! ($post_type = static::post_type) )
				throw new ArgumentException('post_type argument is empty');
		
		if( $post_type_object = get_post_type_object($post_type) )
			return $post_type_object->labels;
		
		$labels = new stdClass();
		$labels->name = 'Message';
		$labels->singular_name = $labels->name;
		return $labels;
	}
	
	/**
	 * Retourne les taxonomies de diffusion des post_types agdp
	 */
	public static function get_taxonomies_diffusion(){
		return self::$taxonomies_diffusion;
	}
	
	/**
	 * Retourne les taxonomies de diffusion des post_types agdp
	 */
	public static function is_managed( $post_type = false){
		if( ! $post_type )
			if( ! static::post_type )
				throw new Exception( 'L\'argument $post_type doit être fourni pour la classe abstract ' . __CLASS__ .'.' );
			else
				$post_type = static::post_type;
		$option = $post_type . '_managed';
		return Agdp::get_option($option, true);
	}
	
	/**
	 * Retourne un identifiant unique contenant le type, l'ID et le blog
	 */
	public static function get_uid( $post_id, $post_type = false ){
		if( ! $post_type ){
			if( ! is_a($post_id, 'WP_Post') ){
				$post = static::get_post($post_id);
			}
			else
				$post = $post_id;
			$post_id = $post->ID;
			$post_type = $post->post_type;
		}
		$parse = parse_url(content_url());
		$uid = sprintf('%s[%d]@%s', $post_type, $post_id, $parse['host']);
		return $uid;
	}
	/**
	 * Retourne le type, l'ID et le blog d'après un identifiant unique
	 */
	public static function parse_uid( $uid ){
		$matches = [];
		//TODO to test
		if( preg_match('/^([^[]+)\[([^\]]+)\]@(.*)$/', $uid, $matches) ){
			return [
				'type' => $matches[1],
				'id' => $matches[2],
				'host' => $matches[3],
			];
		}
		throw new Exception('L\'argument $uid doit être de la forme post_type[post_id]@host. Valeur fournie : "' . print_r($uid, true) . '"');
	}
	
	/***************
	 * the_title()
	 */
 	public static function the_title( $title, $post_id ) {
 		global $post;
 		if( ! $post
 		|| $post->ID != $post_id
 		// || $post->post_type != static::post_type
 		|| ! in_array( $post->post_type, self::$post_types )
		){
 			return $title;
		}
	    return (self::abstracted_class($post->post_type))::get_post_title( $post );
	}

	/**
	 * Hook
	 */
 	public static function the_content( $content ) {
 		global $post;
		// debug_log('the_content', $post->post_type, self::$post_types );
		if( ! $post
 		// || $post->post_type != static::post_type
		|| ! in_array( $post->post_type, self::$post_types )
		){
 			return $content;
		}
			
		if(isset($_GET['action']) && $_GET['action'] == 'activation'){
			$post = static::do_post_activation($post);
		}
		
	    return (self::abstracted_class($post->post_type))::get_post_content( $post );
	}
	
	/**
	* Retourne le post actuel si c'est bien du type agdpevent
	*
	*/
	public static function get_post($post_id = false, $post_type = false) {
		if( ! $post_type )
			if( ! ($post_type = static::post_type) )
				throw new ArgumentException('post_type argument is empty');
		
		if($post_id){
			$post = get_post($post_id);
			if( ! $post
			|| $post->post_type !== $post_type)
				return null;
			return $post;
		}
			
		global $post;
 		if( $post
 		&& $post->post_type === $post_type)
 			return $post;

		foreach([$_POST, $_GET] as $request){
			foreach(['_wpcf7_container_post', 'post_id', 'post', 'p'] as $field_name){
				if(array_key_exists($field_name, $request) && $request[$field_name]){
					$post = get_post($request[$field_name]);
					if( $post ){
						if($post->post_type === $post_type){
							//Nécessaire pour WPCF7 pour affecter une valeur à _wpcf7_container_post
							global $wp_query;
							$wp_query->in_the_loop = true;
							return $post;
						}
						return false;
					}
				}
			}
		}
		
		return false;
	}
	
	/**
	* Retourne l'export d'un post
	*
	*/
	public static function get_posts_export($posts = false, $file_format = 'ics', $return = 'url', $filters = false) {
		
		require_once( dirname(__FILE__) . '/class.agdp-posts-export.php');
		return Agdp_Posts_Export::do_export( $posts, $file_format, $return, $filters);
		
	}
	
	/**
	* Retourne les posts importés
	*
	*/
	public static function import_post_type_ics($post_type, $file_name, $default_post_status = 'publish', $original_file_name = null) {
		require_once( dirname(__FILE__) . '/class.agdp-posts-import.php');
		return Agdp_Posts_Import::import_post_type_ics( $post_type, $file_name, $default_post_status, $original_file_name);
	}
	
	/**
	 * Hook navigation template
	 * Supprime la navigation dans les posts
	 */
	public static function on_navigation_markup_template_cb( $template, $class){
		if($class === 'post-navigation'){
			// var_dump($template, $class);
			global $post;
			if( $post
			&& in_array( $post->post_type, self::$post_types )){
				$template = '<!-- no nav -->';
			};
		}
		return $template;
	}
	
	/**
	 * Hook d'une page introuvable.
	 * Il peut s'agir d'un évènement qui vient d'être créé et que seul son créateur peut voir.
	 */
	public static function on_pre_handle_404_cb($preempt, $query){
		if( ! have_posts()){
			// var_dump($query);
			//Dans le cas où l'agenda est la page d'accueil, l'url de base avec des arguments ne fonctionne pas.
			if(is_home()){
				//TODO et si Agdp_Covoiturage en page d'accueil, hein ?
				$query_field = Agdp_Evenement::postid_argument;
				$page_id_name = Agdp_Evenement::posts_page_option;
				if( (! isset($query->query_vars['post_type'])
					|| $query->query_vars['post_type'] === '')
				&& isset($query->query[$query_field])){
					$page = Agdp::get_option($page_id_name);
					$query->query_vars['post_type'] = 'page';
					$query->query_vars['page_id'] = $page;
					global $wp_query;
					$wp_query = new WP_Query($query->query_vars);
					return false;
						
				}
			}
			
			//Dans le cas d'une visualisation d'un post non publié, pour le créateur non connecté
			if(isset($query->query['post_type'])
			&& $query->query['post_type'] == static::post_type){
				foreach(['p', 'post', 'post_id', static::post_type] as $key){
					if( array_key_exists($key, $query->query)){
						if(is_numeric($query->query[$key]))
							$post = get_post($query->query[$key]);
						else{
							//Ne fonctionne pas en 'pending', il faut l'id
							$post = get_page_by_path(static::post_type . '/' . $query->query[$key]);
						}
						if(!$post)
							return false;
		
						if(in_array($post->post_status, ['draft','pending','future'])){
							
							$query->query_vars['post_status'] = $post->post_status;
							global $wp_query;
							$wp_query = new WP_Query($query->query_vars);
							return false;
						
						}
						return true;
					}
				}
			}
			return false;
		}
	}
	
	/**
	 * Interception des redirections "post_type=agdpevent&p=1837" vers "/agdpevent/nom-de-l-evenement" si il a un post_status != 'publish'
	 */
	public static function on_redirect_canonical_cb ( $redirect_url, $requested_url ){
		$query = parse_url($requested_url, PHP_URL_QUERY);
		parse_str($query, $query);
		//var_dump($query, $redirect_url, $requested_url);
		if(isset($query['post_type']) && $query['post_type'] == static::post_type
		&& isset($query['p']) && $query['p']){
			$post = get_post($query['p']);
			if($post){
				if($post->post_status != 'publish'){
					// die();
					return false;
				}
				else{
					$redirect_url = str_replace('&etat=en-attente', '', $redirect_url);
				}
				//TODO nocache_headers();
			}
		}
		return $redirect_url;
	}
	
	/**
	 * Returns, par exemple, le meta ev-siteweb. Mais si $check_show_field, on teste si le meta ev-siteweb-show est vrai.
	 */
	public static function get_post_meta($post_id, $meta_name, $single = false, $check_show_field = null){
		if(is_a($post_id, 'WP_Post'))
			$post_id = $post_id->ID;
		if($check_show_field){
			if(is_bool($check_show_field))
				$check_show_field = '-show';
			if( ! get_post_meta($post_id, $meta_name . $check_show_field, true))
				return;
		}
		return get_post_meta($post_id, $meta_name, true);

	}
	
	/**
	 * Change post status
	 */
	public static function change_post_status($post_id, $post_status) {
		if($post_status == 'publish')
			$ignore = 'sessionid';
		else
			$ignore = false;
		if(self::user_can_change_post($post_id, $ignore)){
			$postarr = ['ID' => $post_id, 'post_status' => $post_status];
			$post = wp_update_post($postarr, true);
			return ! is_a($post, 'WP_Error');
		}
		// echo self::user_can_change_post($post_id, $ignore, true);
		return false;
	}

	/**
	 * Cherche le code secret dans la requête et le compare à celui du post
	 */
	public static function get_secretcode_in_request( $post ) {
		
		// Ajax : code secret
		if(array_key_exists(static::secretcode_argument, $_REQUEST)){
			$meta_name = static::field_prefix . static::secretcode_argument;
			$codesecret = static::get_post_meta($post, $meta_name, true);	
			if($codesecret
			&& (strcasecmp( $codesecret, $_REQUEST[static::secretcode_argument]) !== 0)){
				$codesecret = '';
			}
		}
		else 
			$codesecret = false;
		return $codesecret;
	}
	/**
	 * Clé d'activation depuis le mail pour basculer en 'publish'
	 */
	public static function get_activation_key($post_id, $force_new = false){
		if(is_a($post_id, 'WP_Post'))
			$post_id = $post_id->ID;
		$meta_name = 'activation_key';
		
		$value = get_post_meta($post_id, $meta_name, true);
		if($value && $value != 1 && ! $force_new)
			return $value;
		
		$guid = uniqid();
		
		$value = crypt($guid, AGDP_TAG . '-' . $meta_name);
		
		update_post_meta($post_id, $meta_name, $value);
		
		return $value;
		
	}
	/**
	 * Indique que l'activation depuis le mail n'a pas été effectuée
	 */
	public static function waiting_for_activation($post_id){
		if(is_a($post_id, 'WP_Post'))
			$post_id = $post_id->ID;
		$meta_name = 'activation_key';
		$value = get_post_meta($post_id, $meta_name, true);
		return !! $value;
		
	}
	
	/**
	 * Contrôle de la clé d'activation 
	 */
	public static function check_activation_key($post_id, $value){
		if(is_a($post_id, 'WP_Post'))
			$post_id = $post_id->ID;
		$meta_name = 'activation_key';
		$meta_value = get_post_meta($post_id, $meta_name, true);
		return hash_equals($value, $meta_value);
	}
	
	/**
	 * Effectue l'activation du post
	 */
	public static function do_post_activation($post){
		if(is_numeric($post)){
			$post_id = $post;
			$post = get_post($post);
		}
		else
			$post_id = $post->ID;
		if(isset($_GET['ak']) 
		&& (! static::waiting_for_activation($post_id)
			|| static::check_activation_key($post, $_GET['ak']))){
			if($post->post_status != 'publish'){
				$result = wp_update_post(array('ID' => $post->ID, 'post_status' => 'publish'));
				$post->post_status = 'publish';
				if(is_wp_error($result)){
					var_dump($result);
				}
				if(static::post_type === Agdp_Covoiturage::post_type)
					echo '<p class="info">Le covoiturage est désormais activé et visible dans les covoiturages</p>';
				else
					echo '<p class="info">L\'évènement est désormais activé et visible dans l\'agenda</p>';
			}
			$meta_name = 'activation_key';
			delete_post_meta($post->ID, $meta_name);
		}
		return $post;
	}
	
	/***********************************************************/
	/**
	 * Extend WordPress search to include custom fields
	 *
	 * https://adambalee.com
	 */
	private static function init_hooks_for_search(){
		add_filter('posts_join', array(__CLASS__, 'cf_search_join' ));
		add_filter( 'posts_where', array(__CLASS__, 'cf_search_where' ));
		add_filter( 'posts_distinct', array(__CLASS__, 'cf_search_distinct' ));
	}
	/**
	 * Join posts and postmeta tables
	 *
	 * http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_join
	 */
	public static function cf_search_join( $join ) {
	    global $wpdb;

	    if ( is_search() ) {
	        $join .=' LEFT JOIN '.$wpdb->postmeta. ' ON '. $wpdb->posts . '.ID = ' . $wpdb->postmeta . '.post_id ';
	    }

	    return $join;
	}

	/**
	 * Modify the search query with posts_where
	 *
	 * http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_where
	 */
	public static function cf_search_where( $where ) {
	    global $pagenow, $wpdb;

	    if ( is_search() ) {
	        $where = preg_replace(
	            "/\(\s*".$wpdb->posts.".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
	            "(".$wpdb->posts.".post_title LIKE $1) OR (".$wpdb->postmeta.".meta_value LIKE $1)", $where );
	    }

	    return $where;
	}

	/**
	 * Prevent duplicates
	 *
	 * http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_distinct
	 */
	public static function cf_search_distinct( $where ) {
	    global $wpdb;

	    if ( is_search() ) {
	        return "DISTINCT";
	    }

	    return $where;
	}

	/**
	 * Retourne tous les termes
	 */
	public static function get_all_terms($taxonomy, $array_keys_field = 'term_id', $query_args = []){
		$query_args = array_merge( array(
				'hide_empty' => false,
				'taxonomy' => $taxonomy
			), $query_args);
		if( in_array( $taxonomy, self::get_taxonomies_diffusion() ) )
			if( empty( $query_args['orderby'] )
			 || $query_args['orderby'] === 'order_index' ){
				$order_index_filters = true;
				add_filter( 'terms_clauses', array( __CLASS__, 'on_terms_clauses_order_index'), 10, 3);
				add_action( 'pre_get_terms', array( __CLASS__, 'on_pre_get_terms_order_index'), 10, 1);
			}
		 
		$terms = get_terms( $query_args );
		
		if( isset($order_index_filters) ){
			remove_filter( 'terms_clauses', array( __CLASS__, 'on_terms_clauses_order_index'), 10, 3);
			remove_filter( 'pre_get_terms', array( __CLASS__, 'on_pre_get_terms_order_index'), 10, 1);
		}
		
		if($array_keys_field){
			$_terms = [];
			foreach($terms as $term){
				if( ! isset($term->$array_keys_field) )
					continue;
				$_terms[$term->$array_keys_field . ''] = $term;
			}
			$terms = $_terms;
		}
		
		$meta_names = [];
		if( in_array( $taxonomy, self::get_taxonomies_diffusion() ) ){
			$meta_names[] = 'default_checked';
			$meta_names[] = 'download_link';
		}
		foreach($meta_names as $meta_name){
			foreach($terms as $term)
				$term->$meta_name = get_term_meta($term->term_id, $meta_name, true);
		}
		return $terms;
	}
	/**
	 * Sort terms with order_index meta_value
		//TODO ne fonctionne pas sans on_terms_clauses
	 */
	public static function on_pre_get_terms_order_index( $query ) {
		$query->query_vars['meta_key'] = 'order_index';  
		$query->query_vars['orderby'] = 'meta_value';
	}
	/**
	 * terms_clauses pour ORDER BY order_index.meta_value 
		//TODO ne fonctionne pas sans on_pre_get_terms
	 */
	public static function on_terms_clauses_order_index( $clauses, $taxonomies, $args ) {
		if( preg_match('/(\w+)\.meta_key\s=\s\'order_index\'/', $clauses['where'], $matches ) )
			$clauses['orderby'] = sprintf('ORDER BY %s.meta_value', $matches[1]);
		return $clauses;
	}
	
	/**
	 * Taxonomies
	 */
	public static function get_taxonomies ( $except = false ){
		return self::abstracted_post_type_class()::get_taxonomies ( $except );
	}
	
	/**
	 * Retourne les éléments d'une taxonomy d'un évènement
	 */
	public static function get_post_terms( $tax_name, $post_id, $args = 'names' ) {
		if(is_object($post_id))
			$post_id = $post_id->ID;
		if( ! is_array($args)){
			if(is_string($args))
				$args = array( 'fields' => $args );
			else
				$args = array();
		}
		if(!$post_id){
			throw new Exception('get_post_terms : $post_id ne peut être null;');
		}
		return wp_get_post_terms($post_id, $tax_name, $args);
	}
	
	/**
	 * get_post_permalink
	 * Si le premier argument === true, $leave_name = true
	 * Si un argument === AGDP_EVENT_SECRETCODE, ajoute AGDP_EVENT_SECRETCODE=codesecret si on le connait
	 * 
	 */
	public static function get_post_permalink( $post, ...$url_args){
		
		if(is_numeric($post))
			$post = get_post($post);
		$post_status = $post->post_status;
		$leave_name = (count($url_args) && $url_args[0] === true);
		if( ! $leave_name
		&& $post->post_status == 'publish' ){
			$url = get_post_permalink( $post->ID);
			
		}
		else {
			if(count($url_args) && $url_args[0] === true)
				$url_args = array_slice($url_args, 1);
			$post_link = add_query_arg(
				array(
					'post_type' => $post->post_type,
					'p'         => $post->ID
				), ''
			);
			$url = home_url( $post_link );
		}
		foreach($url_args as $args){
			if($args){
				if(is_array($args))
					$args = add_query_arg($args);
				elseif($args == static::secretcode_argument){			
					//Maintient la transmission du code secret
					$ekey = static::get_secretcode_in_request($post->ID);		
					if($ekey){
						$args = static::secretcode_argument . '=' . $ekey;
					}
					else 
						continue;
				}
				if($args
				&& strpos($url, $args) === false)
					$url .= (strpos($url,'?')>0 || strpos($args,'?') ? '&' : '?') . $args;
			}
		}
		return $url;
	}
	
	/**
	* Définit si l'utilsateur courant peut modifier l'évènement
	*/
	public static function user_can_change_post($post, $ignore = false, $verbose = false){
		if(!$post)
			return false;
		if(is_numeric($post))
			$post = get_post($post);
		
		if($post->post_status == 'trash'){
			return false;
		}
		$post_id = $post->ID;
		
		//Admin : ok 
		//TODO check is_admin === interface ou user
		//TODO user can edit only his own posts
		if( is_admin() && !wp_doing_ajax()){
			die("is_admin");
			return true;
		}		
		
		//Session id de création du post identique à la session en cours
		
		if($ignore !== 'sessionid'){
			$meta_name = static::field_prefix.'sessionid' ;
			$sessionid = static::get_post_meta($post_id, $meta_name, true, false);

			if($sessionid
			&& $sessionid == Agdp::get_session_id()){
				return true;
			}
			if($verbose){
				echo sprintf('<p>Session : %s != %s</p>', $sessionid, Agdp::get_session_id());
			}
		}
		
		if(is_user_logged_in()){
			global $current_user;
			//Rôle autorisé
			if(	$current_user->has_cap( 'edit_posts' ) ){
				return true;
			}
			
			//Utilisateur associé
			if(	$current_user->ID == $post->post_author ){
				return true;
			}
			
			$user_email = $current_user->user_email;
			if( ! is_email($user_email)){
				$user_email = false;
			}
		}
		else {
			$user_email = false;
			if($verbose)
				echo sprintf('<p>Non connecté</p>');
		}
		
		$meta_name = static::field_prefix.'email' ;
		$email = get_post_meta($post_id, $meta_name, true);
		//Le mail de l'utilisateur est le même que celui du post
		if($email
		&& $user_email == $email){
			return true;
		}
		if($verbose){
			echo sprintf('<p>Email : %s != %s</p>', $email, $user_email);
		}

		//Requête avec clé de déblocage
		$ekey = static::get_secretcode_in_request($post_id);
		if($ekey){
			return true;
		}
		if($verbose){
			echo sprintf('<p>Code secret : %s != %s</p>', $ekey, $_REQUEST[static::secretcode_argument]);
		}
		
		return false;
		
	}
	
	/**
	 * Refuse import from an other blog
	 */
	public static function do_refuse_import($post_id, $refuse = true) {
		if(static::user_can_change_post($post_id)){
			// $post = wp_delete_post($post_id);
			
			$meta_name = AGDP_IMPORT_REFUSED;
			update_post_meta( $post_id, $meta_name, $refuse );
			
			if( $refuse ){
				$post = static::change_post_status($post_id, 'draft');
				if( is_a($post, 'WP_Error') )
					return false;
			}
			return true;
		}
		// echo static::user_can_change_post($post_id, false, true);
		return false;
	}
	
	/**
	 * Remove event
	 */
	public static function do_remove($post_id) {
		if(static::user_can_change_post($post_id)){
			// $post = wp_delete_post($post_id);
			$post = static::change_post_status($post_id, 'trash');
			if( is_a($post, 'WP_Error') )
				return false;
			
			static::send_for_diffusion( $post_id );
			return true;
		}
		// echo static::user_can_change_post($post_id, false, true);
		return false;
	}
	
	/**
	 * Complète le html d'un formulaire WPCF7 avec les radios et checkboxes à jour en fonction des taxonomies
	 * Si $post est fourni, modifie les valeurs sélectionnées.
	 */
 	public static function init_wpcf7_form_html( $html, $post = false ) { 
		foreach( static::get_taxonomies() as $tax_name => $taxonomy){
		
			if($post){
				$post_terms = array();
				foreach(wp_get_post_terms($post->ID, $tax_name, []) as $term)
					$post_terms[ $term->term_id . ''] = $term->name;
			}
			else {
				$post_terms = false;
			}
			$all_terms = static::get_all_terms($tax_name);
			$checkboxes = '';
			$selected = '';
			$free_text = false;
			$index = 0;
			$titles = [];
			foreach($all_terms as $term){
				$checkboxes .= sprintf(' "%s|%d"', $term->name, $term->term_id);
				if($post_terms && array_key_exists($term->term_id . '', $post_terms)){
					$selected .= sprintf('%d_', $index+1);
				}
				elseif( ! $post && $term->default_checked)
					$selected .= sprintf('%d_', $index+1);
				
				if($term->description)
					$titles[$term->name] = $term->description;
				
				$index++;
			}
			$input_name = $taxonomy['input'];
			
			if( count($titles) === 0 )
				$titles = '';
			else
				//cf agendapartage.js
				$titles = sprintf('<span class="tax_terms_titles hidden" input="%s" titles="%s"></span>'
							, self::field_prefix . $tax_name . 's[]'
							, esc_attr(json_encode($titles))
				);
			
			$html = preg_replace('/\[((checkbox|radio)\*? '.$input_name.')[^\]]*[\]]('.preg_quote('<span class="tax_terms_titles').'.*\<\/span\>)?/'
								, sprintf('[$1 %s use_label_element %s %s]%s'
									, $free_text
									, $selected ? 'default:' . rtrim($selected, '_') : ''
									, $checkboxes
									, $titles)
								, $html);
		}
		return $html;
	}
	
	public static function change_email_recipient($contact_form){
		$mail_data = $contact_form->prop('mail');
		
		$requested_id = isset($_REQUEST[static::postid_argument]) ? $_REQUEST[static::postid_argument] : false;
		if( ! ($post = self::get_post($requested_id)))
			return;
		
		$meta_name = static::field_prefix . 'email' ;
		$mail_data['recipient'] = self::get_post_meta($post, $meta_name, true);
		
		$contact_form->set_properties(array('mail'=>$mail_data));
	}
	
	/**
	 * Diffusion d'un post selon le terme de diffusion.
	 * Traite les termes ayant un paramètre "connexion",
	 * par exemple : mailto:evenement.un-autre-site@agenda-partage.fr|from:mon-site@agenda-partage.fr
	 */
	public static function send_for_diffusion( $post_id, $taxonomy_diffusion = false, $tax_inputs = false, $previous_tax_inputs = false ){
		// debug_log('send_for_diffusion', $post_id, $taxonomy_diffusion, $tax_inputs/* , $previous_tax_inputs */ );
		$post_class = self::abstracted_class();
		
		if( ! $taxonomy_diffusion
		 || ( is_string($taxonomy_diffusion)
			&& ! is_numeric($taxonomy_diffusion) )
		){
			$tax_name = is_string($taxonomy_diffusion) ? $taxonomy_diffusion : false;
			$taxonomy_diffusion = $post_class::taxonomy_diffusion;
			$query_args = [
				'meta_key' => 'connexion',
				'meta_value' => '',
				'meta_compare' => '!=',
			];
			$terms = self::abstracted_post_type_class()::get_all_diffusions( 'term_id', $query_args );
			// debug_log('send_for_diffusion  terms ', $taxonomy_diffusion, $terms );
			foreach( $terms as $term )
				static::send_for_diffusion( $post_id, $term, $tax_inputs, $previous_tax_inputs );
			return;
		}
		if( is_a($taxonomy_diffusion, 'WP_Term') ){
			$diffusion_term_id = $taxonomy_diffusion->term_id;
			$taxonomy_diffusion = $taxonomy_diffusion->taxonomy;
		}
		else
			$diffusion_term_id = $taxonomy_diffusion;
		
		if( empty($diffusion_term_id ) )
			return false;
		
		if( ! is_array($tax_inputs) ){
			$tax_inputs = self::get_post_terms( $taxonomy_diffusion, $post_id, 'ids' );
			if( is_a($tax_inputs, 'WP_Error') )
				die( __FUNCTION__ . ' : ' . $taxonomy_diffusion . ' ' . print_r($tax_inputs, true) );
		}
			
		if( is_array($tax_inputs) ){
			foreach($tax_inputs as $index=>$term)
				if( is_a($term, 'WP_Term') )
					$tax_inputs[$index] = $term->term_id;
		}
		if( is_array($previous_tax_inputs) ){
			foreach($previous_tax_inputs as $index=>$term)
				if( is_a($term, 'WP_Term') )
					$previous_tax_inputs[$index] = $term->term_id;
		}
		
		//N'existe ni avant ni après
		if( is_array($tax_inputs) || is_array($previous_tax_inputs) )
			if( ! (
				is_array($tax_inputs) && in_array( $diffusion_term_id, $tax_inputs)
				|| is_array($previous_tax_inputs) && in_array( $diffusion_term_id, $previous_tax_inputs)
			) )
				return false;
		
		$post_is_deleted = false;
		$post_status = get_post_status($post_id);
		
		if( $post_status !== 'publish' ){
			if( ! in_array( $diffusion_term_id, $tax_inputs) )
				return false;
			$post_is_deleted = true;
		}
		//Existait mais n'existe plus
		elseif( is_array($tax_inputs) && ! in_array( $diffusion_term_id, $tax_inputs) 
		 && is_array($previous_tax_inputs) && in_array( $diffusion_term_id, $previous_tax_inputs) ){
			foreach($previous_tax_inputs as $index=>$term)
				$post_is_deleted = true;
		}
		//N'existe pas
		elseif( is_array($tax_inputs) && ! in_array( $diffusion_term_id, $tax_inputs) ){
			return false;
		}
		
		$term_meta = 'connexion';
		$connexion = get_term_meta( $diffusion_term_id, $term_meta, true );
		// debug_log('send_for_diffusion $connexion',  $diffusion_term_id, $connexion );
		if( ! $connexion )
			return false;
		
		$attributes = [ 'connexion' => $connexion ];
		foreach( preg_split( '/[|\n]+/m', $connexion) as $index => $attribute){
			$attribute = explode( ':', $attribute );
			if( $index === 0 ) {
				$attributes['action'] = $action = trim(strtolower($attribute[0]), " \n\r\t");
				$first_attribute = false;
			}
			// !! strtolower( $key ) !!
			$attributes[strtolower($attribute[0])] = trim($attribute[1], " \n\r\t");
		}
		$history_key = sprintf('%d>%s:%s', $post_id, $action, $attributes[$action]);
		if( in_array( $history_key, self::$send_for_diffusion_history ) ){
			debug_log('send_for_diffusion - already in send_for_diffusion_history', $action);
			return;
		}
		else
			self::$send_for_diffusion_history[] = $history_key;

		// Export
		if( static::secretcode_argument ) 
			$filters = [ static::secretcode_argument => true ];
		if( $post_is_deleted )
			$filters['set_post_status'] = 'trash';
		
		// Export file_format .ics or 'export' attribute
		$export_type = 'ics';
		if( isset($attributes['export']) ){
			switch( $attributes['export'] ){
				case '0' :
					$export_type = false;
					break;
				case '1' :
					break;
				default :
					$export_type = $attributes['export_type'];
			}
				
		}
		
		if( $export_type )
			$export = static::get_posts_export( [ $post_id ], $export_type, 'file',  $filters );
		else
			$export = false;
		// debug_log('send_for_diffusion $export', $attributes, $filters, $export_type, $export ? file_get_contents($export) : '-' );
		
		//Send
		switch( $action ){
			case 'mailto':
				static::send_for_diffusion_mailto( $post_class, $post_id, $post_is_deleted, $post_status, $filters, $action, $attributes, $export, $export_type );
				break;
			case 'blog' :
			default:
				debug_log('send_for_diffusion ! unknown action', $action);
		}
	}
	
	/**
	 * send_for_diffusion_mailto
	 */
	public static function send_for_diffusion_mailto( $post_class, $post_id, $post_is_deleted, $post_status, $filters, $action, $attributes, $export, $export_type ){
		// debug_log('send_for_diffusion mailto', $action, $attributes, $export);
		$subject = sprintf('[%s][%s]%s.%d:status=%s'
			, get_bloginfo('name')
			, $post_class::taxonomy_diffusion
			, $post_class::post_type
			, $post_id
			, $post_is_deleted ? 'cancelled' : $post_status
		);
		$url = get_post_permalink( $post_id );
		$message = sprintf("%s\n----\n%s"
			, $url
			, html_to_plain_text($post_class::get_post_details_for_email( $post_id ))
		);
		$headers = [];
		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-type: text/html; charset=utf-8';
		$headers[] = 'Content-Transfer-Encoding: quoted-printable';
		
		// AGDP UID
		$headers[] = sprintf('X-%s-UID: %s', AGDP_TAG, $post_class::get_uid($post_id));
		
		if( ! empty($attributes['from']) )
			$headers[] = 'From:' . $attributes['from'];
		if( ! empty($attributes['reply-to']) )
			$headers[] = 'Reply-to:' . $attributes['reply-to'];
		$attachments = [];
		if( $export )
			$attachments[] = $export;
		
		$data = [
			'taxonomy' => $post_class::taxonomy_diffusion,
			'post_type' => $post_class::post_type,
			'post_id' => $post_id,
			'post_status' => $post_is_deleted ? 'cancelled' : $post_status,
			'action' => $action,
			'attributes' => $attributes,
			'export_type' => $export_type,
			'export' => $export,
			'export_filters' => $filters,
			'to' => $attributes['mailto'],
			'subject' => $subject,
			'message' => $message,
			'headers' => $headers,
			'attachments' => $attachments,
		];
		
		$filter_applied = false;
		if( isset($attributes['format']) ){
			switch( $attributes['format']) {
				case 'message' :
					$post = get_post($post_id);
					
					//Flag du post pour ne pas envoyer des messages à chaque mise à jour.
					$meta_name = sprintf('%s_%s_%s', $post_class::taxonomy_diffusion, $action, $attributes[$action] );
				// delete_post_meta($post->ID, $meta_name); //DEBUG
					$already_sent = static::get_post_meta($post, $meta_name, true);
					
					if( $data['post_status'] !== 'publish' ){
						if( ! $already_sent ){
							debug_log(__CLASS__.'::'.__FUNCTION__ . ' ! publish && ! already_sent', $post->ID . ' > ' . $post->post_title, $already_sent);
							return false;
						}
						debug_log(__CLASS__.'::'.__FUNCTION__ . ' ! publish && already_sent', $post->ID . ' > ' . $post->post_title, $already_sent);
						delete_post_meta($post->ID, $meta_name);
					}
					// elseif( $already_sent ){
						// debug_log(__CLASS__.'::'.__FUNCTION__ . ' already_sent !', $post->ID . ' > ' . $post->post_title, $already_sent);
						// return false;
					// }
					else
						update_post_meta($post->ID, $meta_name, wp_date('Y-m-d H:i:s'));
					
					$data = apply_filters($post_class::post_type . '_send_for_diffusion_' . $action, $data);
					$filter_applied = true;
					if( is_array( $data ) ) {
						if( $data['post_status'] !== 'publish' ){
							$data['subject'] = sprintf('%s%s', AGDP_SUBJECT_CANCELED, $data['subject']);
						}
						elseif( $already_sent ){
							$data['subject'] = sprintf('%s%s', AGDP_SUBJECT_UPDATED, $data['subject']);
						}
					}
					break;
				default:
			}
		}
		if( ! $filter_applied )
			$data = apply_filters($post_class::post_type . '_send_for_diffusion_' . $action, $data);
		
		if( is_array( $data ) ) {
			$subject = '=?UTF-8?B?' . base64_encode($data['subject']). '?=';
			// if( false ) //debug
			$result = wp_mail( $data['to'], $subject, $data['message'], $data['headers'], $data['attachments'] );
		}
		else
			$result = $data;
		// debug_log('send_for_diffusion debug ! wp_mail', $data );
		return $result;
	}
	
	/**
	 * Retourne l'analyse de la page des évènements ou covoiturages
	 * Fonction appelable via Agdp_Evenement, Agdp_Covoiturage ou une page quelconque
	 */
	public static function get_diagram( $blog_diagram, $page ){
		
		$page_id = $page->ID;
		$diagram = [ 
			'type' => 'page', 
			'id' => $page_id, 
			'page' => $page, 
		];
		
		//diffusion 
		if( static::post_type ){
			$post_class = self::abstracted_class();
		
			$diffusions = [];
			$taxonomy_diffusion = $post_class::taxonomy_diffusion;
			// $query_args = [
				// 'meta_key' => 'connexion',
				// 'meta_value' => '',
				// 'meta_compare' => '!=',
			// ];
			$terms = self::abstracted_post_type_class()::get_all_diffusions();//( 'term_id', $query_args );
			foreach( $terms as $term ){
				$diffusion = [
					'type' => 'diffusion', 
					'id' => $term->term_id,
					'name' => $term->taxonomy,
				];
				$term_meta = 'connexion';
				if( $connexion = get_term_meta( $term->term_id, $term_meta, true ) ){
					$attributes = [];
					foreach( explode( '|', $connexion) as $index => $attribute){
						$attribute = explode( ':', $attribute );
						if( $index === 0 ) {
							if( count($attribute) === 1 ){
								$attribute[] = $attribute[0];
								$attribute[0] = 'mailto';
							}
							$attributes['action'] = $action = strtolower($attribute[0]);
							$first_attribute = false;
						}
						elseif( count($attribute) === 1 )
							$attribute[] = true;
						$attributes[strtolower($attribute[0])] = $attribute[1];
					}
					$diffusion['connexion'] = $attributes;
				}
				$diffusions[ $term->name ] = $diffusion;
			}
			$diagram['diffusions'] = $diffusions;
		}
		
		//WPCF7
		$wpcf7s = [];
		foreach(Agdp_WPCF7::get_page_wpcf7( $page ) as $wpcf7_id => $post ){
			$wpcf7s[ $post->ID.'' ] = $post;
		}
		foreach( [ 
			'new_agdpevent_page_id' => 'agdpevent_edit_form_id',
			'new_covoiturage_page_id' => 'covoiturage_edit_form_id'
		] as $new_post_page_id => $new_post_form_id )
			if( $page->ID == Agdp::get_option($new_post_page_id) ){
				$new_post_form_id = Agdp::get_option($new_post_form_id);
				$wpcf7s[ $new_post_form_id.'' ] = get_post( $new_post_form_id );
			}
		if( count($wpcf7s) )
			$diagram['forms'] = $wpcf7s;
		
		return $diagram;
	}
	/**
	 * Rendu Html d'un diagram
	 */
	public static function get_diagram_html( $page, $diagram = false, $blog_diagram = false ){
		$html = '';
		
		if( ! static::post_type
		 && $blog_diagram
		 && isset( $blog_diagram['posts_pages'][$page->ID.''] ) ){
			$post_class = $blog_diagram['posts_pages'][$page->ID.'']['class'];
			return $post_class::get_diagram_html( $page, $diagram, $blog_diagram );
		}
		
		if( ! $diagram )
			if( ! $blog_diagram )
				throw new Exception('$blog_diagram doit être renseigné si $diagram ne l\'est pas.');
			$diagram = self::get_diagram( $blog_diagram, $page );
		
		
		if( static::post_type ){
			$meta_key = static::post_type . '_need_validation';
			if( Agdp::get_option( $meta_key ) ){
				$admin_param = is_admin() ? sprintf(' <a href="/wp-admin/admin.php?page=%s&tab=%s">%s</a>'
						,  AGDP_TAG
						, 'agdp_section_'.static::post_type.'s'
						, Agdp::icon('edit show-mouse-over')
					) : '';
				$html .= sprintf('<div>%s Validation par e-mail%s</div>'
					, Agdp::icon('lock')
					, $admin_param
				);
			}
		}
		
		$property = 'diffusions';
		if( ! empty($diagram[ $property ]) ){
			foreach( $diagram[ $property ] as $diffusion_name => $diffusion ){
				$admin_edit = is_admin() ? sprintf(' <a href="/wp-admin/term.php?taxonomy=%s&tag_ID=%d&post_type=%s">%s</a>'
					, $diffusion_name
					, $diffusion['id']
					, static::post_type
					, Agdp::icon('edit show-mouse-over')
				) : '';
				$icon = 'external';
				if( empty( $diffusion['connexion'] ) ){
					$html .= sprintf('<div>%s Diffusion %s%s</div>'
							, Agdp::icon($icon)
							, $diffusion_name
							, $admin_edit
						);
					
					continue;
				}
				
				// $icon = 'email-alt2';
				$html .= sprintf('<h3 class="toggle-trigger">%s Diffusion %s</h3>'
						, Agdp::icon($icon)
						, $diffusion_name
					);
				$html .= '<div class="toggle-container">';
					if( $admin_edit )
						$html .= sprintf('<div>%s Diffusion %s%s</div>'
								, Agdp::icon($icon)
								, $diffusion_name
								, $admin_edit
							);
					$connexion = $diffusion['connexion'];
					switch($connexion['action']){
						case 'mailto':
							$property = 'mailto';
							$html .= sprintf('<div>%s : %s</div>'
								, $property
								, $connexion[$property]
							);
							$property = 'reply-to';
							if( ! empty($connexion[$property]) )
								$html .= sprintf('<div>%s : %s</div>'
									, $property
									, $connexion[$property]
								);
							break;
						default:
							$property = $connexion['action'];
							$html .= sprintf('<div>%s : %s</div>'
								, $property
								, $connexion[$property]
							);
					}
				$html .= '</div>';
			}
		}

		$property = 'forms';
		$icon = 'feedback';
		if( ! empty( $diagram[$property] ) )
			foreach( $diagram[$property] as $wpcf7_id => $wpcf7 ){
				$html .= sprintf('<h3 class="toggle-trigger">%s %s</h3>'
					, Agdp::icon($icon)
					, $wpcf7->post_title
				);
				$html .= '<div class="toggle-container">';
					$html .= Agdp_WPCF7::get_diagram_html( $wpcf7, false, $blog_diagram );
				$html .= '</div>';
			}
			
		return $html;
	}

}
