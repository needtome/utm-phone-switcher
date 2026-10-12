(function () {
	'use strict';

	var config = window.UTMPhoneSwitcher;
	if (!config || !Array.isArray(config.rules) || !config.rules.length) {
		return;
	}

	var cookieName = String(config.cookie_name || 'utm_phone_switcher');
	var legacyCookieName = String(config.legacy_cookie_name || 'pdxphone_for_utm');
	var rulesById = new Map();
	config.rules.forEach(function (rule) {
		if (rule && rule.id) {
			rulesById.set(String(rule.id), rule);
		}
	});

	function readSavedRuleIds() {
		var prefix = encodeURIComponent(cookieName) + '=';
		var part = document.cookie.split(';').map(function (item) {
			return item.trim();
		}).find(function (item) {
			return item.indexOf(prefix) === 0;
		});

		if (!part) {
			return [];
		}

		try {
			var ids = JSON.parse(decodeURIComponent(part.slice(prefix.length)));
			return Array.isArray(ids) ? ids.map(String).filter(function (id) {
				return rulesById.has(id);
			}) : [];
		} catch (error) {
			return [];
		}
	}

	function readLegacyRuleIds() {
		var prefix = encodeURIComponent(legacyCookieName) + '=';
		var part = document.cookie.split(';').map(function (item) {
			return item.trim();
		}).find(function (item) {
			return item.indexOf(prefix) === 0;
		});

		if (!part) {
			return [];
		}

		try {
			var legacyMatches = JSON.parse(decodeURIComponent(part.slice(prefix.length)));
			if (!Array.isArray(legacyMatches)) {
				return [];
			}

			var matchedIds = [];
			legacyMatches.forEach(function (legacyMatch) {
				if (!legacyMatch || typeof legacyMatch.sector !== 'string' || typeof legacyMatch.phone_number !== 'string') {
					return;
				}

				config.rules.forEach(function (rule) {
					if (rule && String(rule.selector) === legacyMatch.sector && String(rule.phone) === legacyMatch.phone_number) {
						matchedIds.push(String(rule.id));
					}
				});
			});

			return Array.from(new Set(matchedIds));
		} catch (error) {
			return [];
		}
	}

	function saveRuleIds(ids) {
		var uniqueIds = Array.from(new Set(ids.map(String))).filter(function (id) {
			return rulesById.has(id);
		}).slice(0, 100);
		var cookie = encodeURIComponent(cookieName) + '=' + encodeURIComponent(JSON.stringify(uniqueIds)) + '; path=/; SameSite=Lax';
		var days = Math.max(0, Math.min(365, parseInt(config.duration_days, 10) || 0));
		if (days > 0) {
			var expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000);
			cookie += '; expires=' + expires.toUTCString();
		}
		if (window.location.protocol === 'https:') {
			cookie += '; Secure';
		}
		document.cookie = cookie;
	}

	function getCurrentMatches() {
		var params = new URLSearchParams(window.location.search);
		return config.rules.filter(function (rule) {
			if (!rule || !rule.query_parameter) {
				return false;
			}

			var parameterPattern = String(rule.query_parameter);
			var expression = new RegExp('^' + parameterPattern.split('*').map(function (part) {
				return part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
			}).join('.*') + '$');
			var requiredValue = String(rule.query_value || '');
			var found = false;

			params.forEach(function (value, name) {
				if (found || !expression.test(name)) {
					return;
				}
				if (requiredValue === '' || value === requiredValue) {
					found = true;
				}
			});

			return found;
		});
	}

	function getActiveRules() {
		var matches = getCurrentMatches();
		if (matches.length) {
			saveRuleIds(matches.map(function (rule) { return rule.id; }));
			return matches;
		}

		var savedIds = readSavedRuleIds();
		if (!savedIds.length) {
			savedIds = readLegacyRuleIds();
		}
		var savedSet = new Set(savedIds);
		return config.rules.filter(function (rule) {
			return rule && savedSet.has(String(rule.id));
		});
	}

	function getTargets(root, selector) {
		var targets = [];
		try {
			if (root.nodeType === 1 && root.matches(selector)) {
				targets.push(root);
			}
			if (root.querySelectorAll) {
				targets = targets.concat(Array.prototype.slice.call(root.querySelectorAll(selector)));
			}
		} catch (error) {
			return [];
		}
		return targets;
	}

	function replacePhoneText(element, phone) {
		var walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, {
			acceptNode: function (node) {
				if (!node.parentElement || node.parentElement.closest('script, style, noscript, svg')) {
					return NodeFilter.FILTER_REJECT;
				}
				return NodeFilter.FILTER_ACCEPT;
			}
		});
		var first = walker.nextNode();
		if (!first) {
			element.appendChild(document.createTextNode(phone));
			return;
		}
		first.nodeValue = phone;
		while (walker.nextNode()) {
			walker.currentNode.nodeValue = '';
		}
	}

	function makeTelValue(phone) {
		var value = String(phone).replace(/[^0-9+]/g, '');
		if (value.charAt(0) === '+') {
			return 'tel:+' + value.slice(1).replace(/\+/g, '');
		}
		return 'tel:' + value.replace(/\+/g, '');
	}

	var activeRules = getActiveRules();
	if (!activeRules.length) {
		return;
	}

	function applyRules(root) {
		var claimed = new WeakSet();
		activeRules.forEach(function (rule) {
			getTargets(root, rule.selector).forEach(function (element) {
				if (claimed.has(element)) {
					return;
				}
				claimed.add(element);
				if (element.textContent.trim() !== String(rule.phone).trim()) {
					replacePhoneText(element, String(rule.phone));
				}
				if (element instanceof HTMLAnchorElement && /^tel:/i.test(element.getAttribute('href') || '')) {
					var telValue = makeTelValue(rule.phone);
					if (element.getAttribute('href') !== telValue) {
						element.setAttribute('href', telValue);
					}
				}
			});
		});
	}

	function start() {
		applyRules(document);
		if (!window.MutationObserver || !document.documentElement) {
			return;
		}
		var observer = new MutationObserver(function (records) {
			records.forEach(function (record) {
				if (record.type === 'characterData' && record.target.parentElement) {
					activeRules.forEach(function (rule) {
						try {
							var textTarget = record.target.parentElement.closest(rule.selector);
							if (textTarget) {
								applyRules(textTarget);
							}
						} catch (error) {
							// An invalid selector is ignored for this rule.
						}
					});
				}
				if (record.type === 'attributes' && record.target.nodeType === 1) {
					applyRules(record.target);
				}
				Array.prototype.forEach.call(record.addedNodes, function (node) {
					if (node.nodeType === 1) {
						applyRules(node);
						activeRules.forEach(function (rule) {
							try {
								var ancestorTarget = node.parentElement && node.parentElement.closest(rule.selector);
								if (ancestorTarget) {
									applyRules(ancestorTarget);
								}
							} catch (error) {
								// An invalid selector is ignored for this rule.
							}
						});
					} else if (node.nodeType === 3 && record.target.nodeType === 1) {
						activeRules.forEach(function (rule) {
							try {
								var textTarget = record.target.matches(rule.selector) ? record.target : record.target.closest(rule.selector);
								if (textTarget) {
									applyRules(textTarget);
								}
							} catch (error) {
								// An invalid selector is ignored for this rule.
							}
						});
					}
				});
			});
		});
		observer.observe(document.documentElement, {
			attributes: true,
			attributeFilter: ['href'],
			characterData: true,
			childList: true,
			subtree: true
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, { once: true });
	} else {
		start();
	}
	window.addEventListener('pageshow', function () { applyRules(document); });
})();
