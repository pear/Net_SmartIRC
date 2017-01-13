<?php
/**
 * Net_SmartIRC
 * This is a PHP class for communication with IRC networks,
 * which conforms to the RFC 2812 (IRC protocol).
 * It's an API that handles all IRC protocol messages.
 * This class is designed for creating IRC bots, chats and showing IRC related
 * info on webpages.
 *
 * Documentation, a HOWTO, and examples are included in SmartIRC.
 *
 * Here you will find a service bot which I am also developing
 * <http://cvs.meebey.net/atbs> and <http://cvs.meebey.net/phpbitch>
 * Latest versions of Net_SmartIRC you will find on the project homepage
 * or get it through PEAR since SmartIRC is an official PEAR package.
 *
 * Official Project Homepage: <http://pear.php.net/package/Net_SmartIRC/>
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
define('SMARTIRC_VERSION', '1.1.10');
define('SMARTIRC_VERSIONSTRING', 'Net_SmartIRC '.SMARTIRC_VERSION);

/**
 * main SmartIRC class
 *
 * @category Net
 * @package Net_SmartIRC
 * @version 1.1.10
 * @author clockwerx
 * @author Mirco 'meebey' Bauer <meebey@meebey.net>
 * @author garrettw
 * @license http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link http://pear.php.net/package/Net_SmartIRC
 */
class Net_SmartIRC extends Net_SmartIRC_messagehandler
{
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
     * @var string
     */
    const IP_PATTERN = '/(?:(?:(?:[0-9]{1,3}\.){3}[0-9]{1,3})|(?:\[[0-9A-Fa-f:]+\])|(?:[a-zA-Z0-9-_.]+)):[0-9]{1,5}/';

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
    protected $_bindto = '0:0';

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
     * Stores all channels in this array where we are joined, works only if channelsyncing is activated.
     * Eg. for accessing a user, use it like this: (in this example the SmartIRC object is stored in $irc)
     * $irc->getUser('#test', 'meebey')->nick;
     *
     * @see setChannelSyncing()
     * @see Net_SmartIRC_channel
     * @see Net_SmartIRC_channeluser
     * @var array
     */
    protected $_channels = array();

    /**
     * @var boolean
     */
    protected $_channelsyncing = false;

    /**
     * Stores all users that had/have contact with us (channel/query/notice etc.), works only if usersyncing is activated.
     * Eg. for accessing a user, use it like this: (in this example the SmartIRC object is stored in $irc)
     * $irc->user['meebey']->host;
     *
     * @see setUserSyncing()
     * @see Net_SmartIRC_ircuser
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
     *
     */
    protected $_lastsentmsgtime = 0;

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
     * Constructor. Initiates the messagebuffer and "links" the replycodes from
     * global into properties. Also some PHP runtime settings are configured.
     *
     * @api
     * @param array $params Properties to set during instantiation
     * @return object
     */
    public function __construct($params = array())
    {
        // can't stop using the global without potentially breaking BC
        $this->nreplycodes = &$GLOBALS['SMARTIRC_nreplycodes'];

        if (isset($_SERVER['REQUEST_METHOD'])) {
            // the script is called from a browser, lets set default log destination
            // to SMARTIRC_BROWSEROUT (makes browser friendly output)
            $this->setLogDestination(SMARTIRC_BROWSEROUT);
        }

        // you'll want to pass an array that includes keys like:
        // ModulePath, Debug, ChannelSyncing, AutoRetry, RunAsDaemon
        // so we can call their setters here
        foreach ($params as $varname => $val) {
            $funcname = 'set' . $varname;
            $this->$funcname($val);
        }
    }

    /**
     * Keeps BC since private properties were once publicly accessible.
     *
     * @param string $name The property name asked for
     * @return mixed the property's value
     */
    public function __get($name)
    {
        // PHP allows $this->getChannel($param)->memberofobject,
        // but we need to not break BC.
        if ($name == 'channel' || $name == 'user'):
            $name = '_' . $name . 's';
        endif;
        return $this->$name;
    }

    /**
     * Handle calls to renamed or deprecated functions
     *
     * @param string $method
     * @param array $args
     * @return mixed|void
     */
    public function __call($method, $args)
    {
        $map = array(
            'setChannelSynching'      => 'setChannelSyncing',
            'setDebug'                => 'setDebugLevel',
            'channel'                 => 'getChannel',
            '_nicknameinuse'          => '_event_err_nicknameinuse',
            'setAutoReconnect'        => '',
            'setUseSockets'           => '',
        );

        if (array_key_exists($method, $map)) {
            if (empty($map[$method])) {
                $this->log(SMARTIRC_DEBUG_NOTICE,
                    "WARNING: you are using $method() which is deprecated "
                    ."and no longer functional.",
                    __FILE__, __LINE__);
            } else {
                $this->log(SMARTIRC_DEBUG_NOTICE,
                    "WARNING: you are using $method() which is a deprecated "
                    ."method, using {$map[$method]}() instead!", __FILE__, __LINE__);
                return call_user_func_array(array($this, $map[$method]), $args);
            }
        } else {
            $this->log(SMARTIRC_DEBUG_NOTICE,
                "WARNING: $method() does not exist!", __FILE__, __LINE__);
        }
    }

