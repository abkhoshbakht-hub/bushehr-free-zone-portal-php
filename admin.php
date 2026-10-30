<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
boot_session();
mb_internal_encoding('UTF-8');
date_default_timezone_set(app()['timezone']);
header('Content-Type: text/html; charset=utf-8');
$lang = 'fa'; set_lang('fa'); // پنل مدیریت فارسی

/* ---------- چیدمان مدیریت ---------- */
$NAV = [
    'dash' => '📊 داشبورد', 'arts' => '📰 اخبار', 'cats' => '🗂 دسته‌ها', 'anns' => '📢 اطلاعیه‌ها',
    'evs' => '🗓 رویدادها', 'mds' => '🎬 چندرسانه', 'invs' => '💼 سرمایه‌گذاری', 'pulse' => '📈 شاخص‌ها', 'set' => '⚙️ تنظیمات',
];

function admin_header(string $title, string $act): void {
    global $NAV;
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>' . e($title) . ' — پنل مدیریت</title>
<link rel="stylesheet" href="assets/css/admin.css?v=2"></head>
<body data-act="' . e($act) . '"><div class="adm"><aside class="side">
<div class="side-brand">خبررسانی منطقه آزاد<br><small>پنل مدیریت</small></div>';
    foreach ($NAV as $k => $v) echo '<a class="sitem' . ($act === $k ? ' on' : '') . '" href="' . e(admin_url($k)) . '">' . $v . '</a>';
    echo '<div class="spacer"></div>
<a class="sitem dim" href="index.php" target="_blank">🌐 مشاهده سایت</a>
<a class="sitem dim" href="' . e(admin_url('logout')) . '">🚪 خروج</a>
</aside><main class="adm-main"><div class="adm-top"><h1>' . e($title) . '</h1>
<span class="who">' . e(current_admin()['name'] ?: current_admin()['user']) . '</span></div>';
    if (!empty($_SESSION['flash'])) { echo '<div class="note ok">' . e($_SESSION['flash']) . '</div>'; unset($_SESSION['flash']); }
    if (!empty($_SESSION['flash_err'])) { echo '<div class="note err">' . e($_SESSION['flash_err']) . '</div>'; unset($_SESSION['flash_err']); }
}
function admin_footer(): void { echo '</main></div></body></html>'; }

/* ---------- تعریف انواع محتوای عمومی ---------- */
$TYPES = [
    'anns'  => ['t' => 'اطلاعیه‌ها', 'table' => 'announcements', 'tr' => 'announcement_translations', 'fk' => 'announcement_id',
                'tcols' => ['title', 'body'], 'order' => 'published_at DESC',
                'cols' => ['published_at', 'type', 'status', 'reference_code'], 'imgField' => 'image', 'desc' => 'بیانیه‌ها و اطلاعیه‌های رسمی سازمان — نمایش در صفحه «اطلاعیه‌ها و فرصت‌های سرمایه‌گذاری» سایت'],
    'evs'   => ['t' => 'رویدادها', 'table' => 'events', 'tr' => 'event_translations', 'fk' => 'event_id',
                'tcols' => ['title', 'description', 'location', 'category'], 'order' => 'event_date ASC',
                'cols' => ['event_date', 'event_time'], 'imgField' => 'image',
                'lbl' => ['title' => 'عنوان', 'description' => 'شرح رویداد', 'location' => 'محل برگزاری', 'category' => 'موضوع'],
                'desc' => 'همایش‌ها و نشست‌های پیش‌رو — نمایش در صفحه «رویدادها» و بخش خانه سایت'],
    'mds'   => ['t' => 'چندرسانه', 'table' => 'media_items', 'tr' => 'media_translations', 'fk' => 'media_id',
                'tcols' => ['title', 'caption', 'mcategory'], 'order' => 'created_at DESC',
                'cols' => ['type', 'source_url', 'duration', 'photo_count'], 'imgField' => 'source_url',
                'lbl' => ['title' => 'عنوان', 'caption' => 'شرح تصویر', 'mcategory' => 'دسته نمایش'],
                'desc' => 'گزارش تصویری و ویدیویی — نمایش در صفحه «چندرسانه‌ای» سایت'],
    'invs'  => ['t' => 'فرصت‌های سرمایه‌گذاری', 'table' => 'investments', 'tr' => 'investment_translations', 'fk' => 'investment_id',
                'tcols' => ['title', 'summary', 'description', 'category', 'volume', 'irr', 'payback', 'location', 'incentives'],
                'order' => 'created_at DESC', 'cols' => ['image'], 'imgField' => 'image',
                'lbl' => ['title' => 'عنوان', 'summary' => 'خلاصه', 'description' => 'شرح پروژه', 'category' => 'حوزه',
                          'volume' => 'حجم سرمایه', 'irr' => 'نرخ بازگشت (IRR)', 'payback' => 'دوره بازگشت',
                          'location' => 'موقعیت', 'incentives' => 'مشوق‌ها (با | جدا کنید)'],
                'desc' => 'پروژه‌های پیشنهادی سرمایه‌گذاری — نمایش در صفحه «اطلاعیه‌ها و فرصت‌های سرمایه‌گذاری» سایت'],
];

