<?php
// Shared helpers for the Railway setup and health check.
const PIWIGO_DIR = '/var/www/html/piwigo';
const PIWIGO_DB_CONFIG = PIWIGO_DIR.'/local/config/database.inc.php';

function rw_log($msg) { fwrite(STDERR, '[railway] '.$msg."\n"); }
function rw_fail($msg) { rw_log('ERROR: '.$msg); exit(1); }

function rw_env($name, $default = '')
{
  $v = getenv($name);
  return ($v === false || $v === '') ? $default : $v;
}

// Database settings come from MYSQL_URL (Railway: ${{MySQL.MYSQL_URL}}).
function rw_db_settings()
{
  $url = rw_env('MYSQL_URL');
  if ($url === '') {
    rw_fail('MYSQL_URL is not set. Point it at the MySQL service, for example ${{MySQL.MYSQL_URL}}.');
  }
  $p = parse_url($url);
  if ($p === false || empty($p['host']) || empty($p['user']) || empty($p['path']) || trim($p['path'], '/') === '') {
    rw_fail('MYSQL_URL is not a valid mysql://user:password@host:port/database URL.');
  }
  $prefix = rw_env('PIWIGO_DB_PREFIX', 'piwigo_');
  if (strlen($prefix) > 20 || preg_match('/^\d/', $prefix) || !preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) {
    rw_fail('PIWIGO_DB_PREFIX may only use letters, digits and _ (max 20, not starting with a digit).');
  }
  return array(
    'host' => $p['host'],
    'port' => isset($p['port']) ? (int)$p['port'] : 3306,
    'user' => rawurldecode($p['user']),
    'password' => isset($p['pass']) ? rawurldecode($p['pass']) : '',
    'name' => rawurldecode(trim($p['path'], '/')),
    'prefix' => $prefix,
  );
}

function rw_connect($db)
{
  mysqli_report(MYSQLI_REPORT_OFF);
  $m = @new mysqli($db['host'], $db['user'], $db['password'], $db['name'], $db['port']);
  if ($m->connect_errno) {
    return array(null, $m->connect_error);
  }
  return array($m, null);
}

function rw_is_installed($m, $prefix)
{
  $r = $m->query("SELECT COUNT(*) FROM `".$prefix."config` WHERE param = 'secret_key'");
  if (!$r) { return false; }
  $row = $r->fetch_row();
  return (int)$row[0] === 1;
}

// Piwigo reads its database settings from this file. Rewritten on every boot,
// so a changed database password or host is picked up after a redeploy.
function rw_write_db_config($db)
{
  $host = $db['port'] == 3306 ? $db['host'] : $db['host'].':'.$db['port'];
  $c = "<?php\n"
    ."// Written by the Railway wrapper on every boot from MYSQL_URL. Edits here are overwritten.\n"
    ."\$conf['dblayer'] = 'mysqli';\n"
    ."\$conf['db_base'] = ".var_export($db['name'], true).";\n"
    ."\$conf['db_user'] = ".var_export($db['user'], true).";\n"
    ."\$conf['db_password'] = ".var_export($db['password'], true).";\n"
    ."\$conf['db_host'] = ".var_export($host, true).";\n\n"
    ."\$prefixeTable = ".var_export($db['prefix'], true).";\n\n"
    ."define('PHPWG_INSTALLED', true);\n"
    ."define('PWG_CHARSET', 'utf-8');\n"
    ."define('DB_CHARSET', 'utf8');\n"
    ."define('DB_COLLATE', '');\n";
  @mkdir(dirname(PIWIGO_DB_CONFIG), 0755, true);
  $tmp = PIWIGO_DB_CONFIG.'.tmp';
  if (file_put_contents($tmp, $c) === false || !chmod($tmp, 0640) || !rename($tmp, PIWIGO_DB_CONFIG)) {
    rw_fail('could not write '.PIWIGO_DB_CONFIG);
  }
}
