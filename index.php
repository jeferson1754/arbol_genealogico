<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrador de Árbol Genealógico</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- D3.js para visualización del árbol -->
    <script src="https://d3js.org/d3.v7.min.js"></script>

    <style id="app-style">
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }

        .nav-tabs .nav-link.active {
            color: #0d6efd;
            font-weight: 600;
        }

        .add-person-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #0d6efd;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transition: all 0.3s;
        }

        .add-person-btn:hover {
            transform: scale(1.1);
            background-color: #0b5ed7;
        }

        .person-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .table th {
            font-weight: 600;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            margin-right: 5px;
        }

        .tree-container {
            width: 100%;
            height: calc(100vh - 150px);
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            background-color: white;
        }

        .tree-controls {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 100;
            background-color: white;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .node circle {
            fill: #fff;
            stroke: steelblue;
            stroke-width: 3px;
        }

        .node text {
            font: 12px sans-serif;
        }

        .link {
            fill: none;
            stroke: #ccc;
            stroke-width: 2px;
        }

        .highlighted-link {
            stroke: #ff7700;
            stroke-width: 3px;
        }

        .highlighted-node circle {
            fill: #ff7700;
            stroke: #ff5500;
        }

        .export-card {
            transition: transform 0.3s;
        }

        .export-card:hover {
            transform: translateY(-5px);
        }

        .modal-photo-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .selection-list {
            height: 200px;
            overflow-y: auto;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 10px;
        }

        .selection-item {
            padding: 5px;
            margin-bottom: 5px;
            border-radius: 4px;
            cursor: pointer;
        }

        .selection-item:hover {
            background-color: #f8f9fa;
        }

        .selected-item {
            background-color: #e9ecef;
        }

        /* Spinner para carga */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        /* Mensajes de notificación */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
        }
    </style>
</head>

