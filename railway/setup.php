<?php
// Waits for MySQL, installs Piwigo unattended on first boot, and keeps
// local/config/database.inc.php in step with MYSQL_URL.
require __DIR__.'/common.php';

$db = rw_db_settings();

// 1. Wait for the database (it may still be starting on a first deploy).
$wait = (int)rw_env('PIWIGO_DB_WAIT_SECONDS', '180');
$deadline = time() + $wait;
$last = '';
while (true) {
  list($m, $err) = rw_connect($db);
  if ($m) { break; }
  if ($err !== $last) { rw_log('waiting for MySQL at '.$db['host'].':'.$db['port'].': '.$err); $last = $err; }
  if (time() >= $deadline) { rw_fail('MySQL did not answer within '.$wait.' seconds'); }
  sleep(3);
}
rw_log('MySQL is reachable');

if (rw_is_installed($m, $db['prefix'])) {
  rw_write_db_config($db);
  rw_log('Piwigo is already installed; database settings refreshed');
  exit(0);
}

// 2. First boot: refuse to install over a half-installed database.
$r = $m->query("SHOW TABLES LIKE '".$m->real_escape_string(str_replace('_', '\\_', $db['prefix']))."%'");
if ($r && $r->num_rows > 0) {
  rw_fail('the database has '.$r->num_rows.' Piwigo tables but no finished install. '
    .'Empty the database (or set PIWIGO_DB_PREFIX to a new prefix) and redeploy.');
}

$admin = trim(rw_env('PIWIGO_ADMIN_USER', 'admin'));
$pass = rw_env('PIWIGO_ADMIN_PASSWORD');
$mail = rw_env('PIWIGO_ADMIN_EMAIL', 'admin@example.com');
if ($pass === '' || strlen($pass) < 8) { rw_fail('PIWIGO_ADMIN_PASSWORD must be set (8 characters or more).'); }
if ($admin === '' || preg_match('/[\'"]/', $admin)) { rw_fail('PIWIGO_ADMIN_USER must be set and may not contain quotes.'); }
if (filter_var($mail, FILTER_VALIDATE_EMAIL) === false) { rw_fail('PIWIGO_ADMIN_EMAIL is not a valid email address.'); }

if (file_exists(PIWIGO_DB_CONFIG)) {
  // install.php stops at an existing settings file.
  rename(PIWIGO_DB_CONFIG, PIWIGO_DB_CONFIG.'.bak-'.time());
}

// 3. Run Piwigo's own installer as if its form had been posted.
// Declaring get_magic_quotes_gpc() stops install.php from addslashes()-ing the
// values, so passwords with quotes or backslashes stay intact.
if (!function_exists('get_magic_quotes_gpc')) {
  function get_magic_quotes_gpc() { return true; }
}
$seed = bin2hex(random_bytes(16)); // replaced below with a proper hash of the real password
$_GET = array('language' => 'en_UK');
$_POST = array(
  'install' => '1',
  'dbhost' => $db['port'] == 3306 ? $db['host'] : $db['host'].':'.$db['port'],
  'dbuser' => $db['user'],
  'dbpasswd' => $db['password'],
  'dbname' => $db['name'],
  'prefix' => $db['prefix'],
  'admin_name' => $admin,
  'admin_pass1' => $seed,
  'admin_pass2' => $seed,
  'admin_mail' => $mail,
  // no 'newsletter_subscribe': an unattended install signs nobody up
);
$_COOKIE = array();
$domain = rw_env('RAILWAY_PUBLIC_DOMAIN', 'localhost');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = $domain;
$_SERVER['SERVER_NAME'] = $domain;
$_SERVER['REQUEST_URI'] = '/install.php';
$_SERVER['SCRIPT_NAME'] = '/install.php';
$_SERVER['PHP_SELF'] = '/install.php';
$_SERVER['SCRIPT_FILENAME'] = PIWIGO_DIR.'/install.php';
$_SERVER['HTTPS'] = 'on';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';

rw_log('installing Piwigo (first boot)');
chdir(PIWIGO_DIR);
ob_start();
try {
  require PIWIGO_DIR.'/install.php';
} catch (Throwable $e) {
  ob_end_clean();
  rw_fail('installer error: '.$e->getMessage());
}
$out = ob_get_clean();

// install.php reports errors in its HTML, so check the result in the database.
list($m2, $err) = rw_connect($db);
if (!$m2 || !rw_is_installed($m2, $db['prefix'])) {
  rw_log(substr(trim(preg_replace('/\s+/', ' ', strip_tags($out))), 0, 1500));
  rw_fail('the installer did not finish');
}

// 4. Store the real admin password with Piwigo's own hasher. Piwigo addslashes()
// every POST value before it checks a login, so hash the password the same way.
require_once PIWIGO_DIR.'/include/passwordhash.class.php';
$hasher = new PasswordHash(13, true);
$hash = $hasher->HashPassword(addslashes($pass));
if (strpos($hash, '$P$') !== 0) { rw_fail('could not hash the admin password'); }
$st = $m2->prepare('UPDATE `'.$db['prefix'].'users` SET password = ? WHERE id = 1');
$st->bind_param('s', $hash);
if (!$st->execute() || $st->affected_rows !== 1) { rw_fail('could not set the admin password'); }

rw_write_db_config($db);
rw_log('Piwigo installed; log in as "'.$admin.'" with PIWIGO_ADMIN_PASSWORD');
exit(0);
