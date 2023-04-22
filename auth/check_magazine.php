<?php

include('/usr/src/app/MyUtils.php');

$log = new Log();
$mu = new MyUtils();

$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START ${requesturi}");

check_magazine();

$log->info('FINISH ' . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function check_magazine()
{
    global $mu;
    global $log;
    $log->info('BEGIN');
    
    clearstatcache();
    if (!file_exists('/tmp/sqlite.db')) {
        init_sqlite();
    }
    
    $pdo_sqlite = new PDO('sqlite:/tmp/sqlite.db');
    
    if ($pdo_sqlite->query("SELECT COUNT('X') FROM sqlite_master WHERE TYPE='table' AND name='m_magazine_data'")->fetchColumn() == '0') {
        init_sqlite();
    }
    
    $pdo_sqlite = null;
    
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
    
    $pdo_sqlite = new PDO('sqlite:/tmp/sqlite.db');
    
    $statement_select = $pdo_sqlite->prepare($sql_select);
    $rc = $statement_select->execute();
    $results = $statement_select->fetchAll();
    
    $time_loop_start = time();
    foreach ($results as $row) {
        $log->info('m_magazine_data select result : ' . $row['symbol'] . ' ' . $row['title'] . ' ' . $row['bibid']);
        
        access_library($pdo_sqlite, $row['symbol'], $row['title'], $row['bibid']);
        if ((time() - $time_loop_start) > 20) {
            break;
        }
    }
    
    $sql_select = <<< __HEREDOC__
SELECT COUNT('X')
  FROM m_magazine_data M1
 WHERE M1.reserve = 1
__HEREDOC__;
    
    if ($pdo_sqlite->query($sql_select)->fetchColumn() != 0) {
        $options = [
            CURLOPT_USERPWD => $_ENV['BASIC_USER'] . ':' . $_ENV['BASIC_PASSWORD'],
        ];
        $mu->get_contents('https://' . $_ENV['RENDER_EXTERNAL_HOSTNAME'] . '/auth/reserve_magazine.php', $options);
    }

    $pdo_sqlite = null;
}

function access_library($pdo_sqlite_, $symbol_, $title_, $bibid_last_)
{
    global $mu;
    global $log;
    $log->info('BEGIN');

    $cookie = '/tmp/cookie' . basename(__FILE__);
    clearstatcache();
    @unlink($cookie);
    
    $options = [
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
    ];

    $res = $mu->get_contents($mu->get_env('LIB_URL_01'), $options);
    
    $post_data = [
        'cmb_column1' => 'title',
        'txt_word1' => $title_,
        'cmb_like1' => '2',
        'cmb_unit1' => '0',
        'cmb_column2' => 'author',
        'txt_word2' => '',
        'cmb_like2' => '2',
        'cmb_unit2' => '0',
        'cmb_column3' => 'publisher',
        'txt_word3' => '',
        'cmb_like3' => '2',
        'cmb_unit3' => '0',
        'cmb_column4' => 'subject',
        'txt_word4' => '',
        'cmb_like4' => '2',
        'cmb_unit4' => '0',
        'selectlang' => '',
        'cmb_column5' => 'langkb',
        'txt_word5' => '',
        'cmb_like5' => '2',
        'cmb_unit5' => '0',
        'txt_ndc' => '',
        'txt_ndcword' => '',
        'txt_stpubdate' => '',
        'txt_edpubdate' => '',
        'cmb_volume_column' => 'volume',
        'txt_stvolume' => '',
        'txt_edvolume' => '',
        'cmb_code_column' => 'isbn',
        'txt_code' => '',
        'txt_lom' => '',
        'txt_cln1' => '',
        'txt_cln2' => '',
        'txt_cln3' => '',
        'chk_hol1tp' => '40',
        'cmb_order' => 'pubYear',
        'opt_order' => '1',
        'opt_pagesize' => '25',
        'submit_btn_searchDetailSelAr' => '検索',
    ];
    
    $chk_area = '';
    for ($i = 0; $i < 13; $i++) {
        $chk_area .= '&chk_area=' . str_pad($i + 1, 2, '0', STR_PAD_LEFT);
    }
    $options = [
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post_data) . $chk_area,
    ];
    
    $res = $mu->get_contents($mu->get_env('LIB_URL_01'), $options);
        
    $rc = preg_match_all('/<a href="\/winj\/opac\/switch-detail\.do\?idx=.+?<\/a>/s', $res, $matches);
    
    $idx = -1;
    foreach ($matches[0] as &$line) {
        $rc = preg_match('/<a href="\/winj\/opac\/switch-detail\.do\?idx=(\d+).+?>' . $title_ . '</s', $line, $match);
        if ($rc === 0) {
            continue;
        }
        // $log->info(print_r($match, true));
        $idx = $match[1];
    }
    
    $options = [
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
    ];
    
    if ($idx != -1) {
        $res = $mu->get_contents($mu->get_env('LIB_URL_02') . $idx, $options);
    }
    
    $res = $mu->get_contents($mu->get_env('LIB_URL_03'), $options);
    
    $rc = preg_match('/<input type="hidden" name="bibid" value="(.+?)"/', $res, $match);
    $bibid = $match[1];
    $log->info('bibid : ' . $bibid);
    
    if ($bibid == '') {
        $log->warn("bibid not found : ${title_}");
        return;
    }
    
    $reserve = 0;
    if ($bibid != $bibid_last_) {
        $reserve = 1;
        $mu->send_slack_message('変更有り ' . $title_);
    }
    
    $sql_update = <<< __HEREDOC__
UPDATE m_magazine_data
   SET bibid = :b_bibid
      ,reserve = :b_reserve
      ,check_datetime = :b_check_datetime
 WHERE symbol = :b_symbol
   AND title = :b_title
__HEREDOC__;
    
    $statement_update = $pdo_sqlite_->prepare($sql_update);
    $rc = $statement_update->execute([
        ':b_bibid' => $bibid,
        ':b_reserve' => $reserve,
        ':b_check_datetime' => date('Y/m/d H:i:s'),
        ':b_symbol' => $symbol_,
        ':b_title' => $title_,
    ]);
    
    if ($reserve == 0) {
        return;
    }
    
    $sql_update = <<< __HEREDOC__
UPDATE m_magazine_data
   SET bibid = :b_bibid
      ,reserve = 1
      ,update_datetime = ADDTIME(NOW(), '9:0')
      ,check_datetime = ADDTIME(NOW(), '9:0')
 WHERE symbol = :b_symbol
   AND title = :b_title
__HEREDOC__;
    
    $pdo = $mu->get_pdo();
    
    $statement_update = $pdo->prepare($sql_update);
    $rc = $statement_update->execute([
        ':b_bibid' => $bibid,
        ':b_symbol' => $symbol_,
        ':b_title' => $title_,
    ]);
    
    $pdo = null;
}

