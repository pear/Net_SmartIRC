<?php
/**
 * $Id$
 * $Revision$
 * $Author$
 * $Date$
 *
 * Net_SmartIRC
 * This is a PHP class for communication with IRC networks,
 * which conforms to the RFC 2812 (IRC protocol).
 * It's an API that handles all IRC protocol messages.
 * This class is designed for creating IRC bots, chats and showing irc related
 * info on webpages.
 *
 * Documentation, a HOWTO, and examples are included in SmartIRC.
 *
 * Here you will find a service bot which I am also developing
 * <http://cvs.meebey.net/atbs> and <http://cvs.meebey.net/phpbitch>
 * Latest versions of Net_SmartIRC you will find on the project homepage
 * or get it through PEAR since SmartIRC is an official PEAR package.
 * See <http://pear.php.net/Net_SmartIRC>.
 *
 * Official Project Homepage: <http://sf.net/projects/phpsmartirc>
 *
 * Net_SmartIRC conforms to RFC 2812 (Internet Relay Chat: Client Protocol)
 * 
 * Copyright (c) 2002-2005 Mirco Bauer <meebey@meebey.net> <http://www.meebey.net>
 * 
 * PHP version 5
 * 
 * Full LGPL License: <http://www.gnu.org/licenses/lgpl.txt>
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
 */
// ------- PHP code ----------
require_once 'Net/SmartIRC/defines.php';
require_once 'Net/SmartIRC/irccommands.php';
require_once 'Net/SmartIRC/messagehandler.php';
define('SMARTIRC_VERSION', '1.1.0-dev ($Revision$)');
define('SMARTIRC_VERSIONSTRING', 'Net_SmartIRC '.SMARTIRC_VERSION);

if (version_compare(PHP_VERSION, '5.3.0', '<')) {
    die('Your version of PHP does not support this version of SmartIRC! '
        .'Please upgrade to a newer version of PHP.'
    );
}

/**
 * main SmartIRC class
 *
 * @package Net_SmartIRC
 * @version 0.6.0-dev
 * @author Mirco 'meebey' Bauer <mail@meebey.net>
 * @access public
 */
class Net_SmartIRC extends Net_SmartIRC_messagehandler
{
    /**
     * This is to prevent an E_NOTICE for example in getChannel() if null needs
     * to be returned.
     * 
     * @var null
     */
    const NULLGUARD = null;
    
    /**
     * @var integer
     */
    const DEF_AUTORETRY_MAX = 5;
    
    /**
     * @var integer
     */
    const DEF_DISCONNECT_TIME = 1000;
    
    /**
     * @var integer
     */
    const DEF_LOGFILE = 'Net_SmartIRC.log';
    
    /**
     * @var integer
     */
    const DEF_MAX_TIMER = 300000;
    
    /**
     * @var integer
     */
    const DEF_RECEIVE_DELAY = 100;
    
    /**
     * @var integer
     */
    const DEF_RECONNECT_DELAY = 10000;
    
    /**
     * @var integer
     */
    const DEF_SEND_DELAY = 250;
    
    /**
     * @var integer
     */
    const DEF_TX_RX_TIMEOUT = 300;
    
    /**
     * @var resource
     */
    protected $_socket;
    
    /**
     * @var string
     */
    protected $_address;
    
    /**
     * @var integer
     */
    protected $_port;
    
    /**
     * @var string
     */
    protected $_bindaddress = null;
    
    /**
     * @var integer
     */
    protected $_bindport = 0;
    
    /**
     * @var string
     */
    protected $_nick;
    
    /**
     * @var string
     */
    protected $_username;
    
    /**
     * @var string
     */
    protected $_realname;
    
    /**
     * @var string
     */
    protected $_usermode;
    
    /**
     * @var string
     */
    protected $_password;
    
    /**
     * @var array
     */
    protected $_performs = array();
    
    /**
     * @var boolean
     */
    protected $_state = SMARTIRC_STATE_DISCONNECTED;
    
    /**
     * @var array
     */
    protected $_actionhandler = array();
    
    /**
     * @var array
     */
    protected $_timehandler = array();
    
    /**
     * @var integer
     */
    protected $_debuglevel = SMARTIRC_DEBUG_NOTICE;
    
    /**
     * @var array
     */
    protected $_messagebuffer = array(
		SMARTIRC_HIGH     => array(),
		SMARTIRC_MEDIUM   => array(),
		SMARTIRC_LOW 	  => array(),
	);
    
    /**
     * @var integer
     */
    protected $_messagebuffersize;
    
    /**
     * @var boolean
     */
    protected $_usesockets = false;
    
    /**
     * @var integer
     */
    protected $_receivedelay = self::DEF_RECEIVE_DELAY;
    
    /**
     * @var integer
     */
    protected $_senddelay = self::DEF_SEND_DELAY;
    
    /**
     * @var integer
     */
    protected $_logdestination = SMARTIRC_STDOUT;
    
    /**
     * @var resource
     */
    protected $_logfilefp = 0;
    
    /**
     * @var string
     */
    protected $_logfile = self::DEF_LOGFILE;
    
    /**
     * @var integer
     */
    protected $_disconnecttime = self::DEF_DISCONNECT_TIME;
    
    /**
     * @var boolean
     */
    protected $_loggedin = false;
    
    /**
     * @var boolean
     */
    protected $_benchmark = false;
    
    /**
     * @var integer
     */
    protected $_benchmark_starttime;
    
    /**
     * @var integer
     */
    protected $_benchmark_stoptime;
    
    /**
     * @var integer
     */
    protected $_actionhandlerid = 0;
    
    /**
     * @var integer
     */
    protected $_timehandlerid = 0;
    
    /**
     * @var array
     */
    protected $_motd = array();
    
    /**
     * @var array
     */
    protected $_channels = array();
    
    /**
     * @var boolean
     */
    protected $_channelsyncing = false;
    
    /**
     * @var array
     */
    protected $_users = array();
    
    /**
     * @var boolean
     */
    protected $_usersyncing = false;
    
    /**
     * Stores the path to the modules that can be loaded.
     *
     * @var string
     */
    protected $_modulepath = '.';
    
    /**
     * Stores all objects of the modules.
     *
     * @var string
     */
    protected $_modules = array();
    
    /**
     * @var string
     */
    protected $_ctcpversion = SMARTIRC_VERSIONSTRING;
    
    /**
     * @var mixed
     */
    protected $_mintimer = false;
    
    /**
     * @var integer
     */
    protected $_maxtimer = self::DEF_MAX_TIMER;
    
    /**
     * @var integer
     */
    protected $_txtimeout = self::DEF_TX_RX_TIMEOUT;
    
    /**
     * @var integer
     */
    protected $_rxtimeout = self::DEF_TX_RX_TIMEOUT;
    
    /**
     * @var integer
     */
    protected $_lastrx;
    
    /**
     * @var integer
     */
    protected $_lasttx;
    
    /**
     * @var integer
     */
    protected $_reconnectdelay = self::DEF_RECONNECT_DELAY;

    /**
     * @var boolean
     */
    protected $_autoretry = false;

    /**
     * @var integer
     */
    protected $_autoretrymax = self::DEF_AUTORETRY_MAX;

    /**
     * @var integer
     */
    protected $_autoretrycount = 0;
    
    /**
     * @var boolean
     */
    protected $_connectionerror = false;

    /**
     * @var boolean
     */
    protected $_runasdaemon = false;
    
    /**
     * @var boolean
     */
    protected $_interrupt = false;
    

    /**
     * All numeric IRC replycodes, the index is the numeric replycode.
     *
     * @see $SMARTIRC_nreplycodes
     * @var array
     */
    public $nreplycodes;
    
    /**
     * Stores all channels in this array where we are joined, works only if channelsyncing is activated.
     * Eg. for accessing a user, use it like this: (in this example the SmartIRC object is stored in $irc)
     * $irc->channel['#test']->users['meebey']->nick;
     *
     * @see setChannelSyncing()
     * @see Net_SmartIRC_channel
     * @see Net_SmartIRC_channeluser
     * @var array
     */
    public $channel;
    
    /**
     * Stores all users that had/have contact with us (channel/query/notice etc.), works only if usersyncing is activated.
     * Eg. for accessing a user, use it like this: (in this example the SmartIRC object is stored in $irc)
     * $irc->user['meebey']->host;
     *
     * @see setUserSyncing()
     * @see Net_SmartIRC_ircuser
     * @var array
     */
    public $user;
    
    /**
     * Constructor. Initiates the messagebuffer and "links" the replycodes from
     * global into properties. Also some PHP runtime settings are configured.
     *
     * @return object
     */
    public function __construct($params = array())
    {
        ob_implicit_flush(true);
        @set_time_limit(0);
        
        $this->nreplycodes = &$GLOBALS['SMARTIRC_nreplycodes'];
        
        // you'll want to pass an array that includes keys like:
        // ModulePath, Debug, UseSockets, ChannelSyncing, AutoRetry, RunAsDaemon
        // so we can call their setters here
        foreach ($params as $varname => $val) {
            $funcname = 'set' . $varname;
            $this->$funcname($val);
        }
        
        // PHP allows $this->getChannel($param)->memberofobject,
        // but we need to not break BC.
        $this->channel = &$this->_channels;
        $this->user = &$this->_users;
        
        if (isset($_SERVER['REQUEST_METHOD'])) {
            // the script is called from a browser, lets set default log destination
            // to SMARTIRC_BROWSEROUT (makes browser friendly output)
            $this->setLogDestination(SMARTIRC_BROWSEROUT);
        }
    }
    
