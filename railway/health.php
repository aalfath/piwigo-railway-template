<?php
// GET /healthz: 200 when PHP runs and Piwigo's database answers, else 503.
header('Content-Type: text/plain');
header('Cache-Control: no-store');
$conf = array();
$ok = false;
if (@include '/var/www/html/piwigo/local/config/database.inc.php') {
  mysqli_report(MYSQLI_REPORT_OFF);
  $hp = explode(':', $conf['db_host']);
  $m = @new mysqli($hp[0], $conf['db_user'], $conf['db_password'], $conf['db_base'], isset($hp[1]) ? (int)$hp[1] : 3306);
  if (!$m->connect_errno) {
    $r = $m->query("SELECT 1 FROM `".$prefixeTable."config` WHERE param = 'secret_key'");
    $ok = $r && $r->num_rows === 1;
  }
}
http_response_code($ok ? 200 : 503);
echo $ok ? "ok\n" : "unavailable\n";
