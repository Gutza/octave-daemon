<?php

class Octave_daemon
	implements iOctave_network
{

	private static $currentInstance;
	private static $serverPool=array();
	private static $lockptr=NULL;
	private static $config_file=NULL;
	private static $config;

	private function __construct()
	{
	}

	public function init()
	{
		self::$config_file=dirname(dirname(dirname(__FILE__)))."/octave-daemon.conf";
		if (!self::processOptions())
			return false;
		return true;
	}

	protected function processOptions()
	{

		global $argv, $argc;
		$daemonize=$use_option=false;
		$option="";
		for($i=1;$i<$argc;$i++) {
			if ($use_option) {
				$option=$argv[$i];
				$use_option=false;
				continue;
			}
			switch($argv[$i]) {
				case "-d":
					$daemonize=true;
					continue;
				case "-c":
					$option=&self::$config_file;
					$use_option=true;
					continue;
				default:
					throw new RuntimeException("Unknown option: ".$argv[$i]);
			}
		}

		if (!self::lock())
			throw new RuntimeException("Another process has already locked this configuration file.");

		self::$config=new Octave_configuration(self::$config_file);
		if (self::$config->lastError)
			throw new RuntimeException("Configuration error: ".self::$config->lastError);

		var_dump(self::$config);
		if ($daemonize && !self::daemonize()) {
			Octave_logger::getCurrent()->log("Failed forking! You have to compile PHP with --enable-pcntl and run this on Unix-like platforms.");
			return false;
		}
	}

	public function getCurrent()
	{
		if (isset(self::$currentInstance))
			return self::$currentInstance;

		if (!self::init())
			return false;

		$class=get_class(self);
		return self::$currentInstance=new $class();
	}

	private function lock()
	{
		self::$lockptr=fopen(self::$config_file,'r');
		if (flock(self::$lockptr,LOCK_EX+LOCK_NB))
			return true;

		fclose(self::$lockptr);
		return false;
	}

	private function unlock()
	{		
		if (!flock(self::$lockptr,LOCK_UN))
			return false;

		fclose(self::$lockptr);
		return true;
	}

	private function daemonize()
	{
		$pid=pcntl_fork();
		if ($pid==-1)
			return false;

		if ($pid)
			// Parent
			exit;

		return true;
	}
}
