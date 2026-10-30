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
<link rel="stylesheet" href="assets/css/main.css?v=15">
<script>try{var m=localStorage.getItem("theme")||"auto";if(m==="dark"||(m==="auto"&&matchMedia("(prefers-color-scheme:dark)").matches))document.documentElement.dataset.theme="dark"}catch(e){}</script>
</head><body>
<div class="u-strip"><div class="wrap">
  <div class="u-info"><span class="u-date">📅 ' . e($portalDate) . '</span><span class="sep">&nbsp;|&nbsp;</span><span class="u-hijri">🕌 ' . e($hijri) . '</span>' . ($greg !== '' ? '<span class="sep">&nbsp;|&nbsp;</span><span class="u-greg">🌍 ' . e($greg) . '</span>' : '') . '</div>
</div></div>
<header class="hdr"><div class="wrap hdr-in">
  <div class="hdr-row1">
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
        <button type="button" class="nav-link dd-toggle' . ($mcOn ? ' on' : '') . '">' . e(t('multimedia')) . '<span class="dd-caret"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></span></button>
        <div class="subnav">
          <a href="' . e(url('media')) . '"><b>' . e(t('media')) . '</b><small>' . e(tl('همه آثار', 'All items', 'كل الأعمال')) . '</small></a>
          <a href="' . e(url('media', ['mc' => 'photo'])) . '">' . e(t('photos')) . '<small>' . e(t('mc_photo')) . '</small></a>
          <a href="' . e(url('media', ['mc' => 'video'])) . '">' . e(t('videos')) . '<small>' . e(t('mc_video')) . '</small></a>
        </div></div>';
    /* dropdown: دسترسی سریع */
    echo '<div class="nav-li has-sub">
        <button type="button" class="nav-link dd-toggle">' . e(t('quick_links')) . '<span class="dd-caret"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></span></button>
        <div class="subnav">
          <a href="https://my.freezones.ir" target="_blank" rel="noopener"><b>' . e(t('services_portal')) . '</b><small>' . e(tl('خدمات الکترونیک و ثبت‌نام', 'e-services &amp; registration', 'الخدمات الإلكترونية والتسجيل')) . '</small></a>
          <a href="https://www.freezones.ir" target="_blank" rel="noopener"><b>' . e(t('sec_portal')) . '</b><small>' . e(tl('قوانین، نشریات و اطلاعیه‌ها', 'rules, bulletins &amp; notices', 'القوانين والنشرات والإعلانات')) . '</small></a>
          <a href="mailto:' . e(setting('email', 'info@bushehrfz.ir')) . '"><b>' . e(t('zr_suggest')) . '</b><small>' . e(tl('ارتباط مستقیم با دبیرخانه', 'direct contact with the secretariat', 'التواصل المباشر مع الأمانة')) . '</small></a>
        </div></div>';
    echo '</nav>
  </div>
  <div class="hdr-row2">
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
    <p>' . e(t('footer_desc')) . '</p>
    <div class="fsoc">
      <span class="fsoc-t">' . e(tl('ما را دنبال کنید', 'Follow us', 'تابعونا')) . '</span>
      <div class="fsoc-row">
        <a class="fs fs-ig" href="https://instagram.com/freena.ir" target="_blank" rel="noopener" title="Instagram" aria-label="Instagram"><svg width="19" height="20" viewBox="0 0 19 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19 5.83359V14.167C18.9964 17.3876 16.5174 19.9969 13.4588 20H5.54241C2.48322 19.9975 0.00296894 17.3876 0 14.1677V5.83484C0.00296894 2.61362 2.48262 0.00250047 5.54241 0H13.4588C16.5174 0.00437582 18.9958 2.61362 18.9994 5.83359V5.83422L19 5.83359ZM17.4158 5.83359C17.4134 3.53316 15.6433 1.66906 13.4588 1.66594H5.54241C3.35668 1.66844 1.58541 3.53316 1.58363 5.83422V14.1683C1.5866 16.4681 3.35727 18.3322 5.54181 18.3347H13.4582C15.6427 18.3316 17.4128 16.4681 17.4158 14.1689V5.83359ZM13.8536 9.99938C13.8507 12.5292 11.9031 14.5796 9.50059 14.5827C7.09754 14.5796 5.14991 12.5292 5.14695 10C5.14991 7.47015 7.09754 5.41977 9.5 5.41664C11.9031 5.41977 13.8507 7.47015 13.8536 9.99938ZM12.27 9.99938C12.2682 8.3897 11.029 7.08445 9.5 7.08258C7.971 7.08445 6.73117 8.38907 6.72939 9.99875C6.73058 11.6091 7.96981 12.9143 9.49941 12.9162C11.029 12.9143 12.2688 11.6091 12.27 9.99875V9.99938ZM15.8327 4.58273C15.8327 5.27286 15.3001 5.83359 14.6458 5.83359C14.0104 5.80484 13.5063 5.25599 13.5063 4.58273C13.5063 3.90948 14.011 3.36063 14.6434 3.33187H14.6458C15.3001 3.33187 15.8327 3.89386 15.8327 4.58273V4.58273Z" fill="white"></path></svg></a>
        <a class="fs fs-tg" href="https://t.me/iranfreezones" target="_blank" rel="noopener" title="Telegram" aria-label="Telegram"><img src="assets/img/social/telegram-plane.svg" width="19" height="19" alt="Telegram"></a>
        <a class="fs fs-bl" href="https://ble.ir/iranfreezones_ir" target="_blank" rel="noopener" title="Bale" aria-label="Bale"><img src="assets/img/social/baleh.svg" width="19" height="19" alt="Bale"></a>
        <a class="fs fs-et" href="http://eitaa.com/iranfreezones_ir" target="_blank" rel="noopener" title="Eitaa" aria-label="Eitaa"><svg xmlns="http://www.w3.org/2000/svg" width="20.144" height="20.594" viewBox="0 0 20.144 20.594"><path d="M10.494,20.543a5.684,5.684,0,0,1-2-.712,5.85,5.85,0,0,1-2.76-3.8c-.13-.615-.146-1.393-.129-6.184.016-5.156,0-4.89.25-5.74A6.886,6.886,0,0,1,6.56,2.593,5.865,5.865,0,0,1,10.447.068,50.9,50.9,0,0,1,15.644,0c5.364,0,5.187-.008,6.063.269a5.841,5.841,0,0,1,3.7,3.59c.283.8.3.933.317,3.05l.017,1.913-.335.23A13.685,13.685,0,0,0,23.419,10.9a32.432,32.432,0,0,1-2.559,2.494,7.073,7.073,0,0,1-3.2,1.6,5.618,5.618,0,0,1-1.975-.082c-.475-.124-.444-.144-.623.4a5.453,5.453,0,0,0-.273,1.327l-.027.349-.125-.026a3.592,3.592,0,0,1-2.475-2.145,3.074,3.074,0,0,1-.223-1.046l-.014-.292-.265-.244a3.186,3.186,0,0,1-1.025-1.768,3.808,3.808,0,0,1,1.32-3.223,8.784,8.784,0,0,1,4.25-2.3,6.934,6.934,0,0,1,2.062-.064,3.346,3.346,0,0,1,2.27,1.406c.156.233.167.266.152.476a.7.7,0,0,1-.157.423,5.354,5.354,0,0,1-2.863,1.442c-2.252.5-3.685-.122-3.461-1.509a1.242,1.242,0,0,0,.027-.269c-.027-.027-.251.089-.493.257a2.486,2.486,0,0,0-.9,1.379,2.131,2.131,0,0,0-.029.558,1.318,1.318,0,0,0,.165.632,2.438,2.438,0,0,0,.35.505l.206.215-.082.1a4.157,4.157,0,0,0-.581,1.044,3.119,3.119,0,0,0-.09,1.557,3.372,3.372,0,0,0,.634,1.26,3.272,3.272,0,0,0,.76.656c.021,0,.038-.041.038-.091a4.955,4.955,0,0,1,.28-1.139,4.862,4.862,0,0,1,2.42-2.351c.21-.112.817-.4,1.349-.65a15.529,15.529,0,0,0,2.155-1.12,4.489,4.489,0,0,0,1.836-3.11,8.126,8.126,0,0,0,0-1.894,4.381,4.381,0,0,0-4.134-3.75c-2.51-.263-5.7,1.676-7.673,4.657a12.256,12.256,0,0,0-1.877,4.67,11.026,11.026,0,0,0-.08,2.36,5.762,5.762,0,0,0,1.633,3.566,5.6,5.6,0,0,0,1.6,1.127,7.234,7.234,0,0,0,6.26.061,9.3,9.3,0,0,0,2.594-1.931,29.354,29.354,0,0,0,3.082-3.825,10.418,10.418,0,0,1,1.95-2.235l.129-.074-.016,2.652a15.275,15.275,0,0,1-.106,3.056,5.808,5.808,0,0,1-4.458,4.5l-.4.092-4.955.009c-4.062.007-5.02,0-5.311-.048Z" transform="translate(-5.599 0.001)" fill="#fff"></path></svg></a>
      </div>
    </div></div>
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
<script src="assets/js/main.js?v=4" defer></script>
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

    /* آخرین اخبار — بدون تکرار خبرهای هیرو */
    $skip = $hero ? [(int)$hero['id']] : [];
    foreach ($sec as $sa) $skip[] = (int)$sa['id'];
    $skipSql = $skip ? 'a.id NOT IN (' . implode(',', $skip) . ')' : '';
    $latest = $skipSql !== '' ? articles_query($skipSql, [], 6, 0, false, 'a.published_at DESC, a.id DESC') : articles_query('', [], 6, 0, false, 'a.published_at DESC, a.id DESC');
    if ($latest) {
        echo '<section class="sec">' . section_head(t('latest_news'), url('news'))
           . '<div class="ngrid">' . implode('', array_map(fn($a) => news_card($a), $latest)) . '</div></section>';
    }

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
    /* پالس منطقه آزاد */
    $pulse = qall("SELECT m.*, COALESCE(t1.label, tf.label) AS label, COALESCE(t1.value, tf.value) AS value,
                          COALESCE(t1.note, tf.note) AS note,
                          COALESCE(t1.change_text, tf.change_text) AS change_text,
                          COALESCE(t1.submetrics, tf.submetrics) AS submetrics
                   FROM pulse_metrics m
                   LEFT JOIN pulse_metric_translations t1 ON t1.metric_id = m.id AND t1.lang = ?
                   LEFT JOIN pulse_metric_translations tf ON tf.metric_id = m.id AND tf.lang = 'fa'
                   WHERE m.is_active = 1 ORDER BY m.sort_order, m.id", [$lang]);
    if ($pulse) {
        echo '<section class="sec">' . section_head(tl('پالس منطقه آزاد', 'Free Zone Pulse', 'نبض المنطقة الحرة'))
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
