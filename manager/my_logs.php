<?php
// Start buffering to allow secure PRG/Redirects if needed
ob_start();
require_once 'header.php';
require_once '../includes/db.php';

$manager_id = $_SESSION['id'] ?? 0;

// PRG is handled cleanly via the natural GET request for search/filters
$filter_type = $_GET['filter_type'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$search_query = trim($_GET['search'] ?? '');

// Base logic: Get transactions that specifically have this manager's ID stamped in the description
$mgr_pattern = "%By Manager ID: " . $manager_id . "%";
$where_clauses = ["t.description LIKE :mgr_pattern"];
$params = [':mgr_pattern' => $mgr_pattern];

if ($filter_type === 'recharge') {
    $where_clauses[] = "t.type = 'credit'";
} elseif ($filter_type === 'pos') {
    $where_clauses[] = "t.type = 'debit'";
}

if (!empty($start_date)) {
    $where_clauses[] = "DATE(t.created_at) >= :start_date";
    $params[':start_date'] = $start_date;
}

if (!empty($end_date)) {
    $where_clauses[] = "DATE(t.created_at) <= :end_date";
    $params[':end_date'] = $end_date;
}

if (!empty($search_query)) {
    // Group search conditions properly
    $where_clauses[] = "(m.name LIKE :search OR m.member_id LIKE :search OR t.description LIKE :search)";
    $params[':search'] = "%$search_query%";
}

$where_sql = implode(' AND ', $where_clauses);

// Changed to LEFT JOIN to prevent data loss if member details are missing/deleted
$sql = "SELECT t.*, m.name as member_name, m.member_id as member_code 
        FROM transactions t 
        LEFT JOIN members m ON t.member_id = m.id 
        WHERE $where_sql 
        ORDER BY t.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_msg = "Could not fetch logs.";
    $logs = [];
}

ob_end_flush();
?>

