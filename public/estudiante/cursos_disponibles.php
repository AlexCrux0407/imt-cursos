<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
$page_title = 'estudiante – Cursos disponibles';
require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>
<div class="container">
  <h2>Cursos disponibles</h2>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
