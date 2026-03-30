let trainees = [];

const dateInput = document.getElementById("presenceDate");
const readableDate = document.getElementById("readableDate");
const prevDayBtn = document.getElementById("prevDay");
const nextDayBtn = document.getElementById("nextDay");
const reportBtn = document.getElementById("reportBtn");
const allPresentBtn = document.getElementById("allPresentBtn");
const tableBody = document.getElementById("presenceTableBody");
const statsGrid = document.getElementById("statsGrid");
const apiUrl = "api/presences.php";

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function initials(fullName) {
  return fullName
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((n) => n[0])
    .join("")
    .toUpperCase();
}

function setToday() {
  const today = new Date().toISOString().slice(0, 10);
  dateInput.value = today;
  renderReadableDate();
}

function renderReadableDate() {
  if (!dateInput.value) {
    readableDate.textContent = "";
    return;
  }

  const d = new Date(`${dateInput.value}T00:00:00`);
  readableDate.textContent = d.toLocaleDateString("fr-FR", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric"
  });
}

function changeDate(offsetDays) {
  if (!dateInput.value) return;

  const d = new Date(`${dateInput.value}T00:00:00`);
  d.setDate(d.getDate() + offsetDays);
  dateInput.value = d.toISOString().slice(0, 10);
  renderReadableDate();
  loadPresences();
}

function renderStats() {
  const presents = trainees.filter((t) => t.status === "present").length;
  const absents = trainees.filter((t) => t.status === "absent").length;

  statsGrid.innerHTML = `
    <article class="stat-card">
      <div class="stat-value ok">${presents}</div>
      <div class="stat-label">Presents</div>
    </article>
    <article class="stat-card">
      <div class="stat-value ko">${absents}</div>
      <div class="stat-label">Absents</div>
    </article>
  `;
}

function renderTable() {
  if (!trainees.length) {
    tableBody.innerHTML = `
      <tr>
        <td class="empty-row" colspan="4">Aucun stagiaire actif disponible</td>
      </tr>
    `;
    return;
  }

  tableBody.innerHTML = trainees
    .map(
      (t) => `
      <tr data-id="${t.id}">
        <td>
          <div class="person">
            <div class="avatar">${escapeHtml(initials(t.name))}</div>
            <div>
              <p class="name">${escapeHtml(t.name)}</p>
              <p class="sub">${escapeHtml(t.track)}</p>
            </div>
          </div>
        </td>
        <td>${escapeHtml(t.school)}</td>
        <td>
          <select class="status-select" data-field="status">
            <option value="">-</option>
            <option value="present" ${t.status === "present" ? "selected" : ""}>Present</option>
            <option value="absent" ${t.status === "absent" ? "selected" : ""}>Absent</option>
          </select>
        </td>
        <td>
          <input class="motif-input" data-field="reason" type="text" placeholder="Motif (optionnel)" value="${escapeHtml(t.reason)}" />
        </td>
      </tr>
    `
    )
    .join("");
}

function updateRow(id, field, value) {
  const row = trainees.find((t) => t.id === id);
  if (!row) return null;
  row[field] = value;
  renderStats();
  return row;
}

async function savePresence(id) {
  const row = trainees.find((t) => t.id === id);
  if (!row || !row.status) return;

  const res = await fetch(apiUrl, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "same-origin",
    body: JSON.stringify({
      stagiaireId: row.id,
      date: dateInput.value,
      status: row.status,
      reason: row.reason
    })
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
}

async function loadPresences() {
  if (!dateInput.value) return;

  tableBody.innerHTML = `
    <tr>
      <td class="empty-row" colspan="4">Chargement des presences...</td>
    </tr>
  `;

  try {
    const res = await fetch(`${apiUrl}?date=${encodeURIComponent(dateInput.value)}`, {
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

    trainees = Array.isArray(data.presences) ? data.presences : [];
    renderTable();
    renderStats();
  } catch (error) {
    console.error(error);
    tableBody.innerHTML = `
      <tr>
        <td class="empty-row" colspan="4">${escapeHtml(error.message || "Impossible de charger les presences")}</td>
      </tr>
    `;
    statsGrid.innerHTML = "";
  }
}

async function markAllPresent() {
  trainees.forEach((t) => {
    t.status = "present";
    t.reason = "";
  });

  renderTable();
  renderStats();

  try {
    await Promise.all(trainees.map((t) => savePresence(t.id)));
  } catch (error) {
    window.alert(error.message);
  }
}

function bindEvents() {
  dateInput.addEventListener("change", () => {
    renderReadableDate();
    loadPresences();
  });

  prevDayBtn.addEventListener("click", () => changeDate(-1));
  nextDayBtn.addEventListener("click", () => changeDate(1));
  allPresentBtn.addEventListener("click", markAllPresent);

  reportBtn.addEventListener("click", () => {
    window.print();
  });

  tableBody.addEventListener("change", async (evt) => {
    const element = evt.target.closest("[data-field]");
    if (!element) return;

    const tr = evt.target.closest("tr[data-id]");
    if (!tr) return;

    const id = Number(tr.dataset.id);
    const row = updateRow(id, element.dataset.field, element.value);

    if (row && element.dataset.field === "status" && row.status) {
      if (row.status === "present") {
        row.reason = "";
        renderTable();
        renderStats();
      }

      try {
        await savePresence(id);
      } catch (error) {
        window.alert(error.message);
      }
    }
  });

  tableBody.addEventListener("input", async (evt) => {
    const element = evt.target.closest("[data-field='reason']");
    if (!element) return;

    const tr = evt.target.closest("tr[data-id]");
    if (!tr) return;

    const id = Number(tr.dataset.id);
    const row = updateRow(id, "reason", element.value);

    if (row && row.status === "absent") {
      try {
        await savePresence(id);
      } catch (error) {
        window.alert(error.message);
      }
    }
  });
}

setToday();
renderStats();
bindEvents();
loadPresences();
