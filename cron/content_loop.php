<?php
/**
 * cron/content_loop.php — Automated SEO Content Expansion
 *
 * Runs nightly. Analyzes anonymized user themes and drafts a relevant
 * wellness article for the blog.
 */

// Since this is a CLI cron job, we define CWD
if (php_sapi_name() !== 'cli' && !isset($_GET['secret'])) {
    die('Unauthorized');
}

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/providers/router.php';

// 1. Analyze platform-wide themes (Last 7 days)
$topTags = db_query(
    "SELECT tags FROM memory_episodes 
     WHERE created_at > datetime('now','-7 days') AND tags IS NOT NULL LIMIT 500"
);

$allTags = [];
foreach ($topTags as $row) {
    $tags = json_decode($row['tags'], true);
    if ($tags) $allTags = array_merge($allTags, $tags);
}

if (empty($allTags)) {
    echo "No trends found this week.\n";
    exit;
}

$tagCounts = array_count_values($allTags);
arsort($tagCounts);
$topTrend = array_key_first($tagCounts);

echo "Top platform trend: {$topTrend}\n";

// 2. Check if we already have a recent post about this
$existing = db_one("SELECT id FROM blog_posts WHERE title LIKE ? OR tags LIKE ?", ["%{$topTrend}%", "%{$topTrend}%"]);
if ($existing) {
    // Try the second trend
    $topTrend = array_keys($tagCounts)[1] ?? $topTrend;
}

// 3. Generate Article using AI
$system = "You are a world-class wellness journalist for Solen.
           Your task is to write a helpful, SEO-optimized blog post about the current trend: '{$topTrend}'.
           The article should offer practical advice, empathy, and mention how an AI coach can help.
           Output ONLY valid JSON:
           {
             \"title\": \"Catchy SEO Title\",
             \"excerpt\": \"Short 2-sentence hook\",
             \"content\": \"Full HTML content with H2, H3, P tags\",
             \"meta_title\": \"SEO Title (60 chars)\",
             \"meta_desc\": \"SEO Description (155 chars)\",
             \"tags\": \"tag1, tag2, trend_tag\",
             \"image_prompt\": \"A detailed, high-quality prompt for DALL-E to generate a serene, professional wellness image for this article. Style: minimal, warm, empathetic, no text.\"
           }";

$prompt = "Write a high-quality article for users currently struggling with '{$topTrend}'.";

$messages = [['role' => 'user', 'content' => $prompt]];

// Sync call to AI Router
$jsonStr = route_ai_request_sync($messages, $system, 3000, [
    'provider' => get_setting('ai_provider', 'claude')
]);

if (!$jsonStr) {
    echo "AI generation failed.\n";
    exit;
}

$data = json_decode(trim(preg_replace('/```json|```/', '', $jsonStr)), true);

if (empty($data['title']) || empty($data['content'])) {
    echo "Invalid AI response.\n";
    exit;
}

// 4. Generate Image (if prompt exists)
$featImg = null;
if (!empty($data['image_prompt'])) {
    require_once dirname(__DIR__) . '/providers/images.php';
    require_once dirname(__DIR__) . '/includes/image_utils.php';
    echo "Generating featured image...\n";
    $remoteUrl = generate_ai_image($data['image_prompt']);
    if ($remoteUrl) {
        echo "Saving image locally...\n";
        $featImg = save_remote_image($remoteUrl, 'uploads/blog');
    }
}

// 5. Save as Draft
$slug = slugify($data['title']);
db_run(
    "INSERT INTO blog_posts (title, slug, excerpt, content, meta_title, meta_desc, tags, featured_image, status, category)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', 'Wellness Trends')",
    [
        $data['title'],
        $slug,
        $data['excerpt'],
        $data['content'],
        $data['meta_title'],
        $data['meta_desc'],
        $data['tags'],
        $featImg
    ]
);

echo "Drafted new article: {$data['title']}\n";
