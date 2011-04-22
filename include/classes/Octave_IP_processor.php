<?php

class Octave_IP_processor
{

	public function testIP($ip)
	{
		return
			preg_match("/^([0-9]{1,3}\\.){3}[0-9]{1,3}$/",$ip) ||
			preg_match("/^([0-9a-f]{4}:){1,7}[0-9a-f]{4}$/",$ip);
	}
}
