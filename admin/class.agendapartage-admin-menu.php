<?php 

/**
 * Chargé lors du hook admin_menu qui est avant le admin_init
 */
class AgendaPartage_Admin_Menu {

	static $initialized = false;

	public static function init() {
		if(self::$initialized)
			return;
		self::$initialized = true;
		self::init_includes();
		self::init_hooks();
		self::init_settings();
		self::init_admin_menu();
	}

	public static function init_includes() {	
	}

	public static function init_hooks() {

		//TODO
		// Le hook admin_menu est avant le admin_init
		//add_action( 'admin_menu', array( __CLASS__, 'init_admin_menu' ), 5 ); 
		add_action('wp_dashboard_setup', array(__CLASS__, 'init_dashboard_widgets') );
		
		add_action('admin_head', array(__CLASS__, 'add_form_enctype'));


	}

	public static function init_settings(){
		// register a new setting for "agendapartage" page
		register_setting( AGDP_TAG, AGDP_TAG );

		// register a new section in the "agendapartage" page
		add_settings_section(
			'agendapartage_section_general',
			__( 'En général', AGDP_TAG ),
			array(__CLASS__, 'settings_sections_cb'),
			AGDP_TAG
		);

			// 
			$field_id = 'admin_message_contact_form_id';
			add_settings_field(
				$field_id, 
				__( 'Message de la part de l\'administrateur', AGDP_TAG ),
				array(__CLASS__, 'agendapartage_combos_posts_cb'),
				AGDP_TAG,
				'agendapartage_section_general',
				[
					'label_for' => $field_id,
					'class' => 'agendapartage_row',
					'post_type' => WPCF7_ContactForm::post_type
				]
			);

			// 
			$field_id = 'newsletter_subscribe_page_id';
			add_settings_field(
				$field_id, 
				__( 'Page d\'inscription à la lettre-info', AGDP_TAG ),
				array(__CLASS__, 'agendapartage_combos_posts_cb'),
				AGDP_TAG,
				'agendapartage_section_general',
				[
					'label_for' => $field_id,
					'class' => 'agendapartage_row',
					'post_type' => 'page'
				]
			);

			// 
			$field_id = 'newsletter_events_register_form_id';
			add_settings_field(
				$field_id, 
				__( 'Formulaire d\'inscription à la lettre-info', AGDP_TAG ),
				array(__CLASS__, 'agendapartage_combos_posts_cb'),
				AGDP_TAG,
				'agendapartage_section_general',
				[
					'label_for' => $field_id,
					'class' => 'agendapartage_row',
					'post_type' => WPCF7_ContactForm::post_type
				]
			);

			// 
			$field_id = 'newsletter_post_id';
			add_settings_field(
				$field_id, 
				__( 'Lettre-info à diffuser', AGDP_TAG ),
				array(__CLASS__, 'agendapartage_combos_posts_cb'),
				AGDP_TAG,
				'agendapartage_section_general',
				[
					'label_for' => $field_id,
					'class' => 'agendapartage_row',
					'post_type' => AgendaPartage_Newsletter::post_type
				]
			);

			// 
			$field_id = 'contact_page_id';
			add_settings_field(
				$field_id, 
				__( 'Page "Ecrivez-nous".', AGDP_TAG ),
				array(__CLASS__, 'agendapartage_combos_posts_cb'),
				AGDP_TAG,
				'agendapartage_section_general',
				[
					'label_for' => $field_id,
					'class' => 'agendapartage_row',
					'post_type' => 'page'
				]
			);

			// 
			$field_id = 'contact_form_id';
			add_settings_field(
				$field_id, 
				__( 'Formulaire "Ecrivez-nous"', AGDP_TAG ),
				array(__CLASS__, 'agendapartage_combos_posts_cb'),
				AGDP_TAG,
				'agendapartage_section_general',
				[
					'label_for' => $field_id,
					'class' => 'agendapartage_row',
					'post_type' => WPCF7_ContactForm::post_type
				]
			);

		// register a new section in the "agendapartage" page
		add_settings_section(
			'agendapartage_section_agdpevents',
			__( 'Évènements', AGDP_TAG ),
			array(__CLASS__, 'settings_sections_cb'),
			AGDP_TAG
		);

			// 
			$field_id = 'agenda_page_id';
			add_settings_field(
				$field_id, 
				__( 'Page de l\'agenda', AGDP_TAG ),
				array(__CLASS__, 'agendapartage_combos_posts_cb'),
				AGDP_TAG,
				'agendapartage_section_agdpevents',
				[
					'label_for' => $field_id,
					'class' => 'agendapartage_row',
					'post_type' => 'page'
				]
			);

			// 
			$field_id = 'new_agdpevent_page_id';
			add_settings_field(
				$field_id, 
				AgendaPartage::get_option_label($field_id),
				array(__CLASS__, 'agendapartage_combos_posts_cb'),
				AGDP_TAG,
				'agendapartage_section_agdpevents',
				[
					'label_for' => $field_id,
					'class' => 'agendapartage_row',
					'post_type' => 'page'
				]
			);

			// 
			$field_id = 'agdpevent_edit_form_id';
			add_settings_field(
				$field_id, 
				__( 'Formulaire d\'ajout et modification des évènements', AGDP_TAG ),
				array(__CLASS__, 'agendapartage_combos_posts_cb'),
				AGDP_TAG,
				'agendapartage_section_agdpevents',
				[
					'label_for' => $field_id,
					'class' => 'agendapartage_row',
					'post_type' => WPCF7_ContactForm::post_type
				]
			);

			// 
			$field_id = 'agdpevent_message_contact_post_id';
			add_settings_field(
				$field_id, 
				__( 'Message aux organisateurs dans les pages des évènements', AGDP_TAG ),
				array(__CLASS__, 'agendapartage_combos_posts_cb'),
				AGDP_TAG,
				'agendapartage_section_agdpevents',
				[
					'label_for' => $field_id,
					'class' => 'agendapartage_row',
					'post_type' => WPCF7_ContactForm::post_type
				]
			);

		// register a new section in the "agendapartage" page
		add_settings_section(
			'agendapartage_section_agdpevents_import',
			__( 'Importation d\'évènements', AGDP_TAG ),
			array(__CLASS__, 'settings_sections_cb'),
			AGDP_TAG
		);

			// 
			$field_id = 'agdpevent_import_ics';
			add_settings_field(
				$field_id, 
				__( 'Importation d\'un fichier ICS', AGDP_TAG ),
				array(__CLASS__, 'agendapartage_import_ics_cb'),
				AGDP_TAG,
				'agendapartage_section_agdpevents_import',
				[
					'label_for' => $field_id,
					'class' => 'agendapartage_row',
				]
			);
	}

