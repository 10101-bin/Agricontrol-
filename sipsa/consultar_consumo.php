<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$productos = $pdo->query("SELECT id_producto, nombre FROM producto WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$anios = $pdo->query("SELECT DISTINCT anio FROM tiempo ORDER BY anio DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Consumo Nacional</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>

<nav class="navbar navbar-expand-lg mb-4">
    <div class="container">
        <span class="navbar-brand">Consulta de Consumo Nacional</span>
        <div class="ms-auto">
            <span class="text-white me-3"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></span>
            <a href="index.php" class="btn btn-outline-light btn-sm">Cargar datos</a>
            <a href="consultar.php" class="btn btn-outline-light btn-sm">Ver precios</a>
            <a href="../logout.php" class="btn btn-outline-light btn-sm ms-2">Salir</a>
        </div>
    </div>
</nav>

<div class="container mb-5">
    <div class="card-sembrio p-4">
        <h2 class="titulo-dashboard mb-4">Consultar Consumo Nacional</h2>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Buscar</label>
                <input type="search" id="buscar" class="form-control" placeholder="Producto" list="sugerenciasConsumo" autocomplete="off">
                <datalist id="sugerenciasConsumo">
                    <?php foreach ($productos as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['nombre']); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Producto</label>
                <select id="producto" class="form-select">
                    <option value="">Todos los productos</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?php echo $p['id_producto']; ?>"><?php echo htmlspecialchars($p['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Año</label>
                <select id="anio" class="form-select">
                    <option value="">Todos los años</option>
                    <?php foreach ($anios as $a): ?>
                        <option value="<?php echo $a['anio']; ?>"><?php echo $a['anio']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-5 mb-3">
                <label class="form-label">Fecha desde</label>
                <input type="date" id="fecha_desde" class="form-control">
            </div>
            <div class="col-md-5 mb-3">
                <label class="form-label">Fecha hasta</label>
                <input type="date" id="fecha_hasta" class="form-control">
            </div>
            <div class="col-md-2 mb-3 d-flex align-items-end">
                <button class="btn-guardar w-100" id="btnConsultarConsumo" type="button">Consultar</button>
            </div>
        </div>
    </div>

    <div class="card-sembrio p-4 mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Detalle de Consumo</h5>
            <span id="totalResultados" class="text-muted">Sin consulta</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-success">
                    <tr>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Unidad</th>
                        <th class="text-end">Cantidad</th>
                        <th>Fuente</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaResultados">
                    <tr>
                        <td colspan="6" class="text-center text-muted">Seleccione filtros y consulte</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarConsumo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar consumo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarConsumo">
                <div class="modal-body">
                    <input type="hidden" id="editConsumoId">
                    <div class="mb-3">
                        <label class="form-label">Cantidad</label>
                        <input type="number" min="0.01" step="0.01" id="editConsumoValor" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fuente</label>
                        <input type="text" id="editConsumoFuente" class="form-control" value="DANE">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-guardar">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/consumo.js?v=<?php echo filemtime(__DIR__ . '/assets/js/consumo.js'); ?>"></script>
</body>
</html>
