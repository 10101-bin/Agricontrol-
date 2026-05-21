function consultarPrecios() {
    const tbody = document.querySelector('#tablaResultados');
    const total = document.querySelector('#totalResultados');

    tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted">Consultando...</td></tr>';
    total.textContent = 'Consultando';

    const params = new URLSearchParams({
        buscar: document.getElementById('buscar').value.trim(),
        producto: document.getElementById('producto').value,
        departamento: document.getElementById('departamento').value,
        municipio: document.getElementById('municipio').value,
        fecha_desde: document.getElementById('fecha_desde').value,
        fecha_hasta: document.getElementById('fecha_hasta').value
    });

    fetch('api.php?' + params.toString())
        .then(response => response.text())
        .then(text => {
            let data;

            try {
                data = JSON.parse(text);
            } catch (error) {
                throw new Error(text || 'La respuesta del servidor no es JSON valido.');
            }

            if (data.error) {
                throw new Error(data.error);
            }

            actualizarTabla(data);
        })
        .catch(error => {
            console.error('Error:', error);
            total.textContent = 'Error';
            tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">${escapeHtml(error.message || 'No se pudo consultar la informacion')}</td></tr>`;
        });
}

function actualizarTabla(data) {
    const tbody = document.querySelector('#tablaResultados');
    const total = document.querySelector('#totalResultados');

    total.textContent = `${data.length} resultado${data.length === 1 ? '' : 's'}`;

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted">No hay datos con los filtros seleccionados</td></tr>';
        return;
    }

    tbody.innerHTML = data.map(item => `
        <tr>
            <td>${formatFecha(item.fecha)}</td>
            <td>${escapeHtml(item.producto)}</td>
            <td>${escapeHtml(item.departamento || '-')}</td>
            <td>${escapeHtml(item.municipio)}</td>
            <td>${escapeHtml(formatoPresentacion(item))}</td>
            <td>${escapeHtml(formatoUnidadBase(item))}</td>
            <td class="text-end"><strong>$${formatNumber(item.precio_promedio)}</strong></td>
            <td class="text-end">${item.precio_por_unidad ? '$' + formatNumber(item.precio_por_unidad) : '-'}</td>
            <td>${escapeHtml(item.fuente_datos || 'SIPSA')}</td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirEditarPrecio(${Number(item.id_precio)}, ${Number(item.precio_promedio)}, '${escapeJs(item.fuente_datos || 'SIPSA')}')">Editar</button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarRegistroSipsa('precio', ${Number(item.id_precio)})">Eliminar</button>
            </td>
        </tr>
    `).join('');
}

function formatoPresentacion(item) {
    if (item.presentacion && item.cantidad_unidad && item.unidad_base) {
        return `${item.presentacion} (${formatCantidad(item.cantidad_unidad)} ${item.unidad_base})`;
    }

    return item.presentacion || item.unidad_medida || '-';
}

function formatoUnidadBase(item) {
    if (item.cantidad_unidad && item.unidad_base) {
        return `${formatCantidad(item.cantidad_unidad)} ${item.unidad_base}`;
    }

    return item.unidad_base || item.unidad_medida || '-';
}

function filtrarMunicipiosPorDepartamento() {
    const departamentoId = document.getElementById('departamento').value;
    const municipioSelect = document.getElementById('municipio');

    Array.from(municipioSelect.options).forEach(option => {
        if (!option.value) {
            option.hidden = false;
            return;
        }

        option.hidden = departamentoId !== '' && option.dataset.departamento !== departamentoId;
    });

    const selected = municipioSelect.options[municipioSelect.selectedIndex];
    if (selected && selected.hidden) {
        municipioSelect.value = '';
    }
}

function formatFecha(fecha) {
    if (!fecha) return '-';

    const partes = fecha.split('-');
    if (partes.length === 3) {
        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }

    return fecha;
}

function formatNumber(num) {
    return new Intl.NumberFormat('es-CO', {
        maximumFractionDigits: 0
    }).format(Math.round(Number(num) || 0));
}

function formatCantidad(num) {
    return new Intl.NumberFormat('es-CO', {
        maximumFractionDigits: 2
    }).format(Number(num) || 0);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

document.addEventListener('DOMContentLoaded', () => {
    const departamento = document.getElementById('departamento');
    const buscar = document.getElementById('buscar');
    const btnConsultar = document.getElementById('btnConsultar');

    if (departamento) {
        departamento.addEventListener('change', filtrarMunicipiosPorDepartamento);
    }

    if (buscar) {
        buscar.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                consultarPrecios();
            }
        });
    }

    if (btnConsultar) {
        btnConsultar.addEventListener('click', consultarPrecios);
    }

    const formEditarPrecio = document.getElementById('formEditarPrecio');
    if (formEditarPrecio) {
        formEditarPrecio.addEventListener('submit', guardarPrecioEditado);
    }
});

function abrirEditarPrecio(id, precio, fuente) {
    document.getElementById('editPrecioId').value = id;
    document.getElementById('editPrecioValor').value = Math.round(precio || 0);
    document.getElementById('editPrecioFuente').value = fuente || 'SIPSA';

    const modal = new bootstrap.Modal(document.getElementById('modalEditarPrecio'));
    modal.show();
}

function guardarPrecioEditado(event) {
    event.preventDefault();

    const formData = new FormData();
    formData.append('tipo', 'precio');
    formData.append('id', document.getElementById('editPrecioId').value);
    formData.append('valor', document.getElementById('editPrecioValor').value);
    formData.append('fuente', document.getElementById('editPrecioFuente').value);

    fetch('guardar_registro.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.exito) throw new Error(data.mensaje || 'No se pudo guardar.');
            bootstrap.Modal.getInstance(document.getElementById('modalEditarPrecio')).hide();
            consultarPrecios();
        })
        .catch(error => alert(error.message));
}

function eliminarRegistroSipsa(tipo, id) {
    if (!confirm('¿Eliminar este registro?')) {
        return;
    }

    const formData = new FormData();
    formData.append('tipo', tipo);
    formData.append('id', id);

    fetch('eliminar_registro.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.exito) throw new Error(data.mensaje || 'No se pudo eliminar.');
            consultarPrecios();
        })
        .catch(error => alert(error.message));
}

function escapeJs(value) {
    return String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}
