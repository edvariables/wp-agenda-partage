<?php 

/**
 * Traitement des packages
 * 
 * Sont appelés packages les exports de données présents en base de données et qui représentent une part de la programmation du plugin.
 * Ces données sont importées
 */
class Agdp_Admin_Packages {

	static $initialized = false;
	
	static $current_user_can = null;

	public static function init() {
		if(self::$initialized)
			return;
		self::$initialized = true;
		self::init_includes();
		self::init_hooks();
	}

	public static function init_includes() {
	}

	public static function init_hooks() {
	}
	
	/**
	 * get_packages_path
	 */
	public static function get_packages_path( $mkdir_if_needed = false ) {
		
		$dir = AGDP_PLUGIN_DIR . '/packages';
		
		if( $mkdir_if_needed
		&& ! file_exists($dir) ){
			mkdir($dir,644);
		}
		return $dir;
	}
	
	/**
	 * get_post_type_package_file
	 */
	public static function get_post_type_package_file( $post_type, $last_update = false ) {
		return sprintf('%s/%s.pack.%s' 
			, self::get_packages_path()
			, $post_type
			// , str_replace(':', '', str_replace('-', '', str_replace(' ', '_', $last_update )))
			, AGDP_TAG
		);
	}
	
	/**
	 * get_package_file_post_type
	 */
	public static function get_package_file_post_type( $file_name ) {
		$infos = explode( '.', basename($file_name) );
		return $infos[0];
	}
	
	/**
	 * get_post_type_package_url
	 */
	public static function get_post_type_package_url( $post_type, $file = null ) {
		if( $file === null )
			$file = self::get_post_type_package_file( $post_type );
		$file = str_replace( '\\', '/', $file );
		return str_replace( str_replace( '\\', '/', WP_CONTENT_DIR), WP_CONTENT_URL, $file );
	}
	
	/**
	 * get_existing_post_type_package_file
	 */
	public static function get_existing_post_type_package_file( $post_type ) {
		$mask = self::get_post_type_package_file( $post_type, '?*' );
		$dir = dirname($mask);
		$mask = basename($mask);
		$cdir = scandir($dir);
		foreach ($cdir as $key => $value){
			if ( fnmatch( $mask, $value ) ){
				if ( ! is_dir($dir . DIRECTORY_SEPARATOR . $value)){
					return $dir . DIRECTORY_SEPARATOR . $value;
				} 
			}
		}
		return false;
	}
	
	/**
	 * get_packageable_post_types
	 */
	public static function get_packageable_post_types() {
		return [
			Agdp_Report::post_type,
			'page',
			Agdp_WPCF7::post_type,
		];
	}
	
	/**
	 * import_form
	 */
	 public static function import_form() {
		 
		if( ! empty($_POST['packages_form_submit']) ){
			self::submit_packages_form();
			self::generate_packages();
		}
		
		echo sprintf('<form id="agdppackages" method="POST">');
		
		foreach( self::get_packageable_post_types() as $post_type ){
			self::generate_form_post_type( $post_type );
		}
		
		echo sprintf('<div><input type="submit" name="packages_form_submit" value="%s" class="button button-primary button-large"></div>'
			, 'Générer le package');
			
		echo '</form>';
	}
	
	/**
	 * current_user_can
	 */
	 public static function current_user_can( $action ) {
		if( self::$current_user_can
		&& isset(self::$current_user_can[$action])
		&& self::$current_user_can[$action] !== null )
			 return self::$current_user_can[$action];
		
		if( ! is_array(self::$current_user_can) )
			self::$current_user_can = [];
		
		$current_user_can = false;
		switch($action){
			case 'generate':
			case 'import':
			default:
				$current_user_can = current_user_can('manage_options');
		}
		self::$current_user_can[$action] = $current_user_can;
		
		return self::$current_user_can[$action];
	}
	/**
	 * current_user_can_generate
	 */
	 public static function current_user_can_generate() {
		 return Agdp::get_option('can_generate_packages')
			&& self::current_user_can('generate');
	}
	/**
	 * current_user_can_import
	 */
	 public static function current_user_can_import() {
		 return self::current_user_can('import');
	}
	
