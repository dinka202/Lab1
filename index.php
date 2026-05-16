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

if (isset($_SESSION['user'])) {
    header('Location: upload.php');
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Пожалуйста, заполните все поля';
    } else {
        $database = new Database();
        $conn = $database->getConnection();

        if (!$conn) {
            $error = 'Не удалось подключиться к базе данных';
        } else {
            try {
                $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
                $stmt->execute([$username, $password]);
                $user = $stmt->fetch();

                if ($user) {
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'username' => $user['username']
                    ];

                    header('Location: upload.php');
                    exit;
                } else {
                    $error = 'Неверный логин или пароль';
                }
            } catch (PDOException $e) {
                $error = 'Ошибка при проверке пользователя: ' . $e->getMessage();
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
    <title>Авторизация</title>
    <link href="./vendor/twbs/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
        .login-card {
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
<div class="container">
    <div class="login-container">
        <div class="card login-card">
            <h2 class="text-center mb-4">Вход в систему</h2>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Логин:</label>
                    <input type="text"
                           class="form-control"
                           id="username"
                           name="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           placeholder="Введите ваш логин"
                           required
                           autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Пароль:</label>
                    <input type="password"
                           class="form-control"
                           id="password"
                           name="password"
                           placeholder="Введите ваш пароль"
                           required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Войти</button>
            </form>
        </div>
    </div>
</div>
<script src="./vendor/components/jquery/jquery.slim.min.js"></script>
<script src="./vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
