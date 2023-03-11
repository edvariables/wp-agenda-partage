<?php 
/**
 * @package AgendaPartage
 */
/*
 * Plugin Name: Agenda Partagé
 * Plugin URI: https://github.com/edvariables/wp-agenda-partage
 * Description: Agenda partagé.
 Only in french language...
 * Author: Emmanuel Durand
 * Author URI: https://agendapartage.fr
 * Tags: 
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Version: 1.0.17
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'AGDP_VERSION', '1.0.14' );
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
define( 'AGDP_SECRETCODE', 'codesecret' ); 
define( 'AGDP_ARG_EVENTID', 'eventid' ); 

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

