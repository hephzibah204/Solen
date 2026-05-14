<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
require_admin();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── SAVE / UPDATE ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) { flash('error','Invalid request.'); redirect('/admin/blog.php'); }

    $title    = trim($_POST['title']     ?? '');
    $slug     = trim($_POST['slug']      ?? '') ?: slugify($title);
    $excerpt  = trim($_POST['excerpt']   ?? '');
    $content  = $_POST['content']        ?? '';
    $category = trim($_POST['category']  ?? 'General');
    $tags     = trim($_POST['tags']      ?? '');
    $status   = $_POST['status']         ?? 'draft';
    $metaT    = trim($_POST['meta_title'] ?? '');
    $metaD    = trim($_POST['meta_desc']  ?? '');
    $featImg  = trim($_POST['featured_image'] ?? '');
    $pubAt    = $status === 'published'
        ? (db_one("SELECT published_at FROM blog_posts WHERE id=?",[$id])['published_at'] ?? date('Y-m-d H:i:s'))
        : null;

    if ($action === 'edit' && $id) {
        db_run("UPDATE blog_posts SET title=?,slug=?,excerpt=?,content=?,category=?,tags=?,status=?,
                meta_title=?,meta_desc=?,featured_image=?,published_at=?,updated_at=datetime('now') WHERE id=?",
            [$title,$slug,$excerpt,$content,$category,$tags,$status,$metaT,$metaD,$featImg,$pubAt,$id]);
        flash('success','Post updated.');
    } else {
        $newId = db_run("INSERT INTO blog_posts (title,slug,excerpt,content,category,tags,status,meta_title,meta_desc,featured_image,author_id,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [$title,$slug,$excerpt,$content,$category,$tags,$status,$metaT,$metaD,$featImg,$_SESSION['user_id'],$pubAt]);
        flash('success','Post created.');
        redirect("/admin/blog.php?action=edit&id=$newId");
    }
    redirect('/admin/blog.php');
}

// ── DELETE ────────────────────────────────────────────────────────────────
if ($action === 'delete' && $id) {
    db_run("DELETE FROM blog_posts WHERE id=?", [$id]);
    flash('success','Post deleted.');
    redirect('/admin/blog.php');
}

// ── AI GENERATE (Phase 8) ─────────────────────────────────────────────────
if ($action === 'ai_generate') {
    require_once dirname(__DIR__) . '/providers/router.php';
    $topic = $_POST['topic'] ?? '';
    if (!$topic) { header('Content-Type: application/json'); echo json_encode(['error' => 'No topic provided']); exit; }

    $system = "You are a master SEO content strategist and wellness writer for Solen, an AI wellness coach.
               Your goal is to write a high-quality, deeply empathetic, and science-backed blog post that ranks well on Google.
               SEO Requirements:
               - Title: Catchy, contains the primary keyword, under 60 characters.
               - Slug: URL-friendly version of the title.
               - Excerpt: A hook that summarizes the post in 150-160 characters.
               - Content: Use semantic HTML (H2, H3). Include a strong introduction, actionable tips, and a conclusion with a soft call-to-action to use Solen.
               - Meta Title/Desc: Optimized for click-through rate.
               - Tags: 5-8 relevant wellness tags.
               Tone: Compassionate, authoritative, and human-like.
               Output valid JSON with keys: title, slug, excerpt, content, meta_title, meta_desc, tags.";
    $prompt = "Write a comprehensive blog post about: {$topic}";

    $messages = [['role' => 'user', 'content' => $prompt]];

    // Use sync (non-streaming) call — the streaming variant writes SSE to output and returns void
    $res = route_ai_request_sync($messages, $system, 3000, [
        'provider' => get_setting('ai_provider', 'claude'),
    ]);

    header('Content-Type: application/json');
    if (!$res) { 
        $lastError = error_get_last();
        echo json_encode(['error' => 'AI generation failed. This usually means the API key is invalid, leaked, or reached its limit. Check your settings.']); 
        exit; 
    }
    // Strip markdown code fences the model may wrap around JSON
    $clean = trim(preg_replace('/^```json|```$/m', '', trim($res)));
    echo $clean;
    exit;
}

