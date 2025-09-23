<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
$page_title = 'estudiante – Perfil';
require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>
<div class="container">
  <h2>Perfil</h2>
  <p>Nombre de usuario: <?= htmlspecialchars($_SESSION['usuario'] ?? $_SESSION['email']) ?></p>
  <p>(Aquí más datos del estudiante y sus cursos con estado: completado, en progreso (%), disponible/activo)</p>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
