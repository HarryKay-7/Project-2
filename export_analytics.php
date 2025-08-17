<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'admin_config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
}

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$format = $_GET['format'] ?? 'txt';

try {
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Get basic stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_memories,
            SUM(CASE WHEN is_locked = 0 OR unlock_date <= NOW() THEN 1 ELSE 0 END) as unlocked_memories,
            SUM(file_size) as total_storage
        FROM vault_items 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Create report content
    $report = "MemoryChain Analytics Report\n";
    $report .= "Generated on: " . date('Y-m-d H:i:s') . "\n";
    $report .= "===========================\n\n";
    $report .= "GENERAL STATISTICS\n";
    $report .= "----------------\n";
    $report .= "Total Memories: " . number_format($stats['total_memories']) . "\n";
    $report .= "Unlocked Memories: " . number_format($stats['unlocked_memories']) . "\n";
    $report .= "Total Storage Used: " . formatBytes($stats['total_storage']) . "\n\n";

    // Recent memories
    $stmt = $pdo->prepare("
        SELECT title, created_at, file_type 
        FROM vault_items 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $report .= "RECENT MEMORIES\n";
    $report .= "--------------\n";
    foreach ($recent as $memory) {
        $report .= date('M j, Y', strtotime($memory['created_at'])) . ": " . 
                  $memory['title'] . " (" . $memory['file_type'] . ")\n";
    }

    // Set headers for download
    $filename = 'MemoryChain_Report_' . date('Ymd_His');
    if ($format === 'pdf') {
        // Convert to HTML file instead of PDF
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="' . $filename . '.html"');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>MemoryChain Analytics Report</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
            <div class="flex min-h-screen">
                <!-- Responsive Sidebar -->
                <aside class="hidden md:block w-64 bg-gradient-to-b from-slate-900 to-slate-800 shadow-2xl p-6">
                    <div class="flex items-center space-x-3 mb-8">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 rounded-xl flex items-center justify-center animate-float">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-white">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m0 0v2m0-2h2m-2 0h-2m6-6a6 6 0 11-12 0 6 6 0 0112 0z" />
                            </svg>
                        </div>
                        <span class="text-xl font-bold text-white">MemoryChain</span>
                    </div>
                    <nav class="space-y-1">
                        <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-gradient-to-r from-blue-500 to-purple-600 text-white font-medium shadow-lg">
                            <span>Dashboard</span>
                        </a>
                        <a href="vault_access.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-slate-700 text-gray-300 transition-all duration-200">
                            <span>My Vault</span>
                        </a>
                        <a href="settings.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-slate-700 text-gray-300 transition-all duration-200">
                            <span>Settings</span>
                        </a>
                        <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-slate-700 text-gray-300 transition-all duration-200">
                            <span>Logout</span>
                        </a>
                    </nav>
                </aside>
                <!-- Main Content -->
                <main class="flex-1 flex flex-col items-center justify-center p-6">
                    <div class="w-full max-w-2xl bg-white/90 backdrop-blur-lg rounded-2xl shadow-2xl p-8 border border-gray-200">
                        <h1 class="text-3xl font-extrabold text-gray-900 mb-6 text-center">MemoryChain Analytics Report</h1>
                        <pre class="whitespace-pre-wrap bg-gray-100 p-6 rounded-lg text-gray-800 text-base mb-4"><?php echo htmlspecialchars($report); ?></pre>
                    </div>
                </main>
            </div>
        </body>
        </html>
        <?php
    } else {
        // Plain text file
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '.txt"');
        echo $report;
    }
    
    // Fetch all the analytics data
    // General stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_memories,
            SUM(CASE WHEN is_locked = 0 OR unlock_date <= NOW() THEN 1 ELSE 0 END) as unlocked_memories,
            SUM(file_size) as total_storage,
            COUNT(DISTINCT DATE(created_at)) as active_days,
            MIN(created_at) as first_memory_date
        FROM vault_items 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Memory types
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN file_type LIKE 'image/%' THEN 'Images'
                WHEN file_type LIKE 'video/%' THEN 'Videos'
                WHEN file_type LIKE 'audio/%' THEN 'Audio'
                WHEN file_type LIKE 'application/pdf' THEN 'PDFs'
                ELSE 'Others'
            END as type,
            COUNT(*) as count
        FROM vault_items 
        WHERE user_id = ?
        GROUP BY 
            CASE 
                WHEN file_type LIKE 'image/%' THEN 'Images'
                WHEN file_type LIKE 'video/%' THEN 'Videos'
                WHEN file_type LIKE 'audio/%' THEN 'Audio'
                WHEN file_type LIKE 'application/pdf' THEN 'PDFs'
                ELSE 'Others'
            END
    ");
    $stmt->execute([$user_id]);
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Monthly activity
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as memories_added
        FROM vault_items 
        WHERE user_id = ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    $stmt->execute([$user_id]);
    $monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($format === 'pdf') {
        // Create PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('MemoryChain');
        $pdf->SetAuthor($_SESSION['username']);
        $pdf->SetTitle('Memory Analytics Report');
        
        // Set default header data
        $pdf->SetHeaderData('', 0, 'Memory Analytics Report', 'Generated on ' . date('Y-m-d H:i:s'));
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        
        // Add a page
        $pdf->AddPage();
        
        // Overview Section
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 10, 'Memory Analytics Overview', 0, 1, 'L');
        $pdf->Ln(10);
        
        // Stats Table
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'General Statistics', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 11);
        
        // Format stats in a table
        $pdf->Cell(100, 10, 'Total Memories:', 0, 0);
        $pdf->Cell(0, 10, number_format($stats['total_memories']), 0, 1);
        
        $pdf->Cell(100, 10, 'Unlocked Memories:', 0, 0);
        $pdf->Cell(0, 10, number_format($stats['unlocked_memories']), 0, 1);
        
        $pdf->Cell(100, 10, 'Total Storage Used:', 0, 0);
        $pdf->Cell(0, 10, formatBytes($stats['total_storage']), 0, 1);
        
        $pdf->Cell(100, 10, 'Active Days:', 0, 0);
        $pdf->Cell(0, 10, number_format($stats['active_days']), 0, 1);
        
        $pdf->Ln(10);
        
        // Memory Types Section
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Memory Types Distribution', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 11);
        
        foreach ($types as $type) {
            $pdf->Cell(100, 10, $type['type'] . ':', 0, 0);
            $pdf->Cell(0, 10, number_format($type['count']), 0, 1);
        }
        
        $pdf->Ln(10);
        
        // Monthly Activity Section
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Monthly Activity', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 11);
        
        foreach ($monthly as $month) {
            $pdf->Cell(100, 10, date('F Y', strtotime($month['month'] . '-01')) . ':', 0, 0);
            $pdf->Cell(0, 10, number_format($month['memories_added']) . ' memories added', 0, 1);
        }
        
        // Output the PDF
        $pdf->Output('MemoryChain_Analytics_' . date('Y-m-d') . '.pdf', 'D');
        
    } elseif ($format === 'docx') {
        // Create Word document
        $phpWord = new PhpWord();
        
        // Add styles
        $titleStyle = array('size' => 20, 'bold' => true, 'spaceAfter' => 240);
        $headingStyle = array('size' => 16, 'bold' => true, 'spaceAfter' => 120);
        $subheadingStyle = array('size' => 14, 'bold' => true, 'spaceAfter' => 120);
        $textStyle = array('size' => 12);
        
        // Add title page
        $section = $phpWord->addSection();
        $section->addText('Memory Analytics Report', $titleStyle);
        $section->addText('Generated on ' . date('F j, Y'), $textStyle);
        $section->addTextBreak(2);
        
        // Overview Section
        $section->addText('General Statistics', $headingStyle);
        $table = $section->addTable();
        
        // Add stats
        $table->addRow();
        $table->addCell(3000)->addText('Total Memories:', array('bold' => true));
        $table->addCell(3000)->addText(number_format($stats['total_memories']));
        
        $table->addRow();
        $table->addCell(3000)->addText('Unlocked Memories:', array('bold' => true));
        $table->addCell(3000)->addText(number_format($stats['unlocked_memories']));
        
        $table->addRow();
        $table->addCell(3000)->addText('Total Storage Used:', array('bold' => true));
        $table->addCell(3000)->addText(formatBytes($stats['total_storage']));
        
        $table->addRow();
        $table->addCell(3000)->addText('Active Days:', array('bold' => true));
        $table->addCell(3000)->addText(number_format($stats['active_days']));
        
        $section->addTextBreak(2);
        
        // Memory Types Section
        $section->addText('Memory Types Distribution', $headingStyle);
        $table = $section->addTable();
        
        foreach ($types as $type) {
            $table->addRow();
            $table->addCell(3000)->addText($type['type'] . ':', array('bold' => true));
            $table->addCell(3000)->addText(number_format($type['count']));
        }
        
        $section->addTextBreak(2);
        
        // Monthly Activity Section
        $section->addText('Monthly Activity', $headingStyle);
        $table = $section->addTable();
        
        foreach ($monthly as $month) {
            $table->addRow();
            $table->addCell(4000)->addText(date('F Y', strtotime($month['month'] . '-01')), array('bold' => true));
            $table->addCell(4000)->addText(number_format($month['memories_added']) . ' memories added');
        }
        
        // Save file
        $filename = 'MemoryChain_Analytics_' . date('Y-m-d') . '.docx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save('php://output');
    }
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    header('Location: analytics.php?error=export_failed');
    exit();
}
