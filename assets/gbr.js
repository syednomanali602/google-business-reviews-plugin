/* Google Business Reviews — carousel, read more, avatar fallback. No dependencies. */
(function () {
	'use strict';

	function swapAvatar(img) {
		var span = document.createElement('span');
		span.className = 'd360-gbr__avatar d360-gbr__avatar--initial';
		span.setAttribute('aria-hidden', 'true');
		span.style.setProperty('--gbr-initial-bg', img.getAttribute('data-bg') || '#5f6368');
		span.textContent = img.getAttribute('data-initial') || '?';
		img.replaceWith(span);
	}

	function init(root) {
		if (root.getAttribute('data-gbr-ready')) return;
		root.setAttribute('data-gbr-ready', '1');

		var track = root.querySelector('.d360-gbr__track');
		var prev = root.querySelector('.d360-gbr__nav--prev');
		var next = root.querySelector('.d360-gbr__nav--next');
		if (!track) return;

		var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		function updateNav() {
			var max = track.scrollWidth - track.clientWidth - 2;
			// Arrow disappears at each end (and both hide when everything fits).
			if (prev) prev.hidden = track.scrollLeft <= 2;
			if (next) next.hidden = max <= 0 || track.scrollLeft >= max;
		}

		function updateClamps() {
			root.querySelectorAll('.d360-gbr__text').forEach(function (p) {
				var btn = p.nextElementSibling;
				if (!btn || !btn.classList.contains('d360-gbr__more') || p.classList.contains('is-open')) return;
				btn.hidden = p.scrollHeight <= p.clientHeight + 1;
			});
		}

		function page(dir) {
			track.scrollBy({ left: dir * track.clientWidth, behavior: reduced ? 'auto' : 'smooth' });
		}

		if (prev) prev.addEventListener('click', function () { page(-1); });
		if (next) next.addEventListener('click', function () { page(1); });

		var ticking = false;
		track.addEventListener('scroll', function () {
			if (ticking) return;
			ticking = true;
			requestAnimationFrame(function () { updateNav(); ticking = false; });
		}, { passive: true });

		root.addEventListener('click', function (e) {
			var btn = e.target.closest('.d360-gbr__more');
			if (!btn) return;
			var p = btn.previousElementSibling;
			var open = p.classList.toggle('is-open');
			btn.textContent = open ? btn.getAttribute('data-less') : btn.getAttribute('data-more');
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		});

		root.querySelectorAll('img.d360-gbr__avatar').forEach(function (img) {
			if (img.complete && img.naturalWidth === 0) { swapAvatar(img); return; }
			img.addEventListener('error', function () { swapAvatar(img); }, { once: true });
		});

		function refresh() { updateNav(); updateClamps(); }

		if ('ResizeObserver' in window) {
			new ResizeObserver(refresh).observe(track);
		} else {
			window.addEventListener('resize', refresh);
		}
		if (document.fonts && document.fonts.ready) document.fonts.ready.then(refresh);
		refresh();
	}

	function boot() {
		document.querySelectorAll('[data-d360-gbr]').forEach(init);
	}

	// For widgets injected later (popups, AJAX tabs): window.d360GbrInit()
	window.d360GbrInit = boot;

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
