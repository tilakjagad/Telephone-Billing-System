<?php
date_default_timezone_set('Asia/Kolkata');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db_saved = false;
$duplicate_found = false;
$db_error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $telephone_no = (string)trim($_POST['TelephoneNo'] ?? '');
    $calls        = filter_var($_POST['calls'] ?? null, FILTER_VALIDATE_INT);
    $area         = trim($_POST['area'] ?? '');

if ($telephone_no === '' || $calls === false || $calls < 0 || !in_array($area, ['Urban', 'Rural'], true))  {
        die("<p style='color: red; font-family: sans-serif; padding: 20px;'>Invalid input. Please return to the form and try again.</p>");
    }

    // Explicit date and time formatting
    $formatted_date = (string)date('d/m/Y');$formatted_time = (string)date('H:i');

    // Pattern to check duplicate for current month/year
    $month_year_pattern = '%/' . date('m/Y');

    // 1. Minimum Rental
    $base_charge = ($area === "Urban") ? 500.00 : 300.00;
    $call_charge = 0.00;

    // 2. Call rate tiered calculation
    if ($calls > 1) {
        $billable_calls =$calls - 1;

        if ($area === "Urban") {
            if ($billable_calls <= 100) {
                $call_charge =$billable_calls * 1.20;
            } else {
                $call_charge = (100 * 1.20) + (($billable_calls - 100) * 1.50);
            }
        } else {
            if ($billable_calls <= 100) {
                $call_charge =$billable_calls * 1.00;
            } else {
                $call_charge = (100 * 1.00) + (($billable_calls - 100) * 1.20);
            }
        }
    }

    $total_bill = (float)($base_charge +$call_charge);

    // 3. Database connection
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "telephone";

    try {
        $conn = new mysqli($host, $user,$pass, $db);$conn->set_charset("utf8mb4");

        // Duplicate check for the same telephone number in current month
        $check_stmt =$conn->prepare("SELECT 1 FROM bill WHERE TelephoneNo = ? AND `Date` LIKE ?");
        $check_stmt->bind_param("ss", $telephone_no,$month_year_pattern);
        $check_stmt->execute();$check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {$duplicate_found = true;
            $db_error = "A bill has already been generated for " . htmlspecialchars($telephone_no) . " in " . date('F Y') . ". Only one entry per month is allowed.";
        } else {
            // Prepared insert with 6 explicit parameters
            $sql = "INSERT INTO bill (TelephoneNo, calls, area, Bill, `Date`, `Time`) VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($sql);
            $insert_stmt->bind_param("sisdss", $telephone_no, $calls,$area, $total_bill,$formatted_date, $formatted_time);$insert_stmt->execute();
            $insert_stmt->close();$db_saved = true;
        }

        $check_stmt->close();$conn->close();
    } catch (mysqli_sql_exception $e) {
        $db_error =$e->getMessage();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Invoice - <?php echo htmlspecialchars($telephone_no); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --bg-surface: #ffffff;
            --bg-page: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --success-bg: #ecfdf5;
            --success-text: #065f46;
            --success-border: #a7f3d0;
            --error-bg: #fef2f2;
            --error-text: #991b1b;
            --error-border: #fecaca;
            --radius-md: 10px;
            --radius-lg: 16px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .portal-wrapper {
            width: 100%;
            max-width: 480px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 32px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.04), 0 8px 10px -6px rgba(15, 23, 42, 0.02);
        }

        .badge {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--primary);
            background: rgba(37, 99, 235, 0.08);
            padding: 4px 10px;
            border-radius: 999px;
            margin-bottom: 8px;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-main);
        }

        .subtitle {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-top: 4px;
            margin-bottom: 24px;
        }

        .invoice-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }

        .invoice-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.925rem;
            color: var(--text-muted);
        }

        .invoice-row strong {
            color: var(--text-main);
            font-weight: 600;
        }

        .divider {
            height: 1px;
            background-color: var(--border-color);
            margin: 6px 0;
        }

        .total-box {
            background-color: #f8fafc;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .total-label {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .total-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
        }

        .status-alert {
            padding: 14px 16px;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .status-success {
            background-color: var(--success-bg);
            color: var(--success-text);
            border: 1px solid var(--success-border);
        }

        .status-error {
            background-color: var(--error-bg);
            color: var(--error-text);
            border: 1px solid var(--error-border);
        }

        .btn-action {
            display: block;
            text-align: center;
            width: 100%;
            padding: 13px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #fff;
            background-color: var(--primary);
            border: none;
            border-radius: var(--radius-md);
            text-decoration: none;
            transition: background-color 0.2s, transform 0.1s;
        }

        .btn-action:hover {
            background-color: var(--primary-hover);
        }

        .btn-action:active {
            transform: scale(0.99);
        }
    </style>
</head>
<body>

<div class="portal-wrapper">
    <div class="card">
        <span class="badge">Generated Receipt</span>
        <h1>Invoice Summary</h1>
        <p class="subtitle">Generated on <?php echo $formatted_date; ?> at <?php echo$formatted_time; ?></p>

        <?php if ($duplicate_found): ?>
            <div class="status-alert status-error">
                <strong>Entry Restricted:</strong> <?php echo htmlspecialchars($db_error); ?>
            </div>
        <?php elseif ($db_saved): ?>
            <div class="status-alert status-success">
                ✓ Bill generated and saved successfully to database!
            </div>
        <?php else: ?>
            <div class="status-alert status-error">
                ✕ Database Error: <?php echo htmlspecialchars($db_error); ?>
            </div>
        <?php endif; ?>

        <div class="invoice-list">
            <div class="invoice-row">
                <span>Telephone Number</span>
                <strong><?php echo htmlspecialchars($telephone_no); ?></strong>
            </div>
            <div class="invoice-row">
                <span>Region Type</span>
                <strong><?php echo htmlspecialchars($area); ?></strong>
            </div>
            <div class="invoice-row">
                <span>Date</span>
                <strong><?php echo $formatted_date; ?></strong>
            </div>
            <div class="invoice-row">
                <span>Time</span>
                <strong><?php echo $formatted_time; ?></strong>
            </div>
            <div class="invoice-row">
                <span>Total Calls</span>
                <strong><?php echo $calls; ?> calls</strong>
            </div>

            <div class="divider"></div>

            <div class="invoice-row">
                <span>Base Rental Charge</span>
                <strong>₹<?php echo number_format($base_charge, 2); ?></strong>
            </div>
            <div class="invoice-row">
                <span>Call Volume Charges</span>
                <strong>₹<?php echo number_format($call_charge, 2); ?></strong>
            </div>
        </div>

        <div class="total-box">
            <span class="total-label">Total Payable</span>
            <span class="total-value">₹<?php echo number_format($total_bill, 2); ?></span>
        </div>

        <a href="index.html" class="btn-action">Back to Home</a>
    </div>
</div>

</body>
</html>
<?php
} else {
    header("Location: index.html");
    exit();
}
?>