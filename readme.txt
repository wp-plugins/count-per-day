=== Count per Day ===
Contributors: Tom Braider
Tags: counter, count, posts, visits, reads, dashboard, widget, shortcode
Requires at least: 3.0
Tested up to: 3.3.1
Stable tag: 3.1.1
License: Postcardware :)
Donate link: http://www.tomsdimension.de/postcards

Visit Counter, shows reads per page, visitors today, yesterday, last week, last months and other statistics.

== Description ==

* count reads and visitors
* shows reads per page
* shows visitors today, yesterday, last week, last months and other statistics on dashboard
* shows country of your visitors
* you can show these statistics on frontend per widget or shortcodes too
* Plugin: http://www.tomsdimension.de/wp-plugins/count-per-day
* Donate: http://www.tomsdimension.de/postcards

"Count per Day" counts 1 visit per IP per day. So any reload of the page do not increment the counter.

= Languages, Translators =

- up to date translations:
- Dutch NL		- Rene -							http://wpwebshop.com
- France		- Bjork -							http://www.habbzone.fr
- German		- I, Tom -							http://www.tomsdimension.de
- Italian		- Gianni Diurno -					http://gidibao.net
- Japanese		- Juno Hayami						http://juno.main.jp/blog/
- Norwegian		- Stein Ivar Johnsen -				http://iDyrøy.no
- Portuguese	- Beto Ribeiro -					http://www.sevenarts.com.br
- Russian		- Ilya Pshenichny -					http://iluhis.com
- Turkish		- Emrullah Tahir Ekmek&ccedil;i - 	http://emrullahekmekci.com.tr

- older translations:
- Azerbaijani	- Bohdan Zograf -					http://wwww.webhostingrating.com
- Belarusian	- Alexander Alexandrov -			http://www.designcontest.com
- Bulgarian		- joro -							http://www.joro711.com
- Dansk			- Jonas Thomsen -					http://jonasthomsen.com
- Espanol		- Juan Carlos del R&iacute;o -
- Greek			- Essetai_Imar -					http://www.elliniki-grothia.com
- Lithuanian	- Nata Strazda - 					http://www.webhostinghub.com
- Norwegian		- Tore Johnny Bråtveit -			http://www.braatveit.net/
- Polish 		- LeXuS -							http://intrakardial.de
- Swedish		- Magnus Suther -					http://www.magnussuther.se
- Ukrainian		- Alyona Lompar -

== Installation ==

1. unzip plugin directory into the '/wp-content/plugins/' directory
1. activate the plugin through the 'Plugins' menu in WordPress
1. after every update you have to deactivate and reactivate the plugin to update some settings!

The activation will create or update a table wp_cpd_counter.

The Visitors-per-Day function use 7 days as default. So don't surprise about a wrong value in the first week.

**Configuration**

See the Options Page and check the default values.

== Frequently Asked Questions ==

= Need Help? Find Bug? =

read and write comments on http://www.tomsdimension.de/wp-plugins/count-per-day

== Screenshots ==

1. Statistics on Count-per-Day Dashboard
2. Options
3. Widget sample

== Arbitrary section ==

**Shortcodes**

You can use these shortcodes in the content of your posts to show a number or list
or in your theme files while adding e.g. '&lt;?php echo do_shortcode("[THE_SHORTCODE]"); ?>'.

[CPD_READS_THIS]
[CPD_READS_TOTAL]
[CPD_READS_TODAY]
[CPD_READS_YESTERDAY]
[CPD_READS_LAST_WEEK]
[CPD_READS_THIS_MONTH]
[CPD_READS_PER_MONTH]
[CPD_VISITORS_TOTAL]
[CPD_VISITORS_ONLINE]
[CPD_VISITORS_TODAY]
[CPD_VISITORS_YESTERDAY]
[CPD_VISITORS_LAST_WEEK]
[CPD_VISITORS_THIS_MONTH]
[CPD_VISITORS_PER_MONTH]
[CPD_VISITORS_PER_DAY]
[CPD_VISITORS_PER_POST]
[CPD_FIRST_COUNT]
[CPD_MOST_VISITED_POSTS]
[CPD_POSTS_ON_DAY]
[CPD_CLIENTS]
[CPD_COUNTRIES]
[CPD_REFERERS]
[CPD_POSTS_ON_DAY date="2010-10-06" limit="3"]
- date (optional), format: year-month-day, default = today
- limit (optional): max records to show, default = all 
[CPD_MAP width="500" height="340" what="reads" min=1]
- width and height: size, default 500x340 px
- what: map content - reads|visitors|online, default reads
- min: 1 (disable title, legend and zoombar), default 0

**Functions**

You can place these functions in your template.
Use
<code>&lt;?php
global $count_per_day;
if(method_exists($count_per_day,"show")) echo $count_per_day->getReadsAll(true);
?></code>
to check if plugin is activated.

