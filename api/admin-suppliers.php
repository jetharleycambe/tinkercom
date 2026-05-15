<?php
/**
 * PART 6 — SUPPLIER MANAGEMENT
 * FILE: admin-suppliers.php (NEW FILE — save in root folder)
 *
 * FEATURES:
 *  - View all suppliers with status
 *  - Add new supplier
 *  - Edit supplier details
 *  - Deactivate / Reactivate supplier
 *  - View products linked to each supplier
 *  - Create restock orders (supplier_orders table)
 *  - View restock order history
 */
session_start();
include 'db.php';
include 'session-check.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    header('Location: index.php');
    exit;
}

$success = '';
$error   = '';

// ── ADD SUPPLIER ──────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'add_supplier') {
    $name    = trim(mysqli_real_escape_string($conn, $_POST['supplier_name']    ?? ''));
    $contact = trim(mysqli_real_escape_string($conn, $_POST['contact_person']   ?? ''));
    $email   = trim(mysqli_real_escape_string($conn, $_POST['email']            ?? ''));
    $phone   = trim(mysqli_real_escape_string($conn, $_POST['phone']            ?? ''));
    $address = trim(mysqli_real_escape_string($conn, $_POST['address']          ?? ''));
    $products_supplied = trim(mysqli_real_escape_string($conn, $_POST['products_supplied'] ?? ''));

    if ($name === '') {
        $error = 'Supplier name is required.';
    } else {
        mysqli_query($conn,
            "INSERT INTO suppliers (supplier_name, contact_person, email, phone, address, products_supplied, status)
             VALUES ('$name','$contact','$email','$phone','$address','$products_supplied','Active')"
        );
        $success = "Supplier \"$name\" added successfully.";
    }
}

// ── EDIT SUPPLIER ─────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'edit_supplier') {
    $id      = intval($_POST['supplier_id']);
    $name    = trim(mysqli_real_escape_string($conn, $_POST['supplier_name']    ?? ''));
    $contact = trim(mysqli_real_escape_string($conn, $_POST['contact_person']   ?? ''));
    $email   = trim(mysqli_real_escape_string($conn, $_POST['email']            ?? ''));
    $phone   = trim(mysqli_real_escape_string($conn, $_POST['phone']            ?? ''));
    $address = trim(mysqli_real_escape_string($conn, $_POST['address']          ?? ''));
    $products_supplied = trim(mysqli_real_escape_string($conn, $_POST['products_supplied'] ?? ''));

    mysqli_query($conn,
        "UPDATE suppliers SET
            supplier_name     = '$name',
            contact_person    = '$contact',
            email             = '$email',
            phone             = '$phone',
            address           = '$address',
            products_supplied = '$products_supplied'
         WHERE supplier_id = $id"
    );
    $success = 'Supplier updated successfully.';
}

// ── TOGGLE STATUS (Active / Inactive) ────────────────────────
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id  = intval($_GET['toggle']);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM suppliers WHERE supplier_id = $id"));
    $new = $row['status'] === 'Active' ? 'Inactive' : 'Active';
    mysqli_query($conn, "UPDATE suppliers SET status = '$new' WHERE supplier_id = $id");
    header('Location: admin-suppliers.php');
    exit;
}

// ── CREATE RESTOCK ORDER ──────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'restock') {
    $supplier_id = intval($_POST['supplier_id']);
    $product_id  = intval($_POST['product_id']);
    $qty         = intval($_POST['quantity_ordered']);
    $unit_cost   = floatval($_POST['unit_cost']);
    $expected    = mysqli_real_escape_string($conn, $_POST['expected_date'] ?? '');
    $notes       = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
    $admin_id    = intval($_SESSION['customer_id']);

    if ($qty < 1 || $unit_cost <= 0) {
        $error = 'Quantity and unit cost must be greater than zero.';
    } else {
        mysqli_query($conn,
            "INSERT INTO supplier_orders
                (supplier_id, product_id, quantity_ordered, unit_cost, status, ordered_by, expected_date, notes)
             VALUES ($supplier_id, $product_id, $qty, $unit_cost, 'PENDING', $admin_id,
                     " . ($expected ? "'$expected'" : "NULL") . ",
                     '$notes')"
        );
        $success = 'Restock order created successfully.';
    }
}

