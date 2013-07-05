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

 	/* classes related to DB conections */


	class dbConnection extends mysqli {

		private static $conn;
		private $log;

		// Construct
		private function __construct($host, $username, $password, $schema, $port, $socket = false) {

			if( $socket === false ) {
				parent::__construct($host, $username, $password, $schema, $port);
			} else {
				parent::__construct($host, $username, $password, $schema, $port, $socket);
			}
			$this->log = false;

		}

		public function setLogStream($log) {
			$this->log = $log;
		}

		// Return a new dbConnection connection object to use to connect to the DB
		public static function getInstance($logStream, $checkVersion=true) {

			if(!self::$conn) {

				global $config;

				// If we have a socket set - use it..
				if(isSet($config['DB']['socket']) ) {	
					self::$conn = new dbConnection(
								$config['DB']['host'], 
								$config['DB']['user'], 
								$config['DB']['password'], 
								$config['DB']['schema'], 
								$config['DB']['port'],
								$config['DB']['socket']
								);
				// If no socket setup - just use TCP settings
				} else {
					self::$conn = new dbConnection(
								$config['DB']['host'], 
								$config['DB']['user'], 
								$config['DB']['password'], 
								$config['DB']['schema'], 
								$config['DB']['port']
								);
				}

				// Check for error connecting...
				// We use mysqli_connect_error and errno instead of $conn->connect_error / errno because it was broken before PHP 5.2.9 and 5.3.0
				if(mysqli_connect_error()) {
					throw new Exception('dbConnection->getInstance: ' . "Error: Can't connect to MySQL (".mysqli_connect_errno().") - please check that settings in config.php are correct."
						. self::$conn->connect_error);
				}

			} else {
				// Test the connection -- throw it away and reconnect if it is bad.
				if( !self::$conn->ping() ) {
					self::$conn = false;
					return self::getInstance($logStream, $checkVersion);
				}
			}

			self::$conn->setLogStream($logStream);

			// If this db connection getter is configged to check the version (default is to do so), then check it.
			if($checkVersion) {
				self::$conn->checkSchemaVersion();
			}

			return self::$conn;

		}


		// Construct
		public function query($sql) {

			if( ( $this->log !== false ) ) {
				$backtrace = debug_backtrace();
				if(isSet($backtrace[1]))
					$this->log->write($backtrace[1]['class'].$backtrace[1]['type'].$backtrace[1]['function'].': Sending SQL: '.$sql, XBM_LOG_DEBUG);
				else
					$this->log->write('Sending SQL: '.$sql, XBM_LOG_DEBUG);

				$timer = new Timer();
			}

			$res = parent::query($sql);

			if( ( $this->log !== false ) ) {
				$elapsed = $timer->elapsed();
				if(isSet($backtrace[1]))
					$this->log->write($backtrace[1]['class'].$backtrace[1]['type'].$backtrace[1]['function'].': Query took '.$elapsed, XBM_LOG_DEBUG);
				else
					$this->log->write('Query took '.$elapsed, XBM_LOG_DEBUG);

			}

			return $res;

		}

		// Get the schema version of the database
		public function getSchemaVersion() {

			$sql = "SELECT version as version FROM schema_version";

			if( ! ( $res = $this->query($sql) ) ) {
				throw new Exception('dbConnetion->getSchemaVersion: '."Error: Query: $sql \nFailed with MySQL Error: $this->error");
			}

			if( ! ( $row = $res->fetch_array() ) ) {
				throw new Exception('dbConnection->getSchemaVersion: '."Error: Could not find any information in schema_version table. Please ensure the database is correctly initialized with schema_init.sql.");
			}

			return $row['version'];

		}

		// Check the schema version of the DB is XBM_SCHEMA_VERSION
		public function checkSchemaVersion() {

			$version = $this->getSchemaVersion();

			if( $version != XBM_SCHEMA_VERSION ) {
				throw new Exception("Error: Found an incompatible database schema version: ".$version." required version: ".XBM_SCHEMA_VERSION." -- Try running 'xbm upgrade'");
			}

			return true;

		}

	}

?>
