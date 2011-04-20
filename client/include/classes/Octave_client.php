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
* @package octave-daemon-client
* @author Bogdan Stăncescu <bogdan@moongate.ro>
* @version 1.0
* @copyright Copyright (c) 2011, Bogdan Stăncescu
* @license http://www.gnu.org/licenses/agpl-3.0.html GNU Affero GPL
*/

/**
* The Octave network client
*/
class Octave_client
{

	public $server_address='127.0.0.1';
	public $server_port=43210;
	public $lastError="";

	public $msgEnd="<od-msg-end>";

	private $socket;
	private $serverCommands=array(
		"run",
		"runRead",
		"query",
		"quit"
	);

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

	protected function _process($method,$payload="")
	{
		if (!$this->_send($method." ".$payload."\n")) {
			$this->lastError="The server has disconnected.";
			return false;
		}

		$reply=$this->_read();
	}

	private function _send($payload)
	{
		$left=$payload;
		while(strlen($left)) {
			$result=socket_write($this->socket,$left);
			if ($result===false)
				return false;
			$left=substr($left,$result);
		}
		return true;
	}

	private function _read()
	{
		$result="";
		while(true) {
			$line=socket_read($this->socket, 2048, PHP_NORMAL_READ);
			if ($line==$this->msgEnd."\n")
				return $result;

			$result.=$line;
		}
	}

}
