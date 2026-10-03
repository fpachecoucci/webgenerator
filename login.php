<?php
session_start();
require_once 'db.php';

// Redireccionar si el usuario ya está logueado
if (isset($_SESSION['idUsuario'])) {
    header("Location: panel.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {
        $stmt = $db->prepare("SELECT idUsuario, email, password FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if ($user['password'] === $password) {
                $_SESSION['idUsuario'] = $user['idUsuario'];
                $_SESSION['email'] = $user['email'];
                header("Location: panel.php");
                exit();
            } else {
                $error = "Contraseña incorrecta.";
            }
        } else {
            $error = "El usuario no existe.";
        }
        $stmt->close();
    } else {
        $error = "Por favor completa todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>webgenerator Francisco Pacheco Ucci</title>
</head>
<body>
    <h1>webgenerator Francisco Pacheco Ucci</h1>

    <?php if (!empty($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <label>Email:</label><br>
        <input type="email" name="email" required><br><br>

        <label>Contraseña:</label><br>
        <input type="password" name="password" required><br><br>

        <button type="submit">Ingresar</button>
    </form>

    <br>
    <a href="register.php">Registrarse</a>
</body>
</html>