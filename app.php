<?php
/**
 * هسته برنامه: خبررسانی منطقه آزاد بوشهر
 * PHP 8+ / MySQL-PDO / چندزبانه fa-en-ar / بدون وابستگی خارجی
 */
declare(strict_types=1);

/* ---------- بارگذاری پیکربندی ---------- */
if (!function_exists('app')) {
    function app(): array { static $cfg; return $cfg ??= (require __DIR__ . '/config.php'); }
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $c = app()['db'];
        try {
            $pdo = new PDO(
                "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}",
                $c['user'], $c['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES => false]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            exit(app()['debug'] ? 'DB Error: ' . $e->getMessage() : 'خطا در اتصال به دیتابیس. نصب‌کننده را اجرا کنید: <a href="install.php">install.php</a>');
        }
    }
    return $pdo;
}

function q(string $sql, array $p = []): PDOStatement { $st = db()->prepare($sql); $st->execute($p); return $st; }
function q1(string $sql, array $p = []): ?array  { $r = q($sql, $p)->fetch(); return $r === false ? null : $r; }
function qall(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function qval(string $sql, array $p = [])        { return q($sql, $p)->fetchColumn(); }

/* ---------- نشست و کاربر مدیر ---------- */
function boot_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        @session_start();
    }
}

/* ---------- کمکی‌های عمومی ---------- */
function e(?string $s): string          { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $to): never    { header('Location: ' . $to); exit; }
function post(string $k, string $d = ''): string { return is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : $d; }
function getp(string $k, string $d = ''): string { return is_string($_GET[$k] ?? null) ? trim($_GET[$k]) : $d; }
function ini(string $k, int $d = 0): int { return (int)($_GET[$k] ?? $d); }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }

/** ساخت آدرس با روتینگ پرس‌وجوئی (بدون نیاز به mod_rewrite) */
function url(string $p, array $qs = []): string {
    $qs['p'] = $p; $qs['lang'] = lang();
    foreach ($qs as $k => $v) if ($v === null || $v === '') unset($qs[$k]);
    return 'index.php?' . http_build_query($qs);
}
function admin_url(string $a, array $qs = []): string {
    $qs['a'] = $a; foreach ($qs as $k => $v) if ($v === null || $v === '') unset($qs[$k]);
    return 'admin.php?' . http_build_query($qs);
}
function asset(string $f): string { return 'assets/' . $f; }
function img(?string $path): ?string {
    if (!$path) return null;
    if (preg_match('~^https?://~i', $path)) return $path;
    return !str_starts_with($path, 'uploads/') && !str_starts_with($path, 'assets/') ? app()['uploads_url'] . '/' . ltrim($path, '/') : $path;
}
function excerpt(string $s, int $n = 180): string {
    $s = trim(preg_replace('~\s+~u', ' ', strip_tags($s)) ?? '');
    if (mb_strlen($s) <= $n) return $s;
    $s = mb_substr($s, 0, $n);
    // حذف علائم انتهایی به‌شیوه چندبایتی (rtrim بایت‌محور کاراکتر فارسی را خراب می‌کند)
    $s = preg_replace('~[\s،,.]+$~u', '', $s) ?? $s;
    return $s . '…';
}

/* ---------- تاریخ شمسی (جلالی) ---------- */
function g2j(int $gy, int $gm, int $gd): array {
    $gdos = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? $gy + 1 : $gy;
    $days = 355666 + (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) + $gd + $gdos[$gm - 1];
    $jy = -1595 + 33 * ((int)($days / 12053)); $days %= 12053;
    $jy += 4 * ((int)($days / 1461)); $days %= 1461;
    if ($days > 365) { $jy += (int)(($days - 1) / 365); $days = ($days - 1) % 365; }
    $jm = $days < 186 ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + ($days % ($days < 186 ? 31 : 30));
    return [$jy, $jm, $jd];
}
function fa_months(): array { return [1=>'فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند']; }
function ar_months(): array { return [1=>'كانين الثاني','شباط','آذار','نيسان','أيار','حزيران','تموز','آب','أيلول','تشرين الأول','تشرين الثاني','كانين الأول']; }

