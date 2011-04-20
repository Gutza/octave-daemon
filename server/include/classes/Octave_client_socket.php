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
	public $msgEnd="<od-msg-end>\n";
	public $responseStart="<od-rsp>\n";
	public $errorStart="<od-err>\n";
	public $pid=0;

	private $socket=NULL;
	private $controller=NULL;

	public function __construct($controller)
	{
		$controller->hangingProcess=true;
		$controller->quiet=true;
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
		@socket_shutdown($this->socket); // fugly: if the client dies while waiting for a connection, we just go through the usual hoops
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

	public function write($message,$complete=true)
	{
		if ($complete)
			$message.=$this->msgEnd;
		
		return $msgLength==socket_write($this->socket,$message,strlen($message));
	}

	public function read()
	{
		return @socket_read($this->socket, 2048, PHP_NORMAL_READ);
	}

	public function entertain()
	{
		$this->write("");
		while(true) {
			$input=$this->read();

			if ($input===false)
				break;

			$input=trim($input);

			if (!strlen($input))
				continue;
			
			if ($input=='quit')
				break;

			@list($command,$payload)=explode(" ",$input,2);
			$this->processCommand($command,$payload);
		}
	}

	protected function processCommand($cmd,$payload)
	{
		if (!in_array($cmd,array('query','runRead','run')))
			return $this->respond(array(
				'response'=>'',
				'error'=>"Unknown command: ".$cmd
			));

		$this->respond(array(
			'response'=>$this->controller->$cmd($payload),
			'error'=>$this->controller->lastError
		));
	}

	private function respond($response)
	{
		if (
			isset($response['error']) &&
			strlen($response['error'])
		)
			$this->write($this->errorStart.$response['error']."\n");

		$this->write($this->responseStart.$response['response']."\n");
	}

}
