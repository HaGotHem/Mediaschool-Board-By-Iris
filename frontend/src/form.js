import './style.css';
import { api, showMessage } from './api.js';
const form = document.querySelector('#registration-form');
const message = document.querySelector('#message');
const button = document.querySelector('#submit-button');
document.querySelector('#birth-date').max = new Date().toLocaleDateString('en-CA');
try {
  const references = await api('/references');
  for (const [name, list] of Object.entries({current_class_id: references.current_classes,
    school_id: references.schools, entry_level_id: references.entry_levels, specialty_id: references.specialties})) {
    const select = form.elements.namedItem(name);
    for (const item of list) { const option = document.createElement('option'); option.value = item.id; option.textContent = item.label; select.append(option); }
  }
  if (references.event) document.querySelector('#event-label').textContent = references.event.label;
  button.disabled = false;
} catch (error) { showMessage(message, `Formulaire indisponible : ${error.message}`); }
form.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!form.reportValidity()) return;
  const payload = Object.fromEntries(new FormData(form));
  for (const field of ['current_class_id','school_id','entry_level_id','specialty_id']) payload[field] = payload[field] === '' ? null : Number(payload[field]);
  button.disabled = true; button.textContent = 'Enregistrement en cours…';
  try {
    const data = await api('/registrations', {method: 'POST', body: payload});
    // L'API doit réellement avoir inséré la ligne avant de retourner la référence.
    if (!data?.reference) throw new Error('La confirmation reçue est incomplète. Contactez l’équipe du salon.');
    showMessage(message, `Votre visite est enregistrée. Référence : ${data.reference}. Merci !`, true);
    form.reset();
  } catch (error) {
    showMessage(message, error.message);
    // Mission FRONT-03 : afficher error.fields près des champs et focus sur le premier champ invalide.
  } finally { button.disabled = false; button.textContent = 'Enregistrer ma visite'; message.focus(); }
});
