export let csrfToken = "";
export async function api(path, { method = "GET", body } = {}) {
  const headers = { Accept: "application/json" };
  if (body !== undefined) headers["Content-Type"] = "application/json";
  if (method !== "GET" && csrfToken) headers["X-CSRF-Token"] = csrfToken;
  let response;
  try {
    response = await fetch(`/api${path}`, {
      method,
      credentials: "same-origin",
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch {
    throw new Error(
      "Connexion interrompue. Vos informations restent dans le formulaire. Réessayez.",
    );
  }
  // Nginx peut répondre en texte/HTML à une limite de taille ou de débit.
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(
      payload.error?.message || `Demande impossible (${response.status}).`,
    );
    error.status = response.status;
    error.fields = payload.error?.fields || {};
    throw error;
  }
  if (payload.data?.csrf_token) csrfToken = payload.data.csrf_token;
  return payload.data;
}
export function showMessage(element, message, success = false) {
  element.className = `alert ${success ? "alert-success" : "alert-error"} mb-4`;
  element.textContent = message;
  element.hidden = false;
}
