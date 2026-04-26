
<?php
require_once 'db_connection.php';
redirectIfNotLoggedIn();

if ($_SESSION['user_role'] !== 'parent') {
    header('Location: dashboard.php');
    exit;
}

$user_email = $_SESSION['user_email'];

$children = $pdo->prepare("
    SELECT s.*, c.class_name, c.section 
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.parent_email = ?
");
$children->execute([$user_email]);
$children = $children->fetchAll();

// Sample fee structure
$fees = [
    'Monthly Tuition' => 5000,
    'Annual Fee' => 12000,
    'Exam Fee' => 2000,
    'Library Fee' => 1000
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Payment - DSR</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .container { padding: 20px; max-width: 800px; margin: 0 auto; }
        .child-card { background: var(--bg); padding: 20px; border-radius: 16px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .pay-btn { background: var(--teal); color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: var(--accent); color: white; text-decoration: none; border-radius: 8px; }
        .total { font-size: 18px; font-weight: bold; margin-top: 15px; padding-top: 10px; border-top: 2px solid var(--border); }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h1>Fee Management</h1>
        
        <?php foreach($children as $child): ?>
        <div class="child-card">
            <h3><?php echo $child['name']; ?> (Roll No: <?php echo $child['roll_no']; ?>)</h3>
            <p>Class: <?php echo $child['class_name'] . ($child['section'] ? '-' . $child['section'] : ''); ?></p>
            
            <table>
                <thead><tr><th>Fee Type</th><th>Amount (Rs)</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach($fees as $type => $amount): 
                        $status = rand(0,1) ? 'Pending' : 'Paid';
                    ?>
                    <tr>
                        <td><?php echo $type; ?></td>
                        <td>Rs <?php echo number_format($amount); ?></td>
                        <td style="color: <?php echo $status === 'Paid' ? 'green' : 'orange'; ?>"><?php echo $status; ?></td>
                        <td>
                            <?php if($status === 'Pending'): ?>
                            <button class="pay-btn" onclick="payFee('<?php echo $child['name']; ?>', '<?php echo $type; ?>', <?php echo $amount; ?>)">Pay Now</button>
                            <?php else: ?>
                            ✓ Paid
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="total">Total Outstanding: Rs 8,000</div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <script>
        function payFee(childName, feeType, amount) {
            alert(`Payment initiated for ${childName}\nFee Type: ${feeType}\nAmount: Rs ${amount}\n\nThis is a demo. In production, you would be redirected to payment gateway.`);
        }
    </script>
</body>
</html>