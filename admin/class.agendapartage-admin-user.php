<?php
class AgendaPartage_Admin_User {

	public static function init() {
		//Add custom user contact Methods
		add_filter( 'user_contactmethods', array( __CLASS__, 'custom_user_contact_methods' ), 10, 2);
		
		if( ! is_network_admin()
		&& (basename($_SERVER['PHP_SELF']) === 'profile.php' 
		|| basename($_SERVER['PHP_SELF']) === 'user-edit.php')) {
			add_action( 'show_user_profile', array(__CLASS__, 'on_custom_user_profil'), 10, 1 );
			add_action( 'edit_user_profile', array(__CLASS__, 'on_custom_user_profil'), 10, 1 );
			add_action( 'insert_custom_user_meta', array(__CLASS__, 'on_insert_custom_user_meta'), 10, 4);
		
		}
		add_action( 'wp_pre_insert_user_data', array(__CLASS__, 'on_wp_pre_insert_user_data'), 10, 4);
		
	}
	
	/**
	 * Surveillance avant création de nouvel utilisateur.
	 * Lutte anti-hack
	 * - wpcore interdit
	 * - Les administrateurs créés ne peuvent pas avoir d'url associée
	 */
	public static function on_wp_pre_insert_user_data($data, $update, $user_id, $userdata){
		$excluded = array('wpcore', 'wp-blog', 'wp-user');
		if( in_array($data['user_nicename'],$excluded) || (isset($data['user_login']) && in_array($data['user_login'], $excluded))){
			if(empty($data['user_activation_key'])){
				debug_log('on_wp_pre_insert_user_data : == bastard',$data, $update, $user_id, $userdata);
				return false;
			}
			$data['user_login'] .= '@bastard';
			$data['user_email'] = 'bastard.'.$data['user_email'];
			$data['display_name'] = 'bastard';
		}
		if($update)
			return $data;
		//Les administrateurs créés ne peuvent pas avoir d'url associée
		if($userdata['role'] === 'administrator' && ! empty($userdata['user_url'])){
			debug_log('on_wp_pre_insert_user_data : administrator + user_url = bastard');
			return false;
		}
		return $data;
	}
	
	
	/**
	 * Mise à jour des paramètres spécifiques de l'utilisateur
	 */
	public static function on_insert_custom_user_meta($custom_meta, $user, $update, $userdata){
		foreach(AgendaPartage_Newsletter::get_newsletters_names() as $newsletter_id =>  $newsletter_name){
			$meta_key = AgendaPartage_Newsletter::get_subscription_meta_key($newsletter_id);
			if( array_key_exists($meta_key, $_POST))
				$custom_meta[$meta_key] = $_POST[$meta_key];
		}
		
		
		$pages = AgendaPartage_Mailbox::get_pages_dispatch();
		foreach( $pages as $page_id => $dispatches ){
			$meta_key = AgendaPartage_Forum::get_subscription_meta_key($page_id);
			if( array_key_exists($meta_key, $_POST))
				$custom_meta[$meta_key] = $_POST[$meta_key];
		}
		
		return $custom_meta;
	}
	
	/**
	 * A l'affichage de l'édition d'un utilisateur, ajoute des champs spécifiques
	 */
	public static function on_custom_user_profil( $profile_user ) {
		self::newsletters_subscriptions( $profile_user );
		self::forums_subscriptions( $profile_user );
	}
	
	/**
	 * Inscriptions aux newsletters
	 */
	public static function forums_subscriptions( $profile_user ) {
		$user_histories = [];
		?><br><h2>Abonnements aux forums</h2>

		<table class="form-table" role="presentation"><?php
			$pages = AgendaPartage_Mailbox::get_pages_dispatch();
			
			$subscription_roles = [
				'' => '(non défini)',
				'administrator' => 'Administrateurice',
				'moderator' => 'Modérateurice',
				'subscriber' => 'Abonné-e',
				'banned' => 'Banni-e',
			];
			$post_statuses = [
				'publish' => 'publié',
				'pending' => 'en attente de modération',
				'draft' => 'brouillon',
			];
				
			foreach( $pages as $page_id => $dispatches ){
				$rigths = $dispatches[0]['rights'];
				$page = get_post($page_id);
				$user_subscription = AgendaPartage_Forum::get_subscription($profile_user->user_email, $page_id);
				$meta_key = AgendaPartage_Forum::get_subscription_meta_key($page_id);
				$post_status = AgendaPartage_Forum::get_forum_post_status($page, $profile_user, $profile_user->user_email);
				if(isset($post_statuses[$post_status]))
					$post_status = $post_statuses[$post_status];
				?><tr class="agdp-forum-subscription">
					<th><label>Page "<?php echo htmlentities($page->post_title)?>"</label>
					<br><?php echo sprintf('<a href="/wp-admin/post.php?post=%s&action=edit">modifier</a>', $page_id)?></label>
					&nbsp;<?php echo sprintf('<a href="%s">voir</a>', get_permalink($page_id))?></label>
					</th>
					<td>
						Règles : <code><?php echo AgendaPartage_Mailbox::get_right_label($rigths); ?></code>
						<br>Nouvelle publication : <code><?php echo $post_status; ?></code>
						<br><select name="<?php echo $meta_key?>">
						<?php 
							foreach( $subscription_roles as $subscribe_code => $label){
								$selected = $user_subscription == $subscribe_code ? 'selected="selected"' : '';
								echo sprintf('<option value="%s" %s>%s</label></option>'
									, $subscribe_code, $selected, $label);
							}
						?>
						</select>
					</td>
				</tr><?php
			}?>
		</table><?php
	}
	
