<?php
class AgendaPartage_Admin_Multisite {

	public static function init() {
		self::init_hooks();
	}

	public static function init_hooks() {
		// if( WP_NETWORK_ADMIN
		// && array_key_exists('coo-synchronise_to_others_blogs', $_POST)
		// && $_POST['coo-synchronise_to_others_blogs'] ) {
			// add_action( 'save_post_agdpevent', array(__CLASS__, 'synchronise_to_others_blogs'), 20, 3 );
			// add_action( 'save_post_wpcf7_contact_form', array(__CLASS__, 'synchronise_to_others_blogs'), 20, 3 );
		// }
	}

	public static function import_site ( $source_blog_id = null){
		$user = wp_get_current_user();
			
		if( $source_blog_id === null )
			$source_blog_id = BLOG_ID_CURRENT_SITE;
		
		$dest_blog_id = get_current_blog_id();
		
		switch_to_blog($source_blog_id);
			$source_name = get_bloginfo( 'name' );
			$source_url = network_site_url( '/' );
		restore_current_blog();
		
		$dest_name = get_bloginfo( 'name' );
		$dest_url = network_site_url( '/' );
		
		$source_options = get_blog_option( $source_blog_id, AGDP_TAG );
		$dest_options = get_blog_option( $dest_blog_id, AGDP_TAG );
			
		$logs = [];
		$logs[] = sprintf('<p>Importation depuis <b>%s (%d)</b> vers <b>%s (%d)</b></p>', 
				$source_name, $source_blog_id
				, $dest_name, $dest_blog_id);
				
		
		$logs[] = sprintf('<p>Périodicités des lettres-infos</p>');
		AgendaPartage_Post_Types::plugin_activation();
		//TODO "La lettre-info" = default checked
		
		//import agdpevent->taxonomies->terms
		switch_to_blog($source_blog_id);
			$source_taxonomies = get_taxonomies(['object_type' => ['agdpevent']],'names');
		restore_current_blog();
		foreach($source_taxonomies as $tax_name){
			if( $tax_name === 'city')
				continue;
			$terms = get_terms(array(
				'taxonomy' => $tax_name,
				'hide_empty' => false,
			) );
			if( empty($terms)){
				switch_to_blog($source_blog_id);
					$terms = get_terms(array(
								'taxonomy' => $tax_name,
								'hide_empty' => false,
							) );
				restore_current_blog();
				$logs[] = sprintf('<pre>Importation de la taxonomie <b>%s</b></pre>', $tax_name);
				foreach( $terms as $term){
					$logs[] = sprintf('<li><b>%s</b> %s</li>', $term->slug, var_export($term, true));
					$new_term = wp_insert_term($term->name, $tax_name, ['slug' => $term->slug, 'description' => $term->description]);
					switch_to_blog($source_blog_id);
						$term_metas = get_term_meta($term->term_id, '', true);
					restore_current_blog();
					$doublons = '';
					foreach($term_metas as $term_meta => $term_meta_value ){
						if( strpos($doublons, $term_meta . '|') !== false)
							continue;
						$doublons .= $term_meta . '|';
						$error = update_term_meta($new_term['term_id'], $term_meta, implode(',',$term_meta_value));
						if( is_wp_error($error))
							$logs[] = 'Erreur : ' . $error->get_error_message() . '<br>';
					}
				}
			}
		}
		
		$source_post_ids = [];
		foreach([
		  'admin_message_contact_form_id' => 'WPCF7_Contact_Form'
		, 'newsletter_events_register_form_id' => 'WPCF7_Contact_Form'
		, 'newsletter_post_id' => 'agdpnl'
		, 'newsletter_subscribe_page_id' => 'page'
		, 'agdpevent_edit_form_id' => 'WPCF7_Contact_Form'
		, 'contact_page_id' => 'page'
		, 'contact_form_id' => 'WPCF7_Contact_Form'
		, 'agdpevent_message_contact_post_id' => 'page'
		, 'agenda_page_id' => 'page'
		, 'new_agdpevent_page_id' => 'page']
		as $option_name => $post_type){
			$option_label = AgendaPartage::get_option_label($option_name);
			
			if ( ! isset( $source_options[$option_name] ) ) {
				$logs[] = sprintf('<p>Le paramètre <b>%s</b> est vide sur le site source</p>', 
						$option_label);
				continue;
			}
			$source_option_value = $source_options[$option_name];
			
			if ( isset( $dest_options[$option_name] ) && $dest_options[$option_name] ) {
				$existing = get_post($dest_options[$option_name]);
				if( $existing
				&& ! is_wp_error($existing)
				&& $existing->post_status !== 'trash'){
					$source_post_ids[$source_option_value . ''] = $existing->ID;
					$logs[] = sprintf('<p>Paramètre <b>%s</b> (%s) déjà connu : %s</p>', 
						$option_label, $option_name, $dest_options[$option_name]);
					continue;
				}
			}
			
			//Two options may be equals, do not import twice
			if( array_key_exists($source_option_value . '', $source_post_ids) ){
				AgendaPartage::update_option($option_name, $source_post_ids[$source_option_value . '']);
				continue;
			}
		
			switch_to_blog($source_blog_id);
				$source_post = get_post( $source_option_value );
				if( ! $source_post ){
					$logs[] = sprintf('<p>Impossible de retrouver le post source <b>%s</b> (%s) #%d</p>', 
						$option_label, $option_name, $source_option_value);
					
					restore_current_blog();
					break;
				}
				if( $source_post->post_status !== 'publish' ){
					$logs[] = sprintf('<p>Le post source <b>%s</b> (%s) n\'est pas publié (%s), il va être importé et restera dans son état.</p>', 
						$option_label, $option_name, $source_post->post_status);
				}
				
				$tax_terms = [];
				foreach( get_taxonomies([ 'object_type' => [$source_post->post_type] ]) as $tax_name){
					$terms = get_the_terms($source_post->ID, $tax_name);
					if( ! is_array($terms))
						debug_log($tax_name . '$terms ! array', $terms);
					else
						$tax_terms[ $tax_name ] = array_map( function($t){ return $t->term_id; }, $terms);
				}
				
				$source_metas = get_post_meta( $source_option_value, '', true );
				
			restore_current_blog();
			
			$dest_metas = [];
			foreach($source_metas as $meta_key=>$meta_value){
				if( ! str_starts_with($meta_key, '_edit_')
				&&  ! str_starts_with($meta_key, 'agdpnl_mailing_')
				&& count($meta_value) && $meta_value[0] !== null
				){
					$dest_metas[$meta_key] = maybe_unserialize($meta_value[0]);
				}
			}
			$dest_metas['_agdp_multisite_import'] = sprintf('%d|%s|%d', $source_blog_id, $source_post->post_type, $source_post->ID);
			// $logs[] = '<pre>' . var_export($source_metas,true) . '</pre>';
			// $logs[] = '<pre>' . var_export($dest_metas,true) . '</pre>';
			// AgendaPartage_Admin::set_import_report($logs);
			// return;
			
			$postarr = [
				'post_author' => $user->ID,
				'post_type' => $source_post->post_type,
				'post_status' => $source_post->post_status,
				'post_title' => $source_post->post_title,
				'post_name' => $source_post->post_name,
				'post_content' => $source_post->post_content, //TODO replace url
				'meta_input' => $dest_metas,
				'tax_input' => $tax_terms
			];
			
			$dest_post = wp_insert_post($postarr);
			
			if( is_wp_error($dest_post)){
				$logs[] = sprintf('<p>Paramètre <b>%s</b> (%s)</p>', 
					$option_label, $option_name, $dest_post->get_error_message());
				continue;
			}
			$dest_post = get_post($dest_post);
			$logs[] = sprintf('<p>Post créé <b>%s</b> (%s)</p>', 
				$dest_post->post_title, $dest_post->ID);
				
			AgendaPartage::update_option($option_name, $dest_post->ID);
			$source_post_ids[$source_option_value . ''] = $dest_post->ID;
		}
		
		AgendaPartage_Admin::set_import_report($logs);
	}