	/**
	 * generate_form
	 */
	 public static function generate_form() {
		// if( ! empty($_POST['import-agdppackage']) ){
			// Agdp_Admin_Options::agdp_import_page_html();
			// return;
		// }
		 
		
		if( ! empty($_POST['packages_form_submit']) ){
			self::submit_packages_form();
			if( Agdp::get_option('can_generate_packages') )
				self::generate_packages();
		}
		
		echo sprintf('<form id="agdppackages" method="POST">');
		
		if( self::current_user_can_generate() ){
			echo sprintf('<ul><li class="agdppackages-post_type">'
					. '<input type="submit" name="packages_form_submit" value="%s" class="button button-primary button-large">'
					. '</li>'
				, 'Générer');
		}
		
		foreach( self::get_packageable_post_types() as $post_type ){
			self::generate_form_post_type( $post_type );
		}
		
		echo '</ul></form>';
	}
	
	/**
	 * get_import_link
	 */
	public static function get_import_link( $post_type, $label = null, $class = "button-primary button-large" ) {
		if( $label === null )
			$label = 'Package existant';
		$file = self::get_existing_post_type_package_file( $post_type );
		if( $file ){
			$file_time = filemtime($file);
			$date = wp_date("d/m/Y à H:i:s", $file_time);
			if( ! self::current_user_can_import() ){
				echo sprintf('<div class="import-file">'
						. '<span class="dashicons-before dashicons-database-import"></span>Package<br>du %s'
						. '</div>'
					, $date//substr( $file, strlen( self::get_packages_path() ) + 1 )
				);
			}
			else {
				$url = wp_nonce_url( '/wp-admin/admin.php?page=agendapartage-import', Agdp_Admin_Edit_Post_Type::get_nonce_name( 'import', 0) );
				$url = add_query_arg( 'import-agdppackage', 1, $url );
				$url = add_query_arg( 'agdppackage-post_type', $post_type, $url );
				echo sprintf('<div class="import-file">'
						. '<a class="%s" href="%s"><span class="dashicons-before dashicons-database-import"></span>'
						. '%s<br>du %s</a>'
						. '</div>'
					, $class
					, $url
					, $label
					, $date//substr( $file, strlen( self::get_packages_path() ) + 1 )
				);
			}
		}
	}
	
