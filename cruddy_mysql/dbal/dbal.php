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
$dir = dirname(__FILE__);
require("${dir}/safeIO.php");

define('DBM_FOLDER',$dir.'/dbm/');
define('DIR_PERMISSION','PHP could not write the cache in given folder');
define('NO_SQL','No SQL statement to execute');
define('ERR_CONN_STR','Error on connection string');
define('NO_CONNECTED','You are not connected to a database');
class dbal {
    /**
     *    Database manager
     *
     *    @var object
     *    @access private
     */
    var $dbm; 
    /**
     *    Information about connection
     *
     *    @var object
     *    @access private
     */
    var $info;
    var $_sql;
    /**
     *    Is connected to database.
     *
     *    @var bool
     *    @access private
     */
    var $isConnected;
    var $cacheDir='.';
    var $buffer;
    
    /**
     *  Class constructor
     *
     *  @access private
     */
    function dbal($uri='') {
        if ($uri!='')$this->connect($uri);
    }    
    
    /**
     *  Connect to a database.
     *  @param string $uri SQL connection string (example: mysql://foo:bar@localhost/bardb)
     *  @access public  
     */
    function connect($uri) {
        $info = parse_url($uri);
        $this->info['scheme']=  isset($info['scheme']) ? $info['scheme'] : '';
        $this->info['user']  =  isset($info['user']) ? $info['user'] : '';
        $this->info['pass']  =  isset($info['pass']) ? $info['pass'] : '';
        $this->info['host']  =  isset($info['host']) ? $info['host'] : '';
        $this->info['db']    =  isset($info['path']) ? substr($info['path'],1) : '';
        return $this->loadDBM();    
    }
    /**
     *  Load a Database Handler
     *
     *  @access private
     *  @return bool
     */
    function loadDBM() {
        if ($this->info['scheme']=='') {
            trigger_error(ERR_CONN_STR,E_USER_WARNING);
            return false;
        }
        require_once(DBM_FOLDER.$this->info['scheme'].".php");
        $this->dbm = new $this->info['scheme'];
        if (!is_subclass_of($this->dbm,'dbm_model') ) {
            die('error in mysql database manager class');
        }
        return true;
    }
    
    /**
     *  Execute an SQL statement, 
     *
     *  In diference to query, this method do not retrieve 
     *  result from database, only true if success or false.
     *
     *  @access public
     */
    function execute() {
        if ( $this->_query_prepare( func_get_args(), $param, $sql) ) {
            if (!$this->_connect_db()) {
                return false;
            }
            return $this->dbm->_execute($sql);
        }
    }
    /**
     *  Execute an SQL statement, 
     *
     *  And retrieve data from database if sucess or false if there is
     *  an error
     *  
     */
    function query() {
        $arg_list = func_get_args();
        /**
         *  Prepare query.
         */
        if ( $this->_query_prepare( $arg_list, $param,$sql) ) {
            /**
             *    Get the time to cache, by default 0
             */
            $this->TTL=0;
            if ( is_numeric($arg_list[$param]) ) {
                $this->TTL = $arg_list[$param];
                $param++;
            }

            if ( $this->TTL > 0) {
                $this->getCacheFileName();
                /* Try to load the cache */
                if (!$this->loadCache($result)) {
                    /* Fails, connect database, then cache*/
                    if (!$this->_connect_db()) {
                        return false;
                    }
                    $result = $this->dbm->_query($sql);
                    if ($result===false) return false;
                    $this->saveCache($result);
                }
            }    else {
                if (!$this->_connect_db()) {
                    return false;
                }
                $result = $this->dbm->_query($sql);
                if ($result===false) return false;
            }
            $f = new dbm_result($result);
            return $f;
        }
        return false;
    }
    
    function compile($_sql) {
        $sql = & $this->_sql;
        $len=strlen($_sql);
        $sql = '';
        for($i=0; $i < $len; $i++) {
            switch ($_sql[$i]) {
                case '"':
                case "'":
                    $start = $_sql[$i];
                    $sql.=$start;
                    for(; $i < $len; $i++) {
                        switch ($_sql[$i]) {
                            case $start:
                                break;
                            case '\\':
                                $sql.=$_sql[$i];
                                $i++;
                            default:
                                $sql.=$_sql[$i];
                                break;
                        }
                    }
                    $sql.=$start;
                    break;
                case ":":
                    $sql.='$';
                    break;
                default:
                    $sql.=$_sql[$i];
                    break;
            }
        }
    }
    
    function close() {
        if ($this->isConnected) {
            $this->dbm->_close();
            $this->isConnected=false;
        }
        $this->dbm=null;
        $this->buffer=null;
    }
    
    function getLastError() {
        return $this->dbm->_getError();
    }

    /**
     *  Connect to a the Database handler
     *
     *  @access private
     *  @return bool
     */
    function _connect_db() {
        if (!$this->isConnected) {
            $this->isConnected=true;
            return $this->dbm->_open($this->info);
        }
        return true;
    }
    
