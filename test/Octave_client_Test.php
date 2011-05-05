<?php

require_once "common_phpunit_test.php";

class clientTest extends commonTests
{
	public static $octave=NULL;

	function __construct()
	{
		if (isset(self::$octave))
			return;

		self::$octave=new Octave("localhost");
	}
}
