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
* @package octave-daemon-server
* @author Bogdan Stăncescu <bogdan@moongate.ro>
* @version 1.0
* @copyright Copyright (c) 2011, Bogdan Stăncescu
* @license http://www.gnu.org/licenses/agpl-3.0.html GNU Affero GPL
*/

/**
* This class manages one client connection and holds its associated {@link Octave_controller controller}.
*
* @package octave-daemon-server
*/
class Octave_client_socket
{
	public $lastError="";
	public $REMOTE_ADDR="";
	private $controller=NULL;

	public $msgSep="\r\n";
	public $pid=0;

	private $socket=NULL;

	public function __construct($controller)
	{
		$controller->hangingProcess=true;
		$this->controller=$controller;
	}

	public function initSocket($socket)
	{
		$this->socket=$socket;
	}

	public function __destruct()
	{
	}

	public function kill()
	{
		socket_shutdown($this->socket);
		$this->close();
	}

	public function close()
	{
		socket_close($this->socket);
	}

	public function busy()
	{
		return (bool) $this->pid;
	}

	public function processFuneral($pid)
	{
		if ($this->pid!=$pid)
			return false;
		$this->pid=0;
		return true;
	}

	public function write($message)
	{
		socket_write($this->socket,$message.$this->msgSep,strlen($message)+strlen($this->msgSep));
	}

	public function read()
	{
		return @socket_read($this->socket, 2048, PHP_NORMAL_READ);
	}

	public function entertain()
	{
		$this->write("HAH");

		$input=$this->read();
		if ($input===false) {
			echo "Client exited!\n";
		} else {
			echo $input."\n";
			$this->write("Quitting...");
		}
		//$this->close();
		echo "Child exiting...\n";

		return;

		$s->hangingServer=true;
		$c=$kid['controller'];

		$s->cSend("HAH!");
		$input=$s->cRead();
		$s->cKill();
		echo "Child exiting...\n";
		exit;
	}

}
