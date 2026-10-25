<?php
include 'header.php';
require_once '../includes/db.php';

$staff_id = $_SESSION['id'];

// Joining members table instead of users
$sql = "
    SELECT 
        sh.quantity_served,
        sh.served_at,
        m.name as menu_item_name,
        o.coupon_id,
        u.name as member_name
    FROM serving_history sh
    JOIN order_items oi ON sh.order_item_id = oi.id
    JOIN menu m ON oi.menu_id = m.id
    JOIN orders o ON oi.order_id = o.id
    JOIN members u ON o.member_id = u.id
    WHERE sh.staff_id = ?
    ORDER BY sh.served_at DESC
    LIMIT 100;
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$staff_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .skeleton-wrapper { width: 100%; }
    .skeleton-box {
        background: #e2e5e7;
        background: linear-gradient(90deg, #e2e5e7 25%, #f0f2f3 50%, #e2e5e7 75%);
        background-size: 200% 100%;
        animation: loadingShimmer 1.5s infinite;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    .skeleton-title { height: 30px; width: 30%; margin-bottom: 25px; }
    .skeleton-text { height: 40px; width: 100%; margin-bottom: 10px; }
    
    @keyframes loadingShimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    .profile-card {
        background: #ffffff;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: none;
        overflow: hidden;
    }
    .card-title-modern {
        font-weight: 600;
        color: #333;
        margin-bottom: 20px;
        border-bottom: 2px solid #f4f7f6;
        padding-bottom: 10px;
    }
    
    .table-modern th { border-top: none; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; color: #6c757d; }
    .table-modern td { vertical-align: middle; }
</style>

<div id="skeleton-loader" class="profile-card p-4 mb-4 skeleton-wrapper">
    <div class="skeleton-box skeleton-title"></div>
    <div class="skeleton-box skeleton-text"></div>
    <div class="skeleton-box skeleton-text"></div>
    <div class="skeleton-box skeleton-text"></div>
    <div class="skeleton-box skeleton-text"></div>
</div>

<div id="actual-content" style="display: none;">
    <div class="profile-card p-4">
        <h5 class="card-title-modern text-primary"><i class="fas fa-history mr-2"></i> My Serving History <span class="badge badge-light text-muted float-right">Last 100 Items</span></h5>
        
        <div class="table-responsive rounded shadow-sm">
            <table class="table table-hover table-modern mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>Coupon ID</th>
                        <th>Member Name</th>
                        <th>Item Served</th>
                        <th class="text-center">Qty Served</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <i class="fas fa-utensils fa-3x text-light mb-3"></i>
                                <h6 class="text-muted">You have not served any items yet.</h6>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $entry): ?>
                        <tr>
                            <td class="text-muted"><i class="far fa-clock mr-1"></i> <?php echo date('d M Y, h:i A', strtotime($entry['served_at'])); ?></td>
                            <td class="font-weight-bold text-primary"><?php echo htmlspecialchars($entry['coupon_id']); ?></td>
                            <td class="font-weight-bold"><?php echo htmlspecialchars($entry['member_name']); ?></td>
                            <td><?php echo htmlspecialchars($entry['menu_item_name']); ?></td>
                            <td class="text-center"><span class="badge badge-success px-2 py-1" style="font-size:0.9rem;"><?php echo $entry['quantity_served']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.getElementById('skeleton-loader').style.display = 'none';
        document.getElementById('actual-content').style.display = 'block';
    }, 400);
});
</script>

<?php include 'footer.php'; ?>