// ── MARK RESTOCK ORDER AS RECEIVED ───────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'receive_order') {
    $so_id    = intval($_POST['supplier_order_id']);
    $received = intval($_POST['quantity_received']);

    // Get the order details
    $so = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM supplier_orders WHERE supplier_order_id = $so_id"
    ));

    if ($so) {
        $new_status = $received >= $so['quantity_ordered'] ? 'RECEIVED' : 'PARTIAL';

        mysqli_query($conn,
            "UPDATE supplier_orders SET
                quantity_received = $received,
                status            = '$new_status',
                received_date     = NOW()
             WHERE supplier_order_id = $so_id"
        );

        // Add received qty to product stock
        mysqli_query($conn,
            "UPDATE products SET stock = stock + $received
             WHERE product_id = " . intval($so['product_id'])
        );

        $success = "Order marked as $new_status. Stock updated.";
    }
}

// ── FETCH DATA ────────────────────────────────────────────────
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$status_where  = $filter_status ? "WHERE s.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'" : '';

$suppliers = mysqli_query($conn,
    "SELECT s.*,
            COUNT(DISTINCT p.product_id) AS product_count
     FROM suppliers s
     LEFT JOIN products p ON p.supplier_id = s.supplier_id
     $status_where
     GROUP BY s.supplier_id
     ORDER BY s.status ASC, s.supplier_name ASC"
);

// Products for restock dropdown (grouped by supplier)
$all_products = mysqli_query($conn,
    "SELECT p.product_id, p.product_name, p.stock, p.cost_price, p.supplier_id,
            s.supplier_name
     FROM products p
     LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
     WHERE p.is_visible = 1
     ORDER BY s.supplier_name, p.product_name"
);
$products_by_supplier = [];
while ($pr = mysqli_fetch_assoc($all_products)) {
    $products_by_supplier[$pr['supplier_id']][] = $pr;
}

// Recent restock orders
$restock_orders = mysqli_query($conn,
    "SELECT so.*, s.supplier_name, p.product_name, p.stock AS current_stock
     FROM supplier_orders so
     JOIN suppliers s ON so.supplier_id = s.supplier_id
     JOIN products  p ON so.product_id  = p.product_id
     ORDER BY so.order_date DESC
     LIMIT 20"
);

// Edit mode
$edit_supplier = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_supplier = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM suppliers WHERE supplier_id = " . intval($_GET['edit'])
    ));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers | Tinkercom Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="assets/tinkercom-favicon.png" type="image/x-icon">
