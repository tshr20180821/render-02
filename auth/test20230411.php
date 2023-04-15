<?php

include('/usr/src/app/log.php');
include('/usr/src/app/MyUtils.php');

$log = new Log();
$mu = new MyUtils();

$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START ${requesturi}");

test20230411a();

$log->info('FINISH ' . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function test20230411a()
{
    global $mu;
    global $log;
    $log->info('BEGIN');
    
    $mu->send_slack_message('機能');
}
