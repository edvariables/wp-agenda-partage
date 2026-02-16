<?php 
/**
 * AgendaPartage Admin -> Contact
 * Custom post type for WordPress in Admin UI.
 * 
 * Capabilities
 * Colonnes de la liste des contacts
 * Dashboard
 *
 * Voir aussi Agdp_Contact
 */
class Agdp_Admin_Contact {

	public static function init() {

		self::init_hooks();
	}

	public static function init_hooks() {
		add_action( 'admin_head', array(__CLASS__, 'init_post_type_supports'), 10, 4 );
		add_filter( 'map_meta_cap', array(__CLASS__, 'map_meta_cap'), 10, 4 );
		
		//add custom columns for list view
		add_filter( 'manage_' . Agdp_Contact::post_type . '_posts_columns', array( __CLASS__, 'manage_columns' ) );
		add_action( 'manage_' . Agdp_Contact::post_type . '_posts_custom_column', array( __CLASS__, 'manage_custom_columns' ), 10, 2 );
		//set custom columns sortable
		add_filter( 'manage_edit-' . Agdp_Contact::post_type . '_sortable_columns', array( __CLASS__, 'manage_sortable_columns' ) );
		if(basename($_SERVER['PHP_SELF']) === 'edit.php'
		&& isset($_GET['post_type']) && $_GET['post_type'] === Agdp_Contact::post_type)
			add_action( 'pre_get_posts', array( __CLASS__, 'on_pre_get_posts'), 10, 1);

		add_action( 'wp_dashboard_setup', array(__CLASS__, 'add_dashboard_widgets'), 10 ); //dashboard
	}
	/****************/
	
	/**
	 * Liste de contacts
	 */
	public static function manage_columns( $columns ) {
		unset( $columns );
		$columns = array(
			'cb'     => __( 'Sélection', AGDP_TAG ),
			'titre'     => __( 'Titre', AGDP_TAG ),
			'horaires'     => __( 'Horaires', AGDP_TAG ),
			'details'     => __( 'Détails', AGDP_TAG ),
			'contact'     => __( 'Contact', AGDP_TAG ),
			'category'      => __( 'Catégorie', AGDP_TAG ),
			'author'        => __( 'Auteur', AGDP_TAG ),
			'date'      => __( 'Date', AGDP_TAG )
		);
		return $columns;
	}
	public static function manage_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'titre' :
				//Evite la confusion avec Agdp_Contact::the_title
				$post = get_post( $post_id );
				echo $post->post_title;
				
				break;
			case 'contact' :
				$contact = get_post_meta( $post_id, 'cont-contact', true );
				$email = get_post_meta( $post_id, 'cont-email', true );
				echo $contact . ($email && $contact ? ' - ' : '' ) . $email;
				
