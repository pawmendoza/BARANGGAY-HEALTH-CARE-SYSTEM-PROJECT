<?php
session_start();
require_once '../configDB/db.php';
require_once '../configDB/logger.php'; // Idinagdag para sa Activity Audit Logging

if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin', 'doctor', 'nurse'])) {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";

// Siguraduhing may 'category' at 'expiration_date' column ang inventory table
$conn->query("ALTER TABLE inventory ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT 'General'");
$conn->query("ALTER TABLE inventory ADD COLUMN IF NOT EXISTS expiration_date DATE DEFAULT NULL");

// Handle Add Medicine
if (isset($_POST['add_medicine'])) {
    $med_name = trim($_POST['medicine_name']);
    $category = trim($_POST['category']);
    $quantity = intval($_POST['quantity']);
    $expiration = $_POST['expiration_date'] ?? null;

    $stmt = $conn->prepare("INSERT INTO inventory (medicine_name, category, stock_level, expiration_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $med_name, $category, $quantity, $expiration);

    if ($stmt->execute()) {
        // I-record sa Audit Trail Logs
        logActivity($conn, "ADD MEDICINE", "Added new medicine: $med_name ($quantity units)");
        $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fa-solid fa-circle-check me-2'></i> Medicine added successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    $stmt->close();
}

// Handle Update Medicine (Edit Expiration Date, Stock Level, etc.)
if (isset($_POST['update_medicine'])) {
    $med_id = intval($_POST['medicine_id']);
    $med_name = trim($_POST['medicine_name']);
    $category = trim($_POST['category']);
    $quantity = intval($_POST['quantity']);
    $expiration = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;

    $stmt = $conn->prepare("UPDATE inventory SET medicine_name = ?, category = ?, stock_level = ?, expiration_date = ? WHERE id = ?");
    $stmt->bind_param("ssisi", $med_name, $category, $quantity, $expiration, $med_id);

    if ($stmt->execute()) {
        // I-record sa Audit Trail Logs
        logActivity($conn, "UPDATE MEDICINE", "Updated medicine ID $med_id: $med_name ($quantity units)");
        $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fa-solid fa-circle-check me-2'></i> Medicine updated successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $message = "<div class='alert alert-danger alert-dismissible fade show'><i class='fa-solid fa-triangle-exclamation me-2'></i> Failed to update medicine.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    $stmt->close();
}

// Handle Delete Medicine
if (isset($_POST['delete_medicine'])) {
    $med_id = intval($_POST['medicine_id']);
    
    // Kunin ang pangalan bago burahin para sa malinaw na audit log
    $get_m = $conn->prepare("SELECT medicine_name FROM inventory WHERE id = ?");
    $get_m->bind_param("i", $med_id);
    $get_m->execute();
    $m_res = $get_m->get_result()->fetch_assoc();
    $med_name_del = $m_res['medicine_name'] ?? "ID #$med_id";
    $get_m->close();

    $del_stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
    $del_stmt->bind_param("i", $med_id);
    if ($del_stmt->execute()) {
        // I-record sa Audit Trail Logs
        logActivity($conn, "DELETE MEDICINE", "Deleted medicine: $med_name_del");
        $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fa-solid fa-circle-check me-2'></i> Medicine deleted successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    $del_stmt->close();
}

// Kunin ang lahat ng medicines para sa client-side live search at filtering
$medicines = $conn->query("SELECT * FROM inventory ORDER BY id DESC");
$categories = ['All', 'Antihypertensive', 'Gastrointestinal', 'Vitamins', 'Antibiotic', 'Analgesic'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Inventory - HCMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css/style.css">
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-brand"><i class="fa-solid fa-hospital-user me-2"></i> Health Center</div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php" class="nav-link"><i class="fa-solid fa-chart-line me-3"></i> Dashboard</a></li>
            <li><a href="home_visitations.php" class="nav-link"><i class="fa-solid fa-house-medical me-3"></i> Home Visitations</a></li>
            <li><a href="inventory.php" class="nav-link active"><i class="fa-solid fa-boxes-stacked me-3"></i> Medicine Inventory</a></li>
            <li><a href="medical_history.php" class="nav-link"><i class="fa-solid fa-clock-rotate-left me-3"></i> Medical History</a></li>
            <li><a href="export_report.php" class="nav-link"><i class="fa-solid fa-file-invoice me-3"></i> FHSIS / Reports</a></li>
            <li><a href="index.php" class="nav-link"><i class="fa-solid fa-users me-3"></i> Registered Patients</a></li>
            <li><a href="audit_logs.php" class="nav-link"><i class="fa-solid fa-shield-halved me-3"></i> Audit Logs</a></li>
        </ul>
        <div class="p-3 border-top mt-auto">
            <a href="../auth/logout.php" class="btn btn-outline-danger w-100 btn-sm py-2 fw-semibold">
                <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Log Out
            </a>
        </div>
    </div>

    <div class="main-wrapper">
        <div class="top-header">
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i> Health Center Medicine Inventory Status</h4>
            <p class="text-muted small mb-0">Search or select a category to filter available medicines:</p>
        </div>

        <?= $message; ?>

        <!-- Filter & Live Search Section -->
        <div class="card card-custom mb-4 p-4">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($categories as $cat): ?>
                            <?php $isActive = ($cat === 'All') ? 'btn-primary' : 'btn-outline-primary'; ?>
                            <button type="button" onclick="filterCategory('<?= $cat; ?>', this)" class="btn btn-sm rounded-pill px-3 py-1.5 filter-btn <?= $isActive; ?>">
                                <?= $cat; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="input-group">
                        <!-- Live Search Input -->
                        <input type="text" id="adminSearchInput" class="form-control" placeholder="Search medicine name..." onkeyup="filterInventory()">
                        <span class="input-group-text bg-white text-primary"><i class="fa-solid fa-magnifying-glass"></i></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory List Table -->
        <div class="card card-custom">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <span class="fw-bold">Medicine Stock List</span>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicineModal">
                    <i class="fa-solid fa-plus me-1"></i> Add Medicine
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Medicine Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Expiration Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTableBody">
                            <?php if ($medicines && $medicines->num_rows > 0): ?>
                                <?php while($row = $medicines->fetch_assoc()): ?>
                                    <?php 
                                        $qty = $row['stock_level'] ?? $row['quantity'] ?? 0;
                                        $exp = $row['expiration_date'] ?? 'N/A';
                                        $cat = $row['category'] ?? 'General';
                                    ?>
                                    <tr class="inventory-row" data-category="<?= htmlspecialchars($cat); ?>">
                                        <td><strong class="med-name"><?= htmlspecialchars($row['medicine_name']); ?></strong></td>
                                        <td><span class="badge bg-info bg-opacity-10 text-info med-cat"><?= htmlspecialchars($cat); ?></span></td>
                                        <td>
                                            <span class="badge <?= $qty > 10 ? 'bg-success' : 'bg-danger'; ?> bg-opacity-10 text-<?= $qty > 10 ? 'success' : 'danger'; ?>">
                                                <?= $qty; ?> units
                                            </span>
                                        </td>
                                        <td><small class="text-muted"><?= htmlspecialchars($exp); ?></small></td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <!-- Edit Button -->
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editMedicineModal<?= $row['id']; ?>" title="Edit Medicine">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <!-- Delete Form -->
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this medicine?');" class="d-inline">
                                                    <input type="hidden" name="medicine_id" value="<?= $row['id']; ?>">
                                                    <button type="submit" name="delete_medicine" class="btn btn-sm btn-outline-danger" title="Delete Medicine">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Edit Medicine Modal per Row -->
                                    <div class="modal fade" id="editMedicineModal<?= $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Edit Medicine Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-start">
                                                        <input type="hidden" name="medicine_id" value="<?= $row['id']; ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Medicine Name</label>
                                                            <input type="text" name="medicine_name" class="form-control" value="<?= htmlspecialchars($row['medicine_name']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Category</label>
                                                            <select name="category" class="form-select" required>
                                                                <option value="Antihypertensive" <?= $cat == 'Antihypertensive' ? 'selected' : ''; ?>>Antihypertensive</option>
                                                                <option value="Gastrointestinal" <?= $cat == 'Gastrointestinal' ? 'selected' : ''; ?>>Gastrointestinal</option>
                                                                <option value="Vitamins" <?= $cat == 'Vitamins' ? 'selected' : ''; ?>>Vitamins</option>
                                                                <option value="Antibiotic" <?= $cat == 'Antibiotic' ? 'selected' : ''; ?>>Antibiotic</option>
                                                                <option value="Analgesic" <?= $cat == 'Analgesic' ? 'selected' : ''; ?>>Analgesic</option>
                                                                <option value="General" <?= $cat == 'General' ? 'selected' : ''; ?>>General</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Quantity</label>
                                                            <input type="number" name="quantity" class="form-control" value="<?= $qty; ?>" required min="0">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Expiration Date</label>
                                                            <input type="date" name="expiration_date" class="form-control" value="<?= ($exp !== 'N/A') ? $exp : ''; ?>">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_medicine" class="btn btn-primary btn-sm">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr id="noResultsRow"><td colspan="5" class="text-center py-4 text-muted">No medicines found in inventory.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Medicine Modal -->
    <div class="modal fade" id="addMedicineModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Add New Medicine</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Medicine Name</label>
                            <input type="text" name="medicine_name" class="form-control" required placeholder="Enter medicine name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="Antihypertensive">Antihypertensive</option>
                                <option value="Gastrointestinal">Gastrointestinal</option>
                                <option value="Vitamins">Vitamins</option>
                                <option value="Antibiotic">Antibiotic</option>
                                <option value="Analgesic">Analgesic</option>
                                <option value="General">General</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Quantity</label>
                            <input type="number" name="quantity" class="form-control" required min="1" placeholder="Enter stock quantity">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Expiration Date</label>
                            <input type="date" name="expiration_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_medicine" class="btn btn-primary btn-sm">Save Medicine</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript para sa Real-time Live Search at Category Filtering -->
    <script>
        let selectedCategory = 'All';

        function filterInventory() {
            let searchVal = document.getElementById('adminSearchInput').value.toLowerCase();
            let rows = document.querySelectorAll('.inventory-row');

            rows.forEach(row => {
                let name = row.querySelector('.med-name').textContent.toLowerCase();
                let category = row.querySelector('.med-cat').textContent.toLowerCase();

                let matchesSearch = name.includes(searchVal) || category.includes(searchVal);
                let matchesCategory = (selectedCategory === 'All') || category.includes(selectedCategory.toLowerCase());

                if (matchesSearch && matchesCategory) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function filterCategory(categoryName, btnElement) {
            selectedCategory = categoryName;
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-primary');
            });
            btnElement.classList.remove('btn-outline-primary');
            btnElement.classList.add('btn-primary');
            filterInventory();
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>