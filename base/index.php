<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Árbol Genealógico Familiar</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5e2e;
            --secondary-color: #81a584;
            --accent-color: #a8b8a3;
            --light-bg: #f8f9f6;
            --wood-color: #8b4513;
        }

        body {
            background: linear-gradient(135deg, var(--light-bg) 0%, #e8f5e8 100%);
            font-family: 'Georgia', serif;
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .tree-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin: 2rem auto;
            padding: 2rem;
            position: relative;
            overflow-x: auto;
        }

        .tree-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="10" cy="10" r="1" fill="%23a8b8a3" opacity="0.1"/><circle cx="30" cy="30" r="1" fill="%2381a584" opacity="0.1"/><circle cx="50" cy="20" r="1" fill="%232c5e2e" opacity="0.1"/><circle cx="70" cy="40" r="1" fill="%23a8b8a3" opacity="0.1"/><circle cx="90" cy="15" r="1" fill="%2381a584" opacity="0.1"/></svg>') repeat;
            pointer-events: none;
            border-radius: 15px;
        }

        .family-tree {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .generation {
            display: flex;
            justify-content: center;
            margin: 2rem 0;
            flex-wrap: wrap;
            gap: 2rem;
        }

        .person-card {
            background: white;
            border: 3px solid var(--accent-color);
            border-radius: 20px;
            padding: 1.5rem;
            text-align: center;
            min-width: 280px;
            max-width: 320px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
        }

        .person-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 12px 30px rgba(44, 94, 46, 0.2);
            border-color: var(--primary-color);
        }

        .person-card.male {
            border-color: #4a90e2;
            background: linear-gradient(145deg, #ffffff 0%, #f0f8ff 100%);
        }

        .person-card.female {
            border-color: #e24a90;
            background: linear-gradient(145deg, #ffffff 0%, #fff0f8 100%);
        }

        .person-card.other {
            border-color: #9a4ae2;
            background: linear-gradient(145deg, #ffffff 0%, #f8f0ff 100%);
        }

        .person-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            border: 4px solid var(--accent-color);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--light-bg);
        }

        .person-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .person-photo i {
            font-size: 2rem;
            color: var(--secondary-color);
        }

        .person-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .person-details {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.4;
        }

        .person-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }

        .btn-tree {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 20px;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary-tree {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary-tree:hover {
            background: var(--secondary-color);
            transform: scale(1.05);
        }

        .btn-secondary-tree {
            background: var(--accent-color);
            color: white;
        }

        .btn-secondary-tree:hover {
            background: var(--secondary-color);
            transform: scale(1.05);
        }

        .tree-branches {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            z-index: 0;
        }

        .branch-line {
            position: absolute;
            background: var(--wood-color);
            border-radius: 2px;
        }

        .controls {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 2px solid var(--accent-color);
            transition: border-color 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 94, 46, 0.25);
        }

        .empty-tree {
            text-align: center;
            padding: 4rem;
            color: var(--secondary-color);
        }

        .empty-tree i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .age-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 15px;
            font-size: 0.8rem;
            display: inline-block;
            margin-top: 0.5rem;
        }

        .deceased {
            opacity: 0.8;
            position: relative;
        }

        .deceased::after {
            content: '✝';
            position: absolute;
            top: -5px;
            right: -5px;
            background: #666;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .files-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.3rem;
            background: var(--light-bg);
            border-radius: 5px;
            margin-bottom: 0.3rem;
            font-size: 0.8rem;
        }

        @media (max-width: 768px) {
            .generation {
                flex-direction: column;
                align-items: center;
            }

            .person-card {
                min-width: 250px;
                max-width: 280px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .controls {
                position: relative;
                top: auto;
                right: auto;
                margin: 1rem 0;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-tree"></i> Árbol Genealógico Familiar</h1>
            <p class="lead">Preserva la historia de tu familia para las generaciones futuras</p>
        </div>
    </div>

    <div class="controls">
        <button class="btn btn-success btn-lg">
            <i class="fas fa-plus"></i> Agregar Persona
        </button>
    </div>

    <div class="container">
        <div class="tree-container">
            <div id="familyTree" class="family-tree">
                <div class="empty-tree">
                    <i class="fas fa-seedling"></i>
                    <h3>Tu árbol familiar está esperando crecer</h3>
                    <p>Comienza agregando la primera persona de tu familia</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para agregar/editar persona -->
    <div class="modal fade" id="personModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="fas fa-user-plus"></i> Agregar Nueva Persona
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="personForm">
                        <input type="hidden" id="personId" name="personId">

                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="fullName" class="form-label">
                                        <i class="fas fa-user"></i> Nombre Completo *
                                    </label>
                                    <input type="text" class="form-control" id="fullName" name="fullName" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="gender" class="form-label">
                                        <i class="fas fa-venus-mars"></i> Género *
                                    </label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="male">Masculino</option>
                                        <option value="female">Femenino</option>
                                        <option value="other">Otro</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rut" class="form-label">
                                        <i class="fas fa-id-card"></i> RUT/Documento
                                    </label>
                                    <input type="text" class="form-control" id="rut" name="rut" placeholder="12.345.678-9">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="birthDate" class="form-label">
                                        <i class="fas fa-birthday-cake"></i> Fecha de Nacimiento
                                    </label>
                                    <input type="date" class="form-control" id="birthDate" name="birthDate">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="marriageDate" class="form-label">
                                        <i class="fas fa-heart"></i> Fecha de Matrimonio
                                    </label>
                                    <input type="date" class="form-control" id="marriageDate" name="marriageDate">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="deathDate" class="form-label">
                                        <i class="fas fa-cross"></i> Fecha de Fallecimiento
                                    </label>
                                    <input type="date" class="form-control" id="deathDate" name="deathDate">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="photo" class="form-label">
                                <i class="fas fa-image"></i> Fotografía
                            </label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                        </div>

                        <div class="mb-3">
                            <label for="files" class="form-label">
                                <i class="fas fa-paperclip"></i> Archivos Adjuntos
                            </label>
                            <input type="file" class="form-control" id="files" name="files" multiple>
                            <small class="form-text text-muted">Puedes adjuntar documentos, imágenes, PDFs, etc.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="parentSelect" class="form-label">
                                        <i class="fas fa-level-up-alt"></i> Padre/Madre
                                    </label>
                                    <select class="form-select" id="parentSelect" name="parentSelect">
                                        <option value="">Sin padre/madre</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="spouseSelect" class="form-label">
                                        <i class="fas fa-ring"></i> Cónyuge
                                    </label>
                                    <select class="form-select" id="spouseSelect" name="spouseSelect">
                                        <option value="">Sin cónyuge</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="savePerson()">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sistema de almacenamiento local (preparado para base de datos)
        class FamilyTreeDB {
            constructor() {
                this.apiUrl = 'http://localhost/Arbol_Genealogico/api.php'; // URL de tu API
            }

            async loadData() {
                const response = await fetch(this.apiUrl);
                return await response.json();
            }

            async saveData(data) {
                await fetch(this.apiUrl, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
            }

            async addPerson(personData) {
                const response = await fetch(this.apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(personData)
                });
                return await response.json(); // Devuelve el nuevo ID
            }

            async updatePerson(id, personData) {
                await fetch(`${this.apiUrl}/${id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(personData)
                });
            }

            async getPerson(id) {
                const response = await fetch(`${this.apiUrl}/${id}`);
                return await response.json();
            }

            async getAllPeople() {
                const response = await fetch(this.apiUrl);
                return await response.json();
            }

            async deletePerson(id) {
                await fetch(`${this.apiUrl}/${id}`, {
                    method: 'DELETE'
                });
            }
        }

        // Instancia global de la base de datos
        const db = new FamilyTreeDB();
        let currentEditingId = null;

        // Funciones de utilidad
        function calculateAge(birthDate, deathDate = null) {
            if (!birthDate) return null;

            const birth = new Date(birthDate);
            const end = deathDate ? new Date(deathDate) : new Date();

            let age = end.getFullYear() - birth.getFullYear();
            const monthDiff = end.getMonth() - birth.getMonth();

            if (monthDiff < 0 || (monthDiff === 0 && end.getDate() < birth.getDate())) {
                age--;
            }

            return age;
        }

        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('es-CL');
        }

        // Funciones de interfaz
        function showAddPersonModal(parentId = null) {
            currentEditingId = null;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Agregar Nueva Persona';
            document.getElementById('personForm').reset();
            document.getElementById('personId').value = '';

            populateSelects();

            if (parentId) {
                document.getElementById('parentSelect').value = parentId;
            }

            const modal = new bootstrap.Modal(document.getElementById('personModal'));
            modal.show();
        }

        function showEditPersonModal(personId) {
            currentEditingId = personId;
            const person = db.getPerson(personId);
            if (!person) return;

            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit"></i> Editar Persona';

            // Llenar formulario
            document.getElementById('personId').value = person.id;
            document.getElementById('fullName').value = person.fullName || '';
            document.getElementById('rut').value = person.rut || '';
            document.getElementById('birthDate').value = person.birthDate || '';
            document.getElementById('marriageDate').value = person.marriageDate || '';
            document.getElementById('deathDate').value = person.deathDate || '';
            document.getElementById('gender').value = person.gender || '';

            populateSelects(personId);

            // Establecer relaciones actuales
            if (person.parents.length > 0) {
                document.getElementById('parentSelect').value = person.parents[0];
            }
            if (person.spouse) {
                document.getElementById('spouseSelect').value = person.spouse;
            }

            const modal = new bootstrap.Modal(document.getElementById('personModal'));
            modal.show();
        }

        function populateSelects(excludeId = null) {
            const people = db.getAllPeople().filter(p => p.id !== excludeId);

            const parentSelect = document.getElementById('parentSelect');
            const spouseSelect = document.getElementById('spouseSelect');

            // Limpiar opciones
            parentSelect.innerHTML = '<option value="">Sin padre/madre</option>';
            spouseSelect.innerHTML = '<option value="">Sin cónyuge</option>';

            people.forEach(person => {
                const option1 = new Option(person.fullName, person.id);
                const option2 = new Option(person.fullName, person.id);
                parentSelect.add(option1);
                spouseSelect.add(option2);
            });
        }

        function savePerson() {
            const form = document.getElementById('personForm');
            const formData = new FormData(form);

            // Validación básica
            if (!formData.get('fullName') || !formData.get('gender')) {
                alert('Por favor completa los campos obligatorios (Nombre y Género)');
                return;
            }

            const personData = {
                fullName: formData.get('fullName'),
                rut: formData.get('rut'),
                birthDate: formData.get('birthDate'),
                marriageDate: formData.get('marriageDate'),
                deathDate: formData.get('deathDate'),
                gender: formData.get('gender')
            };

            // Manejar archivos (simulado - en producción se subirían al servidor)
            const photoFile = formData.get('photo');
            const attachedFiles = formData.getAll('files');

            if (photoFile && photoFile.size > 0) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    personData.photo = e.target.result;
                    completeSavePerson(personData, attachedFiles, formData);
                };
                reader.readAsDataURL(photoFile);
            } else {
                completeSavePerson(personData, attachedFiles, formData);
            }
        }

        function completeSavePerson(personData, attachedFiles, formData) {
            // Procesar archivos adjuntos
            if (attachedFiles.length > 0) {
                personData.files = Array.from(attachedFiles).map(file => ({
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    // En producción, aquí se subiría el archivo y se guardaría la URL
                    url: '#'
                }));
            }

            let personId;

            if (currentEditingId) {
                // Actualizar persona existente
                db.updatePerson(currentEditingId, personData);
                personId = currentEditingId;
            } else {
                // Crear nueva persona
                personId = db.addPerson(personData);
            }

            // Manejar relaciones
            const parentId = formData.get('parentSelect');
            const spouseId = formData.get('spouseSelect');

            if (parentId) {
                db.addRelationship(parseInt(parentId), personId);
            }

            if (spouseId) {
                db.setSpouse(personId, parseInt(spouseId));
            }

            // Cerrar modal y actualizar vista
            const modal = bootstrap.Modal.getInstance(document.getElementById('personModal'));
            modal.hide();

            renderFamilyTree();
        }

        function deletePerson(personId) {
            const person = db.getPerson(personId);
            if (!person) return;

            if (confirm(`¿Estás seguro de que quieres eliminar a ${person.fullName}? Esta acción no se puede deshacer.`)) {
                db.deletePerson(personId);
                renderFamilyTree();
            }
        }

        function addChild(parentId) {
            showAddPersonModal(parentId);
        }

        function renderPersonCard(person) {
            const age = calculateAge(person.birthDate, person.deathDate);
            const isDeceased = person.deathDate;

            return `
                <div class="person-card ${person.gender} ${isDeceased ? 'deceased' : ''}" onclick="showEditPersonModal(${person.id})">
                    <div class="person-photo">
                        ${person.photo ? 
                            `<img src="${person.photo}" alt="${person.fullName}">` : 
                            `<i class="fas fa-${person.gender === 'female' ? 'female' : person.gender === 'male' ? 'male' : 'user'}"></i>`
                        }
                    </div>
                    <div class="person-name">${person.fullName}</div>
                    <div class="person-details">
                        ${person.rut ? `<div><i class="fas fa-id-card"></i> ${person.rut}</div>` : ''}
                        ${person.birthDate ? `<div><i class="fas fa-birthday-cake"></i> ${formatDate(person.birthDate)}</div>` : ''}
                        ${person.marriageDate ? `<div><i class="fas fa-heart"></i> ${formatDate(person.marriageDate)}</div>` : ''}
                        ${person.deathDate ? `<div><i class="fas fa-cross"></i> ${formatDate(person.deathDate)}</div>` : ''}
                        ${age !== null ? `<div class="age-badge">${age} años${isDeceased ? ' (al morir)' : ''}</div>` : ''}
                        ${person.files && person.files.length > 0 ? `<div class="files-section">
                            <small><i class="fas fa-paperclip"></i> ${person.files.length} archivo(s)</small>
                        </div>` : ''}
                    </div>
                    <div class="person-actions" onclick="event.stopPropagation()">
                        <button class="btn btn-tree btn-primary-tree" onclick="addChild(${person.id})" title="Agregar hijo">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="btn btn-tree btn-secondary-tree" onclick="showEditPersonModal(${person.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-tree btn-danger" onclick="deletePerson(${person.id})" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        }

        function renderFamilyTree() {
            const people = db.getAllPeople();
            const treeContainer = document.getElementById('familyTree');

            if (people.length === 0) {
                treeContainer.innerHTML = `
                    <div class="empty-tree">
                        <i class="fas fa-seedling"></i>
                        <h3>Tu árbol familiar está esperando crecer</h3>
                        <p>Comienza agregando la primera persona de tu familia
                    </div>
                `;
                return;
            }

            // Organizar personas por generaciones
            const generations = organizeByGenerations(people);

            let html = '';
            generations.forEach((generation, index) => {
                if (generation.length > 0) {
                    html += `<div class="generation" data-generation="${index}">`;
                    generation.forEach(person => {
                        html += renderPersonCard(person);
                    });
                    html += '</div>';
                }
            });

            treeContainer.innerHTML = html;
        }

        function organizeByGenerations(people) {
            const generations = [];
            const visited = new Set();

            // Encontrar personas sin padres (raíces del árbol)
            const roots = people.filter(person => person.parents.length === 0);

            if (roots.length === 0 && people.length > 0) {
                // Si no hay raíces claras, tomar la primera persona
                roots.push(people[0]);
            }

            function addToGeneration(person, level) {
                if (visited.has(person.id)) return;
                visited.add(person.id);

                if (!generations[level]) {
                    generations[level] = [];
                }

                generations[level].push(person);

                // Agregar hijos a la siguiente generación
                person.children.forEach(childId => {
                    const child = people.find(p => p.id === childId);
                    if (child) {
                        addToGeneration(child, level + 1);
                    }
                });
            }

            roots.forEach(root => addToGeneration(root, 0));

            // Agregar personas no visitadas (pueden estar desconectadas)
            people.forEach(person => {
                if (!visited.has(person.id)) {
                    if (!generations[0]) {
                        generations[0] = [];
                    }
                    generations[0].push(person);
                }
            });

            return generations;
        }

        // Funciones adicionales para mejorar la experiencia
        function exportFamilyData() {
            const data = db.data;
            const blob = new Blob([JSON.stringify(data, null, 2)], {
                type: 'application/json'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'arbol_genealogico.json';
            a.click();
            URL.revokeObjectURL(url);
        }

        function importFamilyData(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = JSON.parse(e.target.result);
                    if (confirm('¿Estás seguro de que quieres importar estos datos? Esto reemplazará todos los datos actuales.')) {
                        localStorage.setItem(db.storageKey, JSON.stringify(data));
                        location.reload();
                    }
                } catch (error) {
                    alert('Error al importar el archivo. Verifica que sea un archivo JSON válido.');
                }
            };
            reader.readAsText(file);
        }

        function searchPerson() {
            const searchTerm = prompt('Buscar persona por nombre:');
            if (!searchTerm) return;

            const people = db.getAllPeople();
            const results = people.filter(person =>
                person.fullName.toLowerCase().includes(searchTerm.toLowerCase())
            );

            if (results.length === 0) {
                alert('No se encontraron personas con ese nombre.');
                return;
            }

            // Destacar resultados (implementación simple)
            results.forEach(person => {
                const card = document.querySelector(`[onclick="showEditPersonModal(${person.id})"]`);
                if (card) {
                    card.style.border = '3px solid #ff6b6b';
                    card.style.animation = 'pulse 2s infinite';
                    setTimeout(() => {
                        card.style.border = '';
                        card.style.animation = '';
                    }, 5000);
                }
            });
        }

        // Funciones para estadísticas básicas
        function showStatistics() {
            const people = db.getAllPeople();
            const males = people.filter(p => p.gender === 'male').length;
            const females = people.filter(p => p.gender === 'female').length;
            const deceased = people.filter(p => p.deathDate).length;
            const married = people.filter(p => p.marriageDate).length;

            const avgAge = people
                .map(p => calculateAge(p.birthDate, p.deathDate))
                .filter(age => age !== null)
                .reduce((sum, age, _, arr) => sum + age / arr.length, 0);

            alert(`Estadísticas del Árbol Genealógico:
            
Total de personas: ${people.length}
Hombres: ${males}
Mujeres: ${females}
Fallecidos: ${deceased}
Casados: ${married}
Edad promedio: ${avgAge ? Math.round(avgAge) : 'N/A'} años`);
        }

        // Eventos del teclado para shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N para nueva persona
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                showAddPersonModal();
            }

            // Ctrl/Cmd + F para buscar
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                searchPerson();
            }

            // Ctrl/Cmd + S for statistics
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                showStatistics();
            }
        });

        // Inicializar la aplicación
        document.addEventListener('DOMContentLoaded', function() {
            renderFamilyTree();

            // Agregar botones adicionales de manera dinámica
            const controlsDiv = document.querySelector('.controls');
            controlsDiv.innerHTML += `
                <div class="btn-group ms-2" role="group">
                    <button class="btn btn-outline-primary" onclick="searchPerson()" title="Buscar (Ctrl+F)">
                        <i class="fas fa-search"></i>
                    </button>
                    <button class="btn btn-outline-info" onclick="showStatistics()" title="Estadísticas (Ctrl+S)">
                        <i class="fas fa-chart-bar"></i>
                    </button>
                    <button class="btn btn-outline-success" onclick="exportFamilyData()" title="Exportar datos">
                        <i class="fas fa-download"></i>
                    </button>
                    <label class="btn btn-outline-warning" title="Importar datos">
                        <i class="fas fa-upload"></i>
                        <input type="file" accept=".json" onchange="importFamilyData(event)" style="display: none;">
                    </label>
                </div>
            `;
        });

        // Validación de RUT chileno (opcional)
        function validateRUT(rut) {
            rut = rut.replace(/\./g, '').replace('-', '');
            const rutRegex = /^[0-9]+[0-9kK]{1}$/;

            if (!rutRegex.test(rut)) return false;

            const rutNumber = rut.slice(0, -1);
            const verifier = rut.slice(-1).toLowerCase();

            let sum = 0;
            let multiplier = 2;

            for (let i = rutNumber.length - 1; i >= 0; i--) {
                sum += parseInt(rutNumber[i]) * multiplier;
                multiplier = multiplier === 7 ? 2 : multiplier + 1;
            }

            const remainder = sum % 11;
            const calculatedVerifier = remainder === 0 ? '0' : remainder === 1 ? 'k' : (11 - remainder).toString();

            return verifier === calculatedVerifier;
        }

        // Formatear RUT mientras se escribe
        document.getElementById('rut').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\./g, '').replace('-', '');

            if (value.length > 1) {
                const rutNumber = value.slice(0, -1);
                const verifier = value.slice(-1);

                // Formatear con puntos
                const formattedNumber = rutNumber.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                e.target.value = formattedNumber + '-' + verifier;
            }
        });

        // Agregar estilos CSS para animaciones
        const additionalStyles = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            .btn-group .btn {
                border-radius: 0;
            }
            
            .btn-group .btn:first-child {
                border-radius: 0.375rem 0 0 0.375rem;
            }
            
            .btn-group .btn:last-child {
                border-radius: 0 0.375rem 0.375rem 0;
            }
            
            .person-card {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .person-card:hover .person-photo {
                transform: scale(1.1);
                transition: transform 0.3s ease;
            }
            
            .generation {
                position: relative;
            }
            
            .generation::before {
                content: '';
                position: absolute;
                top: -1rem;
                left: 50%;
                right: 50%;
                height: 2rem;
                background: linear-gradient(to bottom, transparent 0%, var(--wood-color) 45%, var(--wood-color) 55%, transparent 100%);
                width: 4px;
                margin-left: -2px;
                z-index: -1;
            }
            
            .generation:first-child::before {
                display: none;
            }
            
            .tooltip {
                position: relative;
                display: inline-block;
            }
            
            .tooltip .tooltiptext {
                visibility: hidden;
                width: 200px;
                background-color: #555;
                color: #fff;
                text-align: center;
                border-radius: 6px;
                padding: 5px;
                position: absolute;
                z-index: 1;
                bottom: 125%;
                left: 50%;
                margin-left: -100px;
                opacity: 0;
                transition: opacity 0.3s;
                font-size: 0.8rem;
            }
            
            .tooltip:hover .tooltiptext {
                visibility: visible;
                opacity: 1;
            }
            
            .loading {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 200px;
            }
            
            .spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid var(--primary-color);
                border-radius: 50%;
                width: 50px;
                height: 50px;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;

        // Agregar estilos adicionales
        const styleSheet = document.createElement('style');
        styleSheet.textContent = additionalStyles;
        document.head.appendChild(styleSheet);

        // Funciones para backup automático
        function autoBackup() {
            const data = db.data;
            const backup = {
                timestamp: new Date().toISOString(),
                data: data
            };

            localStorage.setItem('familyTreeBackup', JSON.stringify(backup));
        }

        // Hacer backup cada 5 minutos
        setInterval(autoBackup, 5 * 60 * 1000);

        // Función para recuperar backup
        function restoreBackup() {
            const backup = localStorage.getItem('familyTreeBackup');
            if (!backup) {
                alert('No hay backup disponible.');
                return;
            }

            try {
                const backupData = JSON.parse(backup);
                const backupDate = new Date(backupData.timestamp).toLocaleString();

                if (confirm(`¿Quieres restaurar el backup del ${backupDate}?`)) {
                    localStorage.setItem(db.storageKey, JSON.stringify(backupData.data));
                    location.reload();
                }
            } catch (error) {
                alert('Error al restaurar el backup.');
            }
        }

        // Drag and drop para reorganizar (funcionalidad básica)
        let draggedElement = null;

        document.addEventListener('dragstart', function(e) {
            if (e.target.classList.contains('person-card')) {
                draggedElement = e.target;
                e.target.style.opacity = '0.5';
            }
        });

        document.addEventListener('dragend', function(e) {
            if (e.target.classList.contains('person-card')) {
                e.target.style.opacity = '1';
                draggedElement = null;
            }
        });

        document.addEventListener('dragover', function(e) {
            e.preventDefault();
        });

        document.addEventListener('drop', function(e) {
            e.preventDefault();
            // Implementar lógica de reordenamiento si es necesario
        });

        // Función para imprimir árbol
        function printFamilyTree() {
            const printWindow = window.open('', '_blank');
            const treeHtml = document.querySelector('.tree-container').innerHTML;

            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Árbol Genealógico - Impresión</title>
                    <style>
                        body { font-family: Georgia, serif; margin: 20px; }
                        .family-tree { display: flex; flex-direction: column; align-items: center; }
                        .generation { display: flex; justify-content: center; margin: 20px 0; flex-wrap: wrap; gap: 20px; }
                        .person-card { border: 2px solid #333; border-radius: 10px; padding: 15px; text-align: center; min-width: 200px; margin: 10px; }
                        .person-photo { width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 10px; border: 2px solid #333; }
                        .person-name { font-weight: bold; margin-bottom: 5px; }
                        .person-details { font-size: 0.9rem; line-height: 1.4; }
                        .person-actions { display: none; }
                        .empty-tree { display: none; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <h1 style="text-align: center;">Árbol Genealógico Familiar</h1>
                    ${treeHtml}
                </body>
                </html>
            `);

            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
        }

        // Agregar botón de imprimir
        window.addEventListener('load', function() {
            const controlsDiv = document.querySelector('.controls .btn-group');
            if (controlsDiv) {
                controlsDiv.innerHTML += `
                    <button class="btn btn-outline-secondary" onclick="printFamilyTree()" title="Imprimir árbol">
                        <i class="fas fa-print"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="restoreBackup()" title="Restaurar backup">
                        <i class="fas fa-history"></i>
                    </button>
                `;
            }
        });
    </script>
</body>

</html>