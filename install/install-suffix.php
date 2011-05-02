if (!Octave_daemon::init())
	throw new RuntimeException(Octave_daemon::$lastError);

Octave_daemon::run();

