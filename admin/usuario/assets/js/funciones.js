// Cargar municipios según departamento (para nueva siembra)
function cargarMunicipios() {
    var deptoId = document.getElementById('departamento').value;
    var municipioSelect = document.getElementById('municipio');
    
    if (deptoId) {
        fetch('get_municipios.php?id_departamento=' + deptoId)
            .then(response => response.json())
            .then(data => {
                municipioSelect.innerHTML = '<option value="">Seleccione municipio</option>';
                data.forEach(function(municipio) {
                    municipioSelect.innerHTML += '<option value="' + municipio.id_municipio + '">' + municipio.nombre + '</option>';
                });
                municipioSelect.disabled = false;
            });
    } else {
        municipioSelect.innerHTML = '<option value="">Primero seleccione departamento</option>';
        municipioSelect.disabled = true;
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
            document.getElementById('edit_tipo_medida_cosecha').value = data.tipo_medida_cosecha || '';
            document.getElementById('edit_fecha_cosecha_real').value = data.fecha_cosecha_real || '';
            var modal = new bootstrap.Modal(document.getElementById('modalEditar'));
            modal.show();
        });
}

function registrarProduccion(id) {
    document.getElementById('prod_id_produccion').value = id;
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
});