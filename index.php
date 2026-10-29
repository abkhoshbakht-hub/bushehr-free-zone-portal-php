<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
boot_session();
mb_internal_encoding('UTF-8');
date_default_timezone_set(app()['timezone']);
$lang = lang();

$p   = getp('p', 'home');
$cid = ini('cat', 0);
$q   = getp('q');

/* ---------- صفحه‌بندی ---------- */
function paginate(int $total, int $per, int $page, string $base): ?string {
    if ($total <= $per) return null;
    $pages = (int)ceil($total / $per);
    $out = '<nav class="pager">';
    $out .= $page > 1 ? '<a class="pg" href="' . e($base . '&page=' . ($page - 1)) . '">‹</a>' : '';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === $page) $out .= '<span class="pg pg-cur">' . e(num_i18n($i)) . '</span>';
        elseif ($pages > 9 && abs($i - $page) > 3 && $i !== 1 && $i !== $pages) {
            if (abs($i - $page) === 4) $out .= '<span class="pg-dots">…</span>';
            continue;
        } else $out .= '<a class="pg" href="' . e($base . '&page=' . $i) . '">' . e(num_i18n($i)) . '</a>';
    }
    $out .= $page < $pages ? '<a class="pg" href="' . e($base . '&page=' . ($page + 1)) . '">›</a>' : '';
    return $out . '</nav>';
}

/* ---------- برند/دسته ---------- */
function site_name(): string {
    $l = lang();
    return setting('site_name_' . $l, setting('site_name', app()['site']['name']));
}
function cat_names(): array {
    static $m = null; if ($m !== null) return $m;
    $m = []; foreach (categories(false) as $c) $m[(int)$c['id']] = (string)$c['name'];
    return $m;
}

