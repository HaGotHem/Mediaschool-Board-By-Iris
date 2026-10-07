import "./style.css";
import { api, showMessage } from "./api.js";
import {
  CHECKED_FIELDS,
  attachErrorSlots,
  fillReferenceSelects,
  liveValidate,
  showFieldError,
  toPayload,
  validateField,
} from "./validation.js";
const message = document.querySelector("#admin-message");
const login = document.querySelector("#login-section");
const dashboard = document.querySelector("#dashboard-section");
const form = document.querySelector("#login-form");

function display(user) {
  login.hidden = Boolean(user);
  dashboard.hidden = !user;
  document.querySelector("#signed-in").textContent = user
    ? `Connecté : ${user.username}`
    : "";
  // Les exports ne dépendent pas de la liste chargée : actifs dès la connexion
  for (const id of ["#export-csv", "#export-pdf"]) {
    document.querySelector(id).disabled = !user;
  }
  if (user) loadStats();
}

async function loadStats() {
  try {
    const stats = await api("/admin/stats");
    const schools = new Set(stats.groups.map((group) => group.school_id));
    document.querySelector("#stat-visits").textContent = stats.total;
    document.querySelector("#stat-schools").textContent = schools.size;
    document.querySelector("#stat-sync").textContent = new Date(
      stats.generated_at,
    ).toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" });
  } catch (error) {
    if (error.status === 401) display(null);
    showMessage(message, error.message);
  }
}

try {
  const session = await api("/auth/session");
  display(session.user);
} catch (error) {
  showMessage(message, error.message);
}

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  const button = form.querySelector("button");
  button.disabled = true;
  try {
    const data = await api("/auth/login", {
      method: "POST",
      body: Object.fromEntries(new FormData(form)),
    });
    display(data.user);
    form.reset();
    message.hidden = true;
  } catch (error) {
    showMessage(message, error.message);
  } finally {
    button.disabled = false;
  }
});

document.querySelector("#logout").addEventListener("click", async () => {
  try {
    await api("/auth/logout", { method: "POST" });
    display(null);
    resetList();
  } catch (error) {
    showMessage(message, error.message);
  }
});

const body = document.querySelector("#visits-body");
const emptyState = body.innerHTML; // l'état « Aucune visite chargée » du HTML
const searchInput = document.querySelector("#filter-search");
const schoolSelect = document.querySelector("#filter-school");
const dialog = document.querySelector("#visit-dialog");
let visits = [];

// --- Adapter ici si les champs de l'API sont différents ---
const pick = (...values) =>
  values.find((v) => v !== undefined && v !== null && v !== "");
const label = (r, key) =>
  pick(
    r[`${key}_label`],
    r[key]?.label,
    typeof r[key] === "string" ? r[key] : undefined,
    "—",
  );

function normalize(r) {
  return {
    id: r.id,
    reference: pick(r.reference, r.id, "—"),
    lastName: r.last_name ?? "",
    firstName: r.first_name ?? "",
    email: r.email ?? "",
    phone: r.phone ?? "",
    birthDate: r.birth_date ?? "",
    currentClass: label(r, "current_class"),
    school: label(r, "school"),
    level: label(r, "entry_level"),
    specialty: label(r, "specialty"),
    remark: r.remark ?? "",
  };
}
// -----------------------------------------------------------

const formatDate = (value) =>
  /^\d{4}-\d{2}-\d{2}/.test(value)
    ? value.slice(0, 10).split("-").reverse().join("/")
    : value;

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function messageRow(text) {
  const tr = el("tr");
  const td = el("td", "py-10 text-center text-base-content/70", text);
  td.colSpan = 4;
  tr.append(td);
  return tr;
}

