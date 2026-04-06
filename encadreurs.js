let supervisors = [];

const statsGrid = document.getElementById("statsGrid");
const tableBody = document.getElementById("supervisorTableBody");
const resultCount = document.getElementById("resultCount");

const detailsModal = document.getElementById("detailsModal");
const detailsContent = document.getElementById("detailsContent");
const editModal = document.getElementById("editModal");
const editForm = document.getElementById("editForm");
const createModal = document.getElementById("createModal");
const createForm = document.getElementById("createForm");
const createFormMessage = document.getElementById("createFormMessage");
const createSubmitBtn = document.getElementById("createSubmitBtn");

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

function accountLabel(account) {
  return account === "avec" ? "Avec compte" : "Sans compte";
}

function renderStats() {
  const total = supervisors.length;
  const withAccount = supervisors.filter((s) => s.account === "avec").length;
  const withoutAccount = supervisors.filter((s) => s.account === "sans").length;
  const invited = supervisors.filter((s) => s.invited).length;

  const cards = [
    { icon: "👥", value: total, label: "Total encadreurs", cls: "" },
    { icon: "🧑‍💼", value: withAccount, label: "Avec compte", cls: "success" },
    { icon: "✉️", value: withoutAccount, label: "Sans compte", cls: "warning" },
    { icon: "🛡️", value: invited, label: "Invitations envoyees", cls: "info" }
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
  resultCount.textContent = `${supervisors.length} encadreur(s)`;

  tableBody.innerHTML = supervisors
    .map(
      (s) => `
      <tr data-id="${s.id}">
        <td>
          <div class="person">
            <div class="avatar">${escapeHtml(initials(s.name))}</div>
            <div><p class="name">${escapeHtml(s.name)}</p></div>
          </div>
        </td>
        <td><span class="role-pill">${escapeHtml(s.role)}</span></td>
        <td>${escapeHtml(s.phone || "")}</td>
        <td>${escapeHtml(s.email)}</td>
        <td>
          <button class="invite-btn" data-action="invite">${s.invited ? "Invite envoye" : "Inviter"}</button>
          <span class="account-pill ${escapeHtml(s.account)}">${escapeHtml(accountLabel(s.account))}</span>
        </td>
        <td class="actions-cell">
          <button class="menu-btn" data-action="toggle-menu">...</button>
          <div class="row-actions" role="menu">
            <button data-action="details">Voir details</button>
            <button data-action="edit">Modifier</button>
            <button data-action="invite">Inviter</button>
            <button data-action="toggle-account">Basculer compte</button>
            <button data-action="delete" class="danger">Supprimer</button>
          </div>
        </td>
      </tr>
    `
    )
    .join("");
}

function getById(id) {
  return supervisors.find((s) => s.id === id);
}

function updateSupervisorInState(updatedRow) {
  if (!updatedRow || typeof updatedRow.id !== "number") return;
  supervisors = supervisors.map((row) => (row.id === updatedRow.id ? updatedRow : row));
}

function showCredentialsAlert(name, credentials) {
  if (!credentials?.identifiant || !credentials?.mot_de_passe_temporaire) {
    return;
  }

  window.alert(
    `Compte pret pour ${name}\nIdentifiant : ${credentials.identifiant}\nMot de passe temporaire : ${credentials.mot_de_passe_temporaire}`
  );
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
      <p><strong>Fonction:</strong> ${escapeHtml(row.role)}</p>
      <p><strong>Telephone:</strong> ${escapeHtml(row.phone || "")}</p>
      <p><strong>Email:</strong> ${escapeHtml(row.email)}</p>
      <p><strong>Compte:</strong> ${escapeHtml(accountLabel(row.account))}</p>
      <p><strong>Invitation:</strong> ${row.invited ? "Envoyee" : "Non envoyee"}</p>
    </div>
  `;
  openModal(detailsModal);
}

function openEdit(row) {
  document.getElementById("editId").value = row.id;
  document.getElementById("editName").value = row.name;
  document.getElementById("editEmail").value = row.email;
  document.getElementById("editPhone").value = row.phone || "";
  document.getElementById("editRole").value = row.role;
  document.getElementById("editAccount").value = row.account;
  openModal(editModal);
}

async function fetchSupervisorApi(url, options = {}) {
  const res = await fetch(url, {
    credentials: "same-origin",
    ...options
  });

  if (res.status === 401) {
    sessionStorage.removeItem("user");
    window.location.replace("login.html");
    return null;
  }

  const data = await res.json();
  if (!res.ok || !data.success) {
    throw new Error(data.message || `HTTP ${res.status}`);
  }

  return data;
}

async function submitEdit(evt) {
  evt.preventDefault();
  const id = Number(document.getElementById("editId").value);
  const row = getById(id);
  if (!row) return;

  try {
    const data = await fetchSupervisorApi(`api/encadreurs.php?id=${id}`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        name: document.getElementById("editName").value.trim(),
        email: document.getElementById("editEmail").value.trim(),
        phone: document.getElementById("editPhone").value.trim(),
        role: document.getElementById("editRole").value.trim(),
        account: document.getElementById("editAccount").value,
        invited: row.invited
      })
    });

    if (!data) return;

    if (data.encadreur) {
      updateSupervisorInState(data.encadreur);
    }

    closeModal(editModal);
    rerender();
    showCredentialsAlert(row.name, data.credentials);
  } catch (error) {
    window.alert(error.message || "Impossible de modifier l'encadreur.");
  }
}

async function sendInvite(id) {
  const row = getById(id);
  if (!row) return;

  const data = await fetchSupervisorApi("api/encadreurs.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "invite", id })
  });
  if (!data) return null;

  if (data.encadreur) {
    updateSupervisorInState(data.encadreur);
    rerender();
  }

  return {
    row,
    credentials: data.credentials ?? null
  };
}

async function toggleAccount(id) {
  const row = getById(id);
  if (!row) return;

  try {
    const data = await fetchSupervisorApi(`api/encadreurs.php?id=${id}`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        name: row.name,
        email: row.email,
        phone: row.phone || "",
        role: row.role,
        account: row.account === "avec" ? "sans" : "avec",
        invited: row.invited
      })
    });

    if (!data) return;

    if (data.encadreur) {
      updateSupervisorInState(data.encadreur);
    }

    rerender();
    showCredentialsAlert(row.name, data.credentials);
  } catch (error) {
    window.alert(error.message || "Impossible de mettre a jour le type de compte.");
  }
}

async function removeSupervisor(id) {
  const idx = supervisors.findIndex((s) => s.id === id);
  if (idx === -1) return;
  const ok = window.confirm("Confirmer la suppression de cet encadreur ?");
  if (!ok) return;

  try {
    const data = await fetchSupervisorApi(`api/encadreurs.php?id=${id}`, {
      method: "DELETE"
    });

    if (!data) return;

    supervisors.splice(idx, 1);
    rerender();
  } catch (error) {
    window.alert(error.message || "Impossible de supprimer l'encadreur.");
  }
}

async function inviteAll() {
  const pendingRows = supervisors.filter((row) => row.account !== "avec");

  if (pendingRows.length === 0) {
    window.alert("Tous les encadreurs disposent deja d'un compte.");
    return;
  }

  const generated = [];
  const errors = [];

  for (const row of pendingRows) {
    try {
      const result = await sendInvite(row.id);
      if (result?.credentials) {
        generated.push(
          `${row.name} : ${result.credentials.identifiant} / ${result.credentials.mot_de_passe_temporaire}`
        );
      }
    } catch (error) {
      errors.push(`${row.name} : ${error.message || "Erreur inconnue"}`);
    }
  }

  const messages = [];
  if (generated.length > 0) {
    messages.push(`Comptes generes :\n${generated.join("\n")}`);
  }
  if (errors.length > 0) {
    messages.push(`Erreurs :\n${errors.join("\n")}`);
  }

  if (messages.length > 0) {
    window.alert(messages.join("\n\n"));
  }
}

function resetCreateForm() {
  createForm.reset();
  document.getElementById("createAccount").value = "sans";
  document.getElementById("createInvited").value = "0";
  createFormMessage.textContent = "";
  createFormMessage.className = "form-message hidden";
  createSubmitBtn.disabled = false;
  createSubmitBtn.textContent = "Enregistrer";
}

function showCreateMessage(message, type) {
  createFormMessage.textContent = message;
  createFormMessage.className = `form-message ${type}`;
}

async function loadSupervisors() {
  const data = await fetchSupervisorApi("api/encadreurs.php");
  if (!data) return;

  supervisors = Array.isArray(data.encadreurs) ? data.encadreurs : [];
  rerender();
}

async function submitCreateForm(evt) {
  evt.preventDefault();

  const payload = {
    name: document.getElementById("createName").value.trim(),
    email: document.getElementById("createEmail").value.trim(),
    phone: document.getElementById("createPhone").value.trim(),
    role: document.getElementById("createRole").value.trim(),
    account: document.getElementById("createAccount").value,
    invited: document.getElementById("createInvited").value === "1"
  };

  createSubmitBtn.disabled = true;
  createSubmitBtn.textContent = "Enregistrement...";
  showCreateMessage("Enregistrement en cours...", "success");

  try {
    const data = await fetchSupervisorApi("api/encadreurs.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });

    if (data.encadreur) {
      supervisors.unshift(data.encadreur);
    }

    rerender();
    showCreateMessage("Encadreur enregistre avec succes.", "success");
    showCredentialsAlert(payload.name, data.credentials);

    window.setTimeout(() => {
      resetCreateForm();
      closeModal(createModal);
    }, 700);
  } catch (error) {
    showCreateMessage(error.message || "Impossible d'enregistrer l'encadreur.", "error");
    createSubmitBtn.disabled = false;
    createSubmitBtn.textContent = "Enregistrer";
  }
}

function handleTableClick(evt) {
  const target = evt.target.closest("[data-action]");
  if (!target) {
    if (!evt.target.closest(".row-actions")) {
      closeAllRowMenus();
    }
    return;
  }

  const action = target.dataset.action;
  const tr = evt.target.closest("tr[data-id]");
  const id = tr ? Number(tr.dataset.id) : null;

  if (action === "toggle-menu") {
    const menu = tr.querySelector(".row-actions");
    const isOpen = menu.classList.contains("open");
    closeAllRowMenus();
    if (!isOpen) menu.classList.add("open");
    return;
  }

  closeAllRowMenus();

  if (action === "details" && id !== null) {
    const row = getById(id);
    if (row) openDetails(row);
  }

  if (action === "edit" && id !== null) {
    const row = getById(id);
    if (row) openEdit(row);
  }

  if (action === "invite" && id !== null) {
    sendInvite(id)
      .then((result) => {
        if (result?.credentials) {
          window.alert(
            `Compte cree pour ${result.row.name}\nIdentifiant : ${result.credentials.identifiant}\nMot de passe temporaire : ${result.credentials.mot_de_passe_temporaire}`
          );
        }
      })
      .catch((error) => {
        window.alert(error.message || "Impossible de generer le compte utilisateur.");
      });
  }

  if (action === "toggle-account" && id !== null) {
    toggleAccount(id);
  }

  if (action === "delete" && id !== null) {
    removeSupervisor(id);
  }
}

function bindEvents() {
  tableBody.addEventListener("click", handleTableClick);

  document.addEventListener("click", (evt) => {
    if (!evt.target.closest(".actions-cell")) {
      closeAllRowMenus();
    }
  });

  document.querySelectorAll("[data-close-modal]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const modal = document.getElementById(btn.getAttribute("data-close-modal"));
      if (modal) closeModal(modal);
      if (btn.getAttribute("data-close-modal") === "createModal") {
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

  editForm.addEventListener("submit", submitEdit);
  createForm.addEventListener("submit", submitCreateForm);

  document.getElementById("newSupervisorBtn").addEventListener("click", () => {
    resetCreateForm();
    openModal(createModal);
  });

  document.getElementById("inviteAllBtn").addEventListener("click", inviteAll);
}

function rerender() {
  renderStats();
  renderTable();
}

async function init() {
  bindEvents();
  renderStats();
  renderTable();

  try {
    await loadSupervisors();
  } catch (error) {
    console.error(error);
    window.alert("Impossible de charger les encadreurs depuis la base de donnees.");
  }
}

init();
