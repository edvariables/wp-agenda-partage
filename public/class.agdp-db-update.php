<?php
/*
 * see Agdp::update_db
 */
class Agdp_DB_Update {
	
	/**
	*/
	public static function update_db_1_2_25(){
		if(Agdp::get_option('agdpforum_subscribe_form_id'))
			return true;
		if( ! Agdp::get_option('newsletter_subscribe_form_id')){
			debug_log(__FUNCTION__, 'Impossible de trouver le modèle');
			return true;
		}
		
		//Création d'un formulaire wpcf7 sur la base du newsletter_subscribe_form_id
		$form = WPCF7_ContactForm::get_instance(Agdp::get_option('newsletter_subscribe_form_id'));
		add_filter('wpcf7_copy', [__CLASS__, 'update_db_1_2_25_wpcf7_copy_agdpforum_generic'], 10, 2);
		$form->copy();
		remove_filter('wpcf7_copy', [__CLASS__, 'update_db_1_2_25_wpcf7_copy_agdpforum_generic'], 10, 2);
		return FALSE;
	}
	public static function update_db_1_2_25_wpcf7_copy_agdpforum_generic( $copy, $src_form ){
		$copy->set_title( 'Abonnement générique à la lettre-info d\'un forum' );
		$properties = $copy->get_properties();
		//debug_log(__FUNCTION__, $properties);
		$properties['form'] = "<label> Votre adresse e-mail
    [email* nl-email autocomplete:email] </label>

<label class=\"next-radio-br\"> Votre abonnement à \"{{titre}}\"</label>[radio nl-period-agdpforum-generic default:2 use_label_element \"(no_change)\" \"Aucun abonnement|none\" \"Tous les jours|d\" \"Toutes les semaines|w\" \"Tous les quinze jours|2w\" \"Tous les mois|m\"]

<div class=\"moderator-user-only\">
    <label> Rôle en tant que membre</label>[select forum-subscription-agdpforum-generic default:4 use_label_element  \"(non membre)|\" \"Membre|subscriber\" \"Modérateurice|moderator\" \"Administrateurice|administrator\" \"Banni-e|banned\"]
</div>

[checkbox nl-send_newsletter-now-agdpforum-generic use_label_element \"Recevoir maintenant les messages\"]


<div class=\"nl-user-fields\">
<table>
<tr><td colspan=\"2\"><label>Se connecter pour ajouter plus facilement les messages </label>[checkbox nl-create-user use_label_element \"Créer un compte|1\"]</td></tr>
<tr><td><label for=\"nl-user-name\">Pour le compte, votre nom</label></td><td>[text nl-user-name size:20]</td></tr>
<tr><td><label for=\"nl-user-city\">Votre commune</label></td><td>[text nl-user-city size:20]</td></tr>
</table>
</div>

[response]
[submit \"Valider\"]";
		$copy->set_properties($properties);
	
		$form_id = $copy->save();
		
		Agdp::update_option('agdpforum_subscribe_form_id', $form_id);
	}
	
	
	/**
	*/
	public static function update_db_1_2_8(){
		if(Agdp::get_option('newsletter_diffusion_term_id')){
			Agdp::update_option('agdpevents_nl_diffusion_term_id', Agdp::get_option('newsletter_diffusion_term_id'));
			Agdp::update_option('newsletter_diffusion_term_id', null);
		}
		if( ! Agdp::get_option('covoiturages_nl_diffusion_term_id')){
			Agdp::update_option('covoiturages_nl_diffusion_term_id', -1);
		}
		
		return true;
	}

	/**
	*/
	public static function update_db_1_2_5(){
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		$sqls = [];
		$sqls["clear-forums"] = "DELETE FROM {$blog_prefix}postmeta WHERE meta_key = 'agdpmailbox' AND meta_value=''";
		foreach($sqls as $name => $sql){
			$result = $wpdb->query($sql);
		
			if( $result === false ){
				debug_log('update_db '.$name.' ERROR ', $sql);
			}
			else
				debug_log('update_db '.$name.' OK : ' . $result);
		}
		
		return true;
	}

	/**
	*/
	public static function update_db_1_1_1(){
		
		if( ! Agdp::get_option('events_nl_post_id')){
			Agdp::update_option('events_nl_post_id', Agdp::get_option('newsletter_post_id'));
			Agdp::update_option('newsletter_post_id', null);
		}
		
		if( ! Agdp::get_option('newsletter_subscribe_form_id')){
			Agdp::update_option('newsletter_subscribe_form_id', Agdp::get_option('newsletter_events_register_form_id'));
			Agdp::update_option('newsletter_events_register_form_id', null);
		}
		
		return true;
	}

	/**
	*/
	public static function update_db_1_0_23(){
		
		if( ! Agdp::get_option('agdpevent_message_contact_form_id')){
			Agdp::update_option('agdpevent_message_contact_form_id', Agdp::get_option('agdpevent_message_contact_post_id'));
			Agdp::update_option('agdpevent_message_contact_post_id', null);
		}
		
		return true;
	}
	
	/**
	*/
	public static function update_db_1_0_22(){
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		$sqls = [];
		$sqls["taxonomy='ev_diffusion'"] = "UPDATE {$blog_prefix}term_taxonomy SET taxonomy='ev_diffusion' WHERE taxonomy = 'publication'";
		$sqls["taxonomy='ev_city'"] = "UPDATE {$blog_prefix}term_taxonomy SET taxonomy='ev_city' WHERE taxonomy = 'city'";
		$sqls["taxonomy='ev_category'"] = "UPDATE {$blog_prefix}term_taxonomy SET taxonomy='ev_category' WHERE taxonomy = 'type_agdpevent'";
		$sqls["postmeta.meta_key ='ev-diffusion'"] = "UPDATE {$blog_prefix}postmeta SET meta_key='ev-diffusion' WHERE meta_key = 'ev-publication'";
		foreach($sqls as $name => $sql){
			$result = $wpdb->query($sql);
		
			if( $result === false ){
				debug_log('update_db '.$name.' ERROR ', $sql);
			}
			else
				debug_log('update_db '.$name.' OK : ' . $result);
		}
		
		if(Agdp::get_option('agdpevent_tax_publication_newsletter_term_id')){
			Agdp::update_option('agdpevents_nl_diffusion_term_id', Agdp::get_option('agdpevent_tax_publication_newsletter_term_id'));
			Agdp::update_option('agdpevent_tax_publication_newsletter_term_id', null);
		}
		
		$post_id = Agdp::get_option('agdpevent_edit_form_id');
		$post = get_post($post_id);
		$post->post_content = str_replace('publication', 'diffusion', $post->post_content);
		$post->post_content = str_replace('Publication', 'Diffusion', $post->post_content);
		$result = wp_update_post([
			'ID' => $post->ID,
			'post_content' => $post->post_content
		]);
		$post_meta = get_post_meta($post_id, '_form', true);
		if( is_array($post_meta) )
			$post_meta = $post_meta[0];
		$post_meta = str_replace('publication', 'diffusion', $post_meta);
		$post_meta = str_replace('Publication', 'Diffusion', $post_meta);
		update_post_meta($post_id, '_form', $post_meta);
		
		debug_log('update_db agdpevent_edit_form_id : ', $result);
		
		return true;
	}
}
