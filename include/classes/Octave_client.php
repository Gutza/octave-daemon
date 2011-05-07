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
class Octave_client extends Octave_partial_processor
	implements iOctave_protocol, iOctave_connector
{

	public $server_address='127.0.0.1';
	public $server_port=OCTAVE_DAEMON_PORT;
	public $lastError="";
	public $socketLimit=1048576;

	private $socket;
	protected $initialized=false;

	public function __construct($host=NULL,$port=NULL)
	{
		if ($host!==NULL)
			$this->server_address=$host;

		if ($port!==NULL)
			$this->server_port=$port;
	}

	public function init()
	{
		if ($this->initialized)
			return NULL;

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

		return $this->initialized=true;
	}

	protected function getSocketError($msg)
	{
		$errorcode = socket_last_error();
		$errormsg = socket_strerror($errorcode);

		return $msg." [".$errorcode."]: ".$errormsg;
	}

	public function run($command)
	{
		return $this->_call("run",$command);
	}

	public function runRead($command)
	{
		return $this->_call("runRead",$command);
	}

	public function query($command)
	{
		return $this->_call("query",$command);
	}

	protected function _call($method,$payload)
	{
		if ($this->init()===false)
			return false;

		return $this->_process($method,$payload);
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
			$result=@socket_write($this->socket,$left);
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
		$MElen=strlen(self::error_end);
		$ESlen=strlen(self::error_start);

		if ($size===NULL) {
			$fixed=false;
			$size=$this->socketLimit;
		} else
			$fixed=true;

		while(true) {
			$atom=@socket_read($this->socket, $size);
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

				$pos=strpos($tail.$atom,self::error_start);

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
				if (substr($this->lastError,-$MElen)==self::error_end) {
					$this->lastError=substr($this->lastError,0,-$MElen);
					return $result;
				}
			}
		}
	}
}
