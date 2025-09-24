<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Mi Perfil';

// Obtener información del docente
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$usuario = $stmt->fetch();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<div class="contenido">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; text-align: center;">
        <h1 style="font-size: 2rem; margin-bottom: 10px;">Mi Perfil</h1>
        <p style="opacity: 0.9;">Gestiona tu información personal y preferencias</p>
    </div>

    <div class="form-container-body">
        <div class="div-fila" style="gap: 30px;">
            <!-- Información Personal -->
            <div style="flex: 2;">
                <h2 style="color: #3498db; margin-bottom: 20px;">Información Personal</h2>
                <form method="POST" action="/imt-cursos/public/docente/actualizar_perfil.php">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Nombre Completo</label>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" 
                               style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" 
                               style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Usuario</label>
                        <input type="text" name="usuario" value="<?= htmlspecialchars($usuario['usuario']) ?>" 
                               style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                    </div>
                    
                    <button type="submit" 
                            style="background: #3498db; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                        Actualizar Información
                    </button>
                </form>
            </div>
            
            <!-- Estadísticas del Perfil -->
            <div style="flex: 1;">
                <h2 style="color: #3498db; margin-bottom: 20px;">Mi Actividad</h2>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 12px;">
                    <div style="text-align: center; margin-bottom: 15px;">
                        <div style="font-size: 1.5rem; font-weight: bold; color: #3498db;">Docente</div>
                        <div style="font-size: 0.9rem; color: #7f8c8d;">Rol actual</div>
                    </div>
                    
                    <div style="border-top: 1px solid #e8ecef; padding-top: 15px; margin-top: 15px;">
                        <div style="margin-bottom: 10px;">
                            <strong>Último acceso:</strong><br>
                            <span style="color: #7f8c8d;"><?= $usuario['last_login_at'] ? date('d/m/Y H:i', strtotime($usuario['last_login_at'])) : 'Nunca' ?></span>
                        </div>
                        <div style="margin-bottom: 10px;">
                            <strong>Cuenta creada:</strong><br>
                            <span style="color: #7f8c8d;"><?= date('d/m/Y', strtotime($usuario['created_at'])) ?></span>
                        </div>
                        <div>
                            <strong>Estado:</strong><br>
                            <span style="background: <?= $usuario['estado'] === 'activo' ? '#27ae60' : '#e74c3c' ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">
                                <?= ucfirst($usuario['estado']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button onclick="window.location.href='/imt-cursos/public/docente/dashboard.php'" 
                            style="width: 100%; background: transparent; color: #3498db; border: 2px solid #3498db; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                        Volver al Dashboard
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
