Installation instructions
=========================

Installing the daemon
---------------------

1. Red Hat and friends (Fedora, CentOS etc)

Run install/install from a console, as root.
Everything should be installed automatically.

2. Other Linux distros

Edit install/install and change $target to an appropriate value (if needed).

Run install/install from a console, as root.

The installer will compile a self-contained PHP file which includes all classes
required to run the daemon, and places it in the file identified by $target.

The installer will then copy the default configuration file under
/etc/octave-daemon.conf (which is where it looks for by default).

After that the installer will probaby fail, but you can use your distro's
mechanisms to start it as a service (the only parameter that you'll probably
want to use is "-d", which starts it as a daemon).

3. Windows

The daemon doesn't work under Windows.

