<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform
 * Tenant Portal - ONU Device List
 *********************************************************************************************************
 */

require_once(dirname(__FILE__) . '/../library/checklogin.php');
require_once(dirname(__FILE__) . '/../../common/library/db_connect.php');

$tenantId = $_SESSION['portal_tenant_id'];
$error = '';
$success = '';

// Get OLT filter
$oltId = isset($_GET['olt_id']) ? intval($_GET['olt_id']) : 0;

// Get available OLTs
$olts = [];
$result = $dbSocket->query("SELECT id, name, brand FROM olt_devices WHERE tenant_id = {$tenantId} ORDER BY name");
if ($result && !DB::isError($result)) {
    while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
        $olts[] = $row;
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!dalo_portal_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid';
    } else {
        $action = $_POST['action'];

        if ($action === 'register') {
            $selectedOltId = intval($_POST['olt_id'] ?? 0);
            $ponPort = intval($_POST['pon_port'] ?? 0);
            $onuId = intval($_POST['onu_id'] ?? 0);
            $serialNumber = trim($_POST['serial_number'] ?? '');
            $customerName = trim($_POST['customer_name'] ?? '');
            $pppoeUsername = trim($_POST['pppoe_username'] ?? '');
            $vlanId = intval($_POST['vlan_id'] ?? 0);

            // Verify OLT ownership
            $checkResult = $dbSocket->query("SELECT id FROM olt_devices WHERE id = {$selectedOltId} AND tenant_id = {$tenantId}");
            if (!$checkResult || DB::isError($checkResult) || $checkResult->numRows() === 0) {
                $error = 'OLT tidak ditemukan';
            } elseif (empty($serialNumber) || $ponPort < 1 || $onuId < 1) {
                $error = 'PON Port, ONU ID, dan Serial Number wajib diisi';
            } else {
                // Insert ONU record
                $sql = sprintf(
                    "INSERT INTO olt_onu_devices (olt_id, pon_port_id, onu_index, serial_number, customer_name,
                                                   pppoe_username, vlan_id, status, created_at)
                     VALUES (%d, %d, %d, '%s', '%s', '%s', %d, 'registered', NOW())",
                    $selectedOltId,
                    $ponPort,  // Using pon_port as pon_port_id for now
                    $onuId,
                    $dbSocket->escapeSimple($serialNumber),
                    $dbSocket->escapeSimple($customerName),
                    $dbSocket->escapeSimple($pppoeUsername),
                    $vlanId
                );
                $result = $dbSocket->query($sql);
                if (!DB::isError($result)) {
                    $success = 'ONU berhasil didaftarkan';
                } else {
                    $error = 'Gagal mendaftarkan ONU: ' . $result->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $onuDbId = intval($_POST['onu_db_id'] ?? 0);
            // Verify ownership through OLT
            $checkSql = "SELECT onu.id FROM olt_onu_devices onu
                         JOIN olt_devices olt ON onu.olt_id = olt.id
                         WHERE onu.id = {$onuDbId} AND olt.tenant_id = {$tenantId}";
            $checkResult = $dbSocket->query($checkSql);
            if ($checkResult && !DB::isError($checkResult) && $checkResult->numRows() > 0) {
                $dbSocket->query("DELETE FROM olt_onu_devices WHERE id = {$onuDbId}");
                $success = 'ONU berhasil dihapus';
            } else {
                $error = 'ONU tidak ditemukan';
            }
        }
    }
}

// Build query for ONU list
$whereClause = "olt.tenant_id = {$tenantId}";
if ($oltId > 0) {
    $whereClause .= " AND onu.olt_id = {$oltId}";
}

// Get ONU devices
$onus = [];
$result = $dbSocket->query("
    SELECT onu.*, olt.name as olt_name, olt.brand as olt_brand,
           (SELECT rx_power FROM olt_signal_logs WHERE onu_id = onu.id ORDER BY recorded_at DESC LIMIT 1) as last_rx_power
    FROM olt_onu_devices onu
    JOIN olt_devices olt ON onu.olt_id = olt.id
    WHERE {$whereClause}
    ORDER BY olt.name, onu.pon_port_id, onu.onu_index
");
if ($result && !DB::isError($result)) {
    while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
        $onus[] = $row;
    }
}

