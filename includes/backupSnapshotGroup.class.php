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


	class backupSnapshotGroup {


		function __construct($scheduledBackupId = false, $snapshotGroupNum = false) {

			$this->scheduledBackupId = $scheduledBackupId;
			$this->snapshotGroupNum = $snapshotGroupNum;
			$this->log = false;

			// Validate this object
			$this->__validate();

		}

		function setLogStream($log) {
			$this->log = $log;
		}

		// Sanity check this object
		function __validate() {
			if(!is_numeric($this->scheduledBackupId) ) {
				throw new Exception('backupSnapshotGroup->__validate: '."Error: The Scheduled Backup ID for this object is not an integer.");
			}
			if(!is_numeric($this->snapshotGroupNum) ) {
				throw new Exception('backupSnapshotGroup->__validate: '."Error: The Snapshot Group Number for this object is not an integer.");
			}
		}

		// Get the seed for this group if it has one
		function getSeed() {

			// Sanity check the object.
			$this->__validate();

			global $config;

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE scheduled_backup_id=".$this->scheduledBackupId.
					" AND snapshot_group_num=".$this->snapshotGroupNum." AND type='SEED' AND status='COMPLETED'";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('backupSnapshotGroup->getSeed: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if( $res->num_rows > 1 ) {
				throw new Exception('backupSnapshotGroup->getSeed: '."Error: Found more than one valid seed for this snapshot group. This should not happen.");
			} elseif( $res->num_rows == 1 ) {
				$row = $res->fetch_array();
				$snapshotGetter = new backupSnapshotGetter();
				return $snapshotGetter->getById($row['backup_snapshot_id']);
			} elseif( $res->num_rows == 0 ) {
				return false;
			}

			throw new Exception('backupSnapshotGroup->getSeed: '."Error: Failed to determine if there was a valid seed and return it. This should not happen.");

		}


		// Return all the incremental snapshots for this snapshot group
		function getIncrementals() {

			// sanity check this object
			$this->__validate();

			global $config;

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE scheduled_backup_id=".$this->scheduledBackupId.
					" AND snapshot_group_num=".$this->snapshotGroupNum." AND type='INCREMENTAL' AND status='COMPLETED'";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('backupSnapshotGroup->getIncrementals: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$incrementals = Array();
			$snapshotGetter = new backupSnapshotGetter();
			while($row = $res->fetch_array() ) {
				$incrementals[] = $snapshotGetter->getById($row['backup_snapshot_id']);
			}

			return $incrementals;

		}


		// Get the snapshot group number
		function getNumber() {
			$this->__validate();
			return $this->snapshotGroupNum;
		}

		// Get the most recently completed scheduled backup snapshot
		function getMostRecentCompletedBackupSnapshot() {

			$this->__validate();

			global $config;

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE status='COMPLETED' AND scheduled_backup_id=".$this->scheduledBackupId." 
					AND snapshot_group_num=".$this->snapshotGroupNum." ORDER BY snapshot_time DESC LIMIT 1";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('backupSnapshotGroup->getMostRecentCompletedBackupSnapshot: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			if( $res->num_rows != 1 ) {
				throw new Exception('backupSnapshotGroup->getMostRecentCompletedBackupSnapshot: '."Error: Could not find the most recent backup snapshot for "
									."Scheduled Backup ID ".$this->scheduledBackupId." and Group No ".$this->snapshotGroupNum." .");
			}

			$row = $res->fetch_array();

			$snapshotGetter = new backupSnapshotGetter();
			$snapshot = $snapshotGetter->getById($row['backup_snapshot_id']);

			return $snapshot;
		}


		// Get an array of snapshots that belong to this group regardless of type
		// ordered newest to oldest
		function getAllSnapshotsNewestToOldest() {

			$this->__validate();

			global $config;

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE status='COMPLETED' AND scheduled_backup_id=".$this->scheduledBackupId." 
					AND snapshot_group_num=".$this->snapshotGroupNum." ORDER BY snapshot_time DESC";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('backupSnapshotGroup->getAllSnapshotsNewestToOldest: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			$snapshots = Array();
			while( $row = $res->fetch_array() ) {

				$snapshotGetter = new backupSnapshotGetter();
				$snapshots[] = $snapshotGetter->getById($row['backup_snapshot_id']);
			}

			return $snapshots;

		}


		// Delete all of the snapshots for this group
		function deleteAllSnapshots() {

			$this->__validate();

			$snapshots = $this->getAllSnapshotsNewestToOldest();
			foreach( $snapshots as $snap ) {
				$snap->delete();
			}

		}

	}

?>
