<?php
/**
* Octave-daemon -- a network daemon for Octave, written in PHP
*
* Copyright (C) 2011 Bogdan Stancescu <bogdan@moongate.ro>
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as
* published by the Free Software Foundation, either version 3 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see {@link http://www.gnu.org/licenses/}.
*
* @package octave-daemon
* @subpackage client
* @author Bogdan Stăncescu <bogdan@moongate.ro>
* @version 1.0
* @copyright Copyright (c) 2011, Bogdan Stăncescu
* @license http://www.gnu.org/licenses/agpl-3.0.html GNU Affero GPL
*/

/**
* This is the public front end.
*
* This is basically a wrapper for {@link Octave_controller} or
* {@link Octave_client}, and also implements a couple of data
* type translators for PHP. Take a look at the {@link __construct() constructor}
* for more information on how to instantiate it, and read the
* {@tutorial quickstart.pkg Quick Start} tutorial for usage examples.
*
* @package octave-daemon
* @subpackage client
*/
class Octave
{

	/**
	* The last error encountered by this instance.
	*
	* Empty string if there were no errors. Gets overwritten
	* on every connector call.
	*
	* @var string
	*/
	public $lastError="";

	/**
	* Whether to issue PHP warnings on errors or not
	*
	* @var boolean
	*/
	public $quiet=false;

	/**
	* The connector used by this instance.
	*
	* This is guaranteed to implement {@link iOctave_connector}.
	*
	* @var object
	*/
	protected $connector=NULL;

	/**
	* The methods supported by the connector.
	*
	* This is automatically populated by {@link init()}
	* with the methods in {@link iOctave_connector}.
	*
	* @var array
	*/
	protected $connectorMethods=array();

	/**
	* Whether this instance was initialized.
	*
	* This gets set by {@link init()}.
	*
	* @var boolean
	*/
	protected $initialized=false;

	/**
	* The constructor.
	*
	* Builds the necessary connector, depending on the parameters:
	* - If $connect is boolean, it instantiates an ad hoc connector
	*   ({@link Octave_controller}); in this case, $port is ignored.
	* - If $connect is a string, it instantiates a network client
	*   ({@link Octave_client}), and $connect is taken to be the
	*   address or hostname of the server.
	*   If $port is not specified, the {@link OCTAVE_DAEMON_PORT default port}
	*   is used; if $port is specified, it is used as such.
	* - If $connect is an object, that object is used as the connector.
	*   The object must implement {@link iOctave_connector}. In this case,
	*   $port is ignored.
	* - In all other cases, an exception is thrown.
	*
	* @param $connect mixed Connector information
	* @param $port integer The port used by network clients.
	* @return void
	*/
	public function __construct($connect,$port=OCTAVE_DAEMON_PORT)
	{
		switch(gettype($connect)) {
			case "string":
				$this->connector=new Octave_client($connect,$port);
				break;
			case "boolean":
				$this->connector=new Octave_controller();
				break;
			case "object":
				$this->connector=$connect;
				break;
			default:
				throw new RuntimeException(
					"Unknown connection type: ".
					gettype($connect)
				);
		}
	}

	/**
	* Initializes the connector.
	*
	* This method is called automatically, you generally don't need
	* to cll this directly (it's typically only called explicitly for
	* development -- debugging, profiling etc).
	*
	* @return mixed true if initialized, false on error, or NULL if already initialized
	*/
	function init()
	{
		if ($this->initialized)
			return NULL;

		if (!$this->connector instanceof iOctave_connector)
			throw new RuntimeException("The connector must implement iOctave_connector!");

		if (!$this->connector->init()) {
			$this->lastError=$this->connector->lastError;
			return false;
		}

		$this->connectorMethods=get_class_methods("iOctave_connector");

		return $this->initialized=true;
	}

	/**
	* Calls for methods registered in {@link iOctave_connector}
	* (these go through directly to the connector).
	*/
	public function __call($method,$payload)
	{
		if (!in_array($method,$this->connectorMethods))
			throw new RuntimeException("Unknown method: ".$method);

		if ($this->init()===false)
			return false;

		$this->connector->quiet=$this->quiet;
		$result=$this->connector->$method($payload[0]);
		$this->lastError=$this->connector->lastError;
		return $result;
	}

	/**
	* A call to {@link iOctave_connector::query()}
	* processed with {@link matrix2array()}.
	*/
	public function getMatrix($query)
	{
		$raw=$this->query($query);
		if (!strlen(trim($raw)))
			return array();
		return $this->matrix2array($raw);
	}

	/**
	* Converts an Octave matrix to a PHP array.
	*
	* Currently only supports two-dimensional arrays, and will
	* output a two-dimensional array even if the input is a vector.
	*
	* The result is an indexed array (the matrix) of indexed arrays
	* (the rows).
	*
	* @param $matrix string the Octave matrix
	* @return array the PHP array
	*/
	public function matrix2array($matrix)
	{
		$array=array();
		$lines=explode("\n",$matrix);
		foreach($lines as $line) {
			$line=trim($line);
			if (!strlen($line))
				continue;
			$array[]=explode(" ",trim($line));
		}

		return $array;
	}

	/**
	* Registers a handler for partial processing.
	*
	* This simply calls the connector's {@link Octave_partial_processor::registerPartialHandler()} method.
	*/
	public function registerPartialHandler($handler=NULL)
	{
		return $this->connector->registerPartialHandler($handler);
	}
}
