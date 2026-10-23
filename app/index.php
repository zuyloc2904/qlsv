<?php
declare(strict_types=1);
session_start();

$host = getenv('DB_HOST') ?: 'db';
$name = getenv('DB_NAME') ?: 'qlsv';
$user = getenv('DB_USER') ?: 'qlsv_user';
$pass = getenv('DB_PASSWORD') ?: 'qlsv_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Không kết nối được MySQL. Kiểm tra service DB và biến DB_* trong docker-compose.yml.');
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function done(string $msg): never { $_SESSION['msg']=$msg; header('Location: /'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403); exit('CSRF token không hợp lệ.');
    }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'class') {
            $id=(int)($_POST['id']??0); $v=trim($_POST['class_name']??'');
            if ($v==='') done('Tên lớp không được để trống.');
            if ($id) { $s=$pdo->prepare('UPDATE classes SET class_name=? WHERE id=?'); $s->execute([$v,$id]); }
            else { $s=$pdo->prepare('INSERT INTO classes(class_name) VALUES(?)'); $s->execute([$v]); }
            done('Đã lưu lớp học.');
        }
        if ($action === 'delclass') {
            $s=$pdo->prepare('DELETE FROM classes WHERE id=?'); $s->execute([(int)$_POST['id']]); done('Đã xóa lớp.');
        }
        if ($action === 'student') {
            $id=(int)($_POST['id']??0); $code=trim($_POST['student_code']??'');
            $full=trim($_POST['full_name']??''); $email=trim($_POST['email']??'');
            $class=(int)($_POST['class_id']??0) ?: null;
            if ($code==='' || $full==='') done('Mã sinh viên và họ tên là bắt buộc.');
            if ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) done('Email không hợp lệ.');
            if ($id) { $s=$pdo->prepare('UPDATE students SET student_code=?,full_name=?,email=?,class_id=? WHERE id=?'); $s->execute([$code,$full,$email?:null,$class,$id]); }
            else { $s=$pdo->prepare('INSERT INTO students(student_code,full_name,email,class_id) VALUES(?,?,?,?)'); $s->execute([$code,$full,$email?:null,$class]); }
            done('Đã lưu sinh viên.');
        }
        if ($action === 'delstudent') {
            $s=$pdo->prepare('DELETE FROM students WHERE id=?'); $s->execute([(int)$_POST['id']]); done('Đã xóa sinh viên và điểm liên quan.');
        }
        if ($action === 'grade') {
            $sid=(int)($_POST['student_id']??0); $subject=trim($_POST['subject']??''); $score=filter_var($_POST['score']??null,FILTER_VALIDATE_FLOAT);
            if (!$sid || $subject==='' || $score===false || $score<0 || $score>10) done('Nhập sinh viên, môn học và điểm từ 0 đến 10.');
            $s=$pdo->prepare('INSERT INTO grades(student_id,subject,score) VALUES(?,?,?)'); $s->execute([$sid,$subject,$score]); done('Đã lưu điểm.');
        }
        if ($action === 'delgrade') {
            $s=$pdo->prepare('DELETE FROM grades WHERE id=?'); $s->execute([(int)$_POST['id']]); done('Đã xóa điểm.');
        }
    } catch (PDOException $e) {
        error_log('QLSV DB error: '.$e->getMessage());
        done($e->getCode()==='23000' ? 'Mã sinh viên hoặc tên lớp bị trùng.' : 'Lỗi dữ liệu. Hãy kiểm tra log ứng dụng.');
    }
}
$edit=null;
if (isset($_GET['edit'])) { $s=$pdo->prepare('SELECT * FROM students WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit=$s->fetch() ?: null; }
$editClass=null;
if (isset($_GET['editclass'])) { $s=$pdo->prepare('SELECT * FROM classes WHERE id=?'); $s->execute([(int)$_GET['editclass']]); $editClass=$s->fetch() ?: null; }
$classes=$pdo->query('SELECT * FROM classes ORDER BY class_name')->fetchAll();
$students=$pdo->query('SELECT s.*,c.class_name FROM students s LEFT JOIN classes c ON c.id=s.class_id ORDER BY s.id DESC')->fetchAll();
$grades=$pdo->query('SELECT g.*,s.student_code,s.full_name FROM grades g JOIN students s ON s.id=g.student_id ORDER BY g.id DESC')->fetchAll();
$msg=$_SESSION['msg']??''; unset($_SESSION['msg']);
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Quản lý sinh viên</title>
<style>
body{margin:0;background:#f3f6fb;color:#172033;font:15px system-ui}header{background:#173b72;color:white;padding:24px max(18px,calc((100% - 1100px)/2))}header h1{margin:0}main{max-width:1100px;margin:24px auto;padding:0 16px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:16px}.box{background:white;border-radius:12px;padding:18px;margin-bottom:16px;box-shadow:0 4px 18px #152e5010}h2{font-size:19px;margin-top:0}label{display:block;font-weight:600;margin-top:9px}input,select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ccd5e3;border-radius:7px;margin:5px 0 8px}button,.btn{display:inline-block;border:0;border-radius:7px;padding:9px 12px;background:#2457a7;color:white;text-decoration:none;cursor:pointer}.danger{background:#b42332}.light{background:#e7edf7;color:#173b72}.msg{padding:12px;background:#dcfce7;border-radius:8px;margin-bottom:15px}.scroll{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:550px}td,th{text-align:left;padding:9px;border-bottom:1px solid #e8edf4}th{background:#f7f9fc}.actions{display:flex;gap:6px;align-items:center}.inline{display:inline}
</style></head><body><header><h1>Quản lý sinh viên</h1><div>Quản lý lớp học, hồ sơ sinh viên và điểm số</div></header><main>
<?php if($msg): ?><div class="msg"><?=h($msg)?></div><?php endif; ?>
<div class="grid">
<section class="box"><h2><?= $editClass?'Sửa lớp':'Thêm lớp' ?></h2><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="class"><input type="hidden" name="id" value="<?=h($editClass['id']??0)?>"><label>Tên lớp</label><input name="class_name" required maxlength="100" value="<?=h($editClass['class_name']??'')?>" placeholder="CNTT K18A"><button>Lưu lớp</button><?php if($editClass):?> <a class="btn light" href="/">Hủy</a><?php endif;?></form></section>
<section class="box"><h2><?= $edit?'Sửa sinh viên':'Thêm sinh viên' ?></h2><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="student"><input type="hidden" name="id" value="<?=h($edit['id']??0)?>"><label>Mã sinh viên *</label><input name="student_code" required value="<?=h($edit['student_code']??'')?>" placeholder="SV001"><label>Họ tên *</label><input name="full_name" required value="<?=h($edit['full_name']??'')?>" placeholder="Nguyễn Văn A"><label>Email</label><input type="email" name="email" value="<?=h($edit['email']??'')?>"><label>Lớp</label><select name="class_id"><option value="0">-- Chưa phân lớp --</option><?php foreach($classes as $c):?><option value="<?=$c['id']?>" <?=((int)($edit['class_id']??0)===(int)$c['id'])?'selected':''?>><?=h($c['class_name'])?></option><?php endforeach;?></select><button>Lưu sinh viên</button><?php if($edit):?> <a class="btn light" href="/">Hủy</a><?php endif;?></form></section>
<section class="box"><h2>Nhập điểm</h2><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="grade"><label>Sinh viên</label><select name="student_id" required><option value="">-- Chọn sinh viên --</option><?php foreach($students as $s):?><option value="<?=$s['id']?>"><?=h($s['student_code'].' - '.$s['full_name'])?></option><?php endforeach;?></select><label>Môn học</label><input name="subject" required placeholder="Cơ sở dữ liệu"><label>Điểm (0–10)</label><input type="number" name="score" min="0" max="10" step="0.1" required><button>Lưu điểm</button></form></section>
</div>
<section class="box"><h2>Danh sách lớp học</h2><div class="scroll"><table><tr><th>ID</th><th>Tên lớp</th><th>Thao tác</th></tr><?php foreach($classes as $c):?><tr><td><?=$c['id']?></td><td><?=h($c['class_name'])?></td><td><div class="actions"><a class="btn light" href="?editclass=<?=$c['id']?>">Sửa</a><form class="inline" method="post" onsubmit="return confirm('Xóa lớp này?')"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="delclass"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="danger">Xóa</button></form></div></td></tr><?php endforeach;?></table></div></section>
<section class="box"><h2>Danh sách sinh viên</h2><div class="scroll"><table><tr><th>Mã SV</th><th>Họ tên</th><th>Email</th><th>Lớp</th><th>Thao tác</th></tr><?php foreach($students as $s):?><tr><td><?=h($s['student_code'])?></td><td><?=h($s['full_name'])?></td><td><?=h($s['email'])?></td><td><?=h($s['class_name']??'Chưa phân lớp')?></td><td><div class="actions"><a class="btn light" href="?edit=<?=$s['id']?>">Sửa</a><form class="inline" method="post" onsubmit="return confirm('Xóa sinh viên và điểm liên quan?')"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="delstudent"><input type="hidden" name="id" value="<?=$s['id']?>"><button class="danger">Xóa</button></form></div></td></tr><?php endforeach;?></table></div></section>
<section class="box"><h2>Bảng điểm</h2><div class="scroll"><table><tr><th>Mã SV</th><th>Họ tên</th><th>Môn học</th><th>Điểm</th><th>Thao tác</th></tr><?php foreach($grades as $g):?><tr><td><?=h($g['student_code'])?></td><td><?=h($g['full_name'])?></td><td><?=h($g['subject'])?></td><td><?=h($g['score'])?></td><td><form method="post" onsubmit="return confirm('Xóa điểm?')"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="delgrade"><input type="hidden" name="id" value="<?=$g['id']?>"><button class="danger">Xóa</button></form></td></tr><?php endforeach;?></table></div></section>
<p>Ứng dụng mẫu học tập; trước khi triển khai thực tế cần bổ sung đăng nhập và phân quyền.</p></main></body></html>
