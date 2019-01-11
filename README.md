This is a very simple quick and dirty script to send some stats about your Blesta install to an address or addresses of your choosing.  It currently will report the total year-to-date revenue per package group from Blesta, as well as the number of services where services.status='active'.

This only includes revenue that is directly tied to services.  It will not include manual invoicing or pro-rated upgrades/downgrades.

This was a quick and hacky script I wrote to fulfill a need.  If you'd like to see something included in it open up an issue and I'll see what I can do :)

# Installation

Installation is very simple.  You can simply clone this project into a location of your choosing.

    git clone https://github.com/neonardo1/blesta-ytd-rev.git
  
From here all you need to do is copy config.new.php to config.php and customize to your liking, then if you wish set it to run on a cron.

    0 8 * * * /usr/bin/php /path/to/cron.php
    
# Configuration Options

The error reporting settings and MySQL configuration lines should be self-explanatory.  The database information provided should be your Blesta database.

### COMBINE_GROUPS
This option can be used when you have a group (or groups) that have similar names/functions and for the sake of reporting you want to combine them.  When you do this, the first group hit from the numerically sequential list returned from the database becomes the parent group who's name is used and combined into.  It can be given an array of arrays as follows:

    define('COMBINE_GROUPS',array(array(7,11),array(20,21,22));

This will combine the group 11 into group 7, and groups 21 and 22 into group 20.

### CALC_CONFIG_OPTS
This option is a bit complicated and not really finished.  It was very hastily thrown together.  It its current state it attempts to break down the monthly recurring value of a configurable option in revenue.  **It does not take into account coupons at all.**

If you do not provide a "values" array to an option then it will assume a value of "1".  If you have multiple values that need to be calculated you need to include an array.  Make sure to follow the correct format for strings as this is passed directly to MySQL.  If you get the syntax wrong here the script will fail.

The intent is that you only list non-0 priced option values here.

### EMAIL_TO
Address(es) you want the email sent to.  Multiple addresses should be separated by a comma.

### EMAIL_SUBJECT
Subject of the email being sent.
