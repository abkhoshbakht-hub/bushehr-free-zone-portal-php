<?php
/**
 * نصب‌کننده پورتال خبررسانی منطقه آزاد بوشهر
 * یک‌بار اجرا: web (فرم) یا CLI:  php install.php [--force]
 * پس از نصب موفق، فایل install.lock ساخته می‌شود؛ لطفاً این فایل را پس از استقرار نهایی حذف کنید.
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$CFG = app_workaround();
function app_workaround(): array {
    $c = require __DIR__ . '/config.php'; return $c;
}
$cli = PHP_SAPI === 'cli';
$force = ($cli && in_array('--force', $argv, true));

/* ---------- بررسی پیش‌نیازها ---------- */
$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
$pdoOk = extension_loaded('pdo_mysql');
if (!$phpOk || !$pdoOk) { die('نیاز به PHP 8+ و اکستنشن pdo_mysql دارید.'); }

$lock = __DIR__ . '/install.lock';
if (file_exists($lock) && !$force) {
    die('پروژه قبلاً نصب شده است (install.lock موجود است). برای نصب مجدد از CLI با --force استفاده کنید.');
}

/* ---------- فرم و اجرای نصب ---------- */
$msg = ''; $done = false; $err = '';
$step = 1;

$req = fn(string $k, string $d = '127.0.0.1') => ($cli ? $CFG['db'] : array_merge($CFG['db'], ['host'=>$_POST['db_host'] ?? $CFG['db']['host']]));

if ($cli) {
    $db_host = $CFG['db']['host']; $db_name = $CFG['db']['name']; $db_user = $CFG['db']['user']; $db_pass = $CFG['db']['pass'];
    $admin_user = 'admin'; $admin_pass = bin2hex(random_bytes(4)) . 'Aa1!'; $site_name = $CFG['site']['name'];
    $step = 2;
} else {
    $db_host = trim($_POST['db_host'] ?? $CFG['db']['host']);
    $db_name = trim($_POST['db_name'] ?? $CFG['db']['name']);
    $db_user = trim($_POST['db_user'] ?? $CFG['db']['user']);
    $db_pass = (string)($_POST['db_pass'] ?? $CFG['db']['pass']);
    $admin_user = trim($_POST['admin_user'] ?? '');
    $admin_pass = (string)($_POST['admin_pass'] ?? '');
    $site_name  = trim($_POST['site_name'] ?? $CFG['site']['name']);
    if (isset($_POST['install'])) $step = 2;
}

