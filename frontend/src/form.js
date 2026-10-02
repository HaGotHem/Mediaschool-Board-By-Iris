import "./style.css";
import { api, showMessage } from "./api.js";
const form = document.querySelector("#registration-form");
const message = document.querySelector("#message");
const button = document.querySelector("#submit-button");
const formSection = document.querySelector("#form-section");
const confirmation = document.querySelector("#confirmation");
const confirmationTitle = document.querySelector("#confirmation-title");


const PATTERNS = {
  // JJ/MM/AAAA
  birthDate: /^(0[1-9]|[12]\d|3[01])\/(0[1-9]|1[0-2])\/(\d{4})$/,
  // Numéros français
  phone: /^(?:(?:\+|00)33\s?|0)[1-9](?:[\s.-]?\d{2}){4}$/,
  // Lettres (accents inclus), avec espaces, apostrophes ou tirets entre les mots
  name: /^\p{L}+(?:[\s'’-]\p{L}+)*$/u,
  email:
    /^[a-z0-9]+(?:[._%+-][a-z0-9]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i,
};

// La regex vérifie le format, cette fonction vérifie que la date existe vraiment
function parseBirthDate(value) {
  const match = PATTERNS.birthDate.exec(value.trim());
  if (!match) return { error: "Format attendu : JJ/MM/AAAA (ex. 05/03/2008)." };

  const [, day, month, year] = match;
  const date = new Date(Number(year), Number(month) - 1, Number(day));
  if (
    date.getFullYear() !== Number(year) ||
    date.getMonth() !== Number(month) - 1 ||
    date.getDate() !== Number(day)
  )
    return { error: "Cette date n’existe pas." };

  const today = new Date();
  today.setHours(0, 0, 0, 0);
  if (date > today) return { error: "La date ne peut pas être dans le futur." };
  if (Number(year) < 1900) return { error: "Année invalide." };

  return { iso: `${year}-${month}-${day}` };
}

const CHECKED_FIELDS = ["last_name", "first_name", "birth_date", "phone", "email"];

function validateField(input) {
  const value = input.value.trim();
  let error = ""; // Champ vide : c'est l'attribut "required" qui s'en charge

  if (value) {
    if (input.name === "birth_date") {
      error = parseBirthDate(value).error ?? "";
    } else if (input.name === "email" && !PATTERNS.email.test(value)) {
    error = "Adresse e-mail invalide (ex. prenom.nom@exemple.fr).";
    } else if (input.name === "phone" && !PATTERNS.phone.test(value)) {
      error = "Numéro invalide (ex. 06 12 34 56 78).";
    } else if (
      (input.name === "last_name" || input.name === "first_name") &&
      !PATTERNS.name.test(value)
    ) {
      error = "Lettres, espaces, apostrophes et tirets uniquement.";
    }
  }
  input.setCustomValidity(error);
}

// Revalide à chaque frappe pour que l'erreur disparaisse dès que c'est corrigé
form.addEventListener("input", (event) => {
  if (CHECKED_FIELDS.includes(event.target.name)) validateField(event.target);
});

const birthInput = form.elements.namedItem("birth_date");

function formatBirthDate(raw, isDeleting) {
  const d = raw.replace(/\D/g, "").slice(0, 8); // chiffres uniquement, 8 max
  const day = d.slice(0, 2);
  const month = d.slice(2, 4);
  const year = d.slice(4);

  if (d.length > 4) return `${day}/${month}/${year}`;
  if (d.length === 4) return isDeleting ? `${day}/${month}` : `${day}/${month}/`;
  if (d.length > 2) return `${day}/${month}`;
  if (d.length === 2) return isDeleting ? day : `${day}/`;
  return d;
}

birthInput.addEventListener("input", (event) => {
  const isDeleting = event.inputType?.startsWith("delete");
  birthInput.value = formatBirthDate(birthInput.value, isDeleting);
});

try {
  const references = await api("/references");
  for (const [name, list] of Object.entries({
    current_class_id: references.current_classes,
    school_id: references.schools,
    entry_level_id: references.entry_levels,
    specialty_id: references.specialties,
  })) {
    const select = form.elements.namedItem(name);
    for (const item of list) {
      const option = document.createElement("option");
      option.value = item.id;
      option.textContent = item.label;
      select.append(option);
    }
  }
  if (references.event)
    document.querySelector("#event-label").textContent = references.event.label;
  button.disabled = false;
} catch (error) {
  showMessage(message, `Formulaire indisponible : ${error.message}`);
}

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  for (const name of CHECKED_FIELDS) validateField(form.elements.namedItem(name));
  if (!form.reportValidity()) return;

  if (!form.reportValidity()) return;
  const payload = Object.fromEntries(new FormData(form));
  payload.birth_date = parseBirthDate(payload.birth_date).iso; // JJ/MM/AAAA -> AAAA-MM-JJ
  payload.email = payload.email.trim().toLowerCase();
  for (const field of [
    "current_class_id",
    "school_id",
    "entry_level_id",
    "specialty_id",
  ])
    payload[field] = payload[field] === "" ? null : Number(payload[field]);
  button.disabled = true;
  button.textContent = "Enregistrement en cours…";
  try {
    const data = await api("/registrations", { method: "POST", body: payload });
    // L'API doit réellement avoir inséré la ligne avant de retourner la référence.
    if (!data?.reference)
      throw new Error(
        "La confirmation reçue est incomplète. Contactez l’équipe du salon.",
      );
    form.reset();
    message.hidden = true;
    document.querySelector("#confirmation-reference").textContent = data.reference;
    formSection.hidden = true;
    confirmation.hidden = false;
    window.scrollTo({ top: 0 });
    confirmationTitle.focus(); // pour les lecteurs d'écran
  } catch (error) {
    showMessage(message, error.message);
    message.focus();
  } finally {
    button.disabled = false;
    button.textContent = "Enregistrer ma visite";
    message.focus();
  }
});
