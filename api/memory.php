<?php
/**
 * /api/memory.php — Solen Memory Control API (Phase 3)
 *
 * Provides user-facing memory management endpoints (Phase 7 foundation):
 *   GET  ?action=list&page=1         → paginated memory episodes
 *   GET  ?action=patterns            → emotional patterns
 *   GET  ?action=export              → full memory export (JSON)
 *   POST ?action=delete              → delete episode {episode_id}
 *   POST ?action=search              → semantic search {query}
 *   POST ?action=resolve_pattern     → mark pattern as resolved {pattern}
 *
 * All actions require authentication.
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/memory.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (get_setting('memory_enabled', '1') !== '1') {
    http_response_code(403);
    echo json_encode(['error' => 'Memory system is not enabled']);
    exit;
}

$user   = current_user();
$userId = (int)$user['id'];
$action = $_GET['action'] ?? 'list';

header('Content-Type: application/json');

// ── GET: List memories ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $filter  = preg_replace('/[^a-z_]/', '', $_GET['type'] ?? '');
    $episodes = memory_get_user_episodes($userId, $page, 20);

    // Deserialise tags
    foreach ($episodes as &$ep) {
        $ep['tags'] = json_decode($ep['tags'] ?? '[]', true);
        unset($ep['embedding']); // never send embeddings to client
    }

    $total = db_count('memory_episodes', 'user_id=?', [$userId]);
    echo json_encode([
        'episodes'   => $episodes,
        'total'      => $total,
        'page'       => $page,
        'per_page'   => 20,
        'has_more'   => ($page * 20) < $total,
    ]);
    exit;
}

// ── GET: Emotional patterns ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'patterns') {
    $patterns = memory_get_emotional_patterns($userId);
    echo json_encode(['patterns' => $patterns]);
    exit;
}

// ── GET: Export ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'export') {
    $export = memory_export($userId);
    // Serve as downloadable JSON
    header('Content-Disposition: attachment; filename="solen-memories-' . date('Y-m-d') . '.json"');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Require POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── POST: Delete episode ──────────────────────────────────────────────────
if ($action === 'delete') {
    $episodeId = (int)($body['episode_id'] ?? 0);
    if (!$episodeId) {
        http_response_code(400);
        echo json_encode(['error' => 'episode_id required']);
        exit;
    }
    $ok = memory_delete_episode($userId, $episodeId);
    echo json_encode(['success' => $ok]);
    exit;
}

// ── POST: Semantic search ─────────────────────────────────────────────────
if ($action === 'search') {
    $query = trim($body['query'] ?? '');
    if (strlen($query) < 3) {
        http_response_code(400);
        echo json_encode(['error' => 'Query too short']);
        exit;
    }
    $results = memory_search_semantic($userId, $query, 10);
    foreach ($results as &$r) {
        $r['tags'] = json_decode($r['tags'] ?? '[]', true);
        unset($r['embedding']);
    }
    echo json_encode(['results' => $results, 'query' => $query]);
    exit;
}

// ── POST: Resolve pattern ─────────────────────────────────────────────────
if ($action === 'resolve_pattern') {
    $pattern = preg_replace('/[^a-z_]/', '', $body['pattern'] ?? '');
    if (!$pattern) {
        http_response_code(400);
        echo json_encode(['error' => 'pattern required']);
        exit;
    }
    memory_resolve_pattern($userId, $pattern);
    echo json_encode(['success' => true, 'pattern' => $pattern]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
