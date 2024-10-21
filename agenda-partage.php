<?php 
/**
 * @package AgendaPartage
 */
/*
 * Plugin Name: Agenda Partagé
 * Plugin URI: https://github.com/edvariables/wp-agenda-partage
 * Text Domain: agenda-partage
 * Description: Agenda partagé tout intégré et facile pour les visiteurs et rédacteurs d'évènements.
 Un module de covoiturage a été ajouté.
 Inclus l'envoi de lettres-info contenant la liste des évènements ou covoiturages à venir.
 Inclus la réception de mails pour enrichir les commentaires d'une page selon des droits paramétrables (public, validation par email, utilisateur connecté, adhésion au forum de la page)
 Visitable et testable : https://agendapartage.fr
 Only in french language...
 - Plugins obligatoires :
	- WP Contact Form 7
- Plugins conseillés
	- Akismet Anti-Spam
	- ReCaptcha v2 for Contact Form 7
	- WP Mail Smtp - SMTP7
 * Author: Emmanuel Durand, edid@free.fr
 * Author URI: https://agenda-partage.fr
 * Tags: 
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Version: 1.2.24
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'AGDP_VERSION', '1.2.24' );
define( 'AGDP_MINIMUM_WP_VERSION', '5.0' );

define( 'AGDP_PLUGIN', __FILE__ );
define( 'AGDP_PLUGIN_BASENAME', plugin_basename( AGDP_PLUGIN ) );
define( 'AGDP_PLUGIN_NAME', trim( str_replace ( '-', '', dirname( AGDP_PLUGIN_BASENAME ) ), '/' ) );
define( 'AGDP_PLUGIN_DIR', untrailingslashit( dirname( AGDP_PLUGIN ) ) );
define( 'AGDP_PLUGIN_MODULES_DIR', AGDP_PLUGIN_DIR . '/modules' );

define( 'AGDP_TAG', strtolower(AGDP_PLUGIN_NAME) ); //agendapartage
define( 'AGDP_EMAIL_DOMAIN', AGDP_PLUGIN_NAME . '.replace' ); //replace.agendapartage.net //Sert à ce que les valeurs fournies par WPCF7 soient remplacées
define( 'AGDP_MAILLOG_ENABLE', 'maillog_enable');
define( 'AGDP_DEBUGLOG_ENABLE', 'debuglog_enable');
define( 'AGDP_CONNECT_MENU_ENABLE', 'connect_menu_enable');
			
//argument de requête pour modification d'évènement. code généré par Agdp::get_secret_code()
define( 'AGDP_EVENT_SECRETCODE', 'codesecret' ); 
define( 'AGDP_COVOIT_SECRETCODE', 'covsecret' ); 
define( 'AGDP_COMMENT_SECRETCODE', 'codesecret' ); 
define( 'AGDP_ARG_EVENTID', 'eventid' ); 
define( 'AGDP_ARG_NEWSLETTERID', 'agdpnlid' ); 
define( 'AGDP_ARG_COVOITURAGEID', 'covoitid' ); 
define( 'AGDP_ARG_COMMENTID', 'commentid' ); 
define( 'AGDP_EMAIL4PHONE', 'email4phone' ); 
define( 'AGDP_PAGE_META_FORUM', 'agdpforum' ); 
define( 'AGDP_PAGE_META_MAILBOX', 'agdpmailbox' ); 
define( 'AGDP_FORUM_META_PAGE', 'comments-page' ); 
define( 'AGDP_IMPORT_UID', 'post-import-uid' ); 
define( 'AGDP_IMPORT_REFUSED', 'post-import-refused' ); 
define( 'AGDP_FORUM_GENERIC', 'agdpforum-generic' ); 

define( 'AGDP_WPCF7_RADIO_NO_CHANGE', '(no_change)');
define( 'AGDP_JS_SKIP_FIELD', '_js_skip_field');

define( 'AGDP_IMG_MAX_WIDTH', 400);
define( 'AGDP_IMG_MAX_HEIGHT', 400);

define( 'AGDP_SUBJECT_CANCELED', '[ANNULATION]');
define( 'AGDP_SUBJECT_UPDATED', '[MISE A JOUR]');

// see translate_level_to_role()
define( 'USER_LEVEL_ADMIN', 8 ); 
define( 'USER_LEVEL_EDITOR', 5 ); 
define( 'USER_LEVEL_AUTHOR', 2 ); 
define( 'USER_LEVEL_CONTRIBUTOR', 1 ); 
define( 'USER_LEVEL_SUBSCRIBER', 0 ); 
define( 'USER_LEVEL_NONE', 0 ); 

require_once( AGDP_PLUGIN_DIR . '/includes/functions.php' );
require_once( AGDP_PLUGIN_DIR . '/public/class.agdp.php' );

//plugin_activation
register_activation_hook( __FILE__, array( 'Agdp', 'plugin_activation' ) );
//plugin_deactivation
register_deactivation_hook( __FILE__, array( 'Agdp', 'plugin_deactivation' ) );

add_action( 'admin_menu', 'agdp_admin_menu' );
function agdp_admin_menu(){
	require_once( AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-menu.php' );
	Agdp_Admin_Menu::init();
}

add_action( 'init', array( 'Agdp', 'init' ) );
add_action( 'admin_init', array( 'Agdp', 'admin_init' ) );

//forbidden xmlrpc
//TODO option to be clear
add_filter( 'xmlrpc_enabled', '__return_false' );