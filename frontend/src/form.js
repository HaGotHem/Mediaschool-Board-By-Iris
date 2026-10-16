import "./style.css";
import { api, showMessage } from "./api.js";
import {
  CHECKED_FIELDS,
  attachErrorSlots,
  fillReferenceSelects,
  liveValidate,
  toPayload,
  validateField,
} from "./validation.js";
const form = document.querySelector("#registration-form");
const message = document.querySelector("#message");
const button = document.querySelector("#submit-button");
const formSection = document.querySelector("#form-section");
const confirmation = document.querySelector("#confirmation");
const confirmationTitle = document.querySelector("#confirmation-title");

attachErrorSlots(form);
liveValidate(form);

try {
  const references = await api("/references");
  fillReferenceSelects(form, references);
  button.disabled = false;
} catch (error) {
  showMessage(message, `Formulaire indisponible : ${error.message}`);
}


let resetTimer;

function showForm() {
  clearTimeout(resetTimer);
  confirmation.hidden = true;
  formSection.hidden = false;
  window.scrollTo({ top: 0 });
  form.elements[0].focus();
}

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  for (const name of CHECKED_FIELDS)
    validateField(form.elements.namedItem(name));
  if (!form.reportValidity()) return;

  const payload = toPayload(form);
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
    document.querySelector("#confirmation-reference").textContent =
      data.reference;
    formSection.hidden = true;
    confirmation.hidden = false;
    window.scrollTo({ top: 0 });
    confirmationTitle.focus(); // pour les lecteurs d'écran
    resetTimer = setTimeout(showForm, 3000);
  } catch (error) {
    showMessage(message, error.message);
    message.focus();
  } finally {
    button.disabled = false;
    button.textContent = "Enregistrer ma visite";
  }
});