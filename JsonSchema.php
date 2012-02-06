<?php
	class JsonUtil {
		public static
		function stringToId( $var ) {
			// performs the easiest transformation to safe id, but is lossy

			if ( is_bool( $var ) ) {
				return str( $var );
			}

			elseif ( is_string( $var ) ) {
				return preg_replace( '/[^a-z0-9\-_:\.]/gi', '', $var );
			} else {
				throw new Exception( 'Cannot convert var to id' );
			}

		}

		public static
		function getTitleFromNode( $schemanode, $nodeindex ) {
			if ( isset( $schemanode['title'] ) ) {
				return $schemanode['title'];
			} else {
				return $nodeindex;
			}

		}

		public static
		function getNewValueForType( $thistype ) {
			switch( $thistype ) {
				case 'map':
					$newvalue = array();
					break;
				case 'seq':
					$newvalue = array();
					break;
				case 'number':
					case 'int':
						$newvalue = 0;
						break;
					case 'str':
						$newvalue = "";
						break;
					case 'bool':
						$newvalue = false;
						break;
					default:
						$newvalue = null;
						break;
			}

			return $newvalue;
		}

		public static
		function getType ( $foo ) {

			if ( $foo == null ) {
				return null;
			}

			switch( gettype( $foo ) ) {
				case "array":

					if ( array_keys( $foo ) == range( 0, count( $foo ) - 1 ) ) {
						return "seq";
					} else {
						return "map";
					}

					break;
				case "integer":
					case "double":
						return "number";
						break;
					case "boolean":
						return "bool";
						break;
					case "string":
						return "str";
						break;
					default:
						return null;
						break;
			}

		}

		public static
		function getSchemaArray( $parent ) {
			$schema = array();
			$schema['type'] = JsonUtil::getType( $parent );
			switch ( $schema['type'] ) {
				case 'map':
					$schema['mapping'] = array();
					foreach ( $parent as $name ) {
						$schema['mapping'][$name] = JsonUtil::getSchemaArray( $parent[$name] );
					}

					break;
				case 'seq':
					$schema['sequence'] = array();
					$schema['sequence'][0] = JsonUtil::getSchemaArray( $parent[0] );
					break;
		}

		return $schema;
	}

}


class TreeRef {
	public $node;
	public $parent;
	public $nodeindex;
	public $nodename;
	public
	function __construct( $node, $parent, $nodeindex, $nodename ) {
		$this->node = $node;
		$this->parent = $parent;
		$this->nodeindex = $nodeindex;
		$this->nodename = $nodename;
	}

}


class JsonTreeRef {
	public
	function __construct( $node, $parent, $nodeindex, $nodename, $schemaref ) {
		$this->node = $node;
		$this->parent = $parent;
		$this->nodeindex = $nodeindex;
		$this->nodename = $nodename;
		$this->schemaref = $schemaref;
		$this->fullindex = $this->getFullIndex();
		$this->attachSchema();
	}

	public
	function attachSchema() {
		if ( $this->schemaref->node['type'] == 'any' ) {
			if ( $this->getType() == 'map' ) {
				$this->schemaref->node['mapping'] = array( "extension" => array(                "title" => "extension field",                 "type" => "any"            )        );
				$this->schemaref->node['user_key'] = "extension";
			}

			elseif ( $this->getType() == 'seq' ) {
				$this->schemaref->node['sequence'] = array( array( "title" => "extension field",                   "type" => "any" )        );
				$this->schemaref->node['user_key'] = "extension";
			}

		}

	}

	public
	function getTitle() {

		if ( isset( $this->nodename ) ) {
			return $this->nodename;
		} else {
			return JsonUtil::getTitleFromNode( $this->node, $this->nodeindex );
		}

	}

	public
	function isUserKey() {
		return $this->userkeyflag;
	}

	public
	function renamePropname( $newindex ) {
		$oldindex = $this->nodeindex;
		$this->parent->node[$newindex] = $this->node;
		$this->nodeindex = $newindex;
		$this->nodename = $newindex;
		$this->fullindex = $this->getFullIndex();
		unset( $this->parent->node[$oldindex] );
	}

	public
	function getType() {
		$nodetype = $this->schemaref->node['type'];

		if ( $nodetype == 'any' ) {

			if ( $this->node == null ) {
				return null;
			} else {
				return JsonUtil::getType( $this->node );
			}

		} else {
			return $nodetype;
		}

	}

	public
	function getFullIndex() {

		if ( $this->parent == null ) {
			return "json_root";
		} else {
			return $this->parent->getFullIndex() + "." + JsonUtil::stringToId( $this->nodeindex );
		}

	}

}

//
// schemaIndex object
//
class JsonSchemaIndex {
	public $root;
	public $idtable;
	public
	function __construct( $schema ) {
		$this->root = $schema;
		$this->idtable = array();

		if ( is_null( $this->root ) ) {
			return null;
		}

		$this->indexSubtree( $this->root );
	}

	public
	function indexSubtree( $schemanode ) {
		$nodetype = $schemanode['type'];
		switch( $nodetype ) {
			case 'map':
				foreach ( $schemanode['mapping'] as $key => $value ) {
					$this->indexSubtree( $value );
				}

				break;
			case 'seq':
				foreach ( $schemanode['sequence'] as $value ) {
					$this->indexSubtree( $value );
				}

				break;
		}
		if ( isset( $schemanode['id'] ) ) {
			$this->idtable[$schemanode['id']] = $schemanode;
		}

	}

	public
	function newRef( $node, $parent, $nodeindex, $nodename ) {

		if ( $node['type'] == 'idref' ) {
			$node = $this->idtable[$node->idref];
		}

		return new TreeRef( $node, $parent, $nodeindex, $nodename );
	}
}
