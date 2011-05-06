<?php

require_once "wrapper_phpunit_test.php";

class adhocTest extends wrapperTests
{
	static $octave=NULL;

	function __construct()
	{
		self::$octave=new Octave(false);
	}
}
