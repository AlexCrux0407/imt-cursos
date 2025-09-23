<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
$page_title = 'Docente – Visualización de curso';
require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>
<div class="container">
    <h2>Visualización de curso</h2>
    <p>(estudiantes inscritos, % de avance por estudiante, fecha de inicio y de compleción)</p>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>