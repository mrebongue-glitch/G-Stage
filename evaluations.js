let evaluations = [];
let evaluationOptions = [];

const statsGrid = document.getElementById("statsGrid");
const tableBody = document.getElementById("evalTableBody");

const evalModal = document.getElementById("evalModal");
const evalForm = document.getElementById("evalForm");
const newEvalBtn = document.getElementById("newEvalBtn");
const modalTitle = document.getElementById("modalTitle");
const assignmentSelect = document.getElementById("fAssignment");
const apiUrl = "api/evaluations.php";

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function appreciation(score) {
  return score >= 10 ? "Bien" : "Insuffisant";
}

function isGood(score) {
  return score >= 10;
}

function avgScore() {
  if (!evaluations.length) return 0;
  const total = evaluations.reduce((sum, e) => sum + Number(e.score), 0);
  return total / evaluations.length;
}

function renderStats() {
  const total = evaluations.length;
  const moyenne = avgScore();
  const mentions = evaluations.filter((e) => Number(e.score) >= 16).length;
  const stagiaires = new Set(evaluations.map((e) => e.trainee)).size;

  const stats = [
    { icon: "📋", value: `${total}`, label: "Total evaluations" },
    { icon: "📈", value: `${moyenne.toFixed(1)}/20`, label: "Moyenne generale" },
    { icon: "🏅", value: `${mentions}`, label: "Mentions >= 16" },
    { icon: "⭐", value: `${stagiaires}`, label: "Stagiaires evalues" }
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

function closeAllRowMenus() {
  document.querySelectorAll(".row-actions.open").forEach((m) => m.classList.remove("open"));
}

function renderTable() {
  if (!evaluations.length) {
    tableBody.innerHTML = `
      <tr>
        <td class="empty-row" colspan="7">Aucune evaluation disponible</td>
      </tr>
    `;
    return;
  }

  tableBody.innerHTML = evaluations
    .map((e) => {
      const good = isGood(Number(e.score));
      return `
        <tr data-id="${e.id}">
          <td>${escapeHtml(e.trainee)}</td>
          <td><span class="module-pill">${escapeHtml(e.module)}</span></td>
          <td>${escapeHtml(e.date)}</td>
          <td><span class="note ${good ? "good" : "bad"}">${Number(e.score)}/20</span></td>
          <td><span class="appreciation-pill ${good ? "good" : "bad"}">${appreciation(Number(e.score))}</span></td>
          <td>${escapeHtml(e.comment)}</td>
          <td class="actions-cell">
            <button class="menu-btn" data-action="toggle-menu">...</button>
            <div class="row-actions" role="menu">
              <button data-action="edit">Modifier</button>
              <button data-action="delete" class="danger">Supprimer</button>
            </div>
          </td>
        </tr>
      `;
    })
    .join("");
}

function getById(id) {
  return evaluations.find((e) => e.id === id);
}

function populateAssignmentSelect(selectedId = "") {
  assignmentSelect.innerHTML = `<option value="">Choisir une affectation</option>`;
  evaluationOptions.forEach((option) => {
    const opt = document.createElement("option");
    opt.value = option.id;
    opt.textContent = option.label;
    if (String(option.id) === String(selectedId)) {
      opt.selected = true;
    }
    assignmentSelect.appendChild(opt);
  });
}

async function loadEvaluations() {
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

    evaluations = Array.isArray(data.evaluations) ? data.evaluations : [];
    evaluationOptions = Array.isArray(data.options) ? data.options : [];
    populateAssignmentSelect();
    rerender();
  } catch (error) {
    console.error(error);
    tableBody.innerHTML = `
      <tr>
        <td class="empty-row" colspan="7">Impossible de charger les evaluations</td>
      </tr>
    `;
  }
}

function openModal(editing = false, row = null) {
  modalTitle.textContent = editing ? "Modifier evaluation" : "Nouvelle evaluation";
  evalForm.reset();

  if (editing && row) {
    document.getElementById("evalId").value = row.id;
    populateAssignmentSelect(row.affectationId);
    document.getElementById("fDate").value = row.date;
    document.getElementById("fScore").value = row.score;
    document.getElementById("fComment").value = row.comment;
  } else {
    document.getElementById("evalId").value = "";
    populateAssignmentSelect();
    document.getElementById("fDate").value = new Date().toISOString().slice(0, 10);
  }

  evalModal.classList.remove("hidden");
}

function closeModal() {
  evalModal.classList.add("hidden");
}

async function saveEvaluation(evt) {
  evt.preventDefault();

  const id = Number(document.getElementById("evalId").value);
  const payload = {
    affectationId: Number(assignmentSelect.value),
    date: document.getElementById("fDate").value,
    score: Number(document.getElementById("fScore").value),
    comment: document.getElementById("fComment").value.trim()
  };

  if (!payload.affectationId || !payload.date || Number.isNaN(payload.score) || !payload.comment) {
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

    if (id) {
      await loadEvaluations();
    } else if (data.evaluation) {
      evaluations.unshift(data.evaluation);
    }

    closeModal();
    rerender();
  } catch (error) {
    window.alert(error.message);
  }
}

async function removeEvaluation(id) {
  if (!window.confirm("Supprimer cette evaluation ?")) return;

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

    evaluations = evaluations.filter((row) => row.id !== id);
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
    const row = getById(id);
    if (row) openModal(true, row);
  }

  if (action === "delete") {
    removeEvaluation(id);
  }
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

  newEvalBtn.addEventListener("click", () => openModal(false));
  evalForm.addEventListener("submit", saveEvaluation);

  document.querySelectorAll("[data-close-modal]").forEach((btn) => {
    btn.addEventListener("click", closeModal);
  });

  evalModal.addEventListener("click", (evt) => {
    if (evt.target === evalModal) closeModal();
  });
}

bindEvents();
loadEvaluations();
