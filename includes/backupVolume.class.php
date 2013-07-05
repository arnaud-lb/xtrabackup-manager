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


	class backupVolume {


		function __construct($id) {

			if(!is_numeric($id) ) {
				throw new Exception('backupVolume->__construct: '."Error: The ID for this object is not an integer.");
			}

			$this->id = $id;
			$this->log = false;
		}


		function setLogStream($log) {
			$this->log = $log;
		}

		// Get the info for this backup volume
		function getInfo() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('backupVolume->getInfo: '."Error: The ID for this object is not an integer.");
			}


			


			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT * FROM backup_volumes WHERE backup_volume_id=".$this->id;

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('backupVolume->getInfo: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
	
			$info = $res->fetch_array();

			return $info;

		}

		// Set the param of this Backup Volume to value
		function setParam($param, $value) {

			if(!is_numeric($this->id)) {
				throw new Exception('backupVolume->setParam: '."Error: The ID for this object is not an integer.");
			}


			
			$conn = dbConnection::getInstance($this->log);

			switch(strtolower($param)) {

				case 'name':
					// Validate input
					self::validateName($value);
					$sql = "UPDATE backup_volumes SET name='".$conn->real_escape_string($value)."' WHERE backup_volume_id=".$this->id;

				break;

				case 'path':
					// Validate input
					self::validatePath($value);
					$backups = $this->getScheduledBackups();
					if(sizeOf($backups) > 0 ) {
						$info = $this->getInfo();
						$errMsg = 'Error: Unable to edit the path of Backup Volume with name: '.$info['name']."\n\n".$this->getScheduledBackupDisplay();
						throw new ProcessingException($errMsg);
					}

					$sql = "UPDATE backup_volumes SET path='".$conn->real_escape_string($value)."' WHERE backup_volume_id=".$this->id;
				break;

				default:
					throw new InputException("Error: Unknown Backup Volume parameter: ".$param);
				break;

			}

			// Query DB
			if( ! $conn->query($sql) ) {
				throw new DBException('backupVolume->setParam: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			return;

		}

		// Validate a Backup Volume Name.
		static function validateName($name) {

			if(!isSet($name)) {
				throw new InputException("Error: Expected a Backup Volume name as input, but did not get one.");
			}

			if(strlen($name) < 1 || strlen($name) > 128) {
				throw new InputException("Error: Backup Volume name must be between 1 and 128 characters long.");
			}

			return;
		}

		// Validate a Backup Volume Path.
		static function validatePath($volumePath) {

			// Path length
			if(strlen($volumePath) > 1024 || strlen($volumePath) < 1 ) {
				throw new InputException("Error: Backup Volume path must be between 1 and 1024 characters in length.");
			}

			// Is the path an actual dir
			if(!is_dir($volumePath) ) {
				throw new InputException("Error: Backup Volume path must be a valid directory -- Path: ".$volumePath);
			}

			return;
		}


		function delete() {

			// Validate this...
			if(!is_numeric($this->id)) {
				throw new Exception('backupVolume->delete: '."Error: The ID for this object is not an integer.");
			}

			$backups = $this->getScheduledBackups();

			// If nothing linked to this volume, just delete it
			if(sizeOf($backups) == 0) {

				
				$conn = dbConnection::getInstance($this->log);

				$sql = "DELETE FROM backup_volumes WHERE backup_volume_id=".$this->id;

				if( ! $conn->query($sql) ) {
					throw new DBException('backupVolume->delete: '."Error Query $sql \nFailed with MySQL Error: $conn->error");
				}

				unset($this->id);
				// Return.. we're done
				return;
			}

			// We have backups linked to this volume
			// Collect and print the information ...

			$info = $this->getInfo();
			
			$errMsg = 'Error: Unable to delete the Backup Volume with name: '.$info['name']."\n\n".$this->getScheduledBackupDisplay();

			throw new ProcessingException($errMsg);

		}

		// Get the scheduled backups that are linked to this backup volume
		function getScheduledBackups() {

			// Validate this...
			if(!is_numeric($this->id)) {
				throw new Exception('backupVolume->getScheduledBackups: '."Error: The ID for this object is not an integer.");
			}

			global $config;

			
			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT scheduled_backup_id FROM scheduled_backups WHERE backup_volume_id=".$this->id;

			if( ! ( $res = $conn->query($sql) ) ) {
				throw new DBException('backupVolume->getScheduledBackups: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$backupGetter = new scheduledBackupGetter();
			$backupGetter->setLogStream($this->log);

			$backups = Array();
			while($row = $res->fetch_array() ) {
				$backups[] = $backupGetter->getById($row['scheduled_backup_id']);
			}

			return $backups;
		}

		// Get a message to print the scheduled backups linked to this volume
		function getScheduledBackupDisplay() {
			// Validate this...
			if(!is_numeric($this->id)) {
				throw new Exception('backupVolume->getScheduledBackupDisplay: '."Error: The ID for this object is not an integer.");
			}

			$backups = $this->getScheduledBackups();

			$errMsg = "The following Scheduled Backup(s) are configured to use it for storage:\n\n";

			foreach( $backups as $backup ) {
				$backupInfo = $backup->getInfo();
				$backupHost = $backup->getHost();
				$hostInfo = $backupHost->getInfo();
				$errMsg .= "  Host: ".$hostInfo['hostname']."  Scheduled Backup: ".$backupInfo['name']."\n";
			}

			return $errMsg;
		}

	}

?>
