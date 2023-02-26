<?php
class AgendaPartage_Admin_User {

	public static function init() {
		//Add custom user contact Methods
		add_filter( 'user_contactmethods', array( __CLASS__, 'custom_user_contact_methods' ));
	}

	// Register User Contact Methods
	public static function custom_user_contact_methods( $user_contact_method ) {

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

}
