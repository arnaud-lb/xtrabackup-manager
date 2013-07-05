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


	class continuousIncrementalBackupTaker {


		function __construct() {
			$this->log = false;
			$this->infolog = false;
			$this->infologVerbose = true;
			$this->ticketsToReleaseOnStart = Array();
		}

		// Set the logStream for general / debug xbm output
		function setLogStream($log) {
			$this->log = $log;
		}

		// Set the logStream for informational output
		function setInfoLogStream($log) {
			$this->infolog = $log;
		}

		// Set whether or not the info log logStream should write to stdout
		function setInfoLogVerbose($bool) {
			$this->infologVerbose = $bool;
		}

		// Set the time thie backup was launched
		function setLaunchTime($launchTime) {
			$this->launchTime = $launchTime;
		}

		// Set the tickets that should be released once the runningBackup object entry for the job is fully initialized..
		function setTicketsToReleaseOnStart($ticketArray) {
			if( !is_array($ticketArray) ) {
				throw new Exception('continuousIncrementalBackupTaker->setTicketsToReleaseOnStart: '."Error: Expected an array as a paramater, but did not get one.");
			}
			$this->ticketsToReleaseOnStart = $ticketArray;
		}

		function validateParams($params) {

			// max_snapshots 
			scheduledBackup::validateMaxSnapshots($params['max_snapshots']);

			return true;

		}

		// The main functin of this class - take the snapshot for a scheduled backup
		// Takes a scheduledBackup object as a param
		function takeScheduledBackupSnapshot ( backupJob $job ) {

			global $config;

			$scheduledBackup = $job->getScheduledBackup();


			// First fetch info to know what we're backing up

			// Validate the parameters of this backup, before we proceed.
			$params = $scheduledBackup->getParameters();
			$this->validateParams($params);

			// Get info on the backup
			$sbInfo = $scheduledBackup->getInfo();

			// Get the host of the backup
			$sbHost = $scheduledBackup->getHost();

			$hostInfo = $sbHost->getInfo();

			// Setup to write to host log
			if(!is_object($this->infolog)) {
				$infolog = new logStream($config['LOGS']['logdir'].'/hosts/'.$hostInfo['hostname'].'.log', $this->infologVerbose, $config['LOGS']['level']);
				$this->setInfoLogStream($infolog);
			}


			$backupTaker = new genericBackupTaker();
			$backupTaker->setInfoLogStream($this->infolog);
			$backupTaker->setTicketsToReleaseOnStart($this->ticketsToReleaseOnStart);

			$sbGroups = $scheduledBackup->getSnapshotGroupsNewestToOldest();

			// There should only be one group...
			if(sizeOf($sbGroups) > 1) {
				throw new Exception('continuousIncrementalBackupTaker->takeScheduledBackupSnapshot: '."Error: Found more than one snapshot group for a backup using continuous incremental strategy.");
			}

			// Find if there is a seed..
			$seedSnap = $sbGroups[0]->getSeed();

			// If this group has a seed snapshot, then take incremental...
			if($seedSnap) {
				$backupTaker->takeIncrementalBackupSnapshot($job, $sbGroups[0], $sbGroups[0]->getMostRecentCompletedBackupSnapshot() );
			// Otherwise take a FULL backup
			} else {
				$backupTaker->takeFullBackupSnapshot($job, $sbGroups[0]);
			}

			return true;

		} // end takeScheduledBackupSnapshot


		// Check for COMPLETED backup snapshots under the scheduledBackup and perform any necessary merging/deletion
		function applyRetentionPolicy( backupJob $job ) {

			global $config;

			$scheduledBackup = $job->getScheduledBackup();


			$this->infolog->write("Checking to see if any snapshots need to be merged into the seed backup.", XBM_LOG_INFO);

			if(!is_object($scheduledBackup)) {
				throw new Exception('continuousIncrementalBackupTaker->applyRetentionPolicy: '."Error: This function requires a scheduledBackup object as a parameter.");
			}

			

			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE status='COMPLETED' AND scheduled_backup_id=".$scheduledBackup->id." AND snapshot_time IS NOT NULL ORDER BY snapshot_time ASC";

			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('continuousIncrementalBackupTaker->applyRetentionPolicy: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}

			// Get info for this scheduledBackup
			$info = $scheduledBackup->getInfo();

			// Get the params/options for this scheduledBackup
			$params = $scheduledBackup->getParameters();
			// Validate them
			$this->validateParams($params);

			// Build service objects for later use
			$snapshotGetter = new backupSnapshotGetter();
			$snapshotMerger = new backupSnapshotMerger();

			// Check to see if the number of rows we have is more than the number of snapshots we should have at a max
			while( $res->num_rows > $params['max_snapshots'] ) {

				// Grab the first row - it is the SEED
				if( ! ( $row = $res->fetch_array() ) ) {
					throw new Exception('continuousIncrementalBackupTaker->applyRetentionPolicy: '."Error: Could not retrieve the object ID for the seed of Scheduled Backup ID ".$scheduledBackup->id);
				}

				$seedSnapshot = $snapshotGetter->getById($row['backup_snapshot_id'] );

				// Grab the second row - it is the DELTA to be collapsed.
				if( ! ( $row = $res->fetch_array() ) ) {
					throw new Exception('continuousIncrementalBackupTaker->applyRetentionPolicy: '."Error: Could not retrieve the object ID for the seed of Scheduled Backup ID ".$scheduledBackup->id);
				}

				$deltaSnapshot = $snapshotGetter->getById($row['backup_snapshot_id'] );

				$this->infolog->write("Merging deltas in Backup Snapshot ID #".$deltaSnapshot->id." with Backup Snapshot ID #".$seedSnapshot->id.".", XBM_LOG_INFO);

				// Merge them together
				$snapshotMerger->mergeSnapshots($seedSnapshot, $deltaSnapshot);

				// Check to see what merge work is needed now.
				$sql = "SELECT backup_snapshot_id FROM backup_snapshots WHERE status='COMPLETED' AND scheduled_backup_id=".$scheduledBackup->id." AND snapshot_time IS NOT NULL ORDER BY snapshot_time ASC";

				if( ! ($res = $conn->query($sql) ) ) {
					throw new Exception('continuousIncrementalBackupTaker->applyRetentionPolicy: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
				}

			}

			return true;

		}


		// Handle any postProcessing
		function postProcess( backupJob $job ) {

			$scheduledBackup = $job->getScheduledBackup();

			// Validate
			if($scheduledBackup === false || !is_object($scheduledBackup) ) {
				throw new Exception('continuousIncrementalBackupTaker->postProcess: '."Error: Expected a scheduledBackup object to be passed as a parameter, but did not get one.");
			}

			// Get Params
			$sbParams = $scheduledBackup->getParameters();
			$this->validateParams($sbParams);

			// If maintain_materialized_copy is set and enabled, then we need to make sure we keep the latest restore available 
			if(isSet($sbParams['maintain_materialized_copy']) && ($sbParams['maintain_materialized_copy'] == 1) ) {

				$this->infolog->write("Maintain materialized copy feature is enabled for this backup -- materializing latest backup ...", XBM_LOG_INFO);
				$job->setStatus('Materializing Latest');

				$manager = new materializedSnapshotManager();
				$manager->setInfoLogStream($this->infolog);
				$manager->setLogStream($this->log);
				$manager->materializeLatest($scheduledBackup);
				$this->infolog->write("Completed materializing latest backup.", XBM_LOG_INFO);

			}



			return true;

		}

	}

?>
