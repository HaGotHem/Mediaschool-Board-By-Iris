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
const body = document.querySelector("#visits-body");
const emptyState = body.innerHTML; // état « Aucune visite chargée » du HTML
const searchInput = document.querySelector("#filter-search");
const schoolSelect = document.querySelector("#filter-school");
const dialog = document.querySelector("#visit-dialog");
const meetingDialog = document.querySelector("#meeting-modal");
let visits = [];

/* ---------- Session et statistiques ---------- */

function display(user) {
  login.hidden = Boolean(user);
  dashboard.hidden = !user;
  document.querySelector("#signed-in").textContent = user
    ? `Connecté : ${user.username}`
    : "";
  // Les exports ne dépendent pas de la liste chargée : actifs dès la connexion
  for (const id of ["#export-csv", "#export-pdf", "#send-recap"]) {
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

/* ---------- Liste des visites ---------- */

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
    appointment: formatAppointment(r.preferred_date, r.preferred_time),
    appointmentDate: r.preferred_date ?? "",
    appointmentTime: r.preferred_time ?? "",
    advisor: r.advisor ?? "",
  };
}
// ---------------------------------------------------------

const formatDate = (value) =>
  /^\d{4}-\d{2}-\d{2}/.test(value)
    ? value.slice(0, 10).split("-").reverse().join("/")
    : value;

// « 2026-10-20 » + « 14:30:00 » -> « 20/10/2026 à 14h30 » ; vide si pas de rendez-vous demandé
const formatAppointment = (date, time) =>
  date
    ? `${formatDate(date)} à ${String(time ?? "").slice(0, 5).replace(":", "h")}`
    : "";

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function messageRow(text) {
  const tr = el("tr");
  const td = el("td", "py-10 text-center text-base-content/70", text);
  td.colSpan = 6;
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

  // data-label : intitulé affiché au-dessus de la valeur en vue mobile (cartes)
  const school = el("td", "", v.school);
  school.dataset.label = "École visée";

  const level = el("td");
  level.dataset.label = "Niveau";
  level.append(
    el(
      "span",
      "badge badge-outline badge-secondary h-auto min-h-6 py-1 text-center",
      v.level,
    ),
  );

  const appointment = el(
    "td",
    v.appointment ? "whitespace-nowrap" : "text-base-content/50",
    v.appointment || "—",
  );
  appointment.dataset.label = "RDV souhaité";

  const advisor = el("td", v.advisor ? "" : "text-base-content/50", v.advisor || "—");
  advisor.dataset.label = "Conseiller";

  const actions = el("td", "visit-actions text-right whitespace-nowrap");
  const btn = el("button", "btn btn-ghost btn-sm text-secondary", "Voir");
  btn.type = "button";
  btn.setAttribute(
    "aria-label",
    `Voir la fiche de ${v.firstName} ${v.lastName}`,
  );
  btn.addEventListener("click", () => showVisit(v, btn));

  const meetingBtn = el(
    "button",
    "btn btn-ghost btn-sm text-secondary",
    "Prendre RDV",
  );
  meetingBtn.type = "button";
  meetingBtn.setAttribute(
    "aria-label",
    `Prendre un rdv avec ${v.firstName} ${v.lastName}`,
  );
  meetingBtn.addEventListener("click", () => openMeeting(v, meetingBtn));
  actions.append(meetingBtn, btn);

  tr.append(who, school, level, appointment, advisor, actions);
  return tr;
}

// --- Prise de rendez-vous ---
const meetingForm = document.querySelector("#meeting-form");
const meetingConfirm = document.querySelector("#meeting-confirm");
const meetingMessage =document.querySelector("#meeting-message");
const meetingSave = document.querySelector("#meeting-save");
let meetingVisit = null; // fiche brute de l'API : le PATCH exige tous les champs de la fiche

async function openMeeting(v, button) {
  button.disabled = true;
  try {
    meetingVisit = await api(`/admin/registrations/${v.id}`);
  } catch (error) {
    if (error.status === 401) display(null);
    showMessage(message, error.message);
    return;
  } finally {
    button.disabled = false;
  }
  document.querySelector("#meeting-name").textContent =
    `${v.firstName} ${v.lastName}`.trim();
  // Les inputs date/time attendent « AAAA-MM-JJ » et « HH:MM »
  meetingForm.elements.preferred_date.value = meetingVisit.preferred_date ?? "";
  meetingForm.elements.preferred_time.value =
    meetingVisit.preferred_time?.slice(0, 5) ?? "";
  meetingForm.elements.advisor_name.value = meetingVisit.advisor_name ?? "";
  meetingMessage.hidden = true;
  meetingDialog.showModal();
}

meetingForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (!meetingForm.reportValidity()) return;

  const r = meetingVisit;
  meetingSave.disabled = true;
  try {
    const updated = await api(`/admin/registrations/${r.id}`, {
      method: "PATCH",
      body: {
        last_name: r.last_name,
        first_name: r.first_name,
        birth_date: r.birth_date,
        phone: r.phone,
        email: r.email,
        current_class_id: r.current_class_id,
        school_id: r.school_id,
        entry_level_id: r.entry_level_id,
        specialty_id: r.specialty_id,
        remark: r.remark,
        advisor_name: meetingForm.elements.advisor_name.value,
        preferred_date: meetingForm.elements.preferred_date.value,
        preferred_time: meetingForm.elements.preferred_time.value,
      },
    });
    visits = visits.map((v) => (v.id === updated.id ? normalize(updated) : v));
    render();
    meetingDialog.close();
    document.querySelector("#meeting-confirm-name").textContent =
      `${updated.first_name} ${updated.last_name}`.trim() || "—";
    document.querySelector("#meeting-confirm-date").textContent = formatDate(
      updated.preferred_date,
    );
    document.querySelector("#meeting-confirm-time").textContent = updated.preferred_time
      .slice(0, 5)
      .replace(":", "h");
    document.querySelector("#meeting-confirm-advisor").textContent =
      updated.advisor_name || "—";
    meetingConfirm.showModal();
  } catch (error) {
    if (error.status === 401) {
      meetingDialog.close();
      display(null);
      showMessage(message, error.message);
      return;
    }
    // Le serveur détaille le champ fautif (jour de week-end, date passée…)
    const detail = Object.values(error.fields ?? {}).join(" ");
    showMessage(meetingMessage, detail || error.message);
  } finally {
    meetingSave.disabled = false;
  }
});

