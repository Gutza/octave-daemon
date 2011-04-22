<?php

require_once "common_phpunit_test.php";

class adhocTest extends commonTests
{
	function __construct()
	{
		if (!parent::__construct())
			return;

		self::$octave=new Octave(false);
	}

	public function testInitialize()
	{
		return parent::testInitialize();
	}
}
