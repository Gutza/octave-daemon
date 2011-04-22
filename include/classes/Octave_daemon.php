<?php

class Octave_daemon
	implements iOctave_network
{

	private static $currentInstance;
	private static $serverPool=array();
	private static $lockptr=NULL;

	private function __construct()
	{
	}

	public function init()
	{
		if (!self::lock()) {
			Octave_logger::getCurrent()->log("This server is already running.",LOG_INFO);
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
		self::$lockptr=fopen(__FILE__,'r');
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

}
