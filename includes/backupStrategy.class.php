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


	class backupStrategy {


		function __construct($id) {
			if(!is_numeric($id) ) {
				throw new Exception('backupStrategy->__construct: '."Error: The ID for this object is not an integer.");
			}
			$this->id = $id;
			$this->log = false;
		}

		// Set the logStream to write out to
		function setLogStream($log) {
			$this->log = $log;
		}

		// Get info about this backupStrategy
		function getInfo() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('backupStrategy->getInfo: '."Error: The ID for this object is not an integer.");
			}


			


			$conn = dbConnection::getInstance($this->log);


			$sql = "SELECT * FROM backup_strategies WHERE backup_strategy_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('backupStrategy->getInfo: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
	
			$info = $res->fetch_array();

			return $info;

		}


        // Validate a strategyCode
        public static function validateStrategyCode($code) {

            if(!isSet($code)) {
                throw new InputException("Error: Expected a Backup Strategy Code as input, but did not get one.");
            }

            if( ! in_array($code, explode(',',XBM_VALID_STRATEGY_CODES) ) ) {
                throw new InputException("Error: The Backup Strategy Code given is invalid. Valid codes are: ".XBM_VALID_STRATEGY_CODES);
            }

            return;
        }

	}

?>
