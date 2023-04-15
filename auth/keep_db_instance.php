<?php

include('/usr/src/app/MyUtils.php');

$log = new Log();
$mu = new MyUtils();

$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START ${requesturi}");

keep_db_instance();

$log->info('FINISH ' . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function keep_db_instance()
{
    global $mu;
    global $log;
    $log->info('BEGIN');
  
    $pdo = $mu->get_pdo();
    
    $rc = $pdo->exec('TRUNCATE TABLE t_dummy');
    $log->info('t_dummy truncate result : ' . $rc);
    
    $rc = $pdo->exec('INSERT INTO t_dummy VALUES(1)');
    $log->info('t_dummy insert result : ' . $rc);
    
    $pdo = null;
}
