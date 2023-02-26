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
