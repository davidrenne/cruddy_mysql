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

class mysql extends dbm_model {
    function doEscape($str) {
        return addslashes($str);
    }
    
    function _open($conn) {
        $this->dbh = mysql_connect($conn['host'],$conn['user'],$conn['pass']);
        if (!$this->dbh) 
            return false;
        return mysql_select_db($conn['db'],$this->dbh);
    }
    
    function _close() {
        mysql_close($this->dbh);
    }
    
    function _getError() {
        return mysql_error($this->dbh);
    }
    
    function _query($sql) {
        $handler = mysql_query($sql,$this->dbh);
        if (!$handler) 
            return false;
        while ($row = mysql_fetch_array($handler)) {
            $buffer[] = $row;
        }
        mysql_free_result($handler);
        return $buffer;
    }
    
    function _execute($sql) {
		$handler = mysql_query($sql,$this->dbh);
        if (!$handler) 
            return false;
        @mysql_free_result($handler);
        return true;
    }
}
?>