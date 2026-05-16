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

// Проверка авторизации
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("Не удалось подключиться к базе данных");
}

// Получение логов с пагинацией
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    $stmt = $conn->prepare("SELECT * FROM log ORDER BY id DESC LIMIT ?, ?");
    $stmt->bindParam(1, $offset, PDO::PARAM_INT);
    $stmt->bindParam(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();

    // Подсчёт общего количества записей
    $totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM log");
    $totalStmt->execute();
    $total = $totalStmt->fetch()['total'];
    $totalPages = ceil($total / $limit);
} catch (PDOException $e) {
    die("Ошибка при получении логов: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Журнал действий</title>
    <link href="./vendor/twbs/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .logs-container {
            max-width: 1200px;
            margin: 50px auto;
        }
        .pagination {
            justify-content: center;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="logs-container">
        <h2 class="text-center mb-4">Журнал действий пользователей</h2>
        <div class="mb-4 text-center">
            <a href="data.php" class="btn btn-outline-primary">Вернуться к данным</a>
            <a href="index.php" class="btn btn-outline-danger">Выйти</a>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Пользователь</th>
                    <th>Действие</th>
                    <th>Таблица</th>
                    <th>Запись ID</th>
                    <th>Описание</th>
                    <th>IP‑адрес</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="text-center">Логи отсутствуют</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['id']) ?></td>
                            <td><?= htmlspecialchars($log['username']) ?></td>
                            <td>
                <span class="badge badge-<?=
                $log['action'] === 'INSERT' ? 'success' :
                    ($log['action'] === 'UPDATE' ? 'warning' : 'danger')
                ?>">
        <?= htmlspecialchars($log['action']) ?>
    </span>
                            </td>
                            <td><?= htmlspecialchars($log['table_name']) ?></td>
                            <td><?= $log['record_id'] ? htmlspecialchars($log['record_id']) : '-' ?></td>
                            <td><?= htmlspecialchars($log['description'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($log['ip_address']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>

    </div>
</div>
<script src="./vendor/components/jquery/jquery.slim.min.js"></script>
<script src="./vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
