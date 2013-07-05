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


	class materializedSnapshot {


		function __construct($mbId = false) {

			$this->id = $mbId;
			$this->log = false;
			$this->infolog = false;

		}

		function setLogStream($log) {
			$this->log = $log;
		}

		function setInfoLogStream($log) {
			$this->infolog = $log;
		}

		// Sanity check this object
		function __validate() {
			if(!is_numeric($this->id) ) {
				throw new Exception('materializedSnapshot->__validate: '."Error: The ID for this object is not an integer.");
			}
		}

		// Initialize a new materializedSnapshot object
		function init($scheduledBackup, $backupSnapshot) {

			global $config;

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "INSERT INTO materialized_snapshots (materialized_snapshot_id, scheduled_backup_id, backup_snapshot_id, status) "
					." VALUES (NULL, ".$scheduledBackup->id.", ".$backupSnapshot->id.", 'INITIALIZING' )";

			// If there was an error - throw exception
			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('materializedSnapshot->init: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$this->id = $conn->insert_id;

			// Get the path for this snapshot
			$path = $this->getPath();

			// Create the dir for the hostname/snapshotId
			if(!mkdir($path, 0700, true) ) {
				throw new Exception('materializedSnapshot->init: '."Error: Could not create the directory for this materialized snapshot at ".$path." .");
			}

			return true;

		}


		// Link this materialized snapshots path direct to an existing backupSnapshot
		function symlinkToSnapshot($snapshot) {

			$snapshotPath = $snapshot->getPath();
			$materialPath = $this->getPath();

			if(!rmdir($materialPath)) {
				throw new Exception('materializedSnapshot->symlinkToSnapshot: '."Error: Failed to remove directory $materialPath before replacing it with a symlink to $snapshotPath .");
			}

			if( ! symlink($snapshotPath, $materialPath) ) {
				throw new Exception('materializedSnapshot->symlinkToSnapshot: '."Error: Failed to symlink $snapshotPath to location $materialPath .");
			}

			return true;
		}


		// Return all the info/details for this t
		function getInfo() {

			// sanity check this object
			$this->__validate();

			global $config;

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT * FROM materialized_snapshots WHERE materialized_snapshot_id=".$this->id;

			// If there was an error - throw exception
			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('materializedSnapshot->getInfo: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			// If we got too many rows - throw exception
			if( $res->num_rows > 1 ) {
				throw new Exception('materializedSnapshot->getInfo: '."Error: Found more than one entry for this materialized backup -- this should never happen.");
			}

			// If we dont get ANY rows - throw exception
			if( ! ($info = $res->fetch_array() ) ) {
				throw new Exception('materializedSnapshot->getInfo: '."Error: Could not find an entry for this matrialized backup.");
			}

			// If we made it - return info array`
			return $info;

		}


		// Get the scheduledBackup that this materialized backup belongs to
		function getScheduledBackup() {

			$this->__validate();

			$info = $this->getInfo();

			$scheduledBackupGetter = new scheduledBackupGetter();

			$this->scheduledBackup = $scheduledBackupGetter->getById($info['scheduled_backup_id']);


			return $this->scheduledBackup;

		}

		// get the backup snapshot that this materialized backup is of
		function getBackupSnapshot() {

			$this->__validate();
			$info = $this->getInfo();
			$backupSnapshotGetter = new backupSnapshotGetter();

			$this->backupSnapshot = $backupSnapshotGetter->getById($info['backup_snapshot_id']);

			return $this->backupSnapshot;

		}


		// Get the path for this materialized backup
		// Just a folder in the hosts backup dir with "m<id>" (giving separate namespace to regular backup stuff)
		function getPath() {

			$scheduledBackup = $this->getScheduledBackup();

			// Get the host and info about it
			$host = $scheduledBackup->getHost();

			$hostInfo = $host->getInfo();


			// Get the volume and info about it
			$volume = $scheduledBackup->getVolume();

			$volumeInfo = $volume->getInfo();


			// Check to see that the volume is a directory first
			if( !is_dir($volumeInfo['path']) ) {
				throw new Exception('materializedSnapshot->getPath: '."Error: The storage volume at ".$volumeInfo['path']." is not a valid directory.");
			}


			return $volumeInfo['path'].'/'.$hostInfo['hostname'].'/m'.$this->id;

		}


		// Change the status of the backup snapshot
		// Fail if the row already has this state and nothing is changed..
		function setStatus($status) {

			$this->__validate();

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "UPDATE materialized_snapshots SET status='".$conn->real_escape_string($status)."' ";

			$sql .= " WHERE materialized_snapshot_id=".$this->id." AND status != '".$conn->real_escape_string($status)."'";

			if( ! $conn->query($sql) ) {
				throw new Exception('materializedSnapshot->setStatus: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if( $conn->affected_rows != 1 ) {
				throw new Exception('materializedSnapshot->setStatus: '."Error: Failed to change materialized snapshot status to $status -- either it already had that status or the snapshot was not found.");
			}

			return true;

		}


		// Delete this materializedSnapshot's file(s) / link(s) on the filesystem
		// Remove its row from the Db
		function destroy() {

			global $config;

			// Validate
			$this->__validate();

			$this->setStatus('DELETING');
			$this->deleteFiles();

			// Remove the row from the DB.
			$conn = dbConnection::getInstance($this->log);

			$sql = "DELETE FROM materialized_snapshots WHERE materialized_snapshot_id=".$this->id;

			if( ! $conn->query($sql) ) {
				throw new Exception('materializedSnapshot->destroy: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if( $conn->affected_rows != 1 ) {
				throw new Exception('materializedSnapshot->destroy: '."Error: Failed to delete the Materialized Snapshot with ID ".$this->id.".");
			}

			return true;

		}

		// Delete the files/dir for this materializedSnapshot on the filesystem
		function deleteFiles() {

			// Get the path
			$path = $this->getPath();

			// Deltree the directory
			if( ( strlen($path) == 0 ) || $path == '/' ) {
				throw new Exception('materializedSnapshot->destroy: '."Error: Detected unsafe path for this snapshot to attempt to perform recursive delete on. Aborting.");
			}

			// In many cases we already moved the directory to become the starting point for a new materialized snapshot
			// so we only attempt to remove if the dir exists

			if(is_link($path)) {
				if(!unlink($path)) {
					throw new Exception('materializedSnapshot->destroy: '."Error: Unable to remove symlink $path .");
				}
			}

			if(is_dir($path)) {
				$deleter = new recursiveDeleter();
				$deleter->delTree($path.'/');
			}


			return true;

		}


	}

?>