</head>
<body class="admin-body">
<div class="admin-layout">

    <?php include 'admin-sidebar.php'; ?>

    <main class="admin-main">

        <!-- Top bar -->
        <div class="admin-topbar">
            <div>
                <h1> Supplier Management</h1>
                <p class="topbar-sub">Manage suppliers and restocking orders</p>
            </div>
            <button class="admin-btn-primary" onclick="openModal('addSupplierModal')">
                + Add Supplier
            </button>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════════════════
             SUPPLIER LIST
             ═══════════════════════════════════════════════════ -->
        <div class="admin-table-section">
            <div class="admin-table-header">
                <h2>Suppliers</h2>
                <!-- Filter tabs -->
                <div class="filter-tabs">
                    <a href="admin-suppliers.php"
                       class="filter-tab <?php echo $filter_status === '' ? 'active' : ''; ?>">All</a>
                    <a href="admin-suppliers.php?status=Active"
                       class="filter-tab <?php echo $filter_status === 'Active' ? 'active' : ''; ?>">Active</a>
                    <a href="admin-suppliers.php?status=Inactive"
                       class="filter-tab <?php echo $filter_status === 'Inactive' ? 'active' : ''; ?>">Inactive</a>
                </div>
            </div>

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Products Supplied</th>
                        <th>Linked Products</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($suppliers) > 0):
                      while ($s = mysqli_fetch_assoc($suppliers)): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($s['supplier_name']); ?></strong>
                            <?php if ($s['address']): ?>
                                <br><small style="color:#888;"><?php echo htmlspecialchars($s['address']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($s['contact_person'] ?? '—'); ?></td>
                        <td>
                            <?php if ($s['email']): ?>
                                <a href="mailto:<?php echo $s['email']; ?>" style="color:var(--primary-color);">
                                    <?php echo htmlspecialchars($s['email']); ?>
                                </a>
                            <?php else: echo '—'; endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($s['phone'] ?? '—'); ?></td>
                        <td>
                            <small><?php echo htmlspecialchars($s['products_supplied'] ?? '—'); ?></small>
                        </td>
                        <td>
                            <span class="stat-chip"><?php echo $s['product_count']; ?> products</span>
                        </td>
                        <td>
                            <span class="status-badge <?php echo strtolower($s['status']); ?>">
                                <?php echo $s['status']; ?>
                            </span>
                        </td>
                        <td class="action-btns">
                            <!-- Edit -->
                            <a href="admin-suppliers.php?edit=<?php echo $s['supplier_id']; ?>"
                               class="btn-action btn-edit">Edit</a>
                            <!-- Restock -->
                            <button class="btn-action btn-view"
                                    onclick="openRestockModal(<?php echo $s['supplier_id']; ?>, '<?php echo htmlspecialchars($s['supplier_name'], ENT_QUOTES); ?>')">
                                Restock
                            </button>
                            <!-- Toggle status -->
                            <a href="admin-suppliers.php?toggle=<?php echo $s['supplier_id']; ?>"
                               class="btn-action <?php echo $s['status'] === 'Active' ? 'btn-danger' : 'btn-success'; ?>"
                               onclick="return confirm('<?php echo $s['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?> this supplier?')">
                                <?php echo $s['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="8" class="no-data">No suppliers found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ═══════════════════════════════════════════════════
             RESTOCK ORDER HISTORY
             ═══════════════════════════════════════════════════ -->
        <div class="admin-table-section" style="margin-top:28px;">
            <div class="admin-table-header">
                <h2> Restock Order History</h2>
                <small style="color:#888;">Showing last 20 orders</small>
            </div>

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Supplier</th>
                        <th>Product</th>
                        <th>Qty Ordered</th>
                        <th>Qty Received</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                        <th>Expected</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($restock_orders) > 0):
                      while ($ro = mysqli_fetch_assoc($restock_orders)): ?>
                    <tr>
                        <td>#<?php echo $ro['supplier_order_id']; ?></td>
                        <td><?php echo htmlspecialchars($ro['supplier_name']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($ro['product_name']); ?>
                            <br><small style="color:#888;">Current stock: <?php echo $ro['current_stock']; ?></small>
                        </td>
                        <td><?php echo $ro['quantity_ordered']; ?></td>
                        <td><?php echo $ro['quantity_received']; ?></td>
                        <td>₱<?php echo number_format($ro['unit_cost'], 2); ?></td>
                        <td>₱<?php echo number_format($ro['quantity_ordered'] * $ro['unit_cost'], 2); ?></td>
                        <td>
                            <?php echo $ro['expected_date']
                                ? date('M j, Y', strtotime($ro['expected_date']))
                                : '—'; ?>
                        </td>
                        <td>
                            <span class="status-badge <?php echo strtolower($ro['status']); ?>">
                                <?php echo $ro['status']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($ro['status'] === 'PENDING' || $ro['status'] === 'PARTIAL'): ?>
                                <button class="btn-action btn-success"
                                        onclick="openReceiveModal(<?php echo $ro['supplier_order_id']; ?>,
                                                 '<?php echo htmlspecialchars($ro['product_name'], ENT_QUOTES); ?>',
                                                 <?php echo $ro['quantity_ordered']; ?>)">
                                    Mark Received
                                </button>
                            <?php else: ?>
                                <span style="color:#888;font-size:12px;">Done</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="10" class="no-data">No restock orders yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>


<!-- ═══════════════════════════════════════════════════════════
     MODAL: ADD SUPPLIER
     ═══════════════════════════════════════════════════════════ -->
<div class="admin-modal-overlay" id="addSupplierModal" style="display:none;">
    <div class="admin-modal">
        <div class="admin-modal-header">
            <h3>Add New Supplier</h3>
            <button class="modal-close" onclick="closeModal('addSupplierModal')">✕</button>
        </div>
        <form method="POST" action="admin-suppliers.php" class="admin-modal-form">
            <input type="hidden" name="action" value="add_supplier">

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Supplier Name <span class="required">*</span></label>
                    <input type="text" name="supplier_name" placeholder="e.g. Epson Philippines" required>
                </div>
                <div class="admin-form-group">
                    <label>Contact Person</label>
                    <input type="text" name="contact_person" placeholder="e.g. Juan dela Cruz">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="supplier@email.com">
                </div>
                <div class="admin-form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" placeholder="e.g. 09171234567">
                </div>
            </div>

            <div class="admin-form-group">
                <label>Address</label>
                <input type="text" name="address" placeholder="e.g. BGC, Taguig City">
            </div>

            <div class="admin-form-group">
                <label>Products Supplied</label>
                <input type="text" name="products_supplied"
                       placeholder="e.g. Epson printers, Epson inks">
                <small class="ai-hint">Brief description of what this supplier provides</small>
            </div>

            <div class="admin-modal-actions">
                <button type="button" class="btn-action btn-cancel"
                        onclick="closeModal('addSupplierModal')">Cancel</button>
                <button type="submit" class="admin-btn-primary">Add Supplier</button>
            </div>
        </form>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     MODAL: EDIT SUPPLIER (only rendered if ?edit= is set)
     ═══════════════════════════════════════════════════════════ -->
<?php if ($edit_supplier): ?>
<div class="admin-modal-overlay" id="editSupplierModal" style="display:flex;">
    <div class="admin-modal">
        <div class="admin-modal-header">
            <h3>Edit Supplier</h3>
            <a href="admin-suppliers.php" class="modal-close">✕</a>
        </div>
        <form method="POST" action="admin-suppliers.php" class="admin-modal-form">
            <input type="hidden" name="action"      value="edit_supplier">
            <input type="hidden" name="supplier_id" value="<?php echo $edit_supplier['supplier_id']; ?>">

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Supplier Name <span class="required">*</span></label>
                    <input type="text" name="supplier_name"
                           value="<?php echo htmlspecialchars($edit_supplier['supplier_name']); ?>" required>
                </div>
                <div class="admin-form-group">
                    <label>Contact Person</label>
                    <input type="text" name="contact_person"
                           value="<?php echo htmlspecialchars($edit_supplier['contact_person'] ?? ''); ?>">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Email</label>
                    <input type="email" name="email"
                           value="<?php echo htmlspecialchars($edit_supplier['email'] ?? ''); ?>">
                </div>
                <div class="admin-form-group">
                    <label>Phone</label>
                    <input type="text" name="phone"
                           value="<?php echo htmlspecialchars($edit_supplier['phone'] ?? ''); ?>">
                </div>
            </div>

            <div class="admin-form-group">
                <label>Address</label>
                <input type="text" name="address"
                       value="<?php echo htmlspecialchars($edit_supplier['address'] ?? ''); ?>">
            </div>

            <div class="admin-form-group">
                <label>Products Supplied</label>
                <input type="text" name="products_supplied"
                       value="<?php echo htmlspecialchars($edit_supplier['products_supplied'] ?? ''); ?>">
            </div>

            <div class="admin-modal-actions">
                <a href="admin-suppliers.php" class="btn-action btn-cancel">Cancel</a>
                <button type="submit" class="admin-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>


<!-- ═══════════════════════════════════════════════════════════
     MODAL: CREATE RESTOCK ORDER
     ═══════════════════════════════════════════════════════════ -->
<div class="admin-modal-overlay" id="restockModal" style="display:none;">
    <div class="admin-modal">
        <div class="admin-modal-header">
            <h3>Create Restock Order</h3>
            <button class="modal-close" onclick="closeModal('restockModal')">✕</button>
        </div>
        <form method="POST" action="admin-suppliers.php" class="admin-modal-form">
            <input type="hidden" name="action"      value="restock">
            <input type="hidden" name="supplier_id" id="restock_supplier_id">

            <div class="admin-form-group">
                <label>Supplier</label>
                <input type="text" id="restock_supplier_name" disabled
                       style="background:#f5f5f5;">
            </div>

            <div class="admin-form-group">
                <label>Product to Restock <span class="required">*</span></label>
                <select name="product_id" id="restock_product_select" required>
                    <option value="">-- Select Product --</option>
                </select>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Quantity to Order <span class="required">*</span></label>
                    <input type="number" name="quantity_ordered" min="1" placeholder="e.g. 10" required>
                </div>
                <div class="admin-form-group">
                    <label>Unit Cost (₱) <span class="required">*</span></label>
                    <input type="number" name="unit_cost" id="restock_unit_cost"
                           min="0.01" step="0.01" placeholder="e.g. 1500.00" required>
                </div>
            </div>

            <div class="admin-form-group">
                <label>Expected Delivery Date</label>
                <input type="date" name="expected_date"
                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
            </div>

            <div class="admin-form-group">
                <label>Notes</label>
                <textarea name="notes" rows="2"
                          placeholder="Optional notes for this order..."></textarea>
            </div>

            <div class="admin-modal-actions">
                <button type="button" class="btn-action btn-cancel"
                        onclick="closeModal('restockModal')">Cancel</button>
                <button type="submit" class="admin-btn-primary">Create Order</button>
            </div>
        </form>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     MODAL: MARK ORDER AS RECEIVED
     ═══════════════════════════════════════════════════════════ -->
<div class="admin-modal-overlay" id="receiveModal" style="display:none;">
    <div class="admin-modal" style="max-width:420px;">
        <div class="admin-modal-header">
            <h3>Mark Order as Received</h3>
            <button class="modal-close" onclick="closeModal('receiveModal')">✕</button>
        </div>
        <form method="POST" action="admin-suppliers.php" class="admin-modal-form">
            <input type="hidden" name="action"            value="receive_order">
            <input type="hidden" name="supplier_order_id" id="receive_order_id">

            <p id="receive_product_label" style="font-weight:600;margin-bottom:12px;"></p>

            <div class="admin-form-group">
                <label>Quantity Actually Received <span class="required">*</span></label>
                <input type="number" name="quantity_received" id="receive_qty"
                       min="1" required>
                <small class="ai-hint" id="receive_hint"></small>
            </div>

            <div class="admin-modal-actions">
                <button type="button" class="btn-action btn-cancel"
                        onclick="closeModal('receiveModal')">Cancel</button>
                <button type="submit" class="admin-btn-primary">Confirm Receipt</button>
            </div>
        </form>
    </div>
</div>


<!-- Products by supplier — passed to JS -->
<script>
// Product data grouped by supplier_id, built from PHP
const productsBySupplier = <?php
    $js_data = [];
    foreach ($products_by_supplier as $sup_id => $prods) {
        $js_data[$sup_id] = array_map(fn($p) => [
            'id'        => $p['product_id'],
            'name'      => $p['product_name'],
            'stock'     => $p['stock'],
            'cost'      => $p['cost_price'],
        ], $prods);
    }
    echo json_encode($js_data);
?>;

// ── Modal helpers ──
function openModal(id)  { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

// Close on overlay click
document.querySelectorAll('.admin-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
});

// ── Open restock modal ──
function openRestockModal(supplierId, supplierName) {
    document.getElementById('restock_supplier_id').value   = supplierId;
    document.getElementById('restock_supplier_name').value = supplierName;

    // Populate product dropdown for this supplier
    const select = document.getElementById('restock_product_select');
    select.innerHTML = '<option value="">-- Select Product --</option>';

    const products = productsBySupplier[supplierId] || [];
    if (products.length === 0) {
        const opt = document.createElement('option');
        opt.disabled = true;
        opt.textContent = 'No products linked to this supplier';
        select.appendChild(opt);
    } else {
        products.forEach(p => {
            const opt = document.createElement('option');
            opt.value              = p.id;
            opt.textContent        = p.name + ' (stock: ' + p.stock + ')';
            opt.dataset.cost       = p.cost;
            select.appendChild(opt);
        });
    }

    openModal('restockModal');
}

// Auto-fill unit cost when product is selected
document.getElementById('restock_product_select').addEventListener('change', function() {
    const opt  = this.options[this.selectedIndex];
    const cost = opt.dataset.cost;
    if (cost && cost > 0) {
        document.getElementById('restock_unit_cost').value = parseFloat(cost).toFixed(2);
    }
});

// ── Open receive modal ──
function openReceiveModal(orderId, productName, qtyOrdered) {
    document.getElementById('receive_order_id').value       = orderId;
    document.getElementById('receive_product_label').textContent = 'Product: ' + productName;
    document.getElementById('receive_qty').max              = qtyOrdered;
    document.getElementById('receive_qty').value            = qtyOrdered;
    document.getElementById('receive_hint').textContent     = 'Max: ' + qtyOrdered + ' (ordered qty)';
    openModal('receiveModal');
}
</script>

</body>
</html>