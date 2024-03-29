<?php

include('/usr/src/app/log.php');

class MyUtils
{
    private $_log;
    
    function __construct() {
        $this->_log = new Log();
    }
    
    public function cmd_execute($line_)
    {
        $this->_log->info("EXECUTE : {$line_}");

        $time_start = microtime(true);
        exec($line_, $res);
        $time_finish = microtime(true);
        $this->logging_object($res);
        $this->_log->info('Process Time : ' . substr(($time_finish - $time_start), 0, 7) . 's');
        return $res;
    }
    
    public function logging_object($obj_)
    {
        if (is_null($obj_)) {
            $this->_log->info('(NULL)');
        } else if (is_array($obj_) || is_object($obj_)) {
            /*
            $res = explode("\n", print_r($obj_, true));
            foreach ($res as $one_line) {
                $this->_log->info($one_line);
            }
            */
            $this->_log->info(print_r($obj_, true));
        } else if (is_string($obj_)) {
            /*
            $res = explode("\n", $obj_);
            foreach ($res as $one_line) {
                $this->_log->info($one_line);
            }
            */
            $this->_log->info($obj_);
        }
    }
    
    public function get_contents($url_, $options_ = null)
    {
        $this->_log->info("URL : {$url_}");

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
                $this->_log->info("curl_setopt : {$key} {$value}");
            }
        }
        if (is_null($options_) === false) {
            foreach ($options_ as $key => $value) {
                if ($key == CURLOPT_USERPWD) {
                    $value = base64_decode($value);
                }
                $rc = curl_setopt($ch, $key, $value);
                if ($rc == false) {
                    $this->_log->info("curl_setopt : {$key} {$value}");
                }
            }
        }
        $res = curl_exec($ch);
        $time_finish = microtime(true);
        $http_code = (string)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->_log->info("HTTP STATUS CODE : {$http_code} [" .
                    substr(($time_finish - $time_start), 0, 5) . 'sec] ' .
                    parse_url($url_, PHP_URL_HOST) .
                    ' [' . number_format(strlen($res)) . 'byte]'
                   );
        curl_close($ch);

        return $res;
    }

    public function get_decrypt_string($encrypt_base64_string_)
    {
        $this->_log->info('BEGIN');

        list($iv,  $encrypt_base64_string) = explode(':', $encrypt_base64_string_);
        return openssl_decrypt($encrypt_base64_string, $_ENV['CIPHER'], $_ENV['ENCRYPT_KEY'], 0, base64_decode($iv));
    }

    public function get_encrypt_string($original_string_)
    {
        $this->_log->info('BEGIN');

        $cipher = $_ENV['CIPHER'];
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
        return base64_encode($iv) . ':' . openssl_encrypt($original_string_, $cipher, $_ENV['ENCRYPT_KEY'], 0, $iv);;
    }
    
    public function get_env($key_name_)
    {
        $this->_log->info('BEGIN');

        $pdo_sqlite = new PDO('sqlite:/tmp/sqlite.db');

        $sql_select = <<< __HEREDOC__
SELECT M1.value
      ,M1.encrypt
  FROM m_env M1
 WHERE M1.key_name = :b_key_name
__HEREDOC__;

        $statement_select = $pdo_sqlite->prepare($sql_select);
        $rc = $statement_select->execute([
            ':b_key_name' => $key_name_,
        ]);
        $results = $statement_select->fetchAll();

        $value = '';
        $encrypt = 0;
        foreach ($results as $row) {
            $value = $row['value'];
            $encrypt = $row['encrypt'];
        }

        $pdo_sqlite = null;

        if ($encrypt == 1) {
            $value = $this->get_decrypt_string($value);
        }
        return $value;
    }
    
    public function get_pdo()
    {
        $this->_log->info('BEGIN');

        $dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']}";
        $options = array(
            PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
        );
        return new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $options);
    }
    
    public function send_mail($subject_, $body_, $cc_ = null)
    {
        $this->_log->info('BEGIN');

        $user_address = $this->get_env('SMTP_USERNAME');

        $mail = new PHPMailer(true);
        try {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str_, $level_) {
                $this->_log->info('PHPMailer log : ' . trim($str_));
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
            $this->_log->warn('ERROR : ' . $mail->ErrorInfo);
        }
    }
    
    public function send_slack_message($message_)
    {
        $this->_log->info('BEGIN');

        $slack_access_token = $this->get_env('SLACK_ACCESS_TOKEN');

        if ($slack_access_token != '') {
            foreach ([$this->get_env('SLACK_CHANNEL_01'), $this->get_env('SLACK_CHANNEL_02')] as &$channel) {
                $options = [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode(['text' => $message_, 'channel' => $channel]),
                    CURLOPT_HTTPHEADER => ["Authorization: Bearer {$slack_access_token}", 'Content-type: application/json'],
                ];
                $url = 'https://slack.com/api/chat.postMessage';
                $res = $this->get_contents($url, $options);
                $this->logging_object(json_decode($res, true));
                sleep(1);
            }
        }
    }
}
