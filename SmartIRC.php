<?php
/**
 * $Id$
 * $Revision$
 * $Author$
 * $Date$
 */
/**
 * Net_SmartIRC
 * Communication for PHP with IRC networks
 *
 * Example of how you could use this class see example.php and example2.php
 * 
 * Here you will find a service bot which I am also developing
 * <http://www.meebey.net/showcode.php?file=antitroll>
 * Latest versions of Net_SmartIRC you will find on my homepage
 *
 * Net_SmartIRC conforms to RFC 2812 (Internet Relay Chat: Client Protocol)
 * 
 * Copyright (C) 2002-2003 Mirco "MEEBEY" Bauer <mail@meebey.net> <http://www.meebey.net>
 * 
 * Full LGPL License: <http://www.meebey.net/lgpl.txt>
 * 
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */ 
// ------- PHP code ----------
include_once('SmartIRC/defines.php');
define('SMARTIRC_VERSION', '0.4.1');
define('SMARTIRC_VERSIONSTRING', 'Net_SmartIRC '.SMARTIRC_VERSION);

class Net_SmartIRC
{
    var $_socket;
    var $_address;
    var $_port;
    var $_nick;
    var $_username;
    var $_realname;
    var $_state = false;
    var $_actionhandler = array();
    var $_timehandler = array();
    var $_debug = SMARTIRC_DEBUG_NOTICE;
    var $_messagebuffer = array();
    var $_lastmicrotimestamp;
    var $_usesockets = false;
    var $_receivedelay = 100;
    var $_senddelay = 250;
    var $_logdestination = SMARTIRC_STDOUT;
    var $_logfilefp;
    var $_logfile = 'Net_SmartIRC.log';
    var $_disconnecttime = 1000;
    var $_loggedin = false;
    var $_benchmark = false;
    var $_benchmark_starttime;
    var $_benchmark_stoptime;
    var $_actionhandlerid = 0;
    var $_timehandlerid = 0;
    var $_motd = array();
    var $_channels = array();
    var $_usermode;

    function Net_SmartIRC()
    {
        ob_implicit_flush(true);
        set_time_limit(0);
        ignore_user_abort(true);
        $this->_lastmicrotimestamp = $this->_microint();
    }
    