// Signal quality helper
function getSignalQuality($rxPower) {
    if ($rxPower === null) return ['class' => 'secondary', 'text' => 'N/A'];
    $rx = floatval($rxPower);
    if ($rx >= -25) return ['class' => 'success', 'text' => 'Excellent'];
    if ($rx >= -27) return ['class' => 'info', 'text' => 'Good'];
    if ($rx >= -29) return ['class' => 'warning', 'text' => 'Fair'];
    return ['class' => 'danger', 'text' => 'Poor'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ONU Management - <?php echo htmlspecialchars($_SESSION['portal_tenant_name']); ?></title>
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
        .signal-bar { width: 100px; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
        .signal-bar-fill { height: 100%; border-radius: 4px; }
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
            <li class="nav-item"><a class="nav-link" href="list.php"><i class="fas fa-network-wired"></i> OLT</a></li>
            <li class="nav-item"><a class="nav-link active" href="onu-list.php"><i class="fas fa-microchip"></i> ONU/ONT</a></li>
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
                <h4 class="mb-0">ONU Management</h4>
                <small class="text-muted">Kelola perangkat ONU/ONT</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerOnuModal" <?php echo empty($olts) ? 'disabled' : ''; ?>>
                <i class="fas fa-plus me-1"></i> Register ONU
            </button>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Filter by OLT</label>
                        <select class="form-select" name="olt_id" onchange="this.form.submit()">
                            <option value="">Semua OLT</option>
                            <?php foreach ($olts as $olt): ?>
                            <option value="<?php echo $olt['id']; ?>" <?php echo $oltId == $olt['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($olt['name']); ?> (<?php echo htmlspecialchars($olt['brand']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <a href="onu-list.php" class="btn btn-outline-secondary">
                            <i class="fas fa-sync me-1"></i>Reset Filter
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- ONU Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-microchip me-2"></i>ONU Devices (<?php echo count($onus); ?>)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>OLT / Port</th>
                                <th>ONU ID</th>
                                <th>Serial Number</th>
                                <th>Pelanggan</th>
                                <th>PPPoE</th>
                                <th>Signal</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($onus)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-microchip fa-3x mb-3 d-block opacity-50"></i>
                                    Belum ada ONU terdaftar
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($onus as $onu):
                                $signal = getSignalQuality($onu['last_rx_power']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($onu['olt_name']); ?></strong><br>
                                    <small class="text-muted">PON <?php echo $onu['pon_port_id']; ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $onu['onu_index']; ?></span>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars($onu['serial_number']); ?></code>
                                </td>
                                <td><?php echo htmlspecialchars($onu['customer_name'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($onu['pppoe_username']): ?>
                                    <code><?php echo htmlspecialchars($onu['pppoe_username']); ?></code>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($onu['last_rx_power'] !== null): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-<?php echo $signal['class']; ?>"><?php echo number_format($onu['last_rx_power'], 2); ?> dBm</span>
                                        <small class="text-<?php echo $signal['class']; ?>"><?php echo $signal['text']; ?></small>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusColors = ['online' => 'success', 'offline' => 'secondary', 'registered' => 'info', 'los' => 'danger'];
                                    $color = $statusColors[$onu['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?> badge-status"><?php echo ucfirst($onu['status']); ?></span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="onu-config.php?id=<?php echo $onu['id']; ?>" class="btn btn-outline-primary" title="Configure">
                                            <i class="fas fa-cog"></i>
                                        </a>
                                        <a href="onu-signal.php?id=<?php echo $onu['id']; ?>" class="btn btn-outline-info" title="Signal History">
                                            <i class="fas fa-chart-line"></i>
                                        </a>
                                        <button class="btn btn-outline-danger" onclick="confirmDelete(<?php echo $onu['id']; ?>, '<?php echo htmlspecialchars($onu['serial_number']); ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Register ONU Modal -->
    <div class="modal fade" id="registerOnuModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo dalo_portal_csrf_token(); ?>">
                    <input type="hidden" name="action" value="register">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Register ONU</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">OLT <span class="text-danger">*</span></label>
                                <select class="form-select" name="olt_id" required>
                                    <option value="">Pilih OLT</option>
                                    <?php foreach ($olts as $olt): ?>
                                    <option value="<?php echo $olt['id']; ?>" <?php echo $oltId == $olt['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($olt['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">PON Port <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="pon_port" min="1" max="16" required placeholder="1-16">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ONU ID <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="onu_id" min="1" max="128" required placeholder="1-128">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Serial Number (MAC) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="serial_number" required placeholder="Contoh: HWTC12345678">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nama Pelanggan</label>
                                <input type="text" class="form-control" name="customer_name" placeholder="Nama pelanggan">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">PPPoE Username</label>
                                <input type="text" class="form-control" name="pppoe_username" placeholder="Username PPPoE">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">VLAN ID</label>
                                <input type="number" class="form-control" name="vlan_id" min="1" max="4094" placeholder="1-4094">
                            </div>
                        </div>

                        <div class="alert alert-info mt-3 mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Tips:</strong> Pastikan ONU sudah terdeteksi di OLT sebelum registrasi. Serial number bisa dilihat dari halaman OLT atau label pada perangkat ONU.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Register</button>
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
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Hapus ONU</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Apakah Anda yakin ingin menghapus ONU <strong id="deleteOnuSn"></strong>?
                </div>
                <div class="modal-footer">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo dalo_portal_csrf_token(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="onu_db_id" id="deleteOnuId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(id, sn) {
            document.getElementById('deleteOnuId').value = id;
            document.getElementById('deleteOnuSn').textContent = sn;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>
