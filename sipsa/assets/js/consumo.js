function consultarConsumo() {
    const tbody = document.querySelector('#tablaResultados');
    const total = document.querySelector('#totalResultados');

    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Consultando...</td></tr>';
    total.textContent = 'Consultando';

    const params = new URLSearchParams({
        buscar: document.getElementById('buscar').value.trim(),
        producto: document.getElementById('producto').value,
        anio: document.getElementById('anio').value,
        fecha_desde: document.getElementById('fecha_desde').value,
        fecha_hasta: document.getElementById('fecha_hasta').value
    });

    fetch('api_consumo.php?' + params.toString())
        .then(response => response.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (error) {
                throw new Error(text || 'La respuesta del servidor no es JSON valida.');
            }

            if (data.error) {
                throw new Error(data.error);
            }

            actualizarTablaConsumo(data);
        })
        .catch(error => {
            total.textContent = 'Error';
            tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">${escapeHtml(error.message)}</td></tr>`;
        });
}

function actualizarTablaConsumo(data) {
    const tbody = document.querySelector('#tablaResultados');
    const total = document.querySelector('#totalResultados');

    total.textContent = `${data.length} resultado${data.length === 1 ? '' : 's'}`;

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No hay datos con los filtros seleccionados</td></tr>';
        return;
    }

    tbody.innerHTML = data.map(item => `
        <tr>
            <td>${formatFecha(item.fecha)}</td>
            <td>${escapeHtml(item.producto)}</td>
            <td>${escapeHtml(item.unidad_medida || '-')}</td>
            <td class="text-end"><strong>${formatNumber(item.cantidad_consumo)}</strong></td>
            <td>${escapeHtml(item.fuente || 'DANE')}</td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirEditarConsumo(${Number(item.id_consumo)}, ${Number(item.cantidad_consumo)}, '${escapeJs(item.fuente || 'DANE')}')">Editar</button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarRegistroConsumo(${Number(item.id_consumo)})">Eliminar</button>
            </td>
        </tr>
    `).join('');
}

function abrirEditarConsumo(id, cantidad, fuente) {
    document.getElementById('editConsumoId').value = id;
    document.getElementById('editConsumoValor').value = cantidad || '';
    document.getElementById('editConsumoFuente').value = fuente || 'DANE';

    new bootstrap.Modal(document.getElementById('modalEditarConsumo')).show();
}

function guardarConsumoEditado(event) {
    event.preventDefault();

    const formData = new FormData();
    formData.append('tipo', 'consumo');
    formData.append('id', document.getElementById('editConsumoId').value);
    formData.append('valor', document.getElementById('editConsumoValor').value);
    formData.append('fuente', document.getElementById('editConsumoFuente').value);

    fetch('guardar_registro.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.exito) throw new Error(data.mensaje || 'No se pudo guardar.');
            bootstrap.Modal.getInstance(document.getElementById('modalEditarConsumo')).hide();
            consultarConsumo();
        })
        .catch(error => alert(error.message));
}

function eliminarRegistroConsumo(id) {
    if (!confirm('¿Eliminar este registro?')) {
        return;
    }

    const formData = new FormData();
    formData.append('tipo', 'consumo');
    formData.append('id', id);

    fetch('eliminar_registro.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.exito) throw new Error(data.mensaje || 'No se pudo eliminar.');
            consultarConsumo();
        })
        .catch(error => alert(error.message));
}

function formatFecha(fecha) {
    if (!fecha) return '-';
    const partes = fecha.split('-');
    return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : fecha;
}

function formatNumber(num) {
    return new Intl.NumberFormat('es-CO', { maximumFractionDigits: 2 }).format(Number(num) || 0);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeJs(value) {
    return String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btnConsultarConsumo')?.addEventListener('click', consultarConsumo);
    document.getElementById('formEditarConsumo')?.addEventListener('submit', guardarConsumoEditado);
    document.getElementById('buscar')?.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            consultarConsumo();
        }
    });
});
