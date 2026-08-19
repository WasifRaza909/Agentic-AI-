/**
 * WC AI Assistant — storefront chat bubble.
 *
 * Renders into a shadow root so the theme's CSS cannot reach in and the widget's
 * CSS cannot leak out onto the store.
 */
(function () {
	'use strict';

	var cfg = window.WCAI_CONFIG;
	if (!cfg || !cfg.endpoint) {
		return;
	}

	var STORAGE_KEY = 'wcai_history';
	var MAX_TURNS = 12;

	var history = [];
	var busy = false;

	try {
		var saved = sessionStorage.getItem(STORAGE_KEY);
		if (saved) {
			history = JSON.parse(saved) || [];
		}
	} catch (e) {
		history = [];
	}

	function persist() {
		try {
			sessionStorage.setItem(STORAGE_KEY, JSON.stringify(history.slice(-MAX_TURNS)));
		} catch (e) {
			/* private browsing — carry on without persistence */
		}
	}

	/* ---------------------------------------------------------------- icons */

	/**
	 * Line icons on a 24x24 grid, 1.75 stroke. Inheriting currentColor keeps them
	 * in step with whatever the surrounding text colour is.
	 */
	var ICONS = {
		/**
		 * A squared-off speech bubble holding two sparkles — the sparkle is the
		 * shorthand people already read as "AI", and the bubble silhouette is
		 * deliberately unlike the round tailed bubble WhatsApp uses.
		 */
		chat:
			'<path d="M6.5 3.75h11a3.75 3.75 0 0 1 3.75 3.75v5a3.75 3.75 0 0 1-3.75 3.75h-5.35L8.4 19.4v-3.15H6.5A3.75 3.75 0 0 1 2.75 12.5v-5A3.75 3.75 0 0 1 6.5 3.75z"/>' +
			'<path class="wcai-spark wcai-spark--lg" d="M11.3 6.6c.38 2.02.98 2.62 3 3-2.02.38-2.62.98-3 3-.38-2.02-.98-2.62-3-3 2.02-.38 2.62-.98 3-3z" fill="currentColor" stroke="none"/>' +
			'<path class="wcai-spark wcai-spark--sm" d="M16.1 10.8c.22 1.18.57 1.53 1.75 1.75-1.18.22-1.53.57-1.75 1.75-.22-1.18-.57-1.53-1.75-1.75 1.18-.22 1.53-.57 1.75-1.75z" fill="currentColor" stroke="none"/>',
		close:
			'<path d="M17.5 6.5 6.5 17.5M6.5 6.5l11 11"/>',
		send:
			'<path d="M21 3 10.5 13.5"/>' +
			'<path d="M21 3 14.3 21.5 10.5 13.5 2.5 9.7z"/>',
		spark:
			'<path d="M12 3.5 13.7 9l5.5 1.7-5.5 1.7L12 18l-1.7-5.6L4.8 10.7 10.3 9z"/>',
		refresh:
			'<path d="M20 12a8 8 0 1 1-2.3-5.6"/>' +
			'<path d="M20 4v4.5h-4.5"/>'
	};

	function svg(name, size) {
		return '<svg class="wcai-i" viewBox="0 0 24 24" width="' + size + '" height="' + size +
			'" fill="none" stroke="currentColor" stroke-width="1.75" ' +
			'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
			ICONS[name] + '</svg>';
	}

	/* ------------------------------------------------------------ rendering */

	function escapeHtml(text) {
		return String(text)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	/**
	 * Escape first, then apply a deliberately small Markdown subset, so nothing
	 * the model emits can inject markup.
	 */
	function renderMarkdown(text) {
		var html = escapeHtml(text);

		html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g,
			'<a href="$2" target="_blank" rel="noopener">$1</a>');
		html = html.replace(/(^|\s)(https?:\/\/[^\s<]+)/g,
			'$1<a href="$2" target="_blank" rel="noopener">$2</a>');
		html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
		html = html.replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
		html = html.replace(/^\s*[-*]\s+(.+)$/gm, '<li>$1</li>');
		html = html.replace(/(<li>[\s\S]*?<\/li>)(?!\s*<li>)/g, '<ul>$1</ul>');
		html = html.replace(/\n{2,}/g, '</p><p>').replace(/\n/g, '<br>');

		return '<p>' + html + '</p>';
	}

	/* ----------------------------------------------------------------- mount */

	var host = document.createElement('div');
	host.id = 'wcai-root';
	document.body.appendChild(host);

	var root = host.attachShadow({ mode: 'open' });

	var link = document.createElement('link');
	link.rel = 'stylesheet';
	link.href = cfg.styles;
	root.appendChild(link);

	/* ------------------------------------------------------------- launcher */

	/**
	 * The launcher is configured from the admin screen: a glyph, a label, or
	 * both. Whichever parts are switched off simply are not rendered, so the
	 * button collapses to a circle or stretches to a pill on its own.
	 */
	var lc = cfg.launcher || {};
	var lcStyle = 'text' === lc.style || 'both' === lc.style ? lc.style : 'icon';
	var lcText = String(lc.text || 'Ask AI');

	function launcherGlyph() {
		if (lc.imageUrl) {
			return '<img class="wcai-launcher-img" src="' + escapeHtml(lc.imageUrl) + '" alt="" width="24" height="24">';
		}
		return lc.icon || svg('chat', 24);
	}

	// When the button carries a label, the accessible name has to contain that
	// label — a screen reader announcing something the wording doesn't match
	// breaks voice control (WCAG "Label in Name").
	var openLabel = 'icon' === lcStyle ? 'Open AI assistant chat' : lcText;

	var launcherInner =
		'  <span class="wcai-launcher-glyphs">' +
		('text' === lcStyle ? '' : '<span class="wcai-launcher-icon wcai-launcher-icon--chat">' + launcherGlyph() + '</span>') +
		'    <span class="wcai-launcher-icon wcai-launcher-icon--close">' + svg('close', 24) + '</span>' +
		'  </span>' +
		('icon' === lcStyle ? '' : '<span class="wcai-launcher-label">' + escapeHtml(lcText) + '</span>');

	var lcOffset = parseInt(lc.offset, 10);
	lcOffset = isNaN(lcOffset) ? 24 : Math.max(0, lcOffset);

	var wrap = document.createElement('div');
	wrap.className = 'wcai wcai--' + lcStyle + ('left' === lc.side ? ' wcai--left' : '');
	wrap.style.setProperty('--wcai-brand', cfg.accent || '#5A2612');
	wrap.style.setProperty('--wcai-offset-bottom', lcOffset + 'px');
	wrap.innerHTML = [
		'<div class="wcai-panel" role="dialog" aria-modal="false" aria-label="Chat with us" hidden>',
		'  <header class="wcai-header">',
		'    <span class="wcai-avatar wcai-avatar--header">' + svg('spark', 18) + '</span>',
		'    <span class="wcai-identity">',
		'      <span class="wcai-name"></span>',
		'      <span class="wcai-status"><i class="wcai-pip"></i><span class="wcai-status-text"></span></span>',
		'    </span>',
		'    <button class="wcai-iconbtn wcai-reset" type="button" aria-label="Start a new conversation" title="New conversation">' + svg('refresh', 18) + '</button>',
		'    <button class="wcai-iconbtn wcai-close" type="button" aria-label="Close chat">' + svg('close', 18) + '</button>',
		'  </header>',
		'  <div class="wcai-log" role="log" aria-live="polite"></div>',
		'  <form class="wcai-composer">',
		'    <input class="wcai-input" type="text" autocomplete="off" aria-label="Your message">',
		'    <button class="wcai-send" type="submit" aria-label="Send message" disabled>' + svg('send', 18) + '</button>',
		'  </form>',
		'  <p class="wcai-footnote"></p>',
		'</div>',
		'<button class="wcai-launcher" type="button" aria-label="' + escapeHtml(openLabel) + '" aria-expanded="false">',
		launcherInner,
		'</button>'
	].join('');
	root.appendChild(wrap);

	var launcher = wrap.querySelector('.wcai-launcher');
	var panel = wrap.querySelector('.wcai-panel');
	var log = wrap.querySelector('.wcai-log');
	var form = wrap.querySelector('.wcai-composer');
	var input = wrap.querySelector('.wcai-input');
	var sendBtn = wrap.querySelector('.wcai-send');
	var closeBtn = wrap.querySelector('.wcai-close');
	var resetBtn = wrap.querySelector('.wcai-reset');

	/* All visible copy comes from the plugin settings, so the storefront can be
	   rebranded — or translated — without touching this file. */
	var statusWrap = wrap.querySelector('.wcai-status');
	var footnote = wrap.querySelector('.wcai-footnote');

	wrap.querySelector('.wcai-name').textContent = cfg.name || 'Assistant';
	input.placeholder = cfg.placeholder || '';

	if (cfg.status) {
		wrap.querySelector('.wcai-status-text').textContent = cfg.status;
	} else {
		statusWrap.remove();
	}

	if (cfg.footnote) {
		footnote.textContent = cfg.footnote;
	} else {
		footnote.remove();
	}

	function addMessage(role, text, isHtml) {
		var row = document.createElement('div');
		row.className = 'wcai-row wcai-row--' + role;

		if (role === 'assistant') {
			var avatar = document.createElement('span');
			avatar.className = 'wcai-avatar wcai-avatar--msg';
			avatar.innerHTML = svg('spark', 14);
			row.appendChild(avatar);
		}

		var bubble = document.createElement('div');
		bubble.className = 'wcai-msg';

		if (isHtml) {
			bubble.innerHTML = renderMarkdown(text);
		} else {
			bubble.textContent = text;
		}

		row.appendChild(bubble);
		log.appendChild(row);
		log.scrollTop = log.scrollHeight;

		return row;
	}

	function addTyping() {
		var row = document.createElement('div');
		row.className = 'wcai-row wcai-row--assistant';
		row.innerHTML =
			'<span class="wcai-avatar wcai-avatar--msg">' + svg('spark', 14) + '</span>' +
			'<div class="wcai-msg wcai-typing"><span></span><span></span><span></span></div>';
		log.appendChild(row);
		log.scrollTop = log.scrollHeight;
		return row;
	}

	function paintHistory() {
		log.innerHTML = '';

		if (!history.length) {
			addMessage('assistant', cfg.greeting || 'Hi! How can I help?', true);
			return;
		}

		history.forEach(function (turn) {
			addMessage(turn.role, turn.content, turn.role === 'assistant');
		});
	}

	function setOpen(open) {
		panel.hidden = !open;
		wrap.classList.toggle('is-open', open);
		launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
		launcher.setAttribute('aria-label', open ? 'Close chat' : openLabel);

		if (open) {
			input.focus();
			log.scrollTop = log.scrollHeight;
		}
	}

	launcher.addEventListener('click', function () {
		setOpen(panel.hidden);
	});

	closeBtn.addEventListener('click', function () {
		setOpen(false);
		launcher.focus();
	});

	resetBtn.addEventListener('click', function () {
		history = [];
		persist();
		paintHistory();
		input.focus();
	});

	input.addEventListener('input', function () {
		sendBtn.disabled = input.value.trim() === '' || busy;
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && !panel.hidden) {
			setOpen(false);
			launcher.focus();
		}
	});

	form.addEventListener('submit', function (event) {
		event.preventDefault();

		var text = input.value.trim();
		if (!text || busy) {
			return;
		}

		busy = true;
		sendBtn.disabled = true;
		input.value = '';
		addMessage('user', text, false);

		var typing = addTyping();

		fetch(cfg.endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				message: text,
				history: history.slice(-MAX_TURNS),
				page: window.location.href
			})
		})
			.then(function (response) {
				return response.json().then(function (data) {
					return { ok: response.ok, data: data };
				});
			})
			.then(function (result) {
				typing.remove();

				if (!result.ok) {
					addMessage('assistant', (result.data && result.data.message) || 'Something went wrong. Please try again.', false);
					return;
				}

				var reply = result.data.reply || '';
				addMessage('assistant', reply, true);

				history.push({ role: 'user', content: text });
				history.push({ role: 'assistant', content: reply });
				history = history.slice(-MAX_TURNS);
				persist();
			})
			.catch(function () {
				typing.remove();
				addMessage('assistant', cfg.errorText || 'Sorry, something went wrong. Please try again.', false);
			})
			.finally(function () {
				busy = false;
				sendBtn.disabled = input.value.trim() === '';
				input.focus();
			});
	});

	paintHistory();
})();
