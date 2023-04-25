<?php

include('/usr/src/app/MyUtils.php');

$log = new Log();
$mu = new MyUtils();

$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START ${requesturi}");

check_imap();

$log->info('FINISH ' . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function check_imap()
{
    global $mu;
    global $log;
    $log->info('BEGIN');
    
    clearstatcache();
    if (!file_exists('/tmp/t_mail_cache.db')) {
        init_sqlite();
    }

    // get method
    
    $html = <<< __HEREDOC__
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>get page</title>
</head>
<body>
<form method="POST" action="__REQUEST_URI__">
<input type="password" name="user" style="ime-mode: disabled;">
<input type="password" name="password" style="ime-mode: disabled;">
<input type="text" name="no_range" style="ime-mode: disabled;">
<input type="password" name="mark_mail_address" style="ime-mode: disabled;">
<input type="text" name="imap_server" style="ime-mode: disabled;">
<input type="submit"> 
</form>
</body></html>
__HEREDOC__;
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $html = str_replace('__REQUEST_URI__', $_SERVER['REQUEST_URI'], $html);
        echo $html;
        return;
    } else if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    
    // check view mode
    
    $no_range = $_POST['no_range'];
    
    $is_view_mode = false;
    if ($no_range != '') {
        $tmp = explode('-', $no_range);
        if (count($tmp) == 1) {
            $start_no = $tmp[0];
            check_imap_view();
            return;
        }
    }
    
    // connect imap

    $html = <<< __HEREDOC__
<html lang="jp">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>retry</title>
<script>
window.onload = function() {
  setTimeout(function() {
    document.form_refresh.submit();
  }, 3 * 60 * 1000);
}
</script>
</head>
<body>
<form name="form_refresh" method="POST" action="__REQUEST_URI__">
<input type="hidden" name="user" value="__USER__">
<input type="hidden" name="password" value="__PASSWORD__">
<input type="hidden" name="no_range" value="__NO_RANGE__">
<input type="hidden" name="mark_mail_address" value="__MARK_MAIL_ADDRESS__">
<input type="hidden" name="imap_server" value="__IMAP_SERVER__">
<input type="submit" value="Manual Retry">
</form>
<div>Auto Retry...</div>
<div>__UPDATE_TIME__</div>
</body>
</html>
__HEREDOC__;
    
    $user = $_POST['user'];
    $password = $_POST['password'];
    $mark_mail_address = $_POST['mark_mail_address'];
    $imap_server = $_POST['imap_server'];
    
    $line = "curl -m 10 -u ${user}:${password} imaps://${imap_server} -X 'EXAMINE INBOX'" . ' | grep -o -E "[0-9]+ EXISTS"';
    $res = $mu->cmd_execute($line);
    
    if (count($res) == 0) {
        $html = str_replace('__REQUEST_URI__', $_SERVER['REQUEST_URI'], $html);
        $html = str_replace('__NO_RANGE__', $no_range, $html);
        $html = str_replace('__USER__', $user, $html);
        $html = str_replace('__PASSWORD__', $password, $html);
        $html = str_replace('__MARK_MAIL_ADDRESS__', $mark_mail_address, $html);
        $html = str_replace('__IMAP_SERVER__', $imap_server, $html);
        $html = str_replace('__UPDATE_TIME__', date('H:i'), $html);
        
        echo $html;
        return;
    }
    
    $log->info('START imap_open');
    $imap = imap_open('{' . $imap_server . '/ssl}', $user, $password);
    $log->info(print_r($imap, true));
    $log->info('FINISH imap_open');
    
    $message_no_max = imap_num_msg($imap);
    $log->info("mail count : ${message_no_max}");
    
    $file_name_mail_count = '/tmp/mail/MAIL_COUNT';
    clearstatcache();
    if (file_exists($file_name_mail_count)) {
        $message_no_max_previous = (int)file_get_contents($file_name_mail_count);
        if ($message_no_max_previous != $message_no_max) {
            /*
            $res = $mu_->send_slack_message(':envelope_with_arrow: MAIL COUNT CHANGED. '
                                            . number_format($message_no_max) . ' +' . ($message_no_max - $message_no_max_previous));
            */
        } else if (file_get_contents('/tmp/mail/previous_range') == $no_range) {
            imap_close($imap);
            $html = file_get_contents('/tmp/mail/previous_html');
            $html = str_replace('__UPDATE_TIME__', date('H:i'), $html);
            echo $html;
            return;
        }
    }

    $start_no = -1;
    $finish_no = -1;
    
    if ($no_range != '') {
        $tmp = explode('-', $no_range);
        if (count($tmp) == 2) {
            [$start_no, $finish_no] = $tmp;
        } else {
            imap_close($imap);
            return;
        }
    } else {
        $start_no = (int)($message_no_max / 10) * 10;
        $finish_no = $start_no + 50;
        $no_range = $start_no . '-' . $finish_no;
    }
    if ($message_no_max < $finish_no) {
        $finish_no = $message_no_max;
    }
    
    $sql_select = <<< __HEREDOC__
SELECT T1.body_short
  FROM t_mail_cache T1
 WHERE T1.message_no = :b_message_no
   AND T1.message_id = :b_message_id
__HEREDOC__;
    
    $sql_upsert = <<< __HEREDOC__
INSERT INTO t_mail_cache (message_no, message_id, body_short, body)
       VALUES(:b_message_no, :b_message_id, :b_body_short, :b_body)
    ON CONFLICT (message_no)
    DO UPDATE SET message_id = :b_message_id
                 ,body_short = :b_body_short
                 ,body = :b_body
__HEREDOC__;

    $pdo = new PDO('sqlite:/tmp/t_mail_cache.db');
    $statement_select = $pdo->prepare($sql_select);
    $statement_upsert = $pdo->prepare($sql_upsert);
    
    $mail_list = [];
    
    for ($i = $start_no - 1; $i < $finish_no; $i++) {
        
        $array_index = count($mail_list);
        
        $header = imap_headerinfo($imap, $i + 1);
        $subject = mb_decode_mimeheader($header->subject);
        $date = date('m/d H:i', strtotime($header->date));
        $from = mb_decode_mimeheader($header->fromaddress);
        $msg_no = $header->Msgno;
        $message_id = $header->message_id;
        $toaddress = $header->toaddress;
        
        if (strpos($toaddress, $mark_mail_address) === false) {
            $mark_level = 0;
        } else {
            $mark_level = 1;
        }
        
        $rc = $statement_select->execute([
            ':b_message_no' => $msg_no,
            ':b_message_id' => $mu->get_encrypt_string($message_id),
        ]);
        $results = $statement_select->fetchAll();
        if (count($results) == 1) {
            $body = $mu->get_decrypt_string($results[0]['body_short']);
            $log->info("CACHE HIT ${msg_no}");
        } else {
            $structure = imap_fetchstructure($imap, $i + 1);
            if (isset($structure->parts)) {
                if ($structure->parts[0]->parameters[0]->attribute == 'charset') {
                    $charset = $structure->parts[0]->parameters[0]->value;
                    $encoding = $structure->parts[0]->encoding;
                } else {
                    $charset = $structure->parts[0]->parts[0]->parameters[0]->value;
                    $encoding = $structure->parts[0]->parts[0]->encoding;
                }
            } else {
                $charset = $structure->parameters[0]->value;
                $encoding = $structure->encoding;
            }
            $log->info("${subject} ${charset}");

            $body = imap_fetchbody($imap, $i + 1, 1, FT_INTERNAL);
            
            switch ($encoding) {
                case 1:
                    $body = imap_qprint(imap_8bit($body));
                    break;
                case 3:
                    $body = imap_base64($body);
                    break;
                case 4:
                    $body = imap_qprint($body);
                    break;
                default:
            }
            $body = mb_convert_encoding($body, 'UTF-8', $charset);
            $body_all = $body;
            $body = preg_replace('/(\r|\n)/', ' ', str_replace('　', ' ', html_entity_decode(strip_tags($body))));
            $body = preg_replace('/ +/', ' ', $body);
            $body = mb_substr($body, 0, 120);
            
            $rc = $statement_upsert->execute([
                ':b_message_no' => $msg_no,
                ':b_message_id' => $mu->get_encrypt_string($message_id),
                ':b_body_short' => $mu->get_encrypt_string($body),
                ':b_body' => $mu->get_encrypt_string($body_all),
            ]);
            $log->info("UPSERT RESULT : ${rc}");
        }
        
        $mail_list[$array_index]['subject'] = $subject;
        $mail_list[$array_index]['mark_level'] = $mark_level;
        $mail_list[$array_index]['date'] = $date;
        $mail_list[$array_index]['from'] = $from;
        $mail_list[$array_index]['body'] = $body;
        $mail_list[$array_index]['number'] = $i + 1;
        $mail_list[$array_index]['message_id'] = $message_id;
        $mail_list[$array_index]['from'] = $from;
        $mail_list[$array_index]['toaddress'] = $toaddress;
    }
    
    $pdo = null;
    
    imap_close($imap);
    file_put_contents($file_name_mail_count, $message_no_max);
    
    $html = <<< __HEREDOC__
<html lang="jp">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>__TITLE__</title>
<script>
var start_time = new Date();
var auto_refresh = function() {
  var now = new Date();
  if (now - start_time > 7 * 60 * 1000) {
    document.form_refresh.submit();
  } else {
    var current_time = (now.getHours() + 100).toString().slice(-2) + ":" + (now.getMinutes() + 100).toString().slice(-2);
    document.getElementById("current_time").innerHTML = current_time;
  }
}

function set_interval() {
  setInterval(auto_refresh, 15 * 1000);
}

function toggle_display() {
  var form_area = document.getElementById("form_area");
  if (form_area.style.display == "none") {
    form_area.style.display = "inline";
  } else {
    form_area.style.display = "none";
  }
}
</script>
<link rel="stylesheet" type="text/css" href="/auth/check_imap.css">
</head>
<body onload="__ON_LOAD__">
<input class="button_3" type="button" onclick="toggle_display();">
<span id="form_area" style="display: none;">
<form name="form_refresh" method="POST" action="__REQUEST_URI__">
<input type="text" name="user" value="__USER__">
<input type="text" name="password" value="__PASSWORD__">
<input type="text" name="no_range" value="__NO_RANGE__">
<input type="text" name="mark_mail_address" value="__MARK_MAIL_ADDRESS__">
<input type="text" name="imap_server" value="__IMAP_SERVER__">
<input type="submit">
</form>
</span>
__BODY_CONTENTS__
<div>__UPDATE_TIME__</div>
<div id="current_time">__UPDATE_TIME__</div>
</body></html>
__HEREDOC__;

    $body_contents = '';
    foreach ($mail_list as $one_record) {
        $body_contents .= '<div>';
        $body_contents .= '<form class="form_1" method="POST" action="' . $_SERVER['REQUEST_URI'] . '" target="_blank">'
            . '<input type="hidden" name="no_range" value="' . $one_record['number'] . '">'
            . '<input type="hidden" name="message_id" value="' . base64_encode($one_record['message_id']) . '">'
            . '<input type="hidden" name="subject" value="' . base64_encode($one_record['subject']) . '">'
            . '<input type="hidden" name="from" value="' . base64_encode($one_record['from']) . '">'
            . '<input type="hidden" name="toaddress" value="' . base64_encode($one_record['toaddress']) . '">'
            . '<input type="hidden" name="date" value="' . base64_encode($one_record['date']) . '">'
            . '<input class="button_1" type="submit" value="' . str_pad($one_record['number'], 5, '0', STR_PAD_LEFT) . '"></form> ';
        if ($one_record['mark_level'] == '0') {
            $body_contents .= '<span class="span_1">' . htmlentities($one_record['subject']) . '</span> ';
        } else {
            $body_contents .= '<span class="span_2">' . htmlentities($one_record['subject']) . '</span> ';
        }
        $body_contents .= '<span class="span_3">' . $one_record['date'] . '</span> ';
        $body_contents .= '<span>' . htmlentities($one_record['from']) . '</span></div>';
        $body_contents .= '<div>' . $one_record['body'] . '</div><div class="div_1">&nbsp;</div>' . "\n";
    }
    $body_contents .= '<hr>';
    
    // pre 100
    
    $body_contents .= '<div><form class="form_1" method="POST" action="' . $_SERVER['REQUEST_URI'] . '" target="_blank">'
            . '<input type="hidden" name="user" value="' . $user . '">'
            . '<input type="hidden" name="password" value="' . $password . '">'
            . '<input type="hidden" name="no_range" value="' . ($start_no - 100 < 1 ? 0 : $start_no - 100) . '-' . $start_no . '">'
            . '<input type="hidden" name="mark_mail_address" value="' . $mark_mail_address . '">'
            . '<input type="hidden" name="imap_server" value="' . $imap_server . '">'
            . '<input class="button_1" type="submit" value="PRE100"></form></div>' . "\n";
    
    // new 50
    
    $body_contents .= '<div><form class="form_1" method="POST" action="' . $_SERVER['REQUEST_URI'] . '">'
            . '<input type="hidden" name="user" value="' . $user . '">'
            . '<input type="hidden" name="password" value="' . $password . '">'
            . '<input type="hidden" name="no_range" value="' . ($message_no_max - $message_no_max % 10) . '-' . ($message_no_max - $message_no_max % 10 + 50) . '">'
            . '<input type="hidden" name="mark_mail_address" value="' . $mark_mail_address . '">'
            . '<input type="hidden" name="imap_server" value="' . $imap_server . '">'
            . '<input class="__BUTTON_CLASS__" type="submit" value="NEW50"></form></div>' . "\n";
    
    // refresh
    
    $is_refresh = false;
    if ($no_range != '') {
        $tmp = explode('-', $no_range);
        if (count($tmp) == 2) {
            [$start_no, $finish_no] = $tmp;
            if ($finish_no > $message_no_max) {
                $is_refresh = true;
            }
        }
    }
    if ($is_refresh == true) {
        $html = str_replace('__TITLE__', 'R ' . $no_range, $html);
        $html = str_replace('__ON_LOAD__', 'set_interval();', $html);
        if (($message_no_max - $message_no_max % 10) . '-' . ($message_no_max - $message_no_max % 10 + 50) == $no_range) {
            $body_contents = str_replace('__BUTTON_CLASS__', 'button_1', $body_contents);
        } else {
            $body_contents = str_replace('__BUTTON_CLASS__', 'button_2', $body_contents);
        }
    } else {
        $html = str_replace('__TITLE__', $no_range, $html);
        $html = str_replace('__ON_LOAD__', '', $html);
        $body_contents = str_replace('__BUTTON_CLASS__', 'button_1', $body_contents);
    }
    
    $html = str_replace('__REQUEST_URI__', $_SERVER['REQUEST_URI'], $html);
    $html = str_replace('__NO_RANGE__', $no_range, $html);
    $html = str_replace('__USER__', $user, $html);
    $html = str_replace('__PASSWORD__', $password, $html);
    $html = str_replace('__MARK_MAIL_ADDRESS__', $mark_mail_address, $html);
    $html = str_replace('__IMAP_SERVER__', $imap_server, $html);
    $html = str_replace('__BODY_CONTENTS__', $body_contents, $html);
    if ($is_refresh == true) {
        file_put_contents('/tmp/mail/previous_html', $html);
        file_put_contents('/tmp/mail/previous_range', $no_range);
    }
    $html = str_replace('__UPDATE_TIME__', date('H:i'), $html);
    
    if ($is_refresh == false) {
        file_put_contents('/tmp/mail/' . $no_range, $html);
        file_put_contents('/tmp/mail/AUTHORIZATION', hash('sha512', $user) . '-' . hash('sha512', $password) . '-' . hash('sha512', $mark_mail_address));
    }
    
    echo $html;
}

