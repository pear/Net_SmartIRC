<?php
/**
 * $Id$
 * $Revision$
 * $Author$
 * $Date$
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
 */

class Net_SmartIRC_messagehandler
{
    /* misc */
    function _ping(&$irc, &$ircdata)
    {
        $irc->_pong(substr($ircdata->rawmessage, 5));
    }
    
    function _error(&$irc, &$ircdata)
    {
        if ($irc->_autoretry == true) {
            $irc->reconnect();
        } else {
            $irc->disconnect(true);
        }
    }
    
    function _join(&$irc, &$ircdata)
    {
        if ($irc->_channelsynching == true) {
            if ($irc->_nick == $ircdata->nick) {
                $channel = &new Net_SmartIRC_channel();
                $channel->name = $ircdata->channel;
                $irc->_channels[$channel->name] = &$channel;
                
                $irc->who($channel->name);
                $irc->mode($channel->name);
                $irc->ban($channel->name);
            }
            
            $channel = &$irc->_channels[$ircdata->channel];
            $user = &new Net_SmartIRC_user();
            $user->nick = $ircdata->nick;
            $user->ident = $ircdata->ident;
            $user->host = $ircdata->host;
            
            $irc->_adduser($channel, $user);
        }
    }
    
    function _part(&$irc, &$ircdata)
    {
        if ($irc->_channelsynching == true) {
            $irc->_removeuser($ircdata);
        }
    }
    
    function _kick(&$irc, &$ircdata)
    {
        if ($irc->_channelsynching == true) {
            $irc->_removeuser($ircdata);
        }
    }
    
    function _quit(&$irc, &$ircdata)
    {
        if ($irc->_channelsynching == true) {
            $irc->_removeuser($ircdata);
        }
    }
    
    function _nick(&$irc, &$ircdata)
    {
        if ($irc->_channelsynching == true) {
            $newnick = substr($ircdata->rawmessageex[2], 1);
            $lowerednewnick = strtolower($newnick);
            $lowerednick = strtolower($ircdata->nick);
            
            foreach ($irc->_channels as $channelkey => $channelvalue) {
                // loop through all channels
                $channel = &$irc->_channels[$channelkey];
                foreach ($channel->users as $userkey => $uservalue) {
                    // loop through all user in this channel
                    
                    if ($ircdata->nick == $uservalue->nick) {
                        // found him
                        // time for updating the object and his nickname
                        $channel->users[$lowerednewnick] = $channel->users[$lowerednick];
                        $channel->users[$lowerednewnick]->nick = $newnick;
                        
                        if ($lowerednewnick != $lowerednick) {
                            unset($channel->users[$lowerednick]);
                        }
                        
                        // he was maybe op or voice, update comming
                        if (isset($channel->ops[$ircdata->nick])) {
                            $channel->ops[$newnick] = $channel->ops[$ircdata->nick];
                            unset($channel->ops[$ircdata->nick]);
                        }
                        if (isset($channel->voices[$ircdata->nick])) {
                            $channel->voices[$newnick] = $channel->voices[$ircdata->nick];
                            unset($channel->voices[$ircdata->nick]);
                        }
                        
                        break;
                    }
                }
            }
        }
    }
    
    function _mode(&$irc, &$ircdata)
    {
        // check if its own usermode
        if ($ircdata->rawmessageex[2] == $irc->_nick) {
            $irc->_usermode = substr($ircdata->rawmessageex[3], 1);
        } else if ($irc->_channelsynching == true) {
            // it's not, and we do channel syching
            $channel = &$irc->_channels[$ircdata->channel];
            $mode = $ircdata->rawmessageex[3];
            $parameters = array_slice($ircdata->rawmessageex, 4);
            
            $add = false;
            $remove = false;
            $channelmode = '';
            $modelength = strlen($mode);
            for ($i = 0; $i < $modelength; $i++) {
                switch($mode[$i]) {
                    case '-':
                        $remove = true;
                    break;
                    case '+':
                        $add = true;
                    break;
                    // user modes
                    case 'o':
                        $nick = array_shift($parameters);
                        $lowerednick = strtolower($nick);
                        if($add) {
                            $channel->ops[$nick] = null;
                            $channel->users[$lowerednick]->op = true;
                        }
                        if($remove) {
                            unset($channel->ops[$nick]);
                            $channel->users[$lowerednick]->op = false;
                        }
                    break;
                    case 'v':
                        $nick = array_shift($parameters);
                        $lowerednick = strtolower($nick);
                        if($add) {
                            $channel->voices[$nick] = null;
                            $channel->users[$lowerednick]->voice = true;
                        }
                        if($remove) {
                            unset($channel->voices[$nick]);
                            $channel->users[$lowerednick]->voice = false;
                        }
                    break;
                    case 'k':
                        $key = array_shift($parameters);
                        if($add) {
                            $channel->key = $key;
                        }
                        if($remove) {
                            $channel->key = '';
                        }
                    break;
                    default:
                        // channel modes
                        if($mode[$i] == 'b') {
                            if($add) {
                                $hostmask = array_shift($parameters);
                                $channel->bans[$hostmask] = true;
                            }
                            if($remove) {
                                $hostmask = array_shift($parameters);
                                unset($channel->bans[$hostmask]);
                            }
                        } else {
                                $channel->mode = str_replace($mode[$i], '', $channel->mode);
                        }
                }
            }
            
            unset($channel);
        }
    }
    
