<?php
session_start();
require_once 'db.php';

// Redireccionar si no está logueado
if (!isset($_SESSION['idUsuario'])) {
    header("Location: login.php");
    exit();
}

$idUsuario = $_SESSION['idUsuario'];
$emailUsuario = $_SESSION['email'] ?? '';
$mensaje = "";
$error = "";

// ACCIÓN: Eliminar Web
if (isset($_GET['action']) && $_GET['action'] === 'eliminar' && isset($_GET['id'])) {
    $idWeb = intval($_GET['id']);

    // Admin puede borrar cualquier web; usuario común solo sus webs
    if ($emailUsuario === 'admin@server.com') {
        $stmt = $db->prepare("SELECT dominio FROM webs WHERE idWeb = ?");
        $stmt->bind_param("i", $idWeb);
    } else {
        $stmt = $db->prepare("SELECT dominio FROM webs WHERE idWeb = ? AND idUsuario = ?");
        $stmt->bind_param("ii", $idWeb, $idUsuario);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    if ($webData = $res->fetch_assoc()) {
        $dominioBorrar = $webData['dominio'];
        $stmt->close();

        // Borrar carpeta físicamente
        if (!empty($dominioBorrar) && file_exists($dominioBorrar)) {
            shell_exec("rm -rf " . escapeshellarg($dominioBorrar));
        }

        // Borrar zip si existía
        if (file_exists($dominioBorrar . ".zip")) {
            unlink($dominioBorrar . ".zip");
        }

        // Borrar registro de la BD
        $stmtDel = $db->prepare("DELETE FROM webs WHERE idWeb = ?");
        $stmtDel->bind_param("i", $idWeb);
        $stmtDel->execute();
        $stmtDel->close();

        $mensaje = "La web '$dominioBorrar' fue eliminada correctamente.";
    } else {
        $error = "No se pudo eliminar la web especificada.";
    }
}

// ACCIÓN: Descargar Web en ZIP
if (isset($_GET['action']) && $_GET['action'] === 'descargar' && isset($_GET['id'])) {
    $idWeb = intval($_GET['id']);

    if ($emailUsuario === 'admin@server.com') {
        $stmt = $db->prepare("SELECT dominio FROM webs WHERE idWeb = ?");
        $stmt->bind_param("i", $idWeb);
    } else {
        $stmt = $db->prepare("SELECT dominio FROM webs WHERE idWeb = ? AND idUsuario = ?");
        $stmt->bind_param("ii", $idWeb, $idUsuario);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    if ($webData = $res->fetch_assoc()) {
        $dominioZip = $webData['dominio'];
        $stmt->close();

        $zipName = $dominioZip . ".zip";

        // Comprimir carpeta en zip usando shell_exec
        shell_exec("zip -r " . escapeshellarg($zipName) . " " . escapeshellarg($dominioZip));

        if (file_exists($zipName)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($zipName) . '"');
            header('Content-Length: ' . filesize($zipName));
            readfile($zipName);
            exit();
        } else {
            $error = "No se pudo generar el archivo ZIP.";
        }
    }
}

// ACCIÓN: Crear nueva Web
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre_web'])) {
    $nombreIngresado = trim($_POST['nombre_web']);

    if (!empty($nombreIngresado)) {
        // Dominio concatenado: idUsuario + nombreIngresado
        $dominio = $idUsuario . $nombreIngresado;

        // Comprobar si ya existe el dominio
        $stmtCheck = $db->prepare("SELECT idWeb FROM webs WHERE dominio = ?");
        $stmtCheck->bind_param("s", $dominio);
        $stmtCheck->execute();
        $stmtCheck->store_result();

        if ($stmtCheck->num_rows > 0) {
            $error = "El dominio '$dominio' ya existe.";
        } else {
            $stmtCheck->close();
            $fecha = date('Y-m-d H:i:s');

            // Insertar en la BD
            $stmtIns = $db->prepare("INSERT INTO webs (idUsuario, dominio, fechaCreacion) VALUES (?, ?, ?)");
            $stmtIns->bind_param("iss", $idUsuario, $dominio, $fecha);

            if ($stmtIns->execute()) {
                // Ejecutar el script wix.sh pasándole el dominio
                shell_exec("bash wix.sh " . escapeshellarg($dominio));
                $mensaje = "Web '$dominio' creada con éxito.";
            } else {
                $error = "Error al registrar la web en la base de datos.";
            }
            $stmtIns->close();
        }
    } else {
        $error = "Debes ingresar un nombre para la web.";
    }
}

// LISTADO DE WEBS
if ($emailUsuario === 'admin@server.com') {
    // Modo Admin: Muestra webs de todos los usuarios
    $sqlWebs = "SELECT * FROM webs ORDER BY fechaCreacion DESC";
    $stmtList = $db->prepare($sqlWebs);
} else {
    // Modo Usuario: Solo webs del usuario logueado
    $sqlWebs = "SELECT * FROM webs WHERE idUsuario = ? ORDER BY fechaCreacion DESC";
    $stmtList = $db->prepare($sqlWebs);
    $stmtList->bind_param("i", $idUsuario);
}

$stmtList->execute();
$websResult = $stmtList->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bienvenido a tu panel</title>
</head>
<body>
    <h1>Bienvenido a tu panel</h1>

    <a href="logout.php">Cerrar sesión de <?php echo htmlspecialchars($idUsuario); ?></a>

    <hr>

    <?php if (!empty($mensaje)): ?>
        <p style="color: green;"><?php echo htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <h2>Generar Web de:</h2>
    <form action="panel.php" method="POST">
        <input type="text" name="nombre_web" placeholder="Nombre de la web" required>
        <button type="submit">Crear web</button>
    </form>

    <hr>

    <h3>Listado de webs:</h3>

    <?php if ($websResult->num_rows > 0): ?>
        <ul>
        <?php while ($web = $websResult->fetch_assoc()): ?>
            <li style="margin-bottom: 8px;">
                <a href="<?php echo htmlspecialchars($web['dominio']); ?>/index.php" target="_blank">
                    <?php echo htmlspecialchars($web['dominio']); ?>
                </a>
                &nbsp;|&nbsp;
                <a href="panel.php?action=descargar&id=<?php echo $web['idWeb']; ?>">descargar web</a>
                &nbsp;|&nbsp;
                <a href="panel.php?action=eliminar&id=<?php echo $web['idWeb']; ?>" onclick="return confirm('¿Seguro deseas eliminar esta web?');">Eliminar</a>
            </li>
        <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p>No tienes webs generadas aún.</p>
    <?php endif; ?>

</body>
</html>