=== Count per Day ===
Contributors: Tom Braider
Donate link: http://www.tomsdimension.de
Tags: counter, count, posts, visits, reads
Requires at least: 2.0
Tested up to: 2.5.1
Stable tag: 1.1

Visit Counter, shows reads per page; today, yesterday, last week, last months and other statistics on dashboard.

== Description ==

Visit Counter, shows reads per page; today, yesterday, last week, last months and other statistics on dashboard.

It counts 1 visit per IP per day. So any reload of the page don't increment the counter.

Languages: english, german

== Installation ==

1. unzip plugin directory into the "/wp-content/plugins/" directory
1. activate the plugin through the 'Plugins' menu in WordPress
1. place "cpdShow()" within post-loop (e.g. in single.php)<br>
	&lt;?php if(function_exists('cpdShow')) { cpdShow(); } ?&gt;

First activation will create the 2 tables wp _ cpd _ counter and wp _ cpd _ counter _ useronline.

**Configuration**

see Options Page :)

Function parameters:

cpdShow( $before, $after, $show )

* $before = text before number e.g. "&lt;p&gt;"
* $after = text after number e.g. " reads&lt;/p&gt;"
* $show = true/false, "echo" complete string (standard) or "return" number only

cpdCount()

* only count reads, without output

== Frequently Asked Questions ==

= no questions =

no answers

== Screenshots ==

1. Statistics on Count-per-Day-Dashboard (german)
2. Options (german)

== Arbitrary section ==

**Filelist**

* counter.php
* counter-options.php
* counter.css
* locale/de_DE.mo
* locale/de_DE.po

**Changelog (german)**

_Version 1.1_

+ Sprachen englisch, deutsch 
+ HTTP _ USER _ AGENT wird mit gespeichert. Kann zur Identifikation von Suchmaschinen genutzt werden.
+ Stylesheet für Admin-Bereich in eigene Datei ausgelagert
+ Search-Bots erweitert, wir wollen ja nur echte Leser zählen

Funktionen:
+ cpdShow( $before='', $after=' reads', $show = true, $count = true ) - neuer Parameter $count: false = nicht zählen, nur anzeigen
+ cpdGetUserPerPost( $limit = 0 ) - Besucher pro Post, Limit = Maximale Anzahl
+ cpdGetFirstCount() - Zählerstart, erster Besucher-Eintrag
+ cpdGetUserPerDay() - Durchschnittliche Besucher pro Tag seit Zählerstart
+ cpdGetUserAll() - Gesamtzahl Besucher

_Version 1.0_

Funktionen:

+ cpdShow( $before='', $after=' reads', $show = true ) - zählt Besucher und zeigt Zählerstand an
+ cpdCount() - zählt Besucher, zeigt aber nichts an
+ cpdIsBot() - checkt HTTP_USER_AGENT mit angegebenen "Bot-Strings", TRUE wenn Bot
+ cpdGetUserOnline() - zeigt Online-Besucher
+ cpdGetUserToday() - zeigt heutige Besucher
+ cpdGetUserYesterday() - zeigt gestrige Besucher
+ cpdGetUserLastWeek() - zeigt Besucher der letzten Woche, 7 Tage
+ cpdGetUserPerMonth() - zeigt Besucher pro Monat
