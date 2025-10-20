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

    // Relacionar columnas con líneas (nueva funcionalidad con nodos)
    relacionarColumnasLineas: {
        construir: function(pares) {
            const contenedor = actividades.utilidades.crearElemento('div', 'actividad-relacionar-pares');
            
            // Crear instrucciones
            const instrucciones = actividades.utilidades.crearElemento('div', 'instrucciones-relacionar');
            instrucciones.innerHTML = '<p><span class="icono">🔗</span> Arrastra desde el nodo de un concepto hasta el nodo de su definición correspondiente para conectarlos.</p>';
            
            // Crear contenedor de columnas
            const contenedorColumnas = actividades.utilidades.crearElemento('div', 'contenedor-columnas');
            
            // Crear columnas
            const columnaConceptos = actividades.utilidades.crearElemento('div', 'columna-conceptos');
            const columnaDefiniciones = actividades.utilidades.crearElemento('div', 'columna-definiciones');
            
            // Agregar títulos
            const tituloConceptos = actividades.utilidades.crearElemento('h4', '', 'Conceptos');
            const tituloDefiniciones = actividades.utilidades.crearElemento('h4', '', 'Definiciones');
            
            columnaConceptos.appendChild(tituloConceptos);
            columnaDefiniciones.appendChild(tituloDefiniciones);
            
            // Mezclar las definiciones para que no estén en orden
            const definicionesMezcladas = actividades.utilidades.mezclar([...pares]);
            
            // Crear elementos de conceptos
            pares.forEach((par, index) => {
                const elementoConcepto = actividades.utilidades.crearElemento('div', 'elemento-concepto');
                elementoConcepto.setAttribute('data-concepto-id', par.conceptoId || `concepto_${index}`);
                elementoConcepto.setAttribute('data-index', index);
                elementoConcepto.textContent = par.concepto;
                
                // Agregar nodo de conexión
                const nodoConcepto = actividades.utilidades.crearElemento('div', 'nodo-conexion');
                nodoConcepto.setAttribute('data-tipo', 'concepto');
                nodoConcepto.setAttribute('data-id', par.conceptoId || `concepto_${index}`);
                elementoConcepto.appendChild(nodoConcepto);
                
                columnaConceptos.appendChild(elementoConcepto);
            });
            
            // Crear elementos de definiciones
            definicionesMezcladas.forEach((par, index) => {
                const elementoDefinicion = actividades.utilidades.crearElemento('div', 'elemento-definicion');
                elementoDefinicion.setAttribute('data-definicion-id', par.definicionId || `definicion_${pares.indexOf(par)}`);
                elementoDefinicion.setAttribute('data-concepto-original', par.conceptoId || `concepto_${pares.indexOf(par)}`);
                elementoDefinicion.textContent = par.definicion;
                
                // Agregar nodo de conexión
                const nodoDefinicion = actividades.utilidades.crearElemento('div', 'nodo-conexion');
                nodoDefinicion.setAttribute('data-tipo', 'definicion');
                nodoDefinicion.setAttribute('data-id', par.definicionId || `definicion_${pares.indexOf(par)}`);
                nodoDefinicion.setAttribute('data-concepto-original', par.conceptoId || `concepto_${pares.indexOf(par)}`);
                elementoDefinicion.appendChild(nodoDefinicion);
                
                columnaDefiniciones.appendChild(elementoDefinicion);
            });
            
            // Crear SVG para las líneas
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('class', 'lineas-conexion');
            svg.style.position = 'absolute';
            svg.style.top = '0';
            svg.style.left = '0';
            svg.style.width = '100%';
            svg.style.height = '100%';
            svg.style.pointerEvents = 'none';
            svg.style.zIndex = '10';
            
            contenedorColumnas.appendChild(columnaConceptos);
            contenedorColumnas.appendChild(columnaDefiniciones);
            contenedorColumnas.appendChild(svg);
            
            contenedor.appendChild(instrucciones);
            contenedor.appendChild(contenedorColumnas);
            
            return contenedor;
        },
        
        renderizar: function(raiz, modelo) {
            raiz.innerHTML = '';
            const actividad = this.construir(modelo.pares);
            raiz.appendChild(actividad);
            
            this._configurarNodosArrastrar(raiz);
        },
        
        _configurarNodosArrastrar: function(contenedor) {
            const conexiones = new Map();
            const svg = contenedor.querySelector('.lineas-conexion');
            let nodoArrastrandose = null;
            let lineaTemporal = null;
            
            const nodos = contenedor.querySelectorAll('.nodo-conexion');
            
            // Función auxiliar para finalizar el arrastre
            const finalizarArrastre = () => {
                if (nodoArrastrandose) {
                    nodoArrastrandose.classList.remove('arrastrando');
                    nodoArrastrandose = null;
                }
                
                if (lineaTemporal) {
                    lineaTemporal.remove();
                    lineaTemporal = null;
                }
                
                // Limpiar estilos de destino
                const nodos = contenedor.querySelectorAll('.nodo-conexion');
                nodos.forEach(nodo => {
                    nodo.classList.remove('destino-valido', 'destino-invalido');
                });
            };
            
            nodos.forEach(nodo => {
                // Configurar eventos de drag and drop
                nodo.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    nodoArrastrandose = nodo;
                    nodo.classList.add('arrastrando');
                    
                    // Crear línea temporal
                    lineaTemporal = this._crearLineaTemporal(nodo, svg);
                    
                    // Resaltar nodos válidos como destino
                    this._resaltarNodosDestino(nodo, contenedor);
                });
                
                nodo.addEventListener('mouseenter', (e) => {
                    if (nodoArrastrandose && nodoArrastrandose !== nodo) {
                        e.preventDefault();
                        const esDestinoValido = this._esDestinoValido(nodoArrastrandose, nodo);
                        if (esDestinoValido) {
                            nodo.classList.add('destino-valido');
                        }
                    }
                });
                
                nodo.addEventListener('mouseleave', (e) => {
                    e.preventDefault();
                    nodo.classList.remove('destino-valido');
                });
                
                nodo.addEventListener('mouseup', (e) => {
                    e.preventDefault();
                    if (nodoArrastrandose && nodoArrastrandose !== nodo) {
                        const esDestinoValido = this._esDestinoValido(nodoArrastrandose, nodo);
                        if (esDestinoValido) {
                            this._crearConexion(nodoArrastrandose, nodo, conexiones, svg);
                        }
                    }
                    finalizarArrastre();
                });
            });
            
            // Eventos globales para el arrastre
            document.addEventListener('mousemove', (e) => {
                if (nodoArrastrandose && lineaTemporal) {
                    e.preventDefault();
                    this._actualizarLineaTemporal(e, lineaTemporal);
                }
            });
            
            document.addEventListener('mouseup', (e) => {
                if (nodoArrastrandose) {
                    e.preventDefault();
                    finalizarArrastre();
                }
            });
            
            // Guardar referencia a las conexiones para evaluación
            contenedor._obtenerConexiones = () => {
                const resultado = [];
                conexiones.forEach((definicionId, conceptoId) => {
                    resultado.push({ conceptoId, definicionId });
                });
                return resultado;
            };
            
            // Función para limpiar todas las conexiones
            contenedor._limpiarConexiones = () => {
                conexiones.clear();
                svg.innerHTML = '';
                const elementos = contenedor.querySelectorAll('.elemento-concepto, .elemento-definicion');
                elementos.forEach(el => {
                    el.classList.remove('relacionado', 'correcto', 'incorrecto');
                });
                const nodosConexion = contenedor.querySelectorAll('.nodo-conexion');
                nodosConexion.forEach(nodo => {
                    nodo.classList.remove('conectado');
                });
                // Notificar actualización de progreso si existe función global
                if (typeof window !== 'undefined' && typeof window.updateProgress === 'function') {
                    window.updateProgress();
                }
            };
        },
        
        _esDestinoValido: function(nodoOrigen, nodoDestino) {
            const elementoOrigen = nodoOrigen.closest('.elemento-concepto, .elemento-definicion');
            const elementoDestino = nodoDestino.closest('.elemento-concepto, .elemento-definicion');
            
            // Solo se puede conectar concepto con definición
            const origenEsConcepto = elementoOrigen.classList.contains('elemento-concepto');
            const destinoEsDefinicion = elementoDestino.classList.contains('elemento-definicion');
            const origenEsDefinicion = elementoOrigen.classList.contains('elemento-definicion');
            const destinoEsConcepto = elementoDestino.classList.contains('elemento-concepto');
            
            return (origenEsConcepto && destinoEsDefinicion) || (origenEsDefinicion && destinoEsConcepto);
        },
        
        _resaltarNodosDestino: function(nodoOrigen, contenedor) {
            const nodos = contenedor.querySelectorAll('.nodo-conexion');
            nodos.forEach(nodo => {
                if (nodo !== nodoOrigen) {
                    const esValido = this._esDestinoValido(nodoOrigen, nodo);
                    if (esValido) {
                        nodo.classList.add('destino-valido');
                    } else {
                        nodo.classList.add('destino-invalido');
                    }
                }
            });
        },
        
        _crearLineaTemporal: function(nodoOrigen, svg) {
            const rect = nodoOrigen.getBoundingClientRect();
            const svgRect = svg.getBoundingClientRect();
            
            const x1 = rect.left + rect.width / 2 - svgRect.left;
            const y1 = rect.top + rect.height / 2 - svgRect.top;
            
            const linea = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            linea.setAttribute('x1', x1);
            linea.setAttribute('y1', y1);
            linea.setAttribute('x2', x1);
            linea.setAttribute('y2', y1);
            linea.classList.add('linea-temporal');
            
            svg.appendChild(linea);
            return linea;
        },
        
        _actualizarLineaTemporal: function(evento, lineaTemporal) {
            const svg = lineaTemporal.closest('svg');
            const svgRect = svg.getBoundingClientRect();
            
            const x2 = evento.clientX - svgRect.left;
            const y2 = evento.clientY - svgRect.top;
            
            lineaTemporal.setAttribute('x2', x2);
            lineaTemporal.setAttribute('y2', y2);
        },
        
        _crearConexion: function(nodoOrigen, nodoDestino, conexiones, svg) {
            const elementoOrigen = nodoOrigen.closest('.elemento-concepto, .elemento-definicion');
            const elementoDestino = nodoDestino.closest('.elemento-concepto, .elemento-definicion');
            
            let conceptoId, definicionId;
            
            if (elementoOrigen.classList.contains('elemento-concepto')) {
                conceptoId = elementoOrigen.getAttribute('data-concepto-id');
                definicionId = elementoDestino.getAttribute('data-definicion-id');
            } else {
                conceptoId = elementoDestino.getAttribute('data-concepto-id');
                definicionId = elementoOrigen.getAttribute('data-definicion-id');
            }
            
            // Eliminar conexión anterior si existe
            this._eliminarConexionExistente(conexiones, conceptoId, definicionId, svg);
            
            // Crear nueva conexión
            conexiones.set(conceptoId, definicionId);
            this._dibujarLineaNodos(nodoOrigen, nodoDestino, svg, conceptoId);
            
            // Actualizar estilos
            elementoOrigen.classList.add('relacionado');
            elementoDestino.classList.add('relacionado');
            nodoOrigen.classList.add('conectado');
            nodoDestino.classList.add('conectado');
            
            // Actualizar campos ocultos del formulario
            this._actualizarCamposFormulario(svg.closest('.actividad-relacionar-pares'));
            
            // Notificar actualización de progreso si existe función global
            if (typeof window !== 'undefined' && typeof window.updateProgress === 'function') {
                window.updateProgress();
            }
        },
        
        _dibujarLineaNodos: function(nodoOrigen, nodoDestino, svg, conceptoId) {
            const rectOrigen = nodoOrigen.getBoundingClientRect();
            const rectDestino = nodoDestino.getBoundingClientRect();
            const svgRect = svg.getBoundingClientRect();
            
            const x1 = rectOrigen.left + rectOrigen.width / 2 - svgRect.left;
            const y1 = rectOrigen.top + rectOrigen.height / 2 - svgRect.top;
            const x2 = rectDestino.left + rectDestino.width / 2 - svgRect.left;
            const y2 = rectDestino.top + rectDestino.height / 2 - svgRect.top;
            
            const linea = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            linea.setAttribute('x1', x1);
            linea.setAttribute('y1', y1);
            linea.setAttribute('x2', x2);
            linea.setAttribute('y2', y2);
            linea.setAttribute('data-conexion', conceptoId);
            linea.classList.add('linea-conexion');
            
            svg.appendChild(linea);
        },
        
        _eliminarConexionExistente: function(conexiones, nuevoConceptoId, nuevaDefinicionId, svg) {
            // Eliminar conexiones que involucren el mismo concepto o definición
            const conexionesAEliminar = [];
            
            conexiones.forEach((definicionId, conceptoId) => {
                if (conceptoId === nuevoConceptoId || definicionId === nuevaDefinicionId) {
                    conexionesAEliminar.push(conceptoId);
                }
            });
            
            conexionesAEliminar.forEach(conceptoId => {
                conexiones.delete(conceptoId);
                // Eliminar línea SVG correspondiente
                const lineaExistente = svg.querySelector(`[data-conexion="${conceptoId}"]`);
                if (lineaExistente) {
                    lineaExistente.remove();
                }
            });
            
            // Limpiar estilos de elementos desconectados
            const elementosConceptos = document.querySelectorAll('.elemento-concepto');
            const elementosDefiniciones = document.querySelectorAll('.elemento-definicion');
            
            elementosConceptos.forEach(el => {
                const id = el.getAttribute('data-concepto-id');
                if (!conexiones.has(id)) {
                    el.classList.remove('relacionado');
                    const nodo = el.querySelector('.nodo-conexion');
                    if (nodo) nodo.classList.remove('conectado');
                }
            });
            
            elementosDefiniciones.forEach(el => {
                const id = el.getAttribute('data-definicion-id');
                let estaConectado = false;
                conexiones.forEach(defId => {
                    if (defId === id) estaConectado = true;
                });
                if (!estaConectado) {
                    el.classList.remove('relacionado');
                    const nodo = el.querySelector('.nodo-conexion');
                    if (nodo) nodo.classList.remove('conectado');
                }
            });
            
            // Actualizar campos ocultos del formulario
            this._actualizarCamposFormulario(svg.closest('.actividad-relacionar-pares'));
            
            // Notificar actualización de progreso si existe función global
            if (typeof window !== 'undefined' && typeof window.updateProgress === 'function') {
                window.updateProgress();
            }
        },
        
        _actualizarCamposFormulario: function(contenedor) {
            if (!contenedor) return;
            
            // Buscar el contenedor de la actividad
            let actividadId = contenedor.getAttribute('data-actividad-id') || contenedor.getAttribute('data-pregunta-id');
            if (!actividadId) return;
            
            console.log('Actualizando campos para actividad ID:', actividadId);
            
            // Obtener las conexiones actuales desde las líneas SVG
            const conexiones = new Map();
            const svg = contenedor.querySelector('svg');
            if (!svg) return;
            
            const lineas = svg.querySelectorAll('line[data-conexion]');
            
            lineas.forEach(linea => {
                const conceptoId = linea.getAttribute('data-conexion');
                
                // Obtener las coordenadas de la línea para encontrar los elementos conectados
                const x1 = parseFloat(linea.getAttribute('x1'));
                const y1 = parseFloat(linea.getAttribute('y1'));
                const x2 = parseFloat(linea.getAttribute('x2'));
                const y2 = parseFloat(linea.getAttribute('y2'));
                
                // Buscar elementos conectados por posición
                const elementosConcepto = contenedor.querySelectorAll('.elemento-concepto');
                const elementosDefinicion = contenedor.querySelectorAll('.elemento-definicion');
                
                let conceptoEncontrado = null;
                let definicionEncontrada = null;
                
                // Buscar el concepto conectado
                elementosConcepto.forEach(el => {
                    if (el.getAttribute('data-concepto-id') === conceptoId) {
                        conceptoEncontrado = el;
                    }
                });
                
                // Buscar la definición conectada por proximidad a las coordenadas
                elementosDefinicion.forEach(el => {
                    const rect = el.getBoundingClientRect();
                    const svgRect = svg.getBoundingClientRect();
                    const relativeX = rect.left + rect.width/2 - svgRect.left;
                    const relativeY = rect.top + rect.height/2 - svgRect.top;
                    
                    // Verificar si las coordenadas de la línea están cerca de este elemento
                    if ((Math.abs(relativeX - x2) < 50 && Math.abs(relativeY - y2) < 50) ||
                        (Math.abs(relativeX - x1) < 50 && Math.abs(relativeY - y1) < 50)) {
                        definicionEncontrada = el;
                    }
                });
                
                if (conceptoEncontrado && definicionEncontrada) {
                    const definicionId = definicionEncontrada.getAttribute('data-definicion-id');
                    if (definicionId) {
                        conexiones.set(conceptoId, definicionId);
                    }
                }
            });
            
            // Crear o actualizar campo oculto con las respuestas
            let campoRespuesta = document.querySelector(`input[name="respuesta_${actividadId}"]`);
            if (!campoRespuesta) {
                campoRespuesta = document.createElement('input');
                campoRespuesta.type = 'hidden';
                campoRespuesta.name = `respuesta_${actividadId}`;
                
                // Buscar el formulario y agregar el campo
                const formulario = document.getElementById('evaluation-form');
                if (formulario) {
                    formulario.appendChild(campoRespuesta);
                }
            }
            
            // Convertir conexiones a formato JSON
            const respuestas = {};
            conexiones.forEach((definicionId, conceptoId) => {
                respuestas[conceptoId] = definicionId;
            });
            
            campoRespuesta.value = JSON.stringify(respuestas);
            
            console.log('Campos formulario actualizados para actividad', actividadId, ':', respuestas);
            console.log('Campo oculto valor:', campoRespuesta.value);
            
            // Llamar a updateProgress después de actualizar el campo
            if (typeof window.updateProgress === 'function') {
                window.updateProgress();
            }
        },
        
        _dibujarLinea: function(elementoOrigen, elementoDestino, svg, conceptoId) {
            const rectOrigen = elementoOrigen.getBoundingClientRect();
            const rectDestino = elementoDestino.getBoundingClientRect();
            const rectContenedor = svg.getBoundingClientRect();
            
            // Calcular posiciones relativas
            const x1 = rectOrigen.right - rectContenedor.left - 6; // Punto derecho del elemento izquierdo
            const y1 = rectOrigen.top + rectOrigen.height / 2 - rectContenedor.top;
            const x2 = rectDestino.left - rectContenedor.left + 6; // Punto izquierdo del elemento derecho
            const y2 = rectDestino.top + rectDestino.height / 2 - rectContenedor.top;
            
            // Crear línea SVG
            const linea = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            linea.setAttribute('x1', x1);
            linea.setAttribute('y1', y1);
            linea.setAttribute('x2', x2);
            linea.setAttribute('y2', y2);
            linea.setAttribute('class', 'linea-conexion nueva');
            linea.setAttribute('data-conexion', conceptoId);
            
            svg.appendChild(linea);
            
            // Remover clase de animación después de la animación
            setTimeout(() => {
                linea.classList.remove('nueva');
            }, 600);
        },
        
        evaluar: function(modelo, conexiones) {
            let correctas = 0;
            const total = modelo.pares.length;
            const detalles = [];
            
            modelo.pares.forEach(par => {
                const conceptoId = par.conceptoId || `concepto_${modelo.pares.indexOf(par)}`;
                const definicionIdCorrecta = par.definicionId || `definicion_${modelo.pares.indexOf(par)}`;
                
                const conexionUsuario = conexiones.find(c => c.conceptoId === conceptoId);
                const esCorrecta = conexionUsuario && conexionUsuario.definicionId === definicionIdCorrecta;
                
                if (esCorrecta) correctas++;
                
                detalles.push({
                    conceptoId: conceptoId,
                    correcto: esCorrecta,
                    respuestaUsuario: conexionUsuario ? conexionUsuario.definicionId : null,
                    respuestaCorrecta: definicionIdCorrecta
                });
            });
            
            return { correctas, total, detalles };
        },
        
        mostrarResultados: function(contenedor, resultados) {
            const svg = contenedor.querySelector('.contenedor-lineas');
            const lineas = svg.querySelectorAll('.linea-conexion');
            
            // Actualizar estilos de elementos y líneas según resultados
            resultados.detalles.forEach(detalle => {
                const elementoConcepto = contenedor.querySelector(`[data-concepto-id="${detalle.conceptoId}"]`);
                const elementoDefinicion = contenedor.querySelector(`[data-definicion-id="${detalle.respuestaUsuario}"]`);
                const linea = svg.querySelector(`[data-conexion="${detalle.conceptoId}"]`);
                
                if (elementoConcepto) {
                    elementoConcepto.classList.add(detalle.correcto ? 'correcto' : 'incorrecto');
                }
                
                if (elementoDefinicion) {
                    elementoDefinicion.classList.add(detalle.correcto ? 'correcto' : 'incorrecto');
                }
                
                if (linea) {
                    linea.classList.add(detalle.correcto ? 'correcta' : 'incorrecta');
                }
            });
        },
        
        inicializar: async function(idActividad, datosIniciales) {
            const contenedor = document.getElementById(idActividad);
            if (!contenedor) throw new Error(`Contenedor ${idActividad} no encontrado`);
            
            this.renderizar(contenedor, datosIniciales);
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
        case 'relacionarColumnasLineas':
            return this.relacionarColumnasLineas.inicializar(idActividad, configuracion);
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