let assignments = [];
let assignmentOptions = {
  trainees: [],
  modules: [],
  supervisors: []
};

const statsGrid = document.getElementById("statsGrid");
const tableBody = document.getElementById("assignTableBody");

const assignModal = document.getElementById("assignModal");
const assignForm = document.getElementById("assignForm");
const newAssignBtn = document.getElementById("newAssignBtn");
const modalTitle = assignModal.querySelector(".modal-head h3");
const assignIdField = document.getElementById("assignId");
const traineeSelect = document.getElementById("fStagiaire");
const moduleSelect = document.getElementById("fModule");
const supervisorSelect = document.getElementById("fEncadreur");
const apiUrl = "api/affectations.php";

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function renderStats() {
  const total = assignments.length;
  const enCours = assignments.filter((a) => a.status === "en-cours").length;
  const terminees = assignments.filter((a) => a.status === "terminee").length;
  const activeModules = new Set(assignments.map((a) => a.moduleId)).size;

  const stats = [
    { icon: "👥", value: total, label: "Total affectations" },
    { icon: "⏱", value: enCours, label: "En cours" },
    { icon: "✅", value: terminees, label: "Terminees" },
    { icon: "📘", value: activeModules, label: "Modules actifs" }
  ];

  statsGrid.innerHTML = stats
    .map(
      (s) => `
      <article class="stat-card">
        <div class="stat-icon">${s.icon}</div>
        <div>
          <div class="stat-value">${s.value}</div>
          <div class="stat-label">${s.label}</div>
        </div>
      </article>
    `
    )
    .join("");
}

function statusLabel(status) {
  return status === "terminee" ? "Terminee" : "En cours";
}

function renderTable() {
  if (!assignments.length) {
    tableBody.innerHTML = `
      <tr>
        <td class="empty-row" colspan="6">Aucune affectation disponible</td>
      </tr>
    `;
    return;
  }

  tableBody.innerHTML = assignments
    .map(
      (a) => `
      <tr data-id="${a.id}">
        <td>${escapeHtml(a.trainee)}</td>
        <td><span class="module-pill">${escapeHtml(a.module)}</span></td>
        <td>${escapeHtml(a.supervisor)}</td>
        <td>${escapeHtml(a.date)}</td>
        <td><span class="status-pill ${escapeHtml(a.status)}">${escapeHtml(statusLabel(a.status))}</span></td>
        <td class="actions-cell">
          <button class="menu-btn" data-action="toggle-menu">...</button>
          <div class="row-actions" role="menu">
            <button data-action="edit">Modifier</button>
            <button data-action="mark-done">Marquer termine</button>
            <button data-action="delete" class="danger">Supprimer</button>
          </div>
        </td>
      </tr>
    `
    )
    .join("");
}

function closeAllRowMenus() {
  document.querySelectorAll(".row-actions.open").forEach((m) => m.classList.remove("open"));
}

function populateSelect(select, items, placeholder, labelKey = "nom") {
  select.innerHTML = `<option value="">${placeholder}</option>`;
  items.forEach((item) => {
    const opt = document.createElement("option");
    opt.value = item.id;
    opt.textContent = item[labelKey];
    select.appendChild(opt);
  });
}

function populateFormOptions() {
  populateSelect(traineeSelect, assignmentOptions.trainees, "Choisir un stagiaire");
  populateSelect(moduleSelect, assignmentOptions.modules, "Choisir un module", "titre");
  populateSelect(supervisorSelect, assignmentOptions.supervisors, "Choisir un encadreur");
}

function getAssignmentById(id) {
  return assignments.find((row) => row.id === id);
}

async function loadAssignments() {
  try {
    const res = await fetch(apiUrl, { credentials: "same-origin" });

    if (res.status === 401) {
      sessionStorage.removeItem("user");
      window.location.replace("login.html");
      return;
    }

    const data = await res.json();
    if (!res.ok || !data.success) {
      throw new Error(data.message || `HTTP ${res.status}`);
    }

    assignments = Array.isArray(data.assignments) ? data.assignments : [];
    assignmentOptions = data.options || assignmentOptions;
    populateFormOptions();
    rerender();
  } catch (error) {
    console.error(error);
    tableBody.innerHTML = `
      <tr>
        <td class="empty-row" colspan="6">Impossible de charger les affectations</td>
      </tr>
    `;
  }
}

