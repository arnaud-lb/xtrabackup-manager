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


	// Used to upgrade the database schema from one version to another
	class schemaUpgrader {

		function __construct() {
			$this->log = false;
		}

		function setLogStream($log) {
			$this->log = $log;
		}

		function upgrade() {

			// Look for running backups without checking schema version
			$runningBackupGetter = new runningBackupGetter();
			$runningBackupGetter->setSchemaVersionChecks(false);

			// Get the runningbackups - this getter automatically removes stale entries, so it should only return truly running pids...
			$runningBackups = $runningBackupGetter->getAll();
			$backupCount = sizeOf($runningBackups);

			// If we find running backups, abort with error
			if($backupCount > 0 ) {
				throw new ProcessingException('schemaUpgrader->upgrade: '."Error: Detected ".$backupCount." backup(s) currently running. Please retry upgrading the schema later.");
			}

			// Create a new DB connection getter that does not check the schema version...
			$conn = dbConnection::getInstance($this->log, false);

			$schemaVersion = $conn->getSchemaVersion();

			switch (true) {
				// If the schema versions match - all is good  - nothing to do
				case ( $schemaVersion == XBM_SCHEMA_VERSION ):
					$this->resultMsg = "The schema version of the XtraBackup Manager database is already at the expected version number (".XBM_SCHEMA_VERSION.").";
					return true;
				break;

				// If the schema version of the DB is higher than what we need - throw an error!
				case ( $schemaVersion > XBM_SCHEMA_VERSION ):
					$this->resultMsg = "The schema version of the XtraBackup Manager database (".$schemaVersion.") is higher than the expected version number (".XBM_SCHEMA_VERSION.").";
					return false;
				break;

				// Schema version is < expected version - we need to upgrade!
				case ( $schemaVersion < XBM_SCHEMA_VERSION ):

					// Find all the files in the $XBM_AUTO_INSTALLDIR/sql/changes/ directory
					global $XBM_AUTO_INSTALLDIR;
					$files = glob($XBM_AUTO_INSTALLDIR.'/sql/changes/*');
					// Sort them just in case the OS gives them back in a strange order
					asort($files);

					// Walk the array and build a list of scripts to run of files that are numeric and > $schemaVersion
					$toRun = Array();
					foreach($files as $filename) {
						$basename = basename($filename);
						if(is_numeric($basename) && ( $basename > $schemaVersion ) && ($basename <= XBM_SCHEMA_VERSION) ) {
							$toRun[] = $filename;
						} 
					}

					// Walk over the array applying each schema update
					foreach($toRun as $scriptName) {

						$version = basename($scriptName);
						// Run the script
						$sql = file_get_contents($scriptName);
						if( ! ($res = $conn->query($sql) ) ) {
							throw new Exception('schemaUpgrader->upgrade: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
						}

						// Update the schemaVersion in the DB
						$sql = "UPDATE schema_version SET version=".$version;
						if( ! ($res = $conn->query($sql) ) ) {
							throw new Exception('schemaUpgrader->upgrade: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
						}
						
					}


					$this->resultMsg = "The XtraBackup Manager database schema was successfully upgraded to schema version ".XBM_SCHEMA_VERSION.".";
					return true;					
					
				break;

				// Catch all - should never get here 
				default:
					$this->resultMsg = "An issue occurred when comparing schema versions. This probably indicated a bug in XtraBackup Manager.";
					return false;
				break;
			}

			// Catch all - should never get here 
			$this->resultMsg = "An issue occurred when comparing schema versions. This probably indicated a bug in XtraBackup Manager.";
			return false;




			


			// Get the schema version

			

			
		}
	}

?>
