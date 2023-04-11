<?php

include('/usr/src/app/log.php');

$log = new Log();

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START ${requesturi}");

check_magazine($log);

$log->info('FINISH ' . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function check_magazine()
{
    global $log;
    $log->info('BEGIN');
    
    clearstatcache();
    if (!file_exists('/tmp/m_env.db')) {
        init_sqlite();
    }
    
    $sql_select = <<< __HEREDOC__
SELECT M1.symbol
      ,M1.title
      ,M1.bibid
      ,M1.reserve
      ,M1.check_datetime
  FROM m_magazine_data M1
 WHERE M1.reserve = 0
 ORDER BY M1.check_datetime
__HEREDOC__;
    
    $pdo_sqlite = new PDO('sqlite:/tmp/m_magazine_data.db');
    
    $statement_select = $pdo_sqlite->prepare($sql_select);
    $rc = $statement_select->execute();
    $results = $statement_select->fetchAll();
    
    foreach ($results as $row) {
        $log->info('m_magazine_data select result : ' . $row['symbol'] . ' ' . $row['title']);
        $symbol = $row['symbol'];
        $title = $row['title'];
        $bibid_last = $row['bibid'];

        $option = [CURLOPT_COOKIEJAR => '/tmp/cookie',
                  CURLOPT_COOKIEFILE => '/tmp/cookie',];

        $url = get_env('LIB_URL_01');
        $res = get_contents($url, $option);
    }
    
    
    $pdo_sqlite = null;
}

function get_pdo()
{
    global $log;
    $log->info('BEGIN');
    
    $dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']}";
    $options = array(
      PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
    );
    return new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $options);
}

function init_sqlite()
{
    global $log;
    $log->info('BEGIN');
    
    $pdo_sqlite = new PDO('sqlite:/tmp/m_env.db');
    
    $log->info('SQLite Version : ' . $pdo_sqlite->query('SELECT sqlite_version()')->fetchColumn());
    
    $sql_create = <<< __HEREDOC__
CREATE TABLE m_env (
 key_name TEXT,
 value TEXT
)
__HEREDOC__;

    $rc = $pdo_sqlite->exec($sql_create);
    $log->info('m_env create table result : ' . $rc);
    
    $sql_insert = <<< __HEREDOC__
INSERT INTO m_env VALUES(:b_key_name, :b_value)
__HEREDOC__;

    $statement_insert = $pdo_sqlite->prepare($sql_insert);

    $pdo = get_pdo();
    
    $log->info('MySQL Version : ' . $pdo->query('SELECT version()')->fetchColumn());
    
    $sql_select = <<< __HEREDOC__
SELECT M1.key_name
      ,M1.value
  FROM m_env M1
 ORDER BY M1.key_name
__HEREDOC__;

    $statement_select = $pdo->prepare($sql_select);
    $rc = $statement_select->execute();
    $results = $statement_select->fetchAll();
    
    foreach ($results as $row) {
        $statement_insert->execute([
            ':b_key_name' => $row['key_name'],
            ':b_value' => $row['value'],
        ]);
        $log->info('m_env insert result : ' . $statement_insert->rowCount() . ' ' . $row['key_name']);
    }

    $pdo = null;
    $pdo_sqlite = null;
    
    $pdo_sqlite = new PDO('sqlite:/tmp/m_lib_account.db');
    
    $sql_create = <<< __HEREDOC__
CREATE TABLE m_lib_account (
 lib_id TEXT,
 lib_password TEXT,
 symbol TEXT
)
__HEREDOC__;

    $rc = $pdo_sqlite->exec($sql_create);
    $log->info('m_lib_account create table result : ' . $rc);
    
    $sql_insert = <<< __HEREDOC__
INSERT INTO m_lib_account VALUES(:b_lib_id, :b_lib_password, :b_symbol)
__HEREDOC__;

    $statement_insert = $pdo_sqlite->prepare($sql_insert);

    $pdo = get_pdo();
    
    $sql_select = <<< __HEREDOC__
SELECT M1.lib_id
      ,M1.lib_password
      ,M1.symbol
  FROM m_lib_account M1
 ORDER BY M1.symbol
__HEREDOC__;

    $statement_select = $pdo->prepare($sql_select);
    $rc = $statement_select->execute();
    $results = $statement_select->fetchAll();
    
    foreach ($results as $row) {
        $statement_insert->execute([
            ':b_lib_id' => $row['lib_id'],
            ':b_lib_password' => $row['lib_password'],
            ':b_symbol' => $row['symbol'],
        ]);
        $log->info('m_lib_account insert result : ' . $statement_insert->rowCount() . ' ' . $row['symbol']);
    }

    $pdo = null;
    $pdo_sqlite = null;
    
    $pdo_sqlite = new PDO('sqlite:/tmp/m_magazine_data.db');
    
    $sql_create = <<< __HEREDOC__
CREATE TABLE m_magazine_data (
 symbol TEXT,
 title TEXT,
 bibid TEXT,
 reserve INTEGER,
 check_datetime TEXT
)
__HEREDOC__;

    $rc = $pdo_sqlite->exec($sql_create);
    $log->info('m_magazine_data create table result : ' . $rc);
    
    $sql_insert = <<< __HEREDOC__
INSERT INTO m_magazine_data VALUES(:b_symbol, :b_title, :b_bibid, :b_reserve, NULL)
__HEREDOC__;

    $statement_insert = $pdo_sqlite->prepare($sql_insert);

    $pdo = get_pdo();
    
    $sql_select = <<< __HEREDOC__
SELECT M1.symbol
      ,M1.title
      ,M1.bibid
      ,M1.reserve
  FROM m_magazine_data M1
 ORDER BY M1.symbol
         ,M1.title
__HEREDOC__;

    $statement_select = $pdo->prepare($sql_select);
    $rc = $statement_select->execute();
    $results = $statement_select->fetchAll();
    
    foreach ($results as $row) {
        $statement_insert->execute([
            ':b_symbol' => $row['symbol'],
            ':b_title' => $row['title'],
            ':b_bibid' => $row['bibid'],
            ':b_reserve' => $row['reserve'],
        ]);
        $log->info('m_magazine_data insert result : ' . $statement_insert->rowCount() . ' ' . $row['symbol'] . ' ' . $row['title']);
    }

    $pdo = null;
    $pdo_sqlite = null;
}