    /**
     * Handle calls to renamed functions
     * 
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        $map = array(
            'setChannelSynching' => 'setChannelSyncing',
            'setDebug' => 'setDebugLevel',
            'setLogdestination' => 'setLogDestination',
            'setLogfile' => 'setLogFile',
            'setDisconnecttime' => 'setDisconnectTime',
            'setReconnectdelay' => 'setReconnectDelay',
            'setReceivedelay' => 'setReceiveDelay',
            'setSenddelay' => 'setSendDelay',
            'setModulepath' => 'setModulePath',
            'registerActionhandler' => 'registerActionHandler',
            'unregisterActionhandler' => 'unregisterActionHandler',
            'unregisterActionid' => 'unregisterActionId',
            'registerTimehandler' => 'registerTimeHandler',
            'unregisterTimeid' => 'unregisterTimeId',
            'setAutoReconnect' => '',
        );
        
        if (array_key_exists($method, $map)) {
            if (empty($map[$method])) {
                $this->log(SMARTIRC_DEBUG_NOTICE,
                    "WARNING: you are using $method() which is deprecated "
                    ."functionality. Please do not call this method.",
                    __FILE__, __LINE__
                );
                return false;
            }
            
            $this->log(SMARTIRC_DEBUG_NOTICE,
                "WARNING: you are using $method() which is a deprecated "
                ."method, using {$map[$method]}() instead!", __FILE__, __LINE__
            );
            return call_user_func_array(array($this, $map[$method]), $args);
        }
        
        $this->log(SMARTIRC_DEBUG_NOTICE,
            "WARNING: $method() does not exist!", __FILE__, __LINE__
        );
        return false;
    }
    
    /**
     * Enables/disables autoretry for connecting to a server.
     * 
     * @param boolean $boolean
     * @return boolean
     */
    public function setAutoRetry($boolean)
    {
        return ($this->_autoretry = ($boolean) ? true : false);
    }

    /**
     * Sets the maximum number of attempts to connect to a server
     * before giving up.
     *
     * @param integer $autoretrymax
     * @return integer
     */
    public function setAutoRetryMax($autoretrymax)
    {
        if (is_integer($autoretrymax)) {
            if ($autoretrymax == 0) {
                $this->setAutoRetry(false);
            } else {
                $this->_autoretrymax = $autoretrymax;
            }
        } else {
            $this->_autoretrymax = self::DEF_AUTORETRY_MAX;
        }
        return $this->_autoretrymax;
    }

    /**
     * Enables/disables the benchmark engine.
     * 
     * @param boolean $boolean
     * @return boolean
     */
    public function setBenchmark($boolean)
    {
        return ($this->_benchmark = ($boolean) ? true : false);
    }
    
    /**
     * Sets an IP address (and optionally, a port) to bind the socket to.
     * 
     * Limits the bot to claiming only one of the machine's IPs as its home.
     * Only works with setUseSockets(TRUE). Call with no parameters to unbind.
     * 
     * @param string $addr
     * @return bool
     */
    public function setBindAddress($addr = null, $port = 0)
    {
        if ($this->_usesockets) {
            if ($port == 0 && ($cpos = strpos($addr, ':'))) {
                $addr = substr($addr, 0, $cpos);
                $port = substr($addr, $cpos + 1);
            }
            $this->bindaddress = $addr;
            $this->bindport = $port;
        }
        return $this->_usesockets;
    }
    
    /**
     * Enables/disables channel syncing.
     *
     * Channel syncing means, all users on all channel we are joined are tracked in the
     * channel array. This makes it very handy for botcoding.
     * 
     * @param boolean $boolean
     * @return boolean
     */
    public function setChannelSyncing($boolean)
    {
        if ($boolean) {
            $this->_channelsyncing = true;
            $this->log(SMARTIRC_DEBUG_CHANNELSYNCING,
                'DEBUG_CHANNELSYNCING: Channel syncing enabled',
                __FILE__, __LINE__
            );
        } else {
            $this->_channelsyncing = false;
            $this->log(SMARTIRC_DEBUG_CHANNELSYNCING,
                'DEBUG_CHANNELSYNCING: Channel syncing disabled',
                __FILE__, __LINE__
            );
        }
        return $this->_channelsyncing;
    }

    /**
     * Sets the CTCP version reply string.
     * 
     * @param string $versionstring
     * @return string
     */
    public function setCtcpVersion($versionstring)
    {
        return ($this->_ctcpversion = $versionstring);
    }
    
    /**
     * Sets the level of debug messages.
     *
     * Sets the debug level (bitwise), useful for testing/developing your code.
     * Here the list of all possible debug levels:
     * SMARTIRC_DEBUG_NONE
     * SMARTIRC_DEBUG_NOTICE
     * SMARTIRC_DEBUG_CONNECTION
     * SMARTIRC_DEBUG_SOCKET
     * SMARTIRC_DEBUG_IRCMESSAGES
     * SMARTIRC_DEBUG_MESSAGETYPES
     * SMARTIRC_DEBUG_ACTIONHANDLER
     * SMARTIRC_DEBUG_TIMEHANDLER
     * SMARTIRC_DEBUG_MESSAGEHANDLER
     * SMARTIRC_DEBUG_CHANNELSYNCING
     * SMARTIRC_DEBUG_MODULES
     * SMARTIRC_DEBUG_USERSYNCING
     * SMARTIRC_DEBUG_ALL
     *
     * Default: SMARTIRC_DEBUG_NOTICE
     *
     * @see DOCUMENTATION
     * @see SMARTIRC_DEBUG_NOTICE
     * @param integer $level
     * @return integer
     */
    public function setDebugLevel($level)
    {
        return ($this->_debuglevel = $level);
    }
    
    /**
     * Sets the delaytime before closing the socket when disconnect.
     *
     * @param integer $milliseconds
     * @return integer
     */
    public function setDisconnectTime($milliseconds)
    {
        if (is_integer($milliseconds)
             && $milliseconds >= self::DEF_DISCONNECT_TIME
        ) {
            $this->_disconnecttime = $milliseconds;
        } else {
            $this->_disconnecttime = self::DEF_DISCONNECT_TIME;
        }
        return $this->_disconnecttime;
    }
    
    /**
     * Sets the destination of all log messages.
     *
     * Sets the destination of log messages.
     * $type can be:
     * SMARTIRC_FILE for saving the log into a file
     * SMARTIRC_STDOUT for echoing the log to stdout
     * SMARTIRC_SYSLOG for sending the log to the syslog
     * Default: SMARTIRC_STDOUT
     *
     * @see SMARTIRC_STDOUT
     * @param integer $type must be on of the constants
     * @return integer
     */
    public function setLogDestination($type)
    {
        switch ($type) {
            case SMARTIRC_FILE:
            case SMARTIRC_STDOUT:
            case SMARTIRC_SYSLOG:
            case SMARTIRC_BROWSEROUT:
            case SMARTIRC_NONE:
                $this->_logdestination = $type;
                break;
            
            default:
                $this->log(SMARTIRC_DEBUG_NOTICE,
                    'WARNING: unknown logdestination type ('.$type
                    .'), will use STDOUT instead', __FILE__, __LINE__);
                $this->_logdestination = SMARTIRC_STDOUT;
        }
        return $this->_logdestination;
    }
    
    /**
     * Sets the file for the log if the destination is set to file.
     *
     * Sets the logfile, if {@link setLogDestination logdestination} is set to SMARTIRC_FILE.
     * This should be only used with full path!
     *
     * @param string $file 
     * @return string
     */
    public function setLogFile($file)
    {
        return ($this->_logfile = $file);
    }
    
    /**
     * Sets the paths for the modules.
     *
     * @param integer $path
     * @return string
     */
    public function setModulePath($path)
    {
        return ($this->_modulepath = $path);
    }

    /**
     * Sets the delay for receiving data from the IRC server.
     *
     * Sets the delaytime between messages that are received, this reduces your CPU load.
     * Don't set this too low (min 100ms).
     * Default: 100
     *
     * @param integer $milliseconds
     * @return integer
     */
    public function setReceiveDelay($milliseconds)
    {
        if (is_integer($milliseconds)
            && $milliseconds >= self::DEF_RECEIVE_DELAY
        ) {
            $this->_receivedelay = $milliseconds;
        } else {
            $this->_receivedelay = self::DEF_RECEIVE_DELAY;
        }
        return $this->_receivedelay;
    }
    
    /**
     * Sets the delaytime before attempting reconnect.
     * Value of 0 disables the delay entirely.
     *
     * @param integer $milliseconds
     * @return integer
     */
    public function setReconnectDelay($milliseconds)
    {
        if (is_integer($milliseconds)) {
            $this->_reconnectdelay = $milliseconds;
        } else {
            $this->_reconnectdelay = self::DEF_RECONNECT_DELAY;
        }
        return $this->_reconnectdelay;
    }

    /**
     * Sets whether the script should be run as a daemon or not
     * ( actually disables/enables ignore_user_abort() )
     *
     * @param boolean $boolean
     * @return boolean
     */
    public function setRunAsDaemon($boolean)
    {
        if ($boolean) {
            $this->_runasdaemon = true;
            ignore_user_abort(true);
        } else {
            $this->_runasdaemon = false;
        }
        return $this->_runasdaemon;
    }
    
    /**
     * Sets the delay for sending data to the IRC server.
     *
     * Sets the delaytime between messages that are sent, because IRC servers doesn't like floods.
     * This will avoid sending your messages too fast to the IRC server.
     * Default: 250
     *
     * @param integer $milliseconds
     * @return integer
     */
    public function setSendDelay($milliseconds) {
        if (is_integer($milliseconds)) {
            $this->_senddelay = $milliseconds;
        } else {
            $this->_senddelay = self::DEF_SEND_DELAY;
        }
        return $this->_senddelay;
    }
    
    /**
     * Sets the receive timeout.
     *
     * If the timeout occurs, the connection will be reinitialized
     * Default: 300 seconds
     *
     * @param integer $seconds
     * @return integer
     */
    public function setReceiveTimeout($seconds)
    {
        if (is_integer($seconds)) {
            $this->_rxtimeout = $seconds;
        } else {
            $this->_rxtimeout = self::DEF_TX_RX_TIMEOUT;
        }
        return $this->_rxtimeout;
    }
    
    /**
     * Sets the transmit timeout.
     *
     * If the timeout occurs, the connection will be reinitialized
     * Default: 300 seconds
     *
     * @param integer $seconds
     * @return integer
     */
    public function setTransmitTimeout($seconds)
    {
        if (is_integer($seconds)) {
            $this->_txtimeout = $seconds;
        } else {
            $this->_txtimeout = self::DEF_TX_RX_TIMEOUT;
        }
        return $this->_txtimeout;
    }
    
