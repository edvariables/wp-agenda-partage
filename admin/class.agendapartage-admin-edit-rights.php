<?php 

/**
 * Edition des droits de publication
 */
class AgendaPartage_Admin_Edit_Rights {

	const TAG = AGDP_TAG . '-rights';

	static $initialized = false;

	public static function init() {
		if(self::$initialized)
			return;
		self::$initialized = true;
		self::init_settings();
	}

	/**
	 * Initialise les sections de la page
	 */
	public static function init_settings(){
		// register a new setting for "agendapartage-rights" page
		register_setting( self::TAG, self::TAG );
		
		$section_args = array(
			'before_section' => '<div class="agdp-tabs-wrap">',
			'after_section' => '</div>'
		);
		
		//Cette section permet d'englober les onglets suivants. Sans ça, pas d'onglets. Idem dans class.agendapartage-admin-menu.php
		add_settings_section(
			'agendapartage_section_checkup',
			'',
			array(__CLASS__, 'check_module_health'),
			self::TAG, $section_args
		);
		
		$publish_all_rights = AgendaPartage_Forum::get_all_rights_labels();
		$pages = AgendaPartage_Mailbox::get_pages_dispatch();
		
		foreach( $pages as $page_id => $dispatches ){
			$dispatch = $dispatches[0];
			$email = $dispatch['email'];
			$email_esc = str_replace('@', '_', $email);
			if( is_numeric($page_id) )
				$page = get_post($page_id);
			else
				$page = $page_id;
			add_settings_section(
				'page_section_' . $page_id,
				$page->post_title ,
				array(__CLASS__, 'settings_sections_cb'),
				self::TAG,
				// $section_args
				array_merge($section_args, [
					'page' => $page,
					'email' => $email,
					'dispatches' => $dispatches
				])
			);

				// 
				$field_id = 'page-publish-mode_' . $page_id;
				if( ! $dispatch['rights'] )
					$dispatch['rights'] = 'P';
				add_settings_field(
					$field_id,
					'Mode de publication',
					array(__CLASS__, 'form_input_cb'),
					self::TAG,
					'page_section_' . $page_id,
					[
						'label_for' => $field_id,
						'class' => 'agendapartage_row',
						'input_type' => 'radio',
						'values' => $publish_all_rights,
						'value' =>  $dispatch['rights']
					]
				);
		}
		
/* 		$all_dispatches = AgendaPartage_Mailbox::get_emails_dispatch($mailbox_id);
		foreach( $all_dispatches as $email => $dispatch ){
			$email_esc = str_replace('@', '_', $email);
			add_settings_section(
				'email_section_' . $email_esc,
				$email ,
				array(__CLASS__, 'settings_sections_cb'),
				self::TAG,
				// $section_args
				array_merge($section_args, [
					'dispatch' => $dispatch
				])
			);

				// 
				$field_id = 'publish-mode_' . $email_esc;
				if( ! $dispatch['rights'] )
					$dispatch['rights'] = 'P';
				add_settings_field(
					$field_id,
					'Mode de publication',
					array(__CLASS__, 'form_input_cb'),
					self::TAG,
					'email_section_' . $email_esc,
					[
						'label_for' => $field_id,
						'class' => 'agendapartage_row',
						'input_type' => 'radio',
						'values' => $publish_all_rights,
						'value' =>  $dispatch['rights']
					]
				);
		} */
	}

	/**
	 * Option parmi la liste des posts du site
	 * Attention, les auteurs de ces pages doivent être administrateurs
	 * $args['post_type'] doit être fourni
	 */
	public static function form_input_cb( $args ) {
		if( empty($args['label_for']) )
			$value = $option_id = false;
		else {
			$option_id = $args['label_for'];
			$value = $args['value'];
		}
		$input_type = empty($args['input_type']) ? false : $args['input_type'];
		
		if($input_type === 'checkbox'){
			echo sprintf('<label><input id="%s" name="%s[%s]" type="%s" class="%s" %s> %s</label>'
				, esc_attr( $option_id )
				, AGDP_TAG, esc_attr( $option_id )
				, $input_type
				, esc_attr( $args['class'] )
				, $value ? 'checked' : ''
				, $args['label']
			);
		} elseif($input_type === 'radio'){
			$values = $args['values'];
			foreach( $args['values'] as $key => $item )
				echo sprintf('<label><input name="%s[%s]" type="%s" class="%s" %s> %s</label><br>'
					, AGDP_TAG, esc_attr( $option_id )
					, $input_type
					, esc_attr( $args['class'] )
					, $value == $key ? 'checked' : ''
					, $item
				);
		} elseif($input_type) {
			echo sprintf('<input id="%s" name="%s[%s]" type="%s" class="%s" placeholder="%s" value="%s">'
				, esc_attr( $option_id )
				, AGDP_TAG, esc_attr( $option_id )
				, $input_type
				, $args['class']
				, isset( $args['placeholder'] ) ? esc_attr( $args['placeholder'] ) : ''
				, esc_attr( $value )
				
			);
		} elseif( ! empty($args['label'])){
			echo sprintf('<label class="%s"> %s</label>'
				, empty($args['class']) ? '' : esc_attr( $args['class'] )
				, $args['label']
			);
		}
		if(isset($args['learn-more'])){
			if($input_type) echo '<br><br>';
			if( ! is_array($args['learn-more']))
				$args['learn-more'] = [$args['learn-more']];
			foreach($args['learn-more'] as $learn_more){
				?><div class="dashicons-before dashicons-welcome-learn-more"><?=$learn_more?></div><?php
			}
		}
	}

