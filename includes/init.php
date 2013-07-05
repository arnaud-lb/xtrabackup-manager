<?php
/*

Copyright 2011-2012 Marin Software

This file is part of XtraBackup Manager.

XtraBackup Manager is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

XtraBackup Manager is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with XtraBackup Manager.  If not, see <http://www.gnu.org/licenses/>.

*/
	
	// Setup global defines that do not depend on anything else
	define('XBM_RELEASE_VERSION', 'XtraBackup Manager v0.81 - Copyright 2011-2012 Marin Software');

	// Log levels, lower is more verbose
	define('XBM_LOG_DEBUG', 0);
	define('XBM_LOG_INFO', 1);
	define('XBM_LOG_ERROR', 2);

	// Define the required schema verions
	define('XBM_SCHEMA_VERSION', 1006);

	// Number of seconds we sleep between checking stuff
	// Usually to see if we can run the backup 
	define('XBM_SLEEP_SECS', 30);

	// Autodetect some things to use in config.php

	// Use gethostname() function if it exists, otherwise fallback to "hostname" shell cmd.
	if( function_exists('gethostname') ) {
		$XBM_AUTO_HOSTNAME = gethostname();
	} else {
		$XBM_AUTO_HOSTNAME = trim(`hostname`);
	}

	$XBM_AUTO_TIMEZONE = date_default_timezone_get();

	$XBM_AUTO_INSTALLDIR = dirname(dirname(__FILE__));

	// Prepare the environment with a reasonable approximation of a multi-byte strlen function
	// if one is not available -- needed for printing tabled output
	if (!function_exists('mb_strlen')) { 
		function mb_strlen($str) { 
			return strlen(iconv("UTF-8","cp1251", $str ));
		}
	}

	// Include config and class / function files
	require('config.php');
	require('dbConnection.class.php');
	require('service.classes.php');
	require('host.class.php');
	require('backupVolume.class.php');
	require('scheduledBackup.class.php');
	require('logStream.class.php');
	require('backupRestorer.class.php');
	require('backupSnapshot.class.php');
	require('backupSnapshotTaker.class.php');
	require('runningBackup.class.php');
	require('mysqlType.class.php');
	require('continuousIncrementalBackupTaker.class.php');
	require('rotatingBackupTaker.class.php');
	require('backupSnapshotGroup.class.php');
	require('genericBackupTaker.class.php');
	require('materializedSnapshot.class.php');
	require('materializedSnapshotManager.class.php');
	require('cliHandler.class.php');
	require('exception.classes.php');
	require('backupStrategy.class.php');
	require('schemaUpgrader.class.php');
	require('backupJob.class.php');
	require('fullonlyBackupTaker.class.php');

	// Include the PHP Text Table project class 
	// From: http://code.google.com/p/php-text-table/
	require('php-text-table/text-table.php');

	// Setup global defines that depend on other stuff
	define('XBM_MAIN_LOG', $config['LOGS']['logdir'].'/xbm.log');

	// Define list of valid backup strategy codes
	define('XBM_VALID_STRATEGY_CODES', "CONTINC,FULLONLY,ROTATING");

	// Set the timezone that should be used
	date_default_timezone_set($config['SYSTEM']['timezone']);


?>
