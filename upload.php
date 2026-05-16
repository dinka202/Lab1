<?php
session_start();
class Database {
    private $host = '127.0.0.1';
    private $dbname = 'myapp';
    private $username = 'root';
    private $password = 'qwerty123_qw';
    private $port = 3308;
    private $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host .
                ";port=" . $this->port .
                ";dbname=" . $this->dbname,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $exception) {
            echo "Ошибка подключения: " . $exception->getMessage();
        }

        return $this->conn;
    }
}
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$uploadError = '';
$uploadSuccess = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadError = 'Ошибка при загрузке файла';
    } else {
        $allowedTypes = [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv'
        ];

        if (!in_array($file['type'], $allowedTypes)) {
            $uploadError = 'Разрешены только Excel (.xls, .xlsx) или CSV (.csv) файлы';
        } else {
            $database = new Database();
            $conn = $database->getConnection();

            if (!$conn) {
                $uploadError = 'Не удалось подключиться к базе данных';
            } else {
                try {
                    $uploadDir = 'uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $fileName = uniqid() . '_' . basename($file['name']);
                    $filePath = $uploadDir . $fileName;

                    if (move_uploaded_file($file['tmp_name'], $filePath)) {
                        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        $data = [];

                        if ($fileExtension === 'csv') {
                            if (($handle = fopen($filePath, 'r')) !== false) {
                                while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                                    $data[] = $row;
                                }
                                fclose($handle);
                            }
                        } else {
                            require_once 'vendor/autoload.php';

                            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                            $worksheet = $spreadsheet->getActiveSheet();
                            $data = $worksheet->toArray();
                        }

                        $skipFirstRow = true;
                        $insertedCount = 0;

                        foreach ($data as $index => $row) {
                            if ($skipFirstRow && $index === 0) {
                                continue;
                            }

                            $col1 = isset($row[0]) ? $conn->quote($row[0]) : 'NULL';
                            $col2 = isset($row[1]) ? $conn->quote($row[1]) : 'NULL';
                            $col3 = isset($row[2]) ? $conn->quote($row[2]) : 'NULL';

                            $sql = "INSERT INTO table_data (col1, col2, col3) VALUES ($col1, $col2, $col3)";

                            if ($conn->exec($sql) !== false) {
                                $insertedCount++;
                            }
                        }

                        $uploadSuccess = "Файл успешно загружен! Добавлено строк: $insertedCount";
                    } else {
                        $uploadError = 'Ошибка перемещения файла';
                    }
                } catch (Exception $e) {
                    $uploadError = 'Ошибка обработки файла: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Загрузка Excel-файла</title>
    <link href="./vendor/twbs/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .upload-container {
            max-width: 600px;
            margin: 100px auto;
        }
        .drop-zone {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        .drop-zone:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .drop-zone.dragover {
            border-color: #007bff;
            background-color: #e9ecef;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="upload-container">
        <h2 class="text-center mb-4">Загрузка Excel-файла</h2>
        <div class="mb-4 text-center">
            <a href="data.php" class="btn btn-outline-secondary">Просмотр данных</a>
            <a href="index.php" class="btn btn-outline-danger">Выйти</a>
        </div>
        <?php if (!empty($uploadError)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($uploadError) ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>
        <?php if (!empty($uploadSuccess)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($uploadSuccess) ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="excel_file">Выберите Excel или CSV файл:</label>
                <div id="dropZone" class="drop-zone">
                    <p>Перетащите файл сюда или нажмите для выбора</p>
                    <input type="file"
                           id="fileInput"
                           name="excel_file"
                           accept=".xls,.xlsx,.csv"
                           style="display: none;"
                           required>
                    <span id="fileNameDisplay" class="text-muted">Файл не выбран</span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Загрузить файл</button>
        </form>
        <div class="mt-4">
            <h5>Требования к файлу:</h5>
            <ul>
                <li>Формат: .xls, .xlsx или .csv</li>
                <li>Максимальный размер: 10 МБ</li>
                <li>Первые 3 столбца будут сохранены в БД</li>
                <li>Первая строка (заголовки) будет пропущена</li>
            </ul>
        </div>
    </div>
</div>
<div class="modal" id="confirmModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Подтверждение загрузки</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Вы уверены, что хотите загрузить этот файл? Данные будут добавлены в базу.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="confirmUploadBtn">Подтвердить загрузку</button>
            </div>
        </div>
    </div>
</div>
<script src="./vendor/components/jquery/jquery.slim.min.js"></script>
<script src="./vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileNameDisplay = document.getElementById('fileNameDisplay');

    dropZone.addEventListener('click', function() {
        fileInput.click();
    });
    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            fileNameDisplay.textContent = this.files[0].name;
        } else {
            fileNameDisplay.textContent = 'Файл не выбран';
        }
    });
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.add('dragover');
        });
    });
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('dragover');
        });
    });
    dropZone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;

        if (files.length > 0) {
            fileInput.files = files;
            fileNameDisplay.textContent = files[0].name;
        }
    });
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!fileInput.files.length) {
            alert('Пожалуйста, выберите файл для загрузки');
            e.preventDefault();
            return;
        }
        $('#confirmModal').modal('show');
        e.preventDefault();
    });
    document.getElementById('confirmUploadBtn').addEventListener('click', function() {
        $('#confirmModal').modal('hide');
        document.querySelector('form').submit();
    });
</script>
</body>
</html>