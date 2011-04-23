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
* This class manages the {@link Octave_client_socket client connections} and {@link Octave_controller controller} pool.
*
* @package octave-daemon
* @subpackage server
*/
class Octave_pool
{
	static private $pool=array();
	static public $maxCount=3;
	static private $pending_connections=array();
	static public $home_directory="";

	private function __construct()
	{
	}

	public function getChild($cSocket)
	{
		if ($kid=self::firstChild($cSocket))
			return $kid;

		if (count(self::$pool)<self::$maxCount)
			return self::registerChild($cSocket);

		return false;
	}

	private function registerChild($cSocket)
	{
		$controller=new Octave_controller();
		$controller->cwd=self::$home_directory;
		try {
			$controller->init();
		} catch(Exception $e) {
			Octave_logger::log("Failed starting Octave controller: ".$e->message);
			return false;
		}
		$kid=new Octave_client_socket($controller);
		$kid->initSocket($cSocket);
		self::$pool[]=$kid;
		return $kid;
	}

	private function firstChild($cSocket)
	{
		foreach(self::$pool as $kid) {
			if ($kid->busy())
				continue;
			$kid->initSocket($cSocket);
			return $kid;
		}
	}

	public function deadChild($sig)
	{
		$pid=pcntl_waitpid(-1,$status);
		foreach(self::$pool as $kid) {
			if ($kid->processFuneral($pid))
				break;
		}
	}

	public function killAll($sig)
	{
		foreach(self::$pool as $kid)
			$kid->kill();
		exit;
	}

	public function newConnection($socket)
	{
		self::$pending_connections[]=$socket;
	}

	public function manageConnections()
	{
		$still_waiting=array();
		foreach(self::$pending_connections as $cSocket) {
			if ($kid=&self::getChild($cSocket)) {
				$pid=pcntl_fork();
				if ($pid==-1) {
					Octave_logger::log("Failed forking!",LOG_ERR);
					exit;
				} elseif ($pid) {
					// Parent
					$kid->pid=$pid;
					$kid->close();
				} else {
					// Child
					chdir(self::$home_directory);
					$kid->entertain();
					$kid->killSocket();
					exit;
				}
			} else
				$still_waiting[]=$cSocket;
		}
		self::$pending_connections=$still_waiting;
	}
}
