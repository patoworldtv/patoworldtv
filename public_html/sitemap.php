<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

header("Content-Type: application/xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://www.patoworldtv.com/</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  
  <url><loc>https://www.patoworldtv.com/index.php?cat=news</loc><changefreq>daily</changefreq><priority>0.8</priority></url>
  <url><loc>https://www.patoworldtv.com/index.php?cat=sport</loc><changefreq>daily</changefreq><priority>0.8</priority></url>
  <url><loc>https://www.patoworldtv.com/index.php?cat=entertainment</loc><changefreq>daily</changefreq><priority>0.8</priority></url>
  <url><loc>https://www.patoworldtv.com/index.php?cat=general</loc><changefreq>daily</changefreq><priority>0.8</priority></url>

  <?php
  // Fetch all posts dynamically from the database
  $st = $pdo->query("SELECT slug, created_at FROM posts ORDER BY created_at DESC");
  while ($p = $st->fetch()) {
      $date = date('Y-m-d', strtotime($p['created_at']));
      echo "  <url>\n";
      echo "    <loc>https://www.patoworldtv.com/post.php?slug=" . htmlspecialchars($p['slug']) . "</loc>\n";
      echo "    <lastmod>{$date}</lastmod>\n";
      echo "    <changefreq>monthly</changefreq>\n";
      echo "    <priority>0.6</priority>\n";
      echo "  </url>\n";
  }
  ?>
</urlset>