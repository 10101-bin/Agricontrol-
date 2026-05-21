// Cargar municipios según departamento (para nueva siembra)
function cargarMunicipios() {
    var deptoId = document.getElementById('departamento').value;
    var municipioSelect = document.getElementById('municipio');
    var buscarMunicipio = document.getElementById('buscar_municipio');
    
    if (deptoId) {
        fetch('get_municipios.php?id_departamento=' + deptoId)
            .then(response => response.json())
            .then(data => {
                municipioSelect.innerHTML = '<option value="">Seleccione municipio</option>';
                data.forEach(function(municipio) {
                    municipioSelect.innerHTML += '<option value="' + municipio.id_municipio + '" data-search="' + escapeHtml(municipio.nombre) + '">' + escapeHtml(municipio.nombre) + '</option>';
                });
                municipioSelect.disabled = false;
                if (buscarMunicipio) {
                    buscarMunicipio.value = '';
                    buscarMunicipio.disabled = false;
                }
                actualizarTotalOpciones(municipioSelect, 'total_municipios_filtrados', 'municipio');
            });
    } else {
        municipioSelect.innerHTML = '<option value="">Primero seleccione departamento</option>';
        municipioSelect.disabled = true;
        if (buscarMunicipio) {
            buscarMunicipio.value = '';
            buscarMunicipio.disabled = true;
        }
        actualizarTexto('total_municipios_filtrados', 'Seleccione departamento');
    }
}

// Cargar municipios según departamento (para edición)
function cargarMunicipiosEditar(selectedMunicipio = null) {
    var deptoId = document.getElementById('edit_id_departamento').value;
    var municipioSelect = document.getElementById('edit_id_municipio');
    
    if (deptoId) {
        fetch('get_municipios.php?id_departamento=' + deptoId)
            .then(response => response.json())
            .then(data => {
                municipioSelect.innerHTML = '<option value="">Seleccione municipio</option>';
                data.forEach(function(municipio) {
                    var selected = selectedMunicipio && selectedMunicipio == municipio.id_municipio ? 'selected' : '';
                    municipioSelect.innerHTML += '<option value="' + municipio.id_municipio + '" ' + selected + '>' + municipio.nombre + '</option>';
                });
                municipioSelect.disabled = false;
            });
    } else {
        municipioSelect.innerHTML = '<option value="">Primero seleccione departamento</option>';
        municipioSelect.disabled = true;
    }
}

function editarSiembra(id) {
    fetch('get_siembra.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_id_produccion').value = data.id_produccion;
            document.getElementById('edit_cantidad_producida').value = data.cantidad_producida || '';
            cargarPresentacionesCosecha(document.getElementById('edit_tipo_medida_cosecha'), data.id_producto, data.tipo_medida_cosecha || '');
            document.getElementById('edit_fecha_cosecha_real').value = data.fecha_cosecha_real || '';
            var modal = new bootstrap.Modal(document.getElementById('modalEditar'));
            modal.show();
        });
}

function registrarProduccion(id, idProducto) {
    document.getElementById('prod_id_produccion').value = id;
    var selectCosecha = document.querySelector('#modalProduccion select[name="tipo_medida_cosecha"]');
    cargarPresentacionesCosecha(selectCosecha, idProducto, '');
    var modal = new bootstrap.Modal(document.getElementById('modalProduccion'));
    modal.show();
}

