<?php
include 'config.php';

$tabla_actual = isset($_GET['tabla']) ? $_GET['tabla'] : 'departamentos';
$mensaje = '';
$tipo_mensaje = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $tabla = isset($_POST['tabla']) ? $_POST['tabla'] : '';
    
    if ($action === 'crear') {
        if ($tabla === 'departamentos') {
            $check = $conn->prepare("SELECT id_departamento FROM departamento WHERE id_departamento = ?");
            $check->bind_param("i", $_POST['id_departamento']);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                $mensaje = "Error: Ya existe un departamento con el ID " . $_POST['id_departamento'];
                $tipo_mensaje = "danger";
            } else {
                $stmt = $conn->prepare("INSERT INTO departamento (id_departamento, codigo_dane, nombre) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $_POST['id_departamento'], $_POST['codigo_dane'], $_POST['nombre']);
                if ($stmt->execute()) {
                    $mensaje = "Departamento creado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error: " . $conn->error;
                    $tipo_mensaje = "danger";
                }
            }
            $check->close();
        }
        elseif ($tabla === 'municipios') {
            $check = $conn->prepare("SELECT id_municipio FROM municipio WHERE id_municipio = ?");
            $check->bind_param("i", $_POST['id_municipio']);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                $mensaje = "Error: Ya existe un municipio con el ID " . $_POST['id_municipio'];
                $tipo_mensaje = "danger";
            } else {
                $stmt = $conn->prepare("INSERT INTO municipio (id_municipio, codigo_dane, nombre, id_departamento) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issi", $_POST['id_municipio'], $_POST['codigo_dane'], $_POST['nombre'], $_POST['id_departamento']);
                if ($stmt->execute()) {
                    $mensaje = "Municipio creado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error: " . $conn->error;
                    $tipo_mensaje = "danger";
                }
            }
            $check->close();
        }
        elseif ($tabla === 'categorias') {
            $check = $conn->prepare("SELECT id_categoria FROM categoria_producto WHERE id_categoria = ?");
            $check->bind_param("i", $_POST['id_categoria']);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                $mensaje = "Error: Ya existe una categoría con el ID " . $_POST['id_categoria'];
                $tipo_mensaje = "danger";
            } else {
                $stmt = $conn->prepare("INSERT INTO categoria_producto (id_categoria, nombre, descripcion) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $_POST['id_categoria'], $_POST['nombre'], $_POST['descripcion']);
                if ($stmt->execute()) {
                    $mensaje = "Categoría creada exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error: " . $conn->error;
                    $tipo_mensaje = "danger";
                }
            }
            $check->close();
        }
        elseif ($tabla === 'productos') {
            $check = $conn->prepare("SELECT id_producto FROM producto WHERE id_producto = ?");
            $check->bind_param("i", $_POST['id_producto']);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                $mensaje = "Error: Ya existe un producto con el ID " . $_POST['id_producto'];
                $tipo_mensaje = "danger";
            } else {
                $activo = isset($_POST['activo']) ? 1 : 0;
                $stmt = $conn->prepare("INSERT INTO producto (id_producto, codigo_dane, nombre, descripcion, id_categoria, unidad_medida, activo, dias_cosecha_estimados, estacion_recomendada) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssisisis", $_POST['id_producto'], $_POST['codigo_dane'], $_POST['nombre'], $_POST['descripcion'], $_POST['id_categoria'], $_POST['unidad_medida'], $activo, $_POST['dias_cosecha_estimados'], $_POST['estacion_recomendada']);
                if ($stmt->execute()) {
                    $mensaje = "Producto creado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error: " . $conn->error;
                    $tipo_mensaje = "danger";
                }
            }
            $check->close();
        }
        
        echo "<script>window.location.href='?tabla=$tabla&mensaje=" . urlencode($mensaje) . "&tipo=$tipo_mensaje';</script>";
        exit;
    }
    
    elseif ($action === 'editar') {
        $id = $_POST['id_registro'];
        
        if ($tabla === 'departamentos') {
            $stmt = $conn->prepare("UPDATE departamento SET codigo_dane = ?, nombre = ? WHERE id_departamento = ?");
            $stmt->bind_param("ssi", $_POST['codigo_dane'], $_POST['nombre'], $id);
            if ($stmt->execute()) $mensaje = "Departamento actualizado";
        }
        elseif ($tabla === 'municipios') {
            $stmt = $conn->prepare("UPDATE municipio SET codigo_dane = ?, nombre = ?, id_departamento = ? WHERE id_municipio = ?");
            $stmt->bind_param("ssii", $_POST['codigo_dane'], $_POST['nombre'], $_POST['id_departamento'], $id);
            if ($stmt->execute()) $mensaje = "Municipio actualizado";
        }
        elseif ($tabla === 'categorias') {
            $stmt = $conn->prepare("UPDATE categoria_producto SET nombre = ?, descripcion = ? WHERE id_categoria = ?");
            $stmt->bind_param("ssi", $_POST['nombre'], $_POST['descripcion'], $id);
            if ($stmt->execute()) $mensaje = "Categoría actualizada";
        }
        elseif ($tabla === 'productos') {
            $activo = isset($_POST['activo']) ? 1 : 0;
            $stmt = $conn->prepare("UPDATE producto SET codigo_dane = ?, nombre = ?, descripcion = ?, id_categoria = ?, unidad_medida = ?, activo = ?, dias_cosecha_estimados = ?, estacion_recomendada = ? WHERE id_producto = ?");
            $stmt->bind_param("sssissisi", $_POST['codigo_dane'], $_POST['nombre'], $_POST['descripcion'], $_POST['id_categoria'], $_POST['unidad_medida'], $activo, $_POST['dias_cosecha_estimados'], $_POST['estacion_recomendada'], $id);
            if ($stmt->execute()) $mensaje = "Producto actualizado";
        }
        echo "<script>window.location.href='?tabla=$tabla&mensaje=" . urlencode($mensaje) . "&tipo=success';</script>";
        exit;
    }
    
    elseif ($action === 'eliminar') {
        $id = intval($_POST['id_registro']);
        
        try {
            if ($tabla === 'departamentos') {
                $conn->query("DELETE FROM departamento WHERE id_departamento = $id");
                $mensaje = "Departamento eliminado";
            }
            elseif ($tabla === 'municipios') {
                $conn->query("DELETE FROM municipio WHERE id_municipio = $id");
                $mensaje = "Municipio eliminado";
            }
            elseif ($tabla === 'categorias') {
                $conn->query("DELETE FROM categoria_producto WHERE id_categoria = $id");
                $mensaje = "Categoría eliminada";
            }
            elseif ($tabla === 'productos') {
                $conn->query("DELETE FROM producto WHERE id_producto = $id");
                $mensaje = "Producto eliminado";
            }
        } catch (mysqli_sql_exception $e) {
            $mensaje = "No se puede eliminar la tabla";
            $tipo_mensaje = 'danger';
        }

        if (empty($tipo_mensaje)) {
            $tipo_mensaje = 'info';
        }
        echo "<script>window.location.href='?tabla=$tabla&mensaje=" . urlencode($mensaje) . "&tipo=$tipo_mensaje';</script>";
        exit;
    }
}

