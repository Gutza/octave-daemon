<?php

require_once "wrapper_phpunit_test.php";

class clientTest extends wrapperTests
{
	public static $octave=NULL;
	private $fp;

	function __construct()
	{
		if (isset(self::$octave))
			return;

		self::$octave=new Octave("localhost");
	}

	function lock()
	{
		$this->fp=fopen(__FILE__,'r');
		flock($this->fp,LOCK_EX);
	}

	function unlock()
	{
		flock($this->fp,LOCK_UN);
	}
}
