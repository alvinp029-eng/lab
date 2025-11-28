<?php
// sidebar.php - Komponen sidebar yang dapat digunakan kembali
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3>SILABKOM</h3>
    </div>
    <nav class="sidebar-menu">
        <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        
        <?php if ($user['role'] === 'kepala_lab' || $user['role'] === 'pj_lab'): ?>
            <!-- Menu Kepala Laboratorium dengan Submenu -->
            <div class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'laboratories.php' || basename($_SERVER['PHP_SELF']) == 'computers.php' || basename($_SERVER['PHP_SELF']) == 'inventory.php') ? 'open' : ''; ?>" id="labManagementMenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu('labManagementMenu'); return false;">
                    <div>
                        <i class="bi bi-building"></i>
                        <span>Manajemen Lab</span>
                    </div>
                    <i class="bi bi-chevron-down dropdown-icon"></i>
                </a>
                <div class="submenu">
                    <a href="laboratories.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'laboratories.php' ? 'active' : ''; ?>">
                        <i class="bi bi-house-door"></i> Laboratorium
                    </a>
                    <a href="computers.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'computers.php' ? 'active' : ''; ?>">
                        <i class="bi bi-pc-display"></i> Komputer
                    </a>
                    <a href="inventory.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>">
                        <i class="bi bi-box-seam"></i> Inventaris
                    </a>
                </div>
            </div>
            
            <!-- Menu Peminjaman dengan Submenu -->
            <div class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_borrows.php' || basename($_SERVER['PHP_SELF']) == 'manage_bookings.php') ? 'open' : ''; ?>" id="borrowManagementMenu">
                <a href="#" class="nav-link" onclick="toggleSubmenu('borrowManagementMenu'); return false;">
                    <div>
                        <i class="bi bi-clipboard-check"></i>
                        <span>Activity</span>
                    </div>
                    <i class="bi bi-chevron-down dropdown-icon"></i>
                </a>
                <div class="submenu">
                    <a href="manage_borrows.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_borrows.php' ? 'active' : ''; ?>">
                        <i class="bi bi-list-check"></i> Kelola Peminjaman
                    </a>
                    <a href="manage_bookings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_bookings.php' ? 'active' : ''; ?>">
                        <i class="bi bi-calendar-check"></i> Pemesanan Lab
                    </a>
                </div>
            </div>
            
            <!-- Menu Laporan -->
            <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-graph-up"></i> Laporan
            </a>
            
            <!-- Menu Pengguna (khusus kepala_lab) -->
            <?php if ($user['role'] === 'kepala_lab'): ?>
                <a href="manage_users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i> Kelola Pengguna
                </a>
            <?php endif; ?>
            
        <?php elseif ($user['role'] === 'siswa' || $user['role'] === 'pengunjung'): ?>
            <!-- Menu Siswa/Pengunjung -->
            <a href="borrow_items.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'borrow_items.php' ? 'active' : ''; ?>">
                <i class="bi bi-box-arrow-up-right"></i> Peminjaman Barang
            </a>
            <a href="return_items.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'return_items.php' ? 'active' : ''; ?>">
                <i class="bi bi-box-arrow-in-down-left"></i> Pengembalian Barang
            </a>
            
        <?php else: ?>
            <!-- Menu Guru/Teknisi -->
            <a href="manage_bookings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_bookings.php' ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check"></i> Pemesanan
            </a>
        <?php endif; ?>
        
        <!-- Menu Profil (untuk semua role) -->
        <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="bi bi-person-circle"></i> Profil
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div class="user-details">
                <p class="user-name"><?php echo htmlspecialchars($user['name']); ?></p>
                <p class="user-role"><?php 
                    switch($user['role']) {
                        case 'kepala_lab': echo 'Kepala Lab'; break;
                        case 'pj_lab': echo 'PJ Lab'; break;
                        case 'guru': echo 'Guru'; break;
                        case 'teknisi': echo 'Teknisi'; break;
                        case 'siswa': echo 'Siswa'; break;
                        case 'pengunjung': echo 'Pengunjung'; break;
                        default: echo ucfirst(htmlspecialchars($user['role']));
                    }
                ?></p>
            </div>
        </div>
        <a href="logout.php" class="nav-link" style="margin-top: 15px;">
            <i class="bi bi-box-arrow-right"></i> Keluar
        </a>
    </div>
</div>