<?php
/**
 * $Id$
 * $Revision$
 * $Author$
 * $Date$
 */
/**
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
// ---EXAMPLE OF HOW TO USE Net_SmartIRC---
// this code shows how you could show on your homepage how many users are in a specific channel
include_once('../SmartIRC.php');

$irc = &new Net_SmartIRC();
$irc->startBenchmark();
$irc->setDebug(SMARTIRC_DEBUG_ALL);
$irc->useSockets(TRUE);
$irc->setBenchmark(TRUE);
$irc->connect('irc.freenet.de', 6667);
$irc->login('Net_SmartIRC', 'Net_SmartIRC Client '.SMARTIRC_VERSION, 'Net_SmartIRC');
$irc->getList('#debian.de');
$resultar = array();
$irc->listenFor(SMARTIRC_TYPE_LIST, $resultar);
$irc->disconnect();
$irc->stopBenchmark();

$resultex = explode(' ', $resultar[0]);
$count = $resultex[1];
?>
<B>On our IRC Channel #debian.de are <? echo $count; ?> Users</B>