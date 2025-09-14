<?php
// Read the JSON file contents
$json_data = file_get_contents('cache/mynewscombined.json');

// Decode the JSON data
$data = json_decode($json_data, true);

function shorten_with_html($html, $limit = 350) {
    $word_count = 0;
    $output = '';
    $open_tags = [];

    // Regex: split into tags OR text
    preg_match_all('/(<[^>]+>|[^<>\s]+|\s+)/u', $html, $parts);

    foreach ($parts[0] as $part) {
        if (preg_match('/^<\s*\/([a-z0-9]+)>/i', $part, $match)) {
            // Closing tag
            $tag = strtolower($match[1]);
            $key = array_search($tag, $open_tags);
            if ($key !== false) {
                unset($open_tags[$key]);
            }
            $output .= $part;
        } elseif (preg_match('/^<\s*([a-z0-9]+)(\s+[^>]*)?>/i', $part, $match)) {
            // Opening tag
            $tag = strtolower($match[1]);
            if (!preg_match('/\/\s*>$/', $part)) { // not self-closing
                array_unshift($open_tags, $tag);
            }
            $output .= $part;
        } elseif (trim($part) !== '') {
            // Text word
            $word_count++;
            $output .= $part;
            if ($word_count >= $limit) {
                $output .= '...';
                break;
            }
        } else {
            // Whitespace
            $output .= $part;
        }
    }

    // Close any still-open tags
    foreach ($open_tags as $tag) {
        $output .= "</$tag>";
    }

    return $output;
}


// Start HTML
$html  = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"UTF-8\">\n";
$html .= "<title>Latest Blog Posts</title>\n";
$html .= "<style>
    body { font-family: Arial, sans-serif; line-height: 1.5; max-width: 700px; margin: auto; padding: 20px; }
    .item { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #ccc; }
    .title { font-size: 1.2em; margin: 0; }
    .date { color: #666; font-size: 0.9em; }
    footer { margin-top: 40px; font-size: 0.85em; color: #555; text-align: center; }
    img { max-width: 200px; display: block; margin-bottom: 10px; }
</style>\n";
$html .= "</head>\n<body>\n";
$html .= "<h1>Latest Blog Posts</h1>\n";
// Check if decoding was successful
if ($data === null) {
    // Handle error if JSON decoding failed
    echo "Error decoding JSON file.";
} 
$items = array_slice($data['items'], 0, 20);

    if (isset($items) && is_array($items)) {
        foreach ($items as $item) {
         $desc = shorten_with_html($item['desc'], 350);      
         $title = $item['title'];       
         $link = $item['link'];
         $img = $item['image'];
         $src = $item['source'];
         $date  = !empty($item['date']) ? date("F j, Y, g:i a", $item['date']) : '';


    
    $html .= "<div class=\"item\">\n";

    if ($img) {
        $html .= "  <img src=\"$img\" alt=\"\">\n";
    }

    $html .= "  <p class=\"title\"><a href=\"$link\" target=\"_blank\">$title</a></p>\n";

    if ($date) {
        $html .= "  <p class=\"date\">$date | <small>$src</small></p>\n";
    }

    if ($desc) {
        $html .= $desc; // insert as-is
    }

    $html .= "</div>\n";    

}
}

// Footer with timestamp
$updated = $data['generated'] ?? date('c');
$html .= "<footer>Last updated: " . date("F j, Y, g:i a", strtotime($updated)) . "</footer>\n";

$html .= "</body>\n</html>";

// Save to file
file_put_contents('mynews.html', $html);

echo "blogs.html generated with " . count($items) . " posts\n";

