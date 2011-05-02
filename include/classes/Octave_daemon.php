<?php

class Octave_daemon
	implements iOctave_network
{
	public static $lastError="";
	public static $child_pids=array();
	public static $child_process=false;

	private static $currentInstance;
	private static $serverPool=array();
	private static $lockptr=NULL;
	private static $config_file=NULL;
	private static $config;
	private static $daemonize=false;
	private static $servers=array();

	private function __construct()
	{
	}

	public function init()
	{
		self::$config_file=
			dirname(dirname(dirname(__FILE__))).
			"/octave-daemon.conf";

		if (!self::lock())
			return false;

		if (!self::processOptions())
			return false;

		if (!self::startServers())
			return false;

		if (!self::daemonize())
			return false;

		if (!self::writePID())
			return false;

		if (!self::changeIdentity())
			return false;

		return true;
	}

	protected function startServers()
	{
		if (!count(self::$config->servers)) {
			self::$lastError="There are no servers! Make sure you have at least one [server] section in the configuration file.";
			return false;
		}
		foreach(self::$config->servers as $server) {
			$s=new Octave_server_socket();
			$s->server_address=$server['server_address'];
			$s->server_port=$server['server_port'];
			$s->allowed_ranges=$server['allowed_ip'];
			if (!$s->init()) {
				self::$lastError=$s->lastError;
				return false;
			}
			self::$servers[]=$s;
		}
		return true;
	}

	protected function processOptions()
	{
		global $argv, $argc;
		$use_option=false;
		$option="";
		for($i=1;$i<$argc;$i++) {
			if ($use_option) {
				$option=$argv[$i];
				$use_option=false;
				continue;
			}
			switch($argv[$i]) {
				case "-d":
					self::$daemonize=true;
					continue;
				case "-c":
					$option=&self::$config_file;
					$use_option=true;
					continue;
				default:
					self::$lastError="Unknown option: ".$argv[$i];
					return false;
			}
		}

		self::$config=new Octave_configuration(self::$config_file);
		if (self::$config->lastError) {
			self::$lastError="Configuration error: ".self::$config->lastError;
			return false;
		}
		Octave_pool::$home_directory=self::$config->globals["home_directory"];
		return true;

	}

	private function lock()
	{
		self::$lockptr=fopen(self::$config_file,'r');
		if (flock(self::$lockptr,LOCK_EX + LOCK_NB))
			return true;

		fclose(self::$lockptr);
		self::$lastError="Another process has already locked this configuration file.";
		return false;
	}

	private function unlock()
	{		
		if (!flock(self::$lockptr,LOCK_UN))
			return false;

		fclose(self::$lockptr);
		return true;
	}

	public function closeServerSockets()
	{
		foreach(self::$servers as $server)
			$server->__destruct();
	}

	public function childMode()
	{
		self::$child_process=true;
		self::$child_pids=array();
		self::closeServerSockets();
	}

	public function kill()
	{
		Octave_pool::killAll();

		if (self::$child_process)
			exit;

		while(self::$child_pids) {
			// Waiting for all the children to die
			while(-1!=($pid=pcntl_waitpid(-1,$status)))
				self::manageDeadPID($pid);

			if (self::$child_pids)
				usleep(100);
		}

		self::closeServerSockets();

		exit;
	}

	public function deadChild()
	{
		$pid=pcntl_waitpid(-1,$status);
		self::manageDeadPID($pid);
	}

	private function manageDeadPID($pid)
	{
		unset(self::$child_pids[$pid]);
		Octave_pool::deadChild($pid);
	}

	public function run()
	{
		declare(ticks = 1); 
		pcntl_signal(SIGCHLD, array('Octave_daemon','deadChild'));
		pcntl_signal(SIGTERM, array('Octave_daemon','kill'));

		while(true) {
			foreach(self::$servers as $server) {
				while($sock=$server->accept_connection())
					Octave_pool::newConnection($sock);

				Octave_pool::manageConnections();
			}
			usleep(100);
		}

	}

	private function changeIdentity()
	{
		if (!function_exists("posix_getuid")) {
			self::$lastError="Octave-daemon requires POSIX functions; please see http://www.php.net/manual/en/book.posix.php";
			return false;
		}
		$root=!posix_getuid();
		if (
			!isset(self::$config->globals["run_as"]) ||
			!strlen($run_as=self::$config->globals["run_as"])
		) {
			if ($root) {
				self::$lastError="Octave-daemon won't run as root. Either run as a different user or specify the user in the configuration (option run_as)";
				return false;
			}
			return true;
		}

		$xploded=explode(".",$run_as);
		if (count($xploded)!=2) {
			self::$lastError="Configuration error: run_as must be <user name>.<group name>; it now contains \"".$run_as."\"";
			return false;
		}

		class_exists("Octave_controller");
		class_exists("Octave_client_socket");

		$uinfo=posix_getpwnam($xploded[0]);
		if (!$uinfo) {
			self::$lastError="Configuration error: Invalid user name in run_as (".$xploded[0].")";
			return false;
		}

		$ginfo=posix_getgrnam($xploded[1]);
		if (!$ginfo) {
			self::$lastError="Configuration error: Invalid group name in run_as (".$xploded[1].")";
			return false;
		}

		if (!posix_setgid($ginfo['gid'])) {
			self::$lastError="Configuration error: Failed setting group to ".$xploded[1];
			return false;
		}
		if (!posix_setuid($uinfo['uid'])) {
			self::$lastError="Configuration error: Failed setting user to ".$xploded[0];
			return false;
		}
		return true;
	}

	private function daemonize()
	{
		if (!self::$daemonize)
			return true;

		self::unlock();

		$pid=pcntl_fork();
		if ($pid==-1) {
			self::$lastError="Failed forking! You have to compile PHP with --enable-pcntl and run this on Unix-like platforms.";
			return false;
		}

		if ($pid)
			// Parent
			exit;

		if (!self::lock())
			return false;

		return true;
	}

	private function writePID()
	{
		if (!isset(self::$config->globals["pid_file"]) || !self::$config->globals["pid_file"])
			return true;

		$fp=@fopen(self::$config->globals["pid_file"],'w');
		if (!$fp) {
			self::$lastError="Failed opening PID file for writing.";
			return false;
		}

		if (!@fputs($fp,getmypid()."\n")) {
			self::$lastError="Failed saving PID in PID file.";
			return false;
		}

		return true;
	}
}
