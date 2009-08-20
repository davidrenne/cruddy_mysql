<?php
/*
***************************************************************************
*   Copyright (C) 2007 by Cesar D. Rodas                                  *
*   cesar@sixdegrees.com.br                                               *
*                                                                         *
*   Permission is hereby granted, free of charge, to any person obtaining *
*   a copy of this software and associated documentation files (the       *
*   "Software"), to deal in the Software without restriction, including   *
*   without limitation the rights to use, copy, modify, merge, publish,   *
*   distribute, sublicense, and/or sell copies of the Software, and to    *
*   permit persons to whom the Software is furnished to do so, subject to *
*   the following conditions:                                             *
*                                                                         *
*   The above copyright notice and this permission notice shall be        *
*   included in all copies or substantial portions of the Software.       *
*                                                                         *
*   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,       *
*   EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF    *
*   MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.*
*   IN NO EVENT SHALL THE AUTHORS BE LIABLE FOR ANY CLAIM, DAMAGES OR     *
*   OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, *
*   ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR *
*   OTHER DEALINGS IN THE SOFTWARE.                                       *
***************************************************************************
*/

class sqlite extends dbm_model {
    function doEscape($str) {
        return sqlite_escape_string($str);
    }
    
    function _open($conn) {
		if ( substr(PHP_OS,0,3) == "WIN"  ) 
 		   	$path = $conn['host'].":/".$conn['db'];
		else
			$path = $conn['host']."/".$conn['db'];
		if ($this->dbh = sqlite_open($path, 0666, $sqliteerror)) 
			return true;
		return false;
    }
    
    function _close() {
        sqlite_close($this->dbh);
    }
    
    function _getError() {
        return sqlite_error_string( sqlite_last_error($this->dbh) );
    }
    
    function _query($sql) {
        $handler = sqlite_unbuffered_query($this->dbh,$sql);
        if (!$handler) 
            return false;
        while ($row = sqlite_fetch_array($handler)) {
            $buffer[] = $row;
        }
        return $buffer;
    }
    
    function _execute($sql) {
        return sqlite_exec($this->dbh,$sql) !== false;
    }
}
?>