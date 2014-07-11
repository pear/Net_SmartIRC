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
class Net_SmartIRC_base
{
    /**
     * @var resource
     * @access private
     */
    private $_socket;
    
    /**
     * @var string
     * @access private
     */
    private $_address;
    
    /**
     * @var integer
     * @access private
     */
    private $_port;
    
    /**
     * @var string
     * @access private
     */
    private $_bindaddress = null;
    
    /**
     * @var integer
     * @access private
     */
    private $_bindport = 0;
    
    /**
     * @var string
     * @access private
     */
    private $_nick;
    
    /**
     * @var string
     * @access private
     */
    private $_username;
    
    /**
     * @var string
     * @access private
     */
    private $_realname;
    
    /**
     * @var string
     * @access private
     */
    private $_usermode;
    
    /**
     * @var string
     * @access private
     */
    private $_password;
    
    /**
     * @var array
     * @access private
     */
    private $_performs = array();
    
    /**
     * @var boolean
     * @access private
     */
    private $_state = SMARTIRC_STATE_DISCONNECTED;
    
    /**
     * @var array
     * @access private
     */
    private $_actionhandler = array();
    
    /**
     * @var array
     * @access private
     */
    private $_timehandler = array();
    
    /**
     * @var integer
     * @access private
     */
    private $_debuglevel = SMARTIRC_DEBUG_NOTICE;
    
    /**
     * @var array
     * @access private
     */
    private $_messagebuffer = array(
		SMARTIRC_CRITICAL => array(),
		SMARTIRC_HIGH     => array(),
		SMARTIRC_MEDIUM   => array(),
		SMARTIRC_LOW 	  => array(),
	);
    
    /**
     * @var integer
     * @access private
     */
    private $_messagebuffersize;
    
    /**
     * @var boolean
     * @access private
     */
    private $_usesockets = false;
    
    /**
     * @var integer
     * @access private
     */
    private $_receivedelay = 100;
    
    /**
     * @var integer
     * @access private
     */
    private $_senddelay = 250;
    
    /**
     * @var integer
     * @access private
     */
    private $_logdestination = SMARTIRC_STDOUT;
    
    /**
     * @var resource
     * @access private
     */
    private $_logfilefp = 0;
    
    /**
     * @var string
     * @access private
     */
    private $_logfile = 'Net_SmartIRC.log';
    
    /**
     * @var integer
     * @access private
     */
    private $_disconnecttime = 1000;
    
    /**
     * @var boolean
     * @access private
     */
    private $_loggedin = false;
    
    /**
     * @var boolean
     * @access private
     */
    private $_benchmark = false;
    
    /**
     * @var integer
     * @access private
     */
    private $_benchmark_starttime;
    
    /**
     * @var integer
     * @access private
     */
    private $_benchmark_stoptime;
    
    /**
     * @var integer
     * @access private
     */
    private $_actionhandlerid = 0;
    
    /**
     * @var integer
     * @access private
     */
    private $_timehandlerid = 0;
    
    /**
     * @var array
     * @access private
     */
    private $_motd = array();
    
    /**
     * @var array
     * @access private
     */
    private $_channels = array();
    
    /**
     * @var boolean
     * @access private
     */
    private $_channelsyncing = false;
    
    /**
     * @var array
     * @access private
     */
    private $_users = array();
    
    /**
     * @var boolean
     * @access private
     */
    private $_usersyncing = false;
    
    /**
     * Stores the path to the modules that can be loaded.
     *
     * @var string
     * @access private
     */
    private $_modulepath = '';
    
    /**
     * Stores all objects of the modules.
     *
     * @var string
     * @access private
     */
    private $_modules = array();
    
    /**
     * @var string
     * @access private
     */
    private $_ctcpversion = SMARTIRC_VERSIONSTRING;
    
    /**
     * @var mixed
     * @access private
     */
    private $_mintimer = false;
    
    /**
     * @var integer
     * @access private
     */
    private $_maxtimer = 300000;
    
    /**
     * @var integer
     * @access private
     */
    private $_txtimeout = 300;
    
    /**
     * @var integer
     * @access private
     */
    private $_rxtimeout = 300;
    
    /**
     * @var integer
     * @access private
     */
    private $_selecttimeout;
    
    /**
     * @var integer
     * @access private
     */
    private $_lastrx;
    
    /**
     * @var integer
     * @access private
     */
    private $_lasttx;
    
    /**
     * @var boolean
     * @access private
     */
    private $_autoreconnect = false;
    
    /**
     * @var integer
     * @access private
     */
    private $_reconnectdelay = 10000;

    /**
     * @var boolean
     * @access private
     */
    private $_autoretry = false;

    /**
     * @var integer
     * @access private
     */
    private $_autoretrymax = 5;

    /**
     * @var integer
     * @access private
     */
    private $_autoretrycount = 0;
    
    /**
     * @var boolean
     * @access private
     */
    private $_connectionerror = false;

    /**
     * @var boolean
     * @access private
     */
    private $_runasdaemon = false;
    

    /**
     * All IRC replycodes, the index is the replycode name.
     *
     * @see $SMARTIRC_replycodes
     * @var array
     * @access public
     */
    public $replycodes;
    
    /**
     * All numeric IRC replycodes, the index is the numeric replycode.
     *
     * @see $SMARTIRC_nreplycodes
     * @var array
     * @access public
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
     * @access public
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
     * @access public
     */
    public $user;
    
