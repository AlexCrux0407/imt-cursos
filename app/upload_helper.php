<?php
/**
 * Helper para manejo de archivos organizados por curso.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paths.php';

class UploadHelper {
    
    private $conn;
    private $base_upload_dir;
    
    /**
     * Inicializa con conexión a base de datos.
     */
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        // Guardar siempre en public/uploads/cursos para servir directo desde el DocumentRoot
        $this->base_upload_dir = PUBLIC_PATH . '/uploads/cursos/';
    }
    
    /**
     * Obtiene información jerárquica para construir rutas.
     */
    private function getHierarchyInfo($entity_type, $entity_id) {
        $info = [];
        
        switch ($entity_type) {
            case 'leccion':
                $stmt = $this->conn->prepare("
                    SELECT l.id as leccion_id, l.titulo as leccion_titulo, l.orden as leccion_orden,
                           s.id as subtema_id, s.titulo as subtema_titulo, s.orden as subtema_orden,
                           t.id as tema_id, t.titulo as tema_titulo, t.orden as tema_orden,
                           m.id as modulo_id, m.titulo as modulo_titulo, m.orden as modulo_orden,
                           c.id as curso_id, c.titulo as curso_titulo
                    FROM lecciones l
                    LEFT JOIN subtemas s ON l.subtema_id = s.id
                    LEFT JOIN temas t ON (l.tema_id = t.id OR s.tema_id = t.id)
                    LEFT JOIN modulos m ON (l.modulo_id = m.id OR t.modulo_id = m.id)
                    LEFT JOIN cursos c ON m.curso_id = c.id
                    WHERE l.id = :entity_id
                ");
                break;
                
            case 'subtema':
                $stmt = $this->conn->prepare("
                    SELECT s.id as subtema_id, s.titulo as subtema_titulo, s.orden as subtema_orden,
                           t.id as tema_id, t.titulo as tema_titulo, t.orden as tema_orden,
                           m.id as modulo_id, m.titulo as modulo_titulo, m.orden as modulo_orden,
                           c.id as curso_id, c.titulo as curso_titulo
                    FROM subtemas s
                    LEFT JOIN temas t ON s.tema_id = t.id
                    LEFT JOIN modulos m ON t.modulo_id = m.id
                    LEFT JOIN cursos c ON m.curso_id = c.id
                    WHERE s.id = :entity_id
                ");
                break;
                
            case 'tema':
                $stmt = $this->conn->prepare("
                    SELECT t.id as tema_id, t.titulo as tema_titulo, t.orden as tema_orden,
                           m.id as modulo_id, m.titulo as modulo_titulo, m.orden as modulo_orden,
                           c.id as curso_id, c.titulo as curso_titulo
                    FROM temas t
                    LEFT JOIN modulos m ON t.modulo_id = m.id
                    LEFT JOIN cursos c ON m.curso_id = c.id
                    WHERE t.id = :entity_id
                ");
                break;
                
            case 'modulo':
                $stmt = $this->conn->prepare("
                    SELECT m.id as modulo_id, m.titulo as modulo_titulo, m.orden as modulo_orden,
                           c.id as curso_id, c.titulo as curso_titulo
                    FROM modulos m
                    LEFT JOIN cursos c ON m.curso_id = c.id
                    WHERE m.id = :entity_id
                ");
                break;
        }
        
        $stmt->execute([':entity_id' => $entity_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Normaliza un nombre para usar como carpeta.
     */
    private function sanitizeFolderName($name) {
        $clean = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $name);
        $clean = preg_replace('/\s+/', '-', trim($clean));
        $clean = strtolower($clean);
        return substr($clean, 0, 50);
    }
    
    /**
     * Construye la ruta de carpeta basada en la jerarquía.
     */
    public function buildFolderPath($entity_type, $entity_id) {
        $info = $this->getHierarchyInfo($entity_type, $entity_id);
        
        if (!$info) {
            throw new Exception("No se pudo obtener información de la entidad");
        }
        
        $curso_folder = $this->sanitizeFolderName($info['curso_titulo']);
        
        $modulo_folder = sprintf("%02d-%s", 
            $info['modulo_orden'], 
            $this->sanitizeFolderName($info['modulo_titulo'])
        );
        
        $path_parts = [$curso_folder, $modulo_folder];
        
        if (!empty($info['tema_id'])) {
            $tema_folder = sprintf("%02d-%s", 
                $info['tema_orden'], 
                $this->sanitizeFolderName($info['tema_titulo'])
            );
            $path_parts[] = $tema_folder;
            
            if (!empty($info['subtema_id'])) {
                $subtema_folder = sprintf("%02d-%s", 
                    $info['subtema_orden'], 
                    $this->sanitizeFolderName($info['subtema_titulo'])
                );
                $path_parts[] = $subtema_folder;
                
                if (!empty($info['leccion_id'])) {
                    $leccion_folder = sprintf("%02d-%s", 
                        $info['leccion_orden'], 
                        $this->sanitizeFolderName($info['leccion_titulo'])
                    );
                    $path_parts[] = $leccion_folder;
                }
            }
        }
        
        return implode('/', $path_parts);
    }
    
    /**
     * Maneja subida de archivos según estructura establecida.
     */
    public function handleFileUpload($file_data, $entity_type, $entity_id, $allowed_extensions = null) {
        if (!isset($file_data) || $file_data['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        
        if ($allowed_extensions === null) {
            $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'mp4', 'avi', 'mov', 'jpg', 'jpeg', 'png'];
        }
        
        $file_info = pathinfo($file_data['name']);
        $extension = strtolower($file_info['extension']);
        
        if (!in_array($extension, $allowed_extensions)) {
            throw new Exception("Tipo de archivo no permitido: " . $extension);
        }
        
        $folder_path = $this->buildFolderPath($entity_type, $entity_id);
        $full_upload_dir = $this->base_upload_dir . $folder_path . '/';
        
        if (!is_dir($full_upload_dir)) {
            if (!mkdir($full_upload_dir, 0755, true)) {
                throw new Exception("No se pudo crear el directorio: " . $full_upload_dir);
            }
        }
        
        $new_filename = $entity_type . '_' . $entity_id . '_' . time() . '.' . $extension;
        $upload_path = $full_upload_dir . $new_filename;
        
        if (!move_uploaded_file($file_data['tmp_name'], $upload_path)) {
            throw new Exception("Error al mover el archivo subido");
        }
        
        // URL pública basada en BASE_URL
        return BASE_URL . '/uploads/cursos/' . $folder_path . '/' . $new_filename;
    }
    
    /**
     * Elimina un archivo del sistema.
     */
    public function deleteFile($file_url) {
        if (empty($file_url) || strpos($file_url, '/uploads/cursos/') !== 0) {
            return false;
        }

        // Intentar primero en public/uploads
        $file_path_public = PUBLIC_PATH . $file_url; // BASE_URL no se incluye en la ruta física
        if (file_exists($file_path_public)) {
            return unlink($file_path_public);
        }

        // Fallback: ruta antigua en la raíz del proyecto (no-public)
        $root_path = dirname(__DIR__); // ../
        $file_path_root = $root_path . $file_url;
        if (file_exists($file_path_root)) {
            return unlink($file_path_root);
        }

        return false;
    }
}