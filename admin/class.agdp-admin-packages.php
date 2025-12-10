<?php 

/**
 * Traitement des packages
 * 
 * Sont appelés packages les exports de données présents en base de données et qui représentent une part de la programmation du plugin.
 * Ces données sont importées
 */
class Agdp_Admin_Packages {

	static $initialized = false;

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
	public static function get_post_type_package_file( $post_type, $last_update ) {
		return sprintf('%s/%s_%s.pack.%s' 
			, self::get_packages_path()
			, $post_type
			, str_replace(':', '', str_replace('-', '', str_replace(' ', '_', $last_update )))
			, AGDP_TAG
		);
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
		return [ Agdp_Report::post_type, 'page' ];
	}
	
	/**
	 * generate_form
	 */
	 public static function generate_form() {
		if( ! empty($_POST['import-agdppackage']) ){
			Agdp_Admin_Options::agdp_import_page_html();
			return;
		}
		 
		 
		if( ! empty($_POST['packages_form_submit']) ){
			self::submit_packages_form();
		}
		self::generate_packages( );
		
		echo sprintf('<form id="agdppackages" method="POST">');
		
		foreach( self::get_packageable_post_types() as $post_type ){
			self::generate_form_post_type( $post_type );
		}
		
		
		echo sprintf('<div><input type="submit" name="packages_form_submit" value="%s" class="button button-primary button-large"></div>'
			, 'Générer');
		echo '</form>';
	}
	
	/**
	 * generate_form_post_type
	 */
	private static function generate_form_post_type( $post_type ) {
		
		echo sprintf('<div class="agdppackages-post_type"><h3>%s</h3><ul>'
			, $post_type
		);
		
		$root_posts = [0];
		$post_ids = Agdp_Posts::get_posts_and_descendants( $post_type, 'publish', false, $root_posts, 0);
		$posts = get_posts([ 'include'=>$post_ids, 'post_type'=>$post_type ]);
		foreach($posts as $post){
			$is_package_root = get_post_meta( $post->ID, 'is_package_root', true );
			echo sprintf('<li><label><input type="checkbox" name="is_package_root[]" value="%d" %s>%s</label></li>'
				, $post->ID
				, $is_package_root ? 'checked="checked"' : ''
				, sprintf('<a href="%s" target="_blank">%s</a>'
					, $post->guid
					, htmlentities($post->post_title)
				)
			);
		}
		
		echo '</ul></div>';
	}
	
	/**
	 * submit_packages_form
	 */
	private static function submit_packages_form() {
		
		// echo '<pre>';
		// var_dump( $_POST );
		// echo '</pre>';
		
		$post_types = self::get_packageable_post_types();
		
		$meta_key = 'is_package_root';
		//posts existants avec is_package_root
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
		
		$meta_key = 'is_package_root';
		//posts existants avec is_package_root
		$root_posts = get_posts([ 
			'post_type' => $post_type,
			'meta_key' => $meta_key,
			'meta_value' => '1',
			'fields' => 'ids',
		]);
		if( ! $root_posts )
			return;
		
		echo sprintf('<div class="agdppackages-post_type" post_type="%s"><h3>%s</h3><ul>'
			, $post_type
			, $post_type
		);

		$post_ids = Agdp_Posts::get_posts_and_descendants( $post_type, 'publish', $root_posts);
		$posts = get_posts([ 'include'=>$post_ids, 'post_type'=>$post_type ]);
		$max_update = '';
		foreach($posts as $post){
			// echo sprintf('<li><label>[%d] %s</label></li>'
				// , $post->ID
				// , sprintf('<a href="%s" target="_blank">%s</a>'
					// , $post->guid
					// , htmlentities($post->post_title)
				// )
			// );
			if( $max_update < $post->post_modified_gmt )
				$max_update = $post->post_modified_gmt;
		}
		$file_name = self::get_post_type_package_file( $post_type, $max_update );
		
		echo sprintf('<h4>%s</h4>', $file_name);
		
		$data = Agdp_Admin_Edit_Post_Type::get_posts_export( $posts );
		if( $data ){
			$data = json_encode($data, JSON_OBJECT_AS_ARRAY & JSON_UNESCAPED_SLASHES);
			// $data = serialize( json_decode($data) );//remove object reference
			// $data = serialize( $data );//remove object reference
			if( file_exists($file_name) )
				unlink($file_name);
			file_put_contents( $file_name, $data );
		}
		
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
		
		echo sprintf('<textarea style="width: 100%%;" rows="2">%s</textarea>', $data );

		echo '</ul></div>';
	}

}

?>