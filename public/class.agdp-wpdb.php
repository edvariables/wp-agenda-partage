<?php
/**
 * Surcharge de wpdb 
 */
class Agdp_wpdb extends wpdb {

	/**
	 * Affectation d'une valeur de propriété de la classe.
	 * Contourne la protection sur la propriété 'check_current_query' pour un problème de caractères spéciaux dans les requêtes ('ô' par exemple).
	 *
	 * @param string $name  The private member to set.
	 * @param mixed  $value The value to set.
	 */
	public function __set( $name, $value ) {
		$un_protected_members = array(
			'check_current_query'
		);
		if ( in_array( $name, $un_protected_members, true ) )
			$this->$name = $value;
		else{
			parent::__set($name, $value);
			if( $this->$name !== $value )
				throw new Exception("$name n'a pas été affecté à l'objet");
		}
	}
}
