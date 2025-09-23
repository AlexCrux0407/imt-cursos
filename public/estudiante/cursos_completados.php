<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
$page_title = 'estudiante – Cursos completados';
require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>
<div class="container">
    <h2>Cursos completados</h2>
    <p>(Aquí: calificación, fecha completado, botón para descargar constancia PDF)</p>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>