if (isset($_GET['mensaje'])) {
    $mensaje = urldecode($_GET['mensaje']);
    $tipo_mensaje = isset($_GET['tipo']) ? $_GET['tipo'] : 'success';
}

// Estadísticas
$stats = [
    'departamentos' => $conn->query("SELECT COUNT(*) as total FROM departamento")->fetch_assoc()['total'] ?? 0,
    'municipios' => $conn->query("SELECT COUNT(*) as total FROM municipio")->fetch_assoc()['total'] ?? 0,
    'categorias' => $conn->query("SELECT COUNT(*) as total FROM categoria_producto")->fetch_assoc()['total'] ?? 0,
    'productos' => $conn->query("SELECT COUNT(*) as total FROM producto")->fetch_assoc()['total'] ?? 0
];

$titulos = [
    'departamentos' => 'Departamentos de Colombia',
    'municipios' => 'Municipios de Colombia',
    'categorias' => 'Categorías de Productos',
    'productos' => 'Productos Agrícolas'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agricontrol | Sistema de Gestión Agrícola</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0 sidebar">
                <div class="logo">
                    <h3>AGRICONTROL</h3>
                    <p>Sistema de Gestión Agrícola</p>
                </div>
                <a href="?tabla=departamentos" class="<?php echo ($tabla_actual == 'departamentos') ? 'active' : ''; ?>">
                    Departamentos
                </a>
                <a href="?tabla=municipios" class="<?php echo ($tabla_actual == 'municipios') ? 'active' : ''; ?>">
                    Municipios
                </a>
                <a href="?tabla=categorias" class="<?php echo ($tabla_actual == 'categorias') ? 'active' : ''; ?>">
                    Categorías
                </a>
                <a href="?tabla=productos" class="<?php echo ($tabla_actual == 'productos') ? 'active' : ''; ?>">
                    Productos
                </a>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="page-header">
                    <div class="header-with-search">
                        <div>
                            <h2><?php echo $titulos[$tabla_actual]; ?></h2>
                            <p>Gestión completa de datos agrícolas</p>
                        </div>
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <!-- Barra de búsqueda -->
                            <div class="search-container">
                                <input type="text" id="searchInput" class="search-input" placeholder="Buscar por nombre, código..." autocomplete="off">
                                <button class="search-btn" onclick="buscarRegistros()">Buscar</button>
                                <button class="clear-search" id="clearSearch" onclick="limpiarBusqueda()">✕</button>
                            </div>
                            <button class="btn-nuevo" data-bs-toggle="modal" data-bs-target="#crudModal" onclick="abrirModalNuevo()">
                                Nuevo Registro
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show mb-3">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Tarjetas de estadísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Departamentos</h4>
                            <p class="stat-number"><?php echo $stats['departamentos']; ?></p>
                            <small>Unidades territoriales</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Municipios</h4>
                            <p class="stat-number"><?php echo $stats['municipios']; ?></p>
                            <small>Municipios registrados</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Categorías</h4>
                            <p class="stat-number"><?php echo $stats['categorias']; ?></p>
                            <small>Tipos de productos</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Productos</h4>
                            <p class="stat-number"><?php echo $stats['productos']; ?></p>
                            <small>Productos agrícolas</small>
                        </div>
                    </div>
                </div>

                <!-- Contenedor de Tarjetas (única vista) -->
                <div id="cardsView" class="cards-container">
                    <?php
                    // Generar tarjetas según la tabla
                    if ($tabla_actual === 'departamentos'):
                        $result = $conn->query("SELECT * FROM departamento ORDER BY id_departamento");
                        while($row = $result->fetch_assoc()):
                    ?>
                    <div class="data-card" data-search="<?php echo strtolower($row['nombre'] . ' ' . $row['codigo_dane'] . ' ' . $row['id_departamento']); ?>">
                        <div class="card-header">
                            <h3><?php echo htmlspecialchars($row['nombre']); ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="card-field">
                                <span class="field-label">ID Departamento</span>
                                <span class="field-value"><?php echo $row['id_departamento']; ?></span>
                            </div>
                            <div class="card-field">
                                <span class="field-label">Código DANE</span>
                                <span class="field-value"><?php echo $row['codigo_dane']; ?></span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn-card btn-card-edit" onclick='editarRegistro(<?php echo $row['id_departamento']; ?>, <?php echo json_encode($row); ?>)' data-bs-toggle="modal" data-bs-target="#crudModal">Editar</button>
                            <button class="btn-card btn-card-delete" onclick="eliminarRegistro(<?php echo $row['id_departamento']; ?>, '<?php echo addslashes($row['nombre']); ?>')">Eliminar</button>
                        </div>
                    </div>
                    <?php endwhile; endif; ?>

                    <?php if ($tabla_actual === 'municipios'):
                        $result = $conn->query("SELECT m.*, d.nombre as depto FROM municipio m LEFT JOIN departamento d ON m.id_departamento = d.id_departamento ORDER BY m.id_municipio");
                        while($row = $result->fetch_assoc()):
                    ?>
                    <div class="data-card" data-search="<?php echo strtolower($row['nombre'] . ' ' . $row['codigo_dane'] . ' ' . $row['depto'] . ' ' . $row['id_municipio']); ?>">
                        <div class="card-header">
                            <h3><?php echo htmlspecialchars($row['nombre']); ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="card-field">
                                <span class="field-label">ID Municipio</span>
                                <span class="field-value"><?php echo $row['id_municipio']; ?></span>
                            </div>
                            <div class="card-field">
                                <span class="field-label">Código DANE</span>
                                <span class="field-value"><?php echo $row['codigo_dane']; ?></span>
                            </div>
                            <div class="card-field">
                                <span class="field-label">Departamento</span>
                                <span class="field-value"><?php echo htmlspecialchars($row['depto']); ?></span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn-card btn-card-edit" onclick='editarRegistro(<?php echo $row['id_municipio']; ?>, <?php echo json_encode($row); ?>)' data-bs-toggle="modal" data-bs-target="#crudModal">Editar</button>
                            <button class="btn-card btn-card-delete" onclick="eliminarRegistro(<?php echo $row['id_municipio']; ?>, '<?php echo addslashes($row['nombre']); ?>')">Eliminar</button>
                        </div>
                    </div>
                    <?php endwhile; endif; ?>

                    <?php if ($tabla_actual === 'categorias'):
                        $result = $conn->query("SELECT * FROM categoria_producto ORDER BY id_categoria");
                        while($row = $result->fetch_assoc()):
                    ?>
                    <div class="data-card" data-search="<?php echo strtolower($row['nombre'] . ' ' . $row['descripcion'] . ' ' . $row['id_categoria']); ?>">
                        <div class="card-header">
                            <h3><?php echo htmlspecialchars($row['nombre']); ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="card-field">
                                <span class="field-label">ID Categoría</span>
                                <span class="field-value"><?php echo $row['id_categoria']; ?></span>
                            </div>
                            <div class="card-field">
                                <span class="field-label">Descripción</span>
                                <span class="field-value"><?php echo htmlspecialchars($row['descripcion']) ?: 'Sin descripción'; ?></span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn-card btn-card-edit" onclick='editarRegistro(<?php echo $row['id_categoria']; ?>, <?php echo json_encode($row); ?>)' data-bs-toggle="modal" data-bs-target="#crudModal">Editar</button>
                            <button class="btn-card btn-card-delete" onclick="eliminarRegistro(<?php echo $row['id_categoria']; ?>, '<?php echo addslashes($row['nombre']); ?>')">Eliminar</button>
                        </div>
                    </div>
                    <?php endwhile; endif; ?>

                    <?php if ($tabla_actual === 'productos'):
                        $result = $conn->query("SELECT p.*, c.nombre as categoria FROM producto p LEFT JOIN categoria_producto c ON p.id_categoria = c.id_categoria ORDER BY p.id_producto");
                        while($row = $result->fetch_assoc()):
                    ?>
                    <div class="data-card" data-search="<?php echo strtolower($row['nombre'] . ' ' . $row['codigo_dane'] . ' ' . $row['categoria'] . ' ' . $row['unidad_medida'] . ' ' . $row['dias_cosecha_estimados'] . ' ' . $row['estacion_recomendada']); ?>">
                        <div class="card-header">
                            <h3><?php echo htmlspecialchars($row['nombre']); ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="card-field">
                                <span class="field-label">ID Producto</span>
                                <span class="field-value"><?php echo $row['id_producto']; ?></span>
                            </div>
                            <div class="card-field">
                                <span class="field-label">Código DANE</span>
                                <span class="field-value"><?php echo $row['codigo_dane']; ?></span>
                            </div>
                            <div class="card-field">
                                <span class="field-label">Categoría</span>
                                <span class="field-value"><?php echo htmlspecialchars($row['categoria']); ?></span>
                            </div>
                            <div class="card-field">
                                <span class="field-label">Unidad de Medida</span>
                                <span class="field-value"><?php echo htmlspecialchars($row['unidad_medida']); ?></span>
                            </div>
                            <div class="card-field">
                                <span class="field-label">Días Cosecha Estimados</span>
                                <span class="field-value"><?php echo $row['dias_cosecha_estimados']; ?></span>
                            </div>
                            <div class="card-field">
                                <span class="field-label">Estación Recomendada</span>
                                <span class="field-value"><?php echo htmlspecialchars($row['estacion_recomendada']); ?></span>
                            </div>
                            <div class="card-field">
                                <span class="field-label">Estado</span>
                                <span class="field-value"><span class="<?php echo $row['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>"><?php echo $row['activo'] ? 'Activo' : 'Inactivo'; ?></span></span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn-card btn-card-edit" onclick='editarRegistro(<?php echo $row['id_producto']; ?>, <?php echo json_encode($row); ?>)' data-bs-toggle="modal" data-bs-target="#crudModal">Editar</button>
                            <button class="btn-card btn-card-delete" onclick="eliminarRegistro(<?php echo $row['id_producto']; ?>, '<?php echo addslashes($row['nombre']); ?>')">Eliminar</button>
                        </div>
                    </div>
                    <?php endwhile; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal CRUD -->
    <div class="modal fade" id="crudModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Registro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="crudForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="action" value="crear">
                        <input type="hidden" name="tabla" value="<?php echo $tabla_actual; ?>">
                        <input type="hidden" name="id_registro" id="id_registro" value="">
                        <div id="formFields"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const tablaActual = '<?php echo $tabla_actual; ?>';
        
        function buscarRegistros() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const clearBtn = document.getElementById('clearSearch');
            const cards = document.querySelectorAll('.data-card');
            let visibleCount = 0;
            
            if (searchTerm === '') {
                limpiarBusqueda();
                return;
            }
            
            clearBtn.style.display = 'block';
            
            cards.forEach(card => {
                const searchData = card.getAttribute('data-search') || '';
                if (searchData.includes(searchTerm)) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            const searchStats = document.getElementById('searchStats');
            if (searchStats) {
                searchStats.textContent = `Mostrando ${visibleCount} de ${cards.length} resultados`;
            }
            
            // Mostrar mensaje si no hay resultados
            const cardsContainer = document.getElementById('cardsView');
            let noResultsMsg = document.getElementById('noResultsMsg');
            
            if (visibleCount === 0) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'noResultsMsg';
                    noResultsMsg.className = 'no-results';
                    noResultsMsg.innerHTML = '<h4>No se encontraron resultados</h4><p>No hay registros que coincidan con "<strong>' + searchTerm + '</strong>"</p>';
                    cardsContainer.appendChild(noResultsMsg);
                } else {
                    noResultsMsg.style.display = 'block';
                    noResultsMsg.querySelector('p').innerHTML = 'No hay registros que coincidan con "<strong>' + searchTerm + '</strong>"';
                }
            } else if (noResultsMsg) {
                noResultsMsg.style.display = 'none';
            }
        }
        
        function limpiarBusqueda() {
            document.getElementById('searchInput').value = '';
            document.getElementById('clearSearch').style.display = 'none';
            
            const cards = document.querySelectorAll('.data-card');
            cards.forEach(card => {
                card.style.display = '';
            });
            
            const searchStats = document.getElementById('searchStats');
            if (searchStats) {
                searchStats.textContent = '';
            }
            
            const noResultsMsg = document.getElementById('noResultsMsg');
            if (noResultsMsg) {
                noResultsMsg.style.display = 'none';
            }
        }
        
        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                buscarRegistros();
            }
        });
        
        function abrirModalNuevo() {
            document.getElementById('action').value = 'crear';
            document.getElementById('modalTitle').innerHTML = 'Nuevo Registro';
            document.getElementById('id_registro').value = '';
            
            let html = '';
            
            if (tablaActual === 'departamentos') {
                html = `
                    <div class="mb-3"><label class="form-label">ID Departamento *</label><input type="number" class="form-control" name="id_departamento" required></div>
                    <div class="mb-3"><label class="form-label">Código DANE *</label><input type="text" class="form-control" name="codigo_dane" required maxlength="5"></div>
                    <div class="mb-3"><label class="form-label">Nombre *</label><input type="text" class="form-control" name="nombre" required></div>
                `;
            } else if (tablaActual === 'municipios') {
                html = `
                    <div class="mb-3"><label class="form-label">ID Municipio *</label><input type="number" class="form-control" name="id_municipio" required></div>
                    <div class="mb-3"><label class="form-label">Código DANE *</label><input type="text" class="form-control" name="codigo_dane" required maxlength="5"></div>
                    <div class="mb-3"><label class="form-label">Nombre *</label><input type="text" class="form-control" name="nombre" required></div>
                    <div class="mb-3"><label class="form-label">ID Departamento *</label><input type="number" class="form-control" name="id_departamento" required></div>
                `;
            } else if (tablaActual === 'categorias') {
                html = `
                    <div class="mb-3"><label class="form-label">ID Categoría *</label><input type="number" class="form-control" name="id_categoria" required></div>
                    <div class="mb-3"><label class="form-label">Nombre *</label><input type="text" class="form-control" name="nombre" required></div>
                    <div class="mb-3"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="3"></textarea></div>
                `;
            } else if (tablaActual === 'productos') {
                html = `
                    <div class="mb-3"><label class="form-label">ID Producto *</label><input type="number" class="form-control" name="id_producto" required></div>
                    <div class="mb-3"><label class="form-label">Código DANE *</label><input type="text" class="form-control" name="codigo_dane" required maxlength="10"></div>
                    <div class="mb-3"><label class="form-label">Nombre *</label><input type="text" class="form-control" name="nombre" required></div>
                    <div class="mb-3"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label">ID Categoría *</label><input type="number" class="form-control" name="id_categoria" required></div>
                    <div class="mb-3"><label class="form-label">Unidad de Medida *</label><input type="text" class="form-control" name="unidad_medida" required></div>
                    <div class="mb-3"><label class="form-label">Días Cosecha Estimados</label><input type="number" class="form-control" name="dias_cosecha_estimados"></div>
                    <div class="mb-3"><label class="form-label">Estación Recomendada</label><input type="text" class="form-control" name="estacion_recomendada"></div>
                    <div class="mb-3 form-check"><input type="checkbox" class="form-check-input" name="activo" value="1" checked><label class="form-check-label">Activo</label></div>
                `;
            }
            
            document.getElementById('formFields').innerHTML = html;
        }
        
        function editarRegistro(id, datos) {
            document.getElementById('action').value = 'editar';
            document.getElementById('modalTitle').innerHTML = 'Editar Registro';
            document.getElementById('id_registro').value = id;
            
            let html = '';
            
            if (tablaActual === 'departamentos') {
                html = `
                    <div class="mb-3"><label class="form-label">ID Departamento</label><input type="number" class="form-control" value="${datos.id_departamento}" readonly disabled></div>
                    <div class="mb-3"><label class="form-label">Código DANE *</label><input type="text" class="form-control" name="codigo_dane" value="${datos.codigo_dane}" required></div>
                    <div class="mb-3"><label class="form-label">Nombre *</label><input type="text" class="form-control" name="nombre" value="${datos.nombre}" required></div>
                `;
            } else if (tablaActual === 'municipios') {
                html = `
                    <div class="mb-3"><label class="form-label">ID Municipio</label><input type="number" class="form-control" value="${datos.id_municipio}" readonly disabled></div>
                    <div class="mb-3"><label class="form-label">Código DANE *</label><input type="text" class="form-control" name="codigo_dane" value="${datos.codigo_dane}" required></div>
                    <div class="mb-3"><label class="form-label">Nombre *</label><input type="text" class="form-control" name="nombre" value="${datos.nombre}" required></div>
                    <div class="mb-3"><label class="form-label">ID Departamento *</label><input type="number" class="form-control" name="id_departamento" value="${datos.id_departamento}" required></div>
                `;
            } else if (tablaActual === 'categorias') {
                html = `
                    <div class="mb-3"><label class="form-label">ID Categoría</label><input type="number" class="form-control" value="${datos.id_categoria}" readonly disabled></div>
                    <div class="mb-3"><label class="form-label">Nombre *</label><input type="text" class="form-control" name="nombre" value="${datos.nombre}" required></div>
                    <div class="mb-3"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="3">${datos.descripcion || ''}</textarea></div>
                `;
            } else if (tablaActual === 'productos') {
                html = `
                    <div class="mb-3"><label class="form-label">ID Producto</label><input type="number" class="form-control" value="${datos.id_producto}" readonly disabled></div>
                    <div class="mb-3"><label class="form-label">Código DANE *</label><input type="text" class="form-control" name="codigo_dane" value="${datos.codigo_dane}" required></div>
                    <div class="mb-3"><label class="form-label">Nombre *</label><input type="text" class="form-control" name="nombre" value="${datos.nombre}" required></div>
                    <div class="mb-3"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="2">${datos.descripcion || ''}</textarea></div>
                    <div class="mb-3"><label class="form-label">ID Categoría *</label><input type="number" class="form-control" name="id_categoria" value="${datos.id_categoria}" required></div>
                    <div class="mb-3"><label class="form-label">Unidad de Medida *</label><input type="text" class="form-control" name="unidad_medida" value="${datos.unidad_medida}" required></div>
                    <div class="mb-3"><label class="form-label">Días Cosecha Estimados</label><input type="number" class="form-control" name="dias_cosecha_estimados" value="${datos.dias_cosecha_estimados || ''}"></div>
                    <div class="mb-3"><label class="form-label">Estación Recomendada</label><input type="text" class="form-control" name="estacion_recomendada" value="${datos.estacion_recomendada || ''}"></div>
                    <div class="mb-3 form-check"><input type="checkbox" class="form-check-input" name="activo" value="1" ${datos.activo == 1 ? 'checked' : ''}><label class="form-check-label">Activo</label></div>
                `;
            }
            
            document.getElementById('formFields').innerHTML = html;
        }
        
        function eliminarRegistro(id, nombre) {
            if (confirm(`¿Está seguro de eliminar "${nombre}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="tabla" value="${tablaActual}">
                    <input type="hidden" name="id_registro" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>