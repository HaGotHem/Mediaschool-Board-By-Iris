import './style.css';
import { api, showMessage } from './api.js';
const message = document.querySelector('#admin-message');
const login = document.querySelector('#login-section');
const dashboard = document.querySelector('#dashboard-section');
const form = document.querySelector('#login-form');
function display(user) {
  login.hidden = Boolean(user); dashboard.hidden = !user;
  document.querySelector('#signed-in').textContent = user ? `Connecté : ${user.username}` : '';
}
try { const session = await api('/auth/session'); display(session.user); }
catch (error) { showMessage(message, error.message); }
form.addEventListener('submit', async (event) => {
  event.preventDefault(); const button = form.querySelector('button'); button.disabled = true;
  try { const data = await api('/auth/login', {method: 'POST', body: Object.fromEntries(new FormData(form))}); display(data.user); form.reset(); message.hidden = true; }
  catch (error) { showMessage(message, error.message); }
  finally { button.disabled = false; }
});
document.querySelector('#logout').addEventListener('click', async () => {
  try { await api('/auth/logout', {method: 'POST'}); display(null); document.querySelector('#list-result').textContent = ''; }
  catch (error) { showMessage(message, error.message); }
});
document.querySelector('#load-list').addEventListener('click', async () => {
  try { const data = await api('/admin/registrations'); document.querySelector('#list-result').textContent = JSON.stringify(data, null, 2); }
  catch (error) { if (error.status === 401) display(null); showMessage(message, error.message); }
});
