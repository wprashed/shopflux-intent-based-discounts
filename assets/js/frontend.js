(function () {
  'use strict';

  if (typeof ShopFluxIBDConfig === 'undefined') {
    return;
  }

  var hasShown = false;
  var inactivityTimer = null;
  var i18n = ShopFluxIBDConfig.i18n || {};

  function isOfferAlreadyShown() {
    if (!ShopFluxIBDConfig.oncePerSession) {
      return false;
    }

    try {
      return window.sessionStorage.getItem(ShopFluxIBDConfig.storageKey) === '1';
    } catch (e) {
      return false;
    }
  }

  function markOfferShown() {
    hasShown = true;
    if (!ShopFluxIBDConfig.oncePerSession) {
      return;
    }

    try {
      window.sessionStorage.setItem(ShopFluxIBDConfig.storageKey, '1');
    } catch (e) {
      // Ignore storage failures in restricted browsers.
    }
  }

  function createModal(offer) {
    var overlay = document.createElement('div');
    overlay.className = 'shopflux-ibd-overlay';

    var modal = document.createElement('div');
    modal.className = 'shopflux-ibd-modal';

    var closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'shopflux-ibd-close';
    closeButton.innerHTML = '&times;';
    closeButton.setAttribute('aria-label', i18n.close || 'Close');

    var title = document.createElement('h3');
    title.className = 'shopflux-ibd-title';
    title.textContent = offer.title;

    var message = document.createElement('p');
    message.className = 'shopflux-ibd-message';
    message.textContent = offer.message;

    var code = document.createElement('div');
    code.className = 'shopflux-ibd-coupon';
    code.textContent = offer.couponCode;

    var actionButton = document.createElement('button');
    actionButton.type = 'button';
    actionButton.className = 'shopflux-ibd-apply';
    actionButton.textContent = offer.buttonLabel;

    var fallbackLink = document.createElement('a');
    fallbackLink.href = offer.applyUrl;
    fallbackLink.className = 'shopflux-ibd-fallback-link';
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
      data.append('action', 'shopflux_ibd_apply_offer');
      data.append('nonce', ShopFluxIBDConfig.nonce);
      data.append('couponCode', offer.couponCode);

      fetch(ShopFluxIBDConfig.ajaxUrl, {
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
    data.append('action', 'shopflux_ibd_get_offer');
    data.append('nonce', ShopFluxIBDConfig.nonce);

    fetch(ShopFluxIBDConfig.ajaxUrl, {
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
    if (!ShopFluxIBDConfig.exitIntentEnabled) {
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
    if (!ShopFluxIBDConfig.inactivityEnabled) {
      return;
    }

    if (inactivityTimer) {
      window.clearTimeout(inactivityTimer);
    }

    inactivityTimer = window.setTimeout(function () {
      requestOffer();
    }, Math.max(5, Number(ShopFluxIBDConfig.inactivitySeconds)) * 1000);
  }

  function setupInactivity() {
    if (!ShopFluxIBDConfig.inactivityEnabled) {
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