    /**
     * @return void
     * @param boolean bool
     * @desc enables/disables the usage of real sockets
     */
    function useSockets($boolean)
    {
        if ($boolean) {
            if (extension_loaded('sockets')) {
                $this->_usesockets = true;
            } else {
                $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: your PHP build doesn\'t support real sockets, will use fsocks');
                $this->_usesockets = false;
            }
        } else {
            $this->_usesockets = false;
        }
    }

    /**
     * @return void
     * @param level int
     * @desc sets the level of debug messages
     */
    function setDebug($level)
    {
        $this->_debug = $level;
    }

    /**
     * @return void
     * @param boolean bool
     * @desc enables/disables benchmark test
     */
    function setBenchmark($boolean)
    {
        if (is_bool($boolean))
            $this->_benchmark = $boolean;
        else 
            $this->_benchmark = false;
    }

    /**
     * @return void
     * @desc starts the benchmark
     */
    function startBenchmark()
    {
        $this->_benchmark_starttime = $this->_microint();
    }

    /**
     * @return void
     * @desc stops the benchmark
     */
    function stopBenchmark()
    {
        $this->_benchmark_stoptime = $this->_microint();
        
        if ($this->_benchmark)
            $this->showBenchmark();
    }

    /**
     * @return void
     * @param boolean bool
     * @desc enables/disables benchmark test
     */
    function showBenchmark()
    {
        $this->log(SMARTIRC_DEBUG_NOTICE, 'benchmark time: '.((float)$this->_benchmark_stoptime-(float)$this->_benchmark_starttime));
    }

    /**
     * @return void
     * @param level int
     * @param entry string
     * @desc adds an entry to the log
     */
    function log($level, $entry)
    {
        if (!($level & $this->_debug))
            return;
        
        if (substr($entry, -1) != "\n")
            $entry .= "\n";

        $formatedentry = date('M d H:i:s ').$entry;
            
        switch ($this->_logdestination) {
            case SMARTIRC_STDOUT:
                echo($formatedentry);
                flush();
            break;
            case SMARTIRC_FILE:
                if (!is_resource($this->_logfilefp))
                    $this->_logfilefp = @fopen($this->_logfile,'w+');

                @fwrite($this->_logfilefp, $formatedentry);
                    fflush($this->_logfilefp);
            break;
            case SMARTIRC_SYSLOG:
                define_syslog_variables();
            
                if (!is_int($this->_logfilefp))
                    $this->_logfilefp = openlog('Net_SmartIRC', LOG_NDELAY, LOG_DAEMON);
                
                syslog(LOG_INFO, $entry);
            break;
        }
    }
    
    /**
     * @return void
     * @param type constant
     * @desc sets the destination of all log messages
     */
    function setLogdestination($type)
    {
        switch ($type) {
            case SMARTIRC_FILE:
                $this->_logdestination = SMARTIRC_FILE;
            break;
            case SMARTIRC_STDOUT:
                $this->_logdestination = SMARTIRC_STDOUT;
            break;
            case SMARTIRC_SYSLOG:
                $this->_logdestination = SMARTIRC_SYSLOG;
            break;
            default:
                $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: unknown logdestination type ('.$type.'), will use STDOUT instead');
                $this->_logdestination = SMARTIRC_STDOUT;
        }
    }
    
    function getMotd()
    {
        return $this->_motd;
    }
    
    function getUsermode()
    {
        return $this->_usermode;
    }
    
    /**
     * @return void
     * @param file string
     * @desc sets the file for the log if the destination is set to file
     */
    function setLogfile($file)
    {
        $this->_logfile = $file;
    }

    /**
     * @return void
     * @param milliseconds int
     * @desc sets the delaytime before closing the socket when disconnect
     */
    function disconnecttime($milliseconds)
    {
        if (is_integer($milliseconds) && $milliseconds >= 100)
            $this->_disconnecttime = $milliseconds;
        else
            $this->_disconnecttime = 100;
    }

    /**
     * @return void
     * @param milliseconds int
     * @desc sets the delay for receiving data from the IRC server
     */
    function setReceivedelay($milliseconds)
    {
        if (is_integer($milliseconds) && $milliseconds >= 100)
            $this->_receivedelay = $milliseconds;
        else
            $this->_receivedelay = 100;
    }

    /**
     * @return void
     * @param milliseconds int
     * @desc sets the delay for sending data to the IRC server
     */
    function setSenddelay($milliseconds)
    {
        if (is_integer($milliseconds))
            $this->_senddelay = $milliseconds;
        else
            $this->_senddelay = 250;
    }
    
    /**
     * @return void
     * @param address string
     * @param port int
     * @desc creates the sockets and connects to the IRC server
     */
    function connect($address, $port)
    {
        $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: connecting');
        $this->_address = $address;
        $this->_port = $port;
        
        if ($this->_usesockets == true) {
            $this->log(SMARTIRC_DEBUG_SOCKET, 'DEBUG_SOCKET: using real sockets');
            $this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            $result = @socket_connect($this->_socket, $this->_address, $this->_port);
            
            if ($result === false) {
                $this->log(SMARTIRC_DEBUG_NOTICE, 'DEBUG_NOTICE: can\'t connect to "'.$address.'" reason: "'.socket_strerror(socket_last_error($this->_socket)).'"');
                die();
            }
        } else {
            $this->log(SMARTIRC_DEBUG_SOCKET, 'DEBUG_SOCKET: using fsockets');
            $this->_socket = @fsockopen($this->_address, $this->_port);
            $this->log(SMARTIRC_DEBUG_SOCKET, 'DEBUG_SOCKET: activating nonblocking socket mode');
            socket_set_blocking($this->_socket, false);
        }

        $this->_updatestate();
    }
    
    /**
     * @return void
     * @param nick string
     * @param realname string
     * @param username string
     * @param password string
     * @desc login and register nickname on the IRC network
     */
    function login($nick, $realname, $username = null, $password = null)
    {
        $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: logging in');

        $this->_nick = str_replace(' ', '', $nick);
        $this->_realname = $realname;
        
        if ($username != null)
            $this->_username = str_replace(' ', '', $username);
        else
            $this->_username = str_replace(' ', '', exec('whoami'));
            
        if ($password != null)
            $this->_rawsend('PASS '.$password);

        $mode = '0';
        $this->_rawsend('NICK '.$this->_nick);
        $this->_rawsend('USER '.$this->_username.' '.$mode.' '.SMARTIRC_UNUSED.' :'.$this->_realname);
    }
    
    /**
     * @return void
     * @desc changes a already used nickname to a new nickname plus 3 random digits
     */
    function _nicknameinuse()
    {
        $newnickname = substr($this->_nick, 0, 5).rand(0, 999);
        $this->_rawsend('NICK '.$newnickname);
        $this->_nick = $newnickname;
    }

    /**
     * @return bool
     * @param data string
     * @desc sends a raw message to the IRC server
     */
    function _rawsend($data)
    {
        if ($this->_state() == SMARTIRC_STATE_CONNECTED) {
            $this->log(SMARTIRC_DEBUG_IRCMESSAGES, 'DEBUG_IRCMESSAGES: sent: "'.$data.'"');
                
            if ($this->_usesockets == true)
                $result = @socket_write($this->_socket, $data.SMARTIRC_CRLF);
            else
                $result = @fwrite($this->_socket, $data.SMARTIRC_CRLF);
            
            
            if ($result == false)
                return false;
            else
                return true;
        } else {
            return false;
        }
    }
    
    /**
     * @return void
     * @param data string
     * @desc adds a message to the messagebuffer
     */
    function _bufferedsend($data)
    {
        $this->_messagebuffer[] = $data;
    }
    
    /**
     * @return void
     * @desc checks the buffer if there are messages to send
     */
    function _checkbuffer()
    {
        if (!$this->_loggedin)
            return;
            
        $messagecount = count($this->_messagebuffer);
        
        if (($this->_microint() >= ($this->_lastmicrotimestamp+($this->_senddelay/1000))) &&
            ($messagecount > 0)) {
            	
            $this->_rawsend(array_shift($this->_messagebuffer));
            $this->_lastmicrotimestamp = $this->_microint();
        }
    }    

    /**
     * @return void
     * @desc checks the timer
     */
    function _checktimer()
    {
        if (!$this->_loggedin)
            return;
            
        for ($i=0; $i<count($this->_timehandler); $i++) {
            $handlerobject = &$this->_timehandler[$i];
            if ($this->_microint() >= ($handlerobject->lastmicrotimestamp+($handlerobject->interval/1000))) {
                $methodobject = &$handlerobject->object;
                $method = $handlerobject->method;
                    
                if (method_exists($methodobject, $method)) {
                    $this->log(SMARTIRC_DEBUG_TIMEHANDLER, 'DEBUG_TIMEHANDLER: calling existing method "'.$method.'"');
                    $methodobject->$method($this);
                }
                
                $handlerobject->lastmicrotimestamp = $this->_microint();
            }
        }
    }    

    /**
     * @return void
     * @desc goes into main idle loop for waiting messages from the IRC server
     */
    function _rawreceive()
    {
        $lastpart = '';
        $rawdataar = array();
        
        while ($this->_state() == SMARTIRC_STATE_CONNECTED) {

            $this->_checkbuffer();

            if ($this->_usesockets == true) {
                $sread = array($this->_socket);
                $result = @socket_select($sread, $w = null, $e = null,  0, $this->_receivedelay*1000);
                
                if ($result == 1)
                    // the socket got data to read
                    $rawdata = @socket_read($this->_socket, 10240);
                else
                    // no data
                    $rawdata = null;
            } else {
                usleep($this->_receivedelay*1000);
                /*
                 * doesn't work somehow, something broken in PHP?
                 * using nonblocking sockets right now as workaround
                 *
                 * $status = socket_get_status ($this->_socket);
                 * $bytes = $status['unread_bytes'];
                 *
                 * if ($bytes > 0)
                 */
                        $rawdata = @fread($this->_socket, 10240);
                /*
                 * else
                 * $rawdata = null;
                 */
            }
            
            $this->_checktimer();

            if ($rawdata != null) {
                $rawdata = str_replace("\r", '', $rawdata);
                $rawdata = $lastpart.$rawdata;
                
                $lastpart = substr($rawdata, strrpos($rawdata ,"\n")+1);
                $rawdata = substr($rawdata, 0, strrpos($rawdata ,"\n"));
                $rawdataar = explode("\n", $rawdata);
            }
            
            for ($i=0; $i < sizeof($rawdataar); $i++) {
                $rawline = array_shift($rawdataar);
                
                $this->log(SMARTIRC_DEBUG_IRCMESSAGES, 'DEBUG_IRCMESSAGES: received: "'.$rawline.'"');
                    
                if (substr($rawline, 0, 4) == 'PING') {
                    $this->_pong(substr($rawline, 5));
                } elseif (substr($rawline, 0, 5) == 'ERROR') {
                    $this->disconnect(true);
                }

                if (substr($rawline, 0, 1) == ':') {
                    $line = substr($rawline, 1);
                    $lineex = explode(' ', $line);
                    $from = $lineex[0];
                    $nick = substr($lineex[0], 0, strpos($lineex[0], '!'));
                    $message = substr(implode(array_slice($lineex, 3), ' '), 1);
                    $type = $this->_gettype($rawline);
                    
                    switch ($type) {
                        case SMARTIRC_TYPE_CTCP:
                            if (substr($message, 1, 4) == 'PING')
                                $this->message(SMARTIRC_TYPE_CTCP, $nick, 'PING '.substr($message, 5, -1));
                            elseif (substr($message, 1, 7) == 'VERSION')
                                $this->message(SMARTIRC_TYPE_CTCP, $nick, 'VERSION '.PSIC_VERSIONSTRING.' by Mirco "MEEBEY" Bauer <http://www.meebey.net> | using PHP version: '.phpversion());
                        break;
                        case SMARTIRC_TYPE_LOGIN:
                                if ($lineex[1] == SMARTIRC_RPL_WELCOME) {
                                    $this->_loggedin = true;
                                    $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: logged in');
                                }
                        break;
                        case SMARTIRC_TYPE_ERROR:
                            $code = $lineex[1];
                        
                            if ($code == SMARTIRC_ERR_NICKNAMEINUSE)
                                $this->_nicknameinuse();
                        break;
                        case SMARTIRC_TYPE_MOTD:
                            $this->_motd[] = $message;
                        break;
                        case SMARTIRC_TYPE_USERMODE:
                            $this->_usermode = $message;
                        break;
                    }
                    
                    $handler = &$this->_actionhandler;
                    for ($j=0; $j<count($handler); $j++) {
                        $handlerobject = &$handler[$j];
                        
                        if (($handlerobject->type & $type) &&
                            (preg_match('/'.$handlerobject->message.'/',$message) == 1)) {
                            	
                            $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: actionhandler match found for id: '.$j.' type: '.$type.' message: "'.$message.'" regex: "'.$handlerobject->message.'"');
                            
                            $ircdata = &new Net_SmartIRC_data();
                            $ircdata->nick = $nick;
                            $ircdata->from = $from;
                            $ircdata->message = $message;
                            $ircdata->type = $type;
                            $ircdata->rawmessage = $rawline;
                            
                            if ($type == SMARTIRC_TYPE_CHANNEL|SMARTIRC_TYPE_ACTION)
                                $ircdata->channel = $lineex[2];

                            $methodobject = &$handlerobject->object;
                            $method = $handlerobject->method;
                    
                            if (method_exists($methodobject, $method)) {
                                $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: calling existing method "'.$method.'" from object "'.get_class($methodobject).'"');
                                $methodobject->$method($this, $ircdata);
                            }
                                    
                            unset($ircdata);
                            break;
                        }
                    }
                }    
            }
        }
    }
    
    /**
     * @return void
     * @param data string
     * @desc sends the pong for keeping alive
     */
    function _pong($data)
    {
        $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: Ping? Pong!');
            
        $this->_rawsend('PONG '.$data);
    }

    /**
     * @return void
     * @param channel string
     * @param key string
     * @desc joins an IRC channel
     */
    function join($channel, $key = null)
    {
        if ($key != null)
            $this->_bufferedsend('JOIN '.$channel.' '.$key);
        else
            $this->_bufferedsend('JOIN '.$channel);
    }

    /**
     * @return void
     * @param channelarray mixed
     * @param reason string
     * @desc parts one or more IRC channels
     */
    function part($channelarray, $reason = null)
    {
        if (!is_array($channelarray))
            $channelarray = array($channelarray);

        $channellist = implode($channelarray, ',');
        
        if ($reason != null)
            $this->_bufferedsend('PART '.$channellist.' :'.$reason);
        else
            $this->_bufferedsend('PART '.$channellist);
    }

    /**
     * @return void
     * @param channel string
     * @param nickname string
     * @param reason string
     * @desc kicks a user from a IRC channel
     */
    function kick($channel, $nickname, $reason = null)
    {
        if ($reason != null)
            $this->_rawsend('KICK '.$channel.' '.$nickname.' :'.$reason);
        else
            $this->_rawsend('KICK '.$channel.' '.$nickname);
    }

    /**
     * @return void
     * @param channelarray mixed
     * @desc gets a list of one ore more channels
     */
    function getList($channelarray = null)
    {
        if ($channelarray != null) {
            if (!is_array($channelarray))
                $channelarray = array($channelarray);
            
            $channellist = implode($channelarray, ',');
            $this->_bufferedsend('LIST '.$channellist);
        }
        else
            $this->_bufferedsend('LIST');
    }

    /**
     * @return void
     * @param channelarray mixed
     * @desc gets all nicknames of one or more channels
     */
    function names($channelarray = null)
    {
        if ($channelarray != null) {
            if (!is_array($channelarray))
                $channelarray = array($channelarray);

            $channellist = implode($channelarray, ',');
            $this->_bufferedsend('NAMES '.$channellist);
        }
        else
            $this->_bufferedsend('NAMES');
    }

    /**
     * @return void
     * @param channel string
     * @param newtopic string
     * @desc sets a new topic of a channel
     */
    function setTopic($channel, $newtopic)
    {
        $this->_bufferedsend('TOPIC '.$channel.' :'.$newtopic);
    }

    /**
     * @return void
     * @param channel string
     * @desc gets the topic of a channel
     */
    function getTopic($channel)
    {
        $this->_bufferedsend('TOPIC '.$channel);
    }

    /**
     * @return void
     * @param target string
     * @param newmode string
     * @desc sets or gets the mode of an user or channel
     */
    function mode($target, $newmode = null)
    {
        if ($newmode != null)
            $this->_bufferedsend('MODE '.$target.' '.$newmode);
        else 
            $this->_bufferedsend('MODE '.$target);
    }

    /**
     * @return void
     * @param channel string
     * @param nickname string
     * @desc ops an user in the given channel
     */
    function op($channel, $nickname)
    {
        $this->mode($channel, '+o '.$nickname);
    }

    /**
     * @return void
     * @param channel string
     * @param nickname string
     * @desc deops an user in the given channel
     */
    function deop($channel, $nickname)
    {
        $this->mode($channel, '-o '.$nickname);
    }

    /**
     * @return void
     * @param channel string
     * @param nickname string
     * @desc voice a user in the given channel
     */
    function voice($channel, $nickname)
    {
        $this->mode($channel, '+v '.$nickname);
    }

    /**
     * @return void
     * @param channel string
     * @param nickname string
     * @desc devoice a user in the given channel
     */
    function devoice($channel, $nickname)
    {
        $this->mode($channel, '-v '.$nickname);
    }

    /**
     * @return void
     * @param channel string
     * @param nickname string
     * @desc bans a hostmask for the given channel or shows the current banlist
     */
    function ban($channel, $hostmask = null)
    {
        if ($hostmask != null)
            $this->mode($channel, '+b '.$hostmask);
        else
            $this->mode($channel, 'b');
    }

    /**
     * @return void
     * @param channel string
     * @param nickname string
     * @desc unbans a hostmask for the given channel
     */
    function unban($channel, $hostmask)
    {
        $this->mode($channel, '-b '.$hostmask);
    }

    /**
     * @return void
     * @param nickname string
     * @param channel string
     * @desc invites a user to a channel
     */
    function invite($nickname, $channel)
    {
        $this->_bufferedsend('INVITE '.$nickname.' '.$channel);
    }

    /**
     * @return void
     * @param newnick string
     * @desc changes the own nickname
     */
    function changeNick($newnick)
    {
        $this->_bufferedsend('NICK '.$newnick);
    }
    
    /**
     * @return void
     * @desc goes into receive mode
     */
    function listen()
    {
        if ($this->_state() == SMARTIRC_STATE_CONNECTED) {
            $this->_rawreceive();
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * @return void
     * @param messagetype constant
     * @param result array
     * @desc waits for a special message type and puts the answer it in $result
     */
    function listenFor($messagetype, &$result)
    {
        $listenfor = &new Net_SmartIRC_listenfor($this);
        $this->registerActionhandler($messagetype, '.*', $listenfor, 'handler');
        $this->listen();
        $result = $listenfor->result;
        unset($listenfor);
    }
    
    /**
     * @return bool
     * @param type constant
     * @param destination string
     * @param message string
     * @desc sends a new message
     */
    function message($type, $destination, $message)
    {
        switch ($type) {
            case SMARTIRC_TYPE_CHANNEL:
            case SMARTIRC_TYPE_QUERY:
                $this->_bufferedsend('PRIVMSG '.$destination.' :'.$message);
            break;
            case SMARTIRC_TYPE_ACTION:
                $this->_bufferedsend('PRIVMSG '.$destination.' :'.chr(1).'ACTION '.$message);
            break;
            case SMARTIRC_TYPE_NOTICE:
                $this->_bufferedsend('NOTICE '.$destination.' :'.$message);
            break;
            case SMARTIRC_TYPE_CTCP:
                $this->_bufferedsend('NOTICE '.$destination.' :'.chr(1).$message.chr(1));
            break;
            default:
                return false;
        }
            
        return true;
    }

    /**
     * @return integer
     * @param handlertype constant
     * @param regexhandler string
     * @param object object
     * @param methodname string
     * @desc registers a new actionhandler and returns the assigned id
     */
    function registerActionhandler($handlertype, $regexhandler, &$object, $methodname)
    {
        $id = $this->_actionhandlerid++;
        $newactionhandler = &new Net_SmartIRC_actionhandler();
        $newactionhandler->id = $id;
        $newactionhandler->type = $handlertype;
        $newactionhandler->message = $regexhandler;
        $newactionhandler->object = &$object;
        $newactionhandler->method = $methodname;
        
        $this->_actionhandler[] = &$newactionhandler;
        $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: actionhandler('.$id.') registered');
        return $id;
    }

    /**
     * @return void
     * @param handlertype constant
     * @param regexhandler string
     * @param object object
     * @param methodname string
     * @desc unregisters an existing actionhandler
     */
    function unregisterActionhandler($handlertype, $regexhandler, &$object, $methodname)
    {
        $handler = &$this->_actionhandler;
        for ($i=0; $i<count($handler); $i++) {
            $handlerobject = &$handler[$i];
                        
            if ($handlerobject->type == $handlertype &&
                $handlerobject->message == $regexhandler &&
                $handlerobject->method == $methodname) {

                $id = $handlerobject->id;
                unset($this->_actionhandler[$i]);
                $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: actionhandler('.$id.') unregistered');
                $this->_reorderactionhandler();
                return true;
            }
        }
        
        $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: could not find actionhandler type: "'.$handlertype.'" message: "'.$regexhandler.'" method: "'.$methodname.'" from object "'.get_class($object).'" _not_ unregistered');
        return false;
    }
    
    /**
     * @return bool
     * @param id integer
     * @desc unregisters an existing actionhandler via the id
     */
    function unregisterActionid($id)
    {
        $handler = &$this->_actionhandler;
        for ($i=0; $i<count($handler); $i++) {
            $handlerobject = &$handler[$i];
                        
            if ($handlerobject->id == $id) {
                unset($this->_actionhandler[$i]);
                $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: actionhandler('.$id.') unregistered');
                $this->_reorderactionhandler();
                return true;
            }
        }
        
        $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: could not find actionhandler id: '.$id.' _not_ unregistered');
        return false;
    }

    /**
     * @return integer
     * @param interval integer
     * @param object object
     * @param methodname string
     * @desc registers a timehandler and returns the assigned id
     */
    function registerTimehandler($interval, &$object, $methodname)
    {
        $id = $this->_timehandlerid++;
        $newtimehandler = &new Net_SmartIRC_timehandler();
        $newtimehandler->id = $id;
        $newtimehandler->interval = $interval;
        $newtimehandler->object = &$object;
        $newtimehandler->method = $methodname;
        $newtimehandler->lastmicrotimestamp = $this->_microint();
        
        $this->_timehandler[] = &$newtimehandler;
        $this->log(SMARTIRC_DEBUG_TIMEHANDLER, 'DEBUG_TIMEHANDLER: timehandler('.$id.') registered');
        return $id;
    }

    /**
     * @return bool
     * @param id integer
     * @desc unregisters an existing timehandler via the id
     */
    function unregisterTimeid($id)
    {
        $handler = &$this->_timehandler;
        for ($i=0; $i<count($handler); $i++) {
            $handlerobject = &$handler[$i];
                        
            if ($handlerobject->id == $id) {
                unset($this->_timehandler[$i]);
                $this->log(SMARTIRC_DEBUG_TIMEHANDLER, 'DEBUG_TIMEHANDLER: timehandler('.$id.') unregistered');
                $this->_reordertimehandler();
                return true;
            }
        }
        
        $this->log(SMARTIRC_DEBUG_TIMEHANDLER, 'DEBUG_TIMEHANDLER: could not find timehandler id: '.$id.' _not_ unregistered');
        return false;
    }

    /**
     * @return void
     * @desc reorders the actionhandler array, needed after removing one
     */
    function _reorderactionhandler()
    {
        $orderedactionhandler = array();
        foreach($this->_actionhandler as $value) {
            $orderedactionhandler[] = &$value;
        }
        $this->_actionhandler = &$orderedactionhandler;
    }
    
    /**
     * @return void
     * @desc reorders the timehandler array, needed after removing one
     */
    function _reordertimehandler()
    {
        $orderedtimehandler = array();
        foreach($this->_timehandler as $value) {
            $orderedtimehandler[] = &$value;
        }
        $this->_timehandler = &$orderedtimehandler;
    }
    
    /**
     * @return constant
     * @param line string
     * @desc determines the messagetype of $line
     */
    function _gettype($line)
    {
        if (preg_match('/^:.* [0-9]{3} .*$/', $line) == 1)
        {
            $lineex = explode(' ', $line);
            $code = $lineex[1];
                
            switch ($code) {
                case SMARTIRC_RPL_WELCOME:
                    return SMARTIRC_TYPE_LOGIN;
                case SMARTIRC_RPL_YOURHOST:
                    return SMARTIRC_TYPE_LOGIN;
                case SMARTIRC_RPL_CREATED:
                    return SMARTIRC_TYPE_LOGIN;
                case SMARTIRC_RPL_MYINFO:
                    return SMARTIRC_TYPE_LOGIN;
                case SMARTIRC_RPL_BOUNCE:
                    return SMARTIRC_TYPE_LOGIN;
                case SMARTIRC_RPL_LUSERCLIENT:
                    return SMARTIRC_TYPE_INFO;
                case SMARTIRC_RPL_LUSEROP:
                    return SMARTIRC_TYPE_INFO;
                case SMARTIRC_RPL_LUSERUNKNOWN:
                    return SMARTIRC_TYPE_INFO;
                case SMARTIRC_RPL_LUSERME:
                    return SMARTIRC_TYPE_INFO;
                case SMARTIRC_RPL_LUSERCHANNELS:
                    return SMARTIRC_TYPE_INFO;
                case SMARTIRC_RPL_MOTDSTART:
                    return SMARTIRC_TYPE_MOTD;
                case SMARTIRC_RPL_MOTD:
                    return SMARTIRC_TYPE_MOTD;
                case SMARTIRC_RPL_ENDOFMOTD:
                    return SMARTIRC_TYPE_MOTD;
                case SMARTIRC_RPL_NAMREPLY:
                    return SMARTIRC_TYPE_NAME;
                case SMARTIRC_RPL_ENDOFNAMES:
                    return SMARTIRC_TYPE_NAME;
                case SMARTIRC_RPL_WHOREPLY:
                    return SMARTIRC_TYPE_WHO;
                case SMARTIRC_RPL_ENDOFWHO:
                    return SMARTIRC_TYPE_WHO;
                case SMARTIRC_RPL_LISTSTART:
                    return SMARTIRC_TYPE_NONRELEVANT;
                case SMARTIRC_RPL_LIST:
                    return SMARTIRC_TYPE_LIST;
                case SMARTIRC_RPL_LISTEND:
                    return SMARTIRC_TYPE_LIST;
                case SMARTIRC_RPL_BANLIST:
                    return SMARTIRC_TYPE_BANLIST;
                case SMARTIRC_RPL_ENDOFBANLIST:
                    return SMARTIRC_TYPE_BANLIST;
                case SMARTIRC_RPL_TOPIC:
                    return SMARTIRC_TYPE_TOPIC;
                case SMARTIRC_RPL_WHOISUSER:
                    return SMARTIRC_TYPE_WHOIS;
                case SMARTIRC_RPL_WHOISSERVER:
                    return SMARTIRC_TYPE_WHOIS;
                case SMARTIRC_RPL_WHOISOPERATOR:
                    return SMARTIRC_TYPE_WHOIS;
                case SMARTIRC_RPL_WHOISIDLE:
                    return SMARTIRC_TYPE_WHOIS;
                case SMARTIRC_RPL_WHOISIDLE:
                    return SMARTIRC_TYPE_WHOIS;
                case SMARTIRC_RPL_ENDOFWHOIS:
                    return SMARTIRC_TYPE_WHOIS;
                case SMARTIRC_RPL_WHOISCHANNELS:
                    return SMARTIRC_TYPE_WHOIS;
                case SMARTIRC_RPL_WHOWASUSER:
                    return SMARTIRC_TYPE_WHOWAS;
                case SMARTIRC_RPL_ENDOFWHOWAS:
                    return SMARTIRC_TYPE_WHOWAS;
                case SMARTIRC_RPL_UMODEIS:
                    return SMARTIRC_TYPE_USERMODE;
                case SMARTIRC_ERR_NICKNAMEINUSE:
                    return SMARTIRC_TYPE_ERROR;
                case SMARTIRC_ERR_NOTREGISTERED:
                    return SMARTIRC_TYPE_ERROR;
                default:
                    $this->log(SMARTIRC_DEBUG_IRCMESSAGES, 'DEBUG_IRCMESSAGES: replycode UNKNOWN ('.$code.'): "'.$line.'"');
            }
        }

        if (preg_match('/^:.* PRIVMSG .* :'.chr(1).'ACTION .*$/', $line) == 1)
            return SMARTIRC_TYPE_ACTION;
        elseif (preg_match('/^:.* PRIVMSG .* :'.chr(1).'.*'.chr(1).'$/', $line) == 1)
            return SMARTIRC_TYPE_CTCP;
        elseif (preg_match('/^:.* PRIVMSG (\&|\#|\+|\!).* :.*$/', $line) == 1)
            return SMARTIRC_TYPE_CHANNEL;
        elseif (preg_match('/^:.* PRIVMSG .*:.*$/', $line) == 1)
            return SMARTIRC_TYPE_QUERY;
        elseif (preg_match('/^:.* NOTICE .* :.*$/', $line) == 1)
            return SMARTIRC_TYPE_NOTICE;
        elseif (preg_match('/^:.* INVITE .* .*$/', $line) == 1)
            return SMARTIRC_TYPE_INVITE;
        elseif (preg_match('/^:.* JOIN .*$/', $line) == 1)
            return SMARTIRC_TYPE_JOIN;
        elseif (preg_match('/^:.* TOPIC .* :.*$/', $line) == 1)
            return SMARTIRC_TYPE_TOPICCHANGE;
        elseif (preg_match('/^:.* NICK .*$/', $line) == 1)
            return SMARTIRC_TYPE_NICKCHANGE;
        elseif (preg_match('/^:.* KICK .* .*$/', $line) == 1)
            return SMARTIRC_TYPE_KICK;
        elseif (preg_match('/^:.* PART .* :.*$/', $line) == 1)
            return SMARTIRC_TYPE_PART;
        elseif (preg_match('/^:.* MODE .* .*$/', $line) == 1)
            return SMARTIRC_TYPE_MODECHANGE;
        elseif (preg_match('/^:.* QUIT :.*$/', $line) == 1)
            return SMARTIRC_TYPE_QUIT;
        else {
            $this->log(SMARTIRC_DEBUG_MESSAGETYPES, 'DEBUG_MESSAGETYPES: SMARTIRC_TYPE_UNKNOWN!: "'.$line.'"');
                
             return SMARTIRC_TYPE_UNKNOWN;
        }
    }
    
    /**
     * @return void
     * @param target string
     * @desc requests a WHO from the specified target
     */
    function who($target)
    {
        $this->_bufferedsend('WHO '.$target);
    }

    /**
     * @return void
     * @param target string
     * @desc requests a WHOIS from the specified target
     */
    function whois($target)
    {
        $this->_bufferedsend('WHOIS '.$target);
    }

    /**
     * @return void
     * @param target string
     * @desc requests a WHOWAS from the specified target
     */
    function whowas($target)
    {
        $this->_bufferedsend('WHOWAS '.$target);
    }
    
    /**
     * @return void
     * @param quitmessage string
     * @desc sends QUIT to IRC server and disconnects
     */
    function quit($quitmessage = null)
    {
        if ($quitmessage != null)
            $this->_bufferedsend('QUIT :'.$quitmessage);
        else
            $this->_bufferedsend('QUIT');
            
        $this->disconnect(true);
    }
    
    /**
     * @return bool
     * @desc updates the current connection state
     */
    function _updatestate()
    {
        $rtype = get_resource_type($this->_socket);
        if ((is_resource($this->_socket)) &&
            ($this->_socket !== false) &&
            ($rtype == 'socket' || $rtype == 'Socket' || $rtype == 'stream')) {
            	
            $this->_state = true;
            return true;
        } else {
            $this->_state = false;
            return false;
        }
    }
    
    /**
     * @return constant
     * @desc returns the current connection state
     */
    function _state()
    {
        $result = $this->_updatestate();
        
        if ($result == true)
            return SMARTIRC_STATE_CONNECTED;
        else
            return SMARTIRC_STATE_DISCONNECTED;
    }
    
    /**
     * @return bool
     * @param quickdisconnect bool
     * @desc disconnects from the IRC server nicely with a QUIT or just destroys the socket
     */
    function disconnect($quickdisconnect = false)
    {
        if ($this->_state() == SMARTIRC_STATE_CONNECTED) {
            if ($quickdisconnect == false) {
                $this->_rawsend('QUIT');
                usleep($this->_disconnecttime*1000);
            }
            
            if ($this->_usesockets == true) {
                @socket_shutdown($this->_socket);
                socket_close($this->_socket);
            } else {
                fclose($this->_socket);
            }

            $this->_updatestate();
            $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: disconnected');
                
            return true;
        } else {
            return false;
        }

        if ($this->_logdestination == SMARTIRC_FILE)
            fclose($this->_logfilefp);
        elseif ($this->_logdestination == SMARTIRC_SYSLOG)
            closelog();
    }
    
    /**
     * @return float
     * @desc getting current microtime, needed for benchmarks
     */
    function _microint()
    {
        $tmp = microtime();
        $parts = explode(' ', $tmp);
        $floattime = (float)$parts[0] + (float)$parts[1];
        return $floattime;
    }
}

class Net_SmartIRC_data
{
    var $nick;
    var $channel;
    var $message;
    var $type;
    var $from;    
    var $rawmessage;
}

class Net_SmartIRC_actionhandler
{
    var $id;
    var $type;
    var $message;
    var $object;
    var $method;
}

class Net_SmartIRC_timehandler
{
    var $id;
    var $interval;
    var $lastmicrotimestamp;
    var $object;
    var $method;
}

class Net_SmartIRC_listenfor
{
    var $result = array();

    /**
     * @return void
     * @param ircdata object
     * @desc stores the received answer into the result array
     */
    function handler(&$irc, &$ircdata)
    {
        $irc->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: listenfor handler called');
        $this->result[] = $ircdata->message;
        $irc->disconnect(true);
    }
}
?>