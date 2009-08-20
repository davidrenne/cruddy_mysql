<?php
/*
***************************************************************************
*   Copyright (C) 2007 by Cesar D. Rodas                                  *
*   crodas@cnc.una.py                                                     *
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
define ('UNLOCK',0);
define ('SHARED',2);
define ('EXCLUSIVE',4);

define('MAX_LOCK_TRY',5);

define('READ',2);
define('APPEND',4);
define('WRITE',5);

class safeIO {
    var $stat;
    
    /* Private vars */
    var $fbased;
    var $Lock;
    var $fp;
    var $filename;
    
    function safeIO() {
        $this->fbased = $this->isRunningOsWin();
        register_shutdown_function("on_exit");
    }
    
    function read($offset) {
        return fread($this->fp, $offset);
    }
    
    function write($str, $length) {
        return fwrite($this->fp, $str, $length);
    }
    
    function seek($offset,$where) {
        return fseek($this->fp,$offset,$where);
    }    
    
    function open($file,$method) {
        $this->filename = $file;
        
        switch ($method) {
            case READ:
                if ( !file_exists($file) )     
                    return false;
                
                $this->fp = fopen($file,"rb");
                if (! $this->sharedLock() ) {
                    $this->close();
                    return false;
                }
                break;
            case APPEND:        
                $this->fp = fopen($file,"ab");
                if (! $this->exclusiveLock() ) {
                    $this->close();
                    return false;
                }
                break;
            case WRITE:
                $this->fp = fopen($file,"ab");
                if (! $this->exclusiveLock() ) {
                    $this->close();
                    return false;
                }
                $this->seek(0,SEEK_SET);
                clearstatcache(); //clean stat cache, important!
                ftruncate($this->fp,0); //truncated
                break;
        }
        $this->stat =  fstat($this->fp);
        return true;
    }
    
    function close() {
        $this->unlock();
        fclose($this->fp);
    }
    
    function exclusiveLock() {
        $i=0;
        while ( !($r=$this->_exclusiveLock()) && $i++ < MAX_LOCK_TRY)
            sleep(1);
        return $r;
    }
    
    function sharedLock() {
        $i=0;
        while ( !($r=$this->_sharedLock()) && $i++ < MAX_LOCK_TRY)
            sleep(1);
        return $r;
    }
    
    
    function isRunningOsWin() {
        return strpos(strtolower(php_uname()),"windows") !== false;
    }
    
    function unlock() {
        if ($this->fbased)
            unlink($this->Lock);
        else
            flock($this->fp,LOCK_UN);
    }
    
    function _sharedLock() {
        global $safeIOLocks;
        if ($this->fbased) {
            if ($this->getLockStatus() == EXCLUSIVE) return false;
            $path = $this->GetNewLockName("read");
            $safeIOLocks[] = $path; /* saves for the end app callback function */
            $this->Lock = $path;
            $fp = fopen($path,"wb");
            $this->FileExistsOrDie($path);
            fclose($fp);
            return true;
        } else return flock($this->fp,LOCK_SH);
    }
    
    function _exclusiveLock() {
        global $safeIOLocks;
        if ($this->fbased) {
            if ($this->getLockStatus() != UNLOCK) return false;        
            $path = $this->GetNewLockName("write");
            $safeIOLocks[] = $path;
            $this->Lock = $path;
            $fp = fopen($path,"wb");
            $this->FileExistsOrDie($path);
            fclose($fp);
            return true;        
        } else return flock($this->fp,LOCK_EX);
    }
    
    function getLockStatus() {
        $r = UNLOCK;
        $path = $this->filename;
        clearstatcache();
        if  (is_file("${path}.*.write"))
            $r = EXCLUSIVE;
        else if  (is_file("${path}.*.read"))
            $r = SHARED;

        return $r;
    }
    
    /*
     *  Generate a new name for the lock file.
     */
    function GetNewLockName($type) {
        $rnd = str_replace(".","",(time()+microtime()));
        return $this->filename.".".$rnd.".${type}";
    }
    
    function  FileExistsOrDie($path) {
        if (!is_file($path)) 
            die("$path couldn't be created!, please see if PHP has write permissions for this file");
    }
    
}
function on_exit() {
	safeIOCleaner();
}

function safeIOCleaner() {
    global $safeIOLocks;
    if (!is_array($safeIOLocks))
        return;
    foreach($safeIOLocks as $value) {
        @unlink($value);
    }
    unset($safeIOLocks);
}
