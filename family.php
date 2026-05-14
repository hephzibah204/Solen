<?php
/**
 * /family.php — Family Sharing UI
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$user   = current_user();
$userId = (int)$user['id'];
$isPremium = in_array($user['plan'], ['premium', 'admin']);
$site   = get_setting('site_name', 'Solen');

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Family Sharing — <?= h($site) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet"/>
<style>
:root{
  --bg:#07070f;--surface:#11111d;--surface2:#181829;
  --text:#f2ede8;--muted:rgba(242,237,232,0.5);
  --accent:#c5a572;--accent2:rgba(197,165,114,0.1);
  --border:rgba(255,255,255,0.08);--green:#34d399;
}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:'Outfit',sans-serif;line-height:1.6;padding-bottom:100px}
.container{max-width:600px;margin:0 auto;padding:24px}

.header{margin-bottom:32px;text-align:center}
.header h1{font-family:'Playfair Display',serif;font-size:32px;font-weight:400;margin-bottom:8px}
.header p{color:var(--muted);font-size:15px}

.card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:24px;margin-bottom:24px;position:relative;overflow:hidden}
.card-title{font-size:18px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:10px}

.member-item{display:flex;align-items:center;gap:16px;padding:16px 0;border-bottom:1px solid var(--border)}
.member-item:last-child{border-bottom:none}
.member-avatar{width:44px;height:44px;border-radius:50%;background:var(--surface2);display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:600;color:var(--accent);border:1px solid var(--border)}
.member-info{flex:1}
.member-name{font-size:15px;font-weight:500;display:flex;align-items:center;gap:8px}
.member-role{font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted);background:var(--surface2);padding:2px 8px;border-radius:4px}
.member-streak{font-size:12px;color:var(--accent);margin-top:2px}

.invite-box{background:var(--surface2);border:1px dashed var(--accent);border-radius:12px;padding:20px;text-align:center;margin-top:16px}
.invite-code{font-family:monospace;font-size:24px;letter-spacing:4px;color:var(--accent);margin:12px 0;font-weight:700}
.copy-btn{background:var(--accent2);border:1px solid var(--accent);color:var(--accent);padding:6px 16px;border-radius:50px;font-size:12px;font-weight:600;cursor:pointer;transition:all 0.2s}
.copy-btn:hover{background:var(--accent);color:#1a1008}

.btn{width:100%;padding:14px;border-radius:12px;border:none;font-family:'Outfit',sans-serif;font-weight:600;font-size:15px;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;justify-content:center;gap:10px}
.btn-primary{background:var(--accent);color:#1a1008}
.btn-primary:hover{background:#d4ae82;transform:translateY(-1px)}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text)}
.btn-outline:hover{background:rgba(255,255,255,0.05)}
.btn-danger{background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.2)}

.empty-state{text-align:center;padding:40px 20px}
.empty-icon{font-size:48px;margin-bottom:16px;opacity:0.5}

.premium-locked{text-align:center;padding:60px 24px}
.lock-icon{font-size:64px;margin-bottom:24px}
.lock-title{font-family:'Playfair Display',serif;font-size:28px;margin-bottom:12px}
.lock-text{color:var(--muted);margin-bottom:32px;max-width:400px;margin-left:auto;margin-right:auto}

.toast{position:fixed;bottom:100px;left:50%;transform:translateX(-50%);background:#1a1a1a;color:#fff;padding:12px 24px;border-radius:50px;font-size:14px;z-index:1000;box-shadow:0 10px 30px rgba(0,0,0,0.5);opacity:0;pointer-events:none;transition:all 0.3s}
.toast.show{opacity:1;transform:translateX(-50%) translateY(-10px)}

#loading{position:fixed;inset:0;background:var(--bg);display:flex;align-items:center;justify-content:center;z-index:9999}
.loader{width:40px;height:40px;border:3px solid var(--surface2);border-top-color:var(--accent);border-radius:50%;animation:spin 0.8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

.nav-bottom{position:fixed;bottom:0;left:0;right:0;background:rgba(7,7,15,0.85);backdrop-filter:blur(20px);border-top:1px solid var(--border);display:flex;justify-content:space-around;padding:12px 0;z-index:100}
.nav-item{text-align:center;color:var(--muted);text-decoration:none;font-size:11px;flex:1}
.nav-item.active{color:var(--accent)}
.nav-icon{font-size:20px;display:block;margin-bottom:4px}
</style>
</head>
<body>

<div id="loading"><div class="loader"></div></div>

<div class="container">
    <div class="header">
        <h1>Family Sharing</h1>
        <p>Wellness is better together.</p>
    </div>

    <?php if (!$isPremium): ?>
    <div class="card premium-locked">
        <div class="lock-icon">💎</div>
        <div class="lock-title">Solen Premium</div>
        <p class="lock-text">Family sharing is a premium feature. Share your journey with up to 4 family members and keep everyone on track together.</p>
        <a href="/pricing.php" class="btn btn-primary">Upgrade to Premium</a>
    </div>
    <?php else: ?>
    <div id="family-content">
        <!-- JS will populate this -->
    </div>
    <?php endif ?>
</div>

<div class="nav-bottom">
    <a href="/app.php" class="nav-item"><span class="nav-icon">💬</span>Coach</a>
    <a href="/rituals.php" class="nav-item"><span class="nav-icon">✨</span>Rituals</a>
    <a href="/dashboard.php" class="nav-item"><span class="nav-icon">📈</span>Growth</a>
    <a href="/family.php" class="nav-item active"><span class="nav-icon">🫂</span>Family</a>
</div>

<div class="toast" id="toast"></div>

<script>
let state = {
    group: null,
    members: [],
    loading: true
};

async function loadFamily() {
    try {
        const res = await fetch('/api/family.php?action=my_group');
        const data = await res.json();
        state.group = data.group;
        state.members = data.members || [];
        state.max = data.max || 4;
        render();
    } catch (e) {
        showToast("Error loading family data.");
    } finally {
        document.getElementById('loading').style.display = 'none';
    }
}

async function createGroup() {
    try {
        const name = prompt("Enter a name for your family group:", "My Family");
        if (!name) return;
        const res = await fetch('/api/family.php?action=create_group', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ name })
        });
        const data = await res.json();
        if (data.ok) {
            showToast("Group created!");
            loadFamily();
        } else {
            showToast(data.error || "Error creating group.");
        }
    } catch (e) {
        showToast("Error.");
    }
}

async function joinGroup() {
    const code = prompt("Enter invite code:");
    if (!code) return;
    try {
        const res = await fetch('/api/family.php?action=join', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ code })
        });
        const data = await res.json();
        if (data.ok) {
            showToast("Joined group!");
            loadFamily();
        } else {
            showToast(data.error || "Invalid code.");
        }
    } catch (e) {
        showToast("Error.");
    }
}

async function removeMember(userId) {
    if (!confirm("Are you sure you want to remove this member?")) return;
    try {
        const res = await fetch('/api/family.php?action=remove_member', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ user_id: userId })
        });
        const data = await res.json();
        if (data.ok) {
            showToast("Member removed.");
            loadFamily();
        }
    } catch (e) {
        showToast("Error.");
    }
}

async function leaveGroup() {
    if (!confirm("Are you sure you want to leave this group?")) return;
    try {
        const res = await fetch('/api/family.php?action=leave', { method: 'POST' });
        const data = await res.json();
        if (data.ok) {
            showToast("You left the group.");
            loadFamily();
        }
    } catch (e) {
        showToast("Error.");
    }
}

function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function copyCode() {
    const code = state.group.invite_code;
    navigator.clipboard.writeText(code);
    showToast("Code copied to clipboard!");
}

function render() {
    const container = document.getElementById('family-content');
    if (!container) return;

    if (!state.group) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">🫂</div>
                <h3>No Family Group</h3>
                <p style="color:var(--muted);margin-bottom:24px">You aren't in a family group yet.</p>
                <button class="btn btn-primary" onclick="createGroup()" style="margin-bottom:12px">Create a Group</button>
                <button class="btn btn-outline" onclick="joinGroup()">Join with Code</button>
            </div>
        `;
        return;
    }

    const isOwner = state.group.role === 'owner';

    let membersHtml = state.members.map(m => `
        <div class="member-item">
            <div class="member-avatar">${m.name.charAt(0)}</div>
            <div class="member-info">
                <div class="member-name">
                    ${m.name} ${m.role === 'owner' ? '<span class="member-role">Owner</span>' : ''}
                </div>
                <div class="member-streak">🔥 ${m.day_streak || 0} day streak</div>
            </div>
            ${isOwner && m.role !== 'owner' ? `<button onclick="removeMember(${m.id})" style="background:none;border:none;color:#ef4444;font-size:12px;cursor:pointer">Remove</button>` : ''}
        </div>
    `).join('');

    container.innerHTML = `
        <div class="card">
            <div class="card-title">👨‍👩‍👧‍👦 ${state.group.name}</div>
            <div class="member-list">
                ${membersHtml}
            </div>
            ${state.members.length < state.max && isOwner ? `
                <div class="invite-box">
                    <div style="font-size:12px;color:var(--muted)">Invite family member</div>
                    <div class="invite-code">${state.group.invite_code}</div>
                    <button class="copy-btn" onclick="copyCode()">Copy Code</button>
                </div>
            ` : ''}
        </div>

        ${!isOwner ? `
            <button class="btn btn-danger" onclick="leaveGroup()">Leave Group</button>
        ` : `
            <button class="btn btn-outline" onclick="createGroup()">Manage Settings</button>
        `}
    `;
}

<?php if ($isPremium): ?>
loadFamily();
<?php else: ?>
document.getElementById('loading').style.display = 'none';
<?php endif ?>
</script>
</body>
</html>
