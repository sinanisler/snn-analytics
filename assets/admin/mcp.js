/**
 * MCP screen: the Copy buttons and the confirmation before a token is revoked.
 * Nothing else. The page itself is plain PHP with admin-post forms.
 */
(function () {
	'use strict';

	/** Selects the field and copies its value. Falls back to execCommand where the Clipboard API is missing. */
	function copyField(field, button) {
		var label = button.textContent;
		var done = function () {
			button.textContent = 'Copied';
			window.setTimeout(function () { button.textContent = label; }, 1500);
		};
		var fallback = function () {
			if (document.execCommand && document.execCommand('copy')) {
				done();
			}
		};

		field.focus();
		field.select();

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(field.value).then(done, fallback);
		} else {
			fallback();
		}
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-copy]') : null;
		if (!button) {
			return;
		}

		var field = document.getElementById(button.getAttribute('data-copy'));
		if (field) {
			copyField(field, button);
		}
	});

	document.addEventListener('submit', function (event) {
		var message = event.target.getAttribute('data-confirm');
		if (message && !window.confirm(message)) {
			event.preventDefault();
		}
	});

	/*
	 * The top bar scrolls sideways when the window is narrow, and this screen's tab comes last. Scroll the bar
	 * so the current tab is in view, without moving the page.
	 */
	var current = document.querySelector('.snn-a-tab.is-current');
	var bar = current ? current.parentNode : null;
	if (bar && bar.scrollWidth > bar.clientWidth) {
		var tabLeft = current.getBoundingClientRect().left - bar.getBoundingClientRect().left + bar.scrollLeft;
		bar.scrollLeft = tabLeft - (bar.clientWidth - current.offsetWidth) / 2;
	}
}());
