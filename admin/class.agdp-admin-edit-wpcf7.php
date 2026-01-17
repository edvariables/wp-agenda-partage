<?php

/**
 * AgendaPartage Admin -> Edit -> WP Contact Form 7
 * 
 * Edition d'un Contact Form du plugin wpcf7
 */
class Agdp_Admin_Edit_WPCF7 {

	const post_type = 'wpcf7';
	
	public static function init() {

		self::init_hooks();
	}
	
	public static function init_hooks() {

		if(class_exists('WPCF7_ContactForm')){
			add_action( 'wpcf7_save_contact_form', array( __CLASS__, 'on_wpcf7_save_contact_form' ), 10, 3 ); //update
			
			foreach( Agdp_Post::get_taxonomies() as $tax_name => $taxonomy){
				//'saved_term' appends before 'saved_<taxonomy>' and it's not good : see class.agdp-admin-edit-location.php
				add_action( 'saved_'.$tax_name, array( __CLASS__, 'on_saved_term_linked_to_wpcf7' ), 20, 5 ); //update
			}
			add_action( 'deleted_term_taxonomy', array( __CLASS__, 'on_deleted_term_linked_to_wpcf7' ), 10, 1 ); //update
			
			add_action( 'wpcf7_admin_footer', array(__CLASS__, 'on_wpcf7_admin_footer'), 10, 1 );
		}

	}
	/****************/
	
	
	/*********************
	 * init_wpcf7_form_html
	 */
	/**
	 * Filtre le html avant enregistrement d'un formulaire wpcf7.
	 */
	public static function on_wpcf7_save_contact_form( $contact_form, $args, $context ){
		
		$meta_key = 'agdp_wpcf7_usage';
		if( empty($args[$meta_key]) )
			delete_post_meta( $contact_form->id(), $meta_key );
		else
			update_post_meta( $contact_form->id(), $meta_key, $args[$meta_key] );
		
		$html = false;
		switch($args['id']){
			case Agdp::get_option('newsletter_subscribe_form_id'):
			case Agdp::get_option('agdpforum_subscribe_form_id'):
				$html = Agdp_Newsletter::init_wpcf7_form_html( $args['form'] );
				break;
			case Agdp::get_option('agdpevent_edit_form_id'):
				$html = Agdp_Event::init_wpcf7_form_html( $args['form'] );
				break;
			case Agdp::get_option('covoiturage_edit_form_id'):
				$html = Agdp_Covoiturage::init_wpcf7_form_html( $args['form'] );
				break;
		}
		if( $html )
			$contact_form->set_properties( ['form' => $html] );
	}
	/**
	 * Met à jour le html d'un formulaire wpcf7 suite à l'ajout d'un terme.
	 */
	public static function on_saved_term_linked_to_wpcf7( $term_id, $tt_id, $update, $args ){
		if( $args && ! empty($args['post_type']) )
			$post_type = $args['post_type'];
		elseif( $args && ! empty($args['taxonomy']) ){
			if( ! ($taxonomy = get_taxonomy( $args['taxonomy'] )))
				return;
			$post_type = $taxonomy->object_type;
		}
		else
			throw new Exception('tax inconnue');
		self::init_wpcf7_form_html( $post_type );
	}
	/**
	 * Met à jour le html d'un formulaire wpcf7 suite à la suppression d'un terme.
	 */
	public static function on_deleted_term_linked_to_wpcf7( $tt_id ){
		if( ! ($term = get_term( $tt_id ) )) return;
		if( ! ($taxonomy = get_taxonomy( $term->taxonomy ))) return;
		self::init_wpcf7_form_html( $taxonomy->object_type );
	}
	/**
	 * Met à jour le html d'un formulaire wpcf7.
	 */
	public static function init_wpcf7_form_html( $post_type ){
		if( is_array($post_type) )
			$post_type = $post_type[0];
		switch( $post_type ){
			//TODO what's up with agdpforum_subscribe_form_id ?
			
			case Agdp_Newsletter::post_type :
				$option_form_id = 'newsletter_subscribe_form_id';
				$function = 'Agdp_Newsletter::init_wpcf7_form_html';
				break;
			case Agdp_Event::post_type :
				$option_form_id = 'agdpevent_edit_form_id';
				$function = 'Agdp_Event::init_wpcf7_form_html';
				break;
			case Agdp_Covoiturage::post_type :
				$option_form_id = 'covoiturage_edit_form_id';
				$function = 'Agdp_Covoiturage::init_wpcf7_form_html';
				break;
			default:
				return;
		}
		
		$form_id = Agdp::get_option($option_form_id);
		$html = get_post_meta($form_id, '_form', true);
		$html = $function( $html );
		if( $html )
			update_post_meta($form_id, '_form', $html);
	}
	/*********************/
	
