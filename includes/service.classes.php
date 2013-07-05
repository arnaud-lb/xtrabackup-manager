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

	// Service class to get back hosts
	class hostGetter {

		function __construct() {
			$this->log = false;
		}


		function setLogStream($log) {
			$this->log = $log;
		}

		// Get all Host objects
		function getAll($activeOnly = false) {
			global $config;


			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT host_id FROM hosts";

			if($activeOnly == true) {
				$sql .= " WHERE active = 'Y'";
			}

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('hostGetter->getAll: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$hosts = Array();
			while($row = $res->fetch_array() ) {
				$host = new host($row['host_id']);
				$host->setLogStream($this->log);
				$hosts[] = $host;
			}

			return $hosts;

		}

		// Create a new host object and return it 
		function getNew($hostname, $hostDesc) {

			// Validate inputs
			host::validateHostname($hostname);
			host::validateHostDescription($hostDesc);

			if( $existing = $this->getByName($hostname) ) {
				throw new ProcessingException("Error: A Host already exists with a hostname matching: $hostname");
			}

			// INSERT the row
			

			$conn = dbConnection::getInstance($this->log);

			$sql = "INSERT INTO hosts (hostname, description) VALUES ('".$conn->real_escape_string($hostname)."', "
				."'".$conn->real_escape_string($hostDesc)."')";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new DBException('hostGetter->getNew: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$host = new host($conn->insert_id);
			$host->setLogStream($this->log);

			return $host;
	

		}


		// Get host by Name
		function getByName($name) {

			global $config;

			host::validateHostname($name);

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT host_id FROM hosts WHERE hostname='".$conn->real_escape_string($name)."'";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new DBException('hostGetter->getByName: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if($res->num_rows != 1 ) {
				return false;
			}

			if( ! ( $row = $res->fetch_array() ) ) {
				throw new Exception('hostGetter->getByName: '."Error: Could not retrieve the ID for Host with Hostname: $name.");
			}

			$host = new host($row['host_id']);
			$host->setLogStream($this->log);

			return $host;

		}


		// Get host by ID
		function getById($id) {

			global $config;

			if(!is_numeric($id) ) {
				throw new Exception('hostGetter->getById: '."Error: Expected a numeric ID as a parameter, but did not get one.");
			}

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT host_id FROM hosts WHERE host_id=".$id;

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('hostGetter->getById: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if($res->num_rows != 1 ) {
				throw new Exception('hostGetter->getById: '."Error: Could not retrieve a Host with ID $id.");
			}

			$host = new host($id);
			$host->setLogStream($this->log);

			return $host;
		}

	}


	// Service class to get backupJobs
	class backupJobGetter {

		function __construct() {
			$this->log = false;
		}   


		function setLogStream($log) {
			$this->log = $log;
		}   

		function getNew(scheduledBackup $scheduledBackup) {

			$sbInfo = $scheduledBackup->getInfo();

			$conn = dbConnection::getInstance($this->log);

			$sql = "INSERT INTO backup_jobs (backup_job_id, start_time, status, scheduled_backup_id, pid ) VALUES ".
					"(NULL, NOW(), 'Initializing', ".$sbInfo['scheduled_backup_id'].", ".getmypid()." )";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new DBException('backupJobGetter->getNew: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
            
            $job = new backupJob($conn->insert_id);
            $job->setLogStream($this->log); 
                
            return $job;

		}

		// Get all the running backup jobs
		function getRunning() {


			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_job_id, pid FROM backup_jobs WHERE status NOT IN ('Failed', 'Completed', 'Killed')";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new DBException('backupJobGetter->getRunning: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$runningJobs = Array();

			while( $row = $res->fetch_array() ) {
				// Check if the pid is valid
				if(file_exists('/proc/'.$row['pid']) ) {
					$runningJobs[] = new backupJob($row['backup_job_id']);
				}
				
			}

			return $runningJobs;
			
		}

		// Get the backup job by ID
		function getById($id, $notFoundException = true) {

			if(!is_numeric($id)) {
				throw new Exception('backupJobGetter->getById: '."Error: Expected a numeric ID for the backup job to fetch, but did not get one.");
			}

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_job_id FROM backup_jobs WHERE backup_job_id=".$id;

			if( ! ($res = $conn->query($sql) ) ) {
				throw new DBException('backupJobGetter->getById: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if($res->num_rows != 1 ) {
				if($notFoundException == true) {
					throw new Exception('backupJobGetter->getById: '."Error: Could not find a Backup Job with ID ".$id);
				} else {
					return false;
				}
			}

			$job = new backupJob($id);

			return $job;

		}

	}


	// Service class to get back storage volumes
	class volumeGetter {

		function __construct() {
			$this->log = false;
		}
		
		function setLogStream($log) {
			$this->log = $log;
		}

		// Get all Volume objects
		function getAll() {
			global $config;

			


			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_volume_id FROM backup_volumes";


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('volumeGetter->getAllVolumes: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$volumes = Array();
			while($row = $res->fetch_array() ) {
				$volume = new backupVolume($row['backup_volume_id']);
				$volume->setLogStream($this->log);
				$volumes[] = $volume;
			}

			return $volumes;

		}

		// Get volume by ID
		function getById($id) {

			global $config;

			if(!is_numeric($id) ) {
				throw new Exception('volumeGetter->getById: '."Error: Expected a numeric ID as a parameter, but did not get one.");
			}

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_volume_id FROM backup_volumes WHERE backup_volume_id=".$id;

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('volumeGetter->getById: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if($res->num_rows != 1 ) {
				throw new Exception('volumeGetter->getById: '."Error: Could not retrieve a Volume with ID $id.");
			}

			$volume = new backupVolume($id);
			$volume->setLogStream($this->log);

			return $volume;
		}


		// Get volume by Name
		function getByName($name) {

			global $config;

			backupVolume::validateName($name);

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_volume_id FROM backup_volumes WHERE name='".$conn->real_escape_string($name)."'";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('volumeGetter->getByName: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if($res->num_rows != 1 ) {
				return false;
			}

			if( ! ( $row = $res->fetch_array() ) ) {
				throw new Exception('volumeGetter->getByName: '."Error: Could not retrieve the ID for Volume with Name: $name.");
			}

			$volume = new backupVolume($row['backup_volume_id']);
			$volume->setLogStream($this->log);

			return $volume;
		}


		// Get a new volume with this name and path...
		function getNew($volumeName, $volumePath) {

			$volumePath = rtrim($volumePath, '/');

			backupVolume::validateName($volumeName);
			backupVolume::validatePath($volumePath);

			if( $existing = $this->getByName($volumeName) ) {
				throw new ProcessingException("Error: A Volume already exists with a name matching: $volumeName");
			}


			// INSERT the row
			

			$conn = dbConnection::getInstance($this->log);

			$sql = "INSERT INTO backup_volumes (name, path) VALUES ('".$conn->real_escape_string($volumeName)."', "
				."'".$conn->real_escape_string($volumePath)."')";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new DBException('volumeGetter->getNew: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$volume = new backupVolume($conn->insert_id);
			$volume->setLogStream($this->log);

			return $volume;

		}

	}


	// Service class to get back scheduled backups
	class scheduledBackupGetter {

		function __construct() {
			$this->log = false;
		}

		function setLogStream($log) {
			$this->log = $log;
		}

		// Get all scheduledBackup objects
		function getAll() {

			global $config;

			


			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT scheduled_backup_id FROM scheduled_backups";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackupGetter->getAll: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$scheduledBackups = Array();
			while($row = $res->fetch_array() ) {
				$scheduledBackup = new scheduledBackup($row['scheduled_backup_id']);
				$scheduledBackup->setLogStream($this->log);
				$scheduledBackups[] = $scheduledBackup;
			}

			return $scheduledBackups;

		}

		// Get one scheduledBackup object by ID
		function getById($id) {

			global $config;

			if(!is_numeric($id) ) {
				throw new Exception('scheduledBackupGetter->getById: '."Error: The ID for this object is not an integer.");
			}

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT scheduled_backup_id FROM scheduled_backups WHERE scheduled_backup_id=".$id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackupGetter->getById: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if($res->num_rows != 1) {
				throw new Exception('scheduledBackupGetter->getById: '."Error: Could not retrieve a Scheduled Backup with ID $id.");
			}

			$scheduledBackup = new scheduledBackup($id);
			$scheduledBackup->setLogStream($this->log);

			return $scheduledBackup;

		}


		// Get a scheduledBackup by name
		function getByHostnameAndName($hostname, $name) {

			host::validateHostname($hostname);
			scheduledBackup::validateName($name);

			$hostGetter = new hostGetter();
			$hostGetter->setLogStream($this->log);

			if( ! ( $host = $hostGetter->getByName($hostname) ) ) {
				throw new ProcessingException("Error: Could not find a host defined with hostname: $hostname");
			}

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT scheduled_backup_id FROM scheduled_backups 
					WHERE host_id=".$host->id." AND name='".$conn->real_escape_string($name)."'";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new DBException('scheduledBackupGetter->getByHostnameAndName: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if($res->num_rows == 0) {
				return false;
			}

			// Cross check we didnt get dupes
			if($res->num_rows > 1) {
				throw new Exception('scheduledBackupGetter->getByHostnameAndName: '."Error: Found more than one Scheduled Backup for host $hostname with name: $name");
			}

			if( ! ( $row = $res->fetch_array() ) ) {
				throw new Exception('scheduledBackupGetter->getByHostnameAndName: '."Error: Could not retrieve a Scheduled Backup for host $hostname with name: $name");
			}

			$scheduledBackup = new scheduledBackup($row['scheduled_backup_id']);
			$scheduledBackup->setLogStream($this->log);

			return $scheduledBackup;
			
		}

		// Get a new scheduledBackup object
		function getNew($hostname, $name, $strategyCode, $cronExpression, $volumeName, $datadir, $mysqlUser, $mysqlPass) {

			$datadir = rtrim($datadir, '/');

			// Validate inputs
			host::validateHostname($hostname);
			scheduledBackup::validateName($name);
			backupStrategy::validateStrategyCode($strategyCode);
			scheduledBackup::validateCronExpression($cronExpression);
			backupVolume::validateName($volumeName);
			scheduledBackup::validateDatadirPath($datadir);
			scheduledBackup::validateMysqlUser($mysqlUser);
			scheduledBackup::validateMysqlPass($mysqlPass);


			// Lookup host by name
			$hostGetter = new hostGetter();
			$hostGetter->setLogStream($this->log);

			if( ! ( $host = $hostGetter->getByName($hostname) ) ) {
				throw new ProcessingException("Error: No Host could be found with a hostname matching: $hostname");
			}
			
			// Lookup volume by name
			$volumeGetter = new volumeGetter();
			$volumeGetter->setLogStream($this->log);

			if( ! ( $volume = $volumeGetter->getByName($volumeName) ) ) {
				throw new ProcessingException("Error: No Volume could be found with a name matching: $volumeName");
			}

			// Lookup backup strategy by code
			$strategyGetter = new backupStrategyGetter();
			$strategyGetter->setLogStream($this->log);

			if( ! ( $strategy = $strategyGetter->getByCode($strategyCode) ) ) {
				throw new ProcessingException("Error: No Backup Strategy could be found matching the code: $strategyCode");
			}


			// Check for existing scheduled backups with the same name for this host
			if( $existing = $this->getByHostnameAndName($hostname, $name) ) {
				throw new ProcessingException("Error: A Scheduled Backup already exists for host $hostname with a name matching: $name");
			}


			// INSERT the row
			

			$conn = dbConnection::getInstance($this->log);


			// Create a new scheduledBackup entry
			// For now we just always create with "xtrabackup" binary used (mysql_type_id = 1)..
			// Maybe this can change later..
			$sql = "INSERT INTO scheduled_backups 
					( name, cron_expression, datadir_path, mysql_user, mysql_password, 
					  host_id, backup_volume_id, mysql_type_id, backup_strategy_id 
					) VALUES ( 
						'".$conn->real_escape_string($name)."',
						'".$conn->real_escape_string($cronExpression)."',
						'".$conn->real_escape_string($datadir)."',
						'".$conn->real_escape_string($mysqlUser)."',
						'".$conn->real_escape_string($mysqlPass)."',
						".$host->id.",
						".$volume->id.",
						1,
						".$strategy->id."
					)";


			if( ! ($conn->query($sql) ) ) {
				throw new DBException('scheduledBackupGetter->getNew: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			// Init the object
			$scheduledBackup = new scheduledBackup($conn->insert_id);
			$scheduledBackup->setLogStream($this->log);

			// Init the default scheduledBackup parameters for the strategy
			$sql = "INSERT INTO scheduled_backup_params (scheduled_backup_id, backup_strategy_param_id, param_value) 
						SELECT ".$scheduledBackup->id.", backup_strategy_param_id, default_value 
						FROM backup_strategy_params
						WHERE backup_strategy_id=".$strategy->id;

			if( ! ( $conn->query($sql) ) ) {
				throw new DBException('scheduledBackupGetter->getNew: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}


			return $scheduledBackup;
			
		}


	} // class: scheduledBackupGetter


	class mysqlTypeGetter {

		function __construct() {
			$this->log = false;
		}

		function setLogStream($log) {
			$this->log = $log;
		}

		function getById($id) {

			if(!is_numeric($id) ) {
				throw new Exception('mysqlTypeGetter->getById: '."Error: The ID for this object is not an integer.");
			}   
			
			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT mysql_type_id FROM mysql_types WHERE mysql_type_id=".$id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('mysqlTypeGetter->getById: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if($res->num_rows != 1) {
				throw new Exception('mysqlTypeGetter->getById: '."Error: Could not retrieve a MySQL Type with ID $id.");
			}

			$mysqlType = new mysqlType($id);
			$mysqlType->setLogStream($this->log);

			return $mysqlType;

		}

	}

	// Service class to sync all backup schedules to crontab
	class cronFlusher {


		function flushSchedule() {

			global $config;

			$cron = $this->buildCron();

			$tmpName = tempnam($config['SYSTEM']['tmpdir'], 'xbmcron');
			$fp = @fopen($tmpName, "w");

			// Validate we got a resource OK
			if(!is_resource($fp)) {
				throw new Exception('cronFlusher->flushSchedule: '."Error: Could not open tempfile for writing - $tmpName");
			}

		
			if( fwrite($fp, $cron) === false ) {
				throw new Exception('cronFlusher->flushSchedule: '."Error: Could not write to tempfile - $tmpName");
			}

			fclose($fp);

			// If we are trying to flush as any other user, give the -u option to crontab
			// unfortunately the -u option can only be used for privileged users, otherwise we get an error
			// This is even the case if you try to use the -u option to specify the current user!!
			if( exec('whoami') != $config['SYSTEM']['user'] ) {

				// Detect what to do for differnt OSes 
				switch( PHP_OS ) {
					case 'Linux':
						exec("crontab -u ".$config['SYSTEM']['user']." $tmpName 2>&1", $output, $returnVar);
						break;

					default:
					case 'SunOS':
						if( stristr(php_uname('v'), 'nexenta') ) {
							$osDetect = 'Nexenta';
						} else {
							$osDetect = 'SunOS';
						}

						throw new Exception('cronFlusher->flushSchedule: '."Error: $osDetect based systems only support altering current user's crontab. Change user to ".$config['SYSTEM']['user']." first.");

						break;

				}

			} else {
				exec("crontab $tmpName 2>&1", $output, $returnVar);
			}

			unlink($tmpName);

			if($returnVar != 0 ) {
				throw new Exception('cronFlusher->flushSchedule: '."Error: Could not install crontab with file - $tmpName - Got error $returnVar and output:\n".implode("\n", $output));
			}


			return $cron;
		}

		// Build the crontab
		function buildCron() {

			global $config;
			global $XBM_AUTO_INSTALLDIR;

			// Start with an empty string...
			$cron = '# This crontab is automatically managed and generated by '.XBM_RELEASE_VERSION."\n";
			$cron .="# You should NEVER edit this crontab directly, but rather reconfigure and use xbm-flush.php\n";

			// Get All Hosts
			$hostGetter = new hostGetter();
			$hosts = $hostGetter->getAll();

			
			// Cycle through each host...
			foreach( $hosts as $host ) {

				// Get host info ..
				$hostInfo = $host->getInfo();

				if($hostInfo['active'] == 'Y' ) {
					$cron .= "\n# Host - ".$hostInfo['hostname']."\n";
				} else {
					continue;
				}

				// Get scheduled backups for host...
				$scheduledBackups = $host->getScheduledBackups();

				// Cycle through each scheduled backup ..
				foreach( $scheduledBackups as $scheduledBackup ) {
					// Get info for the scheduled backup ..
					$scheduledBackupInfo = $scheduledBackup->getInfo();
					$sbParams = $scheduledBackup->getParameters();
					$host = $scheduledBackup->getHost();
					$hostInfo = $host->getInfo();

					if( $scheduledBackupInfo['active'] == 'Y' ) {

						$cron .= "\n# Backup: ".$scheduledBackupInfo['name']." -- Strategy: ".$scheduledBackupInfo['strategy_code']."\n";

						switch($scheduledBackupInfo['strategy_code']) {

							case 'FULLONLY':
								$cron .= "# Params Used: max_snapshots: ".$sbParams['max_snapshots']."\n";
							break;

							case 'CONTINC':
								$cron .= "# Params Used: max_snapshots: ".$sbParams['max_snapshots']."  Materialized Backups: ".$sbParams['maintain_materialized_copy']."\n";
							break;

							case 'ROTATING':

								if($sbParams['rotate_method'] == 'DAY_OF_WEEK') {

									// This is kind of stupid but it was quicker than trying to coerce date functions to play nice.
									$dayList = Array( 0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday');

									$rotateDays = explode(',',$sbParams['rotate_day_of_week']);

									$cron .= "# Day of week rotation on day(s): ";;
									foreach($rotateDays as $dayOfWeek) {
										$cron .= $dayList[$dayOfWeek]."  ";
									}
									$cron .= "\n";

								} elseif($sbParams['rotate_method'] == 'AFTER_SNAPSHOT_COUNT') {

									$cron .= "# After snapshot count rotation after ".$sbParams['rotate_snapshot_no']."\n";

								}

								$cron .= "# Params Used: max_snapshot_groups: ".$sbParams['max_snapshot_groups']."  Materialized Backups: ".$sbParams['maintain_materialized_copy']."\n";
							break;

							default:
							break;

						}
						$cron .= $scheduledBackupInfo['cron_expression'].' '.$XBM_AUTO_INSTALLDIR.'/xbm backup run '.$hostInfo['hostname'].' '.$scheduledBackupInfo['name']." quiet\n";
					} else {
						continue;
					}

					
				}
			}

			return $cron;
			
		}
	}


	// Timer class for timing things for performance monitoring...
	class Timer {

		var $classname  = "Timer";
		var $start	  = 0;
		var $stop	   = 0;
		var $elapsed	= 0;
		var $started	= false;

		# Constructor 
		function Timer( $start = true ) {
				if ( $start )
						$this->start();
		}

		# Start counting time 
		function start() {
				$this->started = true;
				$this->start = $this->_gettime();
		}

		# Stop counting time 
		function stop() {
				$this->started = false;
				$this->stop	  = $this->_gettime();
				$this->elapsed = $this->_compute();
		}

		# Get Elapsed Time 
		function elapsed() {
				if ( $this->started == true)
						$this->stop();

				return $this->elapsed;
		}

		# Reset timer
		function reset() {
				$this->started = false;
				$this->start	= 0;
				$this->stop	 = 0;
				$this->elapsed  = 0;
		}

		#### PRIVATE METHODS #### 

		# Get Current Time 
		function _gettime() {
			$mtime = microtime();
			$mtime = explode( " ", $mtime );
			return $mtime[1] + $mtime[0];
		}

		# Compute elapsed time 
		function _compute() {
			return $this->stop - $this->start;
		}

	}


	// Service class used to find available ports in the configured port range
	class portFinder {


		function __construct() {
			$this->log = false;
			$this->availablePort = false;
		}

		function setLogStream($log) {
			$this->log = $log;
		}


		// Inspects all entries in the running_backups table to see what ports are supposedly in use
		// cycles over the configured port range until it finds a free port
		// Will attempt to read from the table $attempts times - default 5
		// Will sleep $usleep microseconds between attempts - default 1MM microseconds = 1 second
		function findAvailablePort($attempts=5, $usleep = 1000000) {

			global $config;


			


			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT port FROM running_backups";

			$count = 0;

			while($this->availablePort === false && $count < $attempts) {

				if( ! ($res = $conn->query($sql) ) ) {
					throw new Exception('portFinder->findAvailablePort: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
				}

				$busyPorts = Array();
				while( $row = $res->fetch_array() ) {
					$busyPorts[] = $row['port'];
				}


				for( $i = $config['SYSTEM']['port_range']['low'] ; $i <= $config['SYSTEM']['port_range']['high'] ; $i++ ) {
					if( !in_array($i, $busyPorts) ) {

						$this->availablePort = $i;
						return true;
					}
				}
				$count++;
				sleep($usleep);
			}

			$this->availablePort = false;

			return true;

		}


	}

	

	// Service class to get the current runningBackup objects
	class runningBackupGetter {

		function __construct() {
			$this->checkSchemaVersion = true;
			$this->log = false;
		}


		function setLogStream($log) {
			$this->log = $log;
		}   

		function setSchemaVersionChecks($checks = true) {
			$this->checkSchemaVersion = $checks;
		}

		// Clean the list of running backups - remove entries for pids that are not running	
		function cleanRunningBackups() {

			global $config;

			$conn = dbConnection::getInstance($this->log, $this->checkSchemaVersion);

			$sql = "SELECT running_backup_id, pid FROM running_backups";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('runningBackupGetter->cleanRunningBackups: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			// Iterate over all running backups
			while($row = $res->fetch_array() ) {

				// Check to see if entry in proc filesystem exists (process is running)
				if(!file_exists('/proc/'.$row['pid'])) {

					// Remove entry if it is not found..
					$sql = "DELETE FROM running_backups WHERE running_backup_id=".$row['running_backup_id'];
					if( ! $conn->query($sql) ) {
						throw new Exception('runningBackupGetter->cleanRunningBackups: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
					}

				}
			}

			return;

		}	

		// Get all scheduledBackup objects
		function getAll() {
		
			global $config;

			// Clean the list before we return it
			$this->cleanRunningBackups();
			

			$conn = dbConnection::getInstance($this->log, $this->checkSchemaVersion);
			

			$sql = "SELECT running_backup_id FROM running_backups";
			
			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('runningBackupGetter->getAll: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}   
			
			$runningBackups = Array();
			while($row = $res->fetch_array() ) {
				$runningBackup = new runningBackup($row['running_backup_id']);
				$runningBackup->setLogStream($this->log);
				$runningBackups[] = $runningBackup;
			}   
			
			return $runningBackups;
			
		}


		// return runningBackups by host
		function getByHost(host $host) {

			global $config;

			// Clean the list of backups before we query it
			$this->cleanRunningBackups();

			if(!is_numeric($host->id)) {
				throw new Exception('runningBackupGetter->getByHost: '."Error: The ID for the given host object is not an integer.");
			}

			$conn = dbConnection::getInstance($this->log, $this->checkSchemaVersion);

			$sql = "SELECT running_backup_id FROM running_backups WHERE host_id=".$host->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('runningBackupGetter->getByHost: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$runningBackups = Array();
			while($row = $res->fetch_array() ) {
				$runningBackups[] = new runningBackup($row['running_backup_id']);
			}

			return $runningBackups;

		}

		// return runningBackups by scheduledBackup
		function getByScheduledBackup(scheduledBackup $scheduledBackup) {

			global $config;

			// Clean the list of backups before we query it
			$this->cleanRunningBackups();

			// Verify the ID is numeric
			if(!is_numeric($scheduledBackup->id)) {
				throw new Exception('runningBackupGetter->getByScheduledBackup: '."Error: The ID for the given scheduledBackup object is not an integer.");
			}

			$conn = dbConnection::getInstance($this->log, $this->checkSchemaVersion);

			$sql = "SELECT running_backup_id FROM running_backups WHERE scheduled_backup_id=".$scheduledBackup->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('runningBackupGetter->getByScheduledBackup: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$runningBackups = Array();
			while($row = $res->fetch_array() ) {
				$runningBackups[] = new runningBackup($row['running_backup_id']);
			}

			return $runningBackups;

		}

	}


	// Service class to basically rm -rf a dirtree
	class recursiveDeleter {

		// Recursively delete everything in a directory
		function delTree($dir) {

			if(!is_dir($dir) ) {
				throw new Exception('recursiveDeleter->delTree: '."Error: Could not delete dir $dir - It is not a directory.");
			}

			if( ( strlen($dir) == 0 ) || ( $dir == '/' ) ) {
				throw new Exception('recursiveDeleter->delTree: '."Error: Detected attempt to delete unsafe path: $dir - Aborting.");
			}

			// Add a trailing slash if there isnt one
			$dir = rtrim($dir, '/'). '/';

			$files = glob( $dir . '*', GLOB_MARK ); 

			foreach( $files as $file ) { 
				if( substr( $file, -1 ) == '/' ) {
					// Skip if the file is the directory we are removing
					// happens if empty
					if(rtrim($dir, '/') == rtrim($file, '/') ) {
						continue;
					}
					$this->delTree( $file );
				} else {
					if( ! unlink( $file ) ) {
						throw new Exception('recursiveDeleter->delTree: '."Error: Could not delete file: $file");
					}
				}
			} 

			if( ! rmdir( $dir ) ) {
				throw new Exception('recursiveDeleter->delTree: '."Error: Could not rmdir() on $dir");
			}

			return true;

		}

	}


	// Service class to get backupSnapshot objects.
	class backupSnapshotGetter {

		function __construct() {
			$this->log = false;
		}
		
		function setLogStream($log) {
			$this->log = $log;
		}
			
		function getById($id) {
				
			if(!is_numeric($id) ) {
				throw new Exception('backupSnapshotGetter->getById: '."Error: The ID for this object is not an integer.");
			}
			
			
				
			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE backup_snapshot_id=".$id;
				
				
			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('backupSnapshotGetter->getById: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if($res->num_rows != 1) {
				throw new Exception('backupSnapshotGetter->getById: '."Error: Could not retrieve a Backup Snapshot with ID $id.");
			}

			$backupSnapshot = new backupSnapshot($id);
			$backupSnapshot->setLogStream($this->log);

			return $backupSnapshot;
		}

	}

	// Service class to get backupSnapshot objects.
	class materializedSnapshotGetter {

		function __construct() {
			$this->log = false;
		}

		function setLogStream($log) {
			$this->log = $log;
		}

		function getById($id) {

			if(!is_numeric($id) ) {
				throw new Exception('materializedSnapshotGetter->getById: '."Error: The ID for this object is not an integer.");
			}

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT materialized_snapshot_id FROM materialized_snapshots WHERE materialized_snapshot_id=".$id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('materializedSnapshotGetter->getById: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if($res->num_rows != 1) {
				throw new Exception('materializedSnapshotGetter->getById: '."Error: Could not retrieve a Materialized Snapshot with ID $id.");
			}

			$materializedSnapshot = new materializedSnapshot($id);
			$materializedSnapshot->setLogStream($this->log);

			return $materializedSnapshot;
		}

	}



	// Service class for making temporary directories
	class remoteTempDir {

		function __construct() {
			$this->initSuccess = false;
		}

		// Create the tmpdir remotely and return the path information
		function init($host, $port, $user, $dir, $prefix='') {

			$this->host = $host;
			$this->port = $port;
			$this->user = $user;
			$this->prefix = $prefix;

			if (substr($dir, -1) != '/') $dir .= '/';

			$c = 0;
			do
			{
				$path = $dir.$prefix.mt_rand(0, 9999999);
				$cmd = 'ssh -o StrictHostKeyChecking=no -p '.$port.' '.$user.'@'.$host." 'mkdir $path' 2>&1";
				@exec($cmd, $output, $returnVar);
				$c++;
			} while ( ( $returnVar != 0 ) && $c < 5 );

			if($c >= 5) {
				throw new Exception('remoteTempDir->makeTempDir: '."Error: Gave up trying to create a temporary directory on ".$user."@".$host." after $c attempts. Last output:\n".implode("\n",$output));
			}

			$this->dir = $path;
			$this->initSuccess = true;
			return $path;

		}

		// Destroy the remote tmpdir
		function destroy() {

			// If this never init successfully, then nothing to destroy...
			if($this->initSuccess == false) {
				return;
			}

			if( !isSet($this->host) || !isSet($this->user) || !isSet($this->dir) ) {
				throw new Exception('remoteTempDir->destroy: '."Error: Expected this object to be populated with host, user and dir, but did not find them.");
			}

			if( (strlen($this->dir) == 0 ) || ($this->dir == '/') ) {
				throw new Exception('remoteTempDir->destroy: '."Error: Detected possibly unsafe to rm -rf temp remote temp dir: ".$this->user."@".$this->host.':'.$this->dir);
			}


			$cmd = 'ssh -o StrictHostKeyChecking=no -p '.$this->port.' '.$this->user.'@'.$this->host." 'rm -rf ".$this->dir."' 2>&1";
			@exec($cmd, $output, $returnVar);

			if( $returnVar != 0 ) {
				throw new Exception('remoteTempDir->destroy: '."Error: Encountered a problem removing remote temp dir: ".$this->user."@".$this->host.":".$this->dir."  Last output:\n".implode("\n",$output));
			}

			return true;
		}

	} // Class: Rmeote tempDir


	class backupSnapshotMerger {

		function mergeSnapshots($seedSnapshot, $deltaSnapshot) {

			// Create a new snapshot entry

			// Find the scheduled backup we are working in
			$scheduledBackup = $seedSnapshot->getScheduledBackup();


			// Init a new snapshot to be the SEED for the same snapshot group
			$mergeSnapshot = new backupSnapshot();
			$mergeSnapshot->init($scheduledBackup, 'SEED', 'MERGE', $seedSnapshot->getSnapshotGroup() );


			// Set status to merging
			$mergeSnapshot->setStatus('MERGING');

			// Merge incremental over seed

			// Get paths for seed and delta
			$seedPath = $seedSnapshot->getPath();

			$deltaPath = $deltaSnapshot->getPath();

			// Find the xtrabackup binary to use
			$xbBinary = $scheduledBackup->getXtraBackupBinary();
		

			// Merge the snapshots by their paths
			$this->mergePaths($seedPath, $deltaPath, $xbBinary);

			// We have a backup entry with a directory - we will need to remove it before we rename
			$mergePath = $mergeSnapshot->getPath();

			if( ( $mergePath == '/' ) || ( strlen($mergePath) == 0 ) ) {
				throw new Exception('backupSnapshotMerger->mergeSnapshots: '."Error: Detected unsafe path to remove: $mergePath");
			}

			if( ! rmdir($mergePath) ) {
				throw new Exception('backupSnapshotMerger->mergeSnapshots: '."Error: Unable to rmdir on: $mergePath");
			}


			// Rename the directory in place
			if( ! rename($seedPath, $mergePath) ) {
				throw new Exception('backupSnapshotMerger->mergeSnapshots: '."Error: Could not move seed from $seedPath to $mergePath - rename() failed.");
			}

			unset($output);
			unset($returnVar);

			// Remove the incremental files
			// rm -rf on $deltaSnapshot->getPath...
			$rmCmd = 'rm -rf '.$deltaPath;
			exec($rmCmd, $output, $returnVar);

			if( $returnVar <> 0 ) {
				throw new Exception('backupSnapshotMerger->mergeSnapshots: '."Error: Could not remove old deltas with command: $rmCmd -- Got output:\n".implode("\n",$output));
			}

			// Set the time to the time of the $deltaSnapshot
			$deltaInfo = $deltaSnapshot->getInfo();

			$mergeSnapshot->setSnapshotTime($deltaInfo['snapshot_time']);

			// Set any snapshot with the parent id of the merged delta to now have the parent id of the new merge snapshot

			// get mergeInfo first
			$mergeInfo = $mergeSnapshot->getInfo();

			// reassign the children of the seed the new parent (merge)
			$deltaSnapshot->assignChildrenNewParent($mergeInfo['backup_snapshot_id']);

			// Set the status of the delta to MERGED
			$deltaSnapshot->setStatus('MERGED');

			// Set the status of the seed snapshot to MERGED
			$seedSnapshot->setStatus('MERGED');

			// Set status to COMPLETED
			$mergeSnapshot->setStatus('COMPLETED');

			return true;

		}


		// Merge the deltas from deltaPath into seedPath using xbBinary xtrabackup binary
		function mergePaths($seedPath, $deltaPath, $xbBinary='') {

			global $config;

			if(strlen($xbBinary) == 0 ) {
				throw new Exception('backupSnapshotMerger->mergePaths: '."Error: Expected an xtrabackup binary passed as a parameter, but string was empty.");
			}

			// Actually kick off the process to do it here...

	
			// Attempt to create some temp dirs to work around XtraBackup Bug #837143
			// https://bugs.launchpad.net/percona-xtrabackup/+bug/837143
			if( !is_dir($seedPath.'/tmp') ) {
				if( ! mkdir($seedPath.'/tmp', 0770, true) ) {
					throw new Exception('genericBackupTaker->takeFullBackupSnapshot: '."Error: Unable to create dir './tmp' in backup dir for apply log process to utilize.");
				}
			}

			if( !is_dir($seedPath.'/mysqldb/tmp') ) {
				if( ! mkdir($seedPath.'/mysqldb/tmp', 0770, true) ) {
					throw new Exception('genericBackupTaker->takeFullBackupSnapshot: '."Error: Unable to create dir './mysqldb/tmp' in backup dir for apply log process to utilize.");
				}
			}


			$mergeCommand = 'innobackupex --defaults-file='.$seedPath.'/backup-my.cnf --ibbackup='.$xbBinary.' --use-memory='.$config['SYSTEM']['xtrabackup_use_memory'].
										' --apply-log --redo-only --incremental-dir='.$deltaPath.' '.$seedPath.' 1>&2';
			

			$mergeDescriptors = Array(
								0 => Array('file', '/dev/null', 'r'), // Process will read from /dev/null
								1 => Array('pipe', 'w'), // Process will write to STDOUT pipe
								2 => Array('pipe', 'w')  // Process will write to STDERR pipe
							);
								
			$mergeProc = proc_open($mergeCommand, $mergeDescriptors, $mergePipes);

			if(!is_resource($mergeProc) ) {
				throw new Exception('backupSnapshotMerger->mergePaths: '."Error: Unable to merge deltas into seed with command: $mergeCommand");
			}


			// Check the status of the backup every 5 seconds...
			$streamContents = '';
			stream_set_blocking($mergePipes[2], 0);
			do {
				$streamContents .= stream_get_contents($mergePipes[2]);
				if( ! ( $mergeStatus = proc_get_status($mergeProc) ) ) {
					throw new Exception('backupSnapshotMerger->mergePaths: '."Error: Unable to retrieve status on merge process.");
				}
				sleep(1);

			} while ($mergeStatus['running']);

			// Check exit status
			if($mergeStatus['exitcode'] <> 0 ) {

				$failMsg ="The process returned code ".$mergeStatus['exitcode'].".\n".
								"The command issued was:\n".$mergeCommand."\n".
								"The output is as follows:\n".$streamContents;
				throw new MergeException('backupSnapshotMerger->mergePaths: '."Error: There was an error merging snapshots - ".$failMsg, $failMsg);
			}

			return true;

		}

	} // Class: backupSnapshotMerger


	// Simple class used to build commands to use for netcat purposes
	// necessary due to parameter variations on different platforms
	class netcatCommandBuilder {

		// Get a netcat (nc) command to use to create a netcat listener on port $port
		// Specify a systemType if you like, otherwise detect the current system.
		function getServerCommand($port, $systemType = PHP_OS) {
	
			switch( $systemType ) {
				default:
				case 'Linux':
					// Check to see if this netcat variant needs -p for port. 
					$checkCommand = 'nc -h 2>&1 |head -3|tail -1|grep "listen for inbound"|grep -c "nc -l -p port"';
					$needsDashP = exec($checkCommand, $output, $returnVar);
					if($returnVar != 0  && $returnVar != 1 ) {
						throw new Exception('netcatCommandBuilder->getServerCommand: '."Error: An error occurred while attempting to detect the netcat variant installed on this system. ".
									"The process returned code ".$returnVar." when issuing the command: ".$checkCommand."\n");
					}

					// Return appropriate listener command...
					if($needsDashP == 1 ) {
						return "nc -l -p $port";
					} else {
						return "nc -l $port";
					}

					break;

				// Lets assume that ALL SunOS netcat needs nc -l -p PORT syntax
				// So far only tested on Nexenta
				case 'SunOS':
					return "nc -l -p $port";
					break;
			}

		}

		// get a netcat (nc) command to use to create a netcat sender/client - connecting to $host on port $port
		// specify a systemType if you like, otherwise detect the type of current system
		function getClientCommand($host, $port) {

			// Currently we can use some BASH magic to make this work on both Nexenta and Linux
			// By default attempt to auto-detect if we have a netcat version that has the -q option mentioned in help output
			// in this case it means netcat does not listen to EOF on stdin without it, so we must add it like -q0
			return '`if [ \`nc -h 2>&1|grep -c "\-q"\` -gt 0 ]; then NC="nc -q0"; else NC="nc"; fi; echo "$NC '.$host.' '.$port.'"`';

		}

	} // Class: netcatCommandBuilder


	// Service class for getting back the right type of object for taking backups with
	// based on the strategy employed for the scheduled backup
	class backupTakerFactory {

		// Return a backupSnapshotTaker object based on the backup strategy...
		function getBackupTakerByStrategy($stratCode = false) {


			switch($stratCode) {

				case 'FULLONLY':
					return new fullonlyBackupTaker();
				break;

				case 'CONTINC':
					return new continuousIncrementalBackupTaker();
				break;

				case 'ROTATING':
					return new rotatingBackupTaker();
				break;
		
				case false:
				default:
					throw new Exception('backupTakerFactory->getBackupTaker: '."Error: A backup strategy code must be specified.");

			}

		}

	} // Class: backupTakerFactory


	// Service class for getting the next snapshot group
	class backupSnapshotGroupFactory {

		// Get the snapshot group that comes after the given group
		// used to create the next snapshotGroup in sequence
		function getNextSnapshotGroup($snapshotGroup) {

			return new backupSnapshotGroup($snapshotGroup->scheduledBackupId, ($snapshotGroup->getNumber() + 1) );

		}
	}


	// Service class for managing waiting in queues
	// eg. Ensures First in First Out (FIFO) when threads enter sleep/wait cycles 
	// if there are too man backups running, etc.
	class queueManager {

		function setLogStream($log) {
			$this->log = $log;
		}

		function setInfoLogStream($log) {
			$this->infolog = $log;
		}

		// Get a ticket number in the queue of the specified name
		function getTicketNumber($queueName) {

			// Clean the queue before we get new tickets
			$this->cleanQueue($queueName);

			
			$conn = dbConnection::getInstance($this->log);

			$sql = "INSERT INTO queue_tickets (queue_ticket_id, queue_name, pid) VALUES (NULL, '".$conn->real_escape_string($queueName)."', ".getmypid().")";

			if( ! ( $result = $conn->query($sql) ) ) {
				throw new DBException('queueManager->getTicketNumber: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			} 

			// return the ticket number
			return $conn->insert_id;

		}

		// Check if we are at the front of the queue
		function checkFrontOfQueue($queueName, $ticketNumber) {

			if(!is_numeric($ticketNumber)) {
				throw new Exception('queueManager->checkFrontOfQueue: '."Error: Expected a numeric ticket number, but did not get one.");
			}

			// Clean the queue before we check it
			$this->cleanQueue($queueName);

			
			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT queue_ticket_id FROM queue_tickets WHERE queue_name='".$conn->real_escape_string($queueName)."' AND queue_ticket_id=".$ticketNumber;

			if( ! ( $res = $conn->query($sql) ) ) {
				throw new DBException('queueManager->checkFrontOfQueue: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if($res->num_rows == 0) {
				throw new Exception('queueManager->checkFrontOfQueue: '."Error Could not find ticket number ".$ticketNumber." in queue with name: ".$queueName);
			}

			$sql = "SELECT queue_ticket_id FROM queue_tickets WHERE queue_name='".$conn->real_escape_string($queueName)."' ORDER BY queue_ticket_id ASC LIMIT 1";

			if( ! ( $res = $conn->query($sql) ) ) {
				throw new DBException('queueManager->checkFrontOfQueue: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if( ! ( $row = $res->fetch_array() ) ) {
				throw new DBException('queueManager->checkFrontOfQueue: '."Error: An unexpected error occurred when trying to get the state of queue with name: ".$queueName);
			}

			// Check if our ticket number is the one at the front.. true if yes, false if not
			if( $row['queue_ticket_id'] == $ticketNumber ) {
				return true;
			} else {
				return false;
			}

		}

		// Check this queue and remove any entry that belongs to a pid that is not actually running
		function cleanQueue($queueName) {

			
			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT queue_ticket_id, pid FROM queue_tickets WHERE queue_name='".$conn->real_escape_string($queueName)."'";

			if( ! ( $res = $conn->query($sql) ) ) {
				throw new DBException('queueManager->cleanQueue: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			// Iterate over all the queue tickets for this queue...
			while($row = $res->fetch_array() ) {

				// Check if there is an entry in /proc filesystem for the pid
				if(!file_exists('/proc/'.$row['pid']) ) {
					// If not, DELETE this entry from the queue_tickets table
					$sql = "DELETE FROM queue_tickets WHERE queue_ticket_id=".$row['queue_ticket_id'];
					if( ! $conn->query($sql) ) {
						throw new DBException('queueManager->cleanQueue: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
					}
				}				
			}

			return;
		}

		// Release this ticket number
		function releaseTicket($ticketNumber) {

			if(!is_numeric($ticketNumber) ) {
				throw new Exception('queueManager->releaseTicket: '."Error: Expected a numeric ticket number, but did not get one.");
			}

			
			$conn = dbConnection::getInstance($this->log);

			$sql = "DELETE FROM queue_tickets WHERE queue_ticket_id=".$ticketNumber;

			if( ! $conn->query($sql) ) {
				throw new DBException('queueManager->releaseTicket: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			return;
		}

	}



	// A service class to get backupStrategy objects
	class backupStrategyGetter {

		// Set the log stream for this object to use
		function setLogStream($log) {
			$this->log = $log;

		}

		// Fetch a backupStrategy by code
		function getByCode($code) {

			global $config;

			backupStrategy::validateStrategyCode($code);

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_strategy_id FROM backup_strategies WHERE strategy_code='".$conn->real_escape_string($code)."'";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new DBException('backupStrategyGetter->getByCode: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if($res->num_rows != 1 ) {
				return false;
			}

			if( ! ( $row = $res->fetch_array() ) ) {
				throw new Exception('backupStrategyGetter->getByCode: '."Error: Could not retrieve the ID for Backup Strategy with code: $code");
			}

			$strategy = new backupStrategy($row['backup_strategy_id']);
			$strategy->setLogStream($this->log);

			return $strategy;

		}

		// Fetch a backupStrategy by id
		function getById($id) {

			global $config;

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_strategy_id FROM backup_strategies WHERE backup_strategy_id=".$id;

			if( ! ($res = $conn->query($sql) ) ) {
				throw new DBException('backupStrategyGetter->getById: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if($res->num_rows != 1 ) {
				return false;
			}

			$strategy = new backupStrategy($id);
			$strategy->setLogStream($this->log);

			return $strategy;
		}
		
	}

	// Little support class to provide readline functionality in systems where there is no readline built in.
	class inputReader {

		function readline($prompt="") {

			echo $prompt;

			return rtrim(fgets(STDIN));


		}

	}

?>
