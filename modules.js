let modules = [];

const modulesGrid = document.getElementById("modulesGrid");
const newModuleBtn = document.getElementById("newModuleBtn");
const modal = document.getElementById("moduleModal");
const modalTitle = document.getElementById("modalTitle");
const form = document.getElementById("moduleForm");
const idField = document.getElementById("moduleId");
const titleField = document.getElementById("moduleTitle");
const descField = document.getElementById("moduleDesc");
const durationField = document.getElementById("moduleDuration");
const apiUrl = "api/modules.php";

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function parseDurationDays(value) {
  const match = String(value).match(/\d+/);
  return match ? Number(match[0]) : 0;
}

function setLoadingState(message) {
  modulesGrid.innerHTML = `<div class="empty">${escapeHtml(message)}</div>`;
}

function renderModules() {
  if (!modules.length) {
    modulesGrid.innerHTML = `<div class="empty">Aucun module disponible. Clique sur "Nouveau module" pour commencer.</div>`;
    return;
  }

  modulesGrid.innerHTML = modules
    .map(
      (m) => `
      <article class="module-card" data-id="${m.id}">
        <div class="card-head">
          <div class="book-icon">📖</div>
          <div class="card-actions">
            <button class="icon-btn" data-action="edit" title="Modifier">✎</button>
            <button class="icon-btn delete" data-action="delete" title="Supprimer">🗑</button>
          </div>
        </div>
        <h3 class="module-title">${escapeHtml(m.title)}</h3>
        <p class="module-desc">${escapeHtml(m.description)}</p>
        <p class="module-duration">Duree : ${escapeHtml(m.durationLabel)}</p>
      </article>
    `
    )
    .join("");
}

function openModal(editing = false, module = null) {
  modalTitle.textContent = editing ? "Modifier module" : "Nouveau module";

  if (editing && module) {
    idField.value = module.id;
    titleField.value = module.title;
    descField.value = module.description;
    durationField.value = module.durationDays;
  } else {
    form.reset();
    idField.value = "";
  }

  modal.classList.remove("hidden");
}

function closeModal() {
  modal.classList.add("hidden");
}

function getModuleById(id) {
  return modules.find((m) => m.id === id);
}

async function loadModules() {
  setLoadingState("Chargement des modules...");

  try {
    const res = await fetch(apiUrl, {
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

    modules = Array.isArray(data.modules) ? data.modules : [];
    renderModules();
  } catch (error) {
    console.error(error);
    setLoadingState("Impossible de charger les modules.");
  }
}

async function saveModule(evt) {
  evt.preventDefault();

  const id = Number(idField.value);
  const payload = {
    title: titleField.value.trim(),
    description: descField.value.trim(),
    durationDays: parseDurationDays(durationField.value)
  };

  if (!payload.title || !payload.description || !payload.durationDays) {
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
      modules = modules.map((row) => (row.id === id ? data.module : row));
    } else if (data.module) {
      modules.unshift(data.module);
    }

    closeModal();
    renderModules();
  } catch (error) {
    window.alert(error.message);
  }
}

async function deleteModule(id) {
  if (!window.confirm("Supprimer ce module ?")) {
    return;
  }

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

    modules = modules.filter((row) => row.id !== id);
    renderModules();
  } catch (error) {
    window.alert(error.message);
  }
}

modulesGrid.addEventListener("click", (evt) => {
  const btn = evt.target.closest("[data-action]");
  if (!btn) return;

  const card = evt.target.closest(".module-card");
  if (!card) return;
  const id = Number(card.dataset.id);

  if (btn.dataset.action === "edit") {
    const row = getModuleById(id);
    if (row) openModal(true, row);
  }

  if (btn.dataset.action === "delete") {
    deleteModule(id);
  }
});

newModuleBtn.addEventListener("click", () => openModal(false));
form.addEventListener("submit", saveModule);

modal.addEventListener("click", (evt) => {
  if (evt.target === modal) closeModal();
});

document.querySelectorAll("[data-close-modal]").forEach((btn) => {
  btn.addEventListener("click", closeModal);
});

loadModules();