/* ---------- اجزای تکراری ---------- */
function news_card(array $a, bool $big = false): string {
    $im = img($a['image']);
    $per = url('article', ['id' => $a['id']]);
    $cat = cat_names()[(int)($a['category_id'] ?? 0)] ?? '';
    return '<article class="ncard' . ($big ? ' ncard-big' : '') . '">
        <a class="ncard-im" href="' . e($per) . '">' . ($im ? '<img src="' . e($im) . '" alt="' . e($a['title']) . '" loading="lazy">' : '<span class="noimg">📰</span>') . '</a>
        <div class="ncard-bd">' . ($cat ? '<span class="ncard-cat">' . e($cat) . '</span>' : '') . '
            <div class="ncard-meta"><time>' . e(date_i18n($a['published_at'], false)) . '</time></div>
            <h3 class="ncard-t"><a href="' . e($per) . '">' . e($a['title']) . '</a></h3>
            <p class="ncard-x">' . e(excerpt($a['excerpt'] ?? '', $big ? 220 : 110)) . '</p>
        </div></article>';
}
function section_head(string $title, ?string $link = null): string {
    return '<div class="shead"><h2 class="stitle"><span></span>' . e($title) . '</h2>' .
        ($link ? '<a class="slink" href="' . e($link) . '">' . e(t('more')) . ' ›</a>' : '') . '</div>';
}
function edate_box(string $ymd): string {
    $ts = strtotime($ymd); if (!$ts) return '';
    if (lang() === 'fa') {
        [, $jm, $jd] = (array)g2j((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
        $mN = fa_months()[(int)$jm] ?? '';
        $d = (string)$jd;
    } else {
        $mn = lang() === 'ar' ? [1=>'يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر']
                              : [1=>'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $d = num_i18n(date('j', $ts)); $mN = $mn[(int)date('n', $ts)];
    }
    return '<div class="edate"><b>' . e($d) . '</b><span>' . e($mN) . '</span></div>';
}
function event_card(array $ev): string {
    $dm = $ev['event_date'] ? date_i18n($ev['event_date'], false) : '';
    return '<div class="ecard">' . edate_box((string)($ev['event_date'] ?? ''))
        . '<div class="ebd"><h3>' . e($ev['title']) . '</h3><p>' . e(excerpt($ev['description'] ?? '', 130)) . '</p>'
        . '<div class="emeta">'
        . ($dm ? '<span class="echip">🗓 ' . e($dm) . '</span>' : '')
        . (!empty($ev['event_time']) ? '<span class="echip">🕐 ' . e(num_i18n(substr((string)$ev['event_time'], 0, 5))) . '</span>' : '')
        . (!empty($ev['location']) ? '<span class="echip">📍 ' . e($ev['location']) . '</span>' : '')
        . (!empty($ev['category']) ? '<span class="echip">🏷 ' . e($ev['category']) . '</span>' : '')
        . '</div></div></div>';
}
function ann_card(array $a, bool $full = false): string {
    $types  = ['tender' => 'مناقصه|Tender|مناقصة', 'auction' => 'مزایده|Auction|مزاد',
               'notice' => 'اطلاعیه|Notice|إعلان', 'call' => 'فراخوان|Call|نشر'];
    $stat   = ['deadline' => 'مهلت محدود|Deadline|موعد محدد', 'new' => 'جدید|New|جديد',
               'active' => 'فعال|Active|نشط', 'expired' => 'پایان‌یافته|Expired|منتهي'];
    $tIdx = lang() === 'fa' ? 0 : (lang() === 'ar' ? 2 : 1);
    $tp = explode('|', $types[$a['type']] ?? '||');
    $ts = explode('|', $stat[$a['status']] ?? '||');
    $iconMap = ['tender' => '📊', 'auction' => '🔨', 'notice' => '📢', 'call' => '📣'];
    return '<div class="acard"><div class="acard-ic">' . ($iconMap[$a['type']] ?? '📢') . '</div><div class="acard-bd">
        <h3>' . e($a['title']) . '</h3>' . ($full ? '<p>' . nl2br(e($a['body'] ?? '')) . '</p>' : '<p>' . e(excerpt($a['body'] ?? '', 150)) . '</p>') . '
        <div class="acard-meta">' . (($tp[$tIdx] ?? '') !== '' ? '<span class="abadge ' . e((string)$a['type']) . '">' . e($tp[$tIdx]) . '</span>' : '')
        . (($ts[$tIdx] ?? '') !== '' ? '<span class="abadge st-' . e((string)$a['status']) . '">' . e($ts[$tIdx]) . '</span>' : '')
        . (!empty($a['reference_code']) ? '<span class="refcode">' . e((string)$a['reference_code']) . '</span>' : '')
        . '<time>' . e(date_i18n((string)($a['published_at'] ?? ''), false)) . '</time></div></div></div>';
}
function media_card(array $mi): string {
    $im = img($mi['source_url'] ?? '');
    return '<div class="mcard' . (($mi['type'] ?? '') === 'video' ? ' is-video' : '') . '">
        <div class="mc-im">' . ($im ? '<img src="' . e($im) . '" alt="' . e($mi['title']) . '" loading="lazy">' : '')
        . (!empty($mi['mcategory']) ? '<span class="mcat">' . e($mi['mcategory']) . '</span>' : '')
        . (!empty($mi['duration']) ? '<span class="mdur">' . e(num_i18n((string)$mi['duration'])) . '</span>' : '')
        . (!empty($mi['photo_count']) ? '<span class="mphotos">📷 ' . e(num_i18n((int)$mi['photo_count'])) . '</span>' : '') . '</div>
        <div class="mbd"><h3>' . e($mi['title']) . '</h3>' . (!empty($mi['caption']) ? '<p>' . e(excerpt($mi['caption'], 90)) . '</p>' : '') . '</div></div>';
}
function investment_row(array $iv, bool $row = false): string {
    $im = img($iv['image'] ?? '');
    $kpis = [
        ['حجم سرمایه', 'Volume', 'حجم الاستثمار', $iv['volume'] ?? ''],
        ['نرخ بازگشت', 'IRR', 'العائد الداخلي', $iv['irr'] ?? ''],
        ['دوره بازگشت', 'Payback', 'فترة الاسترداد', $iv['payback'] ?? ''],
    ];
    $k = lang() === 'fa' ? 0 : (lang() === 'ar' ? 2 : 1);
    $inc = array_filter(array_map('trim', explode('|', (string)($iv['incentives'] ?? ''))));
    $incHtml = '';
    foreach ($inc as $one) $incHtml .= '<span>✓ ' . e(num_i18n($one)) . '</span>';
    $kpiH = '<div class="ikpis">';
    foreach ($kpis as [$fa, $en, $ar, $v]) if ($v !== '' && $v !== null)
        $kpiH .= '<div class="ikpi"><b>' . e(num_i18n((string)$v)) . '</b><span>' . e([$fa, $en, $ar][$k]) . '</span></div>';
    $kpiH .= '</div>';
    return '<div class="' . ($row ? 'irow' : 'icard') . '">
        <div class="icard-im">' . ($im ? '<img src="' . e($im) . '" alt="" loading="lazy">' : '<div class="icard-ph noimg">💼</div>') . '</div>
        <div class="irow-bd">
            ' . (!empty($iv['category']) ? '<span class="icard-cat">' . e($iv['category']) . '</span>' : '') . '
            <h3>' . e($iv['title']) . '</h3>
            <p>' . e(excerpt($iv['description'] ?? $iv['summary'] ?? '', 190)) . '</p>
            ' . $kpiH . '
            ' . (!empty($iv['location']) ? '<span class="icard-loc">📍 ' . e($iv['location']) . '</span>' : '') . '
            ' . ($incHtml ? '<div class="incentives">' . $incHtml . '</div>' : '') . '
        </div></div>';
}

/* ---------- هدر ---------- */
function page_header(string $pTitle = ''): void {
    $lang = lang();
    $site = site_name();
    $sub  = tl('منطقه آزاد تجاری–صنعتی بوشهر', 'Bushehr Free Trade Zone', 'المنطقة الحرة بوشهر');
    $today = date('Y-m-d');
    $portalDate = date_i18n($today, false);
    if ($lang === 'fa') $portalDate = weekday_name($today) . '، ' . date_i18n($today, false);
    if ($lang === 'en') $portalDate = date('l, F j, Y');
    if ($lang === 'ar') {
        $am = ar_months();
        $ts = strtotime($today);
        $portalDate = weekday_name($today) . '، ' . num_i18n(date('j', $ts)) . ' ' . $am[(int)date('n', $ts)] . ' ' . num_i18n(date('Y', $ts));
    }
    $hijri = hijri_date($today);
    $greg = '';
    if ($lang === 'fa') {
        $gm = [1=>'ژانویه','فوریه','مارس','آوریل','مه','ژوئن','ژوئیه','اوت','سپتامبر','اکتبر','نوامبر','دسامبر'];
        $ts = strtotime($today);
        $greg = fa_num(date('j', $ts)) . ' ' . $gm[(int)date('n', $ts)] . ' ' . fa_num(date('Y', $ts));
    }
    echo '<!doctype html><html lang="' . e($lang) . '" dir="' . dir_of() . '"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . e(($pTitle ? $pTitle . ' — ' : '') . $site . ' | ' . $sub) . '</title>
<meta name="description" content="' . e(setting('about_' . $lang, t('footer_desc'))) . '">
<link rel="icon" href="assets/img/brand/bushehr-free-zone-logo.jpg">
<link rel="stylesheet" href="assets/css/main.css?v=8">
<script>try{var m=localStorage.getItem("theme")||"auto";if(m==="dark"||(m==="auto"&&matchMedia("(prefers-color-scheme:dark)").matches))document.documentElement.dataset.theme="dark"}catch(e){}</script>
</head><body>
<div class="u-strip"><div class="wrap">
  <div class="u-info"><span class="u-date">📅 ' . e($portalDate) . '</span><span class="sep">&nbsp;|&nbsp;</span><span class="u-hijri">🕌 ' . e($hijri) . '</span>' . ($greg !== '' ? '<span class="sep">&nbsp;|&nbsp;</span><span class="u-greg">🌍 ' . e($greg) . '</span>' : '') . '</div>
  <div class="u-quick">
    <a href="' . e(url('announcements')) . '">' . e(t('merged_ann')) . '</a><span class="sep">|</span>
    <a href="' . e(url('about')) . '">' . e(t('about_portal')) . '</a><span class="sep">|</span>
    <a href="admin.php">' . e(tl('پنل مدیریت', 'Admin', 'الإدارة')) . '</a>
  </div>
</div></div>
<header class="hdr"><div class="wrap hdr-in">
  <a class="hdr-brand" href="' . e(url('home')) . '">
    <span class="hdr-logo"><img src="assets/img/brand/bushehr-free-zone-logo.jpg" alt="' . e($site) . '"></span>
    <span class="hdr-bt"><b>' . e($site) . '</b><small>' . e($sub) . '</small></span>
  </a>
  <nav class="mainnav">';
    $pageNow = getp('p', 'home');
    /* items: leaf routes */
    $leaf = [ 'home' => t('home'), 'news' => t('news'), 'events' => t('events'),
              'announcements' => t('merged_ann'), 'about' => t('about_portal'), 'contact' => t('contact') ];
    foreach ($leaf as $k => $v) {
        if ($k === 'announcements') $v = t('merged_ann');
        echo '<a class="nav-link' . ($pageNow === $k ? ' on' : '') . '" href="' . e(url($k)) . '">' . e($v) . '</a>';
    }
    /* dropdown: چند رسانه‌ای */
    $mcOn = $pageNow === 'media';
    echo '<div class="nav-li has-sub' . ($mcOn ? ' active' : '') . '">
        <button type="button" class="nav-link dd-toggle' . ($mcOn ? ' on' : '') . '">' . e(t('multimedia')) . '<span class="dd-caret">▾</span></button>
        <div class="subnav">
          <a href="' . e(url('media')) . '"><b>' . e(t('media')) . '</b><small>' . e(tl('همه آثار', 'All items', 'كل الأعمال')) . '</small></a>
          <a href="' . e(url('media', ['mc' => 'photo'])) . '">' . e(t('photos')) . '<small>' . e(t('mc_photo')) . '</small></a>
          <a href="' . e(url('media', ['mc' => 'video'])) . '">' . e(t('videos')) . '<small>' . e(t('mc_video')) . '</small></a>
        </div></div>';
    /* dropdown: دسترسی سریع */
    echo '<div class="nav-li has-sub">
        <button type="button" class="nav-link dd-toggle">' . e(t('quick_links')) . '<span class="dd-caret">▾</span></button>
        <div class="subnav">
          <a href="https://my.freezones.ir" target="_blank" rel="noopener">' . e(t('services_portal')) . '</a>
          <a href="https://www.freezones.ir" target="_blank" rel="noopener">' . e(t('sec_portal')) . '</a>
          <a href="mailto:' . e(setting('email', 'info@bushehrfz.ir')) . '">' . e(t('zr_suggest')) . '</a>
        </div></div>';
    echo '</nav>
  <div class="socials" aria-label="social">
    <a href="https://instagram.com/freena.ir" target="_blank" rel="noopener" title="Instagram"><svg viewBox="0 0 19 20" width="17" height="18" fill="currentColor" aria-hidden="true"><path d="M19 5.83V14.17a5.46 5.46 0 0 1-5.46 5.46H5.54a5.46 5.46 0 0 1-5.54-5.46V5.83A5.46 5.46 0 0 1 5.54 .04h8a5.46 5.46 0 0 1 5.46 5.79ZM17.42 5.83a3.51 3.51 0 0 0-3.52-3.5H5.54A3.51 3.51 0 0 0 2 5.83v8.28a3.51 3.51 0 0 0 3.54 3.5h8a3.51 3.51 0 0 0 3.52-3.54V5.83Zm-3.57 4.19a4.5 4.5 0 0 1-4.35 4.58A4.4 4.4 0 0 1 5.15 10a4.4 4.4 0 0 1 4.35-4.42 4.35 4.35 0 0 1 4.35 4.42ZM12.27 10a2.25 2.25 0 0 1-2.27 2.34A2.25 2.25 0 0 1 7.73 10a2.25 2.25 0 0 1 4.54 -.02Zm3.56-5.42a1.25 1.25 0 1 1-1.25-1.24 1.25 1.25 0 0 1 1.25 1.24Z"/></svg></a>
    <a href="https://t.me/iranfreezones" target="_blank" rel="noopener" title="Telegram"><img src="assets/img/social/telegram-plane.svg" width="17" height="17" alt=""></a>
    <a href="https://ble.ir/iranfreezones_ir" target="_blank" rel="noopener" title="Bale"><img src="assets/img/social/baleh.svg" width="17" height="17" alt=""></a>
    <a href="http://eitaa.com/iranfreezones_ir" target="_blank" rel="noopener" title="Eitaa"><svg viewBox="0 0 20 21" width="16" height="17" fill="currentColor" aria-hidden="true"><path d="M10.49 20.54a5.68 5.68 0 0 1-4.76-4.51c-.13-.62-.146-1.39-.13-6.18.016-5.16 -.002-4.89.25-5.74a6.89 6.89 0 0 1 3.51-4.04A5.87 5.87 0 0 1 15.64 0c5.36 .03 5.19 -.01 6.06 .27a5.84 5.84 0 0 1 3.7 3.59c.28 .8 .3 .93 .317 3.05l.017 1.91-.335 .23a13.69 13.69 0 0 0-2.3 2.45 32.4 32.4 0 0 1-2.56 2.49 7.07 7.07 0 0 1-3.2 1.6 5.62 5.62 0 0 1-1.98-.08c-.47-.12-.44-.14-.62.4a5.45 5.45 0 0 0-.27 1.33l-.028.35-.125-.026a3.59 3.59 0 0 1-2.48-2.15 3.07 3.07 0 0 1-.22-1.04l-.014-.3-.265-.24Z" transform="translate(.001 -.2)"/></svg></a>
  </div>
  <div class="top-actions">
    <form class="qsearch" action="index.php" method="get" role="search">
      <input type="hidden" name="p" value="search"><input type="hidden" name="lang" value="' . e($lang) . '">
      <input class="qin" type="search" name="q" placeholder="' . e(t('search_ph')) . '" required>
      <button aria-label="' . e(t('search')) . '">🔍</button>
    </form>
    <div class="langsw">';
    $g2 = $_GET;
    foreach (all_langs() as $lc) {
        $g2['p'] = getp('p', 'home'); $g2['lang'] = $lc;
        $ln = ['fa' => 'فارسی', 'en' => 'EN', 'ar' => 'عربی'][$lc];
        if ($lc !== $lang) echo '<a class="lang-b" href="index.php?' . e(http_build_query($g2)) . '">' . e($ln) . '</a>';
        else echo '<button class="lang-b cur" disabled>' . e($ln) . '</button>';
    }
    echo '</div>
    <button id="themeBtn" class="icon-btn" aria-label="theme">🌒</button>
    <button id="menuBtn" class="icon-btn menu-btn">☰</button>
  </div>
</div></header>
<main class="wrap main">';
}

/* ---------- فوتر ---------- */
function page_footer(): void {
    $site = site_name();
    echo '</main>
<footer class="foot"><div class="wrap"><div class="fgrid">
  <div><div class="fbrand"><span class="flogo"><img src="assets/img/brand/bushehr-free-zone-logo.jpg" alt=""></span>
     <span><b>' . e($site) . '</b><small>' . e(tl('منطقه آزاد تجاری–صنعتی بوشهر', 'Bushehr Free Trade Zone', 'المنطقة الحرة بوشهر')) . '</small></span></div>
    <p>' . e(t('footer_desc')) . '</p></div>
  <div><h4>' . e(t('quick_links')) . '</h4>
    <a class="flnk" href="' . e(url('news')) . '">' . e(t('news')) . '</a>
    <a class="flnk" href="' . e(url('announcements')) . '">' . e(t('merged_ann')) . '</a>
    <a class="flnk" href="' . e(url('events')) . '">' . e(t('events')) . '</a></div>
  <div><h4>' . e(t('links_col')) . '</h4>
    <a class="flnk" href="https://my.freezones.ir" target="_blank" rel="noopener">' . e(t('services_portal')) . '</a>
    <a class="flnk" href="https://www.freezones.ir" target="_blank" rel="noopener">' . e(t('sec_portal')) . '</a>
    <a class="flnk" href="https://freena.ir" target="_blank" rel="noopener">' . e(t('fz_mag')) . '</a>
    <a class="flnk" href="https://www.pmo.ir" target="_blank" rel="noopener">' . e(t('pmo')) . '</a>
    <a class="flnk" href="https://irica.ir" target="_blank" rel="noopener">' . e(t('irica')) . '</a></div>
  <div><h4>' . e(t('contact')) . '</h4>
    <span>' . e(setting('address', '')) . '</span>
    <span dir="ltr">' . e(setting('phone', '')) . '</span>
    <span dir="ltr">' . e(setting('email', '')) . '</span></div>
</div>
<div class="foot-b"><span>© ' . e(num_i18n((int)date('Y'))) . ' ' . e($site) . '</span>
  <span class="credit"><img src="assets/img/brand/liandesign-logo.png" alt="Lian Design" width="28" height="26" loading="lazy">' . e(tl('طراحی و توسعه: لیان دیزاین', 'Design & Development: Lian Design', 'التصميم والتطوير: ليان ديزاين')) . '</span>
  <button class="totop" type="button" onclick="window.scrollTo({top:0,behavior:\'smooth\'})">↑ ' . e(tl('بازگشت به بالا', 'Back to top', 'العودة إلى الأعلى')) . '</button></div></div>
</footer>
<script src="assets/js/main.js?v=3" defer></script>
</body></html>';
}

/* ---------- ۴۰۴ ---------- */
function page_404(): void {
    http_response_code(404);
    page_header('404');
    echo '<div class="empty"><div class="empty-ic">🔍</div><h2>' . e(t('not_found')) . '</h2>
    <a class="btn" href="' . e(url('home')) . '">' . e(t('home')) . '</a></div>';
    page_footer();
}

/* ---------- روتینگ ---------- */
switch ($p) {

/* ===== صفحه اصلی ===== */
case 'home': {
    page_header();
    $hero = articles_query('', [], 1, 0, false, 'a.published_at DESC, a.id DESC')[0] ?? null;
    $sec = $hero ? articles_query('a.id != ' . (int)$hero['id'], [], 3, 0, false, 'a.published_at DESC, a.id DESC') : [];

    echo '<section class="hero"><div class="hero-grid">';
    if ($hero) {
        $im = img($hero['image']);
        echo '<a class="hero-main" href="' . e(url('article', ['id' => $hero['id']])) . '">'
           . ($im ? '<img src="' . e($im) . '" alt="">' : '<div class="hero-noimg noimg"></div>')
           . '<div class="hero-bd"><span class="badge">' . e(t('top_story')) . '</span>'
           . '<h2 class="hero-t">' . e($hero['title']) . '</h2>'
           . '<p class="hero-x">' . e(excerpt($hero['excerpt'] ?? '', 180)) . '</p>'
           . '<div class="meta"><time>' . e(date_i18n($hero['published_at'])) . '</time></div></div></a>';
        echo '<div class="hero-side">';
        foreach ($sec as $a) {
            $im = img($a['image']);
            echo '<a class="hero-card" href="' . e(url('article', ['id' => $a['id']])) . '">'
               . '<span class="hc-im">' . ($im ? '<img src="' . e($im) . '" alt="" loading="lazy">' : '') . '</span>'
               . '<span class="hc-bd"><h3>' . e($a['title']) . '</h3><time>' . e(date_i18n($a['published_at'], false)) . '</time></span></a>';
        }
        echo '</div>';
    }
    echo '</div></section>';

    /* پالس */
    $pulse = qall("SELECT m.*, COALESCE(t1.label, tf.label) AS label, COALESCE(t1.value, tf.value) AS value,
                          COALESCE(t1.note, tf.note) AS note,
                          COALESCE(t1.change_text, tf.change_text) AS change_text,
                          COALESCE(t1.submetrics, tf.submetrics) AS submetrics
                   FROM pulse_metrics m
                   LEFT JOIN pulse_metric_translations t1 ON t1.metric_id = m.id AND t1.lang = ?
                   LEFT JOIN pulse_metric_translations tf ON tf.metric_id = m.id AND tf.lang = 'fa'
                   WHERE m.is_active = 1 ORDER BY m.sort_order, m.id", [$lang]);
    if ($pulse) {
        echo '<section class="sec">' . section_head(tl('پالس منطقه آزاد', 'Free Zone Pulse', 'نبض المنطقة الحرة'), url('investments'))
           . '<div class="pulse">';
        foreach ($pulse as $m) {
            $subs = [];
            foreach ((array)json_decode((string)($m['submetrics'] ?? ''), true) as $r) {
                if (!is_array($r) || !isset($r[0])) continue;
                $sv = (string)($r[1] ?? '');
                $subs[] = '<span>' . e((string)$r[0]) . ($sv !== '' ? ' <b>' . e(num_i18n($sv)) . '</b>' : '') . '</span>';
            }
            $val = num_i18n((string)$m['value']);
            echo '<div class="pi"><div class="pi-v">' . e($val) . '</div><div class="pi-l">' . e($m['label']) . '</div>
                  <span class="pchg ' . e((string)$m['trend']) . '">' . e((string)($m['change_text'] ?? '')) . '</span>
                  <div class="psub">' . implode('', $subs) . '</div>
                  <div class="pi-n"><p class="pi-note">' . e((string)($m['note'] ?? '')) . '</p></div></div>';
        }
        echo '</div></section>';
    }

    /* آخرین اخبار */
    $latest = articles_query('', [], 6, 0, false, 'a.published_at DESC, a.id DESC');
    echo '<section class="sec">' . section_head(t('latest_news'), url('news'))
       . '<div class="ngrid">' . implode('', array_map(fn($a) => news_card($a), $latest)) . '</div></section>';

    /* اطلاعیه‌ها */
    $anns = x_list('announcements', 'announcement_translations', 'announcement_id', 'published_at DESC', true, ['title', 'body'], limit: 4);
    if ($anns) {
        echo '<section class="sec">' . section_head(t('announcements'), url('announcements')) . '<div class="alist">';
        foreach ($anns as $a) echo ann_card($a);
        echo '</div></section>';
    }

    /* رویدادها */
    $evs = x_list('events', 'event_translations', 'event_id', 'event_date ASC', true, ['title', 'description', 'location', 'category'], where: ['x.event_date >= CURDATE()'], limit: 4);
    if ($evs) {
        echo '<section class="sec">' . section_head(t('events'), url('events')) . '<div class="egrid">';
        foreach ($evs as $ev) echo event_card($ev);
        echo '</div></section>';
    }

    /* چندرسانه */
    $md = x_list('media_items', 'media_translations', 'media_id', 'created_at DESC', true, ['title', 'caption', 'mcategory'], limit: 3);
    if ($md) {
        echo '<section class="sec">' . section_head(t('media'), url('media')) . '<div class="mgrid">';
        foreach ($md as $mi) echo media_card($mi);
        echo '</div></section>';
    }

    /* سرمایه‌گذاری */
    $invs = x_list('investments', 'investment_translations', 'investment_id', 'created_at DESC', true, ['title', 'summary', 'description', 'category', 'volume', 'irr', 'payback', 'location', 'incentives'], limit: 4);
    if ($invs) {
        echo '<section class="sec">' . section_head(t('investments'), url('investments')) . '<div class="igrid">';
        foreach ($invs as $iv) echo investment_row($iv);
        echo '</div></section>';
    }
    page_footer();
} break;

/* ===== آرشیو اخبار ===== */
case 'news': {
    $where = []; $ttl = t('news');
    if ($cid) { foreach (cat_names() as $id2 => $nm) if ($id2 === $cid && $nm !== '') $ttl = $nm; $where[] = 'a.category_id = ' . $cid; }
    $page = max(1, ini('page', 1)); $per = app()['per_page']; $off = ($page - 1) * $per;
    [$total] = articles_query($where ? implode(' AND ', $where) : '', [], 0, 0, true);
    $rows = articles_query($where ? implode(' AND ', $where) : '', [], $per, $off, false, 'a.published_at DESC, a.id DESC');
    page_header($ttl);
    echo '<h1 class="pgtitle">' . e($ttl) . '</h1>
    <div class="filters"><a class="chip' . ($cid ? '' : ' on') . '" href="' . e(url('news')) . '">' . e(t('all_categories')) . '</a>';
    foreach (categories(false) as $c)
        echo '<a class="chip' . ($cid === (int)$c['id'] ? ' on' : '') . '" href="' . e(url('news', ['cat' => (int)$c['id']])) . '">' . e($c['name'] ?: '—') . '</a>';
    echo '</div><div class="ngrid">';
    foreach ($rows as $a) echo news_card($a);
    echo '</div>' . (paginate($total, $per, $page, url('news', ['cat' => $cid ?: ''])) ?? '');
    page_footer();
} break;

/* ===== صفحه مقاله ===== */
case 'article': {
    $id = ini('id', 0); $a = $id ? article_find($id) : null;
    if (!$a) { page_404(); break; }
    q("UPDATE articles SET views = views + 1 WHERE id = ?", [$id]);
    $catName = cat_names()[(int)$a['category_id']] ?? '';
    page_header($a['title']);
    $im = img($a['image']);
    echo '<nav class="crumbs"><a href="' . e(url('home')) . '">' . e(t('home')) . '</a> › <a href="' . e(url('news')) . '">' . e(t('news')) . '</a>' .
         ($catName ? ' › <a href="' . e(url('news', ['cat' => (int)$a['category_id']])) . '">' . e($catName) . '</a>' : '') . '</nav>
    <article class="art">
      <h1 class="art-t">' . e($a['title']) . '</h1>
      <div class="art-meta">
        <time>🗓 ' . e(date_i18n($a['published_at'])) . '</time>
        <span>👁 ' . e(num_i18n((int)$a['views'] + 1)) . ' ' . e(t('views')) . '</span>' .
        ($a['source_url'] ? '<a class="src" href="' . e($a['source_url']) . '" target="_blank" rel="noopener">' . e(t('source')) . ': ' . e($a['source_name'] ?: (string)parse_url((string)$a['source_url'], PHP_URL_HOST)) . ' ↗</a>' : '') . '
      </div>' .
      ($im ? '<figure class="art-im"><img src="' . e($im) . '" alt="' . e($a['title']) . '"></figure>' : '') .
      ($a['excerpt'] ? '<p class="art-lead">' . e($a['excerpt']) . '</p>' : '') .
      ($lang !== 'fa' && !q1("SELECT id FROM article_translations WHERE article_id = ? AND lang = ? AND body IS NOT NULL", [$id, $lang])
          ? '<p class="tr-note">ℹ️ ' . e(tl('ترجمه کامل متن این خبر به این زبان در دست تهیه است؛ متن کامل به فارسی نشان داده می‌شود.',
                                          'Full translation of this article is in progress; the original Persian text is shown below.',
                                          'الترجمة الكاملة لهذا الخبر قيد الإعداد؛ يتم عرض النص الفارسي أدناه.')) . '</p>' : '') .
      '<div class="prose">' . nl2br(e($a['body'] ?? '')) . '</div>';
    $tags = $a['tags'] ?? qall("SELECT tag FROM article_tags WHERE article_id = ?", [$id]);
    if ($tags) {
        echo '<div class="tags">';
        foreach ($tags as $tg) echo '<span class="tag">#' . e(is_array($tg) ? (string)$tg['tag'] : (string)$tg) . '</span>';
        echo '</div>';
    }
    echo '<div class="share"><span>' . e(tl('اشتراک‌گذاری', 'Share', 'مشاركة')) . ':</span>
      <a target="_blank" rel="noopener" href="https://t.me/share/url?url=' . e(urlencode('http://' . ($_SERVER['HTTP_HOST'] ?? '') . strtok($_SERVER['REQUEST_URI'] ?: '', '?') . '?p=article&id=' . $id)) . '">' . e(tl('تلگرام', 'Telegram', 'تيليجرام')) . '</a>
      <a target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?url=' . e(urlencode('http://' . ($_SERVER['HTTP_HOST'] ?? '') . '?p=article&id=' . $id)) . '">X</a>
      <button id="copyBtn" onclick="copyLink()">🔗 ' . e(tl('کپی لینک', 'Copy link', 'نسخ الرابط')) . '</button></div>
    </article>';
    $rel = articles_query((int)$a['category_id'] ? 'a.category_id = ' . (int)$a['category_id'] . ' AND a.id != ' . $id : 'a.id != ' . $id, [], 3);
    if ($rel) echo '<section class="sec">' . section_head(t('related')) . '<div class="ngrid">' . implode('', array_map(fn($r) => news_card($r), $rel)) . '</div></section>';
    page_footer();
} break;

/* ===== اطلاعیه‌ها و فرصت‌های سرمایه‌گذاری (ترکیبی) ===== */
case 'announcements': {
    page_header(t('merged_ann'));
    echo '<h1 class="pgtitle">' . e(t('merged_ann')) . '</h1>';
    /* بخش اول: فرصت‌های سرمایه‌گذاری */
    $ivs = x_list('investments', 'investment_translations', 'investment_id', 'created_at DESC', true, ['title', 'summary', 'description', 'category', 'volume', 'irr', 'payback', 'location', 'incentives']);
    echo '<h2 class="sect-t">' . e(t('investments')) . '</h2><div class="igrid" style="grid-template-columns:1fr">';
    foreach ($ivs as $iv) echo investment_row($iv, true);
    if (!$ivs) echo '<div class="empty">' . e(t('no_items')) . '</div>';
    echo '</div>';
    /* بخش دوم: اطلاعیه‌ها */
    $rows = x_list('announcements', 'announcement_translations', 'announcement_id', 'published_at DESC', true, ['title', 'body']);
    echo '<h2 class="sect-t">' . e(t('announcements')) . '</h2><div class="alist">';
    foreach ($rows as $a) echo ann_card($a, true);
    if (!$rows) echo '<div class="empty">' . e(t('no_items')) . '</div>';
    echo '</div>';
    page_footer();
} break;

/* investments به صفحه ترکیبی منتقل شد */
case 'investments': {
    header('Location: ' . url('announcements'));
    exit;
} break;

/* ===== رویدادها ===== */
case 'events': {
    page_header(t('events'));
    $rows = x_list('events', 'event_translations', 'event_id', 'event_date ASC', true, ['title', 'description', 'location', 'category'], where: ['x.event_date >= CURDATE() - INTERVAL 1 DAY']);
    echo '<h1 class="pgtitle">' . e(t('events')) . '</h1><div class="egrid">';
    foreach ($rows as $ev) echo event_card($ev);
    if (!$rows) echo '<div class="empty">' . e(t('no_items')) . '</div>';
    echo '</div>';
    page_footer();
} break;

/* ===== چندرسانه ===== */
case 'media': {
    $mc = getp('mc', '');
    $ttl = $mc === 'photo' ? t('photos') : ($mc === 'video' ? t('videos') : t('media'));
    page_header($ttl);
    $rows = x_list('media_items', 'media_translations', 'media_id', 'created_at DESC', true, ['title', 'caption', 'mcategory']);
    if ($mc === 'photo' || $mc === 'video') {
        /* دسته‌بندی بر اساس مقدار فارسی mcategory */
        $faMap = [];
        foreach (qall("SELECT media_id, mcategory FROM media_translations WHERE lang='fa'") as $r) $faMap[(int)$r['media_id']] = (string)$r['mcategory'];
        $grp = $mc === 'photo' ? ['گالری منظره', 'گزارش تصویری', 'مجموعه عکس'] : ['مستند کوتاه', 'ویدئوی مستند'];
        $rows = array_values(array_filter($rows, fn($r) => in_array($faMap[(int)($r['id'] ?? 0)] ?? '', $grp, true)));
    }
    echo '<h1 class="pgtitle">' . e($ttl) . '</h1>
    <div class="filters">
      <a class="chip' . ($mc === '' ? ' on' : '') . '" href="' . e(url('media')) . '">' . e(t('media')) . '</a>
      <a class="chip' . ($mc === 'photo' ? ' on' : '') . '" href="' . e(url('media', ['mc' => 'photo'])) . '">' . e(t('photos')) . '</a>
      <a class="chip' . ($mc === 'video' ? ' on' : '') . '" href="' . e(url('media', ['mc' => 'video'])) . '">' . e(t('videos')) . '</a>
    </div><div class="mgrid">';
    foreach ($rows as $mi) echo media_card($mi);
    if (!$rows) echo '<div class="empty">' . e(t('no_items')) . '</div>';
    echo '</div>';
    page_footer();
} break;

/* ===== سرمایه‌گذاری ===== */
case 'investments': {
    page_header(t('investments'));
    $rows = x_list('investments', 'investment_translations', 'investment_id', 'created_at DESC', true, ['title', 'summary', 'description', 'category', 'volume', 'irr', 'payback', 'location', 'incentives']);
    echo '<h1 class="pgtitle">' . e(t('investments')) . '<div style="font-size:13px;color:var(--muted);font-family:var(--f-app);font-weight:400;margin-top:4px">' . e(tl('فرصت‌های رسمی سرمایه‌گذاری منطقه آزاد بوشهر', 'Official investment opportunities of Bushehr Free Zone', 'فرصت الاستثمار الرسمية في المنطقة الحرة بوشهر')) . '</div></h1>';
    echo '<div class="igrid" style="grid-template-columns:1fr">';
    foreach ($rows as $iv) echo investment_row($iv, true);
    if (!$rows) echo '<div class="empty">' . e(t('no_items')) . '</div>';
    echo '</div>';
    page_footer();
} break;

/* ===== جستجو ===== */
case 'search': {
    page_header($q !== '' ? t('search') . ': ' . $q : t('search'));
    $results = []; $total = 0; $page = max(1, ini('page', 1)); $per = app()['per_page'];
    if ($q !== '') {
        $where = "(tl.title LIKE ? OR tf.title LIKE ? OR tl.excerpt LIKE ? OR tf.excerpt LIKE ? OR tl.body LIKE ? OR tf.body LIKE ?)";
        $pb = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        [$total] = articles_query($where, [$pb, $pb, $pb, $pb, $pb, $pb], 0, 0, true);
        $results = articles_query($where, [$pb, $pb, $pb, $pb, $pb, $pb], $per, ($page - 1) * $per);
    }
    echo '<h1 class="pgtitle">' . e(t('search')) . '</h1>
    <form class="qsearch big" action="index.php" method="get">
      <input type="hidden" name="p" value="search"><input type="hidden" name="lang" value="' . e($lang) . '">
      <input class="qin" type="search" name="q" placeholder="' . e(t('search_ph')) . '" value="' . e($q) . '">
      <button>' . e(t('search')) . '</button></form>';
    if ($q !== '') {
        echo '<div class="search-cnt">' . e(num_i18n($total)) . ' ' . tl('نتیجه', 'results', 'نتيجة') . '</div>';
        if ($results) {
            echo '<div class="ngrid">';
            foreach ($results as $a) echo news_card($a);
            echo '</div>' . (paginate($total, $per, $page, url('search', ['q' => $q])) ?? '');
        } else echo '<div class="empty">' . e(t('not_found')) . '</div>';
    }
    page_footer();
} break;

/* ===== درباره ما ===== */
case 'about': {
    page_header(t('about_portal'));
    echo '<h1 class="pgtitle">' . e(t('about_portal')) . '</h1>
    <div class="pbody">
      <p>' . e(t('about_body_p1')) . '</p>
      <p>' . e(t('about_body_p2')) . '</p>
      <p>' . e(t('about_body_p3')) . '</p>
    </div>
    <div class="cgrid">
      <div class="ccard"><a href="' . e(url('contact')) . '" class="chip on" style="text-decoration:none">' . e(t('contact')) . '</a></div>
      <div class="ccard"><a href="' . e(url('announcements')) . '" class="chip" style="text-decoration:none">' . e(t('merged_ann')) . '</a></div>
    </div>';
    page_footer();
} break;

/* ===== تماس با ما ===== */
case 'contact': {
    page_header(t('contact'));
    echo '<h1 class="pgtitle">' . e(t('contact')) . '</h1>
    <p class="intro-p">' . e(t('contact_intro')) . '</p>
    <div class="cgrid">
      <div class="ccard"><h3>' . e(t('address_lbl')) . '</h3><p>' . e(setting('address', '')) . '</p></div>
      <div class="ccard"><h3>' . e(t('phone_lbl')) . '</h3><p dir="ltr">' . e(setting('phone', '')) . '</p></div>
      <div class="ccard"><h3>' . e(t('email_lbl')) . '</h3><p dir="ltr">' . e(setting('email', '')) . '</p></div>
      <div class="ccard"><h3>' . e(t('hours_lbl')) . '</h3><p>' . e(t('hours_val')) . '</p></div>
    </div>';
    page_footer();
} break;

default: page_404();
}
