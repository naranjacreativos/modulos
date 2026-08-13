(function () {
  'use strict';

  function updateCartIndicators(count) {
    var selectors = [
      '.cart-products-count', '.cart-products-count-btn', '.ajax_cart_quantity',
      '.shopping_cart .ajax_cart_quantity', '.cart-count', '.js-cart-count',
      '[data-cart-count]', '.blockcart .cart-products-count'
    ];
    selectors.forEach(function (selector) {
      document.querySelectorAll(selector).forEach(function (node) {
        var current = (node.textContent || '').trim();
        if (/^\(?\d+\)?$/.test(current)) {
          node.textContent = String(count);
        } else {
          node.textContent = current.replace(/\d+/, String(count));
        }
        if (node.hasAttribute('data-cart-count')) {
          node.setAttribute('data-cart-count', String(count));
        }
      });
    });
  }

  function addSample(button) {
    if (!button || button.dataset.fsAdding === '1') return;

    var card = button.closest('.fabric-sample-card');
    var success = card ? card.querySelector('.js-fs-added-message') : null;
    var error = card ? card.querySelector('.js-fs-error-message') : null;
    var originalText = button.textContent;

    button.dataset.fsAdding = '1';
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.textContent = 'Añadiendo...';
    if (success) success.hidden = true;
    if (error) {
      error.hidden = true;
      error.textContent = '';
    }

    var body = new URLSearchParams();
    body.set('ajax', '1');
    body.set('sample_action', 'add');
    body.set('id_product', button.getAttribute('data-id-product') || '0');
    body.set('quantity', '1');
    body.set('token', window.fabricSamplesToken || '');

    fetch(window.fabricSamplesAjaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    })
      .then(function (response) {
        return response.json().then(function (data) {
          if (!response.ok || !data || !data.success) {
            throw new Error(data && data.message ? data.message : 'No se pudo añadir la muestra.');
          }
          return data;
        });
      })
      .then(function (data) {
        if (success) success.hidden = false;
        updateCartIndicators(parseInt(data.cart_count, 10) || 0);
        var catalog = document.querySelector('.js-fs-catalog');
        var quantity = parseInt(data.quantity, 10) || 1;
        if (catalog && card) {
          var template = catalog.getAttribute('data-in-cart-template') || 'En el carrito: %count%';
          var status = card.querySelector('.js-fs-in-cart-status');
          if (!status) {
            status = document.createElement('p');
            status.className = 'fabric-sample-in-cart js-fs-in-cart-status';
            var actions = card.querySelector('.fabric-sample-actions');
            if (actions) actions.insertBefore(status, actions.firstChild);
          }
          status.textContent = template.replace('%count%', String(quantity));
          var remove = card.querySelector('.js-fs-page-remove');
          if (!remove && data.id_customization) {
            remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'fabric-sample-remove-button js-fs-page-remove';
            remove.setAttribute('data-id-customization', String(data.id_customization));
            remove.textContent = catalog.getAttribute('data-remove-text') || 'Quitar muestra';
            var link = card.querySelector('.fabric-sample-product-link-button');
            var parent = card.querySelector('.fabric-sample-actions');
            if (parent) parent.insertBefore(remove, link || null);
          }
          if (Array.isArray(window.fabricSampleCustomizationIds) && data.id_customization) {
            if (window.fabricSampleCustomizationIds.indexOf(parseInt(data.id_customization, 10)) === -1) {
              window.fabricSampleCustomizationIds.push(parseInt(data.id_customization, 10));
            }
          }
        }
        if (window.prestashop && typeof window.prestashop.emit === 'function') {
          window.prestashop.emit('updateCart', {
            reason: {
              idProduct: parseInt(button.getAttribute('data-id-product'), 10) || 0,
              idProductAttribute: 0,
              linkAction: 'add-to-cart'
            },
            resp: data
          });
        }
      })
      .catch(function (exception) {
        if (error) {
          error.textContent = exception.message || 'No se pudo añadir la muestra.';
          error.hidden = false;
        }
      })
      .finally(function () {
        button.dataset.fsAdding = '0';
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.textContent = originalText;
      });
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('.js-fs-add-sample');
    if (!button) return;
    event.preventDefault();
    addSample(button);
  });
}());

