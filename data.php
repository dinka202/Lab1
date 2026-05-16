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

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("Не удалось подключиться к базе данных");
}

function logAction($conn, $user_id, $username, $action, $table_name, $record_id = null, $description = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    try {
        $stmt = $conn->prepare("
            INSERT INTO log (user_id, username, action, table_name, record_id, description, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $username,
            $action,
            $table_name,
            $record_id,
            $description,
            $ip
        ]);
    } catch (PDOException $e) {
        error_log("Log error: " . $e->getMessage());
    }
}

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $col1 = $_POST['col1'] ?? '';
            $col2 = $_POST['col2'] ?? '';
            $col3 = $_POST['col3'] ?? '';

            try {
                $stmt = $conn->prepare("INSERT INTO table_data (col1, col2, col3) VALUES (?, ?, ?)");
                $stmt->execute([$col1, $col2, $col3]);

                if ($stmt->rowCount() > 0) {
                    $lastInsertId = $conn->lastInsertId();
                    // Запись в лог
                    logAction($conn, $_SESSION['user']['id'], $_SESSION['user']['username'],
                        'INSERT', 'table_data', $lastInsertId, "Добавлена запись с ID: $lastInsertId");

                    echo json_encode(['success' => true, 'message' => 'Запись добавлена!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Ошибка при добавлении записи.']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()]);
            }
            break;

        case 'update':
            $id = (int)$_POST['id'];
            $col1 = $_POST['col1'] ?? '';
            $col2 = $_POST['col2'] ?? '';
            $col3 = $_POST['col3'] ?? '';

            try {
                $stmt = $conn->prepare("UPDATE table_data SET col1 = ?, col2 = ?, col3 = ? WHERE id = ?");
                $stmt->execute([$col1, $col2, $col3, $id]);

                if ($stmt->rowCount() > 0) {
                    // Запись в лог
                    logAction($conn, $_SESSION['user']['id'], $_SESSION['user']['username'],
                        'UPDATE', 'table_data', $id, "Отредактирована запись с ID: $id");

                    echo json_encode(['success' => true, 'message' => 'Запись обновлена!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Ошибка при обновлении записи.']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()]);
            }
            break;

        case 'delete':
            $id = (int)$_POST['id'];

            try {
                $stmt = $conn->prepare("DELETE FROM table_data WHERE id = ?");
                $stmt->execute([$id]);

                if ($stmt->rowCount() > 0) {
                    // Запись в лог
                    logAction($conn, $_SESSION['user']['id'], $_SESSION['user']['username'],
                        'DELETE', 'table_data', $id, "Удалена запись с ID: $id");

                    echo json_encode(['success' => true, 'message' => 'Запись удалена!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Ошибка при удалении записи.']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()]);
            }
            break;
    }
    exit;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $stmt = $conn->prepare("SELECT * FROM table_data ORDER BY id DESC LIMIT ?, ?");
    $stmt->bindParam(1, $offset, PDO::PARAM_INT);
    $stmt->bindParam(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll();

    $totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM table_data");
    $totalStmt->execute();
    $total = $totalStmt->fetch()['total'];
    $totalPages = ceil($total / $limit);
} catch (PDOException $e) {
    die("Ошибка при получении данных: " . $e->getMessage());
}
?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Данные из таблицы</title>
        <link href="./vendor/twbs/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            .data-container {
                max-width: 1200px;
                margin: 50px auto;
            }
            .pagination {
                justify-content: center;
            }
            .table-responsive {
                margin-top: 20px;
            }
        </style>
    </head>
<body>
<div class="container">
    <div class="data-container">
    <h2 class="text-center mb-4">Данные из таблицы</h2>
    <div class="mb-4 text-center">
        <a href="upload.php" class="btn btn-outline-primary">Загрузить файл</a>
        <a href="logs.php" class="btn btn-outline-secondary">
            Просмотр журнала действий
        </a>
        <a href="index.php" class="btn btn-outline-danger">Выйти</a>
    </div>
    <button class="btn btn-success mb-3" data-toggle="modal" data-target="#addModal">
        Добавить запись
    </button>
    <div class="table-responsive">
    <table class="table table-striped table-hover">
    <thead>
    <tr>
        <th>ID</th>
        <th>Колонка 1</th>
        <th>Колонка 2</th>
        <th>Колонка 3</th>
        <th>Действия</th>
    </tr>
    </thead>
    <tbody>
