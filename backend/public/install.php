<?php

declare(strict_types=1);

/**
 * نصب‌کنندهٔ وبیِ سامانهٔ اتوماسیون اداری گروه متحد 7
 * ---------------------------------------------------------------------------
 * این فایل، معادلِ وبیِ دستور  `php bin/console install`  است. آن را کنارِ
 * سایر فایل‌ها در پوشهٔ  public  قرار دهید و در مرورگر باز کنید:
 *
 *     https://YOUR-DOMAIN/install.php
 *
 * کاری که انجام می‌دهد:
 *   ۱) بررسی پیش‌نیازها (نسخهٔ PHP، اکستنشن‌ها، قابل‌نوشتن بودنِ storage)
 *   ۲) نوشتنِ فایل  .env  با تنظیماتِ انتخابیِ شما (SQLite یا MySQL)
 *   ۳) ساختِ جداول (Migrate) و بارگذاریِ دادهٔ اولیه (Seed) یا Import یک فایل SQL
 *   ۴) نمایشِ اطلاعاتِ ورود
 *
 * ⚠ پس از نصبِ موفق، حتماً این فایل را حذف کنید (دکمهٔ حذف در پایان صفحه هست).
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

$root       = dirname(__DIR__);                 // ‎…/backend
$autoloader = $root . '/src/Core/Autoloader.php';
$configFile = $root . '/config/config.php';
$envFile    = $root . '/.env';
$lockFile   = $root . '/storage/installed.lock';

function h(mixed $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

// ── وضعیت و پیام‌ها ─────────────────────────────────────────────────────────
$errors  = [];
$done    = false;
$summary = [];

$phpOk       = version_compare(PHP_VERSION, '8.1.0', '>=');
$hasPdo      = extension_loaded('pdo');
$hasJson     = extension_loaded('json');
$hasSqlite   = extension_loaded('pdo_sqlite');
$hasMysql    = extension_loaded('pdo_mysql');
$hasSqlsrv   = extension_loaded('pdo_sqlsrv') || extension_loaded('pdo_dblib');
$appPresent  = is_file($autoloader) && is_file($configFile);
$storageDir  = $root . '/storage';
$storageOk   = is_dir($storageDir) ? is_writable($storageDir) : is_writable($root);
$alreadyDone = is_file($lockFile);

// مقادیر فرم (پیش‌فرض‌ها)
$f = [
    'db_driver'  => $_POST['db_driver']  ?? 'sqlite',
    'db_host'    => $_POST['db_host']    ?? '127.0.0.1',
    'db_port'    => $_POST['db_port']    ?? '3306',
    'db_name'    => $_POST['db_name']    ?? 'civil7_automation',
    'db_user'    => $_POST['db_user']    ?? 'root',
    'db_pass'    => $_POST['db_pass']    ?? '',
    'jwt_secret' => $_POST['jwt_secret'] ?? bin2hex(random_bytes(24)),
    'app_debug'  => $_POST['app_debug']  ?? 'false',
    'cors'       => $_POST['cors']       ?? '*',
    'data_mode'  => $_POST['data_mode']  ?? 'seed', // seed | sql | none
];

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

// ── حذفِ خودِ فایل نصب‌کننده (باید پیش از هر خروجی انجام شود) ─────────────────
if ($isPost && isset($_POST['self_delete'])) {
    @unlink(__FILE__);
    header('Location: ./');
    exit;
}

// ── پردازشِ نصب ─────────────────────────────────────────────────────────────
if ($isPost && isset($_POST['do_install'])) {
    if (!isset($_POST['confirm'])) {
        $errors[] = 'برای ادامه باید تیکِ «می‌دانم که داده‌های قبلی بازنویسی می‌شوند» را بزنید.';
    }
    if (!$appPresent) {
        $errors[] = 'فایل‌های اصلی برنامه پیدا نشد. install.php باید داخلِ پوشهٔ public پروژه باشد.';
    }
    if ($f['db_driver'] === 'sqlite' && !$hasSqlite) {
        $errors[] = 'اکستنشن pdo_sqlite فعال نیست. یا آن را فعال کنید یا MySQL را انتخاب کنید.';
    }
    if ($f['db_driver'] === 'mysql' && !$hasMysql) {
        $errors[] = 'اکستنشن pdo_mysql فعال نیست.';
    }
    if ($f['db_driver'] === 'sqlsrv' && !$hasSqlsrv) {
        $errors[] = 'اکستنشن pdo_sqlsrv (درایور Microsoft SQL Server) فعال نیست.';
    }

    // فایل SQL آپلودی (اختیاری)
    $sqlUploadPath = null;
    if ($f['data_mode'] === 'sql') {
        $up = $_FILES['sql_file'] ?? null;
        if ($up === null || ($up['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'حالتِ «Import فایل SQL» انتخاب شده اما فایلی بارگذاری نشد.';
        } else {
            $sqlUploadPath = $up['tmp_name'];
        }
    }

    if ($errors === []) {
        try {
            // ۱) نوشتنِ .env
            $env = [];
            $env[] = '# ساخته‌شده توسط install.php در ' . date('Y-m-d H:i:s');
            $env[] = 'APP_DEBUG=' . ($f['app_debug'] === 'true' ? 'true' : 'false');
            $env[] = 'CORS_ORIGINS=' . $f['cors'];
            $env[] = 'JWT_SECRET=' . $f['jwt_secret'];
            $env[] = 'DB_DRIVER=' . $f['db_driver'];
            if ($f['db_driver'] === 'mysql' || $f['db_driver'] === 'sqlsrv') {
                $env[] = 'DB_HOST=' . $f['db_host'];
                $env[] = 'DB_PORT=' . $f['db_port'];
                $env[] = 'DB_NAME=' . $f['db_name'];
                $env[] = 'DB_USER=' . $f['db_user'];
                $env[] = 'DB_PASS=' . $f['db_pass'];
            }
            if ($f['db_driver'] === 'sqlsrv') {
                // Local/self-signed instances need the certificate trusted.
                $env[] = 'DB_TRUST_CERT=true';
            }
            if (@file_put_contents($envFile, implode("\n", $env) . "\n") === false) {
                throw new RuntimeException('نوشتنِ فایل .env ناموفق بود؛ سطحِ دسترسیِ پوشهٔ backend را بررسی کنید.');
            }

            // ۲) بارگذاریِ برنامه + اعمالِ همین تنظیمات در همین درخواست
            require_once $autoloader;
            (new \SevenCivilGroup\Core\Autoloader())
                ->addNamespace('SevenCivilGroup', $root . '/src')
                ->register();

            // مقادیر انتخابی را مستقیم در محیط بگذار تا Config همین‌ها را بخواند
            foreach ([
                'APP_DEBUG'   => $f['app_debug'] === 'true' ? 'true' : 'false',
                'CORS_ORIGINS' => $f['cors'],
                'JWT_SECRET'  => $f['jwt_secret'],
                'DB_DRIVER'   => $f['db_driver'],
                'DB_HOST'     => $f['db_host'],
                'DB_PORT'     => $f['db_port'],
                'DB_NAME'     => $f['db_name'],
                'DB_USER'     => $f['db_user'],
                'DB_PASS'     => $f['db_pass'],
                'DB_TRUST_CERT' => $f['db_driver'] === 'sqlsrv' ? 'true' : '',
            ] as $k => $v) {
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
                putenv("$k=$v");
            }

            $config     = \SevenCivilGroup\Core\Config::fromFile($configFile);
            $connection = new \SevenCivilGroup\Infrastructure\Database\Connection($config);

            // اتصال را زودتر تست کن (خطای واضح‌تر برای MySQL)
            $connection->pdo();

            // ۳) ساختِ جداول
            (new \SevenCivilGroup\Infrastructure\Database\Migrator($connection))->fresh();
            $summary[] = 'جداولِ پایگاه‌داده ساخته شدند.';

            // ۴) دادهٔ اولیه
            if ($f['data_mode'] === 'seed') {
                (new \SevenCivilGroup\Infrastructure\Database\Seeder($connection))->run();
                $summary[] = 'داده‌های نمونه بارگذاری شدند (کاربران، واحدها، نامه‌های نمونه).';
            } elseif ($f['data_mode'] === 'sql' && $sqlUploadPath !== null) {
                $sql = (string) file_get_contents($sqlUploadPath);
                $count = runSqlDump($connection->pdo(), $sql);
                $summary[] = "فایل SQL شما اجرا شد ($count دستور).";
            } else {
                $summary[] = 'بدون دادهٔ اولیه (فقط جداولِ خالی).';
            }

            // ۵) قفلِ نصب
            if (!is_dir($storageDir)) {
                @mkdir($storageDir, 0775, true);
            }
            @file_put_contents($lockFile, 'installed at ' . date('c') . "\n");

            $done = true;
        } catch (\Throwable $e) {
            $errors[] = 'خطا هنگام نصب: ' . $e->getMessage();
        }
    }
}

/**
 * اجرای یک فایل SQL؛ دستورها را با ; جدا می‌کند (کافی برای اکثر dumpها).
 */
