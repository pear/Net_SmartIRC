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
 * Copyright (c) 2002-2003 Mirco "MEEBEY" Bauer <mail@meebey.net> <http://www.meebey.net>
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
 *
 * @package Net_SmartIRC
 * @version 0.5.0
 * @author Mirco "MEEBEY" Bauer <mail@meebey.net>
 */
// ------- PHP code ----------
include_once('SmartIRC/defines.php');
include_once('SmartIRC/messagehandler.php');
define('SMARTIRC_VERSION', '0.5.0');
define('SMARTIRC_VERSIONSTRING', 'Net_SmartIRC '.SMARTIRC_VERSION);

class Net_SmartIRC
{
    var $_socket;
    var $_address;
    var $_port;
    var $_nick;
    var $_username;
    var $_realname;
    var $_usermode;
    var $_password;
    var $_state = false;
    var $_actionhandler = array();
    var $_timehandler = array();
    var $_debug = SMARTIRC_DEBUG_NOTICE;
    var $_messagebuffer = array();
    var $_messagebuffersize;
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
    var $_channelsynching = false;
    var $_ctcpversion;
    var $_messagehandlerobject;
    var $_mintimer = false;
    var $_maxtimer = 300000;
    var $_txtimeout = 300;
    var $_rxtimeout = 300;
    var $_selecttimeout;
    var $_lastrx;
    var $_lasttx;
    var $_autoreconnect = false;
    var $replycodes;
    var $nreplycodes;
    var $channel;
    
    function Net_SmartIRC()
    {
        ob_implicit_flush(true);
        set_time_limit(0);
        ignore_user_abort(true);
        $this->_messagebuffer[SMARTIRC_CRITICAL] = array();
        $this->_messagebuffer[SMARTIRC_HIGH] = array();
        $this->_messagebuffer[SMARTIRC_MEDIUM] = array();
        $this->_messagebuffer[SMARTIRC_LOW] = array();
        $this->_messagehandlerobject = &new Net_SmartIRC_messagehandler();
        $this->_lastrx = time();
        $this->_lasttx = time();
        $this->replycodes = &$GLOBALS['SMARTIRC_replycodes'];
        $this->nreplycodes = &$GLOBALS['SMARTIRC_nreplycodes'];
        
        // hack till PHP allows $object->method($param)->$object
        $this->channel = &$this->_channels;
    }
    