	/**
	 * Inscriptions aux newsletters
	 */
	public static function newsletters_subscriptions( $profile_user ) {
		$user_histories = [];
		?><br><h2>Abonnements aux lettres-infos de l'Agenda partagé</h2>

		<table class="form-table" role="presentation"><?php
			$active_newsletters = AgendaPartage_Newsletter::get_active_newsletters();
			foreach(AgendaPartage_Newsletter::get_newsletters_names() as $newsletter_id =>  $newsletter_name){
				$subscription_periods = AgendaPartage_Newsletter::subscription_periods($newsletter_id);		
				$user_subscription = AgendaPartage_Newsletter::get_subscription($profile_user->user_email, $newsletter_id);
				$user_history = AgendaPartage_Newsletter::get_user_mailings($profile_user->user_email, $newsletter_id);
				$user_histories = array_merge($user_histories, $user_history);
				$meta_key = AgendaPartage_Newsletter::get_subscription_meta_key($newsletter_id);
				?><tr class="agdp-newsletter-subscription">
					<th><label>Lettre-info "<?=htmlentities($newsletter_name)?>"</label><?php 
						if( empty($active_newsletters[$newsletter_id]))
							echo '<br>' . AgendaPartage::icon('info', 'inactive');
					?></th>
					<td><ul>
						<?php 
							foreach( $subscription_periods as $subscribe_code => $label){
								$selected = $user_subscription == $subscribe_code ? 'checked' : '';
								echo sprintf('<li><label><input type="radio" name="%s" value="%s" %s>%s</label></li>'
									, $meta_key, $subscribe_code, $selected, $label);
							}
						?></ul>
					</td>
				</tr><?php
			}
			if( count($user_histories) ){?>
			<tr id="agdp-newsletter-history">
				<th><label>Derniers envois</label></th>
				<td><ul>
					<?php 
						$newsletters_names = AgendaPartage_Newsletter::get_newsletters_names();
						foreach( $user_histories as $key => $mailing_date){
							//debug_log('$key = ' . $key);
							$newsletter = [];
							if(is_numeric($key)){
								$newsletter_id = (int)$key;
								$newsletter_name = $newsletters_names[$newsletter_id];	
							}else{
								preg_match_all('/^([^\|]+)\|(.+)$/', $key, $newsletter);
								$newsletter_id = $newsletter[1][0];
								$newsletter_name = $newsletter[2][0];
							}
							$ajax_data = [
								'ID' => $profile_user->ID,
								'nl_id' => $newsletter_id
							];
							echo sprintf('<li><label>%s le %s</label>  %s</li>'
								, $newsletter_name
								, wp_date('d/m/Y', strtotime($mailing_date))
								, AgendaPartage::get_ajax_action_link($profile_user, 'remove_mailing', 'trash', ' ', 'Cliquez ici pour supprimer cet historique', false, $ajax_data)
							); //TODO h:i:s
						}
					?></ul>
				</td>
			</tr><?php
			}?>
		</table><?php
	}

	// Register User Contact Methods
	public static function custom_user_contact_methods( $user_contact_method, $user ) {

/*		$user_contact_method['email3'] = __( 'Autre email', AGDP_TAG );

		$user_contact_method['tel'] = __( 'Téléphone', AGDP_TAG );
		$user_contact_method['tel2'] = __( 'Autre téléphone', AGDP_TAG );

		$user_contact_method['facebook'] = __( 'Compte Facebook', AGDP_TAG );
		$user_contact_method['twitter'] = __( 'Compte Twitter', AGDP_TAG );

		$user_contact_method['address'] = __( 'Adresse', AGDP_TAG );
		$user_contact_method['address2'] = __( 'Adresse (suite)', AGDP_TAG );
		$user_contact_method['city'] = __( 'Code postal et commune', AGDP_TAG );
*/
		return $user_contact_method;

	}
	
	/**
	 * Exécution ajax
	 */
	public static function user_action_remove_mailing($data){
		$meta_key = AgendaPartage_Newsletter::get_mailing_meta_key($_POST['data']['nl_id']);
		if(delete_user_meta($_POST['user_id'], $meta_key))
			return "js:jQuery(this).parents('li:first').remove();";
		return "Echec de suppression de l'information.";
	}

}
