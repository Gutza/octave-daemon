<?php

/**
* The basic process controller for Octave.
* It only performs the most basic tasks (it starts the process, sends commands and reads raw output).
*/

class Octave_controller
{
	/**
	* Explicit path to the Cctave binary. In most setups you shouldn't need to populate this.
	*/
	public $octave_path="";

	/**
	* The name of the Octave binary. You typically don't need to change this.
	*/ 
	public $octave_binary="octave";

	private $stdin;
	private $stdout;
	private $stderr;
	private $process;

	/**
	* Change this to true if you don't want warnings from Octave to bubble up.
	*/
	public $quiet=false;

	/**
	* The Octave errors triggered by the last command. Read these if you set {@link $quiet} to false
	*/
	public $errors="";

	/**
	* The class constructor.
	*/
	public function __construct()
	{
	}

	/**
	* Initializes the controller.
	* Starts the Octave process and dumps the welcome message.
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
		list($this->stdin,$this->stdout,$this->stderr)=$pipes;
		$this->_read($this->stdout,true); // dump the welcome message
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
	* This is really, really private stuff -- you really never need to call this;
	* call {@link _retrieve}() instead from descendants, or {@link execute}() from
	* outside.
	*
	* @param resource $socket The socket; must be one of {@link $stdout} or {@link $stderr}
	* @param boolean $mandatory Whether output must be present. Waits indefinitely if true and there's no output.
	* @return string Whatever was found in the socket's buffer.
	*/
	private function _read($socket,$mandatory=false)
	{
		if ($mandatory) {
			stream_set_blocking($socket,true);
			$result=fgets($socket);
			stream_set_blocking($socket,false);
		} else
			$result="";

		$read=array($socket);
		$write=NULL;
		$except=NULL;

		while(stream_select($read,$write,$except,0))
			$result.=fgets($read[0]);

		return $result;
	}

	/**
	* Reads generic output from the Octave process.
	* This method tames {@link _read}() to some degree, but you typically shouldn't
	* need to call it directly anyway.
	*
	* @return string Whatever was found in Octave's output buffer.
	*/
	protected function _retrieve()
	{
		$payload=$this->_read($this->stdout,true);

		if (
			($this->errors=$this->_read($this->stderr)) &&
			!$this->quiet
		)
			trigger_error("Octave: ".trim($this->errors),E_USER_WARNING);

		return $payload;
	}

	/**
	* Prepares commands for passing to Octave
	* Ensures that we pass clean commands that don't produce any output.
	* Used internally by {@link exec}.
	* @param string $command the command to be send
	* @return string the proper command, with a trailing semicolon and newline
	*/
	private function _prepareCommand($command)
	{
		$command=trim($command);
		while(substr($command,-1)==';')
			$command=substr($command,0,-1);
		$command.=";\n";
	}

	/*
	* Executes a command that doesn't generate output
	* @param string $command the command to execute
	* @param boolean $raw whether the command should be executed as-is.
	* 	You typically don't need to use this.
	* @return boolean whether the command was successfully sent to Octave
	*/
	public function exec($command,$raw=false)
	{
		if (!$raw)
			$command=$this->_prepareCommand($command);
		return $this->_send($command);
	}

	public function execRead($command)
	{
		$this->exec(trim($command)."\n",true);
		return $this->_retrieve();
	}
}

$c=new Octave_controller();
$c->init();

$pid=pcntl_fork();
if ($pid==-1) {
	die("Could not fork!");
} elseif ($pid) {
	// wait for child
	pcntl_wait($status);
	echo "Child is done\n";
}

echo $c->execRead("5+5");
