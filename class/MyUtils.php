<?php

include('/usr/src/app/log.php');

class MyUtils
{
    private $log;
    
    function __construct() {
        $log = new Log();
    }
    
    public function get_contents($url_, $options_ = null)
    {
        global $log;
        $log->info("URL : ${url_}");

        $options = [
            CURLOPT_URL => $url_,
            CURLOPT_USERAGENT => $_ENV['USER_AGENT'],
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
    
    public function get_env($key_name_)
    {
        global $log;
        $log->info('BEGIN');

        $pdo_sqlite = new PDO('sqlite:/tmp/sqlite.db');

        $sql_select = <<< __HEREDOC__
SELECT M1.value
  FROM m_env M1
 WHERE M1.key_name = :b_key_name
__HEREDOC__;

        $statement_select = $pdo_sqlite->prepare($sql_select);
        $rc = $statement_select->execute([
            ':b_key_name' => $key_name_,
        ]);
        $results = $statement_select->fetchAll();

        $value = '';
        foreach ($results as $row) {
            $value = $row['value'];
        }

        $pdo_sqlite = null;

        return $value;
    }
    
    public function get_pdo()
    {
        global $log;
        $log->info('BEGIN');

        $dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']}";
        $options = array(
            PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
        );
        return new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $options);
    }
    
    public function send_mail($subject_, $body_, $cc_ = null)
    {
        global $log;
        $log->info('BEGIN');

        $user_address = $this->get_env('SMTP_USERNAME');

        $mail = new PHPMailer(true);
        try {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str_, $level_) {
                $log->info('PHPMailer log : ' . trim($str_));
            };

            $mail->isSMTP();
            $mail->Host = $this->get_env('SMTP_SERVER');
            $mail->SMTPAuth = true;
            $mail->Username = $user_address;
            $mail->Password = $this->get_env('SMTP_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom($user_address);
            $mail->addAddress($user_address);
            if ($cc_ != null) {
                $mail->addCC($cc_);
            }

            $mail->isHTML(false);
            $mail->Encoding = '7bit';
            $mail->CharSet = 'iso-2022-jp';

            $mail->Subject = mb_encode_mimeheader($subject_, 'iso-2022-jp');
            $mail->Body = mb_convert_encoding($body_, 'iso-2022-jp', 'utf-8');

            $mail->send();
        } catch (Exception $e) {
            $log->warn('ERROR : ' . $mail->ErrorInfo);
        }
    }
}