'show( $before, $after, $show, $count, $page )'

* $before = text before number e.g. '&lt;p&gt;' (default "")
* $after = text after number e.g. 'reads&lt;/p&gt;' (default " reads")
* $show = true/false, "echo" complete string or "return" number only (default true)
* $count = true/false, false will not count the reads (default true)
* $page (optional) PostID

'count()'

* only count reads, without any output
* 'show' call it

'getFirstCount( $return )'

* shows date of first count
* $return: 0 echo, 1 return output

'getUserPerDay( $days, $return )'

* shows average number of visitors per day of the last _$days_ days
* default on dashboard (see it with mouse over number) = "Latest Counts - Days" in options
* $return: 0 echo, 1 return output

'getReadsAll( $return )'
 
* shows number of total reads
* $return: 0 echo, 1 return output

'getReadsToday( $return )'

* shows number of reads today
* $return: 0 echo, 1 return output

'getReadsYesterday( $return )'
 
* shows number of reads yesterday
* $return: 0 echo, 1 return output

'getReadsLastWeek( $return )'

* shows number of reads last week (7 days)
* $return: 0 echo, 1 return output

'getReadsThisMonth( $return )'

* shows number of reads current month
* $return: 0 echo, 1 return output

'getReadsPerMonth( $return )'

* lists number of reads per month
* $return: 0 echo, 1 return output

'getUserAll( $return )'

* shows number of total visitors
* $return: 0 echo, 1 return output

'getUserOnline( $frontend, $country, $return )'

* shows number of visitors just online
* $frontend: 1 no link to map
* $country: 0 number, 1 country list
* $return: 0 echo, 1 return output

'getUserToday( $return )'

* shows number of visitors today
* $return: 0 echo, 1 return output

'getUserYesterday( $return )'
 
* shows number of visitors yesterday
* $return: 0 echo, 1 return output

'getUserLastWeek( $return )'

* shows number of visitors last week (7 days)
* $return: 0 echo, 1 return output

'getUserThisMonth( $return )'

* shows number of visitors current month
* $return: 0 echo, 1 return output

'getUserPerMonth( $frontend, $return )'

* lists number of visitors per month
* $frontend: 1 no links
* $return: 0 echo, 1 return output

'getUserPerPost( $limit, $frontend, $return )'

* lists _$limit_ number of posts, -1: all, 0: get option from DB, x: number
* $frontend: 1 no links
* $return: 0 echo, 1 return output

'getMostVisitedPosts( $days, $limits, $frontend, $postsonly, $return )'

* shows a list with the most visited posts in the last days
* $days = days to calc (last days), 0: get option from DB
* $limit = count of posts (last posts), 0: get option from DB
* $frontend: 1 no links
* $postsonly: 0 show, 1 don't show categories and taxonomies
* $return: 0 echo, 1 return output

'getVisitedPostsOnDay( $date, $limit, $show_form, $show_notes, $frontend, $return )'

* shows visited pages at given day
* $date day in MySQL date format yyyy-mm-dd, 0 today
* $limit count of posts
* $show_form show form for date selection, default on, in frontend set it to 0
* $show_notes show button to add notes in form, default on, in frontend set it to 0
* $frontend: 1 no links
* $return: 0 echo, 1 return output

'getClients( $return )'

* shows visits per client/browser in percent
* $return: 0 echo, 1 return output

'getReferers( $limit, $return, $days )'

* lists top _$limit_ referrers of the last $days days, 0: get option from DB, x: number
* $return: 0 echo, 1 return output

'getMostVisitedPostIDs( $days, $limit, $cats, $return_array )'

* $days last x days, default = 365
* $limit return max. x posts, default = 10
* $cats IDs of categories to filter, array or number
* $return_array true returns an array with Post-ID, title and count, false returns comma separated list of Post-IDs

'function getMap( $what, $width, $height, $min )'

* gets a world map
* $what visitors|reads|online
* $width size in px
* $height size in px
* $min : 1 disable title, legend and zoombar

'getDayWithMostReads( $return )'

* shows day with most Reads
* $return: 0 echo, 1 return output

'getDayWithMostVisitors( $return )'

* shows day with most Visitors
* $return: 0 echo, 1 return output

**GeoIP**

* With GeoIP you can associate your visitors to an country using the ip address.
* In the database a new column 'country' will be insert on plugin activation.
* On options page you can update you current visits. This take a while! The Script checks 100 IP addresses at once an reload itself until less then 100 addresses left. Click the update button to check the rest.
* If the rest remains greater than 0 the IP address is not in GeoIP database (accuracy 99.5%).
* You can update the GeoIP database from time to time to get new IP data. This necessitates write rights to geoip directory (e.g. chmod 777).
* If the automatically update don't work download <a href="http://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz">GeoIP.dat.gz</a> and extract it to the "geoip" directory.
* More information about GeoIP on http://www.maxmind.com/app/geoip_country

