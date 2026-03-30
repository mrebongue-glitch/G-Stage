/* ───────────────────────────────────────────────────────────
   attestations.js — Génération des attestations de fin de stage
   ─────────────────────────────────────────────────────────── */

let trainees = [];
const apiUrl = "api/attestations.php";

function formatDateFR(isoDate) {
  if (!isoDate) return "—";
  const d = new Date(`${isoDate}T00:00:00`);
  return d.toLocaleDateString("fr-FR", { day: "2-digit", month: "long", year: "numeric" });
}

function computeDuration(startISO, endISO) {
  if (!startISO || !endISO) return "—";

  const start = new Date(`${startISO}T00:00:00`);
  const end = new Date(`${endISO}T00:00:00`);
  const diffMs = end - start;

  if (diffMs < 0) return "—";

  const totalDays = Math.round(diffMs / (1000 * 60 * 60 * 24));
  const months = Math.floor(totalDays / 30);
  const remaining = totalDays % 30;
  const weeks = Math.floor(remaining / 7);
  const days = remaining % 7;

  const parts = [];
  if (months > 0) parts.push(`${months} mois`);
  if (weeks > 0) parts.push(`${weeks} semaine${weeks > 1 ? "s" : ""}`);
  if (days > 0) parts.push(`${days} jour${days > 1 ? "s" : ""}`);
  return parts.length ? parts.join(" et ") : "1 jour";
}

function buildRef(id) {
  return String(id).padStart(3, "0");
}

const selectEl = document.getElementById("stagiaireSelect");
const placeholder = document.getElementById("placeholder");
const preview = document.getElementById("attestationPreview");
const printBtn = document.getElementById("printBtn");

const aName = document.getElementById("aName");
const aSchool = document.getElementById("aSchool");
const aField = document.getElementById("aField");
const aLevel = document.getElementById("aLevel");
const aPeriod = document.getElementById("aPeriod");
const aDuration = document.getElementById("aDuration");
const aMention = document.getElementById("aMention");
const aDate = document.getElementById("aDate");
const refYear = document.getElementById("refYear");
const refNum = document.getElementById("refNum");

function populateSelect() {
  selectEl.innerHTML = `<option value="">Choisir un stagiaire</option>`;

  trainees.forEach((t) => {
    const opt = document.createElement("option");
    opt.value = t.id;
    opt.textContent = t.nom;
    selectEl.appendChild(opt);
  });
}

async function generateAttestation(traineeId) {
  try {
    const res = await fetch(apiUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify({ stagiaireId: Number(traineeId) })
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

    const t = data.stagiaire;
    const attestation = data.attestation;
    const [, year, number] = String(attestation.reference || "").split("/");

    aName.textContent = t.nom;
    aSchool.textContent = t.etablissement;
    aField.textContent = t.filiere;
    aLevel.textContent = t.niveau;
    aPeriod.textContent = `Du ${formatDateFR(t.date_debut)} au ${formatDateFR(t.date_fin)}`;
    aDuration.textContent = computeDuration(t.date_debut, t.date_fin);
    aMention.textContent = attestation.mention;
    aDate.textContent = formatDateFR(attestation.date_generation);
    refYear.textContent = year || new Date().getFullYear();
    refNum.textContent = number || buildRef(t.id);

    placeholder.classList.add("hidden");
    preview.classList.remove("hidden");
    printBtn.classList.remove("hidden");
  } catch (error) {
    console.error(error);
    resetView();
    window.alert("Impossible de générer l'attestation.");
  }
}

function resetView() {
  preview.classList.add("hidden");
  printBtn.classList.add("hidden");
  placeholder.classList.remove("hidden");
}

function printAttestation() {
  window.print();
}

async function loadTrainees() {
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

    trainees = Array.isArray(data.stagiaires) ? data.stagiaires : [];
    populateSelect();
  } catch (error) {
    console.error(error);
    window.alert(error.message || "Impossible de charger les stagiaires pour les attestations.");
  }
}

selectEl.addEventListener("change", function () {
  const val = this.value;
  if (val === "") {
    resetView();
  } else {
    generateAttestation(val);
  }
});

loadTrainees();