function eliminarSiembra(id) {
    if (confirm('¿Estás seguro de eliminar esta siembra? Esta acción no se puede deshacer.')) {
        window.location.href = 'eliminar_siembra.php?id=' + id + '&csrf_token=' + document.querySelector('input[name="csrf_token"]').value;
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    var deptoSelect = document.getElementById('departamento');
    if (deptoSelect) {
        deptoSelect.addEventListener('change', cargarMunicipios);
    }

    var productoSelect = document.getElementById('id_producto');
    if (productoSelect) {
        productoSelect.addEventListener('change', function() {
            cargarDiasProducto();
            mostrarUnidadProducto();
        });
    }

    var buscarProducto = document.getElementById('buscar_producto');
    if (buscarProducto && productoSelect) {
        buscarProducto.addEventListener('input', function() {
            filtrarOpcionesSelect(productoSelect, buscarProducto.value);
            seleccionarCoincidenciaExacta(productoSelect, buscarProducto.value);
            cargarDiasProducto();
            mostrarUnidadProducto();
            actualizarTotalOpciones(productoSelect, null, 'producto');
        });
    }

    var buscarMunicipio = document.getElementById('buscar_municipio');
    var municipioSelect = document.getElementById('municipio');
    if (buscarMunicipio && municipioSelect) {
        buscarMunicipio.addEventListener('input', function() {
            filtrarOpcionesSelect(municipioSelect, buscarMunicipio.value);
            seleccionarCoincidenciaExacta(municipioSelect, buscarMunicipio.value);
            actualizarTotalOpciones(municipioSelect, 'total_municipios_filtrados', 'municipio');
        });
    }

    var limpiarFiltrosSiembra = document.getElementById('limpiar_filtros_siembra');
    if (limpiarFiltrosSiembra) {
        limpiarFiltrosSiembra.addEventListener('click', limpiarFiltrosFormularioSiembra);
    }

    var fechaSiembra = document.getElementById('fecha_siembra');
    if (fechaSiembra) {
        fechaSiembra.addEventListener('change', actualizarFechaCosecha);
    }

    var diasInput = document.getElementById('dias_cosecha_estimados');
    if (diasInput) {
        diasInput.addEventListener('input', actualizarFechaCosecha);
    }

    mostrarUnidadProducto();
    if (productoSelect) {
        actualizarTotalOpciones(productoSelect, null, 'producto');
    }
});

function actualizarFechaCosecha() {
    var fechaSiembra = document.getElementById('fecha_siembra');
    var diasInput = document.getElementById('dias_cosecha_estimados');
    var preview = document.getElementById('fecha_cosecha_estimada_preview');

    if (!fechaSiembra || !diasInput || !preview) {
        return;
    }

    var fecha = fechaSiembra.value;
    var dias = parseInt(diasInput.value, 10);

    if (fecha && !isNaN(dias)) {
        var fechaObj = new Date(fecha);
        fechaObj.setDate(fechaObj.getDate() + dias);
        preview.value = fechaObj.toISOString().split('T')[0];
    } else {
        preview.value = '';
    }
}

function cargarDiasProducto() {
    var productoSelect = document.getElementById('id_producto');
    var diasInput = document.getElementById('dias_cosecha_estimados');

    if (!productoSelect || !diasInput) {
        return;
    }

    var opcion = productoSelect.options[productoSelect.selectedIndex];
    if (opcion && opcion.dataset && opcion.dataset.dias) {
        diasInput.value = opcion.dataset.dias;
    } else {
        diasInput.value = '';
    }

    actualizarFechaCosecha();
}

