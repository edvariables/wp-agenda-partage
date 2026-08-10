<?php 
/**
 * AgendaPartage Admin -> Contact -> Import
 * Custom post type for WordPress in Admin UI.
 * 
 * Capabilities
 * Colonnes de la liste des contacts
 * Dashboard
 *
 * Voir aussi Agdp_Contact
 */
class Agdp_Admin_Contact_Import {

	public static function init() {

		self::init_hooks();
	}

	public static function init_hooks() {
		
	}
	/****************/
	
	/**
	 * Page d'importation
	 **/
	public static function import_page_html(){
		$search = empty( $_POST['search'] ) ? '' : $_POST['search'];
		$categories = empty( $_POST['categories'] ) ? '' : $_POST['categories'];
		
		self::search_form($search, $categories);
		
		if( $search || $categories ){
			echo self::search_result_html($search, $categories);
		}
	}
	
	/**
	 * Formulaire de recherche
	 **/
	public static function search_form($search, $categories){
		?><h1>Annuaire : recherche externe et importation</h1>
		<form method="POST">
		<div>
			<label>Recherche : </label><input type="text" name="search" value="<?php echo $search?>">
		</div>
		<div>
			<label>Catégories : </label><input type="text" name="categories" value="<?php echo $categories?>">
		</div>
		<?php
		?><input type="submit" name="submit" value="Valider" class="button button-primary button-large">
		</form><?php
	}
	
	/**
	 * Résultat de la recherche
	 **/
	public static function search_result_html($search, $categories){
		$data = self::search_result_pages_jaunes($search, $categories);
		
		echo '<pre>';
		
		var_dump($data);
		
		echo '</pre>';
		
		
	}
	
	/**
	 * Résultat de la recherche
	 **/
	public static function search_result_pages_jaunes($search, $categories){
		$localisation = 'Saint+Félicien+(07410)';
		$url = 'https://www.pagesjaunes.fr/annuaire/chercherlespros?univers=pagesjaunes';
		$url = add_query_arg( array(
				'ou' => $localisation,
				'quoiqui' => $categories,
			),
			$url );
		
		// $url = 'https://pays-de-saint-felicien.agenda-partage.fr';
		
		if( $data = self::search_result_cache_get( $url ) ){
			echo "<div><i><code>Données issues du cache</code></i></div>";
			return $data;
		}
		echo "<div><i><code>Requête : $url</code></i></div>";
		
        $ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0');
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		
		$headers = [];
		$headers[] = 'Host:www.pagesjaunes.fr';
		// $headers[] = 'Host:pays-de-saint-felicien.agenda-partage.fr';
		$headers[] = 'Content-Type:text/html';
		$headers[] = 'Connection:keep-alive';
		$headers[] = 'Accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
		$headers[] = 'Accept-Encoding:gzip, deflate, br, zstd';
		$headers[] = 'Accept-Language:fr,fr-FR;q=0.9,en-US;q=0.8,en;q=0.7';
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
        $data = curl_exec($ch);
		
		debug_log( __FUNCTION__, $data);
		
		// if( $data )
			// self::search_result_cache_set( $url, $data );
		
        return $data;
	}
	
	/**
	 * Résultat de la recherche en cache
	 **/
	public static function search_result_cache_get( $url ){
		$data = wp_cache_get( $url, __CLASS__ );
		return $data;
	}
	
	/**
	 * Résultat de la recherche en cache
	 **/
	public static function search_result_cache_set( $url, $data ){
		$expire = 3600;
		return wp_cache_set( $url, __CLASS__, $data, $expire );
	}
}
?>