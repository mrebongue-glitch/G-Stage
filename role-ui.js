const ROLE_UI_CONFIG = {
  adminOnlyPages: new Set(["encadreurs.html", "modules.html", "affectations.html"]),
  adminOnlyLinks: new Set(["encadreurs.html", "modules.html", "affectations.html"])
};

function getCurrentPageName() {
  const parts = window.location.pathname.split("/");
  return parts[parts.length - 1] || "dashboard.html";
}

function hideAdminNavigationForSupervisor() {
  document.querySelectorAll(".menu a[href]").forEach((link) => {
    const href = link.getAttribute("href");
    if (ROLE_UI_CONFIG.adminOnlyLinks.has(href)) {
      link.style.display = "none";
    }
  });
}

function applyPageRoleState(user) {
  if (!user || user.role !== "encadreur") {
    return;
  }

  hideAdminNavigationForSupervisor();

  const pageName = getCurrentPageName();
  if (ROLE_UI_CONFIG.adminOnlyPages.has(pageName)) {
    window.location.replace("dashboard.html");
  }
}

function bindLogout() {
  const logoutBtn = document.getElementById("logoutBtn");
  if (!logoutBtn || logoutBtn.dataset.bound === "1") {
    return;
  }

  logoutBtn.dataset.bound = "1";
  logoutBtn.addEventListener("click", (event) => {
    event.preventDefault();
    fetch("api/logout.php", { credentials: "same-origin" })
      .finally(() => {
        sessionStorage.removeItem("user");
        window.location.replace("login.html");
      });
  });
}

function publishUser(user) {
  window.dispatchEvent(new CustomEvent("app-user-ready", { detail: { user } }));
}

async function initRoleUi() {
  try {
    const res = await fetch("api/check_auth.php", { credentials: "same-origin" });

    if (!res.ok) {
      sessionStorage.removeItem("user");
      window.location.replace("login.html");
      return;
    }

    const data = await res.json();
    if (!data.authenticated || !data.user) {
      sessionStorage.removeItem("user");
      window.location.replace("login.html");
      return;
    }

    sessionStorage.setItem("user", JSON.stringify(data.user));
    publishUser(data.user);
    applyPageRoleState(data.user);
    bindLogout();
  } catch (error) {
    sessionStorage.removeItem("user");
    window.location.replace("login.html");
  }
}

document.addEventListener("DOMContentLoaded", initRoleUi);
