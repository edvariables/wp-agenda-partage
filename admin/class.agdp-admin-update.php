<?php 

/**
 * 
 * Mise à jour par git
 */
class Agdp_Admin_Update {

	static $initialized = false;

	public static function init() {
		if(self::$initialized)
			return;
		self::$initialized = true;
		self::init_hooks();
	}

	public static function init_hooks() {
	}

	/**
	 * Update du plugin
	 */
	public static function update_form() {
		return self::git_form();
	}
	
	/**
	 * Update du plugin via GIT
	 */
	public static function git_form() {
		if ( ! current_user_can( 'manage_network_plugins' ) ) 
			die( 'Accès non autorisé' );
		
		echo sprintf('<h1>Mise à jour de l\'Agenda partagé</h1>' );
		
		$is_status = empty($_REQUEST['action']);
		
		if( ! $is_status )
			self::git_discard_changes();
		
		if( $is_status = empty($_REQUEST['action']) ){
			echo sprintf('<h2>Etat courant</h2>' );
			$result = self::git_exec('status');
		} else {
			echo sprintf('<h2>Mise à jour</h2>' );
			$result = self::git_exec('pull');
		}
		
		//discard_changes from status
		if( $is_status ){
			$discard_inputs = self::get_discard_changes_inputs( $result );
		}
		else {
			self::import_package_links( $result );
		}
		
		echo sprintf('<form method="POST" action="%s">', $_SERVER['REQUEST_URI'])
			. isset($discard_inputs) ? $discard_inputs : ''
			. '<input type="submit" name="action" value="Mettre à jour" class="button button-primary button-large"/>'
			. '</form>';
	}

	/**
	 * Exec git cmd
	 */
	private static function git_exec( string $params, bool $verbose = true) {
		if ( ! current_user_can( 'manage_network_plugins' ) ) 
			die( 'Accès non autorisé' );
		
		$cmd = sprintf('git -C %s %s', AGDP_PLUGIN_DIR, $params);
			
		if( $verbose )
			echo sprintf('<label>%s</label>', $cmd );
		$result = shell_exec( $cmd );
		if( $result === null )
			$result = '';
		elseif( $verbose )
			echo sprintf('<pre>%s</pre>', $result);
		return $result;
	}
	
	/**
	 * Cancel files modifications as user confirmed in form (needed before git pull)
	 */
	private static function git_discard_changes() {
		if ( ! current_user_can( 'manage_network_plugins' ) ) 
			die( 'Accès non autorisé' );
		
		$discard_changes = empty($_POST['discard_changes']) ? [] : $_POST['discard_changes'];
		foreach( $discard_changes as $discard_file ){
			echo sprintf('<h2>Annulation des changements du fichier %s</h2>', $discard_file );
			
			$params = sprintf('checkout %s ', $discard_file);
			$result = self::git_exec($params);
		}
	}

	/**
	 * Cancel files modifications as user confirmed in form (needed before git pull)
	 */
	private static function get_discard_changes_inputs($git_status) {
		$discard_inputs = '';
		$matches = [];
		if( preg_match_all('/\t(modified|deleted)\:\s+([^\r\n]*)[\r\n]/', $git_status, $matches ) ){
			foreach($matches[1] as $i => $file_status){
				$modified_file = $matches[2][$i];
				$discard_inputs .= sprintf('<li><label><input type="checkbox" name="discard_changes[]" value="%s">%s (%s)</label></li>',
					$modified_file, $modified_file, $file_status
				);
			}
		}
		if( $discard_inputs ){
			$discard_inputs = sprintf('<ul class=""><h4>Abandon des modifications en cours</h4>%s</ul></br></br>', $discard_inputs);
		}
		
		return $discard_inputs;
	}

	/**
	 * Cancel files modifications as user confirmed in form (needed before git pull)
	 */
	public static function import_package_links($git_result) {
		if( ! class_exists('Agdp_Admin_Packages') ){
			require_once( AGDP_PLUGIN_DIR . "/admin/class.agdp-admin-packages.php");
			Agdp_Admin_Packages::init();
		}
		
		$matches = [];
		$extension = '.pack.' . AGDP_TAG;
		$git_result = str_replace("\\", '/', $git_result);
		if( preg_match_all('/[\/](([^\/]+)' . preg_quote($extension) . ')\s+\|/', $git_result, $matches ) ){
			foreach($matches[2] as $i => $post_type){
				$label = 'Importer le package <b>' . $post_type . '</b>';
				echo Agdp_Admin_Packages::get_import_link( $post_type, $label );
			}
		}
	}
}
?>