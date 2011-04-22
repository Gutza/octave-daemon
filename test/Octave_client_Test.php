<?php

require_once "common_phpunit_test.php";

class clientTest extends commonTests
{
	function __construct()
	{
		if (!parent::__construct())
			return;

		self::$octave=new Octave("localhost");
	}
}
