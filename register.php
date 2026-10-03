<?php
session_start();
require_once 'db.php';

// Redireccionar si el usuario ya inició sesión
if (isset($_SESSION['idUsuario'])) {
    header("Location: panel.php");
    exit();
}

$error = "";
$exito = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $repeat_password = trim($_POST['repeat_password'] ?? '');

    if ($password !== $repeat_password) {
        $error = "Las contraseñas no coinciden.";
    } else {
        // Verificar si el email ya existe en la BD
        $stmt = $db->prepare("SELECT idUsuario FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "El email ya se encuentra registrado.";
        } else {
            $stmt->close();
            // Insertar el nuevo usuario
            $fecha = date('Y-m-d H:i:s');
            $stmtInsert = $db->prepare("INSERT INTO usuarios (email, password, fechaRegistro) VALUES (?, ?, ?)");
            $stmtInsert->bind_param("sss", $email, $password, $fecha);

            if ($stmtInsert->execute()) {
                header("Location: login.php");
                exit();
            } else {
                $error = "Error al registrar el usuario en la base de datos.";
            }
            $stmtInsert->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrarte es simple.</title>
</head>
<body>
    <h1>Registrarte es simple.</h1>

    <?php if (!empty($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <label>Email:</label><br>
        <input type="email" name="email" required><br><br>

        <label>Contraseña:</label><br>
        <input type="password" name="password" required><br><br>

        <label>Repetir contraseña:</label><br>
        <input type="password" name="repeat_password" required><br><br>

        <button type="submit">Registrarse</button>
    </form>

    <br>
    <a href="login.php">¿Ya tienes cuenta? Inicia sesión aquí</a>
</body>
</html>