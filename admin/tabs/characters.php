<?php
// ---------------- إضافة شخصية ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_character'])) {
    $slug = trim($_POST['slug']) ?: strtolower(uniqid('char_'));
    $name = trim($_POST['name']);
    $title = trim($_POST['title']);
    $trait = trim($_POST['trait']);
    $color = $_POST['color'] ?: '#6C63FF';
    $move = $_POST['move'] ?: 'wiggle';
    $isPremium = isset($_POST['is_premium']) ? 1 : 0;
    $icons = array_filter(array_map('trim', explode(',', $_POST['icons'] ?? '')));
    if (!$icons) $icons = ['✨','⭐','🌟'];

    $imagePath = save_upload('image', character_media_dir('image', $slug), ['jpg','jpeg','png','webp','gif']);
    $imageRel = $imagePath ? 'assets/images/characters/' . preg_replace('/[^a-z0-9_-]/i','',$slug) . '/' . basename($imagePath) : null;
    $audioPath = save_upload('audio', character_media_dir('audio', $slug), ['mp3','wav','ogg']);
    $audioRel = $audioPath ? 'assets/audio/characters/' . preg_replace('/[^a-z0-9_-]/i','',$slug) . '/' . basename($audioPath) : null;

    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO characters (slug,name,title,trait,color,move_type,icons_json,is_premium,image_path,audio_path) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$slug,$name,$title,$trait,$color,$move,json_encode($icons, JSON_UNESCAPED_UNICODE),$isPremium,$imageRel,$audioRel]);
        $_SESSION['admin_flash'] = 'تمت إضافة الشخصية ✅';
    }
    header('Location: ?tab=characters'); exit;
}

// ---------------- تعديل شخصية موجودة (اسم/صورة/صوت/حركة/ثيم) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_character_id'])) {
    $id = (int)$_POST['edit_character_id'];
    $cur = $pdo->prepare("SELECT * FROM characters WHERE id = ?"); $cur->execute([$id]); $cur = $cur->fetch();
    if ($cur) {
        $name = trim($_POST['name']) ?: $cur['name'];
        $title = trim($_POST['title']);
        $trait = trim($_POST['trait']);
        $color = $_POST['color'] ?: $cur['color'];
        $move = $_POST['move'] ?: $cur['move_type'];
        $iconsRaw = array_filter(array_map('trim', explode(',', $_POST['icons'] ?? '')));
        $icons = $iconsRaw ?: json_decode_safe($cur['icons_json'], ['✨','⭐','🌟']);

        $imagePath = save_upload('image', character_media_dir('image', $cur['slug']), ['jpg','jpeg','png','webp','gif']);
        $imageRel = $imagePath ? 'assets/images/characters/' . preg_replace('/[^a-z0-9_-]/i','',$cur['slug']) . '/' . basename($imagePath) : $cur['image_path'];
        $audioPath = save_upload('audio', character_media_dir('audio', $cur['slug']), ['mp3','wav','ogg']);
        $audioRel = $audioPath ? 'assets/audio/characters/' . preg_replace('/[^a-z0-9_-]/i','',$cur['slug']) . '/' . basename($audioPath) : $cur['audio_path'];

        $pdo->prepare("UPDATE characters SET name=?, title=?, trait=?, color=?, move_type=?, icons_json=?, image_path=?, audio_path=? WHERE id=?")
            ->execute([$name, $title, $trait, $color, $move, json_encode($icons, JSON_UNESCAPED_UNICODE), $imageRel, $audioRel, $id]);
        $_SESSION['admin_flash'] = 'تم تعديل الشخصية ✅';
    }
    header('Location: ?tab=characters'); exit;
}

// ---------------- تعديل حالة premium أو حذف ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_premium_id'])) {
    $id = (int)$_POST['toggle_premium_id'];
    $pdo->prepare("UPDATE characters SET is_premium = 1 - is_premium WHERE id = ?")->execute([$id]);
    $_SESSION['admin_flash'] = 'تم تحديث حالة الشخصية';
    header('Location: ?tab=characters'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_character_id'])) {
    $pdo->prepare("DELETE FROM characters WHERE id = ?")->execute([(int)$_POST['delete_character_id']]);
    $_SESSION['admin_flash'] = 'تم حذف الشخصية';
    header('Location: ?tab=characters'); exit;
}

$characters = $pdo->query("SELECT * FROM characters ORDER BY sort_order, id")->fetchAll();
?>
<h2>إدارة الشخصيات</h2>
<p style="color:var(--ink-soft);max-width:640px;">ارفع صورة وملف صوتي حقيقيين لكل شخصية (يجب أن تملكوا حقوق استخدامهما). حدّد "شخصية مدفوعة" لتظهر مقفلة للمستخدمين المجانيين وتُفتح تلقائياً بعد تفعيل اشتراكهم.</p>

