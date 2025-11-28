<?php
// register.php (DIPERBAIKI UNTUK MENDUKUNG ROLE PENGGUNJUNG)
session_start();

// Jika user sudah login, redirect ke dashboard yang sesuai
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    
    switch($role) {
        case 'pengunjung':
            header('Location: visitor_dashboard.php');
            break;
        case 'siswa':
            header('Location: dashboard.php');
            break;
        default: // kepala_lab, pj_lab, guru, teknisi
            header('Location: dashboard.php');
            break;
    }
    exit();
}

require_once 'config.php';

 $error = '';
 $success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $nip_nim = trim($_POST['nip_nim']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    
    // Validasi input
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $error = "Semua field wajib harus diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } elseif (strlen($password) < 6) {
        $error = "Password harus minimal 6 karakter.";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok.";
    } elseif (!in_array($role, ['pengunjung', 'siswa'])) {
        $error = "Role tidak valid.";
    } else {
        try {
            // Cek apakah email sudah terdaftar
            $sql = "SELECT id FROM users WHERE email = :email LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->fetch()) {
                $error = "Email sudah terdaftar. Gunakan email lain.";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user baru
                $sql = "INSERT INTO users (name, email, password, role, nip_nim, phone, department) 
                        VALUES (:name, :email, :password, :role, :nip_nim, :phone, :department)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':role', $role);
                $stmt->bindParam(':nip_nim', $nip_nim);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':department', $department);
                
                if ($stmt->execute()) {
                    $success = "Pendaftaran berhasil! Silakan login dengan akun Anda.";
                } else {
                    $error = "Gagal mendaftar. Silakan coba lagi.";
                }
            }
        } catch (PDOException $e) {
            error_log("Register error: " . $e->getMessage());
            $error = "Terjadi kesalahan server. Silakan coba lagi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - SILABKOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { 
            --primary-color: #0d6efd; 
            --secondary-color: #0a58ca; 
            --light-color: #f8f9fa; 
            --dark-color: #212529; 
            --success-color: #198754; 
            --danger-color: #dc3545; 
            --warning-color: #ffc107; 
            --info-color: #0dcaf0; 
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: var(--dark-color); 
            padding: 20px 0;
        }
        
        .register-container { 
            background-color: white; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); 
            overflow: hidden; 
            width: 100%; 
            max-width: 900px; 
            min-height: 600px; 
            display: flex; 
        }
        
        .register-info { 
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); 
            color: white; 
            padding: 40px; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            flex: 1; 
        }
        
        .register-form { 
            padding: 40px; 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            overflow-y: auto;
            max-height: 90vh;
        }
        
        .register-logo { 
            text-align: center; 
            margin-bottom: 30px; 
        }
        
        .register-logo h1 { 
            font-weight: bold; 
            margin: 0; 
            font-size: 2.5rem; 
        }
        
        .register-logo p { 
            margin: 5px 0 0; 
            opacity: 0.8; 
        }
        
        .form-floating { 
            margin-bottom: 15px; 
        }
        
        .form-control { 
            border-radius: 8px; 
            border: 1px solid #ced4da; 
            padding: 12px 15px; 
            height: auto; 
        }
        
        .form-control:focus { 
            border-color: var(--primary-color); 
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25); 
        }
        
        .btn-register { 
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); 
            border: none; 
            border-radius: 8px; 
            padding: 12px; 
            font-weight: 500; 
            width: 100%; 
            transition: all 0.3s; 
        }
        
        .btn-register:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3); 
        }
        
        .alert { 
            border-radius: 8px; 
            padding: 12px 15px; 
            margin-bottom: 20px; 
        }
        
        .login-link { 
            text-align: center; 
            margin-top: 20px; 
        }
        
        .login-link a { 
            color: var(--primary-color); 
            text-decoration: none; 
            font-weight: 500; 
        }
        
        .login-link a:hover { 
            text-decoration: underline; 
        }
        
        .feature-list { 
            margin-top: 30px; 
        }
        
        .feature-item { 
            display: flex; 
            align-items: center; 
            margin-bottom: 15px; 
        }
        
        .feature-item i { 
            margin-right: 10px; 
            font-size: 1.2rem; 
        }
        
        @media (max-width: 768px) { 
            .register-container { 
                flex-direction: column; 
                max-width: 400px; 
            } 
            .register-info { 
                padding: 30px; 
            } 
            .register-form { 
                padding: 30px; 
            } 
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-info">
            <div class="register-logo">
                <h1>SILABKOM</h1>
                <p>Sistem Informasi Laboratorium Komputer</p>
            </div>
            <div class="feature-list">
                <div class="feature-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Pemesanan Laboratorium Online</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Manajemen Inventaris</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Tracking Peminjaman</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Laporan Real-time</span>
                </div>
            </div>
        </div>
        <div class="register-form">
            <h2 class="mb-4">Buat Akun Baru</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="name" name="name" placeholder="Nama Lengkap" required>
                            <label for="name">Nama Lengkap</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                            <label for="email">Email</label>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                            <label for="password">Password</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Konfirmasi Password" required>
                            <label for="confirm_password">Konfirmasi Password</label>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <select class="form-control" id="role" name="role" required>
                                <option value="">Pilih Role</option>
                                <option value="pengunjung">Pengunjung</option>
                                <option value="siswa">Siswa</option>
                            </select>
                            <label for="role">Role</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="nip_nim" name="nip_nim" placeholder="NIP/NIM">
                            <label for="nip_nim">NIP/NIM (Opsional)</label>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control" id="phone" name="phone" placeholder="No. Telepon">
                            <label for="phone">No. Telepon (Opsional)</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="department" name="department" placeholder="Jurusan/Unit">
                            <label for="department">Jurusan/Unit (Opsional)</label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-register">
                    <i class="bi bi-person-plus"></i> Daftar
                </button>
            </form>
            
            <div class="login-link">
                <p>Sudah punya akun? <a href="login.php">Masuk</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>