	/**
	 * generate_form_post_type
	 */
	private static function generate_form_post_type( $post_type ) {
		
		echo sprintf('<li class="agdppackages-post_type"><h3>%s</h3><ul class="in_package">'
			, $post_type
		);
		
		self::get_import_link( $post_type );
		
		if( self::current_user_can_generate() ){
			$file = self::get_existing_post_type_package_file( $post_type );
			if( $file ){
				$file_time = filemtime($file);
				$file_time = strtotime(wp_date('Y-m-d H:i:s', $file_time));
			}
			else
				$file_time = false;
			
			$root_posts = [0];
			$post_ids = Agdp_Posts::get_posts_and_descendants( $post_type, 'publish', false, $root_posts, 0);
			$posts = get_posts([ 'include'=>$post_ids, 'post_type'=>$post_type ]);
			foreach($posts as $post){
				$meta_key = '_is_package_root';
				$is_package_root = get_post_meta( $post->ID, $meta_key, true );
				
				$post_time = strtotime( $post->post_modified );
				$is_newer = $file_time && $file_time < $post_time;
				
				if( $post->post_type === Agdp_WPCF7::post_type )
					$url = sprintf('/wp-admin/admin.php?page=wpcf7&post=%s&action=edit', $post->ID);
				else
					$url = $post->guid;
				echo sprintf('<li><label><input type="checkbox" name="%s[]" value="%d" %s>%s%s</label></li>'
					, $meta_key
					, $post->ID
					, $is_package_root ? 'checked="checked"' : ''
					, sprintf('<a href="%s" target="_blank">%s</a>'
						, $url
						, htmlentities($post->post_title)
					)
					, ! $is_newer ? '' 
						: sprintf('<span class="dashicons-before dashicons-plus" title="Plus récent (%s > %s)"></span>'
							, date('d/m/Y H:i:s', $post_time)
							, date('d/m/Y H:i:s', $file_time)
						)
				);
			}
			
			self::generate_form_post_type_taxonomies( $post_type );
		}
		echo '</ul></li>';
	}
	
	
	/**
	 * generate_form_post_type_taxonomies
	 */
	private static function generate_form_post_type_taxonomies( $post_type ) {
		
		$post_taxonomies = get_taxonomies([ 'object_type' => [$post_type] ], 'objects');
		if( count($post_taxonomies) ){
			echo '<label>Taxonomies</label>';
			foreach($post_taxonomies as $taxonomy){
				$tax_terms = get_terms(array(
					'taxonomy'   => $taxonomy->name,
					'hide_empty' => false,
				));
				if( ! $tax_terms || count($tax_terms) === 0 )
					continue;
				
				$meta_key = '_tax_in_package';
				echo sprintf('<li><label><input disabled type="checkbox" name="%s[]" value="%d" %s>%s</label>'
					, $meta_key
					, $taxonomy->name
					, 'checked=1'
					, sprintf('<a href="%s" target="_blank">%s</a>'
						, "/wp-admin/edit-tags.php?taxonomy=$taxonomy->name&post_type=$post_type"
						, htmlentities($taxonomy->label)
					)
				);
				echo '<ul>';
				foreach( $tax_terms as $term ){
					$meta_key = '_term_in_package';
					$is_package_root = get_term_meta( $term->term_id, $meta_key, true );
					echo sprintf('<li><label><input type="checkbox" name="%s[]" value="%d" %s>%s</label></li>'
						, $meta_key
						, $term->term_id
						, $is_package_root ? 'checked="checked"' : ''
						, sprintf('<a href="%s" target="_blank">%s</a>'
							, "/wp-admin/term.php?taxonomy=$taxonomy->name&tag_ID=$term->term_id&post_type=$post_type"
							, htmlentities($term->name)
						)
					);
				}
				echo '</ul></li>';
			}
		}
	}
	
	/**
	 * submit_packages_form
	 */
	private static function submit_packages_form() {
		
		// echo '<pre>';
		// var_dump( $_POST );
		// echo '</pre>';
		
		$post_types = self::get_packageable_post_types();
		
		$meta_key = '_is_package_root';
		//posts existants avec _is_package_root
		$posts = get_posts([ 
			'post_type' => $post_types,
			'meta_key' => $meta_key,
			'meta_value' => '1',
			'fields' => 'ids',
		]);
		
		$checked_ids = [];
		foreach($posts as $post_id){
			if( ! in_array( $post_id, $_POST[$meta_key])){
				// debug_log(__FUNCTION__, 'delete_post_meta', $post_id, $meta_key ); 
				delete_post_meta( $post_id, $meta_key );
			}
			else {
				$checked_ids[] = $post_id;
			}
		}
		
		if( ! empty($_POST[$meta_key]) )
			foreach( $_POST[$meta_key] as $post_id){
				if( ! in_array( $post_id, $checked_ids ) )
					update_post_meta( $post_id, $meta_key, '1' ); 
			}
		
		/**
		 * Taxonomies
		 */
		$meta_key = '_term_in_package';
		if( ! empty($_POST[$meta_key]) ){
			foreach($post_types as $post_type){
				$post_taxonomies = get_taxonomies([ 'object_type' => [$post_type] ], 'names');
				if( ! $post_taxonomies || count($post_taxonomies) === 0 )
					continue;
				//terms existants avec _term_in_package
				$terms = get_terms([ 
					'taxonomy' => $post_taxonomies,
					'hide_empty' => false,
					'meta_key' => $meta_key,
					'meta_value' => '1',
					'fields' => 'ids',
				]);
				if( is_wp_error($terms) ){
					var_dump($terms);
					continue;
				}
				
				$checked_ids = [];
				foreach($terms as $term_id){
					if( ! in_array( $term_id, $_POST[$meta_key])){ //TODO SIC danger si no import tax
						// debug_log(__FUNCTION__, 'delete_term_meta', $term_id, $meta_key ); 
						delete_term_meta( $term_id, $meta_key );
					}
					else {
						$checked_ids[] = $term_id;
					}
				}
				
				if( ! empty($_POST[$meta_key]) )
					foreach( $_POST[$meta_key] as $term_id){
						if( ! in_array( $term_id, $checked_ids ) )
							update_term_meta( $term_id, $meta_key, '1' ); 
					}
			}
		}
	}
	
