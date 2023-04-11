<?php

include('/usr/src/app/log.php');

$log = new Log();

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START ${requesturi}");

test20230411($log);

$log->info('FINISH ' . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function test20230411($log_)
{
    $log_->info('BEGIN');
    
    $option = [CURLOPT_COOKIEJAR => '/tmp/cookie',
              CURLOPT_COOKIEFILE => '/tmp/cookie',];
    
    $url = $_ENV['URL001'];
    $res = get_contents($log_, $url, $option);
    // $log_->info($res);
    
    $post_data = [
        'cmb_column1' => 'title',
        'txt_word1' => $_ENV['WORD01'],
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
    $option = [CURLOPT_COOKIEJAR => '/tmp/cookie',
              CURLOPT_COOKIEFILE => '/tmp/cookie',
              CURLOPT_POST => true,
              CURLOPT_POSTFIELDS => http_build_query($post_data) . $chk_area,
              ];
    
    $log_->info(http_build_query($post_data));
    
    $url = $_ENV['URL001'];
    $res = get_contents($log_, $url, $option);
    // $log_->info($res);
    
    $rc = preg_match_all('/<a href="\/winj\/opac\/switch-detail\.do\?idx=.+?<\/a>/s', $res, $matches);
    
    $log_->info(print_r($matches, true));
    
    $idx = -1;
    foreach ($matches[0] as &$line) {
        $rc = preg_match('/<a href="\/winj\/opac\/switch-detail\.do\?idx=(\d+).+?>' . $_ENV['WORD01'] . '</s', $line, $match);
        if ($rc === 0) {
            continue;
        }
        $log_->info(print_r($match, true));
        $idx = $match[1];
    }
    
    $option = [CURLOPT_COOKIEJAR => '/tmp/cookie',
              CURLOPT_COOKIEFILE => '/tmp/cookie',];
    
    $url = $_ENV['URL002'] . $idx;
    
    $res = get_contents($log_, $url, $option);
    // $log_->info($res);
    
    $url = $_ENV['URL003'];
    
    $res = get_contents($log_, $url, $option);
    $log_->info($res);
    
    $rc = preg_match('/<input type="hidden" name="bibid" value=".+?">/s', $res, $match);
    $log_->info('bibid : ' . $match[1]);
}

function get_contents($log_, $url_, $options_ = null)
{
    $log_->info('BEGIN');
    $log_->info("URL : ${url_}");

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
            $log_->info("curl_setopt : ${key} ${value}");
        }
    }
    if (is_null($options_) === false) {
        foreach ($options_ as $key => $value) {
            $rc = curl_setopt($ch, $key, $value);
            if ($rc == false) {
                $log_->info("curl_setopt : ${key} ${value}");
            }
        }
    }
    $res = curl_exec($ch);
    $time_finish = microtime(true);
    $http_code = (string)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $log_->info("HTTP STATUS CODE : ${http_code} [" .
                substr(($time_finish - $time_start), 0, 5) . 'sec] ' .
                parse_url($url_, PHP_URL_HOST) .
                ' [' . number_format(strlen($res)) . 'byte]'
               );
    curl_close($ch);

    return $res;
}
