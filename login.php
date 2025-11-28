<?php
// login.php (FINAL VERSION - REDIRECT OTOMATIS BERDASARKAN ROLE)
session_start();

// Cek jika user sudah login dan redirect ke dashboard yang sesuai.
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    switch ($_SESSION['user_role']) {
        case 'pengunjung':
            header('Location: visitor_home.php');
            break;
        case 'siswa':
            header('Location: dashboard_student.php');
            break;
        case 'guru':
            header('Location: dashboard_guru.php');
            break;
        case 'teknisi':
            header('Location: dashboard_teknisi.php');
            break;
        case 'kepala_lab':
        case 'pj_lab':
            header('Location: dashboard.php');
            break;
        default:
            // Jika role tidak dikenal, hancurkan session dan redirect ke login
            session_unset();
            session_destroy();
            header('Location: login.php?error=invalid_role');
            break;
    }
    exit();
}

require_once 'config.php';

 $error = '';
// Cek apakah ada error parameter dari redirect
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'invalid_role') {
        $error = "Peran pengguna tidak valid. Silakan hubungi administrator.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validasi input
    if (empty($email) || empty($password)) {
        $error = "Email dan password harus diisi.";
    } else {
        try {
            // Query untuk mendapatkan data user berdasarkan email
            $sql = "SELECT id, name, email, password, role FROM users WHERE email = :email LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            // Verifikasi user dan password
            if ($user && password_verify($password, $user['password'])) {
                // Login berhasil, atur session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                // Redirect berdasarkan role yang diambil dari database
                switch($user['role']) {
                    case 'pengunjung':
                        header('Location: visitor_home.php');
                        break;
                    case 'siswa':
                        header('Location: dashboard_student.php');
                        break;
                    case 'guru':
                        header('Location: dashboard_guru.php');
                        break;
                    case 'teknisi':
                        header('Location: dashboard_teknisi.php');
                        break;
                    case 'kepala_lab':
                    case 'pj_lab':
                        header('Location: dashboard.php');
                        break;
                    default:
                        // Role tidak dikenal, logout dan tampilkan error
                        session_unset();
                        session_destroy();
                        header('Location: login.php?error=invalid_role');
                        break;
                }
                exit();
            } else {
                $error = "Email atau password salah.";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "Terjadi kesalahan. Silakan coba lagi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SILABKOM</title>
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
        }
        
        .login-container { 
            background-color: white; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); 
            overflow: hidden; 
            width: 100%; 
            max-width: 900px; 
            min-height: 500px; 
            display: flex; 
        }
        
        .login-info { 
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); 
            color: white; 
            padding: 40px; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            flex: 1; 
        }
        
        .login-form { 
            padding: 40px; 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
        }
        
        .login-logo { 
            text-align: center; 
            margin-bottom: 30px; 
        }
        
        .login-logo h1 { 
            font-weight: bold; 
            margin: 0; 
            font-size: 2.5rem; 
        }
        
        .login-logo p { 
            margin: 5px 0 0; 
            opacity: 0.8; 
        }
        
        .form-floating { 
            margin-bottom: 20px; 
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
        
        .btn-login { 
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); 
            border: none; 
            border-radius: 8px; 
            padding: 12px; 
            font-weight: 500; 
            width: 100%; 
            transition: all 0.3s; 
        }
        
        .btn-login:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3); 
        }
        
        .alert { 
            border-radius: 8px; 
            padding: 12px 15px; 
            margin-bottom: 20px; 
        }
        
        .register-link { 
            text-align: center; 
            margin-top: 20px; 
        }
        
        .register-link a { 
            color: var(--primary-color); 
            text-decoration: none; 
            font-weight: 500; 
        }
        
        .register-link a:hover { 
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
            .login-container { 
                flex-direction: column; 
                max-width: 400px; 
            } 
            .login-info { 
                padding: 30px; 
            } 
            .login-form { 
                padding: 30px; 
            } 
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-info">
            <div class="login-logo">
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
        <div class="login-form">
            <h2 class="mb-4">Masuk ke Akun Anda</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                    <label for="email">Email</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password">Password</label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Masuk
                </button>
            </form>
            
            <div class="register-link">
                <p>Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Fokus pada email field saat halaman dimuat
            document.getElementById('email').focus();
        });
    </script>
</body>
</html>