/**
 * LIFF front-end.
 *
 * The ID token is fetched from the LIFF SDK on every request rather than
 * cached, because the server verifies it with LINE and a stale one is
 * rejected. Nothing here trusts the profile the SDK reports locally; the
 * server decides who the visitor is.
 */
(function () {
	'use strict';

	var config = window.mofolineLiff || {};
	var strings = config.strings || {};

	if (!config.liffId || typeof liff === 'undefined') {
		return;
	}

	/**
	 * POST JSON to a plugin REST endpoint.
	 *
	 * @param {string} url  Endpoint.
	 * @param {Object} body Payload.
	 * @return {Promise<Object>}
	 */
	function send(url, body) {
		return fetch(url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(body)
		}).then(function (response) {
			return response.json().then(function (data) {
				if (!response.ok) {
					throw new Error((data && data.message) || strings.failed);
				}

				return data;
			});
		});
	}

	/**
	 * Show a status line inside a widget.
	 *
	 * @param {Element} scope   Widget root.
	 * @param {string}  message Text.
	 */
	function status(scope, message) {
		var element = scope.querySelector('[data-moksa-chat-status], .moksa-liff__status');

		if (element) {
			element.textContent = message;
			element.hidden = false;
		}
	}

	function initProfile(root, idToken) {
		return send(config.sessionUrl, { id_token: idToken }).then(function (profile) {
			var name = profile.display_name || '';
			var greeting = (strings.greeting || 'Hello, %s').replace('%s', name);

			root.innerHTML = '';

			if (profile.picture_url) {
				var image = document.createElement('img');
				image.className = 'moksa-liff__avatar';
				image.src = profile.picture_url;
				image.alt = '';
				image.width = 64;
				image.height = 64;
				root.appendChild(image);
			}

			var heading = document.createElement('p');
			heading.className = 'moksa-liff__greeting';
			heading.textContent = greeting;
			root.appendChild(heading);
		});
	}

	function initChat(root, idToken) {
		var form = root.querySelector('[data-moksa-chat-form]');

		if (!form) {
			return Promise.resolve();
		}

		return send(config.sessionUrl, { id_token: idToken }).then(function () {
			status(root, '');
			root.querySelector('[data-moksa-chat-status]').hidden = true;
			form.hidden = false;

			form.addEventListener('submit', function (event) {
				event.preventDefault();

				var field = form.querySelector('textarea');
				var button = form.querySelector('button');
				var text = field.value.trim();

				if (!text) {
					return;
				}

				button.disabled = true;
				status(root, strings.sending);

				// A fresh token per send: LIFF tokens are short lived and the
				// visitor may have had the page open for a while.
				liff.getIDToken();

				send(config.messageUrl, { id_token: liff.getIDToken(), message: text }).then(function () {
					field.value = '';
					status(root, strings.sent);
				}).catch(function (error) {
					status(root, error.message || strings.failed);
				}).then(function () {
					button.disabled = false;
				});
			});
		});
	}

	liff.init({ liffId: config.liffId }).then(function () {
		if (!liff.isLoggedIn()) {
			liff.login();
			return;
		}

		var idToken = liff.getIDToken();

		if (!idToken) {
			document.querySelectorAll('.moksa-liff').forEach(function (root) {
				status(root, strings.notInLine);
			});

			return;
		}

		document.querySelectorAll('[data-moksa-liff-profile]').forEach(function (root) {
			initProfile(root, idToken).catch(function (error) {
				status(root, error.message || strings.failed);
			});
		});

		document.querySelectorAll('[data-moksa-liff-chat]').forEach(function (root) {
			initChat(root, idToken).catch(function (error) {
				status(root, error.message || strings.failed);
			});
		});
	}).catch(function (error) {
		document.querySelectorAll('.moksa-liff').forEach(function (root) {
			status(root, error.message || strings.notInLine);
		});
	});
}());
