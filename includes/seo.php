<?php
/**
 * /includes/seo.php
 * Render full SEO <head> block: meta, OG, Twitter Card, Schema.org JSON-LD
 */

function seo_head(array $opts = []): void {
    $base     = rtrim(get_setting('canonical_url') ?: SITE_URL, '/');
    $siteName = get_setting('site_name', 'Solen');
    $hreflang = get_setting('hreflang', 'en-US');
    $index    = get_setting('robots_index','1') === '1';
    $ogImg    = get_setting('og_image') ?: $base . '/assets/og.jpg';
    $twitter  = get_setting('twitter_handle','');
    $ga       = get_setting('google_analytics','');
    $gtm      = get_setting('gtm_id','');
    $fbPixel  = get_setting('fb_pixel','');
    $customHead = get_setting('custom_head_scripts','');

    $title    = $opts['title']       ?? get_setting('meta_title_home', $siteName . ' — AI Wellness Coach That Remembers You');
    $desc     = $opts['desc']        ?? get_setting('meta_desc_home',  'Solen is your personal AI wellness coach. Get daily check-ins, mood tracking, and personalized support. Start free.');
    $canon    = $opts['canonical']   ?? $base . ($_SERVER['REQUEST_URI'] ?? '/');
    $type     = $opts['og_type']     ?? 'website';
    $image    = $opts['og_image']    ?? $ogImg;
    $noIndex  = $opts['noindex']     ?? false;
    $schema   = $opts['schema']      ?? null;

    // Clean canonical — strip query strings for non-blog pages
    if (empty($opts['canonical'])) {
        $path  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $canon = $base . $path;
    }

    $robotsContent = (!$index || $noIndex) ? 'noindex, nofollow' : 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1';
    ?>
<meta name="description" content="<?= h($desc) ?>"/>
<meta name="robots" content="<?= $robotsContent ?>"/>
<link rel="canonical" href="<?= h($canon) ?>"/>
<link rel="alternate" hreflang="<?= h($hreflang) ?>" href="<?= h($canon) ?>"/>

<!-- Open Graph -->
<meta property="og:type"        content="<?= h($type) ?>"/>
<meta property="og:title"       content="<?= h($title) ?>"/>
<meta property="og:description" content="<?= h($desc) ?>"/>
<meta property="og:url"         content="<?= h($canon) ?>"/>
<meta property="og:image"       content="<?= h($image) ?>"/>
<meta property="og:image:width" content="1200"/>
<meta property="og:image:height"content="630"/>
<meta property="og:site_name"   content="<?= h($siteName) ?>"/>
<meta property="og:locale"      content="<?= h(str_replace('-','_',$hreflang)) ?>"/>

<!-- Twitter Card -->
<meta name="twitter:card"        content="summary_large_image"/>
<meta name="twitter:title"       content="<?= h($title) ?>"/>
<meta name="twitter:description" content="<?= h($desc) ?>"/>
<meta name="twitter:image"       content="<?= h($image) ?>"/>
<?php if ($twitter): ?>
<meta name="twitter:site" content="<?= h($twitter) ?>"/>
<?php endif ?>

<!-- Schema.org JSON-LD -->
<script type="application/ld+json">
<?= json_encode($schema ?? default_schema($title, $desc, $canon, $image, $siteName, $base), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>

<?php if ($ga): ?>
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= h($ga) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= h($ga) ?>');</script>
<?php endif ?>

<?php if ($gtm): ?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?= h($gtm) ?>');</script>
<?php endif ?>

<?php if ($fbPixel): ?>
<!-- Facebook Pixel -->
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','<?= h($fbPixel) ?>');fbq('track','PageView');</script>
<?php endif ?>

<?php if ($customHead) echo $customHead ?>
    <?php
}

function default_schema(string $title, string $desc, string $canon, string $image, string $siteName, string $base): array {
    return [
        '@context'         => 'https://schema.org',
        '@type'            => 'SoftwareApplication',
        'name'             => $siteName,
        'description'      => $desc,
        'url'              => $canon,
        'image'            => $image,
        'applicationCategory' => 'HealthApplication',
        'operatingSystem'  => 'Web',
        'offers'           => [
            ['@type'=>'Offer','price'=>'0','priceCurrency'=>'USD','name'=>'Free Trial'],
            ['@type'=>'Offer','price'=>'12.99','priceCurrency'=>'USD','priceSpecification'=>['@type'=>'RecurringCharge','billingFrequency'=>'Monthly'],'name'=>'Solen Pro'],
            ['@type'=>'Offer','price'=>'24.99','priceCurrency'=>'USD','priceSpecification'=>['@type'=>'RecurringCharge','billingFrequency'=>'Monthly'],'name'=>'Solen Premium'],
        ],
        'publisher'        => ['@type'=>'Organization','name'=>$siteName,'url'=>$base],
        'aggregateRating'  => ['@type'=>'AggregateRating','ratingValue'=>'4.8','reviewCount'=>'312','bestRating'=>'5'],
    ];
}

function blog_post_schema(array $post, string $canon, string $base): array {
    return [
        '@context'      => 'https://schema.org',
        '@type'         => 'Article',
        'headline'      => $post['title'],
        'description'   => $post['excerpt'] ?? '',
        'url'           => $canon,
        'image'         => $post['featured_image'] ?? get_setting('og_image'),
        'datePublished' => $post['published_at']   ?? $post['created_at'],
        'dateModified'  => $post['updated_at']     ?? $post['created_at'],
        'publisher'     => [
            '@type' => 'Organization',
            'name'  => get_setting('site_name','Solen'),
            'logo'  => ['@type'=>'ImageObject','url'=>$base.'/assets/logo.png'],
        ],
        'author'        => ['@type'=>'Person','name'=>'Solen Editorial'],
        'mainEntityOfPage' => ['@type'=>'WebPage','@id'=>$canon],
    ];
}

function faq_schema(array $faqs): array {
    return [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_map(fn($f) => [
            '@type'          => 'Question',
            'name'           => $f['q'],
            'acceptedAnswer' => ['@type'=>'Answer','text'=>$f['a']],
        ], $faqs),
    ];
}
