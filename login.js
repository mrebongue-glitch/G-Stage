// ──────────────────────────────────────────────────────────
//  login.js — Connexion G-Stage (Douanes du Cameroun)
// ──────────────────────────────────────────────────────────

const loginForm = document.getElementById("loginForm");
const message   = document.getElementById("message");
const togglePwd = document.getElementById("togglePwd");
const pwdInput  = document.getElementById("password");
const submitBtn = document.getElementById("submitBtn");
const btnText   = document.getElementById("btnText");
const btnSpinner= document.getElementById("btnSpinner");
const authLoginUrl = "api/auth.php";

// ── Afficher / masquer le mot de passe ──────────────────
togglePwd.addEventListener("click", () => {
  const hidden = pwdInput.type === "password";
  pwdInput.type = hidden ? "text" : "password";
  togglePwd.textContent = hidden ? "🔓" : "🔒";
  togglePwd.setAttribute(
    "aria-label",
    hidden ? "Masquer le mot de passe" : "Afficher le mot de passe"
  );
});

// ── Helpers ─────────────────────────────────────────────
function setLoading(loading) {
  submitBtn.disabled = loading;
  btnText.textContent = loading ? "Connexion..." : "Se connecter";
  btnSpinner.classList.toggle("hidden", !loading);
}

function showError(msg) {
  message.textContent = msg;
  message.style.color = "#dc2626";
}

function showSuccess(msg) {
  message.textContent = msg;
  message.style.color = "#166534";
}

// ── Soumission ───────────────────────────────────────────
loginForm.addEventListener("submit", (event) => {
  event.preventDefault();
  message.textContent = "";

  const identifiant  = document.getElementById("username").value.trim();
  const mot_de_passe = pwdInput.value;

  if (!identifiant || !mot_de_passe) {
    showError("Veuillez remplir tous les champs.");
    return;
  }

  setLoading(true);
  showSuccess("Connexion en cours...");

  fetch(authLoginUrl, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "same-origin",
    body: JSON.stringify({ identifiant, mot_de_passe })
  })
  .then(async (res) => {
    const ct = res.headers.get("content-type") || "";
    if (!ct.includes("application/json")) {
      const text = await res.text();
      throw new Error("Réponse inattendue : " + text.slice(0, 200));
    }
    return res.json();
  })
  .then((data) => {
    if (data.success) {
      sessionStorage.setItem("user", JSON.stringify(data.user));
      window.location.replace("dashboard.html");
    } else {
      setLoading(false);
      showError(data.message || "Identifiant ou mot de passe incorrect.");
    }
  })
  .catch((err) => {
    setLoading(false);
    showError("Erreur de connexion : " + err.message);
    console.error(err);
  });
});

// ── Effacer le message en cours de saisie ───────────────
document.getElementById("username").addEventListener("input", () => { message.textContent = ""; });
pwdInput.addEventListener("input", () => { message.textContent = ""; });
