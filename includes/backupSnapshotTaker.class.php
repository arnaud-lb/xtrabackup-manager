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


	class backupSnapshotTaker {


		function __construct() {
			$this->log = false;
			$this->infolog = false;
			$this->infologVerbose = true;
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

		// The main functin of this class - take the snapshot for a scheduled backup based on the backup strategy
		// Takes a scheduledBackup object as a param
		function takeScheduledBackupSnapshot ( scheduledBackup $scheduledBackup  ) {

			global $config;

			$this->launchTime = time();



			// Get the backup strategy for the scheduledBackup
			$sbInfo = $scheduledBackup->getInfo();

			// Get the host of the backup
			$sbHost = $scheduledBackup->getHost();

			// Get host info
			$hostInfo = $sbHost->getInfo();

			// create a backupTakerFactory
			$takerFactory = new backupTakerFactory();

			// use it to get the right object type for the backup strategy
			$backupTaker = $takerFactory->getBackupTakerByStrategy($sbInfo['strategy_code']);

			// Setup to write to host log
			$infolog = new logStream($config['LOGS']['logdir'].'/hosts/'.$hostInfo['hostname'].'.log', $this->infologVerbose, $config['LOGS']['level']);
			$this->setInfoLogStream($infolog);
			try {

				$msg = 'Initializing Scheduled Backup "'.$sbInfo['name'].'" (ID #'.$sbInfo['scheduled_backup_id'].') for host: '.$hostInfo['hostname'].' ... ';
				$this->infolog->write($msg, XBM_LOG_INFO);
				$msg = 'Using Backup Strategy: '.$sbInfo['strategy_name'];
				$this->infolog->write($msg, XBM_LOG_INFO);

				// Create an entry for this backup job
				$jobGetter = new backupJobGetter();
				$jobGetter->setLogStream($this->log);

				$job = $jobGetter->getNew($scheduledBackup);

				// Check to see if we can even start this party

				// First, take a number in the queues for the scheduledBackup and the host itself..

				$queueManager = new queueManager();
				$queueManager->setLogStream($this->log);

				// Set the global queueName for use later.
				$globalQueueName = 'scheduledBackup:GLOBAL';

				// Take a number in the scheduledBackup queue for THIS backup...
				$schedQueueName = 'scheduledBackup:'.$sbInfo['scheduled_backup_id'];
				$schedQueueTicket = $queueManager->getTicketNumber($schedQueueName);

				// Take a number in the host queue...
				$hostQueueName = 'hostBackup:'.$sbInfo['host_id'];
				$hostQueueTicket = $queueManager->getTicketNumber($hostQueueName);

				// If we are not at the front of the scheduledBackup queue when we start up, then just exit
				// assume another job is running already for this scheduledBackup.. we dont queue up dupe backup jobs
				// we skip them
				if( $queueManager->checkFrontOfQueue($schedQueueName, $schedQueueTicket) == false ) {

					// Release our tickets in the queues, then throw exception...
					$queueManager->releaseTicket($hostQueueTicket);
					$queueManager->releaseTicket($schedQueueTicket);

					$this->infolog->write("Detected this scheduled backup job is already running, exiting...", XBM_LOG_ERROR);
					throw new Exception('backupSnapshotTaker->takeScheduledBackupSnapshot: '."Error: Detected this scheduled backup job is already running.");

				}


				// Create this object now, so we don't recreate it a tonne of times in the loop
				$runningBackupGetter = new runningBackupGetter();

				// Mark us as "QUEUED" while we figure out if we can launch...
				$job->setStatus('Queued');

				$readyToRun = false; 
				while($readyToRun == false ) {

					// If we are not at the front of the queue for this host, then sleep/wait until we are.
					if( ! $queueManager->checkFrontOfQueue($hostQueueName, $hostQueueTicket) ) {
						$this->infolog->write("There are jobs before this one in the queue for this host. Sleeping ".XBM_SLEEP_SECS." before checking again...", XBM_LOG_INFO);
						for($i = 0; $i <= XBM_SLEEP_SECS; $i++) {

							if($job->isKilled() ) {
								throw new KillException('The backup was killed by an administrator.');
							}
							sleep(1);

						}
						continue;
					}

					// We are at the front of the queue for this host...
					// Check to see how many backups are running for the host already...
					$runningBackups = $sbHost->getRunningBackups();

					// If we are at or greater than max num of backups for the host, then sleep before we try again.
					if( sizeOf($runningBackups) >= $config['SYSTEM']['max_host_concurrent_backups'] ) {
						// Output to info log - this currently spits out every 30 secs (define is 30 at time of writing) 
						// maybe it is too much
						$this->infolog->write("Found ".sizeOf($runningBackups)." backup(s) running for this host out of a maximum of ".
							$config['SYSTEM']['max_host_concurrent_backups']." per host. Sleeping ".XBM_SLEEP_SECS." before retry...", XBM_LOG_INFO);

						for($i = 0; $i <= XBM_SLEEP_SECS; $i++) {

							if($job->isKilled() ) {
								throw new KillException('The backup was killed by an administrator.');
							}
							sleep(1);

						}

						continue;
					}

					// Only take a ticket in the global queue if we dont already have one
					// Wait until this point to prevent blocking up the GLOBAL queue when the host itself is blocked..
					if(!isSet($globalQueueTicket)) {
						$globalQueueTicket = $queueManager->getTicketNumber($globalQueueName);
					}

					// If we are not at the front of the queue for global backups, then sleep/wait until we are
					if( ! $queueManager->checkFrontOfQueue($globalQueueName, $globalQueueTicket) ) {
						$this->infolog->write("There are jobs before this one in the global backup queue. Sleeping ".XBM_SLEEP_SECS." before checking again...", XBM_LOG_INFO);
						for($i = 0; $i <= XBM_SLEEP_SECS; $i++) {

							if($job->isKilled() ) {
								throw new KillException('The backup was killed by an administrator.');
							}
							sleep(1);

						}
						continue;
					}

					// Now check to see the how many backups are running globally and if we should be allowed to run...
					$globalRunningBackups = $runningBackupGetter->getAll();

					if( sizeOf($globalRunningBackups) >= $config['SYSTEM']['max_global_concurrent_backups'] ) {
						//output to info log -- currentl every 30 secs based on define at time of writing
						// maybe too much?
						$this->infolog->write("Found ".sizeOf($globalRunningBackups)." backup(s) running out of a global maximum of ".
							$config['SYSTEM']['max_global_concurrent_backups'].". Sleeping ".XBM_SLEEP_SECS." before retry...", XBM_LOG_INFO);

						for($i = 0; $i <= XBM_SLEEP_SECS; $i++) {

							if($job->isKilled() ) {
								throw new KillException('The backup was killed by an administrator.');
							}
							sleep(1);

						}
						continue;
					}

					// If we made it to here - we are ready to run!
					$readyToRun = true;
				}

				// Populate the backupTaker with the relevant settings like log/infolog/Verbose, etc.
				$backupTaker->setLogStream($this->log);
				$backupTaker->setInfoLogStream($this->infolog);
				$backupTaker->setInfoLogVerbose($this->infologVerbose);
				$backupTaker->setLaunchTime($this->launchTime);
				$backupTaker->setTicketsToReleaseOnStart(Array($hostQueueTicket, $globalQueueTicket));

				// Kick off takeScheduledBackupSnapshot method of the actual backup taker
				$job->setStatus('Performing Backup');
				$backupTaker->takeScheduledBackupSnapshot($job);

				// Release the ticket for running the backup..
				$queueManager->releaseTicket($schedQueueTicket);

				$retentionQueueName = 'retentionApply:'.$sbInfo['scheduled_backup_id'];
				$retentionQueueTicket = $queueManager->getTicketNumber($retentionQueueName);

				// Proceed once we're at the start of the retention policy queue for this scheduled backup
				while(!$queueManager->checkFrontOfQueue($retentionQueueName, $retentionQueueTicket) ) {
					$this->infolog->write('There is already a task applying retention policy for this scheduled backup. Sleeping '.XBM_SLEEP_SECS.' before retry...', XBM_LOG_INFO);
					for($i = 0; $i <= XBM_SLEEP_SECS; $i++) {

						if($job->isKilled() ) {
							throw new KillException('The backup was killed by an administrator.');
						}
						sleep(1);

					}
				}

				// Apply the retention policy
				$this->infolog->write("Applying snapshot retention policy ...", XBM_LOG_INFO);
				$job->setStatus('Deleting Old Backups');
				$backupTaker->applyRetentionPolicy($job);
				$this->infolog->write("Application of retention policy complete.", XBM_LOG_INFO);

				$queueManager->releaseTicket($retentionQueueTicket);

				// Perform any post processingA

				// Get ticket/queue
				$postProcessQueueName = 'postProcess:'.$sbInfo['scheduled_backup_id'];
				$postProcessQueueTicket = $queueManager->getTicketNumber($postProcessQueueName);
				while(!$queueManager->checkFrontOfQueue($postProcessQueueName, $postProcessQueueTicket) ) {
					$this->infolog->write('There is already a task performing post processing for this scheduled backup. Sleeping '.XBM_SLEEP_SECS.' before retry...', XBM_LOG_INFO);
					for($i = 0; $i <= XBM_SLEEP_SECS; $i++) {

						if($job->isKilled() ) {
							throw new KillException('The backup was killed by an administrator.');
						}
						sleep(1);

					}
				}

				$this->infolog->write("Performing any post-processing necessary ...", XBM_LOG_INFO);
				$job->setStatus('Performing Post-Processing');
				$backupTaker->postProcess($job);

				$this->infolog->write("Post-processing completed.", XBM_LOG_INFO);
				$queueManager->releaseTicket($postProcessQueueTicket);

				$this->infolog->write("Scheduled Backup Task Complete!", XBM_LOG_INFO);
				$job->setStatus('Completed');

			} catch ( KillException $e ) {

				if(isSet($job)) {
					$job->setStatus('Killed');
				}

				$this->infolog->write('Exiting after the backup job was killed...', XBM_LOG_ERROR);
				throw $e;

			} catch( Exception $e ) {

				if(isSet($job) ) {
					$job->setStatus('Failed');
				}

				$this->infolog->write('An error occurred while trying to perform the backup. Proceeding to log some details to help debug...', XBM_LOG_ERROR);
				$this->infolog->write('Error Caught: '.$e->getMessage(), XBM_LOG_ERROR);
				$this->infolog->write('Trace: '.$e->getTraceAsString(), XBM_LOG_ERROR);
				throw $e;
			}

			return true;

		} // end takeScheduledBackupSnapshot


	}

?>
