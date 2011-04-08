About
=====
Wolk is an attempt to implement some sort of syncing HTML5's localStorage across
multiple browsers with as little hassle as possible. The client could be
implemented as a javascript library or a browser extension, the server side is
written in PHP and should work on a simple shared hosting account.

Getting *all* the code
====================
Since this repository depends on another repository, namely [lightopenid](http://gitorious.org/lightopenid/), you will need to take some additional steps to get the submodules:

	git clone git://github.com/jelmervdl/wolk.git
	git submodule init
	git submodule update

Set up
======
Just copy `conf/db.php.default` to `conf/db.php` and provide your mysql credentials and a database name. Use `wolk.sql` to create the tables.

API
===
todo

Read data
---------
GET request.  
Required parameters: `api_key`  
Optional parameters: `namespace`, `since`

Sync data
---------
POST request
Required parameters: `api_key`  
Optional parameters: `namespace`, `since`  
Supply key-value-modified pairs as JSON in the request's body.

In addition to saving data in the wolk this request will return the same data as a GET request would have. This is by design so syncing can be done with a single request by pushing all the changes since the last sync and supplying the `since` parameter to receive all the changes you missed out on.

You will have to do the bookkeeping yourself (i.e. keep track of whether your version of a key-value-pair is newer than the received version) For this pushing part this is done by the database for you. So when you send old data, it will never overwrite newer data already in the wolk.