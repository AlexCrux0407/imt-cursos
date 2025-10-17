// Sistema de Actividades Interactivas
// Estructura base para actividades educativas

// Espacio de nombres global para actividades
window.actividades = {
    // Funciones de utilidad
    utilidades: {
        generarId: function() {
            return 'act_' + Math.random().toString(36).substr(2, 9);
        },
        
        mezclar: function(array) {
            const mezclado = [...array];
            for (let i = mezclado.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [mezclado[i], mezclado[j]] = [mezclado[j], mezclado[i]];
            }
            return mezclado;
        },
        
        crearElemento: function(etiqueta, nombreClase, contenido) {
            const elemento = document.createElement(etiqueta);
            if (nombreClase) elemento.className = nombreClase;
            if (contenido) elemento.innerHTML = contenido;
            return elemento;
        },
        
        guardarDatos: function(clave, datos) {
            localStorage.setItem(clave, JSON.stringify(datos));
        },
        
        cargarDatos: function(clave) {
            const datos = localStorage.getItem(clave);
            return datos ? JSON.parse(datos) : null;
        }
    },

    // Completar textos (drag & drop)
    completarTextos: {
        construir: function(elementos) {
            const contenedor = actividades.utilidades.crearElemento('div', 'actividad-completar-textos');
            
            elementos.forEach((elemento, indice) => {
                const contenedorElemento = actividades.utilidades.crearElemento('div', 'elemento-completar-texto');
                contenedorElemento.setAttribute('data-elemento-id', elemento.id);
                
                // Crear la oración con espacios
                const contenedorOracion = actividades.utilidades.crearElemento('div', 'contenedor-oracion');
                
                let htmlOracion = elemento.oracion;
                elemento.espacios.forEach(espacio => {
                    const marcadorEspacio = `[${espacio.espacioId}]`;
                    const zonaColocar = `<span class="zona-colocar" data-espacio-id="${espacio.espacioId}" data-respuesta="${espacio.respuesta}"></span>`;
                    htmlOracion = htmlOracion.replace(marcadorEspacio, zonaColocar);
                });
                
                contenedorOracion.innerHTML = htmlOracion;
                contenedorElemento.appendChild(contenedorOracion);
                
                // Crear banco de palabras
                const contenedorBanco = actividades.utilidades.crearElemento('div', 'banco-palabras');
                
                const bancoMezclado = actividades.utilidades.mezclar(elemento.banco);
                bancoMezclado.forEach(palabra => {
                    const elementoPalabra = actividades.utilidades.crearElemento('span', 'palabra-arrastrable', palabra);
                    elementoPalabra.setAttribute('draggable', 'true');
                    elementoPalabra.setAttribute('data-palabra', palabra);
                    contenedorBanco.appendChild(elementoPalabra);
                });
                
                contenedorElemento.appendChild(contenedorBanco);
                contenedor.appendChild(contenedorElemento);
            });
            
            return contenedor;
        },
        
        renderizar: function(raiz, modelo) {
            raiz.innerHTML = '';
            const actividad = this.construir(modelo.elementos);
            raiz.appendChild(actividad);
            
            // Implementar arrastrar y soltar
            this._configurarArrastrarSoltar(raiz);
        },
        
        _configurarArrastrarSoltar: function(contenedor) {
            const arrastrables = contenedor.querySelectorAll('.palabra-arrastrable');
            const zonasColocar = contenedor.querySelectorAll('.zona-colocar');
            
            arrastrables.forEach(arrastrable => {
                arrastrable.addEventListener('dragstart', (e) => {
                    e.dataTransfer.setData('text/plain', e.target.dataset.palabra);
                    e.target.classList.add('arrastrando');
                });
                
                arrastrable.addEventListener('dragend', (e) => {
                    e.target.classList.remove('arrastrando');
                });
            });
            
            zonasColocar.forEach(zona => {
                zona.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    zona.classList.add('sobre-zona');
                });
                
                zona.addEventListener('dragleave', () => {
                    zona.classList.remove('sobre-zona');
                });
                
                zona.addEventListener('drop', (e) => {
                    e.preventDefault();
                    const palabra = e.dataTransfer.getData('text/plain');
                    zona.textContent = palabra;
                    zona.classList.remove('sobre-zona');
                    zona.classList.add('ocupada');
                    zona.dataset.respuestaUsuario = palabra;
                });
            });
        },
        
        evaluar: function(modelo, respuestasUsuario) {
            let correctas = 0;
            const total = Object.keys(respuestasUsuario).length;
            const detalles = [];
            
            modelo.elementos.forEach(elemento => {
                elemento.espacios.forEach(espacio => {
                    const respuestaUsuario = respuestasUsuario[espacio.espacioId];
                    const esCorrecta = respuestaUsuario === espacio.respuesta;
                    if (esCorrecta) correctas++;
                    
                    detalles.push({
                        espacioId: espacio.espacioId,
                        correcto: esCorrecta
                    });
                });
            });
            
            return { correctas, total, detalles };
        },
        
        inicializar: async function(idActividad, datosIniciales) {
            const contenedor = document.getElementById(idActividad);
            if (!contenedor) throw new Error(`Contenedor ${idActividad} no encontrado`);
            
            this.renderizar(contenedor, datosIniciales);
            return Promise.resolve();
        }
    },

    // Relacionar conceptos (matching)
    relacionarConceptos: {
        construir: function(pares) {
            const contenedor = actividades.utilidades.crearElemento('div', 'actividad-relacionar-pares');
            
            const columnaIzquierda = actividades.utilidades.crearElemento('div', 'columna-relacion columna-izquierda');
            const columnaDerecha = actividades.utilidades.crearElemento('div', 'columna-relacion columna-derecha');
            
            pares.forEach(par => {
                const elementoIzquierdo = actividades.utilidades.crearElemento('div', 'elemento-relacion elemento-izquierdo');
                elementoIzquierdo.setAttribute('data-izquierdo-id', par.izquierdoId);
                elementoIzquierdo.textContent = par.izquierdo;
                
                const elementoDerecho = actividades.utilidades.crearElemento('div', 'elemento-relacion elemento-derecho');
                elementoDerecho.setAttribute('data-derecho-id', par.derechoId);
                elementoDerecho.textContent = par.derecho;
                
                columnaIzquierda.appendChild(elementoIzquierdo);
                columnaDerecha.appendChild(elementoDerecho);
            });
            
            contenedor.appendChild(columnaIzquierda);
            contenedor.appendChild(columnaDerecha);
            
            return contenedor;
        },
        
        renderizar: function(raiz, modelo) {
            raiz.innerHTML = '';
            const actividad = this.construir(modelo.pares);
            raiz.appendChild(actividad);
            
            this._configurarRelaciones(raiz);
        },
        
        _configurarRelaciones: function(contenedor) {
            let seleccionadoIzquierdo = null;
            const relaciones = new Map();
            
            const elementosIzquierdos = contenedor.querySelectorAll('.elemento-izquierdo');
            const elementosDerechos = contenedor.querySelectorAll('.elemento-derecho');
            
            elementosIzquierdos.forEach(elemento => {
                elemento.addEventListener('click', () => {
                    elementosIzquierdos.forEach(e => e.classList.remove('seleccionado'));
                    elemento.classList.add('seleccionado');
                    seleccionadoIzquierdo = elemento;
                });
            });
            
            elementosDerechos.forEach(elemento => {
                elemento.addEventListener('click', () => {
                    if (seleccionadoIzquierdo) {
                        const idIzquierdo = seleccionadoIzquierdo.dataset.izquierdoId;
                        const idDerecho = elemento.dataset.derechoId;
                        
                        // Limpiar relaciones anteriores
                        relaciones.forEach((valor, clave) => {
                            if (valor === idDerecho || clave === idIzquierdo) {
                                relaciones.delete(clave);
                            }
                        });
                        
                        relaciones.set(idIzquierdo, idDerecho);
                        
                        // Actualizar UI
                        elementosIzquierdos.forEach(e => e.classList.remove('relacionado'));
                        elementosDerechos.forEach(e => e.classList.remove('relacionado'));
                        
                        relaciones.forEach((idD, idI) => {
                            const elementoIzq = contenedor.querySelector(`[data-izquierdo-id="${idI}"]`);
                            const elementoDer = contenedor.querySelector(`[data-derecho-id="${idD}"]`);
                            elementoIzq.classList.add('relacionado');
                            elementoDer.classList.add('relacionado');
                        });
                        
                        seleccionadoIzquierdo.classList.remove('seleccionado');
                        seleccionadoIzquierdo = null;
                    }
                });
            });
            
            contenedor._obtenerRelaciones = () => {
                const resultado = [];
                relaciones.forEach((idDerecho, idIzquierdo) => {
                    resultado.push({ idIzquierdo, idDerecho });
                });
                return resultado;
            };
        },
        
        evaluar: function(modelo, relaciones) {
            let correctas = 0;
            const total = modelo.pares.length;
            const detalles = [];
            
            modelo.pares.forEach(par => {
                const relacionUsuario = relaciones.find(r => r.idIzquierdo === par.izquierdoId);
                const esCorrecta = relacionUsuario && relacionUsuario.idDerecho === par.derechoId;
                if (esCorrecta) correctas++;
                
                detalles.push({
                    izquierdoId: par.izquierdoId,
                    correcto: esCorrecta
                });
            });
            
            return { correctas, total, detalles };
        },
        
        inicializar: async function(idActividad, datosIniciales) {
            const contenedor = document.getElementById(idActividad);
            if (!contenedor) throw new Error(`Contenedor ${idActividad} no encontrado`);
            
            this.renderizar(contenedor, datosIniciales);
            return Promise.resolve();
        }
    },

    // Relacionar normativas (leyes, normas, manuales)
    relacionarNormativas: {
        muestrear: function(banco, k = 2) {
            const muestreados = [];
            
            // Tomar k elementos de cada categoría
            const leyes = actividades.utilidades.mezclar(banco.leyes).slice(0, k);
            const normas = actividades.utilidades.mezclar(banco.normas).slice(0, k);
            const manuales = actividades.utilidades.mezclar(banco.manuales).slice(0, k);
            
            muestreados.push(...leyes, ...normas, ...manuales);
            return actividades.utilidades.mezclar(muestreados);
        },
        
        construir: function(pares) {
            return actividades.relacionarConceptos.construir(pares);
        },
        
        renderizar: function(raiz, modelo) {
            actividades.relacionarConceptos.renderizar(raiz, modelo);
        },
        
        evaluar: function(modelo, relaciones) {
            return actividades.relacionarConceptos.evaluar(modelo, relaciones);
        },
        
        inicializar: async function(idActividad, bancoCompleto) {
            const contenedor = document.getElementById(idActividad);
            if (!contenedor) throw new Error(`Contenedor ${idActividad} no encontrado`);
            
            const elementosMuestreados = this.muestrear(bancoCompleto);
            const pares = elementosMuestreados.map((elemento, indice) => ({
                izquierdoId: `izq_${indice}`,
                izquierdo: elemento.titulo,
                derechoId: `der_${indice}`,
                derecho: elemento.descripcion
            }));
            
            this.renderizar(contenedor, { pares });
            return Promise.resolve();
        }
    },

    // Organigrama (colocar en espacios)
    organigrama: {
        construir: function(configuracion) {
            const contenedor = actividades.utilidades.crearElemento('div', 'actividad-organigrama');
            
            // Crear área de nodos disponibles
            const bancoNodos = actividades.utilidades.crearElemento('div', 'banco-nodos');
            const tituloNodos = actividades.utilidades.crearElemento('h4', '', 'Elementos disponibles:');
            bancoNodos.appendChild(tituloNodos);
            
            configuracion.nodos.forEach(nodo => {
                const elementoNodo = actividades.utilidades.crearElemento('div', 'nodo-arrastrable', nodo.etiqueta);
                elementoNodo.setAttribute('draggable', 'true');
                elementoNodo.setAttribute('data-nodo-id', nodo.id);
                bancoNodos.appendChild(elementoNodo);
            });
            
            // Crear área del organigrama con espacios
            const areaOrganigrama = actividades.utilidades.crearElemento('div', 'area-organigrama');
            
            configuracion.espacios.forEach(espacio => {
                const elementoEspacio = actividades.utilidades.crearElemento('div', 'espacio-organigrama');
                elementoEspacio.setAttribute('data-espacio-id', espacio.espacioId);
                elementoEspacio.setAttribute('data-acepta', JSON.stringify(espacio.acepta));
                
                const etiquetaEspacio = actividades.utilidades.crearElemento('div', 'etiqueta-espacio', `Espacio: ${espacio.espacioId}`);
                elementoEspacio.appendChild(etiquetaEspacio);
                areaOrganigrama.appendChild(elementoEspacio);
            });
            
            contenedor.appendChild(bancoNodos);
            contenedor.appendChild(areaOrganigrama);
            
            return contenedor;
        },
        
        renderizar: function(raiz, configuracion) {
            raiz.innerHTML = '';
            const actividad = this.construir(configuracion);
            raiz.appendChild(actividad);
            
            this._configurarOrganigrama(raiz);
        },
        
        _configurarOrganigrama: function(contenedor) {
            const nodos = contenedor.querySelectorAll('.nodo-arrastrable');
            const espacios = contenedor.querySelectorAll('.espacio-organigrama');
            
            nodos.forEach(nodo => {
                nodo.addEventListener('dragstart', (e) => {
                    e.dataTransfer.setData('text/plain', e.target.dataset.nodoId);
                    e.target.classList.add('arrastrando');
                });
                
                nodo.addEventListener('dragend', (e) => {
                    e.target.classList.remove('arrastrando');
                });
            });
            
            espacios.forEach(espacio => {
                espacio.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    const nodoId = e.dataTransfer.getData('text/plain');
                    const acepta = JSON.parse(espacio.dataset.acepta);
                    
                    if (acepta.includes(nodoId)) {
                        espacio.classList.add('colocacion-valida');
                    } else {
                        espacio.classList.add('colocacion-invalida');
                    }
                });
                
                espacio.addEventListener('dragleave', () => {
                    espacio.classList.remove('colocacion-valida', 'colocacion-invalida');
                });
                
                espacio.addEventListener('drop', (e) => {
                    e.preventDefault();
                    const nodoId = e.dataTransfer.getData('text/plain');
                    const acepta = JSON.parse(espacio.dataset.acepta);
                    
                    if (acepta.includes(nodoId)) {
                        // Limpiar espacio anterior si existe
                        const nodoExistente = espacio.querySelector('.nodo-colocado');
                        if (nodoExistente) {
                            nodoExistente.remove();
                        }
                        
                        // Crear nodo colocado
                        const elementoNodo = contenedor.querySelector(`[data-nodo-id="${nodoId}"]`);
                        const nodoColocado = actividades.utilidades.crearElemento('div', 'nodo-colocado', elementoNodo.textContent);
                        nodoColocado.setAttribute('data-nodo-id', nodoId);
                        
                        espacio.appendChild(nodoColocado);
                        espacio.dataset.nodoColocado = nodoId;
                        
                        // Ocultar nodo original
                        elementoNodo.style.display = 'none';
                    }
                    
                    espacio.classList.remove('colocacion-valida', 'colocacion-invalida');
                });
            });
        },
        
        evaluar: function(configuracion, colocacion) {
            let correctas = 0;
            let total = 0;
            
            configuracion.espacios.forEach(espacio => {
                total++;
                const nodoColocadoId = colocacion[espacio.espacioId];
                if (nodoColocadoId && espacio.acepta.includes(nodoColocadoId)) {
                    correctas++;
                }
            });
            
            return { correctas, total };
        },
        
        inicializar: async function(idActividad, datosIniciales) {
            const contenedor = document.getElementById(idActividad);
            if (!contenedor) throw new Error(`Contenedor ${idActividad} no encontrado`);
            
            this.renderizar(contenedor, datosIniciales);
            return Promise.resolve();
        }
    },

    // Cuestionario de opción múltiple
    cuestionario: {
        opcionMultiple: {
            construir: function(preguntas) {
                const contenedor = actividades.utilidades.crearElemento('div', 'actividad-cuestionario-multiple');
                
                preguntas.forEach((pregunta, indice) => {
                    const contenedorPregunta = actividades.utilidades.crearElemento('div', 'contenedor-pregunta');
                    contenedorPregunta.setAttribute('data-pregunta-id', pregunta.id);
                    
                    const textoPregunta = actividades.utilidades.crearElemento('div', 'texto-pregunta', `${indice + 1}. ${pregunta.texto}`);
                    
                    const contenedorOpciones = actividades.utilidades.crearElemento('div', 'contenedor-opciones');
                    
                    pregunta.opciones.forEach((opcion, indiceOpcion) => {
                        const etiquetaOpcion = actividades.utilidades.crearElemento('label', 'etiqueta-opcion');
                        
                        const inputRadio = actividades.utilidades.crearElemento('input');
                        inputRadio.type = 'radio';
                        inputRadio.name = `pregunta_${pregunta.id}`;
                        inputRadio.value = indiceOpcion;
                        inputRadio.className = 'input-opcion';
                        
                        const textoOpcion = actividades.utilidades.crearElemento('span', 'texto-opcion', opcion);
                        
                        etiquetaOpcion.appendChild(inputRadio);
                        etiquetaOpcion.appendChild(textoOpcion);
                        contenedorOpciones.appendChild(etiquetaOpcion);
                    });
                    
                    contenedorPregunta.appendChild(textoPregunta);
                    contenedorPregunta.appendChild(contenedorOpciones);
                    
                    if (pregunta.explicacion) {
                        const elementoExplicacion = actividades.utilidades.crearElemento('div', 'explicacion-pregunta oculta', pregunta.explicacion);
                        elementoExplicacion.setAttribute('data-explicacion', pregunta.explicacion);
                        contenedorPregunta.appendChild(elementoExplicacion);
                    }
                    
                    contenedor.appendChild(contenedorPregunta);
                });
                
                return contenedor;
            },
            
            renderizar: function(raiz, modelo) {
                raiz.innerHTML = '';
                const actividad = this.construir(modelo.preguntas);
                raiz.appendChild(actividad);
            },
            
            mezclarOpciones: function(modelo) {
                modelo.preguntas.forEach(pregunta => {
                    const respuestaCorrecta = pregunta.opciones[pregunta.indiceRespuesta];
                    pregunta.opciones = actividades.utilidades.mezclar(pregunta.opciones);
                    pregunta.indiceRespuesta = pregunta.opciones.indexOf(respuestaCorrecta);
                });
            },
            
            evaluar: function(modelo, respuestasUsuario) {
                let puntuacion = 0;
                const total = modelo.preguntas.length;
                const detalles = [];
                
                modelo.preguntas.forEach(pregunta => {
                    const respuestaUsuario = respuestasUsuario[pregunta.id];
                    const esCorrecta = respuestaUsuario === pregunta.indiceRespuesta;
                    if (esCorrecta) puntuacion++;
                    
                    detalles.push({
                        id: pregunta.id,
                        correcto: esCorrecta
                    });
                });
                
                return { puntuacion, total, detalles };
            },
            
            inicializar: async function(idActividad, datosIniciales) {
                const contenedor = document.getElementById(idActividad);
                if (!contenedor) throw new Error(`Contenedor ${idActividad} no encontrado`);
                
                this.renderizar(contenedor, datosIniciales);
                return Promise.resolve();
            }
        }
    },

    // Tarea SDD (marcar como concluido)
    tareaSDD: {
        construir: function(configuracion) {
            const contenedor = actividades.utilidades.crearElemento('div', 'actividad-tarea-sdd');
            
            const encabezado = actividades.utilidades.crearElemento('div', 'encabezado-tarea');
            const titulo = actividades.utilidades.crearElemento('h3', '', 'Tarea SDD');
            const instrucciones = actividades.utilidades.crearElemento('div', 'instrucciones-tarea', configuracion.instrucciones);
            
            encabezado.appendChild(titulo);
            encabezado.appendChild(instrucciones);
            
            const contenidoTarea = actividades.utilidades.crearElemento('div', 'contenido-tarea');
            
            if (configuracion.recursos) {
                const seccionRecursos = actividades.utilidades.crearElemento('div', 'seccion-recursos');
                const tituloRecursos = actividades.utilidades.crearElemento('h4', '', 'Recursos:');
                seccionRecursos.appendChild(tituloRecursos);
                
                configuracion.recursos.forEach(recurso => {
                    const enlaceRecurso = actividades.utilidades.crearElemento('a', 'enlace-recurso', recurso.nombre);
                    enlaceRecurso.href = recurso.url;
                    enlaceRecurso.target = '_blank';
                    seccionRecursos.appendChild(enlaceRecurso);
                });
                
                contenidoTarea.appendChild(seccionRecursos);
            }
            
            const botonCompletar = actividades.utilidades.crearElemento('button', 'boton-completar-tarea', 'Marcar como Completada');
            botonCompletar.addEventListener('click', () => {
                this._marcarCompletada(contenedor, configuracion);
            });
            
            contenedor.appendChild(encabezado);
            contenedor.appendChild(contenidoTarea);
            contenedor.appendChild(botonCompletar);
            
            return contenedor;
        },
        
        renderizar: function(raiz, configuracion) {
            raiz.innerHTML = '';
            const actividad = this.construir(configuracion);
            raiz.appendChild(actividad);
        },
        
        _marcarCompletada: function(contenedor, configuracion) {
            const boton = contenedor.querySelector('.boton-completar-tarea');
            boton.textContent = 'Tarea Completada';
            boton.disabled = true;
            boton.classList.add('completada');
            
            const mensaje = actividades.utilidades.crearElemento('div', 'mensaje-completada', '¡Tarea marcada como completada!');
            contenedor.appendChild(mensaje);
            
            // Guardar estado
            actividades.utilidades.guardarDatos(`tarea_sdd_${configuracion.id}`, {
                completada: true,
                fecha: new Date().toISOString()
            });
        },
        
        evaluar: function(configuracion) {
            const datos = actividades.utilidades.cargarDatos(`tarea_sdd_${configuracion.id}`);
            return {
                completada: datos ? datos.completada : false,
                fecha: datos ? datos.fecha : null
            };
        },
        
        inicializar: async function(idActividad, configuracion) {
            const contenedor = document.getElementById(idActividad);
            if (!contenedor) throw new Error(`Contenedor ${idActividad} no encontrado`);
            
            this.renderizar(contenedor, configuracion);
            
            // Verificar si ya está completada
            const estado = this.evaluar(configuracion);
            if (estado.completada) {
                this._marcarCompletada(contenedor, configuracion);
            }
            
            return Promise.resolve();
        }
    },

    // Examen general
    examen: {
        construir: function(configuracion) {
            const contenedor = actividades.utilidades.crearElemento('div', 'actividad-examen');
            
            const encabezado = actividades.utilidades.crearElemento('div', 'encabezado-examen');
            const titulo = actividades.utilidades.crearElemento('h3', '', configuracion.titulo || 'Examen');
            const descripcion = actividades.utilidades.crearElemento('div', 'descripcion-examen', configuracion.descripcion || '');
            
            encabezado.appendChild(titulo);
            if (configuracion.descripcion) {
                encabezado.appendChild(descripcion);
            }
            
            const contenidoExamen = actividades.utilidades.crearElemento('div', 'contenido-examen');
            
            // Mostrar información del examen
            if (configuracion.tiempoLimite) {
                const infoTiempo = actividades.utilidades.crearElemento('div', 'info-tiempo', `Tiempo límite: ${configuracion.tiempoLimite} minutos`);
                contenidoExamen.appendChild(infoTiempo);
            }
            
            if (configuracion.numeroPreguntas) {
                const infoPreguntas = actividades.utilidades.crearElemento('div', 'info-preguntas', `Número de preguntas: ${configuracion.numeroPreguntas}`);
                contenidoExamen.appendChild(infoPreguntas);
            }
            
            const botonIniciar = actividades.utilidades.crearElemento('button', 'boton-iniciar-examen', 'Iniciar Examen');
            botonIniciar.addEventListener('click', () => {
                this._iniciarExamen(contenedor, configuracion);
            });
            
            contenedor.appendChild(encabezado);
            contenedor.appendChild(contenidoExamen);
            contenedor.appendChild(botonIniciar);
            
            return contenedor;
        },
        
        renderizar: function(raiz, configuracion) {
            raiz.innerHTML = '';
            const actividad = this.construir(configuracion);
            raiz.appendChild(actividad);
        },
        
        _iniciarExamen: function(contenedor, configuracion) {
            const boton = contenedor.querySelector('.boton-iniciar-examen');
            boton.textContent = 'Examen en Progreso...';
            boton.disabled = true;
            
            const mensaje = actividades.utilidades.crearElemento('div', 'mensaje-examen', 'El examen ha sido iniciado. Serás redirigido a la página del examen.');
            contenedor.appendChild(mensaje);
            
            setTimeout(() => {
                if (configuracion.urlExamen) {
                    window.location.href = configuracion.urlExamen;
                } else {
                    mensaje.textContent = 'URL del examen no configurada.';
                }
            }, 2000);
        },
        
        evaluar: function(configuracion, respuestas) {
            return {
                iniciado: true,
                completado: respuestas ? true : false,
                puntuacion: respuestas ? respuestas.puntuacion : null
            };
        },
        
        inicializar: async function(idActividad, configuracion) {
            const contenedor = document.getElementById(idActividad);
            if (!contenedor) throw new Error(`Contenedor ${idActividad} no encontrado`);
            
            this.renderizar(contenedor, configuracion);
            return Promise.resolve();
        }
    }
};

// Función de inicialización global
actividades.inicializar = function(tipo, idActividad, configuracion) {
    switch(tipo) {
        case 'completarTextos':
            return this.completarTextos.inicializar(idActividad, configuracion);
        case 'relacionarConceptos':
            return this.relacionarConceptos.inicializar(idActividad, configuracion);
        case 'relacionarNormativas':
            return this.relacionarNormativas.inicializar(idActividad, configuracion);
        case 'organigrama':
            return this.organigrama.inicializar(idActividad, configuracion);
        case 'cuestionario':
        case 'cuestionario.opcionMultiple':
            return this.cuestionario.opcionMultiple.inicializar(idActividad, configuracion);
        case 'tareaSDD':
            return this.tareaSDD.inicializar(idActividad, configuracion);
        case 'examen':
            return this.examen.inicializar(idActividad, configuracion);
        default:
            throw new Error(`Tipo de actividad no reconocido: ${tipo}`);
    }
};