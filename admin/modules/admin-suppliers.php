<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('ap.manage');

$page_title = 'Suppliers Management';
include __DIR__ . '/../../includes/header.php';

// Max lengths (must match DB: suppliers table)
define('SUPPLIER_CODE_MAX', 50);
define('SUPPLIER_TIN_MAX', 20);
define('SUPPLIER_PAYMENT_TERMS_MAX', 100);
define('SUPPLIER_CONTACT_NUMBER_MAX', 20);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_code = isset($_POST['supplier_code']) ? trim($_POST['supplier_code']) : '';
    $supplier_name = isset($_POST['supplier_name']) ? trim($_POST['supplier_name']) : '';
    $tin = isset($_POST['tin']) ? trim($_POST['tin']) : '';
    $payment_terms = isset($_POST['payment_terms']) ? trim($_POST['payment_terms']) : '';
    $contact_number = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : '';

    $validation_errors = [];
    if (strlen($supplier_code) > SUPPLIER_CODE_MAX) {
        $validation_errors[] = 'Supplier code must be ' . SUPPLIER_CODE_MAX . ' characters or less.';
    }
    if (strlen($tin) > SUPPLIER_TIN_MAX) {
        $validation_errors[] = 'TIN must be ' . SUPPLIER_TIN_MAX . ' characters or less.';
    }
    if (strlen($payment_terms) > SUPPLIER_PAYMENT_TERMS_MAX) {
        $validation_errors[] = 'Payment terms must be ' . SUPPLIER_PAYMENT_TERMS_MAX . ' characters or less.';
    }
    if (strlen($contact_number) > SUPPLIER_CONTACT_NUMBER_MAX) {
        $validation_errors[] = 'Contact number must be ' . SUPPLIER_CONTACT_NUMBER_MAX . ' characters or less.';
    }

    if (!empty($validation_errors)) {
        $_SESSION['error'] = implode(' ', $validation_errors);
    } elseif (isset($_POST['add_supplier'])) {
        try {
            $query = "INSERT INTO suppliers 
                     (supplier_code, supplier_name, tin, payment_terms, contact_person, contact_number, email, address) 
                     VALUES (:supplier_code, :supplier_name, :tin, :payment_terms, :contact_person, :contact_number, :email, :address)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'supplier_code' => $supplier_code,
                'supplier_name' => $supplier_name,
                'tin' => $tin ?: null,
                'payment_terms' => $payment_terms ?: null,
                'contact_person' => isset($_POST['contact_person']) ? trim($_POST['contact_person']) : null,
                'contact_number' => $contact_number ?: null,
                'email' => isset($_POST['email']) ? trim($_POST['email']) : null,
                'address' => isset($_POST['address']) ? trim($_POST['address']) : null
            ]);
            $_SESSION['success'] = "Supplier added successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding supplier: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_supplier'])) {
        try {
            $query = "UPDATE suppliers 
                     SET supplier_code = :supplier_code, supplier_name = :supplier_name, tin = :tin,
                         payment_terms = :payment_terms, contact_person = :contact_person,
                         contact_number = :contact_number, email = :email, address = :address,
                         is_active = :is_active
                     WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'id' => $_POST['supplier_id'],
                'supplier_code' => $supplier_code,
                'supplier_name' => $supplier_name,
                'tin' => $tin ?: null,
                'payment_terms' => $payment_terms ?: null,
                'contact_person' => isset($_POST['contact_person']) ? trim($_POST['contact_person']) : null,
                'contact_number' => $contact_number ?: null,
                'email' => isset($_POST['email']) ? trim($_POST['email']) : null,
                'address' => isset($_POST['address']) ? trim($_POST['address']) : null,
                'is_active' => $_POST['is_active'] ?? 0
            ]);
            $_SESSION['success'] = "Supplier updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating supplier: " . $e->getMessage();
        }
    }
}