	/**
	* Modifie la balise <from en ajoutant l'attribut enctype="multipart/form-data".
	* Injecté dans le <head>
	*/
	public static function add_form_enctype() {
		?><script type="text/javascript">
				jQuery(document).ready(function(){
					var $form = jQuery('#wpbody-content form:first');
					var $input = $form.children('input[name="option_page"][value="<?php echo(AGDP_TAG)?>"]:first');
					if($input.length){
						$form.attr('enctype','multipart/form-data');
						$form.attr('encoding', 'multipart/form-data');
					}
				});
			</script>";<?php
	}
	
	/**
	 * Section
	 */
	public static function settings_sections_cb($args ) {
		switch($args['id']){
			case 'agendapartage_section_general' : 
				$message = __('Paramètres réservés aux administrateurs, c\'est à dire à ceux qui savent ce qu\'ils font...', AGDP_TAG);
				break;
			case 'agendapartage_section_agdpevents' : 
				$message = __('Paramètres concernant les évènements et leurs pages.', AGDP_TAG);
				break;
			case 'agendapartage_section_agdpevents_import' : 
				$message = '';//__('Outils d\'importation d\'évènements.', AGDP_TAG);
				break;
			default : 
				$message = '';
		}
		?>
		<p id="<?php echo esc_attr( $args['id'] ); ?>"><?php esc_html_e(  $message, AGDP_TAG ); ?></p>
		<?php
	}
	
	public static function agendapartage_import_ics_cb( $args ){
		// get the value of the setting we've registered with register_setting()
		$option_id = $args['label_for'];
		$post_status = AgendaPartage::get_option($option_id . '-post_status');
		// output the field
		?>
		<input id="<?php echo esc_attr( $option_id ); ?>"
			name="<?php echo AGDP_TAG;?>[<?php echo esc_attr( $option_id ); ?>]"
			type="file"
		/>
		<br/>
		<label>Statut des évènements importés</label>
			<select name="<?php echo AGDP_TAG;?>[<?php echo esc_attr( $option_id ); ?>-post_status]">
			<?php 
				foreach(array("publish"=>"Publié", "pending"=>"En attente de relecture", "draft"=>"Brouillon"/*, "future"=>"Future"*/) as $value=>$name)
					echo sprintf('<option value="%s" %s>%s</option>'
						, $value
						, selected($value, $post_status, false)
						, $name);
			?>
			</select>
		<br>
		<?php /*Avec la case à cocher de confirmation on force un changement de valeur dans les options pour s'assurer de provoquer la mise à jour et passer par le hook update_option. 
		Ca pourrait être n'importe quel autre champ qu'on modifierait mais c'est plus pratique comme ça.*/
		?><label>
			<input id="<?php echo esc_attr( $option_id ); ?>-confirm"
				name="<?php echo AGDP_TAG;?>[<?php echo esc_attr( $option_id ); ?>-confirm]"
				type="checkbox"
			/> Je confirme l'importation de nouveaux
		</label>
		<br>
		<br>
		<div class="dashicons-before dashicons-welcome-learn-more">Les évènements avec le même nom et la même date qu'un évènement déjà existant sont ignorés.</div>
		<div class="dashicons-before dashicons-welcome-learn-more">Les évènements avec une date ancienne sont ignorés.</div>
		<div class="dashicons-before dashicons-welcome-learn-more">Les évènements importés n'ont, à priori, ni catégories ni organisateur ni e-mail associés.</div>
		<?php
		$option_value = AgendaPartage::get_option($option_id);
		echo AgendaPartage_Admin::get_import_report(true);

	}

