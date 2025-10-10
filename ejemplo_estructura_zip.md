# 📁 Estructura de ZIP para Importar Cursos

## Estructura Requerida

Para importar contenido de un curso mediante ZIP, el archivo debe seguir esta estructura exacta:

```
mi-curso.zip
├── contenido/
│   ├── modulo-01/
│   │   ├── tema-01/
│   │   │   ├── subtema-01/
│   │   │   │   ├── leccion-01.html
│   │   │   │   ├── leccion-02.html
│   │   │   │   └── leccion-03.html
│   │   │   └── subtema-02/
│   │   │       ├── leccion-01.html
│   │   │       └── leccion-02.html
│   │   └── tema-02/
│   │       └── subtema-01/
│   │           └── leccion-01.html
│   └── modulo-02/
│       └── tema-01/
│           └── subtema-01/
│               ├── leccion-01.html
│               └── leccion-02.html
└── tema/ (opcional)
    └── tema.css
```

## Reglas de Nomenclatura

### Módulos
- **Formato:** `modulo-XX` o `modulo_XX`
- **Ejemplos:** `modulo-01`, `modulo-02`, `modulo_03`

### Temas
- **Formato:** `tema-XX` o `tema_XX`
- **Ejemplos:** `tema-01`, `tema-02`, `tema_03`

### Subtemas
- **Formato:** `subtema-XX` o `subtema_XX`
- **Ejemplos:** `subtema-01`, `subtema-02`, `subtema_03`

### Lecciones
- **Formato:** `leccion-XX.html` o `leccion_XX.html`
- **Ejemplos:** `leccion-01.html`, `leccion-02.html`

## Contenido de las Lecciones

Cada archivo HTML debe contener:

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Título de la Lección</title>
</head>
<body>
    <h1>Título Principal de la Lección</h1>
    
    <p>Contenido de la lección...</p>
    
    <!-- El sistema extraerá automáticamente el texto del h1 como título -->
</body>
</html>
```

## CSS Personalizado (Opcional)

Si incluyes un archivo `tema/tema.css`, se aplicará como estilo personalizado para todo el curso.

## Ejemplo de Contenido Mínimo

```
curso-ejemplo.zip
├── contenido/
│   └── modulo-01/
│       └── tema-01/
│           └── subtema-01/
│               └── leccion-01.html
```

## Notas Importantes

1. **Numeración:** Los números en los nombres de carpetas y archivos determinan el orden de visualización
2. **Títulos:** El sistema extraerá automáticamente el título de cada lección del elemento `<h1>` del HTML
3. **Reemplazo:** Puedes elegir si reemplazar el contenido existente o agregarlo al curso
4. **Validación:** El sistema validará la estructura antes de procesar el contenido

## Proceso de Importación
1. Solicita al administrador que cree un curso
2. Selecciona el curso al que quieres agregar contenido
3. Haz clic en "📁 Cargar ZIP"
4. Selecciona tu archivo ZIP con la estructura correcta
5. Elige si reemplazar el contenido existente
6. Haz clic en "Procesar ZIP"
7. El sistema procesará automáticamente toda la estructura 

¡Listo! El contenido estará disponible inmediatamente para los estudiantes.