	/**
	 * generate_packages
	 */
	private static function generate_packages( ) {
		
		echo sprintf('<div class="agdppackages"><h2>Packages</h2>');
		
		self::delete_packages();
		
		foreach( self::get_packageable_post_types() as $post_type )
			self::generate_post_type_package($post_type);
		
		echo '</div>';
	}
	
	/**
	 * delete_packages
	 */
	private static function delete_packages( ) {
		$mask = basename(self::get_post_type_package_file( '?*', '?*' ));
		$dir = self::get_packages_path(true);
		$cdir = scandir($dir);
		foreach ($cdir as $key => $value){
			if ( fnmatch( $mask, $value ) ){
				if ( ! is_dir($dir . DIRECTORY_SEPARATOR . $value)){
					unlink( $dir . DIRECTORY_SEPARATOR . $value );
				} 
			}
		}
	}
	
	/**
	 * generate_post_type_package
	 */
	private static function generate_post_type_package( $post_type ) {
		
		$meta_key = '_is_package_root';
		//posts existants avec _is_package_root
		$root_posts = get_posts([ 
			'post_type' => $post_type,
			'meta_key' => $meta_key,
			'meta_value' => '1',
			'fields' => 'ids',
		]);
		if( ! $root_posts )
			return;
		
		$meta_key = '_term_in_package';
		//terms existants avec _term_in_package
		$post_taxonomies = get_taxonomies([ 'object_type' => [$post_type] ], 'names');
		if( $post_taxonomies ){
			//terms existants avec _term_in_package
			$terms = get_terms([ 
				'taxonomy' => $post_taxonomies,
				'hide_empty' => false,
				'meta_key' => $meta_key,
				'meta_value' => '1',
				'fields' => 'ids',
			]);
		} else 
			$terms = false;
		
		echo sprintf('<div class="agdppackages-post_type" post_type="%s"><h3>%s</h3><ul>'
			, $post_type
			, $post_type
		);

		$post_ids = Agdp_Posts::get_posts_and_descendants( $post_type, 'publish', $root_posts);
		$posts = get_posts([ 'include'=>$post_ids, 'post_type'=>$post_type ]);
		$max_update = '';
		foreach($posts as $post){
			if( $max_update < $post->post_modified_gmt )
				$max_update = $post->post_modified_gmt;
		}
		$file_name = self::get_post_type_package_file( $post_type, $max_update );
		
		echo sprintf('<h4>%s</h4>', $file_name);
		
		$options = [];
		if( $terms )
			$options[ 'include_terms' ] = $terms;
		
		$data = Agdp_Posts_Export::export_posts_object( $posts, $options );
		if( $data ){
			$data = json_encode($data, JSON_OBJECT_AS_ARRAY | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			// $data = serialize( json_decode($data) );//remove object reference
			// $data = serialize( $data );//remove object reference
			if( file_exists($file_name) )
				unlink($file_name);
			file_put_contents( $file_name, $data );
		}
		else
			$data = '';
		
		$url = wp_nonce_url( '/wp-admin/admin.php?page=agendapartage-import', Agdp_Admin_Edit_Post_Type::get_nonce_name( 'import', 0) );
		// $nonce_name = Agdp_Admin_Edit_Post_Type::get_nonce_name( 'import', 0 );
		echo sprintf('<form action="%s" method="POST">'
			// . '<input type="hidden" name="%s" value="%s">'
			. '<input type="hidden" name="import-agdppackage" value="1">'
			. '<input type="hidden" name="agdppackage-post_type" value="%s">'
			. '<button type="send"><span class="dashicons-before dashicons-database-import"></span>Importer le package</button>'
			. '</form>'
			, $url
			// , $$nonce_name
			, $post_type
		);
		
		echo sprintf('<textarea style="width: 100%%;" rows="2">%s</textarea>', htmlspecialchars($data) );

		echo '</ul></div>';
	}

}

?>