$a = getp('a', 'dash');

/* ---------- احراز هویت ---------- */
if ($a === 'login' || is_admin() === false && $a === 'login') {
    if (is_post() && post('op') === 'login') {
        csrf_check();
        if (auth_login(post('u'), post('pw'))) redirect(admin_url('dash'));
        $_SESSION['flash_err'] = 'نام کاربری یا رمز نادرست است.';
        redirect(admin_url('login'));
    }
    admin_header('ورود', 'login');
    echo '<div class="loginbox"><form method="post" autocomplete="off">' . csrf_field() . '
    <input type="hidden" name="op" value="login">
    <label>نام کاربری</label><input name="u" required autofocus>
    <label>رمز عبور</label><input name="pw" type="password" required>
    <button class="btn">ورود</button></form></div>';
    admin_footer(); exit;
}
admin_guard();

/* چهار خبر تازه منتشرشده = هیرو صفحه اصلی (منطق دقیقاً مثل index.php) */
$hero_ids = array_map(fn($r) => (int)$r['id'], qall("SELECT id FROM articles WHERE is_published = 1 ORDER BY published_at DESC, id DESC LIMIT 4"));

/* ---------- تابع عمومی حذف ---------- */
    if (is_post() && post('op') === 'del') {
        csrf_check();
        $id = ini_post_local('id');
        if ($a === 'arts') { article_delete((int)$id); flash('خبر حذف شد.'); redirect(admin_url('arts')); }
        if (isset($TYPES[$a])) { $T = $TYPES[$a]; x_delete($T['table'], $T['tr'], $T['fk'], (int)$id); flash('آیتم حذف شد.'); redirect(admin_url($a)); }
        if ($a === 'cats') { q("DELETE FROM categories WHERE id = ?", [$id]); flash('دسته حذف شد.'); redirect(admin_url('cats')); }
        if ($a === 'pulse') { q("DELETE FROM pulse_metrics WHERE id = ?", [$id]); q("DELETE FROM pulse_metric_translations WHERE metric_id = ?", [$id]); flash('حذف شد.'); redirect(admin_url('pulse')); }
    }

function flash(string $msg): void { $_SESSION['flash'] = $msg; }
function ini_post_local(string $k): int { return (int)($_POST[$k] ?? 0); }

