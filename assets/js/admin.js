(function () {
	'use strict';

	var addButton = document.getElementById('utm-phone-switcher-add-rule');
	var tableBody = document.getElementById('utm-phone-switcher-rules');
	var template = document.getElementById('utm-phone-switcher-row-template');
	var browserItems = Array.prototype.slice.call(document.querySelectorAll('.utm-phone-switcher-browser-item'));
	var consentMode = document.getElementById('utm-phone-switcher-consent-mode');
	var consentOptionRows = Array.prototype.slice.call(document.querySelectorAll('[data-utm-consent-option]'));

	function updateConsentOptions() {
		if (!consentMode) {
			return;
		}
		consentOptionRows.forEach(function (row) {
			var modes = (row.getAttribute('data-utm-consent-option') || '').split(',');
			row.hidden = modes.indexOf(consentMode.value) === -1;
		});
	}

	if (consentMode) {
		consentMode.addEventListener('change', updateConsentOptions);
		updateConsentOptions();
	}

	function closeBrowserTooltips(except) {
		browserItems.forEach(function (item) {
			if (item === except) {
				return;
			}
			var button = item.querySelector('.utm-phone-switcher-browser-toggle');
			var tooltip = item.querySelector('.utm-phone-switcher-browser-tooltip');
			if (button && tooltip) {
				button.setAttribute('aria-expanded', 'false');
				tooltip.hidden = true;
				delete item.dataset.openedByClick;
			}
		});
	}

	function openBrowserTooltip(item) {
		var button = item.querySelector('.utm-phone-switcher-browser-toggle');
		var tooltip = item.querySelector('.utm-phone-switcher-browser-tooltip');
		if (!button || !tooltip) {
			return;
		}
		closeBrowserTooltips(item);
		button.setAttribute('aria-expanded', 'true');
		tooltip.hidden = false;
	}

	function closeBrowserTooltip(item) {
		var button = item.querySelector('.utm-phone-switcher-browser-toggle');
		var tooltip = item.querySelector('.utm-phone-switcher-browser-tooltip');
		if (button && tooltip) {
			button.setAttribute('aria-expanded', 'false');
			tooltip.hidden = true;
			delete item.dataset.openedByClick;
		}
	}

	browserItems.forEach(function (item) {
		var button = item.querySelector('.utm-phone-switcher-browser-toggle');
		var hoverTimer = null;
		if (!button) {
			return;
		}

		item.addEventListener('pointerenter', function (event) {
			if (event.pointerType !== 'mouse') {
				return;
			}
			window.clearTimeout(hoverTimer);
			hoverTimer = window.setTimeout(function () {
				openBrowserTooltip(item);
			}, 300);
		});

		item.addEventListener('pointerleave', function (event) {
			if (event.pointerType !== 'mouse' || item.dataset.openedByClick === 'true' || document.activeElement === button) {
				return;
			}
			window.clearTimeout(hoverTimer);
			hoverTimer = window.setTimeout(function () {
				closeBrowserTooltip(item);
			}, 120);
		});

		button.addEventListener('click', function () {
			window.clearTimeout(hoverTimer);
			if (button.getAttribute('aria-expanded') === 'true' && item.dataset.openedByClick === 'true') {
				closeBrowserTooltip(item);
				return;
			}
			openBrowserTooltip(item);
			item.dataset.openedByClick = 'true';
		});

		button.addEventListener('focus', function () {
			openBrowserTooltip(item);
		});

		button.addEventListener('blur', function (event) {
			if (!item.contains(event.relatedTarget) && item.dataset.openedByClick !== 'true') {
				closeBrowserTooltip(item);
			}
		});
	});

	document.addEventListener('click', function (event) {
		if (!event.target.closest('.utm-phone-switcher-browser-item')) {
			closeBrowserTooltips();
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Escape') {
			return;
		}
		var openItem = browserItems.find(function (item) {
			var button = item.querySelector('.utm-phone-switcher-browser-toggle');
			return button && button.getAttribute('aria-expanded') === 'true';
		});
		if (openItem) {
			closeBrowserTooltips();
		}
	});

	if (!addButton || !tableBody || !template) {
		return;
	}

	function makeRuleId() {
		if (window.crypto && typeof window.crypto.randomUUID === 'function') {
			return window.crypto.randomUUID().replace(/[^a-zA-Z0-9]/g, '').slice(0, 12).toLowerCase();
		}
		return ('rule' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7)).replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
	}

	addButton.addEventListener('click', function () {
		var index = parseInt(tableBody.getAttribute('data-next-index') || '0', 10);
		var html = template.innerHTML;
		html = html.replace(/__INDEX__/g, String(index));
		html = html.replace(/__RULE_ID__/g, makeRuleId());
		tableBody.insertAdjacentHTML('beforeend', html);
		tableBody.setAttribute('data-next-index', String(index + 1));
	});

	tableBody.addEventListener('click', function (event) {
		var target = event.target instanceof Element ? event.target : event.target.parentElement;
		var removeButton = target ? target.closest('.utm-phone-switcher-remove-rule') : null;
		if (!removeButton) {
			return;
		}
		var row = removeButton.closest('tr');
		if (row) {
			row.remove();
		}
	});
})();