				break;
			case 'horaires' :
				$horaires = get_post_meta( $post_id, 'cont-horaires', true );
				echo $horaires;
				break;
			case 'diffusion' :
				the_terms( $post_id, $column, '<cite class="entry-terms">', ', ', '</cite>' );
				break;
			case 'details' :
				$post = get_post( $post_id );
				$description = $post->post_content;
				if(strlen($description)>20)
						$description = trim(substr($description, 0, 20)) . '...';
				$phone      = get_post_meta( $post_id, 'cont-phone', true );
				$phone_show = get_post_meta( $post_id, 'cont-phone-show', true );
				echo trim(
					  ($description ? $description . ' - ' : '')
					. ($phone && $phone_show ? antispambot($phone) : '')
				);
				break;
			default:
				break;
		}
	}

	/**
	 * manage_sortable_columns
	 */
	public static function manage_sortable_columns( $columns ) {
		$columns['titre']    = 'titre';
		$columns['author']    = 'author';
		$columns['horaires'] = 'horaires';
		$columns['details'] = 'details';
		$columns['contact'] = 'contact';
		return $columns;
	}
	
	/**
	 * Sort custom column
	 */
	public static function on_pre_get_posts( $query ) {
		global $wpdb;
		if(empty($query->query_vars))
			return;
			
		if( ! empty($query->query_vars['orderby']) ){
			switch( $query->query_vars['orderby']) {
				case 'horaires':
					$query->set('meta_key','cont-horaires');  
					$query->set('orderby','meta_value'); 
					break;
				case 'contact':
					$query->set('meta_key','cont-email');  
					$query->set('orderby','meta_value');  
					break;
			}
		}
	}
	/****************/

	/**
	 * N'affiche le bloc Auteur qu'en Archive (liste) / modification rapide
	 * N'affiche l'éditeur que pour le contact modèle ou si l'option Agdp::contact_show_content_editor
	 */
	public static function init_post_type_supports(){
		global $post;
		if( current_user_can('manage_options') ){
			if(is_archive()){
				add_post_type_support( Agdp_Contact::post_type, 'author' );
			}
		}
	}

	/**
	 * map_meta_cap
	 TODO all
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {

		if( 0 ) {
			echo "<br>\n-------------------------------------------------------------------------------";
			print_r(func_get_args());
			/*echo "<br>\n-----------------------------------------------------------------------------";
			print_r($caps);*/
		}
		if($cap == 'edit_contacts'){
			//var_dump($cap, $caps);
					$caps = array();
					//$caps[] = ( current_user_can('manage_options') ) ? 'read' : 'do_not_allow';
					$caps[] = 'read';
			return $caps;
		}
		/* If editing, deleting, or reading an event, get the post and post type object. */
		if ( 'edit_contact' == $cap || 'delete_contact' == $cap || 'read_contact' == $cap ) {
			$post = get_post( $args[0] );
			$post_type = get_post_type_object( $post->post_type );

			/* Set an empty array for the caps. */
			$caps = array();
		}

		/* If editing an event, assign the required capability. */
		if ( 'edit_contact' == $cap ) {
			if ( $user_id == $post->post_author )
				$caps[] = $post_type->cap->edit_posts;
			else
				$caps[] = $post_type->cap->edit_others_posts;
		}

		/* If deleting an event, assign the required capability. */
		elseif ( 'delete_contact' == $cap ) {
			if ( $user_id == $post->post_author )
				$caps[] = $post_type->cap->delete_posts;
			else
				$caps[] = $post_type->cap->delete_others_posts;
		}

		/* If reading a private event, assign the required capability. */
		elseif ( 'read_contact' == $cap ) {

			if ( 'private' != $post->post_status )
				$caps[] = 'read';
			elseif ( $user_id == $post->post_author )
				$caps[] = 'read';
			else
				$caps[] = $post_type->cap->read_private_posts;
		}

		/* Return the capabilities required by the user. */
		return $caps;
	}

	/**
	 * dashboard_widgets
	 */

	/**
	 * Init
	 */
	public static function get_my_contacts($num_posts = 5) {
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
	    $current_user = wp_get_current_user();
		$user_id = $current_user->ID;
		$user_email = $current_user->user_email;
		
		$sql = "SELECT post.ID, post.post_title, post.post_name, post.post_status, post.post_date, post.post_modified"
			. "\n FROM {$blog_prefix}posts post"
			. "\n INNER JOIN {$blog_prefix}postmeta post_email"
			. "\n ON post_email.post_id = post.ID"
			. "\n AND post_email.meta_key = 'cont-email'"
			. "\n INNER JOIN {$blog_prefix}postmeta post_date_debut"
			. "\n ON post_date_debut.post_id = post.ID"
			. "\n AND post_date_debut.meta_key = 'cont-date-debut'"
			. "\n WHERE post.post_type = '".Agdp_Contact::post_type."'"
			. "\n AND post.post_status IN ('publish', 'pending', 'draft')"
			. "\n AND post_date_debut.meta_value >= CURRENT_DATE()"
			. "\n AND post.post_author = {$user_id}"
			. "\n AND ( post.post_author = {$user_id}"
				. "\n OR post_email.meta_value = '{$user_email}')"
			. "\n ORDER BY post.post_modified DESC"
			. "\n LIMIT {$num_posts}"
			;
		$dbresults = $wpdb->get_results($sql);
		if( is_a($dbresults, 'WP_Error') )
			throw $dbresults;
		return $dbresults;
	}
	
	/**
	 * Init
	 */
	public static function add_dashboard_widgets() {
	    global $wp_meta_boxes;
		//TODO : trier par les derniers ajoutés
		//TODO : author OR email
		if( ! current_user_can('moderate_comments') ){
			$contacts = self::get_my_contacts(5);
			if( count($contacts) ) {
				add_meta_box( 'dashboard_my_contacts',
					__('Mes contacts', AGDP_TAG),
					array(__CLASS__, 'dashboard_my_contacts_cb'),
					'dashboard',
					'normal',
					'high',
					array('contacts' => $contacts) );
			}
		}
		
		if(current_user_can('moderate_comments')
		|| current_user_can(Agdp_Contact::post_type)){
		    $contacts = Agdp_Contacts::get_posts( 10, [
				'post_status' => ['publish', 'pending', 'draft']
				, 'orderby' => ['post_modified' => 'DESC']
			]);
			if( count($contacts) ) {
				add_meta_box( 'dashboard_all_contacts',
					__('Les contacts', AGDP_TAG),
					array(__CLASS__, 'dashboard_all_contacts_cb'),
					'dashboard',
					'side',
					'high',
					array('contacts' => $contacts) );
			}
		}
	}

	/**
	 * Callback
	 */
	public static function dashboard_my_blogs_cb($post , $widget) {
		$blogs = $widget['args']['blogs'];
		?><ul><?php
		foreach($blogs as $blog){
			//;
			echo '<li>';
			?><header class="entry-header"><?php 
				echo sprintf('<h3 class="entry-title"><a href="%s/wp-admin"><img src="%s" class="coo-favicon"/>%s</a></h3>', $blog->siteurl, $blog->siteurl . '/favicon.ico', $blog->blogname);
			?></header><?php
			echo '</li>';
			
		}
		?></ul><?php
	}

	/**
	 * Callback
	 */
	public static function dashboard_my_contacts_cb($post , $widget) {
		$contacts = $widget['args']['contacts'];
		$edit_url = current_user_can('manage_options');
		$post_statuses = get_post_statuses();
		?><ul><?php
		foreach($contacts as $contact){
			echo '<li>';
			?><header class="entry-header"><?php 
				if($edit_url)
					$url = get_edit_post_link($contact);
				else
					$url = Agdp_Contact::get_post_permalink($contact);
				echo sprintf( '<h3 class="entry-title"><a href="%s">%s</a></h3>', $url, Agdp_Contact::get_post_title($contact) );
				$the_date = get_the_date('', $contact);
				$the_modified_date = get_the_modified_date('', $contact);
				$html = sprintf('<span>publié le %s</span>', $the_date) ;
				if($the_date != $the_modified_date)
					$html .= sprintf('<span>, mis à jour le %s</span>', $the_modified_date) ;
				if($contact->post_status != 'publish')
					$html .= sprintf('<br><b>%s</b>', Agdp::icon( 'warning',$post_statuses[$contact->post_status])) ;		
				echo sprintf( '<cite>%s</cite>', $html);		
			?></header><?php
			/*?><div class="entry-summary">
				<?php echo get_the_excerpt($contact); //TODO empty !!!!? ?>
			</div><?php */
			echo '<hr></li>';
			
		}
		?></ul><?php
	}

	/**
	 * Callback
	 */
	public static function dashboard_all_contacts_cb($post , $widget) {
		$contacts = $widget['args']['contacts'];
		$today_date = date(get_option( 'date_format' ));
		$max_rows = 4;
		$post_statuses = get_post_statuses();
		?><ul><?php
		foreach($contacts as $contact){
			echo '<li>';
			edit_post_link( Agdp_Contact::get_post_title($contact), '<h3 class="entry-title">', '</h3>', $contact );
			$the_date = get_the_date('', $contact);
			$the_modified_date = get_the_modified_date('', $contact);
			$html = '';
			$html .= sprintf('<span>ajouté le %s</span>', $the_date) ;
			if($the_date != $the_modified_date)
				$html .= sprintf('<span>, mis à jour le %s</span>', $the_modified_date) ;
			if($contact->post_status != 'publish')
				$html .= sprintf('<br><b>%s</b>', Agdp::icon( 'warning', $post_statuses[$contact->post_status])) ;
			
			$meta_key = 'cont-email';
			$value = Agdp_Contact::get_post_meta($contact, $meta_key, true);
			if($value)
				$html .= sprintf(' - %s', $value);
			
			echo sprintf( '<cite>%s</cite>', $html);		
			echo '<hr></li>';

			if( --$max_rows <= 0 && $the_modified_date != $today_date )
				break;
		}
		echo sprintf('<li><a href="%s">%s...</a></li>', get_home_url( null, 'wp-admin/edit.php?post_type=' . Agdp_Contact::post_type), __('Tous les contacts', AGDP_TAG));
		?>
		</ul><?php
	}
	
	/**
	 *
	 *
	 * cf Agdp_Admin::on_wp_ajax_admin_action_cb
	 */
	public static function on_wp_ajax_action_insert_term($data){
		$tax_name = $data['taxonomy'];
		$term = $data['term'];
		$result = wp_insert_term($term, $tax_name
			, array(
				'slug' => sanitize_title($term)
			)
		);
		if( is_wp_error($result) )
			return Agdp::icon('warning', $result->get_error_message());
		return Agdp::icon('info', sprintf('Le terme "%s" a été ajouté.', $tax_name));
	}

}
?>