function normalizarTextoBusqueda(texto) {
    return String(texto || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function filtrarOpcionesSelect(select, texto) {
    var busqueda = normalizarTextoBusqueda(texto);
    var seleccionOculta = false;

    Array.from(select.options).forEach(function(option) {
        if (!option.value) {
            option.hidden = false;
            return;
        }

        var textoOpcion = option.dataset.search || option.textContent;
        var visible = !busqueda || normalizarTextoBusqueda(textoOpcion).includes(busqueda);
        option.hidden = !visible;

        if (option.selected && !visible) {
            seleccionOculta = true;
        }
    });

    if (seleccionOculta) {
        select.value = '';
        if (select.id === 'id_producto') {
            cargarDiasProducto();
            mostrarUnidadProducto();
        }
    }
}

function seleccionarCoincidenciaExacta(select, texto) {
    var busqueda = normalizarTextoBusqueda(texto);
    if (!busqueda) {
        return;
    }

    var coincidencias = Array.from(select.options).filter(function(option) {
        if (!option.value || option.hidden) {
            return false;
        }

        var textoOpcion = option.dataset.search || option.textContent;
        return normalizarTextoBusqueda(textoOpcion) === busqueda;
    });

    if (coincidencias.length === 1) {
        select.value = coincidencias[0].value;
    }
}

function actualizarTotalOpciones(select, targetId, nombre) {
    if (!targetId) {
        return;
    }

    var visibles = Array.from(select.options).filter(function(option) {
        return option.value && !option.hidden;
    }).length;

    actualizarTexto(targetId, visibles + ' ' + nombre + (visibles === 1 ? '' : 's') + ' disponible' + (visibles === 1 ? '' : 's'));
}

function actualizarTexto(id, texto) {
    var elemento = document.getElementById(id);
    if (elemento) {
        elemento.textContent = texto;
    }
}

function limpiarFiltrosFormularioSiembra() {
    var buscarProducto = document.getElementById('buscar_producto');
    var productoSelect = document.getElementById('id_producto');
    var departamento = document.getElementById('departamento');
    var buscarMunicipio = document.getElementById('buscar_municipio');
    var municipio = document.getElementById('municipio');

    if (buscarProducto) buscarProducto.value = '';
    if (productoSelect) {
        productoSelect.value = '';
        filtrarOpcionesSelect(productoSelect, '');
    }

    if (departamento) departamento.value = '';
    if (buscarMunicipio) {
        buscarMunicipio.value = '';
        buscarMunicipio.disabled = true;
    }
    if (municipio) {
        municipio.innerHTML = '<option value="">Primero seleccione departamento</option>';
        municipio.disabled = true;
    }

    cargarDiasProducto();
    mostrarUnidadProducto();
    actualizarTexto('total_municipios_filtrados', 'Seleccione departamento');
}

function mostrarUnidadProducto() {
    var productoSelect = document.getElementById('id_producto');
    var textoUnidad = document.getElementById('unidad_producto_sugerida');

    if (!productoSelect || !textoUnidad) {
        return;
    }

    var opcion = productoSelect.options[productoSelect.selectedIndex];
    var unidad = opcion && opcion.dataset ? opcion.dataset.unidad : '';

    textoUnidad.textContent = unidad
        ? 'Unidad del producto en base de datos: ' + unidad
        : 'Unidad del producto: no seleccionada';
}

function obtenerPresentacionesProducto() {
    var script = document.getElementById('presentacionesProductoData');
    if (!script) {
        return {};
    }

    try {
        return JSON.parse(script.textContent || '{}');
    } catch (error) {
        return {};
    }
}

function cargarPresentacionesCosecha(select, idProducto, selectedValue) {
    if (!select) {
        return;
    }

    if (!select.dataset.baseOptions) {
        select.dataset.baseOptions = select.innerHTML;
    }

    select.innerHTML = select.dataset.baseOptions;

    var presentaciones = obtenerPresentacionesProducto()[idProducto] || [];
    if (!presentaciones.length) {
        select.value = selectedValue || select.value;
        return;
    }

    var grupo = document.createElement('optgroup');
    grupo.label = 'Presentaciones SIPSA del producto';

    presentaciones.forEach(function(item) {
        var texto = formatoPresentacionSipsa(item);
        if (!texto) {
            return;
        }

        var option = document.createElement('option');
        option.value = texto.toLowerCase();
        option.textContent = texto;
        grupo.appendChild(option);
    });

    select.insertBefore(grupo, select.firstChild);

    if (selectedValue) {
        select.value = selectedValue;
    }
}

function formatoPresentacionSipsa(item) {
    var presentacion = item.presentacion || '';
    var cantidad = Number(item.cantidad_unidad || 0);
    var unidad = item.unidad_base || '';

    if (presentacion && cantidad > 0 && unidad) {
        return presentacion + ' ' + new Intl.NumberFormat('es-CO', { maximumFractionDigits: 2 }).format(cantidad) + ' ' + unidad;
    }

    return presentacion;
}

function escapeHtml(value) {
    return String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