(function () {
  'use strict';

  function asInt(value) {
    var n = parseInt(value, 10);
    return isNaN(n) ? 0 : n;
  }

  function getCustomizationId(element) {
    if (!element) return 0;

    var direct = element.getAttribute('data-id-customization') ||
      element.getAttribute('data-id_customization') ||
      element.dataset && (element.dataset.idCustomization || element.dataset.id_customization);
    if (direct) return asInt(direct);

    var href = element.getAttribute('href') || '';
    if (href) {
      try {
        var url = new URL(href, window.location.href);
        var fromUrl = url.searchParams.get('id_customization');
        if (fromUrl) return asInt(fromUrl);
      } catch (e) {}

      var match = href.match(/[?&]id_customization=(\d+)/i);
      if (match) return asInt(match[1]);
    }

    var row = element.closest(
      '[data-id-customization], .cart-item, .cart-overview .product-line-grid, ' +
      '.cart-product-line, .product-line-grid, .blockcart-product, ' +
      '.cart-preview .product, .shopping-cart .product, li'
    );
    if (row) {
      var marker = row.querySelector('.js-fabric-sample-cart-marker[data-id-customization]');
      if (marker) return asInt(marker.getAttribute('data-id-customization'));
      var rowValue = row.getAttribute('data-id-customization') || row.getAttribute('data-id_customization');
      if (rowValue) return asInt(rowValue);

      // Search every URL/data URL in the dynamically rendered mini-cart row.
      var linked = row.querySelectorAll('[href], [data-url], [data-id-customization], [data-id_customization]');
      for (var i = 0; i < linked.length; i += 1) {
        var candidate = linked[i].getAttribute('data-id-customization') ||
          linked[i].getAttribute('data-id_customization') ||
          linked[i].getAttribute('href') || linked[i].getAttribute('data-url') || '';
        var linkedMatch = String(candidate).match(/[?&](?:id_customization|fs_id_customization)=(\d+)/i);
        if (linkedMatch) return asInt(linkedMatch[1]);
      }
    }

    return 0;
  }

  function isKnownSample(idCustomization) {
    if (!idCustomization) return false;
    var ids = Array.isArray(window.fabricSampleCustomizationIds) ? window.fabricSampleCustomizationIds : [];
    return ids.map(asInt).indexOf(asInt(idCustomization)) !== -1;
  }

  function findDeleteControl(target) {
    var control = target.closest(
      '.remove-from-cart, a[data-link-action="delete-from-cart"], ' +
      'button[data-link-action="delete-from-cart"], .cart-line-product-actions a, ' +
      '.product-line-actions a, .remove-product, ' +
      '[data-action="delete-from-cart"], [data-action="remove-from-cart"], ' +
      '.cart-item .remove, .cart-item .delete, .blockcart .remove, ' +
      '.blockcart .delete, .shopping-cart .remove, .shopping-cart .delete'
    );
    if (control) return control;

    // Custom themes often use an unclassified anchor/button containing only a
    // trash icon. Accept it when its URL clearly represents a cart deletion.
    control = target.closest('a, button');
    if (!control) return null;
    var href = control.getAttribute('href') || control.getAttribute('data-url') || '';
    var action = control.getAttribute('data-link-action') || control.getAttribute('data-action') || '';
    if (/delete|remove/i.test(action) || /(?:delete|remove|update)=/i.test(href)) {
      return control;
    }
    return null;
  }

  function removeSample(control, idCustomization) {
    if (control.dataset.fsRemoving === '1') return;
    control.dataset.fsRemoving = '1';
    control.setAttribute('aria-busy', 'true');

    var body = new URLSearchParams();
    body.set('ajax', '1');
    body.set('sample_action', 'remove');
    body.set('id_customization', String(idCustomization));
    body.set('token', window.fabricSamplesToken || '');

    fetch(window.fabricSamplesAjaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    })
      .then(function (response) {
        return response.json().then(function (data) {
          if (!response.ok || !data || !data.success) {
            throw new Error(data && data.message ? data.message : 'No se pudo eliminar la muestra.');
          }
          return data;
        });
      })
      .then(function (data) {
        window.location.replace(data.cart_url || window.location.href);
      })
      .catch(function (error) {
        control.dataset.fsRemoving = '0';
        control.removeAttribute('aria-busy');
        window.alert(error.message || 'No se pudo eliminar la muestra.');
      });
  }

  document.addEventListener('click', function (event) {
    var control = findDeleteControl(event.target);
    if (!control) return;

    var idCustomization = getCustomizationId(control);
    if (!isKnownSample(idCustomization)) return;

    event.preventDefault();
    event.stopPropagation();
    if (typeof event.stopImmediatePropagation === 'function') {
      event.stopImmediatePropagation();
    }
    removeSample(control, idCustomization);
  }, true);
}());

