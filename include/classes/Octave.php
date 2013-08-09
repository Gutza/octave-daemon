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
	* The default configuration settings. Use this with
	* {@link Octave::getCurrent()}.
	*
	* This emulates the parameters accepted by {@link Octave::__construct()}:
	* - If it's a boolean, it instantiates an ad hoc connector
	* - If it's a string, it can be either a host name, or a hostname
	*   followed by a colon followed by the port.
	* - If it's an object, that object is used as the connector
	*   (see the constructor's documentation for details).
	*/
	static public $defaultConfig="";

	/**
	* Used internally by {@link Octave::setCurrent()} and {@link Octave::getCurrent()}.
	*/
	static protected $currentInstance;

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
		if ($this->init()===false)
			return false;

		if (!in_array($method,$this->connectorMethods))
			throw new RuntimeException("Unknown method: ".$method);

		$this->connector->quiet=$this->quiet;
		$result=$this->connector->$method($payload[0]);
		$this->lastError=$this->connector->lastError;
		return $result;
	}

	/**
	* A call to {@link iOctave_connector::query()},
	* the result of which is interpreted as a boolean
	* value.
	*
	* @return boolean true or false
	*/
	public function getBoolean($query)
	{
		$raw = $this->query($query);
		return (bool) intval($raw);
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

	/**
	* Checks whether variable $varName is set in Octave.
	*
	* @param string $varName the name of the variable to test
	* @return boolean true if set, false otherwise
	*/
	public function exists($varName)
	{
		return (bool) trim($this->query("exist(\"".addslashes($varName)."\")"));
	}

	/**
	* Use this method to instantiate and/or access a single Octave instance
	* anywhere across your code.
	*
	* If you want to set your own instance, use {@link Octave::setCurrent()}
	* to set the current instance.
	*
	* Alternately, set {@link Octave::$defaultConfig} and you're done --
	* {@link Octave::getCurrent()} instantiates the static instance when it's
	* first called and returns it.
	*
	* Please be advised you don't have any guarantee that you'll get the same
	* Octave process every time -- this method is simply a way to avoid lugging
	* around a global variable storing the PHP Octave instance, but that's all
	* it does.
	*/
	public function getCurrent()
	{
		if (isset(self::$currentInstance))
			return self::$currentInstance;

		if (is_string(self::$defaultConfig) && preg_match("/^(.+)\\:([0-9]+)$/",self::$defaultConfig,$matches))
			self::setCurrent(new Octave($matches[1],$matches[2]));
		else
			self::setCurrent(new Octave(self::$defaultConfig));

		return self::$currentInstance;
	}

	/**
	* This is used to set the current static Octave instance -- for details
	* see {@link Octave::getCurrent()}.
	*/
	public function setCurrent($instance)
	{
		self::$currentInstance=$instance;
	}
}
