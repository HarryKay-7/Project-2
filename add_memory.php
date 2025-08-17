<?php
// Set PHP runtime limits for large uploads
ini_set('upload_max_filesize', '2G');
ini_set('post_max_size', '2G');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);
ini_set('memory_limit', '512M');

session_start();
require_once 'auth.php';
require_once 'admin_config.php'; // Include the database connection
// Your PDO connection file

// Security check - redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];


$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_memory') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $unlock_date = $_POST['unlock_date'] ?? '';
        $unlock_time = $_POST['unlock_time'] ?? '';
        $security_level = $_POST['security_level'] ?? 'standard';
        $pin_code = trim($_POST['pin_code'] ?? '');
        $sec_question = trim($_POST['security_question'] ?? '');
        $sec_answer = trim($_POST['security_answer'] ?? '');

        // File upload validation
        $file_path = null;
        $file_name = null;
        $file_type = null;
        $file_size = 0;
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "A file is required.";
        } else {
            $upload_dir = 'uploads/' . $user_id . '/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_tmp = $_FILES['file']['tmp_name'];
            $file_name = basename($_FILES['file']['name']);
            $file_type = $_FILES['file']['type'];
            $file_size = $_FILES['file']['size'];
            $file_path = $upload_dir . $file_name;
            if (!move_uploaded_file($file_tmp, $file_path)) {
                $errors[] = "Failed to upload file.";
            }
        }

        // Validation
        if (empty($title)) {
            $errors[] = "Title is required.";
        }
        if (empty($description)) {
            $errors[] = "Description is required.";
        }
        if (empty($unlock_date)) {
            $errors[] = "Unlock date is required.";
        }
        if (empty($unlock_time)) {
            $errors[] = "Unlock time is required.";
        }
        if (!empty($pin_code) && !ctype_digit($pin_code)) {
            $errors[] = "PIN code must be numeric.";
        }
        if (!empty($sec_question) && empty($sec_answer)) {
            $errors[] = "Security answer is required if a question is set.";
        }

        if (empty($errors)) {
            try {
                $unlock_datetime = date('Y-m-d H:i:s', strtotime("$unlock_date $unlock_time"));
                $pin_hash = !empty($pin_code) ? password_hash($pin_code, PASSWORD_DEFAULT) : null;
                $answer_hash = !empty($sec_answer) ? password_hash($sec_answer, PASSWORD_DEFAULT) : null;
                $is_locked = (!empty($pin_hash) || !empty($sec_question)) ? 1 : 0;
                $stmt = $pdo->prepare("
                    INSERT INTO vault_items 
                    (user_id, title, description, file_path, file_name, file_type, file_size, unlock_date, created_at, updated_at, is_locked)
                    VALUES 
                    (:user_id, :title, :description, :file_path, :file_name, :file_type, :file_size, :unlock_date, NOW(), NOW(), :is_locked)
                ");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':title' => $title,
                    ':description' => $description,
                    ':file_path' => $file_path,
                    ':file_name' => $file_name,
                    ':file_type' => $file_type,
                    ':file_size' => $file_size,
                    ':unlock_date' => $unlock_datetime,
                    ':is_locked' => $is_locked
                ]);
                header("Location: dashboard.php?status=success&msg=" . urlencode("Memory saved in vault successfully!"));
                exit();
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Add Memory - MemoryChain</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .drag-area {
            border: 2px dashed #cbd5e1;
            transition: all 0.3s ease;
        }
        .drag-area.active {
            border-color: #818cf8;
            background-color: #e0e7ff;
        }
        .file-preview {
            transition: all 0.3s ease;
        }
        .file-preview:hover {
            transform: translateY(-2px);
        }
        .progress-bar {
            transition: width 0.3s ease;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 min-h-screen font-sans">
    <!-- Navigation Bar -->
    <nav class="bg-white/10 backdrop-blur-lg border-b border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="dashboard.php" class="text-white flex items-center space-x-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Dashboard</span>
                    </a>
                </div>
                <div class="text-white font-medium">
                    Welcome, <?php echo htmlspecialchars($username); ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-xl p-8">
        <h1 class="text-3xl font-bold mb-6 text-gray-900">Welcome, <?php echo htmlspecialchars($username); ?></h1>

        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-8" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_memory" />
            
            <!-- File Upload Area -->
            <div class="mb-8">
                <div class="drag-area bg-gray-50 rounded-lg p-8 text-center cursor-pointer">
                    <input type="file" id="file-input" name="file" required class="hidden" />
                    <div class="flex flex-col items-center gap-4">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400"></i>
                        <div class="text-lg text-gray-600">
                            <span class="font-medium text-indigo-600">Click to upload</span> or drag and drop
                        </div>
                        <p class="text-sm text-gray-500">
                            Only one file per memory
                        </p>
                    </div>
                </div>

                <!-- File Preview Section -->
                <div id="preview-section" class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <!-- Preview items will be added here dynamically -->
                </div>

                <!-- Upload Progress -->
                <div class="mt-4 hidden" id="upload-progress">
                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                        <span>Uploading...</span>
                        <span id="progress-text">0%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-indigo-600 h-2 rounded-full progress-bar" style="width: 0%"></div>
                    </div>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label for="title" class="block text-gray-700 font-semibold mb-2">Title</label>
                    <input id="title" type="text" name="title" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600" 
                           placeholder="Enter memory title" />
                </div>
                
                <div>
                    <label for="category" class="block text-gray-700 font-semibold mb-2">Category</label>
                    <select id="category" name="category" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600">
                        <option value="personal">Personal</option>
                        <option value="work">Work</option>
                        <option value="family">Family</option>
                        <option value="travel">Travel</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label for="description" class="block text-gray-700 font-semibold mb-2">Description</label>
                <textarea id="description" name="description" rows="4" required
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600"
                          placeholder="Describe this memory..."></textarea>
            </div>
            
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label for="unlock_date" class="block text-gray-700 font-semibold mb-2">Unlock Date</label>
                    <input id="unlock_date" type="date" name="unlock_date" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600" />
                </div>
                <div>
                    <label for="unlock_time" class="block text-gray-700 font-semibold mb-2">Unlock Time</label>
                    <input id="unlock_time" type="time" name="unlock_time" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600" />
                </div>
            </div>

            <!-- Security Settings Card -->
            <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-shield-alt mr-2 text-indigo-600"></i>
                    Security Settings
                </h3>
                
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label for="security_level" class="block text-gray-700 font-semibold mb-2">Security Level</label>
                        <select id="security_level" name="security_level"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600">
                            <option value="standard">Standard</option>
                            <option value="private">Private</option>
                            <option value="confidential">Confidential</option>
                        </select>
                    </div>

                    <div>
                        <label for="pin_code" class="block text-gray-700 font-semibold mb-2">PIN Protection</label>
                        <input id="pin_code" type="password" name="pin_code" pattern="\d*" maxlength="6"
                               placeholder="Enter 4-6 digit PIN"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600" />
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-gray-700 font-semibold mb-2">Security Question</label>
                    <div class="grid md:grid-cols-2 gap-4">
                        <select id="security_question" name="security_question"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600">
                            <option value="">Select a security question</option>
                            <option value="pet">What was your first pet's name?</option>
                            <option value="school">What school did you first attend?</option>
                            <option value="city">In which city were you born?</option>
                            <option value="custom">Custom question...</option>
                        </select>
                        <input id="security_answer" type="password" name="security_answer"
                               placeholder="Your answer"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600" />
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end space-x-4">
                <button type="button" onclick="window.location.href='dashboard.php'"
                    class="px-6 py-3 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="px-8 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-lg hover:from-indigo-700 hover:to-purple-700 transition-colors shadow-lg">
                    <i class="fas fa-save mr-2"></i>
                    Save Memory
                </button>
            </div>
        </form>

        <!-- JavaScript for File Upload UI -->
        <script>
            const dragArea = document.querySelector('.drag-area');
            const fileInput = document.querySelector('#file-input');
            const previewSection = document.querySelector('#preview-section');
            const progressBar = document.querySelector('.progress-bar');
            const progressText = document.querySelector('#progress-text');
            const uploadProgress = document.querySelector('#upload-progress');

            // File type icons
            const fileIcons = {
                'image': 'fa-image',
                'video': 'fa-video',
                'audio': 'fa-music',
                'application/pdf': 'fa-file-pdf',
                'application/msword': 'fa-file-word',
                'application/vnd.ms-excel': 'fa-file-excel',
                'text/plain': 'fa-file-alt',
                'default': 'fa-file'
            };

            dragArea.addEventListener('click', () => fileInput.click());

            dragArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                dragArea.classList.add('active');
            });

            dragArea.addEventListener('dragleave', () => {
                dragArea.classList.remove('active');
            });

            dragArea.addEventListener('drop', (e) => {
                e.preventDefault();
                dragArea.classList.remove('active');
                handleFiles(e.dataTransfer.files);
            });

            fileInput.addEventListener('change', () => {
                handleFiles(fileInput.files);
            });

            function handleFiles(files) {
                // Only allow one file
                previewSection.innerHTML = '';
                if (files.length > 0) {
                    const file = files[0];
                    const preview = document.createElement('div');
                    preview.className = 'file-preview bg-gray-50 p-4 rounded-lg border border-gray-200 flex items-center space-x-3';
                    const fileType = file.type.split('/')[0];
                    const iconClass = fileIcons[fileType] || fileIcons[file.type] || fileIcons.default;
                    preview.innerHTML = `
                        <i class="fas ${iconClass} text-gray-400 text-xl"></i>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">${file.name}</p>
                            <p class="text-xs text-gray-500">${formatFileSize(file.size)}</p>
                        </div>
                        <button type="button" class="text-gray-400 hover:text-red-500">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    const removeBtn = preview.querySelector('button');
                    removeBtn.onclick = () => {
                        preview.remove();
                        fileInput.value = '';
                    };
                    previewSection.appendChild(preview);
                }
                uploadProgress.classList.remove('hidden');
                simulateUpload();
            }

            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            function simulateUpload() {
                let progress = 0;
                const interval = setInterval(() => {
                    progress += 5;
                    if (progress > 100) {
                        clearInterval(interval);
                        setTimeout(() => {
                            uploadProgress.classList.add('hidden');
                        }, 1000);
                    } else {
                        progressBar.style.width = progress + '%';
                        progressText.textContent = progress + '%';
                    }
                }, 100);
            }
        </script>
    </div>
</body>
</html>