    /**
     * Enables/disables user syncing.
     *
     * User syncing means, all users we have or had contact with through channel, query or
     * notice are tracked in the $irc->user array. This is very handy for botcoding.
     *
     * @param boolean $boolean
     * @return boolean
     */
    public function setUserSyncing($boolean)
    {
        if ($boolean) {
            $this->_usersyncing = true;
			$this->log(SMARTIRC_DEBUG_USERSYNCING,
                'DEBUG_USERSYNCING: User syncing enabled', __FILE__, __LINE__);
        } else {
            $this->_usersyncing = false;
			$this->log(SMARTIRC_DEBUG_USERSYNCING,
                'DEBUG_USERSYNCING: User syncing disabled', __FILE__, __LINE__);
        }
        return $this->_usersyncing;
    }
    
    /**
     * Enables/disables the usage of real sockets.
     *
     * Enables/disables the usage of real sockets instead of fsocks
     * (works only if your PHP build has loaded the PHP socket extension)
     * Default: false
     *
     * @param bool $boolean
     * @return boolean
     */
    public function setUseSockets($boolean)
    {
        if (!$boolean) {
            $this->_usesockets = false;
            return true;
        }
        
        if (@extension_loaded('sockets')) {
            $this->_usesockets = true;
        } else {
            $this->log(SMARTIRC_DEBUG_NOTICE,
                'WARNING: socket extension not loaded, trying to load it...',
                __FILE__, __LINE__
            );
            
            if ((strtoupper(substr(PHP_OS, 0,3)) == 'WIN'
                    && @dl('php_sockets.dll')
                )
                || @dl('sockets.so')
            ) {
                $this->log(SMARTIRC_DEBUG_NOTICE,
                    'WARNING: socket extension successfully loaded',
                    __FILE__, __LINE__
                );
                $this->_usesockets = true;
            } else {
                $this->log(SMARTIRC_DEBUG_NOTICE,
                    "WARNING: couldn't load the socket extension, "
                    .'will use fsocks instead', __FILE__, __LINE__
                );
                $this->_usesockets = false;
            }
        }
        
        return $this->_usesockets;
    }
    
    /**
     * Starts the benchmark (sets the counters).
     *
     * @return void
     */
    public function startBenchmark()
    {
        $this->_benchmark_starttime = microtime(true);
        $this->log(SMARTIRC_DEBUG_NOTICE, 'benchmark started', __FILE__, __LINE__);
    }
    
    /**
     * Stops the benchmark and displays the result.
     *
     * @return void
     */
    public function stopBenchmark()
    {
        $this->_benchmark_stoptime = microtime(true);
        $this->log(SMARTIRC_DEBUG_NOTICE, 'benchmark stopped', __FILE__, __LINE__);
        
        if ($this->_benchmark) {
            $this->showBenchmark();
        }
    }
    
    /**
     * Shows the benchmark result.
     *
     * @return void
     */
    public function showBenchmark()
    {
        $this->log(SMARTIRC_DEBUG_NOTICE, 'benchmark time: '
            .((float)$this->_benchmark_stoptime-(float)$this->_benchmark_starttime),
            __FILE__, __LINE__
        );
    }
    
    /**
     * Adds an entry to the log.
     *
     * Adds an entry to the log with Linux style log format.
     * Possible $level constants (can also be combined with "|"s)
     * SMARTIRC_DEBUG_NONE
     * SMARTIRC_DEBUG_NOTICE
     * SMARTIRC_DEBUG_CONNECTION
     * SMARTIRC_DEBUG_SOCKET
     * SMARTIRC_DEBUG_IRCMESSAGES
     * SMARTIRC_DEBUG_MESSAGETYPES
     * SMARTIRC_DEBUG_ACTIONHANDLER
     * SMARTIRC_DEBUG_TIMEHANDLER
     * SMARTIRC_DEBUG_MESSAGEHANDLER
     * SMARTIRC_DEBUG_CHANNELSYNCING
     * SMARTIRC_DEBUG_MODULES
     * SMARTIRC_DEBUG_USERSYNCING
     * SMARTIRC_DEBUG_ALL
     *
     * @see SMARTIRC_DEBUG_NOTICE
     * @param integer $level bit constants (SMARTIRC_DEBUG_*)
     * @param string $entry the new log entry
     * @return boolean
     */
    public function log($level, $entry, $file = null, $line = null)
    {
        // prechecks
        if (!(
            is_integer($level)
            && ($level & SMARTIRC_DEBUG_ALL)
        )) {
            $this->log(SMARTIRC_DEBUG_NOTICE,
                'WARNING: invalid log level passed to log() ('.$level.')',
                __FILE__, __LINE__
            );
            return false;
        }
        
        if (!($level & $this->_debuglevel)
            || $this->_logdestination == SMARTIRC_NONE
        ) {
            return true;
        }
        
        if (substr($entry, -1) != "\n") {
            $entry .= "\n";
        }
        
        if ($file !== null && $line !== null) {
            $file = basename($file);
            $entry = $file.'('.$line.') '.$entry;
        } else {
            $entry = 'unknown(0) '.$entry;
        }
        
        $formattedentry = date('M d H:i:s ').$entry;
        
        switch ($this->_logdestination) {
            case SMARTIRC_STDOUT:
                echo $formattedentry;
                flush();
                break;
            
            case SMARTIRC_BROWSEROUT:
                echo '<pre>'.htmlentities($formattedentry).'</pre>';
                break;
            
            case SMARTIRC_FILE:
                if (!is_resource($this->_logfilefp)) {
                    if ($this->_logfilefp === null) {
                        // we reconncted and don't want to destroy the old log entries
                        $this->_logfilefp = fopen($this->_logfile,'a');
                    } else {
                        $this->_logfilefp = fopen($this->_logfile,'w');
                    }
                }
                
                fwrite($this->_logfilefp, $formattedentry);
                fflush($this->_logfilefp);
                break;
            
            case SMARTIRC_SYSLOG:
                if (!is_int($this->_logfilefp)) {
                    $this->_logfilefp = openlog('Net_SmartIRC', LOG_NDELAY,
                        LOG_DAEMON
                    );
                }
                
                syslog(LOG_INFO, $entry);
        }
        return true;
    }
    
    /**
     * Returns the full motd.
     *
     * @return array
     */
    public function getMotd()
    {
        return $this->_motd;
    }
    
    /**
     * Returns the usermode.
     *
     * @return string
     */
    public function getUsermode()
    {
        return $this->_usermode;
    }
    
    /**
     * Returns a reference to the channel object of the specified channelname.
     *
     * @param string $channelname
     * @return object
     */
    public function &getChannel($channelname)
    {
        if (!$this->_channelsyncing) {
            $this->log(SMARTIRC_DEBUG_NOTICE,
                'WARNING: getChannel() is called and the required Channel '
                .'Syncing is not activated!', __FILE__, __LINE__
            );
            return self::NULLGUARD;
        }
        
        if (isset($this->_channels[strtolower($channelname)])) {
            return $this->_channels[strtolower($channelname)];
        } else {
            $this->log(SMARTIRC_DEBUG_NOTICE,
                'WARNING: getChannel() is called and the required channel '
                .$channelname.' has not been joined!', __FILE__, __LINE__
            );
            return self::NULLGUARD;
        }
    }
    