if ($action === 'ai_social_pack') {
    require_once dirname(__DIR__) . '/providers/router.php';
    $id = (int)($_POST['id'] ?? 0);
    $post = db_one("SELECT * FROM blog_posts WHERE id=?", [$id]);
    if (!$post) { header('Content-Type: application/json'); echo json_encode(['error' => 'Post not found']); exit; }

    $system = "You are a social media growth expert. Generate a 'Social Pack' for this blog post.
               Output valid JSON with keys:
               - twitter: A high-engagement 1-3 tweet thread summary.
               - instagram: A punchy caption with relevant hashtags.
               - linkedin: A professional summary with 3 key takeaways.
               Tone: Catchy, curiosity-driven, and aligned with wellness.";
    $prompt = "Post Title: {$post['title']}\nContent: " . strip_tags($post['content']);

    $res = route_ai_request_sync([['role' => 'user', 'content' => $prompt]], $system, 1000, [
        'provider' => get_setting('ai_provider', 'claude'),
    ]);
    header('Content-Type: application/json');
    echo trim(preg_replace('/^```json|```$/m', '', trim($res)));
    exit;
}

// ── EDIT / NEW FORM ───────────────────────────────────────────────────────
if ($action === 'edit' || $action === 'new') {
    $post = $id ? db_one("SELECT * FROM blog_posts WHERE id=?",[$id]) : null;
    if ($action === 'edit' && !$post) { flash('error','Post not found.'); redirect('/admin/blog.php'); }
    $cats = ['Anxiety & Stress','Personal Growth','Emotional Wellness','Social Confidence','Mindfulness','Mental Health','Relationships','Self-Care'];

    admin_head($post ? 'Edit Post' : 'New Post'); admin_sidebar('blog');
    ?>
    <div class="main">
      <div class="topbar">
        <div class="topbar-title"><?= $post ? 'Edit Post' : 'New Post' ?></div>
        <div style="display:flex;gap:8px">
          <?php if ($post && $post['status']==='published'): ?>
            <a href="/blog/<?= h($post['slug']) ?>" target="_blank" class="btn btn-ghost btn-sm">View Live →</a>
          <?php endif ?>
          <button type="button" class="btn btn-primary btn-sm" id="aiWriterBtn" onclick="toggleAiWriter()">✨ AI Writer</button>
          <?php if ($post): ?>
            <button type="button" class="btn btn-ghost btn-sm" onclick="generateSocialPack()">📢 Social Pack</button>
          <?php endif ?>
          <a href="/admin/blog.php" class="btn btn-ghost btn-sm">← Back</a>
        </div>
      </div>

      <!-- AI Writer Modal (Phase 8) -->
      <div id="aiWriterPanel" style="display:none;background:var(--card);border-bottom:1px solid var(--border);padding:24px;animation:slideDown 0.3s ease">
        <div style="max-width:600px;margin:0 auto">
          <div class="card-title" style="margin-bottom:12px">AI Article Architect</div>
          <div style="display:flex;gap:10px">
            <input type="text" id="aiTopic" class="form-control" placeholder="e.g. 5 ways to recover from burnout at work" style="flex:1"/>
            <button type="button" class="btn btn-primary" onclick="generateAiPost()" id="aiGenBtn">Draft Article</button>
          </div>
          <div id="aiStatus" style="font-size:12px;color:var(--muted);margin-top:10px">Powered by <?= h(get_setting('ai_provider', 'Solen AI')) ?></div>
        </div>
      </div>

      <!-- Social Pack Modal -->
      <div id="socialPackModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:9999;display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(8px)">
        <div class="card" style="max-width:600px;width:100%;max-height:90vh;overflow:auto">
          <div class="card-header"><div class="card-title">Social Marketing Pack</div><button type="button" onclick="document.getElementById('socialPackModal').style.display='none'" style="background:none;border:none;color:#fff;cursor:pointer;font-size:20px">×</button></div>
          <div id="socialPackContent" style="display:flex;flex-direction:column;gap:16px">
             <div class="text-muted" style="text-align:center;padding:40px">AI is crafting your social posts…</div>
          </div>
        </div>
      </div>

      <style>
        @keyframes slideDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }
      </style>
      <div class="content">
        <?php admin_flash() ?>

        <form method="POST" id="postForm">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>"/>

          <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">

            <!-- Main editor -->
            <div style="display:flex;flex-direction:column;gap:16px">

              <div class="card">
                <div class="form-group">
                  <label>Post Title</label>
                  <input class="form-control" name="title" id="titleInput" placeholder="Enter a compelling title…" value="<?= h($post['title']??'') ?>" required
                         style="font-size:18px;padding:14px 16px"
                         oninput="autoSlug(this.value)"/>
                </div>
                <div class="form-group" style="margin-top:14px">
                  <label>URL Slug</label>
                  <div style="display:flex;gap:8px;align-items:center">
                    <span class="text-muted text-sm" style="white-space:nowrap;padding:10px 0">/blog/</span>
                    <input class="form-control" name="slug" id="slugInput" placeholder="url-slug-here" value="<?= h($post['slug']??'') ?>"/>
                  </div>
                </div>
              </div>

              <div class="card">
                <div class="card-header">
                  <div class="card-title">Content</div>
                  <div style="display:flex;gap:6px">
                    <button type="button" class="btn btn-ghost btn-sm" onclick="insertMd('**','**')"><b>B</b></button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="insertMd('*','*')"><i>I</i></button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="insertMd('\n## ','')">H2</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="insertMd('\n### ','')">H3</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="insertMd('\n> ','')">Quote</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="insertMd('\n- ','')">List</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="togglePreview()" id="previewBtn">Preview</button>
                  </div>
                </div>
                <textarea class="form-control" name="content" id="contentEditor"
                          style="min-height:420px;font-family:monospace;font-size:13px;line-height:1.7"
                          placeholder="Write your post content here. Supports HTML."><?= h($post['content']??'') ?></textarea>
                <div id="previewPane" style="display:none;min-height:420px;padding:16px;background:var(--bg2);border-radius:8px;font-size:15px;line-height:1.8;color:rgba(240,237,232,0.88)"></div>
              </div>

              <div class="card">
                <div class="card-title" style="margin-bottom:14px">Excerpt</div>
                <textarea class="form-control" name="excerpt" rows="3"
                          placeholder="Short summary shown on blog listing and social shares (160 chars ideal)…"><?= h($post['excerpt']??'') ?></textarea>
              </div>

              <!-- SEO -->
              <div class="card">
                <div class="card-header"><div class="card-title">SEO</div></div>
                <div style="display:flex;flex-direction:column;gap:14px">
                  <div class="form-group">
                    <label>Meta Title <span class="text-muted">(defaults to post title)</span></label>
                    <input class="form-control" name="meta_title" placeholder="SEO title…" value="<?= h($post['meta_title']??'') ?>"/>
                  </div>
                  <div class="form-group">
                    <label>Meta Description <span class="text-muted">(160 chars)</span></label>
                    <textarea class="form-control" name="meta_desc" rows="2" placeholder="SEO description…"><?= h($post['meta_desc']??'') ?></textarea>
                  </div>
                  <!-- Live SEO preview -->
                  <div style="background:var(--bg2);border-radius:10px;padding:16px;font-size:13px">
                    <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px">Google Preview</div>
                    <div id="seoTitle" style="color:#8ab4f8;font-size:18px;margin-bottom:4px"><?= h($post['meta_title']??$post['title']??'Post Title') ?></div>
                    <div style="color:#34a853;font-size:13px;margin-bottom:4px"><?= SITE_URL ?>/blog/<span id="seoSlug"><?= h($post['slug']??'post-slug') ?></span></div>
                    <div id="seoDesc" style="color:rgba(240,237,232,0.6)"><?= h($post['meta_desc']??$post['excerpt']??'Post description will appear here…') ?></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Sidebar -->
            <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:72px">

              <!-- Publish -->
              <div class="card">
                <div class="card-title" style="margin-bottom:14px">Publish</div>
                <div class="form-group" style="margin-bottom:14px">
                  <label>Status</label>
                  <select class="form-control" name="status" id="statusSelect">
                    <option value="draft"     <?= ($post['status']??'')==='draft'    ?'selected':'' ?>>Draft</option>
                    <option value="published" <?= ($post['status']??'')==='published'?'selected':'' ?>>Published</option>
                  </select>
                </div>
                <?php if ($post && $post['published_at']): ?>
                  <div style="font-size:12px;color:var(--muted);margin-bottom:14px">
                    Published: <?= format_date($post['published_at'],'M j, Y g:ia') ?>
                  </div>
                <?php endif ?>
                <button class="btn btn-primary" style="width:100%;justify-content:center">
                  <?= $post ? 'Update Post' : 'Create Post' ?>
                </button>
                <?php if ($post): ?>
                  <a href="/admin/blog.php?action=delete&id=<?= $id ?>"
                     onclick="return confirm('Delete this post permanently?')"
                     class="btn btn-danger btn-sm" style="width:100%;justify-content:center;margin-top:8px">Delete Post</a>
                <?php endif ?>
              </div>

              <!-- Category & Tags -->
              <div class="card">
                <div class="form-group" style="margin-bottom:14px">
                  <label>Category</label>
                  <select class="form-control" name="category">
                    <?php foreach ($cats as $c): ?>
                      <option value="<?= h($c) ?>" <?= ($post['category']??'')===$c?'selected':'' ?>><?= h($c) ?></option>
                    <?php endforeach ?>
                    <option value="General" <?= ($post['category']??'')==='General'?'selected':'' ?>>General</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Tags <span class="text-muted">(comma separated)</span></label>
                  <input class="form-control" name="tags" placeholder="anxiety, wellness, sleep" value="<?= h($post['tags']??'') ?>"/>
                </div>
              </div>

              <!-- Featured image -->
              <div class="card">
                <div class="form-group">
                  <label>Featured Image URL</label>
                  <input class="form-control" name="featured_image" id="imgInput"
                         placeholder="https://…" value="<?= h($post['featured_image']??'') ?>"
                         oninput="previewImg(this.value)"/>
                  <div id="imgPreview" style="margin-top:10px;border-radius:8px;overflow:hidden;display:<?= $post['featured_image']?'block':'none' ?>">
                    <?php if ($post['featured_image'] ?? ''): ?>
                      <img src="<?= h($post['featured_image']) ?>" style="width:100%;height:120px;object-fit:cover;border-radius:8px"/>
                    <?php endif ?>
                  </div>
                </div>
              </div>

              <!-- Post stats -->
              <?php if ($post): ?>
              <div class="card">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;text-align:center">
                  <div style="padding:12px;background:var(--bg2);border-radius:8px">
                    <div style="font-family:'Cormorant Garamond',serif;font-size:24px;color:var(--accent)"><?= number_format($post['views']) ?></div>
                    <div class="text-muted text-sm">Views</div>
                  </div>
                  <div style="padding:12px;background:var(--bg2);border-radius:8px">
                    <div style="font-family:'Cormorant Garamond',serif;font-size:24px;color:var(--accent)"><?= str_word_count(strip_tags($post['content']??'')) ?></div>
                    <div class="text-muted text-sm">Words</div>
                  </div>
                </div>
                <div style="font-size:12px;color:var(--muted);margin-top:10px">
                  Created: <?= format_date($post['created_at'],'M j, Y') ?><br>
                  Updated: <?= format_date($post['updated_at'],'M j, Y g:ia') ?>
                </div>
              </div>
              <?php endif ?>
            </div>
          </div>
        </form>
      </div>
    </div>

    <script>
    function autoSlug(val) {
      const s = val.toLowerCase().replace(/[^a-z0-9\s-]/g,'').replace(/[\s-]+/g,'-').trim().replace(/^-|-$/g,'');
      const slugEl = document.getElementById('slugInput');
      if (!slugEl.dataset.edited) slugEl.value = s;
      document.getElementById('seoSlug').textContent = s || 'post-slug';
      document.getElementById('seoTitle').textContent = document.querySelector('[name=meta_title]').value || val || 'Post Title';
    }
    document.getElementById('slugInput').addEventListener('input', function() { this.dataset.edited = '1'; document.getElementById('seoSlug').textContent = this.value; });
    document.querySelector('[name=meta_title]').addEventListener('input', function() { document.getElementById('seoTitle').textContent = this.value || document.getElementById('titleInput').value; });
    document.querySelector('[name=meta_desc]').addEventListener('input', function() { document.getElementById('seoDesc').textContent = this.value; });

    function insertMd(before, after) {
      const ta = document.getElementById('contentEditor');
      const s = ta.selectionStart, e = ta.selectionEnd;
      const sel = ta.value.substring(s,e);
      ta.value = ta.value.substring(0,s) + before + sel + after + ta.value.substring(e);
      ta.selectionStart = s + before.length;
      ta.selectionEnd   = s + before.length + sel.length;
      ta.focus();
    }

    let previewOn = false;
    function togglePreview() {
      previewOn = !previewOn;
      const ed = document.getElementById('contentEditor');
      const pv = document.getElementById('previewPane');
      const btn = document.getElementById('previewBtn');
      if (previewOn) {
        pv.innerHTML = ed.value; // In production: render markdown
        ed.style.display = 'none'; pv.style.display = 'block'; btn.textContent = 'Edit';
      } else {
        ed.style.display = 'block'; pv.style.display = 'none'; btn.textContent = 'Preview';
      }
    }

    function previewImg(url) {
      const div = document.getElementById('imgPreview');
      if (url) {
        div.style.display = 'block';
        div.innerHTML = `<img src="${url}" style="width:100%;height:120px;object-fit:cover;border-radius:8px" onerror="this.style.display='none'"/>`;
      } else { div.style.display = 'none'; }
    }

    function toggleAiWriter() {
        const p = document.getElementById('aiWriterPanel');
        p.style.display = p.style.display === 'none' ? 'block' : 'none';
    }

    async function generateAiPost() {
        const topic = document.getElementById('aiTopic').value;
        const btn   = document.getElementById('aiGenBtn');
        const status = document.getElementById('aiStatus');
        if (!topic) return alert("Enter a topic first");

        btn.disabled = true;
        btn.textContent = 'Generating…';
        status.textContent = 'AI is researching and writing your post…';

        try {
            const fd = new FormData();
            fd.append('topic', topic);
            const res = await fetch('/admin/blog.php?action=ai_generate', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.error) throw new Error(data.error);

            // Populate form
            document.getElementById('titleInput').value = data.title || '';
            document.getElementById('slugInput').value = data.slug || '';
            document.querySelector('[name=excerpt]').value = data.excerpt || '';
            document.getElementById('contentEditor').value = data.content || '';
            document.querySelector('[name=meta_title]').value = data.meta_title || '';
            document.querySelector('[name=meta_desc]').value = data.meta_desc || '';
            document.querySelector('[name=tags]').value = data.tags || '';
            
            autoSlug(data.title || '');
            toggleAiWriter();
            alert("Draft generated! Please review and publish.");
        } catch(e) {
            alert("AI Error: " + e.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Draft Article';
            status.textContent = 'Ready';
        }
    }

    async function generateSocialPack() {
        const modal = document.getElementById('socialPackModal');
        const content = document.getElementById('socialPackContent');
        modal.style.display = 'flex';
        content.innerHTML = '<div class="text-muted" style="text-align:center;padding:40px">Crafting your viral strategy… ✦</div>';

        try {
            const fd = new FormData();
            fd.append('id', '<?= $id ?>');
            const res = await fetch('/admin/blog.php?action=ai_social_pack', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.error) throw new Error(data.error);

            content.innerHTML = `
                <div class="form-group">
                    <label>Twitter / X Thread</label>
                    <textarea class="form-control" rows="4" readonly onclick="this.select(); document.execCommand('copy')">${data.twitter}</textarea>
                </div>
                <div class="form-group">
                    <label>Instagram / Threads</label>
                    <textarea class="form-control" rows="4" readonly onclick="this.select(); document.execCommand('copy')">${data.instagram}</textarea>
                </div>
                <div class="form-group">
                    <label>LinkedIn Professional</label>
                    <textarea class="form-control" rows="4" readonly onclick="this.select(); document.execCommand('copy')">${data.linkedin}</textarea>
                </div>
                <div class="text-muted text-sm">Click any box to select text.</div>
            `;
        } catch(e) {
            content.innerHTML = `<div class="alert alert-error">AI Error: ${e.message}</div>`;
        }
    }
    </script>
    </body></html>
    <?php exit;
}

