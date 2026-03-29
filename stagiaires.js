let trainees = [];
let query = "";

const statsGrid = document.getElementById("statsGrid");
const searchInput = document.getElementById("searchInput");
const tableBody = document.getElementById("traineeTableBody");
const resultCount = document.getElementById("resultCount");

const detailsModal = document.getElementById("detailsModal");
const detailsContent = document.getElementById("detailsContent");
const editModal = document.getElementById("editModal");
const editForm = document.getElementById("editForm");
const createModal = document.getElementById("createModal");
const createForm = document.getElementById("createForm");
const createFormMessage = document.getElementById("createFormMessage");
const createSubmitBtn = document.getElementById("createSubmitBtn");

const statusLabel = {
  actif: "Actif",
  termine: "Termine",
  abandonne: "Abandonne"
};

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function initials(name) {
  return name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((n) => n[0])
    .join("")
    .toUpperCase();
}

function fmtPeriod(row) {
  return `${row.startDate} - ${row.endDate}`;
}

function filteredRows() {
  const q = query.trim().toLowerCase();
  if (!q) return trainees;
  return trainees.filter((t) =>
    [t.name, t.email, t.school, t.field, t.level, statusLabel[t.status]]
      .join(" ")
      .toLowerCase()
      .includes(q)
  );
}

function renderStats() {
  const total = trainees.length;
  const actifs = trainees.filter((t) => t.status === "actif").length;
  const termines = trainees.filter((t) => t.status === "termine").length;
  const abandonnes = trainees.filter((t) => t.status === "abandonne").length;

  const cards = [
    { icon: "👥", value: total, label: "Total stagiaires", cls: "" },
    { icon: "🧑‍💼", value: actifs, label: "Actifs", cls: "success" },
    { icon: "🎓", value: termines, label: "Termines", cls: "info" },
    { icon: "❌", value: abandonnes, label: "Abandonnes", cls: "danger" }
  ];

  statsGrid.innerHTML = cards
    .map(
      (c) => `
      <article class="stat-card ${c.cls}">
        <div class="stat-icon">${c.icon}</div>
        <div>
          <div class="stat-value">${c.value}</div>
          <div class="stat-label">${c.label}</div>
        </div>
      </article>
    `
    )
    .join("");
}

function closeAllRowMenus() {
  document.querySelectorAll(".row-actions.open").forEach((m) => m.classList.remove("open"));
}

function renderTable() {
  const rows = filteredRows();
  resultCount.textContent = `${rows.length} resultat(s)`;

  tableBody.innerHTML = rows
    .map(
      (t) => `
      <tr data-id="${t.id}">
        <td>
          <div class="person">
            <div class="avatar">${escapeHtml(initials(t.name))}</div>
            <div>
              <p class="name">${escapeHtml(t.name)}</p>
              <p class="email">${escapeHtml(t.email)}</p>
            </div>
          </div>
        </td>
        <td>${escapeHtml(t.school)}</td>
        <td>${escapeHtml(t.field)}</td>
        <td><span class="level-pill">${escapeHtml(t.level)}</span></td>
        <td>${escapeHtml(fmtPeriod(t))}</td>
        <td><span class="status-pill ${escapeHtml(t.status)}">${escapeHtml(statusLabel[t.status])}</span></td>
        <td class="actions-cell">
          <button class="menu-btn" data-action="toggle-menu">...</button>
          <div class="row-actions" role="menu">
            <button data-action="details">Voir details</button>
            <button data-action="edit">Modifier</button>
            <button data-action="mark-active">Marquer Actif</button>
            <button data-action="mark-abandon">Marquer Abandonne</button>
            <button data-action="delete" class="danger">Supprimer</button>
          </div>
        </td>
      </tr>
    `
    )
    .join("");
}

function getRowById(id) {
  return trainees.find((t) => t.id === id);
}

function openModal(modal) {
  modal.classList.remove("hidden");
}

function closeModal(modal) {
  modal.classList.add("hidden");
}

function openDetails(row) {
  detailsContent.innerHTML = `
    <div class="details-grid">
      <p><strong>Nom:</strong> ${escapeHtml(row.name)}</p>
      <p><strong>Email:</strong> ${escapeHtml(row.email)}</p>
      <p><strong>Etablissement:</strong> ${escapeHtml(row.school)}</p>
      <p><strong>Filiere:</strong> ${escapeHtml(row.field)}</p>
      <p><strong>Niveau:</strong> ${escapeHtml(row.level)}</p>
      <p><strong>Periode:</strong> ${escapeHtml(fmtPeriod(row))}</p>
      <p><strong>Statut:</strong> ${escapeHtml(statusLabel[row.status])}</p>
    </div>
  `;
  openModal(detailsModal);
}

function openEdit(row) {
  document.getElementById("editId").value = row.id;
  document.getElementById("editName").value = row.name;
  document.getElementById("editEmail").value = row.email;
  document.getElementById("editSchool").value = row.school;
  document.getElementById("editField").value = row.field;
  document.getElementById("editLevel").value = row.level;
  document.getElementById("editStart").value = row.startDate;
  document.getElementById("editEnd").value = row.endDate;
  document.getElementById("editStatus").value = row.status;
  openModal(editModal);
}

function updateFromEditForm(evt) {
  evt.preventDefault();
  const id = Number(document.getElementById("editId").value);
  const row = getRowById(id);
  if (!row) return;

  row.name = document.getElementById("editName").value.trim();
  row.email = document.getElementById("editEmail").value.trim();
  row.school = document.getElementById("editSchool").value.trim();
  row.field = document.getElementById("editField").value.trim();
  row.level = document.getElementById("editLevel").value.trim();
  row.startDate = document.getElementById("editStart").value;
  row.endDate = document.getElementById("editEnd").value;
  row.status = document.getElementById("editStatus").value;

  closeModal(editModal);
  rerender();
}

