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


	class rotatingBackupTaker {


		function __construct() {
			$this->log = false;
			$this->infolog = false;
			$this->infologVerbose = true;
			$this->groupFactory = new backupSnapshotGroupFactory();
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

		// Set the time this backup was launched
		function setLaunchTime($launchTime) {
			$this->launchTime = $launchTime;
		}

		// Set the tickets that should be released once the runningBackup object entry for the job is fully initialized..
		function setTicketsToReleaseOnStart($ticketArray) {
			if( !is_array($ticketArray) ) {
				throw new Exception('rotatingBackupTaker->setTicketsToReleaseOnStart: '."Error: Expected an array as a paramater, but did not get one.");
			}
			$this->ticketsToReleaseOnStart = $ticketArray;
		}

		// Validate the parameters for this backup strategy 
		function validateParams($sbParams) {


			//
			// VALIDATE POSSIBLE PARAMETERS FOR THIS BACKUP
			//

			// Pick a rotation method (required)
			// rotate_method - DAY_OF_WEEK  or  AFTER_SNAPSHOT_COUNT 
			// Validate Rotation Method
			scheduledBackup::validateRotateMethod($sbParams['rotate_method']);


			switch($sbParams['rotate_method']) {

				//
				// DAY_OF_WEEK options
				//
				case 'DAY_OF_WEEK':	
					// rotate_day_of_week -  Create a new snapshot group on this day of the week.
					// The above uses the same day numbering as cron - 0-6 where Sunday = 0, Monday = 1, ...., Saturday = 6. 
					// Multiple values accepted in comma separated format - eg. 0,3 for Sunday and Wednesday

					// Validate rotate_day_of_week
					scheduledBackup::validateRotateDayOfWeek($sbParams['rotate_day_of_week']);
	
					// max_snapshots_per_group - The max tatal num of snapshots allowed in a group.
					// Only used with DAY_OF_WEEK rotate_method
					// We won't take any more incremental snapshots if we already have a total of this many snapshots for the group.
					// This prevents us just continuing to endlessly take incremental snapshots if the cron schedule is never running
					// the backup on the necessary day of the week for any reason.

					// Validate max_snapshots_per_group
					scheduledBackup::validateMaxSnapshotsPerGroup($sbParams['max_snapshots_per_group']);

					// backup_skip_fatal  - If no snapshot is taken because we hit max_snapshots_per_group, but it is not the rotation day of week
					// consider it a fatal error. 0 for OFF or 1 for ON (default). This can happen if your backup never ran on the day it should have.

					// Validate backup_skip_fatal
					// if not set, assume 1 / Yes.
					if(!isSet($sbParams['backup_skip_fatal'])) {
						$sbParams['backup_skip_fatal'] = 1;
					}
	
					scheduledBackup::validateBackupSkipFatal($sbParams['backup_skip_fatal']);
					break;


				//
				// AFTER_SNAPSHOT_COUNT options
				//
				case 'AFTER_SNAPSHOT_COUNT':	
	
					// rotate_snapshot_no - We rotate on the snapshot after this many in a group. 
					// Eg. if 7 and daily snapshots, you are creating a new group for the 8th.

					// Validate rotate_snapshot_no
					scheduledBackup::validateRotateSnapshotNo($sbParams['rotate_snapshot_no']);
					break;

				default:
					throw new Exception('rotatingBackupTaker->validateParams: '."Error: Could not find a method to validate this backups parameters. This should never happen.");
	
			}
		
	
			// GLOBAL rotate_method options...
	
			// max_snapshot_groups - The maximum number of groups of snapshots we will maintain. 
			// If 2, we would throw away group 1 after successfully taking the SEED for the third group.

			// validate max_snapshot_groups
			// must be set..
			scheduledBackup::validateMaxSnapshotGroups($sbParams['max_snapshot_groups']);


			// validate maintain_materialized_copy (if set)
			if(isSet($sbParams['maintain_materialized_copy']) ) {
				scheduledBackup::validateMaintainMaterializedCopy($sbParams['maintain_materialized_copy']);
			}

			//
			// If we made it this far, the backup parameters should be valid... proceed with the actual backup!
			//

			return true;

		} //func: validateParams


		// The main functin of this class - take the snapshot for a scheduled backup
		function takeScheduledBackupSnapshot ( backupJob $job ) {

			global $config;

			$scheduledBackup = $job->getScheduledBackup();

			// First fetch info to know what we're backing up
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

			// Get the params for the scheduledBackup and validate them...
			$sbParams = $scheduledBackup->getParameters();
			$this->validateParams($sbParams);

			// create a generic backup taker to use for snapshot taking.
			$backupTaker = new genericBackupTaker();
			$backupTaker->setInfoLogStream($this->infolog);
			$backupTaker->setTicketsToReleaseOnStart($this->ticketsToReleaseOnStart);

			// Get the snpshot groups for this scheduled backup
			$snapshotGroups = $scheduledBackup->getSnapshotGroupsNewestToOldest();

			//
			// Handle the case where we dont have any snapshots yet at all.
			//

			$this->infolog->write("Using ".$sbParams['rotate_method']." as the rotation method...", XBM_LOG_INFO);

			// if we have only one group...
			if(sizeOf($snapshotGroups) == 1 ) {
				// and we dont even have a seed yet...

				if($snapshotGroups[0]->getSeed() === false) {
					// take a seed snapshot and then return
					$this->infolog->write("No snapshots found for this scheduled backup at all - taking an initial full backup.", XBM_LOG_INFO);
					$backupTaker->takeFullBackupSnapshot($job, $snapshotGroups[0]);
					return true;
				}
			}

			

			// Now handle things based on rotate_method...
			switch($sbParams['rotate_method']) {


				case 'DAY_OF_WEEK':

					// Figure out what day of the week it is.
					$launchDayOfWeek = date('w', $this->launchTime);
					// is it the day of the week we should be rotating?
					$rotateDays = explode(',', $sbParams['rotate_day_of_week']);

					// This is kind of stupid but it was quicker than trying to coerce date functions to play nice.
					$dayList = Array( 0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday');

					$msg = "Set to rotate on: ";
					foreach($rotateDays as $dayOfWeek) {
						$msg .= $dayList[$dayOfWeek]."  ";
					}
					$this->infolog->write($msg, XBM_LOG_INFO);
					$this->infolog->write("This backup was launched on a ".$dayList[$launchDayOfWeek], XBM_LOG_INFO);

					// if yes..
					if(in_array($launchDayOfWeek, $rotateDays)) {
						$this->infolog->write("Based on the launch time of this backup, a rotate should be attempted.. checking if we already rotated today...", XBM_LOG_INFO);

						// get the seed of the newest group and find out what day it was taken
						$newestSeed = $snapshotGroups[0]->getSeed();
						$seedInfo = $newestSeed->getInfo();
						$seedDate = date('Ymd', strtotime($seedInfo['creation_time']) );
						$launchDate = date('Ymd', $this->launchTime);

						// was the seed taken today ?
						// if no, create next group and take seed
						if($launchDate != $seedDate) {
							$this->infolog->write("No FULL backup found today - rotating to the next snapshot group and taking a full backup for it.", XBM_LOG_INFO);
							$newGroup = $this->groupFactory->getNextSnapshotGroup($snapshotGroups[0]);
							$backupTaker->takeFullBackupSnapshot($job, $newGroup);
							return true;
						}
						$this->infolog->write("Found that we already took a FULL backup today, no rotation needed - proceeding to take an incremental.", XBM_LOG_INFO);
					} else {
						$this->infolog->write("Based on the launch time of this backup, no rotation should occur today - proceeding to take an incremental.", XBM_LOG_INFO);
					}

					// we get here if either
					// a) its not the day of week to take a seed
					// b) it is the day of week to take a seed, but we already took one

					// check to see if we can go ahead and take an incremental for the current group in that case..

					// how many snapshots do we have in the current group?
					$snapshots = $snapshotGroups[0]->getAllSnapshotsNewestToOldest();
					// is it >= max?
					// if yes - stop and if we treat this critical throw an exception/failure
					if( sizeOf($snapshots) >= $sbParams['max_snapshots_per_group'] ) {

						if( !isSet($sbParams['backup_skip_fatal']) || $sbParams['backup_skip_fatal'] == 1 ) {

							throw new Exception('rotatingBackupTaker->takeScheduledBackupSnapshot: '."Error: Refusing to take another backup, as max_snapshots_per_group would be exceeded.");
						}
						$this->infolog->write("Refusing to take another backup, as max_snapshots_per_group would be exceeded. Not treating as fatal due to backup_skip_fatal being disabled.", XBM_LOG_INFO);

						return true;
					} 

					// if no, go ahead and take incremental
					$backupTaker->takeIncrementalBackupSnapshot($job, $snapshotGroups[0],
																		$snapshotGroups[0]->getMostRecentCompletedBackupSnapshot() );
					break;

				case 'AFTER_SNAPSHOT_COUNT':

					// get the number of snapshots in the newest group
					$snaps = $snapshotGroups[0]->getAllSnapshotsNewestToOldest();

					$this->infolog->write("Detected ".sizeOf($snaps)." snapshots in current group and we are configured to rotate after number ".$sbParams['rotate_snapshot_no'].".", XBM_LOG_INFO);
					// is it >= maximum?
					if(sizeOf($snaps) >= $sbParams['rotate_snapshot_no'] ) {
						$this->infolog->write("Rotating to the next group and taking a full backup for it.", XBM_LOG_INFO);

						// if yes, create new group and take seed
						$newGroup = $this->groupFactory->getNextSnapshotGroup($snapshotGroups[0]);
						$backupTaker->takeFullBackupSnapshot($job, $newGroup);
						return true;
					} else {
						// if no, create new incremental in current group based on the most recent complete snap for the group
						$this->infolog->write("No group rotation needed - proceeding with incremental backup.", XBM_LOG_INFO);
						$backupTaker->takeIncrementalBackupSnapshot($job, $snapshotGroups[0], 
																		$snapshotGroups[0]->getMostRecentCompletedBackupSnapshot() );
						return true;

					}
					break;

				default:
					throw new Exception('rotatingBackupTaker->takeScheduledBackupSnapshot: '."Error: Could not find a handler for this rotate_method. This should never happen.");

			}
			

			return true;

		} // end takeScheduledBackupSnapshot


		// Check for COMPLETED backup snapshots under the scheduledBackup and perform any necessary merging/deletion
		function applyRetentionPolicy( backupJob $job ) {

			$scheduledBackup = $job->getScheduledBackup();

			$groups = array_reverse($scheduledBackup->getSnapshotGroupsNewestToOldest());

			$sbParams = $scheduledBackup->getParameters();

			$this->infolog->write("Checking to see if we have more snapshot groups than the maximum configured of ".$sbParams['max_snapshot_groups'].".", XBM_LOG_INFO);

			// while there are more groups than the configured maximum, delete contents of the oldest group
			while( sizeOf($groups) > $sbParams['max_snapshot_groups'] ) {
				$this->infolog->write("Found ".sizeOf($groups)." groups - deleting the oldest group and checking again.", XBM_LOG_INFO);

				// Deletes the files for all snapshots in the group as well as marking them as deleted.
				$groups[0]->deleteAllSnapshots();

				// Fetch the new list of groups (Oldest to Newest)
				$groups = array_reverse($scheduledBackup->getSnapshotGroupsNewestToOldest());
			}


			return true;

		}

		// Handle any postProcessing
		function postProcess(backupJob $job) {

			$scheduledBackup = $job->getScheduledBackup();
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