<body>
    <!-- Navbar principal -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="javascript:void(0)">
                <i class="fas fa-tree me-2"></i>
                Árbol Genealógico
            </a>
        </div>
    </nav>

    <!-- Pestañas de navegación -->
    <div class="container mt-4">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="people-tab" data-bs-toggle="tab" data-bs-target="#people" type="button" role="tab" aria-controls="people" aria-selected="true">
                    <i class="fas fa-users me-2"></i>Personas
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tree-tab" data-bs-toggle="tab" data-bs-target="#tree" type="button" role="tab" aria-controls="tree" aria-selected="false">
                    <i class="fas fa-sitemap me-2"></i>Árbol
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="export-tab" data-bs-toggle="tab" data-bs-target="#export" type="button" role="tab" aria-controls="export" aria-selected="false">
                    <i class="fas fa-file-export me-2"></i>Exportar
                </button>
            </li>
        </ul>

        <!-- Contenido de las pestañas -->
        <div class="tab-content mt-3" id="myTabContent">
            <!-- Pestaña de Personas -->
            <div class="tab-pane fade show active" id="people" role="tabpanel" aria-labelledby="people-tab">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Foto</th>
                                        <th>Nombre</th>
                                        <th>Nacimiento</th>
                                        <th>Defunción</th>
                                        <th>Edad</th>
                                        <th>Género</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="personsTableBody">
                                    <!-- Los datos se cargarán dinámicamente -->
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Cargando...</span>
                                            </div>
                                            <p class="mt-2">Cargando datos...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <nav aria-label="Navegación de páginas">
                            <ul class="pagination justify-content-center" id="pagination">
                                <li class="page-item disabled">
                                    <a class="page-link" href="javascript:void(0)" tabindex="-1" aria-disabled="true">Anterior</a>
                                </li>
                                <li class="page-item active"><a class="page-link" href="javascript:void(0)">1</a></li>
                                <li class="page-item"><a class="page-link" href="javascript:void(0)">2</a></li>
                                <li class="page-item"><a class="page-link" href="javascript:void(0)">3</a></li>
                                <li class="page-item">
                                    <a class="page-link" href="javascript:void(0)">Siguiente</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>

                <!-- Botón flotante para agregar persona -->
                <a href="javascript:void(0)" class="add-person-btn" data-bs-toggle="modal" data-bs-target="#personModal">
                    <i class="fas fa-plus fa-lg"></i>
                </a>
            </div>

            <!-- Pestaña del Árbol -->
            <div class="tab-pane fade" id="tree" role="tabpanel" aria-labelledby="tree-tab">
                <div class="card">
                    <div class="card-body position-relative">
                        <div class="tree-controls">
                            <button class="btn btn-sm btn-outline-primary me-2" id="zoomIn">
                                <i class="fas fa-search-plus"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary me-2" id="zoomOut">
                                <i class="fas fa-search-minus"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary" id="resetZoom">
                                <i class="fas fa-compress-arrows-alt"></i>
                            </button>
                        </div>
                        <div id="treeContainer" class="tree-container">
                            <!-- El árbol se generará con D3.js -->
                            <div class="d-flex justify-content-center align-items-center h-100">
                                <div class="text-center">
                                    <div class="spinner-border text-primary mb-3" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <p>Cargando el árbol genealógico...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña de Exportación -->
            <div class="tab-pane fade" id="export" role="tabpanel" aria-labelledby="export-tab">
                <div class="row justify-content-center">
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 export-card">
                            <div class="card-body text-center">
                                <div class="display-1 text-primary mb-3">
                                    <i class="far fa-file-pdf"></i>
                                </div>
                                <h5 class="card-title">Exportar a PDF</h5>
                                <p class="card-text">Genera un documento PDF con la información completa del árbol genealógico.</p>
                                <button id="exportPdfBtn" class="btn btn-primary">
                                    <i class="fas fa-download me-2"></i> Descargar PDF
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 export-card">
                            <div class="card-body text-center">
                                <div class="display-1 text-primary mb-3">
                                    <i class="far fa-file-image"></i>
                                </div>
                                <h5 class="card-title">Exportar a Imagen</h5>
                                <p class="card-text">Genera una imagen PNG de alta resolución con la visualización del árbol.</p>
                                <button id="exportImageBtn" class="btn btn-primary">
                                    <i class="fas fa-download me-2"></i> Descargar Imagen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Agregar/Editar Persona -->
    <div class="modal fade" id="personModal" tabindex="-1" aria-labelledby="personModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="personModalLabel">Agregar Persona</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="personForm">
                        <input type="hidden" id="personId">

                        <div class="mb-3">
                            <label for="fullName" class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control" id="fullName" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="birthDate" class="form-label">Fecha de Nacimiento</label>
                                <input type="date" class="form-control" id="birthDate" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="deathDate" class="form-label">Fecha de Defunción</label>
                                <input type="date" class="form-control" id="deathDate">
                                <div class="form-text">Dejar en blanco si está vivo</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="gender" class="form-label">Género</label>
                                <select class="form-select" id="gender" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="Masculino">Masculino</option>
                                    <option value="Femenino">Femenino</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="ageAtDeath" class="form-label">Edad al Morir</label>
                                <input type="text" class="form-control" id="ageAtDeath" readonly>
                                <div class="form-text">Se calcula automáticamente</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="photoInput" class="form-label">Fotografía</label>
                            <div class="input-group">
                                <input type="file" class="form-control" id="photoInput" accept="image/*">
                                <button class="btn btn-outline-secondary" type="button" id="useUrlBtn">Usar URL</button>
                            </div>
                            <div id="photoUrlContainer" class="mt-2 d-none">
                                <input type="text" class="form-control" id="photoUrl" placeholder="URL de la imagen">
                            </div>
                            <div id="photoPreviewContainer" class="mt-2 text-center d-none">
                                <img id="photoPreview" class="modal-photo-preview">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notas</label>
                            <textarea class="form-control" id="notes" rows="3"></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Ascendientes</label>
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" id="searchAscendants" placeholder="Buscar por nombre...">
                                    <button class="btn btn-outline-secondary" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <div class="selection-list" id="ascendantsList">
                                    <!-- Aquí se cargarán los ascendientes disponibles -->
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Descendientes</label>
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" id="searchDescendants" placeholder="Buscar por nombre...">
                                    <button class="btn btn-outline-secondary" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <div class="selection-list" id="descendantsList">
                                    <!-- Aquí se cargarán los descendientes disponibles -->
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="savePersonBtn">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro de que desea eliminar a <span id="deletePersonName" class="fw-bold"></span>?</p>
                    <p class="text-danger">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Eliminar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenedor de mensajes toast -->
    <div class="toast-container"></div>

    <!-- Overlay de carga -->
    <div class="spinner-overlay d-none" id="loadingSpinner">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>

    <!-- Bootstrap Bundle con Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script id="app-script">
        // Datos simulados para el prototipo
        let mockPersons = [];

        fetch('api/get_persons.php')
            .then(response => response.json())
            .then(data => {
                mockPersons = data;
            })
            .catch(error => {
                console.error('Error al obtener datos:', error);
            });


        // Variables globales
        let currentPersonId = null;
        let currentPage = 1;
        const itemsPerPage = 5;

        // Función para mostrar notificaciones toast
        function showToast(message, type = 'success') {
            const toastId = 'toast-' + Date.now();
            const toastHTML = `
    <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true" id="${toastId}">
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
    `;

            document.querySelector('.toast-container').insertAdjacentHTML('beforeend', toastHTML);
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, {
                autohide: true,
                delay: 3000
            });
            toast.show();

            // Eliminar el toast del DOM después de ocultarse
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        }

        // Función para mostrar/ocultar el spinner de carga
        function toggleLoadingSpinner(show) {
            const spinner = document.getElementById('loadingSpinner');
            if (show) {
                spinner.classList.remove('d-none');
            } else {
                spinner.classList.add('d-none');
            }
        }

        // Función para cargar los datos de la tabla de personas
        function loadPersonsTable() {
            toggleLoadingSpinner(true);

            fetch(`api/persons.php?page=${currentPage}&itemsPerPage=${itemsPerPage}`)
                .then(response => response.json())
                .then(data => {
                    const tableBody = document.getElementById('personsTableBody');
                    tableBody.innerHTML = '';

                    if (data.persons.length === 0) {
                        tableBody.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-info-circle fa-2x text-info mb-3"></i>
                                <p>No hay personas registradas en el árbol genealógico.</p>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#personModal">
                                    <i class="fas fa-plus me-2"></i>Agregar Persona
                                </button>
                            </td>
                        </tr>
                        `;
                    } else {
                        data.persons.forEach(person => {
                            const row = document.createElement('tr');

                            // Formatear fechas
                            const birthDate = new Date(person.fecha_nacimiento).toLocaleDateString('es-ES');
                            const deathDate = person.fecha_muerte ? new Date(person.fecha_muerte).toLocaleDateString('es-ES') : '-';
                            const ageText = person.edad_muerte ? `${person.edad_muerte} años` : 'Vivo';

                            row.innerHTML = `
              <td>
                <img src="${person.foto}" alt="${person.nombre}" class="person-photo">
              </td>
              <td>${person.nombre}</td>
              <td>${birthDate}</td>
              <td>${deathDate}</td>
              <td>${ageText}</td>
              <td>${person.genero}</td>
              <td>
                <button class="btn btn-sm btn-outline-primary action-btn edit-btn" data-id="${person.id}" title="Editar">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger action-btn delete-btn" data-id="${person.id}" data-name="${person.nombre}" title="Eliminar">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            `;

                            tableBody.appendChild(row);
                        });

                        // Configurar botones de acción
                        document.querySelectorAll('.edit-btn').forEach(btn => {
                            btn.addEventListener('click', function() {
                                const personId = parseInt(this.getAttribute('data-id'));
                                openPersonModal(personId);
                            });
                        });

                        document.querySelectorAll('.delete-btn').forEach(btn => {
                            btn.addEventListener('click', function() {
                                const personId = parseInt(this.getAttribute('data-id'));
                                const personName = this.getAttribute('data-name');
                                openDeleteModal(personId, personName);
                            });
                        });


                        // Actualizar paginación con data.totalPages
                        updatePagination(data.totalPages);
                    }
                })
                .catch(error => {
                    showToast('Error al cargar datos: ' + error.message, 'danger');
                })
                .finally(() => {
                    toggleLoadingSpinner(false);
                });
        }

        // Función para actualizar la paginación
        function updatePagination() {
            const pagination = document.getElementById('pagination');
            const totalPages = Math.ceil(mockPersons.length / itemsPerPage);

            let paginationHTML = '';

            // Botón anterior
            paginationHTML += `
    <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" data-page="${currentPage - 1}">Anterior</a>
    </li>
    `;

            // Páginas numeradas
            for (let i = 1; i <= totalPages; i++) {
                paginationHTML += `
        <li class="page-item ${currentPage === i ? 'active' : ''}">
        <a class="page-link" href="javascript:void(0)" data-page="${i}">${i}</a>
        </li>
        `;
            }

            // Botón siguiente
            paginationHTML += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" data-page="${currentPage + 1}">Siguiente</a>
        </li>
        `;

            pagination.innerHTML = paginationHTML;

            // Añadir eventos a los enlaces de paginación
            document.querySelectorAll('.page-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = parseInt(this.getAttribute('data-page'));
                    if (page !== currentPage && page > 0 && page <= totalPages) {
                        currentPage = page;
                        loadPersonsTable();
                    }
                });
            });
        }

        // Función para abrir el modal de persona (añadir/editar)
        function openPersonModal(personId = null) {
            const modal = document.getElementById('personModal');
            const modalTitle = document.getElementById('personModalLabel');
            const form = document.getElementById('personForm');

            // Limpiar el formulario
            form.reset();
            document.getElementById('photoPreviewContainer').classList.add('d-none');
            document.getElementById('photoUrlContainer').classList.add('d-none');

            // Establecer modo (agregar o editar)
            if (personId) {
                modalTitle.textContent = 'Editar Persona';
                currentPersonId = personId;

                // Buscar los datos reales de la persona desde la API
                fetch(`api/persons.php?id=${personId}`)
                    .then(response => response.json())
                    .then(person => {
                        document.getElementById('personId').value = person.id;
                        document.getElementById('fullName').value = person.nombre;
                        document.getElementById('birthDate').value = person.fecha_nacimiento;
                        document.getElementById('deathDate').value = person.fecha_muerte || '';
                        document.getElementById('gender').value = person.genero;
                        document.getElementById('ageAtDeath').value = person.edad_muerte || ''; // opcional
                        document.getElementById('notes').value = person.notas || '';

                        // Mostrar la foto
                        if (person.foto) {
                            document.getElementById('photoUrl').value = person.foto;
                            document.getElementById('photoPreview').src = person.foto || 'https://cdn.pixabay.com/photo/2015/10/05/22/37/blank-profile-picture-973460_1280.png';
                            document.getElementById('photoPreviewContainer').classList.remove('d-none');
                        }

                        // Cargar ascendientes y descendientes
                        loadRelations(person.ascendants || [], person.descendants || []);

                        // Mostrar el modal después de cargar los datos
                        const modalInstance = new bootstrap.Modal(modal);
                        modalInstance.show();
                    })
                    .catch(error => {
                        showToast('Error al cargar datos de la persona: ' + error.message, 'danger');
                    });
            } else {
                // Modo agregar
                modalTitle.textContent = 'Agregar Persona';
                currentPersonId = null;
                document.getElementById('personId').value = '';

                loadRelations([], []);
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
            }


            // Mostrar el modal
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
        }

        // Función para cargar las relaciones (ascendientes y descendientes)
        function loadRelations(ascendants = [], descendants = []) {
            const ascendantsList = document.getElementById('ascendantsList');
            const descendantsList = document.getElementById('descendantsList');

            // Limpiar las listas
            ascendantsList.innerHTML = '';
            descendantsList.innerHTML = '';

            // Agregar personas disponibles como ascendientes
            mockPersons.forEach(person => {
                const isSelected = ascendants.includes(person.id);
                const ascendantItem = document.createElement('div');
                ascendantItem.className = `selection-item${isSelected ? ' selected-item' : ''}`;
                ascendantItem.dataset.id = person.id;
                ascendantItem.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" ${isSelected ? 'checked' : '' }>
                    <label class="form-check-label">${person.name}</label>
                </div>
            </div>
            `;
                ascendantsList.appendChild(ascendantItem);

                // Evento para seleccionar/deseleccionar
                ascendantItem.addEventListener('click', function() {
                    this.classList.toggle('selected-item');
                    const checkbox = this.querySelector('input[type="checkbox"]');
                    checkbox.checked = !checkbox.checked;
                });
            });

            // Agregar personas disponibles como descendientes
            mockPersons.forEach(person => {
                const isSelected = descendants.includes(person.id);
                const descendantItem = document.createElement('div');
                descendantItem.className = `selection-item${isSelected ? ' selected-item' : ''}`;
                descendantItem.dataset.id = person.id;
                descendantItem.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" ${isSelected ? 'checked' : '' }>
                    <label class="form-check-label">${person.name}</label>
                </div>
            </div>
            `;
                descendantsList.appendChild(descendantItem);

                // Evento para seleccionar/deseleccionar
                descendantItem.addEventListener('click', function() {
                    this.classList.toggle('selected-item');
                    const checkbox = this.querySelector('input[type="checkbox"]');
                    checkbox.checked = !checkbox.checked;
                });
            });
        }

        // Función para abrir el modal de confirmación de eliminación
        function openDeleteModal(personId, personName) {
            const modal = document.getElementById('deleteConfirmModal');
            document.getElementById('deletePersonName').textContent = personName;

            const confirmBtn = document.getElementById('confirmDeleteBtn');
            confirmBtn.onclick = function() {
                deletePerson(personId);
                bootstrap.Modal.getInstance(modal).hide();
            };

            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
        }

        // Función para eliminar una persona
        function deletePerson(personId) {
            toggleLoadingSpinner(true);

            // ENDPOINT PHP: En la implementación final, esta simulación será reemplazada por:
            // fetch('api/persons.php?id=' + personId, {
            // method: 'DELETE'
            // })
            // .then(response => response.json())
            // .then(data => {
            // if (data.success) {
            // showToast('Persona eliminada correctamente', 'success');
            // loadPersonsTable();
            // } else {
            // showToast('Error al eliminar: ' + data.message, 'danger');
            // }
            // })
            // .catch(error => {
            // showToast('Error de conexión: ' + error.message, 'danger');
            // })
            // .finally(() => {
            // toggleLoadingSpinner(false);
            // });

            setTimeout(() => {
                // Simular eliminación (en una aplicación real, esto sería una llamada a la API)
                const index = mockPersons.findIndex(p => p.id === personId);
                if (index !== -1) {
                    mockPersons.splice(index, 1);
                    showToast('Persona eliminada correctamente', 'success');
                    loadPersonsTable();
                } else {
                    showToast('Error al eliminar la persona', 'danger');
                }

                toggleLoadingSpinner(false);
            }, 1000);
        }

        // Función para inicializar el árbol D3.js
        function initializeTree() {
            fetch('api/tree.php')
                .then(response => response.json())
                .then(treeData => {
                    if (treeData.error) {
                        showToast(treeData.error, 'danger');
                        return;
                    }

                    // Renderizar el árbol con los datos recibidos
                    const width = document.getElementById('treeContainer').offsetWidth;
                    const height = document.getElementById('treeContainer').offsetHeight;

                    // Limpiar el contenedor
                    document.getElementById('treeContainer').innerHTML = '';

                    // Crear el SVG
                    const svg = d3.select('#treeContainer')
                        .append('svg')
                        .attr('width', width)
                        .attr('height', height)
                        .append('g')
                        .attr('transform', 'translate(50, 50)');

                    // Definir la jerarquía
                    const root = d3.hierarchy(treeData);

                    // Configurar el layout del árbol
                    const treeLayout = d3.tree().size([height - 100, width - 200]);

                    // Asignar las coordenadas a los nodos
                    treeLayout(root);

                    // Crear los enlaces (líneas)
                    svg.selectAll('.link')
                        .data(root.links())
                        .enter()
                        .append('path')
                        .attr('class', 'link')
                        .attr('d', d3.linkHorizontal()
                            .x(d => d.y)
                            .y(d => d.x));

                    // Crear los nodos (grupos)
                    const node = svg.selectAll('.node')
                        .data(root.descendants())
                        .enter()
                        .append('g')
                        .attr('class', 'node')
                        .attr('transform', d => `translate(${d.y}, ${d.x})`)
                        .on('click', highlightNode);

                    // Agregar círculos a los nodos
                    node.append('circle')
                        .attr('r', 20);

                    // Agregar imágenes a los nodos
                    node.append('clipPath')
                        .attr('id', d => `clip-${d.data.id}`)
                        .append('circle')
                        .attr('r', 20);

                    node.append('image')
                        .attr('xlink:href', d => d.data.photo)
                        .attr('x', -20)
                        .attr('y', -20)
                        .attr('width', 40)
                        .attr('height', 40)
                        .attr('clip-path', d => `url(#clip-${d.data.id})`);

                    // Agregar texto (nombre) a los nodos
                    node.append('text')
                        .attr('dy', 35)
                        .attr('text-anchor', 'middle')
                        .text(d => d.data.name.split(' ')[0]);

                    // Función para resaltar un nodo y su línea de ascendencia/descendencia
                    function highlightNode(event, d) {
                        // Eliminar cualquier resaltado anterior
                        svg.selectAll('.link').classed('highlighted-link', false);
                        svg.selectAll('.node').classed('highlighted-node', false);

                        // Resaltar el nodo actual
                        d3.select(this).classed('highlighted-node', true);

                        // Resaltar todos los antepasados
                        let current = d;
                        while (current.parent) {
                            // Resaltar el enlace al padre
                            svg.selectAll('.link')
                                .filter(link => link.source === current.parent && link.target === current)
                                .classed('highlighted-link', true);

                            // Resaltar el nodo padre
                            svg.selectAll('.node')
                                .filter(node => node === current.parent)
                                .classed('highlighted-node', true);

                            current = current.parent;
                        }

                        // Función recursiva para resaltar descendientes
                        function highlightDescendants(node) {
                            if (!node.children) return;

                            node.children.forEach(child => {
                                // Resaltar el enlace al hijo
                                svg.selectAll('.link')
                                    .filter(link => link.source === node && link.target === child)
                                    .classed('highlighted-link', true);

                                // Resaltar el nodo hijo
                                svg.selectAll('.node')
                                    .filter(n => n === child)
                                    .classed('highlighted-node', true);

                                // Recursivamente resaltar los descendientes
                                highlightDescendants(child);
                            });
                        }

                        // Resaltar todos los descendientes
                        highlightDescendants(d);
                    }

                    // Configurar los controles de zoom
                    let currentZoom = 1;

                    document.getElementById('zoomIn').addEventListener('click', function() {
                        currentZoom += 0.2;
                        svg.attr('transform', `translate(50, 50) scale(${currentZoom})`);
                    });

                    document.getElementById('zoomOut').addEventListener('click', function() {
                        currentZoom = Math.max(0.1, currentZoom - 0.2);
                        svg.attr('transform', `translate(50, 50) scale(${currentZoom})`);
                    });

                    document.getElementById('resetZoom').addEventListener('click', function() {
                        currentZoom = 1;
                        svg.attr('transform', 'translate(50, 50) scale(1)');
                    });

                })
                .catch(error => {
                    showToast('Error al cargar el árbol: ' + error.message, 'danger');
                });

        }

        // Función para calcular la edad
        function calculateAge() {
            const birthDateInput = document.getElementById('birthDate');
            const deathDateInput = document.getElementById('deathDate');
            const ageAtDeathInput = document.getElementById('ageAtDeath');

            if (birthDateInput.value) {
                const birthDate = new Date(birthDateInput.value);
                let age;

                if (deathDateInput.value) {
                    const deathDate = new Date(deathDateInput.value);
                    age = deathDate.getFullYear() - birthDate.getFullYear();

                    // Ajustar la edad si aún no ha pasado el cumpleaños en el año de fallecimiento
                    const m = deathDate.getMonth() - birthDate.getMonth();
                    if (m < 0 || (m === 0 && deathDate.getDate() < birthDate.getDate())) {
                        age--;
                    }

                    ageAtDeathInput.value = age;
                } else {
                    ageAtDeathInput.value = 'Vivo';
                }
            } else {
                ageAtDeathInput.value = '';
            }
        }

        // Función para exportar a PDF
        function exportToPdf() {
            toggleLoadingSpinner(true);

            // ENDPOINT PHP: En la implementación final, generar PDF en el servidor:
            // fetch('api/export.php?format=pdf', {
            // method: 'POST' ,
            // headers: {
            // 'Content-Type' : 'application/json'
            // },
            // body: JSON.stringify({
            // // Opciones de exportación si son necesarias
            // })
            // })
            // .then(response=> response.blob())
            // .then(blob => {
            // // Crear URL para descargar el PDF generado
            // const url = window.URL.createObjectURL(blob);
            // const a = document.createElement('a');
            // a.href = url;
            // a.download = 'arbol-genealogico.pdf';
            // document.body.appendChild(a);
            // a.click();
            // window.URL.revokeObjectURL(url);
            // })
            // .catch(error => {
            // showToast('Error al generar PDF: ' + error.message, 'danger');
            // })
            // .finally(() => {
            // toggleLoadingSpinner(false);
            // });

            setTimeout(() => {
                toggleLoadingSpinner(false);
                showToast('PDF generado correctamente. Iniciando descarga...', 'success');

                // En una aplicación real, aquí se generaría y descargaría el PDF
                showToast('Esta es una simulación. En la versión final, se descargará el PDF real.', 'info');
            }, 2000);
        }

        // Función para exportar a imagen
        function exportToImage() {
            toggleLoadingSpinner(true);

            // ENDPOINT PHP: En la implementación final, generar imagen en el servidor:
            // fetch('api/export.php?format=image', {
            // method: 'POST',
            // headers: {
            // 'Content-Type': 'application/json'
            // },
            // body: JSON.stringify({
            // // Opciones de exportación como resolución, formato, etc.
            // })
            // })
            // .then(response => response.blob())
            // .then(blob => {
            // // Crear URL para descargar la imagen generada
            // const url = window.URL.createObjectURL(blob);
            // const a = document.createElement('a');
            // a.href = url;
            // a.download = 'arbol-genealogico.png';
            // document.body.appendChild(a);
            // a.click();
            // window.URL.revokeObjectURL(url);
            // })
            // .catch(error => {
            // showToast('Error al generar imagen: ' + error.message, 'danger');
            // })
            // .finally(() => {
            // toggleLoadingSpinner(false);
            // });

            setTimeout(() => {
                toggleLoadingSpinner(false);
                showToast('Imagen generada correctamente. Iniciando descarga...', 'success');

                // En una aplicación real, aquí se generaría y descargaría la imagen
                showToast('Esta es una simulación. En la versión final, se descargará la imagen real.', 'info');
            }, 2000);
        }

        // Función para guardar los datos de una persona
        function savePerson() {
            const personId = document.getElementById('personId').value;
            const fullName = document.getElementById('fullName').value;
            const birthDate = document.getElementById('birthDate').value;
            const deathDate = document.getElementById('deathDate').value;
            const gender = document.getElementById('gender').value;
            const notes = document.getElementById('notes').value;
            const photoUrl = document.getElementById('photoUrl').value || document.getElementById('photoPreview').src;

            // Calcular edad al morir
            let ageAtDeath = null;
            if (birthDate && deathDate) {
                const birth = new Date(birthDate);
                const death = new Date(deathDate);
                ageAtDeath = death.getFullYear() - birth.getFullYear();

                // Ajustar la edad si aún no ha pasado el cumpleaños en el año de fallecimiento
                const m = death.getMonth() - birth.getMonth();
                if (m < 0 || (m === 0 && death.getDate() < birth.getDate())) {
                    ageAtDeath--;
                }
            }

            // Obtener ascendientes y descendientes seleccionados
            const ascendants = Array.from(document.querySelectorAll('#ascendantsList .selected-item'))
                .map(item => parseInt(item.dataset.id));

            const descendants = Array.from(document.querySelectorAll('#descendantsList .selected-item'))
                .map(item => parseInt(item.dataset.id));

            // Validación de datos
            if (!fullName || !birthDate || !gender) {
                showToast('Por favor, complete los campos requeridos', 'danger');
                return;
            }

            toggleLoadingSpinner(true);

            const url = currentPersonId ? `api/persons.php?id=${currentPersonId}` : 'api/persons.php';
            const method = currentPersonId = 'POST';

            fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: currentPersonId,
                        name: fullName,
                        birthDate: birthDate,
                        deathDate: deathDate || null,
                        gender: gender,
                        notes: notes,
                        photo: photoUrl,
                        ascendants: ascendants,
                        descendants: descendants
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');

                        // Cerrar el modal y actualizar la tabla
                        const modal = document.getElementById('personModal');
                        bootstrap.Modal.getInstance(modal).hide();
                        loadPersonsTable();
                    } else {
                        showToast('Error: ' + (data.error || data.message), 'danger');
                    }
                })
                .catch(error => {
                    showToast('Error de conexión: ' + error.message, 'danger');
                })
                .finally(() => {
                    toggleLoadingSpinner(false);
                });
        }


        // Inicialización cuando el DOM está listo
        document.addEventListener('DOMContentLoaded', function() {
            // NOTA: Inicialización de la aplicación
            // En la versión final con PHP/MySQL, se podría incluir una llamada para verificar conexión
            // fetch('api/status.php')
            // .then(response => response.json())
            // .then(data => {
            // if (!data.connected) {
            // showToast('Error de conexión a la base de datos', 'danger');
            // }
            // });

            // Cargar la tabla de personas
            loadPersonsTable();

            // Manejar el evento de cambio de pestaña para inicializar el árbol
            document.getElementById('tree-tab').addEventListener('click', function() {
                setTimeout(() => {
                    initializeTree();
                }, 100);
            });

            // Eventos para el formulario de persona
            document.getElementById('birthDate').addEventListener('change', calculateAge);
            document.getElementById('deathDate').addEventListener('change', calculateAge);

            // Evento para el botón de guardar persona
            document.getElementById('savePersonBtn').addEventListener('click', savePerson);

            // Eventos para los botones de exportación
            document.getElementById('exportPdfBtn').addEventListener('click', exportToPdf);
            document.getElementById('exportImageBtn').addEventListener('click', exportToImage);

            // Evento para alternar entre subir foto o usar URL
            document.getElementById('useUrlBtn').addEventListener('click', function() {
                const urlContainer = document.getElementById('photoUrlContainer');
                urlContainer.classList.toggle('d-none');
            });

            // Vista previa de la foto
            document.getElementById('photoInput').addEventListener('change', function(e) {
                if (e.target.files && e.target.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('photoPreview').src = e.target.result;
                        document.getElementById('photoPreviewContainer').classList.remove('d-none');
                    };
                    reader.readAsDataURL(e.target.files[0]);
                }
            });

            document.getElementById('photoUrl').addEventListener('input', function() {
                if (this.value) {
                    document.getElementById('photoPreview').src = this.value;
                    document.getElementById('photoPreviewContainer').classList.remove('d-none');
                } else {
                    document.getElementById('photoPreviewContainer').classList.add('d-none');
                }
            });

            // Búsqueda en listas de relaciones
            document.getElementById('searchAscendants').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                document.querySelectorAll('#ascendantsList .selection-item').forEach(item => {
                    const name = item.querySelector('label').textContent.toLowerCase();
                    if (name.includes(searchTerm)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });

            document.getElementById('searchDescendants').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                document.querySelectorAll('#descendantsList .selection-item').forEach(item => {
                    const name = item.querySelector('label').textContent.toLowerCase();
                    if (name.includes(searchTerm)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>

</html>