function setStatus(id, status) {
  const row = getRowById(id);
  if (!row) return;
  row.status = status;
  rerender();
}

function removeTrainee(id) {
  const idx = trainees.findIndex((t) => t.id === id);
  if (idx === -1) return;
  const ok = window.confirm("Confirmer la suppression de ce stagiaire ?");
  if (!ok) return;
  trainees.splice(idx, 1);
  rerender();
}

function resetCreateForm() {
  createForm.reset();
  document.getElementById("createStatus").value = "actif";
  createFormMessage.textContent = "";
  createFormMessage.className = "form-message hidden";
  createSubmitBtn.disabled = false;
  createSubmitBtn.textContent = "Enregistrer";
}

function showCreateMessage(message, type) {
  createFormMessage.textContent = message;
  createFormMessage.className = `form-message ${type}`;
}

async function loadTrainees() {
  const res = await fetch("api/stagiaires.php", {
    credentials: "same-origin"
  });

  if (res.status === 401) {
    sessionStorage.removeItem("user");
    window.location.replace("login.html");
    return;
  }

  if (!res.ok) {
    throw new Error(`HTTP ${res.status}`);
  }

  const data = await res.json();
  if (!data.success) {
    throw new Error(data.message || "Chargement impossible");
  }

  trainees = Array.isArray(data.stagiaires) ? data.stagiaires : [];
  rerender();
}

async function submitCreateForm(evt) {
  evt.preventDefault();

  const payload = {
    name: document.getElementById("createName").value.trim(),
    email: document.getElementById("createEmail").value.trim(),
    school: document.getElementById("createSchool").value.trim(),
    field: document.getElementById("createField").value.trim(),
    level: document.getElementById("createLevel").value.trim(),
    startDate: document.getElementById("createStart").value,
    endDate: document.getElementById("createEnd").value,
    status: document.getElementById("createStatus").value
  };

  createSubmitBtn.disabled = true;
  createSubmitBtn.textContent = "Enregistrement...";
  showCreateMessage("Enregistrement en cours...", "success");

  try {
    const res = await fetch("api/stagiaires.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify(payload)
    });

    const data = await res.json();

    if (!res.ok || !data.success) {
      throw new Error(data.message || `HTTP ${res.status}`);
    }

    if (data.stagiaire) {
      trainees.unshift(data.stagiaire);
    }

    rerender();
    showCreateMessage("Stagiaire enregistre avec succes.", "success");

    window.setTimeout(() => {
      resetCreateForm();
      closeModal(createModal);
    }, 700);
  } catch (error) {
    showCreateMessage(error.message || "Impossible d'enregistrer le stagiaire.", "error");
    createSubmitBtn.disabled = false;
    createSubmitBtn.textContent = "Enregistrer";
  }
}

function handleTableClick(evt) {
  const actionTarget = evt.target.closest("[data-action]");
  if (!actionTarget) {
    if (!evt.target.closest(".row-actions")) {
      closeAllRowMenus();
    }
    return;
  }

  const tr = evt.target.closest("tr[data-id]");
  if (!tr) return;
  const id = Number(tr.dataset.id);
  const action = actionTarget.dataset.action;
  const menu = tr.querySelector(".row-actions");

  if (action === "toggle-menu") {
    const isOpen = menu.classList.contains("open");
    closeAllRowMenus();
    if (!isOpen) menu.classList.add("open");
    return;
  }

  closeAllRowMenus();

  if (action === "details") {
    const row = getRowById(id);
    if (row) openDetails(row);
  }

  if (action === "edit") {
    const row = getRowById(id);
    if (row) openEdit(row);
  }

  if (action === "mark-active") {
    setStatus(id, "actif");
  }

  if (action === "mark-abandon") {
    setStatus(id, "abandonne");
  }

  if (action === "delete") {
    removeTrainee(id);
  }
}

function rerender() {
  renderStats();
  renderTable();
}

function bindGlobalEvents() {
  searchInput.addEventListener("input", (e) => {
    query = e.target.value;
    renderTable();
  });

  tableBody.addEventListener("click", handleTableClick);

  document.addEventListener("click", (evt) => {
    if (!evt.target.closest(".actions-cell")) {
      closeAllRowMenus();
    }
  });

  document.querySelectorAll("[data-close-modal]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const id = btn.getAttribute("data-close-modal");
      const modal = document.getElementById(id);
      if (modal) {
        closeModal(modal);
      }
      if (id === "createModal") {
        resetCreateForm();
      }
    });
  });

  detailsModal.addEventListener("click", (evt) => {
    if (evt.target === detailsModal) closeModal(detailsModal);
  });

  editModal.addEventListener("click", (evt) => {
    if (evt.target === editModal) closeModal(editModal);
  });

  createModal.addEventListener("click", (evt) => {
    if (evt.target === createModal) {
      closeModal(createModal);
      resetCreateForm();
    }
  });

  editForm.addEventListener("submit", updateFromEditForm);
  createForm.addEventListener("submit", submitCreateForm);

  document.getElementById("newTraineeBtn").addEventListener("click", () => {
    resetCreateForm();
    openModal(createModal);
  });

  document.getElementById("exportPdfBtn").addEventListener("click", () => {
    window.print();
  });
}

async function init() {
  bindGlobalEvents();
  renderStats();
  renderTable();

  try {
    await loadTrainees();
  } catch (error) {
    console.error(error);
    window.alert("Impossible de charger les stagiaires depuis la base de donnees.");
  }
}

init();
