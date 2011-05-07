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
* @subpackage common
* @author Bogdan Stăncescu <bogdan@moongate.ro>
* @version 1.0
* @copyright Copyright (c) 2011, Bogdan Stăncescu
* @license http://www.gnu.org/licenses/agpl-3.0.html GNU Affero GPL
*/

/**
* The base class for partial processors
* @package octave-daemon
* @subpackage common
*/
abstract class Octave_partial_processor
{
	protected $partialProcessing=false;
	private $partialHandler=NULL;

	/**
	* A method which registers a partial processor for this connector
	*
	* If no handler is passed, the existing handler is unregistered.
	*
	* @param $handler {@link http://www.php.net/manual/en/language.pseudo-types.php#language.types.callback callback}
	* @return void
	*/
	public function registerPartialHandler($handler=NULL)
	{
		if ($handler===NULL) {
			$this->partialProcessing=false;
			return;
		}
		$this->partialProcessing=true;
		$this->partialHandler=$handler;
	}

	/**
	* This is the method that calls the callbacks.
	*
	* Callbacks are expected to accept the same parameters
	* as this method.
	*
	* @param $payload string the contect
	* @param $partial boolean whether this is a partial result
	*/
	protected function partialProcess($payload,$partial)
	{
		return call_user_func($this->partialHandler,$payload,$partial);
	}
}
