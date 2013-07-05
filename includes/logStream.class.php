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

	class logStream {

		function __construct($filename, $verbose, $level = 0) {
			$this->filename = $filename;
			$this->verbose = $verbose;
			$this->open = false;
			$this->level = $level;
			$this->levelNames = Array();
			$this->levelNames[0] = 'DEBUG';
			$this->levelNames[1] = 'INFO';
			$this->levelNames[2] = 'ERROR';
			
		}


		function setLevel($level) {
			$this->level = $level;
		}

		function write($msg, $level) {
	
			// Only attempt to write if the message level is not lower than the logStream level
			if( $level < $this->level ) {
				return true;
			}

			// If we didn't open, then open for writing.
			if($this->filename !== false) {
				if( ! $this->open ) {
					$this->open();
				}
			}


			// Write
			$toWrite = date('Y-m-d H:i:s O'). " [".$this->levelNames[$level]."] : [ ".$msg." ]\n";

			if($this->filename !== false) {
				if( ! fwrite($this->fp, $toWrite) ) {
					throw new Exception('logStream->write: '."Error: Failed attempt to write to log file - $this->filename");
				}
			}

			if($this->verbose)
				echo($toWrite);

			return true;

		}

		// Open the logstream for writing...
		function open() {

			if($this->filename === false) {
				$this->open = true;
				return true;
			}

			if( ! ($this->fp = @fopen($this->filename, 'a') ) ) {
				// Could not open logStream for writing.
				throw new Exception('logStream->open: '."Error: Failed to open log file - $this->filename");
			} else {
				$this->open = true;
			}

			return true;

		}

	}

?>