function visitRow(v) {
  const tr = el("tr", "hover");

  const who = el("td");
  who.append(
    el("div", "font-medium", `${v.firstName} ${v.lastName}`.trim() || "—"),
  );
  who.append(el("div", "text-sm text-base-content/70", v.email));

  const school = el("td", "", v.school);

  const level = el("td");
  level.append(el("span", "badge badge-outline badge-secondary", v.level));

  const actions = el("td", "text-right");
  const btn = el("button", "btn btn-ghost btn-sm text-secondary", "Voir");
  btn.type = "button";
  btn.setAttribute(
    "aria-label",
    `Voir la fiche de ${v.firstName} ${v.lastName}`,
  );
  btn.addEventListener("click", () => showVisit(v, btn));
  actions.append(btn);

  tr.append(who, school, level, actions);
  return tr;
}

function render() {
  const q = searchInput.value.trim().toLowerCase();
  const school = schoolSelect.value;
  const rows = visits.filter(
    (v) =>
      (!school || v.school === school) &&
      (!q ||
        `${v.firstName} ${v.lastName} ${v.email}`.toLowerCase().includes(q)),
  );
  body.replaceChildren(
    ...(rows.length
      ? rows.map(visitRow)
      : [messageRow("Aucune visite ne correspond à votre recherche.")]),
  );
}

function openDetails(v) {
  document.querySelector("#visit-title").textContent =
    `${v.firstName} ${v.lastName}`.trim();
  const fields = [
    ["Référence", v.reference],
    ["E-mail", v.email],
    ["Téléphone", v.phone],
    ["Naissance", formatDate(v.birthDate)],
    ["Classe actuelle", v.currentClass],
    ["École visée", v.school],
    ["Niveau", v.level],
    ["Spécialité", v.specialty],
    ["Remarque", v.remark || "—"],
  ];
  const dl = document.querySelector("#visit-details");
  dl.replaceChildren();
  for (const [name, value] of fields) {
    dl.append(el("dt", "text-base-content/70", name));
    dl.append(el("dd", "col-span-2 font-medium break-words", value || "—"));
  }
  setEditMode(false);
  if (!dialog.open) dialog.showModal();
}

// La liste ne contient ni e-mail, ni téléphone, ni naissance : on charge la fiche complète
async function showVisit(v, button) {
  button.disabled = true;
  try {
    currentVisit = await api(`/admin/registrations/${v.id}`);
    openDetails(normalize(currentVisit));
  } catch (error) {
    if (error.status === 401) display(null);
    showMessage(message, error.message);
  } finally {
    button.disabled = false;
  }
}

// --- Modification d'une fiche ---
const visitView = document.querySelector("#visit-view");
const visitForm = document.querySelector("#visit-form");
const visitMessage = document.querySelector("#visit-message");
const saveButton = document.querySelector("#visit-save");
let currentVisit = null; // fiche brute de l'API (avec les id des listes)
let referencesLoaded = false;

attachErrorSlots(visitForm);
liveValidate(visitForm);

function setEditMode(editing) {
  visitView.hidden = editing;
  visitForm.hidden = !editing;
  visitMessage.hidden = true;
}

async function startEdit() {
  try {
    if (!referencesLoaded) {
      fillReferenceSelects(visitForm, await api("/references"));
      referencesLoaded = true;
    }
  } catch (error) {
    showMessage(visitMessage, `Modification indisponible : ${error.message}`);
    return;
  }
  const r = currentVisit;
  const values = {
    last_name: r.last_name,
    first_name: r.first_name,
    birth_date: formatDate(r.birth_date ?? ""), // AAAA-MM-JJ -> JJ/MM/AAAA
    phone: r.phone,
    email: r.email,
    current_class_id: r.current_class_id,
    school_id: r.school_id,
    entry_level_id: r.entry_level_id,
    specialty_id: r.specialty_id,
    remark: r.remark,
  };
  for (const [name, value] of Object.entries(values))
    visitForm.elements.namedItem(name).value = value == null ? "" : String(value);
  for (const name of CHECKED_FIELDS)
    showFieldError(visitForm.elements.namedItem(name), "");
  setEditMode(true);
  visitForm.elements.namedItem("last_name").focus();
}

document.querySelector("#infoedit").addEventListener("click", startEdit);
document
  .querySelector("#visit-cancel")
  .addEventListener("click", () => setEditMode(false));
