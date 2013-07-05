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


	class runningBackup {


		function __construct($id = false) {
			if( ($id !== false) && !is_numeric($id) ) {
				throw new Exception('runningBackup->__construct: '."Error: Expected an integer id for this object and did not get one.");
			}
			$this->id = $id;
			$this->log = false;
		}

		function setLogStream($log) {
			$this->log = $log;
		}

		function setInfoLogStream($log) {
			$this->infolog = $log;
		}

		// Atempt to initialize a running backup
		function init($host, $scheduledBackup) {

			// create a port finder object
			// portFound = false
			// while portFound = false
			// get available port number
			// attempt to create running backup entry with that port number
			// if success, then portFound = true
			// end while

			


			$conn = dbConnection::getInstance($this->log);


			$portFinder = new portFinder();

			$attempts = 0;

			if($this->infolog !== false) 
				$this->infolog->write("Attempting to find available ports for use...", XBM_LOG_INFO);

			while( $attempts < 5 ) {

				$attempts++;

				$portFinder->findAvailablePort();

				// If we didn't get a port for some reason, try again
				if( $portFinder->availablePort === false ) {

					if($this->infolog !== false) 
						$this->infolog->write("Attempted to acquire an available port with portFinder, unsuccessfully. Sleeping ".XBM_SLEEP_SECS." secs before trying again...", XBM_LOG_INFO);

					sleep(XBM_SLEEP_SECS);
					continue;
				}


				$sql = "INSERT INTO running_backups (host_id, scheduled_backup_id, port, pid) VALUES (".$host->id.", ".$scheduledBackup->id.", ".$portFinder->availablePort.", ".getmypid().")";

				if( ! $conn->query($sql) ) {


					// This is hacky as it relies on the order of the keys, but basically...
					// If we get a dupe key error on the port field, we'll try again, otherwise, consider it fatal.
					if($conn->errno == 1062 && stristr($conn->error, 'key 2')) {
						// If log enabled - write info to it...
						if($this->infolog !== false) 
							$this->infolog->write("Attempted to lock port ".$portFinder->availablePort." by creating runningBackup, but somebody snatched it. Sleeping ".XBM_SLEEP_SECS." secs before retry...", XBM_LOG_INFO);

						sleep(XBM_SLEEP_SECS);
						continue;

					} else {
						throw new Exception('runningBackuup->init: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
					}
				}

				if($this->infolog !== false) {
					$this->infolog->write("Got lock on port ".$portFinder->availablePort.".", XBM_LOG_INFO);
				}

				$this->id = $conn->insert_id;
				return true;

			}

			if($this->infolog !== false ) {
				$this->infolog->write("Was unable to allocate a port for the backup after $attempts attempts. Giving up!", XBM_LOG_ERROR);
			}

			throw new Exception('runningBackup->init: '."Error: Was unable to allocate a port for the backup after $attempts attempts. Gave up!");

		}


		function getInfo() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('runningBackup->getInfo: '."Error: The ID for this object is not an integer.");
			}


			


			$conn = dbConnection::getInstance($this->log);


			$sql = "SELECT * FROM running_backups WHERE running_backup_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('runningBackup->getInfo: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
	
			$info = $res->fetch_array();

			return $info;

		}


		// Clean up the running backup entry
		function finish() {

			global $config;

			if(!is_numeric($this->id) ) {
				throw new Exception('runningBackup->finish: '."Error: The ID for this object is not an integer.");
			}

			


			$conn = dbConnection::getInstance($this->log);

			$info = $this->getInfo();


			// If we have a staging tmpdir -- try to remove it
			if( isSet($this->remoteTempDir) && is_object($this->remoteTempDir) ) {

				$this->remoteTempDir->destroy();

			}

			$sql = "DELETE FROM running_backups WHERE running_backup_id=".$this->id;

			if( ! $conn->query($sql) ) {
				throw new Exception('runningBackup->finish: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$this->infolog->write("Released lock on port ".$info['port'].".", XBM_LOG_INFO);

			return true;

		}


		// Set the staging tmpdir in the running backup object
		function getStagingTmpdir() {

			global $config;

			if(!is_numeric($this->id) ) {
				throw new Exception('runningBackup->getStagingTmpdir: '."Error: The ID for this object is not an integer.");
			}

			


			$conn = dbConnection::getInstance($this->log);


			$info = $this->getInfo();

			// Collect the info we need to connect to the remote host 
			$backupGetter = new scheduledBackupGetter();

			$scheduledBackup = $backupGetter->getById($info['scheduled_backup_id']);

			$sbInfo = $scheduledBackup->getInfo();

			$host = $scheduledBackup->getHost();

			$hostInfo = $host->getInfo();

			$this->remoteTempDir = new remoteTempDir();

			$tempDir = $this->remoteTempDir->init($hostInfo['hostname'], $hostInfo['ssh_port'], $sbInfo['backup_user'], $hostInfo['staging_path'], 'xbm-');

			// Put the path into the DB

			$sql = "UPDATE running_backups SET staging_tmpdir='".$conn->real_escape_string($tempDir)."' WHERE running_backup_id=".$this->id;

			if( ! $conn->query($sql) ) {
				throw new Exception('runningBackup->getStagingTmpdir: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			return $tempDir;

		}

	}

?>