<div class="admin-form">
  <h3 style="margin-top:0;">إضافة شخصية جديدة</h3>
  <form method="POST" enctype="multipart/form-data">
    <div class="row">
      <input name="name" placeholder="اسم الشخصية" required>
      <input name="slug" placeholder="معرّف فريد (بالإنجليزية) مثال: nova">
      <input name="title" placeholder="الوصف (مثال: القط الذكي)">
      <input name="color" type="color" value="#6C63FF">
    </div>
    <div class="row">
      <input name="trait" placeholder="الصفة الأساسية">
      <input name="icons" placeholder="إيموجيات مفصولة بفواصل: 🐱,✨,⭐">
      <select name="move">
        <option value="wiggle">اهتزاز</option><option value="bounce">قفز</option>
        <option value="dash">اندفاع</option><option value="float">تحليق</option>
        <option value="hop">وثب</option><option value="stomp">دبدبة قوية</option>
      </select>
      <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_premium" style="width:auto;"> شخصية مدفوعة 🔒</label>
    </div>
    <div class="row">
      <div><label style="font-size:13px;font-weight:700;">صورة الشخصية</label><input type="file" name="image" accept="image/*"></div>
      <div><label style="font-size:13px;font-weight:700;">ملف صوتي (ترحيب)</label><input type="file" name="audio" accept="audio/*"></div>
    </div>
    <button type="submit" name="add_character" class="btn btn-primary">إضافة الشخصية</button>
  </form>
</div>

<div class="admin-card-list">
  <?php foreach ($characters as $c): ?>
    <div class="admin-item-card">
      <?php if ($c['image_path']): ?><img src="../<?php echo h($c['image_path']); ?>" style="width:56px;height:56px;border-radius:50%;object-fit:cover;">
      <?php else: ?><div style="font-size:36px;"><?php echo character_icons($c)[0] ?? '✨'; ?></div><?php endif; ?>
      <b><?php echo h($c['name']); ?></b>
      <p style="font-size:13px;color:var(--ink-soft);"><?php echo h($c['title']); ?></p>
      <span class="pill" style="<?php echo $c['is_premium'] ? 'color:#B8892A;background:#FFF1DD;' : 'color:var(--mint);background:#E3FBF8;'; ?>"><?php echo $c['is_premium'] ? '🔒 مدفوعة' : '🆓 مجانية'; ?></span>
      <?php if ($c['audio_path']): ?><p style="font-size:11px;color:var(--mint);">🔊 يوجد صوت مرفوع</p><?php endif; ?>
      <p style="font-size:11px;color:var(--ink-soft);">📁 مجلد الصور: assets/images/characters/<?php echo h($c['slug']); ?>/</p>
      <div class="admin-item-actions">
        <button type="button" class="btn btn-sm btn-ghost" onclick="document.getElementById('edit-<?php echo $c['id']; ?>').classList.toggle('hidden')">✏️ تعديل</button>
        <form method="POST"><input type="hidden" name="toggle_premium_id" value="<?php echo (int)$c['id']; ?>"><button class="btn btn-sm btn-ghost" type="submit">تبديل مجاني/مدفوع</button></form>
        <form method="POST" onsubmit="return confirm('حذف هذه الشخصية؟');"><input type="hidden" name="delete_character_id" value="<?php echo (int)$c['id']; ?>"><button class="btn btn-sm btn-ghost" type="submit">حذف</button></form>
      </div>
      <form method="POST" enctype="multipart/form-data" id="edit-<?php echo $c['id']; ?>" class="hidden" style="margin-top:12px;border-top:1px solid #eee;padding-top:12px;">
        <input type="hidden" name="edit_character_id" value="<?php echo (int)$c['id']; ?>">
        <div class="row">
          <input name="name" placeholder="الاسم" value="<?php echo h($c['name']); ?>">
          <input name="title" placeholder="الوصف" value="<?php echo h($c['title']); ?>">
        </div>
        <div class="row">
          <input name="trait" placeholder="الصفة" value="<?php echo h($c['trait']); ?>">
          <input name="color" type="color" value="<?php echo h($c['color']); ?>">
        </div>
        <div class="row">
          <select name="move">
            <?php foreach (['wiggle'=>'اهتزاز','bounce'=>'قفز','dash'=>'اندفاع','float'=>'تحليق','hop'=>'وثب','stomp'=>'دبدبة قوية'] as $mv=>$lbl): ?>
              <option value="<?php echo $mv; ?>" <?php echo $c['move_type']===$mv?'selected':''; ?>><?php echo $lbl; ?></option>
            <?php endforeach; ?>
          </select>
          <input name="icons" placeholder="إيموجيات (اتركها فارغة للإبقاء)">
        </div>
        <div class="row">
          <div><label style="font-size:12px;">صورة جديدة (اختياري)</label><input type="file" name="image" accept="image/*"></div>
          <div><label style="font-size:12px;">صوت جديد (اختياري)</label><input type="file" name="audio" accept="audio/*"></div>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">حفظ التعديلات</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