    /**
     * Returns a reference to the user object for the specified username and channelname.
     *
     * @param string $channelname
     * @param string $username
     * @return object
     */
    public function &getUser($channelname, $username)
    {
        if (!$this->_channelsyncing) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: getUser() is called and'
                .' the required Channel Syncing is not activated!',
                __FILE__, __LINE__
            );
            return self::NULLGUARD;
        }
        
        if ($this->isJoined($channelname, $username)) {
            return $this->getChannel($channelname)->users[strtolower($username)];
        } else {
            return self::NULLGUARD;
        }
    }

    /**
     * Creates the sockets and connects to the IRC server on the given port.
     *
     * @param string $address 
     * @param integer $port
     * @return boolean
     */
    public function connect($address, $port)
    {
        $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: connecting',
            __FILE__, __LINE__
        );
        $this->_address = $address;
        $this->_port = $port;
        
        if ($this->_usesockets) {
            $this->log(SMARTIRC_DEBUG_SOCKET, 'DEBUG_SOCKET: using real sockets',
                __FILE__, __LINE__
            );
            $this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            
            if ($this->_bindaddress !== null) {
                if (socket_bind($this->_socket, $this->_bindaddress,
                    $this->_bindport
                )) {
                    $this->log(SMARTIRC_DEBUG_SOCKET,
                        'DEBUG_SOCKET: bound to '.$this->_bindaddress.':'
                        .$this->_bindport, __FILE__, __LINE__);
                } else {
                    $errno = socket_last_error($this->_socket);
                    $error_msg = 'unable to bind '.$this->_bindaddress.':'
                        .$this->_bindport.' reason: '.socket_strerror($errno)
                        .' ('.$errno.')';
                    $this->log(SMARTIRC_DEBUG_NOTICE,
                        'DEBUG_NOTICE: '.$error_msg, __FILE__, __LINE__);
                    $this->throwError($error_msg);
                }
            }
            
            $result = socket_connect($this->_socket, $this->_address, $this->_port);
        } else {
            $this->log(SMARTIRC_DEBUG_SOCKET, 'DEBUG_SOCKET: using fsockets',
                __FILE__, __LINE__
            );
            $result = fsockopen($this->_address, $this->_port, $errno, $errstr);
        }
        
        if ($result === false) {
            if ($this->_usesockets) {
                $error = socket_strerror(socket_last_error($this->_socket));
            } else {
                $error = $errstr.' ('.$errno.')';
            }
            
            $error_msg = 'couldn\'t connect to "'.$address.'" reason: "'.$error.'"';
            $this->log(SMARTIRC_DEBUG_NOTICE, 'DEBUG_NOTICE: '.$error_msg,
                __FILE__, __LINE__
            );
            
            $this->throwError($error_msg);
            
            if ($this->_autoretry
                && $this->_autoretrycount < $this->_autoretrymax
            ) {
                 $this->_delayReconnect();
                 $this->_autoretrycount++;
                 return $this->reconnect();
            }
            
            return false;
        } else {
            $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: connected',
                __FILE__, __LINE__
            );
            $this->_autoretrycount = 0;
            $this->_connectionerror = false;
            
            if (!$this->_usesockets) {
                $this->_socket = $result;
                $this->log(SMARTIRC_DEBUG_SOCKET, 'DEBUG_SOCKET: activating '
                    .'nonblocking fsocket mode', __FILE__, __LINE__
                );
                stream_set_blocking($this->_socket, 0);
            }
            
            $this->registerTimeHandler(($this->_rxtimeout / 8) * 1000,
                $this, '_pingcheck'
            );
        }
        
        $this->_lastrx = time();
        $this->_lasttx = $this->_lastrx;
        $this->_updatestate();
        
        return ($result !== false);
    }
    
    /**
     * Disconnects from the IRC server nicely with a QUIT or just destroys the socket.
     *
     * Disconnects from the IRC server in the given quickness mode.
     * $quick:
     * - true, just close the socket
     * - false, send QUIT and wait {@link $_disconnectime $_disconnectime} before
     *   closing the socket
     *
     * @param boolean $quick default: false
     * @return boolean
     */
    function disconnect($quick = false)
    {
        if ($this->_updatestate() != SMARTIRC_STATE_CONNECTED) {
            return false;
        }
        
        if (!$quick) {
            $this->send('QUIT', SMARTIRC_CRITICAL);
            usleep($this->_disconnecttime*1000);
        }
        
        if ($this->_usesockets) {
            @socket_shutdown($this->_socket);
            @socket_close($this->_socket);
        } else {
            fclose($this->_socket);
        }
        
        $this->_updatestate();
        $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: disconnected',
            __FILE__, __LINE__
        );
        
        if ($this->_channelsyncing) {
            // let's clean our channel array
            $this->_channels = array();
            $this->log(SMARTIRC_DEBUG_CHANNELSYNCING, 'DEBUG_CHANNELSYNCING: '
                .'cleaned channel array', __FILE__, __LINE__
            );
        }
        
        if ($this->_usersyncing) {
            // let's clean our user array
            $this->_users = array();
            $this->log(SMARTIRC_DEBUG_USERSYNCING, 'DEBUG_USERSYNCING: cleaned '
                .'user array', __FILE__, __LINE__
            );
        }
        
        if ($this->_logdestination == SMARTIRC_FILE) {
            fclose($this->_logfilefp);
            $this->_logfilefp = null;
        } else if ($this->_logdestination == SMARTIRC_SYSLOG) {
            closelog();
        }
        
        return true;
    }
    
    /**
     * Reconnects to the IRC server with the same login info,
     * it also rejoins the channels
     *
     * @return boolean
     */
    public function reconnect()
    {
        $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: reconnecting...',
            __FILE__, __LINE__
        );
        
        // remember in which channels we are joined
        $channels = array();
        foreach ($this->_channels as $value) {
            if (empty($value->key)) {
                $channels[] = array('name' => $value->name);
            } else {
                $channels[] = array('name' => $value->name, 'key' => $value->key);
            }
        }
        
        $this->disconnect(true);
        
        if (!$this->connect($this->_address, $this->_port)) {
            return false;
        }
        
        $this->login($this->_nick, $this->_realname, $this->_usermode,
            $this->_username, $this->_password
        );
        
        // rejoin the channels
        foreach ($channels as $value) {
            if (isset($value['key'])) {
                $this->join($value['name'], $value['key']);
            } else {
                $this->join($value['name']);
            }
        }
        
        return true;
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
    public function login($nick, $realname, $usermode = 0, $username = null,
        $password = null
    ) {
        $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: logging in', 
            __FILE__, __LINE__
        );
        
        $this->_nick = str_replace(' ', '', $nick);
        $this->_realname = $realname;
        
        if ($username !== null) {
            $this->_username = str_replace(' ', '', $username);
        } else {
            $this->_username = str_replace(' ', '', exec('whoami'));
        }
        
        if ($password !== null) {
            $this->_password = $password;
            $this->send('PASS '.$this->_password, SMARTIRC_CRITICAL);
        }
        
        if (!is_numeric($usermode)) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'DEBUG_NOTICE: login() usermode ('
                .$usermode.') is not valid, will use 0 instead',
                __FILE__, __LINE__
            );
            $usermode = 0;
        }
        
        $this->send('NICK '.$this->_nick, SMARTIRC_CRITICAL);
        $this->send('USER '.$this->_username.' '.$usermode.' '.SMARTIRC_UNUSED
            .' :'.$this->_realname, SMARTIRC_CRITICAL
        );
        
        if (count($this->_performs)) {
            // if we have extra commands to send, do it now
            foreach ($this->_performs as $command) {
                $this->send($command, SMARTIRC_HIGH);
            }
            // if we sent "ns auth" commands, we may need to resend our nick
            $this->send('NICK '.$this->_nick, SMARTIRC_HIGH);
        }
    }
    
    // </IRC methods>
    
    /**
     * adds a command to the list of commands to be sent after login() info
     * 
     * @param string $cmd the command to add to the perform list
     * @return void
     */
    public function perform($cmd)
    {
        $this->_performs[] = $cmd;
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
    public function send($data, $priority = SMARTIRC_MEDIUM)
    {
        switch ($priority) {
            case SMARTIRC_CRITICAL:
                $this->_rawsend($data);
            break;
            case SMARTIRC_HIGH:
            case SMARTIRC_MEDIUM:
            case SMARTIRC_LOW:
                $this->_messagebuffer[$priority][] = $data;
            break;
            default:
                $this->log(SMARTIRC_DEBUG_NOTICE, "WARNING: message ($data) "
                    ."with an invalid priority passed ($priority), message is "
                    .'ignored!', __FILE__, __LINE__
                );
                return false;
        }
        
        return true;
    }
    
    /**
     * checks if the passed nickname is our own nickname
     *
     * @param string $nickname
     * @return boolean
     */
    public function isMe($nickname)
    {
        return ($nickname == $this->_nick);
    }
    
    /**
     * checks if we or the given user is joined to the specified channel and returns the result
     * ChannelSyncing is required for this.
     *
     * @see setChannelSyncing
     * @param string $channel
     * @param string $nickname
     * @return boolean
     */
    public function isJoined($channel, $nickname = null)
    {
        if (!$this->_channelsyncing) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: isJoined() is called '
                .'and the required Channel Syncing is not activated!',
                __FILE__, __LINE__
            );
            return false;
        }
        
        if (!isset($this->_channels[strtolower($channel)])) {
            if ($nickname !== null) {
                $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: isJoined() is called'
                    .' on a user in a channel we are not joined to!',
                    __FILE__, __LINE__
                );
            }
            return false;
        }
        
        if ($nickname === null) {
            return true;
        }
        
        return isset($this->getChannel($channel)->users[strtolower($nickname)]);
    }
    
    /**
     * Checks if we or the given user is founder on the specified channel and returns the result.
     * ChannelSyncing is required for this.
     *
     * @see setChannelSyncing
     * @param string $channel
     * @param string $nickname
     * @return boolean
     */
    public function isFounder($channel, $nickname = null)
    {
        if (!$this->_channelsyncing) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: isFounder() is called '
                .'and the required Channel Syncing is not activated!',
                __FILE__, __LINE__
            );
            return false;
        }
        
        if ($nickname === null) {
            $nickname = $this->_nick;
        }
        
        return ($this->isJoined($channel, $nickname)
            && $this->getUser($channel, $nickname)->founder
        );
    }
    
    /**
     * Checks if we or the given user is admin on the specified channel and returns the result.
     * ChannelSyncing is required for this.
     *
     * @see setChannelSyncing
     * @param string $channel
     * @param string $nickname
     * @return boolean
     */
    public function isAdmin($channel, $nickname = null)
    {
        if (!$this->_channelsyncing) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: isAdmin() is called '
                .'and the required Channel Syncing is not activated!',
                __FILE__, __LINE__
            );
            return false;
        }
        
        if ($nickname === null) {
            $nickname = $this->_nick;
        }
        
        return ($this->isJoined($channel, $nickname)
            && $this->getUser($channel, $nickname)->admin
        );
    }
    
    /**
     * Checks if we or the given user is opped on the specified channel and returns the result.
     * ChannelSyncing is required for this.
     *
     * @see setChannelSyncing
     * @param string $channel
     * @param string $nickname
     * @return boolean
     */
    public function isOpped($channel, $nickname = null)
    {
        if (!$this->_channelsyncing) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: isOpped() is called '
                .'and the required Channel Syncing is not activated!',
                __FILE__, __LINE__
            );
            return false;
        }
        
        if ($nickname === null) {
            $nickname = $this->_nick;
        }
        
        return ($this->isJoined($channel, $nickname)
            && $this->getUser($channel, $nickname)->op
        );
    }
    
    /**
     * Checks if we or the given user is hopped on the specified channel and returns the result.
     * ChannelSyncing is required for this.
     *
     * @see setChannelSyncing
     * @param string $channel
     * @param string $nickname
     * @return boolean
     */
    public function isHopped($channel, $nickname = null)
    {
        if (!$this->_channelsyncing) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: isHopped() is called '
                .'and the required Channel Syncing is not activated!',
                __FILE__, __LINE__
            );
            return false;
        }
        
        if ($nickname === null) {
            $nickname = $this->_nick;
        }
        
        return ($this->isJoined($channel, $nickname)
            && $this->getUser($channel, $nickname)->hop
        );
    }
    
    /**
     * Checks if we or the given user is voiced on the specified channel and returns the result.
     * ChannelSyncing is required for this.
     *
     * @see setChannelSyncing
     * @param string $channel
     * @param string $nickname
     * @return boolean
     */
    public function isVoiced($channel, $nickname = null)
    {
        if (!$this->_channelsyncing) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: isVoiced() is called '
                .'and the required Channel Syncing is not activated!',
                __FILE__, __LINE__
            );
            return false;
        }
        
        if ($nickname === null) {
            $nickname = $this->_nick;
        }
        
        return ($this->isJoined($channel, $nickname)
            && $this->getUser($channel, $nickname)->voice
        );
    }
    
    /**
     * Checks if the hostmask is on the specified channel banned and returns the result.
     * ChannelSyncing is required for this.
     *
     * @see setChannelSyncing
     * @param string $channel
     * @param string $hostmask
     * @return boolean
     */
    public function isBanned($channel, $hostmask)
    {
        if (!$this->_channelsyncing) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: isBanned() is called '
                .'and the required Channel Syncing is not activated!',
                __FILE__, __LINE__
            );
            return false;
        }
        
        return ($this->isJoined($channel)
                && array_search($hostmask, $this->getChannel($channel)->bans
            ) !== false
        );
    }
    
    /**
     * Provides a mechanism to interrupt a listen() loop by a bot or something
     * 
     * @return boolean
     */
    public function interrupt($ival = true)
    {
        return ($this->_interrupt = $ival);
    }
    
    /**
     * goes into receive mode
     *
     * Goes into receive and idle mode. Only call this if you want to "spawn" the bot.
     * No further lines of PHP code will be processed after this call, only the bot methods!
     *
     * @return void
     */
    public function listen()
    {
        while ($this->listenOnce() && !$this->_interrupt) {}
    }
    
    /**
     * goes into receive mode _only_ for one pass
     *
     * Goes into receive mode. It will return when one pass is complete.
     * Use this when you want to connect to multiple IRC servers.
     *
     * @return boolean
     */
    public function listenOnce()
    {
        // if we're not connected, we can't listen, so return
        if ($this->_updatestate() != SMARTIRC_STATE_CONNECTED) {
            return false;
        }
        
        // before we listen, let's send what's queued
        if ($this->_loggedin) {
            static $highsent = 0;
            static $lastmicrotimestamp = 0;
            
            if ($lastmicrotimestamp == 0) {
                $lastmicrotimestamp = microtime(true);
            }
            
            $highcount = count($this->_messagebuffer[SMARTIRC_HIGH]);
            $mediumcount = count($this->_messagebuffer[SMARTIRC_MEDIUM]);
            $lowcount = count($this->_messagebuffer[SMARTIRC_LOW]);
            $this->_messagebuffersize = $highcount+$mediumcount+$lowcount;
            
            // don't send them too fast
            if ($this->_messagebuffersize
                && microtime(true) 
                    >= ($lastmicrotimestamp+($this->_senddelay/1000))
            ) {
                $result = null;
                if ($highcount > 0 && $highsent <= 2) {
                    $this->_rawsend(array_shift($this->_messagebuffer[SMARTIRC_HIGH]));
                    $lastmicrotimestamp = microtime(true);
                    $highsent++;
                } else if ($mediumcount > 0) {
                    $this->_rawsend(array_shift($this->_messagebuffer[SMARTIRC_MEDIUM]));
                    $lastmicrotimestamp = microtime(true);
                    $highsent = 0;
                } else if ($lowcount > 0) {
                    $this->_rawsend(array_shift($this->_messagebuffer[SMARTIRC_LOW]));
                    $lastmicrotimestamp = microtime(true);
                }
            }
        }
        
        // get data from the real socket or fsock
        if ($this->_usesockets) {
            // calculate selecttimeout
            $compare = array($this->_maxtimer, $this->_rxtimeout*1000);
            
            if ($this->_mintimer) {
                $compare[] = $this->_mintimer;
            }
            
            $selecttimeout = ($this->_messagebuffersize != 0)
                ? $this->_senddelay
                : min($compare)
            ;
            
            // check the socket to see if data is waiting for us
            // this will trigger a warning when catching a signal - silence it
            $sockarr = array($this->_socket);
            $result = @socket_select($sockarr, $w = null, $e = null, 0,
                $selecttimeout * 1000
            );
            
            $rawdata = null;
            
            if ($result) {
                // the socket got data to read
                $rawdata = socket_read($this->_socket, 1024);
            } else if ($result === false) {
                if (socket_last_error() == 4) {
                    // we got hit with a SIGHUP signal, try to reload modules
                    foreach ($this->_modules as $mod) {
                        if (is_callable(array($mod, 'reload'))) {
                            $mod->reload();
                        }
                    }
                } else {
                    // panic! panic! something went wrong!
                    $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: socket_select()'
                        .' returned false, something went wrong! Reason: '
                        .socket_strerror(socket_last_error()),
                        __FILE__, __LINE__
                    );
                    exit;
                }
            }
            // no data on the socket
        } else {
            usleep($this->_receivedelay*1000);
            $rawdata = fread($this->_socket, 1024);
        }
        
        // if reading from the socket failed, the connection is broken
        if ($rawdata === false) {
            $this->_connectionerror = true;
        }
        
        // see if any timehandler needs to be called
        if ($this->_loggedin) {
            foreach ($this->_timehandler as &$handlerobject) {
                $microtimestamp = microtime(true);
                if ($microtimestamp >= $handlerobject->lastmicrotimestamp
                    + ($handlerobject->interval / 1000)
                ) {
                    $methodobject = &$handlerobject->object;
                    $method = $handlerobject->method;
                    $handlerobject->lastmicrotimestamp = $microtimestamp;
                    
                    if (method_exists($methodobject, $method)) {
                        $this->log(SMARTIRC_DEBUG_TIMEHANDLER, 'DEBUG_TIMEHANDLER: '
                            .'calling method "'.get_class($methodobject).'->'
                            .$method.'"', __FILE__, __LINE__
                        );
                        $methodobject->$method($this);
                    }
                }
            }
        }
        
        // make sure we haven't timed out in any sort of way {
        $timestamp = time();
        if ($this->_lastrx < ($timestamp - $this->_rxtimeout)) {
            $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: '
                .'receive timeout detected, doing reconnect...',
                __FILE__, __LINE__
            );
            $this->_delayReconnect();
            $this->reconnect();
        } else if ($this->_lasttx < ($timestamp - $this->_txtimeout)) {
            $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: '
                .'transmit timeout detected, doing reconnect...',
                __FILE__, __LINE__
            );
            $this->_delayReconnect();
            $this->reconnect();
        }
        // }
        
        $rawdataar = array();
        
        // if we have data, split it up by message
        if (!empty($rawdata)) {
            $this->_lastrx = $timestamp;
            $rawdata = str_replace("\r", '', $rawdata);
            
            // not sure what the point of this line was..
            // $lastpart = substr($rawdata, strrpos($rawdata ,"\n")+1);
            $rawdata = substr($rawdata, 0, strrpos($rawdata ,"\n"));
            $rawdataar = explode("\n", $rawdata);
        }
        
        // loop through our received messages
        foreach ($rawdataar as $rawline) {
            $this->log(SMARTIRC_DEBUG_IRCMESSAGES, 'DEBUG_IRCMESSAGES: '
                ."received: \"$rawline\"", __FILE__, __LINE__
            );
            
            // building our data packet
            $ircdata = new Net_SmartIRC_data();
            $ircdata->rawmessage = $rawline;
            $ircdata->rawmessageex = explode(' ', $rawline); // kept for BC
            
            // parsing the message {
            $prefix = $trailing = '';
            $prefixEnd = -1;
            
            // parse out the prefix
            if ($rawline{0} == ':') {
                $prefixEnd = strpos($rawline, ' ');
                $prefix = substr($rawline, 1, $prefixEnd - 1);
            }
            
            // parse out the trailing
            if ($trailingStart = strpos($rawline, ' :')) { // this is not ==
                $trailing = substr($rawline, $trailingStart + 2);
            } else {
                $trailingStart = strlen($rawline);
            }
            
            // parse out command and params
            $params = explode(' ', substr($rawline,
                                          $prefixEnd + 1,
                                          $trailingStart - $prefixEnd - 1
            ));
            $command = array_shift($params);
            // }
            
            $ircdata->from = $prefix;
            $ircdata->params = $params;
            $ircdata->message = $trailing;
            $ircdata->messageex = explode(' ', $trailing); // kept for BC
            
            // parse ident thingy
            if (preg_match('/^(\S+)!(\S+)@(\S+)$/', $prefix, $matches)) {
                $ircdata->nick = $matches[1];
                $ircdata->ident = $matches[2];
                $ircdata->host = $matches[3];
            }
            
            // figure out what SMARTIRC_TYPE this message is
            switch ($command) {
                case SMARTIRC_RPL_WELCOME:
                case SMARTIRC_RPL_YOURHOST:
                case SMARTIRC_RPL_CREATED:
                case SMARTIRC_RPL_MYINFO:
                case SMARTIRC_RPL_BOUNCE:
                    $ircdata->type = SMARTIRC_TYPE_LOGIN;
                    break;
                
                case SMARTIRC_RPL_LUSERCLIENT:
                case SMARTIRC_RPL_LUSEROP:
                case SMARTIRC_RPL_LUSERUNKNOWN:
                case SMARTIRC_RPL_LUSERME:
                case SMARTIRC_RPL_LUSERCHANNELS:
                    $ircdata->type = SMARTIRC_TYPE_INFO;
                    break;
                
                case SMARTIRC_RPL_MOTDSTART:
                case SMARTIRC_RPL_MOTD:
                case SMARTIRC_RPL_ENDOFMOTD:
                    $ircdata->type = SMARTIRC_TYPE_MOTD;
                    break;
                
                case SMARTIRC_RPL_NAMREPLY:
                case SMARTIRC_RPL_ENDOFNAMES:
                    $ircdata->type = SMARTIRC_TYPE_NAME;
                    $ircdata->channel = $params[0];
                    break;
                
                case SMARTIRC_RPL_WHOREPLY:
                case SMARTIRC_RPL_ENDOFWHO:
                    $ircdata->type = SMARTIRC_TYPE_WHO;
                    $ircdata->channel = $params[0];
                    break;
                
                case SMARTIRC_RPL_LISTSTART:
                    $ircdata->type = SMARTIRC_TYPE_NONRELEVANT;
                    break;
                
                case SMARTIRC_RPL_LIST:
                case SMARTIRC_RPL_LISTEND:
                    $ircdata->type = SMARTIRC_TYPE_LIST;
                    break;
                
                case SMARTIRC_RPL_BANLIST:
                case SMARTIRC_RPL_ENDOFBANLIST:
                    $ircdata->type = SMARTIRC_TYPE_BANLIST;
                    $ircdata->channel = $params[0];
                    break;
                
                case SMARTIRC_RPL_TOPIC:
                    $ircdata->type = SMARTIRC_TYPE_TOPIC;
                    $ircdata->channel = $params[0];
                    break;
                
                case SMARTIRC_RPL_WHOISUSER:
                case SMARTIRC_RPL_WHOISSERVER:
                case SMARTIRC_RPL_WHOISOPERATOR:
                case SMARTIRC_RPL_WHOISIDLE:
                case SMARTIRC_RPL_ENDOFWHOIS:
                case SMARTIRC_RPL_WHOISCHANNELS:
                    $ircdata->type = SMARTIRC_TYPE_WHOIS;
                    break;
                
                case SMARTIRC_RPL_WHOWASUSER:
                case SMARTIRC_RPL_ENDOFWHOWAS:
                    $ircdata->type = SMARTIRC_TYPE_WHOWAS;
                    break;
                
                case SMARTIRC_RPL_UMODEIS:
                    $ircdata->type = SMARTIRC_TYPE_USERMODE;
                    break;
                
                case SMARTIRC_RPL_CHANNELMODEIS:
                    $ircdata->type = SMARTIRC_TYPE_CHANNELMODE;
                    $ircdata->channel = $params[0];
                    break;
                
                case SMARTIRC_ERR_NICKNAMEINUSE:
                case SMARTIRC_ERR_NOTREGISTERED:
                    $ircdata->type = SMARTIRC_TYPE_ERROR;
                    break;
                
                case 'PRIVMSG':
                    if (strspn($ircdata->params[0], '&#+!')) {
                        $ircdata->type = SMARTIRC_TYPE_CHANNEL;
                        $ircdata->channel = $params[0];
                        break;
                    }
                    if ($ircdata->message{0} == chr(1)) {
                        if (preg_match('/^'.chr(1).'ACTION .*'.chr(1).'$/',
                                       $ircdata->message
                        )) {
                            $ircdata->type = SMARTIRC_TYPE_ACTION;
                            $ircdata->channel = $params[0];
                            break;
                        }
                        if (preg_match('/^'.chr(1).'.*'.chr(1).'$/',
                                       $ircdata->message
                        )) {
                            $ircdata->type = (SMARTIRC_TYPE_CTCP_REQUEST
                                            | SMARTIRC_TYPE_CTCP
                            );
                            break;
                        }
                    }
                    $ircdata->type = SMARTIRC_TYPE_QUERY;
                    break;
                
                case 'NOTICE':
                    if (preg_match('/^'.chr(1).'.*'.chr(1).'$/',
                                   $ircdata->message
                    )) {
                        $ircdata->type = (SMARTIRC_TYPE_CTCP_REPLY
                                        | SMARTIRC_TYPE_CTCP
                        );
                        break;
                    }
                    $ircdata->type = SMARTIRC_TYPE_NOTICE;
                    break;
                
                case 'INVITE':
                    $ircdata->type = SMARTIRC_TYPE_INVITE;
                    break;
                
                case 'JOIN':
                    $ircdata->type = SMARTIRC_TYPE_JOIN;
                    $ircdata->channel = $params[0];
                    break;
                
                case 'TOPIC':
                    $ircdata->type = SMARTIRC_TYPE_TOPICCHANGE;
                    $ircdata->channel = $params[0];
                    break;
                
                case 'NICK':
                    $ircdata->type = SMARTIRC_TYPE_NICKCHANGE;
                    break;
                
                case 'KICK':
                    $ircdata->type = SMARTIRC_TYPE_KICK;
                    $ircdata->channel = $params[0];
                    break;
                
                case 'PART':
                    $ircdata->type = SMARTIRC_TYPE_PART;
                    $ircdata->channel = $params[0];
                    break;
                
                case 'MODE':
                    $ircdata->type = SMARTIRC_TYPE_MODECHANGE;
                    $ircdata->channel = $params[0];
                    break;
                
                case 'QUIT':
                    $ircdata->type = SMARTIRC_TYPE_QUIT;
                    break;
                
                default:
                    $this->log(SMARTIRC_DEBUG_IRCMESSAGES, 'DEBUG_IRCMESSAGES: '
                        ."command UNKNOWN ($code): \"$line\"",
                        __FILE__, __LINE__
                    );
                    $ircdata->type = SMARTIRC_TYPE_UNKNOWN;
                    break;
            }
            
            $this->log(SMARTIRC_DEBUG_MESSAGEPARSER, 'DEBUG_MESSAGEPARSER: '
                .'ircdata nick: "'.$ircdata->nick
                .'" ident: "'.$ircdata->ident
                .'" host: "'.$ircdata->host
                .'" type: "'.$ircdata->type
                .'" from: "'.$ircdata->from
                .'" channel: "'.$ircdata->channel
                .'" message: "'.$ircdata->message.'"', __FILE__, __LINE__
            );
            
            // lets see if we have a messagehandler for it
            if (is_numeric($command)) {
                if (!array_key_exists($command, $this->nreplycodes)) {
                    $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER,
                        'DEBUG_MESSAGEHANDLER: ignoring unrecognized messagecode "'
                        .$command.'"', __FILE__, __LINE__
                    );
                    $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER,
                        'DEBUG_MESSAGEHANDLER: this IRC server ('.$this->_address
                        .") doesn't conform to RFC 2812!",
                        __FILE__, __LINE__
                    );
                    // maybe not what we like, but we did listen successfully
                    return true;
                }
                
                $methodname = 'event_'.strtolower($this->nreplycodes[$command]);
                $_methodname = '_'.$methodname;
                $_codetype = 'by numeric';
            } else {
                $methodname = 'event_'.strtolower($command);
                $_methodname = '_'.$methodname;
                $_codetype = 'by string';
            }
            
            $found = false;
            
            // if exists call internal method for the handling
            if (method_exists($this, $_methodname)) {
                $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER, 'DEBUG_MESSAGEHANDLER: '
                    .'calling internal method "'.get_class($this).'->'
                    .$_methodname.'" ('.$_codetype.')', __FILE__, __LINE__
                );
                $this->$_methodname($ircdata);
                $found = true;
            }
            
            // if exists call user defined method for the handling
            if (method_exists($this, $methodname)) {
                $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER, 'DEBUG_MESSAGEHANDLER: '
                    .'calling user defined method "'.get_class($this).'->'
                    .$methodname.'" ('.$_codetype.')', __FILE__, __LINE__
                );
                $this->$methodname($ircdata);
                $found = true;
            }
            
            if (!$found) {
                $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER, 'DEBUG_MESSAGEHANDLER: no'
                    .' method found for "'.$command.'" ('.$methodname.')',
                    __FILE__, __LINE__
                );
            }
            
            // now the actionhandlers are coming
            foreach ($this->_actionhandler as $i => &$handlerobject) {
                    
                $regex = 
                    ($handlerobject->message{0}
                        == $handlerobject->message{
                            strlen($handlerobject->message) - 1
                        }
                    ) ? $handlerobject->message
                    : '/'.$handlerobject->message . '/'
                ;
                
                if (($handlerobject->type & $ircdata->type)
                    && preg_match($regex, $ircdata->message)
                ) {
                    $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: '
                        ."actionhandler match found for id: $i type: "
                        .$ircdata->type.' message: "'.$ircdata->message
                        ."\" regex: \"$regex\"", __FILE__, __LINE__
                    );
                    
                    $methodobject = &$handlerobject->object;
                    $method = $handlerobject->method;
                    
                    if (method_exists($methodobject, $method)) {
                        $this->log(SMARTIRC_DEBUG_ACTIONHANDLER,
                            'DEBUG_ACTIONHANDLER: calling method "'
                            .get_class($methodobject).'->'.$method.'"',
                            __FILE__, __LINE__
                        );
                        $methodobject->$method($this, $ircdata);
                    } else {
                        $this->log(SMARTIRC_DEBUG_ACTIONHANDLER,
                            'DEBUG_ACTIONHANDLER: method doesn\'t exist! "'
                            .get_class($methodobject).'->'.$method.'"',
                            __FILE__, __LINE__
                        );
                    }
                }
            }
            
            unset($ircdata);
        }
        
        // if we've done anything that didn't work and the connection is broken,
        // log it and fix it
        if ($this->_connectionerror) {
            $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: connection'
                .' error detected, will reconnect!', __FILE__, __LINE__
            );
            $this->reconnect();
        }
        return true;
    }
    
    /**
     * waits for a special message type and returns the answer
     *
     * Creates a special actionhandler for that given TYPE and returns the answer.
     * This will only receive the requested type, immediately quit and disconnect from the IRC server.
     * Made for showing IRC statistics on your homepage, or other IRC related information.
     *
     * @param integer $messagetype see in the documentation 'Message Types'
     * @return array answer from the IRC server for this $messagetype
     */
    public function listenFor($messagetype)
    {
        $listenfor = new Net_SmartIRC_listenfor();
        $this->registerActionHandler($messagetype, '.*', $listenfor, 'handler');
        $this->listen();
        return $listenfor->result;
    }
    
    /**
     * registers a new actionhandler and returns the assigned id
     *
     * Registers an actionhandler in Net_SmartIRC for calling it later.
     * The actionhandler id is needed for unregistering the actionhandler.
     *
     * @see example.php
     * @param integer $handlertype bits constants, see in this documentation Message Types
     * @param string $regexhandler the message that has to be in the IRC message in regex syntax
     * @param object $object a reference to the objects of the method
     * @param string $methodname the methodname that will be called when the handler happens
     * @return integer assigned actionhandler id
     */
    public function registerActionHandler($handlertype, $regexhandler, &$object,
        $methodname
    ) {
        // precheck
        if (!($handlertype & SMARTIRC_TYPE_ALL)) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: passed invalid handler'
                .'type to registerActionHandler()', __FILE__, __LINE__
            );
            return false;
        }
        
        $id = $this->_actionhandlerid++;
        $newactionhandler = new Net_SmartIRC_actionhandler();
        
        $newactionhandler->id = $id;
        $newactionhandler->type = $handlertype;
        $newactionhandler->message = $regexhandler;
        $newactionhandler->object = &$object;
        $newactionhandler->method = $methodname;
        
        $this->_actionhandler[] = &$newactionhandler;
        $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: '
            .'actionhandler('.$id.') registered', __FILE__, __LINE__
        );
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
    public function unregisterActionHandler($handlertype, $regexhandler,
        &$object, $methodname
    ) {
        // precheck
        if (!($handlertype & SMARTIRC_TYPE_ALL)) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: passed invalid handler'
                .'type to unregisterActionHandler()', __FILE__, __LINE__
            );
            return false;
        }
        
        $handler = &$this->_actionhandler;
        $handlercount = count($handler);
        
        for ($i = 0; $i < $handlercount; $i++) {
            $handlerobject = &$handler[$i];
                        
            if ($handlerobject->type == $handlertype
                && $handlerobject->message == $regexhandler
                && $handlerobject->method == $methodname
            ) {
                unset($this->_actionhandler[$i]);
                
                $id = $handlerobject->id;
                $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: '
                    .'actionhandler('.$id.') unregistered', __FILE__, __LINE__
                );
                $this->_actionhandler = array_values($this->_actionhandler);
                return true;
            }
        }
        
        $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: could '
            .'not find actionhandler type: "'.$handlertype.'" message: "'
            .$regexhandler.'" method: "'.$methodname.'" from object "'
            .get_class($object).'" _not_ unregistered', __FILE__, __LINE__
        );
        return false;
    }
    
    /**
     * unregisters an existing actionhandler via the id
     *
     * @param integer $id
     * @return boolean
     */
    public function unregisterActionId($id)
    {
        $handler = &$this->_actionhandler;
        $handlercount = count($handler);
        
        for ($i = 0; $i < $handlercount; $i++) {
            $handlerobject = &$handler[$i];
                        
            if ($handlerobject->id == $id) {
                unset($this->_actionhandler[$i]);
                
                $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: '
                    .'actionhandler('.$id.') unregistered', __FILE__, __LINE__
                );
                $this->_actionhandler = array_values($this->_actionhandler);
                return true;
            }
        }
        
        $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: could '
            .'not find actionhandler id: '.$id.' _not_ unregistered',
            __FILE__, __LINE__
        );
        return false;
    }
    
    /**
     * registers a timehandler and returns the assigned id
     *
     * Registers a timehandler in Net_SmartIRC, which will be called in the specified interval.
     * The timehandler id is needed for unregistering the timehandler.
     *
     * @see example7.php
     * @param integer $interval interval time in milliseconds
     * @param object $object a reference to the objects of the method
     * @param string $methodname the methodname that will be called when the handler happens
     * @return integer assigned timehandler id
     */
    public function registerTimeHandler($interval, &$object, $methodname)
    {
        $id = $this->_timehandlerid++;
        $newtimehandler = new Net_SmartIRC_timehandler();
        
        $newtimehandler->id = $id;
        $newtimehandler->interval = $interval;
        $newtimehandler->object = &$object;
        $newtimehandler->method = $methodname;
        $newtimehandler->lastmicrotimestamp = microtime(true);
        
        $this->_timehandler[] = &$newtimehandler;
        $this->log(SMARTIRC_DEBUG_TIMEHANDLER, 'DEBUG_TIMEHANDLER: timehandler('
            .$id.') registered', __FILE__, __LINE__
        );
        
        if (($interval < $this->_mintimer) || ($this->_mintimer == false)) {
            $this->_mintimer = $interval;
        }
            
        return $id;
    }
    
    /**
     * unregisters an existing timehandler via the id
     *
     * @see example7.php
     * @param integer $id
     * @return boolean
     */
    public function unregisterTimeId($id)
    {
        $handler = &$this->_timehandler;
        $handlercount = count($handler);
        
        for ($i = 0; $i < $handlercount; $i++) {
            $handlerobject = &$handler[$i];
            
            if ($handlerobject->id == $id) {
                unset($this->_timehandler[$i]);
                
                $this->log(SMARTIRC_DEBUG_TIMEHANDLER, 'DEBUG_TIMEHANDLER: '
                    .'timehandler('.$id.') unregistered', __FILE__, __LINE__
                );
                $this->_timehandler = array_values($this->_timehandler);
                
                $timerarray = array();
                foreach ($this->_timehandler as $values) {
                    $timerarray[] = $values->interval;
                }
                
                $this->_mintimer = (
                    array_multisort($timerarray, SORT_NUMERIC, SORT_ASC)
                    && isset($timerarray[0])
                ) ? $timerarray[0]
                    : false
                ;
                
                return true;
            }
        }
        
        $this->log(SMARTIRC_DEBUG_TIMEHANDLER, 'DEBUG_TIMEHANDLER: could not '
            ."find timehandler id: $id _not_ unregistered",
            __FILE__, __LINE__
        );
        return false;
    }
    
    /**
     * loads a module using preset path and given name
     * 
     * @param string $name
     * @return boolean
     */
    public function loadModule($name)
    {
        // is the module already loaded?
        if (in_array($name, $this->_modules)) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING! module with the name "'
                .$name.'" already loaded!', __FILE__, __LINE__
            );
            return false;
        }
        
        $filename = $this->_modulepath."/$name.php";
        if (!file_exists($filename)) {
            $this->log(SMARTIRC_DEBUG_MODULES, "DEBUG_MODULES: couldn't load "
                ."module; file \"$filename\" doesn't exist", __FILE__, __LINE__
            );
            return false;
        }
        
        $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: loading module: '
            ."\"$name\"...", __FILE__, __LINE__
        );
        // pray that there is no parse error, it will kill us!
        include_once($filename);
        $classname = "Net_SmartIRC_module_$name";
        
        if (!class_exists($classname)) {
            $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: class '
                ."$classname not found in $filename", __FILE__, __LINE__
            );
            return false;
        }
        
        $methods = get_class_methods($classname);
        
        if (!(in_array('__construct', $methods)
            || in_array('module_init', $methods)
        )) {
            $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: required method '
                .$classname.'::__construct not found, aborting...',
                __FILE__, __LINE__
            );
            return false;
        }
        
        if (!(in_array('__destruct', $methods)
            || in_array('module_exit', $methods)
        )) {
            $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: required method '
                .$classname.'::__destruct not found, aborting...',
                __FILE__, __LINE__
            );
            return false;
        }
        
        $vars = array_keys(get_class_vars($classname));
        $required = array('name', 'description', 'author', 'license');
        
        foreach ($required as $varname) {
            if (!in_array($varname, $vars)) {
                $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: required'
                    .'variable '.$classname.'::'.$varname
                    .' not found, aborting...',
                    __FILE__, __LINE__
                );
                return false;
            }
        }
        
        // looks like the module satisfies us, so instantiate it
        if (in_array('module_init', $methods)) {
            // we're using an old module_init style module
            $module = new $classname;
            $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: calling '
                .$classname.'::module_init()', __FILE__, __LINE__
            );
            $module->module_init($this);
        } else if (func_num_args() == 1) {
            // we're using a new __construct style module, which maintains its
            // own reference to the $irc client object it's being used on
            $module = new $classname($this);
        } else
        // we're using new style AND we have args to pass to the constructor
        if (func_num_args() == 2) {
            // only one arg, so pass it as is
            $module = new $classname($this, func_get_arg(1));
        } else {
            // multiple args, so pass them in an array
            $module = new $classname($this, array_slice(func_get_args(), 1));
        }
        
        $this->_modules[$name] = &$module;
        
        $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: successfully loaded'
            ." module: $name", __FILE__, __LINE__
        );
        return true;
    }
    
    /**
     * unloads a module by the name originally loaded with
     * 
     * @param string $name
     * @return boolean
     */
    public function unloadModule($name)
    {
        $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: unloading module: '
            ."$name...", __FILE__, __LINE__
        );
        
        $modules_keys = array_keys($this->_modules);
        $modulecount = count($modules_keys);
        for ($i = 0; $i < $modulecount; $i++) {
            $module = &$this->_modules[$modules_keys[$i]];
            $modulename = get_class($module);
            
            if (strtolower($modulename) == "net_smartirc_module_$name") {
                if (in_array('module_exit', get_class_methods($modulename))) { 
                    $module->module_exit($this);
                }
                unset($this->_modules[$i]); // should call __destruct() on it
                $this->_modules = array_values($this->_modules);
                $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: successfully'
                    ." unloaded module: $name", __FILE__, __LINE__);
                return true;
            }
        }
        
        $this->log(SMARTIRC_DEBUG_MODULES, "DEBUG_MODULES: couldn't unload"
            ." module: $name (it's not loaded!)", __FILE__, __LINE__
        );
        return false;
    }
    
    // <protected methods>
    /**
     * adds an user to the channelobject or updates his info
     *
     * @param object $channel
     * @param object $newuser
     * @return void
     */
    protected function _adduser(&$channel, &$newuser)
    {
        $lowerednick = strtolower($newuser->nick);
        if ($this->isJoined($channel->name, $newuser->nick)) {
            $this->log(SMARTIRC_DEBUG_CHANNELSYNCING, 'DEBUG_CHANNELSYNCING: '
                .'updating user: '.$newuser->nick.' on channel: '
                .$channel->name, __FILE__, __LINE__
            );
            
            // lets update the existing user
            $currentuser = &$channel->users[$lowerednick];
            
            $props = array('ident', 'host', 'realname', 'ircop', 'founder',
                'admin', 'op', 'hop', 'voice', 'away', 'server', 'hopcount'
            );
            foreach ($props as $prop) {
                if ($newuser->$prop !== null) {
                    $currentuser->$prop = $newuser->$prop;
                }
            }
        } else {
            $this->log(SMARTIRC_DEBUG_CHANNELSYNCING, 'DEBUG_CHANNELSYNCING: '
                .'adding user: '.$newuser->nick.' to channel: '.$channel->name,
                __FILE__, __LINE__
            );
            
            // he is new just add the reference to him
            $channel->users[$lowerednick] = &$newuser;
        }
        
        $user = &$channel->users[$lowerednick];
        $modes = array('founder', 'admin', 'op', 'hop', 'voice');
        
        foreach ($modes as $mode) {
            if ($user->$mode) {
                $this->log(SMARTIRC_DEBUG_CHANNELSYNCING,
                    "DEBUG_CHANNELSYNCING: adding $mode: ".$user->nick
                    .' to channel: '.$channel->name, __FILE__, __LINE__
                );
                $ms = $mode.'s';
                $channel->$ms[$user->nick] = true;
            }
        }
    }
    
    /**
     * Delay reconnect
     *
     * @return void
     */
    protected function _delayReconnect()
    {
        if ($this->_reconnectdelay > 0) {
            $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: delaying '
                .'reconnect for '.$this->_reconnectdelay.' ms',
                __FILE__, __LINE__
            );
            usleep($this->_reconnectdelay * 1000);
        }
    }
    
    /**
     * changes an already used nickname to a new nickname plus 3 random digits
     *
     * @return void
     */
    protected function _nicknameinuse()
    {
        $newnickname = substr($this->_nick, 0, 5) . rand(0, 999);
        $this->changeNick($newnickname, SMARTIRC_CRITICAL);
    }
    
    /**
     * An active-pinging system to keep the bot from dropping the connection
     * 
     * @return void
     */
    protected function _pingcheck () {
        $time = time();
        if ($time - $this->_lastrx > $this->_rxtimeout) {
            $this->reconnect();
            $this->_lastrx = $time;
        } elseif ($time - $this->_lastrx > $this->_rxtimeout/2) {
            $this->send('PING '.$this->_address, SMARTIRC_CRITICAL);
        }
    }
    
    /**
     * sends a raw message to the IRC server
     *
     * Don't use this directly! Use message() or send() instead.
     *
     * @param string $data
     * @return boolean
     */
    protected function _rawsend($data)
    {
        if ($this->_updatestate() != SMARTIRC_STATE_CONNECTED) {
            return false;
        }
        
        $this->log(SMARTIRC_DEBUG_IRCMESSAGES, 'DEBUG_IRCMESSAGES: sent: "'
            .$data.'"', __FILE__, __LINE__
        );
        
        if ($this->_usesockets) {
            $result = socket_write($this->_socket, $data.SMARTIRC_CRLF);
        } else {
            $result = fwrite($this->_socket, $data.SMARTIRC_CRLF);
        }
        
        
        if ($result === false) {
            // writing to the socket failed, means the connection is broken
            $this->_connectionerror = true;
        } else {
            $this->_lasttx = time();
        }
        
        return ($result !== false);
    }

    /**
     * removes an user from one channel or all if he quits
     *
     * @param object $ircdata
     * @return void
     */
    protected function _removeuser(&$ircdata)
    {
        if ($ircdata->type & (SMARTIRC_TYPE_PART | SMARTIRC_TYPE_QUIT)) {
            $nick = $ircdata->nick;
        } else if ($ircdata->type & SMARTIRC_TYPE_KICK) {
            $nick = $ircdata->rawmessageex[3];
        } else {
            $this->log(SMARTIRC_DEBUG_CHANNELSYNCING, 'DEBUG_CHANNELSYNCING: '
                .'unknown TYPE ('.$ircdata->type
                .') in _removeuser(), trying default', __FILE__, __LINE__
            );
            $nick = $ircdata->nick;
        }
        
        $lowerednick = strtolower($nick);
        
        if ($this->_nick == $nick) {
            $this->log(SMARTIRC_DEBUG_CHANNELSYNCING,
                'DEBUG_CHANNELSYNCING: we left channel: '.$ircdata->channel
                .' destroying...', __FILE__, __LINE__
            );
            unset($this->_channels[strtolower($ircdata->channel)]);
        } else {
            $lists = array('founders', 'admins', 'ops', 'hops', 'voices');
            
            if ($ircdata->type & SMARTIRC_TYPE_QUIT) {
                $this->log(SMARTIRC_DEBUG_CHANNELSYNCING,
                    'DEBUG_CHANNELSYNCING: user '.$nick
                    .' quit, removing him from all channels', __FILE__, __LINE__
                );
                
                // remove the user from all channels
                $channelkeys = array_keys($this->_channels);
                foreach ($channelkeys as $channelkey) {
                    // loop through all channels
                    $channel = &$this->getChannel($channelkey);
                    foreach ($channel->users as $uservalue) {
                        // loop through all user in this channel
                        if ($nick == $uservalue->nick) {
                            // found him, kill him
                            $this->log(SMARTIRC_DEBUG_CHANNELSYNCING,
                                'DEBUG_CHANNELSYNCING: found him on channel: '
                                .$channel->name.' destroying...',
                                __FILE__, __LINE__
                            );
                            unset($channel->users[$lowerednick]);
                            
                            foreach ($lists as $list) {
                                if (isset($channel->$list[$nick])) {
                                    // die!
                                    $this->log(SMARTIRC_DEBUG_CHANNELSYNCING,
                                        'DEBUG_CHANNELSYNCING: removing him '
                                        ."from $list list", __FILE__, __LINE__
                                    );
                                    unset($channel->$list[$nick]);
                                }
                            }
                        }
                    }
                }
            } else {
                $this->log(SMARTIRC_DEBUG_CHANNELSYNCING,
                    'DEBUG_CHANNELSYNCING: removing user: '.$nick
                    .' from channel: '.$ircdata->channel, __FILE__, __LINE__
                );
                $channel = &$this->getChannel($ircdata->channel);
                unset($channel->users[$lowerednick]);
                
                foreach ($lists as $list) {
                    if (isset($channel->$list[$nick])) {
                        $this->log(SMARTIRC_DEBUG_CHANNELSYNCING,
                            'DEBUG_CHANNELSYNCING: removing him '
                            ."from $list list", __FILE__, __LINE__
                        );
                        unset($channel->$list[$nick]);
                    }
                }
            }
        }
    }
    
    /**
     * updates and returns the current connection state
     *
     * @return boolean
     */
    protected function _updatestate()
    {
        if (is_resource($this->_socket)) {
            $rtype = get_resource_type($this->_socket);
            if ($this->_socket !== false
                && (strtolower($rtype) == 'socket' || $rtype == 'stream')
            ) {
                $this->_state = SMARTIRC_STATE_CONNECTED;
            }
        } else {
            $this->_state = SMARTIRC_STATE_DISCONNECTED;
            $this->_loggedin = false;
        }
        
        return $this->_state;
    }

    // </protected methods>
    
    function isError($object) // is this even needed/used?
    {
        return (is_object($object)
            && strtolower(get_class($object)) == 'net_smartirc_error'
        );
    }
    
    protected function &throwError($message)
    {
        return new Net_SmartIRC_Error($message);
    }
}

