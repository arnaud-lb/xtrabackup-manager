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


	class mysqlType {


		function __construct($id) {
			if(!is_numeric($id)) {
				throw new Exception('mysqlType->getInfo: '."Error: The ID for this object is not an integer.");
			}
			$this->id = $id;
			$this->log = false;
		}

		function setLogStream($log) {
			$this->log = $log;
		}

		function getInfo() {

			global $config;

			if(!is_numeric($this->id)) {
				throw new Exception('mysqlType->getInfo: '."Error: The ID for this object is not an integer.");
			}


			
			$conn = dbConnection::getInstance($this->log);

			$sql = "SELECT * FROM mysql_types WHERE mysql_type_id=".$this->id;


			if( ! ($res = $conn->query($sql) ) ) {
				throw new Exception('mysqlType->getInfo: '."Error: Query: $sql \nFailed with MySQL Error: $conn->error");
			}
	
			$info = $res->fetch_array();

			return $info;

		}


	}

?>
