(function () {
  'use strict';

  if (typeof IBDConfig === 'undefined') {
    return;
  }

  var hasShown = false;
  var inactivityTimer = null;
  var i18n = IBDConfig.i18n || {};

  function isOfferAlreadyShown() {
    if (!IBDConfig.oncePerSession) {
      return false;
    }

    try {
      return window.sessionStorage.getItem(IBDConfig.storageKey) === '1';
    } catch (e) {
      return false;
    }
  }

  function markOfferShown() {
    hasShown = true;
    if (!IBDConfig.oncePerSession) {
      return;
    }

    try {
      window.sessionStorage.setItem(IBDConfig.storageKey, '1');
    } catch (e) {
      // Ignore storage failures in restricted browsers.
    }
  }

  function createModal(offer) {
    var overlay = document.createElement('div');
    overlay.className = 'ibd-overlay';

    var modal = document.createElement('div');
    modal.className = 'ibd-modal';

    var closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'ibd-close';
    closeButton.innerHTML = '&times;';
    closeButton.setAttribute('aria-label', i18n.close || 'Close');

    var title = document.createElement('h3');
    title.className = 'ibd-title';
    title.textContent = offer.title;

    var message = document.createElement('p');
    message.className = 'ibd-message';
    message.textContent = offer.message;

    var code = document.createElement('div');
    code.className = 'ibd-coupon';
    code.textContent = offer.couponCode;

    var actionButton = document.createElement('button');
    actionButton.type = 'button';
    actionButton.className = 'ibd-apply';
    actionButton.textContent = offer.buttonLabel;

    var fallbackLink = document.createElement('a');
    fallbackLink.href = offer.applyUrl;
    fallbackLink.className = 'ibd-fallback-link';
    fallbackLink.textContent = i18n.fallback || 'Go to cart manually';

    closeButton.addEventListener('click', function () {
      document.body.removeChild(overlay);
    });

    overlay.addEventListener('click', function (event) {
      if (event.target === overlay) {
        document.body.removeChild(overlay);
      }
    });

    actionButton.addEventListener('click', function () {
      actionButton.disabled = true;
      actionButton.textContent = i18n.applying || 'Applying...';

      var data = new URLSearchParams();
      data.append('action', 'ibd_apply_offer');
      data.append('nonce', IBDConfig.nonce);
      data.append('couponCode', offer.couponCode);

      fetch(IBDConfig.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: data.toString()
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (json) {
          if (json && json.success && json.data && json.data.redirectUrl) {
            window.location.href = json.data.redirectUrl;
            return;
          }

          window.location.href = offer.applyUrl;
        })
        .catch(function () {
          window.location.href = offer.applyUrl;
        });
    });

    modal.appendChild(closeButton);
    modal.appendChild(title);
    modal.appendChild(message);
    modal.appendChild(code);
    modal.appendChild(actionButton);
    modal.appendChild(fallbackLink);
    overlay.appendChild(modal);

    document.body.appendChild(overlay);
  }

  function requestOffer() {
    if (hasShown || isOfferAlreadyShown()) {
      return;
    }

    markOfferShown();

    var data = new URLSearchParams();
    data.append('action', 'ibd_get_offer');
    data.append('nonce', IBDConfig.nonce);

    fetch(IBDConfig.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: data.toString()
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (json) {
        if (json && json.success && json.data) {
          createModal(json.data);
        }
      })
      .catch(function () {
        // No-op: fail silently for browsing continuity.
      });
  }

  function setupExitIntent() {
    if (!IBDConfig.exitIntentEnabled) {
      return;
    }

    document.addEventListener('mouseout', function (event) {
      if (event.relatedTarget || event.toElement) {
        return;
      }

      if (event.clientY <= 0) {
        requestOffer();
      }
    });
  }

  function resetInactivityTimer() {
    if (!IBDConfig.inactivityEnabled) {
      return;
    }

    if (inactivityTimer) {
      window.clearTimeout(inactivityTimer);
    }

    inactivityTimer = window.setTimeout(function () {
      requestOffer();
    }, Math.max(5, Number(IBDConfig.inactivitySeconds)) * 1000);
  }

  function setupInactivity() {
    if (!IBDConfig.inactivityEnabled) {
      return;
    }

    ['mousemove', 'keydown', 'scroll', 'click', 'touchstart'].forEach(function (name) {
      document.addEventListener(name, resetInactivityTimer, { passive: true });
    });

    resetInactivityTimer();
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (isOfferAlreadyShown()) {
      hasShown = true;
      return;
    }

    setupExitIntent();
    setupInactivity();
  });
})();