    /**
     * enables/disables the usage of real sockets
     *
     * Enables/disables the usage of real sockets instead of fsocks
     * (works only if your PHP build has loaded the PHP socket extension)
     * Default: false
     *
     * @param bool $boolean
     * @return void
     */
    function setUseSockets($boolean)
    {
        if (is_bool($boolean)) {
            if (extension_loaded('sockets')) {
                $this->_usesockets = $boolean;
            } else {
                $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: socket extension not loaded, trying to load it...');
                
                if (dl('socket')) {
                    $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: socket extension succesfull loaded');
                    $this->_usesockets = true;
                } else {
                    $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: couldn\'t load the socket extension');
                    $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: your PHP build doesn\'t support real sockets, will use fsocks instead');
                    $this->_usesockets = false;
                }
            }
        } else {
            $this->_usesockets = false;
        }
    }
    
    /**
     * sets the level of debug messages
     *
     * Sets the debug level (bitwise), useful for testing/developing your code.
     * A full list of avialable debug levels see 'Debug Levels'.
     * Default: SMARTIRC_DEBUG_NOTICE
     *
     * @param integer $level
     * @return void
     */
    function setDebug($level)
    {
        $this->_debug = $level;
    }
    
    /**
     * enables/disables the benchmark engine
     * 
     * @param boolean $boolean
     * @return void
     */
    function setBenchmark($boolean)
    {
        if (is_bool($boolean))
            $this->_benchmark = $boolean;
        else 
            $this->_benchmark = false;
    }
    
    /**
     * enables/disables channel synching
     * 
     * @param boolean $boolean
     * @return void
     */
    function setChannelSynching($boolean)
    {
        if (is_bool($boolean))
            $this->_channelsynching = $boolean;
        else 
            $this->_channelsynching = false;
    }
    
    /**
     * sets the CTCP version reply string
     * 
     * @param string $versionstring
     * @return void
     */
    function setCtcpVersion($versionstring)
    {
        $this->_ctcpversion = $versionstring;
    }
    
    /**
     * sets the destination of all log messages
     *
     * Sets the destination of log messages.
     * $type can be:
     * SMARTIRC_FILE for saving the log into a file
     * SMARTIRC_STDOUT for echoing the log to stdout
     * SMARTIRC_SYSLOG for sending the log to the syslog
     * Default: SMARTIRC_STDOUT
     *
     * @param integer $type must be on of the constants
     * @return void
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
    
    /**
     * sets the file for the log if the destination is set to file
     *
     * Sets the logfile, if {@link setLogdestination logdestination} is set to SMARTIRC_FILE.
     * This should be only used with full path!
     *
     * @param string $file 
     * @return void
     */
    function setLogfile($file)
    {
        $this->_logfile = $file;
    }
    
    /**
     * sets the delaytime before closing the socket when disconnect
     *
     * @param integer $milliseconds
     * @return void
     */
    function setDisconnecttime($milliseconds)
    {
        if (is_integer($milliseconds) && $milliseconds >= 100)
            $this->_disconnecttime = $milliseconds;
        else
            $this->_disconnecttime = 100;
    }
    
    /**
     * sets the delay for receiving data from the IRC server
     *
     * Sets the delaytime between messages that are received, this reduces your CPU load.
     * Don't set this too low (min 100ms).
     * Default: 100
     *
     * @param integer $milliseconds
     * @return void
     */
    function setReceivedelay($milliseconds)
    {
        if (is_integer($milliseconds) && $milliseconds >= 100)
            $this->_receivedelay = $milliseconds;
        else
            $this->_receivedelay = 100;
    }
    
    /**
     * sets the delay for sending data to the IRC server
     *
     * Sets the delaytime between messages that are sent, because IRC servers doesn't like floods.
     * This will avoid sending your messages too fast to the IRC server.
     * Default: 250
     *
     * @param integer $milliseconds
     * @return void
     */
    function setSenddelay($milliseconds)
    {
        if (is_integer($milliseconds))
            $this->_senddelay = $milliseconds;
        else
            $this->_senddelay = 250;
    }
    
    /**
     * enables/disables autoreconnecting
     * 
     * @param boolean $boolean
     * @return void
     */
    function setAutoReconnect($boolean)
    {
        if (is_bool($boolean))
            $this->_autoreconnect = $boolean;
        else 
            $this->_autoreconnect = false;
    }
    
    /**
     * sets the receive timeout
     *
     * If the timeout occurs, the connection will be reinitialized
     * Default: 300 seconds
     *
     * @param integer $seconds
     * @return void
     */
    function setReceiveTimeout($seconds)
    {
        if (is_integer($seconds))
            $this->_rxtimeout = $seconds;
        else
            $this->_rxtimeout = 300;
    }
    
    /**
     * sets the transmit timeout
     *
     * If the timeout occurs, the connection will be reinitialized
     * Default: 300 seconds
     *
     * @param integer $seconds
     * @return void
     */
    function setTransmitTimeout($seconds)
    {
        if (is_integer($seconds))
            $this->_txtimeout = $seconds;
        else
            $this->_txtimeout = 300;
    }
    
    /**
     * starts the benchmark (sets the counters)
     *
     * @return void
     */
    function startBenchmark()
    {
        $this->_benchmark_starttime = $this->_microint();
    }
    
    /**
     * stops the benchmark and displays the result
     *
     * @return void
     */
    function stopBenchmark()
    {
        $this->_benchmark_stoptime = $this->_microint();
        
        if ($this->_benchmark)
            $this->showBenchmark();
    }
    
    /**
     * shows the benchmark result
     *
     * @return void
     */
    function showBenchmark()
    {
        $this->log(SMARTIRC_DEBUG_NOTICE, 'benchmark time: '.((float)$this->_benchmark_stoptime-(float)$this->_benchmark_starttime));
    }
    
    /**
     * adds an entry to the log
     *
     * Adds an entry to the log with Linux style log format.
     * Possible $level constants (can also be combined with "|"s)
     * SMARTIRC_DEBUG_NOTICE
     * SMARTIRC_DEBUG_CONNECTION
     * SMARTIRC_DEBUG_SOCKET
     * SMARTIRC_DEBUG_IRCMESSAGES
     * SMARTIRC_DEBUG_MESSAGETYPES
     * SMARTIRC_DEBUG_ACTIONHANDLER
     * SMARTIRC_DEBUG_TIMEHANDLER
     * SMARTIRC_DEBUG_MESSAGEHANDLER
     *
     * @param integer $level bit constants (SMARTIRC_DEBUG_*)
     * @param string $entry the new log entry
     * @return void
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
     * returns the motd
     *
     * @return array
     */
    function getMotd()
    {
        return $this->_motd;
    }
    
    /**
     * returns the usermode
     *
     * @return string
     */
    function getUsermode()
    {
        return $this->_usermode;
    }
    
    /**
     * Creates the sockets and connects to the IRC server on the given port.
     *
     * @param string $address 
     * @param integer $port
     * @return void
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
            
            if ($result !== false) {
                $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: connected');
            }
        } else {
            $this->log(SMARTIRC_DEBUG_SOCKET, 'DEBUG_SOCKET: using fsockets');
            $result = @fsockopen($this->_address, $this->_port);
            
            if ($result !== false) {
                $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: connected');
                $this->_socket = $result;
                $this->log(SMARTIRC_DEBUG_SOCKET, 'DEBUG_SOCKET: activating nonblocking socket mode');
                socket_set_blocking($this->_socket, false);
            }
        }
        
        if ($result === false) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'DEBUG_NOTICE: couldn\'t connect to "'.$address.'" reason: "'.socket_strerror(socket_last_error($this->_socket)).'"');
            die();
        }
        
        $this->_updatestate();
    }
    
    /**
     * disconnects from the IRC server nicely with a QUIT or just destroys the socket
     *
     * disconnects from the IRC server in the given quickness mode.
     * $quickdisconnect:
     * true, just close the socket
     * false, send QUIT and wait {@link $_disconnectime $_disconnectime} before closing the socket
     *
     * @param boolean $quickdisconnect default: false
     * @return boolean
     */
    function disconnect($quickdisconnect = false)
    {
        if ($this->_state() == SMARTIRC_STATE_CONNECTED) {
            if ($quickdisconnect == false) {
                $this->_send('QUIT', SMARTIRC_CRITICAL);
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
     * reconnects to the IRC server with the same login info,
     * it also rejoins the channels
     *
     * @return void
     */
    function reconnect()
    {
        // remember in which channels we are joined
        foreach ($this->_channels as $value) {
            $channels[] = $value->name;
        }
        
        $this->disconnect(true);
        $this->connect($this->_address, $this->_port);
        $this->login($this->_nick, $this->_realname, $this->_usermode, $this->_username, $this->_password);
        
        // rejoin the channels
        foreach ($channels as $value) {
            $tis->join($value);
        }
    }
    
    /**
     * login and register nickname on the IRC network
     *
     * Registers the nickname and user information on the IRC network.
     *
     * @param string $nick
     * @param string $realname
     * @param integer $usermode
     * @param string $username
     * @param string $password
     * @return void
     */
    function login($nick, $realname, $usermode = 0, $username = null, $password = null)
    {
        $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: logging in');
        
        $this->_nick = str_replace(' ', '', $nick);
        $this->_realname = $realname;
        
        if ($username !== null)
            $this->_username = str_replace(' ', '', $username);
        else
            $this->_username = str_replace(' ', '', exec('whoami'));
            
        if ($password !== null) {
            $this->_password = $password;
            $this->_send('PASS '.$this->_password, SMARTIRC_CRITICAL);
        }
        
        if ($usermode !== null)
            $this->_usermode = $usermode;
            
        $this->_send('NICK '.$this->_nick, SMARTIRC_CRITICAL);
        $this->_send('USER '.$this->_username.' '.$usermode.' '.SMARTIRC_UNUSED.' :'.$this->_realname, SMARTIRC_CRITICAL);
    }
    
    // <IRC methods>
    /**
     * sends a new message
     *
     * Sends a message to a channel or user.
     *
     * @param integer $type specifies the type, like QUERY/ACTION or CTCP see 'Message Types'
     * @param string $destination can be a user or channel
     * @param string $message the message
     * @return boolean
     */
    function message($type, $destination, $message)
    {
        switch ($type) {
            case SMARTIRC_TYPE_CHANNEL:
            case SMARTIRC_TYPE_QUERY:
                $this->_send('PRIVMSG '.$destination.' :'.$message);
            break;
            case SMARTIRC_TYPE_ACTION:
                $this->_send('PRIVMSG '.$destination.' :'.chr(1).'ACTION '.$message);
            break;
            case SMARTIRC_TYPE_NOTICE:
                $this->_send('NOTICE '.$destination.' :'.$message);
            break;
            case SMARTIRC_TYPE_CTCP:
                $this->_send('NOTICE '.$destination.' :'.chr(1).$message.chr(1));
            break;
            default:
                return false;
        }
            
        return true;
    }
    
    /**
     * returns an object reference to the specified channel
     *
     * If the channel does not exist (because not joint) false will be returned.
     *
     * @param string $channelname
     * @return object reference to the channel object
     */
    function &channel($channelname)
    {
        if (isset($this->_channels[$channelname]))
            return $this->_channels[$channelname];
        else
            return false;
    }
    
    /**
     * Joins one or more IRC channels with an optional key.
     *
     * @param mixed $channelarray
     * @param string $key 
     * @return void
     */
    function join($channelarray, $key = null)
    {
        if (!is_array($channelarray))
            $channelarray = array($channelarray);
        
        $channellist = implode($channelarray, ',');
        
        if ($key != null)
            $this->_send('JOIN '.$channellist.' '.$key);
        else
            $this->_send('JOIN '.$channellist);
    }
    
    /**
     * parts from one or more IRC channels with an optional reason
     *
     * @param mixed $channelarray 
     * @param string $reason
     * @return void
     */
    function part($channelarray, $reason = null)
    {
        if (!is_array($channelarray))
            $channelarray = array($channelarray);
        
        $channellist = implode($channelarray, ',');
        
        if ($reason != null)
            $this->_send('PART '.$channellist.' :'.$reason);
        else
            $this->_send('PART '.$channellist);
    }
    
    /**
     * Kicks one or more user from an IRC channel with an optional reason.
     *
     * @param string $channel
     * @param mixed $nicknamearray
     * @param string $reason
     * @return void
     */
    function kick($channel, $nicknamearray, $reason = null)
    {
        if (!is_array($nicknamearray))
            $nicknamearray = array($nicknamearray);
        
        $nicknamelist = implode($nicknamearray, ',');
        
        if ($reason != null)
            $this->_send('KICK '.$channel.' '.$nicknamelist.' :'.$reason, SMARTIRC_CRITICAL);
        else
            $this->_send('KICK '.$channel.' '.$nicknamelist, SMARTIRC_CRITICAL);
    }
    
    /**
     * gets a list of one ore more channels
     *
     * Requests a full channellist if $channelarray is not given.
     * (use it with care, usualy its a looooong list)
     *
     * @param mixed $channelarray
     * @return void
     */
    function getList($channelarray = null)
    {
        if ($channelarray != null) {
            if (!is_array($channelarray))
                $channelarray = array($channelarray);
            
            $channellist = implode($channelarray, ',');
            $this->_send('LIST '.$channellist);
        }
        else
            $this->_send('LIST');
    }

    /**
     * requests all nicknames of one or more channels
     *
     * The requested nickname list also includes op and voice state
     *
     * @param mixed $channelarray
     * @return void
     */
    function names($channelarray = null)
    {
        if ($channelarray != null) {
            if (!is_array($channelarray))
                $channelarray = array($channelarray);
            
            $channellist = implode($channelarray, ',');
            $this->_send('NAMES '.$channellist);
        }
        else
            $this->_send('NAMES');
    }
    
    /**
     * sets a new topic of a channel
     *
     * @param string $channel
     * @param string $newtopic
     * @return void
     */
    function setTopic($channel, $newtopic)
    {
        $this->_send('TOPIC '.$channel.' :'.$newtopic);
    }
    
    /**
     * gets the topic of a channel
     *
     * @param string $channel
     * @return void
     */
    function getTopic($channel)
    {
        $this->_send('TOPIC '.$channel);
    }
    
    /**
     * sets or gets the mode of an user or channel
     *
     * Changes/requests the mode of the given target.
     *
     * @param string $target the target, can be an user (only yourself) or a channel
     * @param string $newmode the new mode like +mt
     * @return void
     */
    function mode($target, $newmode = null)
    {
        if ($newmode != null)
            $this->_send('MODE '.$target.' '.$newmode);
        else 
            $this->_send('MODE '.$target);
    }
    
    /**
     * ops an user in the given channel
     *
     * @param string $channel
     * @param string $nickname
     * @return void
     */
    function op($channel, $nickname)
    {
        $this->mode($channel, '+o '.$nickname);
    }
    
    /**
     * deops an user in the given channel
     *
     * @param string $channel
     * @param string $nickname
     * @return void
     */
    function deop($channel, $nickname)
    {
        $this->mode($channel, '-o '.$nickname);
    }
    
    /**
     * voice a user in the given channel
     *
     * @param string $channel
     * @param string $nickname
     * @return void
     */
    function voice($channel, $nickname)
    {
        $this->mode($channel, '+v '.$nickname);
    }
    
    /**
     * devoice a user in the given channel
     *
     * @param string $channel
     * @param string $nickname
     * @return void
     */
    function devoice($channel, $nickname)
    {
        $this->mode($channel, '-v '.$nickname);
    }
    
    /**
     * bans a hostmask for the given channel or requests the current banlist
     *
     * The banlist will be requested if no hostmask is specified
     *
     * @param string $channel
     * @param string $hostmask
     * @return void
     */
    function ban($channel, $hostmask = null)
    {
        if ($hostmask != null)
            $this->mode($channel, '+b '.$hostmask);
        else
            $this->mode($channel, 'b');
    }
    
    /**
     * unbans a hostmask on the given channel
     *
     * @param string $channel
     * @param string $hostmask
     * @return void
     */
    function unban($channel, $hostmask)
    {
        $this->mode($channel, '-b '.$hostmask);
    }
    
    /**
     * invites a user to the specified channel
     *
     * @param string $nickname
     * @param string $channel
     * @return void
     */
    function invite($nickname, $channel)
    {
        $this->_send('INVITE '.$nickname.' '.$channel);
    }
    
    /**
     * changes the own nickname
     *
     * Trys to set a new nickname, nickcollisions are handled.
     *
     * @param string $newnick
     * @return void
     */
    function changeNick($newnick)
    {
        $this->_send('NICK '.$newnick, SMARTIRC_CRITICAL);
    }
    
    /**
     * requests a 'WHO' from the specified target
     *
     * @param string $target
     * @return void
     */
    function who($target)
    {
        $this->_send('WHO '.$target);
    }
    
    /**
     * requests a 'WHOIS' from the specified target
     *
     * @param string $target
     * @return void
     */
    function whois($target)
    {
        $this->_send('WHOIS '.$target);
    }
    
    /**
     * requests a 'WHOWAS' from the specified target
     * (if he left the IRC network)
     *
     * @param string $target
     * @return void
     */
    function whowas($target)
    {
        $this->_send('WHOWAS '.$target);
    }
    
    /**
     * sends QUIT to IRC server and disconnects
     *
     * @param string $quitmessage optional quitmessage
     * @return void
     */
    function quit($quitmessage = null)
    {
        if ($quitmessage != null)
            $this->_send('QUIT :'.$quitmessage);
        else
            $this->_send('QUIT');
            
        $this->disconnect(true);
    }
    // </IRC methods>
    
    /**
     * goes into receive mode
     *
     * Goes into receive and idle mode. Only call this if you want to "spawn" the bot.
     * No further lines of PHP code will be processed after this call, only the bot methods!
     *
     * @return boolean
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
     * waits for a special message type and puts the answer in $result
     *
     * Creates a special actionhandler for that given TYPE and returns the answer.
     * This will only receive the requested type, immediately quit and disconnect from the IRC server.
     * Made for showing IRC statistics on your homepage, or other IRC related information.
     *
     * @param integer $messagetype see in the documentation 'Message Types'
     * @return array answer from the IRC server for this $messagetype
     */
    function listenFor($messagetype)
    {
        $listenfor = &new Net_SmartIRC_listenfor($this);
        $this->registerActionhandler($messagetype, '.*', $listenfor, 'handler');
        $this->listen();
        $result = $listenfor->result;
        unset($listenfor);
        return $result;
    }
    
    /**
     * registers a new actionhandler and returns the assigned id
     *
     * Registers an actionhandler in Net_SmartIRC for calling it later.
     * The actionhandler id is needed for unregistering the actionhandler.
     *
     * @param integer $handlertype bits constants, see in this documentation Message Types
     * @param string $regexhandler the message that has to be in the IRC message in regex syntax
     * @param object $object a reference to the objects of the method
     * @param string $methodname the methodname that will be called when the handler happens
     * @return integer assigned actionhandler id
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
     * unregisters an existing actionhandler
     *
     * @param integer $handlertype
     * @param string $regexhandler
     * @param object $object
     * @param string $methodname
     * @return boolean
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
     * unregisters an existing actionhandler via the id
     *
     * @param integer $id
     * @return boolean
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
     * registers a timehandler and returns the assigned id
     *
     * Registers a timehandler in Net_SmartIRC, which will be called in the specified interval.
     * The timehandler id is needed for unregistering the timehandler.
     *
     * @param integer $interval interval time in milliseconds
     * @param object $object a reference to the objects of the method
     * @param string $methodname the methodname that will be called when the handler happens
     * @return integer assigned timehandler id
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
        
        if (($interval < $this->_mintimer) || ($this->_mintimer == false))
            $this->_mintimer = $interval;
            
        return $id;
    }
    
    /**
     * unregisters an existing timehandler via the id
     *
     * @param integer $id
     * @return boolean
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
                $this->_updatemintimer();
                return true;
            }
        }
        
        $this->log(SMARTIRC_DEBUG_TIMEHANDLER, 'DEBUG_TIMEHANDLER: could not find timehandler id: '.$id.' _not_ unregistered');
        return false;
    }
    
    // <private methods>
    /**
     * changes a already used nickname to a new nickname plus 3 random digits
     *
     * @return void
     */
    function _nicknameinuse()
    {
        $newnickname = substr($this->_nick, 0, 5).rand(0, 999);
        $this->changeNick($newnickname);
        $this->_nick = $newnickname;
    }
    
    /**
     * sends an IRC message
     *
     * Adds a message to the messagequeue, with the optional priority.
     * $priority:
     * SMARTIRC_CRITICAL
     * SMARTIRC_HIGH
     * SMARTIRC_MEDIUM
     * SMARTIRC_LOW
     *
     * @param string $data
     * @param integer $priority must be one of the priority constants
     * @return boolean
     */
    function _send($data, $priority = SMARTIRC_MEDIUM)
    {
        switch ($priority) {
            case SMARTIRC_CRITICAL:
                $this->_rawsend($data);
                return true;
            break;
            case (SMARTIRC_HIGH||SMARTIRC_MEDIUM||SMARTIRC_LOW):
                $this->_messagebuffer[$priority][] = $data;
                return true;
            break;
            default:
                return false;
        }
    }
    
    /**
     * checks the buffer if there are messages to send
     *
     * @return void
     */
    function _checkbuffer()
    {
        if (!$this->_loggedin)
            return;
        
        static $highsent = 0;
        static $lastmicrotimestamp = 0;
        
        if ($lastmicrotimestamp == 0)
            $lastmicrotimestamp = $this->_microint();
            
        $highcount = count($this->_messagebuffer[SMARTIRC_HIGH]);
        $mediumcount = count($this->_messagebuffer[SMARTIRC_MEDIUM]);
        $lowcount = count($this->_messagebuffer[SMARTIRC_LOW]);
        $this->_messagebuffersize = $highcount+$mediumcount+$lowcount;
        
        // don't send them too fast
        if ($this->_microint() >= ($lastmicrotimestamp+($this->_senddelay/1000))) {
            if ($highcount > 0 && $highsent <= 2) {
                $this->_rawsend(array_shift($this->_messagebuffer[SMARTIRC_HIGH]));
                $lastmicrotimestamp = $this->_microint();
                $highsent++;
            } else if ($mediumcount > 0) {
                $this->_rawsend(array_shift($this->_messagebuffer[SMARTIRC_MEDIUM]));
                $lastmicrotimestamp = $this->_microint();
                $highsent = 0;
            } else if ($lowcount > 0) {
                $this->_rawsend(array_shift($this->_messagebuffer[SMARTIRC_HIGH]));
                $lastmicrotimestamp = $this->_microint();
            }
        }
    }
    
    /**
     * Checks the running timers and calls the registered timehandler,
     * when the interval is reached.
     *
     * @return void
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
                    $this->log(SMARTIRC_DEBUG_TIMEHANDLER, 'DEBUG_TIMEHANDLER: calling method "'.get_class($methodobject).'->'.$method.'"');
                    $methodobject->$method($this);
                }
                
                $handlerobject->lastmicrotimestamp = $this->_microint();
            }
        }
    }
    
    /**
     * Checks if a receive or transmit timeout occured and reconnects if configured
     *
     * @return void
     */
    function _checktimeout()
    {
        if ($this->_autoreconnect == true) {
            $timestamp = time();
            if ($this->_lastrx < ($timestamp-$this->_rxtimeout)) {
                $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: receive timeout detected, doing reconnect...');
                $this->reconnect();
            } else if ($this->_lasttx < ($timestamp-$this->_txtimeout)) {
                $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: transmit timeout detected, doing reconnect...');
                $this->reconnect();
            }
        }
    }
    
    /**
     * sends a raw message to the IRC server (don't use this!!)
     *
     * Use message() or _send() instead.
     *
     * @param string $data
     * @return boolean
     */
    function _rawsend($data)
    {
        if ($this->_state() == SMARTIRC_STATE_CONNECTED) {
            $this->log(SMARTIRC_DEBUG_IRCMESSAGES, 'DEBUG_IRCMESSAGES: sent: "'.$data.'"');
                
            if ($this->_usesockets == true)
                $result = @socket_write($this->_socket, $data.SMARTIRC_CRLF);
            else
                $result = @fwrite($this->_socket, $data.SMARTIRC_CRLF);
            
            
            if ($result === false)
                return false;
            else
                return true;
        } else {
            return false;
        }
    }
    
    /**
     * goes into main idle loop for waiting messages from the IRC server
     *
     * @return void
     */
    function _rawreceive()
    {
        $lastpart = '';
        $rawdataar = array();
        
        while ($this->_state() == SMARTIRC_STATE_CONNECTED) {
            $this->_checkbuffer();
            
            $timeout = $this->_selecttimeout();
            if ($this->_usesockets == true) {
                $sread = array($this->_socket);
                $result = @socket_select($sread, $w = null, $e = null, 0, $timeout*1000);
                
                if ($result == 1)
                    // the socket got data to read
                    $rawdata = @socket_read($this->_socket, 10240);
                else
                    // no data
                    $rawdata = null;
            } else {
                usleep($timeout*1000);
                $rawdata = @fread($this->_socket, 10240);
            }
            
            $this->_checktimer();
            $this->_checktimeout();
            
            if ($rawdata !== null && !empty($rawdata)) {
                $rawdata = str_replace("\r", '', $rawdata);
                $rawdata = $lastpart.$rawdata;
                
                $lastpart = substr($rawdata, strrpos($rawdata ,"\n")+1);
                $rawdata = substr($rawdata, 0, strrpos($rawdata ,"\n"));
                $rawdataar = explode("\n", $rawdata);
            }
            
            // loop through our received messages
            while (sizeof($rawdataar) > 0) {
                $this->_lastrx = time();
                // current message and then shifting please
                $rawline = array_shift($rawdataar);
                $validmessage = false;
                
                $this->log(SMARTIRC_DEBUG_IRCMESSAGES, 'DEBUG_IRCMESSAGES: received: "'.$rawline.'"');
                
                // building our data packet
                $ircdata = &new Net_SmartIRC_data();
                $ircdata->rawmessage = $rawline;
                $lineex = explode(' ', $rawline);
                $ircdata->rawmessageex = $lineex;
                $messagecode = $lineex[0];
                
                if (substr($rawline, 0, 1) == ':') {
                    $validmessage = true;
                    $line = substr($rawline, 1);
                    $lineex = explode(' ', $line);
                    
                    // conform to RFC 2812
                    $from = $lineex[0];
                    $messagecode = $lineex[1];
                    $exclamationpos = strpos($from, '!');
                    $atpos = strpos($from, '@');
                    $nick = substr($from, 0, $exclamationpos);
                    $ident = substr($from, $exclamationpos+1, ($atpos-$exclamationpos)-1);
                    $host = substr($from, $atpos+1);
                    $message = substr(implode(array_slice($lineex, 3), ' '), 1);
                    $type = $this->_gettype($rawline);
                    
                    $ircdata->from = $from;
                    $ircdata->nick = $nick;
                    $ircdata->ident = $ident;
                    $ircdata->host = $host;
                    $ircdata->message = $message;
                    $ircdata->messageex = explode(' ', $message);
                    $ircdata->type = $type;
                    
                    if ($type & (SMARTIRC_TYPE_CHANNEL|
                                 SMARTIRC_TYPE_ACTION|
                                 SMARTIRC_TYPE_MODECHANGE|
                                 SMARTIRC_TYPE_KICK|
                                 SMARTIRC_TYPE_PART))
                        $ircdata->channel = $lineex[2];
                    elseif ($type & SMARTIRC_TYPE_JOIN)
                        $ircdata->channel = substr($lineex[2], 1);
                    elseif ($type & (SMARTIRC_TYPE_WHO|
                                     SMARTIRC_TYPE_BANLIST|
                                     SMARTIRC_TYPE_TOPIC|
                                     SMARTIRC_TYPE_CHANNELMODE))
                        $ircdata->channel = $lineex[3];
                    elseif ($type & SMARTIRC_TYPE_NAME)
                        $ircdata->channel = $lineex[4];
                        
                    if ($ircdata->channel !== null)
                        $ircdata->channel = strtolower($ircdata->channel);
                }
                
                // lets see if we have a messagehandler for it
                $this->_handlemessage($messagecode, $ircdata);
                    
                if ($validmessage == true)
                    // now the actionhandlers are comming
                    $this->_handleactionhandler($ircdata);
                
                unset($ircdata);
            }
        }
    }
    
    /**
     * sends the pong for keeping alive
     *
     * Sends the PONG signal as reply of the PING from the IRC server.
     *
     * @param string $data
     * @return void
     */
    function _pong($data)
    {
        $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: Ping? Pong!');
            
        $this->_send('PONG '.$data, SMARTIRC_CRITICAL);
    }
    
    function _selecttimeout() {
        if ($this->_messagebuffersize == 0) {
            $this->_selecttimeout = null;
            
            if ($this->_mintimer != false)
                $this->_calculateselecttimeout($this->_mintimer);
            if ($this->_autoreconnect == true) 
                $this->_calculateselecttimeout($this->_rxtimeout*1000);
            
            $this->_calculateselecttimeout($this->_maxtimer);
            return $this->_selecttimeout;
        } else {
            return $this->_senddelay;
        }
    }
    
    function _calculateselecttimeout($microseconds)
    {
        if(($this->_selecttimeout > $microseconds) || $this->_selecttimeout == null)
            $this->_selecttimeout = $microseconds;
    }
    
    /**
     * updates _mintimer to the smallest timer interval
     *
     * @return void
     */
    function _updatemintimer()
    {
        $timerarray = array();
        foreach ($this->_timehandler as $key) {
            $timerarray[] = $key->interval;
        }
        
        $result = array_multisort($timerarray, SORT_NUMERIC, SORT_ASC);
        if($result == true && isset($timerarray[0]))
            $this->_mintimer = $timerarray[0];
        else
            $this->_mintimer = false;
    }
    
    /**
     * reorders the actionhandler array, needed after removing one
     *
     * @return void
     */
    function _reorderactionhandler()
    {
        $orderedactionhandler = array();
        foreach ($this->_actionhandler as $value) {
            $orderedactionhandler[] = $value;
        }
        $this->_actionhandler = &$orderedactionhandler;
    }
    
    /**
     * reorders the timehandler array, needed after removing one
     *
     * @return void
     */
    function _reordertimehandler()
    {
        $orderedtimehandler = array();
        foreach ($this->_timehandler as $value) {
            $orderedtimehandler[] = $value;
        }
        $this->_timehandler = &$orderedtimehandler;
    }
    
    /**
     * determines the messagetype of $line
     *
     * Analyses the type of an IRC message and returns the type.
     *
     * @param string $line
     * @return integer SMARTIRC_TYPE_* constant
     */
    function _gettype($line)
    {
        if (preg_match('/^:.* [0-9]{3} .*$/', $line) == 1) {
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
                case SMARTIRC_RPL_CHANNELMODEIS:
                    return SMARTIRC_TYPE_CHANNELMODE;
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
     * updates the current connection state
     *
     * @return boolean
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
     * returns the current connection state
     *
     * @return integer SMARTIRC_STATE_CONNECTED or SMARTIRC_STATE_DISCONNECTED
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
     * tries to find a messagehandler for the received message ($ircdata) and calls it
     *
     * @param string $messagecode
     * @param object $ircdata
     * @return void
     */
    function _handlemessage($messagecode, &$ircdata)
    {
        $messagehandlerobject = &$this->_messagehandlerobject;
        $found = false;
        
        if (is_numeric($messagecode)) {
            if (!array_key_exists($messagecode, $this->nreplycodes)) {
                $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER, 'DEBUG_MESSAGEHANDLER: ignoring unreconzied messagecode! "'.$messagecode.'"');
                $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER, 'DEBUG_MESSAGEHANDLER: this IRC server ('.$this->_address.') doesn\'t conform to the RFC 2812!');
                return;
            }
            
            $methodname = strtolower($this->nreplycodes[$messagecode]);
            $_methodname = '_'.$methodname;
            
            // if exist, call internal method for the handling
            if (method_exists($messagehandlerobject, $_methodname)) {
                $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER, 'DEBUG_MESSAGEHANDLER: calling internal method "'.get_class($messagehandlerobject).'->'.$_methodname.'" (by numeric)');
                $messagehandlerobject->$_methodname($this, $ircdata);
                $found = true;
            }
            
            // if exist, call user defined method for the handling
            if (method_exists($messagehandlerobject, $methodname)) {
                $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER, 'DEBUG_MESSAGEHANDLER: calling userdefined method "'.get_class($messagehandlerobject).'->'.$methodname.'" (by numeric)');
                $messagehandlerobject->$methodname($this,$ircdata);
                $found = true;
            }
        } else if (is_string($messagecode)) { // its not numericcode so already a name/string
            $methodname = strtolower($messagecode);
            $_methodname = '_'.$methodname;
            
            // same as above
            if (method_exists($messagehandlerobject, $_methodname)) {
                $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER, 'DEBUG_MESSAGEHANDLER: calling internal method "'.get_class($messagehandlerobject).'->'.$_methodname.'" (by string)');
                $messagehandlerobject->$_methodname($this, $ircdata);
                $found = true;
            }
            
            if (method_exists($messagehandlerobject, $methodname)) {
                $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER, 'DEBUG_MESSAGEHANDLER: calling userdefined method "'.get_class($messagehandlerobject).'->'.$methodname.'" (by string)');
                $messagehandlerobject->$methodname($this, $ircdata);
                $found = true;
            }
        }
        
        if ($found == false)
            $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER, 'DEBUG_MESSAGEHANDLER: no method found for "'.$messagecode.'" ('.$methodname.')');
    }
    
    /**
     * tries to find a actionhandler for the received message ($ircdata) and calls it
     *
     * @param object $ircdata
     * @return void
     */
    function _handleactionhandler(&$ircdata)
    {
        $handler = &$this->_actionhandler;
        for ($i=0; $i<count($handler); $i++) {
            $handlerobject = &$handler[$i];
            
            if (($handlerobject->type & $ircdata->type) &&
                (preg_match('/'.$handlerobject->message.'/',$ircdata->message) == 1)) {
                
                $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: actionhandler match found for id: '.$i.' type: '.$ircdata->type.' message: "'.$ircdata->message.'" regex: "'.$handlerobject->message.'"');
                
                $methodobject = &$handlerobject->object;
                $method = $handlerobject->method;
                
                if (method_exists($methodobject, $method)) {
                    $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: calling method "'.get_class($methodobject).'->'.$method.'"');
                    $methodobject->$method($this, $ircdata);
                } else {
                    $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: method doesn\'t exist! "'.get_class($methodobject).'->'.$method.'"');
                }
                
                break;
            }
        }
    }
    
    /**
     * getting current microtime, needed for benchmarks
     *
     * @return float
     */
    function _microint()
    {
        $tmp = microtime();
        $parts = explode(' ', $tmp);
        $floattime = (float)$parts[0] + (float)$parts[1];
        return $floattime;
    }
    
    /**
     * adds an user to the channelobject or updates his info
     *
     * @param object $channel
     * @param object $newuser
     * @return void
     */
    function _adduser(&$channel, &$newuser)
    {
        $lowerednick = strtolower($newuser->nick);
        if (isset($channel->users[$lowerednick])) {
            // lets update the existing user
            $currentuser = &$channel->users[$lowerednick];
            
            if ($newuser->ident !== null)
                $currentuser->ident = $newuser->ident;
            if ($newuser->host !== null)
                $currentuser->host = $newuser->host;
            if ($newuser->realname !== null)
                $currentuser->realname = $newuser->realname;
            if ($newuser->op !== null)
                $currentuser->op = $newuser->op;
            if ($newuser->voice !== null)
                $currentuser->voice = $newuser->voice;
            if ($newuser->ircop !== null)
                $currentuser->ircop = $newuser->ircop;
            if ($newuser->away !== null)
                $currentuser->away = $newuser->away;
            if ($newuser->server !== null)
                $currentuser->server = $newuser->server;
            if ($newuser->hopcount !== null)
                $currentuser->hopcount = $newuser->hopcount;
        } else {
            // he is new just add the reference to him
            $channel->users[$lowerednick] = &$newuser;
        }
        
        $user = &$channel->users[$lowerednick];
        if ($user->op)
            $channel->ops[$user->nick] = true;
        if ($user->voice)
            $channel->voices[$user->nick] = true;
        unset($user);
    }
    
    /**
     * removes an user from one channel or all if he quits
     *
     * @param object $ircdata
     * @return void
     */
    function _removeuser(&$ircdata)
    {
        if ($ircdata->type & (SMARTIRC_TYPE_PART|SMARTIRC_TYPE_QUIT)) {
            $nick = $ircdata->nick;
        } else if ($ircdata->type & SMARTIRC_TYPE_KICK) {
            $nick = $ircdata->rawmessageex[3];
        } else {
            $this->log(SMARTIRC_DEBUG_CHANNELSYNCHING, 'DEBUG_CHANNELSYNCHING: unknown TYPE ('.$ircdata->type.') in _removeuser(), trying default');
            $nick = $ircdata->nick;
        }
        
        if ($this->_nick == $nick) {
            unset($this->_channels[$ircdata->channel]);
        } else {
            if ($ircdata->type & SMARTIRC_TYPE_QUIT) {
                // remove the user from all channels
                foreach ($this->_channels as $channelkey => $channelvalue) {
                    // loop through all channels
                    foreach ($channelvalue->users as $userkey => $uservalue) {
                        // loop through all user in this channel
                        
                        if ($nick == $uservalue->nick) {
                            $lowerednick = $ircdata->nick;
                            // found him
                            // die
                            unset($channelvalue->users[$lowerednick]);
                            
                            if (isset($channelvalue->ops[$lowerednick]))
                                // die!
                                unset($channelvalue->ops[$lowerednick]);
                                
                            if (isset($channelvalue->voices[$lowerednick]))
                                // die!!
                                unset($channelvalue->voices[$lowerednick]);
                                
                            // ups this was not DukeNukem 3D
                        }
                    }
                }
            } else {
                $channel = &$this->_channels[$ircdata->channel];
                unset($channel->users[strtolower($nick)]);
                unset($channel);
            }
        }
    }
    // </private methods>
}

class Net_SmartIRC_data
{
    var $from;    
    var $nick;
    var $ident;
    var $host;
    var $channel;
    var $message;
    var $messageex;
    var $type;
    var $rawmessage;
    var $rawmessageex;
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

class Net_SmartIRC_channel
{
    var $name;
    var $users = array();
    var $ops;
    var $voices;
    var $bans;
    var $topic;
    var $mode;
}

class Net_SmartIRC_user
{
    var $nick;
    var $ident;
    var $host;
    var $realname;
    var $op;
    var $voice;
    var $ircop;
    var $away;
    var $server;
    var $hopcount;
}

class Net_SmartIRC_listenfor
{
    var $result = array();
    
    /**
     * stores the received answer into the result array
     *
     * @param object $irc
     * @param object $ircdata
     * @return void
     */
    function handler(&$irc, &$ircdata)
    {
        $irc->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: listenfor handler called');
        $this->result[] = $ircdata->message;
        $irc->disconnect(true);
    }
}
?>