<?php

include('/usr/src/app/MyUtils.php');

$log = new Log();
$mu = new MyUtils();

$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START ${requesturi}");

$html = <<< __HEREDOC__
<html><body>
<form method="POST" action="./get_encrypt_string.php">
<input type="text" name="original" />
<input type="submit" /> 
</form>
</body></html>
__HEREDOC__;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $s = $mu->get_encrypt_string($_POST['original']);
    $log->warn($s);
    header("Content-Type: text/plain");
    echo $mu->get_decrypt_string($s);
} else {
    echo $html;
}

$log->info('FINISH ' . substr((microtime(true) - $time_start), 0, 7) . 's');
