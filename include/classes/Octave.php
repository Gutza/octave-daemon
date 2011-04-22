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
* {@link Octave_client}, which also implements a couple of data
* type translators for PHP.
*
* @package octave-daemon
* @subpackage client
*/
class Octave
{
	public $lastError="";

	protected $connector=NULL;
	protected $connectorMethods=array();

	public function __construct($connect,$port=NULL)
	{
		switch(gettype($connect)) {
			case "string":
				$this->connector=new Octave_client($connect,$port);
				break;
			case "boolean":
				$this->connector=new Octave_controller();
				break;
			case "object":
				if (!$connect instanceof iOctave_connector)
					throw new RuntimeException("The connector must implement iOctave_connector!");
				$this->connector=$connect;
				break;
			default:
				throw new RuntimeException("Unknown connect type: ".gettype($connect));
		}
		$this->connector->init();
		$this->connectorMethods=get_class_methods("iOctave_connector");
	}

	public function __call($method,$payload)
	{
		if (!in_array($method,$this->connectorMethods))
			throw new RuntimeException("Unknown method: ".$method);

		$result=$this->connector->$method($payload[0]);
		$this->lastError=$this->connector->lastError;
		return $result;
	}
}
