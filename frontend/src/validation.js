// Règles de saisie partagées entre le formulaire public et la modification admin
export const PATTERNS = {
  // JJ/MM/AAAA
  birthDate: /^(0[1-9]|[12]\d|3[01])\/(0[1-9]|1[0-2])\/(\d{4})$/,
  // Numéros français
  phone: /^(?:(?:\+|00)33\s?|0)[1-9](?:[\s.-]?\d{2}){4}$/,
  // Lettres (accents inclus), avec espaces, apostrophes ou tirets entre les mots
  name: /^\p{L}+(?:[\s'’-]\p{L}+)*$/u,
  email:
    /^[a-z0-9]+(?:[._%+-][a-z0-9]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i,
};

export const CHECKED_FIELDS = [
  "last_name",
  "first_name",
  "birth_date",
  "phone",
  "email",
];

// La regex vérifie le format, cette fonction vérifie que la date existe vraiment
export function parseBirthDate(value) {
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

// Message d'erreur d'un champ vérifié, chaîne vide s'il est valide
export function fieldError(name, rawValue) {
  const value = rawValue.trim();
  if (!value) return "Ce champ est obligatoire.";
  if (name === "birth_date") return parseBirthDate(value).error ?? "";
  if (name === "email" && !PATTERNS.email.test(value))
    return "Adresse e-mail invalide (ex. prenom.nom@exemple.fr).";
  if (name === "phone" && !PATTERNS.phone.test(value))
    return "Numéro invalide (ex. 06 12 34 56 78).";
  if ((name === "last_name" || name === "first_name") && !PATTERNS.name.test(value))
    return "Lettres, espaces, apostrophes et tirets uniquement.";
  return "";
}

// Ajoute un message d'erreur sous chaque champ vérifié, relié pour les lecteurs d'écran
export function attachErrorSlots(form) {
  for (const name of CHECKED_FIELDS) {
    const input = form.elements.namedItem(name);
    const errorEl = document.createElement("p");
    errorEl.id = `${input.id}-error`;
    errorEl.className = "text-sm mt-1 text-error";
    errorEl.hidden = true;
    input.after(errorEl);
    input.setAttribute("aria-describedby", errorEl.id);
  }
}

// Affiche (ou retire) l'erreur d'un champ ; error vide = champ valide
export function showFieldError(input, error) {
  input.setCustomValidity(error);
  const errorEl = document.getElementById(`${input.id}-error`);
  errorEl.textContent = error;
  errorEl.hidden = !error;
  input.classList.toggle("input-error", Boolean(error));
  input.setAttribute("aria-invalid", String(Boolean(error)));
}

export function validateField(input) {
  const error = fieldError(input.name, input.value);
  showFieldError(input, error);
  return !error;
}

export function formatBirthDate(raw, isDeleting) {
  const d = raw.replace(/\D/g, "").slice(0, 8); // chiffres uniquement, 8 max
  const day = d.slice(0, 2);
  const month = d.slice(2, 4);
  const year = d.slice(4);

  if (d.length > 4) return `${day}/${month}/${year}`;
  if (d.length === 4)
    return isDeleting ? `${day}/${month}` : `${day}/${month}/`;
  if (d.length > 2) return `${day}/${month}`;
  if (d.length === 2) return isDeleting ? day : `${day}/`;
  return d;
}

// Validation à chaque frappe + formatage JJ/MM/AAAA de la date de naissance
export function liveValidate(form) {
  const birthInput = form.elements.namedItem("birth_date");
  birthInput.addEventListener("input", (event) => {
    const isDeleting = event.inputType?.startsWith("delete");
    birthInput.value = formatBirthDate(birthInput.value, isDeleting);
  });
  // "input" (et non "keydown") : la valeur est déjà à jour quand on valide.
  // Écouté sur le formulaire, il passe après le formatage de la date de naissance.
  form.addEventListener("input", (event) => {
    if (CHECKED_FIELDS.includes(event.target.name)) validateField(event.target);
  });
}

// Remplit les listes déroulantes avec les référentiels de /references
export function fillReferenceSelects(form, references) {
  for (const [name, list] of Object.entries({
    current_class_id: references.current_classes,
    school_id: references.schools,
    entry_level_id: references.entry_levels,
    specialty_id: references.specialties,
  })) {
    const select = form.elements.namedItem(name);
    for (const item of list) select.append(new Option(item.label, item.id));
  }
}

// Valeurs du formulaire au format attendu par l'API
export function toPayload(form) {
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
  return payload;
}
