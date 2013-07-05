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

	// Disable PHP Warnings - comment this out to re-enable them (or use php.ini settings)
	//error_reporting(0);

	/* The user that xbm should install it's cron in and launch backups from */	
	$config['SYSTEM']['user'] = 'xbm';

	// The tmpdir to use -- usually for just generating temporary crontab files.
	$config['SYSTEM']['tmpdir'] = '/tmp';

	// Change this if you want to override the autodetected system timezone (sometimes inaccurate).
	$config['SYSTEM']['timezone'] = $XBM_AUTO_TIMEZONE;
	
	// Where the logs should be stored
	$config['LOGS']['logdir'] = $XBM_AUTO_INSTALLDIR . '/logs';

	// What log level should we use - constants of any of: XBM_LOG_DEBUG, XBM_LOG_INFO, XBM_LOG_ERROR
	$config['LOGS']['level'] = XBM_LOG_INFO;

	// The port range made available for use by XBM with netcat - 
	// these ports need to be openable on the backup hsot
	$config['SYSTEM']['port_range']['low'] = 10000;
	$config['SYSTEM']['port_range']['high'] = 11000;

	// Whether or not to clean up after ourselves when a backup fails
	// Keeping the files around can be useful for troubleshooting what may have gone wrong
	$config['SYSTEM']['cleanup_on_failure'] = 1;

	// How much memory to allocate when performing --apply-log
	// This is given to xtrabackup / innobackupex as --use-memory parameter when
	// applying deltas or preparing backups after copying files.

	// Using 1G default - be mindful of this multiplied by possible concurrent backup jobs
	// Setting too high for will result in slower results due to problems with older linked versions of InnoDB
	$config['SYSTEM']['xtrabackup_use_memory'] = 1073741824; 

	// How many can run at once
	// Globally in this install of XBM
	$config['SYSTEM']['max_global_concurrent_backups'] = 4;
	// For any one host at a time... 
	$config['SYSTEM']['max_host_concurrent_backups'] = 1;

	// The hostname of the host xbm runs on - needs to resolve on the hosts to be backed up
	// Auto-detected system setting by gethostname():
	$config['SYSTEM']['xbm_hostname'] = $XBM_AUTO_HOSTNAME;
	// or, you can uncomment this and set your own hostname explicitly:
	// $config['SYSTEM']['xbm_hostname'] = '';

	/* Credentials for connecting to the XBM MySQL DB */
	$config['DB']['user'] = 'xbm';
	$config['DB']['password'] = 'xbm';
	$config['DB']['host'] = 'localhost';
	$config['DB']['port'] = 3306;
	$config['DB']['schema'] = 'xbm';
	// Socket to use -- Comment out if you don't want to use a socket file to connect (TCP)
	//$config['DB']['socket'] = '/mysqldb/tmp/mysql.sock';


	// Email alerts

	// Send email alerts when failures are caught? true/false
	$config['ALERTS']['enabled'] = false;

	// Where should alert emails be sent to? Comma separated for multiple email addresses
	//$config['ALERTS']['email'] = 'alerts@yourdomain.com';

	// What should the reply to address for alert emails be
	$config['ALERTS']['replyto'] = 'xbmdev-noreply@yourdomain.com';

?>
