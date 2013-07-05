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

	class backupSnapshot {


		function __construct($id = false) {

			if( ($id !== false) && !is_numeric($id)) {
				throw new Exception('backupSnapshot->__construct: '."Error: Expected a numeric id and did not get one.");
			}
			$this->id = $id;
			$this->log = false;
			$this->scheduledBackup = NULL;
		}

		function setLogStream($log) {
			$this->log = $log;
		}


		function init($scheduledBackup, $type, $creation_method, $snapshotGroup, $parentId = false) {

			global $config;

			if(!is_numeric($scheduledBackup->id) ) {
				throw new Exception('backupSnapshot->init: '."Error: Expected ScheduledBackup with a numeric ID and did not get one.");
			}

			$this->scheduledBackup = $scheduledBackup;

			


			$conn = dbConnection::getInstance($this->log);


			if( $parentId === false ) {

				$sql = "INSERT INTO backup_snapshots (scheduled_backup_id, type, creation_method, snapshot_group_num) VALUES
						(".$scheduledBackup->id.", '".$conn->real_escape_string($type)."', '".$conn->real_escape_string($creation_method)."', "
						.$snapshotGroup->getNumber()." )";

			} else {

				if(!is_numeric($parentId) ) {
					throw new Exception('backupSnapshot->init: '."Error: Expected numeric parent ScheduledBackup ID and did not get one.");
				}

				$sql = "INSERT INTO backup_snapshots (scheduled_backup_id, type, creation_method, snapshot_group_num, parent_snapshot_id) VALUES 
						(".$scheduledBackup->id.", '".$conn->real_escape_string($type)."', '".$conn->real_escape_string($creation_method)."',
							".$snapshotGroup->getNumber().", ".$parentId.")";
			}


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('backupSnapshot->init: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$this->id = $conn->insert_id;

			// Get the path for this snapshot
			$path = $this->getPath();

			// Create the dir for the hostname/snapshotId
			if(!mkdir($path, 0700, true) ) {
				throw new Exception('backupSnapshot->init: '."Error: Could not create the directory for this snapshot at ".$path." .");
			}

			return true;
		}


		// Return the scheduledBackup parent object for this snapshot.
		function getScheduledBackup() {

			if(!is_object($this->scheduledBackup) ) {

				$info = $this->getInfo();
				
				$scheduledBackupGetter = new scheduledBackupGetter();

				$this->scheduledBackup = $scheduledBackupGetter->getById($info['scheduled_backup_id']);

			}   


			return $this->scheduledBackup;
	
		}


		// Check to see if the storage volume exists and returns a path for the snapshot.
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
				throw new Exception('backupSnapshot->getPath: '."Error: The storage volume at ".$volumeInfo['path']." is not a valid directory.");
			}


			return $volumeInfo['path'].'/'.$hostInfo['hostname'].'/'.$this->id;


		}


		function getInfo() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('backupSnapshot->getInfo: '."Error: The ID for this object is not an integer.");
			}


			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT * FROM backup_snapshots WHERE backup_snapshot_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('backupSnapshot->getInfo: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
	
			$info = $res->fetch_array();

			return $info;

		}


		function getSnapshotGroup() {

			if(!is_numeric($this->id)) {
				throw new Exception('backupSnapshot->getSnapshotGroup: '."Error: The ID for this object is not an integer.");
			}

			$snapInfo = $this->getInfo();

			$snapshotGroup = new backupSnapshotGroup($snapInfo['scheduled_backup_id'], $snapInfo['snapshot_group_num']);

			return $snapshotGroup;
		}

		// Change the status of the backup snapshot
		// Fail if the row already has this state and nothing is changed..
		function setStatus($status) {
			
			if(!is_numeric($this->id)) {
				throw new Exception('backupSnapshot->setStatus: '."Error: The ID for this object is not an integer.");
			}


			


			$conn = dbConnection::getInstance($this->log);


			$sql = "UPDATE backup_snapshots SET status='".$conn->real_escape_string($status)."' ";

			$sql .= " WHERE backup_snapshot_id=".$this->id." AND status != '".$conn->real_escape_string($status)."'";

			if( ! $conn->query($sql) ) {
				throw new Exception('backupSnapshot->setStatus: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if( $conn->affected_rows != 1 ) {
				throw new Exception('backupSnapshot->setStatus: '."Error: Failed to change snapshot status to $status -- either it already had that status or the snapshot was not found.");
			}

			return true;

		}


		// Set the snapshot time of the backup snapshot - uses NOW() if unset.
		function setSnapshotTime($snapshotTime = false) {

			if(!is_numeric($this->id)) {
				throw new Exception('backupSnapshot->setSnapshotTime: '."Error: The ID for this object is not an integer.");
			}


			


			$conn = dbConnection::getInstance($this->log);

			if($snapshotTime === false) {
				$snapshotTime = 'NOW()';
			}

			$sql = "UPDATE backup_snapshots SET snapshot_time='".$conn->real_escape_string($snapshotTime)."' ";

			$sql .= " WHERE backup_snapshot_id=".$this->id;

			if( ! $conn->query($sql) ) {
				throw new Exception('backupSnapshot->setSnapshotTime: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			return true;

		}


		// delete this snapshot - removes all files as well as marking it as "DELETED".
		function delete() {

			$this->setStatus('DELETING');
			$this->deleteFiles();
			$this->setStatus('DELETED');

			return true;


		}

		// Completely destroy this snapshot, files and database entry
		function destroy() {

			$this->delete();

			if(!is_numeric($this->id)) {
				throw new Exception('backupSnapshot->destroy: '."Error: The ID for this object is not an integer.");
			}


			$conn = dbConnection::getInstance($this->log);

			$sql = "DELETE FROM backup_snapshots WHERE backup_snapshot_id=".$this->id;

			if( ! $conn->query($sql) ) {
				throw new Exception('backupSnapshot->destroy: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			return true;
	
		}

		// Completely removes all files from the backup snapshot directory and the directory itself
		function deleteFiles() {

			// Get the path
			$path = $this->getPath();

			if( ( strlen($path) == 0 ) || $path == '/' ) {
				throw new Exception('backupSnapshot->deleteFiles: '."Error: Detected unsafe path for this snapshot to attempt to perform recursive delete on. Aborting.");
			}

			if(!is_dir($path)) {
				return true;
			}

			$deleter = new recursiveDeleter();

			$deleter->delTree($path.'/');

			return true;
		} 


		// Get the log sequence number position for this backup snapshot
		function getLsn() {

			// Read the to_lsn value from the xtrabackup_checkpoints file in the backup dir

			$path = $this->getPath();
	
			if(!is_file($path.'/xtrabackup_checkpoints')) {
				throw new Exception('backupSnapshot->getLsn: '."Error: Could not find file ".$path."/xtrabackup_checkpoints for log sequence information.");
			}

			if( ! ( $file = file_get_contents($path.'/xtrabackup_checkpoints') ) ) {
				throw new Exception('backupSnapshot->getLsn: '."Error: Could not read file ".$path."/xtrabackup_checkpoints for log sequence information.");
			}

			if( preg_match('/to_lsn = ([0-9]+:[0-9]+|[0-9]+)/', $file, $matches) == 0 ) {
				throw new Exception('backupSnapshot->getLsn: '."Error: Could find log sequence information in file: ".$path."/xtrabackup_checkpoints");
			}

			if( !isSet($matches[1]) || strlen($matches[1]) == 0 ) {
				throw new Exception('backupSnapshot->getLsn: '."Error: Could find log sequence information in file: ".$path."/xtrabackup_checkpoints");
			}

			return $matches[1];

		}

		// Reassign any snapshot(s) whose parent snapshot is this snapshot to another snapshot - used when merging snapshots
		function assignChildrenNewParent($parentId) {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('backupSnapshot->assignChildrenNewParent: '."Error: The ID for this object is not an integer.");
			}

			if(!is_numeric($parentId) ) {
				throw new Exception('backupSnapshot->assignChildrenNewParent: '."Error: Expected numeric value for new parent to assign to children of this snapshot, but did not get one.");
			}

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "UPDATE backup_snapshots SET parent_snapshot_id=".$parentId." WHERE parent_snapshot_id=".$this->id;


			if( ! $conn->query($sql) ) {
				throw new Exception('backupSnapshot->assignChildrenNewParent: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			return true;

			
		}


		// Get the child backupSnapshot of this one
		function getChild() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('backupSnapshot->getChild: '."Error: The ID for this object is not an integer.");
			}

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE status='COMPLETED' AND parent_snapshot_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('backupSnapshot->getChild: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if($res->num_rows != 1 ) {
				throw new Exception('backupSnapshot->getChild: '."Error: Could not identify a single child of this backupSnapshot.");
			}

			$row = $res->fetch_array();

			$backupGetter = new backupSnapshotGetter();

			return $backupGetter->getById($row['backup_snapshot_id']);

		}

	}

?>
