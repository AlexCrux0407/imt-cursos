&lt;!DOCTYPE html&gt;
&lt;html&gt;
&lt;head&gt;
    &lt;title&gt;Debug Button Activation&lt;/title&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;h1&gt;Debug: Activación del Botón&lt;/h1&gt;
    
    &lt;script&gt;
    // Simular la función updateProgress para debug
    function updateProgress() {
        console.log('=== DEBUG updateProgress ===');
        const containers = document.querySelectorAll('.question-container');
        const totalQuestions = containers.length;
        let answeredQuestions = 0;
        
        console.log('Total preguntas encontradas:', totalQuestions);
        
        containers.forEach(container =&gt; {
            const id = container.dataset.preguntaId;
            const tipo = container.dataset.tipo;
            let answered = false;
            
            console.log(`Procesando pregunta ${id}, tipo: ${tipo}`);
            
            if (tipo === 'relacionar_pares') {
                const campoRespuesta = document.querySelector(`input[name="respuesta_${id}"]`);
                console.log(`Campo respuesta para ${id}:`, campoRespuesta);
                console.log(`Valor del campo:`, campoRespuesta ? campoRespuesta.value : 'null');
                
                if (campoRespuesta && campoRespuesta.value) {
                    try {
                        const respuestas = JSON.parse(campoRespuesta.value);
                        console.log(`Respuestas parseadas para ${id}:`, respuestas);
                        
                        const scriptPairs = document.getElementById(`pairs-data-${id}`);
                        console.log(`Script pairs para ${id}:`, scriptPairs);
                        
                        if (scriptPairs) {
                            const pairs = JSON.parse(scriptPairs.textContent);
                            console.log(`Pairs esperados para ${id}:`, pairs.length);
                            console.log(`Respuestas dadas para ${id}:`, Object.keys(respuestas).length);
                            answered = Object.keys(respuestas).length === pairs.length;
                        } else {
                            console.log(`No se encontró script pairs para pregunta ${id}`);
                            answered = Object.keys(respuestas).length &gt; 0;
                        }
                    } catch (e) {
                        console.log(`Error parseando respuestas para ${id}:`, e);
                        answered = false;
                    }
                } else {
                    answered = false;
                }
            }
            
            console.log(`Pregunta ${id} respondida:`, answered);
            if (answered) answeredQuestions++;
        });
        
        console.log(`Preguntas respondidas: ${answeredQuestions}/${totalQuestions}`);
        
        const progress = (answeredQuestions / totalQuestions) * 100;
        console.log(`Progreso: ${progress}%`);
        
        const submitBtn = document.getElementById('submit-btn');
        console.log('Botón submit encontrado:', submitBtn);
        
        const shouldEnable = answeredQuestions === totalQuestions;
        console.log('¿Debería habilitarse el botón?', shouldEnable);
        
        if (submitBtn) {
            submitBtn.disabled = !shouldEnable;
            console.log('Estado del botón después de actualizar:', submitBtn.disabled ? 'DESHABILITADO' : 'HABILITADO');
        }
        
        console.log('=== FIN DEBUG updateProgress ===');
    }
    
    // Función para probar manualmente
    function testButtonActivation() {
        console.log('Ejecutando test manual de activación del botón...');
        updateProgress();
    }
    
    // Agregar botón de test
    document.addEventListener('DOMContentLoaded', function() {
        const testBtn = document.createElement('button');
        testBtn.textContent = 'Test Button Activation';
        testBtn.onclick = testButtonActivation;
        testBtn.style.cssText = 'position:fixed;top:10px;right:10px;z-index:9999;background:red;color:white;padding:10px;';
        document.body.appendChild(testBtn);
    });
    &lt;/script&gt;
&lt;/body&gt;
&lt;/html&gt;