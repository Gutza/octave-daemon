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
*/

/**
* The basic process controller for Octave.
*
* It only performs the most basic tasks (it starts the process, sends commands and reads raw output).
*
* @author Bogdan Stăncescu <bogdan@moongate.ro>
* @version 1.0
* @package octave-controller
* @copyright Copyright (c) 2011, Bogdan Stăncescu
* @license http://www.gnu.org/licenses/agpl-3.0.html GNU Affero GPL
*
* @example simple_controller_example.php Simple usage example
*/

class Octave_controller
{
	/**
	* Explicit path to the Cctave binary.
	*
	* In most setups you shouldn't need to populate this.
	*
	* @var string
	*/
	public $octave_path="";

	/**
	* The name of the Octave binary.
	*
	* You typically don't need to change this.
	*
	* @var string
	*/ 
	public $octave_binary="octave";

	/**
	* The Octave process's standard input stream
	*
	* @var resource
	*/
	private $stdin;

	/**
	* The Octave process's standard output stream
	*
	* @var resource
	*/
	private $stdout;

	/**
	* The Octave process's standard error stream
	*
	* @var resource
	*/
	private $stderr;

	/**
	* The Octave process
	*
	* @var resource
	*/
	private $process;

	/**
	* Change this to true if you don't want warnings from Octave to bubble up.
	*
	* @var boolean
	*/
	public $quiet=false;

	/**
	* The Octave errors triggered by the last command.
	*
	* Read these if you set {@link $quiet} to false.
	*
	* @var string
	*/
	public $errors="";

	/**
	* An internal separator that indicates the end of octave output.
	*
	* @var string
	*/
	protected $separator="--octave-PHP-daemon--";

	/**
	* The class constructor.
	*
	* @return void
	*/
	public function __construct()
	{
	}

	/**
	* The class destructor.
	*
	* Cleanly quits Octave, closes the pipes and the process.
	* @return void
	*/
	public function __destruct()
	{
		$this->_send("quit\n");
		fclose($this->stdin);
		fclose($this->stdout);
		fclose($this->stderr);
		proc_close($this->process);
	}

	/**
	* Initializes the controller.
	*
	* Starts the Octave process and drops the welcome message.
	* Throws a RuntimeException if it fails to start the Octave process.
	*
	* @return void
	*/
	public function init()
	{
		$this->process=proc_open(
			$this->octave_path.$this->octave_binary,
			array(
				0 => array("pipe", "r"),
				1 => array("pipe", "w"),
				2 => array("pipe", "w")
			),
			$pipes
		);

		if (!is_resource($this->process))
			throw new RuntimeException("Failed starting the Octave process.");

		list($this->stdin,$this->stdout,$this->stderr)=$pipes;

		// Waiting for some output on either stdin or stderr
		$read=array($this->stdout,$this->stderr);
		$write=NULL;
		$except=NULL;

		if (!stream_select($read, $write, $except, 5))
			throw new RuntimeException("The process timed out on all pipes -- this should never happen, please report this problem!");
		
		stream_set_blocking($this->stderr,false);
		if ($error=fgets($this->stderr))
			throw new RuntimeException("Failed starting the Octave process: ".trim($error));
		stream_set_blocking($this->stderr,true);

		// All is well
		$this->_read("stdout"); // dump the welcome message
		$this->_send("format none\n");
	}

	/**
	* Sends a string to the Octave process.
	*
	* @param string $payload The string to send over.
	* @return boolean Whether the payload was successfully delivered
	*/
	private function _send($payload)
	{
		return strlen($payload)==fwrite($this->stdin,$payload);
	}

	/**
	* Reads from one of Octave process's sockets.
	*
	* @param string $socket Must be one of "stdout" or "stderr"
	* @return string Whatever was found in Octave's buffer
	*/
	private function _read($socket)
	{
		switch($socket) {
			case 'stdout':
				$mySocket=&$this->stdout;
				break;
			case 'stderr':
				$mySocket=&$this->stderr;
				break;
			default:
				throw new RuntimeException("Unknown socket for reading (".$socket."); expecting \"stdout\" or \"stderr\".");
		}

		$this->_send(
			"fflush(".$socket."); ".
			"fdisp(".$socket.",\"".$this->separator."\");\n"
		);
		$result=$line="";
		while(true) {
			$line=fgets($mySocket);
			if (
				$line==$this->separator."\n" ||
				$line===false
			)
				return $result;

			$result.=$line;
		}
	}

	/**
	* Reads generic output from the Octave process.
	*
	* This method tames {@link _read()} to some degree, but you typically shouldn't
	* need to call it directly anyway -- use {@link runRead()}.
	*
	* @return string Whatever was found in Octave's output buffer.
	*/
	private function _retrieve()
	{
		$payload=$this->_read("stdout");

		if (
			($this->errors=$this->_read("stderr")) &&
			($this->errors=rtrim($this->errors)) &&
			!$this->quiet
		)
			trigger_error("Octave: ".trim($this->errors),E_USER_WARNING);

		return rtrim($payload);
	}

	/**
	* Prepares commands for passing to Octave.
	*
	* Ensures that we pass clean commands that predictably produce or don't produce output.
	* Used internally by {@link run}().
	* @param string $command the command to be send
	* @param boolean $withOutput whether to produce output (controls the final semicolon)
	* @return string the proper command, with a trailing semicolon and newline
	*/
	private function _prepareCommand($command,$withOutput=false)
	{
		$command=trim($command);

		while(substr($command,-1)==';')
			$command=substr($command,0,-1);

		if ($withOutput)
			return $command."\n";

		return $command.";\n";
	}

	/**
	* Executes a command that doesn't generate output.
	*
	* @param string $command the command to execute
	* @return boolean whether the command was successfully sent to Octave
	*/
	public function run($command)
	{
		return $this->_send($this->_prepareCommand($command));
	}

	/**
	* Essentially the same as {@link run()}, but also returns the result.
	*
	* @param $command string The command to execute
	* @return mixed The result as a string, or boolean false on error.
	*/
	public function runRead($command)
	{
		if (!$this->_send($this->_prepareCommand($command,true)))
			return false;

		return $this->_retrieve();
	}

	/**
	* Excpects a single statement, which gets executed via Octave's {@link http://www.gnu.org/software/octave/doc/interpreter/Terminal-Output.html#index-disp-797 disp()}
	* 
	*
	* @param $command string The statement to execute
	* @return mixed the result as a string
	*/
	public function query($command)
	{
		return $this->runRead("disp(".$command.")");
	}
}