/** تاریخ+ساعت به‌روز شده با زبان جاری */
function date_i18n(string $dt, bool $withTime = true): string {
    $ts = strtotime($dt); if (!$ts) return '';
    $lang = lang();
    $y = date('Y', $ts); $m = (int)date('n', $ts); $d = (int)date('j', $ts);
    if ($lang === 'fa') {
        [$jy, $jm, $jd] = g2j((int)$y, (int)$m, (int)$d);
        $fa = fa_months(); $persianDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $str = strtr("$jd {$fa[$jm]} $jy", array_combine(range(0, 9), $persianDigits));
        return $withTime ? $str . '، ' . date('H:i', $ts) : $str;
    }
    if ($lang === 'ar') {
        $mo = ar_months();
        return ar_num($d) . ' ' . $mo[$m] . ' ' . ar_num((string)$y) . ($withTime ? '، ' . ar_num(date('H:i', $ts)) : '');
    }
    return date('j M Y', $ts) . ($withTime ? ', ' . date('H:i', $ts) : '');
}

/* ---------- چندزبانه ---------- */
function lang(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) boot_session();
    static $l = null;
    if ($l === null) {
        $allowed = app()['languages'];
        $g = getp('lang');
        $l = in_array($g, $allowed, true) ? $g : ($_SESSION['lang'] ?? null);
        if (!$l) $l = $allowed[0];
        $_SESSION['lang'] = $l;
    }
    return $l;
}
function set_lang(string $lang): void { $_SESSION['lang'] = $lang; }
function all_langs(): array { return app()['languages']; }
function dir_of(?string $l = null): string { return ($l ?? lang()) === 'fa' ? 'rtl' : 'ltr'; }
function html_attr(?string $l = null): string { return ($l ?? lang()) === 'fa' ? 'fa-IR' : (($l ?? lang()) === 'ar' ? 'ar' : 'en'); }
function fa_num(int|string $n): string {
    if (lang() !== 'fa') return (string)$n;
    return strtr((string)$n, array_combine(range(0, 9), ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹']));
}
/** ارقام عربی ٠-٩ (بدون توجه به زبان) */
function ar_num(int|string $n): string {
    return strtr((string)$n, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
                              '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
}
/** عدد به ارقام زبان جاری: fa → ۰-۹، ar → ٠-٩، en → 0-9 */
function num_i18n(int|string $n): string {
    return match (lang()) {
        'fa' => fa_num($n),
        'ar' => ar_num($n),
        default => (string)$n,
    };
}

/** دیکشنری رابط کاربری fa/en/ar */
const I18N = [
  'home'         => ['خانه', 'Home', 'الرئيسية'],
  'news'         => ['اخبار', 'News', 'الأخبار'],
  'announcements'=> ['اطلاعیه‌ها', 'Announcements', 'الإعلانات'],
  'events'       => ['رویدادها', 'Events', 'الأحداث'],
  'media'        => ['چندرسانه', 'Multimedia', 'وسائط'],
  'investments'  => ['فرصت‌های سرمایه‌گذاری', 'Investment Opportunities', 'فرصت الاستثمار'],
  'search'       => ['جستجو', 'Search', 'البحث'],
  'search_ph'    => ['جستجو در اخبار و مطالب…', 'Search news and posts…', 'البحث في الأخبار والمقالات…'],
  'read_more'    => ['ادامه مطلب', 'Read more', 'اقرأ المزيد'],
  'latest_news'  => ['آخرین اخبار', 'Latest News', 'أحدث الأخبار'],
  'headline'     => ['تیتر خبر', 'Headline', 'العنوان الرئيسي'],
  'top_story'    => ['خبر مهم', 'Top Story', 'الخبر الأبرز'],
  'source'       => ['منبع', 'Source', 'المصدر'],
  'views'        => ['بازدید', 'Views', 'المشاهدات'],
  'published'    => ['انتشار', 'Published', 'منشور'],
  'not_found'    => ['موردی یافت نشد', 'Nothing found', 'لا يوجد نتائج'],
  'related'      => ['اخبار مرتبط', 'Related News', 'أخبار ذات صلة'],
  'all_categories'=> ['همه دسته‌ها', 'All categories', 'كل الفئات'],
  'no_items'     => ['موردی برای نمایش وجود ندارد', 'No items to display', 'لا توجد عناصر للعرض'],
  'footer_desc'  => ['مرکز اطلاع‌رسانی رسمی منطقه آزاد تجاری–صنعتی بوشهر؛ پوشش لحظه‌ای اخبار، اطلاعیه‌ها و رویدادهای منطقه.',
                     'Official information portal of Bushehr Free Trade–Industrial Zone; covering news, announcements and events.',
                     'بوابة المعلومات الرسمية لمنطقة بوشهر الحرة؛ تغطية الأخبار والإعلانات والأحداث.'],
  'quick_links'  => ['دسترسی سریع', 'Quick links', 'روابط سريعة'],
  'contact'      => ['ارتباط با ما', 'Contact us', 'اتصل بنا'],
  'about'        => ['درباره ما', 'About us', 'من نحن'],
  'about_portal' => ['درباره ما', 'About', 'حول'],
  'merged_ann'   => ['اطلاعیه‌ها و فرصت‌های سرمایه‌گذاری', 'Announcements & Investment Opportunities', 'الإعلانات و فرص الاستثمار'],
  'multimedia'   => ['چند رسانه‌ای', 'Multimedia', 'الوسائط المتعددة'],
  'photos'       => ['عکس', 'Photos', 'الصور'],
  'videos'       => ['ویدئو', 'Videos', 'الفيديو'],
  'services_portal' => ['درگاه خدمات مناطق آزاد', 'Free Zones Services Portal', 'بوابة خدمات المناطق الحرة'],
  'zr_rules'     => ['قوانین و مقررات', 'Rules & Regulations', 'القوانين واللوائح'],
  'zr_suggest'   => ['پیشنهادات و شکایات', 'Suggestions & Complaints', 'الاقتراحات والشكاوى'],
  'links_col'    => ['پیوندها', 'Useful links', 'روابط مفيدة'],
  'sec_portal'   => ['دبیرخانه شورایعالی مناطق آزاد و ویژه اقتصادی', 'Secretariat of the Supreme Council of Free Zones', 'أمانة المجلس الأعلى للمناطق الحرة'],
  'pmo'          => ['سازمان بنادر و دریانوردی', 'Ports & Maritime Organization', 'منظمة الموانئ والملاحة'],
  'irica'        => ['اداره کل گمرک ایران', 'Islamic Republic of Iran Customs', 'الجمارك الإيرانية'],
  'fz_mag'       => ['فرینا؛ پایگاه خبری مناطق آزاد', 'Freena; Free Zones News Agency', 'فرينا؛ وكالة أخبار المناطق الحرة'],
  'mc_photo'     => ['عکس و گالری', 'Photos & gallery', 'صور ومعارض'],
  'mc_video'     => ['ویدئو و مستند', 'Videos & documentaries', 'مرئيات ووثائقيات'],
  'about_body_p1' => ['«خبررسانی منطقه آزاد» مرجع رسمی اطلاع‌رسانی منطقه آزاد تجاری–صنعتی بوشهر است. مأموریت ما انتشار دقیق و به‌هنگام اخبار، اطلاعیه‌ها، فرصت‌های سرمایه‌گذاری و رویدادهای این منطقه برای فعالان اقتصادی، سرمایه‌گذاران داخلی و خارجی، رسانه‌ها و عموم مردم است.',
                      'The Bushehr Free Zone Portal is the official reference for information on the Bushehr Free Trade–Industrial Zone. Our mission is accurate and timely publication of news, announcements, investment opportunities and events for businesses, domestic and foreign investors, media and the public.',
                      '«بوابة المنطقة الحرة» هي المرجع الرسمي لنشر معلومات منطقة بوشهر الحرة التجارية والصناعية. مهمتنا نشر الأخبار والإعلانات وفرص الاستثمار والفعاليات بدقة وبتوقيت مناسب لرجال الأعمال والمستثمرين والإعلام والجمهور.'],
  'about_body_p2' => ['منطقه آزاد بوشهر با برخورداری از دسترسی مستقیم به آب‌های آزاد خلیج فارس، بنادر مهم تجاری، زیرساخت‌های انرژی و همجواری با بازارهای منطقه‌ای، به‌عنوان یکی از کانون‌های توسعه تجارت، تولید و اقتصاد دریامحور کشور در حال شکل‌گیری و توسعه است و این پایگاه اخبار، مستندکننده مسیر این توسعه است.',
                      'With direct access to the open waters of the Persian Gulf, major trade ports, energy infrastructure and proximity to regional markets, the Bushehr Free Zone is emerging as a hub for trade, production and sea-based economy; this portal documents that development path.',
                      'مع الطريق المباشر إلى مياه الخليج العربي المفتوحة والموانئ التجارية الكبرى وبنية الطاقة التحتية والقرب من الأسواق الإقليمية، تبرز منطقة بوشهر الحرة كركن لتطوير التجارة والإنتاج والاقتصاد البحري، وهذه البوابة توثق هذا المسار.'],
  'about_body_p3' => ['اطلاعات این پرتال در سه زبان فارسی، انگلیسی و عربی گردآوری می‌شود و منابع خبری معتبر از جمله فرینا، ایرنا، برنا، ندای استان و تسنیم، اصلی‌ترین پشتوانه محتوای اخبار ما هستند.',
                      'Content of this portal is published in three languages — Persian, English and Arabic — and credible news sources such as Freena, IRNA, Borna, Nedayostan and Tasnim are the main foundation of our news coverage.',
                      'يتم إعداد محتوى هذا الموقع بثلاث لغات: الفارسية والإنجليزية والعربية، وتُعتمد شبكة محترفة من المصادر مثل فرينا وإيرنا وبرنا ونيداء الاستان وتسنيم.'],
  'contact_intro' => ['برای ارتباط با دبیرخانه خبررسانی منطقه آزاد بوشهر از راه‌های زیر استفاده کنید؛ برای پیگیری نظرات و پیشنهادها نیز فرم تماس در دسترس است.',
                      'To reach the editorial office of the Bushehr Free Zone Portal use the channels below.',
                      'للتواصل مع مكتب تحرير بوابة المنطقة الحرة بوشهر استخدم قنوات الاتصال التالية.'],
  'address_lbl'  => ['نشانی', 'Address', 'العنوان'],
  'phone_lbl'    => ['تلفن', 'Phone', 'الهاتف'],
  'email_lbl'    => ['پست الکترونیک', 'E-mail', 'البريد الإلكتروني'],
  'hours_lbl'    => ['ساعت پاسخگویی', 'Office hours', 'ساعات الدوام'],
  'hours_val'    => ['شنبه تا چهارشنبه، ۸ تا ۱۵', 'Sat–Wed, 8:00–15:00', 'الأحد حتى الأربعاء، ٨:٠٠–١٥:٠٠'],
  'more'         => ['مشاهده همه', 'View all', 'عرض الكل'],
];
function t(string $k): string {
    $row = I18N[$k] ?? null; if (!$row) return $k;
    return $row[array_search(lang(), all_langs(), true)] ?? $row[0];
}

/** ترجمه یک ردیف چندزبانه با بازگشت به زبان پیش‌فرض */
function tr(array $translations, string $field, string $lang): string {
    return trim((string)($translations[$lang][$field] ?? $translations[all_langs()[0]][$field] ?? ''));
}

/* ---------- سلاگ ---------- */
function slugify(string $s): string {
    $s = trim(mb_strtolower(strip_tags(stripslashes($s))));
    $s = preg_replace('~[^\p{L}\p{N}]+~u', '-', $s);
    return trim($s ?? '', '-') ?: (string)time();
}

/* ---------- CSRF ---------- */
function csrf_token(): string {
    boot_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(20));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $ok = isset($_POST['_csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['_csrf']);
    if (!$ok) { http_response_code(403); exit('Invalid token.'); }
}

/* ---------- احراز هویت مدیر ---------- */
function auth_login(string $user, string $pass): bool {
    $a = q1("SELECT * FROM admins WHERE username = ?", [$user]);
    if ($a && password_verify($pass, $a['password_hash'])) {
        boot_session(); session_regenerate_id(true);
        $_SESSION['admin'] = ['id' => (int)$a['id'], 'name' => $a['name'], 'user' => $a['username']];
        q("UPDATE admins SET last_login_at = NOW() WHERE id = ?", [$a['id']]);
        return true;
    }
    return false;
}
function admin_logout(): void { boot_session(); unset($_SESSION['admin']); }
function is_admin(): bool { boot_session(); return !empty($_SESSION['admin']); }
function admin_guard(): void {
    if (!is_admin()) redirect('admin.php?a=login');
}
function current_admin(): array { return $_SESSION['admin'] ?? ['name'=>'', 'user'=>'']; }

/* ---------- آپلود تصویر ---------- */
function handle_upload(string $field, string $subdir): ?string {
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 4 * 1024 * 1024) return null;
    $fi = new finfo(FILEINFO_MIME_TYPE); $mime = $fi->file($f['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) return null;
    $dir = app()['uploads_dir'] . '/' . $subdir . '/' . date('Y/m');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    return move_uploaded_file($f['tmp_name'], "$dir/$name")
        ? app()['uploads_url'] . '/' . $subdir . '/' . date('Y/m') . '/' . $name
        : null;
}

/* ---------- تنظیمات ---------- */
function setting(string $k, string $d = ''): string { return (string)(qval("SELECT v FROM settings WHERE k = ?", [$k]) ?: $d); }
function settings_all(): array { return array_column(qall("SELECT k, v FROM settings"), 'v', 'k'); }

/* ---------- دسته‌ها ---------- */
function categories(bool $ordered = true): array {
    $rows = qall("SELECT c.*, t.name FROM categories c
                  LEFT JOIN category_translations t ON t.category_id = c.id AND t.lang = ?" . ($ordered ? ' ORDER BY c.sort_order, c.id' : ''), [lang()]);
    foreach ($rows as &$r) {
        if (trim((string)$r['name']) === '') {
            $r['name'] = (string)qval("SELECT name FROM category_translations WHERE category_id = ? AND lang = ?", [$r['id'], all_langs()[0]]);
        }
    }
    return $rows;
}
function category_save(int $id, array $dxf): int {
    if ($id) q("UPDATE categories SET sort_order = ? WHERE id = ?", [(int)(post('sort_order') ?: 0), $id]);
    else { q("INSERT INTO categories (sort_order) VALUES (?)", [(int)(post('sort_order') ?: 0)]); $id = (int)db()->lastInsertId(); }
    foreach (all_langs() as $lng) {
        if (trim($dxf[$lng]['name'] ?? '') !== '')
            q("INSERT INTO category_translations (category_id, lang, name) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name = VALUES(name)", [$id, $lng, $dxf[$lng]['name']]);
    }
    return $id;
}

/* ---------- اخبار ---------- */
/** ساختن join ترجمه با بازگشت به زبان پیش‌فرض */
function art_join(string $alias = 'a'): string {
    $l = lang(); $f = all_langs()[0];
    $fl = fn($x) => "'" . str_replace("'", '', (string)$x) . "'";
    return " LEFT JOIN article_translations tl ON tl.article_id = {$alias}.id AND tl.lang = {$fl($l)}
             LEFT JOIN article_translations tf ON tf.article_id = {$alias}.id AND tf.lang = {$fl($f)}";
}
function art_fields(): string {
    return "COALESCE(tl.title, tf.title) AS title, COALESCE(tl.excerpt, tf.excerpt) AS excerpt, COALESCE(tl.body, tf.body) AS body";
}

function articles_query(string $where, array $p, int $limit = 0, int $off = 0, bool $count = false, string $order = ''): array {
    $w = " WHERE a.is_published = 1" . ($where !== '' ? " AND $where" : '');
    if ($count) return [qval("SELECT COUNT(*) FROM articles a" . art_join('a') . $w, $p)];
    $sql = "SELECT a.*, " . art_fields() . ", c.slug AS cslug FROM articles a
            LEFT JOIN categories c ON c.id = a.category_id" . art_join('a') . $w .
           " ORDER BY " . ($order !== '' ? $order : 'a.is_pinned DESC, a.published_at DESC, a.id DESC') .
           ($limit ? " LIMIT $limit OFFSET $off" : '');
    return qall($sql, $p);
}
function article_find(int $id): ?array {
    return q1("SELECT a.*, " . art_fields() . " FROM articles a" . art_join('a') . " WHERE a.id = ? AND a.is_published = 1", [$id]);
}
function article_admin(int $id): ?array {
    $a = q1("SELECT * FROM articles WHERE id = ?", [$id]); if (!$a) return null;
    foreach (qall("SELECT * FROM article_translations WHERE article_id = ?", [$id]) as $t) $a['tr'][$t['lang']] = $t;
    foreach (qall("SELECT tag FROM article_tags WHERE article_id = ?", [$id]) as $t) $a['tags'][] = $t['tag'];
    return $a;
}
function article_save(int $id, array $vals, array $dxf): int {
    if ($id) {
        $sets = []; $p = [];
        foreach (['category_id','image','source_name','source_url','is_pinned','is_published','published_at'] as $r) {
            if (array_key_exists($r, $vals)) { $sets[] = "$r = ?"; $p[] = $vals[$r]; }
        }
        if ($sets) { $p[] = $id; q("UPDATE articles SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?", $p); }
    } else {
        $cols = ['category_id','image','source_name','source_url','is_pinned','is_published','published_at'];
        $v = []; foreach ($cols as $c) $v[] = $vals[$c] ?? null;
        q("INSERT INTO articles (" . implode(',', $cols) . ") VALUES (" . rtrim(str_repeat('?,', 7), ',') . ")", $v);
        $id = (int)db()->lastInsertId();
    }
    foreach (all_langs() as $lng) {
        $t = $dxf[$lng];
        if (trim((string)($t['title'] ?? '')) !== '') {
            q("INSERT INTO article_translations (article_id, lang, title, slug, excerpt, body) VALUES (?,?,?,?,?,?)
              ON DUPLICATE KEY UPDATE title = VALUES(title), slug = VALUES(slug), excerpt = VALUES(excerpt), body = VALUES(body)",
              [$id, $lng, $t['title'] ?? '', slugify($t['title'] ?? ''), $t['excerpt'] ?? '', $t['body'] ?? '']);
        } else {
            q("DELETE FROM article_translations WHERE article_id = ? AND lang = ?", [$id, $lng]);
        }
    }
    q("DELETE FROM article_tags WHERE article_id = ?", [$id]);
    $tags = array_values(array_filter(array_map('trim', preg_split('~[,،]~u', $vals['tags'] ?? '') ?: [])));
    foreach (array_slice($tags, 0, 12) as $tg)
        q("INSERT INTO article_tags (article_id, tag) VALUES (?, ?)", [$id, mb_substr($tg, 0, 60)]);
    return $id;
}
function article_delete(int $id): void {
    q("DELETE FROM article_translations WHERE article_id = ?", [$id]);
    q("DELETE FROM article_tags WHERE article_id = ?", [$id]);
    q("DELETE FROM articles WHERE id = ?", [$id]);
}

/* ---------- سایر محتواها (اطلاعیه، رویداد، رسانه، سرمایه‌گذاری، پالس) ---------- */
function tr_lookup(string $table, string $fk, int $id): array {
    $out = []; foreach (qall("SELECT * FROM {$table} WHERE {$fk} = ?", [$id]) as $r) $out[$r['lang']] = $r; return $out;
}

function x_save(string $base, string $trTable, string $fk, int $id, array $cols, array $vals, array $dxf, array $trCols): int {
    if ($id) {
        $sets = []; $p = [];
        foreach ($cols as $r) if (array_key_exists($r, $vals)) { $sets[] = "$r = ?"; $p[] = $vals[$r]; }
        if ($sets) { $p[] = $id; q("UPDATE {$base} SET " . implode(', ', $sets) . " WHERE id = ?", $p); }
    } else {
        $k = array_keys($vals); q("INSERT INTO {$base} (" . implode(',', $k) . ") VALUES (" . rtrim(str_repeat('?,', count($k)), ',') . ")", array_values($vals));
        $id = (int)db()->lastInsertId();
    }
    foreach (all_langs() as $lng) {
        $t = $dxf[$lng] ?? [];
        $has = false; foreach ($trCols as $c) if (trim((string)($t[$c] ?? '')) !== '') { $has = true; break; }
        if ($has) {
            $qk = [$fk, 'lang']; $qv = [$id, $lng];
            foreach ($trCols as $c) { $qk[] = $c; $qv[] = $t[$c] ?? ''; }
            $ph = rtrim(str_repeat('?,', count($qk)), ',');
            $upd = array_map(fn($c) => "$c = VALUES($c)", $trCols);
            q("INSERT INTO {$trTable} (" . implode(',', $qk) . ") VALUES ($ph) ON DUPLICATE KEY UPDATE " . implode(', ', $upd), $qv);
        } else {
            q("DELETE FROM {$trTable} WHERE {$fk} = ? AND lang = ?", [$id, $lng]);
        }
    }
    return $id;
}

function x_list(string $table, string $trTable, string $fk, string $orderCol, bool $onlyPublished, array $trCols, array $where = [], array $p = [], int $limit = 0, int $off = 0): array {
    $w = $onlyPublished ? " WHERE x.is_published = 1" : '';
    if ($where) $w .= ($w ? " AND " : " WHERE ") . implode(' AND ', $where);
    $sql = "SELECT x.*, " . tr_fields($trCols) . " FROM {$table} x" . tr_join($trTable, $fk) . $w . " ORDER BY $orderCol, x.id DESC" . ($limit ? " LIMIT $limit OFFSET $off" : '');
    return qall($sql, $p);
}
function x_count(string $table, string $trTable, string $fk, bool $onlyPublished, array $where = [], array $p = []): int {
    $w = $onlyPublished ? " WHERE x.is_published = 1" : '';
    if ($where) $w .= ($w ? ' AND ' : ' WHERE ') . implode(' AND ', $where);
    return (int)qval("SELECT COUNT(*) FROM {$table} x" . tr_join($trTable, $fk) . $w, $p);
}
/** فقط ستون‌های ترجمه‌ای که جدول دارد، با fallback زبان پیش‌فرض */
function tr_fields(array $cols): string {
    $out = [];
    foreach ($cols as $c) $out[] = "COALESCE(P1.$c, PF.$c) AS $c";
    return implode(', ', $out);
}
function tr_join(string $trTable, string $fk, string $x = 'x'): string {
    $l = lang(); $f = all_langs()[0];
    $fl = fn($s) => "'" . str_replace("'", '', (string)$s) . "'";
    return " LEFT JOIN $trTable P1 ON P1.$fk = $x.id AND P1.lang = {$fl($l)}
             LEFT JOIN $trTable PF ON PF.$fk = $x.id AND PF.lang = {$fl($f)}";
}

/** واکشی یک آیتم با ترجمه‌ها برای فرم مدیریت */
function x_get_admin(string $table, string $trTable, string $fk, int $id): ?array {
    $row = q1("SELECT * FROM $table WHERE id = ?", [$id]); if (!$row) return null;
    $row['tr'] = []; foreach (qall("SELECT * FROM $trTable WHERE $fk = ?", [$id]) as $t) $row['tr'][$t['lang']] = $t;
    return $row;
}
function x_delete(string $table, string $trTable, string $fk, int $id): void {
    q("DELETE FROM $trTable WHERE $fk = ?", [$id]);
    q("DELETE FROM $table WHERE id = ?", [$id]);
}

/** برچسب سریع سه‌زبانه: tl('فارسی','English','العربية') */
function tl(string $fa, string $en, string $ar): string {
    return ['fa' => $fa, 'en' => $en, 'ar' => $ar][lang()] ?? $fa;
}

/** JDN تقویم هجری جدولی (همان الگوریتم moment-hijri) */
function hij_jdn(int $hy, int $hm, int $hd): int {
    return ((int)((11 * $hy + 3) / 30)) + 354 * $hy + 30 * $hm - ((int)(($hm - 1) / 2)) + $hd + 1948440 - 385;
}

/** تبدیل میلادی → هجری قمری [سال، ماه، روز] */
function g2h(int $gy, int $gm, int $gd): array {
    $jdn = (int)gregoriantojd($gm, $gd, $gy);
    $approx = (int)floor(($jdn - 1948440) / 354.367) + 1;
    $best = [max(1, $approx - 1), 1, 1];
    for ($yr = max(1, $approx - 1); $yr <= $approx + 2; $yr++) {
        for ($mo = 1; $mo <= 12; $mo++) {
            $j = hij_jdn($yr, $mo, 1);
            if ($j > $jdn) break;
            $best = [$yr, $mo, $jdn - $j + 1];
        }
    }
    if ($best[2] > 30) { $best[1]++; $best[2] = 1; }
    return $best;
}

function hijri_months(): array {
    return [1=>'محرم','صفر','ربیع‌الاول','ربیع‌الثانی','جمادی‌الاول','جمادی‌الثانی','رجب','شعبان','رمضان','شوال','ذیقعده','ذیحجه',
    /*en*/ 'Muharram','Safar','Rabi al-Awwal','Rabi al-Thani','Jumada al-Awwal','Jumada al-Thani','Rajab','Shaaban','Ramadan','Shawwal','Dhu al-Qadah','Dhu al-Hijjah',
    /*ar*/ 'محرم','صفر','ربيع الأول','ربيع الثاني','جمادى الأولى','جمادى الآخرة','رجب','شعبان','رمضان','شوال','ذو القعدة','ذو الحجة'];
}

/** «۱۰ شوال ۱۴۴۷» بر حسب زبان */
function hijri_date(string $ymd, bool $withYear = true): string {
    $ts = strtotime($ymd); if (!$ts) return '';
    [$hy, $hm, $hd] = g2h((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    $m = hijri_months();
    $l = lang();
    $block = $l === 'en' ? 12 : ($l === 'ar' ? 24 : 0);
    $name = $m[$block + $hm] ?? '';
    $dn = num_i18n($hd);
    $yn = num_i18n($hy);
    return $dn . ' ' . $name . ($withYear ? ' ' . $yn : '');
}

/** نام روز هفته */
function weekday_name(string $ymd): string {
    $w = (int)date('w', strtotime($ymd));
    return tl(['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'][$w],
              ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][$w],
              ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'][$w]);
}
