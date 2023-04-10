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
    $log_->info(file_get_contents('/tmp/cookie'));
    
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
        "chk_area[0]" => '01',
        "chk_area[1]" => '02',
        "chk_area[2]" => '03',
        "chk_area[3]" => '04',
        "chk_area[4]" => '05',
        "chk_area[5]" => '06',
        "chk_area[6]" => '07',
        "chk_area[7]" => '08',
        "chk_area[8]" => '09',
        "chk_area[9]" => '10',
        "chk_area[10]" => '11',
        "chk_area[11]" => '12',
        "chk_area[12]" => '13',
        'cmb_order' => 'pubYear',
        'opt_order' => '1',
        'opt_pagesize' => '25',
    ];
    
    $option = [CURLOPT_COOKIEJAR => '/tmp/cookie',
              CURLOPT_COOKIEFILE => '/tmp/cookie',
              CURLOPT_POST => true,
              CURLOPT_POSTFIELDS => http_build_query($post_data),
              ];
    
    $res = get_contents($log_, $url, $option);
    $log_->info($res);
    $log_->info(file_get_contents('/tmp/cookie'));
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
