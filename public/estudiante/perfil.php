<?php
// Vista Estudiante – Perfil de usuario
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Estudiante – Perfil';

$estudiante_id = (int)($_SESSION['user_id'] ?? 0);

// Información del estudiante
$stmt = $conn->prepare("SELECT id, nombre, email, created_at FROM usuarios WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $estudiante_id]);
$usuario = $stmt->fetch();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estudiante.css">

<div class="student-dashboard">
    <div class="student-welcome">
        <h1 class="welcome-title">Perfil del Estudiante</h1>
        <p class="welcome-subtitle">Hola, <?= htmlspecialchars($usuario['nombre'] ?? ($_SESSION['nombre'] ?? 'Estudiante')) ?>. Aquí puedes ver tu información y progreso.</p>
    </div>

    
    <div class="profile-section">
        <h2 class="section-title">Información Personal</h2>
        <div class="profile-grid">
            <div class="profile-info-card">
                <div class="card-top">
                    <div class="info-icon">👤</div>
                    <div class="info-label">Nombre</div>
                </div>
                <div class="info-value">
                    <?= htmlspecialchars($usuario['nombre'] ?? ($_SESSION['nombre'] ?? '—')) ?>
                </div>
            </div>
            <div class="profile-info-card">
                <div class="card-top">
                    <div class="info-icon">✉️</div>
                    <div class="info-label">Correo</div>
                </div>
                <div class="info-value">
                    <?= htmlspecialchars($usuario['email'] ?? ($_SESSION['email'] ?? '—')) ?>
                </div>
            </div>
            <div class="profile-info-card">
                <div class="card-top">
                    <div class="info-icon">📅</div>
                    <div class="info-label">Fecha de Registro</div>
                </div>
                <div class="info-value">
                    <?= isset($usuario['created_at']) ? date('d/m/Y', strtotime($usuario['created_at'])) : '—' ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
