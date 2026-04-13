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
	
	/**
	 * Retrieves an entire SQL result set from the database (i.e., many rows).
	 *
	 * Agdp : Surcharge pour forcer check_current_query = false
	 *
	 * Executes a SQL query and returns the entire SQL result.
	 *
	 * @since 0.71
	 *
	 * @param string $query  SQL query.
	 * @param string $output Optional. Any of ARRAY_A | ARRAY_N | OBJECT | OBJECT_K constants.
	 *                       With one of the first three, return an array of rows indexed
	 *                       from 0 by SQL result row number. Each row is an associative array
	 *                       (column => value, ...), a numerically indexed array (0 => value, ...),
	 *                       or an object ( ->column = value ), respectively. With OBJECT_K,
	 *                       return an associative array of row objects keyed by the value
	 *                       of each row's first column's value. Duplicate keys are discarded.
	 *                       Default OBJECT.
	 * @return array|object|null Database query results.
	 */
	public function get_results( $query = null, $output = OBJECT ) {
		$this->check_current_query = false;
		return parent::get_results( $query, $output);
	}

}