    /**
     *  Prepare an SQL statement
     *
     *  
     */
    function _query_prepare($arg_list,&$param, &$sql) {
        if ( !$this->dbm ) {
            trigger_error(NO_CONNECTED,E_USER_WARNING);
            return false;
        }
        $result = & $this->buffer;
        $sql =  $this->_sql;
        $dbm = & $this->dbm;
        $param = 0;

        if ( is_string($arg_list[$param]) ) {
            $this->compile($arg_list[$param]);
            $sql =  $this->_sql;
            $param++;
        }
        
        if ( $sql=='') {
            trigger_error(NO_SQL,E_USER_ERROR);
            return false;
        }
        
        /**
         *    Change SQL variable by the value of the
         *    array.
         */
        if ( is_array($arg_list[$param]) ) {
            foreach($arg_list[$param] as $k => $v){  
                eval('$'.$k.'=\'"\'.$dbm->doEscape($arg_list[$param][$k]).\'"\';');
            } 
            $sql = eval('return "'.addslashes($sql).'";');
            $param++;
        } 
        $this->__sql = $sql;
        return true;
    }
    
    /**
     *  Load Query result from cache
     *
     *  @param string &$r Here the result will be referenced.
     *  @return bool
     */
    function loadCache(&$r) {
        if ($this->TTL <= 0) return false;
        
        $sio = new safeIO;
        $e=$sio->open($this->cachePath,READ);
        if ($e) {
            $f = stat($this->cachePath);
            $e =  $f['mtime']+$this->TTL > time();
            if ($e) {
                $t = unserialize( $sio->read( filesize($this->cachePath)) );
                foreach($t->content as $k => $v) {
                    $head = & $r[$k];
                    $i=0;
                    foreach($v as $k1 => $v1) {
                        $head[$i ] = $v1;
                        $head[$k1] = & $head[$i++];
                        
                    }
                }
            }
            $sio->close();
        }
        return $e;
    }
    /**
     *  Save query result on cache
     *
     *  @param string &$r Query result
     */
    function saveCache(&$r) {
        if ($this->TTL <= 0) return;
        /**
         *    PHP puts on database result
         *    and index with the column index
         *     and column name. For keep hdd space
         *    we're only store an array with colum
         *    name.
         */
        $buffer=array();
        foreach($r as $k => $v)   {
            $new = & $buffer[$k];
            foreach($v as $k1 => $v1)
                if (!is_numeric($k1))
                    $new[$k1] = $v1;
        }
        /**/
        $cache = new stdClass;
        $cache->ttl = $this->TTL;
        $cache->content = $buffer;
        $str = serialize($cache);
        /* Instante the safe IO */
        $sio = new safeIO;
        $e=$sio->open($this->cachePath,WRITE);
        if ($e) {
            $sio->write( $str,strlen($str));
            $sio->close();
        }
    }
    
    /**
     *  Set cache repository dir
     *
     *  @param string $dir Directory for save cache
     */
    function setCacheDir($dir) {
        if ( is_dir($dir) && is_writable($dir) ) {
            $this->cacheDir = $dir;
        } else {
            trigger_error(DIR_PERMISSION,E_USER_WARNING);
        }
    }
    /**
     *  Get cache repository dir
     *
     *  @return string
     */
    function getCacheDir() {
        return $this->cacheDir;
    }
    /**
     *  Get cache filename
     *
     *  @return string Cache file name
     */
    function getCacheFileName() {
        $pwd = & $this->cacheDir;
        $code  = md5(implode('',$this->info).$this->__sql);
        $path=$code[0]."/".$code[1]."/";
        if (!file_exists($pwd."/".$code[0]) ) mkdir($pwd."/".$code[0]);
        if (!file_exists($pwd."/".$path) ) mkdir($pwd."/".$path);
        $this->cachePath = $this->cacheDir."/".$code[0]."/".$code[1]."/$code.cache";
    }
}

class dbm_result {
    var $_binders;
    var $_result;
    var $_i;
    var $_length;
    
    function dbm_result($arr=array()) {
        $this->_i = 0;
        $this->_length = count($arr);
        $this->_result = $arr;
    }
    
    function fetchAll() {
        return $this->_result;
    }
    
    function & bindColumn($name) {
        $this->_binders[$name]="";
        return $this->_binders[$name];
    }
    
    function setBegin() {
        $this->_i = 0;
    }
    
    
    function getNext() {
        if ( isset($this->_result[$this->_i]) ) {
            foreach($this->_result[$this->_i] as $k => $v) {
                if ( isset($this->_binders[$k]) ) {
                    $this->_binders[$k] = $v;
                }
            }
        } else
            return false;
        return $this->_i< $this->_length ? $this->_result[$this->_i++] : false ;
    }
    
}

class dbm_model {
    /**
     *    Database handler
     *
     *    @var resource
     *    @access private
     */
    var $dbh; 
    function _open() {
        die('this method must be overwrited');
    }
    function _close() {
        die('this method must be overwrited');
    }
    function _query() {
        die('this method must be overwrited');
    }
    function _execute() {
        die('this method must be overwrited');
    }
    function _getError() {
        die('this method must be overwrited');
    }
    function doEscape() {
        die('this method must be overwrited');
    }
    

}

?>
