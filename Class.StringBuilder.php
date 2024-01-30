<?php

/**
 * Action for rapport document
 *
 * @author Nevea
 * @version $Id$
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FORMATION
 */
/**
 */
class StringBuilder implements IteratorAggregate {
	
	private $items;
	private $count_items = 0;

	public function __construct () {

		$this->items = array ();
	}

	// Definition requise de l'interface IteratorAggregate
	public function getIterator () {

		return new ArrayIterator($this->items);
	}

	public function add ( $string ) {

		$this->items[$this->count_items++] = $string;
	}

	public function addString ( $arguments ) {

		// Lecture des arguments
		$args = func_get_args();
		
		if (count($args) > 1) {
			// Liste des variables
			$liste_variables[] = $args[0];
			
			for ($i = 1; $i < count($args); $i++) {
				$liste_variables[] = $args[$i];
			}
			
			// Construction de la chaine
			$filter = call_user_func_array("sprintf", $liste_variables);
			
			// Ajouter au tableau
			$this->add($filter);
		
		} else {
			$this->add($arguments);
		
		}
	}

	public function buildString ( $separateur = "\n" ) {

		if ($this->count_items > 0) {
			$return = join($separateur, $this->items);
		
		} else {
			$return = "";
		}
		
		return $return;
	}

	public function count () {

		return $this->count_items;
	}
	
	public function getArray(){
		return $this->items;
	}
}
?>