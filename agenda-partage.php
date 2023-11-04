<?php 
/**
 * @package AgendaPartage
 */
/*
 * Plugin Name: Agenda Partagé
 * Plugin URI: https://github.com/edvariables/wp-agenda-partage
 * Text Domain: agenda-partage
 * Description: Agenda partagé tout intégré et facile pour les visiteurs et rédacteurs d'évènements.
 Inclus l'envoi de lettres-info contenant la liste des évènements à venir.
 Visitiable et testable : https://agendapartage.fr
 Only in french language...
 - Plugins obligatoires :
	- WP Contact Form 7
- Plugins conseillés
	- Akismet Anti-Spam
	- ReCaptcha v2 for Contact Form 7
	- WP Mail Smtp - SMTP7
 * Author: Emmanuel Durand
 * Author URI: https://agendapartage.fr
 * Tags: 
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Version: 1.1.0
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'AGDP_VERSION', '1.1.1' );
define( 'AGDP_MINIMUM_WP_VERSION', '5.0' );

define( 'AGDP_PLUGIN', __FILE__ );
define( 'AGDP_PLUGIN_BASENAME', plugin_basename( AGDP_PLUGIN ) );
define( 'AGDP_PLUGIN_NAME', trim( str_replace ( '-', '', dirname( AGDP_PLUGIN_BASENAME ) ), '/' ) );
define( 'AGDP_PLUGIN_DIR', untrailingslashit( dirname( AGDP_PLUGIN ) ) );
define( 'AGDP_PLUGIN_MODULES_DIR', AGDP_PLUGIN_DIR . '/modules' );

define( 'AGDP_TAG', strtolower(AGDP_PLUGIN_NAME) ); //agendapartage
define( 'AGDP_EMAIL_DOMAIN', AGDP_TAG . '.net' ); //agendapartage.net //TODO sic
define( 'AGDP_MAILLOG_ENABLE', 'maillog_enable');
define( 'AGDP_DEBUGLOG_ENABLE', 'debuglog_enable');
			
//argument de requête pour modification d'évènement. code généré par AgendaPartage::get_secret_code()
define( 'AGDP_EVENT_SECRETCODE', 'codesecret' ); 
define( 'AGDP_COVOIT_SECRETCODE', 'covsecret' ); 
define( 'AGDP_ARG_EVENTID', 'eventid' ); 
define( 'AGDP_ARG_NEWSLETTERID', 'agdpnlid' ); 
define( 'AGDP_ARG_COVOITURAGEID', 'covoitid' ); 
define( 'AGDP_EMAIL4PHONE', 'email4phone' ); 

// see translate_level_to_role()
define( 'USER_LEVEL_ADMIN', 8 ); 
define( 'USER_LEVEL_EDITOR', 5 ); 
define( 'USER_LEVEL_AUTHOR', 2 ); 
define( 'USER_LEVEL_CONTRIBUTOR', 1 ); 
define( 'USER_LEVEL_SUBSCRIBER', 0 ); 
define( 'USER_LEVEL_NONE', 0 ); 

require_once( AGDP_PLUGIN_DIR . '/includes/functions.php' );
require_once( AGDP_PLUGIN_DIR . '/public/class.agendapartage.php' );

//plugin_activation
register_activation_hook( __FILE__, array( 'AgendaPartage', 'plugin_activation' ) );
//plugin_deactivation
register_deactivation_hook( __FILE__, array( 'AgendaPartage', 'plugin_deactivation' ) );

add_action( 'admin_menu', 'agendapartage_admin_menu' );
function agendapartage_admin_menu(){
	require_once( AGDP_PLUGIN_DIR . '/admin/class.agendapartage-admin-menu.php' );
	AgendaPartage_Admin_Menu::init();
}

add_action( 'init', array( 'AgendaPartage', 'init' ) );
add_action( 'admin_init', array( 'AgendaPartage', 'admin_init' ) );