	public static function get_other_blogs_of_user ($user_id = false){
		// if( ! $user_id )
			// $user_id = get_current_user_id();
		// $current_blog_id = get_current_blog_id();
		// $blogs = get_blogs_of_user($user_id);
		// if(isset($blogs[$current_blog_id]))
			// unset($blogs[$current_blog_id]);

		// if( WP_DEBUG ) { //TODO delete
			// $blogs = array();
			// $blogId= 3;
			// $blogs[$blogId] = new stdClass();
			// $blogs[$blogId]->userblog_id = $blogId;
			// $blogs[$blogId]->blogname = 'AgendaPartage de DEV';
			// $blogs[$blogId]->siteurl = site_url();
			// $blogId++;
			// $blogs[$blogId] = new stdClass();
			// $blogs[$blogId]->userblog_id = $blogId;
			// $blogs[$blogId]->blogname = 'AgendaPartage du Pays de Saint-Félicien';
			// $blogs[$blogId]->siteurl = site_url('pays-de-saint-felicien');
			// $blogId++;
		// }
		// return $blogs;
	}

	public static function synchronise_to_others_blogs ($post_id, $post, $is_update){
		// if( $post->post_status != 'publish'){
			// AgendaPartage_Admin::add_admin_notice("La synchronisation n'a pas été effectuée car la page n'est pas encore publiée.", 'warning');
			// return;
		// }
		// $blogs = self::get_other_blogs_of_user($post->post_author);
		// foreach ($blogs as $blog) {
			// self::synchronise_to_other_blog ($post_id, $post, $is_update, $blog);
		// }
	}