if ($step === 2) {
    try {
        /* ساخت دیتابیس */
        $root = new PDO("mysql:host={$db_host};charset=utf8mb4", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if (!$cli) $root->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
        if ($cli) { // در CLI دیتابیس باید ساخته شود
            $base = new PDO("mysql:host={$db_host};charset=utf8mb4", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $base->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if (!($cli ? ($db_user === $CFG['db']['user'] && $db_pass === $CFG['db']['pass'] && $db_host === $CFG['db']['host']) : false)) {
            // بازنویسی config.php با اطلاعات واردشده
            write_config($db_host, $CFG['db']['port'], $db_name, $db_user, $db_pass, $site_name, $CFG);
        }

        /* اسکیمای دیتابیس */
        foreach (schema() as $st) $pdo->exec($st);

        /* حساب مدیر */
        if (strlen($admin_pass) < 8) throw new RuntimeException('رمز مدیر حداقل ۸ کاراکتر باشد.');
        $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
        $pdo->prepare("DELETE FROM admins WHERE username = ?")->execute([$admin_user]);
        $pdo->prepare("INSERT INTO admins (username, password_hash, name) VALUES (?,?,?)")->execute([$admin_user, $hash, 'مدیر ارشد']);

        seed($pdo, $db_host, $CFG);

        file_put_contents($lock, gmdate('c'));
        $done = true; $msg = "نصب کامل شد! کاربر مدیر: {$admin_user} — رمز: {$admin_pass}";
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
        if ($cli) { echo "خطا: ", $err, "\n"; exit(1); }
    }
}

if ($cli) { echo ($done ? "OK — " : ''), $msg, PHP_EOL; exit($done ? 0 : 1); }

/* ---------- نمایش فرم وب ---------- */
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>نصب پورتال خبررسانی</title>
<style>
body{font-family:Tahoma,Vazirmatn,sans-serif;background:#f2f4f8;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center}
.card{background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.08);padding:32px;width:min(560px,94vw)}
h1{font-size:20px;margin:0 0 18px}
label{display:block;font-size:13px;color:#555;margin:12px 0 4px}
input{width:100%;padding:10px;border:1px solid #d8dce4;border-radius:10px;box-sizing:border-box;font-size:14px}
button{margin-top:18px;width:100%;padding:12px;background:#0a7d5a;color:#fff;border:0;border-radius:10px;font-size:15px;cursor:pointer}
.err{background:#fdecec;color:#a33;padding:10px;border-radius:10px;margin-bottom:10px;font-size:13px}
.ok{background:#e9f7f0;color:#0a5d44;padding:10px;border-radius:10px;font-size:14px}
</style>
</head>
<body>
<div class="card">
  <h1>📦 نصب پورتال خبررسانی منطقه آزاد</h1>
  <?php if ($done): ?>
    <div class="ok">✅ نصب با موفقیت انجام شد.<br><code><?= htmlspecialchars($msg) ?></code><br>اکنون می‌توانید <a href="index.php">سایت</a> و <a href="admin.php">پنل مدیریت</a> را باز کنید و این صفحه (install.php و install.lock) را از سرور حذف کنید.</div>
  <?php endif; ?>
  <?php if ($err): ?><div class="err">⛔ <?= htmlspecialchars($err) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <label>آدرس MySQL (Host)</label><input name="db_host" value="<?= htmlspecialchars($db_host) ?>">
    <label>نام دیتابیس</label><input name="db_name" value="<?= htmlspecialchars($db_name) ?>">
    <label>کاربر MySQL</label><input name="db_user" value="<?= htmlspecialchars($db_user) ?>">
    <label>رمز MySQL</label><input name="db_pass" type="password" value="<?= htmlspecialchars($db_pass) ?>">
    <label>نام کاربری مدیر پنل</label><input name="admin_user" value="admin" required>
    <label>رمز عبور مدیر (حداقل ۸ کاراکتر)</label><input name="admin_pass" type="password" required minlength="8">
    <label>نام سایت</label><input name="site_name" value="<?= htmlspecialchars($site_name) ?>">
    <button type="submit" name="install" value="1">نصب کن</button>
  </form>
</div>
</body>
</html>
<?php
exit;

function write_config(string $h, int $port, string $n, string $u, string $pw, string $site, array $old): void {
    $tpl = <<<PHP
<?php
return [
    'db' => ['host' => {H}, 'port' => {PORT}, 'name' => {N}, 'user' => {U}, 'pass' => {PW}, 'charset' => 'utf8mb4'],
    'languages' => ['fa', 'en', 'ar'],
    'site' => [
        'name' => {SITE},
        'email' => 'info@bfzo.ir',
        'phone' => '077-0000-0000',
        'address' => 'بوشهر، منطقه آزاد تجاری–صنعتی بوشهر',
    ],
    'uploads_dir' => __DIR__ . '/uploads',
    'uploads_url' => 'uploads',
    'per_page' => 12,
    'timezone' => 'Asia/Tehran',
    'debug' => false,
];
PHP;
    $code = strtr($tpl, [
        '{H}' => "'{$h}'", '{PORT}' => $port, '{N}' => "'{$n}'", '{U}' => "'{$u}'",
        '{PW}' => "'{$pw}'", '{SITE}' => "'{$site}'",
    ]);
    file_put_contents(__DIR__ . '/config.php', $code);
}

/* ---------- اسکیمای دیتابیس (utf8mb4) ---------- */
function schema(): array {
    $ENGINE = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
"CREATE TABLE IF NOT EXISTS admins (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, name VARCHAR(120) NOT NULL DEFAULT '', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, last_login_at DATETIME NULL){$ENGINE}",
"CREATE TABLE IF NOT EXISTS settings (k VARCHAR(100) PRIMARY KEY, v TEXT){$ENGINE}",
"CREATE TABLE IF NOT EXISTS categories (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(120) UNIQUE NULL, sort_order INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP){$ENGINE}",
"CREATE TABLE IF NOT EXISTS category_translations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_id INT UNSIGNED NOT NULL, lang VARCHAR(2) NOT NULL, name VARCHAR(160) NOT NULL, UNIQUE KEY uq (category_id, lang), KEY (lang), FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE){$ENGINE}",
"CREATE TABLE IF NOT EXISTS articles (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_id INT UNSIGNED NULL, image VARCHAR(300) NULL, source_name VARCHAR(120) NULL, source_url VARCHAR(400) NULL, is_pinned TINYINT(1) DEFAULT 0, is_published TINYINT(1) DEFAULT 0, published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, views INT UNSIGNED DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL, KEY idx_pub (is_published, published_at), FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL){$ENGINE}",
"CREATE TABLE IF NOT EXISTS article_translations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, article_id INT UNSIGNED NOT NULL, lang VARCHAR(2) NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, excerpt TEXT NULL, body LONGTEXT NULL, UNIQUE KEY uq (article_id, lang), KEY (lang), FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE){$ENGINE}",
"CREATE TABLE IF NOT EXISTS article_tags (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, article_id INT UNSIGNED NOT NULL, tag VARCHAR(60) NOT NULL, KEY (tag), FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE){$ENGINE}",
"CREATE TABLE IF NOT EXISTS announcements (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, is_published TINYINT(1) DEFAULT 1, published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, created_at DATETIME DEFAULT CURRENT_TIMESTAMP){$ENGINE}",
"CREATE TABLE IF NOT EXISTS announcement_translations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, announcement_id INT UNSIGNED NOT NULL, lang VARCHAR(2) NOT NULL, title VARCHAR(255) NOT NULL, body TEXT NULL, UNIQUE KEY uq (announcement_id, lang), FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE){$ENGINE}",
"CREATE TABLE IF NOT EXISTS events (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_date DATE NULL, event_time TIME NULL, is_published TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP){$ENGINE}",
"CREATE TABLE IF NOT EXISTS event_translations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_id INT UNSIGNED NOT NULL, lang VARCHAR(2) NOT NULL, title VARCHAR(255) NOT NULL, description TEXT NULL, UNIQUE KEY uq (event_id, lang), FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE){$ENGINE}",
"CREATE TABLE IF NOT EXISTS media_items (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, type ENUM('photo','video') DEFAULT 'photo', source_url VARCHAR(500) NULL, is_published TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP){$ENGINE}",
"CREATE TABLE IF NOT EXISTS media_translations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, media_id INT UNSIGNED NOT NULL, lang VARCHAR(2) NOT NULL, title VARCHAR(255) NOT NULL, caption VARCHAR(400) NULL, UNIQUE KEY uq (media_id, lang), FOREIGN KEY (media_id) REFERENCES media_items(id) ON DELETE CASCADE){$ENGINE}",
"CREATE TABLE IF NOT EXISTS investments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, image VARCHAR(300) NULL, is_published TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP){$ENGINE}",
"CREATE TABLE IF NOT EXISTS investment_translations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, investment_id INT UNSIGNED NOT NULL, lang VARCHAR(2) NOT NULL, title VARCHAR(255) NOT NULL, summary VARCHAR(400) NULL, description TEXT NULL, UNIQUE KEY uq (investment_id, lang), FOREIGN KEY (investment_id) REFERENCES investments(id) ON DELETE CASCADE){$ENGINE}",
"CREATE TABLE IF NOT EXISTS pulse_metrics (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sort_order INT DEFAULT 0, is_active TINYINT(1) DEFAULT 1){$ENGINE}",
"CREATE TABLE IF NOT EXISTS pulse_metric_translations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, metric_id INT UNSIGNED NOT NULL, lang VARCHAR(2) NOT NULL, label VARCHAR(120) NOT NULL, value VARCHAR(60) NOT NULL, note VARCHAR(255) NULL, UNIQUE KEY uq (metric_id, lang), FOREIGN KEY (metric_id) REFERENCES pulse_metrics(id) ON DELETE CASCADE){$ENGINE}",
    ];
}

/* ---------- داده‌های ابتدایی (seed) ---------- */
function seed(PDO $pdo, string $host, array $CFG): void {
    if ((int)$pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn() > 0) return; // فقط یک‌بار

    /* دسته‌ها */
    $catNames = [
        'strategic'  => ['fa'=>'اخبار استراتژیک', 'en'=>'Strategic News', 'ar'=>'أخبار استراتجية'],
        'investment' => ['fa'=>'سرمایه‌گذاری', 'en'=>'Investment', 'ar'=>'الاستثمار'],
        'industry'   => ['fa'=>'صنعت و تولید', 'en'=>'Industry', 'ar'=>'الصناعة'],
        'trade'      => ['fa'=>'تجارت و صادرات', 'en'=>'Trade', 'ar'=>'التجارة'],
        'tourism'    => ['fa'=>'گردشگری', 'en'=>'Tourism', 'ar'=>'السياحة'],
        'transport'  => ['fa'=>'حمل‌ونقل و بنادر', 'en'=>'Transport & Ports', 'ar'=>'النقل والموانئ'],
        'health'     => ['fa'=>'سلامت و درمان', 'en'=>'Health', 'ar'=>'الصحة'],
        'energy'     => ['fa'=>'انرژی و خلیج فارس', 'en'=>'Energy', 'ar'=>'الطاقة'],
    ];
    $catMap = [];
    $cats = [];
    $st = $pdo->prepare("INSERT INTO categories (sort_order) VALUES (?)");
    $st2 = $pdo->prepare("INSERT INTO category_translations (category_id, lang, name) VALUES (?,?,?)");
    $i = 1;
    foreach ($catNames as $key => $names) {
        $st->execute([$i]); $id = (int)$pdo->lastInsertId(); $catMap[$key] = $id;
        foreach (['fa','en','ar'] as $l) $st2->execute([$id, $l, $names[$l]]);
        $i++;
    }

    /* تنظیمات */
    $set = $pdo->prepare("INSERT INTO settings (k, v) VALUES (?, ?)");
    foreach ([
        'site_name' => $CFG['site']['name'],
        'site_name_en' => 'Bushehr Free Zone News Portal',
        'site_name_ar' => 'بوابة أخبار المنطقة الحرة',
        'about'  => 'مرکز اطلاع‌رسانی رسمی منطقه آزاد تجاری–صنعتی بوشهر',
        'email'  => $CFG['site']['email'], 'phone' => $CFG['site']['phone'], 'address' => $CFG['site']['address'],
        'telegram' => '', 'instagram' => '', 'twitter' => '',
    ] as $k => $v) $set->execute([$k, $v]);

    /* اخبار از اسنپ‌شات فرینا */
    $json = file_get_contents(__DIR__ . '/data/freenaSnapshot.json');
    if (!$json) return;
    $data = json_decode($json, true); $arts = $data['articles'] ?? [];
    $cnt = 0;
    foreach ($arts as $idx => $a) {
        $img = null;
        if (!empty($a['imageUrl'])) {
            $base = basename(parse_url($a['imageUrl'], PHP_URL_PATH) ?: '');
            $src = __DIR__ . '/uploads/news/' . $base; // در صورت وجود فایل‌های سینک‌شده در پوشه
            foreach (glob(__DIR__ . '/uploads/news/*.jpg') ?: [] as $f) {}
            $img = file_exists($src) ? 'uploads/news/' . $base : null;
        }
        $pub = date('Y-m-d H:i:s', strtotime($a['publishedAt'] ?? 'now'));
        $pdo->prepare("INSERT INTO articles (category_id, image, source_name, source_url, is_pinned, is_published, published_at)
                       VALUES (?,?,?,?,1,1,?)")
            ->execute([$catMap[$a['categoryId']] ?? $catMap['strategic'], $img, $a['sourceName'] ?? '', $a['sourceUrl'] ?? '', $pub]);
        $aid = (int)$pdo->lastInsertId();
        $slug = trim(preg_replace('~[^\p{L}\p{N}]+~u', '-', $a['title'] ?? '') ?? '', '-') ?: "news-$aid";
        $pdo->prepare("INSERT INTO article_translations (article_id, lang, title, slug, excerpt, body) VALUES (?,?,?,?,?,?)")
            ->execute([$aid, 'fa', $a['title'] ?: 'خبر', $slug, $a['summary'] ?? '', $a['content'] ?? '']);
        foreach (array_slice($a['tags'] ?? [], 0, 12) as $tg)
            $pdo->prepare("INSERT INTO article_tags (article_id, tag) VALUES (?,?)")->execute([$aid, mb_substr(trim($tg), 0, 60)]);
        $cnt++;
    }

    /* شاخص‌های پالس (نمونه — در مدیریت ویرایش‌پذیر) */
    $metrics = [
        ['fa'=>['label'=>'شاغل مستقیم','value'=>'1,200+','note'=>'آمار به‌روزرسانی ۱۴۰۵'], 'en'=>['label'=>'Direct jobs','value'=>'1,200+','note'=>'Updated FY 2026'], 'ar'=>['label'=>'فرصات عمل مباشرة','value'=>'+1200','note'=>'بيانات محدثة 2026']],
        ['fa'=>['label'=>'واحد فعال تولیدی','value'=>'38','note'=>'پروژه‌های بهره‌برداری‌شده'], 'en'=>['label'=>'Active units','value'=>'38','note'=>'Operational projects'], 'ar'=>['label'=>'وحدات تنتج نشطة','value'=>'38','note'=>'مشروعات قائمة']],
        ['fa'=>['label'=>'سرمایه‌گذاری فعال','value'=>'۹ هزار میلیارد ریال','note'=>'جذب‌شده در منطقه'], 'en'=>['label'=>'Active capital','value'=>'IRR 9tn','note'=>'Invested in the zone'], 'ar'=>['label'=>'رأس المال النشط','value'=>'9 تريليون إ.ر','note'=>'المستثمر في المنطقة']],
        ['fa'=>['label'=>'دسته‌بندی اخبار','value'=>'۷ حوزه','note'=>'پوشش چندزبانه'], 'en'=>['label'=>'Coverage areas','value'=>'7','note'=>'Multilingual coverage'], 'ar'=>['label'=>'مجالات التغطية','value'=>'7','note'=>'تغطية ثلاثية اللغة']],
    ];
    foreach ($metrics as $n) {
        $pdo->prepare("INSERT INTO pulse_metrics (sort_order, is_active) VALUES (?,1)")->execute([$i]);
        $mid = (int)$pdo->lastInsertId();
        foreach (['fa','en','ar'] as $l) $pdo->prepare("INSERT INTO pulse_metric_translations (metric_id, lang, label, value, note) VALUES (?,?,?,?,?)")->execute([$mid, $l, $n[$l]['label'], $n[$l]['value'], $n[$l]['note']]);
        $i++;
    }

    /* فرصت‌های سرمایه‌گذاری نمونه */
    $inv = [
        'fa' => ['title'=>'تولید مبتنی بر فرآورده‌های نفتی', 'summary'=>'زمین آماده با زیرساخت کامل در شهرک صنعتی منطقه آزاد', 'desc'=>'امکانات انرژی، دسترسی بندری و معافیت‌های مالیاتی به مدت ۲۰ سال برای واحدهای تولیدی مجاز.'],
        'en' => ['title'=>'Petro-based manufacturing', 'summary'=>'Serviced land plots in the zone industrial park', 'desc'=>'Utility access, port proximity and 20-year tax exemptions for eligible production units.'],
        'ar' => ['title'=>'وحدات تصنيعية مقر ة على رو روشها', 'summary'=>'أراضٍ مجهزة في الحديقة الصناعية للمنطقة', 'desc'=>'أدوات طاقة، قرب الميناء وإعفاءات ضريبية لمدة 20 عاماً.'],
    ];
    $pdo->prepare("INSERT INTO investments (is_published) VALUES (1)")->execute();
    $iid = (int)$pdo->lastInsertId();
    foreach (['fa','en','ar'] as $l) $pdo->prepare("INSERT INTO investment_translations (investment_id, lang, title, summary, description) VALUES (?,?,?,?,?)")->execute([$iid, $l, $inv[$l]['title'], $inv[$l]['summary'], $inv[$l]['desc']]);

    /* رویدادهای نمونه */
    $ev = [
        ['fa'=>['title'=>'نشست هم‌اندیشی سرمایه‌گذاران منطقه آزاد', 'description'=>'بررسی فرصت‌های سرمایه‌گذاری و پاسخ به پرسش‌های سرمایه‌گذاران، ساعت ۱۰ صبح، سالن همایش‌های منطقه آزاد.'],
         'en'=>['title'=>'Investor workshop at BFZ', 'description'=>'Discussing investment opportunities, 10:00, Zone conference hall.'],
         'ar'=>['title'=>'لاقة المستثمرين في المنطقة', 'description'=>'مناقشة فرصات الاستثمار، الساعة 10 صباحاً، قاعة المؤتمرات.']],
        ['fa'=>['title'=>'جشنواره گردشگری خلیج فارس', 'description'=>'برگزاری رویداد فرهنگی-گردشگری با حضور مسافران و فعالان توریسم.'],
         'en'=>['title'=>'Persian Gulf tourism festival', 'description'=>'Cultural and tourism event with visitors and tourism stakeholders.'],
         'ar'=>['title'=>'مهرجان السياحة في الخليج', 'description'=>'فعالية ثقافية وسياحية بمشاركة الزوار و فعالين السياحة.']],
    ];
    $base = time();
    foreach ($ev as $n) {
        $pdo->prepare("INSERT INTO events (event_date, event_time, is_published) VALUES (?,?,1)")->execute([date('Y-m-d', $base + 7*86400), '10:00:00']);
        $eid = (int)$pdo->lastInsertId();
        foreach (['fa','en','ar'] as $l) $pdo->prepare("INSERT INTO event_translations (event_id, lang, title, description) VALUES (?,?,?,?)")->execute([$eid, $l, $n[$l]['title'], $n[$l]['description']]);
        $base += 6*86400;
    }

    /* اطلاعیه و چندرسانه نمونه */
    $pdo->prepare("INSERT INTO announcements (is_published) VALUES (1)")->execute();
    $acd = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO announcement_translations (announcement_id, lang, title, body) VALUES (?, 'fa', ?, ?)")
        ->execute([$acd, 'ساعتها اداره‌ی هفته جاری', 'بارگذاری اخبار منطقه آزاد در همین هفته انجام خواهد شد. دسترسی همه بخش‌ها ذخیره اخبار آزمایشی است.']);
    $pdo->prepare("INSERT INTO announcements (is_published) VALUES (1)")->execute();
    $acd = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO announcement_translations (announcement_id, lang, title, body) VALUES (?, 'fa', ?, ?)")
        ->execute([$acd, 'دریافت درخواست‌های سرمایه‌گذاری برای پروژه‌های جدید', 'فرم درخواست سرمایه‌گذاری از طریق بخش فرصت‌های سرمایه‌گذاری قابل تکمیل است.']);

    $imgs = glob(__DIR__ . '/uploads/news/*.jpg') ?: [];
    $cntImgs = count($imgs);
    for ($k = 0; $k < min(4, $cntImgs); $k++) {
        $pdo->prepare("INSERT INTO media_items (type, source_url, is_published) VALUES ('photo', ?, 1)")->execute(['uploads/news/' . basename($imgs[$k])]);
        $mid = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO media_translations (media_id, lang, title, caption) VALUES (?, 'fa', ?, ?)")
            ->execute([$mid, 'گزارش تصویری رویدادهای منطقه', 'تصاویر ثبت‌شده از مراسم و نشست‌های منطقه آزاد بوشهر']);
    }
}
