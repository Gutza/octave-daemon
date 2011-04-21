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
* @subpackage server
* @author Bogdan Stăncescu <bogdan@moongate.ro>
* @version 1.0
* @copyright Copyright (c) 2011, Bogdan Stăncescu
* @license http://www.gnu.org/licenses/agpl-3.0.html GNU Affero GPL
*/

/**
* This class manages one client connection and holds its associated {@link Octave_controller controller}.
*
* @package octave-daemon
* @subpackage server
*/
class Octave_client_socket implements iOctave_protocol
{
	public $errorStart="<od-err>\n";
	public $msgEnd="<od-end>\n";
	public $pid=0;
	public $socketLimit=1048576;

	private $socket=NULL;
	private $controller=NULL;

	public function __construct($controller)
	{
		$controller->hangingProcess=true;
		$controller->quiet=true;
		$controller->allowPartial=true;
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

	public function write($message,$partial)
	{
		if (!$partial)
			$message.=$this->msgEnd;

		$msgLength=strlen($message);
		
		return $msgLength==@socket_write($this->socket,$message,$msgLength);
	}

	public function read()
	{
		$result="";
		while(substr($result,-1)!="\n") {
			$atom=@socket_read($this->socket, $this->socketLimit);
			if ($atom===false || $atom==="")
				// Client exited (empty lines are "\n", not "")
				return false;
			$result.=$atom;
		}
		return substr($result,0,-1);
	}

	public function entertain()
	{
		$this->write($this->errorStart,false);
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
			if (!$this->processCommand($command,$payload))
				break;
		}
	}

	public function sendError($message)
	{
		return $this->respond(array(
			'response'=>'',
			'error'=>$message,
			'partial'=>false
		));
	}

	protected function sendFile($filename)
	{
		if (!file_exists($filename) || !is_readable($filename))
			return $this->sendError("File doesn't exist: ".$filename);

		$this->write(filesize($filename).$this->errorStart,false);
		$fp=@fopen($filename,'r');
		while(!feof($fp)) {
			$str=fread($fp,102400); // 100 KB at a time
			$this->write($str,true);
		}
		$this->write($this->errorStart,false);
		fclose($fp);
	}

	protected function processCommand($cmd,$payload)
	{
		if (!in_array($cmd,array('query','runRead','run','retr')))
			return $this->sendError("Unknown command: ".$cmd);

		if ($cmd=='retr')
			return $this->sendFile($payload);

		$this->respond(array(
			'response'=>$this->controller->$cmd($payload),
			'error'=>$this->controller->lastError,
			'partial'=>$this->controller->partialResult
		));
	}

	private function respond($response)
	{
		$previousPartial=false;

		do {
			$partial=$response['partial'];

			// This is partial, because the errors always follow
			if (!$this->write($response['response'],true))
				return false;

			if ($partial) {
				$response=array(
					'response'=>$this->controller->more(),
					'error'=>$this->controller->lastError,
					'partial'=>$this->controller->partialResult
				);
			} else
				return $this->write($this->errorStart.$response['error'],$partial);

		} while($partial);
	}

}
