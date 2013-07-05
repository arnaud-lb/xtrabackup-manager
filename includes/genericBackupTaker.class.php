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


	class genericBackupTaker {


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

		// Set the tickets that should be released once the runningBackup object entry for the job is fully initialized..
		function setTicketsToReleaseOnStart($ticketArray) {
			if( !is_array($ticketArray) ) {
				throw new Exception('genericBackupTaker->setTicketsToReleaseOnStart: '."Error: Expected an array as a paramater, but did not get one.");
			}
			$this->ticketsToReleaseOnStart = $ticketArray;
		}

		// Take a full backup snapshot into the snapshotGroup given
		function takeFullBackupSnapshot(backupJob $job, $snapshotGroup) {

			global $config;

			$scheduledBackup = $job->getScheduledBackup();

			/**********************
				TAKING FULL BACKUP
			***********************/

			// Create object for the lock...
			$runningBackup = new runningBackup();
			$runningBackup->setInfoLogStream($this->infolog);


			// Get scheduledBackup info
			$sbInfo = $scheduledBackup->getInfo();
			// Get scheduledBackup host
			$sbHost = $scheduledBackup->getHost();
			// get host info
			$hostInfo = $sbHost->getInfo();

			// Initialize a backup for sbHost for scheduledBackup
			$runningBackup->init($sbHost, $scheduledBackup);

			// Release our queue tickets
			$queueManager = new queueManager();
			$queueManager->setLogStream($this->log);
			foreach($this->ticketsToReleaseOnStart as $ticket) {
				$queueManager->releaseTicket($ticket);
			}

			try {

				// Create new object shell
				$snapshot = new backupSnapshot();

				$tempDir = $runningBackup->getStagingTmpdir();
	
				// Initialise the snapshot we're taking..
				$snapshot->init($scheduledBackup, 'SEED', 'FULL', $snapshotGroup);
	
				// Start a try block to cleanup the snapshot if we fail..
				try {
	
					// Now get back the info about the running backup we just created.
					$rbInfo = $runningBackup->getInfo();
			
					// Now we should run a backup on the right port / host / etc.
					$path = $snapshot->getPath();
			
					// Fire up a netcat listener
					$ncBuilder = new netcatCommandBuilder();
					$ncServer = $ncBuilder->getServerCommand($rbInfo['port']);
	
					// Set the command we plan to run
					$ncLogfile = $config['LOGS']['logdir'].'/hosts/'.$hostInfo['hostname'].'.netcat.log';

					// Change dir to the path where the files should end up
					$ncCommand = ' cd '.$path.' ; ';
					// Detect if there is a /usr/bnu/bin/tar -- This lets us catch GNU tar on Solaris 11 and use it (non-GNU tar doesn't work properly)
					$ncCommand .= ' if [ -f /usr/gnu/bin/tar ]; then TAR="/usr/gnu/bin/tar"; else TAR=tar; fi; ';
					// Lastly, put the netcat command
					$ncCommand .= $ncServer;

					// Add the throttle if enabled
					if($sbInfo['throttle'] > 0) {

						system('pv -V > /dev/null 2>&1', $pvReturn);
						if($pvReturn <> 0 ) {
							throw new Exception('genericBackupTaker->takeFullBackupSnapshot: '."Error: Throttling is enabled, but could not find pv command in the path.");
						}
						$ncCommand .= " | pv -q -L".$scheduledBackup->getMbpsThrottleValue()."m ";
					}

					// Pipe output through previously detected tar command to use
					$ncCommand .= ' | $TAR xvif - >> '.$ncLogfile.' 2>&1';
			
					// Open the process with a stream to read from it

					// Set up how we'll interact with the IO file handlers of the process
					$ncDescriptors = Array(
										0 => Array('pipe', 'r'), // Process will read from a pipe
										1 => Array('pipe', 'w'), // Process will write to STDOUT a pipe 
										2 => Array('pipe', 'w')  // Process will write to STDERR a pipe 
									);

					// Open the netcat listener proc
					$this->infolog->write("Attempting to start netcat with command: ".$ncCommand, XBM_LOG_DEBUG);

					$ncProc = proc_open($ncCommand, $ncDescriptors, $ncPipes);

					if(!is_resource($ncProc) ) {
						throw new Exception('genericBackupTaker->takeFullBackupSnapshot: '."Error: Unable to start netcat with command: $ncCommand .");
					}
			
					// Info output
					$this->infolog->write("Started Netcat (nc) listener on port ".$rbInfo['port']." to receive backup tar stream into directory $path ...", XBM_LOG_INFO);
			
					// Find which binary we should use
					$xbBinary = $scheduledBackup->getXtraBackupBinary();
			
					// Proceed with running the backup
			
					// Build the command...
					$xbCommand = 'ssh -o StrictHostKeyChecking=no -p '.$hostInfo['ssh_port'].' '.$sbInfo['backup_user'].'@'.$hostInfo['hostname'].
								" 'cd $tempDir ; innobackupex --ibbackup=".$xbBinary." --stream=tar ".$sbInfo['datadir_path']." --user=".$sbInfo['mysql_user'].
								" --password=".$sbInfo['mysql_password']." --slave-info --safe-slave-backup --tmpdir=".$tempDir;
			
					// If table locking for the backup is disabled add the --no-lock option to innobackupex
					if($sbInfo['lock_tables'] == 'N') {
						$xbCommand .= " --no-lock ";
					}
		

					$ncClient = $ncBuilder->getClientCommand($config['SYSTEM']['xbm_hostname'], $rbInfo['port']);
					$xbCommand .= " | ".$ncClient.
								' ; exit ${PIPESTATUS[1]}\''; // Makes sure the command run on the remote machine returns the exit status of innobackupex, which is what SSH will return
			
					// Set up how we'll interact with the IO file handlers of the process
					$xbDescriptors = Array(
										0 => Array('file', '/dev/null', 'r'), // Process will read from /dev/null
										1 => Array('pipe', 'w'), // Process will write to STDOUT pipe
										2 => Array('pipe', 'w')  // Process will write to STDERR pipe
									);
			
	
					////////
					////////
					// KICK OFF XTRABACKUP //
					////////
					////////
			
					// Set the state of the snapshot to RUNNING
					$snapshot->setStatus('RUNNING');
			
					// Info output
					$this->infolog->write("Running FULL xtrabackup snapshot of ".$sbInfo['datadir_path']." via ssh: ".$sbInfo['backup_user']."@".$hostInfo['hostname']." ...", XBM_LOG_INFO);
			
					// DEBUG
					$this->infolog->write("Attempting to run the full backup with command:\n ".preg_replace('/--password=(.+?)\s+/', '--password=XXXXXXX ', $xbCommand)."\n", XBM_LOG_DEBUG);
					// Start the xtrabackup process
					$xbProc = proc_open($xbCommand, $xbDescriptors, $xbPipes);
			
					// Check that we launched OK.
					if( !is_resource($xbProc) ) {
						throw new Exception('genericBackupTaker->takeFullBackupSnapshot: '."Error: Unable to use ssh to start innobackupex with: $xbCommand .");
					}
			
					// Check the status of the backup every second...
					$streamContents = '';
					stream_set_blocking($xbPipes[2], 0);
					do {
						$streamContents .= stream_get_contents($xbPipes[2]);
	
						if( ! ( $xbStatus = proc_get_status($xbProc) ) ) {
							throw new Exception('genericBackupTaker->takeFullBackupSnapshot: '."Error: Unable to retrieve status on backup process.");
						}

						if($job->isKilled() ) {
							throw new KillException('The backup was killed by an administrator.');
						}

						sleep(1);
			
					} while ($xbStatus['running']);
			
			
					if($xbStatus['exitcode'] <> 0 ) {
						throw new Exception('genericBackupTaker->takeFullBackupSnapshot: '."Error: There was an error backing up - The process returned code ".$xbStatus['exitcode'].".".
												" The output from the backup is as follows:\n".$streamContents);
					} 
			
			
/*					print_r($xbStatus);
					echo "\nWe got STDOUT: \n";
					echo stream_get_contents($xbPipes[1]);
					echo "\nWe got STDERR: \n";
					echo stream_get_contents($xbPipes[2]); */
			
					$this->infolog->write("XtraBackup completed with the following output:\n".$streamContents, XBM_LOG_INFO);
			
					// Close out backup process (it should be killed already)
					proc_close($xbProc);
			
					// Close out the netcat process
					proc_close($ncProc);	
			
			
					$this->infolog->write("XtraBackup step completed OK. Proceeding with apply-log step...", XBM_LOG_INFO);
			
					// Begin the apply log process
			
					// Set the state of the snapshot to APPLY LOG
					$snapshot->setStatus('APPLY LOG');
		

					// Attempt to create some temp dirs to work around XtraBackup Bug #837143
					// https://bugs.launchpad.net/percona-xtrabackup/+bug/837143
					if( ! mkdir($path.'/tmp', 0770, true) ) {
						throw new Exception('genericBackupTaker->takeFullBackupSnapshot: '."Error: Unable to create dir './tmp' in backup dir for apply log process to utilize.");
					}

					if( ! mkdir($path.'/mysqldb/tmp', 0770, true) ) {
						throw new Exception('genericBackupTaker->takeFullBackupSnapshot: '."Error: Unable to create dir './mysqldb/tmp' in backup dir for apply log process to utilize.");
					}
	
			
					// Build the command for applying log
					$applyCommand = $xbBinary." --defaults-file=".$path."/backup-my.cnf --use-memory=".$config['SYSTEM']['xtrabackup_use_memory']." --prepare --apply-log-only --target-dir=".$path." 1>&2";
			
					// Set up how we'll interact with the IO file handlers of the process
					$applyDescriptors = Array(
										0 => Array('pipe', 'r'), // Process will read from STDIN pipe
										1 => Array('pipe', 'w'), // Process will write to STDOUT pipe
										2 => Array('pipe', 'w')  // Process will write to STDERR pipe
									);
			
					// Info log output...
					$this->infolog->write("Running apply-log with command: ".$applyCommand, XBM_LOG_DEBUG);
			
					// Kick off the command
					$applyProc = proc_open($applyCommand, $applyDescriptors, $applyPipes);
			
					// Check that we launched OK.
					if( !is_resource($applyProc) ) {
						throw new Exception('genericBackupTaker->takeFullBackupSnapshot: '."Error: Unable to run apply log command: $applyCommand");
					}
			
					// Check the status of the apply log every second...
					$streamContents = '';
					stream_set_blocking($applyPipes[2], 0);
					do {
						$streamContents .= stream_get_contents($applyPipes[2]);
						if( ! ( $applyStatus = proc_get_status($applyProc) ) ) {
							throw new Exception('genericBackupTaker->takeFullBackupSnapshot: '."Error: Unable to retrieve status on apply log process.");
						}

						if($job->isKilled() ) {
							throw new KillException('The backup was killed by an administrator.');
						}

						sleep(1);
			
					} while ($applyStatus['running']);
			
			
					if($applyStatus['exitcode'] <> 0 ) {
						throw new Exception('genericBackupTaker->takeFullBackupSnapshot: '."Error: There was an error applying logs - The process returned code ".$applyStatus['exitcode'].".".
												" The output from the apply log process is as follows:\n".$streamContents);
					}
			
			
					/* For debug
					print_r($applyStatus);
					echo "\nWe got STDOUT: \n";
					echo stream_get_contents($applyPipes[1]);
					echo "\nWe got STDERR: \n";
					echo stream_get_contents($applyPipes[2]); */
			
					// Write output to infolog..
					$this->infolog->write("Apply log process completed successfully with the following output:\n".$streamContents, XBM_LOG_INFO);
			
					// Close out backup process (it should be killed already)
					proc_close($applyProc);
			
			
					// Set the snapshot time to be now
					$snapshot->setSnapshotTime(date('Y-m-d H:i:s'));
					// Set the state of the snapshot to COMPLETED
					$snapshot->setStatus('COMPLETED');
			
					$this->infolog->write("Backup completed successfully!", XBM_LOG_INFO);
	
				} catch (Exception $e) {

					if(get_class($e) == 'KillException') {
						$this->infolog->write("The backup job was killed by an administrator. Aborting...", XBM_LOG_ERROR);
					}

					// If we had a netcat process, check to see if we need to kill it/clean up
					if(isSet($ncProc) && is_resource($ncProc) ) {
						proc_terminate($ncProc);
					}

					// Remove files and make status failed
					if($config['SYSTEM']['cleanup_on_failure'] == true ) {
						$this->infolog->write("Cleaning up files...", XBM_LOG_INFO);
						$snapshot->deleteFiles();
					} else {
						$this->infolog->write("Skipping cleanup as cleanup_on_failure is turned off...", XBM_LOG_INFO);
					}
					$snapshot->setStatus('FAILED');
					// Rethrow
					throw $e;
				}	


			} catch (Exception $e) {
				// Clean up our runningBackup locks then rethrow..
				$runningBackup->finish();
				throw $e;

			}

			// We're done!
			$runningBackup->finish();

			return $snapshot;
	
		} // func: takeFullBackupSnapshot



		// Take an incremental backup snapshot into the snapshotGroup using log sequence number of seedSnap
		function takeIncrementalBackupSnapshot(backupJob $job, $snapshotGroup, $seedSnap) {

			global $config;

			$scheduledBackup = $job->getScheduledBackup();

			/****************************
	 		  TAKING INCREMENTAL BACKUP
			****************************/

			// Create object for the lock...
			$runningBackup = new runningBackup();
			$runningBackup->setInfoLogStream($this->infolog);


			// Get scheduledBackup info
			$sbInfo = $scheduledBackup->getInfo();
			// Get scheduledBackup host
			$sbHost = $scheduledBackup->getHost();
			// get host info
			$hostInfo = $sbHost->getInfo();

			$mostRecentSnap = $snapshotGroup->getMostRecentCompletedBackupSnapshot();

			// Initialize a backup for sbHost for scheduledBackup
			$runningBackup->init($sbHost, $scheduledBackup);

			// Release our queue tickets
			$queueManager = new queueManager();
			$queueManager->setLogStream($this->log);
			foreach($this->ticketsToReleaseOnStart as $ticket) {
				$queueManager->releaseTicket($ticket);
			}

			try {

				$lsn = $seedSnap->getLsn();

				// Create new object shell
				$snapshot = new backupSnapshot();
	
				// Init the snapshot here and use the ID for the tempdirname
				$snapshot->init($scheduledBackup, 'DELTA', 'INCREMENTAL', $snapshotGroup, $mostRecentSnap->id);

				// Get runningBackup info
				$rbInfo = $runningBackup->getInfo();

				// Start another TRY block so we can catch any exceptions and clean up our snapshot status.
				try {
					$snapshotInfo = $snapshot->getInfo();
		
		
					$tempDir = $runningBackup->getStagingTmpdir();
			
					// Build the command to run the snapshot into the staging dir
					$xbBinary = $scheduledBackup->getXtraBackupBinary();
	
					// Command should look like this:
					$xbCommand = "ssh -o StrictHostKeyChecking=no -p ".$hostInfo['ssh_port']." ".$sbInfo['backup_user']."@".$hostInfo['hostname'].
								" 'cd $tempDir ; innobackupex --ibbackup=".$xbBinary." --slave-info --incremental-lsn=".$lsn." ".$tempDir."/deltas".
								" --user=".$sbInfo['mysql_user']." --safe-slave-backup ".
								" --password=".$sbInfo['mysql_password']." --no-timestamp --incremental --throttle=".$scheduledBackup->getXtraBackupThrottleValue()." 1>&2 '";
					
					// Set up how we'll interact with the IO file handlers of the process
					$xbDescriptors = Array(
										0 => Array('file', '/dev/null', 'r'), // Process will read from /dev/null
										1 => Array('pipe', 'w'), // Process will write to STDOUT pipe
										2 => Array('pipe', 'w')  // Process will write to STDERR pipe
									);

					// Connect to the remote host and create the backup into a staging directory then copy it back via netcat and tar
	
	
					////////
					////////
					// KICK OFF XTRABACKUP INCREMENTAL //
					////////
					////////
				
					// Set the state of the snapshot to RUNNING
					$snapshot->setStatus('RUNNING');
				
					// Info output
					$this->infolog->write("Staging an INCREMENTAL xtrabackup snapshot of ".$sbInfo['datadir_path']." via ssh: ".$sbInfo['backup_user']."@".$hostInfo['hostname']." to ".$tempDir."/deltas...", XBM_LOG_INFO);
					
					// DEBUG
					$this->infolog->write("Attempting to run the incremental backup with command:\n".preg_replace('/--password=(.+?)\s+/', '--password=XXXXXXX ', $xbCommand)." \n", XBM_LOG_DEBUG);

					// Start the xtrabackup process
					$xbProc = proc_open($xbCommand, $xbDescriptors, $xbPipes);
					
					// Check that we launched OK.
					if( !is_resource($xbProc) ) {
						throw new Exception('genericBackupTaker->takeIncrementalBackupSnapshot: '."Error: Unable to use ssh to start xtrabackup with: $xbCommand .");
					}   
					
					// Check the status of the backup every second...
					$streamContents = '';
					stream_set_blocking($xbPipes[2], 0);
					do {

						$streamContents .= stream_get_contents($xbPipes[2]);

						if( ! ( $xbStatus = proc_get_status($xbProc) ) ) {
							throw new Exception('genericBackupTaker->takeIncrementalBackupSnapshot: '."Error: Unable to retrieve status on backup process.");
						}

						if($job->isKilled() ) {
							throw new KillException('The backup was killed by an administrator.');
						}

						sleep(1);

					} while ($xbStatus['running']);

					if($xbStatus['exitcode'] <> 0 ) {
						throw new Exception('genericBackupTaker->takeIncrementalBackupSnapshot: '."Error: There was an error backing up - The process returned code ".$xbStatus['exitcode'].".".
												" The output from the backup is as follows:\n".$streamContents);
					}
	
		
				  	/*print_r($xbStatus);
					echo "\nWe got STDOUT: \n";
					echo stream_get_contents($xbPipes[1]);
					echo "\nWe got STDERR: \n";
					echo stream_get_contents($xbPipes[2]); */
	
					$this->infolog->write("XtraBackup completed staging the backup with the following output:\n".$streamContents, XBM_LOG_INFO);
		
		
					// Copy it to local machine
		
					$path = $snapshot->getPath();
		
		
					// Fire up a netcat listener
	
					// Set the command we plan to run
					$ncBuilder = new netcatCommandBuilder();
					$ncServer = $ncBuilder->getServerCommand($rbInfo['port']);

					$ncLogfile = $config['LOGS']['logdir'].'/hosts/'.$hostInfo['hostname'].'.netcat.log';
					// Change to the dir where we want to put the files
					$ncCommand = ' cd '.$path.' ; ';
					// Check to see if there us a GNU tar - used to make XBM work on Solaris 11, since their tar doesn't work correctly
					$ncCommand .= ' if [ -f /usr/gnu/bin/tar ]; then TAR="/usr/gnu/bin/tar"; else TAR=tar; fi; ';
					// Now start the netcat listener and pipe through the auto-detected tar command
					$ncCommand .= $ncServer.' | $TAR xvf - >> '.$ncLogfile.' 2>&1';
	
					// Open the process with a stream to read from it

					// Set up how we'll interact with the IO file handlers of the process
					$ncDescriptors = Array(
										0 => Array('pipe', 'r'), // Process will read from a pipe
										1 => Array('pipe', 'w'), // Process will write to STDOUT a pipe 
										2 => Array('pipe', 'w')  // Process will write to STDERR a pipe 
									);

					// Open the netcat listener proc
					$this->infolog->write("Attempting to run netcat with command: ".$ncCommand, XBM_LOG_DEBUG);

					$ncProc = proc_open($ncCommand, $ncDescriptors, $ncPipes);

					if(!is_resource($ncProc) ) {
						throw new Exception('genericBackupTaker->takeIncrementalBackupSnapshot: '."Error: Unable to start netcat with command: $ncCommand .");
					}
	
					// Info output
					$this->infolog->write("Started Netcat (nc) listener on port ".$rbInfo['port']." to receive backup tar stream into directory $path ...", XBM_LOG_INFO);
		
					$ncClient = $ncBuilder->getClientCommand($config['SYSTEM']['xbm_hostname'], $rbInfo['port']);
					// Copy the backup back via the netcat listener
					$copyCommand = "ssh -o StrictHostKeyChecking=no -p ".$hostInfo['ssh_port']." ".$sbInfo['backup_user']."@".$hostInfo['hostname']." 'cd ".$tempDir."/deltas; tar cvf - . | ".$ncClient." '";
	
					// Set the state of the snapshot to COPYING
					$snapshot->setStatus('COPYING');
	
	
					// Set up how we'll interact with the IO file handlers of the process
					$copyDescriptors = Array(
										0 => Array('file', '/dev/null', 'r'), // Process will read from /dev/null
										1 => Array('pipe', 'w'), // Process will write to STDOUT pipe
										2 => Array('pipe', 'w')  // Process will write to STDERR pipe
									);
		
		
					// Start the xtrabackup process
					$this->infolog->write("Attempting to run copy with command: ".$copyCommand, XBM_LOG_DEBUG);
					$copyProc = proc_open($copyCommand, $copyDescriptors, $copyPipes);
		
					// Check that we launched OK.
					if( !is_resource($copyProc) ) {
						throw new Exception('genericBackupTaker->takeIncrementalBackupSnapshot: '."Error: Unable to use ssh to start copy with: $copyCommand .");
					}
		
					// Check the status of the backup every second...
					$streamContents = '';
					stream_set_blocking($copyPipes[2], 0);
					do {
						$streamContents .= stream_get_contents($copyPipes[2]);
						if( ! ( $copyStatus = proc_get_status($copyProc) ) ) {
							throw new Exception('genericBackupTaker->takeIncrementalBackupSnapshot: '."Error: Unable to retrieve status on copy process.");
						}

						if($job->isKilled() ) {
							throw new KillException('The backup was killed by an administrator.');
						}

						sleep(1);
	
					} while ($copyStatus['running']);
		
		
					if($copyStatus['exitcode'] <> 0 ) {
						throw new Exception('genericBackupTaker->takeIncrementalBackupSnapshot: '."Error: There was an error copying files - The process returned code ".$copyStatus['exitcode'].".".
												" The output from the backup is as follows:\n".$streamContents);
					}
		
					/* For debugging...
					print_r($copyStatus);
					echo "\nWe got STDOUT: \n";
					echo stream_get_contents($copyPipes[1]);
					echo "\nWe got STDERR: \n";
					echo stream_get_contents($copyPipes[2]); 
					*/

					// Close out the copy and netcat processes
					proc_close($copyProc);
					proc_close($ncProc);
		
					// Set the snapshot time to be now
					$snapshot->setSnapshotTime(date('Y-m-d H:i:s'));

					// Set the state of the snapshot to COMPLETED
					$snapshot->setStatus('COMPLETED');

					$this->infolog->write("Completed copying the backup via netcat with the following output:\n".$streamContents, XBM_LOG_INFO);

				} catch (Exception $e) {

					if(get_class($e) == 'KillException') {
						$this->infolog->write("The backup job was killed by an administrator. Aborting...", XBM_LOG_ERROR);
					}

					// If we had a netcat process, check to see if we need to kill it/clean up
					if(isSet($ncProc) && is_resource($ncProc) ) {
						proc_terminate($ncProc);
					}

					// Remove the snapshot files and mark it as failed.
					if($config['SYSTEM']['cleanup_on_failure'] == true ) {
						$this->infolog->write("Cleaning up files...", XBM_LOG_INFO);
						$snapshot->deleteFiles();
					} else {
						$this->infolog->write("Skipping cleanup as cleanup_on_failure is turned off...", XBM_LOG_INFO);
					}
					$snapshot->setStatus('FAILED');

					throw $e;
				}
		
			} catch (Exception $e) {
				// Clean up the running backup entry and rethrow error..
				$runningBackup->finish();
				throw $e;
			}
	

			// Clean up after ourselves.. 
			// release our lock on the netcat port, scheduled backup, etc.
			$runningBackup->finish();

			return $snapshot;

		} // func: takeIncrementalBackupSnapshot



	} // Class: genericBackupTaker

?>