== Changelog ==

= 3.1.1 Security update =
+ Bugfix: important fixes in map.php and download.php, thanks to http://6scan.com

= 3.1 =
+ New: memory check before backup to avoid "out of memory" error
+ New: create temporary backup files for download only
+ New: delete backup files in wp-content on settings page
+ Bugfix: all posts shows 1 read in posts list
+ Bugfix: clean database shows 0 entries deleted

= 3.0 =
+ New: use now default WordPress database functions to be compatible to e.g. multi-db plugins
+ New: backup your counter data
+ New: collect entries of counter table per month and per post to reduce the database and speed up the statistics
+ New: functions and shortcodes [CPD_DAY_MOST_READS] [CPD_DAY_MOST_USERS] to shows days with most reads/visitors
+ New: option to cut referrer on "?" to not store query strings
+ New: parameter '$postsonly' for 'getMostVisitedPosts' function to list single posts and pages only
+ New: flags for Moldavia and Nepal
+ New language: Norwegian, thanks to Stein Ivar Johnsen and Tore Johnny Bråtveit
+ New language: Azerbaijani, thanks to Bohdan Zograf
+ New language: Japanese, thanks to Juno Hayami
+ Bugfix: visitors per month list
+ Change: some function parameters
+ Change: little memory optimizing
+ Change: visitors currently online and notes will now managed per option, without seperate tables in database
+ Change: design updated
+ Change: old bar charts deleted

= 2.17 =
+ Bugfix: JavaScript error on dashboard page, boxes not movable
+ Bugfix: PHP4 compatibility
+ New: function and shortcode to show world map in frontend
+ New: option - who is allowed to see the statistics page
+ New language: Turkish, thanks to Emrullah Tahir Ekmek&ccedil;i

= 2.16.1 =
+ Bugfix: widget was empty

= 2.16 =
+ New: more modern charts (jQuery flot plugin)
+ New: widgets now sortable
+ New: GeoIP database included, non extra download after plugin update necessary
+ New: list "Visitors online" per country
+ New: option to limit the referrers list
+ New: option to not load stylesheet in frontend 
+ New: function 'getMostVisitedPostIDs', can create a "related posts" list
+ Bugfix: GeoIP functions renamed, conflicts with other plugins
+ New Language: Greek, thanks to Essetai_Imar

= 2.15.1 =
+ Bugifx: error in "Visitors per month" counter

= 2.15 =
+ New: functions and shortcodes [CPD_READS_THIS_MONTH] [CPD_VISITORS_THIS_MONTH]
+ New: reads last week, reads this month and visitors this month in widget
+ Bugfix: Ajax counter for cached pages is now multi widget compatible
+ Language updates: Polish, Russia

= 2.14 =
+ New: multi widget compatible, place the widget several times with individual settings
+ New: WordPress Multisite compatible, networkwide activation creates tables in all blogs
+ New: list reads per month  
+ New: functions and shortcodes [CPD_POSTS_ON_DAY] [CPD_READS_PER_MONTH] [CPD_READS_LAST_WEEK]
+ New: show/hide local referrers
+ New: optional deactivation of saving clients and referrers to save space in the database
+ New: debug mode per URL parameter (?debug=1)
+ Bugfix: GeoIP database update, problem with local IP adresses
+ Bugfix: Userlevel/Capabilities
+ Bugfix: yesterday reads and visitors (timezone)
+ Bugfix: links on mass bots page
+ little cosmetics
+ Language update: Italian

= 2.13.1 =
+ New Language: Espanol, thanks to Juan Carlos del R&iacute;o
+ Bugfix: problems with MySQL 4.x
+ Bugfix: changed error handling

= 2.13 =
+ New: Top referrers
+ Bugfix: Thickbox only in backend needed, RTL on he_IL was broken
+ Bugfix: startpage was not counted everywhere
+ Language update: Portuguese (Brazil)

= 2.12 =
+ New: Flags images as sprite included
+ New: improved "Browsers" management, set your own favorites
+ New: improved "Mass Bots" management, more infos
+ New: "Visitors per country" list
+ New: "Visitors per day" list/chart
+ New: works now in cached pages too (optional, BETA)
+ New: easier switch to debug mode on settings
+ Language update: Dansk, Dutch, France, German, Italian, Russian, Swedish 
+ Bugfix: CleanDB delete by IP function changed
+ Bugfix: because windows symlink problem plugin dir is hardcoded as 'count-per-day' now
+ Code updated (deprecated functions)