function get_env($key_name_)
{
    global $log;
    $log->info('BEGIN');
    
    $pdo_sqlite = new PDO('sqlite:/tmp/m_env.db');
    
    $sql_select = <<< __HEREDOC__
SELECT M1.value
  FROM m_env M1
 WHERE M1.key_name = :b_key_name
__HEREDOC__;

    $statement_select = $pdo_sqlite->prepare($sql_select);
    $statement_select->bindValue(':b_key_name', $key_name_);
    $rc = $statement_select->execute();
    $results = $statement_select->fetchAll();
    
    $value = '';
    foreach ($results as $row) {
        $value = $row['value'];
    }
    
    $pdo_sqlite = null;
    
    return $value;
}

function get_contents($url_, $options_ = null)
{
    global $log;
    $log->info("URL : ${url_}");

    $options = [
        CURLOPT_URL => $url_,
        // CURLOPT_USERAGENT => getenv('USER_AGENT'),
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:111.0) Gecko/20100101 Firefox/111.0',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_PATH_AS_IS => true,
        CURLOPT_TCP_FASTOPEN => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
        CURLOPT_TIMEOUT => 25,
    ];

    if (is_null($options_) === false && array_key_exists(CURLOPT_USERAGENT, $options_)) {
        unset($options[CURLOPT_USERAGENT]);
    }

    $time_start = 0;
    $time_finish = 0;
    $time_start = microtime(true);
    $ch = curl_init();
    foreach ($options as $key => $value) {
        $rc = curl_setopt($ch, $key, $value);
        if ($rc == false) {
            $log->info("curl_setopt : ${key} ${value}");
        }
    }
    if (is_null($options_) === false) {
        foreach ($options_ as $key => $value) {
            $rc = curl_setopt($ch, $key, $value);
            if ($rc == false) {
                $log->info("curl_setopt : ${key} ${value}");
            }
        }
    }
    $res = curl_exec($ch);
    $time_finish = microtime(true);
    $http_code = (string)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $log->info("HTTP STATUS CODE : ${http_code} [" .
                substr(($time_finish - $time_start), 0, 5) . 'sec] ' .
                parse_url($url_, PHP_URL_HOST) .
                ' [' . number_format(strlen($res)) . 'byte]'
               );
    curl_close($ch);

    return $res;
}