async function updateAssignmentStatus(id, status) {
  const res = await fetch(`${apiUrl}?id=${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    credentials: "same-origin",
    body: JSON.stringify({ status })
  });

  if (res.status === 401) {
    sessionStorage.removeItem("user");
    window.location.replace("login.html");
    return false;
  }

  const data = await res.json();
  if (!res.ok || !data.success) {
    throw new Error(data.message || `HTTP ${res.status}`);
  }

  return true;
}

async function markDone(id) {
  try {
    const ok = await updateAssignmentStatus(id, "terminee");
    if (!ok) return;
    assignments = assignments.map((row) => (row.id === id ? { ...row, status: "terminee" } : row));
    rerender();
  } catch (error) {
    window.alert(error.message);
  }
}

async function removeAssignment(id) {
  if (!window.confirm("Supprimer cette affectation ?")) return;

  try {
    const res = await fetch(`${apiUrl}?id=${id}`, {
      method: "DELETE",
      credentials: "same-origin"
    });

    if (res.status === 401) {
      sessionStorage.removeItem("user");
      window.location.replace("login.html");
      return;
    }

    const data = await res.json();
    if (!res.ok || !data.success) {
      throw new Error(data.message || `HTTP ${res.status}`);
    }

    assignments = assignments.filter((row) => row.id !== id);
    rerender();
  } catch (error) {
    window.alert(error.message);
  }
}

function openModal(editing = false, assignment = null) {
  assignForm.reset();
  populateFormOptions();
  modalTitle.textContent = editing ? "Modifier l'affectation" : "Nouvelle affectation";

  if (editing && assignment) {
    assignIdField.value = assignment.id;
    traineeSelect.value = String(assignment.traineeId);
    moduleSelect.value = String(assignment.moduleId);
    supervisorSelect.value = String(assignment.supervisorId);
    document.getElementById("fDate").value = assignment.date;
    document.getElementById("fStatus").value = assignment.status;
  } else {
    assignIdField.value = "";
    document.getElementById("fDate").value = new Date().toISOString().slice(0, 10);
    document.getElementById("fStatus").value = "en-cours";
  }

  assignModal.classList.remove("hidden");
}

function closeModal() {
  assignModal.classList.add("hidden");
}

async function addAssignment(evt) {
  evt.preventDefault();
  const id = Number(assignIdField.value);

  const payload = {
    traineeId: Number(traineeSelect.value),
    moduleId: Number(moduleSelect.value),
    supervisorId: Number(supervisorSelect.value),
    date: document.getElementById("fDate").value,
    status: document.getElementById("fStatus").value
  };

  if (!payload.traineeId || !payload.moduleId || !payload.supervisorId || !payload.date || !payload.status) {
    return;
  }

  try {
    const res = await fetch(id ? `${apiUrl}?id=${id}` : apiUrl, {
      method: id ? "PUT" : "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify(payload)
    });

    if (res.status === 401) {
      sessionStorage.removeItem("user");
      window.location.replace("login.html");
      return;
    }

    const data = await res.json();
    if (!res.ok || !data.success) {
      throw new Error(data.message || `HTTP ${res.status}`);
    }

    if (id && data.assignment) {
      assignments = assignments.map((row) => (row.id === id ? data.assignment : row));
    } else if (data.assignment) {
      assignments.unshift(data.assignment);
    }

    closeModal();
    rerender();
  } catch (error) {
    window.alert(error.message);
  }
}

function handleTableClick(evt) {
  const target = evt.target.closest("[data-action]");
  if (!target) {
    if (!evt.target.closest(".row-actions")) closeAllRowMenus();
    return;
  }

  const tr = evt.target.closest("tr[data-id]");
  if (!tr) return;
  const id = Number(tr.dataset.id);
  const action = target.dataset.action;
  const menu = tr.querySelector(".row-actions");

  if (action === "toggle-menu") {
    const isOpen = menu.classList.contains("open");
    closeAllRowMenus();
    if (!isOpen) menu.classList.add("open");
    return;
  }

  closeAllRowMenus();

  if (action === "edit") {
    const row = getAssignmentById(id);
    if (row) openModal(true, row);
  }

  if (action === "mark-done") markDone(id);
  if (action === "delete") removeAssignment(id);
}

function rerender() {
  renderStats();
  renderTable();
}

function bindEvents() {
  tableBody.addEventListener("click", handleTableClick);

  document.addEventListener("click", (evt) => {
    if (!evt.target.closest(".actions-cell")) closeAllRowMenus();
  });

  newAssignBtn.addEventListener("click", openModal);
  assignForm.addEventListener("submit", addAssignment);

  document.querySelectorAll("[data-close-modal]").forEach((btn) => {
    btn.addEventListener("click", closeModal);
  });

  assignModal.addEventListener("click", (evt) => {
    if (evt.target === assignModal) closeModal();
  });
}

bindEvents();
loadAssignments();