// ── LIST ──────────────────────────────────────────────────────────────────
$page   = max(1,(int)($_GET['page']??1));
$search = trim($_GET['q']??'');
$status = $_GET['status']??'';
$cat    = $_GET['cat']??'';
$perPage = 15;

$where = '1'; $params = [];
if ($search) { $where .= " AND (title LIKE ? OR content LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status) { $where .= " AND status=?"; $params[] = $status; }
if ($cat)    { $where .= " AND category=?"; $params[] = $cat; }

$total = db_count('blog_posts', $where, $params);
$pg    = paginate($total, $page, $perPage);
$posts = db_query("SELECT p.*, u.name as author FROM blog_posts p LEFT JOIN users u ON p.author_id=u.id WHERE $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $pg['offset']]));

admin_head('Blog Posts'); admin_sidebar('blog');
?>
<div class="main">
  <div class="topbar">
    <div class="topbar-title">Blog Posts <span class="text-muted text-sm">(<?= number_format($total) ?>)</span></div>
    <a href="/admin/blog.php?action=new" class="btn btn-primary btn-sm">+ New Post</a>
  </div>
  <div class="content">
    <?php admin_flash() ?>

    <!-- Stats row -->
    <div style="display:flex;gap:12px;margin-bottom:18px;flex-wrap:wrap">
      <?php
      $pubCount   = db_count('blog_posts',"status='published'");
      $draftCount = db_count('blog_posts',"status='draft'");
      $totalViews = db_one("SELECT COALESCE(SUM(views),0) as v FROM blog_posts WHERE status='published'")['v'] ?? 0;
      ?>
      <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px 18px;font-size:13px">
        <span style="color:var(--accent);font-size:18px;font-family:'Cormorant Garamond',serif"><?= $pubCount ?></span>
        <span class="text-muted" style="margin-left:6px">Published</span>
      </div>
      <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px 18px;font-size:13px">
        <span style="color:var(--accent);font-size:18px;font-family:'Cormorant Garamond',serif"><?= $draftCount ?></span>
        <span class="text-muted" style="margin-left:6px">Drafts</span>
      </div>
      <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px 18px;font-size:13px">
        <span style="color:var(--accent);font-size:18px;font-family:'Cormorant Garamond',serif"><?= number_format($totalViews) ?></span>
        <span class="text-muted" style="margin-left:6px">Total Views</span>
      </div>
    </div>

    <!-- Filters -->
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
      <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap">
        <input class="search-bar" name="q" placeholder="Search posts…" value="<?= h($search) ?>"/>
        <select class="form-control" name="status" style="width:130px">
          <option value="">All statuses</option>
          <option value="published" <?= $status==='published'?'selected':'' ?>>Published</option>
          <option value="draft"     <?= $status==='draft'?'selected':'' ?>>Draft</option>
        </select>
        <button class="btn btn-ghost">Filter</button>
        <?php if ($search||$status||$cat): ?><a href="/admin/blog.php" class="btn btn-ghost">Clear</a><?php endif ?>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Author</th><th>Views</th><th>Updated</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($posts as $p): ?>
        <tr>
          <td style="max-width:300px">
            <strong><?= h($p['title']) ?></strong>
            <div class="text-muted text-sm">/blog/<?= h($p['slug']) ?></div>
          </td>
          <td class="text-muted"><?= h($p['category']) ?></td>
          <td><?= status_badge($p['status']) ?></td>
          <td class="text-muted"><?= h($p['author'] ?? '—') ?></td>
          <td class="text-muted"><?= number_format($p['views']) ?></td>
          <td class="text-muted text-sm"><?= time_ago($p['updated_at']) ?></td>
          <td>
            <div style="display:flex;gap:6px">
              <a href="/admin/blog.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
              <?php if ($p['status']==='published'): ?>
                <a href="/blog/<?= h($p['slug']) ?>" target="_blank" class="btn btn-ghost btn-sm">↗</a>
              <?php endif ?>
            </div>
          </td>
        </tr>
        <?php endforeach ?>
        <?php if (!$posts): ?>
          <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted)">No posts yet. <a href="/admin/blog.php?action=new" style="color:var(--accent)">Create your first →</a></td></tr>
        <?php endif ?>
        </tbody>
      </table>
    </div>

    <?php if ($pg['pages'] > 1): ?>
    <div class="pagination">
      <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
        <a class="<?= $i===$page?'active':'' ?>" href="?page=<?= $i ?><?= $search?"&q=".urlencode($search):'' ?><?= $status?"&status=$status":'' ?>"><?= $i ?></a>
      <?php endfor ?>
    </div>
    <?php endif ?>
  </div>
</div>
</body></html>
