<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Actividades Interactivas</title>
    <link rel="stylesheet" href="styles/css/actividades.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .test-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-section {
            margin-bottom: 40px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .test-title {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .test-buttons {
            margin: 20px 0;
        }
        .test-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 5px;
            border-radius: 5px;
            cursor: pointer;
        }
        .test-btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>Prueba de Actividades Interactivas</h1>
        
        <!-- Prueba 1: Completar Espacios -->
        <div class="test-section">
            <h2 class="test-title">1. Completar Espacios (Drag & Drop)</h2>
            <div class="test-buttons">
                <button class="test-btn" onclick="crearCompletarEspacios()">Crear Actividad</button>
                <button class="test-btn" onclick="evaluarActividad('completar-espacios-1')">Evaluar</button>
                <button class="test-btn" onclick="reiniciarActividad('completar-espacios-1')">Reiniciar</button>
            </div>
            <div id="completar-espacios-1"></div>
        </div>

        <!-- Prueba 2: Relacionar Conceptos -->
        <div class="test-section">
            <h2 class="test-title">2. Relacionar Conceptos</h2>
            <div class="test-buttons">
                <button class="test-btn" onclick="crearRelacionarConceptos()">Crear Actividad</button>
                <button class="test-btn" onclick="evaluarActividad('relacionar-conceptos-1')">Evaluar</button>
                <button class="test-btn" onclick="reiniciarActividad('relacionar-conceptos-1')">Reiniciar</button>
            </div>
            <div id="relacionar-conceptos-1"></div>
        </div>

        <!-- Prueba 3: Cuestionario Opción Múltiple -->
        <div class="test-section">
            <h2 class="test-title">3. Cuestionario Opción Múltiple</h2>
            <div class="test-buttons">
                <button class="test-btn" onclick="crearCuestionario()">Crear Actividad</button>
                <button class="test-btn" onclick="evaluarActividad('cuestionario-1')">Evaluar</button>
                <button class="test-btn" onclick="reiniciarActividad('cuestionario-1')">Reiniciar</button>
            </div>
            <div id="cuestionario-1"></div>
        </div>
    </div>

    <script src="styles/js/actividades.js"></script>
    <script>
        // Función para crear actividad de completar espacios
        function crearCompletarEspacios() {
            const configuracion = {
                elementos: [
                    {
                        id: 'elemento-1',
                        oracion: "La gestión de la {calidad} es un enfoque sistemático para garantizar que los {productos} y {servicios} cumplan con los {requisitos} del cliente.",
                        espacios: [
                            { espacioId: 'espacio-1', respuesta: 'calidad' },
                            { espacioId: 'espacio-2', respuesta: 'productos' },
                            { espacioId: 'espacio-3', respuesta: 'servicios' },
                            { espacioId: 'espacio-4', respuesta: 'requisitos' }
                        ],
                        banco: [
                            "calidad",
                            "productos", 
                            "servicios",
                            "requisitos",
                            "proceso", // opción distractora
                            "sistema"  // opción distractora
                        ]
                    },
                    {
                        id: 'elemento-2',
                        oracion: "El {control} de calidad implica la {verificación} de que los procesos se ejecuten correctamente.",
                        espacios: [
                            { espacioId: 'espacio-5', respuesta: 'control' },
                            { espacioId: 'espacio-6', respuesta: 'verificación' }
                        ],
                        banco: [
                            "control",
                            "verificación",
                            "auditoría", // opción distractora
                            "mejora"     // opción distractora
                        ]
                    }
                ]
            };
            
            actividades.inicializar('completarTextos', 'completar-espacios-1', configuracion);
        }

        // Función para crear actividad de relacionar conceptos
        function crearRelacionarConceptos() {
            const configuracion = {
                titulo: "Relaciona los conceptos de gestión de calidad",
                instrucciones: "Conecta cada concepto con su definición correspondiente.",
                pares: [
                    {
                        izquierda: "ISO 9001",
                        derecha: "Norma internacional de gestión de calidad"
                    },
                    {
                        izquierda: "Mejora continua",
                        derecha: "Proceso de optimización constante"
                    },
                    {
                        izquierda: "Auditoría",
                        derecha: "Evaluación sistemática de procesos"
                    },
                    {
                        izquierda: "No conformidad",
                        derecha: "Incumplimiento de requisitos"
                    }
                ]
            };
            
            actividades.inicializar('relacionarConceptos', 'relacionar-conceptos-1', configuracion);
        }

        // Función para crear cuestionario
        function crearCuestionario() {
            const configuracion = {
                titulo: "Cuestionario sobre Gestión de Calidad",
                instrucciones: "Selecciona la respuesta correcta para cada pregunta.",
                preguntas: [
                    {
                        pregunta: "¿Qué significa ISO?",
                        opciones: [
                            "International Standards Organization",
                            "International Organization for Standardization",
                            "International System Organization",
                            "International Service Organization"
                        ],
                        correcta: 1
                    },
                    {
                        pregunta: "¿Cuál es el principio fundamental de la gestión de calidad?",
                        opciones: [
                            "Reducir costos",
                            "Satisfacción del cliente",
                            "Aumentar ventas",
                            "Mejorar la imagen"
                        ],
                        correcta: 1
                    }
                ]
            };
            
            actividades.inicializar('cuestionario.opcionMultiple', 'cuestionario-1', configuracion);
        }

        // Función para evaluar actividad
        function evaluarActividad(idActividad) {
            const contenedor = document.getElementById(idActividad);
            if (contenedor && contenedor.actividad) {
                const resultado = contenedor.actividad.evaluar();
                alert(`Resultado: ${resultado.puntuacion}/${resultado.total} puntos\nPorcentaje: ${resultado.porcentaje}%`);
            } else {
                alert('No hay actividad para evaluar');
            }
        }

        // Función para reiniciar actividad
        function reiniciarActividad(idActividad) {
            const contenedor = document.getElementById(idActividad);
            if (contenedor) {
                contenedor.innerHTML = '';
                contenedor.actividad = null;
            }
        }

        // Mensaje de bienvenida
        console.log('Página de prueba de actividades cargada. Usa los botones para probar cada actividad.');
    </script>
</body>
</html>