    /**
     * Enables/disables autoretry for connecting to a server.
     *
     * @api
     * @param boolean $boolean
     * @return boolean
     */
    public function setAutoRetry($boolean)
    {
        return ($this->_autoretry = (bool) $boolean);
    }

    /**
     * Sets the maximum number of attempts to connect to a server
     * before giving up.
     *
     * @api
     * @param integer|null $autoretrymax
     * @return integer
     */
    public function setAutoRetryMax($autoretrymax = null)
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
     * @api
     * @param boolean $boolean
     * @return boolean
     */
    public function setBenchmark($boolean)
    {
        return ($this->_benchmark = (bool) $boolean);
    }

    /**
     * Sets an IP address (and optionally, a port) to bind the socket to.
     *
     * Limits the bot to claiming only one of the machine's IPs as its home.
     * Call with no parameters to unbind.
     *
     * @api
     * @param string $addr
     * @param int $port
     * @return string The bound address with port
     */
    public function setBindAddress($addr = '0', $port = 0)
    {
        if (preg_match(self::IP_PATTERN, $addr) === 0) {
            $addr .= ':' . $port;
        }
        return ($this->_bindto = $addr);
    }

    /**
     * Enables/disables channel syncing.
     *
     * Channel syncing means, all users on all channel we are joined are tracked in the
     * channel array. This makes it very handy for botcoding.
     *
     * @api
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
     * @api
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
     * @api
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
     * @api
     * @param integer|null $milliseconds
     * @return integer
     */
    public function setDisconnectTime($milliseconds = null)
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
     * @api
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
     * @api
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
     * @api
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
     * @api
     * @param integer|null $milliseconds
     * @return integer
     */
    public function setReceiveDelay($milliseconds = null)
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
     * @api
     * @param integer|null $milliseconds
     * @return integer
     */
    public function setReconnectDelay($milliseconds = null)
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
     * @api
     * @param boolean $boolean
     * @return boolean
     */
    public function setRunAsDaemon($boolean)
    {
        $this->_runasdaemon = (bool) $boolean;
        ignore_user_abort($this->_runasdaemon);
        return $this->_runasdaemon;
    }