<?php if (empty($result)): ?>
    <tr>
        <td colspan="5" class="text-center">Данные отсутствуют</td>
    </tr>
<?php else: ?>
    <?php foreach ($result as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['id']) ?></td>
            <td><?= htmlspecialchars($row['col1']) ?></td>
            <td><?= htmlspecialchars($row['col2']) ?></td>
            <td><?= htmlspecialchars($row['col3']) ?></td>
            <td>
                <button class="btn btn-sm btn-warning edit-btn"
                    data-id="<?= $row['id'] ?>"
            data-col1="<?= htmlspecialchars($row['col1']) ?>"
            data-col2="<?= htmlspecialchars($row['col2']) ?>"
            data-col3="<?= htmlspecialchars($row['col3']) ?>"
            data-toggle="modal"
            data-target="#editModal">
            Редактировать
        </button>
        <button class="btn btn-sm btn-danger delete-btn"
            data-id="<?= $row['id'] ?>"
            data-toggle="modal"
            data-target="#deleteModal">
            Удалить
        </button>
    </td>
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

<div class="modal fade" id="addModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Добавить новую запись</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addForm">
                    <div class="form-group">
                        <label for="addCol1">Колонка 1:</label>
                        <input type="text" class="form-control" id="addCol1" name="col1" required>
                    </div>
            <div class="form-group">
                <label for="addCol2">Колонка 2:</label>
                <input type="text" class="form-control" id="addCol2" name="col2" required>
            </div>
            <div class="form-group">
                <label for="addCol3">Колонка 3:</label>
                <input type="text" class="form-control" id="addCol3" name="col3" required>
            </div>
        </form>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
        <button type="button" class="btn btn-primary" id="saveAddBtn">Сохранить</button>
    </div>
</div>
</div>
</div>
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редактировать запись</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" name="id" id="editId">
            <div class="form-group">
                <label for="editCol1">Колонка 1:</label>
                <input type="text" class="form-control" id="editCol1" name="col1" required>
            </div>
            <div class="form-group">
                <label for="editCol2">Колонка 2:</label>
                <input type="text" class="form-control" id="editCol2" name="col2" required>
            </div>
            <div class="form-group">
                <label for="editCol3">Колонка 3:</label>
                <input type="text" class="form-control" id="editCol3" name="col3" required>
            </div>
        </form>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
        <button type="button" class="btn btn-primary" id="saveEditBtn">Сохранить изменения</button>
    </div>
</div>
</div>
</div>
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Подтверждение удаления</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Вы уверены, что хотите удалить эту запись? Это действие нельзя отменить.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Удалить</button>
            </div>
        </div>
    </div>
</div>
 <script src="./vendor/components/jquery/jquery.slim.min.js"></script>
 <script src="./vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script>
let deleteId = null;

$('.edit-btn').on('click', function() {
    const $btn = $(this);
    $('#editId').val($btn.data('id'));
    $('#editCol1').val($btn.data('col1'));
    $('#editCol2').val($btn.data('col2'));
    $('#editCol3').val($btn.data('col3'));
});

$('#saveAddBtn').on('click', function() {
    const formData = $('#addForm').serializeArray();
    formData.push({name: 'action', value: 'add'});

    $.post('data.php', formData, function(response) {
        const res = JSON.parse(response);
        if (res.success) {
            $('#addModal').modal('hide');
            alert(res.message);
            location.reload();
        } else {
            alert('Ошибка: ' + res.message);
        }
    });
});

$('#saveEditBtn').on('click', function() {
    const formData = $('#editForm').serializeArray();
    formData.push({name: 'action', value: 'update'});

    $.post('data.php', formData, function(response) {
        const res = JSON.parse(response);
        if (res.success) {
            $('#editModal').modal('hide');
            alert(res.message);
            location.reload();
        } else {
            alert('Ошибка: ' + res.message);
        }
    });
});

$('.delete-btn').on('click', function() {
    deleteId = $(this).data('id');
    $('#deleteModal').modal('show');
});

$('#confirmDeleteBtn').on('click', function() {
    $.post('data.php', {action: 'delete', id: deleteId}, function(response) {
        const res = JSON.parse(response);
        if (res.success) {
            $('#deleteModal').modal('hide');
            alert(res.message);
            location.reload();
        } else {
            alert('Ошибка: ' + res.message);
        }
    });
});
</script>
</body>
</html>