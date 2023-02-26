<?php
class AgendaPartage_User {

	public static function init() {
		
	}

	public static function create_user_for_agdpevent($email = false, $user_name = false, $user_login = false, $data = false){

		if( ! $email){
			$post = get_post();
			$email = get_post_meta($post->ID, 'ev-email', true);
		}
		
		$user_id = email_exists( $email );
		if($user_id){
			return self::promote_user_to_blog(new WP_User( $user_id ));
		}

		if(!$user_login) {
			$user_login = sanitize_key( $user_name ? $user_name : $email );
		}
		if(!$user_id && $user_login) {
			$i = 2;
			while(username_exists( $user_login)){
				$user_login .= $i++;
			}
		}

		// Generate the password and create the user
		$password = wp_generate_password( 12, false );
		$user_id = wp_create_user( $user_login ? $user_login : $email, $password, $email );

		if( is_wp_error($user_id) ){
			return $user_id;
		}

		if( ! is_array($data))
			$data = array();
		$data = array_merge($data, 
			array(
				'ID'				=>	$user_id,
				'nickname'			=>	$user_name ? $user_name : ($user_login ? $user_login : $email),
				'first_name'			=>	$user_name ? $user_name : ($user_login ? $user_login : $email),
				'display_name'			=>	$user_name ? $user_name : ($user_login ? $user_login : $email)

			)
		);

		wp_update_user($data);

		// Set the role
		$user = new WP_User( $user_id );
		if($user) {
			$user->set_role( AgendaPartage_Evenement::user_role );
			/*if($user->Errors){

			}
			else {
				// Email the user
				//wp_mail( $email_address, 'Welcome!', 'Your Password: ' . $password );
			}*/		
		}
		
		return self::promote_user_to_blog($user);
	}

	private static function promote_user_to_blog( WP_User $user, $blog = false ){
		if( ! $blog )
			$blog_id = get_current_blog_id();
		elseif(is_object($blog))//TODO
			$blog_id = $blog->ID;
		else //TODO
			$blog_id = $blog;

		//copie from wp-admin/user-new.php ligne 64
		// Adding an existing user to this blog.
		if ( ! array_key_exists( $blog_id, get_blogs_of_user( $user->ID ) ) ) {

			if( current_user_can( 'promote_user', $user->ID )  ){
				$result = add_existing_user_to_blog(
					array(
						'user_id' => $user->ID,
						'role'    => AgendaPartage_Evenement::user_role,
					)
				);
				if(is_wp_error($result)){
					AgendaPartage_Admin::add_admin_notice( sprintf(__("L'utilisateur %s n'a pas accès à ce site web pour la raison suivante : %s", AGDP_TAG), $user->display_name, $result->get_error_message()), 'error');
				}
				else {
					AgendaPartage_Admin::add_admin_notice( sprintf(__("Désormais, l'utilisateur %s a accès à ce site web en tant qu'organisateur d'évènements.", AGDP_TAG), $user->display_name), 'success');
				}
			}
			else{
				AgendaPartage_Admin::add_admin_notice( sprintf(__("L'utilisateur %s n'a pas accès à ce site web et vous n'avez pas l'autorisation de le lui accorder. Contactez un administrateur de niveau supérieur.", AGDP_TAG), $user->display_name), 'warning');
			}
		}
		return $user;
	}

	//TODO
	public static function get_blog_admin_id(){
		$email = get_bloginfo('admin_email');
		return null;
	}

	/**
	 * Retourne un blog auquel appartient l'utilisateur et en priorité le blog en cours
	 */
	public static function get_current_or_default_blog_id($user){
		$blog_id = get_current_blog_id();
		if($user){
			$blogs = get_blogs_of_user($user->ID);
			if( ! array_key_exists($blog_id, $blogs))
				foreach($blogs as $blog){
					$blog_id = $blog->userblog_id;
					break;
				}
		}
		return $blog_id;
	}

}
