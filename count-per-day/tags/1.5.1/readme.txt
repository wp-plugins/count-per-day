=== Count per Day ===
Contributors: Tom Braider
Donate link: http://www.unicef.org
Tags: counter, count, posts, visits, reads
Requires at least: 2.7
Tested up to: 2.7
Stable tag: 1.5.1

Visit Counter, shows reads per page, visitors today, yesterday, last week, last months and other statistics.

== Description ==

* count reads and visitors
* shows reads per page
* shows visitors today, yesterday, last week, last months and other statistics on dashboard
* you can show these statistics on frontend (e.g. on sidebar) too
* if you use Wordpress < 2.7 please use Count-per-Day v1.4

It counts 1 visit per IP per day. So any reload of the page don't increment the counter.

Languages: english, german, italian, portuguese

== Installation ==

1. unzip plugin directory into the '/wp-content/plugins/' directory
1. activate the plugin through the 'Plugins' menu in WordPress

First activation will create the 2 tables wp _ cpd _ counter and wp _ cpd _ counter _ useronline.

**Configuration**

See the Options Page. It's easy. :)

If "Auto counter" is on reads on single-posts and pages will count without any changes on template.<br>

* place functions within post-loop (e.g. in single.php)<br/>
	'&lt;?php if(function_exists("cpdShow")) { cpdShow(); } ?&gt;'
* for more informations see "Other Notes"

== Frequently Asked Questions ==

= Need Help? Find Bug? =
read and write comments on <a href="http://www.tomsdimension.de/wp-plugins/count-per-day">plugin page</a>

== Screenshots ==

1. Statistics on Count-per-Day Dashboard (german)
2. Options (german)

== Arbitrary section ==

**Functions**

You can place these functions in your template.

'cpdShow( $before, $after, $show, $count )'

* $before = text before number e.g. '&lt;p&gt;' (standard "")
* $after = text after number e.g. 'reads&lt;/p&gt;' (standard " reads")
* $show = true/false, "echo" complete string or "return" number only (standard true)
* $count = true/false, false will not count the reads (standard true)

'cpdCount()'

* only count reads, without any output
* cpdShow call it

'cpdGetFirstCount()'

* shows date of first count

'cpdGetUserPerDay()'

* shows average number of visitors per day

'cpdGetUserAll()'
 
* shows number of total visitors

'cpdGetUserOnline()'

* shows number of visitors just online

'cpdGetUserToday()'

* shows number of visitors today

'cpdGetUserYesterday()'
 
* shows number of visitors yesterday

'cpdGetUserLastWeek()'

* shows number of visitors last week (7 days)

'cpdGetUserPerMonth()'
 
* lists number of visitors per month

'cpdGetUserPerPost( $limit = 0 )'

* lists _$limit_ posts with number of visits

**Filelist**

* counter.php
* counter-options.php
* counter.css
* locale/de_DE.mo
* locale/de_DE.po
* locale/it_IT.mo
* locale/it_IT.po
* locale/pt_BR.mo
* locale/pt_BR.po
* locale/by_BY.mo
* locale/by_BY.po

**Changelog**

_Version 1.5.1_

+ New language: Belorussian, thanks to Marcis Gasuns http://www.fatcow.com

_Version 1.5_

+ NEW: Dashboard Widget
+ WP 2.7 optimized, for WP<2.7 please use CPD 1.4 

_Version 1.4_

+ NEW: uninstall function of WP 2.7 implemented
+ litle changes on layout to be suitable for WP 2.7

_Version 1.3_

+ New: you can delete old data if you add a new bot string
+ Bugfix: Bot check was case-sensitive
+ New language: Portuguese, thanks to Filipe

_Version 1.2.3_

+ Bugfix: autocount endless looping

_Version 1.2.2_

+ New language: Italian, thanks to Gianni Diurno http://gidibao.net/index.php/portfolio/

_Version 1.2.1_

+ Bugfix: Error 404 "Page not found" with "auto count"

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