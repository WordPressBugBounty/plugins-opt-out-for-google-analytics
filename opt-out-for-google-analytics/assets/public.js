// If cookie is removed, check the local storage. If this is true, create the coockie.
if (typeof gaoo_data !== 'undefined' && document.cookie.indexOf(gaoo_data.disable_string + '=true') == -1 && gaoo_localstorage('get', gaoo_data.disable_string) == 'true') {
  gaoo_create_cookie();
  gaoo_consent_denied();
}

// Handle the clicks on the links, so the individual texts of the clicked link can be used.
document.addEventListener('click', function (event) {
  var link = event.target.closest ? event.target.closest('.gaoo-link') : null;

  if (!link || typeof gaoo_data === 'undefined') {
    return;
  }

  event.preventDefault();

  // In the Elementor editor, a click selects the widget and must not change the opt-out.
  if (!document.body.classList.contains('elementor-editor-active')) {
    gaoo_handle_optout(link);
  }
});

// The link has the role "button", so it can also be used with the space key, like a real button.
document.addEventListener('keydown', function (event) {
  var link = (event.key === ' ' || event.key === 'Spacebar') && event.target.closest ? event.target.closest('.gaoo-link') : null;

  if (link) {
    event.preventDefault();

    // Holding the key down repeats the event, the status is only changed once.
    if (!event.repeat) {
      link.click();
    }
  }
});

// Show the current status on the links, because the page could be delivered from a cache.
if (typeof gaoo_data !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', gaoo_init);
  }
  else {
    gaoo_init();
  }
}

function gaoo_init() {
  gaoo_update_links();
  gaoo_live_region();
}

// Hidden live region for screen readers, which announces the new status, if there is no popup text.
// It must be in the page before the text changes, otherwise screen readers do not announce it.
function gaoo_live_region() {
  var region = document.getElementById('gaoo-live');

  if (!region && document.body) {
    region = document.createElement('span');
    region.id = 'gaoo-live';
    region.setAttribute('role', 'status');
    region.style.cssText = 'position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0';
    document.body.appendChild(region);
  }

  return region;
}

function gaoo_announce(text) {
  var region = gaoo_live_region();

  if (!region || !text) {
    return;
  }

  region.textContent = '';
  setTimeout(function () {
    region.textContent = text;
  }, 100);
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

// Google Consent Mode (if enabled): deny "analytics_storage". On the opt-in, the consent of the cookie banner applies again after the next page load.
function gaoo_consent_denied() {
  if (!gaoo_data.consent_mode) {
    return;
  }

  window.dataLayer = window.dataLayer || [];

  (function () {
    window.dataLayer.push(arguments);
  })('consent', 'update', {analytics_storage: 'denied'});
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

// Display of the popup text of the link (data-gaoo-popup-type), otherwise the setting: "alert" or "notice".
function gaoo_popup_type(link) {
  var type = link && link.getAttribute ? link.getAttribute('data-gaoo-popup-type') : null;

  return type || (gaoo_data.popup_notice ? 'notice' : 'alert');
}

// Reload of the page after the click: setting of the link (data-gaoo-reload), otherwise the setting.
function gaoo_force_reload(link) {
  var reload = link && link.getAttribute ? link.getAttribute('data-gaoo-reload') : null;

  if (reload === 'yes' || reload === 'no') {
    return reload === 'yes';
  }

  return gaoo_data.hasOwnProperty('force_reload') && gaoo_data.force_reload == true;
}

// Show the popup text: as a browser popup, or as a notice below the link. Returns true, if a notice is shown.
function gaoo_show_popup(link, text) {
  var notice;

  if (!text) {
    return false;
  }

  if (gaoo_popup_type(link) !== 'notice' || !link || !link.parentNode) {
    alert(text);
    return false;
  }

  notice = link.nextElementSibling;

  if (!notice || !notice.classList.contains('gaoo-notice')) {
    notice = document.createElement('span');
    notice.className = 'gaoo-notice';
    link.parentNode.insertBefore(notice, link.nextSibling);
  }

  // Notices below other links show an old status, so they are removed.
  Array.prototype.forEach.call(document.querySelectorAll('.gaoo-notice'), function (other) {
    if (other !== notice && other.parentNode) {
      other.parentNode.removeChild(other);
    }
  });

  // The notice is visible, screen readers get the text from the live region, which is in the page since loading.
  notice.textContent = text;
  gaoo_announce(text);

  return true;
}

function gaoo_handle_optout(link) {
  var popup,
    notice = false;

  if (document.cookie.indexOf(gaoo_data.disable_string + '=true') > -1) {
    gaoo_remove_cookie();

    popup = gaoo_popup_text(link, 'activate');
    notice = gaoo_show_popup(link, popup);

    window.dispatchEvent(new CustomEvent('gaoptout', {detail: false}));
  }
  else {
    gaoo_create_cookie();
    gaoo_consent_denied();

    popup = gaoo_popup_text(link, 'deactivate');
    notice = gaoo_show_popup(link, popup);

    window.dispatchEvent(new CustomEvent('gaoptout', {detail: true}));
  }

  gaoo_update_links();

  // Without popup text only the text of the link changes, which screen readers do not announce. So the new text of the link is announced.
  // A notice is announced by itself, a browser popup is read anyway.
  if (!popup) {
    gaoo_announce(link.textContent);
  }

  // A notice is visible for a moment before the reload, a browser popup waits for the click anyway.
  if (gaoo_force_reload(link)) {
    setTimeout(function () {
      location.reload();
    }, notice ? 2500 : 0);
  }
}