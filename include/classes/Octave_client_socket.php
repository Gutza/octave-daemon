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
	public $pid=0;
	public $socketLimit=1048576;
	public $remoteIP;

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
		socket_getpeername($socket,$this->remoteIP);
		socket_set_nonblock($socket);
		$this->socket=$socket;
	}

	public function __destruct()
	{
	}

	public function killSocket()
	{
		if (!is_resource($this->socket))
			// Already closed
			return;
		@socket_shutdown($this->socket); // fugly: if the client dies while waiting for a connection, we just go through the usual hoops
		$this->close();
	}

	public function kill()
	{
		if ($this->pid) {
			posix_kill($this->pid,SIGTERM);
			return;
		}
		$this->controller->hangingProcess=false;
		$this->controller->__destruct();
		$this->killSocket();
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

	public function write($message,$partial=false)
	{
		if (!$partial)
			$message.=self::error_end;

		$bytesLeft=strlen($message);
		while($bytesLeft) {
			$written=@socket_write($this->socket,$message,$bytesLeft);
			if ($written===false)
				return false;
			$bytesLeft-=$written;
			if ($bytesLeft) {
				$message=substr($message,$written);
				usleep(100);
			}
		}
		return true;
	}

	public function read()
	{
		$result="";
		while(substr($result,-1)!="\n") {
			$rd=array($this->socket);
			if (!socket_select($rd,$N1=NULL,$N2=NULL,0)) {
				usleep(100);
				continue;
			}
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
		Octave_logger::log("Connection accepted from ".$this->remoteIP);

		$this->write(self::error_start);
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
		Octave_logger::log("Closed connection from ".$this->remoteIP);
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

		$this->write(filesize($filename).self::error_start,false);
		$fp=@fopen($filename,'r');
		while(!feof($fp)) {
			$str=fread($fp,102400); // 100 KB at a time
			$this->write($str,true);
		}
		$this->write(self::error_start,false);
		fclose($fp);
		return true;
	}

	protected function processCommand($cmd,$payload)
	{
		if (!in_array($cmd,array('query','runRead','run','retr')))
			return $this->sendError("Unknown command: ".$cmd);

		if ($cmd=='retr')
			return $this->sendFile($payload);

		return $this->respond(array(
			'response'=>$this->controller->$cmd($payload),
			'error'=>$this->controller->lastError,
			'partial'=>$this->controller->partialResult
		));
	}

	protected function respond($response)
	{
		while (true) {
			$partial=$response['partial'];

			// This is partial, because the errors always follow
			if (!$this->write($response['response'],true))
				return $this->controller->everything() && false;

			if (!$partial) {
				if ($response['error'])
					Octave_logger::log("[".$this->remoteIP."] ".$response['error'],LOG_WARNING);
				return $this->write(self::error_start.$response['error'],$partial);
			}

			$response=array(
				'response'=>$this->controller->more(),
				'error'=>$this->controller->lastError,
				'partial'=>$this->controller->partialResult
			);
		};
	}

}
