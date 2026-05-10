<?php
require_once 'auth.php';
require_once '../../config/database.php';

$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['usuario_nombre'];

$stmt = $pdo->prepare("
    SELECT 
        p.id_produccion,
        pr.nombre as producto_nombre,
        pr.dias_cosecha_estimados,
        m.nombre as municipio_nombre,
        d.nombre as departamento_nombre,
        p.cantidad_sembrada,
        p.tipo_medida_siembra,
        p.fecha_siembra,
        p.fecha_cosecha_estimada,
        p.cantidad_producida,
        p.tipo_medida_cosecha,
        p.rendimiento,
        p.fecha_cosecha_real,
        CASE 
            WHEN p.fecha_cosecha_real IS NOT NULL THEN 'cosechado'
            WHEN p.fecha_cosecha_estimada < CURDATE() THEN 'vencido'
            ELSE 'activo'
        END as estado
    FROM produccion p
    JOIN producto pr ON p.id_producto = pr.id_producto
    JOIN municipio m ON p.id_municipio = m.id_municipio
    JOIN departamento d ON m.id_departamento = d.id_departamento
    WHERE p.id_usuario = ?
    ORDER BY p.fecha_siembra DESC
");
$stmt->execute([$usuario_id]);
$siembras = $stmt->fetchAll();

$productos = $pdo->query("SELECT id_producto, nombre FROM producto WHERE activo = 1 ORDER BY nombre")->fetchAll();
$departamentos = $pdo->query("SELECT id_departamento, nombre FROM departamento ORDER BY nombre")->fetchAll();

$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agricontrol - Mis Siembras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>

<nav class="navbar navbar-expand-lg mb-4">
    <div class="container">
        <a class="navbar-brand" href="#">
            <img src="/agricontrol/img/logo-agricontrol.png" alt="Agricontrol Logo" style="height: 50px; width: auto;">
        </a>
        <div class="ms-auto">
            <span class="text-white me-3">Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?></span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
        </div>
    </div>
</nav>

<div class="container mb-5">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="titulo-dashboard">Mis Siembras</h2>
                <button class="btn-agregar" data-bs-toggle="modal" data-bs-target="#modalSiembra">
                    + Nueva Siembra
                </button>
            </div>
        </div>
    </div>
    
    <div class="row">
        <?php if (empty($siembras)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    No tienes siembras registradas. ¡Comienza agregando una nueva siembra!
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($siembras as $siembra): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card-sembrio">
                        <div class="card-header-sembrio">
                            <h5><?php echo htmlspecialchars($siembra['producto_nombre']); ?></h5>
                            <div class="mt-2">
                                <?php if ($siembra['estado'] == 'activo'): ?>
                                    <span class="estado-activo">En crecimiento</span>
                                <?php elseif ($siembra['estado'] == 'cosechado'): ?>
                                    <span class="estado-cosechado">Cosechado</span>
                                <?php else: ?>
                                    <span class="estado-vencido">Pendiente de cosecha</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body-sembrio">
                            <div class="info-item">
                                <div class="info-label">Producto</div>
                                <div class="info-value"><?php echo htmlspecialchars($siembra['producto_nombre']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Ubicación</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($siembra['municipio_nombre'] . ', ' . $siembra['departamento_nombre']); ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Cantidad sembrada</div>
                                <div class="info-value">
                                    <?php echo (int)$siembra['cantidad_sembrada']; ?> 
                                    <?php echo strtoupper($siembra['tipo_medida_siembra']); ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Tipo medida siembra</div>
                                <div class="info-value"><?php echo htmlspecialchars($siembra['tipo_medida_siembra']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Fecha de siembra</div>
                                <div class="info-value"><?php echo date('d/m/Y', strtotime($siembra['fecha_siembra'])); ?></div>
                            </div>
                            <?php if ($siembra['fecha_cosecha_estimada']): ?>
                            <div class="info-item">
                                <div class="info-label">Fecha estimada cosecha</div>
                                <div class="info-value"><?php echo date('d/m/Y', strtotime($siembra['fecha_cosecha_estimada'])); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($siembra['cantidad_producida']): ?>
                            <div class="info-item">
                                <div class="info-label">Cantidad producida</div>
                                <div class="info-value">
                                    <?php echo number_format($siembra['cantidad_producida'], 2); ?> 
                                    <?php echo strtoupper($siembra['tipo_medida_cosecha']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($siembra['tipo_medida_cosecha']): ?>
                            <div class="info-item">
                                <div class="info-label">Tipo medida cosecha</div>
                                <div class="info-value"><?php echo htmlspecialchars($siembra['tipo_medida_cosecha']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($siembra['rendimiento']): ?>
                            <div class="info-item">
                                <div class="info-label">Rendimiento</div>
                                <div class="info-value"><?php echo number_format($siembra['rendimiento'], 2); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($siembra['fecha_cosecha_real']): ?>
                            <div class="info-item">
                                <div class="info-label">Fecha cosecha real</div>
                                <div class="info-value"><?php echo date('d/m/Y', strtotime($siembra['fecha_cosecha_real'])); ?></div>
                            </div>
                            <?php endif; ?>
                            <div class="info-item">
                                <div class="info-label">Días cosecha estimados</div>
                                <div class="info-value"><?php echo $siembra['dias_cosecha_estimados'] ? $siembra['dias_cosecha_estimados'] . ' días' : 'No definido'; ?></div>
                            </div>
                            
                            <div class="mt-3 d-flex gap-2">
                                <button class="btn-editar flex-grow-1" onclick="editarSiembra(<?php echo $siembra['id_produccion']; ?>)">
                                    Editar
                                </button>
                                <?php if (!$siembra['cantidad_producida'] && $siembra['estado'] != 'cosechado'): ?>
                                <button class="btn-produccion flex-grow-1" onclick="registrarProduccion(<?php echo $siembra['id_produccion']; ?>)">
                                    Registrar Cosecha
                                </button>
                                <?php endif; ?>
                                <button class="btn-eliminar" onclick="eliminarSiembra(<?php echo $siembra['id_produccion']; ?>)">
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nueva Siembra -->
<div class="modal fade" id="modalSiembra" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva Siembra</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="procesar_siembra.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Departamento</label>
                            <select class="form-select" id="departamento" name="id_departamento" required>
                                <option value="">Seleccione departamento</option>
                                <?php foreach ($departamentos as $depto): ?>
                                    <option value="<?php echo $depto['id_departamento']; ?>">
                                        <?php echo htmlspecialchars($depto['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Municipio</label>
                            <select class="form-select" id="municipio" name="id_municipio" required disabled>
                                <option value="">Primero seleccione departamento</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Producto</label>
                            <select class="form-select" name="id_producto" required>
                                <option value="">Seleccione producto</option>
                                <?php foreach ($productos as $producto): ?>
                                    <option value="<?php echo $producto['id_producto']; ?>">
                                        <?php echo htmlspecialchars($producto['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Cantidad sembrada</label>
                            <input type="number" step="0.01" class="form-control" name="cantidad_sembrada" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unidad de medida (siembra)</label>
                            <select class="form-select" name="tipo_medida_siembra" required>
                                <option value="kilos">Kilogramos</option>
                                <option value="matas">Matas (plantas)</option>
                                <option value="unidades">Unidades</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Fecha de siembra</label>
                            <input type="date" class="form-control" name="fecha_siembra" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha estimada de cosecha</label>
                            <input type="date" class="form-control" name="fecha_cosecha_estimada" required>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Información:</strong> La cantidad producida se registrará después de la cosecha.
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-guardar">Guardar Siembra</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Siembra -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Producción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="actualizar_produccion.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="id_produccion" id="edit_id_produccion">
                    
                    <div class="mb-3">
                        <label class="form-label">Cantidad producida</label>
                        <input type="number" step="0.01" class="form-control" name="cantidad_producida" id="edit_cantidad_producida" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Unidad de medida (cosecha)</label>
                        <select class="form-select" name="tipo_medida_cosecha" id="edit_tipo_medida_cosecha" required>
                            <option value="kilos">Kilogramos</option>
                            <option value="cajas">Cajas</option>
                            <option value="unidades">Unidades</option>
                            <option value="toneladas">Toneladas</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Fecha de cosecha real</label>
                        <input type="date" class="form-control" name="fecha_cosecha_real" id="edit_fecha_cosecha_real" required>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-guardar">Actualizar Producción</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Registrar Producción -->
<div class="modal fade" id="modalProduccion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registrar Producción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="actualizar_produccion.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="id_produccion" id="prod_id_produccion">
                    
                    <div class="mb-3">
                        <label class="form-label">Cantidad producida</label>
                        <input type="number" step="0.01" class="form-control" name="cantidad_producida" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Unidad de medida (cosecha)</label>
                        <select class="form-select" name="tipo_medida_cosecha" required>
                            <option value="kilos">Kilogramos</option>
                            <option value="cajas">Cajas</option>
                            <option value="unidades">Unidades</option>
                            <option value="toneladas">Toneladas</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Fecha de cosecha real</label>
                        <input type="date" class="form-control" name="fecha_cosecha_real" required>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-guardar">Guardar Producción</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/funciones.js"></script>
</body>
</html>