= 2.11 =
+ Bugfix: GeoIP, update old data used wrong IP format
+ Bugfix: CleanDB deletes to many entries (index, categories, tags)
+ Bugfix: date/timezone problem
+ New: anonymous IP addresses (last bit, optional)
+ New: simple scroll function in charts
+ New language: Polish, thanks to LeXuS

= 2.10.1 =
+ New language: Dutch, thanks to Rene

= 2.10 =
+ New language: French, thanks to Bjork
+ New: Worldmap to visualize visitors per country
+ New: Shortcodes to add lists and charts to posts and pages, check counter.css too
+ Bugfix: mysql_fetch_assoc() error, non existing options
+ Post edit links in lists for editors only (user_level >= 7)

= 2.9 =
+ New: little note system to mark special days
+ New: functions to get reads/page views total, today and yesterday
+ Language update: Italian, thanks to Gianni Diurno
+ Language update: Portuguese (Brazil), thanks to Lucato
+ Language update: Swedish, thanks to Magnus Suther
+ Language update: Dansk, thanks to Jonas Thomsen
+ Language update: German

= 2.8 =
+ New: set user level until CpD will count logged users
+ New: link to plugin page on Count per Day dashboard
+ New: click on a bar in the charts reload the page with given date for 'Visitors per day' metabox
+ New language: Swedish, thanks to Magnus Suther
+ New language: Dansk, thanks to GeorgeWP

= 2.7 =
+ Bugfix: date/timezone problem
+ New: change start date and start count on option page
+ New: "edit post" links on lists
+ New: new list shows visitors per post on user defined date  
+ New: link to plugin page

= 2.6 =
+ languages files now compatible with Wordpress 2.9
+ New: improved CSS support for RTL blogs (e.g. arabic)

= 2.5 =
+ BACKUP YOUR COUNTER DATABASE BEFORE UPDATE!
+ Change: some big changes on database and functions to speed up mysql queries. This will take a while on activation! 
+ New: "Mass Bot Detector" shows and deletes clients that view more than x pages per day
+ New: see count and time of queries if CPD_DEBUG is true (on top of counter.php)
+ Bugfix: cleanDB by IP now works
+ Language update: Portuguese (Brazil), thanks to Beto Ribeiro

= 2.4.2 =
+ Bugfix: mysql systax error
+ Bugfix: no country data was stored (GeoIP), use "Update old counter data" on options page

= 2.4 =
+ Bugfix: works with PHP 4.x again (error line 169)
+ Change: some functions now faster
+ New: GeoIP included. You have to load GeoIP.dat file on option page before you can use it.
+ Language updates: Italian (Gianni Diurno) and German

= 2.3.1 =
+ Bugfix: counter do not work without GeoIP Addon (nonexisting row 'country' in table)

= 2.3 =
+ New: chart "visitors per day"
+ New: counts index pages: homepage, categories, tags (if autocount is on)
+ New: visits per client/browser in percent
+ New: added some parameters to functions to overwrite default values
+ New language: Usbek, thanks to Alisher

= 2.2 =
+ Change: USER_AGENT must have > 20 chars, otherwise we call it "bot"
+ New: optional GeoIP addon to show page views per country - see Section "GeoIP addon"

= 2.1 =
+ New: custom names on widget
+ New: function "first count" on widget
+ little changes on german translation

= 2.0 =
+ New: sidebar widget
+ New: reset button to set all counter to 0
+ New: custom number of "reads per post" on dashboard page
+ New: little chart of "reads per day" on dashboard page
+ New: reads in post and page lists (optional)
+ New: most visited posts in last days on dashboard page
+ New: recognize bots by IP address
+ New: movable metaboxes on dashboard page
+ New: clean function now deletes counter of deleted pages too
+ Bugfix: updates online counter on every load
+ Bugfix: now empty user agents/clients will not be count
+ change options to array
+ create class, update/clean up/rename functions

= 1.5.1 =
+ New language: Belorussian, thanks to Marcis Gasuns

= 1.5 =
+ New: Dashboard Widget
+ WP 2.7 optimized, for WP<2.7 please use CPD 1.4 

= 1.4 =
+ New: uninstall function of WP 2.7 implemented
+ litle changes on layout to be suitable for WP 2.7

= 1.3 =
+ New: you can delete old data if you add a new bot string
+ Bugfix: Bot check was case-sensitive
+ New language: Portuguese, thanks to Filipe

= 1.2.3 =
+ Bugfix: autocount endless looping

= 1.2.2 =
+ New language: Italian, thanks to Gianni Diurno

= 1.2.1 =
+ Bugfix: Error 404 "Page not found" with "auto count"

= 1.2 =
+ Bugfix: tables in DB were not be created every time (seen on mysql < 5)
+ New: "auto count" can count visits without changes on template

= 1.1 =
+ Languages: english, german 
+ HTTP_USER_AGENT will be saved, identification of new search bots
+ Stylesheet in file counter.css

= 1.0 =
+ first release