function init_sqlite()
{
    global $mu;
    global $log;
    $log->info('BEGIN');
    
    $pdo_sqlite = new PDO('sqlite:/tmp/sqlite.db');
    
    $log->info('SQLite Version : ' . $pdo_sqlite->query('SELECT sqlite_version()')->fetchColumn());
    
    $sql_create = <<< __HEREDOC__
CREATE TABLE m_env (
 key_name TEXT,
 value TEXT,
 encrypt INTEGER
)
__HEREDOC__;

    $rc = $pdo_sqlite->exec($sql_create);
    $log->info('m_env create table result : ' . $rc);
    
    $sql_insert = <<< __HEREDOC__
INSERT INTO m_env VALUES(:b_key_name, :b_value, :b_encrypt)
__HEREDOC__;

    $statement_insert = $pdo_sqlite->prepare($sql_insert);

    $pdo = $mu->get_pdo();
    
    $log->info('MySQL Version : ' . $pdo->query('SELECT version()')->fetchColumn());
    
    $sql_select = <<< __HEREDOC__
SELECT M1.key_name
      ,M1.value
      ,M1.encrypt
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
            ':b_encrypt' => $row['encrypt'],
        ]);
        $log->info('m_env insert result : ' . $statement_insert->rowCount() . ' ' . $row['key_name']);
    }

    $pdo = null;
    $pdo_sqlite = null;
    
    $pdo_sqlite = new PDO('sqlite:/tmp/sqlite.db');
    
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

    $pdo = $mu->get_pdo();
    
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
    
    $pdo_sqlite = new PDO('sqlite:/tmp/sqlite.db');
    
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

    $pdo = $mu->get_pdo();
    
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