(function () {
  'use strict';

  function asInt(value) {
    var n = parseInt(value, 10);
    return isNaN(n) ? 0 : n;
  }

  function getSampleId(control) {
    var row = control.closest('[data-id-customization], .cart-item, .product-line-grid, .cart-overview li');
    if (!row) return 0;
    var marker = row.querySelector('.js-fabric-sample-cart-marker[data-id-customization]');
    if (marker) return asInt(marker.getAttribute('data-id-customization'));
    return asInt(row.getAttribute('data-id-customization'));
  }

  function getDirection(control) {
    if (control.matches(
      '.bootstrap-touchspin-up, .js-increase-product-quantity, ' +
      '[data-link-action="increase-product-quantity"], [data-action="increase-product-quantity"], ' +
      '.touchspin-up, .product-quantity-up'
    )) return 'up';

    if (control.matches(
      '.bootstrap-touchspin-down, .js-decrease-product-quantity, ' +
      '[data-link-action="decrease-product-quantity"], [data-action="decrease-product-quantity"], ' +
      '.touchspin-down, .product-quantity-down'
    )) return 'down';

    return '';
  }

  function findQuantityControl(target) {
    return target.closest(
      '.bootstrap-touchspin-up, .bootstrap-touchspin-down, ' +
      '.js-increase-product-quantity, .js-decrease-product-quantity, ' +
      '[data-link-action="increase-product-quantity"], [data-link-action="decrease-product-quantity"], ' +
      '[data-action="increase-product-quantity"], [data-action="decrease-product-quantity"], ' +
      '.touchspin-up, .touchspin-down, .product-quantity-up, .product-quantity-down'
    );
  }

  function updateQuantity(control, idCustomization, direction) {
    if (control.dataset.fsUpdating === '1') return;
    control.dataset.fsUpdating = '1';
    control.setAttribute('aria-busy', 'true');

    var body = new URLSearchParams();
    body.set('ajax', '1');
    body.set('sample_action', 'update_quantity');
    body.set('id_customization', String(idCustomization));
    body.set('direction', direction);
    body.set('token', window.fabricSamplesToken || '');

    fetch(window.fabricSamplesAjaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    })
      .then(function (response) {
        return response.json().then(function (data) {
          if (!response.ok || !data || !data.success) {
            throw new Error(data && data.message ? data.message : 'No se pudo actualizar la cantidad.');
          }
          return data;
        });
      })
      .then(function (data) {
        window.location.replace(data.cart_url || window.location.href);
      })
      .catch(function (error) {
        control.dataset.fsUpdating = '0';
        control.removeAttribute('aria-busy');
        window.alert(error.message || 'No se pudo actualizar la cantidad.');
      });
  }

  document.addEventListener('click', function (event) {
    var control = findQuantityControl(event.target);
    if (!control) return;

    var direction = getDirection(control);
    if (!direction) return;

    var idCustomization = getSampleId(control);
    if (!idCustomization) return;

    var ids = Array.isArray(window.fabricSampleCustomizationIds) ? window.fabricSampleCustomizationIds : [];
    if (ids.map(asInt).indexOf(idCustomization) === -1) return;

    event.preventDefault();
    event.stopPropagation();
    if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();

    updateQuantity(control, idCustomization, direction);
  }, true);
}());

