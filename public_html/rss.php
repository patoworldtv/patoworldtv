<?php
declare(strict_types=1);

// 1. Prevent execution timeouts & buffer truncations
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '30');

// 2. Initialize bootstrap/database
require __DIR__ . '/../app/bootstrap.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Database engine initialization failure.';
    exit;
}

// 3. Clear existing output buffers to prevent partial corruptions
if (ob_get_level()) {
    ob_end_clean();
}

// 4. Set RSS Content-Type Header
header('Content-Type: application/rss+xml; charset=UTF-8');

// Helper function to sanitize non-printable/invalid XML 1.0 characters and ensure UTF-8 encoding
function sanitize_xml_string(?string $str): string {
    if ($str === null || $str === '') {
        return '';
    }
    // Clean UTF-8 bytes
    $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
    // Strip invalid ASCII control characters (keep standard tab, newline, carriage return)
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
}

// Buffer output
ob_start();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" 
     xmlns:content="http://purl.org/rss/1.0/modules/content/" 
     xmlns:media="http://search.yahoo.com/mrss/">
<channel>
    <title>PatoworldTV</title>
    <link>https://patoworldtv.com</link>
    <description>Latest interactive news feeds, video updates, and media posts from PatoworldTV.</description>
    <language>en-us</language>
    <lastBuildDate><?= date(DATE_RSS) ?></lastBuildDate>

    <?php
    try {
        // Optimized query limit to 5 posts to prevent Make.com timeout errors
        $stmt = $pdo->query("SELECT title, slug, body, media_url, media_type, created_at 
                             FROM posts 
                             ORDER BY id DESC LIMIT 5");
        
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as $post) {
            $title = sanitize_xml_string($post['title'] ?? '');
            $postTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $postLink = 'https://patoworldtv.com/post.php?slug=' . urlencode($post['slug'] ?? '');
            $pubDate = !empty($post['created_at']) ? date(DATE_RSS, strtotime($post['created_at'])) : date(DATE_RSS);
            
            // Sanitization for XML safely
            $rawBody = sanitize_xml_string($post['body'] ?? '');
            $plainBody = strip_tags($rawBody);
            
            // Short description excerpt
            $excerpt = htmlspecialchars(mb_substr($plainBody, 0, 250, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '...';

            // Escape CDATA closing tags if any exist in the content
            $safeBody = str_replace(']]>', ']]&gt;', $rawBody);

            echo " <item>\n";
            echo "   <title>{$postTitle}</title>\n";
            echo "   <link>{$postLink}</link>\n";
            echo "   <guid isPermaLink=\"true\">{$postLink}</guid>\n";
            echo "   <pubDate>{$pubDate}</pubDate>\n";
            echo "   <description>{$excerpt}</description>\n";
            echo "   <content:encoded><![CDATA[" . $safeBody . "]]></content:encoded>\n";

            // Media attachment handling
            if (!empty($post['media_url'])) {
                $mediaPath = $post['media_url'];
                if (strpos($mediaPath, 'http') !== 0) {
                    $mediaPath = 'https://patoworldtv.com' . (strpos($mediaPath, '/') === 0 ? '' : '/') . $mediaPath;
                }
                
                $mediaUrl = htmlspecialchars(sanitize_xml_string($mediaPath), ENT_QUOTES, 'UTF-8');
                $mediaType = !empty($post['media_type']) ? htmlspecialchars(sanitize_xml_string($post['media_type']), ENT_QUOTES, 'UTF-8') : 'image/jpeg';
                $medium = (strpos($mediaType, 'video') !== false ? 'video' : 'image');

                echo "   <media:content url=\"{$mediaUrl}\" type=\"{$mediaType}\" medium=\"{$medium}\" />\n";
                echo "   <enclosure url=\"{$mediaUrl}\" type=\"{$mediaType}\" length=\"0\" />\n";
                echo "   <image>\n";
                echo "     <url>{$mediaUrl}</url>\n";
                echo "   </image>\n";
            }

            echo " </item>\n";
        }
    } catch (Throwable $t) {
        // Log errors to web server error log without stopping execution mid-item
        error_log("RSS Feed Processing Error: " . $t->getMessage());
    }
    ?>
</channel>
</rss>
<?php
// Output full buffer safely
ob_end_flush();
?>