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


	class host {


		function __construct($id) {
			if(!is_numeric($id) ) {
				throw new Exception('host->__construct: '."Error: The ID for this object is not an integer.");
			}
			$this->id = $id;
			$this->active = NULL;
			$this->log = false;
		}

		// Set the logStream to write out to
		function setLogStream($log) {
			$this->log = $log;
		}

		// Get info about this host
		function getInfo() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('host->getInfo: '."Error: The ID for this object is not an integer.");
			}


			


			$conn = dbConnection::getInstance($this->log);


			$sql = "SELECT * FROM hosts WHERE host_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('host->getInfo: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
	
			$info = $res->fetch_array();

			return $info;

		}

		// Get scheduled backups
		function getScheduledBackups() {

			global $config;

			

			$conn = dbConnection::getInstance($this->log);


			$sql = "SELECT scheduled_backup_id FROM scheduled_backups WHERE host_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('host->getScheduledBackups: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$scheduledBackups = Array();
			while($row = $res->fetch_array() ) {
				$scheduledBackups[] = new scheduledBackup($row['scheduled_backup_id']);
			}

			return $scheduledBackups;

		}

		// Return true or false 
		function isActive() {

			$info = $this->getInfo();

			if($info['active'] == 'Y') {
				return true;
			} else {
				return false;
			}

		}


		// Get the running backups for this host
		function getRunningBackups() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('host->getRunningBackups: '."Error: The ID for this object is not an integer.");
			}

			$backupGetter = new runningBackupGetter();
			$backupGetter->setLogStream($this->log);

			return $backupGetter->getByHost($this);

		}


		// Static function for validating hostnames
		public static function validateHostname($name) {

			if( ! isSet($name) ) {
				throw new InputException("Error: Expected a hostname as input, but did not get one.");
			}

			if(strlen($name) < 1 || strlen($name) > 255) {
				throw new InputException("Error: Hostname must be between 1 and 255 characters in length.");
			}

			// Hostname validation pattern
			$pattern = '/^(?=.{1,255}$)[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?(?:\.[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?)*\.?$/';

			// Validate
			if(preg_match($pattern, $name) != 1) {
				throw new InputException("Error: The specified hostname is invalid per RFC 2396 Section 3.2.2");
			}

			return;
		}


		// Static function for validating host descriptions
		public static function validateHostDescription($desc) {

			if(!isSet($desc) ) {
				throw new InputException("Error: Expected a description as input, but did not get one.");
			}

			if(strlen($desc) < 1 || strlen($desc) > 256) {
				throw new InputException("Error: Description must be between 1 and 256 characters in length.");
			}

			return;
		}

		public static function validateSSHPort($port) {

			if(!isSet($port)) {
				throw new InputException("Error: Expected an SSH Port number as input, but did not get one.");
			}

			if(!is_numeric($port) || $port < 1 || $port > 65535 ) {
				throw new InputException("Error: SSH Port must be a number between 1 and 65535.");
			}

			return;
		}


		// Delete the host - if it has nothing attached to it
		function delete() {

			// Validate this...
			if(!is_numeric($this->id)) {
				throw new Exception('host->delete: '."Error: The ID for this object is not an integer.");
			}

			$backups = $this->getScheduledBackups();

			// If nothing linked to this volume, just delete it
			if(sizeOf($backups) == 0) {

				
				$conn = dbConnection::getInstance($this->log);

				$sql = "DELETE FROM hosts WHERE host_id=".$this->id;

				if( ! $conn->query($sql) ) {
					throw new DBException('host->delete: '."Error Query $sql \nFailed with MySQL Error: $conn->error");
				}

				unset($this->id);
				// Return.. we're done
				return;
			}

			// We have backups linked to this volume
			// Collect and print the information ...

			$info = $this->getInfo();

			$errMsg = 'Error: Unable to delete the Host with hostname: '.$info['hostname']."\n\n".$this->getScheduledBackupDisplay();

			throw new ProcessingException($errMsg);

		}


		// Get a displayed list of scheduled backups
		// Get a message to print the scheduled backups linked to this volume
		function getScheduledBackupDisplay() {

			// Validate this...
			if(!is_numeric($this->id)) {
				throw new Exception('host->getScheduledBackupDisplay: '."Error: The ID for this object is not an integer.");
			}

			$backups = $this->getScheduledBackups();

			$errMsg = "The following Scheduled Backup(s) are configured for this host:\n\n";

			foreach( $backups as $backup ) {
				$backupInfo = $backup->getInfo();
				$errMsg .= "  Scheduled Backup: ".$backupInfo['name']."\n";
			}

			return $errMsg;
		}

		// Validate input for ACTIVE Y or N
		public static function validateActive($active) {

			// Check that we got a value
			if(!isSet($active) ) {
				throw new InputException("Error: Expected a parameter as input for active status, but did not get one.");
			}

			// Validate
			if( strtoupper($active) == 'Y' || strtoupper($active) == 'N' ) {
				return;
			} else {
				throw new InputException("Error: Active parameter for a host must be either Y or N.");
			}

		}

		// Validate input for staging path
		public static function validateStagingPath($path) {

			// Check that we got a value
			if(!isSet($path)) {
				throw new InputException("Error: Expected a parameter as input for staging path, but did not get one.");
			}

			// Check length range
			if(strlen($path) < 0 || strlen($path) > 1024 ) {
				throw new InputException("Error: Staging path for a host must be between 1 and 1024 charaters in length.");
			}

			return;

		}

		// Set Param to value for the hose
		function setParam($param, $value) {

			// Validate this...
			if(!is_numeric($this->id)) {
				throw new Exception('host->getScheduledBackupDisplay: '."Error: The ID for this object is not an integer.");
			}

			
			$conn = dbConnection::getInstance($this->log);

			switch(strtolower($param)) {

				case 'hostname':
					self::validateHostname($value);
					$backups = $this->getScheduledBackups();
					if(sizeOf($backups) > 0) {
						// We have backups linked to this volume
						// Collect and print the information ...

						$info = $this->getInfo();
						$errMsg = 'Error: Unable to edit the hostname for host with hostname: '.$info['hostname']."\n\n".$this->getScheduledBackupDisplay();
						throw new ProcessingException($errMsg);

					}

					$sql = "UPDATE hosts SET hostname='".$conn->real_escape_string($value)."' WHERE host_id=".$this->id;
				break;

				case 'description':
					self::validateHostDescription($value);
					$sql = "UPDATE hosts SET description='".$conn->real_escape_string($value)."' WHERE host_id=".$this->id;
				break;

				case 'ssh_port':
					self::validateSSHPort($value);
					$sql = "UPDATE hosts SET ssh_port=".$value." WHERE host_id=".$this->id;
				break;

				case 'active':
					self::validateActive($value);
					$sql = "UPDATE hosts SET active='".$conn->real_escape_string(strtoupper($value))."' WHERE host_id=".$this->id;
				break;

				case 'staging_path':
					self::validateStagingPath($value);
					$sql = "UPDATE hosts SET staging_path='".$conn->real_escape_string($value)."' WHERE host_id=".$this->id;
				break;

				default:
					throw new InputException("Error: Unknown Host parameter: ".$param);
				break;
			}

			if( ! $conn->query($sql) ) {
				throw new DBException('host->setParam: '."Error: Query $sql \nFailed with MySQL Error: $conn->error");
			}

			return;

		}

	}

?>