(function () {
  'use strict';

  function request(url, options) {
    return fetch(url, options).then(function (response) {
      return response.json().then(function (data) {
        if (!response.ok || !data || !data.success) {
          throw new Error(data && data.message ? data.message : 'No se pudo completar la operación.');
        }
        return data;
      });
    });
  }

  function updateCatalogCartIndicators(count) {
    [
      '.cart-products-count', '.cart-products-count-btn', '.ajax_cart_quantity',
      '.shopping_cart .ajax_cart_quantity', '.cart-count', '.js-cart-count',
      '[data-cart-count]', '.blockcart .cart-products-count'
    ].forEach(function (selector) {
      document.querySelectorAll(selector).forEach(function (node) {
        var current = (node.textContent || '').trim();
        if (/^\(?\d+\)?$/.test(current)) {
          node.textContent = String(count);
        } else if (/\d+/.test(current)) {
          node.textContent = current.replace(/\d+/, String(count));
        }
        if (node.hasAttribute('data-cart-count')) {
          node.setAttribute('data-cart-count', String(count));
        }
      });
    });
  }

  function removeFromCatalog(button) {
    if (!button || button.dataset.fsRemoving === '1') return;
    var catalog = button.closest('.js-fs-catalog');
    var card = button.closest('.fabric-sample-card');
    var idCustomization = parseInt(button.getAttribute('data-id-customization'), 10) || 0;
    if (!idCustomization) return;

    button.dataset.fsRemoving = '1';
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    var body = new URLSearchParams();
    body.set('ajax', '1');
    body.set('sample_action', 'remove');
    body.set('id_customization', String(idCustomization));
    body.set('token', window.fabricSamplesToken || '');

    request(window.fabricSamplesAjaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    }).then(function (data) {
      if (card) {
        var status = card.querySelector('.js-fs-in-cart-status');
        var success = card.querySelector('.js-fs-added-message');
        var errorMessage = card.querySelector('.js-fs-error-message');
        if (status) status.remove();
        if (success) success.hidden = true;
        if (errorMessage) {
          errorMessage.hidden = true;
          errorMessage.textContent = '';
        }
        button.remove();
      }

      var addText = catalog ? (catalog.getAttribute('data-add-text') || 'Añadir muestra al carrito') : 'Añadir muestra al carrito';
      if (catalog) {
        catalog.querySelectorAll('.fabric-sample-card').forEach(function (sampleCard) {
          var addButton = sampleCard.querySelector('.js-fs-add-sample');
          var stillInCart = sampleCard.querySelector('.js-fs-in-cart-status, .js-fs-page-remove');
          if (addButton && !stillInCart) {
            addButton.disabled = false;
            addButton.removeAttribute('aria-disabled');
            addButton.removeAttribute('aria-busy');
            addButton.dataset.fsAdding = '0';
            addButton.textContent = addText;
          }
        });
      }

      if (Array.isArray(window.fabricSampleCustomizationIds)) {
        window.fabricSampleCustomizationIds = window.fabricSampleCustomizationIds.filter(function (id) {
          return parseInt(id, 10) !== idCustomization;
        });
      }

      updateCatalogCartIndicators(parseInt(data.cart_count, 10) || 0);
      if (window.prestashop && typeof window.prestashop.emit === 'function') {
        window.prestashop.emit('updateCart', {
          reason: {
            idProduct: card ? (parseInt(card.getAttribute('data-id-product'), 10) || 0) : 0,
            idProductAttribute: 0,
            linkAction: 'delete-from-cart'
          },
          resp: data
        });
      }
    }).catch(function (error) {
      window.alert(error.message || 'No se pudo quitar la muestra.');
      button.disabled = false;
      button.removeAttribute('aria-busy');
      button.dataset.fsRemoving = '0';
    });
  }

  function loadCatalog(catalog, url, updateHistory) {
    if (!catalog || catalog.getAttribute('data-ajax-enabled') !== '1') {
      window.location.href = url;
      return;
    }

    var requestUrl = new URL(url, window.location.href);
    requestUrl.searchParams.set('ajax_catalog', '1');
    catalog.classList.add('is-loading');
    request(requestUrl.toString(), {
      credentials: 'same-origin',
      headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).then(function (data) {
      var target = catalog.querySelector('.js-fs-catalog-results');
      if (target) target.innerHTML = data.html || '';
      requestUrl.searchParams.delete('ajax_catalog');
      if (updateHistory !== false) window.history.pushState({}, '', requestUrl.toString());
      window.scrollTo({top: Math.max(0, catalog.offsetTop - 20), behavior: 'smooth'});
    }).catch(function () {
      requestUrl.searchParams.delete('ajax_catalog');
      window.location.href = requestUrl.toString();
    }).finally(function () {
      catalog.classList.remove('is-loading');
    });
  }

  document.addEventListener('click', function (event) {
    var removeButton = event.target.closest('.js-fs-page-remove');
    if (removeButton) {
      event.preventDefault();
      event.stopPropagation();
      removeFromCatalog(removeButton);
      return;
    }


    var pageLink = event.target.closest('.js-fs-page-link');
    if (pageLink) {
      var catalog = pageLink.closest('.js-fs-catalog');
      if (catalog && catalog.getAttribute('data-ajax-enabled') === '1') {
        event.preventDefault();
        loadCatalog(catalog, pageLink.href, true);
      }
    }
  });

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('.js-fs-filter-form');
    if (!form) return;
    var catalog = form.closest('.js-fs-catalog');
    if (!catalog || catalog.getAttribute('data-ajax-enabled') !== '1') return;
    event.preventDefault();
    var url = new URL(form.action, window.location.href);
    new FormData(form).forEach(function (value, key) { url.searchParams.set(key, value); });
    url.searchParams.delete('page');
    loadCatalog(catalog, url.toString(), true);
  });

  window.addEventListener('popstate', function () {
    var catalog = document.querySelector('.js-fs-catalog');
    if (catalog && catalog.getAttribute('data-ajax-enabled') === '1') {
      loadCatalog(catalog, window.location.href, false);
    }
  });
}());
