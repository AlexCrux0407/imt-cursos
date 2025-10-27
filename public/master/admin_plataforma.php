<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../app/auth.php';

// Verificar autenticación y rol
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'master') {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

$page_title = 'Administración de Plataforma - IMT';

// Obtener configuración actual de la plataforma
$stmt = $conn->prepare("
    SELECT * FROM configuracion_plataforma 
    WHERE id = 1
");
$stmt->execute();
$config = $stmt->fetch();

// Si no existe configuración, crear valores por defecto
if (!$config) {
    $stmt = $conn->prepare("
        INSERT INTO configuracion_plataforma (id, nombre_plataforma, logo_header, logo_footer, created_at) 
        VALUES (1, 'IMT Cursos', 'Logo_IMT.png', 'Logo_blanco.png', NOW())
    ");
    $stmt->execute();
    
    // Obtener la configuración recién creada
    $stmt = $conn->prepare("SELECT * FROM configuracion_plataforma WHERE id = 1");
    $stmt->execute();
    $config = $stmt->fetch();
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/master.css">

<?php if (isset($_SESSION['mensaje_exito'])): ?>
    <div style="position: fixed; top: 90px; right: 20px; background: #d4edda; color: #155724; padding: 15px 20px; border-radius: 8px; border: 1px solid #c3e6cb; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        <strong>✅ Éxito:</strong> <?= htmlspecialchars($_SESSION['mensaje_exito']) ?>
        <button onclick="this.parentElement.style.display='none'" style="background: none; border: none; float: right; margin-left: 10px; cursor: pointer; font-size: 18px; color: #155724;">&times;</button>
    </div>
    <?php unset($_SESSION['mensaje_exito']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['mensaje_error'])): ?>
    <div style="position: fixed; top: 90px; right: 20px; background: #f8d7da; color: #721c24; padding: 15px 20px; border-radius: 8px; border: 1px solid #f5c6cb; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        <strong>❌ Error:</strong> <?= htmlspecialchars($_SESSION['mensaje_error']) ?>
        <button onclick="this.parentElement.style.display='none'" style="background: none; border: none; float: right; margin-left: 10px; cursor: pointer; font-size: 18px; color: #721c24;">&times;</button>
    </div>
    <?php unset($_SESSION['mensaje_error']); ?>
<?php endif; ?>

<div class="contenido">
    <!-- Header Principal -->
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #3498db); color: white; text-align: center;">
        <h1 style="font-size: 2.5rem; margin-bottom: 10px; font-weight: 600;">
            <img src="<?= BASE_URL ?>/styles/iconos/config.png" alt="Config" style="width: 40px; height: 40px; margin-right: 15px; vertical-align: middle; filter: brightness(0) invert(1);">
            Administración de Plataforma
        </h1>
        <p style="font-size: 1.1rem; opacity: 0.9;">Gestión de identidad visual y configuración general</p>
        <small style="opacity: 0.8;">Personaliza el logo y nombre de la plataforma</small>
    </div>

    <!-- Configuración Actual -->
    <div class="form-container-body" style="margin-bottom: 20px;">
        <h2 style="color: var(--master-primary); margin-bottom: 20px; display: flex; align-items: center;">
            <img src="<?= BASE_URL ?>/styles/iconos/detalles.png" alt="Info" style="width: 24px; height: 24px; margin-right: 10px;">
            Configuración Actual
        </h2>
        
        <div class="div-fila" style="gap: 30px; align-items: flex-start;">
            <!-- Vista Previa del Header -->
            <div style="flex: 1; background: #f8f9fa; padding: 20px; border-radius: 12px; border: 2px solid #e9ecef;">
                <h3 style="color: #495057; margin-bottom: 15px; font-size: 1.1rem;">Vista Previa - Header</h3>
                <div style="background: #3498db; padding: 15px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center;">
                        <img src="<?= BASE_URL ?>/styles/iconos/<?= htmlspecialchars($config['logo_header']) ?>" 
                             alt="Logo Header" 
                             style="width: 32px; height: 32px; margin-right: 15px;"
                             onerror="this.src='<?= BASE_URL ?>/styles/iconos/Logo_IMT.png'">
                        <h1 style="color: white; margin: 0; font-size: 1.5rem;"><?= htmlspecialchars($config['nombre_plataforma']) ?></h1>
                    </div>
                    <div style="color: white; font-size: 0.9rem;">Usuario Master</div>
                </div>
            </div>

            <!-- Vista Previa del Footer -->
            <div style="flex: 1; background: #f8f9fa; padding: 20px; border-radius: 12px; border: 2px solid #e9ecef;">
                <h3 style="color: #495057; margin-bottom: 15px; font-size: 1.1rem;">Vista Previa - Footer</h3>
                <div style="background: white; padding: 15px; border-radius: 8px; border-top: 1px solid #ddd; display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <img src="<?= BASE_URL ?>/styles/iconos/<?= htmlspecialchars($config['logo_footer']) ?>" 
                         alt="Logo Footer" 
                         style="width: 20px; height: 20px; filter: brightness(0) saturate(100%) invert(26%) sepia(15%) saturate(1487%) hue-rotate(190deg) brightness(95%) contrast(90%);"
                         onerror="this.src='<?= BASE_URL ?>/styles/iconos/Logo_blanco.png'">
                    <p style="margin: 0; color: #5C5C69; font-size: 0.8em;">&copy; <?= date('Y') ?> <?= htmlspecialchars($config['nombre_plataforma']) ?> - Desarrollado por división de telemática</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario de Configuración -->
    <div class="form-container-body">
        <h2 style="color: var(--master-primary); margin-bottom: 20px; display: flex; align-items: center;">
            <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Editar" style="width: 24px; height: 24px; margin-right: 10px;">
            Configurar Plataforma
        </h2>

        <form action="procesar_plataforma.php" method="POST" enctype="multipart/form-data" style="max-width: 800px;">
            <!-- Nombre de la Plataforma -->
            <div style="margin-bottom: 25px;">
                <label for="nombre_plataforma" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                    <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Nombre" style="width: 16px; height: 16px; margin-right: 8px; vertical-align: middle;">
                    Nombre de la Plataforma
                </label>
                <input type="text" 
                       id="nombre_plataforma" 
                       name="nombre_plataforma" 
                       value="<?= htmlspecialchars($config['nombre_plataforma']) ?>"
                       required
                       style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s ease;"
                       placeholder="Ej: IMT Cursos, Mi Plataforma Educativa">
                <small style="color: #6c757d; font-size: 0.85rem;">Este nombre aparecerá en el header y footer de toda la plataforma</small>
            </div>

            <!-- Logo del Header -->
            <div style="margin-bottom: 25px;">
                <label for="logo_header" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                    <img src="<?= BASE_URL ?>/styles/iconos/plus.png" alt="Logo Header" style="width: 16px; height: 16px; margin-right: 8px; vertical-align: middle;">
                    Logo del Header (Barra Superior)
                </label>
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                    <img src="<?= BASE_URL ?>/styles/iconos/<?= htmlspecialchars($config['logo_header']) ?>" 
                         alt="Logo Header Actual" 
                         style="width: 48px; height: 48px; border: 2px solid #e9ecef; border-radius: 8px; padding: 4px;"
                         onerror="this.src='<?= BASE_URL ?>/styles/iconos/Logo_IMT.png'">
                    <div>
                        <div style="font-weight: 500; color: #495057;">Logo Actual: <?= htmlspecialchars($config['logo_header']) ?></div>
                        <small style="color: #6c757d;">Tamaño recomendado: 32x32px</small>
                    </div>
                </div>
                <input type="file" 
                       id="logo_header" 
                       name="logo_header" 
                       accept=".png,.jpg,.jpeg,.gif,.svg"
                       style="width: 100%; padding: 12px; border: 2px dashed #e9ecef; border-radius: 8px; background: #f8f9fa;">
                <small style="color: #6c757d; font-size: 0.85rem;">Formatos permitidos: PNG, JPG, JPEG, GIF, SVG. Máximo 2MB</small>
            </div>

            <!-- Logo del Footer -->
            <div style="margin-bottom: 25px;">
                <label for="logo_footer" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                    <img src="<?= BASE_URL ?>/styles/iconos/plus.png" alt="Logo Footer" style="width: 16px; height: 16px; margin-right: 8px; vertical-align: middle;">
                    Logo del Footer (Barra Inferior)
                </label>
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                    <img src="<?= BASE_URL ?>/styles/iconos/<?= htmlspecialchars($config['logo_footer']) ?>" 
                         alt="Logo Footer Actual" 
                         style="width: 48px; height: 48px; border: 2px solid #e9ecef; border-radius: 8px; padding: 4px; filter: brightness(0) saturate(100%) invert(26%) sepia(15%) saturate(1487%) hue-rotate(190deg) brightness(95%) contrast(90%);"
                         onerror="this.src='<?= BASE_URL ?>/styles/iconos/Logo_blanco.png'">
                    <div>
                        <div style="font-weight: 500; color: #495057;">Logo Actual: <?= htmlspecialchars($config['logo_footer']) ?></div>
                        <small style="color: #6c757d;">Tamaño recomendado: 20x20px</small>
                    </div>
                </div>
                <input type="file" 
                       id="logo_footer" 
                       name="logo_footer" 
                       accept=".png,.jpg,.jpeg,.gif,.svg"
                       style="width: 100%; padding: 12px; border: 2px dashed #e9ecef; border-radius: 8px; background: #f8f9fa;">
                <small style="color: #6c757d; font-size: 0.85rem;">Formatos permitidos: PNG, JPG, JPEG, GIF, SVG. Máximo 2MB</small>
            </div>

            <!-- Video de Bienvenida -->
            <div style="margin-bottom: 25px;">
                <label for="video_bienvenida" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                    <img src="<?= BASE_URL ?>/styles/iconos/detalles.png" alt="Video" style="width: 16px; height: 16px; margin-right: 8px; vertical-align: middle;">
                    Video de Bienvenida (se muestra en la portada)
                </label>
                <?php if (!empty($config['video_bienvenida'])): ?>
                    <?php
                        $video_file = basename($config['video_bienvenida']);
                        $video_proxy_url = BASE_URL . '/serve_media.php?file=' . rawurlencode($video_file);
                        $video_static_url = BASE_URL . '/uploads/media/' . rawurlencode($video_file);
                    ?>
                    <div style="margin-bottom: 10px; background: #f8f9fa; padding: 12px; border-radius: 8px; border: 2px solid #e9ecef;">
                        <div style="font-weight: 500; color: #495057; margin-bottom: 8px;">Video Actual:</div>
                        <video src="<?= htmlspecialchars($video_proxy_url) ?>" controls style="width: 100%; max-width: 600px; border-radius: 8px; border: 1px solid #e9ecef; background: #000;"></video>
                        <small style="color: #6c757d; display: block; margin-top: 6px;">Archivo: <?= htmlspecialchars($video_file) ?></small>
                        <small style="color: #6c757d; display: block;">URL estática: <a href="<?= htmlspecialchars($video_static_url) ?>" target="_blank"><?= htmlspecialchars($video_static_url) ?></a></small>
                    </div>
                <?php else: ?>
                    <small style="color: #6c757d; display: block; margin-bottom: 8px;">Aún no se ha cargado un video de bienvenida</small>
                <?php endif; ?>
                <input type="file" 
                       id="video_bienvenida" 
                       name="video_bienvenida" 
                       accept="video/mp4,video/webm,video/ogg"
                       style="width: 100%; padding: 12px; border: 2px dashed #e9ecef; border-radius: 8px; background: #f8f9fa;">
                <small style="color: #6c757d; font-size: 0.85rem;">Formatos permitidos: MP4, WEBM u OGG. Máximo 50MB</small>
            </div>

            <!-- Botones de Acción -->
            <div style="display: flex; gap: 15px; margin-top: 30px;">
                <button type="submit" 
                        style="flex: 1; background: linear-gradient(135deg,#3498db, #3498db); color: white; border: none; padding: 15px 25px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: transform 0.2s ease;">
                    <img src="<?= BASE_URL ?>/styles/iconos/plus.png" alt="Guardar" style="width: 18px; height: 18px; margin-right: 8px; vertical-align: middle; filter: brightness(0) invert(1);">
                    Guardar Configuración
                </button>
                
                <button type="button" 
                        onclick="window.location.href='<?= BASE_URL ?>/master/dashboard.php'"
                        style="flex: 0 0 auto; background: transparent; color: var(--master-primary); border: 2px solid var(--master-primary); padding: 15px 25px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                    <img src="<?= BASE_URL ?>/styles/iconos/back_bl.png" alt="Volver" style="width: 18px; height: 18px; margin-right: 8px; vertical-align: middle;">
                    Volver al Dashboard
                </button>
            </div>
        </form>
    </div>

    <!-- Información Adicional -->
    <div class="form-container-body" style="margin-top: 20px; background: #e8f4fd; border-left: 4px solid var(--master-primary);">
        <h3 style="color: var(--master-primary); margin-bottom: 15px; display: flex; align-items: center;">
            <img src="<?= BASE_URL ?>/styles/iconos/detalles.png" alt="Info" style="width: 20px; height: 20px; margin-right: 10px;">
            Información Importante
        </h3>
        <ul style="color: #495057; line-height: 1.6; margin: 0; padding-left: 20px;">
            <li><strong>Logos:</strong> Se recomienda usar imágenes PNG con fondo transparente para mejor integración visual</li>
            <li><strong>Tamaños:</strong> Header (32x32px), Footer (20x20px) - las imágenes se redimensionarán automáticamente</li>
            <li><strong>Formatos:</strong> PNG, JPG, JPEG, GIF, SVG (máximo 2MB por archivo)</li>
            <li><strong>Cambios:</strong> Los cambios se aplicarán inmediatamente en toda la plataforma</li>
            <li><strong>Respaldo:</strong> Los logos anteriores se conservan como respaldo en caso de problemas</li>
        </ul>
    </div>
</div>

<style>
/* Estilos específicos para admin_plataforma */
input[type="text"]:focus {
    border-color: var(--master-primary) !important;
    outline: none;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

input[type="file"]:hover {
    border-color: var(--master-primary);
    background: #e8f4fd !important;
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

button[type="button"]:hover {
    background: var(--master-primary) !important;
    color: white !important;
}

.form-container-body {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    border: 1px solid #e9ecef;
}

.form-container-head {
    padding: 30px;
    border-radius: 12px 12px 0 0;
    margin-bottom: 0;
}
</style>

<?php require __DIR__ . '/../partials/footer.php'; ?>