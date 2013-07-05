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


	// Class to handle the "xbm" generic command functionality
	class cliHandler {

		// Set the logStream to write to
		function setLogStream($log) {
			$this->log = $log;
		}

		// Print top level command help-text
		function printBaseHelpText() {

			echo("Usage: xbm <context> <action> <args> ...\n\n");
			echo("Contexts and actions may be one of the following:\n\n");

			echo("volume [add|list|edit|delete] <args>\t\t -- Manage Backup Volumes\n");

			echo("host [add|list|edit|delete] <args>\t\t -- Manage Hosts to Backup\n");

			echo("backup [add|list|edit|info|delete|run] <args>\t -- Manage Scheduled Backup Tasks\n");

			echo("snapshot [list|restore|restore-latest] <args>\t -- Manage Backup Snapshots\n");

			echo("status\t\t\t\t\t\t -- Show info about running Backup Tasks\n");

			echo("kill [job_id]\t\t\t\t\t -- Kill running Backup Tasks\n");

			echo("upgrade\t\t\t\t\t\t -- Upgrade the XtraBackup Manager database schema\n");

			echo("\n");
			echo("You may specify only a context, or a context and action to get help on its relevant arguments.\n");
			echo("\n");
		
			return;

		}

		function printHeader() {
			print("\n".XBM_RELEASE_VERSION."\n\n");
		}

		// Handles the arguments given on the command-line
		// Accepts the $argv array 
		function handleArguments($args) {

			// If we arent given any parameters
			if(!isSet($args[1])) {

				// Just output help information and exit
				$this->printHeader();

				echo("Error: Context missing.\n\n");

				$this->printBaseHelpText();

				return;
				
			}


			// Handle the first arg to determine what context we're in
			switch($args[1]) {

				// Call volume context handler
				case 'volumes':
				case 'volume':
					$this->printHeader();
					$this->handleVolumeActions($args);
				break;

				// Call host context handler
				case 'hosts':
				case 'host':
					$this->printHeader();
					$this->handleHostActions($args);
				break;

				// Call backup context handler
				case 'backup':
				case 'backups':
					$this->handleBackupActions($args);
				break;

				// Call snapshot context handler
				case 'snapshot':
				case 'snapshots':
					$this->printHeader();
					$this->handleSnapshotActions($args);
				break;

				// Call the kill context handler
				case 'kill':
					$this->printHeader();
					$this->handleKillAction($args);
				break;

				// Call the upgrade context handler
				case 'upgrade':
					$this->printHeader();
					$this->handleUpgradeAction();
				break;

				// Call the status context handler
				case 'status':
					$this->printHeader();
					$this->handleStatusAction();
				break;

				// Handle unknown action context
				default:
					$this->printHeader();
					echo("Error: Unrecognized context specified: ".$args[1]."\n\n");
					$this->printBaseHelpText();
				break;				
			}

			// Flush to crontab in case anything changed
			// This is a lazy catch all way to handle this, but it should work.
			$cronFlusher = new cronFlusher();
			$cronFlusher->flushSchedule();


			return;

		}

		// Print out the help text for volumes context
		function printVolumeHelpText($args) {

			echo("Usage: xbm ".$args[1]." <action> <args> ...\n\n");
			echo("Actions may be one of the following:\n\n");

			echo("  add <name> <path>\t\t\t -- Add a New Backup Volume\n");
			echo("  list\t\t\t\t\t -- List available Backup Volumes\n");
			echo("  edit <name> <parameter> <value>\t -- Edit a Backup Volume to set <parameter> to <value>\n");
			echo("  delete <name>\t\t\t\t -- Delete a Backup Volume\n");

			echo("\n");
			echo("You may specify an action without parameters to get help on its relevant arguments.\n");
			echo("\n");

			return;

		}


		// Print out the help text for snpshots context
		function printSnapshotHelpText($args) {

			echo("Usage: xbm ".$args[1]." <actions> <args> ...\n\n");
			echo("Actions may be one of the following:\n\n");

			echo("  list <hostname> [<backup_name>]\t\t\t -- List backup snapshots for <hostname>, optionally filtered by <backup_name>.\n");
			echo("  restore <target_path> <snapshot_id>\t\t\t -- Restore/copy <snapshot_id> to <target_path>\n");
			echo("  restore-latest <target_path> <host> [<backup_name>]\t -- Restore the latest snapshot for <host> to <target_path>. Give <backup_name> if multiple exist.\n");
			echo("\n");
			echo("You may specify an action without parameters to get help on its relevant arguments.\n");
			echo("\n");

			return;
		}


		// Print out the help text for hosts context
		function printHostHelpText($args) {

			echo("Usage: xbm ".$args[1]." <actions> <args> ...\n\n");
			echo("Actions may be one of the following:\n\n");

			echo("  add <hostname> <description>\t\t -- Add a new Host\n");
			echo("  list\t\t\t\t\t -- List available Hosts\n");
			echo("  edit <hostname> <parameter> <value>\t -- Edit a Host to set <parameter> to <value>\n");
			echo("  delete <hostname>\t\t\t -- Delete a Host\n");
	
			echo("\n");
			echo("You may specify an action without parameters to get help on its relevant arguments.\n");
			echo("\n");

			return;
		}

		// Print out the help text for backups context
		function printBackupHelpText($args) {

			echo("Usage: xbm ".$args[1]." <actions> <args> ...\n\n");
			echo("Actions may be one of the following:\n\n");


			echo("  add <hostname> <backup_name> <strategy_code> <cron_expression> <backup_volume> <datadir_path> <mysql_user> <mysql_password>\n");
			echo("   ^---------------------------------------------------- -- Add a new Scheduled Backup\n\n");
			echo("  list [<hostname>]\t\t\t\t\t -- List Scheduled Backups, optionally filtered by <hostname>\n");
			echo("  edit <hostname> <backup_name> <parameter> <value>\t -- Edit a Scheduled Backup to set <parameter> to <value>\n");
			echo("  delete <hostname> <backup_name>\t\t\t -- Delete a Scheduled Backup with <backup_name> from <hostname>\n");
			echo("  run <hostname> <backup_name>\t\t\t\t -- Run the backup <backup_name> for <hostname>\n");
			echo("  info <hostname> <backup_name>\t\t\t\t -- Print complete information about <backup_name> for <hostname>\n");

			echo("\n");
			echo("You may specify an action without parameters to get help on its relevant arguments.\n");
			echo("\n");

			return;
		}

		// Handle actions for the snapshot context
		function handleSnapshotActions($args) {

			global $config;

			if(!isSet($args[2]) ) {
				// Just output some helpful info and exit
				echo("Error: Action missing.\n\n");
				$this->printSnapshotHelpText($args);
				return;
			}

			switch($args[2]) {

				// Handle list
				case 'list':

					// If we just get a hostname, check to see if it only has one Scheduled Backup task
					if(isSet($args[3]) && !isSet($args[4]) ) {

						$hostname = $args[3];

						$hostGetter = new hostGetter();
						$hostGetter->setLogStream($this->log);
						if( ! ( $host = $hostGetter->getByName($hostname) ) ) {
							throw new ProcessingException("Error: Could not find a host with hostname: $hostname");
						}

						$scheduledBackups = $host->getScheduledBackups();

						// If we dont find scheduled backups - throw exception
						if(sizeOf($scheduledBackups) == 0 ) {
							throw new ProcessingException("Error: Could not find any backups for host: $hostname");
						}

					}

					// If we are dealing with only one specific backup 
					if(  isSet($args[3]) && isSet($args[4]) ) {

						$scheduledBackupGetter = new scheduledBackupGetter();
						$scheduledBackupGetter->setLogStream($this->log);

						$hostname = $args[3];
						$backupName = $args[4];

						$scheduledBackups = Array();
						if( ! ( $scheduledBackups[0] = $scheduledBackupGetter->getByHostnameAndName($hostname, $backupName) ) ) {
							throw new ProcessingException("Error: Could not find a backup for host: $hostname with name: $backupName");
						}
						
					}

					if( !isSet($args[3]) && !isSet($args[4]) ) {
						throw new InputException("Error: Not all required parameters for the snapshots to list were given.\n\n"
									."  Syntax:\n\n	xbm ".$args[1]." list <hostname> [<backup_name>]\n\n"
									."  Example:\n\n	xbm ".$args[1]." list db01.mydomain.com 'Nightly Backup'\n\n");
					}

					echo("-- Listing Backup Snapshots for $hostname --\n\n");	

					// By this point we should have an array scheduledBackups to display info for
					foreach($scheduledBackups as $scheduledBackup) {

						$sbInfo = $scheduledBackup->getInfo();
						$groups = $scheduledBackup->getSnapshotGroupsNewestToOldest();
						$strategy = $scheduledBackup->getBackupStrategy();
						$strategyInfo = $strategy->getInfo();

						echo(" -- Snapshots for Backup Name: ".$sbInfo['name']."\n");

						// Display materialized snapshot if enabled
						$params = $scheduledBackup->getParameters();
						if(isSet($params['maintain_materialized_copy']) && $params['maintain_materialized_copy'] == 1) {

							$materialized = $scheduledBackup->getMostRecentCompletedMaterializedSnapshot();
							echo("	-- Latest Materialized Snapshot:\n");
							if($materialized == false) {
								echo("	  None.\n");
							} else {
								$materializedInfo = $materialized->getInfo();
								$mSnap = $materialized->getBackupSnapshot();
								$mSnapInfo = $mSnap->getInfo();
								echo("	  ID: m".$materializedInfo['materialized_snapshot_id']."  Snapshot Time: ".$mSnapInfo['snapshot_time']);
									// Was going to display path here, but decided users should never know the path
									// they should always use xbm commands for restores to ensure correct locking
									//."  Path: ".$materialized->getPath()."\n");
							}
						}


						// Display Snapshots (by group if ROTATING method is used)
						$groupNum = 0;
						foreach($groups as $group) {
							$groupNum++;
							if($strategyInfo['strategy_code'] == 'ROTATING') {
								echo("	-- Group: $groupNum\n");
							}
							$snapshots = $group->getAllSnapshotsNewestToOldest();
							foreach($snapshots as $snapshot) {
								$snapInfo = $snapshot->getInfo();
								echo("	  ID: ".$snapInfo['backup_snapshot_id']."  Type: ".$snapInfo['type']."  Snapshot Time: ".$snapInfo['snapshot_time']."  Creation Method: ".$snapInfo['creation_method']."\n");
							}

							if(sizeOf($snapshots) == 0) {
								echo("	  None.\n");
							}

						}
						
					
					}
					echo("\n\n");
					
				break;


				// Handle restore
				case 'restore-latest':

					// If we just got a path and hostname, 
					if(isSet($args[3]) && isSet($args[4]) && !isSet($args[5]) ) {

						$hostname = $args[4];
						$targetPath = $args[3];

						$hostGetter = new hostGetter();
						$hostGetter->setLogStream($this->log);
						if( ! ( $host = $hostGetter->getByName($hostname) ) ) {
							throw new ProcessingException("Error: Could not find a host with hostname: $hostname");
						}

						$scheduledBackups = $host->getScheduledBackups();

						// If we dont find scheduled backups - throw exception
						if(sizeOf($scheduledBackups) == 0 ) {
							throw new ProcessingException("Error: Could not find any backups for host: $hostname");
						}

						// If we find more than 1 scheduled backup - throw exception
						if(sizeOf($scheduledBackups) > 1 ) {
							throw new ProcessingException("Error: Found multiple Backup Tasks for host: $hostname - Please specify which backup name to restore the latest snapshot for.");
						}

						$scheduledBackup = $scheduledBackups[0];

					}


					// If we got a host AND backup name
					if(isSet($args[3]) && isSet($args[4]) && isSet($args[5]) ) {

						$targetPath = $args[3];
						$hostname = $args[4];
						$backupName = $args[5];

						$scheduledBackupGetter = new scheduledBackupGetter();
						$scheduledBackupGetter->setLogStream($this->log);

						if( ! ($scheduledBackup = $scheduledBackupGetter->getByHostnameAndName($hostname, $backupName) ) ) {
							throw new ProcessingException("Error: Could not find a Scheduled Backup Task for host: $hostname with name: $backupName");
						}

					}

					if(!isSet($args[3]) || !isSet($args[4])  ) {
						throw new InputException("Error: Not all required parameters for the Scheduled Backup you wish to restore the latest snapshot for were given.\n\n"
									."  Syntax:\n\n	xbm ".$args[1]." restore-latest <target_path> <hostname> [<backup_name>]\n\n"
									."  Example:\n\n	xbm ".$args[1]." restore-latest /restores/myrestore db01.mydomain.com 'Nightly Backup'\n\n");
					}


					$sbInfo = $scheduledBackup->getInfo();
					// Check if we have any COMPLETED snapshots anyway
					$snapCount = $scheduledBackup->getCompletedSnapshotCount();
					if($snapCount < 1 ) {
						throw new ProcessingException("Error: No snapshots exist for the Scheduled Backup Task with name: ".$sbInfo['name']." for host: $hostname");
					}

					// We have a scheduledBackup - figure out if we might have a materialized snapshot and work accordingly
					$params = $scheduledBackup->getParameters();

					$snapshot = false;
					
					if($params['maintain_materialized_copy'] == 1) {
						// Is there a materialized snapshot?
						$snapshot = $scheduledBackup->getMostRecentCompletedMaterializedSnapshot();
					}

					// If not, lets try finding the most recent snapshot for this scheduledBackup
					if($snapshot == false ) {
						$snapshot = $scheduledBackup->getMostRecentCompletedBackupSnapshot();
					}					

					// At this point we either have a materialized snapshot or regular snapshot in $snapshot
					// Lets proceed with restoring...
					$this->handleRestore($snapshot, $targetPath );

				break;

					
				case 'restore':

					if(  isSet($args[3]) && isSet($args[4]) ) {

						// Handle the valid snapshot id inputs that we know

						// Numeric - regular snapshot
						if( is_numeric($args[4]) ) {
							$backupSnapshotGetter = new backupSnapshotGetter();
							$backupSnapshotGetter->setLogStream($this->log);
							if( ! ($snapshot = $backupSnapshotGetter->getById($args[4]) ) ) {
								throw new ProcessingException("Error: Could not find a Backup Snapshot with ID: ".$args[4]);
							}

						// Materialized snapshot
						} elseif( substr($args[4], 0, 1) == 'm' && is_numeric(substr($args[4], 1) ) ) {

							$materialId = substr($args[4], 1) ;
							$materializedSnapshotGetter = new materializedSnapshotGetter();
							$materializedSnapshotGetter->setLogStream($this->log);
							if( ! ( $snapshot = $materializedSnapshotGetter->getById($materialId) ) ) {
								throw new ProcessingException("Error: Could not find a materialized Backup Snapshot with ID: ".$args[4]);
							}

						// Catch all for when the input snapshot id is not recognized...
						// it should have been one of the following:
						// letter m, followed by numeric ID - eg. m12 - m signifies a materialized snapshot
						// just a numeric id - eg. 12 - no letter m prepended signifies a regular snapshot
						} else {
								throw new InputException("Error: The Snapshot ID specified must be of any of these forms: 12, m12 (m denotes a Materialized Snapshot ID)\n\n"
									."  Syntax:\n\n	xbm ".$args[1]." restore <snapshot_id> <target_path>\n\n"
									."  Example:\n\n	xbm ".$args[1]." restore m21 /restores/myrestore\n\n");
						}

						$snapInfo = $snapshot->getInfo();
						if($snapInfo['status'] != 'COMPLETED') {
							throw new ProcessingException("Error: The snapshot specified is in ".$snapInfo['status']." status. Only COMPLETED snapshots can be restored.");
						}

						$this->handleRestore($snapshot, $args[3]);

					} else {
							throw new InputException("Error: Not all required parameters for the restore action were given.\n\n"
									."  Syntax:\n\n	xbm ".$args[1]." restore <snapshot_idt> <target_path>\n\n"
									."  Example:\n\n	xbm ".$args[1]." restore m21 /restores/myrestore\n\n");
					}

				break;

				// Catch unknown action
				default:
					echo("Error: Unrecognized action for ".$args[1]." context: ".$args[2]."\n\n");
					$this->printSnapshotHelpText($args);
				break;

			}

		}


		// Handle restoring of a snapshot
		function handleRestore($snapshot, $path) {

			global $config;

			// Collect info regardless of class type
			$scheduledBackup = $snapshot->getScheduledBackup();
			$sbInfo = $scheduledBackup->getInfo();
			$host = $scheduledBackup->getHost();
			$hostInfo = $host->getInfo();

			switch(get_class($snapshot) ) {

				// Handle regular backup snapshot
				case 'backupSnapshot':
					throw new Exception("dont handle regular snapshots yet");
				break;

				// Handle materialized snapshot
				case 'materializedSnapshot':

					$snapInfo = $snapshot->getInfo();
					$backupSnapshot = $snapshot->getBackupSnapshot();
					$bsInfo = $backupSnapshot->getInfo();
					echo("  -- Restoring --\n\n");
					echo("  About to restore the following snapshot:\n\n");
					echo("  Host: ".$hostInfo['hostname']."  Backup Name: ".$sbInfo['name']."  Snapshot ID: m".$snapInfo['materialized_snapshot_id']."  Snapshot Time: ".$bsInfo['snapshot_time']."\n\n");
				break;

				default:
					throw new ProcessingException("Error: Encountered an unrecognized class of snapshot when restoring: ".get_class($snapshot));
				break;
			}

			echo("  To the following target path: $path\n\n");
			// Confirm action...
			$inputReader = new inputReader();
			$input = $inputReader->readline("Are you sure? - Please type 'YES' to confirm: ");

			if($input != 'YES') {
				echo("No 'YES' confirmation received, exiting...\n\n");
			} else {
				echo("\n\n");
				// Check the file/dir exists
				if(file_exists($path)) {
					// If it is not a directory throw an error
					if(!is_dir($path)) {
						throw new ProcessingException("Error: A file already exists at the specified path that is not a directory.");
					}
					// If it is not writable throw an error
					if(!is_writable($path) ) {
						throw new ProcessingException("Error: The specified path exists, but is not writable. Please check permissions and try again.");
					}

					// Proceed with restore here...
					echo("  Proceeding with restore. Please wait...\n\n");
					$backupRestorer = new backupRestorer();
					$backupRestorer->setLogStream($this->log);
					$infolog = new logStream($config['LOGS']['logdir'].'/hosts/'.$hostInfo['hostname'].'.log', false, $config['LOGS']['level']);
					$backupRestorer->setInfoLogStream($infolog);
					$backupRestorer->restoreLocal($snapshot, $path);

				} else {
					// If path doesnt exist throw an error
					throw new ProcessingException("Error: The specified path does not exist, please create it first.");
				}

			}


			return;

		}


		// Handle actions for the backup context
		function handleBackupActions($args) {

			global $config;

			//If we arent given any more parameters
			if(!isSet($args[2]) ) {
				// Just output some helpful info and exit
				$this->printHeader();
				echo("Error: Action missing.\n\n");
				$this->printBackupHelpText($args);
				return;
			}

			// Handle the "backup" context action parameter...
			switch($args[2]) {

				// Handle add
				case 'add':
					$this->printHeader();
					// Cycle through args 3-10 checking if they are set
					// Easier than one big ugly if with many ORs
					for( $i=3; $i <= 10; $i++ ) {
						if( !isSet($args[$i]) ) {
							throw new InputException("Error: Not all required parameters for the Scheduled Backup to add were given.\n\n"
									."  Syntax:\n\n	xbm ".$args[1]." add <hostname> <backup_name> <strategy_code> <cron_expression> <backup_volume> <datadir_path> <mysql_user> <mysql_password>\n\n"
									."  Example:\n\n	xbm ".$args[1].' add "db01.mydomain.com" "nightlyBackup" ROTATING "30 20 * * *" "Storage Array 1" /usr/local/mysql/data backup "p4ssw0rd"'."\n\n");
						}
					}
					
					// Populate vars with our args
					$hostname = $args[3];
					$backupName = $args[4];
					$strategyCode = $args[5];
					$cronExpression = $args[6];
					$volumeName = $args[7];
					$datadirPath = $args[8];
					$mysqlUser = $args[9];
					$mysqlPass = $args[10];

					$backupGetter = new scheduledBackupGetter();
					$backupGetter->setLogStream($this->log);


					// Get the new Scheduled Backup
					$scheduledBackup = $backupGetter->getNew($hostname, $backupName, $strategyCode, $cronExpression, $volumeName, $datadirPath, $mysqlUser, $mysqlPass);

					echo("Action: New Scheduled Backup '$backupName' was created for host: ".$hostname."\n\n");


				break;

				// Handle list
				case 'list':
					$this->printHeader();

					$hostGetter = new hostGetter();
					$hostGetter->setLogStream($this->log);

					// Optionally accept a hostname parameter to filter by
					if(isSet($args[3]) ) {

						$hostname = $args[3];


						if( ! ( $host = $hostGetter->getByName($hostname) ) ) {
							throw new ProcessingException("Error: Could not find a host with hostname: $hostname");
						}

						$scheduledBackups = $host->getScheduledBackups();

						
						echo("-- Listing Scheduled Backups for $hostname --\n\n");
						foreach($scheduledBackups as $scheduledBackup) {
							$sbInfo = $scheduledBackup->getInfo();
							echo("  Name: ".$sbInfo['name']."  Active: ".$sbInfo['active']."  Cron_Expression: ".$sbInfo['cron_expression']."\n");
						}
						echo("\n");

					} else {

						echo("-- Listing Scheduled Backups for all hosts --\n");
						echo("\n  Note: You may additionally provide a hostname as a parameter to see backups for only that host.\n");

						// Get all hosts
						$hosts = $hostGetter->getAll();

						// Iterate over each host, getting scheduled backups and print them
						foreach($hosts as $host) {
							$scheduledBackups = $host->getScheduledBackups();
							$hostInfo = $host->getInfo();
							echo("\n\tHostname: ".$hostInfo['hostname']."\n\n");
							if(sizeOf($scheduledBackups) > 0 ) {
								foreach($scheduledBackups as $scheduledBackup) {
									$sbInfo = $scheduledBackup->getInfo();
									echo("\t  Name: ".$sbInfo['name']."  Active: ".$sbInfo['active']."  Cron_Expression: ".$sbInfo['cron_expression']."\n");
								}
							} else {
								echo("\t\tNo Scheduled Backups.\n");
							}

						}
						echo("\n");
						
					}

				break;


				// Handle info
				case 'info':
					$this->printHeader();

					// If we just get a hostname, check to see if it only has one Scheduled Backup task
					if(isSet($args[3]) && !isSet($args[4]) ) {

						$hostname = $args[3];

						$hostGetter = new hostGetter();
						$hostGetter->setLogStream($this->log);
						if( ! ( $host = $hostGetter->getByName($hostname) ) ) {
							throw new ProcessingException("Error: Could not find a host with hostname: $hostname");
						}

						$scheduledBackups = $host->getScheduledBackups();

						if(sizeOf($scheduledBackups) == 0 ) {
							throw new ProcessingException("Error: Could not find any backups for host: $hostname");
						}

						// If we have just 1 scheduledBackup, then feed it to
						if(sizeOf($scheduledBackups) == 1 ) {
							$scheduledBackup = $scheduledBackups[0];
						} elseif( sizeOf($scheduledBackups) > 1 ) {
							throw new ProcessingException("Error: Found more than one Scheduled Backup for host: $hostname -- Please specify a Scheduled Backup name.");
						}

					}

					// Requires hostname and backup name 
					if( isSet($scheduledBackup) || ( isSet($args[3]) && isSet($args[4]) ) ) {

						if(!isSet($scheduledBackup) ) {
							$scheduledBackupGetter = new scheduledBackupGetter();
							$scheduledBackupGetter->setLogStream($this->log);

							$hostname = $args[3];
							$backupName = $args[4];

							if( ! ( $scheduledBackup = $scheduledBackupGetter->getByHostnameAndName($hostname, $backupName) ) ) {
								throw new ProcessingException("Error: Could not find a backup for host: $hostname with name: $backupName");
							}
						}

						$sbInfo = $scheduledBackup->getInfo();
						echo("-- Listing Scheduled Backup Info for Host: $hostname - Backup Name: ".$sbInfo['name']." --\n\n");
						$volume = $scheduledBackup->getVolume();
						$volInfo = $volume->getInfo();
						$strategy = $scheduledBackup->getBackupStrategy();
						$stratInfo = $strategy->getInfo();
						
						echo("  Name: ".$sbInfo['name']."  Active: ".$sbInfo['active']."  Cron_Expression: ".$sbInfo['cron_expression']."\n");
						echo("  Backup User: ".$sbInfo['backup_user']."  Datadir: ".$sbInfo['datadir_path']."\n");
						echo("  MySQL User: ".$sbInfo['mysql_user']."  Lock Tables: ".$sbInfo['lock_tables']."\n");
						echo("  Backup Volume: ".$volInfo['name']." ( ".$volInfo['path']." )\n");
						echo("  Backup Strategy: ".$stratInfo['strategy_name']." ( ".$stratInfo['strategy_code']." )\n");
						if($sbInfo['throttle'] == 0 ) {
							echo("  Throttle: Disabled (0)\n");
						} else {
							echo("  Throttle: Enabled (".$sbInfo['throttle']." MB/sec)\n");
						}
						echo("  Backup Strategy Params:\n");
						$params = $scheduledBackup->getParameters();
						foreach($params as $param => $value) {
							echo("    $param: $value\n");
						}
						echo("\n");

					} else {

						throw new InputException("Error: Not all required parameters for the Scheduled Backup to print info for were given.\n\n"
										."  Syntax:\n\n	xbm ".$args[1]." info <hostname> <backup name>\n\n"
										."  Example:\n\n	xbm ".$args[1]." info db01.mydomain.com 'Nightly Backup'\n\n");
					}

				break;

				// Handle edit
				case 'edit':
					$this->printHeader();

					if( !isSet($args[3]) || !isSet($args[4]) || !isSet($args[5]) || !isSet($args[6]) ) {
						$errMsg = "Error: Hostname and backup name of the backup to edit must be given along with parameter and value.\n\n";
						$errMsg .= "  Parameters:\n\n";
						$errMsg .= "	name - The name of the Scheduled Backup task\n";
						$errMsg .= "	cron_expression - The cron expression for when the backup should be run\n";
						$errMsg .= "	backup_user - The username that XtraBackup Manager will attempt to SSH to the remote host with\n";
						$errMsg .= "	datadir_path - The datadir path for MySQL on the remote host\n";
						$errMsg .= "	mysql_user - The MySQL username for innobackupex to use for communicating with MySQL\n";
						$errMsg .= "	mysql_password - The MySQL password for the above\n";
						$errMsg .= "	lock_tables - Whether FLUSH TABLES WITH READ LOCK should be used for MyISAM consistency (Y/N)\n";
						$errMsg .= "	active - Whether this Scheduled Backup task is activated and should run (Y/N)\n";
						$errMsg .= "	throttle - How many MB/sec to throttle this backup to (0 to disable throttling)\n";
						$errMsg .= "\n";
						$errMsg .= "  Backup Strategy Parameters:\n\n";
						$errMsg .= "	FULLONLY:\n";
						$errMsg .= "	  max_snapshots - The maximum number of full backup snapshots to maintain on disk.\n";
						$errMsg .= "	ROTATING:\n";
						$errMsg .= "	  rotate_method - Whether to rotate to a new snapshot group based on DAY_OF_WEEK or AFTER_SNAPSHOT_COUNT.\n";
						$errMsg .= " 	  rotate_day_of_week - Only relevant to DAY_OF_WEEK. Comma-separated list of days to rotate groups on. 0=Sun...6=Sat\n";
						$errMsg .= "	  max_snapshots_per_group - Maximum number of snapshots that may be taken in any snapshot group.\n";
						$errMsg .= "	  backup_skip_fatal - If a backup exceeds the max per group, consider skipping that backup fatal and alert.\n";
						$errMsg .= "	  rotate_snapshot_no - Only relevant to AFTER_SNAPSHOT_COUNT. Rotate groups after this many snapshots.\n";
						$errMsg .= "	  max_snapshot_groups - Maximum number of snapshot groups to maintain.\n";
						$errMsg .= "	  maintain_materialized_copy  Keep a fully materialized copy of the latest backup for faster restores.\n";
						$errMsg .= "	CONTINC:\n";
						$errMsg .= "	  max_snapshots - The maximum number of backup snapshots to maintain in total (FULL and INCREMENTAL)\n";
						$errMsg .= "	  maintain_materialized_copy - Keep a fully materialized copy of the latest backup for faster restores.\n";

						$errMsg .= "\n  Example:\n\n	xbm ".$args[1].' edit db01.mydomain.com "Daily Backup" cron_expression "0 18 * * *"';

						throw new InputException($errMsg);

					}

					$hostname = $args[3];
					$backupName = $args[4];
					$backupParam = $args[5];
					$backupValue = $args[6];

					$scheduledBackupGetter = new scheduledBackupGetter();
					$scheduledBackupGetter->setLogStream($this->log);
					if( ! ($scheduledBackup = $scheduledBackupGetter->getByHostnameAndName($hostname, $backupName) ) ) {
						throw new ProcessingException("Error: No backup exists for host: $hostname with name: ".$backupName);
					}

					$scheduledBackup->setParam($backupParam, $backupValue);

					echo("Action: Backup for host: ".$hostname." with name: ".$backupName." parameter '".$backupParam."' set to: ".$backupValue."\n\n");

				break;

				// Handle delete
				case 'delete':
					$this->printHeader();

					if( isSet($args[3]) && isSet($args[4]) ) {

						$scheduledBackupGetter = new scheduledBackupGetter();
						$scheduledBackupGetter->setLogStream($this->log);

						$hostname = $args[3];
						$backupName = $args[4];

						if( ! ( $scheduledBackup = $scheduledBackupGetter->getByHostnameAndName($hostname, $backupName) ) ) {
							throw new ProcessingException("Error: Could not find a backup for host: $hostname with name: $backupName");
						}

						// Fetch all the snapshots to get a count
						$snapshotGroups = $scheduledBackup->getSnapshotGroupsNewestToOldest();
						$snapshots = Array();
						foreach( $snapshotGroups as $group ) {
							$snapshots = array_merge($snapshots, $group->getAllSnapshotsNewestToOldest() ) ;
						}

						$snapCount = sizeOf($snapshots);

						$performDel = false;

						if($snapCount > 0 ) {
							echo("\nThe Scheduled Backup Task for host: $hostname with name: $backupName has $snapCount backup snapshots associated with it.\n\n");
							echo("If you delete this Scheduled Backup Task, all associated backup snapshots will be removed -- this cannot be undone.\n\n");
							$inputReader = new inputReader();
							$input = $inputReader->readline("Are you sure? - Please type 'YES' to confirm: ");
							if($input != 'YES') {
								echo("No 'YES' confirmation received, exiting...\n\n");
							} else {
								$performDel = true;
							}
						} else {
							$performDel = true;
						}


						if($performDel == true) {
							$scheduledBackup->destroy();
							echo("\nAction: Backup for host: ".$hostname." with name: ".$backupName." and all associated snapshots was deleted.\n\n");
						}


					} else {
						throw new InputException("Error: Not all required parameters for the Scheduled Backup to delete were given.\n\n"
										."  Syntax:\n\n xbm ".$args[1]." delete <hostname> <backup name>\n\n"
										."  Example:\n\n	xbm ".$args[1]." delete db01.mydomain.com 'Nightly Backup'\n\n");
					}
				break;

				// Handle run
				case 'run':
	
					if( isSet($args[3]) && isSet($args[4]) && (!isSet($args[5]) || (isSet($args[5]) && strtolower($args[5]) == 'quiet') ) ) {

						if( isSet($args[5]) && strtolower($args[5]) == 'quiet' ) {
							$quietMode = true;
						} else {
							$quietMode = false;
							$this->printHeader();
						}

						$scheduledBackupGetter = new scheduledBackupGetter();
						$scheduledBackupGetter->setLogStream($this->log);

						$hostname = $args[3];
						$backupName = $args[4];

						if( ! ( $scheduledBackup = $scheduledBackupGetter->getByHostnameAndName($hostname, $backupName) ) ) {
							throw new ProcessingException("Error: Could not find a backup for host: $hostname with name: $backupName");
						}


						try {

							$scheduledBackupInfo = $scheduledBackup->getInfo();

							// Check if the scheduled backup is active
							if( ! $scheduledBackup->isActive()) {
								print(basename(__FILE__).": The specified backup is not active - Reason: ".$scheduledBackup->inactive_reason." -- exiting...\n");
								die();
							}

							// Proceed with the backup!
							$snapshotTaker = new backupSnapshotTaker();

							$snapshotTaker->setLogStream($this->log);

							// If we are in quiet mode, turn off the output to stdout
							if( $quietMode ) {
								$snapshotTaker->setInfoLogVerbose(false);
							}

							$snapshotTaker->takeScheduledBackupSnapshot($scheduledBackup);

						} catch ( Exception $e ) {

							if($config['ALERTS']['enabled'] == true) {

								try {
									global $XBM_AUTO_HOSTNAME;

									$host = $scheduledBackup->getHost();
									$hostInfo = $host->getInfo();
									$sbInfo = $scheduledBackup->getInfo();


									if(get_class($e) == 'KillException') {
										$subj = "XtraBackup Manager - ALERT - Backup Aborted for ".$hostInfo['hostname'];
										$msg =  "The following backup was aborted by an administrator\n\n";
										$msg .= "MySQL Backup Host: ".$hostInfo['hostname']." - ".$hostInfo['description']."\n";
										$msg .= "XtraBackup Manager Host: ".$XBM_AUTO_HOSTNAME."\n";
										$msg .= "Scheduled Backup: ".$sbInfo['name']."\n";

									} else {

										$subj =  "XtraBackup Manager - ALERT - Backup Failure for ".$hostInfo['hostname'];

										$msg =  "A fatal error occurred while attempting to run the following backup\n\n";
										$msg .= "MySQL Backup Host: ".$hostInfo['hostname']." - ".$hostInfo['description']."\n";
										$msg .= "XtraBackup Manager Host: ".$XBM_AUTO_HOSTNAME."\n";
										$msg .= "Scheduled Backup: ".$sbInfo['name']."\n";
										$msg .= "Exception details:\n".$e->getMessage()."\n";
										$msg .= "Trace:\n".$e->getTraceAsString()."\n\n";
									}

								} catch ( Exception $secondaryException ) {

									$subj =  "XtraBackup Manager - ALERT - Backup Failure";

									$msg =  "A fatal error occurred while attempting to run a backup:\n\n";
									$msg .= "XtraBackup Manager Host: ".$XBM_AUTO_HOSTNAME."\n";
									$msg .= "Exception details:\n".$e->getMessage();
									$msg .= "Trace:\n".$e->getTraceAsString()."\n\n";
									$msg .= "Additionally a further error occurred while attempting to collect information on the original exception:\n\n";
									$msg .= "Exception details:\n".$secondaryException->getMessage()."\n";
									$msg .= "Trace:\n".$secondaryException->getTraceAsString()."\n\n";

								}

								$msg .= "-- \nThis was an automated message from ".XBM_RELEASE_VERSION."\n\n";

								if( ! mail($config['ALERTS']['email'], $subj, $msg, 'From: XtraBackup Manager <'.$config['ALERTS']['replyto'].'>') ) {
										$log->write(basename(__FILE__).": Error: Failed to send alert email!", XBM_LOG_ERROR);
								}
							}

							die();
						}

					} else {
						$this->printHeader();
						throw new InputException("Error: Invalid or incomplete parameters for the Scheduled Backup to run were given.\n\n"
										."  Syntax:\n\n	xbm ".$args[1]." run <hostname> <backup name> [quiet]\n\n"
										."  Example:\n\n	xbm ".$args[1]." run db01.mydomain.com 'Nightly Backup'\n\n"
										."  Note: Append the keyword 'quiet' to suppress output and only write to log files.\n\n");
					}

				break;

				// Catch unknown action
				default:
					$this->printHeader();
					echo("Error: Unrecognized action for ".$args[1]." context: ".$args[2]."\n\n");
					$this->printBackupHelpText($args);
				break;

			}

			return;
		}

		// Handle actions relating to hosts context
		// Accepts an argv array from the command line
		function handleHostActions($args) {

			// If we arent given any more parameters
			if(!isSet($args[2]) ) {

				// Just output some helpful information and exit
				echo("Error: Action missing.\n\n");
				$this->printHostHelpText($args);
				return;

			}

			// Handle actions
			switch($args[2]) {

				// Handle add
				case 'add':

					// Check for parameters first
					if(!isSet($args[3]) || !isSet($args[4]) ) {
						throw new InputException("Error: The Hostname and Description of the Host to add are required.\n\n  Example:\n\n	xbm ".$args[1].' add "db01.mydomain.com" "Production DB #1"');
					}

					$hostname = $args[3];
					$hostDesc = $args[4];

					$hostGetter = new hostGetter();
					$hostGetter->setLogStream($this->log);

					// Get the new Volume
					$host = $hostGetter->getNew($hostname, $hostDesc);

					echo("Action: New host created with hostname/description: ".$hostname." -- ".$hostDesc."\n\n");

					
				break;


				// Handle list
				case 'list':

					$hostGetter = new hostGetter();
					$hostGetter->setLogStream($this->log);

					$hosts = $hostGetter->getAll();

					echo("-- Listing all Hosts --\n\n");

					foreach($hosts as $host) {
						$hostInfo = $host->getInfo();
						echo("Hostname: ".$hostInfo['hostname']."  Description: ".$hostInfo['description']."\n");
						echo("Active: ".$hostInfo['active']."  Staging_path: ".$hostInfo['staging_path']."  SSH Port: ".$hostInfo['ssh_port']."\n\n");
					}

					if(sizeOf($hosts) == 0 ) {
						echo("	No hosts configured.\n\n");
					}

					echo("\n");
				break;

				// Handle edit
				case 'edit':

					if( !isSet($args[3]) || !isSet($args[4]) || !isSet($args[5]) ) {
						$errMsg = "Error: Hostname of the host to edit must be given along with parameter and value.\n\n";
						$errMsg .= "  Parameters:\n\n";
						$errMsg .= "	hostname - The hostname of the Host - May only be edited if no Scheduled Backups are configured for the host.\n";
						$errMsg .= "	description - The description of the Host - Can be edited at any time.\n";
						$errMsg .= "	staging_path - The temporary dir to use on the host for staging incremental backups - Can be edited at any time.\n";
						$errMsg .= "	ssh_port - The SSH port number to use to connect to the remote host - Can be edited at any time.\n";
						$errMsg .= "	active - Whether or the Host is active Y or N - Can be edited at any time.\n\n";
						$errMsg .= "  Example:\n\n	xbm ".$args[1].' edit db01.mydomain.com staging_path /storage1/backuptmp';

						throw new InputException($errMsg);

					}

					$hostname = $args[3];
					$hostParam = $args[4];
					$hostValue = $args[5];

					$hostGetter = new hostGetter();
					$hostGetter->setLogStream($this->log);
					if( ! ($host = $hostGetter->getByName($hostname) ) ) {
						throw new ProcessingException("Error: No Host exists with name: ".$hostname);
					}

					$host->setParam($hostParam, $hostValue);

					echo("Action: Host with hostname: ".$hostname." parameter '".$hostParam."' set to: ".$hostValue."\n\n");

				break;

				// Handle delete
				case 'delete':

					if( !isSet($args[3]) ) {
						throw new InputException("Error: Hostname of Host to delete must be given.");
					}

					$hostname = $args[3];

					$hostGetter = new hostGetter();
					$hostGetter->setLogStream($this->log);

					if( ! ($host = $hostGetter->getByName($hostname) ) ) {
						throw new ProcessingException("Error: No Host exists with hostname: ".$hostname);
					}

					$host->delete();

					echo("Action: Host with hostname: ".$hostname." deleted.\n\n");

				break;
 
				// Handle unknown action
				default:

					echo("Error: Unrecognized action for ".$args[1]." context: ".$args[2]."\n\n");
					$this->printHostHelpText($args);

				break;
				
			}


			return;

		}

		// Handle actions relating to volumes
		// Accepts an argv array from the command line
		function handleVolumeActions($args) {

			// If we arent given any more parameters
			if(!isSet($args[2]) ) {

				// Just output some helpful information and exit
				echo("Error: Action missing.\n\n");
				$this->printVolumeHelpText($args);

				return;
			}


			// Handle actions
			switch($args[2]) { 

				// Handle add
				case 'add':

					// Verify that we have all the params we need and they are OK.

					// Are they set..
					if(!isSet($args[3]) || !isSet($args[4]) ) {
						// Input exception
						throw new InputException("Error: Name and Path of Backup Volume to add must be given.\n\n  Example:\n\n	xbm ".$args[1].' add "Storage Array 1" /backup');
					}

					$volumeName = $args[3];
					$volumePath = rtrim($args[4], '/');

					$volumeGetter = new volumeGetter();
					$volumeGetter->setLogStream($this->log);

					// Get the new Volume
					$volume = $volumeGetter->getNew($volumeName, $volumePath);

					echo("Action: New volume created with name/path: ".$volumeName." -- ".$volumePath."\n\n");
	
				break;

				// Handle listing
				case 'list':

					$volumeGetter = new volumeGetter();
					$volumeGetter->setLogStream($this->log);


					$volumes = $volumeGetter->getAll();


					echo("-- Listing all Backup Volumes --\n\n");

					foreach($volumes as $volume) {
						$volumeInfo = $volume->getInfo();
						echo("Name: ".$volumeInfo['name']."\tPath: ".$volumeInfo['path']."\n");
					}

					echo("\n\n");
					
				break;

				// Handle editing
				case 'edit':

					if( !isSet($args[3]) || !isSet($args[4]) || !isSet($args[5]) ) {
						$errMsg = "Error: Name of Backup Volume to edit must be given along with parameter and value.\n\n";
						$errMsg .= "  Parameters:\n\n";
						$errMsg .= "	name - The name of the Backup Volume - Can be edited at any time.\n";
						$errMsg .= "	path - The path of the Backup Volume - May only be edited if no Scheduled Backups are configured for the volume.\n\n";
						$errMsg .= "  Example:\n\n	xbm ".$args[1].' edit "Storage Array 1" path /storage1';

						throw new InputException($errMsg);

					}

					$volumeName = $args[3];
					$volumeParam = $args[4];
					$volumeValue = $args[5];

					$volumeGetter = new volumeGetter();
					$volumeGetter->setLogStream($this->log);
					if( ! ($volume = $volumeGetter->getByName($volumeName) ) ) {
						throw new ProcessingException("Error: No Backup Volume exists with name: ".$volumeName);
					}

					$volume->setParam($volumeParam, $volumeValue);

					echo("Action: Backup Volume: ".$volumeName." parameter '".$volumeParam."' set to: ".$volumeValue."\n\n");
					
				break;

				// Handle deleting
				case 'delete':

					if( !isSet($args[3]) ) {
						throw new InputException("Error: Name of Backup Volume to delete must be given.");
					}

					$volumeName = $args[3];

					$volumeGetter = new volumeGetter();
					$volumeGetter->setLogStream($this->log);
					if( ! ($volume = $volumeGetter->getByName($volumeName) ) ) {
						throw new ProcessingException("Error: No Backup Volume exists with name: ".$volumeName);
					}

					$volume->delete();

					echo("Action: Backup Volume: ".$volumeName." deleted.\n\n");

				break;

				// Handle unknown action
				default:

					echo("Error: Unrecognized action for ".$args[1]." context: ".$args[2]."\n\n");
					$this->printVolumeHelpText($args);

				break;
			}


			return;

		}

		// Handler for upgrading the xbm database
		function handleUpgradeAction() {

			$schemaUpgrader = new schemaUpgrader();
			$schemaUpgrader->setLogStream($this->log);

			if($schemaUpgrader->upgrade()) {
				die("Success: ".$schemaUpgrader->resultMsg."\n\n");
			} else {
				die("Failure: ".$schemaUpgrader->resultMsg."\n\n");
			}


			return;

		}


		// Handler for printing status information about running backup tasks
		function handleStatusAction() {

			$backupJobGetter = new backupJobGetter();
			$backupJobGetter->setLogStream($this->log);
			$scheduledBackupGetter = new scheduledBackupGetter();
			$scheduledBackupGetter->setLogStream($this->log);
			$hostGetter = new hostGetter();
			$hostGetter->setLogStream($this->log);

			$runningJobs = $backupJobGetter->getRunning();

			$backupRows = Array();
			foreach($runningJobs as $job) {

				$info = $job->getInfo();
				$scheduledBackup = $job->getScheduledBackup();
				$host = $scheduledBackup->getHost();

				$hostInfo = $host->getInfo();
				$sbInfo = $scheduledBackup->getInfo();

				$backupRows[] = array(
									'Job ID' => $info['backup_job_id'], 
									'Host' => $hostInfo['hostname'],
									'Backup Name' => $sbInfo['name'],
									'Start Time' => $info['start_time'],
									'Status' => $info['status'],
									'PID' => $info['pid']
								);
			}
			if(sizeOf($backupRows) > 0) {
				$textTable = new ArrayToTextTable($backupRows);
				$textTable->showHeaders(true);
				$tableOutput = $textTable->render(true);

				print("Currently Running Backups:\n\n".$tableOutput."\n\n");
			} else {
				print("There are no backups currently running.\n\n");
			}

		}


		// Handler for killing backup jobs
		function handleKillAction($args) {

			if(!isSet($args[2]) || !is_numeric($args[2]) ) {
				echo("Error: Expected a numeric Job ID as a parameter but did not get one.\n\n");
				return;
			}

			$jobGetter = new backupJobGetter();
			$jobGetter->setLogStream($this->log);

			if( ! ( $job = $jobGetter->getById($args[2], false) ) ) {
				throw new ProcessingException("Error: Unable to find a Backup Job with ID ".$args[2].".");
			} 

			if($job->isRunning() ) {

				$job->setKilled(true);

				echo("Action: Backup Job ID ".$args[2]." was killed.\n\n");
			} else {

				echo("Error: The Backup Job with ID ".$args[2]." is not running.\n\n");
			}

			return;
			
		}

	}

?>
