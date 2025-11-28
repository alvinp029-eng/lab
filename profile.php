<?php
// profile.php (VERSION PERBAIKAN - EDIT PROFILE & GANTI PASSWORD)
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

// Fungsi untuk membuat avatar dengan inisial
function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
            if (strlen($initials) >= 2) break;
        }
    }
    
    return $initials ?: 'U';
}

// Ambil data user dari database
 $user = null;
try {
    $sql = "SELECT id, name, email, nip_nim, phone, department, role, avatar, password, is_active FROM users WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
} catch (PDOException $e) {
    die("Terjadi kesalahan saat memuat data pengguna.");
}

 $message = '';
 $message_type = '';

// Proses update profile
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Hanya proses jika ini adalah aksi "save_profile"
    if (isset($_POST['action']) && $_POST['action'] === 'save_profile') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $nip_nim = trim($_POST['nip_nim']);
        $phone = trim($_POST['phone']);
        $department = trim($_POST['department']);
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validasi dasar
        if (empty($name) || empty($email)) {
            $message = "Nama dan email harus diisi.";
            $message_type = "danger";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Format email tidak valid.";
            $message_type = "danger";
        } else {
            try {
                // Mulai transaksi untuk keamanan
                $pdo->beginTransaction();

                // Cek apakah email baru sudah digunakan oleh user lain
                $sql_check_email = "SELECT id FROM users WHERE email = :email AND id != :id";
                $stmt_check_email = $pdo->prepare($sql_check_email);
                $stmt_check_email->bindParam(':email', $email);
                $stmt_check_email->bindParam(':id', $user['id']);
                $stmt_check_email->execute();
                if ($stmt_check_email->fetch()) {
                    throw new Exception("Email sudah digunakan oleh pengguna lain.");
                }

                // Array untuk data yang akan diupdate
                $update_fields = [
                    'name' => $name,
                    'email' => $email,
                    'nip_nim' => $nip_nim,
                    'phone' => $phone,
                    'department' => $department
                ];

                // Logika perubahan password
                $password_changed = false;
                if (!empty($new_password)) {
                    if (empty($current_password)) {
                        throw new Exception("Password lama harus diisi untuk mengubah password.");
                    }
                    if (!password_verify($current_password, $user['password'])) {
                        throw new Exception("Password lama yang Anda masukkan salah.");
                    }
                    if (strlen($new_password) < 8) {
                        throw new Exception("Password baru minimal 8 karakter.");
                    }
                    if ($new_password !== $confirm_password) {
                        throw new Exception("Password baru dan konfirmasi password tidak cocok.");
                    }
                    // Hash password baru dan tambahkan ke array update
                    $update_fields['password'] = password_hash($new_password, PASSWORD_DEFAULT);
                    $password_changed = true;
                }

                // Handle upload avatar
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == UPLOAD_ERR_OK) {
                    // Validasi file
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $file_info = finfo_open(FILEINFO_MIME_TYPE);
                    $file_type = finfo_file($file_info, $_FILES['avatar']['tmp_name']);
                    finfo_close($file_info);
                    
                    if (!in_array($file_type, $allowed_types)) {
                        throw new Exception("Hanya file gambar (JPG, PNG, GIF) yang diperbolehkan.");
                    }
                    
                    if ($_FILES['avatar']['size'] > 2097152) { // 2MB
                        throw new Exception("Ukuran file maksimal 2MB.");
                    }
                    
                    $upload_dir = 'uploads/avatars/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                    $file_name = 'avatar_' . $user['id'] . '_' . time() . '.' . $file_extension;
                    $target_file = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_file)) {
                        // Hapus avatar lama jika ada dan bukan default
                        if ($user['avatar'] && $user['avatar'] != 'default-avatar.png' && file_exists($upload_dir . $user['avatar'])) {
                            unlink($upload_dir . $user['avatar']);
                        }
                        $update_fields['avatar'] = $file_name;
                    } else {
                        throw new Exception("Gagal mengupload avatar.");
                    }
                }

                // Buat query SET secara dinamis
                $set_clause_parts = [];
                foreach ($update_fields as $key => $value) {
                    $set_clause_parts[] = "`$key` = :$key";
                }
                $set_clause = implode(', ', $set_clause_parts);
                
                $sql = "UPDATE users SET $set_clause, updated_at = NOW() WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                
                // Bind semua nilai termasuk ID
                $stmt->bindValue(':id', $user['id']);
                foreach ($update_fields as $key => $value) {
                    $stmt->bindValue(":$key", $value);
                }
                
                $stmt->execute();
                $pdo->commit();

                // Update session data
                $_SESSION['user_name'] = $name;

                // Update user data for display
                $user['name'] = $name;
                $user['email'] = $email;
                $user['nip_nim'] = $nip_nim;
                $user['phone'] = $phone;
                $user['department'] = $department;
                if (isset($update_fields['avatar'])) {
                    $user['avatar'] = $update_fields['avatar'];
                }

                $message = "Profil berhasil diperbarui." . ($password_changed ? " Password telah diubah." : "");
                $message_type = "success";

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Profile update error: " . $e->getMessage());
                $message = $e->getMessage();
                $message_type = "danger";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .profile-avatar-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .profile-avatar-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--primary-color);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }
        .profile-avatar-img:hover {
            transform: scale(1.05);
        }
        .form-section-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        /* Style untuk mode edit */
        .form-control:disabled, .form-select:disabled {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            transition: all 0.3s;
        }
        .strength-weak { background-color: #dc3545; width: 33%; }
        .strength-medium { background-color: #ffc107; width: 66%; }
        .strength-strong { background-color: #28a745; width: 100%; }
    </style>
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>

    <!-- Sidebar Dinamis Berdasarkan Role -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>SILABKOM</h3>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="nav-link">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            
            <?php if (in_array($user['role'], ['kepala_lab', 'pj_lab'])): ?>
                <!-- Menu Kepala Laboratorium dengan Submenu -->
                <div class="menu-item" id="labManagementMenu">
                    <a href="#" class="nav-link" onclick="toggleSubmenu('labManagementMenu'); return false;">
                        <div>
                            <i class="bi bi-building"></i>
                            <span>Manajemen Lab</span>
                        </div>
                        <i class="bi bi-chevron-down dropdown-icon"></i>
                    </a>
                    <div class="submenu">
                        <a href="laboratories.php" class="nav-link">
                            <i class="bi bi-house-door"></i> Laboratorium
                        </a>
                        <a href="computers.php" class="nav-link">
                            <i class="bi bi-pc-display"></i> Komputer
                        </a>
                        <a href="inventory.php" class="nav-link">
                            <i class="bi bi-box-seam"></i> Inventaris
                        </a>
                    </div>
                </div>
                
                <!-- Menu Peminjaman dengan Submenu -->
                <div class="menu-item" id="borrowManagementMenu">
                    <a href="#" class="nav-link" onclick="toggleSubmenu('borrowManagementMenu'); return false;">
                        <div>
                            <i class="bi bi-clipboard-check"></i>
                            <span> Activity</span>
                        </div>
                        <i class="bi bi-chevron-down dropdown-icon"></i>
                    </a>
                    <div class="submenu">
                        <a href="manage_borrows.php" class="nav-link">
                            <i class="bi bi-list-check"></i> Kelola Peminjaman
                        </a>
                        <a href="manage_bookings.php" class="nav-link">
                            <i class="bi bi-calendar-check"></i> Pemesanan Lab
                        </a>
                    </div>
                </div>
                
                <!-- Menu Laporan -->
                <a href="reports.php" class="nav-link">
                    <i class="bi bi-graph-up"></i> Laporan
                </a>
                
                <!-- Menu Pengguna (khusus kepala_lab) -->
                <?php if ($user['role'] === 'kepala_lab'): ?>
                    <a href="manage_users.php" class="nav-link">
                        <i class="bi bi-people"></i> Kelola Pengguna
                    </a>
                <?php endif; ?>
                
            <?php elseif ($user['role'] === 'siswa' || $user['role'] === 'pengunjung'): ?>
                <!-- Menu Siswa/Pengunjung -->
                <a href="borrow_items.php" class="nav-link">
                    <i class="bi bi-box-arrow-up-right"></i> Peminjaman Barang
                </a>
                <a href="return_items.php" class="nav-link">
                    <i class="bi bi-box-arrow-in-down-left"></i> Pengembalian Barang
                </a>
                
            <?php else: ?>
                <!-- Menu Guru/Teknisi -->
                <a href="manage_bookings.php" class="nav-link">
                    <i class="bi bi-calendar-check"></i> Pemesanan
                </a>
            <?php endif; ?>
            
            <!-- Menu Profil (untuk semua role) -->
            <a href="profile.php" class="nav-link active">
                <i class="bi bi-person-circle"></i> Profil
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar role-<?php echo $user['role']; ?> <?php echo $user['is_active'] ? '' : 'inactive'; ?>">
                    <?php if ($user['avatar'] && file_exists('uploads/avatars/' . $user['avatar'])): ?>
                        <img src="uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                    <?php else: ?>
                        <!-- Avatar default berdasarkan role -->
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                            <?php echo getInitials($user['name']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($user['name']); ?></p>
                    <p class="user-role">
                        <?php 
                        switch($user['role']) {
                            case 'kepala_lab': 
                                echo '<i class="bi bi-patch-check-fill"></i> Kepala Lab'; 
                                break;
                            case 'pj_lab': 
                                echo '<i class="bi bi-shield-check"></i> PJ Lab'; 
                                break;
                            case 'guru': 
                                echo '<i class="bi bi-book"></i> Guru'; 
                                break;
                            case 'teknisi': 
                                echo '<i class="bi bi-wrench"></i> Teknisi'; 
                                break;
                            case 'siswa': 
                                echo '<i class="bi bi-mortarboard"></i> Siswa'; 
                                break;
                            case 'pengunjung': 
                                echo '<i class="bi bi-person"></i> Pengunjung'; 
                                break;
                            default: 
                                echo '<i class="bi bi-person-circle"></i> ' . ucfirst(htmlspecialchars($user['role']));
                        }
                        ?>
                    </p>
                </div>
            </div>
            <a href="profile.php" class="nav-link">
                <i class="bi bi-person-gear"></i> Pengaturan
            </a>
            <a href="logout.php" class="nav-link">
                <i class="bi bi-box-arrow-right"></i> Keluar
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-header">
            <h1 class="page-title">Profil Saya</h1>
            <span><?php echo date('d F Y'); ?></span>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="card-header-custom">
                <h3 class="card-title">Informasi Akun</h3>
                <!-- Tombol Edit/Save akan dikontrol oleh JavaScript -->
                <div id="edit-actions" style="display: block;">
                    <button type="button" class="btn btn-primary" id="editBtn" onclick="enableEdit()">
                        <i class="bi bi-pencil-square"></i> Edit Profil
                    </button>
                </div>
                <div id="save-actions" style="display: none;">
                    <button type="button" class="btn btn-success" id="saveBtn" onclick="saveProfile()">
                        <i class="bi bi-check-circle"></i> Simpan Perubahan
                    </button>
                    <button type="button" class="btn btn-secondary" id="cancelBtn" onclick="location.reload()">
                        <i class="bi bi-x-circle"></i> Batal
                    </button>
                </div>
            </div>
            
            <form id="profileForm" method="POST" action="" enctype="multipart/form-data">
                <!-- Hidden field untuk action -->
                <input type="hidden" name="action" id="formAction" value="">
                
                <div class="row">
                    <div class="col-md-4 text-center">
                        <div class="profile-avatar-container">
                            <img src="uploads/avatars/<?php echo htmlspecialchars($user['avatar'] ?? 'default-avatar.png'); ?>" alt="Avatar" class="profile-avatar-img" id="avatarPreview">
                            <div class="mt-3">
                                <label for="avatar" class="btn btn-sm btn-outline-secondary" id="avatarLabel" style="display: none;">Ganti Foto</label>
                                <input type="file" class="form-control d-none" id="avatar" name="avatar" accept="image/*" onchange="previewAvatar(event)">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" disabled required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nip_nim" class="form-label">NIP / NIM</label>
                                <input type="text" class="form-control" id="nip_nim" name="nip_nim" value="<?php echo htmlspecialchars($user['nip_nim']); ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">No. Telepon</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" disabled>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="department" class="form-label">Departemen / Jurusan</label>
                                <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($user['department']); ?>" disabled>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section-title">Ubah Password</div>
                <p class="text-muted">Biarkan kosong jika Anda tidak ingin mengubah password.</p>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="current_password" class="form-label">Password Lama</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" disabled>
                            <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword" style="display: none;">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="new_password" class="form-label">Password Baru</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" disabled onkeyup="checkPasswordStrength()">
                            <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword" style="display: none;">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="passwordStrength"></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" disabled>
                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword" style="display: none;">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        let isEditMode = false;
        
        function enableEdit() {
            // Aktifkan semua input form
            const inputs = document.querySelectorAll('#profileForm input:not([type="hidden"])');
            inputs.forEach(input => input.disabled = false);

            // Tampilkan tombol save/cancel, sembunyikan tombol edit
            document.getElementById('edit-actions').style.display = 'none';
            document.getElementById('save-actions').style.display = 'block';
            
            // Tampilkan label ganti foto dan tombol toggle password
            document.getElementById('avatarLabel').style.display = 'inline-block';
            document.getElementById('toggleCurrentPassword').style.display = 'block';
            document.getElementById('toggleNewPassword').style.display = 'block';
            document.getElementById('toggleConfirmPassword').style.display = 'block';
            
            isEditMode = true;
        }
        
        function saveProfile() {
            // Set action value sebelum submit
            document.getElementById('formAction').value = 'save_profile';
            
            // Validasi form
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Validasi password hanya jika field password baru diisi
            if (newPassword !== '' && newPassword !== confirmPassword) {
                alert('Password baru dan konfirmasi password tidak cocok.');
                return false;
            }
            
            // Validasi password lama jika password baru diisi
            if (newPassword !== '') {
                const currentPassword = document.getElementById('current_password').value;
                if (currentPassword === '') {
                    alert('Password lama harus diisi untuk mengubah password.');
                    return false;
                }
            }
            
            // Submit form
            document.getElementById('profileForm').submit();
        }
        
        function previewAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }
        
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthElement = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthElement.className = 'password-strength';
                return;
            }
            
            let strength = 0;
            
            // Panjang password
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            
            // Mengandung huruf kecil
            if (password.match(/[a-z]+/)) strength++;
            
            // Mengandung huruf besar
            if (password.match(/[A-Z]+/)) strength++;
            
            // Mengandung angka
            if (password.match(/[0-9]+/)) strength++;
            
            // Mengandung karakter khusus
            if (password.match(/[$@#&!]+/)) strength++;
            
            // Set class berdasarkan kekuatan password
            if (strength <= 2) {
                strengthElement.className = 'password-strength strength-weak';
            } else if (strength <= 4) {
                strengthElement.className = 'password-strength strength-medium';
            } else {
                strengthElement.className = 'password-strength strength-strong';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle password visibility
            document.getElementById('toggleCurrentPassword').addEventListener('click', function() {
                const passwordField = document.getElementById('current_password');
                const icon = this.querySelector('i');
                
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    passwordField.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            });
            
            document.getElementById('toggleNewPassword').addEventListener('click', function() {
                const passwordField = document.getElementById('new_password');
                const icon = this.querySelector('i');
                
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    passwordField.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            });
            
            document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
                const passwordField = document.getElementById('confirm_password');
                const icon = this.querySelector('i');
                
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    passwordField.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            });
            
            // Validasi real-time untuk konfirmasi password
            document.getElementById('confirm_password').addEventListener('input', function() {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = this.value;
                
                if (confirmPassword !== '' && newPassword !== confirmPassword) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });
        });
    </script>
</body>
</html>