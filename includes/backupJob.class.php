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


	class backupJob {


		function __construct($id) {
			if(!is_numeric($id) ) {
				throw new Exception('backupJob->__construct: '."Error: The ID for this object is not an integer.");
			}
			$this->id = $id;
			$this->log = false;
		}

		// Set the logStream to write out to
		function setLogStream($log) {
			$this->log = $log;
		}

		// Get info about this backup job
		function getInfo() {


			if(!is_numeric($this->id)) {
				throw new Exception('backupJob->getInfo: '."Error: The ID for this object is not an integer.");
			}


			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT * FROM backup_jobs WHERE backup_job_id=".$this->id;

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('backupJob->getInfo: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
	
			$info = $res->fetch_array();

			return $info;

		}

		// Get the scheduled backup associated with this job
		function getScheduledBackup() {

			if(!is_numeric($this->id)) {
				throw new Exception('backupJob->getInfo: '."Error: The ID for this object is not an integer.");
			}
			

			$info = $this->getInfo();

			$scheduledBackupGetter = new scheduledBackupGetter();
			$scheduledBackupGetter->setLogStream($this->log);

			return $scheduledBackupGetter->getById($info['scheduled_backup_id']);

		}

		// Set the status of the backup job
		function setStatus($status = false) {

			if(!is_numeric($this->id)) {
				throw new Exception('backupJob->setStatus: '."Error: The ID for this object is not an integer.");
			}

			if($status == false || strlen($status) < 1) {
				throw new Exception('backupJob->setStatus: '."Error: Expected a valid status to set the backup job to, but did not get one.");
			}

			$conn = dbConnection::getInstance($this->log);

			$sql = "UPDATE backup_jobs SET status='".$conn->real_escape_string($status)."' WHERE backup_job_id=".$this->id;

			if( ! $conn->query($sql) ) {
				throw new DBException('backupJob->setStatus: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			return;	

		}


		// Mark this job as killed - this flag is monitored during sleep/wait loops 
		// and allows us to throw exceptions to abort the job
		function setKilled( $bool = true ) {

			if(!is_numeric($this->id) ) {
				throw new Exception('backupJob->setKilled: '."Error: The ID for this object is not an integer.");
			}

			$conn = dbConnection::getInstance($this->log);

			if($bool) {
				$killed = 1;
			} else {
				$killed = 0;
			}

			$sql = "UPDATE backup_jobs SET killed = ".$killed." WHERE backup_job_id=".$this->id;

			if( ! $conn->query($sql) ) {
				throw new DBException('backupJob->setKilled: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			return;
		}

		// Check to see if this job is marked as killed or not.
		function isKilled() {

			$info = $this->getInfo();

			if($info['killed'] == true ) {
				return true;
			} else {
				return false;
			}

		}


		// Check to see if this job is actually running
		function isRunning() {

			$info = $this->getInfo();

			$endStates = array('Killed', 'Completed', 'Failed');

			if(!in_array($info['status'], $endStates) ) {
				return true;
			} else {
				return false;
			}

		}


	}

?>
