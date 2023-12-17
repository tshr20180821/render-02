<?php

include('/usr/src/app/MyUtils.php');

$log = new Log();
$mu = new MyUtils();

$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START {$requesturi}");

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
  <title>Health Check __FQDN__</title>
  <link href="http://example.org/"/>
  <updated>2022-01-01T00:00:00Z</updated>
  <author>
    <name>__FQDN__</name>
  </author>
  <id>tag:__FQDN__</id>
  <entry>
    <title>__DEPLOY_DATETIME__ Deployed</title>
    <link href="http://example.org/"/>
    <id>tag:__ID__</id>
    <updated>__UPDATED__</updated>
    <summary>Log Size : __LOG_SIZE__MB
__RECORD__
apt Check : __APT_RESULT__</summary>
  </entry>
</feed>
__HEREDOC__;
    
    $sql_select = <<< __HEREDOC__
SELECT M1.title
      ,M1.reserve
      ,M1.check_datetime
  FROM m_magazine_data M1
 WHERE M1.reserve = 0
   AND M1.check_datetime = ''
 UNION ALL
SELECT M1.title
      ,M1.reserve
      ,M1.check_datetime
  FROM m_magazine_data M1
 WHERE M1.reserve <> 0
 UNION ALL
SELECT M1.title
      ,M1.reserve
      ,M1.check_datetime
  FROM m_magazine_data M1
 INNER JOIN ( SELECT MIN(M2.check_datetime) check_datetime
                FROM m_magazine_data M2
               WHERE M2.reserve = 0
                 AND M2.check_datetime <> ''
            ) Q1
    ON Q1.check_datetime = M1.check_datetime
 WHERE M1.reserve = 0
   AND M1.check_datetime <> ''
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
    
    $file_size = 0;
    if (file_exists($_ENV['SQLITE_LOG_DB_FILE'])) {
        $file_size = filesize($_ENV['SQLITE_LOG_DB_FILE']) / 1024 / 1024;
    }
    
    $redis = new Redis();
    // UPSTASH_REDIS_URL : tlsv1.2://...
    $redis->connect(getenv('UPSTASH_REDIS_URL'), getenv('UPSTASH_REDIS_PORT'), 10, NULL, 0, 0, ['auth' => getenv('UPSTASH_REDIS_PASSWORD')]);
    $apt_result = $redis->get('APT_RESULT_' . getenv('RENDER_EXTERNAL_HOSTNAME'));
    $redis->close();
    
    $tmp = str_split($_ENV['DEPLOY_DATETIME'], 2);
    $atom = str_replace('__DEPLOY_DATETIME__', $tmp[0] . $tmp[1] . '-' . $tmp[2] . '-' . $tmp[3] . ' ' . $tmp[4] . ':' . $tmp[5] . ':' . $tmp[6], $atom);
    $atom = str_replace('__ID__', $_ENV['RENDER_EXTERNAL_HOSTNAME'] . '-' . uniqid(), $atom);
    $atom = str_replace('__FQDN__', $_ENV['RENDER_EXTERNAL_HOSTNAME'], $atom);
    $atom = str_replace('__UPDATED__', date('Y-m-d') . 'T' . date('H:i:s') . '+09', $atom);
    $atom = str_replace('__RECORD__', trim($record), $atom);
    $atom = str_replace('__LOG_SIZE__', number_format($file_size), $atom);
    $atom = str_replace('__APT_RESULT__', $apt_result, $atom);

    echo $atom;
}