	//TODO
	public static function synchronise_to_other_blog ($post_id, $post, $is_update, $to_blog){
		/* global $wpdb;
		$src_prefix = $wpdb->base_prefix;
		$src_prefix = preg_replace('/_$/', '', $src_prefix);
		$basic_prefix = preg_replace('/_\d*$/', '', $wpdb->base_prefix);
		$dest_prefix = $basic_prefix . ( $to_blog->userblog_id == 1 ? '' : '_' . $to_blog->userblog_id );

		//Find this in other blog 
		$sql = "SELECT dest.ID
				FROM {$dest_prefix}_posts dest
				INNER JOIN {$src_prefix}_posts src
					ON src.post_author = dest.post_author
					AND src.post_status = dest.post_status
					AND src.post_type = dest.post_type
					AND src.post_name = dest.post_name
				WHERE src.ID = {$post_id}
				";

		//AgendaPartage_Admin::add_admin_notice($sql, 'warning');
		$results = $wpdb->get_results($sql);
		//AgendaPartage_Admin::add_admin_notice(print_r($results, true), 'warning');

		if(is_wp_error($results)){
			AgendaPartage_Admin::add_admin_notice("Erreur SQL in synchronise_to_other_blog() : {$message->get_error_messages()}", 'error');
		}
		elseif(count($results) == 0){
			// AgendaPartage_Admin::add_admin_notice("La publication \"{$post->post_title}\" n'a pas d'équivalent dans le blog {$to_blog->blogname}. La synchronisation ne peut pas être faite. L'équivalence porte sur le titre, l'auteur, le statut et le type.\n{$sql}", 'warning');
		}
		elseif(count($results) > 1){
			AgendaPartage_Admin::add_admin_notice("La publication \"{$post->post_title}\" a plusieurs équivalents dans le blog {$to_blog->blogname}. La synchronisation ne peut pas être faite. L'équivalence porte sur le titre, l'auteur, le statut et le type.", 'warning');
		}
		elseif(count($results) == 1){
			//TODO Synchro des images
			$dest_post_id = $results[0]->ID;
			$sql = "UPDATE {$dest_prefix}_posts dest
					JOIN {$src_prefix}_posts src
					ON src.ID = {$post_id}
					AND dest.ID = {$dest_post_id}
					SET dest.post_content = src.post_content,
					dest.post_title = src.post_title,
					dest.post_excerpt = src.post_excerpt 
					";
			$results = $wpdb->get_results($sql);
			if(is_wp_error($results)){
				AgendaPartage_Admin::add_admin_notice("Erreur SQL in synchronise_to_other_blog() : {$message->get_error_messages()}. \n{$sql}", 'error');
			}
			else {
				$sql = "UPDATE {$dest_prefix}_postmeta dest
					JOIN {$src_prefix}_postmeta src
					ON dest.meta_key = src.meta_key
					SET dest.meta_value = src.meta_value
					WHERE src.post_id = {$post_id}
					AND dest.post_id = {$dest_post_id}
					";
				$results = $wpdb->get_results($sql);
				if(is_wp_error($results)){
					AgendaPartage_Admin::add_admin_notice("Erreur SQL in synchronise_to_other_blog() : {$results->get_error_messages()}", 'error');
				}
				else {
					AgendaPartage_Admin::add_admin_notice("Synchronisation de \"{$post->post_title}\" vers le blog {$to_blog->blogname}.", 'success');
				}
			}
		} */
	}
}
