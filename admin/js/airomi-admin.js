(function () {
	'use strict';

	var root = typeof window !== 'undefined' ? window : this;
	var airomi = root.airomiAdmin || {};
	root.airomiAdmin = airomi;

	function getNonce() {
		return airomi.nonce || '';
	}

	function getAjaxUrl() {
		return airomi.ajaxUrl || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
	}

	// ----- Batch init orders -----
	function initOrdersBatch() {
		var btn = document.getElementById('airomi-init-orders-btn');
		var progressWrap = document.getElementById('airomi-init-progress');
		var progressFill = progressWrap && progressWrap.querySelector('.airomi-progress-fill');
		var progressText = progressWrap && progressWrap.querySelector('.airomi-progress-text');
		if (!btn || !progressWrap) return;

		btn.addEventListener('click', function () {
			btn.disabled = true;
			progressWrap.style.display = 'block';
			if (progressFill) progressFill.style.width = '0%';
			if (progressText) progressText.textContent = '';

			var totalSoFar = 0;
			var total = 0;
			var page = 1;

			function runBatch() {
				var formData = new FormData();
				formData.append('action', 'airomi_init_orders');
				formData.append('nonce', getNonce());
				formData.append('page', String(page));

				fetch(getAjaxUrl(), {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				})
					.then(function (r) { return r.json(); })
					.then(function (data) {
						if (data.success && data.data) {
							totalSoFar += data.data.processed || 0;
							if (total === 0 && data.data.total != null) total = data.data.total;
							if (data.data.total != null) total = data.data.total;
							var pct = total > 0 ? Math.min(100, Math.round((totalSoFar / total) * 100)) : 0;
							if (progressFill) progressFill.style.width = pct + '%';
							if (progressText) progressText.textContent = totalSoFar + ' / ' + total + ' processed';

							if (data.data.done) {
								if (progressFill) progressFill.style.width = '100%';
								if (progressText) progressText.textContent = 'Done. Reloading…';
								window.location.reload();
								return;
							}
							page = data.data.next_page || (page + 1);
							runBatch();
						} else {
							btn.disabled = false;
							if (progressText) progressText.textContent = data.data && data.data.message ? data.data.message : 'Error';
						}
					})
					.catch(function () {
						btn.disabled = false;
						if (progressText) progressText.textContent = 'Request failed';
					});
			}
			runBatch();
		});
	}

	function formatJson(str) {
		if (str === null || str === undefined || str === '') return '';
		if (typeof str !== 'string') return JSON.stringify(str, null, 2);
		try {
			var parsed = JSON.parse(str);
			return JSON.stringify(parsed, null, 2);
		} catch (e) {
			return str;
		}
	}

	function escapeHtml(s) {
		if (s == null) return '';
		s = String(s);
		return s
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function openModal(orderId) {
		var formData = new FormData();
		formData.append('action', 'airomi_get_order_detail');
		formData.append('nonce', getNonce());
		formData.append('order_id', String(orderId));

		fetch(getAjaxUrl(), {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.success || !data.data) {
					alert(data.data && data.data.message ? data.data.message : 'Failed to load details');
					return;
				}
				var d = data.data;
				var payloadStr = formatJson(d.payload);
				var responseStr = formatJson(d.response_body);

				var overlay = document.createElement('div');
				overlay.className = 'airomi-modal-overlay';
				overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:100000;display:flex;align-items:center;justify-content:center;';
				var box = document.createElement('div');
				box.className = 'airomi-modal-box';
				box.style.cssText = 'background:#fff;max-width:90%;max-height:90vh;width:700px;border-radius:4px;box-shadow:0 4px 20px rgba(0,0,0,0.2);display:flex;flex-direction:column;overflow:hidden;';
				box.innerHTML =
					'<div style="padding:12px 16px;border-bottom:1px solid #c3c4c7;display:flex;justify-content:space-between;align-items:center;">' +
					'<strong>Order #' + escapeHtml(orderId) + ' – Request &amp; Response</strong>' +
					'<button type="button" class="button airomi-modal-close">Close</button>' +
					'</div>' +
					'<div style="padding:16px;overflow:auto;flex:1;">' +
					'<p style="margin:0 0 8px;font-weight:600;">Request payload</p>' +
					'<pre class="airomi-modal-payload" style="background:#f6f7f7;padding:12px;border:1px solid #c3c4c7;border-radius:4px;overflow:auto;max-height:280px;font-size:12px;white-space:pre-wrap;word-break:break-all;">' + escapeHtml(payloadStr || '—') + '</pre>' +
					'<p style="margin:16px 0 8px;font-weight:600;">Response body</p>' +
					'<pre class="airomi-modal-response" style="background:#f6f7f7;padding:12px;border:1px solid #c3c4c7;border-radius:4px;overflow:auto;max-height:280px;font-size:12px;white-space:pre-wrap;word-break:break-all;">' + escapeHtml(responseStr || '—') + '</pre>' +
					'</div>';

				overlay.appendChild(box);
				document.body.appendChild(overlay);

				function close() {
					document.body.removeChild(overlay);
				}
				box.querySelector('.airomi-modal-close').addEventListener('click', close);
				overlay.addEventListener('click', function (e) {
					if (e.target === overlay) close();
				});
			})
			.catch(function () {
				alert('Request failed');
			});
	}

	function bindViewDetails() {
		document.addEventListener('click', function (e) {
			var btn = e.target && e.target.closest && e.target.closest('.airomi-view-details');
			if (!btn) return;
			e.preventDefault();
			var orderId = btn.getAttribute && btn.getAttribute('data-order-id');
			if (orderId) openModal(parseInt(orderId, 10));
		});
	}

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}
	ready(function () {
		initOrdersBatch();
		bindViewDetails();
	});
})();
