<?php
// --- Settings ---
$feeds = [
    'Record'  =>'https://www.dailyrecord.co.uk/all-about/celtic-fc/?service=rss',
    'Scotsman'  =>'https://www.scotsman.com/sport/football/celtic/rss',
    'BBC'  =>    'https://feeds.bbci.co.uk/sport/6d397eab-9d0d-b84a-a746-8062a76649e5/rss.xml',
    'G Times'  =>    'https://www.glasgowtimes.co.uk/sport/celtic/rss/',
    'Guardian'  =>    'https://www.theguardian.com/football/celtic/rss',
    'STV'  =>    'https://news.stv.tv/topic/celtic/feed',
    'Charity Celtic'  =>    'https://charity.celticfc.com/feed/',
    'Express'  =>    'https://www.express.co.uk/posts/rss/67.99/celtic',
    'Football Scotland'  =>    'https://www.footballscotland.co.uk/all-about/celtic-fc?service=rss',
    'G World'  =>    'https://www.glasgowworld.com/sport/football/celtic/rss',
    'G Live'  =>    'https://www.glasgowlive.co.uk/all-about/celtic-fc/?service=rss'
];

$default_image = 'fleg.jpg'; 
$cache_time = 15 * 60; 
$max_items  = 30;      
$display_limit = 20;   

// Filters
$exclude_keywords = ['baby formula']; 
$include_keywords = [];               
$filter_source   = '';                
$filter_category = '';                

$all_items = [];

// --- Fetch & Cache ---
foreach ($feeds as $key => $url) {
    $cache_file = __DIR__ . "/cache/{$key}.json";
    $feed_url = "http://localhost/fulltxt/makefulltextfeed.php?url=" . urlencode($url) . "&max=5&links=preserve&exc=&format=json";

    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
        $json_data = file_get_contents($cache_file);
    } else {
        $json_data = @file_get_contents($feed_url);
        if ($json_data) {
            file_put_contents($cache_file, $json_data);
        } elseif (file_exists($cache_file)) {
            $json_data = file_get_contents($cache_file);
        } else {
            continue;
        }
    }

    $json = json_decode($json_data);
    if (!$json) continue;

    $channel = $json->rss->channel ?? null;
    if (!$channel) continue;

    $items = is_array($channel->item) ? $channel->item : [$channel->item];

    foreach ($items as $item) {
    
 /*   
        $desc_raw = $item->description ?? '';
        $desc_no_attrs = preg_replace('/<p\b[^>]*>/i', '<p>', $desc_raw);
 */
   // --- Description cleaning ---
    $desc_raw = $item->description ?? '';

    // remove <script> and <style>
    $desc_clean = preg_replace('#<(script|style)[^>]*>.*?</\1>#si', '', $desc_raw);

    // extract <p> content
    preg_match_all('#<p\b[^>]*>(.*?)</p>#si', $desc_clean, $matches);

    $desc = '';
    if (!empty($matches[1])) {
        foreach ($matches[1] as $p) {
            // keep only allowed tags
            $text = trim(strip_tags($p, '<a><strong><em>'));
            $text = preg_replace('/\s+/u', ' ', $text);

            // sanitize <a> tags
            $text = preg_replace_callback(
                '#<a\s+[^>]*href=(["\'])([^"\']+)\1[^>]*>(.*?)</a>#si',
                function ($m) {
                    $href_safe = htmlspecialchars($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $label = htmlspecialchars($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    return '<a href="' . $href_safe . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
                },
                $text
            );

            if (trim(strip_tags($text)) === '') continue;
            $desc .= '<p>' . $text . '</p>';
        }
    } else {
        // fallback if no <p>
        $fallback = trim(strip_tags($desc_clean, '<a><strong><em>'));
        if ($fallback !== '') {
            $desc = '<p>' . htmlspecialchars(preg_replace('/\s+/u', ' ', $fallback), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>';
        }
    }

    $title = $item->title ?? '';


    $cats = !empty($item->category) ? (array)$item->category : [];
    $cat_string = implode(', ', $cats);

    $all_items[] = [
        'source' => $channel->title ?? $key,
        'title'  => $title,
        'link'   => $item->link ?? '',
        'date'   => strtotime($item->pubDate ?? ''),
        'desc'   => $desc,
        'cats'   => $cat_string,
        'image'  => $item->og_image ?? $default_image
    ];

   }
   
   }


// --- Sort newest first ---
usort($all_items, fn($a, $b) => $b['date'] <=> $a['date']);

// --- Filters ---
if ($filter_source) {
    $all_items = array_filter($all_items, fn($i) => stripos($i['source'], $filter_source) !== false);
}
if ($filter_category) {
    $all_items = array_filter($all_items, fn($i) => stripos($i['cats'], $filter_category) !== false);
}
if ($exclude_keywords) {
    $all_items = array_filter($all_items, function($i) use ($exclude_keywords) {
        $haystack = strtolower($i['title'] . ' ' . $i['desc']);
        foreach ($exclude_keywords as $word) {
            if (strpos($haystack, strtolower($word)) !== false) {
                return false;
            }
        }
        return true;
    });
}
if ($include_keywords) {
    $all_items = array_filter($all_items, function($i) use ($include_keywords) {
        $haystack = strtolower($i['title'] . ' ' . $i['desc']);
        foreach ($include_keywords as $word) {
            if (strpos($haystack, strtolower($word)) !== false) {
                return true;
            }
        }
        return false;
    });
}

// --- Limit global pool ---
$all_items = array_slice($all_items, 0, $max_items);

// --- Save combined JSON ---
file_put_contents(
    __DIR__ . '/cache/mynewscombined.json',
    json_encode(
        [
            'generated' => date('c'),
            'count'     => count($all_items),
            'items'     => $all_items
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    )
);

echo "Combined JSON written to cache/mynewscombined.json (" . count($all_items) . " items)\n";

