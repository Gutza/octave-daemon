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
*/

/**
* The basic process controller for Octave.
*
* It only performs the most basic tasks (it starts the process, sends commands and reads raw output).
* Internally, this is the code that actually gets the job done. In most cases,
* you don't need to instantiate this class directly -- use {@link Octave} instead.
*
* @author Bogdan Stăncescu <bogdan@moongate.ro>
* @version 1.0
* @copyright Copyright (c) 2011, Bogdan Stăncescu
* @license http://www.gnu.org/licenses/agpl-3.0.html GNU Affero GPL
* @package octave-daemon
* @subpackage server
*
* @example controller_example.php Usage example
*/

class Octave_controller extends Octave_partial_processor
	implements iOctave_connector
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
	* The options you want to pass to the Octave process.
	*
	* The "-i" option is added automatically in {@link init()}; if you add
	* it here it will be ignored.
	*
	* The "--no-window-system" is useful because it avoids some annoying
	* warnings related to plots, but you can remove this option
	* if you prefer to receive those warnings.
	*
	* @var array
	*/
	public $octave_options=array("--no-window-system", "--no-gui", "--no-gui-libs");

	/**
	* The Octave binary version.
	*
	* This is set automatically after {@link init()}
	*
	* @var string
	*/
	public $octave_version=NULL;

	/**
	* The path Octave will be started in.
	*
	* Leave it to NULL if you want to use the working directory of the current PHP process.
	* This can also be set via the constructor.
	*
	* @var string
	*/
	public $cwd=NULL;

	/**
	* If set to true, the destructor leaves the process alone.
	*
	* @var boolean
	*/
	public $hangingProcess=false;

	/**
	* The maximum chunk size for pipe reading.
	*
	* By default it's 1 MiB. You typically shouldn't need to change it.
	* @var int
	*/
	public $streamLimit=1048576;

	/**
	* The minimum chunk size for partial results.
	* By default it's 10 KiB. You typically shouldn't need to change it.
	* @var int
	*/
	public $partialLimit=10240;

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
	* The cureent Octave {@link $process}'s PID
	*
	* @var integer
	*/
	public $pid;

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
	public $lastError="";

	/**
	* Whether to allow partial reads.
	*
	* If enabled, you need to check whether {@link $partialResult} is set;
	* if set, call {@link more()} to receive the next batch; repeat until
	* finished (i.e. unti $partialResult is false). In addition,
	* {@link $lastError} is guaranteed to be set correctly only after the
	* final call to {@link more()} (i.e. when $partialResult is false);
	* that's also when the errors are processed, if you don't set
	* {@link $quiet}.
	*
	* @var boolean
	*/
	public $allowPartial=false;

	/**
	* Whether the last result was partial.
	*
	* See {@link $allowPartial} for details.
	*
	* @var boolean
	*/
	public $partialResult;

	/**
	* Octave's cursor; it indicates the end of Octave output.
	*
	* @var string
	*/
	protected $octave_cursor="<od-msg-end>";

	/**
	* Used internally in {@link _return()} to track the Octave
	* prompt when {@link $allowPartial} is enabled.
	*
	* @var string
	*/
	private $tail="";

	/**
	* Whether this instance has been initialized
	*
	* @var boolean
	*/
	protected $initialized=false;

	/**
	* The class constructor.
	*
	* @return void
	*/
	public function __construct($cwd=NULL)
	{
		if ($cwd!==NULL)
			$this->cwd=$cwd;
	}

	/**
	* The class destructor.
	*
	* Cleanly quits Octave, closes the pipes and the process.
	* @return void
	*/
	public function __destruct()
	{
		if (!is_resource($this->process))
			// Already dead
			return;

		if ($this->hangingProcess) {
			$this->_closePipes();
			return;
		}

		$this->_send("quit\n");
		$this->_closePipes();
		$status=proc_close($this->process);
		if ($status==-1)
			throw new RuntimeException("Failed closing process");

		Octave_daemon::manageDeadPID($this->pid);
	}

	/**
	* Closes all the pipes open in this thread to the underlying Octave process.
	* @return void
	*/
	private function _closePipes()
	{
		fclose($this->stdin);
		fclose($this->stdout);
		fclose($this->stderr);
	}

	/**
	* Initializes the controller.
	*
	* Starts the Octave process and drops the welcome message.
	*
	* @return mixed true on success, false on failure or NULL if
	* already initialized. On failure, look at {@link $lastError}.
	*/
	public function init()
	{
		if ($this->initialized)
			return NULL;

		$options = array("-i");
		foreach($this->octave_options as $option)
			$options[] = trim($option);
		$options = " ".implode(" ", array_unique($options));

		$this->process=proc_open(
			$this->octave_path.$this->octave_binary.$options,
			array(
				0 => array("pipe", "r"),
				1 => array("pipe", "w"),
				2 => array("pipe", "w")
			),
			$pipes,
			$this->cwd
		);

		if (!is_resource($this->process)) {
			$this->lastError="Failed starting the Octave process.";
			return false;
		}

		$status=proc_get_status($this->process);
		$this->pid=$status['pid'];
		Octave_daemon::$child_pids[$status['pid']]=true;

		list($this->stdin,$this->stdout,$this->stderr)=$pipes;

		// Waiting for some output on either stdin or stderr
		$read=array($this->stdout,$this->stderr);
		$write=NULL;
		$except=NULL;

		if (!stream_select($read, $write, $except, 5)) {
			$this->lastError="The process timed out on all pipes -- this should never happen, please report this problem!";
			proc_close($this->process);
			return false;
		}

		// Any output on stderr on startup is bad news; quit just to make sure
		if ($read[0]==$this->stderr) {
			$this->lastError="Failed starting the Octave process: ".trim(stream_get_contents($this->stderr));
			return false;
		}

		// All is well -- set options and read welcome message (no need for partial reading here)
		$p=$this->allowPartial;
		$this->allowPartial=false;
		$welcome=$this->runRead('PS1("'.$this->octave_cursor.'"); format none;'."\n");
		$this->allowPartial=$p;

		if (!preg_match("/version ([0-9.]+)$/m",$welcome,$matches)) {
			$this->lastError="Unrecognized welcome message from Octave:\n{\n".$welcome."}";
			return false;
		}

		$this->octave_version=$matches[1];
		$this->initialized=true;
		return true;
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
	* @return string Whatever was found in Octave's buffer
	*/
	private function _read()
	{
		$N1=$N2=NULL;

		$result=array(
			'stdout'=>"",
			'stderr'=>"",
		);

		$len=-strlen($this->octave_cursor);

		while (true) {
			$read=array($this->stdout,$this->stderr);
			$anyout=false;
			while (stream_select($read,$N1,$N2,0)) {
				foreach($read as $stream) {
					if ($stream==$this->stdout) {
						$result['stdout'].=fread($stream,$this->streamLimit);
						$anyout=true;
					}
					if ($stream==$this->stderr)
						$result['stderr'].=fread($stream,$this->streamLimit);
				}
			}
			if ($anyout) {
				if (substr($this->tail.$result['stdout'],$len)==$this->octave_cursor) {
					$result['stdout']=substr($result['stdout'],0,$len);
					$this->partialResult=false;
					if ($this->partialProcessing) {
						$this->partialProcess($result['stdout'],false);
						$result['stdout']="";
					}
					return $result;
				}
				if ($this->partialProcessing) {
					$this->partialProcess($result['stdout'],true);
					$result['stdout']="";
				} elseif ($this->allowPartial && strlen($result['stdout'])>=$this->partialLimit) {
					$this->partialResult=true;
					$this->tail=substr($this->tail.$result['stdout'],$len);
					return $result;
				}
			}
			usleep(100);
		}
	}

	/**
	* Processes the current errors, if applicable
	*
	* @return void
	*/
	private function _processErrors($result)
	{
		$this->lastError.=$result['stderr'];
		if ($this->partialResult)
			return;

		if ($this->lastError &&	!$this->quiet)
			trigger_error("Octave: ".$this->lastError,E_USER_WARNING);
	}

	/**
	* Retrieves more output from Octave's pipes.
	*
	* See {@link $allowPartial} for details.
	*
	* @return string the same kind of result as {@link _retrieve()}
	*/
	public function more()
	{
		if (!$this->partialResult) {
			$this->lastError="more() called, but no partial result was in progress";
			return false;
		}
		$payload=$this->_read();
		$this->_processErrors($payload);

		return $payload['stdout'];
	}

	/**
	* Retrieves all output remaning in a partial call.
	*
	* This is like an uninterrupted sequence of calls to {@link more()}
	*
	* @return string the same kind of result as {@link more()}
	*/
	public function everything()
	{
		$payload="";
		while($this->partialResult)
			$payload.=$this->more();

		return $payload;
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
		$this->tail="";
		$payload=$this->_read();
		$this->lastError="";
		$this->_processErrors($payload);

		return $payload['stdout'];
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

		if ($withOutput===NULL)
			return $command."\n";

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
		$success=$this->_send($this->_prepareCommand($command));

		$pp=$this->partialProcessing;
		$this->partialProcessing=false;
		$this->_retrieve();
		$this->partialProcessing=$pp;

		return $success;
	}

	/**
	* Essentially the same as {@link run()}, but also returns the result.
	*
	* @param $command string The command to execute
	* @return mixed The result as a string, or boolean false on error.
	*/
	public function runRead($command)
	{
		if (!$this->_send($this->_prepareCommand($command,NULL)))
			return false;

		return $this->_retrieve();
	}

	/**
	* Expects a single statement, which gets executed via Octave's {@link http://www.gnu.org/software/octave/doc/interpreter/Terminal-Output.html#index-disp-797 disp()}
	*
	* @param $command string The statement to execute
	* @return mixed the result as a string
	*/
	public function query($command)
	{
		return $this->runRead("disp(".$command.");");
	}

	/**
	* Retrieves (and returns) the contents of the file indicated by the parameter.
	*
	* This method is one of the reasons why you should never leave an
	* octave daemon open on the Internet.
	*
	* You will typically want to use this method in order to retrieve
	* plots generated by Octave. If the server running the daemon
	* isn't running X11 (which is generally to be expected), you will
	* need to execute the following Octave command before attempting
	* to generate the plot:
	*   figure(1, "visible", "off");
	* ... and once you generated the plot:
	*   print -djpg "/tmp/foo.jpg";
	* ...and then you can go ahead and retrieve file /tmp/foo.jpg.
	*/
	public function retrieve($filename)
	{
		$this->lastError="";
		if (!file_exists($filename) || !is_readable($filename)) {
			$this->lastError="File doesn't exist: ".$filename;
			return false;
		}

		$result="";

		$fp=@fopen($filename,'r');
		while(!feof($fp)) {
			$str=fread($fp,102400); // 100 KB at a time
			if ($this->partialProcessing)
				$this->partialProcess($str,!feof($fp));
			else
				$result.=$str;
		}
		fclose($fp);

		return $result;
	}

}