/**
 * Struct for parsed incoming messages
 */
class Net_SmartIRC_data
{
    /**
     * @var string
     */
    public $from;
    
    /**
     * @var string
     */
    public $nick;
    
    /**
     * @var string
     */
    public $ident;
    
    /**
     * @var string
     */
    public $host;
    
    /**
     * @var string
     */
    public $channel;
    
    /**
     * @var array
     */
    public $params = array();
    
    /**
     * @var string
     */
    public $message;
    
    /**
     * @var array
     */
    public $messageex = array();
    
    /**
     * @var integer
     */
    public $type;
    
    /**
     * @var string
     */
    public $rawmessage;
    
    /**
     * @var array
     */
    public $rawmessageex = array();
}

/**
 * Struct for individual action handlers
 */
class Net_SmartIRC_actionhandler
{
    /**
     * @var integer
     */
    public $id;
    
    /**
     * @var integer
     */
    public $type;
    
    /**
     * @var string
     */
    public $message;
    
    /**
     * @var object
     */
    public $object;
    
    /**
     * @var string
     */
    public $method;
}

/**
 * Struct for individual time handlers
 */
class Net_SmartIRC_timehandler
{
    /**
     * @var integer
     */
    public $id;
    
    /**
     * @var integer
     */
    public $interval;
    
