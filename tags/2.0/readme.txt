=== Count per Day ===
Contributors: Tom Braider
Donate link: http://www.unicef.org
Tags: counter, count, posts, visits, reads
Requires at least: 2.7
Tested up to: 2.8
Stable tag: 2.0

Visit Counter, shows reads per page, visitors today, yesterday, last week, last months and other statistics.

== Description ==

* count reads and visitors
* shows reads per page
* shows visitors today, yesterday, last week, last months and other statistics on dashboard
* you can show these statistics on frontend (e.g. on sidebar) too
* if you use Wordpress < 2.7 please use Count-per-Day v1.4

It counts 1 visit per IP per day. So any reload of the page don't increment the counter.

Languages: english, german, italian, portuguese, belorussian

== Installation ==

1. unzip plugin directory into the '/wp-content/plugins/' directory
1. activate the plugin through the 'Plugins' menu in WordPress

First activation will create the 2 tables wp _ cpd _ counter and wp _ cpd _ counter _ useronline.

**Configuration**

See the Options Page and check the default values. It's easy. :)

If "Auto counter" is on reads on single-posts and pages will count without any changes on template.

For more informations see "Other Notes".

== Frequently Asked Questions ==

= Need Help? Find Bug? =
read and write comments on <a href="http://www.tomsdimension.de/wp-plugins/count-per-day">plugin page</a>

== Screenshots ==

1. Statistics on Count-per-Day Dashboard
2. Options
3. Widget sample

== Arbitrary section ==

**Functions**

You can place these functions in your template.<br/>
Place functions within post-loop (e.g. in single.php)<br/>
Use '&lt;?php if(method _ exists($count _ per _ day, "show")) $count _ per _ day->show(); ?&gt;' to check if plugin is aktivated.

'show( $before, $after, $show, $count )'

* $before = text before number e.g. '&lt;p&gt;' (standard "")
* $after = text after number e.g. 'reads&lt;/p&gt;' (standard " reads")
* $show = true/false, "echo" complete string or "return" number only (standard true)
* $count = true/false, false will not count the reads (standard true)

'count()'

* only count reads, without any output
* cpdShow call it

'getFirstCount()'

* shows date of first count

'getUserPerDay()'

* shows average number of visitors per day

'getUserAll()'
 
* shows number of total visitors

'getUserOnline()'

* shows number of visitors just online

'getUserToday()'

* shows number of visitors today

'getUserYesterday()'
 
* shows number of visitors yesterday

'getUserLastWeek()'

* shows number of visitors last week (7 days)

'getUserPerMonth()'
 
* lists number of visitors per month

'getUserPerPost( $limit = 0 )'

* lists _$limit_ posts with number of visits

'getUserPerPost( $limit = 0 )'

* lists _$limit_ number of posts, -1: all, 0: get option from db, x: number

'getMostVisitedPosts()'

* shows a list with the most visited posts in the last days

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

== Changelog ==

= 2.0 =
+ NEW: sidebar widget
+ NEW: reset button to set all counter to 0
+ NEW: custom number of "reads per post" on dashboard page
+ NEW: little chart of "reads per day" on dashboard page
+ NEW: reads in post and page lists (optional)
+ NEW: most visited posts in last days on dashboard page
+ NEW: recognize bots by IP address
+ NEW: moveble metaboxes on dashboard page
+ NEW: clean function now deletes counter of deleted pages too
+ Bugfix: updates online counter on every load
+ Bugfix: now empty user agents/clients will not be count
+ change options to array
+ create class, update/clean up/rename functions

= 1.5.1 =
+ New language: Belorussian, thanks to Marcis Gasuns 

= 1.5 =
+ NEW: Dashboard Widget
+ WP 2.7 optimized, for WP<2.7 please use CPD 1.4 

= 1.4 =
+ NEW: uninstall function of WP 2.7 implemented
+ litle changes on layout to be suitable for WP 2.7

= 1.3 =
+ New: you can delete old data if you add a new bot string
+ Bugfix: Bot check was case-sensitive
+ New language: Portuguese, thanks to Filipe

= 1.2.3 =
+ Bugfix: autocount endless looping

= 1.2.2 =
+ New language: Italian, thanks to Gianni Diurno http://gidibao.net/index.php/portfolio/

= 1.2.1 =
+ Bugfix: Error 404 "Page not found" with "auto count"

= 1.2 =
+ Bugfix: tables in DB were not be created every time (seen on mysql < 5)
+ New: "auto count" can count visits without changes on template

= 1.1 =
+ Languages: english, german 
+ HTTP _ USER _ AGENT will be saved, identification of new search bots
+ Stylesheet in file counter.css

= 1.0 =
+ first release