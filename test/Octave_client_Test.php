<?php

require_once "common_phpunit_test.php";

class clientTest extends commonTests
{
	public static $octave=NULL;

	function __construct()
	{
		self::$octave=new Octave("localhost");
	}
}
