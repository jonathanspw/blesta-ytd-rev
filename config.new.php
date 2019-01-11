<?php

// define mysql user
define('MYSQL_USER','');
// define mysql database
define('MYSQL_DATABASE','blesta');
// define mysql host
define('MYSQL_HOST','localhost');
// define db user password
define('MYSQL_PASSWORD','');

//combine groups - in case we want to combine multiple groups in reports
define('COMBINE_GROUPS',array(array(),array()));

//calculate income from configurable option
//strings in the values array must be double then single quoted
//define('CALC_CONFIG_OPTS',
//    array(
//        ['name' => 'option_name1'],
//        [
//            'name' => 'option_name2',
//            'values' => ["'V'","'U'",1,2]
//        ]
//    )
//);

// set the email to address(s).  separate multiple by comma
define('EMAIL_TO','');
define('EMAIL_TITLE','Blesta YTD Revenue Statistics');