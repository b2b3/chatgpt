(function ($) {
	'use strict';

	const state = {
		rows: [],
		sortKey: 'title',
		sortDirection: 'asc',
	};

	function escapeHtml(str) {
		return String(str || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function setMessage(message, type = 'info') {
		const $message = $('#mse-message');
		$message.removeClass('mse-error mse-success mse-info').addClass(`mse-${type}`).text(message);
	}

	function fillSelect($select, items, valueKey, labelKey) {
		items.forEach((item) => {
			$select.append(`<option value="${escapeHtml(item[valueKey])}">${escapeHtml(item[labelKey])}</option>`);
		});
	}

	function initFilters() {
		fillSelect($('#mse-filter-post-type'), MSEAdmin.postTypes, 'value', 'label');
		fillSelect($('#mse-filter-category'), MSEAdmin.categories, 'id', 'name');
		fillSelect($('#mse-filter-tag'), MSEAdmin.tags, 'id', 'name');
		fillSelect($('#mse-filter-status'), MSEAdmin.statuses, 'value', 'label');
	}

	function getFilters() {
		return {
			post_type: $('#mse-filter-post-type').val(),
			category: $('#mse-filter-category').val(),
			tag: $('#mse-filter-tag').val(),
			status: $('#mse-filter-status').val(),
		};
	}

	function sortRows(rows) {
		const sorted = [...rows].sort((a, b) => {
			const x = (a[state.sortKey] || '').toString().toLowerCase();
			const y = (b[state.sortKey] || '').toString().toLowerCase();
			if (x < y) return state.sortDirection === 'asc' ? -1 : 1;
			if (x > y) return state.sortDirection === 'asc' ? 1 : -1;
			return 0;
		});
		return sorted;
	}

	function renderTable() {
		const $tbody = $('#mse-table tbody');
		$tbody.empty();

		const rows = sortRows(state.rows);
		if (!rows.length) {
			$tbody.append(`<tr><td colspan="9">${escapeHtml(MSEAdmin.i18n.empty)}</td></tr>`);
			return;
		}

		rows.forEach((row) => {
			const categoryNames = (row.categories || []).map((c) => c.name).join(', ');
			const categoryIds = (row.categories || []).map((c) => c.id).join('|');
			const h1Class = row.h1 ? '' : 'mse-warning';
			const descLen = (row.meta_description || '').length;
			const descClass = descLen > 160 || (descLen > 0 && descLen < 70) ? 'mse-warning' : '';

			$tbody.append(`
				<tr data-id="${row.id}">
					<td><input type="text" class="mse-field" data-field="title" value="${escapeHtml(row.title)}" /></td>
					<td><input type="text" class="mse-field" data-field="slug" value="${escapeHtml(row.slug)}" /></td>
					<td><input type="text" class="mse-field ${h1Class}" data-field="h1" value="${escapeHtml(row.h1)}" /></td>
					<td><input type="text" class="mse-field" data-field="meta_title" value="${escapeHtml(row.meta_title)}" /></td>
					<td><textarea class="mse-field ${descClass}" data-field="meta_description">${escapeHtml(row.meta_description)}</textarea></td>
					<td><input type="text" class="mse-field" data-field="tags" value="${escapeHtml(row.tags)}" /></td>
					<td>
						<input type="text" class="mse-field" data-field="categories_label" value="${escapeHtml(categoryNames)}" />
						<input type="hidden" class="mse-field" data-field="categories" value="${escapeHtml(categoryIds)}" />
					</td>
					<td>${escapeHtml(row.post_type)}</td>
					<td>${escapeHtml(row.status)}</td>
				</tr>
			`);
		});
	}

	function fetchRows() {
		setMessage(MSEAdmin.i18n.loading, 'info');
		const data = {
			action: 'mse_get_rows',
			nonce: MSEAdmin.nonce,
			...getFilters(),
		};

		$.post(MSEAdmin.ajaxUrl, data)
			.done((response) => {
				if (!response.success) {
					setMessage(response?.data?.message || MSEAdmin.i18n.saveError, 'error');
					return;
				}
				state.rows = response.data.rows;
				renderTable();
				setMessage(`${state.rows.length} elementos cargados.`, 'success');
			})
			.fail(() => setMessage(MSEAdmin.i18n.saveError, 'error'));
	}

	function collectRows() {
		const rows = [];
		$('#mse-table tbody tr').each(function () {
			const $row = $(this);
			const id = parseInt($row.attr('data-id'), 10);
			const rowData = { id };

			$row.find('.mse-field').each(function () {
				const $field = $(this);
				rowData[$field.data('field')] = $field.val();
			});

			rowData.categories = String(rowData.categories || '')
				.split('|')
				.map((idValue) => parseInt(idValue, 10))
				.filter((value) => !Number.isNaN(value));
			delete rowData.categories_label;

			rows.push(rowData);
		});
		return rows;
	}

	function saveBulk() {
		const items = collectRows();
		setMessage(MSEAdmin.i18n.loading, 'info');

		$.post(MSEAdmin.ajaxUrl, {
			action: 'mse_save_bulk',
			nonce: MSEAdmin.nonce,
			items: JSON.stringify(items),
		})
			.done((response) => {
				if (!response.success) {
					const warnings = response?.data?.errors ? ` ${response.data.errors.join(' | ')}` : '';
					setMessage((response?.data?.message || MSEAdmin.i18n.saveError) + warnings, 'error');
					return;
				}
				setMessage(response?.data?.message || MSEAdmin.i18n.saved, 'success');
				fetchRows();
			})
			.fail(() => setMessage(MSEAdmin.i18n.saveError, 'error'));
	}

	$(document).ready(() => {
		initFilters();
		fetchRows();

		$('#mse-apply-filters').on('click', (event) => {
			event.preventDefault();
			fetchRows();
		});

		$('#mse-save-all').on('click', (event) => {
			event.preventDefault();
			saveBulk();
		});

		$('#mse-table thead th[data-sort]').on('click', function () {
			const key = $(this).data('sort');
			if (state.sortKey === key) {
				state.sortDirection = state.sortDirection === 'asc' ? 'desc' : 'asc';
			} else {
				state.sortKey = key;
				state.sortDirection = 'asc';
			}
			renderTable();
		});
	});
})(jQuery);
