<?php
/**
* Octave-daemon -- a network daemon for Octave, written in PHP
*
* This is the actual server.
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
* The main octave-daemon library.
*/
require "include/Octave_lib.php";

$server=new Octave_server_socket();
if (!$server->init()) {
	echo "Init failed:\n";
	echo $server->lastError."\n";
	exit;
}

declare(ticks = 1); 
pcntl_signal(SIGCHLD, array('Octave_pool','deadChild'));

while(true) {
	while($sock=$server->accept_connection())
		Octave_pool::newConnection($sock);

	Octave_pool::manageConnections($server);
	usleep(100);
}

