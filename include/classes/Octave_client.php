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
* The Octave network client
* @package octave-daemon
* @subpackage client
*/
class Octave_client
{

	public $server_address='127.0.0.1';
	public $server_port=43210;
	public $lastError="";
	public $socketLimit=1048576;

	public $errorStart="<od-err>\n";
	public $msgEnd="<od-end>\n";

	private $socket;
	private $serverCommands=array(
		"run",
		"runRead",
		"query",
		"quit"
	);
	private $partialProcessing=false;
	private $partialHandler=NULL;

	public function init()
	{
		$this->socket=@socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if ($this->socket===false) {
			$this->lastError=$this->getSocketError("Failed creating client socket");
			return false;
		}

		if (!@socket_connect($this->socket,$this->server_address,$this->server_port)) {
			$this->lastError=$this->getSocketError(
				"Failed connecting client to server at ".$this->server_address.":".$this->server_port
			);
			return false;
		}

		$this->_read();

		return true;
	}

	protected function getSocketError($msg)
	{
		$errorcode = socket_last_error();
		$errormsg = socket_strerror($errorcode);

		return $msg." [".$errorcode."]: ".$errormsg;
	}

	public function __call($method,$payload)
	{
		if (!in_array($method,$this->serverCommands))
			throw new RuntimeException("Unknown command: ".$method);

		return $this->_process($method,$payload[0]);
	}

	public function retrieve($filename)
	{
		if (!$this->_send("retr ".$filename."\n"))
			return false;

		$pp=$this->partialProcessing;
		$this->partialProcessing=false;
		$size=$this->_read();
		$this->partialProcessing=$pp;
		if ($this->lastError)
			return false;

		if (!is_numeric($size)) {
			$this->lastError="Unknown server response (not numeric).";
			return false;
		}

		return $this->_read($size);
	}

	protected function _process($method,$payload="")
	{
		if (!$this->_send($method." ".$payload."\n"))
			return false;

		$reply=$this->_read();
		return $reply;
	}

	private function _send($payload)
	{
		$left=$payload;
		while(strlen($left)) {
			$result=socket_write($this->socket,$left);
			if ($result===false) {
				$this->lastError="The server has disconnected.";
				return false;
			}
			$left=substr($left,$result);
		}
		return true;
	}

	private function _read($size=NULL)
	{
		$state="result";
		$result=$this->lastError=$tail="";
		$MElen=strlen($this->msgEnd);
		$ESlen=strlen($this->errorStart);

		if ($size===NULL) {
			$fixed=false;
			$size=$this->socketLimit;
		} else
			$fixed=true;

		while(true) {
			$atom=socket_read($this->socket, $size);
			if ($atom===false || $atom==="") {
				$this->lastError="The server has disconnected.";
				return false;
			}

			if ($state=="result") {
				if ($fixed) {
					$size-=strlen($atom);

					if ($size==0) {
						$fixed=false;
						$size=$this->socketLimit;
					}

					if ($this->partialProcessing)
						$this->partialProcess($atom,$fixed);
					else
						$result.=$atom;

					continue;
				}

				// Look for $this->errorStart
				$pos=strpos($tail.$atom,$this->errorStart);

				if ($pos!==false) {
					$atomPart=$tail.substr($atom,0,$pos-strlen($tail));

					if ($this->partialProcessing)
						$this->partialProcess($atomPart,false);
					else
						$result.=$atomPart;

					// Clip $atom to error start
					$atom=substr($atom,$pos-strlen($tail)+$ESlen);

					// Switch to error state
					$state="error";
				} else {
					$atomPart=substr($tail.$atom,0,-$ESlen);

					if ($this->partialProcessing)
						$this->partialProcess($atomPart,true);
					else
						$result.=$atomPart;

					// Populate the tail
					$tail=substr($result.$tail.$atom,-$ESlen);
				}
			}

			if ($state=="error") {
				$this->lastError.=$atom;
				if (substr($this->lastError,-$MElen)==$this->msgEnd) {
					$this->lastError=substr($this->lastError,0,-$MElen);
					return $result;
				}
			}
		}
	}

	public function registerPartialHandler($handler=NULL)
	{
		if ($handler===NULL) {
			$this->partialProcessing=false;
			return;
		}
		$this->partialProcessing=true;
		$this->partialHandler=$handler;
	}

	protected function partialProcess($payload,$partial)
	{
		return call_user_func($this->partialHandler,$payload,$partial);
	}
}