    /**
     * @var integer
     */
    public $lastmicrotimestamp;
    
    /**
     * @var object
     */
    public $object;
    
    /**
     * @var string
     */
    public $method;
}

/**
 * Struct for individual channel data
 */
class Net_SmartIRC_channel
{
    /**
     * @var string
     */
    public $name;
    
    /**
     * @var string
     */
    public $key;
    
    /**
     * @var array
     */
    public $users = array();
    
    /**
     * @var array
     */
    public $founders = array();
    
    /**
     * @var array
     */
    public $admins = array();
    
    /**
     * @var array
     */
    public $ops = array();
    
    /**
     * @var array
     */
    public $hops = array();
    
    /**
     * @var array
     */
    public $voices = array();
    
    /**
     * @var array
     */
    public $bans = array();
    
    /**
     * @var string
     */
    public $topic;
    
    /**
     * @var string
     */
    public $user_limit = false;
    
    /**
     * @var string
     */
    public $mode;
    
    /**
     * @var integer
     */
    public $synctime_start = 0;
    
    /**
     * @var integer
     */
    public $synctime_stop = 0;
    
    /**
     * @var integer
     */
    public $synctime;
}

/**
 * Struct for individual user data
 */
class Net_SmartIRC_user
{
    /**
     * @var string
     */
    public $nick;
    
