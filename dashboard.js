let statsData = [];
let recentTrainees = [];
let assignments = [];

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function renderStats() {
  const root = document.getElementById("statsGrid");

  root.innerHTML = statsData
    .map(
      (s) => `
      <article class="stat-card">
        <div class="label">${escapeHtml(s.label)}</div>
        <div class="value">${escapeHtml(s.value)}</div>
      </article>
    `
    )
    .join("");
}

function renderRecent() {
  const list = document.getElementById("recentList");
  document.getElementById("recentTotal").textContent = `${recentTrainees.length} total`;

  if (!recentTrainees.length) {
    list.innerHTML = `<div class="empty">Aucun stagiaire recent</div>`;
    return;
  }

  list.innerHTML = recentTrainees
    .map(
      (t) => `
      <div class="list-item">
        <div class="avatar">${escapeHtml(t.initials)}</div>
        <div>
          <p class="item-title">${escapeHtml(t.name)}</p>
          <p class="item-sub">${escapeHtml(t.sub)}</p>
        </div>
        <span class="status status-${escapeHtml(t.status)}">${escapeHtml(t.statusLabel)}</span>
      </div>
    `
    )
    .join("");
}

function renderAssignments() {
  const list = document.getElementById("assignList");
  document.getElementById("assignTotal").textContent = `${assignments.length} total`;

  if (!assignments.length) {
    list.innerHTML = `<div class="empty">Aucune affectation</div>`;
    return;
  }

  list.innerHTML = assignments
    .map(
      (a) => `
      <div class="list-item">
        <div class="avatar">${escapeHtml(
          a.name
            .split(" ")
            .slice(0, 2)
            .map((n) => n[0] || "")
            .join("")
            .toUpperCase()
        )}</div>
        <div>
          <p class="item-title">${escapeHtml(a.name)}</p>
          <p class="item-sub">Module : ${escapeHtml(a.module)}</p>
        </div>
        <span class="status status-${escapeHtml(a.status)}">${escapeHtml(a.statusLabel)}</span>
      </div>
    `
    )
    .join("");
}

function renderError(message) {
  const statsRoot = document.getElementById("statsGrid");
  const recentList = document.getElementById("recentList");
  const assignList = document.getElementById("assignList");

  statsRoot.innerHTML = `<article class="stat-card stat-card-error"><div class="label">Erreur</div><div class="error-text">${escapeHtml(message)}</div></article>`;
  recentList.innerHTML = `<div class="empty">${escapeHtml(message)}</div>`;
  assignList.innerHTML = `<div class="empty">${escapeHtml(message)}</div>`;
}

function updateUserHeading(user) {
  const heading = document.getElementById("welcomeTitle");
  if (!heading || !user || !user.nom) {
    return;
  }

  heading.textContent = `Bienvenue, ${user.nom}`;
}

function initActions() {
  const exportBtn = document.getElementById("exportBtn");
  exportBtn.addEventListener("click", () => {
    alert("Export Google Sheets non branche. Connecte ton API ici.");
  });
}

async function loadDashboard() {
  try {
    const res = await fetch("api/dashboard.php", {
      credentials: "same-origin",
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

    statsData = data.stats || [];
    recentTrainees = data.recentTrainees || [];
    assignments = data.assignments || [];

    if (data.user) {
      sessionStorage.setItem("user", JSON.stringify(data.user));
      updateUserHeading(data.user);
    }

    renderStats();
    renderRecent();
    renderAssignments();
  } catch (error) {
    console.error(error);
    renderError("Impossible de charger les donnees du tableau de bord.");
  }
}

document.addEventListener("DOMContentLoaded", () => {
  initActions();
  loadDashboard();
});