switch ($a) {

/* ===== داشبورد ===== */
case 'dash': {
    $cnt = [
        qval("SELECT COUNT(*) FROM articles"), qval("SELECT COUNT(*) FROM announcements"),
        qval("SELECT COUNT(*) FROM events"), qval("SELECT COUNT(*) FROM media_items"),
    ];
    $views = (int)qval("SELECT SUM(views) FROM articles");
    admin_header('داشبورد', 'dash');
    echo '<div class="kpis">
      <div class="kpi"><b>' . fa_num((int)$cnt[0]) . '</b><span>خبر</span></div>
      <div class="kpi"><b>' . fa_num((int)$cnt[1]) . '</b><span>اطلاعیه</span></div>
      <div class="kpi"><b>' . fa_num((int)$cnt[2]) . '</b><span>رویداد</span></div>
      <div class="kpi"><b>' . fa_num((int)$cnt[3]) . '</b><span>چندرسانه</span></div>
      <div class="kpi"><b>' . fa_num($views) . '</b><span>مجموع بازدید</span></div></div>
      <div class="panel"><h2>آخرین اخبار ثبت‌شده</h2><table><tr><th>عنوان</th><th>وضعیت</th><th>تاریخ</th><th></th></tr>';
    foreach (qall("SELECT a.id, a.is_published, a.published_at, t.title FROM articles a JOIN article_translations t ON t.article_id = a.id AND t.lang = 'fa' ORDER BY a.id DESC LIMIT 8") as $r)
        echo '<tr><td>' . e($r['title']) . (in_array((int)$r['id'], $hero_ids, true) ? ' <span class="bdg b">🏆 هیرو</span>' : '') . '</td><td><span class="bdg ' . ($r['is_published'] ? 'g' : 'y') . '">' . ($r['is_published'] ? 'منتشرشده' : 'پیش‌نویس') . '</span></td><td>' . e(date_i18n($r['published_at'], false)) . '</td>
            <td><a class="lnk" href="' . e(admin_url('art_edit', ['id' => $r['id']])) . '">ویرایش</a></td></tr>';
    echo '</table></div>';
    admin_footer();
} break;

/* ===== اخبار ===== */
case 'arts': {
    $sq = getp('q'); $w = ''; $p = [];
    if ($sq !== '') { $w = "t.title LIKE ?"; $p = ['%' . str_replace(['%', '_'], ['\\%', '\\_'], $sq) . '%']; }
    $page = max(1, ini('page', 1)); $per = 15; $off = ($page - 1) * $per;
    $total = (int)qval("SELECT COUNT(*) FROM articles a JOIN article_translations t ON t.article_id = a.id AND t.lang='fa'" . ($w ? " WHERE $w" : ""), $p);
    admin_header('مدیریت اخبار', 'arts');
    echo '<div class="tools"><form method="get"><input type="hidden" name="a" value="arts"><input name="q" placeholder="جستجوی عنوان…" value="' . e($sq) . '"><button class="btn ghost">جستجو</button></form>
    <a class="btn" href="' . e(admin_url('art_edit')) . '">+ خبر جدید</a></div>
    <table class="lt"><tr><th>#</th><th>عنوان</th><th>دسته</th><th>انتشار</th><th>سنجاق</th><th>بازدید</th><th>تاریخ</th><th>عملیات</th></tr>';
    foreach (qall("SELECT a.*, t.title, c.id AS cid, c.name AS cname FROM articles a
                   JOIN article_translations t ON t.article_id = a.id AND t.lang='fa'
                   LEFT JOIN category_translations c ON c.category_id = a.category_id AND c.lang='fa'
                   " . ($w ? "WHERE $w " : "") . "ORDER BY a.id DESC LIMIT $per OFFSET $off", $p) as $r) {
        echo '<tr><td>' . fa_num((int)$r['id']) . '</td><td class="tt">' . e($r['title']) . (in_array((int)$r['id'], $hero_ids, true) ? ' <span class="bdg b">🏆 هیرو</span>' : '') . '</td><td class="dim">' . e($r['cname'] ?: '—') . '</td>
          <td><span class="bdg ' . ($r['is_published'] ? 'g' : 'y') . '">' . ($r['is_published'] ? '✅' : 'پیش‌نویس') . '</span></td>
          <td>' . ($r['is_pinned'] ? '📌' : '') . '</td><td>' . fa_num((int)$r['views']) . '</td><td>' . e(date_i18n($r['published_at'], false)) . '</td>
          <td class="ops"><a href="' . e(admin_url('art_edit', ['id' => $r['id']])) . '">✏️</a>
          <form method="post" onsubmit="return confirm(&quot;حذف شود؟&quot;)"><input type="hidden" name="a" value="arts"><input type="hidden" name="op" value="del"><input type="hidden" name="id" value="' . (int)$r['id'] . '">' . csrf_field() . '<button class="lblk">🗑</button></form></td></tr>';
    }
    echo '</table>';
    if ($total > $per) {
        echo '<div class="pager">';
        for ($i = 1; $i <= (int)ceil($total / $per); $i++)
            echo '<a class="pg' . ($i === $page ? ' pg-cur' : '') . '" href="' . e(admin_url('arts', ['q' => $sq, 'page' => $i])) . '">' . fa_num($i) . '</a>';
        echo '</div>';
    }
    admin_footer();
} break;

/* ===== ثبت/ویرایش خبر ===== */
case 'art_edit': {
    $id = ini('id', 0);
    $row = $id ? article_admin($id) : null;
    if ($id && !$row) { $_SESSION['flash_err'] = 'خبر یافت نشد.'; redirect(admin_url('arts')); }
    if (is_post() && post('op') === 'save') {
        csrf_check();
        $img = handle_upload('image', 'news') ?: post('image_keep');
        $keep = $img ?: ($id ? ($row['image'] ?? null) : null);
        $dts = str_replace('T', ' ', post('published_at'));
        if ($dts !== '' && strlen($dts) === 16) $dts .= ':00';
        $vals = [
            'category_id'  => post('category_id') !== '' ? (int)post('category_id') : null,
            'image'        => $keep,
            'source_name'  => post('source_name'),
            'source_url'   => post('source_url'),
            'is_pinned'    => post('is_pinned') ? 1 : 0,
            'is_published' => post('is_published') ? 1 : 0,
            'published_at' => $dts ?: date('Y-m-d H:i:s'),
            'tags'         => post('tags'),
        ];
        $dxf = [];
        foreach (all_langs() as $l) $dxf[$l] = ['title' => post("t_$l"), 'excerpt' => post("x_$l"), 'body' => post("b_$l")];
        if (trim((string)$dxf[all_langs()[0]]['title']) === '') { $_SESSION['flash_err'] = 'عنوان فارسی الزامی است.'; redirect(admin_url('art_edit', ['id' => $id ?: 0])); }
        $nid = article_save($id, $vals, $dxf);
        flash('خبر ذخیره شد.');
        redirect(admin_url('art_edit', ['id' => $nid]));
    }
    admin_header($id ? 'ویرایش خبر' : 'خبر جدید', 'arts');
    $cats = categories(false);
    echo '<form method="post" enctype="multipart/form-data" class="frm">' . csrf_field() . '<input type="hidden" name="op" value="save">
    <div class="frm-grid">
      <div class="colA">';
    foreach (all_langs() as $l) {
        $tr = $row['tr'][$l] ?? [];
        echo '<div class="ltabs" data-lang="' . $l . '">
        <div class="tabh"><span class="lg">' . strtoupper($l) . '</span></div>
        <label>عنوان (' . strtoupper($l) . ') ' . ($l === 'fa' ? '<i class="req">*</i>' : '') . '</label>
        <input name="t_' . $l . '" value="' . e($tr['title'] ?? '') . '" ' . ($l === 'fa' ? 'required' : '') . '>
        <label>لطما/چکیده</label><textarea name="x_' . $l . '" rows="2">' . e($tr['excerpt'] ?? '') . '</textarea>
        <label>متن کامل</label><textarea name="b_' . $l . '" rows="10" class="mono">' . e($tr['body'] ?? '') . '</textarea></div>';
    }
    echo '</div><div class="colB">
      <div class="panel"><h2>انتشار</h2>
        <label>وضعیت</label>
        <label class="chk"><input type="checkbox" name="is_published"' . (($row['is_published'] ?? 1) ? ' checked' : '') . '> منتشر شود</label>
        <label class="chk"><input type="checkbox" name="is_pinned"' . (($row['is_pinned'] ?? 0) ? ' checked' : '') . '> 📌 سنجاق در صفحه اصلی</label>
        <label>تاریخ و ساعت انتشار</label>
        <input type="datetime-local" name="published_at" value="' . e(str_replace(' ', 'T', $row['published_at'] ?? date('Y-m-d H:i'))) . '">
      </div>
      <div class="panel"><h2>تصویر</h2>' . (($im = img($row['image'] ?? null)) ? '<img class="prev" src="' . e($im) . '">' : '') . '
        <label>آپلود تصویر (jpg/png/webp حداکثر 4MB)</label><input type="file" name="image" accept="image/*">
      </div>
      <div class="panel"><h2>دسته و منبع</h2>
        <label>دسته</label><select name="category_id"><option value="">— بدون دسته —</option>';
    foreach ($cats as $c) echo '<option value="' . (int)$c['id'] . '"' . (((int)($row['category_id'] ?? 0) === (int)$c['id']) ? ' selected' : '') . '>' . e($c['name'] ?: '—') . '</option>';
    echo '</select>
        <label>نام منبع</label><input name="source_name" value="' . e($row['source_name'] ?? '') . '">
        <label>لینک منبع</label><input name="source_url" dir="ltr" value="' . e($row['source_url'] ?? '') . '">
        <label>برچسب‌ها (با , جدا کنید)</label><input name="tags" value="' . e(implode(', ', $row['tags'] ?? [])) . '">
      </div>
      <button class="btn wide">💾 ذخیره</button>
      <a class="btn ghost wide" href="' . e(admin_url('arts')) . '">بازگشت</a>
    </div></form></div>';
    admin_footer();
} break;

/* ===== دسته‌ها ===== */
case 'cats': {
    if (is_post() && post('op') === 'save') {
        csrf_check();
        $dxf = []; foreach (all_langs() as $l) $dxf[$l] = ['name' => post('name_' . $l)];
        category_save(ini_post_local('id'), $dxf);
        flash('دسته ذخیره شد.'); redirect(admin_url('cats'));
    }
    admin_header('دسته‌بندی اخبار', 'cats');
    echo '<div class="tools"><a class="btn" href="' . e(admin_url('cats', ['new' => 1])) . '">+ دسته جدید</a></div>';
    if (getp('new') !== '') {
        echo '<div class="panel"><h2>دسته جدید</h2>
        <form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="op" value="save"><input type="hidden" name="id" value="0">
        <input name="name_fa" placeholder="نام فارسی" required> <input name="name_en" dir="ltr" placeholder="English"> <input name="name_ar" dir="ltr" placeholder="العربية">
        <input name="sort_order" placeholder="ترتیب" style="width:56px"><button class="btn">افزودن</button></form></div>';
    }
    echo '<div class="rowlist">';
    foreach (qall("SELECT c.* FROM categories c ORDER BY c.sort_order, c.id") as $c) {
        $ns = [];
        foreach (qall("SELECT lang, name FROM category_translations WHERE category_id = ?", [$c['id']]) as $t) $ns[$t['lang']] = $t['name'];
        echo '<div class="rowbox"><form method="post" class="inline">' . csrf_field() .
             '<input type="hidden" name="op" value="save"><input type="hidden" name="id" value="' . (int)$c['id'] . '">
             <input name="name_fa" value="' . e($ns['fa'] ?? '') . '" placeholder="فارسی">
             <input name="name_en" dir="ltr" value="' . e($ns['en'] ?? '') . '" placeholder="English">
             <input name="name_ar" dir="ltr" value="' . e($ns['ar'] ?? '') . '" placeholder="العربية">
             <input name="sort_order" style="width:56px" value="' . (int)$c['sort_order'] . '" placeholder="ترتیب">
             <button class="btn ghost sm">ذخیره</button></form>
             <form method="post" onsubmit="return confirm(&quot;حذف شود؟&quot;)">' . csrf_field() . '<input type="hidden" name="a" value="cats"><input type="hidden" name="op" value="del"><input type="hidden" name="id" value="' . (int)$c['id'] . '"><button class="lblk">🗑</button></form></div>';
    }
    echo '</div>';
    admin_footer();
} break;

/* ===== انواع عمومی (اطلاعیه/رویداد/رسانه/سرمایه‌گذاری) ===== */
case in_array($a, array_keys($TYPES), true): {
    $T = $TYPES[$a];
    $id = array_key_exists('id', $_GET) ? (int)getp('id', '0') : null;   // null = لیست، 0 = ایجاد
    if (is_post() && post('op') === 'save') {
        csrf_check();
        $idPt = (int)post('id');
        $vals = [];
        foreach ($T['cols'] as $c) {
            $v = post($c);
            $vals[$c] = match ($c) {
                'published_at' => (($v !== '' ? str_replace('T', ' ', $v) : date('Y-m-d H:i:s')))
                                  . (strlen((string)$v) === 16 ? ':00' : ''),
                'event_time'   => $v !== '' ? $v . ':00' : null,
                'type'         => $a === 'mds' ? ($v === 'video' ? 'video' : 'photo') : ($v !== '' ? $v : 'notice'),
                'photo_count'  => $v !== '' ? (int)$v : null,
                default        => $v !== '' ? $v : null,
            };
        }
        $updir = match ($a) { 'invs' => 'invest', 'anns' => 'anns', 'evs' => 'events', default => 'media' };
        if (($T['imgField'] ?? false) && ($up = handle_upload('image', $updir)))
            $vals[$T['imgField']] = $up;
        $dxf = [];
        foreach (all_langs() as $l) { $dxf[$l] = []; foreach ($T['tcols'] as $tc) $dxf[$l][$tc] = post("f_{$tc}_$l"); }
        if (trim((string)($dxf[all_langs()[0]]['title'] ?? '')) === '') {
            $_SESSION['flash_err'] = 'عنوان (فارسی) الزامی است.';
            redirect(admin_url($a, $idPt ? ['id' => $idPt] : []));
        }
        x_save($T['table'], $T['tr'], $T['fk'], $idPt, array_keys($vals), $vals, $dxf, $T['tcols']);
        flash($T['t'] . ' ذخیره شد.');
        redirect(admin_url($a, $idPt ? ['id' => $idPt] : []));
    }
    if ($id !== null) {
        $row = $id ? x_get_admin($T['table'], $T['tr'], $T['fk'], $id) : null;
        admin_header(($id ? 'ویرایش ' : 'جدید — ') . $T['t'], $a);
        echo '<p class="sub">' . e($T['desc']) . '</p>
        <form method="post" enctype="multipart/form-data" class="frm xfrm">' . csrf_field()
           . '<input type="hidden" name="op" value="save"><input type="hidden" name="id" value="' . (int)$id . '">
        <div class="frm-grid"><div class="colA">';
        foreach (all_langs() as $l) {
            $tr = $row['tr'][$l] ?? [];
            echo '<div class="ltabs"><div class="tabh"><span class="lg">' . strtoupper($l) . '</span></div>';
            foreach ($T['tcols'] as $tc) {
                $lbl = ($T['lbl'][$tc] ?? ['title' => 'عنوان', 'body' => 'متن', 'description' => 'توضیحات', 'summary' => 'خلاصه', 'caption' => 'شرح تصویر'][$tc]) ?? $tc;
                $big = in_array($tc, ['body', 'description', 'incentives'], true);
                if ($big)
                    echo '<label>' . e($lbl) . ' (' . strtoupper($l) . ')</label><textarea name="f_' . $tc . '_' . $l . '" rows="6">' . e($tr[$tc] ?? '') . '</textarea>';
                else
                    echo '<label>' . e($lbl) . ' (' . strtoupper($l) . ') ' . ($tc === 'title' && $l === 'fa' ? '<i class="req">*</i>' : '')
                       . '</label><input name="f_' . $tc . '_' . $l . '"' . ($tc === 'title' && $l === 'fa' ? ' required' : '') . ' value="' . e($tr[$tc] ?? '') . '">';
            }
            echo '</div>';
        }
        echo '</div><div class="colB"><div class="panel"><h2>جزئیات</h2>';
        if ($a === 'anns') {
            echo '<label>تاریخ انتشار</label><input type="datetime-local" name="published_at" value="' . e(str_replace(' ', 'T', $row['published_at'] ?? date('Y-m-d H:i'))) . '">
            <label>نوع اطلاعیه</label><select name="type">';
            foreach (['notice' => 'اطلاعیه', 'tender' => 'مناقصه', 'auction' => 'مزایده', 'call' => 'فراخوان'] as $k => $v)
                echo '<option value="' . $k . '"' . (($row['type'] ?? 'notice') === $k ? ' selected' : '') . '>' . $v . '</option>';
            echo '</select><label>وضعیت</label><select name="status">';
            foreach (['active' => 'فعال', 'new' => 'جدید', 'deadline' => 'مهلت محدود', 'expired' => 'پایان‌یافته'] as $k => $v)
                echo '<option value="' . $k . '"' . (($row['status'] ?? 'active') === $k ? ' selected' : '') . '>' . $v . '</option>';
            echo '</select><label>کد رهگیری</label><input dir="ltr" name="reference_code" value="' . e($row['reference_code'] ?? '') . '">'
               . (($im = img($row['image'] ?? null)) ? '<label>تصویر فعلی</label><img class="prev" src="' . e($im) . '">' : '')
               . '<label>تصویر اطلاعیه (jpg/png/webp — حداکثر 4MB)</label><input type="file" name="image" accept="image/*">';
        }
        if ($a === 'evs')  echo '<label>تاریخ رویداد</label><input type="date" name="event_date" value="' . e($row['event_date'] ?? '') . '"><label>ساعت</label><input type="time" name="event_time" value="' . e(substr((string)($row['event_time'] ?? ''), 0, 5)) . '">'
            . (($im = img($row['image'] ?? null)) ? '<label>تصویر فعلی</label><img class="prev" src="' . e($im) . '">' : '')
            . '<label>تصویر رویداد (jpg/png/webp — حداکثر 4MB)</label><input type="file" name="image" accept="image/*">';
        if ($a === 'mds') {
            echo '<label>نوع</label><select name="type"><option value="photo"' . (($row['type'] ?? 'photo') === 'photo' ? ' selected' : '') . '>عکس</option><option value="video"' . (($row['type'] ?? '') === 'video' ? ' selected' : '') . '>ویدیو</option></select>
            <label>آپلود تصویر/پوستر</label><input type="file" name="image" accept="image/*">
            <label>یا لینک (تصویر/ویدئو)</label><input dir="ltr" name="source_url" value="' . e($row['source_url'] ?? '') . '">
            <label>مدت ویدیو (مثل ۰۴:۲۵)</label><input dir="ltr" name="duration" value="' . e($row['duration'] ?? '') . '">
            <label>تعداد عکس (برای گزارش تصویری)</label><input type="number" name="photo_count" value="' . e((string)($row['photo_count'] ?? '')) . '">';
        }
        if ($a === 'invs') {
            echo '<label>تصویر</label>' . (($im = img($row['image'] ?? null)) ? '<img class="prev" src="' . e($im) . '">' : '') . '<input type="file" name="image" accept="image/*">';
        }
        echo '</div><button class="btn wide">💾 ذخیره</button><a class="btn ghost wide" href="' . e(admin_url($a)) . '">بازگشت</a></div></form></div>';
        admin_footer();
        break;
    }
    admin_header($T['t'], $a);
    echo '<p class="sub">' . e($T['desc']) . '</p>
    <div class="tools"><a class="btn" href="' . e(admin_url($a, ['id' => 0])) . '">+ جدید</a></div>
    <table class="lt"><tr><th>#</th><th>عنوان</th><th>جزئیات</th><th>وضعیت</th><th>عملیات</th></tr>';
    foreach (x_list($T['table'], $T['tr'], $T['fk'], $T['order'], false, $T['tcols']) as $r) {
        echo '<tr><td>' . fa_num((int)$r['id']) . '</td><td class="tt">' . e($r['title'] ?? '') . '</td><td class="dim">';
        foreach ($T['cols'] as $c) if (!in_array($c, ['image', 'source_url'], true) && !empty($r[$c])) echo '<span>' . e((string)$r[$c]) . '</span> ';
        echo '</td><td>' . ($r['is_published'] ? '<span class="bdg g">فعال</span>' : '<span class="bdg y">مخفی</span>') . '</td>
        <td class="ops"><a href="' . e(admin_url($a, ['id' => $r['id']])) . '">✏️</a>
        <form method="post" onsubmit="return confirm(&quot;حذف شود؟&quot;)"><input type="hidden" name="a" value="' . e($a) . '"><input type="hidden" name="op" value="del"><input type="hidden" name="id" value="' . (int)$r['id'] . '">' . csrf_field() . '<button class="lblk">🗑</button></form></td></tr>';
    }
    echo '</table>';
    admin_footer();
} break;

/* ===== شاخص‌های پالس ===== */
case 'pulse': {
    $id = array_key_exists('id', $_GET) ? (int)getp('id', '0') : null;
    if (is_post() && post('op') === 'save') {
        csrf_check();
        $idPt = (int)post('id');
        $vals = ['sort_order' => (int)(post('sort_order') ?: 0), 'is_active' => post('is_active') ? 1 : 0,
                 'trend' => post('trend') ?: 'neutral'];
        $dxf = [];
        foreach (all_langs() as $l)
            $dxf[$l] = ['label' => post("f_label_$l"), 'value' => post("f_value_$l"), 'note' => post("f_note_$l"),
                        'change_text' => post("f_change_$l"), 'submetrics' => post("f_subm_$l")];
        if (trim((string)($dxf[all_langs()[0]]['label'] ?? '')) === '') {
            $_SESSION['flash_err'] = 'برچسب (فارسی) الزامی است.';
            redirect(admin_url('pulse', $idPt ? ['id' => $idPt] : []));
        }
        $idPt = x_save('pulse_metrics', 'pulse_metric_translations', 'metric_id', $idPt,
                       ['sort_order', 'is_active', 'trend'], $vals, $dxf,
                       ['label', 'value', 'note', 'change_text', 'submetrics']);
        flash('شاخص ذخیره شد.');
        redirect(admin_url('pulse', $idPt ? ['id' => $idPt] : []));
    }
    if ($id !== null) {
        $row = $id ? x_get_admin('pulse_metrics', 'pulse_metric_translations', 'metric_id', $id) : null;
        admin_header($id ? 'ویرایش شاخص' : 'شاخص جدید', 'pulse');
        echo '<form method="post" class="frm xfrm">' . csrf_field() . '<input type="hidden" name="op" value="save"><input type="hidden" name="id" value="' . (int)$id . '">
        <div class="frm-grid"><div class="colA">';
        foreach (all_langs() as $l) {
            $tr = $row['tr'][$l] ?? [];
            echo '<div class="ltabs"><div class="tabh"><span class="lg">' . strtoupper($l) . '</span></div>
            <label>برچسب (' . strtoupper($l) . ') ' . ($l === 'fa' ? '<i class="req">*</i>' : '') . '</label>
            <input name="f_label_' . $l . '" value="' . e($tr['label'] ?? '') . '" ' . ($l === 'fa' ? 'required' : '') . '>
            <label>مقدار</label><input name="f_value_' . $l . '" value="' . e($tr['value'] ?? '') . '">
            <label>توضیح کوتاه</label><input name="f_note_' . $l . '" value="' . e($tr['note'] ?? '') . '">
            <label>متن تغییر (' . strtoupper($l) . ')</label><input name="f_change_' . $l . '" value="' . e($tr['change_text'] ?? '') . '">
            <label>زیرشاخص‌ها JSON (' . strtoupper($l) . ')</label><textarea name="f_subm_' . $l . '" rows="2" dir="ltr" class="mono">' . e($tr['submetrics'] ?? '') . '</textarea></div>';
        }
        echo '</div><div class="colB"><div class="panel"><h2>نمایش</h2>
        <label>ترتیب</label><input name="sort_order" style="width:56px" value="' . (int)($row['sort_order'] ?? 0) . '">
        <label class="chk"><input type="checkbox" name="is_active"' . (($row['is_active'] ?? 1) ? ' checked' : '') . '> فعال باشد</label>
        <label>روند</label><select name="trend">';
        foreach (['up' => '▲ صعودی', 'neutral' => '◆ خنثی', 'stable' => '◇ باثبات', 'down' => '▼ نزولی'] as $kk => $vv)
            echo '<option value="' . $kk . '"' . (($row['trend'] ?? 'neutral') === $kk ? ' selected' : '') . '>' . $vv . '</option>';
        echo '</select></div>
        <button class="btn wide">💾 ذخیره</button><a class="btn ghost wide" href="' . e(admin_url('pulse')) . '">بازگشت</a></div></form></div>';
        admin_footer();
        break;
    }
    admin_header('شاخص‌های پالس', 'pulse');
    echo '<div class="tools"><a class="btn" href="' . e(admin_url('pulse', ['id' => 0])) . '">+ شاخص جدید</a></div>
    <table class="lt"><tr><th>#</th><th>برچسب</th><th>مقدار</th><th>فعال</th><th>عملیات</th></tr>';
    foreach (qall("SELECT m.id, m.sort_order, m.is_active, COALESCE(t1.label, tf.label) AS label, COALESCE(t1.value, tf.value) AS value
                   FROM pulse_metrics m
                   LEFT JOIN pulse_metric_translations t1 ON t1.metric_id = m.id AND t1.lang = 'fa'
                   LEFT JOIN pulse_metric_translations tf ON tf.metric_id = m.id AND tf.lang = 'fa'
                   ORDER BY m.sort_order, m.id") as $r) {
        echo '<tr><td>' . fa_num((int)$r['id']) . '</td><td class="tt">' . e($r['label']) . '</td><td>' . e($r['value']) . '</td>
        <td>' . ($r['is_active'] ? '<span class="bdg g">فعال</span>' : '<span class="bdg y">خاموش</span>') . '</td>
        <td class="ops"><a href="' . e(admin_url('pulse', ['id' => $r['id']])) . '">✏️</a>
        <form method="post" onsubmit="return confirm(&quot;حذف شود؟&quot;)"><input type="hidden" name="a" value="pulse"><input type="hidden" name="op" value="del"><input type="hidden" name="id" value="' . (int)$r['id'] . '">' . csrf_field() . '<button class="lblk">🗑</button></form></td></tr>';
    }
    echo '</table>';
    admin_footer();
} break;

/* ===== تنظیمات ===== */
case 'set': {
    if (is_post() && post('op') === 'set') {
        csrf_check();
        foreach (['site_name_fa', 'site_name_en', 'site_name_ar', 'about', 'email', 'phone', 'address', 'telegram', 'instagram', 'bale', 'eitaa'] as $k) {
            q("INSERT INTO settings (k, v) VALUES (?,?) ON DUPLICATE KEY UPDATE v = VALUES(v)", [$k, post($k)]);
        }
        flash('تنظیمات ذخیره شد.'); redirect(admin_url('set'));
    }
    if (is_post() && post('op') === 'pw') {
        csrf_check();
        if (post('pw1') !== '' && post('pw1') === post('pw2') && strlen(post('pw1')) >= 8)
            q("UPDATE admins SET password_hash = ? WHERE id = ?", [password_hash(post('pw1'), PASSWORD_DEFAULT), (int)$_SESSION['admin']['id']]);
        flash('رمز به‌روزرسانی شد.'); redirect(admin_url('set'));
    }
    $s = settings_all();
    admin_header('تنظیمات سایت', 'set');
    echo '<form method="post" class="frm wide">' . csrf_field() . '<input type="hidden" name="op" value="set"><div class="panel">
    <h2>نام سایت در هر زبان</h2>
    <label>فارسی</label><input name="site_name_fa" value="' . e($s['site_name'] ?? '') . '">
    <label>انگلیسی</label><input name="site_name_en" dir="ltr" value="' . e($s['site_name_en'] ?? '') . '">
    <label>عربی</label><input name="site_name_ar" dir="ltr" value="' . e($s['site_name_ar'] ?? '') . '"></div>
    <div class="panel"><h2>درباره و تماس</h2>
    <label>معرفی کوتاه (فوتر)</label><textarea name="about" rows="3">' . e($s['about'] ?? '') . '</textarea>
    <label>ایمیل</label><input name="email" dir="ltr" value="' . e($s['email'] ?? '') . '">
    <label>تلفن</label><input name="phone" dir="ltr" value="' . e($s['phone'] ?? '') . '">
    <label>آدرس</label><input name="address" value="' . e($s['address'] ?? '') . '"></div>
    <div class="panel"><h2>شبکه‌های اجتماعی — آیکون‌های فوتر سایت از همین‌جا خوانده می‌شود</h2>
    <label>اینستاگرام (لینک کامل یا فقط آیدی)</label><input name="instagram" dir="ltr" value="' . e($s['instagram'] ?? '') . '" placeholder="https://instagram.com/… یا mypage">
    <label>تلگرام (لینک کامل یا فقط آیدی)</label><input name="telegram" dir="ltr" value="' . e($s['telegram'] ?? '') . '" placeholder="https://t.me/… یا mypage">
    <label>بله (لینک کامل یا فقط آیدی)</label><input name="bale" dir="ltr" value="' . e($s['bale'] ?? '') . '" placeholder="https://ble.ir/… یا mypage">
    <label>ایتا (لینک کامل یا فقط آیدی)</label><input name="eitaa" dir="ltr" value="' . e($s['eitaa'] ?? '') . '" placeholder="https://eitaa.com/… یا mypage">
    <p class="sub">بدون مقدار، همان لینک‌های فعلی (پیش‌فرض) در فوتر دیده می‌شود.</p></div>
    <button class="btn">💾 ذخیره تنظیمات</button></form>
    <div class="panel"><h2>تغییر رمز مدیر</h2><form method="post">' . csrf_field() . '<input type="hidden" name="op" value="pw">
    <label>رمز جدید (حداقل ۸)</label><input name="pw1" type="password" minlength="8" required>
    <label>تکرار</label><input name="pw2" type="password" required><button class="btn">🔒 تغییر رمز</button></form></div>';
    admin_footer();
} break;

case 'logout': admin_logout(); redirect(admin_url('login')); break;
default: redirect(admin_url('dash'));
}