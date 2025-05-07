<?php
class Agdp_Admin_Multisite {

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
			$source_description = get_bloginfo( 'description' );
			$source_url = get_bloginfo( 'url' );
		restore_current_blog();
		
		$dest_name = get_bloginfo( 'name' );
		$dest_description = get_bloginfo( 'description' );
		$dest_url = get_bloginfo( 'url' );
		
		$source_options = get_blog_option( $source_blog_id, AGDP_TAG );
		$dest_options = get_blog_option( $dest_blog_id, AGDP_TAG );
			
		$logs = [];
		$logs[] = sprintf('<p>Importation depuis <b>%s #%d</b> vers <b>%s #%d</b></p>', 
				$source_name, $source_blog_id
				, $dest_name, $dest_blog_id);
				
		
		$logs[] = sprintf('<p>Périodicités des lettres-infos</p>');
		Agdp_Post_Types::plugin_activation();
		//TODO "La lettre-info" = default checked
		
		//import agdpevent->taxonomies->terms
		switch_to_blog($source_blog_id);
			$source_taxonomies = get_taxonomies(['object_type' => ['agdpevent']],'names');
		restore_current_blog();
		foreach($source_taxonomies as $tax_name){
			if( $tax_name === Agdp_Evenement::taxonomy_city)
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
					switch_to_blog($source_blog_id);
						$term_metas = get_term_meta($term->term_id, '', true);
					restore_current_blog();
					
					if( $tax_name === Agdp_Evenement::taxonomy_diffusion ){
						
						//create only "default_checked" terms
						$meta_name = 'default_checked';
						if( empty($term_metas[$meta_name]) ){
							continue;
						}
					}
					
					$logs[] = sprintf('<li><b>%s</b></li>', $term->slug);//, var_export($term, true));
					$new_term = wp_insert_term($term->name, $tax_name, ['slug' => $term->slug, 'description' => $term->description]);
					$doublons = '';
					foreach($term_metas as $term_meta => $term_meta_value ){
						if( strpos($doublons, $term_meta . '|') !== false)
							continue;
						$doublons .= $term_meta . '|';
						$term_meta_value = maybe_unserialize($term_meta_value[0]);
						if( is_array($term_meta_value))
							$term_meta_value = implode(',', $term_meta_value);//TODO
						$error = update_term_meta($new_term['term_id'], $term_meta, $term_meta_value);
						if( is_wp_error($error)){
							$logs[] = 'Erreur : ' . $error->get_error_message() . '<br>';
						}
					}
					
					if( $tax_name === Agdp_Evenement::taxonomy_diffusion
					&& ! empty($source_options[Agdp_Evenements::newsletter_diffusion_term_id])
					&& $term->term_id == $source_options[Agdp_Evenements::newsletter_diffusion_term_id]){
						Agdp::update_option(Agdp_Evenements::newsletter_diffusion_term_id, $new_term['term_id']);
					};
					
					if( $tax_name === Agdp_Covoiturage::taxonomy_diffusion
					&& ! empty($source_options[Agdp_Covoiturages::newsletter_diffusion_term_id])
					&& $term->term_id == $source_options[Agdp_Covoiturages::newsletter_diffusion_term_id]){
						Agdp::update_option(Agdp_Covoiturages::newsletter_diffusion_term_id, $new_term['term_id']);
					};
				}
			}
		}
		
		$source_post_ids = [];
		foreach([
		  'admin_message_contact_form_id' => 'WPCF7_Contact_Form'
		, 'newsletter_subscribe_form_id' => 'WPCF7_Contact_Form'
		, 'agdpforum_subscribe_form_id' => 'WPCF7_Contact_Form'
		, 'events_nl_post_id' => 'agdpnl'
		, 'newsletter_subscribe_page_id' => 'page'
		, 'agdpevent_edit_form_id' => 'WPCF7_Contact_Form'
		, 'contact_page_id' => 'page'
		, 'contact_form_id' => 'WPCF7_Contact_Form'
		, 'agdpevent_message_contact_form_id' => 'page'
		, 'agenda_page_id' => 'page'
		, 'new_agdpevent_page_id' => 'page'
		, 'blog_presentation_page_id' => 'page'
		, 'covoiturage_edit_form_id' => 'WPCF7_Contact_Form'
		, 'new_covoiturage_page_id' => 'page'
		, 'covoiturages_page_id' => 'page'
		]
		as $option_name => $post_type){
			$option_label = Agdp::get_option_label($option_name);
			
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
				Agdp::update_option($option_name, $source_post_ids[$source_option_value . '']);
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
					//TODO count($meta_value) > 1
					$meta_value = maybe_unserialize($meta_value[0]);
					
					//Replace url in value
					if( is_string($meta_value )
					&& strpos($meta_value, $source_url) !== false){
						if( $meta_value && is_string($meta_value ))
							$meta_value = str_replace($source_url, $dest_url, $meta_value );
						elseif( is_array($meta_value))
							array_walk_recursive($meta_value, function(&$meta_value, $meta_key) use($source_url, $dest_url){
								if( $meta_value && is_string($meta_value ))
									$meta_value = str_replace($source_url, $dest_url, $meta_value );
							});
					}
			
					$dest_metas[$meta_key] = $meta_value;
				}
			}
			$dest_metas['_agdp_multisite_import'] = sprintf('%d|%s|%d', $source_blog_id, $source_post->post_type, $source_post->ID);
			
			$postarr = [
				'post_author' => $user->ID,
				'post_type' => $source_post->post_type,
				'post_status' => $source_post->post_status,
				'post_title' => $source_post->post_title,
				'post_name' => $source_post->post_name,
				'post_content' => $source_post->post_content, 
				'meta_input' => $dest_metas,
				'tax_input' => $tax_terms
			];
			
			//Replace blog name
			switch($option_name){
				case 'blog_presentation_page_id':
					$postarr['post_title'] = $dest_name;
					//slug, try to replace with slugged name
					$source_slug = sanitize_title($source_post->post_title);
					$dest_slug = sanitize_title($dest_name);
					$postarr['post_name'] = str_replace($source_slug, $dest_slug, $postarr['post_name']);
					
					break;
			}
			//Replace url in content
			$postarr['post_content'] = str_replace($source_url, $dest_url, $postarr['post_content'] );
			
			// $logs[] = '<pre>' . var_export($source_metas,true) . '</pre>';
			// $logs[] = '<pre>' . $source_url . ' >> ' . $dest_url . '</pre>';
			// $logs[] = '<pre>' . var_export($postarr,true) . '</pre>';
			// Agdp_Admin::set_import_report($logs);
			// return;
			
			$dest_post = wp_insert_post($postarr);
			
			if( is_wp_error($dest_post)){
				$logs[] = sprintf('<p>Paramètre <b>%s</b> (%s)</p>', 
					$option_label, $option_name, $dest_post->get_error_message());
				continue;
			}
			$dest_post = get_post($dest_post);
			$logs[] = Agdp::icon('plus', sprintf('Post créé <b>%s</b> (%s)', 
				$dest_post->post_title, $dest_post->ID),'', 'p');
				
			Agdp::update_option($option_name, $dest_post->ID);
			$source_post_ids[$source_option_value . ''] = $dest_post->ID;
		}
		
		Agdp_Admin::set_import_report($logs);
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
			// Agdp_Admin::add_admin_notice("La synchronisation n'a pas été effectuée car la page n'est pas encore publiée.", 'warning');
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

		//Agdp_Admin::add_admin_notice($sql, 'warning');
		$results = $wpdb->get_results($sql);
		//Agdp_Admin::add_admin_notice(print_r($results, true), 'warning');

		if(is_wp_error($results)){
			Agdp_Admin::add_admin_notice("Erreur SQL in synchronise_to_other_blog() : {$message->get_error_messages()}", 'error');
		}
		elseif(count($results) == 0){
			// Agdp_Admin::add_admin_notice("Le post \"{$post->post_title}\" n'a pas d'équivalent dans le blog {$to_blog->blogname}. La synchronisation ne peut pas être faite. L'équivalence porte sur le titre, l'auteur, le statut et le type.\n{$sql}", 'warning');
		}
		elseif(count($results) > 1){
			Agdp_Admin::add_admin_notice("Le post \"{$post->post_title}\" a plusieurs équivalents dans le blog {$to_blog->blogname}. La synchronisation ne peut pas être faite. L'équivalence porte sur le titre, l'auteur, le statut et le type.", 'warning');
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
				Agdp_Admin::add_admin_notice("Erreur SQL in synchronise_to_other_blog() : {$message->get_error_messages()}. \n{$sql}", 'error');
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
					Agdp_Admin::add_admin_notice("Erreur SQL in synchronise_to_other_blog() : {$results->get_error_messages()}", 'error');
				}
				else {
					Agdp_Admin::add_admin_notice("Synchronisation de \"{$post->post_title}\" vers le blog {$to_blog->blogname}.", 'success');
				}
			}
		} */
	}
}
