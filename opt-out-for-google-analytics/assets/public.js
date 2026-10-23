// If cookie is removed, check the local storage. If this is true, create the coockie.
if (typeof gaoo_data !== 'undefined' && document.cookie.indexOf(gaoo_data.disable_string + '=true') == -1 && gaoo_localstorage('get', gaoo_data.disable_string) == 'true') {
  gaoo_create_cookie();
}

// Handle the clicks on the links, so the individual texts of the clicked link can be used.
document.addEventListener('click', function (event) {
  var link = event.target.closest ? event.target.closest('.gaoo-link') : null;

  if (link && typeof gaoo_data !== 'undefined') {
    event.preventDefault();
    gaoo_handle_optout(link);
  }
});

// Show the current status on the links, because the page could be delivered from a cache.
if (typeof gaoo_data !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', gaoo_update_links);
  }
  else {
    gaoo_update_links();
  }
}

function gaoo_localstorage(method, name, value) {
  try {
    if ('localStorage' in window && window['localStorage'] !== null) {
      if (method == 'set' && typeof name !== 'undefined' && typeof value !== 'undefined') {
        localStorage.setItem(name, value);
      }

      if (method == 'get' && typeof name !== 'undefined') {
        return localStorage.getItem(name);
      }
    }
  } catch (e) {
    return false;
  }
}

function gaoo_remove_cookie() {
  document.cookie = gaoo_data.disable_string + '=; expires=Thu, 01 Jan 1970 00:00:01 UTC; path=/';
  window[gaoo_data.disable_string] = false;
  gaoo_localstorage('set', gaoo_data.disable_string, false);

  document.cookie = gaoo_data.generic_disable_string + '=; expires=Thu, 01 Jan 1970 00:00:01 UTC; path=/';
  window[gaoo_data.generic_disable_string] = false;
  gaoo_localstorage('set', gaoo_data.generic_disable_string, false);
}

function gaoo_create_cookie() {
  document.cookie = gaoo_data.disable_string + '=true; expires=Thu, 31 Dec 2099 23:59:59 UTC; path=/';
  window[gaoo_data.disable_string] = true;
  gaoo_localstorage('set', gaoo_data.disable_string, true);

  document.cookie = gaoo_data.generic_disable_string + '=true; expires=Thu, 31 Dec 2099 23:59:59 UTC; path=/';
  window[gaoo_data.generic_disable_string] = true;
  gaoo_localstorage('set', gaoo_data.generic_disable_string, true);
}

function gaoo_update_links() {
  var is_disabled = document.cookie.indexOf(gaoo_data.disable_string + '=true') > -1,
    links = document.querySelectorAll('.gaoo-link');

  for (var i = 0; i < links.length; i++) {
    // Individual link texts of the block / shortcode, otherwise the texts from the settings.
    links[i].textContent = is_disabled ? (links[i].getAttribute('data-gaoo-activate') || gaoo_data.link_activate) : (links[i].getAttribute('data-gaoo-deactivate') || gaoo_data.link_deactivate);
    links[i].className = links[i].className.replace(/\bgaoo-link-(de)?activate\b/, is_disabled ? 'gaoo-link-activate' : 'gaoo-link-deactivate');
  }
}

// Returns the text of the popup: individual text of the clicked link, otherwise the text from the settings.
function gaoo_popup_text(link, status) {
  var text = link && link.getAttribute ? link.getAttribute('data-gaoo-popup-' + status) : null;

  if (text) {
    return text;
  }

  return gaoo_data.hasOwnProperty('popup_' + status) ? gaoo_data['popup_' + status] : '';
}

function gaoo_handle_optout(link) {
  var popup;

  if (document.cookie.indexOf(gaoo_data.disable_string + '=true') > -1) {
    gaoo_remove_cookie();

    popup = gaoo_popup_text(link, 'activate');

    if (popup) {
      alert(popup);
    }

    window.dispatchEvent(new CustomEvent('gaoptout', {detail: false}));
  }
  else {
    gaoo_create_cookie();

    popup = gaoo_popup_text(link, 'deactivate');

    if (popup) {
      alert(popup);
    }

    window.dispatchEvent(new CustomEvent('gaoptout', {detail: true}));
  }

  gaoo_update_links();

  if (gaoo_data.hasOwnProperty('force_reload') && gaoo_data.force_reload == true) {
    location.reload();
  }
}