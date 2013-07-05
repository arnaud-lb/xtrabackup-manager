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


	class scheduledBackup {


		function __construct($id) {
			if(!is_numeric($id)) {
				throw new Exception('scheduledBackup->__construct'."Error: Expected a numeric ID for this object and did not get one.");
			}
			$this->id = $id;
			$this->active = NULL;
			$this->inactive_reason = '';
			$this->log = false;
			$this->isRunning = NULL;
			$this->runningBackups = Array();
		}

		function setLogStream($log) {
			$this->log = $log;
		}

		function getInfo() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->getInfo: '."Error: The ID for this object is not an integer.");
			}


			


			$conn = dbConnection::getInstance($this->log);


			$sql = "SELECT sb.*, bs.strategy_code, bs.strategy_name FROM scheduled_backups sb JOIN backup_strategies bs ON sb.backup_strategy_id=bs.backup_strategy_id WHERE scheduled_backup_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackup->getInfo: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
	
			if( ! ($info = $res->fetch_array() ) ) {
				throw new Exception('scheduledBackup->getInfo: '."Error: Could not getInfo for this Scheduled Backup Task. No entry found -- Perhaps this Scheduled Backup Task was deleted.");
			}

			return $info;

		}

		// Sets this->active to true or false based on logic.
		function isActive() {

			$info = $this->getInfo();

			// Check first if this scheduled backup is active
			if($info['active'] != 'Y') {

				$this->inactive_reason = 'The Scheduled Backup is not active.';
				return false;
			} else {

				// Then check to make sure that the host is active
				$host = $this->getHost();

				// Poll host being active...
					// If it's not active..
				if( $host->isActive() == false ) {
					$this->inactive_reason = 'The Host is not active.';
					return false;
				} else {
				// Otherwise..
					$this->inactive_reason = NULL;
					return true;
				}


			}
			
		}


		// Get the host object that this scheduledBackup is for	
		function getHost() {

			$info = $this->getInfo();

			$hostGetter = new hostGetter();
			$host = $hostGetter->getById($info['host_id']);

			return $host;

		}

		// Get the backupStrategy object that this scheduledBackup is set to use
		function getBackupStrategy() {

			$info = $this->getInfo();

			$backupStrategyGetter = new backupStrategyGetter();
			$backupStrategyGetter->setLogStream($this->log);
			$strat = $backupStrategyGetter->getById($info['backup_strategy_id']);

			return $strat;

		}


		// Get the volume object that this scheduledBackup is stored on
		function getVolume() {

			$info = $this->getInfo();

			$volumeGetter = new volumeGetter();
			$volume = $volumeGetter->getById($info['backup_volume_id']);

			return $volume;

		}


		// Get the name of the command that should be used for xtrabackup
		// based on the configured mysql_type of this scheduledBackup
		function getXtraBackupBinary() {

			$info = $this->getInfo();

			$mysqlTypeGetter = new mysqlTypeGetter();
			$mysqlTypeGetter->setLogStream($this->log);

			$mysqlType = $mysqlTypeGetter->getById($info['mysql_type_id']);

			$mysqlTypeInfo = $mysqlType->getInfo();

			return $mysqlTypeInfo['xtrabackup_binary'];
		}



		// Return the valid seed of this scheduledBackup or false otherwise
		// This should be replaced by use of snapshotGroup->getSeed()
		function getSeed() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->getSeed: '."Error: The ID for this object is not an integer.");
			}   

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE scheduled_backup_id=".$this->id." AND type='SEED' AND status='COMPLETED'";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackup->getSeed: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if( $res->num_rows > 1 ) {
				throw new Exception('scheduledBackup->getSeed: '."Error: Found more than one valid seed for this backup. This should not happen.");
			} elseif( $res->num_rows == 1 ) {
				$row = $res->fetch_array();
				$snapshotGetter = new backupSnapshotGetter();
				return $snapshotGetter->getById($row['backup_snapshot_id']);
			} elseif( $res->num_rows == 0 ) {
				return false;
			}

			throw new Exception('scheduledBackup->getSeed: '."Error: Failed to determine if there was a valid seed and return it. This should not happen.");

		}

		// Return the number of completed snapshots for this scheduledBackup
		function getCompletedSnapshotCount() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->getCompletedSnapshotCount: '."Error: The ID for this object is not an integer.");
			}

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE scheduled_backup_id=".$this->id." AND status='COMPLETED'";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackup->getCompletedSnapshotCount: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			return $res->num_rows;

		}

		// Get an array of the snapshot groups for the scheduledBackup
		function getSnapshotGroupsNewestToOldest() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->getSnapshotGroupsNewestToOldest: '."Error: The ID for this object is not an integer.");
			}

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT DISTINCT snapshot_group_num FROM backup_snapshots WHERE scheduled_backup_id=".$this->id." AND status='COMPLETED' ORDER BY snapshot_group_num DESC";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackup->getSnapshotGroupsNewestToOldest: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$groups = Array();
			while($row = $res->fetch_array() ) {
				$groups[] = new backupSnapshotGroup($this->id, $row['snapshot_group_num']);
			}

			// If there are no groups in the DB, manually inject the initial group number 1...
			if(sizeOf($groups) == 0 ) {
				$groups[] = new backupSnapshotGroup($this->id, 1);
			}


			return $groups;

		}


		// Check to see if there is a running backup entry already for this scheduled backup
		function isRunning() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->isRunning: '."Error: The ID for this object is not an integer.");
			}   
			

			$backupGetter = new runningBackupGetter();
			$backupGetter->setLogStream($this->log);

			$this->runningBackups = $backupGetter->getByScheduledBackup($this);
	
			if( sizeOf($this->runningBackups) == 0 ) {
				return false;
			} elseif( sizeOf($this->runningBackups) > 0 ) {
				return true;
			}

			
		}



		// Get the most recently completed scheduled backup snapshot
		function getMostRecentCompletedBackupSnapshot() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->getMostRecentCompletedBackupSnapshot: '."Error: The ID for this object is not an integer.");
			}

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE status='COMPLETED' AND scheduled_backup_id=".$this->id." ORDER BY snapshot_time DESC LIMIT 1";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackup->getMostRecentCompletedBackupSnapshot: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if( $res->num_rows != 1 ) {
				throw new Exception('scheduledBackup->getMostRecentCompletedBackupSnapshot: '."Error: Could not find the most recent backup snapshot for Scheduled Backup ID ".$this->id);
			}

			$row = $res->fetch_array();

			$snapshotGetter = new backupSnapshotGetter();
			$snapshot = $snapshotGetter->getById($row['backup_snapshot_id']);

			return $snapshot;
		}


		// Get the most recently completed materialized snapshot
		function getMostRecentCompletedMaterializedSnapshot() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->getMostRecentCompletedMaterializedSnapshot: '."Error: The ID for this object is not an integer.");
			}

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT materialized_snapshot_id FROM materialized_snapshots WHERE status='COMPLETED' AND scheduled_backup_id=".$this->id." ORDER BY creation_time DESC LIMIT 1";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackup->getMostRecentCompletedMaterializedSnapshot: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if( $res->num_rows < 1 ) {
				return false;
			}

			$row = $res->fetch_array();

			$snapshotGetter = new materializedSnapshotGetter();
			$snapshot = $snapshotGetter->getById($row['materialized_snapshot_id']);

			return $snapshot;
		}


		// Get an array list of the scheduledBackup parameters
		function getParameters() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->getParameters: '."Error: The ID for this object is not an integer.");
			}

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT bsp.param_name, sbp.param_value FROM 
						scheduled_backups sb JOIN backup_strategy_params bsp 
							ON sb.backup_strategy_id = bsp.backup_strategy_id 
						JOIN scheduled_backup_params sbp
							ON sbp.scheduled_backup_id = sb.scheduled_backup_id AND 
								bsp.backup_strategy_param_id = sbp.backup_strategy_param_id
					WHERE
						sb.scheduled_backup_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('scheduledBackup->getParameters: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$params = Array();
			while( $row = $res->fetch_array() ) {
				$params[$row['param_name']] = $row['param_value'];
			}


			return $params;

		}

		// Validate a scheduledBackup name
		public static function validateName($name) {

			if(!isSet($name) ) {
				throw new InputException("Error: Expected a Scheduled Backup name as input, but did not get one.");
			}

			if(strlen($name) < 1 || strlen($name) > 128 ) {
				throw new InputException("Error: Scheduled Backup name must be between 1 and 128 characters in length.");
			}

			return;
		}

		// Validate a cronExpression
		public static function validateCronExpression($cron) {

			// Check that we got a value
			if(!isSet($cron) ) {
				throw new InputException("Error: Expected a cron expression as a parameter, but did not get one.");
			}

			// Build the cron validator regexp
			// Adapted from code by Jordi Salvat i Alabart - with thanks to www.salir.com

			$numbers= array(
				'min'=>'[0-5]?\d',
				'hour'=>'[01]?\d|2[0-3]',
				'day'=>'0?[1-9]|[12]\d|3[01]',
				'month'=>'[1-9]|1[012]',
				'dow'=>'[0-7]'
			);

			foreach($numbers as $field=>$number) {
				$range= "($number)(-($number)(\/\d+)?)?";
				$field_re[$field]= "\*(\/\d+)?|$range(,$range)*";
			}

			$field_re['month'].='|jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec';
			$field_re['dow'].='|mon|tue|wed|thu|fri|sat|sun';

			$fields_re= '('.join(')\s+(', $field_re).')';

			$replacements= '@reboot|@yearly|@annually|@monthly|@weekly|@daily|@midnight|@hourly';

			$regExp = '^('.
					"$fields_re".
					"|($replacements)".
				')$';

			// Validate the cron
			if(preg_match("/$regExp/", $cron) != 1) {
				throw new InputException("Error: The cron expression given is invalid.");
			}

			return;

		}

		// Validate a datadir path
		public static function validateDatadirPath($path) {

			if(!isSet($path) ) {
				throw new InputException("Error: Expected a datadir as a parameter, but did not get one.");
			}

			if(strlen($path) < 1 || strlen($path) > 1024 ) {
				throw new InputException("Error: Datadir must be between 1 and 1024 characters in length.");
			}

			return;

		}

		// Validate a mysql user
		public static function validateMysqlUser($user) {

			if(!isSet($user) ) {
				throw new InputException("Error: Expected a MySQL user as a parameter, but did not get one.");
			}

			if(strlen($user) < 1 || strlen($user) > 16) {
				throw new InputException("Error: MySQL user name must be between 1 and 16 characters in length.");
			}

			return;
		}

		// Validate a mysql password
		public static function validateMysqlPass($pass) {

			if(!isSet($pass)) {
				throw new InputException("Error: Expected a MySQL password as a parameter, but did not get one.");
			}

			if(strlen($pass) < 1 || strlen($pass) > 256 ) {
				throw new InputException("Error: MySQL password must be between 1 and 256 characters in length.");
			}

			return;
		}

		// Validate a backup user
		public static function validateBackupUser($user) {

			if(!isSet($user)) {
				throw new InputException("Error: Expected a Backup User as a parameter, but did not get one.");
			}

			if(strlen($user) < 1 || strlen($user) > 256 ) {
				throw new InputException("Error: Backup User must be between 1 and 256 characters in length.");
			}

			return;

		}

		// Validate a Yes/No
		public static function validateYesNo($param) {

			if(!isSet($param)) {
				throw new InputException("Error: Expected a Y or N as an active flag, but did not get one.");
			}

			if( ! in_array($param, array('Y', 'N') ) ) {
				throw new InputException("Error: Actuve flag must be either Y or N.");
			}

			return;

		}

		// Validate Throttle value
		public static function validateThrottle($param) {

			if(!isSet($param)) {
				throw new InputException("Error: Expected a throttle value as a parameter, but did not get one.");
			}

			if(!is_numeric($param) || ! ( $throttle >= 0) ) {
				throw new InputException("Error: Throttle value must be numeric and greater than or equal to 0.");
			}

		}

		// Validate a backup user
		public static function validateActive($param) {

			self::validateYesNo($param);

			return;

		}

		// Validate a backup user
		public static function validateLockTables($param) {

			self::validateYesNo($param);

			return;

		}

		// Validate rotate_day_of_week parameter
		public static function validateRotateDayOfWeek($param) {
			$dayOfWeekRegex = '/^[0-6](,[0-6])*$/';
			
			if( !isSet($param) || preg_match($dayOfWeekRegex, $param) !== 1 ) {
				throw new InputException("Error: rotate_day_of_week must be a comma separated list of integers between 0 and 6. 0=Sunday .. 6=Saturday.");
			}

		}

		// Validate rotate_method
		public static function validateRotateMethod($param) {
			$validRotationMethods = Array('DAY_OF_WEEK', 'AFTER_SNAPSHOT_COUNT');
			if(!in_array($param, $validRotationMethods)) {
				throw new InputException("Error: rotate_method must be defined as one of: ".implode($validRotationMethods, ','));
			}
		}

		// Validate maintain_materialized_copy
		public static function validateMaintainMaterializedCopy($param) {
			// must be 0 or 1
			if( preg_match('/^[01]$/', $param) !== 1 ) {
				throw new InputException("Error: maintain_materialized_copy must be set to either 1=Yes or 0=No.");
			}

		}

		// Validate max_snapshots
		public static function validateMaxSnapshots($param) {
			// must be set
			if(!isSet($param) ) {
				throw new InputException("Error: max_snapshots must be set when using DAY_OF_WEEK rotate_method.");
			}
			// must be numeric...	   
			if(!is_numeric($param) ) {
				throw new InputException("Error: max_snapshots must be numeric.");
			}
			// must be >= 1
			if($param < 1) {
				throw new InputException("Error: max_snapshots must be set to a value >= 1.");
			}
		}

		// Validate max_snapshots_per_group
		public static function validateMaxSnapshotsPerGroup($param) {
			// must be set
			if(!isSet($param) ) {
				throw new InputException("Error: max_snapshots_per_group must be set when using DAY_OF_WEEK rotate_method.");
			}
			// must be numeric...	   
			if(!is_numeric($param) ) {
				throw new InputException("Error: max_snapshots_per_group must be numeric.");
			}
			// must be >= 1
			if($param < 1) {
				throw new InputException("Error: max_snapshots_per_group must be set to a value >= 1.");
			}
		}

		// Validate backup_skip_fatal
		public static function validateBackupSkipFatal($param) {
			// Value must be either 0 or 1.
			if( preg_match('/^[01]$/', $param) !== 1 ) {
				throw new InputException("Error: backup_skip_fatal must be set to either 1=Yes or 0=No.");
			}
		}

		// Validate rotate_snapshot_no
		public static function validateRotateSnapshotNo($param) {
			// must be set
			if(!isSet($param) ) {
				throw new InputException("Error: rotate_snapshot_no must be set when using DAY_OF_WEEK rotate_method.");
			}
			// must be numeric...	   
			if(!is_numeric($param) ) {
				throw new InputException("Error: rotate_snapshot_no must be numeric.");
			}
			// must be >= 1
			if($param < 1) {
				throw new InputException("Error: rotate_snapshot_no must be set to a value >= 1.");
			}
		}

		// Validate max_snapshot_groups
		public static function validateMaxSnapshotGroups($param) {
			// must be set
			if(!isSet($param) ) {
				throw new InputException("Error: max_snapshot_groups must be set when using DAY_OF_WEEK rotate_method.");
			}
			// must be numeric...	   
			if(!is_numeric($param) ) {
				throw new InputException("Error: max_snapshot_groups must be numeric.");
			}
			// must be >= 1
			if($param < 1) {
				throw new InputException("Error: max_snapshot_groups must be set to a value >= 1.");
			}
		}

		// Set Param to value for the scheduledBackup
		function setParam($param, $value) {

			// Validate this...
			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->setParam: '."Error: The ID for this object is not an integer.");
			}

			
			$conn = dbConnection::getInstance($this->log);

			switch(strtolower($param)) {

				case 'name':
					self::validateName($value);
					$sql = "UPDATE scheduled_backups SET name='".$conn->real_escape_string($value)."' WHERE scheduled_backup_id=".$this->id;
				break;

				case 'cron_expression':
					self::validateCronExpression($value);
					$sql = "UPDATE scheduled_backups SET cron_expression='".$conn->real_escape_string($value)."' WHERE scheduled_backup_id=".$this->id;
				break;

				case 'backup_user':
					self::validateBackupUser($value);
					$sql = "UPDATE scheduled_backups SET backup_user='".$conn->real_escape_string($value)."' WHERE scheduled_backup_id=".$this->id;
				break;

				case 'datadir_path':
					self::validateDatadirPath($value);
					$sql = "UPDATE scheduled_backups SET datadir_path='".$conn->real_escape_string($value)."' WHERE scheduled_backup_id=".$this->id;
				break;

				case 'mysql_user':
					self::validateMysqlUser($value);
					$sql = "UPDATE scheduled_backups SET mysql_user='".$conn->real_escape_string($value)."' WHERE scheduled_backup_id=".$this->id;
				break;

				case 'mysql_password':
					self::validateMysqlPass($value);
					$sql = "UPDATE scheduled_backups SET mysql_password='".$conn->real_escape_string($value)."' WHERE scheduled_backup_id=".$this->id;
				break;

				case 'lock_tables':
					// Force param to upper case..
					$value = strtoupper($value);
					self::validateLockTables($value);
					$sql = "UPDATE scheduled_backups SET lock_tables='".$conn->real_escape_string($value)."' WHERE scheduled_backup_id=".$this->id;
				break;

				case 'active':
					// Force param to upper case..
					$value = strtoupper($value);
					self::validateActive($value);
					$sql = "UPDATE scheduled_backups SET active='".$conn->real_escape_string($value)."' WHERE scheduled_backup_id=".$this->id;
				break;

				case 'throttle':
					self::validateThrottle($value);
					$sql = "UPDATE scheduled_backups SET throttle=".$value." WHERE scheduled_backup_id=".$this->id;
				break;

				//
				// Handle the parameters specific to the backup strategy of this backup //
				//
				case 'rotate_day_of_week':
				case 'rotate_method':
				case 'maintain_materialized_copy':
				case 'max_snapshots':
				case 'max_snapshots_per_group':
				case 'backup_skip_fatal':
				case 'rotate_snapshot_no':
				case 'max_snapshot_groups':
					$this->setBackupStrategyParam($param, $value);
					return;
				break;

				default:
					throw new InputException("Error: Unknown Scheduled Backup parameter: ".$param);
				break;
			}

			if( ! $conn->query($sql) ) {
				throw new DBException('scheduledBackup->setParam: '."Error: Query $sql \nFailed with MySQL Error: $conn->error");
			}

			return;

		}

		// Set the backup strategy parameter $param to value $value
		function setBackupStrategyParam($param, $value) {

			// Validate
			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->setBackupStrategyParam: '."Error: The ID for this object is not an integer");
			}

			// Check to make sure that the backup Strategy for this backup supports the specified parameter...
			$validParams = array_keys($this->getParameters());
			if(!in_array($param, $validParams) ) {
				throw new ProcessingException("Error: The specified parameter is not valid for this Scheduled Backup: $param.");
			}

			$sql = "UPDATE
						scheduled_backups sb JOIN backup_strategy_params bsp 
							ON sb.backup_strategy_id = bsp.backup_strategy_id 
						JOIN scheduled_backup_params sbp
							ON sbp.scheduled_backup_id = sb.scheduled_backup_id AND 
								bsp.backup_strategy_param_id = sbp.backup_strategy_param_id ";


			// Handle param
			switch($param) {

				case 'rotate_day_of_week':
					self::validateRotateDayOfWeek($value);

				break;

				case 'rotate_method':
					self::validateRotateMethod($value);
				break;

				case 'maintain_materialized_copy':
					self::validateMaintainMaterializedCopy($value);
				break;

				case 'max_snapshots':
					self::validateMaxSnapshots($value);
				break;

				case 'max_snapshots_per_group':
					self::validateMaxSnapshotsPerGroup($value);
				break;

				case 'backup_skip_fatal':
					self::validateBackupSkipFatal($value);
				break;

				case 'rotate_snapshot_no':
					self::validateRotateSnapshotNo($value);
				break;

				case 'max_snapshot_groups':
					self::validateMaxSnapshotGroups($value);
				break;

				default:
					throw new InputException("Error: Unrecognized Backup Strategy Parameter: $param");

			}

			
			$conn = dbConnection::getInstance($this->log);

			$sql .= " SET sbp.param_value='".$conn->real_escape_string($value)."' ";
			$sql .= " WHERE sb.scheduled_backup_id=".$this->id." AND bsp.param_name='".$conn->real_escape_string($param)."'";

			if( ! $conn->query($sql) ) {
				throw new DBException('scheduledBackup->setParam: '."Error: Query $sql \nFailed with MySQL Error: $conn->error");
			}

			return;
			
		}


		// Destroy this scheduled backup and anything attached to it.
		function destroy() {

			// Validate this...
			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->destroy: '."Error: The ID for this object is not an integer.");
			}

			$queueManager = new queueManager();
			$queueManager->setLogStream($this->log);

			// We need to take over all queues for this backup and make sure nothing is running.
			$queues = Array('scheduledBackup:'.$this->id, 'retentionApply:'.$this->id, 'postProcess:'.$this->id);
			foreach($queues as $queue) {
				$ticket = $queueManager->getTicketNumber($queue);
				if( ! $queueManager->checkFrontOfQueue($queue, $ticket) ) {
					throw new ProcessingException("Error: Cannot remove the Scheduled Backup Task as it is currently running.");
				}
			}


			// Check to see if anything is running for this scheduledBackup
			$runningBackupGetter = new runningBackupGetter();
			$runningBackupGetter->setLogStream($this->log);

			$runningBackups = $runningBackupGetter->getByScheduledBackup($this);

			if(sizeOf($runningBackups) > 0 ) {
				throw new ProcessingException("Error: Cannot remove the Scheduled Backup Task as it is currently running.");
			}

			// Get all snapshots and destroy them..
			$groups = $this->getSnapshotGroupsNewestToOldest();
			foreach( $groups as $group ) {
				$snapshots = $group->getAllSnapshotsNewestToOldest();
				foreach($snapshots as $snapshot) {
					$snapshot->destroy();
				}
			}

			// If we have a materialized snapshot - destroy that too
			if( $latestMaterialized = $this->getMostRecentCompletedMaterializedSnapshot() ) {
				$latestMaterialized->destroy();
			}

			
			$conn = dbConnection::getInstance($this->log);

			// Remove DB the entries for this scheduledBackup
			$sql = "DELETE sb.*, sbp.* FROM scheduled_backups sb JOIN scheduled_backup_params sbp USING (scheduled_backup_id) WHERE scheduled_backup_id = ".$this->id;

			if( ! $conn->query($sql) ) {
				throw new DBException('scheduledBackup->setParam: '."Error: Query $sql \nFailed with MySQL Error: $conn->error");
			}

			return;			

		}

		// Get the throttle value we must pass to XtraBackup
		// Based on my tests:
		// * XtraBackup will burn a 2MB/s just for log scanning
		// * XtraBackup IOPs are 1MB each
		// Thus a minimum value given of 1 will result in 3MB/s of IO
		// When users tell XBM they want to throttle at X MB/s - we take into account this 2MB/s.
		function getXtraBackupThrottleValue() {
			
			// Validate this...
			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->getXtraBackupThrottleValue: '."Error: The ID for this object is not an integer.");
			}

			$info = $this->getInfo();

			// If it is disabled, return 0.
			if($info['throttle'] <= 0 ) {
				return 0;
			}

			// Otherwise figure out what adjusted value to use...

			// Remove the 2MB/sec that is burned for log scanning
			$throttleVal = $info['throttle'] - 2;
			// If our value is less that the minimum throttle we can do, just make it the min.
			if($throttleVal < 1) {
				$throttleVal = 1;
			}
			
			return $throttleVal;

		}

		// Get the throttle setting for this backup in Mbps
		// We store it in the database this way, so simply retrieving from getInfo is enough.
		function getMbpsThrottleValue() {

			// Validate this...
			if(!is_numeric($this->id)) {
				throw new Exception('scheduledBackup->getMbpsThrottleValue: '."Error: The ID for this object is not an integer.");
			}

			// Get info
			$info = $this->getInfo();

			// Return the throttle setting in Mbps
			return $info['throttle'];
			
		}


	} // Class: scheduledBackup

?>
