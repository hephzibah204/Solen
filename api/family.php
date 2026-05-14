<?php
/**
 * /api/family.php — Family Sharing API (Premium only)
 *
 * POST action=create_group  → create a family group
 * GET  action=my_group      → get current user's group
 * GET  action=join&code=XX  → join via invite code
 * POST action=remove_member → owner removes a member {user_id}
 * POST action=leave         → member leaves group
 * POST action=rename        → owner renames group {name}
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) { http_response_code(401); die(json_encode(['error' => 'Unauthorized'])); }

$user   = current_user();
$userId = (int)$user['id'];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$body   = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];

// Premium check helper
function require_premium(array $user): void {
    if (!in_array($user['plan'], ['premium', 'admin'])) {
        http_response_code(403);
        die(json_encode(['error' => 'Family sharing requires Solen Premium', 'upgrade' => '/pricing.php']));
    }
}

$maxMembers = (int)get_setting('family_max_members', '4');

switch ($action) {

    case 'create_group':
        require_premium($user);
        // Only one group per owner
        $existing = db_one("SELECT * FROM family_groups WHERE owner_id=?", [$userId]);
        if ($existing) { echo json_encode(['ok' => true, 'group' => $existing]); break; }

        $code  = strtoupper(bin2hex(random_bytes(4))); // 8-char code
        $name  = trim($body['name'] ?? '') ?: explode(' ', $user['name'])[0] . "'s Family";
        $gid   = db_run("INSERT INTO family_groups (owner_id, name, invite_code) VALUES (?,?,?)", [$userId, $name, $code]);
        // Add owner as a member with role=owner
        db_run("INSERT OR IGNORE INTO family_members (group_id, user_id, role) VALUES (?,?,'owner')", [$gid, $userId]);
        $group = db_one("SELECT * FROM family_groups WHERE id=?", [$gid]);
        echo json_encode(['ok' => true, 'group' => $group]);
        break;

    case 'my_group':
        $membership = db_one("SELECT fg.*, fm.role FROM family_groups fg JOIN family_members fm ON fm.group_id=fg.id WHERE fm.user_id=?", [$userId]);
        if (!$membership) { echo json_encode(['ok' => true, 'group' => null]); break; }

        $members = db_query(
            "SELECT u.id, u.name, u.email, fm.role, fm.joined_at,
                    cp.day_streak, cp.coach_name, cp.purpose
             FROM family_members fm
             JOIN users u ON u.id = fm.user_id
             LEFT JOIN coach_profiles cp ON cp.user_id = fm.user_id
             WHERE fm.group_id=?",
            [(int)$membership['id']]
        );
        echo json_encode(['ok' => true, 'group' => $membership, 'members' => $members, 'max' => $maxMembers]);
        break;

    case 'join':
        $code = strtoupper(trim($_GET['code'] ?? $body['code'] ?? ''));
        if (!$code) { echo json_encode(['error' => 'Invite code required']); break; }
        $group = db_one("SELECT * FROM family_groups WHERE invite_code=?", [$code]);
        if (!$group) { echo json_encode(['error' => 'Invalid invite code']); break; }

        // Check capacity
        $count = db_count('family_members', 'group_id=?', [(int)$group['id']]);
        if ($count >= $maxMembers) { echo json_encode(['error' => "Group is full (max {$maxMembers})"]); break; }

        // Check not already in any group
        $alreadyIn = db_one("SELECT id FROM family_members WHERE user_id=?", [$userId]);
        if ($alreadyIn) { echo json_encode(['error' => 'You are already in a family group']); break; }

        db_run("INSERT OR IGNORE INTO family_members (group_id, user_id, role) VALUES (?,'member')", [$group['id'], $userId]);
        echo json_encode(['ok' => true, 'group' => $group]);
        break;

    case 'remove_member':
        $ownerGroup = db_one("SELECT * FROM family_groups WHERE owner_id=?", [$userId]);
        if (!$ownerGroup) { echo json_encode(['error' => 'Not a group owner']); break; }
        $targetId = (int)($body['user_id'] ?? 0);
        if ($targetId === $userId) { echo json_encode(['error' => 'Cannot remove yourself as owner']); break; }
        db_run("DELETE FROM family_members WHERE group_id=? AND user_id=?", [(int)$ownerGroup['id'], $targetId]);
        echo json_encode(['ok' => true]);
        break;

    case 'leave':
        $mem = db_one("SELECT fm.*, fg.owner_id FROM family_members fm JOIN family_groups fg ON fg.id=fm.group_id WHERE fm.user_id=?", [$userId]);
        if (!$mem) { echo json_encode(['error' => 'Not in a group']); break; }
        if ((int)$mem['owner_id'] === $userId) { echo json_encode(['error' => 'Owner cannot leave — delete the group instead']); break; }
        db_run("DELETE FROM family_members WHERE user_id=? AND group_id=?", [$userId, (int)$mem['group_id']]);
        echo json_encode(['ok' => true]);
        break;

    case 'delete_group':
        $ownerGroup = db_one("SELECT * FROM family_groups WHERE owner_id=?", [$userId]);
        if (!$ownerGroup) { echo json_encode(['error' => 'Not a group owner']); break; }
        db_run("DELETE FROM family_groups WHERE id=?", [(int)$ownerGroup['id']]);
        echo json_encode(['ok' => true]);
        break;

    case 'rename':
        require_premium($user);
        $name = trim($body['name'] ?? '');
        if (!$name) { echo json_encode(['error' => 'Name required']); break; }
        db_run("UPDATE family_groups SET name=? WHERE owner_id=?", [$name, $userId]);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown action']);
}