<style>
    :root {
        --skeleton-base: #e2e8f0;
        --skeleton-highlight: #f8fafc;
    }

    /* Skeleton Loader Styling */
    @keyframes shimmer {
        0% { background-position: -1000px 0; }
        100% { background-position: 1000px 0; }
    }
    .skeleton-box {
        background: var(--skeleton-base);
        background-image: linear-gradient(90deg, var(--skeleton-base) 0px, var(--skeleton-highlight) 50%, var(--skeleton-base) 100%);
        background-size: 1000px 100%;
        animation: shimmer 2s infinite linear;
        border-radius: 8px;
    }
    .sk-header { height: 40px; width: 30%; margin-bottom: 20px; }
    .sk-filter { height: 60px; width: 100%; margin-bottom: 30px; border-radius: 12px; }
    .sk-table { height: 400px; width: 100%; border-radius: 12px; }

    /* Page UI Styling */
    .card-custom { border-radius: 16px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .table-custom th { border-top: none; background: #f8fafc; color: #475569; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px; white-space: nowrap;}
    .table-custom td { vertical-align: middle; color: #1e293b; font-weight: 500;}
    
    .badge-pos { background-color: #fef2f2; color: #ef4444; border: 1px solid #fca5a5; }
    .badge-recharge { background-color: #f0fdf4; color: #16a34a; border: 1px solid #86efac; }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    #page-content { animation: fadeIn 0.5s ease forwards; display: none; }
</style>

<div id="page-skeleton">
    <div class="sk-header skeleton-box"></div>
    <div class="sk-filter skeleton-box"></div>
    <div class="sk-table skeleton-box"></div>
</div>

<div id="page-content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h2 class="font-weight-bold text-dark m-0"><i class="fas fa-clipboard-list text-primary mr-2"></i> My Logs</h2>
            <p class="text-muted m-0 mt-1">Review the wallet recharges and POS orders authorized by your manager account.</p>
        </div>
    </div>

    <div class="card card-custom mb-4">
        <div class="card-body bg-light rounded" style="border: 1px solid #e2e8f0;">
            <form action="my_logs.php" method="GET" class="row align-items-end" id="filterForm">
                <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                    <label class="text-muted small font-weight-bold text-uppercase">Log Type</label>
                    <select name="filter_type" class="form-control shadow-sm" style="border-radius: 10px;">
                        <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Logs</option>
                        <option value="recharge" <?= $filter_type === 'recharge' ? 'selected' : '' ?>>Wallet Recharges</option>
                        <option value="pos" <?= $filter_type === 'pos' ? 'selected' : '' ?>>POS Orders</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-3 mb-md-0">
                    <label class="text-muted small font-weight-bold text-uppercase">Start Date</label>
                    <input type="date" name="start_date" class="form-control shadow-sm" style="border-radius: 10px;" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-2 col-sm-6 mb-3 mb-md-0">
                    <label class="text-muted small font-weight-bold text-uppercase">End Date</label>
                    <input type="date" name="end_date" class="form-control shadow-sm" style="border-radius: 10px;" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                    <label class="text-muted small font-weight-bold text-uppercase">Realtime Search</label>
                    <input type="text" id="searchInput" name="search" class="form-control shadow-sm" style="border-radius: 10px;" placeholder="Type to filter instantly..." value="<?= htmlspecialchars($search_query) ?>">
                </div>
                <div class="col-md-2 col-sm-12 d-flex mt-3 mt-md-0">
                    <button type="submit" class="btn btn-primary w-100 font-weight-bold shadow-sm" style="border-radius: 10px;">
                        <i class="fas fa-filter mr-1"></i> Filter
                    </button>
                    <a href="my_logs.php" class="btn btn-light border ml-2 shadow-sm" style="border-radius: 10px;" title="Clear Filters">
                        <i class="fas fa-sync-alt"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-custom">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-custom table-hover mb-0" id="logsTable">
                    <thead>
                        <tr>
                            <th class="pl-4">Date & Time</th>
                            <th>Member Details</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th class="pr-4">Description</th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $log): 
                                $is_pos = strpos($log['description'], 'POS') !== false || $log['type'] === 'debit';
                                $badge_class = $is_pos ? 'badge-pos' : 'badge-recharge';
                                $badge_text = $is_pos ? 'POS Order' : 'Wallet Recharge';
                                $icon = $is_pos ? 'fa-cash-register' : 'fa-wallet';
                                
                                // Handle cases where member might be missing (LEFT JOIN fallback)
                                $memberName = $log['member_name'] ?? 'Unknown Member';
                                $memberCode = $log['member_code'] ?? 'N/A';
                            ?>
                            <tr class="log-row">
                                <td class="pl-4 text-muted white-space-nowrap">
                                    <i class="far fa-clock mr-1"></i> <?= date('d M Y, h:i A', strtotime($log['created_at'])) ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($memberName) ?></strong><br>
                                    <small class="text-primary font-weight-bold">ID: <?= htmlspecialchars($memberCode) ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-pill <?= $badge_class ?> px-3 py-2">
                                        <i class="fas <?= $icon ?> mr-1"></i> <?= $badge_text ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="<?= $log['type'] === 'credit' ? 'text-success' : 'text-danger' ?>">
                                        <?= $log['type'] === 'credit' ? '+' : '-' ?>₹<?= number_format($log['amount'], 2) ?>
                                    </strong>
                                </td>
                                <td class="pr-4 text-muted small">
                                    <?= htmlspecialchars($log['description']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <tr id="noResultsRow" style="<?= count($logs) > 0 ? 'display: none;' : '' ?>">
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="fas fa-search fa-3x mb-3 opacity-50"></i>
                                    <h5 id="noResultsText">No logs found matching your criteria.</h5>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Skeleton loader simulation to handle PRG natively
    setTimeout(() => {
        let skeleton = document.getElementById('page-skeleton');
        let content = document.getElementById('page-content');
        
        if (skeleton && content) {
            skeleton.style.transition = 'opacity 0.4s ease';
            skeleton.style.opacity = '0';
            setTimeout(() => { 
                skeleton.style.display = 'none'; 
                content.style.display = 'block'; 
            }, 400);
        }
    }, 600); // Small artificial delay for smooth UX

    // Real-time Search functionality
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('.log-row');
    const noResultsRow = document.getElementById('noResultsRow');

    if(searchInput) {
        searchInput.addEventListener('input', function() {
            const filterText = this.value.toLowerCase().trim();
            let visibleCount = 0;

            tableRows.forEach(row => {
                // Get text content of the entire row
                const rowText = row.textContent.toLowerCase();
                
                if (rowText.includes(filterText)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Toggle "No Results" message
            if (visibleCount === 0) {
                noResultsRow.style.display = '';
            } else {
                noResultsRow.style.display = 'none';
            }
        });
        
        // Prevent form from submitting if user presses 'Enter' in search box to allow pure real-time experience
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
    }
});
</script>

<?php require_once 'footer.php'; ?>