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


	class fullonlyBackupTaker {


		function __construct() {
			$this->log = false;
			$this->infolog = false;
			$this->infologVerbose = true;
			$this->ticketsToReleaseOnStart = Array();
			$this->groupFactory = new backupSnapshotGroupFactory();
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
				throw new Exception('fullonlyBackupTaker->setTicketsToReleaseOnStart: '."Error: Expected an array as a paramater, but did not get one.");
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

			// If there is one group and no backup yet, take a full backup for group 0
			if(sizeOf($sbGroups) == 1 && $sbGroups[0]->getSeed() === false  ) {
				$backupTaker->takeFullBackupSnapshot($job, $sbGroups[0]);
			} else {

				// Otherwise, create the next group and take the next backup there...
				$newGroup = $this->groupFactory->getNextSnapshotGroup($sbGroups[0]);
				$backupTaker->takeFullBackupSnapshot($job, $newGroup);
			}

			return true;

		} // end takeScheduledBackupSnapshot


		// Check for COMPLETED backup snapshots under the scheduledBackup and perform any necessary merging/deletion
		function applyRetentionPolicy( backupJob $job ) {

			global $config;

			$scheduledBackup = $job->getScheduledBackup();

			if(!is_object($scheduledBackup)) {
				throw new Exception('fullonlyBackupTaker->applyRetentionPolicy: '."Error: This function requires a scheduledBackup object as a parameter.");
			}


			// Get the params/options for this scheduledBackup
			$params = $scheduledBackup->getParameters();

			// Validate them
			$this->validateParams($params);

			$sbGroups = array_reverse($scheduledBackup->getSnapshotGroupsNewestToOldest());

			// While we have too many - destroy the oldest snapshot
			while(sizeOf($sbGroups) > $params['max_snapshots']) {
				$this->infolog->write('There are more backups than the allowed maximum of '.$params['max_snapshots'].', removing the oldest backup...', XBM_LOG_INFO);
				$snapshot = $sbGroups[0]->getSeed();
				$snapshot->destroy();
				$sbGroups = array_reverse($scheduledBackup->getSnapshotGroupsNewestToOldest());

			}

			return true;

		}


		// Handle any postProcessing
		function postProcess( backupJob $job ) {


			// We don't have anything special for FULL ONLY backups
			return true;

		}

	}

?>
