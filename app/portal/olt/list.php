<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Tenant Portal - OLT Device List
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/../library/checklogin.php');
require_once(dirname(__FILE__) . '/../../common/library/db_connect.php');

$tenantId = $_SESSION['portal_tenant_id'];
$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!dalo_portal_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid';
    } else {
        $action = $_POST['action'];

        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $model = trim($_POST['model'] ?? '');
            $ipAddress = trim($_POST['ip_address'] ?? '');
            $port = intval($_POST['port'] ?? 23);
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $location = trim($_POST['location'] ?? '');

            if (empty($name) || empty($brand) || empty($ipAddress) || empty($username)) {
                $error = 'Nama, brand, IP address, dan username wajib diisi';
            } else {
                $sql = sprintf(
                    "INSERT INTO olt_devices (tenant_id, name, brand, model, ip_address, telnet_port, username, password, location, is_active, created_at)
                     VALUES (%d, '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', 1, NOW())",
                    $tenantId,
                    $dbSocket->escapeSimple($name),
                    $dbSocket->escapeSimple($brand),
                    $dbSocket->escapeSimple($model),
                    $dbSocket->escapeSimple($ipAddress),
                    $port,
                    $dbSocket->escapeSimple($username),
                    $dbSocket->escapeSimple($password),
                    $dbSocket->escapeSimple($location)
                );
                $result = $dbSocket->query($sql);
                if (!DB::isError($result)) {
                    $success = 'OLT berhasil ditambahkan';
                } else {
                    $error = 'Gagal menambahkan OLT: ' . $result->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $oltId = intval($_POST['olt_id'] ?? 0);
            // Check ownership
            $checkSql = "SELECT id FROM olt_devices WHERE id = {$oltId} AND tenant_id = {$tenantId}";
            $checkResult = $dbSocket->query($checkSql);
            if ($checkResult && !DB::isError($checkResult) && $checkResult->numRows() > 0) {
                $dbSocket->query("DELETE FROM olt_devices WHERE id = {$oltId}");
                $success = 'OLT berhasil dihapus';
            } else {
                $error = 'OLT tidak ditemukan';
            }
        } elseif ($action === 'toggle') {
            $oltId = intval($_POST['olt_id'] ?? 0);
            $dbSocket->query("UPDATE olt_devices SET is_active = NOT is_active WHERE id = {$oltId} AND tenant_id = {$tenantId}");
            $success = 'Status OLT berhasil diubah';
        }
    }
}

