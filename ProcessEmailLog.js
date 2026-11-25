/**
 * Email preview (template preview and log detail).
 *
 * Markup: an element with data-email-preview and
 * - data-state: JSON with the current settings, e.g. {"format":"html","size":"desktop","language":1027}
 * - data-sizes: JSON with the box width per device
 * - data-has-text: "0" if the template has no text version (shows #email-preview-text-hint)
 * Controls: <a data-preview="format|size|language" data-value="…"> inside an <li> that gets uk-active.
 *
 * The settings are kept in the page URL, in the iframe URL and in links with .email-preview-template.
 */
(function() {
	'use strict';

	// the preview is at least as high as the space left in the window (short emails don't
	// make the page scroll), longer emails make it taller, so the page scrolls and not the iframe
	var minHeight = 320;

	function parse(value, fallback) {
		try {
			return JSON.parse(value) || fallback;
		} catch (e) {
			return fallback;
		}
	}

	function init(root) {
		var iframe = root.querySelector('#email-preview-iframe');
		var frame = root.querySelector('#email-preview-frame');
		var hint = root.querySelector('#email-preview-text-hint');
		var hasTextTemplate = root.getAttribute('data-has-text') !== '0';
		var sizes = parse(root.getAttribute('data-sizes'), {});
		var state = parse(root.getAttribute('data-state'), {});

		function contentHeight() {
			try {
				var doc = iframe.contentDocument;
				if (!doc || !doc.documentElement) return 0;
				// measure without the current height stretching the document
				iframe.style.height = '0px';
				return Math.max(doc.documentElement.scrollHeight, doc.body ? doc.body.scrollHeight : 0);
			} catch (e) {
				return 0;
			}
		}

		// space the page needs below the preview (paddings, footer); measured while the preview
		// is taller than the window, otherwise the sticky footer of the admin theme would distort it
		function spaceBelow() {
			iframe.style.height = (window.innerHeight * 2) + 'px';
			var frameBottom = frame.getBoundingClientRect().bottom + window.scrollY;
			return Math.max(0, document.documentElement.scrollHeight - frameBottom);
		}

		function fitIframe() {
			if (!iframe || !frame) return;
			var top = frame.getBoundingClientRect().top + window.scrollY;
			var available = Math.floor(window.innerHeight - top - spaceBelow());
			iframe.style.height = Math.max(minHeight, available, contentHeight()) + 'px';
		}

		// write the settings into a URL; a language of 0 means "none"
		function applyParams(url) {
			Object.keys(state).forEach(function(key) {
				if (state[key] === 0 || state[key] === '') url.searchParams.delete(key);
				else url.searchParams.set(key, String(state[key]));
			});
			return url;
		}

		function syncUrls() {
			try {
				history.replaceState(null, '', applyParams(new URL(window.location.href)).toString());
			} catch (e) {}
			root.querySelectorAll('.email-preview-template').forEach(function(link) {
				link.href = applyParams(new URL(link.href, window.location.href)).toString();
			});
		}

		function reloadIframe() {
			if (!iframe) return;
			var url = applyParams(new URL(iframe.src, window.location.href));
			url.searchParams.delete('size');
			iframe.src = url.toString();
		}

		function markActive(control, value) {
			root.querySelectorAll('[data-preview="' + control + '"]').forEach(function(el) {
				el.parentElement.classList.toggle('uk-active', el.getAttribute('data-value') === value);
			});
		}

		function setState(control, value) {
			if (!(control in state)) return;
			if (control === 'language') value = parseInt(value, 10) || 0;
			if (state[control] === value) return;
			state[control] = value;
			markActive(control, String(value));

			if (control === 'size') {
				if (frame && sizes[value]) {
					frame.style.width = sizes[value];
					// a narrower email gets taller, measure after the width transition
					setTimeout(fitIframe, 250);
				}
			} else {
				if (control === 'format' && hint) {
					hint.hidden = hasTextTemplate || state.format !== 'text';
					// the hint moves the preview down
					fitIframe();
				}
				reloadIframe();
			}
			syncUrls();
		}

		root.addEventListener('click', function(e) {
			var el = e.target.closest('[data-preview]');
			if (!el || !root.contains(el)) return;
			e.preventDefault();
			setState(el.getAttribute('data-preview'), el.getAttribute('data-value'));
		});

		if (iframe) {
			iframe.addEventListener('load', fitIframe);
			window.addEventListener('resize', fitIframe);
			// in case the iframe finished loading before this script ran
			fitIframe();
		}

		syncUrls();
	}

	// forms with data-confirm ask with the ProcessWire dialog before they are sent
	document.addEventListener('submit', function(e) {
		var form = e.target;
		if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
		if (form.getAttribute('data-confirmed') === '1') {
			form.removeAttribute('data-confirmed');
			return;
		}
		e.preventDefault();
		var submitter = e.submitter || null;
		var send = function() {
			form.setAttribute('data-confirmed', '1');
			// requestSubmit() keeps the name/value of the clicked button
			if (typeof form.requestSubmit === 'function') {
				form.requestSubmit(submitter);
				return;
			}
			if (submitter && submitter.name) {
				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = submitter.name;
				input.value = submitter.value;
				form.appendChild(input);
			}
			form.submit();
		};
		var message = form.getAttribute('data-confirm');
		// ProcessWire.confirm() calls the cancel callback without checking it
		if (window.ProcessWire && typeof ProcessWire.confirm === 'function') ProcessWire.confirm(message, send, function() {});
		else if (window.confirm(message)) send();
	});

	function boot() {
		document.querySelectorAll('[data-email-preview]').forEach(init);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
