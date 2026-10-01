(function () {
	'use strict';
	document.querySelectorAll('.zad-repeater').forEach(function (box) {
		var name = box.dataset.name, a = box.dataset.a, b = box.dataset.b;
		function reindex() {
			box.querySelectorAll('.zad-row').forEach(function (row, i) {
				row.querySelector('input').name = 'zad[' + name + '][' + i + '][' + a + ']';
				row.querySelector('textarea').name = 'zad[' + name + '][' + i + '][' + b + ']';
			});
		}
		box.addEventListener('click', function (e) {
			if (e.target.classList.contains('zad-del')) {
				e.target.closest('.zad-row').remove();
				reindex();
			}
			if (e.target.classList.contains('zad-add')) {
				var row = document.createElement('div');
				row.className = 'zad-row';
				row.innerHTML = '<input type="text" placeholder="' + box.dataset.pa + '"><textarea rows="2" placeholder="' + box.dataset.pb + '"></textarea><button type="button" class="button zad-del">حذف</button>';
				box.insertBefore(row, e.target);
				reindex();
			}
		});
	});

	var btn = document.getElementById('zad_gallery_btn');
	if (btn && window.wp && wp.media) {
		var frame;
		btn.addEventListener('click', function () {
			if (!frame) {
				frame = wp.media({ title: 'معرض الخدمة', multiple: 'add', library: { type: 'image' } });
				frame.on('select', function () {
					var ids = [], prev = document.getElementById('zad_gallery_prev');
					prev.innerHTML = '';
					frame.state().get('selection').each(function (att) {
						ids.push(att.id);
						var s = att.attributes.sizes, u = (s && s.thumbnail) ? s.thumbnail.url : att.attributes.url;
						var img = document.createElement('img');
						img.src = u; img.width = 60; img.height = 60;
						prev.appendChild(img);
					});
					document.getElementById('zad_gallery').value = ids.join(',');
				});
			}
			frame.open();
		});
	}
})();