    /**
     * @var string
     */
    public $ident;
    
    /**
     * @var string
     */
    public $host;
    
    /**
     * @var string
     */
    public $realname;
    
    /**
     * @var boolean
     */
    public $ircop;
    
    /**
     * @var boolean
     */
    public $away;
    
    /**
     * @var string
     */
    public $server;
    
    /**
     * @var integer
     */
    public $hopcount;
}

/**
 * Struct for extra data that applies to each user in each channel they're in
 */
class Net_SmartIRC_channeluser extends Net_SmartIRC_user
{
    /**
     * @var boolean
     */
    public $founder;

    /**
     * @var boolean
     */
    public $admin;
    
    /**
     * @var boolean
     */
    public $op;
    
    /**
     * @var boolean
     */
    public $hop;
    
    /**
     * @var boolean
     */
    public $voice;
}

/**
 * Struct for data that applies to each user server-wide
 */
class Net_SmartIRC_ircuser extends Net_SmartIRC_user
{
    /**
     * @var array
     */
    public $joinedchannels = array();
}

/**
 * Built-in bot used by Net_SmartIRC::listenFor()
 */
class Net_SmartIRC_listenfor
{
    /**
     * @var array
     */
    public $result = array();
    
    /**
     * stores the received answer into the result array
     *
     * @param object $irc
     * @param object $ircdata
     * @return void
     */
    public function handler(&$irc, &$ircdata)
    {
        $irc->log(SMARTIRC_DEBUG_ACTIONHANDLER,
            'DEBUG_ACTIONHANDLER: listenfor handler called', __FILE__, __LINE__
        );
        $this->result[] = $ircdata;
        $irc->disconnect();
    }
}

class Net_SmartIRC_Error
{
    private $error_msg;
    
    public function __construct($message)
    {
        $this->error_msg = $message;
    }
    
    public function getMessage()
    {
        return $this->error_msg;
    }
}
