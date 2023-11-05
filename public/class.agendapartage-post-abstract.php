<?php

/**
 * AgendaPartage -> Post abstract
 * Extension des custom post type.
 * Uilisé par AgendaPartage_Evenement et AgendaPartage_Covoiturage
 * 
 */
abstract class AgendaPartage_Post_Abstract {

	const user_role = 'author';

	const post_type = false; //Must override
	const secretcode_argument = false; //Must override
	const field_prefix = false; //Must override

	const postid_argument = false; //Must override
	const posts_page_option = false; //Must override
	
	private static $post_types = [];

	private static $initiated = false;
	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;

			self::init_hooks_for_self();
		}
			
		self::$post_types[] = static::post_type;
	}

	/**
	 * Hooks pour AgendaPartage_Post_Abstract (static::post_type === false)
	 */
	private static function init_hooks_for_self() {
		self::init_hooks_for_search();

		add_filter( 'the_title', array(__CLASS__, 'the_title'), 10, 2 );
		add_filter( 'the_content', array(__CLASS__, 'the_content'), 10, 1 );
	}

	/**
	 * Hooks pour les classes enfants (static::post_type !== false)
	 */
	public static function init_hooks() {
		// debug_log(static::post_type . ' init_hooks', AgendaPartage::get_current_post_type());

		// add_filter( 'the_title', array(static::class, 'the_title'), 10, 2 );
		// add_filter( 'the_content', array(static::class, 'the_content'), 10, 1 );
		
		add_filter( 'navigation_markup_template', array(static::class, 'on_navigation_markup_template_cb'), 10, 2 );
		
		add_filter( 'pre_handle_404', array(static::class, 'on_pre_handle_404_cb'), 10, 2 );
		add_filter( 'redirect_canonical', array(static::class, 'on_redirect_canonical_cb'), 10, 2);
		
		add_filter( 'wpcf7_form_class_attr', array(static::class, 'on_wpcf7_form_class_attr_cb'), 10, 1 ); 
	}
	
	/**
	 * Retourne la classe qui hérite de celle-ci correspondant au post_type donné
	 */
	private static function abstracted_class($post_type = false){
		if( ! $post_type )
			if( ! ($post_type = static::post_type) )
				throw new ArgumentException('post_type argument is empty');
		switch ($post_type){
			case AgendaPartage_Evenement::post_type :
				return 'AgendaPartage_Evenement';
			case AgendaPartage_Covoiturage::post_type :
				return 'AgendaPartage_Covoiturage';
			default:
				throw new ArgumentException('post_type argument is unknown : ' . var_export($post_type, true));
		}
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
		debug_log('the_content', $post->post_type, self::$post_types );
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
	 * Hook navigation template
	 * Supprime la navigation dans les posts
	 */
	public static function on_navigation_markup_template_cb( $template, $class){
		if($class === 'post-navigation'){
			// var_dump($template, $class);
			global $post;
			if( $post
			 && $post->post_type === static::post_type){
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
			//var_dump($query);
			//Dans le cas où l'agenda est la page d'accueil, l'url de base avec des arguments ne fonctionne pas
			if(is_home()){
				$query_field = static::postid_argument;
				$page_id_name = static::posts_page_option;
				if( (! isset($query->query_vars['post_type'])
					|| $query->query_vars['post_type'] === '')
				&& isset($query->query[$query_field])){
					$page = AgendaPartage::get_option($page_id_name);
					$query->query_vars['post_type'] = 'page';
					$query->query_vars['page_id'] = $page;
					global $wp_query;
					$wp_query = new WP_Query($query->query_vars);
					return false;
						
				}
			}
			
			//Dans le cas d'une visualisation d'un évènement non publié, pour le créateur non connecté
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
			$meta_name = 'ev-'.static::secretcode_argument;
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
	public static function get_activation_key($post, $force_new = false){
		if(is_numeric($post)){
			$post = get_post($post);
		}
		$post_id = $post->ID;
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
	public static function check_activation_key($post, $value){
		if(is_numeric($post)){
			$post = get_post($post);
		}
		$post_id = $post->ID;
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
				if(static::post_type === AgendaPartage_Covoiturage::post_type)
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
			throw new ArgumentException('get_post_terms : $post_id ne peut être null;');
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
			&& $sessionid == AgendaPartage::get_session_id()){
				return true;
			}
			if($verbose){
				echo sprintf('<p>Session : %s != %s</p>', $sessionid, AgendaPartage::get_session_id());
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
	 * Interception du formulaire avant que les shortcodes ne soient analysés.
	 * Affectation des valeurs par défaut.
	 */
 	public static function on_wpcf7_form_class_attr_cb( $form_class ) { 
			
		$form = WPCF7_ContactForm::get_current();
		
		switch($form->id()){
			case AgendaPartage::get_option('agdpevent_message_contact_form_id') :
			case AgendaPartage::get_option('contact_form_id') :
			case AgendaPartage::get_option('admin_message_contact_form_id') :
				static::wpcf7_contact_form_init_tags( $form );
				if( strpos($form_class, ' preventdefault-reset') === false)
					$form_class .= ' preventdefault-reset';
				else
					debug_log(__CLASS__.'::on_wpcf7_form_class_attr_cb() : appels multiples');
				break;
			default:
				break;
		}
		return $form_class;
	}
	
 	// public static function change_email_recipient($contact_form){
		// $mail_data = $contact_form->prop('mail');
		
		// $requested_id = isset($_REQUEST[AGDP_ARG_EVENTID]) ? $_REQUEST[AGDP_ARG_EVENTID] : false;
		// $agdpevent = AgendaPartage_Evenement_Edit::get_agdpevent_post($requested_id);
		
		// $meta_name = 'ev-email' ;
		// $mail_data['recipient'] = static::get_post_meta($agdpevent, $meta_name, true);
		
		// $contact_form->set_properties(array('mail'=>$mail_data));
	// }
}
