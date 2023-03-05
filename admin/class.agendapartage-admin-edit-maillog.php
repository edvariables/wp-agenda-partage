<?php

/**
 * AgendaPartage Admin -> Edit -> Maillog
 * Custom post type for WordPress in Admin UI.
 * 
 * Edition d'une trace mail
 * Définition des metaboxes et des champs personnalisés des Traces mail 
 *
 * Voir aussi AgendaPartage_Maillog, AgendaPartage_Admin_Maillog
 */
class AgendaPartage_Admin_Edit_Maillog extends AgendaPartage_Admin_Edit_Post_Type {

	public static function init() {
		parent::init();

		self::init_hooks();
	}
	
	public static function init_hooks() {

		add_action( 'add_meta_boxes_' . AgendaPartage_Maillog::post_type, array( __CLASS__, 'register_agdpmaillog_metaboxes' ), 10, 1 ); //edit

		if(basename($_SERVER['PHP_SELF']) === 'post.php'
		&& array_key_exists('post_type', $_POST)
		&& $_POST['post_type'] == AgendaPartage_Maillog::post_type)
			add_action( 'save_post_' . AgendaPartage_Maillog::post_type, array(__CLASS__, 'save_post_agdpmaillog_cb'), 10, 3 );

	}
	/****************/
	
	/**
	 * Register Meta Boxes (boite en édition de l'trace mail)
	 */
	public static function register_agdpmaillog_metaboxes($post){
				
		add_meta_box('agdp_agdpmaillog-details', __('Détails', AGDP_TAG), array(__CLASS__, 'metabox_callback'), AgendaPartage_Maillog::post_type, 'normal', 'high');
	}

	/**
	 * Callback
	 */
	public static function metabox_callback($post, $metabox){
		//var_dump(func_get_args());
		
		switch ($metabox['id']) {
			
			case 'agdp_agdpmaillog-details':
				parent::metabox_html( self::get_metabox_details_fields(), $post, $metabox );
				break;
			
			default:
				break;
		}
	}
	
	public static function get_metabox_all_fields(){
		return get_metabox_details_fields();
	}
	
	/**
	 *
	 */
	public static function get_metabox_details_fields(){
		$post = get_post();
		$meta_input = get_post_meta($post->ID, '', true);
		
		?><table id="agdp-agdpmaillog" class="form-table" role="presentation">
		<?php
		
		if(isset($meta_input['error'])){
			?><tr><th><h3 class="error">Erreur</h3></th>
				<td><pre><code><?=implode("\r\n", $meta_input['error'])?></code></pre></td>
			</tr><?php
		}
		
		foreach($meta_input as $meta_name => $meta_value){
			$meta_value = implode("\r\n", $meta_value);
			?><tr><th><label><?=$meta_name?></label></th>
				<td><?php
					echo sprintf('<pre><code>%s</code></pre>', htmlentities( $meta_value));
				?></td>
			</tr><?php
		}
		?></table><?php
	
		return [];
	}
	
	/**
	 * Callback lors de l'enregistrement du post.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function save_post_agdpmaillog_cb ($agdpmaillog_id, $agdpmaillog, $is_update){
		if( $agdpmaillog->post_status == 'trashed' ){
			return;
		}
		
		// if( ! array_key_exists('send-nl-test', $_POST)
		// || ! $_POST['send-nl-test']
		// || ! array_key_exists('send-nl-test-email', $_POST))
			// return;
		
		// $email = sanitize_email($_POST['send-nl-test-email']);
		// if( ! is_email($email)){
			// AgendaPartage_Admin::add_admin_notice("Il manque l'adresse e-mail pour le test d'envoi.", 'error');
			// return;
		// }
	}
	
}
?>