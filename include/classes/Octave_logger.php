<?php

/**
* Octave-daemon -- a network daemon for Octave, written in PHP
*
* Copyright (C) 2011 Bogdan Stancescu <bogdan@moongate.ro>
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as
* published by the Free Software Foundation, either version 3 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see {@link http://www.gnu.org/licenses/}.
*
* @package octave-daemon
* @subpackage server
* @author Bogdan Stăncescu <bogdan@moongate.ro>
* @version 1.0
* @copyright Copyright (c) 2011, Bogdan Stăncescu
* @license http://www.gnu.org/licenses/agpl-3.0.html GNU Affero GPL
*/

/**
* A simple class to manage system logging.
*
* @package octave-daemon
* @subpackage server
*/
class Octave_logger
{
	public static $ident="octave-daemon";
	public static $facility=LOG_DAEMON;
	public static $options=array(
		LOG_CONS=>false,
		LOG_NDELAY=>false,
		LOG_ODELAY=>true,
		LOG_PERROR=>false,
		LOG_PID=>true,
	);

	private static $instance=NULL;
	private static $initialized=false;

	private function __construct()
	{
	}

	public static function getCurrent()
	{
		if (isset(self::$instance))
			return self::$instance;
		return self::$instance=new Octave_logger();
	}

	public static function init()
	{
		if (self::$initialized)
			return NULL;

		openlog(
			self::$ident,
			array_sum(array_keys(self::$options,true)),
			self::$facility
		);

		self::$initialized=true;
	}

	public static function log($message,$priority=LOG_WARNING)
	{
		self::init();
		syslog($priority,$message);
	}
}