function render() {
  const q = searchInput.value.trim().toLowerCase();
  const school = schoolSelect.value;
  const rows = visits.filter(
    (v) =>
      (!school || v.school === school) &&
      (!q ||
        `${v.firstName} ${v.lastName} ${v.email}`.toLowerCase().includes(q)),
  );
  const empty = visits.length
    ? "Aucune visite ne correspond à votre recherche."
    : "Aucune visite enregistrée pour le moment.";
  body.replaceChildren(
    ...(rows.length ? rows.map(visitRow) : [messageRow(empty)]),
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
    ["RDV souhaité", v.appointment],
    ["Conseiller", v.advisor],
    ["Remarque", v.remark || "—"],
  ];
  const dl = document.querySelector("#visit-details");
  dl.replaceChildren();
  for (const [name, value] of fields) {
    // Mobile : intitulé au-dessus de la valeur ; à partir de sm : deux colonnes
    dl.append(el("dt", "text-xs sm:text-sm text-base-content/70", name));
    dl.append(
      el(
        "dd",
        "mb-3 sm:mb-0 sm:col-span-2 font-medium wrap-anywhere",
        value || "—",
      ),
    );
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
    preferred_date: r.preferred_date,
    preferred_time: r.preferred_time?.slice(0, 5), // « 14:30:00 » -> « 14:30 » pour l'input time
    advisor_name: r.advisor_name,
    remark: r.remark,
  };
  for (const [name, value] of Object.entries(values))
    visitForm.elements.namedItem(name).value = value == null ? "" : String(value);
  for (const name of CHECKED_FIELDS)
    showFieldError(visitForm.elements.namedItem(name), "");
  setEditMode(true);
  visitForm.elements.namedItem("last_name").focus();
}

const confirmDialog = document.querySelector("#confirm-dialog");

function askConfirmation(visit) {
  document.querySelector("#confirm-id").textContent = visit.id;
  document.querySelector("#confirm-last-name").textContent = visit.last_name || "—";
  document.querySelector("#confirm-first-name").textContent = visit.first_name || "—";
  return new Promise((resolve) => {
    confirmDialog.returnValue = "";
    confirmDialog.addEventListener(
      "close",
      () => resolve(confirmDialog.returnValue === "confirm"),
      { once: true },
    );
    confirmDialog.showModal();
  });
}

async function startSupp(visit) {
  if (!(await askConfirmation(visit)))
    return;

  const button = document.querySelector("#infosupprimer");
  button.disabled = true;

  try {
    await api(`/admin/registrations/${visit.id}`, { method: "DELETE" });

    // On ne touche à l'affichage qu'une fois la suppression confirmée par le serveur
    visits = visits.filter((v) => v.id !== visit.id);
    currentVisit = null;
    dialog.close();
    fillSchoolFilter();
    render();
    loadStats();
    showMessage(message, "La visite a été supprimée.", true);
  } catch (error) {
    if (error.status === 401) {
      dialog.close();
      display(null);
    }
    showMessage(message, error.message);
  } finally {
    button.disabled = false;
  }
}

document.querySelector("#infosupprimer").addEventListener("click", () => {
  startSupp(currentVisit);
})
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
    render();
  } catch (error) {
    if (error.status === 401) display(null);
    showMessage(message, error.message);
  }
}

// Délégation : le bouton « Charger la liste » est recréé après une déconnexion
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
/* ---------- Envoi du récapitulatif par e-mail ---------- */

const sendDialog = document.querySelector("#send-dialog");
const sendForm = document.querySelector("#send-form");
const sendError = document.querySelector("#send-error");
const sendSubmit = document.querySelector("#send-submit");
const sendInput = document.querySelector("#send-recipient");

function setSendError(text) {
  sendError.textContent = text;
  sendError.hidden = !text;
  sendInput.classList.toggle("input-error", Boolean(text));
}

document.querySelector("#send-recap").addEventListener("click", () => {
  setSendError("");
  sendDialog.showModal();
  sendInput.focus();
});

document
  .querySelector("#send-cancel")
  .addEventListener("click", () => sendDialog.close());

sendDialog.addEventListener("close", () => {
  sendForm.reset();
  setSendError("");
});

sendForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const recipient = sendInput.value.trim();
  const format = new FormData(sendForm).get("format");

  if (!recipient || !sendInput.checkValidity()) {
    setSendError("Saisissez une adresse e-mail valide.");
    sendInput.focus();
    return;
  }

  sendSubmit.disabled = true;
  sendSubmit.textContent = "Envoi…";
  try {
    await api("/admin/summary/email", {
      method: "POST",
      body: { recipient, format },
    });
    sendDialog.close();
    showMessage(message, `Récapitulatif envoyé à ${recipient}.`, true);
  } catch (error) {
    if (error.status === 401) {
      sendDialog.close();
      display(null);
    }
    setSendError(error.fields?.recipient ?? error.message);
  } finally {
    sendSubmit.disabled = false;
    sendSubmit.textContent = "Envoyer";
  }
});