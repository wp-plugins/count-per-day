=== Count per Day ===
Contributors: Tom Braider
Donate link: http://www.unicef.org
Tags: counter, count, posts, visits, reads
Requires at least: 2.0
Tested up to: 2.5.1
Stable tag: 1.2

Visit Counter, shows reads per page, visitors today, yesterday, last week, last months and other statistics.

== Description ==

* count reads and visitors
* shows reads per page
* shows visitors today, yesterday, last week, last months and other statistics on dashboard
* you can show these statistics on frontend (e.g. on sidebar) too

It counts 1 visit per IP per day. So any reload of the page don't increment the counter.

Languages: english, german

== Installation ==

1. unzip plugin directory into the "/wp-content/plugins/" directory
1. activate the plugin through the 'Plugins' menu in WordPress

First activation will create the 2 tables wp _ cpd _ counter and wp _ cpd _ counter _ useronline.

**Configuration**

go to Options Page :)

If "Auto counter" is on reads on single-posts and pages will count without any changes on template.<br>

* place functions within post-loop (e.g. in single.php)<br>
	&lt;?php if(function_exists('cpdShow')) { cpdShow(); } ?&gt;
* for more informations see "Other Notes"

== Frequently Asked Questions ==

= no questions =

no answers

== Screenshots ==

1. Statistics on Count-per-Day-Dashboard (german)
2. Options (german)

== Arbitrary section ==

**Functions**

You can place these functions in your template.

_cpdShow( $before, $after, $show, $count )_

* $before = text before number e.g. "&lt;p&gt;" (standard '')
* $after = text after number e.g. " reads&lt;/p&gt;" (standard ' reads')
* $show = true/false, "echo" complete string or "return" number only (standard true)
* $count = true/false, false will not count the reads (standard true)

_cpdCount()_

* only count reads, without any output
* cpdShow call it

_cpdGetFirstCount()_

* shows date of first count

_cpdGetUserPerDay()_

* shows average number of visitors per day

_cpdGetUserAll()_
 
* shows number of total visitors

_cpdGetUserOnline()_

* shows number of visitors just online

_cpdGetUserToday()_

* shows number of visitors today

_cpdGetUserYesterday()_
 
* shows number of visitors yesterday

_cpdGetUserLastWeek()_

* shows number of visitors last week (7 days)

_cpdGetUserPerMonth()_
 
* lists number of visitors per month

_cpdGetUserPerPost( $limit = 0 )_

* lists _$limit_ posts with number of visits

**Filelist**

* counter.php
* counter-options.php
* counter.css
* locale/de_DE.mo
* locale/de_DE.po

**Changelog**

_Version 1.2_

+ Bugfix: tables in DB were not be created every time (seen on mysql < 5)
+ New: "auto count" can count visits without changes on template

_Version 1.1_

+ Languages: english, german 
+ HTTP _ USER _ AGENT will be saved, identification of new search bots
+ Stylesheet in file counter.css

Functions:

+ cpdShow (updated)
+ cpdGetUserPerPost
+ cpdGetFirstCount
+ cpdGetUserPerDay
+ cpdGetUserAll

_Version 1.0_

Functions:

+ cpdShow
+ cpdCount
+ cpdGetUserOnline
+ cpdGetUserToday
+ cpdGetUserYesterday
+ cpdGetUserLastWeek
+ cpdGetUserPerMonth
