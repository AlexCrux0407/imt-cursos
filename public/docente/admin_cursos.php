<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
$page_title = 'Docente – Administración de cursos';
require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>
<div class="container">
  <h2>Administración de cursos</h2>
  <p>(CRUD de cursos: Título, Bienvenida, Objetivo general, Objetivos específicos, A quién va dirigido, horas, estado)</p>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