    /**
     * Sets the delay for sending data to the IRC server.
     *
     * Sets the delay time between sending messages, to avoid flooding
     * IRC servers. If your bot has special flooding permissions on the
     * network you're connected to, you can set this quite low to send
     * messages faster.
     * Default: 250
     *
     * @api
     * @param integer|null $milliseconds
     * @return integer
     */
    public function setSendDelay($milliseconds = null)
    {
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
     * @api
     * @param integer|null $seconds
     * @return integer
     */
    public function setReceiveTimeout($seconds = null)
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
     * @api
     * @param integer|null $seconds
     * @return integer
     */
    public function setTransmitTimeout($seconds = null)
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
     * @api
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
     * Starts the benchmark (sets the counters).
     *
     * @api
     * @return Net_SmartIRC
     */
    public function startBenchmark()
    {
        $this->_benchmark_starttime = microtime(true);
        $this->log(SMARTIRC_DEBUG_NOTICE, 'benchmark started', __FILE__, __LINE__);
        return $this;
    }

    /**
     * Stops the benchmark and displays the result.
     *
     * @api
     * @return Net_SmartIRC
     */
    public function stopBenchmark()
    {
        $this->_benchmark_stoptime = microtime(true);
        $this->log(SMARTIRC_DEBUG_NOTICE, 'benchmark stopped', __FILE__, __LINE__);

        if ($this->_benchmark) {
            $this->showBenchmark();
        }
        return $this;
    }

    /**
     * Shows the benchmark result.
     *
     * @api
     * @return Net_SmartIRC
     */
    public function showBenchmark()
    {
        $this->log(SMARTIRC_DEBUG_NOTICE, 'benchmark time: '
            .((float)$this->_benchmark_stoptime-(float)$this->_benchmark_starttime),
            __FILE__, __LINE__
        );
        return $this;
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
     * @api
     * @see SMARTIRC_DEBUG_NOTICE
     * @param integer $level bit constants (SMARTIRC_DEBUG_*)
     * @param string $entry the new log entry
     * @param string|null $file The source file originating the log() call
     * @param int|null $line The line of code that called log()
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
     * @api
     * @return array
     */
    public function getMotd()
    {
        return $this->_motd;
    }

    /**
     * Returns the usermode.
     *
     * @api
     * @return string
     */
    public function getUsermode()
    {
        return $this->_usermode;
    }

    /**
     * Returns a reference to the channel object of the specified channelname.
     *
     * @api
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
            return;
        }

        if (isset($this->_channels[strtolower($channelname)])) {
            return $this->_channels[strtolower($channelname)];
        } else {
            $this->log(SMARTIRC_DEBUG_NOTICE,
                'WARNING: getChannel() is called and the required channel '
                .$channelname.' has not been joined!', __FILE__, __LINE__
            );
        }
    }

    /**
     * Returns a reference to the user object for the specified username and channelname.
     *
     * @api
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
            return;
        }

        if ($this->isJoined($channelname, $username)) {
            return $this->getChannel($channelname)->users[strtolower($username)];
        }
    }

    /**
     * Creates the sockets and connects to the IRC server on the given port.
     *
     * Returns this SmartIRC object on success, and false on failure.
     *
     * @api
     * @param string $addr
     * @param integer $port
     * @param bool $reconnecting For internal use only
     * @return boolean|Net_SmartIRC
     */
    public function connect($addr, $port = 6667, $reconnecting = false)
    {
        ob_implicit_flush();
        $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: connecting',
            __FILE__, __LINE__
        );

        if ($hasPort = preg_match(self::IP_PATTERN, $addr)) {
            $colon = strrpos($addr, ':');
            $this->_address = substr($addr, 0, $colon);
            $this->_port = (int) substr($addr, $colon + 1);
        } elseif ($hasPort === 0) {
            $this->_address = $addr;
            $this->_port = $port;
            $addr .= ':' . $port;
        }

        $timeout = ini_get("default_socket_timeout");
        $context = stream_context_create(array('socket' => array('bindto' => $this->_bindto)));
        $this->log(SMARTIRC_DEBUG_SOCKET, 'DEBUG_SOCKET: binding to '.$this->_bindto,
            __FILE__, __LINE__);


        if ($this->_socket = stream_socket_client($addr, $errno, $errstr,
                $timeout, STREAM_CLIENT_CONNECT, $context)
        ) {
            if (!stream_set_blocking($this->_socket, 0)) {
                $this->log(SMARTIRC_DEBUG_SOCKET, 'DEBUG_SOCKET: unable to unblock stream',
                    __FILE__, __LINE__
                );
                $this->throwError('unable to unblock stream');
            }

            $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: connected',
                __FILE__, __LINE__
            );

            $this->_autoretrycount = 0;
            $this->_connectionerror = false;

            $this->registerTimeHandler($this->_rxtimeout * 125, $this, '_pingcheck');

            $this->_lasttx = $this->_lastrx = time();
            $this->_updatestate();
            return $this;
        }

        $error_msg = "couldn't connect to \"$addr\" reason: \"$errstr ($errno)\"";
        $this->log(SMARTIRC_DEBUG_SOCKET, 'DEBUG_NOTICE: '.$error_msg,
            __FILE__, __LINE__
        );
        $this->throwError($error_msg);

        return ($reconnecting) ? false : $this->reconnect();
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
     * @api
     * @param boolean $quick default: false
     * @return boolean|Net_SmartIRC
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

        fclose($this->_socket);

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

        return $this;
    }

    /**
     * Reconnects to the IRC server with the same login info,
     * it also rejoins the channels
     *
     * @api
     * @return boolean|Net_SmartIRC
     */
    public function reconnect()
    {
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

        while ($this->_autoretry === true
            && ($this->_autoretrymax == 0 || $this->_autoretrycount < $this->_autoretrymax)
            && $this->_updatestate() != SMARTIRC_STATE_CONNECTED
        ) {
            $this->_autoretrycount++;

            if ($this->_reconnectdelay > 0) {
                $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: delaying '
                    .'reconnect for '.$this->_reconnectdelay.' ms',
                    __FILE__, __LINE__
                );

                for ($i = 0; $i < $this->_reconnectdelay; $i++) {
                    $this->_callTimeHandlers();
                    usleep(1000);
                }
            }

            $this->_callTimeHandlers();
            $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: reconnecting...',
                __FILE__, __LINE__
            );