// Toujours rouvrir une fiche en mode lecture
dialog.addEventListener("close", () => setEditMode(false));

visitForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  for (const name of CHECKED_FIELDS)
    validateField(visitForm.elements.namedItem(name));
  if (!visitForm.reportValidity()) return;

  saveButton.disabled = true;
  try {
    const updated = await api(`/admin/registrations/${currentVisit.id}`, {
      method: "PATCH",
      body: toPayload(visitForm),
    });
    currentVisit = updated;
    // Met à jour la ligne du tableau sans recharger toute la liste
    visits = visits.map((v) => (v.id === updated.id ? normalize(updated) : v));
    fillSchoolFilter();
    render();
    openDetails(normalize(updated));
    showMessage(visitMessage, "Fiche mise à jour.", true);
  } catch (error) {
    if (error.status === 401) {
      dialog.close();
      display(null);
      showMessage(message, error.message);
      return;
    }
    // Erreurs par champ renvoyées par le serveur (ex. e-mail déjà utilisé)
    for (const [name, text] of Object.entries(error.fields ?? {})) {
      const input = visitForm.elements.namedItem(name);
      if (input && document.getElementById(`${input.id}-error`))
        showFieldError(input, text);
    }
    showMessage(visitMessage, error.message);
  } finally {
    saveButton.disabled = false;
  }
});

function updateStats() {
  document.querySelector("#stat-visits").textContent = visits.length;
  document.querySelector("#stat-schools").textContent = new Set(
    visits.map((v) => v.school).filter((s) => s !== "—"),
  ).size;
  document.querySelector("#stat-sync").textContent =
    new Date().toLocaleTimeString("fr-FR", {
      hour: "2-digit",
      minute: "2-digit",
    });
}

function fillSchoolFilter() {
  const schools = [...new Set(visits.map((v) => v.school))].sort((a, b) =>
    a.localeCompare(b, "fr"),
  );
  schoolSelect.replaceChildren(new Option("Toutes les écoles", ""));
  for (const s of schools) schoolSelect.append(new Option(s, s));
}

function resetList() {
  visits = [];
  body.innerHTML = emptyState;
  searchInput.value = "";
  schoolSelect.replaceChildren(new Option("Toutes les écoles", ""));
  for (const id of ["#stat-visits", "#stat-schools", "#stat-sync"])
    document.querySelector(id).textContent = "—";
}

async function loadList() {
  try {
    const data = await api("/admin/registrations");
    const list = Array.isArray(data)
      ? data
      : (data.registrations ?? data.items ?? []);
    visits = list.map(normalize);
    fillSchoolFilter();
    updateStats();
    render();
  } catch (error) {
    if (error.status === 401) display(null);
    showMessage(message, error.message);
  }
}

// Délégation : le bouton « Charger la liste » est recréé quand on se déconnecte
body.addEventListener("click", (event) => {
  if (event.target.closest("#load-list")) loadList();
});
searchInput.addEventListener("input", render);
schoolSelect.addEventListener("change", render);

async function downloadFile(buttonId, path, filename) {
  const button = document.querySelector(buttonId);
  button.disabled = true;
  try {
    const response = await fetch(`/api/admin/exports/${path}`, {
      credentials: "same-origin",
    });
    if (!response.ok) {
      const payload = await response.json().catch(() => ({}));
      const error = new Error(
        payload.error?.message || `Export impossible (${response.status}).`,
      );
      error.status = response.status;
      throw error;
    }
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (error) {
    if (error.status === 401) display(null);
    showMessage(message, error.message);
  } finally {
    // Ne pas réactiver le bouton si on a été déconnecté entre-temps
    button.disabled = dashboard.hidden;
  }
}

// CSV = liste complète des inscrits ; PDF = récapitulatif agrégé
document
  .querySelector("#export-csv")
  .addEventListener("click", () =>
    downloadFile("#export-csv", "registrations/csv", "inscrits-salon.csv"),
  );
document
  .querySelector("#export-pdf")
  .addEventListener("click", () =>
    downloadFile("#export-pdf", "pdf", "recap-salon.pdf"),
  );