function check_imap_view()
{
    global $mu;
    global $log;
    $log->info('BEGIN');
    
    $msg_no = $_POST['no_range'];
    $message_id = base64_decode($_POST['message_id']);
    $subject = base64_decode($_POST['subject']);
    $from = base64_decode($_POST['from']);
    $toaddress = base64_decode($_POST['toaddress']);
    $date = base64_decode($_POST['date']);

    $sql_select = <<< __HEREDOC__
SELECT T1.body
  FROM t_mail_cache T1
 WHERE T1.message_no = :b_message_no
   AND T1.message_id = :b_message_id
__HEREDOC__;
    
    $pdo = new PDO('sqlite:/tmp/t_mail_cache.db');
    
    $statement_select = $pdo->prepare($sql_select);
    
    $rc = $statement_select->execute([
        ':b_message_no' => $msg_no,
        ':b_message_id' => $mu->get_encrypt_string($message_id),
    ]);
    $results = $statement_select->fetchAll();
    
    $body = $mu->get_decrypt_string($results[0]['body']);
    
    $pdo = null;
    
    $html = <<< __HEREDOC__
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>__TITLE__</title>
</head>
<body>
<div style="font-weight: bold;">__SUBJECT__</div>
<div>__FROM__</div>
<div>__TOADDRESS__</div>
<div>__DATE__</div>
<hr>
<div><pre style="white-space: pre-wrap;">__BODY__</pre></div>
</body></html>
__HEREDOC__;
    
    $html = str_replace('__TITLE__', $msg_no, $html);
    $html = str_replace('__SUBJECT__', $subject, $html);
    $html = str_replace('__FROM__', htmlentities($from), $html);
    $html = str_replace('__TOADDRESS__', htmlentities($toaddress), $html);
    $html = str_replace('__DATE__', $date, $html);
    $html = str_replace('__BODY__', $body, $html);
    
    echo $html;
}

function init_sqlite()
{
    global $mu;
    global $log;
    $log->info('BEGIN');

    $pdo = new PDO('sqlite:/tmp/t_mail_cache.db');

    $sql_create = <<< __HEREDOC__
CREATE TABLE t_mail_cache (
    message_no INTEGER PRIMARY KEY,
    message_id TEXT NOT NULL,
    body_short TEXT,
    body TEXT
)
__HEREDOC__;

    $rc = $pdo->exec($sql_create);
    $log->info('t_mail_cache create table result : ' . $rc);
    
    $pdo = null;
}