            if ($this->connect($this->_address, $this->_port, true) !== false) {
                break;
            }
        }

        if ($this->_updatestate() != SMARTIRC_STATE_CONNECTED) {
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

        return $this;
    }

    /**
     * login and register nickname on the IRC network
     *
     * Registers the nickname and user information on the IRC network.
     *
     * @api
     * @param string $nick
     * @param string $realname
     * @param integer $usermode
     * @param string $username
     * @param string $password
     * @return Net_SmartIRC
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
            $ipos = strpos($usermode, 'i');
            $wpos = strpos($usermode, 'w');
            $val = 0;
            if ($ipos) $val += 8;
            if ($wpos) $val += 4;

            if ($val == 0) {
                $this->log(SMARTIRC_DEBUG_NOTICE, 'DEBUG_NOTICE: login() usermode ('
                    .$usermode.') is not valid, using 0 instead',
                    __FILE__, __LINE__
                );
            }
            $usermode = $val;
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

        return $this;
    }

    // </IRC methods>

    /**
     * adds a command to the list of commands to be sent after login() info
     *
     * @api
     * @param string $cmd the command to add to the perform list
     * @return Net_SmartIRC
     */
    public function perform($cmd)
    {
        $this->_performs[] = $cmd;
        return $this;
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
     * @api
     * @param string $data
     * @param integer $priority must be one of the priority constants
     * @return boolean|Net_SmartIRC
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

        return $this;
    }

    /**
     * checks if the passed nickname is our own nickname
     *
     * @api
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
     * @api
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
     * @api
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
     * @api
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
     * @api
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
     * @api
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
     * @api
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
     * @api
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
     * @api
     * @param bool $ival Whether to interrupt the next listen iteration
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
     * @api
     * @return Net_SmartIRC
     */
    public function listen()
    {
        set_time_limit(0);
        while ($this->listenOnce() && !$this->_interrupt) {}
        return $this;
    }

    /**
     * goes into receive mode _only_ for one pass
     *
     * Goes into receive mode. It will return when one pass is complete.
     * Use this when you want to connect to multiple IRC servers.
     *
     * @api
     * @return boolean|Net_SmartIRC
     */
    public function listenOnce()
    {
        // if we're not connected, we can't listen, so return
        if ($this->_updatestate() != SMARTIRC_STATE_CONNECTED) {
            return false;
        }

        // before we listen...
        if ($this->_loggedin) {
            // see if any timehandler needs to be called
            $this->_callTimeHandlers();

            // also let's send any queued messages
            if ($this->_lastsentmsgtime == 0) {
                $this->_lastsentmsgtime = microtime(true);
            }

            $highcount = count($this->_messagebuffer[SMARTIRC_HIGH]);
            $mediumcount = count($this->_messagebuffer[SMARTIRC_MEDIUM]);
            $lowcount = count($this->_messagebuffer[SMARTIRC_LOW]);
            $this->_messagebuffersize = $highcount+$mediumcount+$lowcount;

            // don't send them too fast
            if ($this->_messagebuffersize
                && microtime(true)
                    >= ($this->_lastsentmsgtime+($this->_senddelay/1000))
            ) {
                $result = null;
                if ($highcount) {
                    $this->_rawsend(array_shift($this->_messagebuffer[SMARTIRC_HIGH]));
                    $this->_lastsentmsgtime = microtime(true);
                } else if ($mediumcount) {
                    $this->_rawsend(array_shift($this->_messagebuffer[SMARTIRC_MEDIUM]));
                    $this->_lastsentmsgtime = microtime(true);
                } else if ($lowcount) {
                    $this->_rawsend(array_shift($this->_messagebuffer[SMARTIRC_LOW]));
                    $this->_lastsentmsgtime = microtime(true);
                }
            }
        }

        // calculate selecttimeout
        $compare = array($this->_maxtimer, $this->_receivedelay * 1000);

        if ($this->_mintimer) {
            $compare[] = $this->_mintimer;
        }

        $selecttimeout = ($this->_messagebuffersize != 0)
            ? $this->_senddelay
            : min($compare)
        ;

        // check the socket to see if data is waiting for us
        // this will trigger a warning when a signal is received
        $r = array($this->_socket);
        $w = null;
        $e = null;
        $result = stream_select($r, $w, $e, 0, $selecttimeout);

        $rawdata = null;

        if ($result) {
            // the socket got data to read
            $rawdata = '';
            do {
                if ($get = fgets($this->_socket)):
                    $rawdata .= $get;
                endif;
                $rawlen = strlen($rawdata);
            } while ($rawlen && $rawdata{$rawlen - 1} != "\n");

        } else if ($result === false) {
            // panic! panic! something went wrong! maybe received a signal.
            $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: stream_select()'
                .' returned false, something went wrong!',
                __FILE__, __LINE__
            );
            exit;
        }
        // no data on the socket

        $timestamp = time();
        if (empty($rawdata)) {
            if ($this->_lastrx < ($timestamp - $this->_rxtimeout)) {
                $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: '
                    .'receive timeout detected, doing reconnect...',
                    __FILE__, __LINE__
                );
                $this->_connectionerror = true;
            } else if ($this->_lasttx < ($timestamp - $this->_txtimeout)) {
                $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: '
                    .'transmit timeout detected, doing reconnect...',
                    __FILE__, __LINE__
                );
                $this->_connectionerror = true;
            }
        } else {
            $this->_lastrx = $timestamp;

            // split up incoming lines, remove any empty ones and
            // trim whitespace off the rest
            $rawdataar = array_map('trim', array_filter(explode("\r\n", $rawdata)));

            // parse and handle them
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
                $ircdata->messageex = explode(' ', $trailing);

                // parse ident thingy
                if (preg_match('/^(\S+)!(\S+)@(\S+)$/', $prefix, $matches)) {
                    $ircdata->nick = $matches[1];
                    $ircdata->ident = $matches[2];
                    $ircdata->host = $matches[3];
                } else {
                    $ircdata->nick = '';
                    $ircdata->ident = '';
                    $ircdata->host = $prefix;
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
                        $ircdata->type = SMARTIRC_TYPE_NAME;
                        if ($params[0] == $this->_nick):
                            $ircdata->channel = $params[2];
                        else:
                            $ircdata->channel = $params[1];
                        endif;
                        break;

                    case SMARTIRC_RPL_ENDOFNAMES:
                        $ircdata->type = SMARTIRC_TYPE_NAME;
                        if ($params[0] == $this->_nick):
                            $ircdata->channel = $params[1];
                        else:
                            $ircdata->channel = $params[0];
                        endif;
                        break;

                    case SMARTIRC_RPL_WHOREPLY:
                    case SMARTIRC_RPL_ENDOFWHO:
                        $ircdata->type = SMARTIRC_TYPE_WHO;
                        if ($params[0] == $this->_nick):
                            $ircdata->channel = $params[1];
                        else:
                            $ircdata->channel = $params[0];
                        endif;
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
                            if (preg_match("/^\1ACTION .*\1\$/", $ircdata->message)) {
                                $ircdata->type = SMARTIRC_TYPE_ACTION;
                                $ircdata->channel = $params[0];
                                break;
                            }
                            if (preg_match("/^\1.*\1\$/", $ircdata->message)) {
                                $ircdata->type = (SMARTIRC_TYPE_CTCP_REQUEST | SMARTIRC_TYPE_CTCP);
                                break;
                            }
                        }
                        $ircdata->type = SMARTIRC_TYPE_QUERY;
                        break;

                    case 'NOTICE':
                        if (preg_match("/^\1.*\1\$/", $ircdata->message)) {
                            $ircdata->type = (SMARTIRC_TYPE_CTCP_REPLY | SMARTIRC_TYPE_CTCP);
                            break;
                        }
                        $ircdata->type = SMARTIRC_TYPE_NOTICE;
                        break;

                    case 'INVITE':
                        $ircdata->type = SMARTIRC_TYPE_INVITE;
                        break;

                    case 'JOIN':
                        $ircdata->type = SMARTIRC_TYPE_JOIN;
                        $ircdata->channel = $params[0] ?: $ircdata->message;
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
                            ."command type UNKNOWN ($command)",
                            __FILE__, __LINE__
                        );
                        $ircdata->type = SMARTIRC_TYPE_UNKNOWN;
                        break;
                }

                $this->log(SMARTIRC_DEBUG_MESSAGEPARSER, 'DEBUG_MESSAGEPARSER: '
                    .'ircdata nick:"'.$ircdata->nick
                    .'" ident:"'.$ircdata->ident
                    .'" host:"'.$ircdata->host
                    .'" type:"'.$ircdata->type
                    .'" from:"'.$ircdata->from
                    .'" channel:"'.$ircdata->channel
                    .'" message:"'.$ircdata->message.'"', __FILE__, __LINE__
                );

                // lets see if we have a messagehandler for it
                if (is_numeric($command)) {
                    if (!array_key_exists($command, $this->nreplycodes)) {
                        $this->log(SMARTIRC_DEBUG_MESSAGEHANDLER,
                            'DEBUG_MESSAGEHANDLER: cannot translate unrecognized'
                            ." messagecode $command into a command type",
                            __FILE__, __LINE__
                        );
                        $methodname = 'event_' . $command;
                    } else {
                        $methodname = 'event_'.strtolower($this->nreplycodes[$command]);
                    }

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
                foreach ($this->_actionhandler as $i => $handlerinfo) {

                    $hmsg = $handlerinfo['message'];
                    $regex = ($hmsg{0} == $hmsg{strlen($hmsg) - 1})
                        ? $hmsg
                        : '/' . $hmsg . '/';

                    if (($handlerinfo['type'] & $ircdata->type)
                        && preg_match($regex, $ircdata->message)
                    ) {
                        $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: '
                            ."actionhandler match found for id: $i type: "
                            .$ircdata->type.' message: "'.$ircdata->message
                            ."\" regex: \"$regex\"", __FILE__, __LINE__
                        );

                        $callback = $handlerinfo['callback'];

                        $cbstring = (is_array($callback))
                            ? (is_object($callback[0])
                                ? get_class($callback[0])
                                : $callback[0]
                              ) . '->' . $callback[1]
                            : '(anonymous function)';

                        if (is_callable($callback)) {
                            $this->log(SMARTIRC_DEBUG_ACTIONHANDLER,
                                'DEBUG_ACTIONHANDLER: calling "'.$cbstring.'"',
                                __FILE__, __LINE__
                            );
                            call_user_func($callback, $this, $ircdata);
                        } else {
                            $this->log(SMARTIRC_DEBUG_ACTIONHANDLER,
                                'DEBUG_ACTIONHANDLER: callback is invalid! "'.$cbstring.'"',
                                __FILE__, __LINE__
                            );
                        }
                    }
                }

                unset($ircdata);
            }
        }

        // if we've done anything that didn't work and the connection is broken,
        // log it and fix it
        if ($this->_connectionerror) {
            $this->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: connection'
                .' error detected, attempting reconnect!', __FILE__, __LINE__
            );
            $this->reconnect();
        }
        return $this;
    }

    /**
     * waits for a special message type and returns the answer
     *
     * Creates a special actionhandler for that given TYPE and returns the answer.
     * This will only receive the requested type, immediately quit and disconnect from the IRC server.
     * Made for showing IRC statistics on your homepage, or other IRC related information.
     *
     * @api
     * @param integer $messagetype see in the documentation 'Message Types'
     * @param string  $regex the pattern to match on
     * @return array answer from the IRC server for this $messagetype
     */
    public function listenFor($messagetype, $regex = '.*')
    {
        $listenfor = new Net_SmartIRC_listenfor();
        $this->registerActionHandler($messagetype, $regex, array($listenfor, 'handler'));
        $this->listen();
        return $listenfor->result;
    }

    /**
     * registers a new actionhandler and returns the assigned id
     *
     * Registers an actionhandler in Net_SmartIRC for calling it later.
     * The actionhandler id is needed for unregistering the actionhandler.
     *
     * @api
     * @see example.php
     * @param integer $handlertype bits constants, see in this documentation Message Types
     * @param string $regexhandler the message that has to be in the IRC message in regex syntax
     * @param object|callable $object either an object with the method, or a callable
     * @param string $methodname the methodname that will be called when the handler happens
     * @return integer assigned actionhandler id
     */
    public function registerActionHandler($handlertype, $regexhandler, $object,
        $methodname = ''
    ) {
        // precheck
        if (!($handlertype & SMARTIRC_TYPE_ALL)) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: passed invalid handler'
                .'type to registerActionHandler()', __FILE__, __LINE__
            );
            return false;
        }

        if (!empty($methodname)) {
            $object = array($object, $methodname);
        }

        $id = $this->_actionhandlerid++;
        $this->_actionhandler[] = array(
            'id' => $id,
            'type' => $handlertype,
            'message' => $regexhandler,
            'callback' => $object,
        );
        $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: '
            .'actionhandler('.$id.') registered', __FILE__, __LINE__
        );
        return $id;
    }

    /**
     * unregisters an existing actionhandler
     *
     * @api
     * @param integer $handlertype
     * @param string $regexhandler
     * @param object $object
     * @param string $methodname
     * @return boolean
     */
    public function unregisterActionHandler($handlertype, $regexhandler,
        $object, $methodname = ''
    ) {
        // precheck
        if (!($handlertype & SMARTIRC_TYPE_ALL)) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING: passed invalid handler'
                .'type to unregisterActionHandler()', __FILE__, __LINE__
            );
            return false;
        }

        if (!empty($methodname)) {
            $object = array($object, $methodname);
        }

        foreach ($this->_actionhandler as $i => &$handlerinfo) {
            if ($handlerinfo['type'] == $handlertype
                && $handlerinfo['message'] == $regexhandler
                && $handlerinfo['callback'] == $object
            ) {
                $id = $handlerinfo['id'];
                unset($this->_actionhandler[$i]);

                $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: '
                    .'actionhandler('.$id.') unregistered', __FILE__, __LINE__
                );
                $this->_actionhandler = array_values($this->_actionhandler);
                return $this;
            }
        }

        $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: could '
            .'not find actionhandler type: "'.$handlertype.'" message: "'
            .$regexhandler.'" matching callback. Nothing unregistered', __FILE__, __LINE__
        );
        return false;
    }

    /**
     * unregisters an existing actionhandler via the id
     *
     * @api
     * @param integer|array $id
     * @return boolean|void
     */
    public function unregisterActionId($id)
    {
        if (is_array($id)) {
            foreach ($id as $each) {
                $this->unregisterActionId($each);
            }
            return $this;
        }

        foreach ($this->_actionhandler as $i => &$handlerinfo) {
            if ($handlerinfo['id'] == $id) {
                unset($this->_actionhandler[$i]);

                $this->log(SMARTIRC_DEBUG_ACTIONHANDLER, 'DEBUG_ACTIONHANDLER: '
                    .'actionhandler('.$id.') unregistered', __FILE__, __LINE__
                );
                $this->_actionhandler = array_values($this->_actionhandler);
                return $this;
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
     * @api
     * @see example7.php
     * @param integer $interval interval time in milliseconds
     * @param object $object a reference to the objects of the method
     * @param string $methodname the methodname that will be called when the handler happens
     * @return integer assigned timehandler id
     */
    public function registerTimeHandler($interval, $object, $methodname = '')
    {
        $id = $this->_timehandlerid++;

        if (!empty($methodname)) {
            $object = array($object, $methodname);
        }


        $this->_timehandler[] = array(
            'id' => $id,
            'interval' => $interval,
            'callback' => $object,
            'lastmicrotimestamp' => microtime(true),
        );
        $this->log(SMARTIRC_DEBUG_TIMEHANDLER, 'DEBUG_TIMEHANDLER: timehandler('
            .$id.') registered', __FILE__, __LINE__
        );

        if (($this->_mintimer == false) || ($interval < $this->_mintimer)) {
            $this->_mintimer = $interval;
        }

        return $id;
    }

    /**
     * unregisters an existing timehandler via the id
     *
     * @api
     * @see example7.php
     * @param integer $id
     * @return boolean
     */
    public function unregisterTimeId($id)
    {
        if (is_array($id)) {
            foreach ($id as $each) {
                $this->unregisterTimeId($each);
            }
            return $this;
        }

        foreach ($this->_timehandler as $i => &$handlerinfo) {
            if ($handlerinfo['id'] == $id) {
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
                    : false;

                return $this;
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
     * @api
     * @param string $name
     * @return boolean|Net_SmartIRC
     */
    public function loadModule($name)
    {
        // is the module already loaded?
        if (isset($this->_modules[$name])) {
            $this->log(SMARTIRC_DEBUG_NOTICE, 'WARNING! module with the name "'
                .$name.'" already loaded!', __FILE__, __LINE__
            );
            return false;
        }

        $classname = "Net_SmartIRC_module_$name";
        if (class_exists($classname)) {
            $this->log(SMARTIRC_DEBUG_MODULES, "DEBUG_MODULES: \"$name\" module class"
                .' exists, initializing...', __FILE__, __LINE__
            );
        } else {
            $filename = $this->_modulepath."/$name.php";
            if (!file_exists($filename)) {
                $this->log(SMARTIRC_DEBUG_MODULES, "DEBUG_MODULES: couldn't load "
                    ."module; file \"$filename\" doesn't exist", __FILE__, __LINE__
                );
                return false;
            }
            // pray that there is no parse error, it will kill us!
            include_once($filename);

            if (!class_exists($classname)) {
                $this->log(SMARTIRC_DEBUG_MODULES, "DEBUG_MODULES: class $classname"
                    ." not found in $filename, aborting...", __FILE__, __LINE__
                );
                return false;
            }

            $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: loading module '
                ."\"$name\" from file...", __FILE__, __LINE__
            );
        }

        $methods = array_flip(get_class_methods($classname));

        if (!(isset($methods['__construct']) || isset($methods['module_init']))) {
            $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: required method '
                .$classname.'::__construct() not found, aborting...',
                __FILE__, __LINE__
            );
            return false;
        }

        if (!(isset($methods['__destruct']) || isset($methods['module_exit']))) {
            $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: required method '
                .$classname.'::__destruct() not found, aborting...',
                __FILE__, __LINE__
            );
            return false;
        }

        $vars = get_class_vars($classname);
        $required = array('name', 'description', 'author', 'license');

        foreach ($required as $varname) {
            if (!isset($vars[$varname])) {
                $this->log(SMARTIRC_DEBUG_NOTICE, 'NOTICE: required module'
                    ."property {$classname}::\${$varname} not found.",
                    __FILE__, __LINE__
                );
            }
        }

        // looks like the module satisfies us, so instantiate it
        if (isset($methods['module_init'])) {
            // we're using an old module_init style module
            $this->_modules[$name] = new $classname;
            $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: calling '
                .$classname.'::module_init()', __FILE__, __LINE__
            );
            $this->_modules[$name]->module_init($this);
        } else if (func_num_args() == 1) {
            // we're using a new __construct style module, which maintains its
            // own reference to the $irc client object it's being used on
            $this->_modules[$name] = new $classname($this);
        } else
        // we're using new style AND we have args to pass to the constructor
        if (func_num_args() == 2) {
            // only one arg, so pass it as is
            $this->_modules[$name] = new $classname($this, func_get_arg(1));
        } else {
            // multiple args, so pass them in an array
            $this->_modules[$name] = new $classname($this, array_slice(func_get_args(), 1));
        }

        $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: successfully loaded'
            ." module: $name", __FILE__, __LINE__
        );
        return $this;
    }

    /**
     * unloads a module by the name originally loaded with
     *
     * @api
     * @param string $name
     * @return boolean|Net_SmartIRC
     */
    public function unloadModule($name)
    {
        $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: unloading module: '
            ."$name...", __FILE__, __LINE__
        );

        if (isset($this->_modules[$name])) {
            if (in_array('module_exit',
                    get_class_methods(get_class($this->_modules[$name]))
            )) {
                $this->_modules[$name]->module_exit($this);
            }

            unset($this->_modules[$name]);
            $this->log(SMARTIRC_DEBUG_MODULES, 'DEBUG_MODULES: successfully'
                ." unloaded module: $name", __FILE__, __LINE__);
            return $this;
        }

        $this->log(SMARTIRC_DEBUG_MODULES, "DEBUG_MODULES: couldn't unload"
            ." module: $name (it's not loaded!)", __FILE__, __LINE__
        );
        return false;
    }

    /**
     * Returns an array of the module names that are currently loaded
     *
     * @api
     * @return array
     */
    public function loadedModules()
    {
        return array_keys($this->_modules);
    }

    // <protected methods>
    /**
     * adds an user to the channelobject or updates his info
     *
     * @internal
     * @param object $channel
     * @param object $newuser
     * @return Net_SmartIRC
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

            $channel->users[$lowerednick] = $newuser;
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
                $channel->{$ms}[$user->nick] = true;
            }
        }
        return $this;
    }

    /**
     * looks for any time handlers that have timed out and calls them if valid
     *
     * @internal
     * @return void
     */
    protected function _callTimeHandlers()
    {
        foreach ($this->_timehandler as &$handlerinfo) {
            $microtimestamp = microtime(true);
            if ($microtimestamp >= $handlerinfo['lastmicrotimestamp']
                + ($handlerinfo['interval'] / 1000.0)
            ) {
                $methodobject = &$handlerinfo['object'];
                $method = $handlerinfo['method'];
                $handlerinfo['lastmicrotimestamp'] = $microtimestamp;

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

    /**
     * An active-pinging system to keep the bot from dropping the connection
     *
     * @internal
     * @return void
     */
    protected function _pingcheck()
    {
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
     * @internal
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

        $result = fwrite($this->_socket, $data.SMARTIRC_CRLF);

        if (!$result) {
            // writing to the socket failed, means the connection is broken
            $this->_connectionerror = true;
        } else {
            $this->_lasttx = time();
        }

        return $result;
    }

    /**
     * removes an user from one channel or all if he quits
     *
     * @internal
     * @param object $ircdata
     * @return Net_SmartIRC
     */
    protected function _removeuser($ircdata)
    {
        if ($ircdata->type & (SMARTIRC_TYPE_PART | SMARTIRC_TYPE_QUIT)) {
            $nick = $ircdata->nick;
        } else if ($ircdata->type & SMARTIRC_TYPE_KICK) {
            $nick = $ircdata->params[1];
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
                                if (isset($channel->{$list}[$nick])) {
                                    // die!
                                    $this->log(SMARTIRC_DEBUG_CHANNELSYNCING,
                                        'DEBUG_CHANNELSYNCING: removing him '
                                        ."from $list list", __FILE__, __LINE__
                                    );
                                    unset($channel->{$list}[$nick]);
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
                    if (isset($channel->{$list}[$nick])) {
                        $this->log(SMARTIRC_DEBUG_CHANNELSYNCING,
                            'DEBUG_CHANNELSYNCING: removing him '
                            ."from $list list", __FILE__, __LINE__
                        );
                        unset($channel->{$list}[$nick]);
                    }
                }
            }
        }
        return $this;
    }

    /**
     * updates and returns the current connection state
     *
     * @internal
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

    public function isError($object) // is this even needed/used?
    {
        return (is_object($object)
            && strtolower(get_class($object)) == 'net_smartirc_error'
        );
    }

    protected function &throwError($message)
    {
        $var = new Net_SmartIRC_Error($message);
        return $var;
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
     * @api
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