    function _topic(&$irc, &$ircdata)
    {
        if ($irc->_channelsynching == true) {
            $channel = &$irc->channel[$ircdata->rawmessageex[2]];
            $channel->topic = $ircdata->message;
        }
    }
    
    function _privmsg(&$irc, &$ircdata)
    {
        if ($ircdata->type == SMARTIRC_TYPE_CTCP) {
            if (substr($ircdata->message, 1, 4) == 'PING') {
                $irc->message(SMARTIRC_TYPE_CTCP, $ircdata->nick, 'PING '.substr($ircdata->message, 5, -1));
            } elseif (substr($ircdata->message, 1, 7) == 'VERSION') {
                if (!empty($irc->_ctcpversion)) {
                    $versionstring = $irc->_ctcpversion.' | using '.SMARTIRC_VERSIONSTRING;
                } else {
                    $versionstring = SMARTIRC_VERSIONSTRING;
                }
                
                $irc->message(SMARTIRC_TYPE_CTCP, $ircdata->nick, 'VERSION '.$versionstring);
            }
        }
    }
    
    /* rpl_ */
    function _rpl_welcome(&$irc, &$ircdata)
    {
        $irc->_loggedin = true;
        $irc->log(SMARTIRC_DEBUG_CONNECTION, 'DEBUG_CONNECTION: logged in');
        
        // updating our nickname, that we got (maybe cutted...)
        $irc->_nick = $ircdata->rawmessageex[2];
    }
    
    function _rpl_motdstart(&$irc, &$ircdata)
    {
        $irc->_motd[] = $ircdata->message;
    }
    
    function _rpl_motd(&$irc, &$ircdata)
    {
        $irc->_motd[] = $ircdata->message;
    }
    
    function _rpl_endofmotd(&$irc, &$ircdata)
    {
        $irc->_motd[] = $ircdata->message;
    }
    
    function _rpl_umodeis(&$irc, &$ircdata)
    {
        $irc->_usermode = $ircdata->message;
    }
    
    function _rpl_channelmodeis(&$irc, &$ircdata) {
        if ($irc->_channelsynching == true && $irc->isJoined($ircdata->channel)) {
            $channel = &$irc->_channels[$ircdata->channel];
            $mode = $ircdata->rawmessageex[4];
            $channel->mode = str_replace('+', '', $mode);
        }
    }
    
    function _rpl_whoreply(&$irc, &$ircdata)
    {
        if ($irc->_channelsynching == true && $irc->isJoined($ircdata->channel)) {
            $channel = &$irc->_channels[$ircdata->channel];
            
            $user = &new Net_SmartIRC_user();
            $user->ident = $ircdata->rawmessageex[4];
            $user->host = $ircdata->rawmessageex[5];
            $user->server = $ircdata->rawmessageex[6];
            $user->nick = $ircdata->rawmessageex[7];
            
            $user->op = false;
            $user->voice = false;
            $user->ircop = false;
            
            $usermode = $ircdata->rawmessageex[8];
            $usermodelength = strlen($usermode);
            for ($i = 0; $i < $usermodelength; $i++) {
                switch ($usermode[$i]) {
                    case 'H':
                        $user->away = false;
                    break;
                    case 'G':
                        $user->away = true;
                    break;
                    case '@':
                        $user->op = true;
                    break;
                    case '+':
                        $user->voice = true;
                    break;
                    case '*':
                        $user->ircop = true;
                    break;
                }
            }
             
            $user->hopcount = substr($ircdata->rawmessageex[9], 1);
            $user->realname = implode(array_slice($ircdata->rawmessageex, 10), ' ');
            
            $irc->_adduser($channel, $user);
        }
    }
    
    function _rpl_namreply(&$irc, &$ircdata)
    {
        if ($irc->_channelsynching == true) {
            $channel = &$irc->_channels[$ircdata->channel];
            
            $userarray = explode(' ',substr($ircdata->message, strpos($ircdata->message, ':')+1, -1));
            $userarraycount = count($userarray);
            for ($i = 0; $i < $userarraycount; $i++) {
                $user = &new Net_SmartIRC_user();
                
                $usermode = substr($userarray[$i], 0, 1);
                switch ($usermode) {
                    case '@':
                        $user->op = true;
                        $user->nick = substr($userarray[$i], 1);
                    break;
                    case '+':
                        $user->voice = true;
                        $user->nick = substr($userarray[$i], 1);
                    break;
                    default:
                        $user->nick = $userarray[$i];
                }
                
                $irc->_adduser($channel, $user);
            }
        }
    }
    
    function _rpl_banlist(&$irc, &$ircdata)
    {
        if ($irc->_channelsynching == true && $irc->isJoined($ircdata->channel)) {
            $channel = &$irc->_channels[$ircdata->channel];
            $hostmask = $ircdata->rawmessageex[4];
            $channel->bans[$hostmask] = true;
        }
    }
    
    function _rpl_topic(&$irc, &$ircdata)
    {
        if ($irc->_channelsynching == true) {
            $channel = &$irc->_channels[$ircdata->channel];
            $topic = substr(implode(array_slice($ircdata->rawmessageex, 4), ' '), 1);
            $channel->topic = $topic;
        }
    }
    
    /* err_ */
    function _err_nicknameinuse(&$irc, &$ircdata)
    {
        $irc->_nicknameinuse();
    }
}
?>