// Get suppliers
try {
    $query = "SELECT s.*, 
                     COUNT(ai.id) as invoice_count,
                     COALESCE(SUM(ai.net_amount), 0) as total_amount
              FROM suppliers s
              LEFT JOIN ap_invoices ai ON s.id = ai.supplier_id AND ai.status != 'Cancelled'
              GROUP BY s.id
              ORDER BY s.supplier_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $suppliers = [];
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Suppliers Management
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Manage supplier information and details
    </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Add Supplier Form -->
    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add New Supplier</h3>
            
            <form method="POST">
                <div class="space-y-4">
                    <div>
                        <label for="supplier_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Supplier Code * <span class="text-gray-500 font-normal">(max 50)</span></label>
                        <input type="text" id="supplier_code" name="supplier_code" required maxlength="50"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="supplier_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Supplier Name *</label>
                        <input type="text" id="supplier_name" name="supplier_name" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="tin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">TIN <span class="text-gray-500 font-normal">(max 20)</span></label>
                        <input type="text" id="tin" name="tin" maxlength="20"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                               placeholder="123-456-789-000">
                    </div>
                    
                    <div>
                        <label for="payment_terms" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Terms <span class="text-gray-500 font-normal">(max 100)</span></label>
                        <input type="text" id="payment_terms" name="payment_terms" maxlength="100"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                               placeholder="Net 30, COD, etc.">
                    </div>
                    
                    <div>
                        <label for="contact_person" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Person</label>
                        <input type="text" id="contact_person" name="contact_person"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="contact_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Number <span class="text-gray-500 font-normal">(max 20)</span></label>
                        <input type="text" id="contact_number" name="contact_number" maxlength="20"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input type="email" id="email" name="email"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                        <textarea id="address" name="address" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"></textarea>
                    </div>
                    
                    <div>
                        <button type="submit" name="add_supplier" title="Add Supplier" class="flex items-center justify-center w-full px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700">
                            <svg data-lucide="plus-circle" class="w-5 h-5"></svg>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Suppliers List -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Suppliers List</h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Code</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Supplier Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Contact</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Invoices</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($supplier['supplier_code']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-300">
                                <div><?php echo htmlspecialchars($supplier['contact_person'] ?: 'N/A'); ?></div>
                                <div class="text-xs"><?php echo htmlspecialchars($supplier['contact_number'] ?: ''); ?></div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                <?php echo $supplier['invoice_count']; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-right">
                                ₱<?php echo number_format($supplier['total_amount'], 2); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                <?php if ($supplier['is_active']): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                <button onclick="editSupplier(<?php echo htmlspecialchars(json_encode($supplier)); ?>)" title="Edit" 
                                        class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300">
                                    <svg data-lucide="edit" class="w-5 h-5"></svg>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div id="editSupplierModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Edit Supplier</h3>
            
            <form method="POST" id="editSupplierForm">
                <input type="hidden" name="supplier_id" id="edit_supplier_id">
                
                <div class="space-y-4">
                    <div>
                        <label for="edit_supplier_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Supplier Code * <span class="text-gray-500 font-normal">(max 50)</span></label>
                        <input type="text" id="edit_supplier_code" name="supplier_code" required maxlength="50"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="edit_supplier_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Supplier Name *</label>
                        <input type="text" id="edit_supplier_name" name="supplier_name" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="edit_tin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">TIN <span class="text-gray-500 font-normal">(max 20)</span></label>
                        <input type="text" id="edit_tin" name="tin" maxlength="20"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="edit_payment_terms" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Terms <span class="text-gray-500 font-normal">(max 100)</span></label>
                        <input type="text" id="edit_payment_terms" name="payment_terms" maxlength="100"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="edit_contact_person" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Person</label>
                        <input type="text" id="edit_contact_person" name="contact_person"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="edit_contact_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Number <span class="text-gray-500 font-normal">(max 20)</span></label>
                        <input type="text" id="edit_contact_number" name="contact_number" maxlength="20"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="edit_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input type="email" id="edit_email" name="email"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="edit_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                        <textarea id="edit_address" name="address" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"></textarea>
                    </div>
                    
                    <div>
                        <label for="edit_is_active" class="inline-flex items-center">
                            <input type="checkbox" id="edit_is_active" name="is_active" value="1" 
                                   class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Active</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeEditModal()" title="Cancel" class="flex justify-center items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                        <svg data-lucide="x" class="w-5 h-5"></svg>
                    </button>
                    <button type="submit" name="update_supplier" title="Update Supplier" class="flex justify-center items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700">
                        <svg data-lucide="save" class="w-5 h-5"></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSupplier(supplier) {
    document.getElementById('edit_supplier_id').value = supplier.id;
    document.getElementById('edit_supplier_code').value = supplier.supplier_code;
    document.getElementById('edit_supplier_name').value = supplier.supplier_name;
    document.getElementById('edit_tin').value = supplier.tin || '';
    document.getElementById('edit_payment_terms').value = supplier.payment_terms || '';
    document.getElementById('edit_contact_person').value = supplier.contact_person || '';
    document.getElementById('edit_contact_number').value = supplier.contact_number || '';
    document.getElementById('edit_email').value = supplier.email || '';
    document.getElementById('edit_address').value = supplier.address || '';
    document.getElementById('edit_is_active').checked = supplier.is_active == 1;
    
    document.getElementById('editSupplierModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editSupplierModal').classList.add('hidden');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editSupplierModal');
    if (event.target === modal) {
        closeEditModal();
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>