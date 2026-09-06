<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['admin', 'super admin']);
requireFinancialPermission('gl.manage');

$page_title = 'Chart of Accounts';
include __DIR__ . '/../../includes/header.php';

// Account code max length (must match DB column chart_of_accounts.account_code)
define('ACCOUNT_CODE_MAX_LENGTH', 20);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_code = isset($_POST['account_code']) ? trim($_POST['account_code']) : '';
    if (strlen($account_code) > ACCOUNT_CODE_MAX_LENGTH) {
        $_SESSION['error'] = "Account code must be " . ACCOUNT_CODE_MAX_LENGTH . " characters or less.";
    } elseif (isset($_POST['add_account'])) {
        try {
            $query = "INSERT INTO chart_of_accounts (account_code, account_name, account_type, sub_type, normal_balance, description) 
                      VALUES (:account_code, :account_name, :account_type, :sub_type, :normal_balance, :description)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'account_code' => $account_code,
                'account_name' => $_POST['account_name'],
                'account_type' => $_POST['account_type'],
                'sub_type' => $_POST['sub_type'] ?? null,
                'normal_balance' => $_POST['normal_balance'],
                'description' => $_POST['description'] ?? null
            ]);
            $_SESSION['success'] = "Account added successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding account: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_account'])) {
        try {
            $query = "UPDATE chart_of_accounts 
                      SET account_code = :account_code, account_name = :account_name, account_type = :account_type, 
                          sub_type = :sub_type, normal_balance = :normal_balance, description = :description,
                          is_active = :is_active
                      WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'id' => $_POST['account_id'],
                'account_code' => $account_code,
                'account_name' => $_POST['account_name'],
                'account_type' => $_POST['account_type'],
                'sub_type' => $_POST['sub_type'] ?? null,
                'normal_balance' => $_POST['normal_balance'],
                'description' => $_POST['description'] ?? null,
                'is_active' => $_POST['is_active'] ?? 0
            ]);
            $_SESSION['success'] = "Account updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating account: " . $e->getMessage();
        }
    }
}

// Get chart of accounts
try {
    $query = "SELECT * FROM chart_of_accounts ORDER BY account_code";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $accounts = [];
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        Chart of Accounts
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Manage the chart of accounts for the financial system
    </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Add Account Form -->
    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add New Account</h3>
            
            <form method="POST">
                <div class="space-y-4">
                    <div>
                        <label for="account_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Account Code <span class="text-gray-500 font-normal">(max 20 characters)</span></label>
                        <input type="text" id="account_code" name="account_code" required maxlength="20"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="account_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Account Name</label>
                        <input type="text" id="account_name" name="account_name" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="account_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Account Type</label>
                        <select id="account_type" name="account_type" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Type</option>
                            <option value="Asset">Asset</option>
                            <option value="Liability">Liability</option>
                            <option value="Equity">Equity</option>
                            <option value="Revenue">Revenue</option>
                            <option value="Expense">Expense</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="sub_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sub Type</label>
                        <input type="text" id="sub_type" name="sub_type"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="normal_balance" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Normal Balance</label>
                        <select id="normal_balance" name="normal_balance" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Balance</option>
                            <option value="Debit">Debit</option>
                            <option value="Credit">Credit</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                        <textarea id="description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"></textarea>
                    </div>
                    
                    <div>
                        <button type="submit" name="add_account" class="w-full px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700">
                            Add Account
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Accounts List -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Accounts List</h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Code</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Normal Balance</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($account['account_code']); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-300"><?php echo htmlspecialchars($account['account_name']); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-300"><?php echo htmlspecialchars($account['account_type']); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-300"><?php echo htmlspecialchars($account['normal_balance']); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                <?php if ($account['is_active']): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                <button onclick="editAccount(<?php echo htmlspecialchars(json_encode($account)); ?>)" title="Edit" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300">
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

<!-- Edit Account Modal -->
<div id="editAccountModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Edit Account</h3>
            
            <form method="POST" id="editAccountForm">
                <input type="hidden" name="account_id" id="edit_account_id">
                
                <div class="space-y-4">
                    <div>
                        <label for="edit_account_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Account Code <span class="text-gray-500 font-normal">(max 20 characters)</span></label>
                        <input type="text" id="edit_account_code" name="account_code" required maxlength="20"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="edit_account_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Account Name</label>
                        <input type="text" id="edit_account_name" name="account_name" required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="edit_account_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Account Type</label>
                        <select id="edit_account_type" name="account_type" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Type</option>
                            <option value="Asset">Asset</option>
                            <option value="Liability">Liability</option>
                            <option value="Equity">Equity</option>
                            <option value="Revenue">Revenue</option>
                            <option value="Expense">Expense</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="edit_sub_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sub Type</label>
                        <input type="text" id="edit_sub_type" name="sub_type"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="edit_normal_balance" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Normal Balance</label>
                        <select id="edit_normal_balance" name="normal_balance" required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Balance</option>
                            <option value="Debit">Debit</option>
                            <option value="Credit">Credit</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="edit_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                        <textarea id="edit_description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"></textarea>
                    </div>
                    
                    <div>
                        <label for="edit_is_active" class="inline-flex items-center">
                            <input type="checkbox" id="edit_is_active" name="is_active" value="1" class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Active</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeEditModal()" title="Cancel" class="flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                        <svg data-lucide="x" class="w-5 h-5"></svg>
                    </button>
                    <button type="submit" name="update_account" title="Update Account" class="flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700">
                        <svg data-lucide="save" class="w-5 h-5"></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editAccount(account) {
    document.getElementById('edit_account_id').value = account.id;
    document.getElementById('edit_account_code').value = account.account_code;
    document.getElementById('edit_account_name').value = account.account_name;
    document.getElementById('edit_account_type').value = account.account_type;
    document.getElementById('edit_sub_type').value = account.sub_type || '';
    document.getElementById('edit_normal_balance').value = account.normal_balance;
    document.getElementById('edit_description').value = account.description || '';
    document.getElementById('edit_is_active').checked = account.is_active == 1;
    
    document.getElementById('editAccountModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editAccountModal').classList.add('hidden');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editAccountModal');
    if (event.target === modal) {
        closeEditModal();
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>