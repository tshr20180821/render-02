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
    
    $url = $_ENV['URL001'];
    $res = get_contents($log_, $url);
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
