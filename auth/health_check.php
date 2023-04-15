<?php

include('/usr/src/app/MyUtils.php');

$log = new Log();
$mu = new MyUtils();

$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START ${requesturi}");

health_check();

$log->info('FINISH ' . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function health_check()
{
    global $log;
    $log->info('BEGIN');
    
    header("Content-Type: application/atom+xml");
  
    $atom = <<< __HEREDOC__
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
 <title>Health Check</title>
 <link href="http://example.org/"/>
 <updated>2022-01-01T00:00:00Z</updated>
 <author>
   <name>__FQDN__</name>
 </author>
 <id>tag:__FQDN__</id>
 <entry>
   <title>Health Check __UPDATED__</title>
   <link href="http://example.org/"/>
   <id>tag:__ID__</id>
   <updated>__UPDATED__</updated>
   <summary>__FQDN__ m_magazine_data __RECORD__</summary>
 </entry>
</feed>
__HEREDOC__;
    
    $sql_select = <<< __HEREDOC__
SELECT M1.title
      ,M1.reserve
      ,M1.check_datetime
  FROM m_magazine_data M1
 ORDER BY M1.reserve DESC
         ,M1.check_datetime
__HEREDOC__;
    
    $record = "\r\n";
    clearstatcache();
    if (file_exists('/tmp/sqlite.db')) {
        $pdo = new PDO('sqlite:/tmp/sqlite.db');
        
        $statement_select = $pdo->prepare($sql_select);
        $rc = $statement_select->execute();
        $results = $statement_select->fetchAll();

        foreach ($results as $row) {
            $record .= $row['reserve'] . ' ' .  $row['title'] . ' ' .  $row['check_datetime'] . "\r\n";
        }
        $pdo = null;
    }
    
    $atom = str_replace('__ID__', $_ENV['RENDER_EXTERNAL_HOSTNAME'] . '-' . uniqid(), $atom);
    $atom = str_replace('__FQDN__', $_ENV['RENDER_EXTERNAL_HOSTNAME'], $atom);
    $atom = str_replace('__UPDATED__', date('Y-m-d') . 'T' . date('H:i:s') . '+09', $atom);
    $atom = str_replace('__RECORD__', $record, $atom);

    echo $atom;
}
