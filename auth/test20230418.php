<?php

include('/usr/src/app/MyUtils.php');

$log = new Log();
$mu = new MyUtils();

$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START ${requesturi}");

test20230418();

$log->info('FINISH ' . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function test20230418()
{
    global $log;
    $log->info('BEGIN');
    
    $sql_select[] = <<< __HEREDOC__
SELECT M1.title
      ,M1.reserve
      ,M1.check_datetime
  FROM m_magazine_data M1
 WHERE M1.reserve = 0
   AND M1.check_datetime = ''
__HEREDOC__;
    
    $sql_select[] = <<< __HEREDOC__
SELECT M1.title
      ,M1.reserve
      ,M1.check_datetime
  FROM m_magazine_data M1
 WHERE M1.reserve <> 0
__HEREDOC__;
    
    $sql_select[] = <<< __HEREDOC__
SELECT M1.title
      ,M1.reserve
      ,MIN(M1.check_datetime)
  FROM m_magazine_data M1
 WHERE M1.reserve = 0
   AND M1.check_datetime <> ''
 GROUP BY M1.check_datetime
 HAVING M1.check_datetime = MIN(M1.check_datetime)
__HEREDOC__;
    
    $pdo = new PDO('sqlite:/tmp/sqlite.db');

    for ($i = 0; $i < 3; $i++) {
        $statement_select = $pdo->prepare($sql_select[$i]);
        $rc = $statement_select->execute();
        $results = $statement_select->fetchAll();

        foreach ($results as $row) {
            $log->debug($i . ' ' . $row['reserve'] . ' ' .  $row['title'] . ' ' .  $row['check_datetime']);
        }
    }
    
    $pdo = null;
}