	/**
	 * on_wpcf7_admin_footer
	 *
	 */
	public static function on_wpcf7_admin_footer( $post ){
		$meta_key = 'agdp_wpcf7_usage';
		$agdp_wpcf7_usage = get_post_meta( $post->id(), $meta_key, true );
		
		$readonly = false;
			
		$usages = [
			'' => "(par défaut)",
			'agdpforum' => "Formulaire de forum",
			'agdpevent_comment' => "Commentaire d'évènement",
		];
		
		$options_usages = [
			  'agdpevent_edit_form_id'
			, 'admin_message_contact_form_id'
			, 'agdpevent_message_contact_form_id'
			, 'contact_form_id'
			, 'newsletter_subscribe_form_id'
			, 'agdpforum_subscribe_form_id'
			, 'covoiturage_edit_form_id'
		];
		
		$label = false;
		foreach($options_usages as $option){
			if( $post->id() == Agdp::get_option($option)){
				$label = sprintf('%s', Agdp::get_option_label($option) );
				$agdp_wpcf7_usage = AGDP_TAG;
				$readonly = true;
				$usages = [ AGDP_TAG => [
					'label' => esc_attr($label),
					'selected' => true,
				]];
				break;
			}
		}
		if( ! $label 
		&& isset( $usages[$agdp_wpcf7_usage] ) ){
			if( is_string($usages[$agdp_wpcf7_usage]) ){
				$label = $usages[$agdp_wpcf7_usage];
				$usages[$agdp_wpcf7_usage] = [
					'label' => esc_attr($label),
					'selected' => true,
				];
			}
			else
				$usages[$agdp_wpcf7_usage]['selected'] = true;
		}
		
		$usages = str_replace('"', '\"', json_encode( $usages ));
		
		$comments = [
			'agdpevent_comment' => [ 'email' => 'commentaire@evenement.agdp' ]
		];
		
		?><script> if( $ === undefined ) var $ = jQuery;
		$("form#wpcf7-admin-form-element").ready(function(e){
			var $form = $(this);
			var $div = $('<div id="titlediv">'
			+ '	<div id="titlewrap"><label>Usage de ce formulaire dans l\'Agenda partagé</label></div>'
			+ '	<div class="agdp_wpcf7_usage inside wp-ui-highlight">'
			+ '	</div>'
			+ '</div>');
			
			var usages = JSON.parse( "<?php echo $usages; ?>" );
			
			var readonly = <?php echo $readonly ? 1 : 0; ?>;
			for( var key in usages ){
				var label = typeof usages[key] === 'object'
						? ( usages[key]['label']
							? usages[key]['label']
							: key )
						: usages[key];
				var selected = typeof usages[key] === 'object'
						? ( usages[key]['selected']
							? usages[key]['selected']
							: false )
						: false;
				$div.find('.inside').append(
					'<label><input type="radio" name="agdp_wpcf7_usage"'
					+ ( readonly ? ' readonly' : '' )
					+ ( selected ? ' checked=checked' : '' )
					+ ' value="' + key + '">' + label + '</label>'
				);
			}
			$div.appendTo( $form.find("#post-body-content > #titlediv") );
			
			//helpers
			//TODO
			$div.on('change', 'input', function(e){
				var $mail_recipient = $('#wpcf7-mail-recipient');
				$mail_recipient.nextAll('.agdp_wpcf7_usage').remove();
				
				switch( this.value ){
					case 'agdpevent_comment' :
						$('#wpcf7-mail-recipient')
							.after('<div class="agdp-message agdp_wpcf7_usage">'
								+ '<label class="dashicons-before dashicons-welcome-learn-more">'
									+ 'Utilisez <code><?php echo $comments['agdpevent_comment']['email'];?></code></label>');
						break;
				}
			});
			$div.find('input[checked]:first').trigger('change');
			
		});
		</script><?php
	 }
}
?>