// Get OLT devices
$olts = [];
$result = $dbSocket->query("
    SELECT o.*,
           (SELECT COUNT(*) FROM olt_pon_ports WHERE olt_id = o.id) as port_count,
           (SELECT COUNT(*) FROM olt_onu_devices WHERE olt_id = o.id) as onu_count
    FROM olt_devices o
    WHERE o.tenant_id = {$tenantId}
    ORDER BY o.name ASC
");
if ($result && !DB::isError($result)) {
    while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
        $olts[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OLT Management - <?php echo htmlspecialchars($_SESSION['portal_tenant_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sidebar-width: 260px; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: var(--sidebar-width); background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); z-index: 1000; overflow-y: auto; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h5 { color: white; margin: 10px 0 5px; }
        .nav-link { color: rgba(255,255,255,0.7) !important; padding: 12px 20px; border-left: 3px solid transparent; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white !important; border-left-color: #38ef7d; }
        .nav-link i { width: 25px; text-align: center; margin-right: 10px; }
        .nav-section { padding: 15px 20px 5px; color: rgba(255,255,255,0.4); font-size: 0.7rem; text-transform: uppercase; }
        .main-content { margin-left: var(--sidebar-width); padding: 20px 30px; min-height: 100vh; }
        .top-bar { background: white; padding: 15px 25px; margin: -20px -30px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .card-header { background: white; border-bottom: 1px solid #eee; padding: 15px 20px; font-weight: 600; }
        .olt-card { transition: all 0.3s; }
        .olt-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .olt-icon { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .olt-icon.hioso { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .olt-icon.hsgq { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; }
        .olt-icon.other { background: linear-gradient(135deg, #f46b45 0%, #eea849 100%); color: white; }
        .badge-status { padding: 5px 12px; border-radius: 20px; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-broadcast-tower fa-2x text-white"></i>
            <h5><?php echo htmlspecialchars($_SESSION['portal_tenant_name']); ?></h5>
            <small class="text-white-50">ISP Management</small>
        </div>
        <div class="nav-section">Main</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="../home-main.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        </ul>
        <div class="nav-section">Perangkat</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="../mikrotik/list.php"><i class="fas fa-server"></i> MikroTik</a></li>
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="fas fa-network-wired"></i> OLT</a></li>
            <li class="nav-item"><a class="nav-link" href="onu-list.php"><i class="fas fa-microchip"></i> ONU/ONT</a></li>
        </ul>
        <div class="nav-section">Lainnya</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link text-danger" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">OLT Management</h4>
                <small class="text-muted">Kelola perangkat OLT EPON</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOltModal">
                <i class="fas fa-plus me-1"></i> Tambah OLT
            </button>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- OLT Grid -->
        <div class="row g-4">
            <?php if (empty($olts)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-network-wired fa-4x text-muted mb-3"></i>
                        <h5>Belum ada OLT</h5>
                        <p class="text-muted">Klik tombol "Tambah OLT" untuk menambahkan perangkat OLT pertama Anda</p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($olts as $olt):
                $iconClass = strtolower($olt['brand']) === 'hioso' ? 'hioso' : (strtolower($olt['brand']) === 'hsgq' ? 'hsgq' : 'other');
            ?>
            <div class="col-md-6 col-xl-4">
                <div class="card olt-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-3">
                            <div class="olt-icon <?php echo $iconClass; ?> me-3">
                                <i class="fas fa-network-wired"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="mb-1"><?php echo htmlspecialchars($olt['name']); ?></h5>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($olt['brand']); ?> <?php echo htmlspecialchars($olt['model']); ?></span>
                            </div>
                            <?php if ($olt['is_active']): ?>
                            <span class="badge bg-success badge-status">Aktif</span>
                            <?php else: ?>
                            <span class="badge bg-secondary badge-status">Nonaktif</span>
                            <?php endif; ?>
                        </div>

                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <div class="border-end">
                                    <h4 class="mb-0 text-primary"><?php echo $olt['port_count']; ?></h4>
                                    <small class="text-muted">PON Ports</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border-end">
                                    <h4 class="mb-0 text-success"><?php echo $olt['onu_count']; ?></h4>
                                    <small class="text-muted">ONU</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <h4 class="mb-0 text-info"><?php echo $olt['telnet_port']; ?></h4>
                                <small class="text-muted">Port</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted d-block"><i class="fas fa-globe me-1"></i>IP: <?php echo htmlspecialchars($olt['ip_address']); ?></small>
                            <?php if ($olt['location']): ?>
                            <small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($olt['location']); ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="onu-list.php?olt_id=<?php echo $olt['id']; ?>" class="btn btn-sm btn-outline-primary flex-grow-1">
                                <i class="fas fa-microchip me-1"></i>ONU
                            </a>
                            <a href="ports.php?olt_id=<?php echo $olt['id']; ?>" class="btn btn-sm btn-outline-info flex-grow-1">
                                <i class="fas fa-signal me-1"></i>PON
                            </a>
                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $olt['id']; ?>, '<?php echo htmlspecialchars($olt['name']); ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add OLT Modal -->
    <div class="modal fade" id="addOltModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo dalo_portal_csrf_token(); ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Tambah OLT</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nama OLT <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required placeholder="Contoh: OLT-Pusat">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Brand <span class="text-danger">*</span></label>
                                <select class="form-select" name="brand" required>
                                    <option value="">Pilih Brand</option>
                                    <option value="Hioso">Hioso</option>
                                    <option value="HSGQ">HSGQ</option>
                                    <option value="ZTE">ZTE</option>
                                    <option value="Huawei">Huawei</option>
                                    <option value="Other">Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Model</label>
                                <input type="text" class="form-control" name="model" placeholder="Contoh: HA7302CS">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">IP Address <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="ip_address" required placeholder="192.168.1.1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Port Telnet</label>
                                <input type="number" class="form-control" name="port" value="23" min="1" max="65535">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Lokasi</label>
                                <input type="text" class="form-control" name="location" placeholder="Contoh: Gedung A Lantai 2">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Hapus OLT</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Apakah Anda yakin ingin menghapus OLT <strong id="deleteOltName"></strong>?
                </div>
                <div class="modal-footer">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo dalo_portal_csrf_token(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="olt_id" id="deleteOltId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(id, name) {
            document.getElementById('deleteOltId').value = id;
            document.getElementById('deleteOltName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>
