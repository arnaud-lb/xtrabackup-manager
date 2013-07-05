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


	// Exception that occurs with some issue in validating input
	class InputException extends Exception {
	}

	// Exception that occurs when attempting to process a requiest/action
	class ProcessingException extends Exception {
	}

	// Exception that occurs when a problem interacting with the database occurs
	class DBException extends Exception {
	}

	// Exception that occurs when the process was killed
	class KillException extends Exception {
	}

	// Exception that occurs when merging a delta backup into a full directory
	class MergeException extends Exception {

		public function __construct($msg, $errorMsg = "", backupSnapshot $failedSnapshot=NULL) {

			$this->errorMsg = $errorMsg;
			$this->failedSnapshot = $failedSnapshot;
			parent::__construct($msg);

		}

		public function getErrorMessage() {
			return $this->errorMsg;
		}

		public function getFailedSnapshot() {
			return $this->failedSnapshot;	
		}
	}


?>