    /**
     * This is to prevent an E_NOTICE for example in getChannel() if null needs
     * to be returned.
     * 
     * @var null
     * @access public
     */
    const NULLGUARD = null;
    
    /**
     * Constructor. Initiates the messagebuffer and "links" the replycodes from
     * global into properties. Also some PHP runtime settings are configured.
     *
     * @access public
     * @return void
     */
    public function __construct($params = array())
    {
        ob_implicit_flush(true);
        @set_time_limit(0);
        
        $this->replycodes = &$GLOBALS['SMARTIRC_replycodes'];
        $this->nreplycodes = &$GLOBALS['SMARTIRC_nreplycodes'];
        
        // you'll want to pass an array that includes keys like:
        // Modulepath, Debug, UseSockets, ChannelSyncing, AutoRetry, RunAsDaemon
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
     * @access public
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
        );
        
        if (array_key_exists($method, $map)) {
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
     * Enables/disables the usage of real sockets.
     *
     * Enables/disables the usage of real sockets instead of fsocks
     * (works only if your PHP build has loaded the PHP socket extension)
     * Default: false
     *
     * @param bool $boolean
     * @return void
     * @access public
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
     * Sets an IP address (and optionally, a port) to bind the socket to.
     * 
     * Limits the bot to claiming only one of the machine's IPs as its home.
     * Only works with setUseSockets(TRUE). Call with no parameters to unbind.
     * 
     * @param string $addr
     * @return bool
     * @access public
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
     * @return void
     * @access public
     */
    public function setDebugLevel($level)
    {
        $this->_debuglevel = $level;
    }
    
    /**
     * Enables/disables the benchmark engine.
     * 
     * @param boolean $boolean
     * @return void
     * @access public
     */
    public function setBenchmark($boolean)
    {
        if (is_bool($boolean)) {
            $this->_benchmark = $boolean;
        } else {
            $this->_benchmark = false;
        }
    }
    
    /**
     * Enables/disables channel syncing.
     *
     * Channel syncing means, all users on all channel we are joined are tracked in the
     * channel array. This makes it very handy for botcoding.
     * 
     * @param boolean $boolean
     * @return void
     * @access public
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
    }

    /**
     * Enables/disables user syncing.
     *
     * User syncing means, all users we have or had contact with through channel, query or
     * notice are tracked in the $irc->user array. This is very handy for botcoding.
     *
     * @param boolean $boolean
     * @return void
     * @access public
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
    }
    
    /**
     * Sets the CTCP version reply string.
     * 
     * @param string $versionstring
     * @return void
     * @access public
     */
    public function setCtcpVersion($versionstring)
    {
        $this->_ctcpversion = $versionstring;
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
     * @return void
     * @access public
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
    }
    
    /**
     * Sets the file for the log if the destination is set to file.
     *
     * Sets the logfile, if {@link setLogDestination logdestination} is set to SMARTIRC_FILE.
     * This should be only used with full path!
     *
     * @param string $file 
     * @return void
     * @access public
     */
    public function setLogFile($file)
    {
        $this->_logfile = $file;
    }
    
    /**
     * Sets the delaytime before closing the socket when disconnect.
     *
     * @param integer $milliseconds
     * @return void
     * @access public
     */
    public function setDisconnectTime($milliseconds)
    {
        if (is_integer($milliseconds) && $milliseconds >= 100) {
            $this->_disconnecttime = $milliseconds;
        } else {
            $this->_disconnecttime = 100;
        }
    }
    
    /**
     * Sets the delaytime before attempting reconnect.
     * Value of 0 disables the delay entirely.
     *
     * @param integer $milliseconds
     * @return void
     * @access public
     */
    public function setReconnectDelay($milliseconds)
    {
        if (is_integer($milliseconds)) {
            $this->_reconnectdelay = $milliseconds;
        } else {
            $this->_reconnectdelay = 10000;
        }
    }

    /**
     * Sets the delay for receiving data from the IRC server.
     *
     * Sets the delaytime between messages that are received, this reduces your CPU load.
     * Don't set this too low (min 100ms).
     * Default: 100
     *
     * @param integer $milliseconds
     * @return void
     * @access public
     */
    public function setReceiveDelay($milliseconds)
    {
        if (is_integer($milliseconds) && $milliseconds >= 100) {
            $this->_receivedelay = $milliseconds;
        } else {
            $this->_receivedelay = 100;
        }
    }
    
    /**
     * Sets the delay for sending data to the IRC server.
     *
     * Sets the delaytime between messages that are sent, because IRC servers doesn't like floods.
     * This will avoid sending your messages too fast to the IRC server.
     * Default: 250
     *
     * @param integer $milliseconds
     * @return void
     * @access public
     */
    public function setSendDelay($milliseconds) {
        if (is_integer($milliseconds)) {
            $this->_senddelay = $milliseconds;
        } else {
            $this->_senddelay = 250;
        }
    }
    
    /**
     * Enables/disables autoreconnecting.
     * 
     * @param boolean $boolean
     * @return void
     * @access public
     */
    public function setAutoReconnect($boolean)
    {
        if ($boolean) {
            $this->_autoreconnect = true;
        } else {
            $this->_autoreconnect = false;
        }
    }
    
    /**
     * Enables/disables autoretry for connecting to a server.
     * 
     * @param boolean $boolean
     * @return void
     * @access public
     */
    public function setAutoRetry($boolean)
    {
        if ($boolean) {
            $this->_autoretry = true;
        } else {
            $this->_autoretry = false;
        }
    }

    /**
     * Sets the maximum number of attempts to connect to a server
     * before giving up.
     *
     * @param integer $autoretrymax
     * @return void
     * @access public
     */
    public function setAutoRetryMax($autoretrymax)
    {
        if (is_integer($autoretrymax)) {
            $this->_autoretrymax = $autoretrymax;
        } else {
            $this->_autoretrymax = 5;
        }
    }

    /**
     * Sets the receive timeout.
     *
     * If the timeout occurs, the connection will be reinitialized
     * Default: 300 seconds
     *
     * @param integer $seconds
     * @return void
     * @access public
     */
    public function setReceiveTimeout($seconds)
    {
        if (is_integer($seconds)) {
            $this->_rxtimeout = $seconds;
        } else {
            $this->_rxtimeout = 300;
        }
    }
    
    /**
     * Sets the transmit timeout.
     *
     * If the timeout occurs, the connection will be reinitialized
     * Default: 300 seconds
     *
     * @param integer $seconds
     * @return void
     * @access public
     */
    public function setTransmitTimeout($seconds)
    {
        if (is_integer($seconds)) {
            $this->_txtimeout = $seconds;
        } else {
            $this->_txtimeout = 300;
        }
    }
    
    /**
     * Sets the paths for the modules.
     *
     * @param integer $path
     * @return void
     * @access public
     */
    public function setModulePath($path)
    {
        $this->_modulepath = $path;
    }

    /**
     * Sets whether the script should be run as a daemon or not
     * ( actually disables/enables ignore_user_abort() )
     *
     * @param boolean $boolean
     * @return void
     * @access public
     */
    public function setRunAsDaemon($boolean)
    {
        if ($boolean) {
            $this->_runasdaemon = true;
            ignore_user_abort(true);
        } else {
            $this->_runasdaemon = false;
        }
    }
    
    /**
     * Starts the benchmark (sets the counters).
     *
     * @return void
     * @access public
     */
    public function startBenchmark()
    {
        $this->_benchmark_starttime = $this->_microint();
        $this->log(SMARTIRC_DEBUG_NOTICE, 'benchmark started', __FILE__, __LINE__);
    }
    
    /**
     * Stops the benchmark and displays the result.
     *
     * @return void
     * @access public
     */
    public function stopBenchmark()
    {
        $this->_benchmark_stoptime = $this->_microint();
        $this->log(SMARTIRC_DEBUG_NOTICE, 'benchmark stopped', __FILE__, __LINE__);
        
        if ($this->_benchmark) {
            $this->showBenchmark();
        }
    }
    
    /**
     * Shows the benchmark result.
     *
     * @return void
     * @access public
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
     * @access public
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
                if (version_compare(PHP_VERSION, '5.3.0', '<')) {
                    define_syslog_variables();
                }
                
                if (!is_int($this->_logfilefp)) {
                    $this->_logfilefp = openlog('Net_SmartIRC', LOG_NDELAY,
                        LOG_DAEMON
                    );
                }
                
                syslog(LOG_INFO, $entry);
        }
    }
    
    /**
     * Returns the full motd.
     *
     * @return array
     * @access public
     */
    public function getMotd()
    {
        return $this->_motd;
    }
    
    /**
     * Returns the usermode.
     *
     * @return string
     * @access public
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
     * @access public
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
        
        if ($this->isJoined($channelname)) {
            return $this->_channels[strtolower($channelname)];
        } else {
            return self::NULLGUARD;
        }
    }
    
    /**
     * Returns a reference to the user object for the specified username and channelname.
     *
     * @param string $channelname
     * @param string $username
     * @return object
     * @access public
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
            return $this->_channels[strtolower($channelname)]
                ->users[strtolower($username)];
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
     * @access public
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
            // TODO! needs to be return value
            $this->throwError($error_msg);
            
            if ($this->_autoretry
                && $this->_autoretrycount < $this->_autoretrymax
            ) {
                 $this->_delayReconnect();
                 $this->_autoretrycount++;
                 $this->reconnect();
            }
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
     * @access public
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
     * @return void
     * @access public
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
        $this->connect($this->_address, $this->_port);
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
     * @access public
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
     * @access public
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
     * @access public
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
     * @access public
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
     * @access public
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
        
        if ($nickname === null) {
            $nickname = $this->_nick;
        }
        
        return isset($this->_channels[strtolower($channel)]
            ->users[strtolower($nickname)]
        );
    }
    
    /**
     * Checks if we or the given user is founder on the specified channel and returns the result.
     * ChannelSyncing is required for this.
     *
     * @see setChannelSyncing
     * @param string $channel
     * @param string $nickname
     * @return boolean
     * @access public
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
            && $this->_channels[strtolower($channel)]
                ->users[strtolower($nickname)]->founder
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
     * @access public
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
            && $this->_channels[strtolower($channel)]
                ->users[strtolower($nickname)]->admin
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
     * @access public
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
            && $this->_channels[strtolower($channel)]
                ->users[strtolower($nickname)]->op
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
     * @access public
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
            && $this->_channels[strtolower($channel)]
                ->users[strtolower($nickname)]->hop
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
     * @access public
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
            && $this->_channels[strtolower($channel)]
                ->users[strtolower($nickname)]->voice
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
     * @access public
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
            && array_search($hostmask,
                $this->_channels[strtolower($channel)]->bans
            ) !== false
        );
    }
    
    /**
     * goes into receive mode
     *
     * Goes into receive and idle mode. Only call this if you want to "spawn" the bot.
     * No further lines of PHP code will be processed after this call, only the bot methods!
     *
     * @return boolean
     * @access public
     */
    public function listen()
    {
        while ($this->_updatestate() == SMARTIRC_STATE_CONNECTED) {
            $this->listenOnce();
        }
            
        return false;
    }
    
    /**
     * goes into receive mode _only_ for one pass
     *
     * Goes into receive mode. It will return when one pass is complete.
     * Use this when you want to connect to multiple IRC servers.
     *
     * @return boolean
     * @access public
     */
    public function listenOnce()
    {
        // TODO: inspect this function to make sure it works right
        if ($this->_updatestate() != SMARTIRC_STATE_CONNECTED) {
            return false;
        }
        
        $this->_rawreceive();
        if ($this->_connectionerror) {
            if ($this->_autoreconnect) {
                $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: connection error detected, will reconnect!', __FILE__, __LINE__);
                $this->reconnect();
            } else {
                $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: connection error detected, will disconnect!', __FILE__, __LINE__);
                $this->disconnect();
            }
        }
        return true;
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
     * @access public
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
     * @access public
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
     * @access public
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
     * @access public
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
     * @access public
     */
    public function registerTimeHandler($interval, &$object, $methodname)
    {
        $id = $this->_timehandlerid++;
        $newtimehandler = new Net_SmartIRC_timehandler();
        
        $newtimehandler->id = $id;
        $newtimehandler->interval = $interval;
        $newtimehandler->object = &$object;
        $newtimehandler->method = $methodname;
        $newtimehandler->lastmicrotimestamp = $this->_microint();
        
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
     * @access public
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
                $this->_updatemintimer();
                return true;
            }
        }
        
        $this->log(SMARTIRC_DEBUG_TIMEHANDLER, 'DEBUG_TIMEHANDLER: could not '
            ."find timehandler id: $id _not_ unregistered",
            __FILE__, __LINE__
        );
        return false;
    }
    
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
                ."module \"$filename\" file doesn't exist", __FILE__, __LINE__
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
        } else {
            // we're using new style AND we have args to pass to the constructor
            if (func_num_args() == 2) {
                // only one arg, so pass it as is
                $module = new $classname($this, func_get_arg(1));
            } else {
                // multiple args, so pass them in an array
                $module = new $classname($this, array_slice(func_get_args(), 1));
            }
        }
        
        $this->_modules[$name] = &$module;
        
        $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: successfully loaded'
            ." module: $name", __FILE__, __LINE__
        );
        return true;
    }
    
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
    
    // <private methods>
    /**
     * checks the buffer if there are messages to send
     *
     * @return boolean
     * @access private
     */
    private function _checkbuffer()
    {
        if (!$this->_loggedin) {
            return false;
        }
        
        static $highsent = 0;
        static $lastmicrotimestamp = 0;
        
        if ($lastmicrotimestamp == 0) {
            $lastmicrotimestamp = $this->_microint();
        }
        
        $highcount = count($this->_messagebuffer[SMARTIRC_HIGH]);
        $mediumcount = count($this->_messagebuffer[SMARTIRC_MEDIUM]);
        $lowcount = count($this->_messagebuffer[SMARTIRC_LOW]);
        $this->_messagebuffersize = $highcount+$mediumcount+$lowcount;
        
        // don't send them too fast
        if ($this->_microint() >= ($lastmicrotimestamp+($this->_senddelay/1000))) {
            $result = null;
            if ($highcount > 0 && $highsent <= 2) {
                $this->_rawsend(array_shift($this->_messagebuffer[SMARTIRC_HIGH]));
                $lastmicrotimestamp = $this->_microint();
                $highsent++;
            } else if ($mediumcount > 0) {
                $this->_rawsend(array_shift($this->_messagebuffer[SMARTIRC_MEDIUM]));
                $lastmicrotimestamp = $this->_microint();
                $highsent = 0;
            } else if ($lowcount > 0) {
                $this->_rawsend(array_shift($this->_messagebuffer[SMARTIRC_LOW]));
                $lastmicrotimestamp = $this->_microint();
            }
        }
        
        return true;
    }
    
    /**
     * Checks the running timers and calls the registered timehandler,
     * when the interval is reached.
     *
     * @return boolean
     * @access private
     */
    private function _checktimer()
    {
        if (!$this->_loggedin) {
            return false;
        }
        
        $handlercount = count($this->_timehandler);
        for ($i = 0; $i < $handlercount; $i++) {
            $handlerobject = &$this->_timehandler[$i];
            $microtimestamp = $this->_microint();
            if ($microtimestamp >= $handlerobject->lastmicrotimestamp
                + ($handlerobject->interval/1000)
            ) {
                $methodobject = &$handlerobject->object;
                $method = $handlerobject->method;
                $handlerobject->lastmicrotimestamp = $microtimestamp;
                
                if (@method_exists($methodobject, $method)) {
                    $this->log(SMARTIRC_DEBUG_TIMEHANDLER, 'DEBUG_TIMEHANDLER: '
                        .'calling method "'.get_class($methodobject).'->'
                        .$method.'"', __FILE__, __LINE__
                    );
                    $methodobject->$method($this);
                }
            }
        }
        
        return true;
    }
    
    /**
     * Checks if a receive or transmit timeout occured and reconnects if configured
     *
     * @return void
     * @access private
     */
    private function _checktimeout()
    {
        if ($this->_autoreconnect) {
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
        }
    }
    
    /**
     * changes an already used nickname to a new nickname plus 3 random digits
     *
     * @return void
     * @access private
     */
    private function _nicknameinuse()
    {
        $newnickname = substr($this->_nick, 0, 5) . rand(0, 999);
        $this->changeNick($newnickname, SMARTIRC_CRITICAL);
    }
    
    /**
     * sends the pong for keeping alive
     *
     * Sends the PONG signal as reply of the PING from the IRC server.
     *
     * @param string $data
     * @return void
     * @access private
     */
    private function _pong($data)
    {
        $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: Ping? Pong!',
            __FILE__, __LINE__
        );
        $this->send('PONG '.$data, SMARTIRC_CRITICAL);
    }
    
    /**
     * sends a raw message to the IRC server
     *
     * Don't use this directly! Use message() or send() instead.
     *
     * @param string $data
     * @return boolean
     * @access private
     */
    private function _rawsend($data)
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
     * goes into main receive mode _once_ per call and waits for messages from the IRC server
     *
     * @return void
     * @access private
     */
    private function _rawreceive()
    {
        $lastpart = '';
        $rawdataar = array();
        
        $this->_checkbuffer();
        
        if ($this->_usesockets) {
            // this will trigger a warning when catching a signal
            $result = @socket_select(array($this->_socket), $w = null,
                $e = null, 0, $this->_selecttimeout() * 1000
            );
            
            if ($result == 1) {
                // the socket got data to read
                $rawdata = socket_read($this->_socket, 1024);
            } else if ($result === false) {
                if (socket_last_error() == 4) {
                    // we got hit with a SIGHUP signal
                    $rawdata = null;
                    global $bot;
                    
                    if (is_callable(array($bot, 'reload'))) {
                        $bot->reload();
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
            } else {
                // no data
                $rawdata = null;
            }
        } else {
            usleep($this->_receivedelay*1000);
            $rawdata = fread($this->_socket, 1024);
        }
        
        if ($rawdata === false) {
            // reading from the socket failed, the connection is broken
            $this->_connectionerror = true;
        }
        
        $this->_checktimer();
        $this->_checktimeout();
        
        if ($rawdata !== null && !empty($rawdata)) {
            $this->_lastrx = time();
            $rawdata = str_replace("\r", '', $rawdata);
            $rawdata = $lastpart.$rawdata;
            
            $lastpart = substr($rawdata, strrpos($rawdata ,"\n")+1);
            $rawdata = substr($rawdata, 0, strrpos($rawdata ,"\n"));
            $rawdataar = explode("\n", $rawdata);
        }
        
        // loop through our received messages
        while (count($rawdataar) > 0) {
            $rawline = array_shift($rawdataar);
            $validmessage = false;
            
            $this->log(SMARTIRC_DEBUG_IRCMESSAGES, 'DEBUG_IRCMESSAGES: '
                ."received: \"$rawline\"", __FILE__, __LINE__
            );
            
            // building our data packet
            $ircdata = new Net_SmartIRC_data();
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
                $colonpos = strpos($line, ' :');
                $ircdata->nick = substr($from, 0, $exclamationpos);
                $ircdata->ident = substr($from, $exclamationpos+1, 
                    $atpos-$exclamationpos-1
                );
                $ircdata->host = substr($from, $atpos+1);
                $ircdata->type = $this->_gettype($rawline);
                $ircdata->from = $from;
                if ($colonpos !== false) {
                    $ircdata->message = substr($line, $colonpos+2);
                    $ircdata->messageex = explode(' ', $ircdata->message);
                }
                
                if ($ircdata->type
                    & (SMARTIRC_TYPE_CHANNEL
                        | SMARTIRC_TYPE_ACTION
                        | SMARTIRC_TYPE_MODECHANGE
                        | SMARTIRC_TYPE_TOPICCHANGE
                        | SMARTIRC_TYPE_KICK
                        | SMARTIRC_TYPE_PART
                        | SMARTIRC_TYPE_JOIN
                    )
                ) {
                    $ircdata->channel = $lineex[2];
                } else if ($ircdata->type
                    & (SMARTIRC_TYPE_WHO
                        | SMARTIRC_TYPE_BANLIST
                        | SMARTIRC_TYPE_TOPIC
                        | SMARTIRC_TYPE_CHANNELMODE
                    )
                ) {
                    $ircdata->channel = $lineex[3];
                } else if ($ircdata->type & SMARTIRC_TYPE_NAME) {
                    $ircdata->channel = $lineex[4];
                }
                
                if ($ircdata->channel !== null
                    && substr($ircdata->channel, 0, 1) == ':'
                ) {
                    $ircdata->channel = substr($ircdata->channel, 1);
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
            }
            
            // lets see if we have a messagehandler for it
            $this->_handlemessage($messagecode, $ircdata);
            
            if ($validmessage) {
                // now the actionhandlers are coming
                $this->_handleactionhandler($ircdata);
            }
            
            unset($ircdata);
        }
    }
    
    /**
     * returns the calculated selecttimeout value
     *
     * @return integer selecttimeout in microseconds
     * @access private
     */
    private function _selecttimeout()
    {
        if ($this->_messagebuffersize != 0) {
            return $this->_senddelay;
        }
        
        $compare = array($this->_maxtimer);
        
        if ($this->_mintimer) {
            $compare[] = $this->_mintimer;
        }
        
        if ($this->_autoreconnect) {
            $compare[] = $this->_rxtimeout*1000;
        }
        
        $this->_selecttimeout = min($compare);
        return $this->_selecttimeout;
    }
    
    /**
     * updates _mintimer to the smallest timer interval
     *
     * @return void
     * @access private
     */
    private function _updatemintimer()
    {
        $timerarray = array();
        foreach ($this->_timehandler as $values) {
            $timerarray[] = $values->interval;
        }
        
        $result = array_multisort($timerarray, SORT_NUMERIC, SORT_ASC);
        if ($result && isset($timerarray[0])) {
            $this->_mintimer = $timerarray[0];
        } else {
            $this->_mintimer = false;
        }
    }

    /**
     * determines the messagetype of $line
     *
     * Analyses the type of an IRC message and returns the type.
     *
     * @param string $line
     * @return integer SMARTIRC_TYPE_* constant
     * @access private
     */
    private function _gettype($line)
    {
        if (preg_match('/^:[^ ]+? [0-9]{3} .+$/', $line)) {
            $lineex = explode(' ', $line);
            $code = $lineex[1];
                
            switch ($code) {
                case SMARTIRC_RPL_WELCOME:
                case SMARTIRC_RPL_YOURHOST:
                case SMARTIRC_RPL_CREATED:
                case SMARTIRC_RPL_MYINFO:
                case SMARTIRC_RPL_BOUNCE:
                    return SMARTIRC_TYPE_LOGIN;
                
                case SMARTIRC_RPL_LUSERCLIENT:
                case SMARTIRC_RPL_LUSEROP:
                case SMARTIRC_RPL_LUSERUNKNOWN:
                case SMARTIRC_RPL_LUSERME:
                case SMARTIRC_RPL_LUSERCHANNELS:
                    return SMARTIRC_TYPE_INFO;
                
                case SMARTIRC_RPL_MOTDSTART:
                case SMARTIRC_RPL_MOTD:
                case SMARTIRC_RPL_ENDOFMOTD:
                    return SMARTIRC_TYPE_MOTD;
                
                case SMARTIRC_RPL_NAMREPLY:
                case SMARTIRC_RPL_ENDOFNAMES:
                    return SMARTIRC_TYPE_NAME;
                
                case SMARTIRC_RPL_WHOREPLY:
                case SMARTIRC_RPL_ENDOFWHO:
                    return SMARTIRC_TYPE_WHO;
                
                case SMARTIRC_RPL_LISTSTART:
                    return SMARTIRC_TYPE_NONRELEVANT;
                
                case SMARTIRC_RPL_LIST:
                case SMARTIRC_RPL_LISTEND:
                    return SMARTIRC_TYPE_LIST;
                
                case SMARTIRC_RPL_BANLIST:
                case SMARTIRC_RPL_ENDOFBANLIST:
                    return SMARTIRC_TYPE_BANLIST;
                
                case SMARTIRC_RPL_TOPIC:
                    return SMARTIRC_TYPE_TOPIC;
                
                case SMARTIRC_RPL_WHOISUSER:
                case SMARTIRC_RPL_WHOISSERVER:
                case SMARTIRC_RPL_WHOISOPERATOR:
                case SMARTIRC_RPL_WHOISIDLE:
                case SMARTIRC_RPL_ENDOFWHOIS:
                case SMARTIRC_RPL_WHOISCHANNELS:
                    return SMARTIRC_TYPE_WHOIS;
                
                case SMARTIRC_RPL_WHOWASUSER:
                case SMARTIRC_RPL_ENDOFWHOWAS:
                    return SMARTIRC_TYPE_WHOWAS;
                
                case SMARTIRC_RPL_UMODEIS:
                    return SMARTIRC_TYPE_USERMODE;
                
                case SMARTIRC_RPL_CHANNELMODEIS:
                    return SMARTIRC_TYPE_CHANNELMODE;
                
                case SMARTIRC_ERR_NICKNAMEINUSE:
                case SMARTIRC_ERR_NOTREGISTERED:
                    return SMARTIRC_TYPE_ERROR;
                
                default:
                    $this->log(SMARTIRC_DEBUG_IRCMESSAGES, 'DEBUG_IRCMESSAGES: '
                        ."replycode UNKNOWN ($code): \"$line\"",
                        __FILE__, __LINE__
                    );
                    return SMARTIRC_TYPE_UNKNOWN;
            }
        }
        
        if (preg_match('/^:.*? PRIVMSG .* :'.chr(1).'ACTION .*'.chr(1).'$/',
                $line
            )
        ) {
            return SMARTIRC_TYPE_ACTION;
        } else if (preg_match('/^:.*? PRIVMSG .* :'.chr(1).'.*'.chr(1).'$/',
                $line
            )
        ) {
            return (SMARTIRC_TYPE_CTCP_REQUEST | SMARTIRC_TYPE_CTCP);
        } else if (preg_match('/^:.*? NOTICE .* :'.chr(1).'.*'.chr(1).'$/',
                $line
            )
        ) {
            return (SMARTIRC_TYPE_CTCP_REPLY | SMARTIRC_TYPE_CTCP);
        } else if (preg_match('/^:.*? PRIVMSG (\&|\#|\+|\!).* :.*$/', $line)) {
            return SMARTIRC_TYPE_CHANNEL;
        } else if (preg_match('/^:.*? PRIVMSG .*:.*$/', $line)) {
            return SMARTIRC_TYPE_QUERY;
        } else if (preg_match('/^:.*? NOTICE .* :.*$/', $line)) {
            return SMARTIRC_TYPE_NOTICE;
        } else if (preg_match('/^:.*? INVITE .* .*$/', $line)) {
            return SMARTIRC_TYPE_INVITE;
        } else if (preg_match('/^:.*? JOIN .*$/', $line)) {
            return SMARTIRC_TYPE_JOIN;
        } else if (preg_match('/^:.*? TOPIC .* :.*$/', $line)) {
            return SMARTIRC_TYPE_TOPICCHANGE;
        } else if (preg_match('/^:.*? NICK .*$/', $line)) {
            return SMARTIRC_TYPE_NICKCHANGE;
        } else if (preg_match('/^:.*? KICK .* .*$/', $line)) {
            return SMARTIRC_TYPE_KICK;
        } else if (preg_match('/^:.*? PART .*$/', $line)) {
            return SMARTIRC_TYPE_PART;
        } else if (preg_match('/^:.*? MODE .* .*$/', $line)) {
            return SMARTIRC_TYPE_MODECHANGE;
        } else if (preg_match('/^:.*? QUIT :.*$/', $line)) {
            return SMARTIRC_TYPE_QUIT;
        } else {
            $this->log(SMARTIRC_DEBUG_MESSAGETYPES, 'DEBUG_MESSAGETYPES: '
                ."SMARTIRC_TYPE_UNKNOWN!: \"$line\"", __FILE__, __LINE__
            );
            return SMARTIRC_TYPE_UNKNOWN;
        }
    }
    
    /**
     * updates and returns the current connection state
     *
     * @return boolean
     * @access private
     */
    private function _updatestate()
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
    
    /**
     * tries to find a messagehandler for the received message ($ircdata) and calls it
     *
     * @param string $messagecode
     * @param object $ircdata
     * @return void
     * @access private
     */
    private function _handlemessage($messagecode, &$ircdata)
    {
        $found = false;
        
        if (is_numeric($messagecode)) {
            if (!array_key_exists($messagecode, $this->nreplycodes)) {
                $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER,
                    'DEBUG_MESSAGEHANDLER: ignoring unrecognized messagecode! "'
                    .$messagecode.'"', __FILE__, __LINE__
                );
                $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER,
                    'DEBUG_MESSAGEHANDLER: this IRC server ('.$this->_address
                    .") doesn't conform to RFC 2812!",
                    __FILE__, __LINE__
                );
                return false;
            }
            
            $methodname = 'event_'.strtolower($this->nreplycodes[$messagecode]);
            $_methodname = '_'.$methodname;
            $_codetype = 'by numeric';
        } else if (is_string($messagecode)) {
            $methodname = 'event_'.strtolower($messagecode);
            $_methodname = '_'.$methodname;
            $_codetype = 'by string';
        }
        
        // if exists call internal method for the handling
        if (@method_exists($this, $_methodname)) {
            $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER, 'DEBUG_MESSAGEHANDLER: '
                .'calling internal method "'.get_class($this).'->'.$_methodname
                .'" ('.$_codetype.')', __FILE__, __LINE__
            );
            $this->$_methodname($ircdata);
            $found = true;
        }
        
        // if exists call user defined method for the handling
        if (@method_exists($this, $methodname)) {
            $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER, 'DEBUG_MESSAGEHANDLER: '
                .'calling user defined method "'.get_class($this).'->'
                .$methodname.'" ('.$_codetype.')', __FILE__, __LINE__
            );
            $this->$methodname($ircdata);
            $found = true;
        }
        
        if (!$found) {
            $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER, 'DEBUG_MESSAGEHANDLER: no'
                .' method found for "'.$messagecode.'" ('.$methodname.')',
                __FILE__, __LINE__
            );
        }
        
        return $found;
    }
    
    /**
     * tries to find a actionhandler for the received message ($ircdata) and calls it
     *
     * @param object $ircdata
     * @return void
     * @access private
     */
    private function _handleactionhandler(&$ircdata)
    {
        $handler = &$this->_actionhandler;
        $handlercount = count($handler);
        for ($i = 0; $i < $handlercount; $i++) {
            $handlerobject = &$handler[$i];
            
            if (substr($handlerobject->message, 0, 1) == '/') {
                $regex = $handlerobject->message;
            } else {
                $regex = '/'.$handlerobject->message.'/';
            }
            
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
                
                if (@method_exists($methodobject, $method)) {
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
    }

    /**
     * Delay reconnect
     *
     * @return void
     * @access private
     */
    private function _delayReconnect()
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
     * getting current microtime, needed for benchmarks
     *
     * @return float
     * @access private
     */
    private function _microint()
    {
        $parts = explode(' ', microtime());
        return ((float)$parts[0] + (float)$parts[1]);
    }
    
    /**
     * adds an user to the channelobject or updates his info
     *
     * @param object $channel
     * @param object $newuser
     * @return void
     * @access private
     */
    private function _adduser(&$channel, &$newuser)
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
     * removes an user from one channel or all if he quits
     *
     * @param object $ircdata
     * @return void
     * @access private
     */
    private function _removeuser(&$ircdata)
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
                    $channel = &$this->_channels[$channelkey];
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
                $channel = &$this->_channels[strtolower($ircdata->channel)];
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
    
    // </private methods>
    
    function isError($object)
    {
        return (is_object($object)
            && strtolower(get_class($object)) == 'net_smartirc_error'
        );
    }
    
    function &throwError($message)
    {
        return new Net_SmartIRC_Error($message);
    }
}

// includes must be after the base class definition, required for PHP5
require_once 'Net/SmartIRC/irccommands.php';
require_once 'Net/SmartIRC/messagehandler.php';

class Net_SmartIRC extends Net_SmartIRC_messagehandler
{
    // empty
}

/**
 * @access public
 */
class Net_SmartIRC_data
{
    /**
     * @var string
     * @access public
     */
    public $from;
    
    /**
     * @var string
     * @access public
     */
    public $nick;
    
    /**
     * @var string
     * @access public
     */
    public $ident;
    
    /**
     * @var string
     * @access public
     */
    public $host;
    
    /**
     * @var string
     * @access public
     */
    public $channel;
    
    /**
     * @var string
     * @access public
     */
    public $message;
    
    /**
     * @var array
     * @access public
     */
    public $messageex = array();
    
    /**
     * @var integer
     * @access public
     */
    public $type;
    
    /**
     * @var string
     * @access public
     */
    public $rawmessage;
    
    /**
     * @var array
     * @access public
     */
    public $rawmessageex = array();
}

/**
 * @access public
 */
class Net_SmartIRC_actionhandler
{
    /**
     * @var integer
     * @access public
     */
    public $id;
    
    /**
     * @var integer
     * @access public
     */
    public $type;
    
    /**
     * @var string
     * @access public
     */
    public $message;
    
    /**
     * @var object
     * @access public
     */
    public $object;
    
    /**
     * @var string
     * @access public
     */
    public $method;
}

/**
 * @access public
 */
class Net_SmartIRC_timehandler
{
    /**
     * @var integer
     * @access public
     */
    public $id;
    
    /**
     * @var integer
     * @access public
     */
    public $interval;
    
    /**
     * @var integer
     * @access public
     */
    public $lastmicrotimestamp;
    
    /**
     * @var object
     * @access public
     */
    public $object;
    
    /**
     * @var string
     * @access public
     */
    public $method;
}

/**
 * @access public
 */
class Net_SmartIRC_channel
{
    /**
     * @var string
     * @access public
     */
    public $name;
    
    /**
     * @var string
     * @access public
     */
    public $key;
    
    /**
     * @var array
     * @access public
     */
    public $users = array();
    
    /**
     * @var array
     * @access public
     */
    public $founders = array();
    
    /**
     * @var array
     * @access public
     */
    public $admins = array();
    
    /**
     * @var array
     * @access public
     */
    public $ops = array();
    
    /**
     * @var array
     * @access public
     */
    public $hops = array();
    
    /**
     * @var array
     * @access public
     */
    public $voices = array();
    
    /**
     * @var array
     * @access public
     */
    public $bans = array();
    
    /**
     * @var string
     * @access public
     */
    public $topic;
    
    /**
     * @var string
     * @access public
     */
    public $user_limit = false;
    
    /**
     * @var string
     * @access public
     */
    public $mode;
    
    /**
     * @var integer
     * @access public
     */
    public $synctime_start = 0;
    
    /**
     * @var integer
     * @access public
     */
    public $synctime_stop = 0;
    
    /**
     * @var integer
     * @access public
     */
    public $synctime;
}

/**
 * @access public
 */
class Net_SmartIRC_user
{
    /**
     * @var string
     * @access public
     */
    public $nick;
    
    /**
     * @var string
     * @access public
     */
    public $ident;
    
    /**
     * @var string
     * @access public
     */
    public $host;
    
    /**
     * @var string
     * @access public
     */
    public $realname;
    
    /**
     * @var boolean
     * @access public
     */
    public $ircop;
    
    /**
     * @var boolean
     * @access public
     */
    public $away;
    
    /**
     * @var string
     * @access public
     */
    public $server;
    
    /**
     * @var integer
     * @access public
     */
    public $hopcount;
}

/**
 * @access public
 */
class Net_SmartIRC_channeluser extends Net_SmartIRC_user
{
    /**
     * @var boolean
     * @access public
     */
    public $founder;

    /**
     * @var boolean
     * @access public
     */
    public $admin;
    
    /**
     * @var boolean
     * @access public
     */
    public $op;
    
    /**
     * @var boolean
     * @access public
     */
    public $hop;
    
    /**
     * @var boolean
     * @access public
     */
    public $voice;
}

/**
 * @access public
 */
class Net_SmartIRC_ircuser extends Net_SmartIRC_user
{
    /**
     * @var array
     * @access public
     */
    public $joinedchannels = array();
}

/**
 * @access public
 */
class Net_SmartIRC_listenfor
{
    /**
     * @var array
     * @access public
     */
    public $result = array();
    
    /**
     * stores the received answer into the result array
     *
     * @param object $irc
     * @param object $ircdata
     * @return void
     */
    function handler(&$irc, &$ircdata)
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
?>
