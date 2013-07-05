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


	// Used to restore backupSnapshots - either locally or remotely
	class backupRestorer {


		function __construct() {
			$this->log = false;
			$this->infolog = false;
		}

		function setLogStream($log) {
			$this->log = $log;
		}

		function setInfoLogStream($log) {
			$this->infolog = $log;
		}

		function validate($backupSnapshot) {

			// Check we got an object
			if(!is_object($backupSnapshot)) {
				throw new Exception('backupRestorer->validate: '."Error: Expected a backupSnapshot object and did not get one.");
			}

			$snapInfo = $backupSnapshot->getInfo();
			if($snapInfo['status'] != 'COMPLETED') {
				throw new Exception('backupRestorer->validate: '."Error: This backupSnapshot is not in COMPLETED status.");
			}

			return true;

		}

		// Restore $backupSnapshot or $materializedBackup to local path $path
		function restoreLocal($backupSnapshot, $path) {

			$this->validate($backupSnapshot);

			// Sanitise the path
			if( ( $path == '/' ) || ( strlen($path) == 0 ) ) {
				throw new Exception('backupRestorer->restoreLocal: '."Error: Detected unsafe path to restore to - $path");
			}

			// Now check that it exists - if not try create it
			if( ! file_exists($path) ) {
				if( !mkdir($path) ) {
					throw new Exception('backupRestorer->restoreLocal: '."Error: Attempted to create directory for restore, but failed -- $path");
				}
				// Set permissions to be rwx owner and rx group.
				if( ! @chmod($path, 0750) ) {
					throw new Exception('backupRestorer->restoreLocal: '."Error: Attempted to change permissions for newly created restore path, but failed -- $path");
				}
			} else if(!is_dir($path)) {
				throw new Exception('backupRestorer->restoreLocal: '."Error: Specified path location is not a directory -- $path");
			} 

			if( ! is_writeable($path) ) {
				throw new Exception('backupRestorer->restoreLocal: '."Error: The specified path location is no writeable -- $path");
			}

			// Strip trailing space (and spaces if there are any for some weird reason
			$path = rtrim($path, '/ ');

			switch(get_class($backupSnapshot)) {
		
				// Handle a regular backupSnapshot class	
				case 'backupSnapshot':
					// Get the group for the backup and find the seed for that group.
					$group = $backupSnapshot->getSnapshotGroup();

					$scheduledBackup = $backupSnapshot->getScheduledBackup();

					// Find the seed of the backupSnapshot
					$seedSnapshot = $group->getSeed();

					// Copy the seed to the right place
					$seedPath = $seedSnapshot->getPath();

					if($seedSnapshot->id == $backupSnapshot->id) {
						$msg = 'Copying snapshot from '.$seedPath.' to path '.$path;
					} else {
						$msg = 'Copying seed snapshot from '.$seedPath.' to path '.$path;
					}

					$this->infolog->write($msg, XBM_LOG_INFO);

					$copyCommand = 'cp -R '.$seedPath.'/* '.$path.'/';

					exec($copyCommand, $output, $returnVar);
					if($returnVar <> 0 ) {
						throw new Exception('backupRestorer->restoreLocal: '."Error: Failed to copy from $seedPath to path $path using command: $copyCommand -- Got output:\n".implode("\n",$output));
					}
			

					$this->infolog->write('Done copying.', XBM_LOG_INFO);

					// If the seed is what we wanted to restore, we're done!
					if($seedSnapshot->id == $backupSnapshot->id) 
						return true;

					$xbBinary = $scheduledBackup->getXtraBackupBinary();

					// Proceed with applying any incrementals needed to get to the snapshot we want!
					$snapshotMerger = new backupSnapshotMerger();

					$parentSnapshot = $seedSnapshot;

					do {

						$deltaSnapshot = $parentSnapshot->getChild();
						$deltaPath = $deltaSnapshot->getPath();

						$this->infolog->write('Merging incremental snapshot deltas from '.$deltaPath.' into path '.$path, XBM_LOG_INFO);
						try {
							$snapshotMerger->mergePaths($path, $deltaPath, $xbBinary);
						} catch( MergeException $mergeEx ) {
							throw new MergeException($mergeEx->getMessage(), $mergeEx->getFailMessage(), $deltaSnapshot);
						}	
						$this->infolog->write('Done merging.', XBM_LOG_INFO);
						$parentSnapshot = $deltaSnapshot;

					} while ( $deltaSnapshot->id != $backupSnapshot->id );


				break;

				case 'materializedSnapshot':

					$snapPath = $backupSnapshot->getPath();
					$msg = 'Copying snapshot from '.$snapPath.' to path '.$path;
					$this->infolog->write($msg, XBM_LOG_INFO);

                    $copyCommand = 'cp -R '.$snapPath.'/* '.$path.'/';

                    exec($copyCommand, $output, $returnVar);
                    if($returnVar <> 0 ) {
                        throw new Exception('backupRestorer->restoreLocal: '."Error: Failed to copy from $snapPath to path $path using command: $copyCommand -- Got output:\n".implode("\n",$output));
                    }


                    $this->infolog->write('Done copying.', XBM_LOG_INFO);
				break;

				default:
					throw new ProcessingException('backupRestorer->restoreLocal: '."Error: Unrecognized class of backup snapshot to restore.");
				break;
			}

			$this->infolog->write('Local restore to '.$path.' complete!', XBM_LOG_INFO);
			return true;	


		}

	
		function restoreRemote($backupSnapshot, $remoteExpression) {
			$this->validate($backupSnapshot);
			return false;
		}


	}

?>