	/**
	 * Option parmi la liste des posts du site
	 * Attention, les auteurs de ces pages doivent être administrateurs
	 * $args['post_type'] doit être fourni
	 */
	public static function agendapartage_combos_posts_cb( $args ) {
		// get the value of the setting we've registered with register_setting()
		$option_id = $args['label_for'];
		$post_type = $args['post_type'];
		$option_value = AgendaPartage::get_option($option_id);
		if( ! isset( $option_value ) ) $option_value = -1;

		$the_query = new WP_Query( 
			array(
				'nopaging' => true,
				'post_type'=> $post_type
				//'author__in' => self::get_admin_ids(),
			)
		);
		if($the_query->have_posts() ) {
			// output the field
			?>
			<select id="<?php echo esc_attr( $option_id ); ?>"
				name="<?php echo AGDP_TAG;?>[<?php echo esc_attr( $option_id ); ?>]"
			><option/>
			<?php
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$author_level = get_the_author_meta('user_level');
				if($author_level >= USER_LEVEL_ADMIN) { //Admin authors only
					echo sprintf('<option value="%d" %s>%s</option>'
						, get_the_ID()
						, selected( $option_value, get_the_ID(), false )
						, esc_html(__( get_the_title(), AGDP_TAG ))
					);
				}
			}
			echo '</select>';
		}

		if( ! $option_value){
			switch($args['post_type']){
				case WPCF7_ContactForm::post_type :
					?>
					<div class="dashicons-before dashicons-warning">Un formulaire de contact doit être défini !</div>
					<?php
					break;
				default:
					?>
					<div class="dashicons-before dashicons-warning">Une page doit être définie !</div>
					<?php
					break;
			}
		}

		switch($args['label_for']){
			case 'contact_page_id':
				?>
				<div class="dashicons-before dashicons-welcome-learn-more">Depuis la page d'un évènement, le visiteur peut nous écrire à propos de cet évènement.</div>
				<?php
				break;
			case 'admin_message_contact_form_id':
				?>
				<div class="dashicons-before dashicons-welcome-learn-more">Dans les pages des évènements, seuls les administrateurs voient un formulaire d'envoi de message à l'organisateur.</div>
				<?php
				break;
			case 'agdpevent_message_contact_post_id':
				?>
				<div class="dashicons-before dashicons-welcome-learn-more">Dans les formulaires, les adresses emails comme organisateur@<?php echo AGDP_EMAIL_DOMAIN?> ou client@<?php echo AGDP_EMAIL_DOMAIN?> sont remplacées par des valeurs dépendantes du contexte.</div>
				<?php
				break;
		}
	}

	/**
	 * top level menu
	 */
	public static function init_admin_menu() {
		// add top level menu page
		add_menu_page(
			__('Réglages', AGDP_TAG),
			'Agenda Partagé',
			'manage_options',
			AGDP_TAG,
			array(__CLASS__, 'agendapartage_options_page_html'),
			'dashicons-lightbulb',
			35
		);

		if(! current_user_can('manage_options')){

		    $user = wp_get_current_user();
		    $roles = ( array ) $user->roles;
		    if(in_array('agdpevent', $roles)) {
				remove_menu_page('posts');//TODO
				remove_menu_page('wpcf7');
			}
		}
	}

	/**
	* top level menu:
	* callback functions
	*/
	public static function agendapartage_options_page_html() {
		// check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// add error/update messages

		// check if the user have submitted the settings
		// wordpress will add the "settings-updated" $_GET parameter to the url
		if ( isset( $_GET['settings-updated'] ) ) {
			// add settings saved message with the class of "updated"
			add_settings_error( 'agendapartage_messages', 'agendapartage_message', __( 'Réglages enregistrés', AGDP_TAG ), 'updated' );

			
		}

		// show error/update messages
		settings_errors( 'agendapartage_messages' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				// output security fields for the registered setting "agendapartage"
				settings_fields( AGDP_TAG );
				// output setting sections and their fields
				// (sections are registered for "agendapartage", each field is registered to a specific section)
				do_settings_sections( AGDP_TAG );
				// output save settings button
				submit_button( __('Enregistrer', AGDP_TAG) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 *
	 */
	public static function init_dashboard_widgets() {
	    self::remove_dashboard_widgets();
	}

	// TODO parametrage initiale pour chaque utilisateur
	public static function remove_dashboard_widgets() {
	    global $wp_meta_boxes, $current_user;
	    /*var_dump($wp_meta_boxes['dashboard']);*/
		if( ! in_array('administrator',(array)$current_user->roles) ) {
			remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
			remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
		}
		remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
	}
}

?>