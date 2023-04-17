<?php

include('/usr/src/app/MyUtils.php');

$log = new Log();
$mu = new MyUtils();

$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START ${requesturi}");

reserve_magazine();

$log->info('FINISH ' . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function reserve_magazine()
{
    global $log;
    $log->info('BEGIN');
    
    $sql_select = <<< __HEREDOC__
SELECT M1.symbol
      ,M1.title
      ,M1.bibid
      ,M2.lib_id
      ,M2.lib_password
  FROM m_magazine_data M1
 INNER JOIN m_lib_account M2
    ON M2.symbol = M1.symbol
 WHERE M1.reserve = 1
 ORDER BY M1.check_datetime
__HEREDOC__;
    
    $pdo_sqlite = new PDO('sqlite:/tmp/sqlite.db');
    
    $statement_select = $pdo_sqlite->prepare($sql_select);
    $rc = $statement_select->execute();
    $results = $statement_select->fetchAll();
    
    foreach ($results as $row) {
        $log->info('m_magazine_data select result : ' . $row['symbol'] . ' ' . $row['title'] . ' ' . $row['bibid']);
        
        access_library2($pdo_sqlite, $row['symbol'], $row['title'], $row['bibid'], $row['lib_id'], $row['lib_password']);
    }
    
    $pdo_sqlite = null;
}

function access_library2($pdo_sqlite_, $symbol_, $title_, $bibid_, $lib_id_, $lib_password_)
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
    
    $res = $mu->get_contents($mu->get_env('LIB_URL_04') . $bibid_, $options);
    
    $rc = preg_match('/<input type="hidden" name="hid_session" value="(.+?)">/', $res, $match);
    $hid_session = $match[1];
    $rc = preg_match('/<input type="hidden" name="hid_vottp" value="(.+?)">/', $res, $match);
    $hid_vottp = $match[1];
    
    $post_data = [
        'hid_session' => $hid_session,
        'idx' => '',
        'revidx' => '',
        'hid_vottp' => $hid_vottp,
        'bibid' => $bibid_,
        'submit_btn_reserve_basket' => '予約かご',
    ];
    
    $options = [
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post_data),
    ];
    
    $res = $mu->get_contents($mu->get_env('LIB_URL_03'), $options);
    
    $post_data = [
        'txt_usercd' => $lib_id_,
        'txt_password' => $lib_password_,
        'submit_btn_login' => 'ログイン',
    ];
    
    $options = [
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post_data),
    ];
    
    $res = $mu->get_contents($mu->get_env('LIB_URL_05'), $options);
    
    $rc = preg_match('/<input type="hidden" name="hid_session" value="(.+?)">/', $res, $match);
    $hid_session = $match[1];
    $rc = preg_match('/<input type="hidden" name="hid_aplph" value="(.+?)">/', $res, $match);
    $hid_aplph = $match[1];
    $rc = preg_match('/<a href="JavaScript:otherWin\(\'(.+?)\'\);">/', $res, $match);
    $chk_check = $match[1];
    
    $post_data = [
        'hid_session' => $hid_session,
        'hid_aplph' => $hid_aplph,
        'chk_check' => $chk_check,
        'cmb_area' => '09',
        'cmb_ctttp' => '004',
        'chk_limit' => 'on',
        'submit_btn_reservation' => '通常予約',
    ];
    
    $options = [
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post_data),
    ];
    
    $res = $mu->get_contents($mu->get_env('LIB_URL_06'), $options);
    $log->warn($res);
    
    $rc = preg_match('/<input type="hidden" name="hid_session" value="(.+?)">/', $res, $match);
    $hid_session = $match[1];
    $rc = preg_match('/<input type="hidden" name="hid_aplph" value="(.+?)">/', $res, $match);
    $hid_aplph = $match[1];
    $rc = preg_match('/<select name="cmb_email" id="cmb_email" title=".+?"><option value="(.+?)" selected="selected">/', $res, $match);
    $cmb_email = $match[1];
    $rc = preg_match('/<span class="title">(.+?)<\/span>/s', $res, $match);
    $title = $match[1];
    
    $post_data = [
        'hid_session' => $hid_session,
        'hid_aplph' => $hid_aplph,
        'cmb_email' => $cmb_email,
        'submit_btn_confirm' => '予約',
    ];
    
    $options = [
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post_data),
    ];
    
    $res = $mu->get_contents($mu->get_env('LIB_URL_07'), $options);
    $log->info($title);
    
    if (strpos($res, '以下のタイトルについて予約を行いました。') === false) {
        $reserve = 2;
    } else {
        $reserve = 0;
    }
    
    $sql_update = <<< __HEREDOC__
UPDATE m_magazine_data
   SET reserve = :b_reserve
      ,check_datetime = :b_check_datetime
 WHERE symbol = :b_symbol
   AND title = :b_title
__HEREDOC__;
    
    $statement_update = $pdo_sqlite_->prepare($sql_update);
    $rc = $statement_update->execute([
        ':b_reserve' => $reserve,
        ':b_check_datetime' => date('YmdHis'),
        ':b_symbol' => $symbol_,
        ':b_title' => $title_,
    ]);
    
    $sql_update = <<< __HEREDOC__
UPDATE m_magazine_data
   SET reserve = :b_reserve
      ,update_datetime = ADDTIME(NOW(), '9:0')
 WHERE symbol = :b_symbol
   AND title = :b_title
__HEREDOC__;
    
    $pdo = $mu->get_pdo();
    
    $statement_update = $pdo->prepare($sql_update);
    $rc = $statement_update->execute([
        ':b_reserve' => $reserve,
        ':b_symbol' => $symbol_,
        ':b_title' => $title_,
    ]);
    
    $pdo = null;
}