	/**
	 * Section
	 */
	public static function settings_sections_cb( $args ) {
		$message = '';
		if( strpos($args['id'], 'email_section_') === 0){
			if( $args['dispatch']['type'] === 'page' ){
				$page = get_post($args['dispatch']['id']);
				$message = sprintf('<a href="%s">%s</a>', get_permalink($page), $page->post_title);
			}
			if( $args['dispatch']['mailbox'] ){
				$mailbox = get_post($args['dispatch']['mailbox']);
				if($message)
					$message .= ', ';
				$message .= sprintf('par la boîte e-mails <a href="/wp-admin/post.php?post=%s&action=edit">%s</a>', $mailbox->ID, $mailbox->post_title);
			}
		}
		elseif( strpos($args['id'], 'page_section_') === 0){
			$page = false;
			$message = '';
			if( is_a($args['page'], 'WP_POST') ){
				$page = $args['page'];
			}
			elseif( $args['page'] === AgendaPartage_Evenement::post_type ){
				$page = get_post(AgendaPartage::get_option('agenda_page_id'));
			}
			elseif( $args['page'] === AgendaPartage_Covoiturage::post_type ){
				$page = get_post(AgendaPartage::get_option('covoiturages_page_id'));
			}
			if( $page )
				$message = sprintf('<a href="%s">%s</a>', get_permalink($page), $page->post_title);
			
			$mailboxes = [];
			foreach( $args['dispatches'] as $dispatch ){
				$mailbox = get_post($dispatch['mailbox']);
				if($message)
					$message .= ', ';
				if( isset($mailboxes[$dispatch['mailbox'].'']) )
					$message .= 'ou ';
				$email = $dispatch['email'];
				if( $email === '*@*'){
					$meta_key = 'imap_email';
					$mailbox_email = get_post_meta($mailbox->ID, $meta_key, true);
					$email = sprintf('toutes les autres adresses dans %s', $mailbox_email);
				}
				$message .= sprintf('par l\'e-mail <a href="/wp-admin/post.php?post=%s&action=edit">%s</a>', $mailbox->ID, $email);
				
				if( $mailbox->post_status != 'publish' )
					$message .= ' (non publié)';
				$mailboxes[$dispatch['mailbox'].''] = $mailbox;
				
			}
		}
		
		if( ! empty($message) ){
			echo sprintf('<p id="%s">%s</p>', esc_attr( $args['id'] ), $message);
		}
	}
	/**
	 *
	 */
	public static function check_module_health() {
		
		$source_options = AgendaPartage::get_option();
	    $logs = [];
		
		if( count($logs) ){
			AgendaPartage_Admin::add_admin_notice($logs, 'error', true);
		}
	}
	/**
	* top level menu:
	* callback functions
	*/
	public static function agendapartage_rights_page_html() {
		// check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			?><h1>Désolé, l'accès à cette page est réservée.</h1><?php
			return;
		}

		?><h1>Attention, cette page n'est pas opérationnelle et ne doit pas être utilisée.</h1><?php
			
		// add error/update messages

		// check if the user have submitted the settings
		// wordpress will add the "settings-updated" $_GET parameter to the url
		if ( isset( $_GET['settings-updated'] ) ) {
			// add settings saved message with the class of "updated"
			add_settings_error( 'agendapartage_messages', 'agendapartage_message', __( 'Droits enregistrés', AGDP_TAG ), 'updated' );
			
		}

		// show error/update messages
		settings_errors( 'agendapartage_messages' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				// output security fields for the registered setting "agendapartage"
				settings_fields( self::TAG );
				// output setting sections and their fields
				// (sections are registered for "agendapartage", each field is registered to a specific section)
				do_settings_sections( self::TAG );
				// output save settings button
				submit_button( __('Enregistrer', AGDP_TAG) );
				?>
			</form>
		</div>
		<?php
	}
}

?>