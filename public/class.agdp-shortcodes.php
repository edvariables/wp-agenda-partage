<?php

/**
 * AgendaPartage -> Shortcodes (abstract)
 * 
 * Définition des shortcodes 
 *	
 */
class Agdp_Shortcodes {

	const post_type = false; //Must inherit
	const info_shortcodes = false; //Must inherit
	const default_attr = false; //Must inherit
	
	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;

			self::init_hooks();
			
			self::add_shortcodes();
		}
	}

	/**
	 * Hook
	 */
	public static function init_hooks() {
		
		add_action( 'wp_ajax_'.AGDP_TAG.'_shortcode', array(__CLASS__, 'on_wp_ajax_shortcode_cb') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_shortcode', array(__CLASS__, 'on_wp_ajax_shortcode_cb') );
	}

 	/**
 	 * get_post_type_shortcode
 	 */
	public static function get_post_type_shortcode( $return_type = false/* ARRAY_N */){
		if( ! static::post_type )
			return;
		$abstracted_class = Agdp_Post::abstracted_class(static::post_type);
		if( ! $abstracted_class )
			return false;
		$shortcode = $abstracted_class::shortcode;
		
		if( ! $shortcode )
			return;
		
		if( ($return_type !== 'first')
		&& is_array($shortcode) )
			return $shortcode[0];
			
		if( ($return_type !== ARRAY_N)
		|| is_array($shortcode) )
			return $shortcode;
			
		if( $return_type === ARRAY_N )
			return [ $shortcode ];
		
		return $shortcode;
	}
	
 	/**
 	 * add_shortcodes
 	 */
	public static function add_shortcodes(){
		
		$shortcodes = static::get_post_type_shortcode( ARRAY_N );
		
		if( ! $shortcodes )
			return;
		
		//Main
		foreach($shortcodes as $shortcode_u)
			add_shortcode( $shortcode_u, array( static::class, 'shortcodes_callback') );
		
		if( ! static::info_shortcodes )
			return;
		
		foreach( static::info_shortcodes as $info )
			foreach($shortcodes as $shortcode_u){
				// debug_log(__FUNCTION__, 'add_shortcode', "$shortcode_u-$info", static::class );
				add_shortcode( $shortcode_u . '-' . $info, array( static::class, 'shortcodes_callback') );
			}

	}

	/**
	* Sanitize attributes
	*/
	public static function sanitize_attributes($atts){
		
		//Indexed array becomes an associative array
		//Attributes without value becomes attr=true
		foreach($atts as $key=>$value){
			if(is_numeric($key)){
				unset($atts[$key]);
				if( static::default_attr
				&& $key == 0
				&& ! isset($atts[ static::default_attr ]) )
					$atts[static::default_attr] = $value;
				elseif( ! array_key_exists($value, $atts)){
					if( ($i = strpos($value, '=', 1)) > 0 ){
						$key = substr($value, 0, $i);
						$value = substr($value, $i + 1);
						if( strlen($value) > 1 && $value[0] === '"' && $value[strlen($value)-1] === '"' )
							$value = substr($value, 1, strlen($value) - 2);
						$atts[ $key ] = $value;
					}
					else
						$atts[$value] = true;
				}
			}
		}
		return $atts;
	}

	/**
	* Callback des shortcodes
	*/
	public static function shortcodes_callback($atts, $content = '', $shortcode = null){

		if( is_admin() 
		&& ! wp_doing_ajax()
		&& ! Agdp_Newsletter::is_sending_email())
			return;
		
		if( ! is_array($atts)){
			$atts = array( $atts );
		}
		// debug_log(__CLASS__.'::'.__FUNCTION__, static::get_post_type_shortcode(), $shortcode, $atts);
		
		$atts = static::sanitize_attributes($atts);
		
		// debug_log(__CLASS__.'::'.__FUNCTION__, 'after sanitize_attributes', $atts);
		
		if(array_key_exists('toggle-ajax', $atts)){
			$atts['toggle'] = $atts['toggle-ajax'];
			$atts['ajax'] = true;
			unset($atts['toggle-ajax']);
		}
		
		$key = 'ajax';
		if( array_key_exists($key, $atts) ){
			$ajax = $atts[$key] ? $atts[$key] : true;
			if( $ajax === "false" || $ajax === "0" )
				$ajax = false;
			unset($atts[$key]);
		}
		else {
			$ajax = false;
		}
		// Si attribut toggle [report toggle="Contactez-nous !"]
		// Fait un appel récursif si il y a l'attribut "ajax"
		// TODO Sauf shortcode conditionnel
		if( array_key_exists('toggle', $atts) ){
			
			$shortcode_atts = '';
			foreach($atts as $key=>$value){
				if($key == 'toggle'){
					$title = array_key_exists('title', $atts) && $atts['title'] 
						? $atts['title']
						: ( $atts['toggle'] 
							? $atts['toggle'] 
							: __($shortcode, AGDP_TAG)) ;
				}
				elseif( ! is_numeric($key) ){
					if(is_numeric($value))
						$shortcode_atts .= sprintf('%s=%s ', $key, $value);
					else
						$shortcode_atts .= sprintf('%s="%s" ', $key, esc_attr($value));
				}
			}
			
			//Inner
			$html = sprintf('[%s %s]%s[/%s]', $shortcode, $shortcode_atts , $content, $shortcode);
			// debug_log( __FUNCTION__, 'SHORTCODE', $html);
			if( ! $ajax){
				$html = do_shortcode($html);
			}
			else{
				$ajax = sprintf('ajax="%s"', esc_attr($ajax));
				$html = esc_attr(str_replace('"', '\\"', $html));
			}
			//toggle
			//Bugg du toggle qui supprime des éléments
			$guid = uniqid(AGDP_TAG);
			$toogler = do_shortcode(sprintf('[toggle title="%s" %s]%s[/toggle]'
				, esc_attr($title)
				, $ajax 
				, $guid
			));
			return str_replace($guid, $html, $toogler);
		}
		
		//info_shortcodes
		if( ! isset( $atts['info'] )
		&& strpos( $shortcode, '-' )
		&& ! in_array( $shortcode, static::get_post_type_shortcode( ARRAY_N ) ) ){
			foreach( static::get_post_type_shortcode( ARRAY_N ) as $root ){
				if( strpos( $shortcode, $root . '-' ) === 0 ){
					$atts['info'] = substr( $shortcode , strlen($root . '-') );
					$shortcode = $root;
					break;
				}
			}
		}
		
		return static::do_shortcode($atts, $content, $shortcode);
	}
	
	/** 
	 * do_shortcode
	 */
	protected static function do_shortcode($atts, $content = '', $shortcode = null){
		debug_log_callstack( __CLASS__ . '::' . __FUNCTION__ , "should not be called - " . static::class . ' must inherit' );
		return $content;
	}
	
	/**
	 * Hook wp_ajax_shortcode
	 */
	public static function on_wp_ajax_shortcode_cb() {
		$ajax_response = '';
		$data = $_POST['data'];
		if($data){ 
			if( is_string( $data ) )
				$data  = str_replace('\\"', '"', wp_specialchars_decode( $data ));
			
			$ajax_response = do_shortcode( $data );
			
		}
		// Make your array as json
		wp_send_json($ajax_response);
	 
		// Don't forget to stop execution afterward.
		wp_die();
	}
	
	/**
	 * Extracts post_id from a string post_id|post_path
	 */
	public static function get_post( $str_post_id, $post_type = false, $relative_to = false ) {
		if( is_numeric($str_post_id) )
			return get_post($str_post_id);
		if( strpos($str_post_id, '|' ) ){
			$str_post_id = substr( $str_post_id, 0, strpos($str_post_id, '|' ) );
			if( is_numeric($str_post_id) )
				return get_post($str_post_id);
		}
		$str_post_id = trim( $str_post_id, '"\'' );
		// debug_log(__FUNCTION__, 'get_relative_page', $str_post_id, $relative_to, $post_type , get_relative_page( $str_post_id, $relative_to, $post_type ));
		return get_relative_page( $str_post_id, $relative_to, $post_type );
	}
 	
	/**
	 * build_shortcode
	 */
 	public static function build_shortcode($post, $attributes = false){
		$shortcode = sprintf('[%s %d|%s %s]'
					, static::get_post_type_shortcode( 'first' )
					, $post->ID
					, get_post_path($post, '/')
					, $attributes
				);
		return $shortcode;
	}
}