function runSqlDump(\PDO $pdo, string $sql): int
{
    // حذفِ کامنت‌های خطی ساده
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $statements = array_filter(array_map('trim', explode(";\n", $sql . "\n")));
    $n = 0;
    foreach ($statements as $stmt) {
        $stmt = trim(rtrim($stmt, ';'));
        if ($stmt === '') {
            continue;
        }
        $pdo->exec($stmt);
        $n++;
    }

    return $n;
}

$selfUrl = h($_SERVER['PHP_SELF'] ?? '/install.php');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>نصب سامانهٔ گروه متحد 7</title>
<style>
  :root{--brand:#1f6feb;--brand2:#0a58ca;--ink:#0d1526;--ink2:#33415c;--ink3:#5b6b86;
    --line:#e3e8f0;--soft:#f4f7fb;--soft2:#eef3fa;--green:#1a9b5a;--red:#e0384d;--gold:#b8860b;}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Vazirmatn,Tahoma,'Segoe UI',sans-serif;background:#eef2f8;color:var(--ink);line-height:1.9;padding:24px 14px}
  .card{max-width:760px;margin:0 auto;background:#fff;border-radius:18px;box-shadow:0 10px 40px rgba(13,21,38,.10);overflow:hidden}
  .hd{background:linear-gradient(135deg,#0a3d91,#1f6feb 60%,#3b8bf5);color:#fff;padding:26px 28px}
  .hd h1{font-size:21px;font-weight:700}
  .hd p{opacity:.92;font-size:13px;margin-top:6px}
  .bd{padding:22px 26px}
  h2{font-size:15px;color:var(--brand2);margin:18px 0 10px;padding-bottom:6px;border-bottom:2px solid var(--soft2)}
  .checks{display:flex;flex-direction:column;gap:6px;margin:6px 0 4px}
  .chk{display:flex;align-items:center;gap:9px;font-size:13px}
  .dot{width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:12px;flex:0 0 auto}
  .ok{background:var(--green)} .bad{background:var(--red)} .warn{background:var(--gold)}
  label{font-size:13px;color:var(--ink2);display:block;margin:10px 0 4px}
  input[type=text],input[type=password],input[type=number],select{width:100%;height:40px;border:1px solid var(--line);
    border-radius:10px;padding:0 11px;font:inherit;font-size:13.5px;background:#fff;color:var(--ink)}
  input[type=text].ltr,input[type=password].ltr,input[type=number].ltr{direction:ltr;text-align:left}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .seg{display:flex;gap:8px;background:var(--soft2);padding:5px;border-radius:12px}
  .seg label{flex:1;margin:0;text-align:center;padding:8px;border-radius:9px;cursor:pointer;font-weight:700;color:var(--ink2)}
  .seg input{display:none}
  .seg input:checked+span{color:#fff}
  .seg label:has(input:checked){background:linear-gradient(135deg,#1f6feb,#0a58ca);color:#fff}
  .mysql{display:none}
  .cbx{display:flex;align-items:flex-start;gap:9px;margin-top:12px;font-size:13px;color:var(--ink2)}
  .cbx input{margin-top:5px}
  .btn{width:100%;height:48px;border:0;border-radius:12px;background:linear-gradient(135deg,#1f6feb,#0a58ca);
    color:#fff;font:inherit;font-size:15px;font-weight:700;cursor:pointer;margin-top:18px;box-shadow:0 6px 18px rgba(31,111,235,.35)}
  .btn.red{background:linear-gradient(135deg,#e0384d,#b5122a);box-shadow:0 6px 18px rgba(224,56,77,.3);height:42px;font-size:13.5px}
  .note{border-radius:12px;padding:12px 15px;margin:12px 0;border-right:4px solid;font-size:12.8px}
  .note.info{background:#eef5ff;border-color:var(--brand)} .note.warn{background:#fff6e9;border-color:var(--gold)}
  .note.err{background:#fdecee;border-color:var(--red);color:#8a1020} .note.ok{background:#eafaf1;border-color:var(--green)}
  .note b{display:block;margin-bottom:3px}
  table{width:100%;border-collapse:collapse;margin:10px 0;font-size:12.5px}
  th,td{border:1px solid var(--line);padding:8px 10px;text-align:right}
  th{background:var(--soft2)} td.ltr{direction:ltr;text-align:left;font-family:monospace}
  code{background:var(--soft2);color:var(--brand2);padding:1px 7px;border-radius:6px;font-size:12px}
  .muted{color:var(--ink3);font-size:11.5px;margin-top:6px}
  ul{margin:6px 22px}
</style>
</head>
<body>
<div class="card">
  <div class="hd">
    <h1>نصب سامانهٔ اتوماسیون اداری گروه متحد 7</h1>
    <p>نصب‌کنندهٔ وبی — معادلِ دستور <code style="color:#fff;background:rgba(255,255,255,.15)">php bin/console install</code></p>
  </div>
  <div class="bd">

  <?php if ($done): ?>
    <div class="note ok"><b>✅ نصب با موفقیت انجام شد</b>
      <ul><?php foreach ($summary as $s): ?><li><?= h($s) ?></li><?php endforeach; ?></ul>
    </div>

    <?php if ($f['data_mode'] === 'seed'): ?>
    <h2>اطلاعات ورود (کاربران نمونه)</h2>
    <p style="font-size:13px">رمز عبورِ همهٔ کاربران: <code>password</code></p>
    <table>
      <tr><th>نقش</th><th>نام کاربری</th></tr>
      <tr><td>مدیر عامل (ادمین / دسترسی کامل)</td><td class="ltr">reza.sadeghi</td></tr>
      <tr><td>کارشناس حسابداری</td><td class="ltr">milad.heydari</td></tr>
      <tr><td>کارشناس انفورماتیک</td><td class="ltr">maryam.sadeghi</td></tr>
    </table>
    <p class="muted">پس از ورود، از بخش «پروفایل» می‌توانید رمز را تغییر دهید.</p>
    <?php endif; ?>

    <div class="note warn"><b>⚠ گامِ امنیتیِ ضروری</b>
      همین حالا فایل <code>install.php</code> را حذف کنید تا کسی نتواند دوباره نصب را اجرا و داده‌ها را پاک کند.
    </div>
    <form method="post" onsubmit="return confirm('فایل install.php حذف شود؟')">
      <button class="btn red" name="self_delete" value="1" type="submit">🗑 حذفِ همین فایل نصب‌کننده</button>
    </form>
    <p style="text-align:center;margin-top:14px"><a href="./" style="color:var(--brand);font-weight:700;text-decoration:none">→ ورود به سامانه</a></p>
  <?php endif; ?>

  <?php if (!$done): ?>
    <?php foreach ($errors as $e): ?>
      <div class="note err"><?= h($e) ?></div>
    <?php endforeach; ?>

    <?php if ($alreadyDone && !$isPost): ?>
      <div class="note warn"><b>به‌نظر می‌رسد قبلاً نصب شده است</b>
        فایلِ قفلِ نصب موجود است (<code>storage/installed.lock</code>). اگر می‌خواهید <b>از نو</b> نصب کنید و داده‌های فعلی پاک شوند، آن فایل را حذف و این صفحه را دوباره باز کنید.
      </div>
    <?php endif; ?>

    <h2>۱) بررسی پیش‌نیازها</h2>
    <div class="checks">
      <?php
      $checks = [
        ['PHP نسخهٔ ۸.۱ یا بالاتر (فعلی: ' . PHP_VERSION . ')', $phpOk, true],
        ['اکستنشن PDO', $hasPdo, true],
        ['اکستنشن JSON', $hasJson, true],
        ['اکستنشن pdo_sqlite (برای SQLite)', $hasSqlite, false],
        ['اکستنشن pdo_mysql (برای MySQL)', $hasMysql, false],
        ['اکستنشن pdo_sqlsrv (برای SQL Server)', $hasSqlsrv, false],
        ['قابلِ نوشتن بودنِ پوشهٔ storage', $storageOk, true],
        ['حضورِ فایل‌های اصلی برنامه', $appPresent, true],
      ];
      foreach ($checks as [$labelText, $pass, $required]):
        $cls = $pass ? 'ok' : ($required ? 'bad' : 'warn');
        $ic  = $pass ? '✓' : ($required ? '✕' : '!');
      ?>
        <div class="chk"><span class="dot <?= $cls ?>"><?= $ic ?></span><span><?= h($labelText) ?></span></div>
      <?php endforeach; ?>
    </div>
    <p class="muted">موارد نارنجی الزامی نیستند؛ فقط برای همان درایورِ پایگاه‌داده لازم‌اند.</p>

    <form method="post" enctype="multipart/form-data">
      <h2>۲) پایگاه‌داده</h2>
      <div class="seg">
        <label><input type="radio" name="db_driver" value="sqlite" onchange="tg()" <?= $f['db_driver']==='sqlite'?'checked':'' ?>><span>SQLite (ساده)</span></label>
        <label><input type="radio" name="db_driver" value="mysql" onchange="tg()" <?= $f['db_driver']==='mysql'?'checked':'' ?>><span>MySQL</span></label>
        <label><input type="radio" name="db_driver" value="sqlsrv" onchange="tg()" <?= $f['db_driver']==='sqlsrv'?'checked':'' ?>><span>SQL Server</span></label>
      </div>
      <div class="mysql" id="mysqlBox">
        <div class="row">
          <div><label>هاست</label><input class="ltr" type="text" name="db_host" value="<?= h($f['db_host']) ?>"></div>
          <div><label>پورت</label><input class="ltr" type="number" name="db_port" value="<?= h($f['db_port']) ?>"></div>
        </div>
        <label>نام دیتابیس</label><input class="ltr" type="text" name="db_name" value="<?= h($f['db_name']) ?>">
        <div class="row">
          <div><label>کاربر</label><input class="ltr" type="text" name="db_user" value="<?= h($f['db_user']) ?>"></div>
          <div><label>رمز عبور</label><input class="ltr" type="password" name="db_pass" value="<?= h($f['db_pass']) ?>"></div>
        </div>
        <p class="muted">دیتابیس را از قبل بسازید؛ نصب‌کننده جداول را داخل آن می‌سازد. <span id="portHint"></span></p>
      </div>

      <h2>۳) دادهٔ اولیه</h2>
      <label>چه چیزی بارگذاری شود؟</label>
      <select name="data_mode" onchange="tgData()" id="dataMode">
        <option value="seed" <?= $f['data_mode']==='seed'?'selected':'' ?>>داده‌های نمونه (کاربران و واحدها) — پیشنهادی</option>
        <option value="sql"  <?= $f['data_mode']==='sql' ?'selected':'' ?>>Import یک فایل SQL دلخواه</option>
        <option value="none" <?= $f['data_mode']==='none'?'selected':'' ?>>فقط جداولِ خالی</option>
      </select>
      <div id="sqlBox" style="display:none">
        <label>فایل SQL</label>
        <input type="file" name="sql_file" accept=".sql,text/plain" style="font-size:12.5px;margin-top:4px">
        <p class="muted">دستورها با «;» از هم جدا می‌شوند؛ برای MySQL مناسب است.</p>
      </div>

      <h2>۴) تنظیمات امنیتی</h2>
      <label>کلید JWT (به‌صورت خودکار ساخته شد؛ می‌توانید تغییر دهید)</label>
      <input class="ltr" type="text" name="jwt_secret" value="<?= h($f['jwt_secret']) ?>">
      <div class="row">
        <div>
          <label>حالت اجرا</label>
          <select name="app_debug">
            <option value="false" <?= $f['app_debug']==='false'?'selected':'' ?>>تولید (Production) — پیشنهادی</option>
            <option value="true"  <?= $f['app_debug']==='true' ?'selected':'' ?>>توسعه (نمایش خطاها)</option>
          </select>
        </div>
        <div><label>دامنه‌های مجاز (CORS)</label><input class="ltr" type="text" name="cors" value="<?= h($f['cors']) ?>"></div>
      </div>

      <label class="cbx"><input type="checkbox" name="confirm" value="1">
        <span>می‌دانم که با اجرای نصب، جداولِ فعلی <b>پاک و بازسازی</b> می‌شوند و داده‌های قبلی از بین می‌روند.</span></label>

      <button class="btn" type="submit" name="do_install" value="1">🚀 اجرای نصب</button>
      <p class="muted" style="text-align:center">این کار همان کاری است که <code>php bin/console install</code> انجام می‌دهد.</p>
    </form>
  <?php endif; ?>

  </div>
</div>

<script>
  function tg(){
    var v = document.querySelector('input[name=db_driver]:checked').value;
    var net = (v==='mysql'||v==='sqlsrv');
    document.getElementById('mysqlBox').style.display = net ? 'block' : 'none';
    var hint = document.getElementById('portHint');
    if (hint) hint.textContent = v==='sqlsrv' ? 'پورت پیش‌فرض SQL Server معمولاً 1433 است.'
                              : v==='mysql'  ? 'پورت پیش‌فرض MySQL برابر 3306 است.' : '';
  }
  function tgData(){document.getElementById('sqlBox').style.display =
    document.getElementById('dataMode').value==='sql'?'block':'none';}
  tg(); tgData();
</script>
</body>
</html>
